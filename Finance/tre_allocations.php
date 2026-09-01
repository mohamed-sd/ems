<?php
/**
 * Finance/tre_allocations.php — تخصيص التحصيل على الفواتير (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * تخصيص التحصيل (TRS-07) — الدفعة الواحدة قد تغطي عدة فواتير وكل تخصيص سطر.
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

$perms = fin_page_perms($conn, 'Finance/tre_allocations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_collection_allocations', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_allocations.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | تخصيص التحصيل على الفواتير';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تخصيص التحصيل على الفواتير'; $header_icon = 'fa fa-arrows-split-up-and-left'; $header_actions = array();
    $header_back = array('href' => 'tre_pay_batch.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اسطر التخصيص</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "payment_id") ?></div><div class="ems-stat-label">سندات مخصصة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "receivable_id") ?></div><div class="ems-stat-label">فواتير مغطاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المخصص</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اسطر تخصيص', 'المتبقي على كل فاتورة يشتق من التخصيصات لا يكتب بيد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_allocations')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف التخصيص' => 'g147',
        'معرف التحصيل' => 'g148',
        'رقم الفاتورة' => 'g149',
        'قيمة الفاتورة' => 'g150',
        'المخصص عليها' => 'g151',
        'المتبقي بعده' => 'g152',
        'حالة الفاتورة بعده' => 'g153',
        'المنشئ' => 'g154',
        'تاريخ الإنشاء' => 'g155',
        'حالة البيانات' => 'g156',
        'مرجع المصدر' => 'g157',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_allocations');
    echo ems_w14_grid('emsList_tre_allocations', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تخصيص التحصيل على الفواتير'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
