<?php
/**
 * Finance/tre_vessels.php — الحسابات البنكية والصناديق (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * اوعية الخزينة (TRS-02) — الخزينة تملك الحسابات والصناديق والتفويض بالتوقيع يقرا من الحوكمة.
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

$perms = fin_page_perms($conn, 'Finance/tre_vessels.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_cash_box', array('orderBy' => 'code', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_vessels.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | الحسابات البنكية والصناديق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الحسابات البنكية والصناديق'; $header_icon = 'fa fa-building-columns'; $header_actions = array();
    $header_back = array('href' => 'accounts_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'دليل الحسابات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الصناديق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "is_active", 1) ?></div><div class="ems-stat-label">صناديق نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "currency") ?></div><div class="ems-stat-label">عملات مستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "custodian_id") ?></div><div class="ems-stat-label">امناء الصناديق</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا صناديق مسجلة', 'كل صندوق بامينه وعملته ورصيده الافتتاحي'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_vessels')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود الوعاء' => 'g44',
        'اسم الوعاء' => 'g45',
        'نوع الوعاء' => 'g46',
        'البنك/الموقع' => 'g47',
        'رقم الحساب' => 'g48',
        'العملة' => 'g49',
        'المفوضون بالتوقيع' => 'g50',
        'مرجع التفويض' => 'g51',
        'حد الصندوق' => 'g52',
        'أمين الصندوق' => 'g53',
        'الرصيد الدفتري' => 'g54',
        'حالة الوعاء' => 'g55',
        'المنشئ' => 'g56',
        'تاريخ الإنشاء' => 'g57',
        'المراجع' => 'g58',
        'المعتمد' => 'g59',
        'تاريخ الاعتماد' => 'g60',
        'حالة البيانات' => 'g61',
        'مرجع المصدر' => 'g62',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_vessels');
    echo ems_w14_grid('emsList_tre_vessels', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الحسابات البنكية والصناديق'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
