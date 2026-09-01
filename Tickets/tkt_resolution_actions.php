<?php
/**
 * Tickets/tkt_resolution_actions.php — إجراءات المعالجة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل اجراء سطر بمرجعه في شاشة الادارة المعالجة والمركز لا ينفذ الحل
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

$perms = w13_perms($conn, 'Tickets/tkt_resolution_actions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_resolution_action',
                 array('orderBy' => 'ticket_id DESC, seq_no', 'limit' => 800));

$page_title = 'إيكوبيشن | إجراءات المعالجة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إجراءات المعالجة'; $header_icon = 'fa fa-screwdriver-wrench'; $header_actions = array();
    $header_back = array('href' => 'tkt_assignment.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الإسناد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاجراءات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "executor_dept") ?></div><div class="ems-stat-label">الادارات المنفذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "ticket_id") ?></div><div class="ems-stat-label">بلاغات لها اجراءات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "dept_doc_ref") ?></div><div class="ems-stat-label">اجراءات لها مستند</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اجراءات معالجة', 'الاجراء سطر بمرجعه في شاشة ادارته'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_resolution_actions')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإجراء' => 'g89',
        'رقم البلاغ' => 'g90',
        'تسلسل الإجراء' => 'g91',
        'المكلف' => 'g92',
        'الإجراء المتخذ' => 'g93',
        'مرجع الإجراء في شاشة الإدارة' => 'g94',
        'نتيجة الإجراء' => 'g95',
        'سبب التعليق' => 'g96',
        'مدة التعليق' => 'g97',
        'وقت الإجراء' => 'g98',
        'المنشئ' => 'g99',
        'تاريخ الإنشاء' => 'g100',
        'حالة البيانات' => 'g101',
        'مرجع المصدر' => 'g102',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tkt_resolution_actions');
    echo ems_w14_grid('emsList_tkt_resolution_actions', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في إجراءات معالجة البلاغ'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
