<?php
/**
 * Financing/fin_covenants.php — مصفوفة الالتزامات التمويلية (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * التزام واحد بقاعدة قياسه ودوريته — وعتبته من السجل لا رقما في شيفرة.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-09)
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

$perms = w12_perms($conn, 'Financing/fin_covenants.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_covenant',
                 array('orderBy' => 'contract_id, covenant_key', 'limit' => 600));

$page_title = 'إيكوبيشن | مصفوفة الالتزامات التمويلية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مصفوفة الالتزامات التمويلية'; $header_icon = 'fa fa-scale-balanced'; $header_actions = array();
    $header_back = array('href' => 'fin_contracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل عقود التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الالتزامات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "breached") ?></div><div class="ems-stat-label">التزامات مخلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "waived") ?></div><div class="ems-stat-label">تنازلات موثقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "contract_id") ?></div><div class="ems-stat-label">عقود لها التزامات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا التزامات مسجلة', 'الالتزام يقاس بقاعدته ويوثق اخلاله او التنازل عنه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_covenants')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود العقد' => 'g174',
        'كود الممول' => 'g175',
        'اسم الممول (بحث)' => 'g176',
        'نموذج التمويل' => 'g177',
        'مستوى الحجية' => 'g178',
        'توفير رأس المال' => 'g179',
        'توقيت إتاحة التمويل' => 'g180',
        'اختيار الأصل' => 'g181',
        'اختيار البائع' => 'g182',
        'دفع قيمة الأصل للبائع' => 'g183',
        'التسليم والاستلام' => 'g184',
        'الفحص والقبول' => 'g185',
        'تسجيل الأصل' => 'g186',
        'الترخيص' => 'g187',
        'التأمين' => 'g188',
        'رسوم التأمين' => 'g189',
        'الضرائب والرسوم' => 'g190',
        'التشغيل' => 'g191',
        'الصيانة' => 'g192',
        'قطع الغيار' => 'g193',
        'مخاطر الهلاك والتلف' => 'g194',
        'حفظ مستندات الملكية' => 'g195',
        'الضمانات والرهن' => 'g196',
        'مسؤولية التعطل وعدم الانتفاع' => 'g197',
        'تحويل الملكية في نهاية التمويل' => 'g198',
        'التسوية المبكرة' => 'g199',
        'رسوم التمويل الإدارية' => 'g200',
        'الإخطارات' => 'g201',
        'توفير المستندات' => 'g202',
        'إجراءات الإقفال' => 'g203',
        'تفصيل الحسم (المرجع بسجل البنود)' => 'g204',
        'عدد الالتزامات المحسومة' => 'g205',
        'حالة اكتمال المصفوفة' => 'g206',
        'مرجع العقد/المادة' => 'g207',
        'المعبئ' => 'g208',
        'تاريخ التعبئة' => 'g209',
        'حالة البيانات' => 'g210',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fin_contract_covenant');
    echo ems_w14_grid('emsList_fin_covenants', $GUIDE_COLS, $__gridRows, $D, 'لا التزام تمويلي مسجل بعد'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
