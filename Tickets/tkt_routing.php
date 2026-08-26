<?php
/**
 * Tickets/tkt_routing.php — سجل التوجيه (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل توجيه سطر والالي بقاعدته وتصحيح المركز بسببه المكتوب
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `PeopleCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w13_view.php';

$ctx = w13_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w13_perms($conn, 'Tickets/tkt_routing.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_routing_history',
                 array('orderBy' => 'ticket_id DESC, seq_no', 'limit' => 800));

$page_title = 'إيكوبيشن | سجل التوجيه';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل التوجيه'; $header_icon = 'fa fa-route'; $header_actions = array();
    $header_back = array('href' => 'tkt_parties.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أطراف البلاغ');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع التوجيه</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "route_kind", "AUTO") ?></div><div class="ems-stat-label">توجيه الي بقاعدته</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "route_kind", "CENTER_CORRECTION") ?></div><div class="ems-stat-label">تصحيح مركز بسببه</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "to_dept") ?></div><div class="ems-stat-label">الادارات الموجه اليها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع توجيه', 'التوجيه واقعة بقاعدتها لا احالة يدوية بلا سبب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>البلاغ</th><th>التسلسل</th><th>نوع التوجيه</th><th>من ادارة</th><th>الى ادارة</th><th>قاعدة التوجيه</th><th>سبب التصحيح</th><th>الموجه</th><th>تاريخ التوجيه</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= (int) $r["seq_no"] ?></td>
                    <td><?= ems_w13_state((string) $r["route_kind"]) ?></td>
                    <td><?= ems_w13_txt($r["from_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["to_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["rule_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["reason"]) ?></td>
                    <td><?= (int) $r["routed_by"] ?></td>
                    <td><?= ems_w13_txt($r["routed_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
