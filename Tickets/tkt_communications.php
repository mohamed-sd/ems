<?php
/**
 * Tickets/tkt_communications.php — سجل التواصل (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * دور المركز تواصل موثق وكل تواصل سطر بقناته ووقته
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

$perms = w13_perms($conn, 'Tickets/tkt_communications.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'ticket_communications',
                 array('orderBy' => 'tk_id DESC, cm_id', 'limit' => 800));

$page_title = 'إيكوبيشن | سجل التواصل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل التواصل'; $header_icon = 'fa fa-comments'; $header_actions = array();
    $header_back = array('href' => 'tkt_parties.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أطراف البلاغ');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع التواصل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "tk_id") ?></div><div class="ems-stat-label">بلاغات لها تواصل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "channel") ?></div><div class="ems-stat-label">القنوات المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "person_id") ?></div><div class="ems-stat-label">الاشخاص المتواصلون</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع تواصل', 'التواصل سطر بقناته لا مكالمة بلا اثر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_communications')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف التواصل' => 'g143',
        'رقم البلاغ' => 'g144',
        'الاتجاه' => 'g145',
        'الطرف' => 'g146',
        'القناة' => 'g147',
        'ملخص التواصل' => 'g148',
        'ضمن مستوى السرية' => 'g149',
        'وقت التواصل' => 'g150',
        'المنشئ' => 'g151',
        'تاريخ الإنشاء' => 'g152',
        'حالة البيانات' => 'g153',
        'مرجع المصدر' => 'g154',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tkt_communications');
    echo ems_w14_grid('emsList_tkt_communications', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مراسلات البلاغ'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
