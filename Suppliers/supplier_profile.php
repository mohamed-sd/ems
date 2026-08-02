<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($supplier_id <= 0) {
    header("Location: suppliers.php?msg=معرف+المورد+غير+صحيح+❌");
    exit();
}

// H-20: جلسةُ مشرف المورد تُقصر على موردها — 403 مسجَّلةٌ لغيره
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
\App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], $supplier_id, 'Suppliers/supplier_profile.php');

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشف الأعمدة أُسقط (مضمونة
// بالسجل)، والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق شركة).
$spf_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier profile super') : ems_tenant_db();

try {
    $supplier_rows = $spf_gate->scopedQuery(array(
        'scope' => array('s' => 'suppliers'),
    ), "SELECT s.* FROM suppliers s
        WHERE {TENANT_SCOPE} AND s.id = ? AND COALESCE(s.is_deleted,0)=0
        LIMIT 1", array($supplier_id));
} catch (\Throwable $t) { $supplier_rows = array(); }
$supplier = !empty($supplier_rows) ? $supplier_rows[0] : null;

if (!$supplier) {
    header("Location: suppliers.php?msg=المورد+غير+موجود+او+خارج+نطاق+الشركة+❌");
    exit();
}

$equipments_count = 0;
$contracts_count = 0;
$active_contracts = 0;
$projects_count = 0;
$total_hours = 0;
$timesheet_hours = 0;

$spf_agg = function (array $decl, $sql, array $params) use ($spf_gate) {
    try {
        $rows = $spf_gate->scopedQuery($decl, $sql, $params);
        return !empty($rows) ? $rows[0]['c'] : 0;
    } catch (\Throwable $t) {
        return 0;
    }
};

$equipments_count = intval($spf_agg(array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(*) AS c FROM equipments e WHERE {TENANT_SCOPE} AND e.suppliers = ?", array($supplier_id)));

$contracts_count = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(*) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$active_contracts = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(*) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ? AND sc.status = 1", array($supplier_id)));

$projects_count = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(DISTINCT sc.project_id) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$total_hours = floatval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT IFNULL(SUM(sc.forecasted_contracted_hours),0) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$timesheet_hours = floatval($spf_agg(array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments')),
    "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS c
     FROM timesheet t
     INNER JOIN operations o ON o.id = t.operator
     INNER JOIN equipments e ON e.id = o.equipment
     WHERE {TENANT_SCOPE} AND e.suppliers = ? AND t.status = 1", array($supplier_id)));

try {
    $equipments_breakdown = $spf_gate->scopedQuery(array(
        'scope'  => array('e' => 'equipments'),
        'enrich' => array('o' => 'operations', 't' => 'timesheet'),
    ), "SELECT
            e.id,
            e.name,
            e.code,
            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS hours_sum,
            COUNT(DISTINCT o.project_id) AS projects_count
        FROM equipments e
        LEFT JOIN operations o ON o.equipment = e.id
        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
        WHERE {TENANT_SCOPE} AND e.suppliers = ?
        GROUP BY e.id, e.name, e.code
        ORDER BY hours_sum DESC
        LIMIT 10", array($supplier_id));
} catch (\Throwable $t) { $equipments_breakdown = array(); }

try {
    $contracts_list = $spf_gate->scopedQuery(array(
        'scope'  => array('sc' => 'supplierscontracts'),
        'enrich' => array('p' => 'project'), // اسم المشروع — LEFT بلا تنطيق (سلوك الأصل)
    ), "SELECT sc.id, sc.contract_signing_date, sc.actual_end, sc.status, sc.hours_monthly_target, sc.forecasted_contracted_hours,
            p.name AS project_name
        FROM supplierscontracts sc
        LEFT JOIN project p ON p.id = sc.project_id
        WHERE {TENANT_SCOPE} AND sc.supplier_id = ?
        ORDER BY sc.id DESC
        LIMIT 10", array($supplier_id));
} catch (\Throwable $t) { $contracts_list = array(); }

$page_title = 'إيكوبيشن | بطاقة المورد';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.supplier-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.supplier-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.supplier-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.supplier-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main supplier-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المورد';
    $header_icon    = 'fas fa-id-card-alt';
    $header_actions = array(
        array('href' => 'supplierscontracts.php?id=' . intval($supplier_id), 'class' => 'add-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود المورد'),
    );
    $header_back = array('href' => 'suppliers.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($supplier['name']); ?></h2>
        <div class="label">
            الكود: <?php echo htmlspecialchars($supplier['supplier_code'] ?: '-'); ?> |
            النوع: <?php echo htmlspecialchars($supplier['supplier_type'] ?: 'غير محدد'); ?> |
            الهاتف: <?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?> |
            الحالة: <?php echo (intval($supplier['status']) === 1) ? 'نشط' : 'معلق'; ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $equipments_count; ?></div><div class="label">عدد المعدات</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $contracts_count; ?></div><div class="label">عدد العقود</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $active_contracts; ?></div><div class="label">العقود النشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $projects_count; ?></div><div class="label">المشاريع المرتبطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($total_hours, 0); ?></div><div class="label">إجمالي ساعات العقود</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($timesheet_hours, 0); ?></div><div class="label">ساعات التشغيل الفعلية</div></div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-truck"></i> المعدات المرتبطة بالمورد</h5></div>
        <div class="card-body">
            <table id="supplierEquipmentsTable" class="display" style="width:100%;">
                <thead><tr><th>المعدة</th><th>الكود</th><th>عدد المشاريع</th><th>الساعات</th></tr></thead>
                <tbody>
                    <?php foreach ($equipments_breakdown as $row): ?>
                        <tr>
                            <td><a href="../Equipments/equipment_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['code']); ?></td>
                            <td><?php echo intval($row['projects_count']); ?></td>
                            <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-file-contract"></i> آخر عقود المورد</h5></div>
        <div class="card-body">
            <table id="supplierContractsTable" class="display" style="width:100%;">
                <thead><tr><th>المشروع</th><th>تاريخ التوقيع</th><th>مستهدف شهري</th><th>إجمالي ساعات</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php foreach ($contracts_list as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['project_name'] ?: 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($row['contract_signing_date']); ?></td>
                            <td><?php echo number_format($row['hours_monthly_target']); ?></td>
                            <td><?php echo number_format($row['forecasted_contracted_hours']); ?></td>
                            <td><?php echo (intval($row['status']) === 1) ? 'ساري' : 'منتهي'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#supplierEquipmentsTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
    $('#supplierContractsTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>
