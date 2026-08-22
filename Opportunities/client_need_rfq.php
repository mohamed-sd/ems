<?php
/**
 * Clients/client_need_rfq.php — احتياج العميل وطلب العرض
 *   الورقة 06 · INJ-SAL-ALIGN-01 — قدرةٌ ثبت غيابُها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **سجلٌّ تابعٌ للفرصةِ لا سطحٌ مستقل**: لا احتياجَ بلا فرصةٍ أمّ، ولا يُسجَّل
 *   على فرصةٍ مغلقة. ودليلُ غيابِه: «طلباتُ العروضِ القائمةُ تخصُّ الموردين لا
 *   العملاء» — فهما قدرتان لا واحدة.
 * ◆ **وشرطُ الإتاحةِ يُفحص في الخدمةِ لا في الزر**: الواجهةُ تُخفي والخادمُ يمنع.
 * ◆ **ولا يُصدَر عرضٌ قبلَ رفعِ الاحتياج** — «وجودُ الأبِ لا يكفي».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Align/CapabilityService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

use App\Services\Align\CapabilityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$__pcNew = ems_post_contract($conn, array(
    'action'  => 'align.client_need.open',
    'perm'    => 'can_add',
    'trigger' => 'open_need',
    'idem'    => array('o' => intval($_POST['opportunity_id'] ?? 0),
                       's' => (string) ($_POST['service_type'] ?? '')),
    'validate' => function (array $in) {
        $o = intval($in['opportunity_id'] ?? 0);
        $s = trim((string) ($in['service_type'] ?? ''));
        $q = (float) ($in['qty'] ?? 0);
        if ($o <= 0) { return array('ok' => false, 'msg' => 'لا احتياجَ بلا فرصةٍ أمّ (422)'); }
        if (mb_strlen($s) < 3) { return array('ok' => false, 'msg' => 'نوعُ الخدمةِ إلزاميّ (422)'); }
        if ($q <= 0) { return array('ok' => false, 'msg' => 'الكميةُ يجب أن تكون موجبة (422)'); }
        return array('ok' => true, 'data' => array(
            'opportunity_id' => $o, 'service_type' => $s, 'qty' => $q,
            'unit_type' => (string) ($in['unit_type'] ?? 'hour'),
            'business_model' => (string) ($in['business_model'] ?? ''),
            'duration_months' => intval($in['duration_months'] ?? 0),
            'required_from' => (string) ($in['required_from'] ?? ''),
            'project_id' => intval($in['project_id'] ?? 0),
            'notes' => (string) ($in['notes'] ?? '')));
    },
));
if (!$__pcNew['ok'] && $__pcNew['msg'] !== '') { $msg = $__pcNew['msg']; }
if ($__pcNew['replay'])                         { $msg = $__pcNew['msg']; }
if ($__pcNew['run'] && $__pcNew['ok']) {
    $res = CAP::openNeed($conn, $gate, $company_id, $__pcNew['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcNew['idem'], $__pcNew['code'], 'sal_client_needs#' . (int) $res['id']); }
}

$__pcSub = ems_post_contract($conn, array(
    'action'  => 'align.client_need.submit',
    'perm'    => 'can_edit',
    'trigger' => 'submit_need',
    'idem'    => array('id' => intval($_POST['submit_need'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['submit_need'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'احتياجٌ غيرُ صالح (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcSub['ok'] && $__pcSub['msg'] !== '') { $msg = $__pcSub['msg']; }
if ($__pcSub['replay'])                         { $msg = $__pcSub['msg']; }
if ($__pcSub['run'] && $__pcSub['ok']) {
    $res = CAP::submitNeed($conn, $gate, $company_id, (int) $__pcSub['data']['id']);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcSub['idem'], $__pcSub['code'], 'sal_client_needs#' . (int) $__pcSub['data']['id']); }
}

$rows = array(); $queueFail = ''; $opps = array();
try {
    $rows = $gate->select('sal_client_needs', array(
        'orderBy' => "`state` = 'draft' DESC, `id` DESC", 'limit' => 200));
    $opps = $gate->select('opportunities', array(
        'columns' => array('id', 'opp_code', 'title', 'stage'),
        'whereRaw' => "`stage` NOT IN ('فوز','خسارة','مستبعدة')",
        'orderBy' => '`id` DESC', 'limit' => 100));
} catch (\Throwable $e) { $queueFail = 'تعذّر قراءةُ السجل: ' . $e->getMessage(); }

$page_title = 'احتياج العميل وطلب العرض';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'opportunity'; $sft_active = 'need';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-clipboard-list';
  $header_title_html = htmlspecialchars('احتياج العميل وطلب العرض', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> احتياجًا</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا احتياجَ مسجَّلٌ بعد',
      'الاحتياجُ يُسجَّل على فرصةٍ مفتوحةٍ ثم يُرفع — وبه وحدَه يُتاح إصدارُ العرض');
  ?>
  <p class="text-muted">الورقة ٠٦ · سجلٌّ تابعٌ للفرصة —
     <strong>لا يُصدَر عرضٌ قبلَ رفعِ الاحتياج</strong>؛ ووجودُ الأبِ لا يكفي.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="cn_opp">الفرصة</label>
      <select class="form-control form-control-sm" name="opportunity_id" id="cn_opp" required>
        <option value="">— فرصةٌ مفتوحة —</option>
        <?php foreach ($opps as $o): ?>
          <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars((string) $o['opp_code'] . ' · ' . mb_substr((string) $o['title'], 0, 40), ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="cn_svc">نوع الخدمة</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" required name="service_type" id="cn_svc"></div>
    <div class="col-auto"><label class="form-label" for="cn_qty">الكمية</label>
      <input class="form-control form-control-sm" type="number" step="0.0001" min="0.0001" required name="qty" id="cn_qty"></div>
    <div class="col-auto"><label class="form-label" for="cn_unit">الوحدة</label>
      <select class="form-control form-control-sm" name="unit_type" id="cn_unit">
        <option value="hour">ساعة</option><option value="ton">طن</option>
        <option value="meter">متر</option><option value="trip">رحلة</option></select></div>
    <div class="col-auto"><label class="form-label" for="cn_dur">المدة (شهرًا)</label>
      <input class="form-control form-control-sm" type="number" min="0" name="duration_months" id="cn_dur"></div>
    <div class="col-auto"><label class="form-label" for="cn_from">مطلوبٌ من</label>
      <input class="form-control form-control-sm" type="date" name="required_from" id="cn_from"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="open_need" value="1"><i class="fa fa-plus"></i> تسجيل احتياج</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>رقم الاحتياج</th><th>الفرصة</th><th>نوع الخدمة</th><th>الكمية</th>
      <th>المدة</th><th>مطلوبٌ من</th><th>الحالة</th><th>سجّله</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="created_at" data-slice="1">تاريخ الإنشاء</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted">لا احتياجَ مسجَّلٌ بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td><?php if ($r['state'] === 'draft'): ?>
          <form method="post"><?php echo csrf_field(); ?>
            <input type="hidden" name="submit_need" value="<?= $id ?>">
            <button class="action-btn" type="submit"><i class="fa fa-paper-plane"></i> رفع</button></form>
        <?php else: ?><span class="badge">—</span><?php endif; ?></td>
        <td><?= htmlspecialchars((string) $r['need_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['opportunity_id'] ?></td>
        <td><?= htmlspecialchars((string) $r['service_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $r['qty'], 2) ?> <?= htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['duration_months'] ?: '—' ?></td>
        <td><?= htmlspecialchars((string) $r['required_from'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['created_by'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
