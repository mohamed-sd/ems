<?php
/**
 * Governance/conduct_acknowledgements.php — إقرارات مدونة السلوك (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل موظف يقر بمدونة السلوك عند التعيين وعند كل إصدار جديد والناقص يعلم
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

$perms = w14_perms($conn, 'Governance/conduct_acknowledgements.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_conduct_ack',
                 array('orderBy' => 'code_version DESC, employee_id', 'limit' => 800));

$page_title = 'إيكوبيشن | إقرارات مدونة السلوك';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إقرارات مدونة السلوك'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'policies.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل السياسات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإقرارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "acknowledged") ?></div><div class="ems-stat-label">إقرارات مكتملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "overdue") ?></div><div class="ems-stat-label">إقرارات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "code_version") ?></div><div class="ems-stat-label">الإصدارات المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إقرارات مسجلة', 'الإقرار سطر بدليله لا خانة اختيار'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_conduct_acknowledgements')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإقرار' => 'ack_no',
        'رقم الموظف' => 'employee_id',
        'إصدار المدونة' => 'code_version',
        'قناة الإقرار' => 'ack_channel',
        'تاريخ الإقرار' => 'acked_at',
        'حالة الإقرار' => '@state',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array();
    echo ems_w14_grid('emsList_conduct_acknowledgements', $GUIDE_COLS, $rows, $D, 'لا إقرارات مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('اقرارات مدونه السلوك', 'إقرارات مدونة السلوك'),
        'table'    => 'gov_conduct_ack',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/conduct_acknowledgements.php',
    )); ?>
</div>
</div>
</body></html>
