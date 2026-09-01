<?php
/**
 * Portal/exec_delegations.php — الإنابات والتفويضات (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * الانابة لا تغير الهيكل والمصدر النافذ سجل التفويضات عند الحوكمة والمنتهية ليست انابة
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة الحوكمة والالتزام** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
 *
 * ◆ **والرؤيةُ لا تساوي السلطة**: هذا سطحُ قراءةٍ بلا فعلِ كتابة؛ والقرارُ
 *   يمرُّ بمحرّكِ الاعتمادِ نفسِه عند مالكِ المستند لا من هنا.
 *
 * ◆ **والنطاقُ من محرّكٍ واحد**: الرئيسُ يرى الشركةَ والنائبُ نطاقَه
 *   والموظّفُ صفوفَه — بالشيفرةِ نفسِها. ⛔ ولا ثلاثةَ أنظمة.
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

$perms = w15_perms($conn, 'Portal/exec_delegations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'valid_from DESC, delegation_id DESC', 'limit' => 300, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'gov_delegations', $vis, $opt);

$page_title = 'إيكوبيشن | الإنابات والتفويضات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإنابات والتفويضات'; $header_icon = 'fa fa-user-shield'; $header_actions = array();
    $header_back = array('href' => 'ceo_assignments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التكليفات والإنابات المؤقتة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإنابات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_empty($rows, "revoked_at") ?></div><div class="ems-stat-label">إنابات غير ملغاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "revoked_at") ?></div><div class="ems-stat-label">إنابات ملغاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "reason") ?></div><div class="ems-stat-label">إنابات لها سبب مكتوب</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إنابات مسجلة', 'الإنابة مدة بنطاقها لا صفة دائمة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_delegations')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'Delegation_ID' => 'g21',
        'Delegate_From' => 'g22',
        'Delegate_To' => 'g23',
        'Scope' => 'g24',
        'From_Date' => 'g25',
        'To_Date' => 'g26',
        'Approval_Level' => 'g27',
        'Exclusions' => 'g28',
        'Status' => 'g29',
        'مرجع سجل الحوكمة' => 'g30',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('dvp_delegations');
    echo ems_w14_grid('emsList_exec_delegations', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الإنابات والتفويضات'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
