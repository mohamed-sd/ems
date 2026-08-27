<?php
/**
 * Portal/my_reports.php — بلاغاتي (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * صاحب البلاغ يرى بلاغاته وحالتها والتسجيل الجديد من باب البلاغات لا من هنا
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة البلاغات** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/my_reports.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'id DESC', 'limit' => 300, 'scope_col' => 'project_id');

$opt['where'] = isset($opt['where']) ? $opt['where'] : array();
$opt['where']['reporter_user_id'] = $ctx['user_id'];
$rows = w15_rows($is_super, 'tickets', $vis, $opt);

$page_title = 'إيكوبيشن | بلاغاتي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'بلاغاتي'; $header_icon = 'fa fa-bullhorn'; $header_actions = array();
    $header_back = array('href' => 'my_portal.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'بوابتي');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد بلاغاتي</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "head_state", "open") ?></div><div class="ems-stat-label">بلاغات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "head_state", "closed") ?></div><div class="ems-stat-label">بلاغات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "priority", "critical") ?></div><div class="ems-stat-label">بلاغات حرجة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بلاغات لك', 'البلاغ يسجل من بابه ويعرض هنا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_my_reports')); ?>
    <table id="emsList_my_reports" class="data-table">
        <thead><tr><th>رقم البلاغ</th><th>تاريخ البلاغ</th><th>الملخص</th><th>الأولوية</th><th>المرحلة</th><th>مهلة الاستجابة</th><th>مهلة المعالجة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["ticket_no"]) ?></td>
                    <td><?= ems_w15_txt($r["call_date"]) ?></td>
                    <td><?= ems_w15_txt($r["operational_summary"]) ?></td>
                    <td><?= ems_w15_state((string) $r["priority"]) ?></td>
                    <td><?= ems_w15_state((string) $r["stage"]) ?></td>
                    <td><?= ems_w15_txt($r["response_due_at"]) ?></td>
                    <td><?= ems_w15_txt($r["resolution_due_at"]) ?></td>
                    <td><?= ems_w15_state((string) $r["head_state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
