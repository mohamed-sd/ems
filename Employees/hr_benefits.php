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

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_benefits')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g150',
        'رقم الموظف' => 'g151',
        'نوع الميزة' => 'g152',
        'الجهة/الوثيقة' => 'g153',
        'حصة الشركة' => 'g154',
        'حصة الموظف' => 'g155',
        'من تاريخ' => 'g156',
        'إلى تاريخ' => 'g157',
        'مرجع المسير' => 'g158',
        'حالة السطر' => 'g159',
        'المنشئ' => 'g160',
        'تاريخ الإنشاء' => 'g161',
        'حالة البيانات' => 'g162',
        'مرجع المصدر' => 'g163',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_benefits');
    echo ems_w14_grid('emsList_hr_benefits', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في المزايا والتأمينات'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('المزايا والتامينات', 'المزايا والتأمينات'),
        'table'    => 'hr_benefits',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Employees/hr_benefits.php',
    )); ?>
</div>
</div>
</body></html>
