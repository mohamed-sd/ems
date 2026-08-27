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
    <table id="emsList_exec_daily_report" class="data-table">
        <thead><tr><th>اليوم</th><th>المشروع</th><th>الموقع</th><th>الحالة</th><th>وقت الإقفال</th><th>ملاحظة الإقفال</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["day_date"]) ?></td>
                    <td><?= (int) $r["project_id"] ?></td>
                    <td><?= (int) $r["site_id"] ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
                    <td><?= ems_w15_txt($r["closed_at"]) ?></td>
                    <td><?= ems_w15_txt($r["close_note"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
