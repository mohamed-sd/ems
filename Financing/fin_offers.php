<?php
/**
 * Financing/fin_offers.php — عروض التمويل والتفاوض (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * عرض تمويل واحد بطبقة اصدارات — والتفاوض ينشئ نسخة ولا يدهس سابقتها.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-05)
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

$perms = w12_perms($conn, 'Financing/fin_offers.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_funding_offer',
                 array('orderBy' => 'offer_code, version_no DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | عروض التمويل والتفاوض';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'عروض التمويل والتفاوض'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'fin_needs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'احتياجات التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاصدارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "offer_code") ?></div><div class="ems-stat-label">عروض متمايزة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "shortlisted") ?></div><div class="ems-stat-label">ضمن القائمة القصيرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "accepted") ?></div><div class="ems-stat-label">مقبولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عروض تمويل مسجلة', 'العرض يقابل حاجة معتمدة ومن ممول مؤهل'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_offers')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود العرض' => 'g69',
        'كود الحاجة' => 'g70',
        'كود العملية الناتجة' => 'g71',
        'كود الممول' => 'g72',
        'اسم الممول (بحث)' => 'g73',
        'صفة العرض' => 'g74',
        'رقم النسخة' => 'g75',
        'النسخة الأساس' => 'g76',
        'ما الذي تغير عن سابقتها' => 'g77',
        'من اقترح التغيير' => 'g78',
        'تاريخ التغيير' => 'g79',
        'حالة التفاوض' => 'g80',
        'نموذج التمويل' => 'g81',
        'رأس المال' => 'g82',
        'العملة' => 'g83',
        'نسبة الأرباح %' => 'g84',
        'قيمة الأرباح' => 'g85',
        'المقدم' => 'g86',
        'المدة (شهر)' => 'g87',
        'عدد الأقساط' => 'g88',
        'قيمة القسط' => 'g89',
        'الضمانات' => 'g90',
        'تاريخ العرض' => 'g91',
        'Offer_Must_Precede' => 'g92',
        'عروض المنافسين' => 'g93',
        'Record_Basis' => 'g94',
        'Derivation_Rule' => 'g95',
        'Confidence' => 'g96',
        'Needs_Review' => 'g97',
        'حالة البيانات' => 'g98',
        'Source_Row_Ref' => 'g99',
        'ملاحظات' => 'g100',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fin_funding_offer');
    echo ems_w14_grid('emsList_fin_offers', $GUIDE_COLS, $__gridRows, $D, 'لا عرض تمويل مسجل بعد'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
