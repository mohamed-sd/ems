<?php
/**
 * Finance/tre_transfers.php — التحويلات بين الحسابات (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * التحويلات بين الاوعية (TRS-11) — ليست دفعا لمستفيد بل مسار اخف بقاعدته وبتوقيع مفوض.
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

$perms = fin_page_perms($conn, 'Finance/tre_transfers.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_transfer', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_transfers.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | التحويلات بين الحسابات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التحويلات بين الحسابات'; $header_icon = 'fa fa-shuffle'; $header_actions = array();
    $header_back = array('href' => 'tre_cash_moves.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'حركة الخزينة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اوامر التحويل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "executed") ?></div><div class="ems-stat-label">منفذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "draft") ?></div><div class="ems-stat-label">قيد التحرير</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المحول</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اوامر تحويل', 'الوعاء لا يحول الى نفسه ولا تحويل بلا قاعدة صلاحية'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_transfers')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'رقم التحويل' => 'g158',
        'من وعاء' => 'g159',
        'إلى وعاء' => 'g160',
        'القيمة' => 'g161',
        'العملة' => 'g162',
        'الغرض' => 'g163',
        'مرجع تفويض الموقع' => 'g164',
        'مرجع التنفيذ البنكي' => 'g165',
        'تأكيد الوصول' => 'g166',
        'حالة التحويل' => 'g167',
        'المنشئ' => 'g168',
        'تاريخ الإنشاء' => 'g169',
        'حالة البيانات' => 'g170',
        'مرجع المصدر' => 'g171',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('tre_transfers');
    echo ems_w14_grid('emsList_tre_transfers', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التحويلات بين الحسابات'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('التحويلات بين الحسابات', 'التحويلات بين الحسابات'),
        'table'    => 'tre_transfers',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'finance/tre_transfers.php',
    )); ?>
</div>
</div>
</body></html>
