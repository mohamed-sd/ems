<?php
/**
 * Portal/exec_daily_report.php — التقرير اليومي التنفيذي (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * تقرير يومي مجمع عن المواقع والمشروعات يتجمع من يوميات المواقع المقفلة ولا يدخل هنا رقم
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة الموقع** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_daily_report.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'day_date DESC, id DESC', 'limit' => 400, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'site_day', $vis, $opt);

$page_title = 'إيكوبيشن | التقرير اليومي التنفيذي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التقرير اليومي التنفيذي'; $header_icon = 'fa fa-calendar-day'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد اليوميات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "closed") ?></div><div class="ems-stat-label">يوميات مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "open") ?></div><div class="ems-stat-label">يوميات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "project_id") ?></div><div class="ems-stat-label">المشروعات المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا يوميات مقفلة بعد', 'التقرير يتجمع من يوميات المواقع ولا يكتب هنا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_daily_report')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g35',
        'التاريخ' => 'g36',
        'المشروع' => 'g37',
        'الموقع' => 'g38',
        'الخطة اليومية' => 'g39',
        'المنفذ' => 'g40',
        'نسبة الإنجاز' => 'g41',
        'ساعات تشغيل المعدات' => 'g42',
        'إجمالي ساعات التوقف' => 'g43',
        'عدد أنواع التوقف تفصيلها ر03-2' => 'g44',
        'المعدات العاملة' => 'g45',
        'المعدات المتوقفة' => 'g46',
        'الأعطال الحرجة' => 'g47',
        'القوى الموجودة' => 'g48',
        'النقص' => 'g49',
        'إصابات مضيعة للوقت LTI' => 'g50',
        'حوادث عالية الجهد HiPo' => 'g51',
        'أحداث إيقاف العمل Stop-Work' => 'g52',
        'الحوادث البيئية' => 'g53',
        'البلاغات الحرجة' => 'g54',
        'الترحيلات المهمة' => 'g55',
        'المواد الحرجة' => 'g56',
        'عدد القرارات المطلوبة تفصيلها ر03-3' => 'g57',
        'عدد الانحرافات الحرجة تفصيلها ر03-3' => 'g58',
        'رابط النزول للسجل الأصلي' => 'g59',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('exec_daily_report');
    echo ems_w14_grid('emsList_exec_daily_report', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التقرير اليومي التنفيذي'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
