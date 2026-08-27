<?php
/**
 * Portal/exec_raised_requests.php — الطلبات المرفوعة إلى القيادة (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * اسقاط فوق سجلات الادارات المالكة والقرار يمر بمحرك الاعتماد نفسه لا من هنا
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **الإدارة المالية** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_raised_requests.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('state' => 'pending_approval'), 'orderBy' => 'sla_due_at, id DESC', 'limit' => 500, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'fin_requests', $vis, $opt);

$page_title = 'إيكوبيشن | الطلبات المرفوعة إلى القيادة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الطلبات المرفوعة إلى القيادة'; $header_icon = 'fa fa-inbox'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">طلبات تنتظر القرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_num(ems_w15_sumf($rows, "amount")) ?></div><div class="ems-stat-label">مجموع القيم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "priority", "critical") ?></div><div class="ems-stat-label">طلبات حرجة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "source_module") ?></div><div class="ems-stat-label">الإدارات المصدر</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات تنتظر القيادة', 'الصندوق إسقاط فوق سجلات مالكيها'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>الإدارة المصدر</th><th>النوع</th><th>المستفيد</th><th>القيمة</th><th>العملة</th><th>المهلة</th><th>مستوى التصعيد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["request_no"]) ?></td>
                    <td><?= ems_w15_state((string) $r["source_module"]) ?></td>
                    <td><?= ems_w15_state((string) $r["request_type"]) ?></td>
                    <td><?= ems_w15_txt($r["beneficiary_name"]) ?></td>
                    <td><?= ems_w15_num($r["amount"]) ?></td>
                    <td><?= ems_w15_txt($r["currency"]) ?></td>
                    <td><?= ems_w15_txt($r["needed_by"]) ?></td>
                    <td><?= (int) $r["escalation_level"] ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
