<?php
/**
 * Portal/exec_actions_followup.php — متابعة القرارات التنفيذية (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * سجل موحد يجمع ما صدر من الاجتماعات والدوري والاستراتيجي والتصعيدات بمرجع كل منها
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **سجل قرارات القيادة** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_actions_followup.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'followup_date, id DESC', 'limit' => 500, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exec_decisions', $vis, $opt);

$page_title = 'إيكوبيشن | متابعة القرارات التنفيذية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'متابعة القرارات التنفيذية'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'exec_meeting_decisions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'قرارات الاجتماعات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد القرارات المتابعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "status", "مفتوح") ?></div><div class="ems-stat-label">قرارات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "status", "مغلق") ?></div><div class="ems-stat-label">قرارات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_empty($rows, "followup_date") ?></div><div class="ems-stat-label">قرارات بلا موعد متابعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قرارات قيد المتابعة', 'المتابعة تجمع بمرجعها ولا تنشئ سجلا ثانيا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_actions_followup')); ?>
    <table id="emsList_exec_actions_followup" class="data-table">
        <thead><tr><th>رقم القرار</th><th>المصدر</th><th>الموضوع</th><th>الإدارة المكلفة</th><th>المهلة</th><th>موعد المتابعة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["decision_no"]) ?></td>
                    <td><?= ems_w15_state((string) $r["issue_type"]) ?></td>
                    <td><?= ems_w15_txt($r["issue_desc"]) ?></td>
                    <td><?= ems_w15_txt($r["assigned_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["exec_deadline"]) ?></td>
                    <td><?= ems_w15_txt($r["followup_date"]) ?></td>
                    <td><?= ems_w15_state((string) $r["status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
