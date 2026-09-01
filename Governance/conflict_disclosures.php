<?php
/**
 * Governance/conflict_disclosures.php — تضارب المصالح (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الإفصاح واجب والقرار للحوكمة ولا يشارك صاحب الإفصاح في قرار محل التضارب
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

$perms = w14_perms($conn, 'Governance/conflict_disclosures.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_conflict_disclosure',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | تضارب المصالح';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تضارب المصالح'; $header_icon = 'fa fa-user-shield'; $header_actions = array();
    $header_back = array('href' => 'auth_profiles.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الأدوار وقوالب صلاحياتها');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإفصاحات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "disclosed") ?></div><div class="ems-stat-label">إفصاحات قيد التقييم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "recused") ?></div><div class="ems-stat-label">حالات تجنيب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "rejected") ?></div><div class="ems-stat-label">حالات مرفوضة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إفصاحات مسجلة', 'الإفصاح سطر بقراره لا استمارة تحفظ'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_conflict_disclosures')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإفصاح' => 'disclosure_no',
        'المفصح' => '#person',
        'صفة المفصح' => '#capacity',
        'طبيعة التضارب' => 'nature_ar',
        'الطرف الآخر' => 'counterparty_ar',
        'العلاقة' => 'relation_ar',
        'القرار المتأثر المحتمل' => 'recused_from',
        'قرار الحوكمة' => '#decision',
        'الضوابط المفروضة' => 'controls_ar',
        'مراجعة دورية' => '#next_review',
        'حالة الإفصاح' => '@state',
        'تاريخ الإنشاء' => 'disclosed_at',
        'المراجع' => '#assessor',
        'تاريخ الاعتماد' => 'approved_at',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        'person'   => function ($r) { return ems_w14_person($r['person_id']); },
        'capacity' => function ($r) { return ems_w14_person_role($r['person_id']); },
        /* القرارُ ومرجعُه معًا — فقرارٌ بلا مرجعٍ لا يُراجَع */
        'decision' => function ($r) {
            $d = trim((string) $r['decision']);
            $d = $d === '' ? '' : ems_w14_ar($d);
            $x = trim((string) $r['decision_ref']);
            return ($d !== '' && $x !== '') ? ($d . ' (' . $x . ')') : ($d !== '' ? $d : $x);
        },
        /* المراجعةُ الدوريّةُ للإفصاحِ القائم: سنةٌ من تاريخِ الإفصاح */
        'next_review' => function ($r) { return ems_w14_year_after($r['disclosed_at']); },
        'assessor' => function ($r) { return ems_w14_person($r['assessed_by']); },
    );
    echo ems_w14_grid('emsList_conflict_disclosures', $GUIDE_COLS, $rows, $D, 'لا إفصاحات مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('تضارب المصالح', 'تضارب المصالح'),
        'table'    => 'gov_conflict_disclosure',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/conflict_disclosures.php',
    )); ?>
</div>
</div>
</body></html>
