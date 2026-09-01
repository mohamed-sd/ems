<?php
/**
 * Portal/exec_contract_registry.php — سجل العقود الموحد (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * نافذة قراءة واحدة تجمع العقود من سجلات مالكيها والقيادة لا تملك العقد ولا سجله
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة المبيعات التعاقدية والعقود** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_contract_registry.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'contract_signing_date DESC, id DESC', 'limit' => 500, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'contracts', $vis, $opt);

$page_title = 'إيكوبيشن | سجل العقود الموحد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل العقود الموحد'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد العقود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "contract_status", "نافذ") ?></div><div class="ems-stat-label">عقود نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "contract_status", "مقفل") ?></div><div class="ems-stat-label">عقود مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "signing_authority_ref") ?></div><div class="ems-stat-label">عقود بمرجع سلطة توقيع</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقود مسجلة', 'السجل نافذة قراءة فوق سجلات مالكيها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_contract_registry')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g135',
        'نوع العقد' => 'g136',
        'الإدارة المالكة' => 'g137',
        'مرجع العقد في مصدره' => 'g138',
        'الكيان' => 'g139',
        'الطرف الآخر' => 'g140',
        'القيمة' => 'g141',
        'العملة' => 'g142',
        'نموذج العمل' => 'g143',
        'سريان من' => 'g144',
        'إلى' => 'g145',
        'أيام حتى الانتهاء' => 'g146',
        'عدد الالتزامات الحرجة القائمة' => 'g147',
        'عدد الكفالات والضمانات المرتبطة' => 'g148',
        'حالة العقد' => 'g149',
        'مرجع التوقيع' => 'g150',
        'رابط النزول للسجل الأصلي' => 'g151',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('exec_contract_registry');
    echo ems_w14_grid('emsList_exec_contract_registry', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في سجل العقود الموحد'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
