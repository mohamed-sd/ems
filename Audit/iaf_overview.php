<?php
/**
 * Audit/iaf_overview.php — لوحة المراجعة الداخلية (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * قراءة حية مشتقة من الملاحظات ومتابعتها ولا إدخال فيها
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ نطاقِه
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد.**
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w14_view.php';
require_once __DIR__ . '/../includes/w14_grid.php';

$ctx = w14_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w14_perms($conn, 'Audit/iaf_overview.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_findings',
                 array('orderBy' => 'id DESC', 'limit' => 300));

$page_title = 'إيكوبيشن | لوحة المراجعة الداخلية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة المراجعة الداخلية'; $header_icon = 'fa fa-clipboard-check'; $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الرئيسية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الملاحظات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "open") ?></div><div class="ems-stat-label">ملاحظات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "severity", "critical") ?></div><div class="ems-stat-label">ملاحظات حرجة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "escalated") ?></div><div class="ems-stat-label">ملاحظات مصعدة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا ملاحظات مسجلة', 'اللوحة قراءة مشتقة لا شاشة إدخال'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_iaf_overview')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_iaf_overview
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف المؤشر' => 'g55',
        'المؤشر KPI Catalog' => 'g56',
        'الفترة' => 'g57',
        'القيمة' => 'g58',
        'المستهدف' => 'g59',
        'الانحراف' => 'g60',
        'الحالة' => 'g61',
        'آخر تحديث' => 'g62',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('iaf_dashboard_kpi');
    echo ems_w14_grid('emsList_iaf_overview', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في لوحة المراجعة الداخلية'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
