<?php
/**
 * Finance/acc_closing_checklist.php — قائمة إقفال الفترة (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * قائمة اقفال الفترة (ACC-22) — لا اقفال قبل اكتمال البنود او توثيق استثناء كل ناقص بقرار.
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

$perms = fin_page_perms($conn, 'Finance/acc_closing_checklist.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_closing_items', array('orderBy' => 'period_id DESC, id', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_closing_checklist.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | قائمة إقفال الفترة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قائمة إقفال الفترة'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'periods_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الفترات المحاسبية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">بنود القائمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "item_state", "done") ?></div><div class="ems-stat-label">بنود مكتملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_blocking($rows) ?></div><div class="ems-stat-label">بنود تحجب الاقفال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_excepted($rows) ?></div><div class="ems-stat-label">استثناءات موثقة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود في قائمة الاقفال', 'البند الناقص يحجب الاقفال ما لم يوثق استثناؤه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_closing_checklist')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف البند' => 'g103',
        'الفترة' => 'g104',
        'بند الفحص' => 'g105',
        'المسؤول' => 'g106',
        'مرجع الإنجاز' => 'g107',
        'النتيجة' => 'g108',
        'استثناء موثق' => 'g109',
        'وقت الإنجاز' => 'g110',
        'حالة البند' => 'g111',
        'المنشئ' => 'g112',
        'تاريخ الإنشاء' => 'g113',
        'حالة البيانات' => 'g114',
        'مرجع المصدر' => 'g115',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('fina_closing_checklist');
    echo ems_w14_grid('emsList_acc_closing_checklist', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في قائمة إقفال الفترة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
