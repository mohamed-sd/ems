<?php
/**
 * Employees/hr_onboarding.php — التهيئة والمباشرة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * لا مباشرة كاملة قبل اكتمال بنود التهيئة او توثيق استثنائها
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

$perms = w13_perms($conn, 'Employees/hr_onboarding.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'hr_onboarding_item',
                 array('orderBy' => 'employee_id, item_code', 'limit' => 500));

$page_title = 'إيكوبيشن | التهيئة والمباشرة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التهيئة والمباشرة'; $header_icon = 'fa fa-clipboard-check'; $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد البنود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "pending") ?></div><div class="ems-stat-label">بنود معلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "waived") ?></div><div class="ems-stat-label">بنود باستثناء موثق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "employee_id") ?></div><div class="ems-stat-label">موظفون قيد التهيئة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود تهيئة', 'البند سطر بحالته لا خانة اختيار بلا اثر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_onboarding')); ?>
    <table id="emsList_hr_onboarding" class="data-table">
        <thead><tr><th>الموظف</th><th>رمز البند</th><th>البند</th><th>الزامي</th><th>الحالة</th><th>مستند الاستثناء</th><th>سند العهدة</th><th>تاريخ الانجاز</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w13_txt($r["item_code"]) ?></td>
                    <td><?= ems_w13_txt($r["item_ar"]) ?></td>
                    <td><?= (int) $r["mandatory"] ?></td>
                    <td><?= ems_w13_state((string) $r["state"]) ?></td>
                    <td><?= ems_w13_txt($r["waiver_doc_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["custody_doc_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["done_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
