<?php
/**
 * إدارة صلاحيات الأدوار - Role Permissions Management
 * ⭐ شاشة تحديد الصلاحيات لكل دور على كل صفحة
 */

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';
$page_title = "إدارة صلاحيات الأدوار";

// ════════════════════════════════════════════════════════════════════════════
// 📊 معالجة الطلبات الأساسية (CRUD)
// ════════════════════════════════════════════════════════════════════════════

$success_msg = null;
$error_msg = null;

// 1️⃣ حفظ صلاحيات الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    $role_id = $_POST['role_id'] ?? null;
    $module_id = $_POST['module_id'] ?? null;
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_add = isset($_POST['can_add']) ? 1 : 0;
    $can_edit = isset($_POST['can_edit']) ? 1 : 0;
    $can_delete = isset($_POST['can_delete']) ? 1 : 0;

    if (!$role_id || !$module_id) {
        $error_msg = 'الدور والصفحة مطلوبان ❌';
    } else {
        $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ?");
        $stmt->bind_param("ii", $role_id, $module_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare(
                "UPDATE role_permissions SET can_view = ?, can_add = ?, can_edit = ?, can_delete = ? 
                 WHERE role_id = ? AND module_id = ?"
            );
            $stmt->bind_param("iiiiii", $can_view, $can_add, $can_edit, $can_delete, $role_id, $module_id);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiiii", $role_id, $module_id, $can_view, $can_add, $can_edit, $can_delete);
        }

        if ($stmt->execute()) {
            $success_msg = 'تم حفظ الصلاحيات بنجاح ✅';
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// 2️⃣ حذف صلاحية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_permission') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        $error_msg = 'معرف الصلاحية غير صحيح ❌';
    } else {
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_msg = 'تم حذف الصلاحية بنجاح ✅';
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// 3️⃣ منح جميع الصلاحيات للدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grant_all') {
    $role_id = $_POST['role_id'] ?? null;

    if (!$role_id) {
        $error_msg = 'الدور مطلوب ❌';
    } else {
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();

        $modules_result = $conn->query("SELECT id FROM modules");
        $stmt = $conn->prepare(
            "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) 
             VALUES (?, ?, 1, 1, 1, 1)"
        );

        while ($module = $modules_result->fetch_assoc()) {
            $module_id = $module['id'];
            $stmt->bind_param("ii", $role_id, $module_id);
            $stmt->execute();
        }

        $success_msg = 'تم منح جميع الصلاحيات للدور ✅';
    }
}

// 4️⃣ إزالة جميع الصلاحيات من الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all') {
    $role_id = $_POST['role_id'] ?? null;

    if (!$role_id) {
        $error_msg = 'الدور مطلوب ❌';
    } else {
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);

        if ($stmt->execute()) {
            $success_msg = 'تم سحب جميع الصلاحيات من الدور ✅';
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 🛠️ دوال مساعدة
// ════════════════════════════════════════════════════════════════════════════

function get_parent_roles($conn, $role_id) {
    $parent_roles = [$role_id];
    
    $stmt = $conn->prepare("SELECT parent_role_id FROM roles WHERE id = ? AND parent_role_id IS NOT NULL");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['parent_role_id']) {
            $parent_roles[] = $row['parent_role_id'];
            $stmt2 = $conn->prepare("SELECT parent_role_id FROM roles WHERE id = ? AND parent_role_id IS NOT NULL");
            $stmt2->bind_param("i", $row['parent_role_id']);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row2 = $result2->fetch_assoc()) {
                if ($row2['parent_role_id'] && !in_array($row2['parent_role_id'], $parent_roles)) {
                    $parent_roles[] = $row2['parent_role_id'];
                }
            }
        }
    }
    
    return $parent_roles;
}

function get_assigned_modules($conn, $role_id) {
    $parent_roles = get_parent_roles($conn, $role_id);
    $parent_roles_list = implode(',', $parent_roles);
    
    $query = "SELECT DISTINCT m.id, m.name, m.code 
              FROM modules m
              WHERE m.owner_role_id IN ({$parent_roles_list})
              ORDER BY m.name";
    
    $result = $conn->query($query);
    $modules = [];
    
    while ($module = $result->fetch_assoc()) {
        $modules[] = $module;
    }
    
    return $modules;
}

// ════════════════════════════════════════════════════════════════════════════
// 📥 جلب البيانات
// ════════════════════════════════════════════════════════════════════════════

$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

$roles_result = $conn->query("SELECT id, name FROM roles WHERE status = 1 ORDER BY name");
$roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $roles[] = $role;
}

$modules = [];
if ($selected_role_id) {
    $modules = get_assigned_modules($conn, $selected_role_id);
}

$permissions_result = $conn->query(
    "SELECT rp.*, r.name as role_name, m.name as module_name, m.code as module_code 
     FROM role_permissions rp
     JOIN roles r ON rp.role_id = r.id
     JOIN modules m ON rp.module_id = m.id
     ORDER BY r.name, m.name"
);
$all_permissions = [];
while ($perm = $permissions_result->fetch_assoc()) {
    $all_permissions[] = $perm;
}

$permissions_map = [];
foreach ($all_permissions as $perm) {
    $permissions_map[$perm['role_id']][$perm['module_id']] = [
        'id' => $perm['id'],
        'can_view' => $perm['can_view'],
        'can_add' => $perm['can_add'],
        'can_edit' => $perm['can_edit'],
        'can_delete' => $perm['can_delete']
    ];
}

include("../inheader.php");
include('../insidebar.php');
?>

<style>
    :root {
        --navy: #0c1c3e;
        --navy-m: #132050;
        --gold: #e8b800;
        --gold-l: #ffd740;
        --blue: #2563eb;
        --teal: #0d9488;
    }

    .main {
        background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
        min-height: 100vh;
        padding: 2rem;
    }

    .header-title {
        color: var(--navy);
        font-weight: 700;
        font-size: 1.8rem;
        margin-bottom: 2rem;
    }

    .card-main {
        background: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
        margin-bottom: 2rem;
    }

    .card-header-custom {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .card-body-custom {
        padding: 2rem;
    }

    .filters-section {
        background: linear-gradient(135deg, #dcf0ff 0%, #e0f0ff 100%);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        border-right: 4px solid var(--blue);
    }

    .form-label {
        font-weight: 600;
        color: var(--navy);
        margin-bottom: 0.5rem;
    }

    .form-select {
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        padding: 0.75rem;
        transition: all 0.3s ease;
    }

    .form-select:focus {
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        border-right: 4px solid var(--gold);
        box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    }

    .stat-card.blue {
        border-right-color: var(--blue);
    }

    .stat-card.teal {
        border-right-color: var(--teal);
    }

    .stat-label {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--navy);
    }

    .permissions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .permission-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .permission-card:hover {
        border-color: var(--blue);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        transform: translateY(-2px);
    }

    .permission-card h6 {
        color: var(--navy);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .permission-code {
        font-size: 0.8rem;
        color: #999;
        margin-bottom: 1rem;
        font-family: monospace;
        background: #f5f5f5;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        display: inline-block;
    }

    .permission-checkbox {
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
    }

    .permission-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-left: 0.75rem;
        cursor: pointer;
        accent-color: var(--blue);
    }

    .permission-checkbox label {
        margin: 0;
        cursor: pointer;
        flex: 1;
    }

    .btn-save {
        background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        width: 100%;
        margin-top: 1rem;
        transition: all 0.3s ease;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .action-bar {
        background: linear-gradient(135deg, #e8f4fd 0%, #e0f0ff 100%);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-right: 4px solid var(--blue);
    }

    .action-bar-title {
        font-weight: 700;
        color: var(--navy);
        margin-bottom: 1rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .action-buttons form {
        display: inline;
    }

    .alert-custom {
        border: none;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        border-right: 4px solid;
    }

    .alert-success {
        background: linear-gradient(135deg, #d1f3d1 0%, #c8f0c8 100%);
        color: var(--teal);
        border-right-color: var(--teal);
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #dc2626;
        border-right-color: #ef4444;
    }

    .alert-info {
        background: linear-gradient(135deg, #e0f2fe 0%, #dcf0ff 100%);
        color: var(--blue);
        border-right-color: var(--blue);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #999;
    }

    .table-custom thead th {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
        color: white;
        font-weight: 600;
        border: none;
        padding: 1rem;
    }

    .table-custom tbody tr {
        border-bottom: 1px solid #e0e0e0;
    }

    .table-custom tbody tr:hover {
        background-color: #f8f9fa;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .status-badge.active {
        background-color: #d1f3d1;
        color: var(--teal);
    }

    .status-badge.inactive {
        background-color: #f0f0f0;
        color: #999;
    }

    .nav-link {
        color: var(--navy);
        border: none;
        border-bottom: 3px solid transparent;
        padding: 1rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: var(--blue);
    }

    .nav-link.active {
        color: var(--blue);
        border-bottom-color: var(--blue);
    }

    @media (max-width: 768px) {
        .permissions-grid {
            grid-template-columns: 1fr;
        }

        .main {
            padding: 1rem;
        }

        .card-body-custom {
            padding: 1rem;
        }
    }
</style>

<div class="main">
    <!-- الرأس -->
    <div class="header-title">
        <i class="fas fa-lock-open"></i> إدارة صلاحيات الأدوار
    </div>

    <!-- الرسائل -->
    <?php if ($success_msg): ?>
        <div class="alert alert-custom alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-custom alert-danger">
            <i class="fas fa-times-circle"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- الإحصائيات -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-label">عدد الأدوار</div>
            <div class="stat-number"><?php echo count($roles); ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">الشاشات المتاحة</div>
            <div class="stat-number"><?php echo count($modules); ?></div>
        </div>
        <div class="stat-card teal">
            <div class="stat-label">إجمالي الصلاحيات</div>
            <div class="stat-number"><?php echo count($all_permissions); ?></div>
        </div>
    </div>

    <!-- التبويبات -->
    <ul class="nav nav-tabs" role="tablist" style="border-bottom: 2px solid #e0e0e0;">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#grid-view">
                <i class="fas fa-th"></i> عرض الشبكة
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#table-view">
                <i class="fas fa-table"></i> عرض الجدول
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#quick-actions">
                <i class="fas fa-bolt"></i> إجراءات سريعة
            </button>
        </li>
    </ul>

    <!-- محتويات التبويبات -->
    <div class="tab-content mt-4">
        <!-- 1️⃣ عرض الشبكة -->
        <div class="tab-pane fade show active" id="grid-view">
            <div class="card-main">
                <div class="card-header-custom">
                    <i class="fas fa-sliders-h"></i> اختر الدور
                </div>
                <div class="card-body-custom">
                    <div class="filters-section">
                        <form method="GET" class="row">
                            <div class="col-md-6">
                                <label class="form-label">الدور</label>
                                <select name="role_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- اختر الدور --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                            <?php echo ($selected_role_id == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    <?php if ($selected_role_id): ?>
                        <?php $selected_role_name = ''; ?>
                        <?php foreach ($roles as $role):
                            if ($role['id'] == $selected_role_id) {
                                $selected_role_name = $role['name'];
                                break;
                            }
                        endforeach; ?>

                        <div class="action-bar">
                            <div class="action-bar-title">
                                <i class="fas fa-cog"></i> إدارة صلاحيات: <strong><?php echo htmlspecialchars($selected_role_name); ?></strong>
                            </div>
                            <div class="action-buttons">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="grant_all">
                                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" style="background: linear-gradient(135deg, var(--teal) 0%, #059669 100%); color: white; border: none;">
                                        <i class="fas fa-check-circle"></i> منح الكل
                                    </button>
                                </form>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="revoke_all">
                                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none;">
                                        <i class="fas fa-ban"></i> سحب الكل
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($modules)): ?>
                            <div class="permissions-grid">
                                <?php foreach ($modules as $module): ?>
                                    <div class="permission-card">
                                        <h6><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($module['name']); ?></h6>
                                        <div class="permission-code"><?php echo htmlspecialchars($module['code']); ?></div>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="save_permissions">
                                            <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">

                                            <?php
                                            $has_permission = isset($permissions_map[$selected_role_id][$module['id']]);
                                            $perm = $has_permission ? $permissions_map[$selected_role_id][$module['id']] : null;
                                            ?>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_view"
                                                        <?php echo ($perm && $perm['can_view']) ? 'checked' : ''; ?>>
                                                    👁️ عرض
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_add"
                                                        <?php echo ($perm && $perm['can_add']) ? 'checked' : ''; ?>>
                                                    ➕ إضافة
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_edit"
                                                        <?php echo ($perm && $perm['can_edit']) ? 'checked' : ''; ?>>
                                                    ✏️ تعديل
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_delete"
                                                        <?php echo ($perm && $perm['can_delete']) ? 'checked' : ''; ?>>
                                                    🗑️ حذف
                                                </label>
                                            </div>

                                            <button type="submit" class="btn-save">
                                                <i class="fas fa-save"></i> حفظ
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state alert-custom alert-info">
                                <i class="fas fa-info-circle"></i> لا توجد شاشات مسندة لهذا الدور
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state alert-custom alert-info">
                            <i class="fas fa-arrow-left"></i> اختر دوراً من القائمة لعرض الشاشات والصلاحيات
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2️⃣ عرض الجدول -->
        <div class="tab-pane fade" id="table-view">
            <div class="card-main">
                <div class="card-header-custom">
                    <i class="fas fa-list"></i> جميع الصلاحيات
                </div>
                <div class="card-body-custom">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>الدور</th>
                                    <th>الشاشة</th>
                                    <th>👁️ عرض</th>
                                    <th>➕ إضافة</th>
                                    <th>✏️ تعديل</th>
                                    <th>🗑️ حذف</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_permissions as $perm): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($perm['role_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($perm['module_name']); ?></td>
                                        <td><span class="status-badge <?php echo $perm['can_view'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_view'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_add'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_add'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_edit'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_edit'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_delete'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_delete'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_permission">
                                                <input type="hidden" name="id" value="<?php echo $perm['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; padding: 0.3rem 0.8rem;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3️⃣ إجراءات سريعة -->
        <div class="tab-pane fade" id="quick-actions">
            <div class="row">
                <div class="col-md-6">
                    <div class="card-main">
                        <div class="card-header-custom" style="background: linear-gradient(135deg, var(--teal) 0%, #059669 100%);">
                            <i class="fas fa-check-circle"></i> منح جميع الصلاحيات
                        </div>
                        <div class="card-body-custom">
                            <form method="POST">
                                <input type="hidden" name="action" value="grant_all">
                                <div class="mb-3">
                                    <label class="form-label">اختر الدور</label>
                                    <select name="role_id" class="form-select" required>
                                        <option value="">-- اختر الدور --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>">
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-save" style="background: linear-gradient(135deg, var(--teal) 0%, #059669 100%); margin-top: 0.5rem;"
                                    onclick="return confirm('سيتم منح جميع الصلاحيات. هل أنت متأكد؟')">
                                    <i class="fas fa-check-circle"></i> منح الكل
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card-main">
                        <div class="card-header-custom" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-ban"></i> سحب جميع الصلاحيات
                        </div>
                        <div class="card-body-custom">
                            <form method="POST">
                                <input type="hidden" name="action" value="revoke_all">
                                <div class="mb-3">
                                    <label class="form-label">اختر الدور</label>
                                    <select name="role_id" class="form-select" required>
                                        <option value="">-- اختر الدور --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>">
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-save" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); margin-top: 0.5rem;"
                                    onclick="return confirm('سيتم سحب جميع الصلاحيات. هل أنت متأكد؟')">
                                    <i class="fas fa-ban"></i> سحب الكل
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
