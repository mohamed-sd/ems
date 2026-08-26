<?php
/**
 * Employees/hr_training.php — التدريب والكفاءة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * التدريب الالزامي يتابع بانتهاء صلاحيته لا باكتماله وحده
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

$perms = w13_perms($conn, 'Employees/hr_training.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_training_record',
                 array('orderBy' => 'employee_id, valid_until', 'limit' => 500));

$page_title = 'إيكوبيشن | التدريب والكفاءة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التدريب والكفاءة'; $header_icon = 'fa fa-graduation-cap'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد سجلات التدريب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "mandatory", "1") ?></div><div class="ems-stat-label">برامج الزامية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "completed") ?></div><div class="ems-stat-label">برامج مكتملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "expired") ?></div><div class="ems-stat-label">برامج منتهية الصلاحية</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سجلات تدريب', 'التدريب سطر بصلاحيته لا شهادة بلا تاريخ'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الموظف</th><th>رمز البرنامج</th><th>البرنامج</th><th>نوع التدريب</th><th>الزامي</th><th>تاريخ الاكتمال</th><th>مرجع الشهادة</th><th>صالح حتى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_txt($r["program_code"]) ?></td>
                    <td><?= ems_w13_txt($r["program_ar"]) ?></td>
                    <td><?= ems_w13_state((string) $r["training_kind"]) ?></td>
                    <td><?= (int) $r["mandatory"] ?></td>
                    <td><?= ems_w13_txt($r["completed_at"]) ?></td>
                    <td><?= ems_w13_txt($r["certificate_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["valid_until"]) ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
