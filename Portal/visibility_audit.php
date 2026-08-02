<?php
/**
 * Portal/visibility_audit.php — سجلُّ تدقيق الظهور (H-16 · الشاشة 186)
 * ───────────────────────────────────────────────────────────────────────────
 * ADM-01 §3-④: «قراءة فقط: سجلُّ كل تغييرٍ بفاعله وسببه ومدته وأثره —
 * **لا يُعدَّل ولا يُحذف**».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityPolicyService as VPS;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$MODULE_CODE = 'Portal/visibility_audit.php';
$can_view = false;
if ($is_super_admin) { $can_view = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحيةَ عرضٍ للسجل ❌')); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('visibility audit super') : ems_tenant_db();
$log = VPS::auditLog($gate, 300);

$page_title = 'إيكوبيشن | سجل تدقيق الظهور';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل تدقيق الظهور'; $header_icon = 'fa fa-clipboard-list';
    $header_actions = array();
    include('../includes/page_header.php');
    ?>

    <div class="card"><div class="card-body"><p style="color:#666">
        <strong>لا تغييرَ صامتٌ على خصوصية أحد</strong> — كلُّ فتحٍ وإغلاقٍ حدثٌ موثَّقٌ
        بفاعله وسببه ومدته وعدد المتأثرين به. السجلُّ <strong>Insert-only</strong>:
        لا زرَّ تعديلٍ ولا حذفٍ في هذه الشاشة ولا في غيرها.
    </p></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> آخرُ 300 حدث</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>#</th><th>الوقت</th><th>العنصر</th><th>النطاق</th>
                <th>من → إلى</th><th>الفاعل</th><th>السبب</th><th>المدة</th><th>المتأثرون</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): ?>
                <tr>
                    <td><?php echo intval($l['id']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)$l['at']); ?></small></td>
                    <td><code><?php echo htmlspecialchars((string)$l['element_code']); ?></code></td>
                    <td><?php echo htmlspecialchars($l['scope_type'] . ' × ' . $l['scope_id']); ?></td>
                    <td><?php echo htmlspecialchars(((string)($l['from_mode'] ?? '—')) . ' → ' . $l['to_mode']); ?>
                        <?php if ((string)$l['to_mode'] === 'denied_self'): ?>
                            <span class="badge badge-danger">منحُ ذاتٍ مرفوض</span>
                        <?php elseif ((string)$l['to_mode'] === 'grant_expired'): ?>
                            <span class="badge badge-secondary">انتهاءٌ آلي</span>
                        <?php endif; ?></td>
                    <td>#<?php echo intval($l['actor']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($l['reason'] ?? '')); ?></small></td>
                    <td><?php echo $l['expires_at'] !== null ? htmlspecialchars((string)$l['expires_at']) : '—'; ?></td>
                    <td><?php echo intval($l['affected_count']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
