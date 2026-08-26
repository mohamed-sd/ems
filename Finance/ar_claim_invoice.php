<?php
/**
 * Finance/ar_claim_invoice.php — فاتورةُ المطالبةِ وإحالتُها
 *   عقدةُ سلسلةِ الأثرِ ١٨ · INJ-CHAIN-CLOSE-01 · الموجة الخامسة · LD-06
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **الفاتورةُ تُبنى على شهادةِ إنجازٍ معتمَدة** لا على رأسِ المطالبة. ومحاسبُ
 *   المبيعاتِ **يهيّئ ولا يعتمد**، والإجازةُ لرئيسِ الحسابات، ثم الإحالةُ لقسمِ
 *   التحصيل — **ولا إحالةَ قبلَ الإجازة**.
 * ◆ والفاتورةُ الرسميةُ الضريبيةُ بيتُها `Contracts/tax_invoices.php` — يُشار
 *   إليها ولا تُنسَخ هنا.
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

$REFER = array('collections' => 'قسم التحصيل', 'on_hold' => 'تعليق', 'cancelled' => 'إلغاء');

$__pcPrep = ems_post_contract($conn, array(
    'action'  => 'chain.claim_invoice.prepare',
    'perm'    => 'can_add',
    'trigger' => 'prep_inv',
    'idem'    => array('cl' => intval($_POST['claim_id'] ?? 0), 'p' => (string) ($_POST['period'] ?? '')),
    'validate' => function (array $in) {
        $cl = intval($in['claim_id'] ?? 0);
        $p  = (string) ($in['period'] ?? '');
        $a  = (float) ($in['amount'] ?? 0);
        $cu = trim((string) ($in['currency'] ?? ''));
        if ($cl <= 0) { return array('ok' => false, 'msg' => 'لا فاتورة مطالبة بلا مطالبة (422)'); }
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) { return array('ok' => false, 'msg' => 'الفترة بصيغة YYYY-MM (422)'); }
        if ($a <= 0) { return array('ok' => false, 'msg' => 'المبلغ يجب أن يكون موجبا (422)'); }
        if (mb_strlen($cu) < 3) { return array('ok' => false, 'msg' => 'لا مبلغ بلا عملة (422)'); }
        return array('ok' => true, 'data' => array('claim_id' => $cl, 'period' => $p,
            'cert_id' => intval($in['cert_id'] ?? 0), 'amount' => $a, 'currency' => $cu));
    },
));
if (!$__pcPrep['ok'] && $__pcPrep['msg'] !== '') { $msg = $__pcPrep['msg']; }
if ($__pcPrep['replay'])                          { $msg = $__pcPrep['msg']; }
if ($__pcPrep['run'] && $__pcPrep['ok']) {
    $res = CN::prepareClaimInvoice($conn, $gate, $company_id, $__pcPrep['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcPrep['idem'], $__pcPrep['code'], 'ar_claim_invoices#' . (int) $res['id']); }
}

foreach (array(
    array('approve_inv', 'chain.claim_invoice.approve', 'approveClaimInvoice', 'can_edit'),
    array('control_inv', 'chain.claim_invoice.control', 'controlClaimInvoice', 'can_edit'),
) as $step) {
    list($trig, $code, $method, $perm) = $step;
    $pc = ems_post_contract($conn, array(
        'action' => $code, 'perm' => $perm, 'trigger' => $trig,
        'idem' => array('id' => intval($_POST[$trig] ?? 0)),
        'validate' => function (array $in) use ($trig) {
            $id = intval($in[$trig] ?? 0);
            if ($id <= 0) { return array('ok' => false, 'msg' => 'فاتورة غير صالحة (422)'); }
            return array('ok' => true, 'data' => array('id' => $id));
        },
    ));
    if (!$pc['ok'] && $pc['msg'] !== '') { $msg = $pc['msg']; }
    if ($pc['replay'])                    { $msg = $pc['msg']; }
    if ($pc['run'] && $pc['ok']) {
        /* ══ وصلُ السلّم LD-06 بنسخةِ سلّمٍ مشتركةٍ مع شهادةِ الإنجاز ══ */
        $__cid = 0;
        $__row = $gate->selectOne('ar_claim_invoices', array(
            'columns' => array('claim_id'), 'where' => array('id' => (int) $pc['data']['id'])));
        if ($__row) { $__cid = (int) $__row['claim_id']; }
        $__lg = ems_ladder_guard($conn, 'LD-06', $company_id, 'claim_invoice',
            (int) $pc['data']['id'], $uid, 'LD-06-INST:' . $__cid);
        if (!$__lg['ok']) {
            $res = array('ok' => false, 'code' => $__lg['code'], 'reason' => $__lg['reason']);
        } else {
        $res = CN::$method($conn, $gate, $company_id, (int) $pc['data']['id'], $uid);
        }
        $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
        if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $pc['idem'], $pc['code'], 'ar_claim_invoices#' . (int) $pc['data']['id']); }
    }
}

$__pcRef = ems_post_contract($conn, array(
    'action'  => 'chain.claim_invoice.refer',
    'perm'    => 'can_edit',
    'trigger' => 'refer_inv',
    'idem'    => array('id' => intval($_POST['refer_inv'] ?? 0), 'to' => (string) ($_POST['refer_to'] ?? '')),
    'validate' => function (array $in) use ($REFER) {
        $id = intval($in['refer_inv'] ?? 0);
        $to = (string) ($in['refer_to'] ?? '');
        if ($id <= 0) { return array('ok' => false, 'msg' => 'فاتورة غير صالحة (422)'); }
        if (!isset($REFER[$to])) { return array('ok' => false, 'msg' => 'وجهة الإحالة محكومة من قائمة مغلقة (422)'); }
        return array('ok' => true, 'data' => array('id' => $id, 'to' => $to));
    },
));
if (!$__pcRef['ok'] && $__pcRef['msg'] !== '') { $msg = $__pcRef['msg']; }
if ($__pcRef['replay'])                         { $msg = $__pcRef['msg']; }
if ($__pcRef['run'] && $__pcRef['ok']) {
    $res = CN::referClaimInvoice($conn, $gate, $company_id, (int) $__pcRef['data']['id'], (string) $__pcRef['data']['to'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcRef['idem'], $__pcRef['code'], 'ar_claim_invoices#' . (int) $__pcRef['data']['id']); }
}

/* ◆ **الإثراءُ باستعلامٍ ثانٍ لا بضمٍّ خام**: رقمُ الشهادةِ يُجلَب من جدولِه
 *   عبرَ البوابةِ نفسِها — فلا استعلامَ خامٍّ على جدولِ مستأجِرٍ في الشاشة. */
$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('ar_claim_invoices', array(
        'orderBy' => "`state` <> 'referred' DESC, `id` DESC", 'limit' => 200));
    $ids = array();
    foreach ($rows as $r) { if ((int) $r['cert_id'] > 0) { $ids[(int) $r['cert_id']] = true; } }
    $certNo = array();
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        foreach ($gate->select('ar_completion_certs', array(
            'columns' => array('id', 'cert_no'),
            'whereRaw' => '`id` IN (' . $marks . ')',
            'params' => array_map('intval', array_keys($ids)))) as $c) {
            $certNo[(int) $c['id']] = (string) $c['cert_no'];
        }
    }
    foreach ($rows as $k => $r) {
        $rows[$k]['cert_no'] = isset($certNo[(int) $r['cert_id']]) ? $certNo[(int) $r['cert_id']] : null;
    }
} catch (\Throwable $e) { $queueFail = 'تعذر قراءة الطابور: ' . $e->getMessage(); }

$page_title = 'فاتورة المطالبة وإحالتها';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-file-invoice';
  $header_title_html = htmlspecialchars('فاتورة المطالبة وإحالتها', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> فاتورة</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا فاتورة مطالبة معدة بعد',
      'الفاتورة تبنى على شهادة إنجاز معتمدة ثم تعتمد وتجاز وتحال للتحصيل');
  ?>
  <p class="text-muted">العقدة ١٨ · السلم <code>LD-06</code> — يهيئ محاسب المبيعات ولا يعتمد ·
     ولا إحالة قبل الإجازة المحاسبية · والفاتورة الرسمية بيتها
     <a href="../Contracts/tax_invoices.php">الفاتورة الضريبية</a> — تشار ولا تنسخ.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="ci_claim">المطالبة</label>
      <input class="form-control form-control-sm" type="number" min="1" required name="claim_id" id="ci_claim"></div>
    <div class="col-auto"><label class="form-label" for="ci_cert">شهادة الإنجاز</label>
      <input class="form-control form-control-sm" type="number" min="0" name="cert_id" id="ci_cert"></div>
    <div class="col-auto"><label class="form-label" for="ci_period">الفترة</label>
      <input class="form-control form-control-sm" type="month" required name="period" id="ci_period"></div>
    <div class="col-auto"><label class="form-label" for="ci_amount">المبلغ</label>
      <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" required name="amount" id="ci_amount"></div>
    <div class="col-auto"><label class="form-label" for="ci_cur">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" required name="currency" id="ci_cur"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="prep_inv" value="1"><i class="fa fa-plus"></i> إعداد فاتورة</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>رقم الفاتورة</th><th>الفترة</th><th>المطالبة</th><th>الشهادة</th>
      <th>المبلغ</th><th>الحالة</th><th>الإحالة</th><th>أعدها</th><th>اعتمدها</th><th>أجازها</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="approved_at" data-slice="1">تاريخ الاعتماد</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" class="text-center text-muted">لا فاتورة مطالبة معدة بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'prepared'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="approve_inv" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-check"></i> اعتماد</button></form>
          <?php elseif ($r['state'] === 'approved'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="control_inv" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-stamp"></i> إجازة محاسبية</button></form>
          <?php elseif ($r['state'] === 'controlled'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="refer_inv" value="<?= $id ?>">
              <label class="visually-hidden" for="ci_to_<?= $id ?>">وجهة الإحالة</label>
              <select class="form-control form-control-sm" name="refer_to" id="ci_to_<?= $id ?>" required>
                <option value="">— وجهة الإحالة —</option>
                <?php foreach ($REFER as $k => $v): ?>
                  <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <button class="action-btn" type="submit"><i class="fa fa-share"></i> إحالة</button></form>
          <?php else: ?><span class="badge">—</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string) $r['invoice_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['claim_id'] ?></td>
        <td><?= $r['cert_no'] !== null ? htmlspecialchars((string) $r['cert_no'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= number_format((float) $r['amount'], 2) ?> <?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $r['referred_to'] !== null && isset($REFER[$r['referred_to']])
                ? htmlspecialchars($REFER[$r['referred_to']], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= (int) $r['prepared_by'] ?: '—' ?></td>
        <td><?= (int) $r['approved_by'] ?: '—' ?></td>
        <td><?= (int) $r['control_by'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php /* ── ACC-08 · بنود فاتورة العميل — سجلٌّ تابعٌ في شاشةِ أبيه لا سطحٌ مستقلّ.
       ◆ **كلُّ بندٍ بسطرِه**: الوصفُ والكميّةُ والسعرُ والضريبة — والإجمالياتُ تُشتقُّ للأمّ.
       ◆ والقراءةُ عبرَ بوّابةِ المستأجرِ فلا يظهر بندٌ من كيانٍ آخر. */
  $__w11_lines = array();
  try { $__w11_lines = $gate->select('acc_invoice_line',
            array('orderBy' => 'invoice_id DESC, line_no', 'limit' => 400)); }
  catch (\Throwable $t) { error_log('ar_claim_invoice lines: ' . $t->getMessage()); }
  ?>
  <h5 class="mt-4">بنود فواتير العملاء</h5>
  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الفاتورة</th><th>البند</th><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th>
      <th>الاجمالي قبل الضريبة</th><th>رمز الضريبة</th><th>الضريبة</th><th>اجمالي البند</th>
    </tr></thead>
    <tbody>
    <?php if (empty($__w11_lines)): ?>
      <tr><td colspan="9" class="text-center text-muted">لا بنود مسجلة على فواتير العملاء</td></tr>
    <?php endif; ?>
    <?php foreach ($__w11_lines as $__l): ?>
      <tr>
        <td>#<?= (int) $__l['invoice_id'] ?></td>
        <td><?= (int) $__l['line_no'] ?></td>
        <td><?= htmlspecialchars((string) $__l['description'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $__l['qty'], 3) ?></td>
        <td><?= number_format((float) $__l['unit_price'], 2) ?></td>
        <td><?= number_format((float) $__l['subtotal'], 2) ?></td>
        <td><?= htmlspecialchars((string) $__l['tax_code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $__l['tax_amount'], 2) ?></td>
        <td><?= number_format((float) $__l['line_total'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
