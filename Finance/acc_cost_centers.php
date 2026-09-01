<?php
/**
 * Finance/acc_cost_centers.php — مراكز التكلفة (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * مراكز التكلفة (ACC-03) — المالية تملك الشجرة والمشاريع والمعدات تربط بمراكزها.
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): القراءةُ
 *   تمرُّ ببوّابةِ المستأجرِ التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر،
 *   ولا رقمَ يخلط كيانَين بلا وسمٍ مسجَّل.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ الدورةِ
 *   بحارسِها ورمزِ ردِّها، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/w11_view.php';

$ctx = fin_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/acc_cost_centers.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_cost_centers', array('orderBy' => 'code', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_cost_centers.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | مراكز التكلفة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مراكز التكلفة'; $header_icon = 'fa fa-sitemap'; $header_actions = array();
    $header_back = array('href' => 'accounts_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'دليل الحسابات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المراكز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "active", 1) ?></div><div class="ems-stat-label">مراكز نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "center_type", "profit") ?></div><div class="ems-stat-label">مراكز ربحية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "level") ?></div><div class="ems-stat-label">مستويات الشجرة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مراكز تكلفة', 'المالية تملك الشجرة ولا تجمع تكلفة بلا مركز'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_cost_centers')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود المركز' => 'g116',
        'اسم المركز' => 'g117',
        'المركز الأب' => 'g118',
        'نوع المركز' => 'g119',
        'المرجع المربوط' => 'g120',
        'مسؤول المركز' => 'g121',
        'حالة المركز' => 'g122',
        'المنشئ' => 'g123',
        'تاريخ الإنشاء' => 'g124',
        'حالة البيانات' => 'g125',
        'مرجع المصدر' => 'g126',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fina_acc_cost_centers');
    echo ems_w14_grid('emsList_acc_cost_centers', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مراكز التكلفة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
