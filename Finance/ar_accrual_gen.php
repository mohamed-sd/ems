<?php
/**
 * Finance/ar_accrual_gen.php — توليدُ استحقاقاتِ عقدِ العميل
 *   عقدةُ سلسلةِ الأثرِ ١٦ · INJ-CHAIN-CLOSE-01 · الموجة الخامسة
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ تحويلُ المطالبةِ التجاريةِ إلى **استحقاقٍ محاسبيّ**. مالكُ العملية: المالية
 *   والمحاسبة · المحاسبُ المنتدبُ (محاسبُ المبيعات) **يُعِدُّ ولا يُجيز** ·
 *   والإجازةُ لرئيسِ الحسابات، والترحيلُ لمحرّكِه وحدَه.
 * ◆ سلطةُ الاعتماد: `RESOLVE_FROM_POLICY:ar_accrual` — لا اسمَ جهةٍ في الشاشة.
 * ◆ لا كتابةَ مباشرةً من هنا: كلُّ فعلٍ يمرُّ بـ`ChainNodeService`.
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

/* ── ① الإعداد — المحاسبُ المنتدبُ يُعِدُّ ولا يُجيز ────────────────────── */
$__pcPrep = ems_post_contract($conn, array(
    'action'  => 'chain.ar_accrual.prepare',
    'perm'    => 'can_add',
    'trigger' => 'prep_accrual',
    'idem'    => array('c' => intval($_POST['contract_id'] ?? 0), 'p' => (string) ($_POST['period'] ?? '')),
    'validate' => function (array $in) {
        $c = intval($in['contract_id'] ?? 0);
        $p = (string) ($in['period'] ?? '');
        $a = (float) ($in['amount'] ?? 0);
        $cur = trim((string) ($in['currency'] ?? ''));
        if ($c <= 0) { return array('ok' => false, 'msg' => 'لا استحقاقَ بلا عقدٍ مرجعيّ (422)'); }
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) { return array('ok' => false, 'msg' => 'الفترةُ بصيغةِ YYYY-MM (422)'); }
        if ($a <= 0) { return array('ok' => false, 'msg' => 'المبلغُ يجب أن يكون موجبًا (422)'); }
        if (mb_strlen($cur) < 3) { return array('ok' => false, 'msg' => 'لا مبلغَ بلا عملة (422)'); }
        return array('ok' => true, 'data' => array('contract_id' => $c, 'period' => $p,
            'claim_id' => intval($in['claim_id'] ?? 0), 'amount' => $a, 'currency' => $cur,
            'fx_rate' => (float) ($in['fx_rate'] ?? 1)));
    },
));
if (!$__pcPrep['ok'] && $__pcPrep['msg'] !== '') { $msg = $__pcPrep['msg']; }
if ($__pcPrep['replay'])                          { $msg = $__pcPrep['msg']; }
if ($__pcPrep['run'] && $__pcPrep['ok']) {
    $res = CN::prepareAccrual($conn, $gate, $company_id, $__pcPrep['data'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcPrep['idem'], $__pcPrep['code'], 'ar_accruals#' . (int) $res['id']); }
}

/* ── ② الإجازةُ المحاسبية — بيدٍ غيرِ يدِ المُعِدّ ───────────────────────── */
$__pcCtrl = ems_post_contract($conn, array(
    'action'  => 'chain.ar_accrual.control',
    'perm'    => 'can_edit',
    'trigger' => 'control_accrual',
    'idem'    => array('id' => intval($_POST['control_accrual'] ?? 0)),
    'validate' => function (array $in) {
        $id = intval($in['control_accrual'] ?? 0);
        if ($id <= 0) { return array('ok' => false, 'msg' => 'استحقاقٌ غيرُ صالح (422)'); }
        return array('ok' => true, 'data' => array('id' => $id));
    },
));
if (!$__pcCtrl['ok'] && $__pcCtrl['msg'] !== '') { $msg = $__pcCtrl['msg']; }
if ($__pcCtrl['replay'])                          { $msg = $__pcCtrl['msg']; }
if ($__pcCtrl['run'] && $__pcCtrl['ok']) {
    $res = CN::controlAccrual($conn, $gate, $company_id, (int) $__pcCtrl['data']['id'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcCtrl['idem'], $__pcCtrl['code'], 'ar_accruals#' . (int) $__pcCtrl['data']['id']); }
}

/* ── الطابور — والرسوبُ يُعلَن ولا يلبس ثوبَ الخلوّ ───────────────────────── */
/* الطابورُ من البوابة — والرسوبُ يُعلَن ولا يلبس ثوبَ الخلوّ */
$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('ar_accruals', array(
        'orderBy' => "`state` = 'prepared' DESC, `id` DESC", 'limit' => 200));
} catch (\Throwable $e) { $queueFail = 'تعذّر قراءةُ الطابور: ' . $e->getMessage(); }

$page_title = 'توليد استحقاقات عقد العميل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-file-invoice-dollar';
  $header_title_html = htmlspecialchars('توليد استحقاقات عقد العميل', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> استحقاقًا</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا استحقاقَ مُعَدٌّ بعد',
      'الاستحقاقُ يُعَدُّ من المطالبةِ المعتمدةِ ثم يُجيزه رئيسُ الحسابات — والترحيلُ لمحرّكِه');
  ?>
  <p class="text-muted">العقدة ١٦ في سلسلةِ الأثر · سلطةُ الاعتماد <code>RESOLVE_FROM_POLICY:ar_accrual</code> —
     المحاسبُ المنتدبُ يُعِدُّ ولا يُجيز، ولا كاتبَ بشريٌّ إلى دفترِ الأستاذ.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="ac_contract">العقد</label>
      <input class="form-control form-control-sm" type="number" min="1" required name="contract_id" id="ac_contract"></div>
    <div class="col-auto"><label class="form-label" for="ac_claim">المطالبة</label>
      <input class="form-control form-control-sm" type="number" min="0" name="claim_id" id="ac_claim"></div>
    <div class="col-auto"><label class="form-label" for="ac_period">الفترة</label>
      <input class="form-control form-control-sm" type="month" required name="period" id="ac_period"></div>
    <div class="col-auto"><label class="form-label" for="ac_amount">المبلغ</label>
      <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" required name="amount" id="ac_amount"></div>
    <div class="col-auto"><label class="form-label" for="ac_cur">العملة</label>
      <input class="form-control form-control-sm" type="text" maxlength="8" required name="currency" id="ac_cur"></div>
    <div class="col-auto"><label class="form-label" for="ac_fx">سعر الصرف</label>
      <input class="form-control form-control-sm" type="number" step="0.00000001" min="0.00000001" value="1" name="fx_rate" id="ac_fx"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="prep_accrual" value="1"><i class="fa fa-plus"></i> إعداد استحقاق</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>رقم الاستحقاق</th><th>الفترة</th><th>العقد</th><th>المطالبة</th>
      <th>المبلغ</th><th>بالعملة الأساس</th><th>الحالة</th><th>أعدَّه</th><th>أجازه</th><th>رقم القيد</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="created_at" data-slice="1">تاريخ الإنشاء</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
      <th class="ems-gov-th none" data-gov="currency" data-slice="3">العملة</th>
      <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3">سعر الصرف</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" class="text-center text-muted">لا استحقاقَ مُعَدٌّ بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'prepared'): ?>
            <form method="post">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="control_accrual" value="<?= (int) $r['id'] ?>">
              <button class="action-btn" type="submit"><i class="fa fa-stamp"></i> إجازة محاسبية</button>
            </form>
          <?php else: ?>
            <span class="badge">—</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string) $r['accrual_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['contract_id'] ?></td>
        <td><?= (int) $r['claim_id'] ? '#' . (int) $r['claim_id'] : '—' ?></td>
        <td><?= number_format((float) $r['amount'], 2) ?> <?= htmlspecialchars((string) $r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((float) $r['base_amount'], 2) ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['prepared_by'] ?: '—' ?></td>
        <td><?= (int) $r['control_by'] ?: '—' ?></td>
        <td><?= (int) $r['journal_entry_id'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
