<?php
/**
 * Employees/hr_disciplinary.php — القضايا التأديبية والتحقيق (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * القضية عملية بمراحلها واقعة ثم تحقيق ثم قرار والخصم يتفرع بمرجع قرارها
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

$perms = w13_perms($conn, 'Employees/hr_disciplinary.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_disciplinary_case',
                 array('orderBy' => 'incident_at DESC, id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | القضايا التأديبية والتحقيق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'القضايا التأديبية والتحقيق'; $header_icon = 'fa fa-gavel'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد القضايا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "investigation") ?></div><div class="ems-stat-label">قضايا قيد التحقيق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "decided") ?></div><div class="ems-stat-label">قضايا محسومة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "decision_kind", "deduction") ?></div><div class="ems-stat-label">قرارات بخصم</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قضايا تاديبية', 'القضية عملية بمراحلها لا حقل خصم في المسير'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم القضية</th><th>الموظف</th><th>تاريخ الواقعة</th><th>الواقعة</th><th>المبلغ</th><th>المحقق</th><th>الادارة المالكة للتحقيق</th><th>مستند التكليف</th><th>نوع القرار</th><th>مرجع القرار</th><th>مصدر القرار</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w13_txt($r["case_no"]) ?></td>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_txt($r["incident_at"]) ?></td>
                    <td><?= ems_w13_txt($r["incident_ar"]) ?></td>
                    <td><?= (int) $r["reported_by"] ?></td>
                    <td><?= (int) $r["investigator_id"] ?></td>
                    <td><?= ems_w13_txt($r["investigation_owner_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["assignment_doc_ref"]) ?></td>
                    <td><?= ems_w13_state((string) $r["decision_kind"]) ?></td>
                    <td><?= ems_w13_txt($r["decision_ref"]) ?></td>
                    <td><?= (int) $r["decided_by"] ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
