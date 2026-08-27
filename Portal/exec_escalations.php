<?php
/**
 * Portal/exec_escalations.php — التصعيدات العليا (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * صف موحد لما وصل للقمة من مصادره ولا سجل مكرر ولا اعادة انشاء للواقعة
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

$perms = w15_perms($conn, 'Portal/exec_escalations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('level' => 'exec'), 'orderBy' => 'esc_id DESC', 'limit' => 400, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'ticket_escalations', $vis, $opt);

$page_title = 'إيكوبيشن | التصعيدات العليا';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التصعيدات العليا'; $header_icon = 'fa fa-arrow-up-right-dots'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التصعيدات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "triggered_by", "sla_breach") ?></div><div class="ems-stat-label">تصعيد بتجاوز المهلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "triggered_by", "safety") ?></div><div class="ems-stat-label">تصعيد بسبب السلامة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "ws_id") ?></div><div class="ems-stat-label">المسارات المتأثرة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تصعيدات وصلت للقمة', 'التصعيد يقرأ من مصدره ولا ينسخ'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم التصعيد</th><th>المسار</th><th>المستوى</th><th>سبب التصعيد</th><th>وقت التصعيد</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["esc_id"] ?></td>
                    <td><?= (int) $r["ws_id"] ?></td>
                    <td><?= ems_w15_state((string) $r["level"]) ?></td>
                    <td><?= ems_w15_state((string) $r["triggered_by"]) ?></td>
                    <td><?= ems_w15_txt($r["at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
