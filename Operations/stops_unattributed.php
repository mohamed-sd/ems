<?php
/**
 * Operations/stops_unattributed.php — التوقفاتُ بلا مسؤول (★ الموقع · update0007-ب F8)
 * «صفرُ واقعةٍ بلا مسؤولٍ شرطُ إقفال اليوم» (UAT §9-①) — ساعاتُ التعطل التي
 * لم تُسند لجهةٍ تُعرض هنا وتُسند بقرارٍ — فلا يُقفل يومٌ وفيه توقفٌ يتيم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['assign_ts'])) {
    $tid  = intval($_POST['assign_ts']);
    $dept = trim($_POST['fault_department'] ?? '');
    if ($dept === '') { $msg = 'الجهةُ المسؤولةُ إلزامية (422)'; }
    else {
        $st = mysqli_prepare($conn, "UPDATE timesheet SET fault_department = ? WHERE id = ? AND company_id = ?
                                     AND (fault_department IS NULL OR fault_department = '')");
        mysqli_stmt_bind_param($st, 'sii', $dept, $tid, $company_id);
        mysqli_stmt_execute($st);
        $msg = mysqli_stmt_affected_rows($st) > 0 ? "أُسند التوقفُ #$tid إلى «{$dept}»" : 'مُسندٌ من قبل (409)';
    }
}

$rows = array();
$r = mysqli_query($conn, "SELECT t.id, t.date, t.operator, t.shift, t.total_fault_hours, t.fault_type, t.fault_details
                          FROM timesheet t
                          WHERE t.company_id = $company_id AND t.total_fault_hours > 0
                            AND (t.fault_department IS NULL OR t.fault_department = '')
                          ORDER BY t.date DESC LIMIT 100");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
$depts = array('العميل','المورد','الصيانة','التشغيل','المشغّل','قوةٌ قاهرة');

$page_title = 'التوقفات بلا مسؤول';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-user-slash"></i> التوقفاتُ بلا مسؤول</h4>
    <span class="badge" style="background:#dc3545"><?= count($rows) ?> — ولا يُقفل يومٌ وفيها واحد</span></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>#</th><th>التاريخ</th><th>المشغّل</th><th>الوردية</th><th>ساعاتُ التعطل</th><th>النوع</th><th>الإسناد</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center" style="color:#198754">✔ صفرُ توقفٍ بلا مسؤول</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><?= intval($t['id']) ?></td>
        <td><?= htmlspecialchars($t['date'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['operator'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['shift'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= floatval($t['total_fault_hours']) ?></strong></td>
        <td><?= htmlspecialchars($t['fault_type'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="assign_ts" value="<?= intval($t['id']) ?>">
            <select name="fault_department" class="form-control form-control-sm" required style="max-width:130px">
              <option value="">— الجهة —</option>
              <?php foreach ($depts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
            </select>
            <button class="action-btn" type="submit">أسند</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
