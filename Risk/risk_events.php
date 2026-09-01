<?php
/**
 * Risk/risk_events.php — أحداث المخاطر والخسائر (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الحدث يقرأ مصدره بمرجعه ولا ينسخه والتوقف يسجل في مصدره التشغيلي
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

$perms = w14_perms($conn, 'Risk/risk_events.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'rsk_event',
                 array('orderBy' => 'occurred_at DESC, id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | أحداث المخاطر والخسائر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'أحداث المخاطر والخسائر'; $header_icon = 'fa fa-bolt'; $header_actions = array();
    $header_back = array('href' => 'risk_register.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل المخاطر المؤسسي');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الأحداث</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "event_kind", "loss") ?></div><div class="ems-stat-label">أحداث خسارة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "event_kind", "near_miss") ?></div><div class="ems-stat-label">أحداث كادت تقع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "deviation_no") ?></div><div class="ems-stat-label">أحداث لها انحراف مرجعي</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أحداث مسجلة', 'الحدث مرجع لمصدره لا نسخة منه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_risk_events')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_risk_events
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الحدث' => 'g23',
        'Risk_ID' => 'g24',
        'مصدر الحدث' => 'g25',
        'مفتاح السجل الأصلي' => 'g26',
        'قراءة الحدث' => 'g27',
        'السبب من مصدره' => 'g28',
        'الجهة المتسببة' => 'g29',
        'المدة/الحجم' => 'g30',
        'الأثر الإنتاجي' => 'g31',
        'الأثر المالي' => 'g32',
        'الأثر التعاقدي' => 'g33',
        'تكرار السبب' => 'g34',
        'قاعدة الخطر المتحققة' => 'g35',
        'حالة الحدث' => 'g36',
        'المنشئ' => 'g37',
        'تاريخ الإنشاء' => 'g38',
        'حالة البيانات' => 'g39',
        'مرجع المصدر' => 'g40',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('rsk_risk_events');
    echo ems_w14_grid('emsList_risk_events', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في أحداث المخاطر والخسائر'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
