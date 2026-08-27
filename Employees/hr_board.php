<?php
/**
 * Employees/hr_board.php — لوحة الموارد البشرية (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * قراءة حية مشتقة من سجل الموظفين ولا ادخال فيها
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

$perms = w13_perms($conn, 'Employees/hr_board.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'employees',
                 array('orderBy' => 'id DESC', 'limit' => 300));

$page_title = 'إيكوبيشن | لوحة الموارد البشرية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة الموارد البشرية'; $header_icon = 'fa fa-users-gear'; $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الرئيسية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الموظفين</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "employee_status", "نشط") ?></div><div class="ems-stat-label">الموظفون النشطون</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "job_title_id") ?></div><div class="ems-stat-label">المسميات الوظيفية المشغولة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "start_date") ?></div><div class="ems-stat-label">موظفون بتاريخ مباشرة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا موظفون في هذا الكيان', 'اللوحة قراءة مشتقة لا شاشة ادخال'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_board')); ?>
    <table id="emsList_hr_board" class="data-table">
        <thead><tr><th>كود الموظف</th><th>الاسم</th><th>التصنيف الوظيفي</th><th>المسمى الوظيفي</th><th>تاريخ المباشرة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w13_txt($r["employee_code"]) ?></td>
                    <td><?= ems_w13_txt($r["name"]) ?></td>
                    <td><?= ems_w13_txt($r["employment_classification"]) ?></td>
                    <td><?= (int) $r["job_title_id"] ?></td>
                    <td><?= ems_w13_txt($r["start_date"]) ?></td>
                    <td><?= ems_w13_txt($r["employee_status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
