<?php
/**
 * Employees/hr_job_movements.php — الحركات الوظيفية (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * النقل والترقية والانتداب حركات موثقة بموجبها واعتمادها
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

$perms = w13_perms($conn, 'Employees/hr_job_movements.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_job_movement',
                 array('orderBy' => 'effective_date DESC, id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | الحركات الوظيفية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الحركات الوظيفية'; $header_icon = 'fa fa-arrows-turn-right'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الحركات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "approved") ?></div><div class="ems-stat-label">حركات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "submitted") ?></div><div class="ems-stat-label">حركات قيد الاعتماد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "employee_id") ?></div><div class="ems-stat-label">موظفون لهم حركات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حركات وظيفية', 'الحركة قرار بموجبه لا تعديل صامت للمنصب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الموظف</th><th>نوع الحركة</th><th>المنصب السابق</th><th>المنصب الجديد</th><th>تاريخ السريان</th><th>مرجع القرار</th><th>طالب الحركة</th><th>معتمد الحركة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_state((string) $r["movement_kind"]) ?></td>
                    <td><?= (int) $r["from_position_id"] ?></td>
                    <td><?= (int) $r["to_position_id"] ?></td>
                    <td><?= ems_w13_txt($r["effective_date"]) ?></td>
                    <td><?= ems_w13_txt($r["doc_ref"]) ?></td>
                    <td><?= (int) $r["requested_by"] ?></td>
                    <td><?= (int) $r["approved_by"] ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
