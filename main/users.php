<?php
session_start();
// تضمين ملف الجلسات
include '../includes/sessions.php';
// تضمين قاعدة البيانات أولاً (قبل sidebar لأننا نحتاجها)
include '../config.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$users_has_company_id = db_table_has_column($conn, 'users', 'company_id');

// تجهيز أعمدة الحذف الناعم إن لم تكن موجودة
$users_has_is_deleted = db_table_has_column($conn, 'users', 'is_deleted');
$users_has_deleted_at = db_table_has_column($conn, 'users', 'deleted_at');
$users_has_deleted_by = db_table_has_column($conn, 'users', 'deleted_by');

if (!$users_has_is_deleted) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
}
if (!$users_has_deleted_at) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL");
}
if (!$users_has_deleted_by) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN deleted_by INT NULL");
}

$users_has_is_deleted = db_table_has_column($conn, 'users', 'is_deleted');
$users_has_deleted_at = db_table_has_column($conn, 'users', 'deleted_at');
$users_not_deleted_sql = $users_has_is_deleted ? "(COALESCE(is_deleted,0)=0)" : "1=1";

if ($users_has_company_id && $current_company_id <= 0) {
    echo "<script>alert('❌ الحساب غير مرتبط بشركة'); window.location.href='../login.php';</script>";
    exit;
}

$roles = array();
$roles_scope = array();
$roles_query = "SELECT id, name, role_scope FROM roles WHERE (parent_role_id IS NULL OR parent_role_id = 0) AND status = 1 ORDER BY level ASC, id ASC";
$roles_result = mysqli_query($conn, $roles_query);
if ($roles_result) {
    while ($role_row = mysqli_fetch_assoc($roles_result)) {
        $role_id = isset($role_row['id']) ? (string) $role_row['id'] : '';
        $role_name = isset($role_row['name']) ? trim($role_row['name']) : '';
        $scope_value = isset($role_row['role_scope']) ? trim($role_row['role_scope']) : 'gloable';
        if ($role_id !== '' && $role_name !== '') {
            $roles[$role_id] = $role_name;
            $roles_scope[$role_id] = ($scope_value === 'mine') ? 'mine' : 'gloable';
        }
    }
}

if (empty($roles)) {
    $roles = array(
        "1" => "مدير المشاريع",
        "2" => "مدير الموردين",
        "3" => "مدير المشغلين",
        "4" => "مدير الأسطول",
        "5" => "مدير موقع",
        "10" => "حركة وتشغيل"
    );

    $roles_scope = array(
        "1" => "gloable",
        "2" => "gloable",
        "3" => "gloable",
        "4" => "gloable",
        "5" => "mine",
        "10" => "mine"
    );
}

// حذف ناعم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

    $delete_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
    $delete_sql = "UPDATE users SET is_deleted = 1, deleted_at = NOW(), deleted_by = $current_user_id, updated_at = NOW() WHERE id = $delete_id AND role != '-1' AND $users_not_deleted_sql $delete_scope";

    if (@mysqli_query($conn, $delete_sql) && mysqli_affected_rows($conn) > 0) {
        echo "<script>alert('✅ تم حذف المستخدم بنجاح'); window.location.href='users.php';</script>";
    } else {
        echo "<script>alert('❌ حدث خطأ أثناء الحذف أو لا توجد صلاحية'); window.location.href='users.php';</script>";
    }
    exit;
}
// تعريف عنوان الصفحة
$page_title = 'Equipation | المستخدمين';
// تضمين الهيدر
include '../inheader.php';
// تضمين الشريط الجانبي
include '../insidebar.php';

// إضافة أو تعديل مستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $passwordRaw = isset($_POST['password']) ? trim($_POST['password']) : '';
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $selected_role_scope = 'gloable';
    $role_lookup_sql = "SELECT role_scope FROM roles WHERE id='" . mysqli_real_escape_string($conn, $role) . "' LIMIT 1";
    $role_lookup_result = mysqli_query($conn, $role_lookup_sql);
    if ($role_lookup_result && mysqli_num_rows($role_lookup_result) > 0) {
        $role_lookup_row = mysqli_fetch_assoc($role_lookup_result);
        if (isset($role_lookup_row['role_scope']) && $role_lookup_row['role_scope'] === 'mine') {
            $selected_role_scope = 'mine';
        }
    }

    $requires_project_context = ($selected_role_scope === 'mine');
    $project = ($requires_project_context && !empty($_POST['project_id'])) ? intval($_POST['project_id']) : 0;
    $mine = ($requires_project_context && !empty($_POST['mine_id'])) ? intval($_POST['mine_id']) : 0;
    $contract = ($requires_project_context && !empty($_POST['contract_id'])) ? intval($_POST['contract_id']) : 0;
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    if ($requires_project_context && ($project <= 0 || $mine <= 0 || $contract <= 0)) {
        echo "<script>alert('⚠️ هذا الدور مرتبط بمنجم محدد، يرجى اختيار المشروع والمنجم والعقد');</script>";
    } elseif ($uid > 0) {

        // تحقق من التكرار عند التعديل (يتجاهل السجل الحالي) - التحقق عالمي عبر جميع الشركات
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != '$uid' AND $users_not_deleted_sql LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // إذا تم إدخال كلمة مرور جديدة، نشفّرها ونحدّثها؛ وإلا لا نغيّرها
            $sql_pass = "";
            if ($passwordRaw !== '') {
                $hashedPass = mysqli_real_escape_string($conn, password_hash($passwordRaw, PASSWORD_BCRYPT));
                $sql_pass = ", password='$hashedPass'";
            }

                $company_update = ($users_has_company_id && $current_company_id > 0) ? ", company_id='$current_company_id'" : "";
                $update_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";

                $sql = "UPDATE users 
                    SET name='$name', username='$username', phone='$phone', role='$role', project_id='$project', mine_id='$mine', contract_id='$contract', updated_at=NOW() $sql_pass
                    $company_update
                    WHERE id='$uid' AND $users_not_deleted_sql $update_scope";
            if (mysqli_query($conn, $sql)) {
                echo "<script>alert('✅ تم التعديل بنجاح'); window.location.href='users.php';</script>";
            } else {
                echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
            }
        }
    } else {
        // تحقق من التكرار عند الإضافة - التحقق عالمي عبر جميع الشركات
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND $users_not_deleted_sql LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            if ($passwordRaw === '') {
                echo "<script>alert('⚠️ كلمة المرور مطلوبة عند إضافة مستخدم جديد');</script>";
            } else {
                $hashedPass = mysqli_real_escape_string($conn, password_hash($passwordRaw, PASSWORD_BCRYPT));

                $insert_columns = "name, username, password, phone, role, project_id, mine_id, contract_id, parent_id, created_at, updated_at";
                $insert_values  = "'$name', '$username', '$hashedPass', '$phone', '$role', '$project', '$mine', '$contract', '0', NOW(), NOW()";

                if ($users_has_company_id && $current_company_id > 0) {
                    $insert_columns .= ", company_id";
                    $insert_values .= ", '$current_company_id'";
                }

                $sql = "INSERT INTO users ($insert_columns) VALUES ($insert_values)";
                if (mysqli_query($conn, $sql)) {
                    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='users.php';</script>";
                } else {
                    echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
                }
            }
        }
    }
}

// ملاحظة: التعامل مع حذف المستخدم موجود في الواجهة (رابط ?delete=...) لكن كود الحذف كان معلقاً في النسخة الأصلية.
// إذا أردت أفعّل الحذف أضيفه لك هنا بأمان مع تحقق الصلاحيات.
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
    /* أنماط الأدوار بألوان مختلفة */
    .role-badge {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    /* مدير المشاريع */
    .role-badge.role-1 {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border-left: 3px solid #1d4ed8;
    }

    /* مدير الموردين */
    .role-badge.role-2 {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
        border-left: 3px solid #6d28d9;
    }

    /* مدير المشغلين */
    .role-badge.role-3 {
        background: linear-gradient(135deg, #ec4899, #be185d);
        color: white;
        border-left: 3px solid #be185d;
    }

    /* مدير الاسطول */
    .role-badge.role-4 {
        background: linear-gradient(135deg, #f97316, #c2410c);
        color: white;
        border-left: 3px solid #c2410c;
    }

    /* مدير الموقع / حركة وتشغيل */
    .role-badge.role-5,
    .role-badge.role-10 {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
        border-left: 3px solid #0891b2;
    }

    /* إضافة اللون أيضاً للصفوف عند التمرير */
    tr:hover .role-badge {
        transform: translateX(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
</style>

<body>

    <div class="main">

        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="title-icon"><i class="fas fa-users-cog"></i></div>
                <h1 class="page-title">إدارة المستخدمين</h1>
            </div>
            <div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
                <button id="toggleForm" class="add">
                    <i class="fas fa-plus-circle"></i> إضافة مستخدم
                </button>
            </div>
        </div>

        <?php

        $userRole = $_SESSION['user']['role'];
        $userName = $_SESSION['user']['name'];
        $roleText = isset($roles[$userRole]) ? $roles[$userRole] : "غير معروف";
        ?>

        <form id="projectForm" action="" method="post" style="display:none;">
            <input type="hidden" name="uid" id="uid" value="0" />
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> إضافة / تعديل مستخدم</h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div>
                            <label><i class="fas fa-user"></i> الاسم الثلاثي</label>
                            <input type="text" name="name" id="name" placeholder="الاسم الثلاثي" value="مستخدم" required />
                        </div>
                        <div>
                            <label><i class="fas fa-id-badge"></i> اسم المستخدم</label>
                            <input type="text" name="username" id="username" placeholder="اسم المستخدم (الحد الأدنى 3 أحرف)" value="username" required autocomplete="off" />
                            <small id="usernameFeedback" style="display:block; margin-top:5px; min-height:20px;"></small>
                        </div>
                        <div>
                            <label><i class="fas fa-lock"></i> كلمة المرور</label>
                            <input type="password" name="password" id="password" placeholder="كلمة المرور" value="12345678" />
                            <small class="text-muted"><i class="fas fa-info-circle"></i> اتركه فارغاً إذا لا تريد تغييره
                                عند التعديل</small>
                        </div>
                        <div>
                            <label><i class="fas fa-user-shield"></i> الدور / الصلاحية</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="">-- حدد الصلاحية --</option>
                                <?php foreach ($roles as $role_id => $role_name): ?>
                                    <option value="<?php echo htmlspecialchars((string) $role_id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required value="09209303903" />
                        </div>

                        <div id="projectDiv" style="display:none;">
                            <label><i class="fas fa-project-diagram"></i> المشروع <span
                                    style="color: red;">*</span></label>
                            <select id="project_id" name="project_id" class="form-control">
                                <option value="">-- اختر المشروع --</option>
                                <?php
                                $project_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
                                $project_not_deleted = db_table_has_column($conn, 'project', 'is_deleted') ? " AND COALESCE(is_deleted,0)=0" : "";
                                $sql = "SELECT id, name, project_code FROM project WHERE status = '1' $project_not_deleted $project_scope ORDER BY name ASC";
                                $result = mysqli_query($conn, $sql);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " ({$row['project_code']})</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div id="mineDiv" style="display:none;">
                            <label><i class="fas fa-mountain"></i> المنجم <span style="color: red;">*</span></label>
                            <select id="mine_id" name="mine_id" class="form-control">
                                <option value="">-- اختر المنجم --</option>
                            </select>
                        </div>

                        <div id="contractDiv" style="display:none;">
                            <label><i class="fas fa-file-contract"></i> العقد <span style="color: red;">*</span></label>
                            <select id="contract_id" name="contract_id" class="form-control">
                                <option value="">-- اختر العقد --</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center;">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i> مسح الحقول
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ المستخدم
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> قائمة المستخدمين</h5>
            </div>
            <div class="card-body">
                <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم </th>
                            <th>اسم المستخدم </th>
                            <th>كلمه المرور </th>
                            <th>الدور </th>
                            <th>رقم الهاتف</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $list_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
                        $query = "SELECT id, name, username, password, phone, role, project_id, mine_id, contract_id FROM users WHERE parent_id='0' AND role!='-1' AND $users_not_deleted_sql $list_scope ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $project_id = $row['project_id'];
                            $mine_id = $row['mine_id'];
                            $contract_id = $row['contract_id'];

                            $project_info = "";

                            $row_role_key = isset($row['role']) ? (string) $row['role'] : '';
                            $row_role_scope = isset($roles_scope[$row_role_key]) ? $roles_scope[$row_role_key] : 'gloable';

                            if ($row_role_scope === 'mine') {
                                // جلب اسم المشروع
                                if ($project_id > 0) {
                                    $select_project = mysqli_query($conn, "SELECT name, project_code FROM `project` WHERE `id` = $project_id");
                                    if ($project_row = mysqli_fetch_array($select_project)) {
                                        $project_info = "<div style='margin-top: 5px;'><i class='fas fa-project-diagram' style='color: var(--accent-color);'></i> " . htmlspecialchars($project_row['name'], ENT_QUOTES, 'UTF-8') . " (" . $project_row['project_code'] . ")";
                                    }
                                }

                                // جلب اسم المنجم
                                if ($mine_id > 0) {
                                    $select_mine = mysqli_query($conn, "SELECT mine_name, mine_code FROM `mines` WHERE `id` = $mine_id");
                                    if ($mine_row = mysqli_fetch_array($select_mine)) {
                                        $project_info .= "<br><i class='fas fa-mountain' style='color: var(--accent-color);'></i> " . htmlspecialchars($mine_row['mine_name'], ENT_QUOTES, 'UTF-8') . " (" . $mine_row['mine_code'] . ")";
                                    }
                                }

                                // جلب تاريخ العقد
                                if ($contract_id > 0) {
                                    $select_contract = mysqli_query($conn, "SELECT contract_signing_date FROM `contracts` WHERE `id` = $contract_id");
                                    if ($contract_row = mysqli_fetch_array($select_contract)) {
                                        $project_info .= "<br><i class='fas fa-file-contract' style='color: var(--accent-color);'></i> عقد #" . $contract_id . " - " . $contract_row['contract_signing_date'];
                                    }
                                }

                                if (!empty($project_info)) {
                                    $project_info = "<div style='font-size: 0.85rem; color: #6c757d; margin-top: 5px;'>" . $project_info . "</div></div>";
                                }
                            }

                            echo "<tr>";
                            echo "<td><strong>" . $i++ . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . $project_info . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                            echo "<td><span class='password-cell' style='letter-spacing:2px;color:#94a3b8;'>••••••••</span></td>";
                            echo "<td><span class='role-badge role-" . $row['role'] . "'>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</span></td>";
                            echo "<td><i class='fas fa-phone'></i>" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>
                                <div class='action-btns'>
                                <a href='javascript:void(0)' class='editBtn action-btn edit' 
                                   data-id='{$row['id']}' 
                                   data-name='" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-username='" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-role='{$row['role']}'
                                   data-project='{$row['project_id']}'
                                   data-mine='{$row['mine_id']}'
                                   data-contract='{$row['contract_id']}'
                                   title='تعديل'><i class='fas fa-edit'></i></a> 
                                <a href='?delete={$row['id']}' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>
                                </div>
                              </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </div>

    <!-- jQuery + DataTables -->
    <script src="../includes/js/jquery-3.7.1.main.js"></script>
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
    <script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

    <script>
document.addEventListener("DOMContentLoaded", function () {

    const roleScopes = <?php echo json_encode($roles_scope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const roleSelect = document.getElementById("role");
    const projectDiv = document.getElementById("projectDiv");
    const mineDiv = document.getElementById("mineDiv");
    const contractDiv = document.getElementById("contractDiv");

    const projectSelect = document.getElementById("project_id");
    const mineSelect = document.getElementById("mine_id");
    const contractSelect = document.getElementById("contract_id");

    const form = document.getElementById('projectForm');
    const usernameInput = document.getElementById("username");
    const usernameFeedback = document.getElementById("usernameFeedback");

    let usernameValid = true;

    function roleNeedsMineScope(roleId) {
        if (!roleId) {
            return false;
        }
        return roleScopes[String(roleId)] === "mine";
    }

    /* =============================
       إظهار الحقول حسب الدور
    ============================== */
    roleSelect.addEventListener("change", function () {

        if (roleNeedsMineScope(this.value)) {

            projectDiv.style.display = "block";
            mineDiv.style.display = "block";
            contractDiv.style.display = "block";

            projectSelect.required = true;
            mineSelect.required = true;
            contractSelect.required = true;

        } else {

            projectDiv.style.display = "none";
            mineDiv.style.display = "none";
            contractDiv.style.display = "none";

            projectSelect.required = false;
            mineSelect.required = false;
            contractSelect.required = false;

            projectSelect.value = "";
            mineSelect.innerHTML = '<option value="">-- اختر المنجم --</option>';
            contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';
        }
    });

    /* =============================
       تحميل المناجم
    ============================== */
    async function loadMines(projectId, selectedMine = null) {

        mineSelect.innerHTML = '<option value="">-- اختر المنجم --</option>';
        contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';

        if (!projectId) return;

        try {
            const response = await fetch(`../Projects/get_project_mines_ajax.php?project_id=${projectId}`);
            const data = await response.json();

            if (data.success && data.mines.length > 0) {
                data.mines.forEach(mine => {
                    const option = document.createElement('option');
                    option.value = mine.id;
                    option.textContent = `${mine.mine_name} (${mine.mine_code})`;
                    mineSelect.appendChild(option);
                });

                if (selectedMine) {
                    mineSelect.value = selectedMine;
                }
            }

        } catch (error) {
            console.error("خطأ في تحميل المناجم:", error);
        }
    }

    /* =============================
       تحميل العقود
    ============================== */
    async function loadContracts(mineId, selectedContract = null) {

        contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';

        if (!mineId) return;

        try {
            const response = await fetch(`../Contracts/get_mine_contracts_ajax.php?mine_id=${mineId}`);
            const data = await response.json();

            if (data.success && data.contracts.length > 0) {
                data.contracts.forEach(contract => {
                    const option = document.createElement('option');
                    option.value = contract.id;
                    option.textContent = `عقد #${contract.id} - ${contract.contract_signing_date}`;
                    contractSelect.appendChild(option);
                });

                if (selectedContract) {
                    contractSelect.value = selectedContract;
                }
            }

        } catch (error) {
            console.error("خطأ في تحميل العقود:", error);
        }
    }

    /* =============================
       عند تغيير المشروع
    ============================== */
    projectSelect.addEventListener("change", async function () {
        await loadMines(this.value);
    });

    /* =============================
       عند تغيير المنجم
    ============================== */
    mineSelect.addEventListener("change", async function () {
        await loadContracts(this.value);
    });

    /* =============================
       DataTable
    ============================== */
    $('#projectsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: 'نسخ' },
            { extend: 'excel', text: 'تصدير Excel' },
            { extend: 'csv', text: 'تصدير CSV' },
            { extend: 'pdf', text: 'تصدير PDF' },
            { extend: 'print', text: 'طباعة' }
        ],
        language: {
            url: "/ems/assets/i18n/datatables/ar.json"
        }
    });

    /* =============================
       إظهار / إخفاء النموذج
    ============================== */
    document.getElementById('toggleForm').addEventListener('click', function () {
        form.reset();
        document.getElementById("uid").value = 0;
        usernameFeedback.innerHTML = "";
        usernameInput.style.borderColor = "#e9ecef";
        usernameInput.style.boxShadow = "";
        usernameValid = true;
        form.style.display = (form.style.display === "none") ? "block" : "none";
    });

    /* =============================
       تعبئة الفورم عند التعديل
    ============================== */
    $(document).on('click', '.editBtn', async function () {

        const id = $(this).data('id');
        const name = $(this).data('name');
        const username = $(this).data('username');
        const phone = $(this).data('phone');
        const role = $(this).data('role');

        const projectId = $(this).data('project');
        const mineId = $(this).data('mine');
        const contractId = $(this).data('contract');

        $('#uid').val(id);
        $('#name').val(name);
        $('#username').val(username);
        $('#phone').val(phone);
        $('#role').val(role).trigger('change');

        // إعادة تعيين حالة التحقق من اسم المستخدم
        usernameFeedback.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> اسم المستخدم الحالي</span>';
        usernameInput.style.borderColor = "#e9ecef";
        usernameInput.style.boxShadow = "";
        usernameValid = true;

        if (roleNeedsMineScope(role)) {

            if (projectId) {
                $('#project_id').val(projectId);
                await loadMines(projectId, mineId);

                if (mineId) {
                    await loadContracts(mineId, contractId);
                }
            }
        }

        $('#password').val("");
        form.style.display = "block";

        $('html, body').animate({
            scrollTop: $(form).offset().top - 20
        }, 500);
    });

    /* =============================
       تحقق Ajax من اسم المستخدم أثناء الكتابة
    ============================== */
    usernameInput.addEventListener("input", async function () {
        const username = this.value.trim();
        const uid = document.getElementById("uid").value;

        // إعادة تعيين الحالة إذا كان المدخل فارغاً
        if (username === "") {
            usernameFeedback.innerHTML = "";
            usernameInput.style.borderColor = "#e9ecef";
            usernameInput.style.boxShadow = "";
            usernameValid = true;
            return;
        }

        // التحقق من الطول الأدنى
        if (username.length < 3) {
            usernameFeedback.innerHTML = '<span style="color: #ffc107;"><i class="fas fa-info-circle"></i> الحد الأدنى 3 أحرف</span>';
            usernameInput.style.borderColor = "#ffc107";
            usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(255, 193, 7, 0.25)";
            usernameValid = false;
            return;
        }

        // إظهار رسالة التحميل
        usernameFeedback.innerHTML = '<span style="color: #17a2b8;"><i class="fas fa-spinner fa-spin"></i> جاري التحقق...</span>';

        try {
            const response = await fetch("check_username_availability.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `username=${encodeURIComponent(username)}&uid=${uid}`
            });

            const data = await response.json();

            if (data.available) {
                usernameFeedback.innerHTML = `<span style="color: #28a745;"><i class="fas fa-check-circle"></i> ${data.message}</span>`;
                usernameValid = true;
                usernameInput.style.borderColor = "#28a745";
                usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(40, 167, 69, 0.25)";
            } else {
                usernameFeedback.innerHTML = `<span style="color: #dc3545;"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                usernameValid = false;
                usernameInput.style.borderColor = "#dc3545";
                usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(220, 53, 69, 0.25)";
            }
        } catch (error) {
            usernameFeedback.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> خطأ في التحقق</span>';
            console.error("خطأ:", error);
            usernameValid = false;
        }
    });

    /* =============================
       منع الإرسال إذا كان اسم المستخدم غير متاح
    ============================== */
    document.getElementById('projectForm').addEventListener('submit', function (e) {
        const username = usernameInput.value.trim();
        
        if (username !== "" && !usernameValid) {
            e.preventDefault();
            alert("⚠️ اسم المستخدم غير متاح، يرجى اختيار اسم آخر");
            usernameInput.focus();
            return false;
        }

        if (username === "") {
            e.preventDefault();
            alert("⚠️ يرجى إدخال اسم المستخدم");
            usernameInput.focus();
            return false;
        }
    });

});
</script>

</body>

</html>

