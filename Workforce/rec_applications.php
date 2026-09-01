<?php
/**
 * Workforce/rec_applications.php — طلبات الترشح (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاغر الواحد له عشرات المرشحين وكل ترشح سطر بمصدره وحالته
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

$perms = w13_perms($conn, 'Workforce/rec_applications.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'rec_applications',
                 array('orderBy' => 'vac_id, app_id', 'limit' => 500));

$page_title = 'إيكوبيشن | طلبات الترشح';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلبات الترشح'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'recruitment_pipeline.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التوظيف من الشاغر الى المباشرة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الترشحات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "vac_id") ?></div><div class="ems-stat-label">الشواغر ذات المرشحين</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "interview_at") ?></div><div class="ems-stat-label">ترشحات لها موعد مقابلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "employee_id") ?></div><div class="ems-stat-label">ترشحات صارت موظفين</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات ترشح', 'الترشح سطر تحت شاغره لا حقل في الشاغر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_rec_applications')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الترشح' => 'g202',
        'رقم الشاغر' => 'g203',
        'المرشح' => 'g204',
        'مصدر الترشح' => 'g205',
        'المؤهل' => 'g206',
        'الخبرة' => 'g207',
        'نتيجة الفرز الأولي' => 'g208',
        'المرحلة الحالية' => 'g209',
        'حالة الترشح' => 'g210',
        'المنشئ' => 'g211',
        'تاريخ الإنشاء' => 'g212',
        'حالة البيانات' => 'g213',
        'مرجع المصدر' => 'g214',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('hr_rec_applications');
    echo ems_w14_grid('emsList_rec_applications', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في طلبات الترشح'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
