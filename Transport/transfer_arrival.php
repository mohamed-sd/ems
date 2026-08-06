<?php
/**
 * Transport/transfer_arrival.php — الوصولُ والتسليم (★ النقل · update0007 S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * الأوامرُ الواصلةُ بانتظار تأكيدِ التسليم — والتسليمُ يقدّمها إلى الإقفال
 * (شاشةُ «إقفال الأمر وتحميل التكلفة» تُتمّ الدورة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['deliver_id'])) {
    $oid = intval($_POST['deliver_id']);
    $note = trim($_POST['delivery_note'] ?? '');
    // التسليمُ حدثٌ موثَّقٌ — والمرحلةُ تبقى arrived حتى الإقفال بتكلفتها
    mysqli_query($conn, "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id)
                         VALUES ($company_id, $oid, 'delivered', '" . mysqli_real_escape_string($conn, $note ?: 'تسليمٌ مؤكَّد') . "', $uid)");
    $msg = "وُثّق تسليمُ الأمر #$oid — أتمّ الدورةَ من شاشة الإقفال وتحميل التكلفة";
}

$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.arrival_datetime, tl.name AS to_loc, e.name AS vehicle,
            (SELECT COUNT(*) FROM transfer_events ev WHERE ev.order_id = o.id AND ev.event_type = 'delivered') AS delivered
     FROM transfer_orders o
     LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
     LEFT JOIN equipments e ON e.id = o.vehicle_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'arrived'
     ORDER BY o.arrival_datetime DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الوصول والتسليم';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-flag-checkered"></i> الوصولُ والتسليم</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الأمر</th><th>الوجهة</th><th>المركبة</th><th>وصل</th><th>التسليم</th><th>إجراء</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">لا أوامرَ واصلةً بانتظار التسليم</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['to_loc'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['vehicle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['arrival_datetime'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $o['delivered'] ? '<span class="badge" style="background:#198754">مُسلَّم</span>' : '<span class="badge" style="background:#6c757d">بانتظار التسليم</span>' ?></td>
        <td>
          <?php if (!$o['delivered']): ?>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="deliver_id" value="<?= intval($o['id']) ?>">
            <input type="text" name="delivery_note" class="form-control form-control-sm" placeholder="ملاحظةُ التسليم" style="max-width:180px">
            <button class="action-btn" type="submit">تأكيدُ التسليم</button>
          </form>
          <?php else: ?>
          <a class="action-btn" href="transfer_close_cost.php?id=<?= intval($o['id']) ?>">أقفل بتكلفته ←</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
