<?php
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

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($client_id <= 0) {
    header("Location: clients.php?msg=معرف+العميل+غير+صحيح+❌");
    exit();
}

$clients_has_company = db_table_has_column($conn, 'clients', 'company_id');
$clients_has_is_deleted = db_table_has_column($conn, 'clients', 'is_deleted');
$clients_has_deleted_at = db_table_has_column($conn, 'clients', 'deleted_at');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');
$project_has_is_deleted = db_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = db_table_has_column($conn, 'project', 'deleted_at');
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : (db_table_has_column($conn, 'project', 'company_client_id') ? 'company_client_id' : 'client_id');

$client_scope_sql = "c.id = $client_id";
if (!$is_super_admin && $clients_has_company) {
    $client_scope_sql .= " AND c.company_id = $company_id";
}
if ($clients_has_is_deleted) {
    $client_scope_sql .= " AND COALESCE(c.is_deleted,0)=0";
} elseif ($clients_has_deleted_at) {
    $client_scope_sql .= " AND c.deleted_at IS NULL";
}

$client_query = "SELECT c.*, u.name AS creator_name
                 FROM clients c
                 LEFT JOIN users u ON u.id = c.created_by
                 WHERE $client_scope_sql
                 LIMIT 1";
$client_result = mysqli_query($conn, $client_query);
$client = ($client_result && mysqli_num_rows($client_result) > 0) ? mysqli_fetch_assoc($client_result) : null;

if (!$client) {
    header("Location: clients.php?msg=العميل+غير+موجود+او+خارج+نطاق+الشركة+❌");
    exit();
}

$project_scope = "p.$project_client_column = $client_id";
if (!$is_super_admin && $project_has_company) {
    $project_scope .= " AND p.company_id = $company_id";
}
if ($project_has_is_deleted) {
    $project_scope .= " AND COALESCE(p.is_deleted,0)=0";
} elseif ($project_has_deleted_at) {
    $project_scope .= " AND p.deleted_at IS NULL";
}

$projects_total = 0;
$projects_active = 0;
$contracts_count = 0;
$suppliers_count = 0;
$equipments_count = 0;
$drivers_count = 0;
$total_hours = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM project p WHERE $project_scope");
if ($r) {
    $projects_total = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM project p WHERE $project_scope AND p.status = 1");
if ($r) {
    $projects_active = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT COUNT(*) AS c
                         FROM contracts ct
                         INNER JOIN project p ON p.id = ct.project_id
                         WHERE $project_scope AND ct.status = 1");
if ($r) {
    $contracts_count = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT COUNT(DISTINCT e.suppliers) AS c
                         FROM operations o
                         INNER JOIN project p ON p.id = o.project_id
                         INNER JOIN equipments e ON e.id = o.equipment
                         WHERE $project_scope");
if ($r) {
    $suppliers_count = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT COUNT(DISTINCT o.equipment) AS c
                         FROM operations o
                         INNER JOIN project p ON p.id = o.project_id
                         WHERE $project_scope");
if ($r) {
    $equipments_count = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS c
                         FROM operations o
                         INNER JOIN project p ON p.id = o.project_id
                         INNER JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                         WHERE $project_scope AND ed.status = 1");
if ($r) {
    $drivers_count = intval(mysqli_fetch_assoc($r)['c']);
}

$r = mysqli_query($conn, "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS c
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         INNER JOIN project p ON p.id = o.project_id
                         WHERE $project_scope AND t.status = 1");
if ($r) {
    $total_hours = floatval(mysqli_fetch_assoc($r)['c']);
}

$projects_breakdown = mysqli_query($conn, "SELECT
                            p.id,
                            p.name,
                            p.project_code,
                            COUNT(DISTINCT o.equipment) AS equipments_count,
                            COUNT(DISTINCT e.suppliers) AS suppliers_count,
                            IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS hours_sum
                          FROM project p
                          LEFT JOIN operations o ON o.project_id = p.id
                          LEFT JOIN equipments e ON e.id = o.equipment
                          LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                          WHERE $project_scope
                          GROUP BY p.id, p.name, p.project_code
                          ORDER BY hours_sum DESC
                          LIMIT 10");

$page_title = 'إيكوبيشن | بطاقة العميل';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.client-profile-page .profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.client-profile-page .profile-card {
    background: #fff;
    border: 1px solid #ece6d8;
    border-radius: 12px;
    padding: 12px;
}
.client-profile-page .kpi {
    font-weight: 800;
    font-size: 1.4rem;
    color: #0f766e;
}
.client-profile-page .label {
    color: #6b7280;
    font-size: .9rem;
}
.client-profile-page .identity-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.client-profile-page .state-badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 700;
    background: #d1fae5;
    color: #065f46;
}
.client-profile-page .state-badge.off {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="main client-profile-page ems-unified-page-shell">
    <div class="main_head">
        <div class="head_actions">
            <a href="../Projects/projects.php?client_id=<?php echo intval($client_id); ?>" class="add-btn">
                <i class="fas fa-diagram-project"></i> مشاريع العميل
            </a>
        </div>
        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-id-card"></i></div>
            بطاقة العميل
        </h1>
        <div class="head_back">
            <a href="clients.php"><i class="fas fa-arrow-right"></i> رجوع</a>
        </div>
    </div>

    <div class="profile-card" style="margin-bottom:12px;">
        <div class="identity-head">
            <div>
                <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars($client['client_name']); ?></h2>
                <div class="label">الكود: <?php echo htmlspecialchars($client['client_code']); ?> | النوع: <?php echo htmlspecialchars($client['entity_type'] ?: 'غير محدد'); ?></div>
            </div>
            <span class="state-badge <?php echo ($client['status'] === 'نشط') ? '' : 'off'; ?>"><?php echo htmlspecialchars($client['status']); ?></span>
        </div>
        <div style="margin-top:10px;" class="label">
            القطاع: <?php echo htmlspecialchars($client['sector_category'] ?: 'غير محدد'); ?> |
            الهاتف: <?php echo htmlspecialchars($client['phone'] ?: '-'); ?> |
            البريد: <?php echo htmlspecialchars($client['email'] ?: '-'); ?> |
            أضيف بواسطة: <?php echo htmlspecialchars($client['creator_name'] ?: 'غير محدد'); ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $projects_total; ?></div><div class="label">إجمالي المشاريع</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $projects_active; ?></div><div class="label">المشاريع النشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $contracts_count; ?></div><div class="label">العقود النشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $suppliers_count; ?></div><div class="label">الموردون المرتبطون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $equipments_count; ?></div><div class="label">المعدات المرتبطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون المرتبطون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($total_hours, 0); ?></div><div class="label">إجمالي ساعات التشغيل</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-list"></i> ملخص مشاريع العميل</h5></div>
        <div class="card-body">
            <div class="table-container">
                <table class="display" id="clientProjectsTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>المشروع</th>
                            <th>كود المشروع</th>
                            <th>المعدات</th>
                            <th>الموردون</th>
                            <th>الساعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($projects_breakdown): while ($row = mysqli_fetch_assoc($projects_breakdown)): ?>
                            <tr>
                                <td><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                                <td><?php echo intval($row['equipments_count']); ?></td>
                                <td><?php echo intval($row['suppliers_count']); ?></td>
                                <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#clientProjectsTable').DataTable({
        language: { url: '/ems/assets/i18n/datatables/ar.json' }
    });
});
</script>
