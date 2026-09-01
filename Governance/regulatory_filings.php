<?php
/**
 * Governance/regulatory_filings.php — التقديمات النظامية (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل إقرار وتقديم نظامي بموعده وإيصاله والمتأخر يعلم ويصعد
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

$perms = w14_perms($conn, 'Governance/regulatory_filings.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_filing',
                 array('orderBy' => 'due_date DESC, filing_no', 'limit' => 500));

$page_title = 'إيكوبيشن | التقديمات النظامية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التقديمات النظامية'; $header_icon = 'fa fa-file-arrow-up'; $header_actions = array();
    $header_back = array('href' => 'licenses_guarantees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التراخيص والكفالات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التقديمات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "acknowledged") ?></div><div class="ems-stat-label">تقديمات باستلام موثق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "late") ?></div><div class="ems-stat-label">تقديمات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "authority_ar") ?></div><div class="ems-stat-label">الجهات المستقبلة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تقديمات مسجلة', 'التقديم سطر بإيصاله لا خانة تعليم'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_regulatory_filings')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف التقديم' => 'filing_no',
        'نوع التقديم' => 'filing_kind',
        'الجهة' => 'authority_ar',
        'الكيان' => '#entity',
        'الفترة المشمولة' => 'period_label',
        'الموعد النظامي' => 'due_date',
        'تاريخ التقديم الفعلي' => 'submitted_at',
        'إيصال/مرجع التقديم' => 'receipt_ref',
        'مرجع الالتزام' => 'obligation_no',
        'حالة التقديم' => '@state',
        'المنشئ' => '#submitter',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        'entity'    => function ($r) { return ems_w14_company($r['company_id']); },
        'submitter' => function ($r) { return ems_w14_person($r['submitted_by']); },
    );
    echo ems_w14_grid('emsList_regulatory_filings', $GUIDE_COLS, $rows, $D, 'لا تقديمات مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('التقديمات النظاميه', 'التقديمات النظامية'),
        'table'    => 'gov_filing',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/regulatory_filings.php',
    )); ?>
</div>
</div>
</body></html>
