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

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_training')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السجل' => 'g136',
        'رقم الموظف' => 'g137',
        'البرنامج' => 'g138',
        'نوع التدريب' => 'g139',
        'الجهة' => 'g140',
        'تاريخ الإتمام' => 'g141',
        'النتيجة' => 'g142',
        'صلاحية الشهادة' => 'g143',
        'إلزامي؟' => 'g144',
        'حالة السجل' => 'g145',
        'المنشئ' => 'g146',
        'تاريخ الإنشاء' => 'g147',
        'حالة البيانات' => 'g148',
        'مرجع المصدر' => 'g149',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_training');
    echo ems_w14_grid('emsList_hr_training', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التدريب والكفاءة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('التدريب والكفاءه', 'التدريب والكفاءة'),
        'table'    => 'hr_training',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Employees/hr_training.php',
    )); ?>
</div>
</div>
</body></html>
