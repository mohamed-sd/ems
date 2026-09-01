<?php
/**
 * Finance/tre_fx_deals.php — تنفيذ عمليات الصرف الأجنبي (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * الصرف الاجنبي (TRS-12) — الشراء والبيع الفعلي بسعر الصفقة الموثق وجدول الاسعار للمقارنة لا للاحلال.
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

$perms = fin_page_perms($conn, 'Finance/tre_fx_deals.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_fx_deal', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_fx_deals.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | تنفيذ عمليات الصرف الأجنبي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تنفيذ عمليات الصرف الأجنبي'; $header_icon = 'fa fa-coins'; $header_actions = array();
    $header_back = array('href' => 'currencies_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أسعار الصرف');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الصفقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "deal_kind", "buy") ?></div><div class="ems-stat-label">صفقات شراء</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "deal_kind", "sell") ?></div><div class="ems-stat-label">صفقات بيع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "buy_currency") ?></div><div class="ems-stat-label">ازواج عملات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا صفقات صرف', 'الصفقة بمستندها وبسعرها الموثق'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_fx_deals')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'رقم الصفقة' => 'g63',
        'تاريخ التنفيذ' => 'g64',
        'العملة المشتراة' => 'g65',
        'العملة المدفوعة' => 'g66',
        'المبلغ المشترى' => 'g67',
        'سعر الصفقة' => 'g68',
        'المكافئ' => 'g69',
        'السعر المرجعي م09' => 'g70',
        'الفرق عن المرجعي' => 'g71',
        'جهة التنفيذ' => 'g72',
        'الغرض' => 'g73',
        'مرجع الحركة' => 'g74',
        'حالة الصفقة' => 'g75',
        'المنشئ' => 'g76',
        'تاريخ الإنشاء' => 'g77',
        'حالة البيانات' => 'g78',
        'مرجع المصدر' => 'g79',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_fx_deals');
    echo ems_w14_grid('emsList_tre_fx_deals', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تنفيذ عمليات الصرف الأجنبي'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
