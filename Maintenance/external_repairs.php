<?php
/**
 * Maintenance/external_repairs.php — الإصلاح الخارجي ومطالبات الضمان (RPR-W07 · MNT-11)
 * ───────────────────────────────────────────────────────────────────────────
 * **الإحالةُ الخارجيّةُ بعقدِها وحدودِها؛ ومطالبةُ الضمانِ على موردِ المعدّةِ
 * بمرجعِ عقدِه** (`MNT-11`). و`referExternal` تردُّ `WARRANTY_WITHOUT_CONTRACT_REF`
 * على مطالبةِ ضمانٍ بلا مرجعِ عقد — **فالمطالبةُ بلا سندٍ ليست مطالبة**.
 * والتكلفةُ الفعليّةُ تدخل في تكلفةِ الأمرِ المشتقّةِ في شهادةِ العودة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Maintenance/external_repairs.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('external_repairs super') : ems_tenant_db();
$pick = isset($_GET['line_kind']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['line_kind']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('line_kind' => $pick); }
    $rows = $gate->select('mnt_external_repair', $opts);
} catch (\Throwable $t) { error_log('external_repairs list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_external_repair', array('columns' => array('line_kind'), 'limit' => 400)) as $c) { if ((string) $c['line_kind'] !== '') { $choices[(string) $c['line_kind']] = true; } } }
catch (\Throwable $t) { error_log('external_repairs choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('mnt_order', array('columns' => array('id', 'code'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['code']; } }
catch (\Throwable $t) { error_log('external_repairs orders: ' . $t->getMessage()); }

$warranty = 0; $cost = 0.0;
foreach ($rows as $r) { if ((string) $r['line_kind'] === 'warranty_claim') { $warranty++; } $cost += (float) $r['actual_cost']; }
$cost = round($cost, 2);

$page_title = 'إيكوبيشن | الإصلاح الخارجي ومطالبات الضمان';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإصلاح الخارجي ومطالبات الضمان'; $header_icon = 'fa fa-screwdriver-wrench'; $header_actions = array();
    $header_back = array('href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر العمل');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أسطر خارجية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $warranty ?></div><div class="ems-stat-label">مطالبات ضمان</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $cost ?></div><div class="ems-stat-label">التكلفة الفعلية</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_er_k">نوع السطر</label><select name="line_kind" id="w7_er_k" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إحالات خارجية ولا مطالبات ضمان', 'الإحالة تفتح من أمر عمل. ومطالبة الضمان بمرجع عقد المورد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>أمر العمل</th><th>النوع</th><th>مرجع العقد</th><th>نطاق العمل</th><th>التكلفة المقدرة</th><th>التكلفة الفعلية</th><th>نتيجة المطالبة</th><th>محضر الاستلام</th><th>حالة السطر</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['line_kind'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['contract_ref']) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $r['scope_ar'], 0, 120)) ?></small></td>
                <td><?= htmlspecialchars((string) $r['estimated_cost']) ?></td>
                <td><?= htmlspecialchars((string) $r['actual_cost']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['claim_result'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['receipt_ref']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11">لا إحالات خارجية ولا مطالبات ضمان.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>
