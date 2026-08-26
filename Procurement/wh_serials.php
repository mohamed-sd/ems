<?php
/**
 * Procurement/wh_serials.php — سجل الأرقام التسلسلية (RPR-W09 · DEC-OPEN-15 ㉕)
 * ───────────────────────────────────────────────────────────────────────────
 * **الرقمُ التسلسليُّ يتتبَّع القطعةَ من الشراءِ حتّى الإعدام**: الموردُ ومرجعُ
 * الشراءِ والاستلامِ والضمانُ والمخزنُ الحاليُّ وحائزُ العهدةِ والأصلُ المركَّبُ
 * عليه وتاريخا التركيبِ والفكِّ وسجلُّ الإصلاحِ والحالةُ وتاريخُ الإعدام.
 *
 * ⛔ **وليست كلُّها إلزاميّةً منذ اليومِ الأوّل** (‏نصُّ القرار): لكلِّ حقلٍ
 *   درجتُه من سياسةِ الصنف.
 *
 * ◆ **والموروثُ يتعايش مع المرقَّم** (㉑): نفسُ الصنفِ قد يحمل قطعًا قديمةً
 *   بلا رقمٍ موثَّقٍ وقطعًا جديدةً مرقَّمة. ⛔ **ولا يُخترَع رقمٌ للتاريخ** —
 *   والقديمُ يُصنَّف رصيدًا موروثًا في سجلِّ حالاتِ المخزون.
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

$pp = check_page_permissions($conn, 'Procurement/wh_serials.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_serials super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z_]/', '', (string) $_GET['state']) : '';

$rows = array(); $items = array(); $whs = array(); $choices = array(); $legacy = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 500);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('proc_serial', $opts);
} catch (\Throwable $t) { error_log('wh_serials list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['state'] !== '') { $choices[(string) $r['state']] = true; } }
try { foreach ($gate->select('proc_item', array('columns' => array('id', 'name'), 'limit' => 900)) as $i) { $items[(int) $i['id']] = (string) $i['name']; } }
catch (\Throwable $t) { error_log('wh_serials items: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'name'), 'limit' => 300)) as $w) { $whs[(int) $w['id']] = (string) $w['name']; } }
catch (\Throwable $t) { error_log('wh_serials whs: ' . $t->getMessage()); }
try { $legacy = $gate->select('proc_stock_state', array('where' => array('state_key' => 'LEGACY_UNTRACKED'), 'limit' => 300)); }
catch (\Throwable $t) { error_log('wh_serials legacy: ' . $t->getMessage()); }

/* ◆ **ساعةُ القاعدةِ لا ساعةُ PHP**: سريانُ الضمانِ يُقارَن بتاريخٍ خزّنَته
     القاعدةُ، فمرجعُ اليومِ منها. */
$__d = $conn->query('SELECT CURDATE()');
$today = $__d ? (string) $__d->fetch_row()[0] : '';
$installed = 0; $warranted = 0; $legacyQty = 0.0;
foreach ($rows as $r) {
    if ((int) $r['asset_id'] > 0 && (string) $r['removed_at'] === '') { $installed++; }
    if ((string) $r['warranty_until'] !== '' && (string) $r['warranty_until'] >= $today) { $warranted++; }
}
foreach ($legacy as $l) { $legacyQty += (float) $l['qty']; }

$page_title = 'إيكوبيشن | سجل الأرقام التسلسلية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الأرقام التسلسلية'; $header_icon = 'fa fa-barcode'; $header_actions = array();
    $header_back = array('href' => 'wh_lots.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الدفعات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">قطع مرقمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $installed ?></div><div class="ems-stat-label">مركبة على أصل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $warranted ?></div><div class="ems-stat-label">ضمانها ساري</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= htmlspecialchars(number_format($legacyQty, 3)) ?></div><div class="ems-stat-label">رصيد موروث بلا ترقيم</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_ser_st">حالة القطعة</label><select name="state" id="w9_ser_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قطع مرقمة بعد',
        'الترقيم يبدأ حين تسجل قطعة جديدة. والرصيد القديم يبقى موروثا ولا يخترع له رقم'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم التسلسلي</th><th>الصنف</th><th>الدفعة</th><th>الضمان حتى</th><th>المخزن الحالي</th><th>حائز العهدة</th><th>الأصل المركب عليه</th><th>تاريخ التركيب</th><th>تاريخ الفك</th><th>مرات الإصلاح</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['serial_no']) ?></td>
                <td><?= htmlspecialchars(isset($items[(int) $r['item_id']]) ? $items[(int) $r['item_id']] : ('#' . (int) $r['item_id'])) ?></td>
                <td><?= ((int) $r['lot_id'] > 0 ? (int) $r['lot_id'] : '') ?></td>
                <td><?= htmlspecialchars((string) $r['warranty_until']) ?></td>
                <td><?= htmlspecialchars(isset($whs[(int) $r['warehouse_id']]) ? $whs[(int) $r['warehouse_id']] : '') ?></td>
                <td><?= ((int) $r['custodian_id'] > 0 ? (int) $r['custodian_id'] : '') ?></td>
                <td><?= ((int) $r['asset_id'] > 0 ? (int) $r['asset_id'] : '') ?></td>
                <td><?= htmlspecialchars((string) $r['installed_at']) ?></td>
                <td><?= htmlspecialchars((string) $r['removed_at']) ?></td>
                <td><?= (int) $r['repair_count'] ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <h3 class="ems-section-title">الرصيد الموروث بلا ترقيم</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>المخزن</th><th>الكمية</th><th>قاعدة الاشتقاق</th></tr></thead>
        <tbody>
        <?php if ($legacy): foreach ($legacy as $l): ?>
            <tr>
                <td><?= htmlspecialchars(isset($items[(int) $l['item_id']]) ? $items[(int) $l['item_id']] : ('#' . (int) $l['item_id'])) ?></td>
                <td><?= htmlspecialchars(isset($whs[(int) $l['warehouse_id']]) ? $whs[(int) $l['warehouse_id']] : '') ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['qty'], 3)) ?></td>
                <td><small><?= htmlspecialchars((string) $l['derive_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="4">لا رصيد موروث غير مرقم</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
