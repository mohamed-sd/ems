<?php
/**
 * Finance/tre_guarantees.php — خطابات الضمان والاعتمادات المستندية (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * خطابات الضمان والاعتمادات (TRS-15) — الاصدار على تسهيله وبقاعدته والكفالات النظامية عند الحوكمة.
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

$perms = fin_page_perms($conn, 'Finance/tre_guarantees.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_guarantee', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_guarantees.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | خطابات الضمان والاعتمادات المستندية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'خطابات الضمان والاعتمادات المستندية'; $header_icon = 'fa fa-file-shield'; $header_actions = array();
    $header_back = array('href' => 'funding_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التسهيلات البنكية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الخطابات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "issued") ?></div><div class="ems-stat-label">صادرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "released") ?></div><div class="ems-stat-label">مفرج عنها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المبالغ</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا خطابات صادرة', 'لا اصدار بلا تسهيل ولا بلا قاعدة صلاحية'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_guarantees')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الأداة' => 'g96',
        'النوع' => 'g97',
        'المستفيد' => 'g98',
        'العقد المرتبط' => 'g99',
        'القيمة' => 'g100',
        'العملة' => 'g101',
        'نسبة الغطاء النقدي' => 'g102',
        'التسهيل المستخدم' => 'g103',
        'تاريخ الإصدار' => 'g104',
        'تاريخ الانتهاء' => 'g105',
        'التمديدات' => 'g106',
        'مرجع ح04 النظامي' => 'g107',
        'حالة الأداة' => 'g108',
        'المنشئ' => 'g109',
        'تاريخ الإنشاء' => 'g110',
        'المراجع' => 'g111',
        'المعتمد' => 'g112',
        'تاريخ الاعتماد' => 'g113',
        'حالة البيانات' => 'g114',
        'مرجع المصدر' => 'g115',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_guarantees');
    echo ems_w14_grid('emsList_tre_guarantees', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في خطابات الضمان والاعتمادات المستندية'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('خطابات الضمان والاعتمادات المستنديه', 'خطابات الضمان والاعتمادات المستندية'),
        'table'    => 'tre_guarantees',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'finance/tre_guarantees.php',
    )); ?>
</div>
</div>
</body></html>
