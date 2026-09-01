<?php
/**
 * Risk/risk_escalations.php — تصعيدات المخاطر (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الاختراق الحرج أو خروج الشهية أو تأخر المعالجة الجوهري يصعد بمساره
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

$perms = w14_perms($conn, 'Risk/risk_escalations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'risk_escalations',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | تصعيدات المخاطر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تصعيدات المخاطر'; $header_icon = 'fa fa-arrow-up-right-dots'; $header_actions = array();
    $header_back = array('href' => 'risk_acceptance.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'قبول المخاطر');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التصعيدات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "is_auto", "1") ?></div><div class="ems-stat-label">تصعيدات آلية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "acknowledged_by") ?></div><div class="ems-stat-label">تصعيدات مستلمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "to_authority") ?></div><div class="ems-stat-label">الجهات المصعد إليها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تصعيدات مسجلة', 'التصعيد واقعة بسببها لا تنبيه يمر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_risk_escalations')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_risk_escalations
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف التصعيد' => 'g57',
        'Risk_ID' => 'g58',
        'مسبب التصعيد' => 'g59',
        'المستوى' => 'g60',
        'المخطر' => 'g61',
        'وقت التصعيد' => 'g62',
        'الاستجابة/القرار' => 'g63',
        'مرجع قيادة ر11' => 'g64',
        'حالة التصعيد' => 'g65',
        'المنشئ' => 'g66',
        'تاريخ الإنشاء' => 'g67',
        'حالة البيانات' => 'g68',
        'مرجع المصدر' => 'g69',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('rsk_risk_escalations');
    echo ems_w14_grid('emsList_risk_escalations', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تصعيدات المخاطر'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
