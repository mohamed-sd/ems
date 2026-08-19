<?php
/**
 * Finance/entitlement_gate.php — بوابةُ الاستحقاق المالي (★ المالية · update0007 S-07)
 * ───────────────────────────────────────────────────────────────────────────
 * «الماليةُ بوابةٌ قبل الترحيل المالي — لا شرطٌ لإثبات الواقعة» (CAP-01 §12).
 * تعرض الأثرَ الأوليَّ المنتظرَ (fin_dues بحالة الاقتراح) وتُمرّره بوابةً:
 * الاعتمادُ عبر UnitJourneyService::postEntitlement — لا كتابةَ مباشرة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Policy/UnitJourneyService.php';

// CS-01 — الحارسُ فوقَ المعالج.
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

/* ══ FN-03 — «شاشةُ فحصِ شروطِ الاستحقاقِ بلا معالجٍ إطلاقًا» ══════════════
   كان رأسُ الملفِّ يعلن «الاعتمادُ عبر خدمةِ الرحلة — لا كتابةَ مباشرة» ولا
   يوجد فيه معالجٌ ولا نداءٌ للخدمة، وعمودُ الإجراءِ يحمل رابطًا واحدًا يُحيل
   إلى شاشةٍ أخرى. و«رأسٌ يعلن ما لا يفعله أسوأُ من شاشةٍ فارغة — فالفارغةُ
   تُرى والكاذبةُ تُصدَّق».
   الآن: **فعلان محروسان** بالعقدِ السبعيِّ ينادِيان الخدمةَ المالكة:
     ① اعتمادُ الاستحقاق (يمرُّ بـUnitJourneyService::postEntitlement)
     ② ردُّه بسببٍ محكومٍ من قائمةٍ مغلقة — لا نصٍّ حر. */
$REJECT_REASONS = array(
    'no_evidence'      => 'بلا دليلٍ يُثبت الواقعة',
    'wrong_period'     => 'الفترةُ خاطئةٌ أو مقفلة',
    'duplicate'        => 'استحقاقٌ مكرَّرٌ لواقعةٍ واحدة',
    'quantity_dispute' => 'الكميةُ محلُّ خلافٍ مع الميدان',
    'contract_mismatch' => 'لا يطابق شروطَ العقد',
);

$__pcApprove = ems_post_contract($conn, array(
    'action'  => 'fin.entitlement.approve',
    'perm'    => 'can_edit',
    'trigger' => 'approve_pe',
    'idem'    => array('pe' => intval($_POST['approve_pe'] ?? 0)),
    'validate' => function (array $in) {
        $pe = intval($in['approve_pe'] ?? 0);
        $dm = intval($in['dept_manager_id'] ?? 0);
        $fm = intval($in['finance_manager_id'] ?? 0);
        if ($pe <= 0) { return array('ok' => false, 'msg' => 'أثرٌ ماليٌّ غيرُ صالح (422)'); }
        if ($dm <= 0 || $fm <= 0) {
            return array('ok' => false, 'msg' => 'لا استحقاقَ بلا اعتمادِ مديرِ الإدارةِ والماليةِ معًا (403)');
        }
        if ($dm === $fm) { return array('ok' => false, 'msg' => 'الاعتمادان من شخصٍ واحد — استقلالُ الموافقاتِ شرطُ صحتها (403)'); }
        return array('ok' => true, 'data' => array('pe' => $pe, 'dm' => $dm, 'fm' => $fm));
    },
));
if (!$__pcApprove['ok'] && $__pcApprove['msg'] !== '') { $msg = $__pcApprove['msg']; }
if ($__pcApprove['replay'])                            { $msg = $__pcApprove['msg']; }
if ($__pcApprove['run'] && $__pcApprove['ok']) {
    $res = \App\Services\Policy\UnitJourneyService::postEntitlement(
        $conn, $company_id, (int) $__pcApprove['data']['pe'],
        (int) $__pcApprove['data']['dm'], (int) $__pcApprove['data']['fm'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcApprove['idem'], $__pcApprove['code'], 'unit_effects#' . (int) $__pcApprove['data']['pe']); }
}

$__pcReject = ems_post_contract($conn, array(
    'action'  => 'fin.entitlement.reject',
    'perm'    => 'can_edit',
    'trigger' => 'reject_pe',
    'idem'    => array('pe' => intval($_POST['reject_pe'] ?? 0), 'why' => (string) ($_POST['reason_code'] ?? '')),
    'validate' => function (array $in) use ($REJECT_REASONS) {
        $pe = intval($in['reject_pe'] ?? 0);
        $rc = (string) ($in['reason_code'] ?? '');
        if ($pe <= 0) { return array('ok' => false, 'msg' => 'أثرٌ ماليٌّ غيرُ صالح (422)'); }
        // ◆ السببُ **محكومٌ** من قائمةٍ مغلقة — لا نصٌّ حرٌّ يُفسد التصنيف.
        if (!isset($REJECT_REASONS[$rc])) { return array('ok' => false, 'msg' => 'سببُ الردِّ غيرُ محكوم — اختر من القائمة (422)'); }
        return array('ok' => true, 'data' => array('pe' => $pe, 'rc' => $rc));
    },
));
if (!$__pcReject['ok'] && $__pcReject['msg'] !== '') { $msg = $__pcReject['msg']; }
if ($__pcReject['replay'])                           { $msg = $__pcReject['msg']; }
if ($__pcReject['run'] && $__pcReject['ok']) {
    $res = \App\Services\Policy\UnitJourneyService::rejectEntitlement(
        $conn, $company_id, (int) $__pcReject['data']['pe'],
        (string) $__pcReject['data']['rc'], (string) $REJECT_REASONS[$__pcReject['data']['rc']], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcReject['idem'], $__pcReject['code'], 'unit_effects#' . (int) $__pcReject['data']['pe']); }
}

/* ══ INJ-0037 — الطابورُ من الخدمةِ المالكةِ لا من استعلامٍ في الشاشة ══════
     كانت الشاشةُ تسأل عن `due_no`/`party_kind`/`beneficiary_ref`/`state`/
     `source_kind` — **وخمستُها لا وجودَ لها في `fin_dues`**. فيرسب الاستعلامُ،
     ويبتلع `if ($res)` رسوبَه، فتُعلن الشاشةُ «لا أثرَ ينتظر» وفي القاعدةِ
     **عشرون صفًّا معلَّقًا**. والآنَ الحكمُ في `gateQueue`، و**الرسوبُ يظهر
     برمزٍ أحمرَ لا يلبس ثوبَ الخلوّ**. */
$queue     = \App\Services\Policy\UnitJourneyService::gateQueue($conn, $company_id);
$rows      = $queue['rows'];
$queueFail = !$queue['ok'] ? $queue['error'] : '';

$page_title = 'بوابة الاستحقاق المالي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-door-closed';
$header_title_html = htmlspecialchars('بوابةُ الاستحقاق المالي', ENT_QUOTES, 'UTF-8');
ob_start(); ?><span class="badge eg-badge-pending">بانتظار البوابة: <?= $queueFail === '' ? count($rows) : '—' ?></span><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الثلاثُ من المكوّنِ المركزيّ
echo ems_states_bundle('لا أثرَ أوليًّا ينتظر البوابة', 'ما يكتمل من سلاسلِ الاستحقاقِ يظهر هنا فورَ اقتراحِ أثرِه');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <p class="text-muted eg-note">الأثرُ الأوليُّ ينتظر اعتمادَ مدير الإدارة + المالية — ولا يصير Posted قبلهما (POL-01).</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <!-- INJ-0037 · «صفرٌ لأنَّ الاستعلامَ رسب» لا يُعرض أبدًا كـ«صفرٌ لأنَّ لا عمل» -->
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>رقم المحضر</th><th>الطرف</th><th>المبلغ</th><th>المصدر</th><th>الحالة</th><th>منذ</th><th>إجراء</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">العقد</th>
              <th class="ems-fn-th" data-fn="1">الوحدة</th>
              <th class="ems-fn-th" data-fn="1">الواقعة المرجعية</th>
              <th class="ems-fn-th" data-fn="1">حكم العميل</th>
              <th class="ems-fn-th" data-fn="1">قيمة إيراد العميل</th>
              <th class="ems-fn-th" data-fn="1">حكم المورد</th>
              <th class="ems-fn-th" data-fn="1">قيمة استحقاق المورد</th>
              <th class="ems-fn-th" data-fn="1">حكم المشغّل</th>
              <th class="ems-fn-th" data-fn="1">قيمة أجر المشغّل</th>
              <th class="ems-fn-th" data-fn="1">تاريخ اكتمال السلسلة</th>
              <th class="ems-fn-th" data-fn="1">رقم الحدث المولَّد</th>
              <th class="ems-fn-th" data-fn="1">رقم القيد</th>
              <th class="ems-fn-th" data-fn="1">ولّده</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th none" data-fn="1">رقم البوابة</th>
              <th class="ems-fn-th none" data-fn="1">سلسلة الاعتماد مكتملة؟</th>
              <th class="ems-fn-th none" data-fn="1">الفترة المحاسبية مفتوحة؟</th>
              <th class="ems-fn-th none" data-fn="1">العقد نافذ؟</th>
              <th class="ems-fn-th none" data-fn="1">الحصة متاحة؟</th>
              <th class="ems-fn-th none" data-fn="1">نتيجة الفحص</th>
              <th class="ems-fn-th none" data-fn="1">سبب الرد</th>
              <th class="ems-fn-th none" data-fn="1">قيمة الأثر</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted">لا أثرَ أوليًّا ينتظر البوابة</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <!-- INJ-0037 · الأعمدةُ من الجدولِ الحقيقيّ: لا `due_no` ولا `party_kind`
             ولا `beneficiary_ref` ولا `state` ولا `source_kind` في `fin_dues`. -->
        <td>#<?= (int) $r['id'] ?><?= $r['period_ref'] !== '' ? ' · ' . htmlspecialchars((string) $r['period_ref'], ENT_QUOTES, 'UTF-8') : '' ?></td>
        <td><?= htmlspecialchars($r['party_type'] . ' / ' . $r['party_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format(floatval($r['amount']), 2) ?> <?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['source_doc_type'] . ((int) $r['source_doc_id'] ? ' #' . (int) $r['source_doc_id'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['due_type'] . ' · ' . $r['settlement_state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr((string) $r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php $eid = intval($r['pe_id']); ?>
          <?php if ($eid <= 0): ?>
            <!-- ◆ لا يُعرض زرٌّ يعلم النظامُ سلفًا أنَّه سيردُّ ٤٠٤: الفعلُ يقع على
                 الأثرِ الماليِّ المقترح (`unit_effects.pe_id`) لا على الاستحقاق. -->
            <span class="badge eg-badge-blocked" title="<?= htmlspecialchars((string) $r['blocked'], ENT_QUOTES, 'UTF-8') ?>">غيرُ قابلٍ للاعتمادِ بعد</span>
            <div class="text-muted eg-blocked-note"><?= htmlspecialchars((string) $r['blocked'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php else: ?>
          <!-- FN-03 · FIXC-0017: فعلان محروسان بالعقدِ السبعيّ — لا رابطٌ يُحيل
               إلى شاشةٍ أخرى ثم يُقال «الاعتمادُ عبر الخدمة». -->
          <form method="post" class="eg-action-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="approve_pe" value="<?= $eid ?>">
            <label class="visually-hidden" for="eg_dm_<?= $eid ?>">مدير الإدارة المعتمِد</label>
            <input type="number" name="dept_manager_id" required min="1"
                   class="form-control form-control-sm eg-w110" placeholder="مدير الإدارة" id="eg_dm_<?= $eid ?>">
            <label class="visually-hidden" for="eg_fm_<?= $eid ?>">المالية المعتمِدة</label>
            <input type="number" name="finance_manager_id" required min="1"
                   class="form-control form-control-sm eg-w100" placeholder="المالية" id="eg_fm_<?= $eid ?>">
            <button class="action-btn" type="submit"><i class="fa fa-check"></i> اعتمد</button>
          </form>
          <form method="post" class="eg-action-form eg-mt4">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="reject_pe" value="<?= $eid ?>">
            <label class="visually-hidden" for="eg_rc_<?= $eid ?>">سببُ الرد</label>
            <select aria-label="سببُ الردِّ المحكوم" name="reason_code" required class="form-control form-control-sm eg-w190" id="eg_rc_<?= $eid ?>">
              <option value="">— سببُ الردِّ المحكوم —</option>
              <?php foreach ($REJECT_REASONS as $k => $v): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <button class="action-btn" type="submit"><i class="fa fa-rotate-left"></i> ردّ</button>
          </form>
          <?php endif; ?>
          <a class="action-btn eg-inbox-link" href="../Finance/approvals_inbox.php">صندوق الاعتماد ←</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
