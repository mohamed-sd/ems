<?php
/**
 * Procurement/wh_issue_requests.php — طلبات الصرف الواردة وبنودها (RPR-W09 · WH-08 · WH-09)
 * ───────────────────────────────────────────────────────────────────────────
 * **«طلبُ صرفٍ × جهة — طلبٌ واحد»** (`WH-08`)، و**بنودُه غيرُ بنودِ السند**
 * (`WH-09` نصًّا). فالجهةُ تطلب والمخزنُ يصرف — **وخلطُهما في كيانٍ واحدٍ يمحو
 * «طُلب ولم يُصرَف»** فلا يبقى ما يُقاس عليه تأخّرُ المخزن.
 *
 * ◆ **والمعتمَدُ لا يتجاوز المطلوب** (`APPROVED_EXCEEDS_REQUESTED`)،
 *   **وخفضُه دونه بسببٍ مكتوب** (`CUT_WITHOUT_REASON` · `chk_ireql_cut`) —
 *   فالخفضُ الصامتُ يجعل الجهةَ تنتظر ما لن يأتي.
 *
 * ◆ **ومَن طلب لا يعتمد طلبَه** (`SAME_ACTOR_REQUEST_AND_APPROVE`)،
 *   **ولا صرفَ في فترةٍ مقفلة** (`ISSUE_FROM_CLOSED_PERIOD`) — وإلّا انفكَّ
 *   الإقفالُ بعد إثباتِه.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/wh_issue_requests.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_issue_requests super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';
$open = isset($_GET['req']) ? (int) $_GET['req'] : 0;

$rows = array(); $choices = array(); $lines = array(); $whs = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('proc_issue_request', $opts);
} catch (\Throwable $t) { error_log('issue_requests list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['state'] !== '') { $choices[(string) $r['state']] = true; } }
try { foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'name'), 'limit' => 300)) as $w) { $whs[(int) $w['id']] = (string) $w['name']; } }
catch (\Throwable $t) { error_log('issue_requests whs: ' . $t->getMessage()); }
if ($open > 0) {
    try { $lines = $gate->select('proc_issue_request_line', array('where' => array('request_id' => $open), 'orderBy' => 'id')); }
    catch (\Throwable $t) { error_log('issue_requests lines: ' . $t->getMessage()); }
}
$pending = 0; $partial = 0;
foreach ($rows as $r) {
    if ((string) $r['state'] === 'draft' || (string) $r['state'] === 'submitted') { $pending++; }
    if ((string) $r['state'] === 'approved') { $partial++; }
}

$page_title = 'إيكوبيشن | طلبات الصرف الواردة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلبات الصرف الواردة'; $header_icon = 'fa fa-inbox'; $header_actions = array();
    $header_back = array('href' => 'issue_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سند الصرف');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">طلبات صرف واردة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $pending ?></div><div class="ems-stat-label">تنتظر الاعتماد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $partial ?></div><div class="ems-stat-label">اعتمدت ولم تصرف</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_ir_st">حالة الطلب</label><select name="state" id="w9_ir_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات صرف واردة', 'الطلب من الجهة والسند من المخزن. وخفض المعتمد عن المطلوب بسبب مكتوب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز الطلب</th><th>المخزن</th><th>الجهة الطالبة</th><th>الغرض</th><th>تاريخ الحاجة</th><th>الأولوية</th><th>سند الصرف</th><th>الحالة</th><th>سبب الرفض</th><th>البنود</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['code']) ?></td>
                <td><?= htmlspecialchars(isset($whs[(int) $r['warehouse_id']]) ? $whs[(int) $r['warehouse_id']] : '') ?></td>
                <td><?= htmlspecialchars((string) $r['requesting_dept']) ?></td>
                <td><?= htmlspecialchars((string) $r['purpose']) ?></td>
                <td><?= htmlspecialchars((string) $r['need_date']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['priority'], $conn)) ?></td>
                <td><?= ((int) $r['issue_id'] > 0 ? (int) $r['issue_id'] : '') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['reject_reason']) ?></td>
                <td><a href="?req=<?= (int) $r['id'] ?>">عرض البنود</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <?php if ($open > 0): ?>
    <h3 class="ems-section-title">بنود طلب الصرف</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>المطلوب</th><th>المعتمد</th><th>المصروف</th><th>المتبقي</th><th>سبب خفض المعتمد</th></tr></thead>
        <tbody>
        <?php if ($lines): foreach ($lines as $l): $rem = (float) $l['qty_approved'] - (float) $l['qty_issued']; ?>
            <tr>
                <td><?= htmlspecialchars((string) $l['item_name']) ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['qty_requested'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['qty_approved'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['qty_issued'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format($rem, 3)) ?></td>
                <td><?= htmlspecialchars((string) $l['cut_reason']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6">لا بنود لهذا الطلب</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</body></html>
