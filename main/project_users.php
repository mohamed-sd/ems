<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$users_has_company_id = db_table_has_column($conn, 'users', 'company_id');

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
$users_not_deleted_sql = $users_has_is_deleted ? "(COALESCE(u.is_deleted,0)=0)" : "1=1";
$users_has_employee_id = db_table_has_column($conn, 'users', 'employee_id'); // ربط المعاون بموظف (قاعدة: لا حساب بلا موظف)

if ($users_has_company_id && $current_company_id <= 0) {
    header("Location: ../login.php?msg=الحساب+غير+مرتبط+بشركة+❌");
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم على وحدة المشرفين
// ══════════════════════════════════════════════════════════════════════════════
$_currentUserRole = intval($_SESSION['user']['role']);

// البحث عن معرف الوحدة مع مراعاة دور المستخدم الحالي
$module_query = "SELECT id FROM modules
                WHERE (code = 'main/project_users.php'
                    OR code = 'project_users'
                    OR code LIKE '%project_users%')
                AND owner_role_id = $_currentUserRole
                LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

// إذا لم يوجد سجل خاص بهذا الدور، افترض جميع الصلاحيات (للتوافق مع الأدوار القديمة)
if (!$module_id) {
    $can_view = $can_add = $can_edit = $can_delete = true;
} else {
    $can_view = false;
    $can_add = false;
    $can_edit = false;
    $can_delete = false;

    $perms = get_module_permissions($conn, $module_id);
    $can_view = $perms['can_view'];
    $can_add = $perms['can_add'];
    $can_edit = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+صفحة+المشرفين+❌");
    exit();
}

$page_title = "إيكوبيشن | المشرفون";

// جلب اسم صلاحية المستخدم الحالي
$currentRole = $_SESSION['user']['role'];
$roleNameQuery = "SELECT name FROM roles WHERE id = $currentRole LIMIT 1";
$roleNameResult = mysqli_query($conn, $roleNameQuery);
$roleName = '';
if ($roleNameResult && $roleRow = mysqli_fetch_assoc($roleNameResult)) {
    $roleName = htmlspecialchars($roleRow['name'], ENT_QUOTES, 'UTF-8');
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: project_users.php?msg=لا+توجد+صلاحية+حذف+المستخدمين+❌");
        exit();
    }
    $deleteId = intval($_GET['delete']);
    $userid = $_SESSION['user']['id'];

    // التحقق من أن المستخدم المراد حذفه تابع للمستخدم الحالي أو من دور تابع
    $verifyQuery = "SELECT u.id FROM users u
                    WHERE u.id = $deleteId
                    " . ($users_has_company_id ? "AND u.company_id = $current_company_id" : "") . "
                    AND $users_not_deleted_sql
                    AND (u.parent_id = '$userid' OR u.role IN (
                        SELECT r.id FROM roles r
                        WHERE r.parent_role_id = {$_SESSION['user']['role']}
                        AND (r.status = '1' OR r.status = 1)
                    ))";

    $verifyResult = mysqli_query($conn, $verifyQuery);

    if ($verifyResult && mysqli_num_rows($verifyResult) > 0) {
        $deleteBy = intval($_SESSION['user']['id']);
        $delete_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
        $deleteSQL = "UPDATE users SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleteBy, updated_at = NOW() WHERE id = $deleteId AND COALESCE(is_deleted,0)=0 $delete_scope";
        if (mysqli_query($conn, $deleteSQL)) {
            header("Location: project_users.php?msg=تم+حذف+المستخدم+بنجاح+✅");
            exit;
        } else {
            header("Location: project_users.php?msg=حدث+خطأ+أثناء+الحذف+❌");
            exit;
        }
    } else {
        header("Location: project_users.php?msg=ليس+لديك+صلاحية+لحذف+هذا+المستخدم+❌");
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة التعديل
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!$can_edit) {
        header("Location: project_users.php?msg=لا+توجد+صلاحية+تعديل+المستخدمين+❌");
        exit();
    }
    $userId = intval($_POST['user_id']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $passwordRaw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    // منع تصعيد الصلاحيات: الدور يجب أن يكون فعلاً من الأدوار الأبناء للمستخدم الحالي
    // (تقييد القائمة في الواجهة وحده لا يكفي — يمكن تزوير الطلب).
    $role_check_id = intval($_POST['role']);
    $role_chk = mysqli_query($conn, "SELECT 1 FROM roles
            WHERE id = $role_check_id
            AND parent_role_id = " . intval($_SESSION['user']['role']) . "
            AND (status = '1' OR status = 1) LIMIT 1");
    if (!$role_chk || mysqli_num_rows($role_chk) === 0) {
        header("Location: project_users.php?msg=صلاحية+غير+مسموحة+❌");
        exit;
    }
    $userid = $_SESSION['user']['id'];

    // التحقق من أن المستخدم المراد تعديله تابع للمستخدم الحالي
    $verifyQuery = "SELECT u.id FROM users u
                    WHERE u.id = $userId
                    " . ($users_has_company_id ? "AND u.company_id = $current_company_id" : "") . "
                    AND $users_not_deleted_sql
                    AND (u.parent_id = '$userid' OR u.role IN (
                        SELECT r.id FROM roles r
                        WHERE r.parent_role_id = {$_SESSION['user']['role']}
                        AND (r.status = '1' OR r.status = 1)
                    ))";

    $verifyResult = mysqli_query($conn, $verifyQuery);

    if (!$verifyResult || mysqli_num_rows($verifyResult) === 0) {
        header("Location: project_users.php?msg=ليس+لديك+صلاحية+لتعديل+هذا+المستخدم+❌");
        exit;
    }

    // 🔗 ربط الموظف (إلزامي) عند التعديل
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    if ($users_has_employee_id && $employee_link_id <= 0) {
        header("Location: project_users.php?msg=يجب+إسناد+موظف+لهذا+الحساب+❌");
        exit;
    }
    if ($employee_link_id > 0) {
        $emp_company_cond = (db_table_has_column($conn, 'employees', 'company_id') && $current_company_id > 0) ? " AND company_id = $current_company_id" : "";
        $emp_chk  = mysqli_query($conn, "SELECT id FROM employees WHERE id = $employee_link_id $emp_company_cond LIMIT 1");
        $link_chk = mysqli_query($conn, "SELECT id FROM users WHERE employee_id = $employee_link_id AND id != $userId AND COALESCE(is_deleted,0)=0 LIMIT 1");
        if (!$emp_chk || mysqli_num_rows($emp_chk) === 0 || ($link_chk && mysqli_num_rows($link_chk) > 0)) {
            header("Location: project_users.php?msg=الموظف+غير+صالح+أو+مرتبط+بحساب+آخر+❌");
            exit;
        }
    }
    $sql_employee = $users_has_employee_id ? ", employee_id = '$employee_link_id'" : "";

    // تحقق من تكرار اسم المستخدم (ما عدا المستخدم الحالي)
    $check_query = "SELECT id FROM users WHERE username = '$username' AND id != $userId AND COALESCE(is_deleted,0)=0";
    if ($users_has_company_id) {
        $check_query .= " AND company_id = $current_company_id";
    }
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=اسم+المستخدم+موجود+مسبقاً+❌");
        exit;
    }

    // تحديث المستخدم
    $passwordUpdate = '';
    if ($passwordRaw !== '') {
        $hashedPassword = mysqli_real_escape_string($conn, password_hash($passwordRaw, PASSWORD_DEFAULT));
        $passwordUpdate = ", password = '$hashedPassword'";
    }

    $updateSQL = "UPDATE users SET name = '$name', username = '$username', phone = '$phone', role = '$role', updated_at = NOW() $passwordUpdate $sql_employee";
    if ($users_has_company_id && $current_company_id > 0) {
        $updateSQL .= ", company_id = '$current_company_id'";
    }
    $updateSQL .= " WHERE id = $userId";

    if (mysqli_query($conn, $updateSQL)) {
        header("Location: project_users.php?msg=تم+تعديل+المستخدم+بنجاح+✅");
        exit;
    } else {
        header("Location: project_users.php?msg=حدث+خطأ+أثناء+التعديل+❌");
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة مستخدم جديد
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && (!isset($_POST['action']) || $_POST['action'] === 'add')) {
    if (!$can_add) {
        header("Location: project_users.php?msg=لا+توجد+صلاحية+إضافة+مستخدمين+جدد+❌");
        exit();
    }
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    // تُجزَّأ كلمة المرور الخام ثم يُهرَّب الـhash فقط (لا تُهرَّب قبل التجزئة — وإلا فشل الدخول مع الرموز الخاصة).
    $passwordRaw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $hashedPassword = mysqli_real_escape_string($conn, password_hash($passwordRaw, PASSWORD_DEFAULT));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    // منع تصعيد الصلاحيات: الدور يجب أن يكون فعلاً من الأدوار الأبناء للمستخدم الحالي
    // (تقييد القائمة في الواجهة وحده لا يكفي — يمكن تزوير الطلب).
    $role_check_id = intval($_POST['role']);
    $role_chk = mysqli_query($conn, "SELECT 1 FROM roles
            WHERE id = $role_check_id
            AND parent_role_id = " . intval($_SESSION['user']['role']) . "
            AND (status = '1' OR status = 1) LIMIT 1");
    if (!$role_chk || mysqli_num_rows($role_chk) === 0) {
        header("Location: project_users.php?msg=صلاحية+غير+مسموحة+❌");
        exit;
    }
    $project = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    $parent_id = intval($_SESSION['user']['id']);

    // 🔗 ربط الموظف (إلزامي): لا يوجد حساب معاون يعمل بلا موظف مُسنَد.
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    if ($users_has_employee_id && $employee_link_id <= 0) {
        header("Location: project_users.php?msg=يجب+إسناد+موظف+لهذا+الحساب+❌");
        exit;
    }
    if ($employee_link_id > 0) {
        $emp_company_cond = (db_table_has_column($conn, 'employees', 'company_id') && $current_company_id > 0) ? " AND company_id = $current_company_id" : "";
        $emp_chk  = mysqli_query($conn, "SELECT id FROM employees WHERE id = $employee_link_id $emp_company_cond LIMIT 1");
        $link_chk = mysqli_query($conn, "SELECT id FROM users WHERE employee_id = $employee_link_id AND COALESCE(is_deleted,0)=0 LIMIT 1");
        if (!$emp_chk || mysqli_num_rows($emp_chk) === 0 || ($link_chk && mysqli_num_rows($link_chk) > 0)) {
            header("Location: project_users.php?msg=الموظف+غير+صالح+أو+مرتبط+بحساب+آخر+❌");
            exit;
        }
    }

    // تحقق من تكرار اسم المستخدم
    $check_query = "SELECT id FROM users WHERE username = '$username' AND COALESCE(is_deleted,0)=0";
    if ($users_has_company_id) {
        $check_query .= " AND company_id = $current_company_id";
    }
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=اسم+المستخدم+موجود+مسبقاً+❌");
        exit;
    }

    // إضافة مستخدم جديد
    $insert_columns = "name, username, password, phone, role , role_id , project_id, parent_id, created_at, updated_at";
    $insert_values = "'$name', '$username', '$hashedPassword', '$phone', '$role', '$role' , '$project', '$parent_id', NOW(), NOW()";
    if ($users_has_company_id && $current_company_id > 0) {
        $insert_columns .= ", company_id";
        $insert_values .= ", '$current_company_id'";
    }
    if ($users_has_employee_id) {
        $insert_columns .= ", employee_id";
        $insert_values .= ", '$employee_link_id'";
    }
    $sql = "INSERT INTO users ($insert_columns) VALUES ($insert_values)";

    if (mysqli_query($conn, $sql)) {
        header("Location: project_users.php?msg=تم+إضافة+المستخدم+بنجاح+✅");
        exit;
    } else {
        header("Location: project_users.php?msg=حدث+خطأ+أثناء+الإضافة+❌");
        exit;
    }
}

// قائمة الموظفين المتاحين للربط (موظفو الشركة + معرّف الحساب المرتبط إن وُجد).
$employees_for_link = array();
$emp_name_by_id = array();
if ($users_has_employee_id) {
    $emp_has_company = db_table_has_column($conn, 'employees', 'company_id');
    $emp_scope = ($emp_has_company && $current_company_id > 0) ? " WHERE e.company_id = $current_company_id" : "";
    $emp_sql = "SELECT e.id, e.name, e.phone,
                       (SELECT u2.id FROM users u2 WHERE u2.employee_id = e.id AND COALESCE(u2.is_deleted,0)=0 LIMIT 1) AS linked_uid
                FROM employees e $emp_scope ORDER BY e.name ASC";
    $emp_res = mysqli_query($conn, $emp_sql);
    if ($emp_res) {
        while ($er = mysqli_fetch_assoc($emp_res)) {
            $employees_for_link[] = array(
                'id'         => intval($er['id']),
                'name'       => $er['name'],
                'phone'      => $er['phone'],
                'linked_uid' => ($er['linked_uid'] !== null) ? intval($er['linked_uid']) : 0,
            );
            $emp_name_by_id[intval($er['id'])] = $er['name'];
        }
    }
}

$page_title = "إيكوبيشن | المشرفون";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main project-users-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon       = 'fas fa-users-cog';
    $header_title_html = 'إدارة مشرفين ' . (!empty($roleName) ? '- ' . $roleName : '');
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مشرف جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مستخدم -->
    <form id="projectForm" action="" method="post" class="allforms">
        <input type="hidden" id="action" name="action" value="add">
        <input type="hidden" id="user_id" name="user_id" value="">
        <div class="card shadow-sm pu-form-card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة مستخدم جديد</span></h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user"></i> الاسم ثلاثي *</label>
                        <input type="text" name="name" id="name" placeholder="أدخل الاسم ثلاثي" value="" required />
                    </div>
                    <div>
                        <label><i class="fas fa-at"></i> اسم المستخدم *</label>
                        <input type="text" name="username" id="username" placeholder="أدخل اسم المستخدم" value=""
                            required autocomplete="off" />
                        <small id="usernameFeedback" class="pu-username-feedback"></small>
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> كلمة المرور <span id="passwordRequired">*</span></label>
                        <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" value="" />
                        <small id="passwordHint" class="pu-password-hint pu-hidden">اتركه فارغاً للاحتفاظ بكلمة المرور
                            الحالية</small>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف *</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" required value="" />
                    </div>
                    <div>
                        <label><i class="fas fa-shield-alt"></i> الصلاحية / الدور *</label>
                        <select name="role" id="role" required>
                            <option value="">-- اختر الصلاحية --</option>
                            <?php
                            // جلب الأدوار التابعة للدور الحالي من قاعدة البيانات
                            $currentRole = $_SESSION['user']['role'];
                            $rolesQuery = "SELECT id, name FROM roles
                                           WHERE parent_role_id = $currentRole
                                           AND (status = '1' OR status = 1)
                                           ORDER BY id ASC";
                            $rolesResult = mysqli_query($conn, $rolesQuery);

                            if ($rolesResult && mysqli_num_rows($rolesResult) > 0) {
                                while ($roleRow = mysqli_fetch_assoc($rolesResult)) {
                                    echo '<option value="' . $roleRow['id'] . '">' .
                                        htmlspecialchars($roleRow['name'], ENT_QUOTES, 'UTF-8') .
                                        '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>لا توجد صلاحيات متاحة</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php if ($users_has_employee_id): ?>
                    <div>
                        <label><i class="fas fa-id-card-alt"></i> الموظف المُسنَد *</label>
                        <select name="employee_id" id="employee_id_link" required>
                            <option value="">— اختر الموظف —</option>
                            <?php foreach ($employees_for_link as $emp): ?>
                                <option value="<?= intval($emp['id']) ?>"
                                    data-linked-uid="<?= intval($emp['linked_uid']) ?>"
                                    data-name="<?= htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-phone="<?= htmlspecialchars((string) $emp['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="pu-password-hint">إلزامي — لا حساب يعمل بلا موظف مُسنَد. تُعبّأ بيانات الموظف تلقائياً عند الاختيار.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ المستخدم</span>
                    </button>
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('projectForm').classList.remove('allforms-visible');">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <table id="usersTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>رقم الهاتف</th>
                        <th>الصلاحية</th>
                        <th>الموظف المرتبط</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المستخدمين التابعين للمدير الحالي
                    // 1. المستخدمون الذين parent_id = المستخدم الحالي
                    // 2. المستخدمون من الأدوار التابعة للدور الحالي
                    $userid = $_SESSION['user']['id'];
                    $currentRole = $_SESSION['user']['role'];

                    $query = "SELECT DISTINCT u.id, u.name, u.username, u.phone, u.role, u.employee_id, u.created_at, ro.name AS role_name
                             FROM users u
                             LEFT JOIN roles ro ON ro.id = u.role
                                                  WHERE " . ($users_has_company_id ? "u.company_id = '$current_company_id' AND " : "") . "COALESCE(u.is_deleted,0)=0 AND (
                                          u.parent_id = '$userid'
                                OR u.role IN (
                                   SELECT r.id FROM roles r
                                   WHERE r.parent_role_id = $currentRole
                                   AND (r.status = '1' OR r.status = 1)
                                )
                                      )
                                      ORDER BY u.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;

                    if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $roleText = !empty($row['role_name'])
                            ? htmlspecialchars($row['role_name'], ENT_QUOTES, 'UTF-8')
                            : '<span class="pu-text-muted">غير معروف</span>';
                        $createdDate = date('Y-m-d', strtotime($row['created_at']));

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td><code class='pu-code'>" . htmlspecialchars($row['username']) . "</code></td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . $roleText . "</td>";
                        $linked_emp_id = isset($row['employee_id']) ? intval($row['employee_id']) : 0;
                        if ($linked_emp_id > 0 && isset($emp_name_by_id[$linked_emp_id])) {
                            echo "<td><a class='client-name-link' href='../Employees/employee_profile.php?id=" . $linked_emp_id . "'><i class='fas fa-id-card-alt'></i> " . htmlspecialchars($emp_name_by_id[$linked_emp_id], ENT_QUOTES, 'UTF-8') . "</a></td>";
                        } else {
                            echo "<td><span class='pu-text-muted'>— غير مرتبط —</span></td>";
                        }
                        echo "<td>" . $createdDate . "</td>";

                        $action_btns = "<td><div class='action-btns'>";
                        if ($can_edit) {
                            $action_btns .= "<a href='javascript:void(0)'
                                       class='action-btn edit'
                                       onclick='editUser({$row['id']}, \"" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "\", \""
                                . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "\", \""
                                . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "\", {$row['role']}, " . intval($row['employee_id']) . ")'
                                       title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            $action_btns .= "<a href='project_users.php?delete={$row['id']}'
                                       class='action-btn delete'
                                       onclick=\"return confirm('هل أنت متأكد من حذف هذا المستخدم؟')\"
                                       title='حذف'><i class='fas fa-trash'></i></a>";
                        }
                        $action_btns .= "</div></td>";
                        echo $action_btns;
                        echo "</tr>";
                    }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (مطلوب أولاً) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    (function () {

        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#usersTable').DataTable({
                responsive: true,
                dom: 'Bfrtip', // أزرار + بحث + ترقيم الصفحات
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');

        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.classList.toggle('allforms-visible');
                // تنظيف الحقول عند الإضافة
                if (projectForm.classList.contains('allforms-visible')) {
                    resetForm();
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                }
            });
        }

        const usernameInput = document.getElementById('username');
        const usernameFeedback = document.getElementById('usernameFeedback');
        let usernameValid = true;

        // ===== ربط الموظف (إلزامي) =====
        const employeeSelect = document.getElementById('employee_id_link');
        function refreshEmployeeOptions(currentUid) {
            if (!employeeSelect) return;
            currentUid = String(currentUid || 0);
            Array.from(employeeSelect.options).forEach(function (opt) {
                if (!opt.value) return;
                const linked = String(opt.dataset.linkedUid || '0');
                opt.disabled = (linked !== '0' && linked !== currentUid);
            });
        }
        if (employeeSelect) {
            employeeSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    if (opt.dataset.name) document.getElementById('name').value = opt.dataset.name;
                    if (opt.dataset.phone && opt.dataset.phone.trim() !== '') document.getElementById('phone').value = opt.dataset.phone;
                }
            });
        }

        function setUsernameFeedback(state, message) {
            usernameFeedback.className = 'pu-username-feedback pu-feedback-' + state;
            usernameFeedback.innerHTML = message;
        }

        // تحقق اسم المستخدم أثناء الكتابة
        usernameInput.addEventListener('input', async function () {
            const username = this.value.trim();
            const uid = document.getElementById('user_id').value || 0;

            if (username === '') {
                usernameFeedback.innerHTML = '';
                usernameFeedback.className = 'pu-username-feedback';
                usernameInput.classList.remove('pu-input-warn', 'pu-input-success', 'pu-input-error');
                usernameValid = true;
                return;
            }
            if (username.length < 3) {
                setUsernameFeedback('warn', '<span><i class="fas fa-info-circle"></i> الحد الأدنى 3 أحرف</span>');
                usernameInput.classList.remove('pu-input-success', 'pu-input-error');
                usernameInput.classList.add('pu-input-warn');
                usernameValid = false;
                return;
            }
            setUsernameFeedback('info', '<span><i class="fas fa-spinner fa-spin"></i> جاري التحقق...</span>');
            try {
                const response = await fetch('check_username_availability.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `username=${encodeURIComponent(username)}&uid=${encodeURIComponent(uid)}`
                });
                const data = await response.json();
                if (data.available) {
                    setUsernameFeedback('ok', `<span><i class="fas fa-check-circle"></i> ${data.message}</span>`);
                    usernameInput.classList.remove('pu-input-warn', 'pu-input-error');
                    usernameInput.classList.add('pu-input-success');
                    usernameValid = true;
                } else {
                    setUsernameFeedback('error', `<span><i class="fas fa-times-circle"></i> ${data.message}</span>`);
                    usernameInput.classList.remove('pu-input-warn', 'pu-input-success');
                    usernameInput.classList.add('pu-input-error');
                    usernameValid = false;
                }
            } catch (error) {
                setUsernameFeedback('error', '<span><i class="fas fa-exclamation-triangle"></i> خطأ في التحقق</span>');
                usernameInput.classList.remove('pu-input-warn', 'pu-input-success');
                usernameInput.classList.add('pu-input-error');
                usernameValid = false;
            }
        });

        // منع الإرسال عند اسم مستخدم غير متاح
        document.getElementById('projectForm').addEventListener('submit', function (e) {
            const username = usernameInput.value.trim();
            if (username !== '' && !usernameValid) {
                e.preventDefault();
                alert('⚠️ اسم المستخدم غير متاح، يرجى اختيار اسم آخر');
                usernameInput.focus();
                return false;
            }
        });

        // دالة تعديل المستخدم — تملأ الفورم ببيانات المستخدم المحدد
        window.editUser = function (userId, name, username, phone, role, employeeId) {
            document.getElementById('user_id').value = userId;
            document.getElementById('name').value = name;
            document.getElementById('username').value = username;
            document.getElementById('phone').value = phone;
            document.getElementById('role').value = role;
            document.getElementById('password').value = '';

            // ربط الموظف: أتح خيار الموظف الحالي ثم اضبط القيمة
            if (employeeSelect) {
                refreshEmployeeOptions(userId);
                employeeSelect.value = (employeeId && parseInt(employeeId, 10) > 0) ? String(employeeId) : '';
            }

            // تغيير نص الفورم والزر ليدل على التعديل
            document.getElementById('formTitle').textContent = 'تعديل المستخدم';
            document.getElementById('submitBtnText').textContent = 'تحديث المستخدم';
            document.getElementById('action').value = 'edit';

            // إعادة تعيين حالة التحقق من اسم المستخدم
            setUsernameFeedback('ok', '<span><i class="fas fa-check-circle"></i> اسم المستخدم الحالي</span>');
            usernameInput.classList.remove('pu-input-warn', 'pu-input-error');
            usernameInput.classList.add('pu-input-success');
            usernameValid = true;

            // كلمة المرور اختيارية عند التعديل
            document.getElementById('passwordRequired').classList.add('pu-hidden');
            document.getElementById('passwordHint').classList.remove('pu-hidden');
            document.getElementById('password').removeAttribute('required');

            // عرض الفورم والتمرير إليه
            projectForm.classList.add('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
        };

        // دالة إعادة تعيين الفورم لحالة الإضافة
        window.resetForm = function () {
            document.getElementById('projectForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('action').value = 'add';
            document.getElementById('formTitle').textContent = 'إضافة مستخدم جديد';
            document.getElementById('submitBtnText').textContent = 'حفظ المستخدم';
            document.getElementById('passwordRequired').classList.remove('pu-hidden');
            document.getElementById('passwordHint').classList.add('pu-hidden');
            document.getElementById('password').setAttribute('required', 'required');

            usernameFeedback.innerHTML = '';
            usernameFeedback.className = 'pu-username-feedback';
            usernameInput.classList.remove('pu-input-warn', 'pu-input-success', 'pu-input-error');
            usernameValid = true;

            if (employeeSelect) { employeeSelect.value = ''; refreshEmployeeOptions(0); }
        };

    })();
</script>

</body>

</html>
