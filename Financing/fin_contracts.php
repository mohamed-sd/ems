<?php
/**
 * Financing/fin_contracts.php — سجل عقود التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * عقد تمويل واحد بمستنده — ومن اعده لا يوقعه.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-07)
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

$perms = w12_perms($conn, 'Financing/fin_contracts.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_finance_contract',
                 array('orderBy' => 'id DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | سجل عقود التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل عقود التمويل'; $header_icon = 'fa fa-file-contract'; $header_actions = array();
    $header_back = array('href' => 'fin_precontract_review.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مراجعة ما قبل التعاقد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد العقود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "active") ?></div><div class="ems-stat-label">عقود سارية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "closed") ?></div><div class="ems-stat-label">عقود مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "principal")) ?></div><div class="ems-stat-label">اجمالي اصل التمويل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقود تمويل مسجلة', 'العقد يفتح العملية ويولد جدول الاقساط'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_contracts')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود العقد' => 'g121',
        'مصدر العقد' => 'g122',
        'مرجع العقد بالمصدر' => 'g123',
        'المرجع الخارجي (رقم عقد الممول)' => 'g124',
        'الكيان المتعاقد (الشركة)' => 'g125',
        'رقم النسخة' => 'g126',
        'تاريخ التوقيع' => 'g127',
        'كود الممول' => 'g128',
        'اسم الممول (بحث)' => 'g129',
        'نموذج التمويل' => 'g130',
        'العملة' => 'g131',
        'رأس المال' => 'g132',
        'بداية العقد' => 'g133',
        'آخر حركة' => 'g134',
        'المدة (شهر)' => 'g135',
        'النهاية التعاقدية' => 'g136',
        'حالة المستند' => 'g137',
        'حالة المراجعة القانونية' => 'g138',
        'حالة الاعتماد' => 'g139',
        'من اعتمد' => 'g140',
        'تاريخ الاعتماد' => 'g141',
        'مرجع آخر ملحق نافذ' => 'g142',
        'آلية الإنهاء/الإقفال' => 'g143',
        'عدد العمليات تحته' => 'g144',
        'حالة العقد' => 'g145',
        'الحجية' => 'g146',
        'حالة البيانات' => 'g147',
        'Source_Row_Ref' => 'g148',
        'ملاحظات' => 'g149',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fin_finance_contract');
    echo ems_w14_grid('emsList_fin_contracts', $GUIDE_COLS, $__gridRows, $D, 'لا عقد تمويل مسجل بعد'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
