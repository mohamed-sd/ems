<?php
/**
 * Procurement/wh_lots.php — سجل الدفعات (RPR-W09 · DEC-OPEN-15 ㉔)
 * ───────────────────────────────────────────────────────────────────────────
 * **الدفعةُ تحمل**: رقمَها والصنفَ والموردَ وأمرَ الشراءِ وسندَ الإدخالِ
 * والكميّةَ الواردةَ والمتاحةَ وتاريخَ التصنيعِ والصلاحيةِ والمخزنَ والموقعَ
 * الداخليَّ وحالةَ الجودة.
 *
 * ⛔ **ولا يلزم منها إلّا ما توجبه سياسةُ الصنف** (‏نصُّ القرار): فحقلٌ فارغٌ
 *   هنا ليس نقصًا ما دامت درجتُه `OPTIONAL` — ويُسجَّل في قيدِ الجودةِ ولا يمنع.
 *
 * ◆ **وتاريخُ التصنيعِ مستقلٌّ عن الصلاحية** (⑱): قد يوجد أحدُهما دون الآخر،
 *   ولا يطلب النظامُ الاثنَين دائمًا.
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

$pp = check_page_permissions($conn, 'Procurement/wh_lots.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_lots super') : ems_tenant_db();
$pick = isset($_GET['wh']) ? (int) $_GET['wh'] : 0;

$rows = array(); $items = array(); $whs = array(); $sups = array();
try {
    $opts = array('orderBy' => 'expiry_date IS NULL, expiry_date, id DESC', 'limit' => 500);
    if ($pick > 0) { $opts['where'] = array('warehouse_id' => $pick); }
    $rows = $gate->select('proc_lot', $opts);
} catch (\Throwable $t) { error_log('wh_lots list: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_item', array('columns' => array('id', 'name', 'track_expiry_level'), 'limit' => 900)) as $i) { $items[(int) $i['id']] = $i; } }
catch (\Throwable $t) { error_log('wh_lots items: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'name'), 'limit' => 300)) as $w) { $whs[(int) $w['id']] = (string) $w['name']; } }
catch (\Throwable $t) { error_log('wh_lots whs: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('wh_lots sups: ' . $t->getMessage()); }

/* ◆ **ساعةُ القاعدةِ لا ساعةُ PHP** — والأفقُ يُحسب فيها أيضًا */
$__d = $conn->query('SELECT CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)');
$__dr = $__d ? $__d->fetch_row() : array('', '');
$today = (string) $__dr[0]; $horizon = (string) $__dr[1];
$expired = 0; $soon = 0; $noExp = 0; $avail = 0.0;
foreach ($rows as $r) {
    $e = (string) $r['expiry_date'];
    $avail += (float) $r['qty_available'];
    if ($e === '') { $noExp++; }
    elseif ($e < $today) { $expired++; }
    elseif ($horizon !== '' && $e <= $horizon) { $soon++; }
}

$page_title = 'إيكوبيشن | سجل الدفعات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الدفعات'; $header_icon = 'fa fa-layer-group'; $header_actions = array();
    $header_back = array('href' => 'wh_receipt.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سند الإدخال');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">دفعات مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= htmlspecialchars(number_format($avail, 3)) ?></div><div class="ems-stat-label">الكمية المتاحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $soon ?></div><div class="ems-stat-label">تنتهي خلال تسعين يوما</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $expired ?></div><div class="ems-stat-label">منتهية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noExp ?></div><div class="ems-stat-label">بلا تاريخ صلاحية</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_lot_wh">المخزن</label><select name="wh" id="w9_lot_wh" onchange="this.form.submit()">
            <option value="0">الكل</option>
            <?php foreach ($whs as $id => $nm): ?>
                <option value="<?= (int) $id ?>" <?= $pick === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($nm) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا دفعات مسجلة',
        'الدفعة تسجل عند الإدخال إن كانت سياسة الصنف تتبعها. والحقل الفارغ لا يمنع ما دام اختياريا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الدفعة</th><th>الصنف</th><th>المورد</th><th>سند الإدخال</th><th>الوارد</th><th>المتاح</th><th>تاريخ التصنيع</th><th>تاريخ الصلاحية</th><th>المخزن</th><th>الموقع الداخلي</th><th>حالة الجودة</th><th>نسخة السياسة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $e = (string) $r['expiry_date']; ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['lot_no']) ?></td>
                <td><?= htmlspecialchars(isset($items[(int) $r['item_id']]) ? $items[(int) $r['item_id']]['name'] : ('#' . (int) $r['item_id'])) ?></td>
                <td><?= htmlspecialchars(isset($sups[(int) $r['supplier_id']]) ? $sups[(int) $r['supplier_id']] : '') ?></td>
                <td><?= ((int) $r['receipt_id'] > 0 ? (int) $r['receipt_id'] : '') ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['qty_received'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['qty_available'], 3)) ?></td>
                <td><?= htmlspecialchars((string) $r['mfg_date']) ?></td>
                <td><?= htmlspecialchars($e !== '' ? $e : 'غير مسجل') ?></td>
                <td><?= htmlspecialchars(isset($whs[(int) $r['warehouse_id']]) ? $whs[(int) $r['warehouse_id']] : '') ?></td>
                <td><?= htmlspecialchars((string) $r['bin']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['quality_state'], $conn)) ?></td>
                <td><?= (int) $r['policy_version'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
