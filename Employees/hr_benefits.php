<?php
/**
 * Employees/hr_benefits.php — المزايا والتأمينات (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * الاشتراكات النظامية والتامين الطبي بحصتيهما تصب في المسير بمرجعها
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

$perms = w13_perms($conn, 'Employees/hr_benefits.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_benefit_enrollment',
                 array('orderBy' => 'employee_id, effective_from DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | المزايا والتأمينات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'المزايا والتأمينات'; $header_icon = 'fa fa-shield-heart'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاشتراكات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "active") ?></div><div class="ems-stat-label">اشتراكات سارية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "benefit_code") ?></div><div class="ems-stat-label">انواع المزايا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_num(ems_w13_sumf($rows, "employer_share")) ?></div><div class="ems-stat-label">اجمالي حصة صاحب العمل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اشتراكات مزايا', 'الميزة اشتراك بحصتيه ومرجعه في المسير'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الموظف</th><th>رمز الميزة</th><th>الميزة</th><th>مقدم الخدمة</th><th>حصة صاحب العمل</th><th>حصة الموظف</th><th>العملة</th><th>ساري من</th><th>مرجع مكون المسير</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_txt($r["benefit_code"]) ?></td>
                    <td><?= ems_w13_txt($r["benefit_ar"]) ?></td>
                    <td><?= ems_w13_txt($r["provider_ref"]) ?></td>
                    <td><?= ems_w13_num($r["employer_share"]) ?></td>
                    <td><?= ems_w13_num($r["employee_share"]) ?></td>
                    <td><?= ems_w13_txt($r["currency"]) ?></td>
                    <td><?= ems_w13_txt($r["effective_from"]) ?></td>
                    <td><?= ems_w13_txt($r["payroll_component_ref"]) ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
