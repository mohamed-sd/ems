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

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_job_movements')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'رقم الحركة' => 'g56',
        'رقم الموظف' => 'g57',
        'نوع الحركة' => 'g58',
        'من منصب/وحدة' => 'g59',
        'إلى منصب/وحدة' => 'g60',
        'الموجب/المرجع' => 'g61',
        'أثر الأجر' => 'g62',
        'تاريخ النفاذ' => 'g63',
        'حالة الحركة' => 'g64',
        'المنشئ' => 'g65',
        'تاريخ الإنشاء' => 'g66',
        'المراجع' => 'g67',
        'المعتمد' => 'g68',
        'تاريخ الاعتماد' => 'g69',
        'حالة البيانات' => 'g70',
        'مرجع المصدر' => 'g71',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_job_movements');
    echo ems_w14_grid('emsList_hr_job_movements', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الحركات الوظيفية'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('الحركات الوظيفيه', 'الحركات الوظيفية'),
        'table'    => 'hr_job_movements',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Employees/hr_job_movements.php',
    )); ?>
</div>
</div>
</body></html>
