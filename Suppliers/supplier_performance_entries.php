<?php
/**
 * Suppliers/supplier_performance_entries.php — الأداءُ والوحداتُ المعتمدة (SUP-17)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **قيدُ أداءٍ معتمدٌ واحد: وحدةٌ × شهر** — معاملةٌ آلتُها
 * `W8_STATES#supplier_contracts` (المرجعُ الصريحُ المربوطُ في الدفتر).
 *
 * ◆ **قيدُ الأداءِ المعتمدُ يُقرأ من موضعِ اعتمادِه**: تسويةُ الموردِ
 *   المعتمدةُ فصاعدًا هي وثيقةُ اعتمادِ أداءِ الشهرِ (ساعاتُ الموردِ
 *   المنفَّذةُ والمسوّاةُ للعميلِ في `settlements` بعد اعتمادِ مديرِ
 *   الموردين بسلّمِه) — فكلُّ صفٍّ هنا قيدُ أداءِ شهرٍ **بمرجعِ تسويتِه**.
 * ◆ والفعلُ (الاعتمادُ) عند فاعلِه في دورةِ التسويةِ — سجلُّ حوكمةٍ بلا POST.
 * ◆ القراءةُ `select` معزولًا والتجميعُ في الذاكرة (GAP-29).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_performance_entries.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier performance super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';

$APPROVED = array('approved' => 1, 'payment_requested' => 1, 'paid' => 1, 'closed' => 1);
$rows = array(); $nE = 0; $sumH = 0.0; $supSet = array(); $perSet = array();
try {
    foreach ($gate->select('settlements', array('orderBy' => 'period_from DESC, id DESC', 'limit' => 3000)) as $x0) {
        if ((string) $x0['party_type'] !== 'supplier') { continue; }
        if ((int) (isset($x0['is_deleted']) ? $x0['is_deleted'] : 0) === 1) { continue; }
        if (!isset($APPROVED[(string) $x0['state']])) { continue; }
        $h = (float) (isset($x0['supplier_executed_hours']) ? $x0['supplier_executed_hours'] : 0);
        $per = substr((string) $x0['period_from'], 0, 7);
        $nE++; $sumH += $h;
        $supSet[(string) $x0['party_name']] = true; $perSet[$per] = true;
        $rows[] = array(
            'ref'   => 'قيد اداء بتسوية ' . (string) $x0['settlement_no'],
            'sup'   => (string) $x0['party_name'],
            'per'   => $per,
            'hours' => $h > 0 ? (number_format($h, 1) . ' ساعة منفذة') : 'ساعات المورد غير مسجلة في التسوية',
            'net'   => number_format((float) $x0['net_amount'], 0) . ' ' . (string) $x0['currency'],
            'by'    => (int) $x0['approved_by'] > 0 ? ('معتمد رقم ' . (int) $x0['approved_by']) : 'غير منطبق',
            'at'    => (string) $x0['approved_at'],
            'sid'   => (int) $x0['id'],
        );
    }
} catch (\Throwable $t) { error_log('supplier_performance: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | الأداء والوحدات المعتمدة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الاداء والوحدات المعتمدة: قيد اداء شهري واحد بمرجع تسويته المعتمدة'; $header_icon = 'fa fa-clipboard-check'; $header_actions = array();
    $header_back = array('href' => 'supplier_targets.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مستهدفات الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nE) ?></div><div class="ems-stat-label">قيود اداء معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($sumH, 0) ?></div><div class="ems-stat-label">ساعات منفذة معتمدة اجمالا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($supSet)) ?></div><div class="ems-stat-label">موردون لهم اداء معتمد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($perSet)) ?></div><div class="ems-stat-label">اشهر مشمولة</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>قيد الاداء</th>
                    <th>المورد</th>
                    <th>الشهر</th>
                    <th>الساعات المنفذة</th>
                    <th>صافي التسوية</th>
                    <th>المعتمد</th>
                    <th>تاريخ الاعتماد</th>
                    <th>مرجع الاعتماد</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['sup']) ?></td>
                    <td><?= htmlspecialchars($x0['per']) ?></td>
                    <td><?= htmlspecialchars($x0['hours']) ?></td>
                    <td><?= htmlspecialchars($x0['net']) ?></td>
                    <td><?= htmlspecialchars($x0['by']) ?></td>
                    <td><?= htmlspecialchars((string) $x0['at']) ?></td>
                    <td><a href="settlements.php?open=<?= $x0['sid'] ?>">دورة التسوية</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8">لا قيود اداء معتمدة بعد. القيد يولد باعتماد تسوية المورد بسلمها</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= ems_w8_machine_panel($conn, 'settlements', 'آلة حالة التسوية التي يولد اعتمادها قيد الاداء، تعرض حرفا من مرجعها') ?>

    <div class="ems-note-box">
        قيد الاداء المعتمد يقرأ من موضع اعتماده: تسوية المورد المعتمدة فصاعدا هي وثيقة اعتماد اداء الشهر،
        وساعات المورد المنفذة من عمودها في التسوية نفسها. الفعل عند فاعله في دورة التسوية ولا كتابة هنا.
    </div>
</div>
<?php include '../infooter.php'; ?>
