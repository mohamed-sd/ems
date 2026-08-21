<?php
/**
 * Finance/tre_pay_batch.php — دفعاتُ الدفعِ والتنفيذ
 *   عقدةُ سلسلةِ الأثرِ ٢٥ · INJ-CHAIN-CLOSE-01 · الموجة السابعة
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **تنفيذٌ نقديٌّ ينتج مرجعَ الحركة — ولا قيد**. الخزينةُ لا تملك قيدًا ولا
 *   اعتمادًا ماليًّا للوحدة، والمحاسبةُ لا تنفّذ نقدًا.
 * ◆ **ومَن أعدَّ الدفعةَ لا ينفّذها**، ولا تنفيذَ بلا مرجعِ حركةٍ بنكيّ —
 *   وقيدان في القاعدةِ يسندان القاعدتين.
 * ◆ سلطتُها `RESOLVE_FROM_POLICY:treasury_disbursement`.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Chain/ChainNodeService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* بوابةُ المستأجِر — لا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الشاشة */
$gate = ems_tenant_db();

use App\Services\Chain\ChainNodeService as CN;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$__pcOpen = ems_post_contract($conn, array(
    'action'  => 'chain.pay_batch.open',
    'perm'    => 'can_add',
    'trigger' => 'open_batch',
    'idem'    => array('d' => (string) ($_POST['value_date'] ?? ''), 'a' => (string) ($_POST['bank_account'] ?? '')),
    'validate' => function (array $in) {
        $d = (string) ($in['value_date'] ?? '');
        $c = trim((string) ($in['currency'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { return array('ok' => false, 'msg' => 'تاريخُ القيمةِ بصيغةِ YYYY-MM-DD (422)'); }
        if (mb_strlen($c) < 3) { return array('ok' => false, 'msg' => 'لا دفعةَ بلا عملة (422)'); }
        return array('ok' => true, 'data' => array('value_date' => $d, 'currency' => $c,
            'bank_account' => trim((string) ($in['bank_account'] ?? ''))));
    },
));
if (!$__pcOpen['ok'] && $__pcOpen['msg'] !== '') { $msg = $__pcOpen['msg']; }
if ($__pcOpen['replay'])                          { $msg = $__pcOpen['msg']; }
if ($__pcOpen['run'] && $__pcOpen['ok']) {
    $res = CN::openBatch($conn, $gate, $company_id, $__pcOpen['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcOpen['idem'], $__pcOpen['code'], 'tre_pay_batches#' . (int) $res['id']); }
}

/* التجهيزُ للتنفيذِ — الإعدادُ لا يُنشئ تنفيذًا، والتجهيزُ خطوةٌ مستقلة */
$__pcReady = ems_post_contract($conn, array(
    'action'  => 'chain.pay_batch.ready',
    'perm'    => 'can_edit',
    'trigger' => 'ready_batch',
    'idem'    => array('id' => intval($_POST['ready_batch'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['ready_batch'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'دفعةٌ غيرُ صالحة (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcReady['ok'] && $__pcReady['msg'] !== '') { $msg = $__pcReady['msg']; }
if ($__pcReady['replay'])                           { $msg = $__pcReady['msg']; }
if ($__pcReady['run'] && $__pcReady['ok']) {
    $id = (int) $__pcReady['data']['id'];
    $res = CN::readyBatch($conn, $gate, $company_id, $id);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcReady['idem'], $__pcReady['code'], 'tre_pay_batches#' . $id); }
}

$__pcExec = ems_post_contract($conn, array(
    'action'  => 'chain.pay_batch.execute',
    'perm'    => 'can_edit',
    'trigger' => 'exec_batch',
    'idem'    => array('id' => intval($_POST['exec_batch'] ?? 0), 'r' => (string) ($_POST['bank_ref'] ?? '')),
    'validate' => function (array $in) {
        $id = intval($in['exec_batch'] ?? 0);
        $r  = trim((string) ($in['bank_ref'] ?? ''));
        if ($id <= 0) { return array('ok' => false, 'msg' => 'دفعةٌ غيرُ صالحة (422)'); }
        if ($r === '') { return array('ok' => false, 'msg' => 'لا تنفيذَ بلا مرجعِ حركةٍ بنكيّ (422)'); }
        return array('ok' => true, 'data' => array('id' => $id, 'bank_ref' => $r));
    },
));
if (!$__pcExec['ok'] && $__pcExec['msg'] !== '') { $msg = $__pcExec['msg']; }
if ($__pcExec['replay'])                          { $msg = $__pcExec['msg']; }
if ($__pcExec['run'] && $__pcExec['ok']) {
    $res = CN::executeBatch($conn, $gate, $company_id, (int) $__pcExec['data']['id'],
                            (string) $__pcExec['data']['bank_ref'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcExec['idem'], $__pcExec['code'], 'tre_pay_batches#' . (int) $__pcExec['data']['id']); }
}

$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('tre_pay_batches', array(
        'orderBy' => "`state` <> 'executed' DESC, `id` DESC", 'limit' => 200));
    foreach ($rows as $k => $r) {
        $rows[$k]['lines_n'] = $gate->count('tre_pay_batch_lines',
            array('where' => array('batch_id' => (int) $r['id'])));
    }
} catch (\Throwable $e) { $queueFail = 'تعذّر قراءةُ الطابور: ' . $e->getMessage(); }

$page_title = 'دفعات الدفع والتنفيذ';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-money-check-dollar';
  $header_title_html = htmlspecialchars('دفعات الدفع والتنفيذ', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> دفعة</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا دفعةَ دفعٍ مفتوحةٌ بعد',
      'الدفعةُ تُفتح ثم تُجهَّز ثم تُنفَّذ بمرجعِ حركةٍ بنكيّ — بيدٍ غيرِ يدِ المُعِدّ');
  ?>
  <p class="text-muted">العقدة ٢٥ · <code>RESOLVE_FROM_POLICY:treasury_disbursement</code> —
     <strong>تنفيذٌ نقديٌّ ينتج مرجعَ الحركةِ ولا قيد</strong>؛ والمستفيدُ يجب أن يكون
     متحقَّقًا في <a href="tre_beneficiary.php">سجل المستفيدين</a>.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="pb_date">تاريخ القيمة</label>
      <input class="form-control form-control-sm" type="date" required name="value_date" id="pb_date"></div>
    <div class="col-auto"><label class="form-label" for="pb_acc">الحساب البنكي</label>
      <input class="form-control form-control-sm" type="text" maxlength="64" name="bank_account" id="pb_acc"></div>
    <div class="col-auto"><label class="form-label" for="pb_cur">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" required name="currency" id="pb_cur"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="open_batch" value="1"><i class="fa fa-plus"></i> فتح دفعة</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>رقم الدفعة</th><th>تاريخ القيمة</th><th>الحساب</th><th>العملة</th>
      <th>السطور</th><th>الحالة</th><th>أعدَّها</th><th>نفَّذها</th><th>مرجع الحركة</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" class="text-center text-muted">لا دفعةَ دفعٍ مفتوحةٌ بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'draft'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="ready_batch" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-clipboard-check"></i> تجهيز</button></form>
          <?php elseif ($r['state'] === 'ready'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="exec_batch" value="<?= $id ?>">
              <label class="visually-hidden" for="pb_ref_<?= $id ?>">مرجع الحركة</label>
              <input class="form-control form-control-sm" type="text" maxlength="120" required
                     name="bank_ref" id="pb_ref_<?= $id ?>" placeholder="مرجع الحركة البنكي">
              <button class="action-btn" type="submit"><i class="fa fa-paper-plane"></i> تنفيذ</button></form>
          <?php else: ?><span class="badge">—</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string) $r['batch_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['value_date'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['bank_account'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['lines_n'] ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['prepared_by'] ?></td>
        <td><?= (int) $r['executed_by'] ?: '—' ?></td>
        <td><?= htmlspecialchars((string) $r['bank_ref'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
