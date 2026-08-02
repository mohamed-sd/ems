<?php
/**
 * Transport/transfer_close_cost.php — إقفالُ الأمر وتحميلُ التكلفة (★ · S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * آخرُ الدورة: الأمرُ الواصلُ المُسلَّمُ يُقفل بتكلفته الفعلية محمَّلةً على
 * مشروعه (analytic_cost_center) — «أمرُ ترحيلٍ مقفَلٌ بتكلفةٍ محمَّلةٍ على
 * مشروعها» (شاهدُ UAT-01 §11-⑪).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['close_id'])) {
    $oid  = intval($_POST['close_id']);
    $cost = floatval($_POST['actual_cost'] ?? 0);
    if ($cost <= 0) {
        $msg = 'التكلفةُ الفعليةُ إلزاميةٌ للإقفال — ولا إقفالَ بتكلفةٍ صفر (422)';
    } else {
        $st = mysqli_prepare($conn, "UPDATE transfer_orders SET stage='closed', actual_cost_usd=?
                                     WHERE id=? AND company_id=? AND stage='arrived'");
        mysqli_stmt_bind_param($st, 'dii', $cost, $oid, $company_id);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_affected_rows($st) > 0) {
            mysqli_query($conn, "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id)
                                 VALUES ($company_id, $oid, 'closed', 'أُقفل بتكلفةٍ فعلية $cost\$', $uid)");
            // سطرُ التكلفة المحمَّل — transfer_cost_lines هو دفترُ التحميل
            mysqli_query($conn, "INSERT INTO transfer_cost_lines (company_id, order_id, cost_type, amount_usd, currency, cost_bearer, analytic_cost_center)
                                 VALUES ($company_id, $oid, 'actual_total', $cost, 'USD', 'project', 'PRJ')");
            $msg = "أُقفل الأمرُ #$oid بتكلفة $cost\$ محمَّلةً على مشروعه";
        } else { $msg = 'لم يُقفل — الأمرُ ليس واصلًا (409)'; }
    }
}

$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.estimated_cost_usd, o.actual_cost_usd, o.project_id,
            p.name AS project_name, o.arrival_datetime
     FROM transfer_orders o
     LEFT JOIN project p ON p.id = o.project_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'arrived'
     ORDER BY o.arrival_datetime");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'إقفال الأمر وتحميل التكلفة';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-lock"></i> إقفالُ الأمر وتحميلُ التكلفة</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الأمر</th><th>المشروعُ المحمَّل</th><th>المقدَّرة $</th><th>الفعلية $</th><th>إقفال</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted">لا أوامرَ بانتظار الإقفال</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['project_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format(floatval($o['estimated_cost_usd']), 2) ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="close_id" value="<?= intval($o['id']) ?>">
            <input type="number" step="0.01" name="actual_cost" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($o['actual_cost_usd'] ?: $o['estimated_cost_usd'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:130px" required>
        </td>
        <td><button class="action-btn" type="submit"><i class="fa fa-lock"></i> أقفل وحمّل</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
