<?php
/**
 * Portal/exec_weekly_report.php — التقرير الأسبوعي التنفيذي (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * مقارنة اسبوعية بمحاورها تقرأ من وقائع التشغيل مباشرة ولا تخزن نسخة اسبوعية
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة التشغيل** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w15_view.php';

$ctx = w15_ctx();
$is_super = $ctx['is_super'];
if (!$is_super && $ctx['company_id'] <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w15_perms($conn, 'Portal/exec_weekly_report.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'stop_date DESC, id DESC', 'limit' => 800, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'ops_stop_register', $vis, $opt);

$page_title = 'إيكوبيشن | التقرير الأسبوعي التنفيذي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التقرير الأسبوعي التنفيذي'; $header_icon = 'fa fa-chart-line'; $header_actions = array();
    $header_back = array('href' => 'exec_daily_report.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التقرير اليومي التنفيذي');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">وقائع المدى</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_num(ems_w15_sumf($rows, "hours")) ?></div><div class="ems-stat-label">مجموع ساعات التوقف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "billable", "1") ?></div><div class="ems-stat-label">وقائع قابلة للتحميل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "equipment_id") ?></div><div class="ems-stat-label">المعدات المتأثرة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع في المدى', 'المقارنة تشتق من الوقائع ولا تخزن'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>اليوم</th><th>المشروع</th><th>المحور</th><th>الساعات</th><th>الطرف المسؤول</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["stop_date"]) ?></td>
                    <td><?= (int) $r["project_id"] ?></td>
                    <td><?= ems_w15_state((string) $r["ops_state"]) ?></td>
                    <td><?= ems_w15_num($r["hours"]) ?></td>
                    <td><?= ems_w15_state((string) $r["resp_party"]) ?></td>
                    <td><?= ems_w15_state((string) $r["decision"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
