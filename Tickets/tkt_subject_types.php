<?php
/**
 * Tickets/tkt_subject_types.php — كتالوج أنواع محل البلاغ (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * المركز يتعامل مع كل الادارات فالكتالوج يمكن الاضافة ولا يحصر قائمة قصيرة
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

$perms = w13_perms($conn, 'Tickets/tkt_subject_types.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_subject_type',
                 array('orderBy' => 'owner_dept, type_code', 'limit' => 300));

$page_title = 'إيكوبيشن | كتالوج أنواع محل البلاغ';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'كتالوج أنواع محل البلاغ'; $header_icon = 'fa fa-diagram-project'; $header_actions = array();
    $header_back = array('href' => 'ticket_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة مركز البلاغات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الانواع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "active", "1") ?></div><div class="ems-stat-label">انواع مفعلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الادارات المالكة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "entity_kind") ?></div><div class="ems-stat-label">اصناف الكيانات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا انواع محل بلاغ', 'محل البلاغ نوع بسجله المرجعي لا نص حر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_subject_types')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود النوع' => 'g161',
        'اسم النوع' => 'g162',
        'السجل المرجعي' => 'g163',
        'مفتاح الربط' => 'g164',
        'الإدارة المالكة' => 'g165',
        'أمثلة' => 'g166',
        'حالة النوع' => 'g167',
        'المنشئ' => 'g168',
        'تاريخ الإنشاء' => 'g169',
        'حالة البيانات' => 'g170',
        'مرجع المصدر' => 'g171',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tkt_subject_types');
    echo ems_w14_grid('emsList_tkt_subject_types', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في أنواع محل البلاغ المعتمدة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
