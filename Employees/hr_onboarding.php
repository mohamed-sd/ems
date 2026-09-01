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
require_once __DIR__ . '/../includes/w14_grid.php';
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
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف التهيئة' => 'g215',
        'رقم الموظف' => 'g216',
        'بند التهيئة' => 'g217',
        'المسؤول' => 'g218',
        'مرجع الإنجاز' => 'g219',
        'النتيجة' => 'g220',
        'تاريخ الإنجاز' => 'g221',
        'حالة البند' => 'g222',
        'المنشئ' => 'g223',
        'تاريخ الإنشاء' => 'g224',
        'حالة البيانات' => 'g225',
        'مرجع المصدر' => 'g226',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_onboarding');
    echo ems_w14_grid('emsList_hr_onboarding', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التهيئة والمباشرة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
