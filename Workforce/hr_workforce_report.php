<?php
/**
 * Workforce/hr_workforce_report.php — تقرير القوى العاملة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * مشتق من السجل والعقود والحضور ولا ادخال فيه
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

$perms = w13_perms($conn, 'Workforce/hr_workforce_report.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'employees',
                 array('orderBy' => 'employment_classification, id', 'limit' => 800));

$page_title = 'إيكوبيشن | تقرير القوى العاملة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقرير القوى العاملة'; $header_icon = 'fa fa-chart-pie'; $header_actions = array();
    $header_back = array('href' => '../Employees/employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اجمالي القوى العاملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "employment_classification") ?></div><div class="ems-stat-label">التصنيفات الوظيفية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "is_workforce", "1") ?></div><div class="ems-stat-label">ضمن القوى التشغيلية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "project_id") ?></div><div class="ems-stat-label">المشاريع الممثلة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بيانات قوى عاملة', 'التقرير مشتق لا يكتب فيه سطر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_hr_workforce_report')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g45',
        'الفترة' => 'g46',
        'الإدارة' => 'g47',
        'عدد الموظفين' => 'g48',
        'منهم مشروعيون' => 'g49',
        'مباشرون جدد' => 'g50',
        'منتهية خدمتهم' => 'g51',
        'معدل الدوران' => 'g52',
        'نسبة الغياب' => 'g53',
        'ساعات إضافية' => 'g54',
        'كلفة العمالة' => 'g55',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_workforce_report');
    echo ems_w14_grid('emsList_hr_workforce_report', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تقرير القوى العاملة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
