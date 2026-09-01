<?php
/**
 * Finance/tre_petty_cash.php — عهد النثرية وتسويتها (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * عهد النثرية (TRS-17) — العهدة بحد وسقف زمني ولا تجديد قبل تسوية السابقة بمستنداتها.
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

$perms = fin_page_perms($conn, 'Finance/tre_petty_cash.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_petty_custody', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_petty_cash.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | عهد النثرية وتسويتها';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'عهد النثرية وتسويتها'; $header_icon = 'fa fa-wallet'; $header_actions = array();
    $header_back = array('href' => 'payments_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'المدفوعات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">العهد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "open") ?></div><div class="ems-stat-label">عهد مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "settled") ?></div><div class="ems-stat-label">عهد مسواة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "spent_amount"), 2) ?></div><div class="ems-stat-label">مجموع المصروف</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عهد نثرية', 'لا تجديد قبل تسوية العهدة السابقة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_petty_cash')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف العهدة' => 'g116',
        'أمين العهدة' => 'g117',
        'الموقع' => 'g118',
        'حد العهدة' => 'g119',
        'تاريخ الفتح' => 'g120',
        'السقف الزمني' => 'g121',
        'المصروف الموثق' => 'g122',
        'المستندات المرفقة' => 'g123',
        'المتبقي' => 'g124',
        'تاريخ التسوية' => 'g125',
        'نتيجة التسوية' => 'g126',
        'التجديد' => 'g127',
        'حالة العهدة' => 'g128',
        'المنشئ' => 'g129',
        'تاريخ الإنشاء' => 'g130',
        'حالة البيانات' => 'g131',
        'مرجع المصدر' => 'g132',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_petty_cash');
    echo ems_w14_grid('emsList_tre_petty_cash', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في عهد النثرية وتسويتها'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
