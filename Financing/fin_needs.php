<?php
/**
 * Financing/fin_needs.php — فرص واحتياجات التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * حاجة تمويلية واحدة بمبررها — ومن رفعها لا يعتمدها.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-04)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/w12_view.php';

$ctx = w12_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w12_perms($conn, 'Financing/fin_needs.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_funding_need',
                 array('orderBy' => 'id DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | فرص واحتياجات التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'فرص واحتياجات التمويل'; $header_icon = 'fa fa-lightbulb'; $header_actions = array();
    $header_back = array('href' => 'financing_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة المحفظة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاحتياجات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "approved") ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "submitted") ?></div><div class="ems-stat-label">قيد الاعتماد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "amount_needed")) ?></div><div class="ems-stat-label">اجمالي المطلوب</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا احتياجات تمويلية مسجلة', 'الحاجة تسبق العرض والعرض يسبق العقد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_needs')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود الحاجة' => 'g43',
        'كود العملية الناتجة' => 'g44',
        'Selected_Financier_ID نتيجة لاحقة بعد العروض والاختيار' => 'g45',
        'Requesting_Department الإدارة الطالبة' => 'g46',
        'Requested_By الطالب' => 'g47',
        'Required_By_Date' => 'g48',
        'Priority' => 'g49',
        'Need_Approval اعتماد الحاجة' => 'g50',
        'Approved_Amount' => 'g51',
        'اسم الممول (بحث)' => 'g52',
        'الأصل المطلوب' => 'g53',
        'كود العين' => 'g54',
        'القيمة (من العملية)' => 'g55',
        'العملة' => 'g56',
        'نموذج التمويل' => 'g57',
        'Need_Date' => 'g58',
        'Need_Must_Precede' => 'g59',
        'المشروع/عقد العميل' => 'g60',
        'سبب الحاجة' => 'g61',
        'Record_Basis' => 'g62',
        'Derivation_Rule' => 'g63',
        'Confidence' => 'g64',
        'Needs_Review' => 'g65',
        'حالة البيانات' => 'g66',
        'Source_Row_Ref' => 'g67',
        'ملاحظات' => 'g68',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fin_funding_need');
    echo ems_w14_grid('emsList_fin_needs', $GUIDE_COLS, $__gridRows, $D, 'لا حاجة تمويل مسجلة بعد'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
