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

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    header('Location: users.php?msg=معرف+المستخدم+غير+صحيح+❌');
    exit();
}

$users_has_company = db_table_has_column($conn, 'users', 'company_id');
$users_has_is_deleted = db_table_has_column($conn, 'users', 'is_deleted');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');
$clients_has_company = db_table_has_column($conn, 'clients', 'company_id');

$scope = "u.id = $user_id";
if (!$is_super_admin && $users_has_company) {
    $scope .= " AND u.company_id = $company_id";
}
if ($users_has_is_deleted) {
    $scope .= " AND COALESCE(u.is_deleted,0)=0";
}

$user_query = "SELECT u.*, r.name AS role_name, p.name AS project_name
               FROM users u
               LEFT JOIN roles r ON r.id = u.role
               LEFT JOIN project p ON p.id = u.project_id
               WHERE $scope
               LIMIT 1";
$user_result = mysqli_query($conn, $user_query);
$user_data = ($user_result && mysqli_num_rows($user_result) > 0) ? mysqli_fetch_assoc($user_result) : null;

if (!$user_data) {
    header('Location: users.php?msg=المستخدم+غير+موجود+او+خارج+نطاق+الشركة+❌');
    exit();
}

$projects_created = 0;
$clients_created = 0;
$suppliers_created = 0;
$last_login = !empty($user_data['last_login_at']) ? $user_data['last_login_at'] : '-';

$project_creator_exists = db_table_has_column($conn, 'project', 'created_by');
$clients_creator_exists = db_table_has_column($conn, 'clients', 'created_by');
$suppliers_creator_exists = db_table_has_column($conn, 'suppliers', 'created_by');

if ($project_creator_exists) {
    $project_scope = "created_by = $user_id";
    if (!$is_super_admin && $project_has_company) {
        $project_scope .= " AND company_id = $company_id";
    }
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM project WHERE $project_scope");
    if ($r) {
        $projects_created = intval(mysqli_fetch_assoc($r)['c']);
    }
}

if ($clients_creator_exists) {
    $client_scope = "created_by = $user_id";
    if (!$is_super_admin && $clients_has_company) {
        $client_scope .= " AND company_id = $company_id";
    }
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM clients WHERE $client_scope");
    if ($r) {
        $clients_created = intval(mysqli_fetch_assoc($r)['c']);
    }
}

if ($suppliers_creator_exists) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM suppliers WHERE created_by = $user_id");
    if ($r) {
        $suppliers_created = intval(mysqli_fetch_assoc($r)['c']);
    }
}

$project_assignments = mysqli_query($conn, "SELECT id, name, project_code, status
                                           FROM project
                                           WHERE id = " . intval($user_data['project_id']) . "
                                           LIMIT 1");

$page_title = 'إيكوبيشن | بطاقة المستخدم';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.user-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.user-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.user-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.user-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main user-profile-page ems-unified-page-shell">
    <div class="main_head">
        <div class="head_actions"></div>
        <h1 class="head-title"><div class="title-icon"><i class="fas fa-id-card"></i></div>بطاقة المستخدم</h1>
        <div class="head_back"><a href="users.php"><i class="fas fa-arrow-right"></i> رجوع</a></div>
    </div>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($user_data['name']); ?></h2>
        <div class="label">
            اسم المستخدم: <?php echo htmlspecialchars($user_data['username']); ?> |
            الدور: <?php echo htmlspecialchars($user_data['role_name'] ?: $user_data['role']); ?> |
            الهاتف: <?php echo htmlspecialchars($user_data['phone'] ?: '-'); ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الحالة: <?php echo htmlspecialchars($user_data['status']); ?> |
            آخر دخول: <?php echo htmlspecialchars($last_login); ?> |
            تاريخ الإنشاء: <?php echo htmlspecialchars($user_data['created_at']); ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $projects_created; ?></div><div class="label">مشاريع أنشأها</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $clients_created; ?></div><div class="label">عملاء أضافهم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $suppliers_created; ?></div><div class="label">موردون أضافهم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo !empty($user_data['project_id']) ? 1 : 0; ?></div><div class="label">لديه مشروع مكلّف</div></div>
        <div class="profile-card"><div class="kpi"><?php echo !empty($user_data['contract_id']) ? 1 : 0; ?></div><div class="label">لديه عقد مكلّف</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-project-diagram"></i> المشروع المكلّف به</h5></div>
        <div class="card-body">
            <table id="userProjectTable" class="display" style="width:100%;">
                <thead><tr><th>اسم المشروع</th><th>كود المشروع</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php if ($project_assignments && mysqli_num_rows($project_assignments) > 0): $p = mysqli_fetch_assoc($project_assignments); ?>
                        <tr>
                            <td><a href="../Projects/project_profile.php?id=<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($p['project_code'] ?: '-'); ?></td>
                            <td><?php echo intval($p['status']) === 1 ? 'نشط' : 'غير نشط'; ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="3">لا يوجد مشروع مكلّف به</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#userProjectTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>
