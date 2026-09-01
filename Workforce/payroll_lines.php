<?php
/**
 * Workforce/payroll_lines.php — أسطر مسير الرواتب (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل موظف سطر داخل المسير بمكوناته والمجاميع في الام تقرا من الاسطر
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

$perms = w13_perms($conn, 'Workforce/payroll_lines.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'payroll_lines',
                 array('orderBy' => 'run_id DESC, person_id', 'limit' => 800));

$page_title = 'إيكوبيشن | أسطر مسير الرواتب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'أسطر مسير الرواتب'; $header_icon = 'fa fa-money-check-dollar'; $header_actions = array();
    $header_back = array('href' => 'payroll_runs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مسير الرواتب');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاسطر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "run_id") ?></div><div class="ems-stat-label">المسيرات الممثلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "person_id") ?></div><div class="ems-stat-label">الموظفون في الاسطر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_num(ems_w13_sumf($rows, "amount")) ?></div><div class="ems-stat-label">اجمالي مبالغ الاسطر</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اسطر مسير', 'السطر مكون باسمه لا رقم مجمع بلا سند'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_payroll_lines')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g72',
        'معرف المسير' => 'g73',
        'رقم الموظف' => 'g74',
        'الأساسي' => 'g75',
        'البدلات' => 'g76',
        'حافز الإنتاج من أساس القوى' => 'g77',
        'الخصومات بمرجع ب08' => 'g78',
        'التأمينات بمرجع ب08-2' => 'g79',
        'أقساط السلف بمرجع ب08-3' => 'g80',
        'الصافي' => 'g81',
        'حالة السطر' => 'g82',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_payroll_lines');
    echo ems_w14_grid('emsList_payroll_lines', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في أسطر مسير الرواتب'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
