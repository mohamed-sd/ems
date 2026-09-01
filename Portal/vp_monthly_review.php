<?php
/**
 * Portal/vp_monthly_review.php — المراجعة الشهرية للنائب (VP-05)
 * ───────────────────────────────────────────────────────────────────────────
 * **جزءٌ من تجهيزِ Executive Monthly Pack بالنطاق** (‏`VP-05` نصًّا) —
 * Grain: **محورٌ × شهرٌ ضمن النطاق**.
 *
 * ◆ المحرّكُ محرّكُ `exec_monthly_pack` نفسُه (`w15_view.php` ⇒
 *   `ScopeEngine::visibility`) — النطاقُ يضيق بهويّةِ النائبِ تلقائيًّا،
 *   ⛔ ولا نسخةَ دوريّةً ولا مخزنَ محلّيًّا.
 * ◆ إسقاطٌ لا مصدر: الحزمةُ تتجمّع من إقفالاتِ الإداراتِ الشهريّة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w15_view.php';

$ctx = w15_ctx();
$is_super = $ctx['is_super'];
if (!$is_super && $ctx['company_id'] <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w15_perms($conn, 'Portal/vp_monthly_review.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'accounting_month DESC, id DESC', 'limit' => 300, 'scope_col' => 'entity_id');
$rows = w15_rows($is_super, 'fin_monthly_close', $vis, $opt);

$page_title = 'إيكوبيشن | المراجعة الشهرية للنائب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'المراجعة الشهرية للنائب: المحرك العام نفسه والنطاق نطاقك'; $header_icon = 'fa fa-calendar-check'; $header_actions = array();
    $header_back = array('href' => 'vp_weekly_report.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التقرير الأسبوعي للنائب');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">إقفالات ضمن نطاقك</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "approved") ?></div><div class="ems-stat-label">إقفالات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "rollforward_ok", "1") ?></div><div class="ems-stat-label">إقفالات متصلة بالسابق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "accounting_month") ?></div><div class="ems-stat-label">الأشهر المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إقفالات شهرية ضمن نطاقك بعد', 'الحزمة تتجمع من إقفالات الإدارات بنطاقك'); ?>

    <?php require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_vp_monthly_review')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g93',
        'Deputy_Role' => 'g17',
        'الشهر' => 'g94',
        'المحور' => 'g95',
        'البند' => 'g96',
        'الفعلي' => 'g97',
        'المستهدف' => 'g98',
        'الانحراف' => 'g99',
        'توقع الشهر الكامل' => 'g101',
        'مراجعة النائب Decision Event' => 'g18',
        'اكتملت المراجعة؟' => 'g19',
        'انعكس في حزمة الرئيس' => 'g20',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('exec_monthly_pack');
    echo ems_w14_grid('emsList_vp_monthly_review', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في المراجعة الشهرية للنائب'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
