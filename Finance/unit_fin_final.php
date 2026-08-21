<?php
/**
 * Finance/unit_fin_final.php — الاعتمادُ الماليُّ النهائيّ
 *   عقدةُ سلسلةِ الأثرِ ٩ · INJ-CHAIN-CLOSE-01 · الموجة الثالثة · LD-07
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **قفلُ الأثرِ الماليِّ للفترة**. ثلاثُ أيدٍ لا واحدة: المحاسبُ المنتدبُ
 *   **يُعِدُّ بياناتِ القيدِ فقط**، والمديرُ الماليُّ يعتمد، ورئيسُ الحساباتِ
 *   **يُجيز قبلَ الترحيل** — والترحيلُ لمحرّكِه وحدَه.
 * ◆ ولا يُعَدُّ اعتمادٌ نهائيٌّ لواقعةٍ لم تكتمل سلسلتُها التجارية.
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

$__pcPrep = ems_post_contract($conn, array(
    'action'  => 'chain.unit_final.prepare',
    'perm'    => 'can_add',
    'trigger' => 'prep_final',
    'idem'    => array('e' => intval($_POST['entry_id'] ?? 0), 'p' => (string) ($_POST['period'] ?? '')),
    'validate' => function (array $in) {
        $e = intval($in['entry_id'] ?? 0);
        $p = (string) ($in['period'] ?? '');
        if ($e <= 0) { return array('ok' => false, 'msg' => 'واقعةٌ غيرُ صالحة (422)'); }
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) { return array('ok' => false, 'msg' => 'الفترةُ بصيغةِ YYYY-MM (422)'); }
        return array('ok' => true, 'data' => array('entry_id' => $e, 'period' => $p));
    },
));
if (!$__pcPrep['ok'] && $__pcPrep['msg'] !== '') { $msg = $__pcPrep['msg']; }
if ($__pcPrep['replay'])                          { $msg = $__pcPrep['msg']; }
if ($__pcPrep['run'] && $__pcPrep['ok']) {
    $res = CN::prepareFinalApproval($conn, $gate, $company_id, (int) $__pcPrep['data']['entry_id'],
                                    (string) $__pcPrep['data']['period'], $uid);
    $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pcPrep['idem'], $__pcPrep['code'], 'unit_final_approvals#' . (int) $res['id']); }
}

foreach (array(
    array('approve_final', 'chain.unit_final.approve', 'approveFinal'),
    array('control_final', 'chain.unit_final.control', 'controlFinal'),
) as $step) {
    list($trig, $code, $method) = $step;
    $pc = ems_post_contract($conn, array(
        'action' => $code, 'perm' => 'can_edit', 'trigger' => $trig,
        'idem' => array('id' => intval($_POST[$trig] ?? 0)),
        'validate' => function (array $in) use ($trig) {
            $id = intval($in[$trig] ?? 0);
            if ($id <= 0) { return array('ok' => false, 'msg' => 'سجلٌّ غيرُ صالح (422)'); }
            return array('ok' => true, 'data' => array('id' => $id));
        },
    ));
    if (!$pc['ok'] && $pc['msg'] !== '') { $msg = $pc['msg']; }
    if ($pc['replay'])                    { $msg = $pc['msg']; }
    if ($pc['run'] && $pc['ok']) {
        $res = CN::$method($conn, $gate, $company_id, (int) $pc['data']['id'], $uid);
        $msg = ($res['ok'] ? '✅ ' : '❌ ') . $res['reason'] . ' (' . $res['code'] . ')';
        if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $pc['idem'], $pc['code'], 'unit_final_approvals#' . (int) $pc['data']['id']); }
    }
}

$rows = array(); $queueFail = '';
try {
    $rows = $gate->select('unit_final_approvals', array(
        'orderBy' => "`state` <> 'posted' DESC, `id` DESC", 'limit' => 200));
    $eids = array();
    foreach ($rows as $r) { $eids[(int) $r['entry_id']] = true; }
    $meta = array();
    if ($eids) {
        $marks = implode(',', array_fill(0, count($eids), '?'));
        foreach ($gate->select('unit_entries', array(
            'columns' => array('id', 'entry_no', 'state'),
            'whereRaw' => '`id` IN (' . $marks . ')',
            'params' => array_map('intval', array_keys($eids)))) as $e) {
            $meta[(int) $e['id']] = $e;
        }
    }
    foreach ($rows as $k => $r) {
        $e = isset($meta[(int) $r['entry_id']]) ? $meta[(int) $r['entry_id']] : null;
        $rows[$k]['entry_no']    = $e ? $e['entry_no'] : null;
        $rows[$k]['entry_state'] = $e ? $e['state'] : '—';
    }
} catch (\Throwable $e) { $queueFail = 'تعذّر قراءةُ الطابور: ' . $e->getMessage(); }

/* مرشَّحاتُ الإعداد — وقائعُ اكتملت سلسلتُها التجاريةُ ولم تُقفَل بعد */
$cand = array();
try {
    $taken = array();
    foreach ($gate->select('unit_final_approvals', array('columns' => array('entry_id'))) as $t) {
        $taken[(int) $t['entry_id']] = true;
    }
    foreach ($gate->select('unit_entries', array(
        'columns' => array('id', 'entry_no', 'entry_date', 'state'),
        'whereRaw' => "`state` IN ('sales_approved','converted')",
        'orderBy' => '`id` DESC', 'limit' => 200)) as $e) {
        if (isset($taken[(int) $e['id']])) { continue; }
        $cand[] = $e;
        if (count($cand) >= 25) { break; }
    }
} catch (\Throwable $e) { /* المرشَّحاتُ زينةٌ لا حكم — وغيابُها لا يُرسِّب الشاشة */ }

$page_title = 'الاعتماد المالي النهائي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
  $header_icon = 'fa fa-lock';
  $header_title_html = htmlspecialchars('الاعتماد المالي النهائي', ENT_QUOTES, 'UTF-8');
  ob_start(); ?><span class="badge"><?= $queueFail === '' ? count($rows) : '—' ?> سجلًّا · <?= count($cand) ?> مرشَّحًا</span><?php
  $header_actions = array(array('raw' => trim((string) ob_get_clean())));
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_states_bundle('لا اعتمادَ نهائيٌّ مُعَدٌّ بعد',
      'الوقائعُ المكتملةُ تجاريًّا تظهر مرشَّحاتٍ هنا — ثم ثلاثُ أيدٍ: إعدادٌ فاعتمادٌ فإجازة');
  ?>
  <p class="text-muted">العقدة ٩ · السلّم <code>LD-07</code> —
     <strong>ثلاثُ أيدٍ لا واحدة</strong>: المُعِدُّ لا يعتمد، والمعتمِدُ لا يُجيز،
     ولا يُملأ رقمُ القيدِ إلا بعدَ الإجازةِ وبمحرّكِ الترحيلِ وحدَه.</p>
  <?php if ($msg !== ''): ?>
    <div class="alert <?= (mb_strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($queueFail !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($queueFail, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 mb-3">
    <?php echo csrf_field(); ?>
    <div class="col-auto"><label class="form-label" for="uf_entry">الواقعة</label>
      <select class="form-control form-control-sm" name="entry_id" id="uf_entry" required>
        <option value="">— اختر واقعةً مكتملةَ السلسلة —</option>
        <?php foreach ($cand as $c): ?>
          <option value="<?= (int) $c['id'] ?>">#<?= (int) $c['id'] ?> ·
            <?= htmlspecialchars((string) $c['entry_no'], ENT_QUOTES, 'UTF-8') ?> ·
            <?= htmlspecialchars((string) $c['entry_date'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-auto"><label class="form-label" for="uf_period">الفترة</label>
      <input class="form-control form-control-sm" type="month" required name="period" id="uf_period"></div>
    <div class="col-auto align-self-end">
      <button class="action-btn" type="submit" name="prep_final" value="1"><i class="fa fa-plus"></i> إعداد الاعتماد</button></div>
  </form>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>الإجراء</th><th>الفترة</th><th>الواقعة</th><th>حالة الواقعة</th><th>الحالة</th>
      <th>السلّم</th><th>أعدَّه</th><th>اعتمده</th><th>أجازه</th><th>رقم القيد</th>
      <th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>
      <th class="ems-gov-th none" data-gov="approved_at" data-slice="1">تاريخ الاعتماد</th>
      <th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" class="text-center text-muted">لا اعتمادَ نهائيٌّ مُعَدٌّ بعد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td>
          <?php if ($r['state'] === 'prepared'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="approve_final" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-check"></i> اعتماد نهائي</button></form>
          <?php elseif ($r['state'] === 'approved'): ?>
            <form method="post"><?php echo csrf_field(); ?>
              <input type="hidden" name="control_final" value="<?= $id ?>">
              <button class="action-btn" type="submit"><i class="fa fa-stamp"></i> إجازة الرقابة</button></form>
          <?php else: ?><span class="badge">—</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>#<?= (int) $r['entry_id'] ?><?= $r['entry_no'] !== null ? ' · ' . htmlspecialchars((string) $r['entry_no'], ENT_QUOTES, 'UTF-8') : '' ?></td>
        <td><?= htmlspecialchars((string) $r['entry_state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><code><?= htmlspecialchars((string) $r['ladder_id'], ENT_QUOTES, 'UTF-8') ?></code></td>
        <td><?= (int) $r['prepared_by'] ?: '—' ?></td>
        <td><?= (int) $r['approved_by'] ?: '—' ?></td>
        <td><?= (int) $r['control_by'] ?: '—' ?></td>
        <td><?= (int) $r['journal_entry_id'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
