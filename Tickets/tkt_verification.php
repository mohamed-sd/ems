<?php
/**
 * Tickets/tkt_verification.php — التحقق والإغلاق (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * المسار الثلاثي معالجة ثم تحقق ثم اغلاق ولا اغلاق بلا تحقق ولا تحقق من المنفذ
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `PeopleCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w13_view.php';

$ctx = w13_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w13_perms($conn, 'Tickets/tkt_verification.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_verification',
                 array('orderBy' => 'ticket_id DESC, cycle_no', 'limit' => 500));

$page_title = 'إيكوبيشن | التحقق والإغلاق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التحقق والإغلاق'; $header_icon = 'fa fa-circle-check'; $header_actions = array();
    $header_back = array('href' => 'tkt_resolution_actions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'إجراءات المعالجة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد دورات التحقق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "verification") ?></div><div class="ems-stat-label">دورات قيد التحقق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "closed") ?></div><div class="ems-stat-label">دورات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "reopened") ?></div><div class="ems-stat-label">دورات اعيد فتحها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا دورات تحقق', 'الاغلاق دورة بتحققها لا زر يغلق بلا شاهد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>البلاغ</th><th>رقم الدورة</th><th>الاولوية</th><th>ادارة المعالجة</th><th>المعالج</th><th>تاريخ المعالجة</th><th>نافذة التحقق بالساعات</th><th>صفة المتحقق</th><th>المتحقق</th><th>تاريخ التحقق</th><th>المغلق</th><th>تاريخ الاغلاق</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= (int) $r["cycle_no"] ?></td>
                    <td><?= ems_w13_state((string) $r["priority_code"]) ?></td>
                    <td><?= ems_w13_txt($r["resolved_dept"]) ?></td>
                    <td><?= (int) $r["resolved_by"] ?></td>
                    <td><?= ems_w13_txt($r["resolved_at"]) ?></td>
                    <td><?= (int) $r["window_hours"] ?></td>
                    <td><?= ems_w13_state((string) $r["verify_kind"]) ?></td>
                    <td><?= (int) $r["verified_by"] ?></td>
                    <td><?= ems_w13_txt($r["verified_at"]) ?></td>
                    <td><?= (int) $r["closed_by"] ?></td>
                    <td><?= ems_w13_txt($r["closed_at"]) ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
