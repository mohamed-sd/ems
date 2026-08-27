<?php
/**
 * Portal/exec_redline_breaches.php — تجاوزات الخطوط الحمراء (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * طلبات استثناء قبل التنفيذ من قاعدة مانعة وتظل مغلقة حتى يصدر القرار بسلطته
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
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w15_view.php';

$ctx = w15_ctx();
$is_super = $ctx['is_super'];
if (!$is_super && $ctx['company_id'] <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w15_perms($conn, 'Portal/exec_redline_breaches.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('risk_level' => 'legal_forbidden'), 'orderBy' => 'req_id DESC', 'limit' => 400, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exception_requests', $vis, $opt);

$page_title = 'إيكوبيشن | تجاوزات الخطوط الحمراء';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تجاوزات الخطوط الحمراء'; $header_icon = 'fa fa-ban'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد طلبات التجاوز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Pending") ?></div><div class="ems-stat-label">تنتظر القرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Approved") ?></div><div class="ems-stat-label">صدر فيها قرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "guard_code") ?></div><div class="ems-stat-label">القواعد المطلوب تجاوزها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات تجاوز', 'القاعدة المانعة تبقى مغلقة حتى قرار بسلطته'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>القاعدة</th><th>السبب</th><th>نطاق الطلب</th><th>من</th><th>إلى</th><th>الأثر المتوقع</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["req_id"] ?></td>
                    <td><?= ems_w15_txt($r["guard_code"]) ?></td>
                    <td><?= ems_w15_txt($r["reason"]) ?></td>
                    <td><?= ems_w15_state((string) $r["scope_type"]) ?></td>
                    <td><?= ems_w15_txt($r["valid_from"]) ?></td>
                    <td><?= ems_w15_txt($r["valid_to"]) ?></td>
                    <td><?= ems_w15_txt($r["expected_impact"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
