<?php
/**
 * Governance/committees.php — اللجان وحوكمة الاجتماعات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * اللجان النافذة بتشكيلها وصلاحياتها ودورية انعقادها
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

$perms = w14_perms($conn, 'Governance/committees.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_committee',
                 array('orderBy' => 'committee_code', 'limit' => 300));

$page_title = 'إيكوبيشن | اللجان وحوكمة الاجتماعات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'اللجان وحوكمة الاجتماعات'; $header_icon = 'fa fa-users-rectangle'; $header_actions = array();
    $header_back = array('href' => 'doc_types.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل أنواع المستندات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد اللجان</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "active") ?></div><div class="ems-stat-label">لجان نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "dissolved") ?></div><div class="ems-stat-label">لجان منحلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "charter_ref") ?></div><div class="ems-stat-label">لجان لها ميثاق</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا لجان مسجلة', 'اللجنة تشكيل بميثاقه لا اسم في محضر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_committees')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'كود اللجنة' => 'committee_code',
        'اسم اللجنة' => 'name_ar',
        'الاختصاص' => 'mandate_ar',
        'التشكيل' => '#members',
        'الرئيس' => '#chair',
        'النصاب' => 'quorum_key',
        'دورية الانعقاد' => '@meeting_cycle',
        'مرجع قرار التشكيل' => 'charter_ref',
        'حالة اللجنة' => '@state',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        'members' => function ($r) {
            $n = (int) $r['member_count'];
            return $n > 0 ? ($n . ' عضوا') : '';
        },
        'chair' => function ($r) { return ems_w14_person($r['chair_person']); },
    );
    echo ems_w14_grid('emsList_committees', $GUIDE_COLS, $rows, $D, 'لا لجان مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('اللجان وحوكمه الاجتماعات', 'اللجان وحوكمة الاجتماعات'),
        'table'    => 'gov_committee',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/committees.php',
    )); ?>
</div>
</div>
</body></html>
