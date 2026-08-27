<?php
/**
 * Employees/hr_employee_documents.php — مستندات الموظف (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل مستند بصلاحيته وتنبيه انتهائه والالزامي المنتهي يعلم الملف
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

$perms = w13_perms($conn, 'Employees/hr_employee_documents.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_employee_document',
                 array('orderBy' => 'employee_id, expires_at', 'limit' => 500));

$page_title = 'إيكوبيشن | مستندات الموظف';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مستندات الموظف'; $header_icon = 'fa fa-folder-open'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المستندات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "is_mandatory", "1") ?></div><div class="ems-stat-label">مستندات الزامية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "expired") ?></div><div class="ems-stat-label">مستندات منتهية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "employee_id") ?></div><div class="ems-stat-label">موظفون لهم مستندات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مستندات مسجلة', 'المستند سطر بصلاحيته لا مرفق بلا تاريخ'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_employee_documents')); ?>
    <table id="emsList_hr_employee_documents" class="data-table">
        <thead><tr><th>الموظف</th><th>نوع المستند</th><th>رقم المستند</th><th>تاريخ الاصدار</th><th>تاريخ الانتهاء</th><th>الزامي</th><th>مرجع الملف</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_state((string) $r["doc_type"]) ?></td>
                    <td><?= ems_w13_txt($r["doc_no"]) ?></td>
                    <td><?= ems_w13_txt($r["issued_at"]) ?></td>
                    <td><?= ems_w13_txt($r["expires_at"]) ?></td>
                    <td><?= (int) $r["is_mandatory"] ?></td>
                    <td><?= ems_w13_txt($r["file_ref"]) ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
