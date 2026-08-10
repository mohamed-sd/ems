<?php
/**
 * Procurement/rfq_compare_award.php — مقارنةُ العروض والترسية (★ المشتريات · S-10)
 * ───────────────────────────────────────────────────────────────────────────
 * الخطوةُ الناقصةُ بين طلب العروض والأمر (NAV-02 §12-②): عروضُ كل طلبٍ
 * تُعرض جنبًا إلى جنبٍ بأسعارها وجاهزيتها — والترسيةُ صفٌّ في rfq_awards
 * بسببٍ موثَّق، فلا ترسيةَ صامتة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Procurement/RfqAwardService.php';

// CS-01 · RF-02 — الحارسُ فوقَ المعالج. كان ‎INSERT INTO rfq_awards‎ في السطرِ 33
// و‎insidebar‎ (منفِّذُ حارسِ العرض) في السطرِ 66 — ترسيةٌ تُرحَّل ثم يُقال «لا صلاحية».
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$rfq = intval($_REQUEST['rfq'] ?? 0);
$msg = '';

// FN-05 · CS-05 — الحكمُ في خدمةِ النطاقِ المالكةِ لجدولِ الترسية، ومسارُ الكتابةِ
// واحدٌ لا مساران (الشاشةُ الأخرى صارت منظرًا قارئًا).
$__pc = ems_post_contract($conn, array(
    'action'  => 'proc.rfq.award',
    'perm'    => 'can_add',
    'trigger' => 'award_quote',
    'idem'    => array(
        'quote'  => intval($_POST['award_quote'] ?? 0),
        'reason' => trim($_POST['award_reason'] ?? ''),
    ),
    'validate' => function (array $in) {
        $qid = intval($in['award_quote'] ?? 0);
        $why = trim($in['award_reason'] ?? '');
        if ($qid <= 0) { return array('ok' => false, 'msg' => 'عرضٌ غيرُ صالح (422)'); }
        if ($why === '') { return array('ok' => false, 'msg' => 'سببُ الترسية إلزامي — لا ترسيةَ صامتة (422)'); }
        return array('ok' => true, 'data' => array('qid' => $qid, 'why' => $why));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $svc = new \App\Services\Procurement\RfqAwardService($conn);
    $res = $svc->award($company_id, (int) $__pc['data']['qid'], (string) $__pc['data']['why'], $uid);
    $msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'rfq_awards#' . $res['award_id']); }
}

$rfqs = array(); $quotes = array();
$r = mysqli_query($conn, "SELECT id, rfq_no, title, state FROM supplier_rfqs
                          WHERE company_id=$company_id AND is_deleted=0 AND state IN ('sent','opened','quoted')
                          ORDER BY id DESC LIMIT 40");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rfqs[] = $x;
if ($rfq > 0) {
    $r = mysqli_query($conn, "SELECT q.id, s.name supplier, q.unit_price, q.currency, q.qty_offered,
                                     q.readiness_days, q.record_rating, q.note
                              FROM rfq_quotes q JOIN suppliers s ON s.id = q.supplier_id
                              WHERE q.rfq_id = $rfq AND q.company_id = $company_id
                              ORDER BY q.unit_price");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $quotes[] = $x;
}

$page_title = 'مقارنة العروض والترسية';
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
$header_icon = 'fa fa-balance-scale';
$header_title_html = htmlspecialchars('مقارنةُ العروض والترسية', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="get" class="ems-form" style="margin-bottom:14px">
    <select name="rfq" class="form-control" style="max-width:420px" onchange="this.form.submit()">
      <option value="">— اختر طلبَ عروض —</option>
      <?php foreach ($rfqs as $f): ?>
        <option value="<?= intval($f['id']) ?>" <?= $f['id'] == $rfq ? 'selected' : '' ?>>
          <?= htmlspecialchars(($f['rfq_no'] ?: '#' . $f['id']) . ' — ' . $f['title'] . ' (' . $f['state'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($rfq > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المورد</th><th>سعرُ الوحدة</th><th>الكمية</th><th>جاهزية (يوم)</th><th>تقييمُ السجل</th><th>ملاحظة</th><th>ترسية</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإرسال</th>
              <th class="ems-fn-th" data-fn="1">مرجع طلب الشراء</th>
              <th class="ems-fn-th" data-fn="1">الأصناف</th>
              <th class="ems-fn-th" data-fn="1">الموردون المدعوون</th>
              <th class="ems-fn-th" data-fn="1">تاريخ إقفال العروض</th>
              <th class="ems-fn-th" data-fn="1">الإجمالي</th>
              <th class="ems-fn-th" data-fn="1">مدة التوريد</th>
              <th class="ems-fn-th" data-fn="1">شروط الدفع</th>
              <th class="ems-fn-th" data-fn="1">التقييم الفني</th>
              <th class="ems-fn-th" data-fn="1">الترتيب</th>
              <th class="ems-fn-th" data-fn="1">القرار</th>
              <th class="ems-fn-th" data-fn="1">مبرر الاختيار</th>
              <th class="ems-fn-th" data-fn="1">أعدّه</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($quotes)): ?><tr><td colspan="7" class="text-center text-muted">لا عروضَ مقدَّمةً لهذا الطلب</td></tr><?php endif; ?>
    <?php $best = $quotes ? floatval($quotes[0]['unit_price']) : 0;
    foreach ($quotes as $q): $isBest = floatval($q['unit_price']) <= $best; ?>
      <tr<?= $isBest ? ' style="background:#f0fff4"' : '' ?>>
        <td><?= htmlspecialchars($q['supplier'], ENT_QUOTES, 'UTF-8') ?><?= $isBest ? ' <span class="badge" style="background:#198754">الأدنى</span>' : '' ?></td>
        <td><?= number_format(floatval($q['unit_price']), 2) ?> <?= htmlspecialchars($q['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= floatval($q['qty_offered']) ?></td>
        <td><?= intval($q['readiness_days']) ?></td>
        <td><?= htmlspecialchars($q['record_rating'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($q['note'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="rfq" value="<?= $rfq ?>">
            <input type="hidden" name="award_quote" value="<?= intval($q['id']) ?>">
            <input type="text" name="award_reason" class="form-control form-control-sm" placeholder="سببُ الترسية" style="max-width:160px" required aria-label="سببُ الترسية">
            <button class="action-btn" type="submit">رسِّ</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
