<?php
/**
 * Workforce/wf_coverage.php — لوحةُ القوى: المطلوبُ مقابل المتوفّر (RPR-W05 · WRK-01/03)
 * ───────────────────────────────────────────────────────────────────────────
 * **سطرُ فجوةٍ مشتقٌّ من الاحتياجِ المعتمدِ والمتوفّرِ الجاهز** (‏`WRK-03` نصًّا)
 * — والسطحُ **بلا نموذجِ إدخالٍ واحد**.
 *
 * ◆ **والمُدخَلُ الحيُّ يبقى مرآةً بفارقِها لا يُدهَس**: `workforce_requirement`
 *   يحمل `shortage_qty` و`state` مُدخَلَين. والمقيسُ حيًّا: **٢٠ صفًّا · ١٩ منها
 *   عجزُه يخالف حسابَه · و١٤ حالتُه تخالف حسابَها** — رقمٌ يُقرأ صحيحًا وهو خطأ.
 *   فيُعرَض المشتقُّ والمُعلَنُ جنبًا إلى جنبٍ ووسمُ الفارق، وحسمُ الفارقِ عند
 *   مالكِ الاحتياجِ لا في شاشةِ قراءة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Fleet/AssetLifecycleService.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/wf_coverage.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('wf coverage super') : ems_tenant_db();

$rows = array(); $projects = array();
try { $rows = $gate->select('wf_coverage', array('orderBy' => 'gap_qty DESC, requirement_id ASC', 'limit' => 500)); }
catch (\Throwable $t) { error_log('wf_coverage.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('project', array('columns' => array('id', 'name'), 'orderBy' => 'id DESC', 'limit' => 400)) as $p) { $projects[(int) $p['id']] = (string) $p['name']; } }
catch (\Throwable $t) { error_log('wf_coverage.php projects: ' . $t->getMessage()); }

/* مؤشّراتُ لوحةِ القوى (‏WRK-01) — كلُّها عدٌّ حيٌّ عبرَ البوابةِ لا حقولٌ محفوظة */
$kpi = array('need' => 0, 'gap' => 0, 'variance' => 0, 'assigned' => 0, 'moves' => 0);
foreach ($rows as $r) {
    $kpi['need'] += (int) $r['required_qty'];
    $kpi['gap']  += (int) $r['gap_qty'];
    if ($r['variance_rule'] === 'W5_COVERAGE_VARIANCE_OPEN') { $kpi['variance']++; }
}
try { $kpi['assigned'] = (int) $gate->count('org_assignments', array('where' => array('state' => 'active'))); }
catch (\Throwable $t) { error_log('wf_coverage.php assigned: ' . $t->getMessage()); }
try { $kpi['moves'] = (int) $gate->count('worker_movement', array()); }
catch (\Throwable $t) { error_log('wf_coverage.php moves: ' . $t->getMessage()); }

$STATE_AR = array('SHORTAGE' => 'عجز', 'SURPLUS' => 'فائض', 'BALANCED' => 'متوازن',
                  'PLANNED' => 'مخطط', 'UNMAPPED' => 'خارج الجسر');

$page_title = 'إيكوبيشن | لوحة القوى — المطلوب مقابل المتوفر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة القوى — المطلوب مقابل المتوفر (مشتق لا مدخل)'; $header_icon = 'fa fa-people-group'; $header_actions = array();
    $header_back = array('href' => 'workforce_requirement.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'احتياج المشروع');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">سطور تغطية مشتقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $kpi['need'] ?></div><div class="ems-stat-label">إجمالي المطلوب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $kpi['gap'] ?></div><div class="ems-stat-label">إجمالي العجز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $kpi['variance'] ?></div><div class="ems-stat-label">فارق مفتوح بين المشتق والمعلن</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $kpi['assigned'] ?></div><div class="ems-stat-label">تكاليف تنظيمية نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $kpi['moves'] ?></div><div class="ems-stat-label">وقائع حركة وتواجد</div></div>
    </div>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سطور تغطية مشتقة بعد', 'الاشتقاق يجري من احتياج المشروع والمتوفر الجاهز — ولا يدخل من هنا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_wf_coverage')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_wf_coverage
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف السطر' => 'g1',
        'المشروع' => 'g2',
        'الفئة' => 'g3',
        'المطلوب' => 'g4',
        'المتوفر الجاهز' => 'g5',
        'المخصص' => 'g6',
        'العجز' => 'g7',
        'مسار السد' => 'g8',
        'شاغر حرج؟' => 'g9',
        'حالة السطر' => 'g10',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('wf_coverage_lines');
    echo ems_w14_grid('emsList_wf_coverage', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في المطلوب مقابل المتوفر'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>
