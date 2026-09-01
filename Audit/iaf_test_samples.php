<?php
/**
 * Audit/iaf_test_samples.php — العينات ونتائج الاختبارات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * العينة تسحب بمنهجية معلنة من مجتمع معرَّف وكل مفردة بنتيجتها
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

$perms = w14_perms($conn, 'Audit/iaf_test_samples.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_sample',
                 array('orderBy' => 'program_no, step_no, sample_no', 'limit' => 800));

$page_title = 'إيكوبيشن | العينات ونتائج الاختبارات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'العينات ونتائج الاختبارات'; $header_icon = 'fa fa-vials'; $header_actions = array();
    $header_back = array('href' => 'iaf_audit_programs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'برامج المراجعة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المفردات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "test_result", "exception") ?></div><div class="ems-stat-label">مفردات بها استثناء</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "test_result", "pass") ?></div><div class="ems-stat-label">مفردات مطابقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "finding_no") ?></div><div class="ems-stat-label">مفردات أنتجت ملاحظة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مفردات عينة', 'المفردة سطر بنتيجتها لا خلاصة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_iaf_test_samples')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_iaf_test_samples
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف المفردة' => 'g32',
        'معرف الخطوة' => 'g33',
        'مرجع المفردة في مصدرها' => 'g34',
        'المجتمع المسحوب منه' => 'g35',
        'حجم المجتمع' => 'g36',
        'أسلوب السحب' => 'g37',
        'نتيجة الفحص' => 'g38',
        'وصف الانحراف' => 'g39',
        'قيمة الأثر' => 'g40',
        'مرجع الملاحظة المتفرعة' => 'g41',
        'المنشئ' => 'g42',
        'تاريخ الإنشاء' => 'g43',
        'حالة البيانات' => 'g44',
        'مرجع المصدر' => 'g45',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('iaf_test_samples');
    echo ems_w14_grid('emsList_iaf_test_samples', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في العينات ونتائج الاختبارات'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
