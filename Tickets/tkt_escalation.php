<?php
/**
 * Tickets/tkt_escalation.php — التصعيد (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * التصعيد الي بمستوياته عند تجاوز المهل ولا يسكت الا بمعالجة او تعليق مبرر
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

$perms = w13_perms($conn, 'Tickets/tkt_escalation.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'ticket_escalations',
                 array('orderBy' => 'ws_id DESC, level', 'limit' => 800));

$page_title = 'إيكوبيشن | التصعيد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التصعيد'; $header_icon = 'fa fa-arrow-up-right-dots'; $header_actions = array();
    $header_back = array('href' => 'tkt_assignment.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الإسناد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع التصعيد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "ws_id") ?></div><div class="ems-stat-label">المسارات المصعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "level") ?></div><div class="ems-stat-label">المستويات المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "to_person_id") ?></div><div class="ems-stat-label">تصعيدات لها مستقبل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع تصعيد', 'التصعيد سلم بمستوياته لا تنبيه يمر بلا اثر'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>مسار العمل</th><th>مستوى التصعيد</th><th>محفز التصعيد</th><th>المصعد اليه</th><th>التاريخ والوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ws_id"] ?></td>
                    <td><?= (int) $r["level"] ?></td>
                    <td><?= ems_w13_state((string) $r["triggered_by"]) ?></td>
                    <td><?= (int) $r["to_person_id"] ?></td>
                    <td><?= ems_w13_txt($r["at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
