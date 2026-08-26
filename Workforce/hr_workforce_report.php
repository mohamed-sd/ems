<?php
/**
 * Workforce/hr_workforce_report.php — تقرير القوى العاملة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * مشتق من السجل والعقود والحضور ولا ادخال فيه
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

$perms = w13_perms($conn, 'Workforce/hr_workforce_report.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'employees',
                 array('orderBy' => 'employment_classification, id', 'limit' => 800));

$page_title = 'إيكوبيشن | تقرير القوى العاملة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقرير القوى العاملة'; $header_icon = 'fa fa-chart-pie'; $header_actions = array();
    $header_back = array('href' => '../Employees/employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اجمالي القوى العاملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "employment_classification") ?></div><div class="ems-stat-label">التصنيفات الوظيفية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "is_workforce", "1") ?></div><div class="ems-stat-label">ضمن القوى التشغيلية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "project_id") ?></div><div class="ems-stat-label">المشاريع الممثلة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بيانات قوى عاملة', 'التقرير مشتق لا يكتب فيه سطر'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>كود الموظف</th><th>الاسم</th><th>التصنيف الوظيفي</th><th>فئة العامل</th><th>المشروع</th><th>تاريخ المباشرة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w13_txt($r["employee_code"]) ?></td>
                    <td><?= ems_w13_txt($r["name"]) ?></td>
                    <td><?= ems_w13_txt($r["employment_classification"]) ?></td>
                    <td><?= ems_w13_txt($r["worker_category"]) ?></td>
                    <td><?= (int) $r["project_id"] ?></td>
                    <td><?= ems_w13_txt($r["start_date"]) ?></td>
                    <td><?= ems_w13_txt($r["employee_status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
