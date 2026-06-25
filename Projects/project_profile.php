<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌');
    exit();
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($project_id <= 0) {
    header('Location: projects.php?msg=معرف+المشروع+غير+صحيح+❌');
    exit();
}

$project_has_company = db_table_has_column($conn, 'project', 'company_id');
$project_has_is_deleted = db_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = db_table_has_column($conn, 'project', 'deleted_at');
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : (db_table_has_column($conn, 'project', 'company_client_id') ? 'company_client_id' : 'client_id');

$scope = "p.id = $project_id";
if (!$is_super_admin && $project_has_company) {
    $scope .= " AND p.company_id = $company_id";
}
if ($project_has_is_deleted) {
    $scope .= " AND COALESCE(p.is_deleted,0)=0";
} elseif ($project_has_deleted_at) {
    $scope .= " AND p.deleted_at IS NULL";
}

$project_query = "SELECT p.*, c.client_name
                  FROM project p
                  LEFT JOIN clients c ON c.id = p.$project_client_column
                  WHERE $scope
                  LIMIT 1";
$project_result = mysqli_query($conn, $project_query);
$project = ($project_result && mysqli_num_rows($project_result) > 0) ? mysqli_fetch_assoc($project_result) : null;

if (!$project) {
    header('Location: projects.php?msg=المشروع+غير+موجود+او+خارج+نطاق+الشركة+❌');
    exit();
}

$contracts_count = 0;
$active_contracts = 0;
$suppliers_count = 0;
$equipments_count = 0;
$drivers_count = 0;
$timesheet_hours = 0;
$mines_count = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM contracts WHERE project_id = $project_id");
if ($r) {
    $contracts_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM contracts WHERE project_id = $project_id AND status = 1");
if ($r) {
    $active_contracts = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT e.suppliers) AS c
                         FROM operations o
                         INNER JOIN equipments e ON e.id = o.equipment
                         WHERE o.project_id = $project_id");
if ($r) {
    $suppliers_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT o.equipment) AS c FROM operations o WHERE o.project_id = $project_id");
if ($r) {
    $equipments_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT ed.employee_id) AS c
                         FROM operations o
                         INNER JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                         WHERE o.project_id = $project_id AND ed.status = 1");
if ($r) {
    $drivers_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS c
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE o.project_id = $project_id AND t.status = 1");
if ($r) {
    $timesheet_hours = floatval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM mines WHERE project_id = $project_id AND status = 1");
if ($r) {
    $mines_count = intval(mysqli_fetch_assoc($r)['c']);
}

$suppliers_breakdown = mysqli_query($conn, "SELECT
                                s.id,
                                s.name,
                                COUNT(DISTINCT o.equipment) AS equipments_count,
                                IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS hours_sum
                            FROM operations o
                            INNER JOIN equipments e ON e.id = o.equipment
                            INNER JOIN suppliers s ON s.id = e.suppliers
                            LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                            WHERE o.project_id = $project_id
                            GROUP BY s.id, s.name
                            ORDER BY hours_sum DESC
                            LIMIT 10");

$page_title = 'إيكوبيشن | بطاقة المشروع';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.project-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.project-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.project-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.project-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main project-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المشروع';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => '../Contracts/contracts.php?filter_project_id=' . intval($project_id), 'class' => 'add-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود المشروع'),
        array('href' => 'project_mines.php?project_id=' . intval($project_id), 'class' => 'add-btn', 'icon' => 'fas fa-mountain', 'label' => 'مناجم المشروع'),
    );
    $header_back = array('href' => 'projects.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($project['name']); ?></h2>
        <div class="label">
            العميل: <?php echo htmlspecialchars($project['client_name'] ?: $project['client']); ?> |
            كود المشروع: <?php echo htmlspecialchars($project['project_code'] ?: '-'); ?> |
            كود المنجم: <?php echo htmlspecialchars($project['mine_code'] ?: '-'); ?> |
            الحالة: <?php echo intval($project['status']) === 1 ? 'نشط' : 'غير نشط'; ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الموقع: <?php echo htmlspecialchars($project['location'] ?: '-'); ?> |
            الولاية: <?php echo htmlspecialchars($project['state'] ?: '-'); ?> |
            المنطقة: <?php echo htmlspecialchars($project['region'] ?: '-'); ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $contracts_count; ?></div><div class="label">إجمالي العقود</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $active_contracts; ?></div><div class="label">العقود النشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $suppliers_count; ?></div><div class="label">الموردون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $equipments_count; ?></div><div class="label">المعدات</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $mines_count; ?></div><div class="label">المناجم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($timesheet_hours, 0); ?></div><div class="label">ساعات التشغيل</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-truck-loading"></i> الموردون المرتبطون بالمشروع</h5></div>
        <div class="card-body">
            <table id="projectSuppliersTable" class="display" style="width:100%;">
                <thead><tr><th>المورد</th><th>عدد المعدات</th><th>الساعات</th></tr></thead>
                <tbody>
                    <?php if ($suppliers_breakdown): while ($row = mysqli_fetch_assoc($suppliers_breakdown)): ?>
                        <tr>
                            <td><a href="../Suppliers/supplier_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo intval($row['equipments_count']); ?></td>
                            <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#projectSuppliersTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>
