<?php
/**
 * Suppliers/supplier_contract_units.php — حصصُ الموردين والوحداتُ التعاقديّة (SUP-12)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **وحدةٌ تعاقديّةٌ واحدةٌ بحصّتِها وهامشِها** — معاملةٌ آلتُها
 * `W8_STATES#supplier_contracts` (المرجعُ الصريحُ المربوطُ في الدفتر).
 *
 * ◆ المصادرُ بالاسم: بنودُ عقودِ الموردين (`supplier_contract_lines` —
 *   الوحدةُ الملتزمةُ بسعرِها وسريانِها وحالتِها) · حصصُ الحاوياتِ
 *   (`v_supplier_share_units` — منظورُ الحصصِ بمداه) · ورأسُ العقدِ
 *   (`supplier_contracts` بحالةِ آلتِه العربيّة).
 * ◆ **الهامشُ لا يُختلق**: سعرُ بيعِ الوحدةِ للعميلِ عند مالكِه التجاريِّ —
 *   فيُعرَض سعرُ شراءِ الوحدةِ من الموردِ وقيمةُ التزامِها، ويُصرَّح أنَّ
 *   احتسابَ الهامشِ يحتاج سعرَ العقدِ العميلِ من شاشتِه.
 * ◆ الأفعالُ (اعتمادُ العقدِ وتوقيعُه وإنفاذُه) عند فاعلِها في دورةِ عقودِ
 *   الموردين — وهذه سجلُّ الحوكمةِ بلوحةِ الآلةِ حرفًا، فلا POST هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_contract_units.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier contract units super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';

$sup = array(); $con = array(); $shares = array();
try { foreach ($gate->select('suppliers', array('columns' => array('id', 'name'), 'limit' => 2000)) as $s0) { $sup[(int) $s0['id']] = (string) $s0['name']; } }
catch (\Throwable $t) { error_log('supplier_contract_units sup: ' . $t->getMessage()); }
try { foreach ($gate->select('supplier_contracts', array('limit' => 2000)) as $c0) { $con[(int) $c0['id']] = $c0; } }
catch (\Throwable $t) { error_log('supplier_contract_units con: ' . $t->getMessage()); }
try {
    foreach ($gate->select('v_supplier_share_units', array('limit' => 3000)) as $v0) {
        $sid0 = (int) $v0['supplier_id'];
        $shares[$sid0] = (isset($shares[$sid0]) ? $shares[$sid0] : 0.0) + (float) $v0['share_units'];
    }
} catch (\Throwable $t) { error_log('supplier_contract_units shares: ' . $t->getMessage()); }

$rows = array(); $nU = 0; $sumCommit = 0.0; $nActive = 0;
try {
    foreach ($gate->select('supplier_contract_lines', array('orderBy' => 'id ASC', 'limit' => 2000)) as $l0) {
        if ((int) (isset($l0['is_deleted']) ? $l0['is_deleted'] : 0) === 1) { continue; }
        $nU++;
        $cid = (int) $l0['contract_id'];
        $c0 = isset($con[$cid]) ? $con[$cid] : null;
        $sid0 = $c0 !== null ? (int) $c0['supplier_id'] : 0;
        $commit = (float) $l0['primary_units_committed'];
        $sumCommit += $commit;
        if ((string) $l0['state'] === 'active') { $nActive++; }
        $rows[] = array(
            'unit'   => 'وحدة تعاقدية رقم ' . (int) $l0['id'],
            'supn'   => $sid0 > 0 ? (isset($sup[$sid0]) ? $sup[$sid0] : ('مورد رقم ' . $sid0)) : 'بلا عقد رأس',
            'ctr'    => $cid > 0 ? ('عقد مورد رقم ' . $cid) : 'غير منطبق',
            'cstate' => $c0 !== null ? (string) $c0['state'] : 'غير منطبق',
            'etype'  => (string) $l0['equipment_type_code'],
            'commit' => number_format($commit, 0) . ' ' . (string) $l0['unit'],
            'standby' => number_format((float) $l0['standby_units_required'], 0) . ' مطلوبة و' . number_format((float) $l0['standby_units_allowed'], 0) . ' مسموحة',
            'price'  => number_format((float) $l0['unit_price'], 2) . ' ' . (string) $l0['currency'],
            'share'  => $sid0 > 0 && isset($shares[$sid0]) ? (number_format($shares[$sid0], 0) . ' وحدة حصص حاويات') : 'بلا حصة حاوية سارية',
            'valid'  => (string) $l0['valid_from'] . ' الى ' . ((string) $l0['valid_to'] !== '' && $l0['valid_to'] !== null ? (string) $l0['valid_to'] : 'مفتوح'),
            'state'  => (string) $l0['state'] === 'active' ? 'سارية' : (string) $l0['state'],
        );
    }
} catch (\Throwable $t) { error_log('supplier_contract_units lines: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | حصص الموردين والوحدات التعاقدية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'حصص الموردين والوحدات التعاقدية: وحدة واحدة بحصتها وسعرها وسريانها'; $header_icon = 'fa fa-cubes'; $header_actions = array();
    $header_back = array('href' => 'supplierscontracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'عقود الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nU) ?></div><div class="ems-stat-label">وحدات تعاقدية مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nActive) ?></div><div class="ems-stat-label">سارية بحالتها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($sumCommit) ?></div><div class="ems-stat-label">وحدات ملتزم بها اجمالا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($shares)) ?></div><div class="ems-stat-label">موردون بحصص حاويات</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>الوحدة</th>
                    <th>المورد</th>
                    <th>عقد الرأس</th>
                    <th>حالة العقد بآلته</th>
                    <th>نوع المعدة</th>
                    <th>الملتزم به</th>
                    <th>الاحتياط</th>
                    <th>سعر شراء الوحدة</th>
                    <th>حصة الحاويات</th>
                    <th>السريان</th>
                    <th>حالة الوحدة</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['unit']) ?></td>
                    <td><?= htmlspecialchars($x0['supn']) ?></td>
                    <td><?= htmlspecialchars($x0['ctr']) ?></td>
                    <td><?= htmlspecialchars($x0['cstate']) ?></td>
                    <td><?= htmlspecialchars($x0['etype']) ?></td>
                    <td><?= htmlspecialchars($x0['commit']) ?></td>
                    <td><?= htmlspecialchars($x0['standby']) ?></td>
                    <td><?= htmlspecialchars($x0['price']) ?></td>
                    <td><?= htmlspecialchars($x0['share']) ?></td>
                    <td><?= htmlspecialchars($x0['valid']) ?></td>
                    <td><?= htmlspecialchars($x0['state']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="11">لا وحدات تعاقدية مسجلة بعد في بنود عقود الموردين</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= ems_w8_machine_panel($conn, 'supplier_contracts', 'آلة حالة عقد المورد الحاكمة للوحدات، تعرض حرفا من مرجعها') ?>

    <div class="ems-note-box">
        الوحدة من بنود عقود الموردين بسعر شرائها وسريانها وحالتها، والحصة من منظور حصص الحاويات بمداه.
        وهامش الوحدة يحتاج سعر بيعها في عقد العميل عند مالكه التجاري فلا يعرض له رقم مختلق هنا.
        افعال دورة العقد عند فاعلها في شاشتها ولا كتابة من هذا السجل.
    </div>
</div>
<?php include '../infooter.php'; ?>
