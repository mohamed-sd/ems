<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
// أعمدة users (company_id/is_deleted/deleted_at/deleted_by/employee_id) قائمة بالترحيلات —
// سقطت فحوص db_table_has_column والهجرة الذاتية (نمط ems_runtime_ddl الساقط).
$users_not_deleted_sql = "(COALESCE(u.is_deleted,0)=0)";
$users_has_employee_id = true; // ربط المعاون بموظف (قاعدة: لا حساب بلا موظف)

if ($current_company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة ❌', 'GOV-FAIL-409', '');
    exit();
}

// العزل عبر بوابة المستأجر (لا مسار سوبر هنا — الشاشة تشترط شركةً للجلسة أصلًا)
$pu_gate = ems_tenant_db();

// ══════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم على وحدة المشرفين
// ══════════════════════════════════════════════════════════════════════════════
$_currentUserRole = intval($_SESSION['user']['role']);

// البحث عن معرف الوحدة مع مراعاة دور المستخدم الحالي (modules مرجع عام — قراءة عبر البوابة)
$module_info = null;
try {
    $module_info = $pu_gate->selectOne('modules', array('columns' => array('id'),
        'whereRaw' => "(code = 'main/project_users.php' OR code = 'project_users' OR code LIKE '%project_users%') AND owner_role_id = " . intval($_currentUserRole)));
} catch (\Throwable $t) { error_log('project_users.php module: ' . $t->getMessage()); }
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
    ems_gov_flash_redirect('../login.php', 'لا توجد صلاحية عرض صفحة المشرفين ❌', 'GOV-PERM-403', '');
    exit();
}

$page_title = "إيكوبيشن | المشرفون";

// جلب اسم صلاحية المستخدم الحالي (roles مرجع عام — قراءة عبر البوابة)
$currentRole = $_SESSION['user']['role'];
$roleName = '';
try {
    $pu_role_row = $pu_gate->selectOne('roles', array('columns' => array('name'), 'where' => array('id' => intval($currentRole))));
    if ($pu_role_row) { $roleName = htmlspecialchars($pu_role_row['name'], ENT_QUOTES, 'UTF-8'); }
} catch (\Throwable $t) { error_log('project_users.php role name: ' . $t->getMessage()); }

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) {
        ems_gov_flash_redirect('project_users.php', 'لا توجد صلاحية حذف المستخدمين ❌', 'GOV-PERM-403', '');
        exit();
    }
    $deleteId = intval($_GET['delete']);
    $userid = $_SESSION['user']['id'];

    // التحقق من أن المستخدم المراد حذفه تابع للمستخدم الحالي أو من دور تابع (النطاق عبر البوابة)
    $verifyResult = array();
    try {
        $verifyResult = $pu_gate->scopedQuery(array('scope' => array('u' => 'users')),
            "SELECT u.id FROM users u
                    WHERE u.id = ? AND {TENANT_SCOPE}
                    AND $users_not_deleted_sql
                    AND (u.parent_id = ? OR u.role IN (
                        SELECT r.id FROM roles r
                        WHERE r.parent_role_id = " . intval($_SESSION['user']['role']) . "
                        AND (r.status = '1' OR r.status = 1)
                    ))", array($deleteId, strval($userid)));
    } catch (\Throwable $t) { error_log('project_users.php delete verify: ' . $t->getMessage()); }

    if (!empty($verifyResult)) {
        $deleteBy = intval($_SESSION['user']['id']);
        $pu_del_ok = false;
        try {
            $pu_gate->update('users', array(
                'is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $deleteBy, 'updated_at' => date('Y-m-d H:i:s'),
            ), array('id' => $deleteId), 'COALESCE(is_deleted,0)=0');
            $pu_del_ok = true;
        } catch (\Throwable $t) { error_log('project_users.php delete: ' . $t->getMessage()); }
        if ($pu_del_ok) {
            ems_gov_flash_redirect('project_users.php', 'تم حذف المستخدم بنجاح ✅', 'GOV-OK-200', '');
            exit;
        } else {
            ems_gov_flash_redirect('project_users.php', 'حدث خطأ أثناء الحذف ❌', 'GOV-FAIL-409', '');
            exit;
        }
    } else {
        ems_gov_flash_redirect('project_users.php', 'ليس لديك صلاحية لحذف هذا المستخدم ❌', 'GOV-PERM-403', '');
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة التعديل
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!$can_edit) {
        ems_gov_flash_redirect('project_users.php', 'لا توجد صلاحية تعديل المستخدمين ❌', 'GOV-PERM-403', '');
        exit();
    }
    $userId = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $passwordRaw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    // منع تصعيد الصلاحيات: الدور يجب أن يكون فعلاً من الأدوار الأبناء للمستخدم الحالي
    // (تقييد القائمة في الواجهة وحده لا يكفي — يمكن تزوير الطلب). roles مرجع عام عبر البوابة.
    $role_check_id = intval($_POST['role']);
    $pu_role_ok = null;
    try {
        $pu_role_ok = $pu_gate->selectOne('roles', array('columns' => array('id'),
            'where' => array('id' => $role_check_id, 'parent_role_id' => intval($_SESSION['user']['role'])),
            'whereRaw' => "(status = '1' OR status = 1)"));
    } catch (\Throwable $t) { error_log('project_users.php role check: ' . $t->getMessage()); }
    if ($pu_role_ok === null) {
        ems_gov_flash_redirect('project_users.php', 'صلاحية غير مسموحة ❌', 'GOV-PERM-403', '');
        exit;
    }
    $userid = $_SESSION['user']['id'];

    // التحقق من أن المستخدم المراد تعديله تابع للمستخدم الحالي (النطاق عبر البوابة)
    $verifyResult = array();
    try {
        $verifyResult = $pu_gate->scopedQuery(array('scope' => array('u' => 'users')),
            "SELECT u.id FROM users u
                    WHERE u.id = ? AND {TENANT_SCOPE}
                    AND $users_not_deleted_sql
                    AND (u.parent_id = ? OR u.role IN (
                        SELECT r.id FROM roles r
                        WHERE r.parent_role_id = " . intval($_SESSION['user']['role']) . "
                        AND (r.status = '1' OR r.status = 1)
                    ))", array($userId, strval($userid)));
    } catch (\Throwable $t) { error_log('project_users.php edit verify: ' . $t->getMessage()); }

    if (empty($verifyResult)) {
        ems_gov_flash_redirect('project_users.php', 'ليس لديك صلاحية لتعديل هذا المستخدم ❌', 'GOV-PERM-403', '');
        exit;
    }

    // 🔗 ربط الموظف (إلزامي) عند التعديل
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    if ($users_has_employee_id && $employee_link_id <= 0) {
        ems_gov_flash_redirect('project_users.php', 'يجب إسناد موظف لهذا الحساب ❌', 'GOV-FAIL-409', '');
        exit;
    }
    if ($employee_link_id > 0) {
        $pu_emp_ok = null; $pu_linked = null;
        try {
            $pu_emp_ok = $pu_gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employee_link_id)));
            $pu_linked = $pu_gate->selectOne('users', array('columns' => array('id'),
                'where' => array('employee_id' => $employee_link_id),
                'whereRaw' => 'id != ' . intval($userId) . ' AND COALESCE(is_deleted,0)=0'));
        } catch (\Throwable $t) { error_log('project_users.php employee check: ' . $t->getMessage()); }
        if ($pu_emp_ok === null || $pu_linked !== null) {
            ems_gov_flash_redirect('project_users.php', 'الموظف غير صالح أو مرتبط بحساب آخر ❌', 'GOV-FAIL-409', '');
            exit;
        }
    }

    // تحقق من تكرار اسم المستخدم (ما عدا المستخدم الحالي) — منطاقٌ بالشركة أصلًا (عبر البوابة)
    $pu_dup = null;
    try {
        $pu_dup = $pu_gate->selectOne('users', array('columns' => array('id'),
            'where' => array('username' => $username),
            'whereRaw' => 'id != ' . intval($userId) . ' AND COALESCE(is_deleted,0)=0'));
    } catch (\Throwable $t) { error_log('project_users.php dup check: ' . $t->getMessage()); }

    if ($pu_dup !== null) {
        ems_gov_flash_redirect('project_users.php', 'اسم المستخدم موجود مسبقاً ❌', 'GOV-FAIL-409', '');
        exit;
    }

    // تحديث المستخدم (كان الأصل يعيد كتابة company_id بقيمة الجلسة نفسها — الصف معزول
    // عبر البوابة أصلًا فالإعادة لغو، وتمريرها في بيانات التحديث محظور تعاقديًا)
    $pu_data = array(
        'name' => $name, 'username' => $username, 'phone' => $phone,
        'role' => $role, 'updated_at' => date('Y-m-d H:i:s'),
    );
    if ($passwordRaw !== '') { $pu_data['password'] = password_hash($passwordRaw, PASSWORD_DEFAULT); }
    if ($users_has_employee_id) { $pu_data['employee_id'] = $employee_link_id; }

    $pu_upd_ok = false;
    try { $pu_gate->update('users', $pu_data, array('id' => $userId)); $pu_upd_ok = true; }
    catch (\Throwable $t) { error_log('project_users.php update: ' . $t->getMessage()); }

    if ($pu_upd_ok) {
        ems_gov_flash_redirect('project_users.php', 'تم تعديل المستخدم بنجاح ✅', 'GOV-OK-200', '');
        exit;
    } else {
        ems_gov_flash_redirect('project_users.php', 'حدث خطأ أثناء التعديل ❌', 'GOV-FAIL-409', '');
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة مستخدم جديد
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && (!isset($_POST['action']) || $_POST['action'] === 'add')) {
    if (!$can_add) {
        ems_gov_flash_redirect('project_users.php', 'لا توجد صلاحية إضافة مستخدمين جدد ❌', 'GOV-PERM-403', '');
        exit();
    }
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    // تُجزَّأ كلمة المرور الخام (لا تُهرَّب قبل التجزئة — وإلا فشل الدخول مع الرموز الخاصة).
    $passwordRaw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $hashedPassword = password_hash($passwordRaw, PASSWORD_DEFAULT);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    // منع تصعيد الصلاحيات: الدور يجب أن يكون فعلاً من الأدوار الأبناء للمستخدم الحالي
    // (تقييد القائمة في الواجهة وحده لا يكفي — يمكن تزوير الطلب). roles مرجع عام عبر البوابة.
    $role_check_id = intval($_POST['role']);
    $pu_role_ok = null;
    try {
        $pu_role_ok = $pu_gate->selectOne('roles', array('columns' => array('id'),
            'where' => array('id' => $role_check_id, 'parent_role_id' => intval($_SESSION['user']['role'])),
            'whereRaw' => "(status = '1' OR status = 1)"));
    } catch (\Throwable $t) { error_log('project_users.php add role check: ' . $t->getMessage()); }
    if ($pu_role_ok === null) {
        ems_gov_flash_redirect('project_users.php', 'صلاحية غير مسموحة ❌', 'GOV-PERM-403', '');
        exit;
    }
    $project = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    $parent_id = intval($_SESSION['user']['id']);

    // 🔗 ربط الموظف (إلزامي): لا يوجد حساب معاون يعمل بلا موظف مُسنَد.
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    if ($users_has_employee_id && $employee_link_id <= 0) {
        ems_gov_flash_redirect('project_users.php', 'يجب إسناد موظف لهذا الحساب ❌', 'GOV-FAIL-409', '');
        exit;
    }
    if ($employee_link_id > 0) {
        $pu_emp_ok = null; $pu_linked = null;
        try {
            $pu_emp_ok = $pu_gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employee_link_id)));
            $pu_linked = $pu_gate->selectOne('users', array('columns' => array('id'),
                'where' => array('employee_id' => $employee_link_id),
                'whereRaw' => 'COALESCE(is_deleted,0)=0'));
        } catch (\Throwable $t) { error_log('project_users.php add employee check: ' . $t->getMessage()); }
        if ($pu_emp_ok === null || $pu_linked !== null) {
            ems_gov_flash_redirect('project_users.php', 'الموظف غير صالح أو مرتبط بحساب آخر ❌', 'GOV-FAIL-409', '');
            exit;
        }
    }

    // تحقق من تكرار اسم المستخدم — منطاقٌ بالشركة أصلًا (عبر البوابة)
    $pu_dup = null;
    try {
        $pu_dup = $pu_gate->selectOne('users', array('columns' => array('id'),
            'where' => array('username' => $username), 'whereRaw' => 'COALESCE(is_deleted,0)=0'));
    } catch (\Throwable $t) { error_log('project_users.php add dup check: ' . $t->getMessage()); }

    if ($pu_dup !== null) {
        ems_gov_flash_redirect('project_users.php', 'اسم المستخدم موجود مسبقاً ❌', 'GOV-FAIL-409', '');
        exit;
    }

    // إضافة مستخدم جديد (company_id تحقنه البوابة من سياق الجلسة)
    $pu_ins_ok = false;
    try {
        $pu_gate->insert('users', array(
            'name' => $name, 'username' => $username, 'password' => $hashedPassword, 'phone' => $phone,
            'role' => $role, 'role_id' => $role, 'project_id' => $project, 'parent_id' => $parent_id,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            'employee_id' => $employee_link_id,
        ));
        $pu_ins_ok = true;
    } catch (\Throwable $t) { error_log('project_users.php insert: ' . $t->getMessage()); }

    if ($pu_ins_ok) {
        ems_gov_flash_redirect('project_users.php', 'تم إضافة المستخدم بنجاح ✅', 'GOV-OK-200', '');
        exit;
    } else {
        ems_gov_flash_redirect('project_users.php', 'حدث خطأ أثناء الإضافة ❌', 'GOV-FAIL-409', '');
        exit;
    }
}

// قائمة الموظفين المتاحين للربط (موظفو الشركة + معرّف الحساب المرتبط إن وُجد).
$employees_for_link = array();
$emp_name_by_id = array();
if ($users_has_employee_id) {
    try {
        $pu_emps = $pu_gate->scopedQuery(array('scope' => array('e' => 'employees'), 'enrich' => array('u2' => 'users')),
            "SELECT e.id, e.name, e.phone,
                       (SELECT u2.id FROM users u2 WHERE u2.employee_id = e.id AND COALESCE(u2.is_deleted,0)=0 LIMIT 1) AS linked_uid
                FROM employees e WHERE 1=1 AND {TENANT_SCOPE} ORDER BY e.name ASC");
        foreach ($pu_emps as $er) {
            $employees_for_link[] = array(
                'id'         => intval($er['id']),
                'name'       => $er['name'],
                'phone'      => $er['phone'],
                'linked_uid' => ($er['linked_uid'] !== null) ? intval($er['linked_uid']) : 0,
            );
            $emp_name_by_id[intval($er['id'])] = $er['name'];
        }
    } catch (\Throwable $t) { error_log('project_users.php employees: ' . $t->getMessage()); }
}

$page_title = "إيكوبيشن | المشرفون";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
                            // جلب الأدوار التابعة للدور الحالي (roles مرجع عام — عبر البوابة)
                            $currentRole = $_SESSION['user']['role'];
                            $rolesResult = array();
                            try {
                                $rolesResult = $pu_gate->select('roles', array(
                                    'columns' => array('id', 'name'),
                                    'where' => array('parent_role_id' => intval($currentRole)),
                                    'whereRaw' => "(status = '1' OR status = 1)",
                                    'orderBy' => 'id ASC'));
                            } catch (\Throwable $t) { error_log('project_users.php roles options: ' . $t->getMessage()); }

                            if (!empty($rolesResult)) {
                                foreach ($rolesResult as $roleRow) {
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
                        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المستخدمين التابعين للمدير الحالي
                    // 1. المستخدمون الذين parent_id = المستخدم الحالي
                    // 2. المستخدمون من الأدوار التابعة للدور الحالي
                    $userid = $_SESSION['user']['id'];
                    $currentRole = $_SESSION['user']['role'];

                    $result = array();
                    try {
                        $result = $pu_gate->scopedQuery(array('scope' => array('u' => 'users')),
                            "SELECT DISTINCT u.id, u.name, u.username, u.phone, u.role, u.employee_id, u.created_at, ro.name AS role_name
                             FROM users u
                             LEFT JOIN roles ro ON ro.id = u.role
                                                  WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND (
                                          u.parent_id = ?
                                OR u.role IN (
                                   SELECT r.id FROM roles r
                                   WHERE r.parent_role_id = " . intval($currentRole) . "
                                   AND (r.status = '1' OR r.status = 1)
                                )
                                      )
                                      ORDER BY u.id DESC", array(strval($userid)));
                    } catch (\Throwable $t) { error_log('project_users.php list: ' . $t->getMessage()); }
                    $i = 1;

                    if ($result) {
                    foreach ($result as $row) {
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
