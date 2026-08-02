<?php
/**
 * Transport/transfer_in_transit.php — الحركةُ في الطريق (★ النقل · update0007 S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * دورةُ الترحيل الناقصة (NAV-02 §12-③): ما بين المغادرة والوصول.
 * أوامرُ `in_transit` بمركباتها وسائقيها ومساراتها — وزرُّ «وصلت» يقدّم
 * المرحلةَ إلى arrived ويؤرّخ الوصولَ ويسجّل الحدث.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['arrive_id'])) {
    $oid = intval($_POST['arrive_id']);
    $st = mysqli_prepare($conn, "UPDATE transfer_orders SET stage='arrived', arrival_datetime=NOW()
                                 WHERE id=? AND company_id=? AND stage='in_transit'");
    mysqli_stmt_bind_param($st, 'ii', $oid, $company_id);
    mysqli_stmt_execute($st);
    if (mysqli_stmt_affected_rows($st) > 0) {
        mysqli_query($conn, "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id)
                             VALUES ($company_id, $oid, 'arrived', 'وصولٌ مؤكَّدٌ من شاشة الحركة في الطريق', $uid)");
        $msg = "سُجّل وصولُ الأمر #$oid — انتقل إلى «الوصول والتسليم»";
    } else { $msg = 'لم يتقدم — الأمرُ ليس في الطريق (409)'; }
}

$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.departure_datetime, o.planned_date, o.route,
            fl.name AS from_loc, tl.name AS to_loc, e.name AS vehicle, emp.name AS driver
     FROM transfer_orders o
     LEFT JOIN trs_locations fl ON fl.id = o.from_location_id
     LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
     LEFT JOIN equipments e ON e.id = o.vehicle_id
     LEFT JOIN employees emp ON emp.id = o.driver_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'in_transit'
     ORDER BY o.departure_datetime");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الحركة في الطريق';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-truck-moving"></i> الحركةُ في الطريق</h4>
    <span class="badge" style="background:#fd7e14"><?= count($rows) ?> في الطريق</span></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الأمر</th><th>من → إلى</th><th>المركبة</th><th>السائق</th><th>غادر</th><th>منذ</th><th>إجراء</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">لا معدةَ في الطريق — وصفرُ عالقٍ</td></tr><?php endif; ?>
    <?php foreach ($rows as $o):
        $dep = $o['departure_datetime'] ? strtotime($o['departure_datetime']) : null;
        $hrs = $dep ? round((time() - $dep) / 3600, 1) : null; ?>
      <tr<?= ($hrs !== null && $hrs > 48) ? ' style="background:#fff3f3"' : '' ?>>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($o['from_loc'] ?: '؟') . ' ← ' . ($o['to_loc'] ?: '؟'), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['vehicle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['driver'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['departure_datetime'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $hrs !== null ? $hrs . ' ساعة' : '—' ?></td>
        <td><form method="post" style="display:inline"><input type="hidden" name="arrive_id" value="<?= intval($o['id']) ?>">
            <button class="action-btn" type="submit"><i class="fa fa-flag-checkered"></i> وصلت</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
