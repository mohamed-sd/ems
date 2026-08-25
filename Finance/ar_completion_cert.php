<?php
/**
 * Finance/ar_completion_cert.php — شهادةُ الإنجازِ الشهرية
 *   عقدةُ سلسلةِ الأثرِ ١٧ · INJ-CHAIN-CLOSE-01 · الموجة الخامسة · LD-06
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ المستندُ الذي **تُبنى عليه الفاتورة**. محاسبُ المبيعاتِ يهيّئ، والاعتمادُ
 *   بيدٍ أخرى — ولا شهادةَ بلا **مرجعِ قياسٍ معتمَد**.
 * ◆ العقدتان ١٧ و١٨ **مرحلتان في نسخةِ سلّمٍ واحدة** (`LD-06-INST`) — فلا
 *   يوقّع المستخدمُ اعتمادًا واحدًا مرتين لمجرد أن الواجهةَ قُسِّمت شاشتين.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../includes/ladder_gate.php';
require_once __DIR__ . '/../app/Services/Chain/ChainNodeService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

use App\Services\Chain\ChainNodeService as CN;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$__pcPrep = ems_post_contract($conn, array(
    'action'  => 'chain.completion_cert.prepare',
    'perm'    => 'can_add',
    'trigger' => 'prep_cert',
    'idem'    => array('c' => intval($_POST['contract_id'] ?? 0), 'p' => (string) ($_POST['period'] ?? '')),
    'validate' => function (array $in) {
        $c = intval($in['contract_id'] ?? 0);
        $p = (string) ($in['period'] ?? '');
        $q = (float) ($in['approved_qty'] ?? 0);
        $m = trim((string) ($in['measure_ref'] ?? ''));
        if ($c <= 0) { return array('ok' => false, 'msg' => 'لا شهادة بلا عقد مرجعي (422)'); }
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) { return array('ok' => false, 'msg' => 'الفترة بصيغة YYYY-MM (422)'); }
        if ($q <= 0) { return array('ok' => false, 'msg' => 'الكمية المعتمدة يجب أن تكون موجبة (422)'); }
        if ($m === '') { return array('ok' => false, 'msg' => 'لا شهادة إنجاز بلا مرجع قياس معتمد (422)'); }
        return array('ok' => true, 'data' => array('contract_id' => $c, 'period' => $p,
            'claim_id' => intval($in['claim_id'] ?? 0), 'approved_qty' => $q,
            'unit_type' => (string) ($in['unit_type'] ?? 'hour'), 'measure_ref' => $m));
    },
));
if (!$__pcPrep['ok'] && $__pcPrep['msg'] !== '') { $msg = $__pcPrep['msg']; }
if ($__pcPrep['replay'])                          { $msg = $__pcPrep['msg']; }
if ($__pcPrep['run'] && $__pcPrep['ok']) {
    $res = CN::prepareCert($conn, $gate, $company_id, $__pcPrep['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcPrep['idem'], $__pcPrep['code'], 'ar_completion_certs#' . (int) $res['id']); }
}

$__pcApp = ems_post_contract($conn, array(
    'action'  => 'chain.completion_cert.approve',
    'perm'    => 'can_edit',
    'trigger' => 'approve_cert',
    'idem'    => array('id' => intval($_POST['approve_cert'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['approve_cert'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'شهادة غير صالحة (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcApp['ok'] && $__pcApp['msg'] !== '') { $msg = $__pcApp['msg']; }
if ($__pcApp['replay'])                         { $msg = $__pcApp['msg']; }
if ($__pcApp['run'] && $__pcApp['ok']) {
    /* ══ وصلُ السلّم LD-06 — ونسخةُ سلّمِه مشتركةٌ مع فاتورةِ المطالبة ══
       فالعقدتان ١٧ و١٨ مرحلتان في نسخةٍ واحدة: لا طلبَ اعتمادٍ ثانٍ. */
    $__cid = 0;
    $__row = $gate->selectOne('ar_completion_certs', array(
        'columns' => array('claim_id'), 'where' => array('id' => (int) $__pcApp['data']['id'])));
    if ($__row) { $__cid = (int) $__row['claim_id']; }
    $__lg = ems_ladder_guard($conn, 'LD-06', $company_id, 'completion_cert',
        (int) $__pcApp['data']['id'], $uid, 'LD-06-INST:' . $__cid);
    if (!$__lg['ok']) {
        $res = array('ok' => false, 'code' => $__lg['code'], 'reason' => $__lg['reason']);
    } else {
    $res = CN::approveCert($conn, $gate, $company_id, (int) $__pcApp['data']['id'], $uid);
    }
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcApp['idem'], $__pcApp['code'], 'ar_completion_certs#' . (int) $__pcApp['data']['id']); }
}

$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('ar_completion_certs', array(
        'orderBy' => "`state` = 'prepared' DESC, `id` DESC", 'limit' => 200));
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة الطابور: ' . $e->getMessage(); }

$page_title = 'شهادة الإنجاز الشهرية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-certificate';
  $header_title_html = htmlspecialchars('شهادة الإنجاز الشهرية', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> شهادة</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا شهادة إنجاز معدة بعد',
      'الشهادة تعد من الأداء المعتمد بمرجع قياس ثم تعتمد — وعليها تبنى الفاتورة');
  ?>
  <p class="text-muted">العقدة ١٧ · السلم <code>LD-06</code> · نسخة السلم <code>LD-06-INST</code> —
     مشتركة مع العقدة ١٨، فلا طلب اعتماد ثان لمجرد تقسيم الواجهة.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="cc_contract">العقد</label>
      <input class="form-control form-control-sm" type="number" min="1" required name="contract_id" id="cc_contract"></div>
    <div class="col-auto"><label class="form-label" for="cc_claim">المطالبة</label>
      <input class="form-control form-control-sm" type="number" min="0" name="claim_id" id="cc_claim"></div>
    <div class="col-auto"><label class="form-label" for="cc_period">الفترة</label>
      <input class="form-control form-control-sm" type="month" required name="period" id="cc_period"></div>
    <div class="col-auto"><label class="form-label" for="cc_qty">الكمية المعتمدة</label>
      <input class="form-control form-control-sm" type="number" step="0.0001" min="0.0001" required name="approved_qty" id="cc_qty"></div>
    <div class="col-auto"><label class="form-label" for="cc_unit">الوحدة</label>
      <select class="form-control form-control-sm" name="unit_type" id="cc_unit">
        <option value="hour">ساعة</option><option value="ton">طن</option>
        <option value="meter">متر</option><option value="trip">رحلة</option></select></div>
    <div class="col-auto"><label class="form-label" for="cc_ref">مرجع القياس</label>
      <input class="form-control form-control-sm" type="text" maxlength="120" required name="measure_ref" id="cc_ref"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="prep_cert" value="1"><i class="fa fa-plus"></i> إعداد شهادة</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>رقم الشهادة</th><th>الفترة</th><th>العقد</th><th>المطالبة</th>
      <th>الكمية</th><th>مرجع القياس</th><th>الحالة</th><th>السلم</th><th>أعدها</th><th>اعتمدها</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="approved_at" data-slice="1">تاريخ الاعتماد</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" class="text-center text-muted">لا شهادة إنجاز معدة بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php if ($r['state'] === 'prepared'): ?>
          <form method="post"><?php echo csrf_field(); ?>
            <input type="hidden" name="approve_cert" value="<?= (int) $r['id'] ?>">
            <button class="action-btn" type="submit"><i class="fa fa-check"></i> اعتماد</button></form>
        <?php else: ?><span class="badge">—</span><?php endif; ?></td>
        <td><?= htmlspecialchars((string) $r['cert_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['contract_id'] ?></td>
        <td><?= (int) $r['claim_id'] ? '#' . (int) $r['claim_id'] : '—' ?></td>
        <td><?= number_format((float) $r['approved_qty'], 2) ?> <?= htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['measure_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><code><?= htmlspecialchars((string) $r['ladder_id'], ENT_QUOTES, 'UTF-8') ?></code></td>
        <td><?= (int) $r['prepared_by'] ?: '—' ?></td>
        <td><?= (int) $r['approved_by'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
