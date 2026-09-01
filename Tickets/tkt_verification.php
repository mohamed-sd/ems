<?php
/**
 * Tickets/tkt_verification.php — التحقق والإغلاق (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * المسار الثلاثي معالجة ثم تحقق ثم اغلاق ولا اغلاق بلا تحقق ولا تحقق من المنفذ
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

$perms = w13_perms($conn, 'Tickets/tkt_verification.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_verification',
                 array('orderBy' => 'ticket_id DESC, cycle_no', 'limit' => 500));

$page_title = 'إيكوبيشن | التحقق والإغلاق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التحقق والإغلاق'; $header_icon = 'fa fa-circle-check'; $header_actions = array();
    $header_back = array('href' => 'tkt_resolution_actions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'إجراءات المعالجة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد دورات التحقق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "verification") ?></div><div class="ems-stat-label">دورات قيد التحقق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "closed") ?></div><div class="ems-stat-label">دورات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "state", "reopened") ?></div><div class="ems-stat-label">دورات اعيد فتحها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا دورات تحقق', 'الاغلاق دورة بتحققها لا زر يغلق بلا شاهد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_verification')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإغلاق' => 'g49',
        'رقم البلاغ' => 'g50',
        'ملخص المعالجة' => 'g51',
        'Resolved في' => 'g52',
        'نوع التحقق' => 'g53',
        'تأكيد المبلغ' => 'g54',
        'تقييم الرضا' => 'g55',
        'نوع الإغلاق' => 'g56',
        'البلاغ الأصل عند التكرار' => 'g57',
        'سبب الإلغاء' => 'g58',
        'وقت الإغلاق' => 'g59',
        'حالة الإغلاق' => 'g60',
        'المنشئ' => 'g61',
        'تاريخ الإنشاء' => 'g62',
        'حالة البيانات' => 'g63',
        'مرجع المصدر' => 'g64',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tkt_verification');
    echo ems_w14_grid('emsList_tkt_verification', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التحقق من المعالجة وإغلاق البلاغ'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
