<?php
/**
 * Audit/iaf_function_risks.php — مخاطر وظيفة المراجعة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * مخاطر الوظيفة نفسها ترفع لخط الرفع بالميثاق لا للإدارة التنفيذية
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

$perms = w14_perms($conn, 'Audit/iaf_function_risks.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_function_risk',
                 array('orderBy' => 'risk_no', 'limit' => 300));

$page_title = 'إيكوبيشن | مخاطر وظيفة المراجعة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مخاطر وظيفة المراجعة'; $header_icon = 'fa fa-user-secret'; $header_actions = array();
    $header_back = array('href' => 'iaf_quality.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تقييم الجودة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المخاطر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "risk_kind", "INDEPENDENCE_LOSS") ?></div><div class="ems-stat-label">مخاطر فقد الاستقلال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "treated") ?></div><div class="ems-stat-label">مخاطر معالجة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "reported_to") ?></div><div class="ems-stat-label">مخاطر مرفوعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مخاطر مسجلة', 'الخطر سطر بمعالجته لا ملاحظة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_iaf_function_risks')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_iaf_function_risks
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g46',
        'Risk_ID بسجل المخاطر' => 'g47',
        'نوع الخطر' => 'g48',
        'الوصف' => 'g49',
        'المستوى المتبقي' => 'g50',
        'الضابط القائم' => 'g51',
        'المعالجة' => 'g52',
        'المالك' => 'g53',
        'حالة الخطر' => 'g54',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('iaf_function_risks');
    echo ems_w14_grid('emsList_iaf_function_risks', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مخاطر وظيفة المراجعة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
