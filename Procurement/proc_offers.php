<?php
/**
 * Procurement/proc_offers.php — عروض الموردين المستلمة وبنودها (RPR-W09 · PRC-08 · PRC-09)
 * ───────────────────────────────────────────────────────────────────────────
 * **رأسُ العرضِ كيانٌ لا سطر** (`PRC-08`): بلا رأسٍ لا موضعَ لصلاحيةِ العرضِ ولا
 * لوقتِ تسليمِه ولا لعملتِه — فتصير المقارنةُ بلا زمنٍ ولا سند. **وبنودُه
 * تُقابَل بندًا ببندٍ ببنودِ الطلب** (`PRC-09`) عبرَ `request_line_id`.
 *
 * ◆ **والمتأخّرُ يُوسَم ولا يُسقَط**: `late` **عمودٌ مشتقٌّ** من مقارنةِ
 *   `submitted_at` بـ`due_date` — فالمتأخّرُ قرارٌ للجنةِ لا حذفٌ صامت.
 *
 * ◆ **والبديلُ يُعلَن ولا يُقارَن كمطابق**: `is_alternative` بسببٍ مكتوب
 *   (`chk_offl_alt`) — وإلّا صارت المقارنةُ بين شيئَين مختلفَين.
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

$pp = check_page_permissions($conn, 'Procurement/proc_offers.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_offers super') : ems_tenant_db();
$rfqPick = isset($_GET['rfq']) ? (int) $_GET['rfq'] : 0;
$open = isset($_GET['offer']) ? (int) $_GET['offer'] : 0;

$rows = array(); $lines = array(); $rfqs = array(); $sups = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($rfqPick > 0) { $opts['where'] = array('rfq_id' => $rfqPick); }
    $rows = $gate->select('proc_offer', $opts);
} catch (\Throwable $t) { error_log('proc_offers list: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_rfq', array('columns' => array('id', 'code', 'title'), 'limit' => 400)) as $q) { $rfqs[(int) $q['id']] = $q; } }
catch (\Throwable $t) { error_log('proc_offers rfqs: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('proc_offers sups: ' . $t->getMessage()); }
if ($open > 0) {
    try { $lines = $gate->select('proc_offer_line', array('where' => array('offer_id' => $open), 'orderBy' => 'id')); }
    catch (\Throwable $t) { error_log('proc_offers lines: ' . $t->getMessage()); }
}
$lateN = 0; $altN = 0;
foreach ($rows as $r) { if ((int) $r['late'] === 1) { $lateN++; } }
foreach ($lines as $l) { if ((int) $l['is_alternative'] === 1) { $altN++; } }

$page_title = 'إيكوبيشن | عروض الموردين المستلمة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'عروض الموردين المستلمة'; $header_icon = 'fa fa-file-invoice'; $header_actions = array();
    $header_back = array('href' => 'proc_rfq.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'طلب العروض');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عروض مستلمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $lateN ?></div><div class="ems-stat-label">وردت بعد الموعد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $altN ?></div><div class="ems-stat-label">بنود بديلة معلنة</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_off_rfq">طلب العروض</label><select name="rfq" id="w9_off_rfq" onchange="this.form.submit()">
            <option value="0">الكل</option>
            <?php foreach ($rfqs as $id => $q): ?>
                <option value="<?= (int) $id ?>" <?= $rfqPick === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $q['code']) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عروض مستلمة', 'العرض من مورد مدعو وحده. والوارد بعد الموعد يوسم ولا يحذف'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>مرجع العرض</th><th>طلب العروض</th><th>المورد</th><th>تاريخ التقديم</th><th>سار حتى</th><th>العملة</th><th>الإجمالي</th><th>بالأساس</th><th>البنود</th><th>مدة التوريد</th><th>ورد بعد الموعد</th><th>البنود</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $q = isset($rfqs[(int) $r['rfq_id']]) ? $rfqs[(int) $r['rfq_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['offer_ref']) ?></td>
                <td><?= htmlspecialchars($q ? (string) $q['code'] : ('#' . (int) $r['rfq_id'])) ?></td>
                <td><?= htmlspecialchars(isset($sups[(int) $r['supplier_id']]) ? $sups[(int) $r['supplier_id']] : ('#' . (int) $r['supplier_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['submitted_at']) ?></td>
                <td><?= htmlspecialchars((string) $r['valid_until']) ?></td>
                <td><?= htmlspecialchars((string) $r['currency']) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['total_amount'], 2)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['base_amount'], 2)) ?></td>
                <td><?= (int) $r['line_count'] ?></td>
                <td><?= (int) $r['delivery_days'] ?></td>
                <td><?= ((int) $r['late'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><a href="?offer=<?= (int) $r['id'] ?>">عرض البنود</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <?php if ($open > 0): ?>
    <h3 class="ems-section-title">بنود العرض</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>بند الطلب</th><th>الكمية المعروضة</th><th>سعر الوحدة</th><th>الإجمالي</th><th>العلامة التجارية</th><th>بديل</th><th>سبب البديل</th></tr></thead>
        <tbody>
        <?php if ($lines): foreach ($lines as $l): ?>
            <tr>
                <td><?= htmlspecialchars((string) $l['item_name']) ?></td>
                <td><?= (int) $l['request_line_id'] ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['qty_offered'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['unit_price'], 4)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $l['subtotal'], 2)) ?></td>
                <td><?= htmlspecialchars((string) $l['brand']) ?></td>
                <td><?= ((int) $l['is_alternative'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars((string) $l['alt_why']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="8">لا بنود لهذا العرض</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</body></html>
