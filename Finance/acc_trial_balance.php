<?php
/**
 * Finance/acc_trial_balance.php — ميزان المراجعة (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * ميزان المراجعة (ACC-21) — مشتق كليا من القيود المنشورة وتوازنه شرط الاقفال.
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

$perms = fin_page_perms($conn, 'Finance/acc_trial_balance.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('acc_trial_balance_run', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_trial_balance.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | ميزان المراجعة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'ميزان المراجعة'; $header_icon = 'fa fa-scale-unbalanced'; $header_actions = array();
    $header_back = array('href' => 'financial_statements_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القوائم المالية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">جولات الميزان</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "balanced", 1) ?></div><div class="ems-stat-label">جولات متوازنة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "period_id") ?></div><div class="ems-stat-label">فترات مغطاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_sum($rows, "line_count") ?></div><div class="ems-stat-label">اسطر مقروءة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا جولات ميزان', 'الميزان مشتق من القيود المنشورة ولا يعدل فيه شيء'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_trial_balance')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g53',
        'الفترة' => 'g54',
        'كود الحساب' => 'g55',
        'اسم الحساب' => 'g56',
        'رصيد افتتاحي مدين' => 'g57',
        'رصيد افتتاحي دائن' => 'g58',
        'حركة مدينة' => 'g59',
        'حركة دائنة' => 'g60',
        'ختامي مدين' => 'g61',
        'ختامي دائن' => 'g62',
        'التوازن الكلي' => 'g63',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fina_acc_trial_balance');
    echo ems_w14_grid('emsList_acc_trial_balance', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في ميزان المراجعة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
