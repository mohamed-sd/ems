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

if ($module_id) {
    // للدور وحدتُه الخاصة — صلاحياتُه منها
    $perms = get_module_permissions($conn, $module_id);
} else {
    /* ◆ ثغرةٌ أُغلقت (2026-08-09): كان الغيابُ يعني «افترض جميع الصلاحيات»،
       فأيُّ دورٍ بلا صفِّ وحدةٍ خاصٍّ به يفتح الشاشةَ بصلاحياتٍ كاملة — وهو
       عينُ ما نقضه قرارُ المالك 2026-08-05 في `check_page_permissions`
       («الشاشةُ غيرُ المسجَّلةِ تُرفض»). الآن يُرتدّ إلى الوحدة الحاكمة
       للمسار فيُحسم الأمرُ بصفِّ صلاحيةٍ حقيقيٍّ أو يُغلق. وُسِّع الرابطُ إلى
       تسعةَ عشرَ دورًا رئيسيًّا — ولا يُوسَّع بابٌ وحارسُه يفترض الإذن. */
    $perms = check_page_permissions($conn, 'main/project_users.php');
}
$can_view   = !empty($perms['can_view']);
$can_add    = !empty($perms['can_add']);
$can_edit   = !empty($perms['can_edit']);
$can_delete = !empty($perms['can_delete']);

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض صفحة المعاونين ❌', 'GOV-PERM-403', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// 🔎 قناةُ «ما الذي يفتحه هذا الدور؟» — معلومةٌ قبلَ الإسناد لا بعده
// ──────────────────────────────────────────────────────────────────────────────
// تُخدَم من الشاشة نفسِها (نمطُ `Clients/clients.php?ajax=…`) لا من معالجٍ مستقل:
// فترثُ حارسَ الشاشةِ أعلاه حرفيًّا، ولا تفتح سطحًا جديدًا يحتاج تسجيلًا في
// `action_guard` (وهو fail-closed — والمعالجُ غيرُ المسجَّل يُحجب).
//
// ◆ الحدُّ الحاكم: لا يُكشف نطاقُ دورٍ إلا إن كان **ابنًا لدور الجلسة** — وهو
//   عينُ الشرط الذي يفرضه الحفظُ عند الإضافة والتعديل. ولولاه لصار مربّعُ
//   الاختيار بابًا لقراءة خريطةِ صلاحياتِ كلِّ أدوار المنصّة.
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['ajax']) && $_GET['ajax'] === 'role_scope') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');

    $scopeRoleId = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;
    $roleRow = null;
    if ($scopeRoleId > 0) {
        try {
            $roleRow = $pu_gate->selectOne('roles', array('columns' => array('id', 'name'),
                'where' => array('id' => $scopeRoleId, 'parent_role_id' => $_currentUserRole),
                'whereRaw' => "(status = '1' OR status = 1)"));
        } catch (\Throwable $t) { error_log('project_users.php role_scope: ' . $t->getMessage()); }
    }
    if ($roleRow === null) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'message' => 'هذا الدور ليس من الأدوار التابعة لإدارتك'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* الشاشاتُ كما يراها الدورُ فعلًا: صفُّ تبعيةٍ حيٌّ في `nav_items` × صلاحيةُ
       عرضٍ على وحدته — وهو الشرطُ نفسُه الذي يُصيِّر به `unified_nav` القائمة،
       فما يُعرض هنا هو ما سيراه المعاونُ حرفيًّا لا ما يُظنّ أنه سيراه.
       (`nav_items`/`link_groups`/`role_permissions` مراجعُ عامةٌ لا مستأجَرة —
       فتُقرأ إثراءً، ونطاقُ الشركةِ يُثبَّت على `users` صفِّ الجلسةِ نفسِه.) */
    $scopeScreens = array();
    $scopeWrite = 0;
    try {
        $scopeScreens = $pu_gate->scopedQuery(
            array('scope' => array('u' => 'users'),
                  'enrich' => array('ni' => 'nav_items', 'lg' => 'link_groups', 'rp' => 'role_permissions')),
            "SELECT ni.label_ar, ni.route, lg.name AS group_name, lg.stage_title,
                    lg.display_order AS gord, ni.sort_order AS sord,
                    COALESCE(rp.can_add, 0)    AS can_add,
                    COALESCE(rp.can_edit, 0)   AS can_edit,
                    COALESCE(rp.can_delete, 0) AS can_delete
               FROM users u
               LEFT JOIN nav_items ni ON ni.role_id = " . $scopeRoleId . " AND ni.active = 1
               LEFT JOIN link_groups lg ON lg.id = ni.group_id
               LEFT JOIN role_permissions rp ON rp.module_id = ni.module_id AND rp.role_id = ni.role_id
              WHERE u.id = " . intval($_SESSION['user']['id']) . " AND {TENANT_SCOPE}
                AND ni.id IS NOT NULL
                AND (ni.permission_code IS NULL OR COALESCE(rp.can_view, 0) = 1)
              ORDER BY gord, sord, ni.id");
    } catch (\Throwable $t) { error_log('project_users.php role_scope screens: ' . $t->getMessage()); }

    $out = array();
    foreach ($scopeScreens as $s) {
        if (intval($s['can_add']) || intval($s['can_edit']) || intval($s['can_delete'])) { $scopeWrite++; }
        $place = trim((string) $s['stage_title']);
        if ($place === '') { $place = trim((string) $s['group_name']); }
        $out[] = array(
            'label'  => (string) $s['label_ar'],
            'route'  => (string) $s['route'],
            'group'  => $place,
            'add'    => intval($s['can_add']),
            'edit'   => intval($s['can_edit']),
            'delete' => intval($s['can_delete']),
        );
    }

    // كم معاونًا يحمل هذا الدور اليوم داخل الشركة
    $scopeHolders = 0;
    try {
        $h = $pu_gate->scopedQuery(array('scope' => array('u' => 'users')),
            "SELECT COUNT(*) n FROM users u
              WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.role = ?",
            array(strval($scopeRoleId)));
        $scopeHolders = $h ? intval($h[0]['n']) : 0;
    } catch (\Throwable $t) { error_log('project_users.php role_scope holders: ' . $t->getMessage()); }

    echo json_encode(array(
        'ok'      => true,
        'role'    => array('id' => $scopeRoleId, 'name' => (string) $roleRow['name']),
        'holders' => $scopeHolders,
        'screens' => $out,
        'write'   => $scopeWrite,
    ), JSON_UNESCAPED_UNICODE);
    exit;
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
$emp_code_by_id = array();
if ($users_has_employee_id) {
    try {
        $pu_emps = $pu_gate->scopedQuery(array('scope' => array('e' => 'employees'), 'enrich' => array('u2' => 'users')),
            "SELECT e.id, e.name, e.phone, e.employee_code,
                       (SELECT u2.id FROM users u2 WHERE u2.employee_id = e.id AND COALESCE(u2.is_deleted,0)=0 LIMIT 1) AS linked_uid
                FROM employees e WHERE 1=1 AND {TENANT_SCOPE} ORDER BY e.name ASC");
        foreach ($pu_emps as $er) {
            $employees_for_link[] = array(
                'id'         => intval($er['id']),
                'name'       => $er['name'],
                'phone'      => $er['phone'],
                'code'       => (string) $er['employee_code'],
                'linked_uid' => ($er['linked_uid'] !== null) ? intval($er['linked_uid']) : 0,
            );
            $emp_name_by_id[intval($er['id'])] = $er['name'];
            $emp_code_by_id[intval($er['id'])] = (string) $er['employee_code'];
        }
    } catch (\Throwable $t) { error_log('project_users.php employees: ' . $t->getMessage()); }
}

/* الأدوارُ التابعةُ لدور الجلسة — مصدرٌ واحدٌ تقرأ منه القائمةُ المنسدلةُ
   ولافتةُ «لا أدوارَ تابعة» معًا. كانت تُجلب داخلَ القائمةِ المنسدلةِ فلا يعرف بقيةُ
   الصفحةِ أفارغةٌ هي أم لا، فتُعرض شاشةُ إضافةٍ لا تستطيع أن تُضيف شيئًا. */
$pu_child_roles = array();
try {
    $pu_child_roles = $pu_gate->select('roles', array(
        'columns'  => array('id', 'name'),
        'where'    => array('parent_role_id' => $_currentUserRole),
        'whereRaw' => "(status = '1' OR status = 1)",
        'orderBy'  => 'id ASC'));
} catch (\Throwable $t) { error_log('project_users.php child roles: ' . $t->getMessage()); }

$page_title = "إيكوبيشن | إدارة المعاونين";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
/* تعريفُ الشاشة بالمكوِّن الموحَّد نفسِه الذي تستعمله الشاشاتُ كلُّها: النصُّ
   هنا مصوغٌ باليد (أبلغُ من المشتقِّ آليًّا لشاشةٍ لها دورةُ عملٍ واضحة)،
   والموضعُ والسلوكُ يتولاهما `assets/js/ems-screen-about.js` — فلا نسخةَ
   ثانيةٌ من المكوِّن في هذا الملف. */
ems_screen_about(
    'من هنا تبني فريقَ إدارتك: حسابٌ لكلِّ معاونٍ أو مشرفٍ تابعٍ لك، بدورٍ محدَّدٍ '
  . 'يرسم ما يراه وما يستطيع فعلَه، ومربوطٌ بموظفٍ في سجلِّ الموارد البشرية.',
    array(
        'اختر الموظف — لا حسابَ يعمل بلا موظفٍ مُسنَد، فتُملأ بياناتُه تلقائيًّا.',
        'اختر الدور — واقرأ لوحةَ «ما يفتحه هذا الدور» التي تظهر تحته قبلَ الحفظ.',
        'سلّم بيانات الدخول — ثم تابع من الجدول: أيُّهم دخل، وأيُّهم لم يدخل بعد.',
    ),
    'لا تُسنَد إلا الأدوارُ التابعةُ لدورك — والمنعُ يقع عند الحفظ في الخادم، لا بإخفاء الخيار.'
);
?>

<div class="main project-users-main ems-unified-page-shell">

    <?php
    // ── جلبُ الفريق مرةً واحدةً قبلَ التصيير: المؤشراتُ والجدولُ من مصدرٍ واحد ──
    // (كان الاستعلامُ داخلَ <tbody> فلا سبيلَ لعدِّ شيءٍ قبله — ومؤشرٌ يُحسب من
    //  استعلامٍ ثانٍ يفترق عن جدوله عند أول تغيّرِ شرط.)
    $userid      = $_SESSION['user']['id'];
    $currentRole = $_SESSION['user']['role'];
    $result = array();
    try {
        $result = $pu_gate->scopedQuery(array('scope' => array('u' => 'users')),
            "SELECT DISTINCT u.id, u.name, u.username, u.phone, u.role, u.employee_id,
                    u.created_at, u.last_login_at, u.status, ro.name AS role_name
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

    $kpi_total = count($result);
    $kpi_active = 0; $kpi_linked = 0; $kpi_never = 0;
    $kpi_roles = array();
    foreach ($result as $r0) {
        if ((string) $r0['status'] === 'active') { $kpi_active++; }
        if (intval($r0['employee_id']) > 0) { $kpi_linked++; }
        if (empty($r0['last_login_at'])) { $kpi_never++; }
        if (!empty($r0['role'])) { $kpi_roles[(string) $r0['role']] = 1; }
    }

    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon       = 'fas fa-users-gear';
    $header_title_html = 'إدارة المعاونين' . (!empty($roleName) ? ' — ' . $roleName : '');
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-user-plus', 'label' => 'إضافة معاون جديد');
    }
    // زرُّ «عن الشاشة» يزرعه المكوِّنُ الموحَّد في `.head_actions` — لا يُكتب هنا
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا معاونين مسجَّلين تحت إدارتك بعدُ', 'أضف أولَ معاونٍ بزرِّ الإضافةِ في رأسِ الشاشة');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars((string) $_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($pu_child_roles)): ?>
        <!-- لافتةُ الحدِّ الحقيقي: شاشةُ إضافةٍ بلا أدوارٍ تابعةٍ لا تستطيع أن تضيف
             شيئًا — تُقال صراحةً بدل أن يكتشفها المستخدمُ من قائمةٍ فارغة. -->
        <div class="pu-notice pu-notice--warn">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <b>لا توجد أدوارٌ تابعةٌ لإدارتك بعد.</b>
                يمكنك عرضُ من يتبعك ومتابعتُهم، ولا يمكن إضافةُ معاونٍ جديد حتى يُنشأ دورٌ تابعٌ لدورك
                <?= (!empty($roleName) ? '«' . htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') . '»' : '') ?>
                من <b>إدارة الصلاحيات</b> — فالخادمُ يرفض إسنادَ أيِّ دورٍ ليس ابنًا لدورك.
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══ مؤشراتُ الفريق ═══ -->
    <div class="pu-kpis" data-cols="5">
        <div class="pu-kpi" data-tone="main">
            <span class="pu-kpi__ico"><i class="fas fa-users"></i></span>
            <span class="pu-kpi__val"><?= $kpi_total ?></span>
            <span class="pu-kpi__lbl">إجمالي الفريق</span>
        </div>
        <div class="pu-kpi" data-tone="ok">
            <span class="pu-kpi__ico"><i class="fas fa-circle-check"></i></span>
            <span class="pu-kpi__val"><?= $kpi_active ?></span>
            <span class="pu-kpi__lbl">حساباتٌ نشطة</span>
        </div>
        <div class="pu-kpi" data-tone="info">
            <span class="pu-kpi__ico"><i class="fas fa-shield-halved"></i></span>
            <span class="pu-kpi__val"><?= count($kpi_roles) ?></span>
            <span class="pu-kpi__lbl">أدوارٌ مستعملة</span>
        </div>
        <div class="pu-kpi" data-tone="<?= ($kpi_total > 0 && $kpi_linked < $kpi_total) ? 'warn' : 'ok' ?>">
            <span class="pu-kpi__ico"><i class="fas fa-id-card-alt"></i></span>
            <span class="pu-kpi__val"><?= $kpi_linked ?>/<?= $kpi_total ?></span>
            <span class="pu-kpi__lbl">مربوطٌ بموظف</span>
        </div>
        <div class="pu-kpi" data-tone="<?= $kpi_never > 0 ? 'warn' : 'ok' ?>">
            <span class="pu-kpi__ico"><i class="fas fa-hourglass-half"></i></span>
            <span class="pu-kpi__val"><?= $kpi_never ?></span>
            <span class="pu-kpi__lbl">لم يسجّل دخولًا بعد</span>
        </div>
    </div>

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
                        <label for="name"><i class="fas fa-user"></i> الاسم ثلاثي *</label>
                        <input type="text" name="name" id="name" placeholder="أدخل الاسم ثلاثي" value="" required />
                    </div>
                    <div>
                        <label for="username"><i class="fas fa-at"></i> اسم المستخدم *</label>
                        <input type="text" name="username" id="username" placeholder="أدخل اسم المستخدم" value=""
                            required autocomplete="off" />
                        <small id="usernameFeedback" class="pu-username-feedback"></small>
                    </div>
                    <div>
                        <label for="password"><i class="fas fa-lock"></i> كلمة المرور <span id="passwordRequired">*</span></label>
                        <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" value="" />
                        <small id="passwordHint" class="pu-password-hint pu-hidden">اتركه فارغاً للاحتفاظ بكلمة المرور
                            الحالية</small>
                    </div>
                    <div>
                        <label for="phone"><i class="fas fa-phone"></i> رقم الهاتف *</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" required value="" />
                    </div>
                    <div>
                        <label for="role"><i class="fas fa-shield-alt"></i> الصلاحية / الدور *</label>
                        <select name="role" id="role" required>
                            <option value="">-- اختر الصلاحية --</option>
                            <?php
                            if (!empty($pu_child_roles)) {
                                foreach ($pu_child_roles as $roleRow) {
                                    echo '<option value="' . intval($roleRow['id']) . '">' .
                                        htmlspecialchars((string) $roleRow['name'], ENT_QUOTES, 'UTF-8') .
                                        '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>لا توجد أدوار تابعة لإدارتك</option>';
                            }
                            ?>
                        </select>
                        <small class="pu-password-hint">اختر الدور لتظهر لك الشاشاتُ التي سيفتحها هذا المعاون.</small>
                    </div>
                    <?php if ($users_has_employee_id): ?>
                    <div>
                        <label for="employee_id_link"><i class="fas fa-id-card-alt"></i> الموظف المُسنَد *</label>
                        <select name="employee_id" id="employee_id_link" required>
                            <option value="">— اختر الموظف —</option>
                            <?php foreach ($employees_for_link as $emp): ?>
                                <option value="<?= intval($emp['id']) ?>"
                                    data-linked-uid="<?= intval($emp['linked_uid']) ?>"
                                    data-name="<?= htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-phone="<?= htmlspecialchars((string) $emp['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8') ?><?= $emp['code'] !== '' ? ' — ' . htmlspecialchars($emp['code'], ENT_QUOTES, 'UTF-8') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="pu-password-hint">إلزامي — لا حساب يعمل بلا موظف مُسنَد. تُعبّأ بيانات الموظف تلقائياً عند الاختيار.</small>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ لوحةُ «ما يفتحه هذا الدور» ═══
                     معلومةٌ قبلَ القرار لا تقريرٌ بعده: تظهر تحت الحقلِ عند اختيار
                     الدور، ولا تحجب النموذجَ ولا تنتزع التركيز (لا نافذةَ حاجزة). -->
                <div class="pu-rolescope" id="puRoleScope" hidden>
                    <div class="pu-rolescope__head">
                        <span><i class="fas fa-shield-halved"></i> ما الذي يفتحه دور <b id="puRoleScopeName">—</b>؟</span>
                        <span class="pu-rolescope__pills" id="puRoleScopePills"></span>
                        <button type="button" class="pu-rolescope__close" id="puRoleScopeClose" title="إخفاء"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="pu-rolescope__body" id="puRoleScopeBody"></div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ المستخدم</span>
                    </button>
                    <button type="button" class="btn-secondary"
                        onclick="document.getElementById('projectForm').classList.remove('allforms-visible');">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <table id="usersTable" class="display nowrap" data-order='[[1,"asc"]]' data-column-defs='[{"targets":0,"orderable":false,"searchable":false,"className":"pu-col-actions"}]'>
                <thead>
                    <tr>
                        <!-- الإجراءاتُ أولًا (قرارُ المالك 2026-08-09): الفعلُ يُطلب قبل
                             القراءةِ في شاشةِ إدارةٍ — فلا يُقطع الصفُّ بحثًا عن زرِّه. -->
                        <th class="pu-col-actions">الإجراءات</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>رقم الهاتف</th>
                        <th>الصلاحية</th>
                        <th>الموظف المرتبط</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>تاريخ الإنشاء</th>
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
                    /* الصفوفُ من `$result` المجلوبِ أعلاه (مصدرٌ واحدٌ مع المؤشرات).
                       ◆ كلُّ قيمةٍ تُغلَّف بـ(string) قبل htmlspecialchars: عمودُ
                         الهاتف يقبل NULL، و PHP 8.1+ يرمي Deprecated على كل صفٍّ
                         (كان يملأ php_errors.log فعلًا من هذا الملف بالذات). */
                    $pu_e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
                    foreach ($result as $row) {
                        $roleText = !empty($row['role_name'])
                            ? $pu_e($row['role_name'])
                            : '<span class="pu-text-muted">غير معروف</span>';
                        $createdDate = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '—';
                        $lastLogin   = !empty($row['last_login_at']) ? date('Y-m-d H:i', strtotime($row['last_login_at'])) : '';
                        $status      = (string) $row['status'];
                        $linked_emp_id = isset($row['employee_id']) ? intval($row['employee_id']) : 0;
                        $empName = ($linked_emp_id > 0 && isset($emp_name_by_id[$linked_emp_id])) ? (string) $emp_name_by_id[$linked_emp_id] : '';
                        $empCode = ($linked_emp_id > 0 && isset($emp_code_by_id[$linked_emp_id])) ? (string) $emp_code_by_id[$linked_emp_id] : '';

                        $statusMap = array(
                            'active'    => array('نشط',   'pu-pill pu-pill--ok',   'fa-circle-check'),
                            'inactive'  => array('موقوف', 'pu-pill pu-pill--mute', 'fa-circle-pause'),
                            'suspended' => array('معلَّق', 'pu-pill pu-pill--err',  'fa-circle-xmark'),
                        );
                        $sm = isset($statusMap[$status]) ? $statusMap[$status] : array($status !== '' ? $status : '—', 'pu-pill pu-pill--mute', 'fa-circle');

                        echo '<tr>';

                        // ① الإجراءات — أولَ عمود
                        echo "<td class='pu-col-actions'><div class='action-btns'>";
                        echo "<a href='javascript:void(0)' class='action-btn view puViewBtn'"
                            . " data-id='" . intval($row['id']) . "'"
                            . " data-name='" . $pu_e($row['name']) . "'"
                            . " data-username='" . $pu_e($row['username']) . "'"
                            . " data-phone='" . $pu_e($row['phone']) . "'"
                            . " data-role='" . intval($row['role']) . "'"
                            . " data-rolename='" . $pu_e($row['role_name']) . "'"
                            . " data-empid='" . $linked_emp_id . "'"
                            . " data-empname='" . $pu_e($empName) . "'"
                            . " data-empcode='" . $pu_e($empCode) . "'"
                            . " data-status='" . $pu_e($sm[0]) . "'"
                            . " data-statuskey='" . $pu_e($status) . "'"
                            . " data-lastlogin='" . $pu_e($lastLogin) . "'"
                            . " data-created='" . $pu_e($createdDate) . "'"
                            . " title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='action-btn edit puEditBtn'"
                                . " data-id='" . intval($row['id']) . "'"
                                . " data-name='" . $pu_e($row['name']) . "'"
                                . " data-username='" . $pu_e($row['username']) . "'"
                                . " data-phone='" . $pu_e($row['phone']) . "'"
                                . " data-role='" . intval($row['role']) . "'"
                                . " data-empid='" . $linked_emp_id . "'"
                                . " title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='project_users.php?delete=" . intval($row['id']) . "'"
                                . " class='action-btn delete'"
                                . " onclick=\"return confirm('هل أنت متأكد من حذف حساب «" . $pu_e($row['name']) . "»؟')\""
                                . " title='حذف'><i class='fas fa-trash'></i></a>";
                        }
                        echo '</div></td>';

                        echo '<td><strong>' . $pu_e($row['name']) . '</strong></td>';
                        echo "<td><code class='pu-code'>" . $pu_e($row['username']) . '</code></td>';
                        echo '<td>' . ($row['phone'] !== null && $row['phone'] !== '' ? $pu_e($row['phone']) : '<span class="pu-text-muted">—</span>') . '</td>';
                        echo '<td>' . $roleText . '</td>';
                        if ($empName !== '') {
                            echo "<td><a class='client-name-link' href='../Employees/employee_profile.php?id=" . $linked_emp_id . "'>"
                                . "<i class='fas fa-id-card-alt'></i> " . $pu_e($empName) . '</a></td>';
                        } else {
                            echo "<td><span class='pu-pill pu-pill--warn'><i class='fas fa-link-slash'></i> غير مرتبط</span></td>";
                        }
                        echo "<td><span class='" . $sm[1] . "'><i class='fas " . $sm[2] . "'></i> " . $pu_e($sm[0]) . '</span></td>';
                        echo '<td>' . ($lastLogin !== '' ? $pu_e($lastLogin)
                                     : "<span class='pu-pill pu-pill--mute'>لم يدخل بعد</span>") . '</td>';
                        echo '<td>' . $pu_e($createdDate) . '</td>';
                        echo '</tr>';
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
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    (function () {



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

        // ═══════════════════════════════════════════════════════════════════
        // «ما الذي يفتحه هذا الدور؟» — لوحةُ معلومةٍ عند اختيار الدور
        // ───────────────────────────────────────────────────────────────────
        // ليست نافذةً حاجزة: لا تسرق التركيزَ ولا تُغلق بـEscape ولا تُوقف
        // الكتابة — لأن المستخدم في منتصف نموذج، ونافذةٌ modal هنا تقطع عملَه
        // لتخبره بمعلومة. تظهر تحت الحقلِ نفسِه فتُقرأ في مكان القرار.
        // ═══════════════════════════════════════════════════════════════════
        var roleSelect = document.getElementById('role');
        var rsBox   = document.getElementById('puRoleScope');
        var rsName  = document.getElementById('puRoleScopeName');
        var rsPills = document.getElementById('puRoleScopePills');
        var rsBody  = document.getElementById('puRoleScopeBody');
        var rsClose = document.getElementById('puRoleScopeClose');
        var rsCache = {};
        var rsSeq   = 0;

        function rsEsc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function rsRender(data) {
            rsName.textContent = data.role.name;
            var writeTxt = data.write > 0
                ? '<span class="pu-chip pu-chip--write"><i class="fas fa-pen"></i> ' + data.write + ' شاشةَ تعديل</span>'
                : '<span class="pu-chip pu-chip--read"><i class="fas fa-eye"></i> قراءةٌ فقط</span>';
            rsPills.innerHTML =
                '<span class="pu-chip"><i class="fas fa-window-restore"></i> ' + data.screens.length + ' شاشة</span>' +
                writeTxt +
                '<span class="pu-chip"><i class="fas fa-user-group"></i> ' + data.holders + ' يحملونه الآن</span>';

            if (!data.screens.length) {
                rsBody.innerHTML = '<div class="pu-rolescope__empty">'
                    + '<i class="fas fa-inbox"></i> لا شاشاتٍ مسنَدةً لهذا الدور بعد — المعاونُ عليه لن يرى قائمةً.'
                    + ' تُسنَد الشاشاتُ من <b>إدارة الصلاحيات</b>.</div>';
                return;
            }
            // تجميعٌ بالمرحلة/المجموعة كما تظهر في القائمة — لا قائمةً مسطَّحة
            var groups = {}, order = [];
            data.screens.forEach(function (s) {
                var g = s.group || 'غير مُصنَّف';
                if (!groups[g]) { groups[g] = []; order.push(g); }
                groups[g].push(s);
            });
            var html = '';
            order.forEach(function (g) {
                html += '<div class="pu-rolescope__grp"><span class="pu-rolescope__gname">' + rsEsc(g) + '</span><ul>';
                groups[g].forEach(function (s) {
                    var marks = '';
                    if (s.add)    { marks += '<i class="fas fa-plus"   title="إضافة"></i>'; }
                    if (s.edit)   { marks += '<i class="fas fa-pen"    title="تعديل"></i>'; }
                    if (s.delete) { marks += '<i class="fas fa-trash"  title="حذف"></i>'; }
                    if (marks === '') { marks = '<i class="fas fa-eye" title="عرض فقط"></i>'; }
                    html += '<li><span>' + rsEsc(s.label) + '</span>'
                          + '<span class="pu-rolescope__marks">' + marks + '</span></li>';
                });
                html += '</ul></div>';
            });
            rsBody.innerHTML = html;
        }

        function rsLoad(roleId) {
            if (!rsBox) return;
            if (!roleId) { rsBox.hidden = true; return; }
            rsBox.hidden = false;
            if (rsCache[roleId]) { rsRender(rsCache[roleId]); return; }

            rsName.textContent = '…';
            rsPills.innerHTML = '';
            rsBody.innerHTML = '<div class="pu-rolescope__empty"><i class="fas fa-spinner fa-spin"></i> جارٍ قراءة نطاق الدور…</div>';

            var mySeq = ++rsSeq;   // تجاهلُ ردٍّ متأخِّرٍ لاختيارٍ سابق
            fetch('project_users.php?ajax=role_scope&role_id=' + encodeURIComponent(roleId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
            })
                .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
                .then(function (d) {
                    if (mySeq !== rsSeq) { return; }
                    if (!d || !d.ok) {
                        rsBody.innerHTML = '<div class="pu-rolescope__empty pu-rolescope__empty--err">'
                            + '<i class="fas fa-triangle-exclamation"></i> '
                            + rsEsc((d && d.message) || 'تعذّرت قراءة نطاق الدور') + '</div>';
                        return;
                    }
                    rsCache[roleId] = d;
                    rsRender(d);
                })
                .catch(function () {
                    if (mySeq !== rsSeq) { return; }
                    rsBody.innerHTML = '<div class="pu-rolescope__empty pu-rolescope__empty--err">'
                        + '<i class="fas fa-triangle-exclamation"></i> تعذّر الاتصال — أعد المحاولة</div>';
                });
        }

        if (roleSelect) { roleSelect.addEventListener('change', function () { rsLoad(this.value); }); }
        if (rsClose)    { rsClose.addEventListener('click', function () { rsBox.hidden = true; }); }

        // ═══ نافذةُ تفاصيل المعاون — النظامُ الموحَّد نفسُه (EmsDetailsModal) ═══
        $(document).on('click', '.puViewBtn', function () {
            var d = this.dataset;
            var tone = (d.statuskey === 'active') ? 'active' : 'inactive';
            var empVal = d.empid && parseInt(d.empid, 10) > 0
                ? '<a class="client-name-link" href="../Employees/employee_profile.php?id=' + encodeURIComponent(d.empid) + '">'
                  + '<i class="fas fa-id-card-alt"></i> ' + rsEsc(d.empname || '—')
                  + (d.empcode ? ' <small>(' + rsEsc(d.empcode) + ')</small>' : '') + '</a>'
                : '<span class="pu-pill pu-pill--warn"><i class="fas fa-link-slash"></i> غير مرتبط بموظف</span>';

            var actions = [];
            <?php if ($can_edit): ?>
            actions.push({
                label: 'تعديل البيانات', icon: 'fas fa-edit', variant: 'primary',
                onClick: function () {
                    EmsDetailsModal.close();
                    window.editUser(d.id, d.name, d.username, d.phone, d.role, d.empid);
                }
            });
            <?php endif; ?>
            actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

            EmsDetailsModal.open({
                title: 'تفاصيل المعاون',
                icon: 'fas fa-user-shield',
                fields: [
                    { label: 'الاسم', value: d.name, icon: 'fas fa-user', size: 'lg' },
                    { label: 'اسم المستخدم', value: d.username, icon: 'fas fa-at' },
                    { label: 'رقم الهاتف', value: d.phone, icon: 'fas fa-phone' },
                    { label: 'الدور المسنَد', value: d.rolename, icon: 'fas fa-shield-halved', size: 'lg' },
                    { label: 'الموظف المرتبط', value: empVal, icon: 'fas fa-id-card-alt', type: 'html', size: 'lg' },
                    { label: 'حالة الحساب', value: d.status, icon: 'fas fa-toggle-on', type: 'status', tone: tone },
                    { label: 'آخر دخول', value: d.lastlogin || 'لم يسجّل دخولًا بعد', icon: 'fas fa-right-to-bracket' },
                    { label: 'تاريخ الإنشاء', value: d.created, icon: 'fas fa-calendar-plus' }
                ],
                actions: actions
            });
        });

        // زرُّ التعديل صار بالبيانات لا بنصٍّ مُقحَمٍ في onclick
        // (اسمٌ فيه علامةُ اقتباسٍ كان يكسر السطرَ المولَّد ويقتل الزر).
        $(document).on('click', '.puEditBtn', function () {
            var d = this.dataset;
            window.editUser(d.id, d.name, d.username, d.phone, d.role, d.empid);
        });

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

            // لوحةُ نطاق الدور تتبع القيمةَ المحمَّلة (لا تبقى على دورٍ سابق)
            rsLoad(document.getElementById('role').value);

            // تغيير نص الفورم والزر ليدل على التعديل
            document.getElementById('formTitle').textContent = 'تعديل بيانات المعاون';
            document.getElementById('submitBtnText').textContent = 'تحديث المعاون';
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
            document.getElementById('formTitle').textContent = 'إضافة معاون جديد';
            document.getElementById('submitBtnText').textContent = 'حفظ المعاون';
            document.getElementById('passwordRequired').classList.remove('pu-hidden');
            document.getElementById('passwordHint').classList.add('pu-hidden');
            document.getElementById('password').setAttribute('required', 'required');

            usernameFeedback.innerHTML = '';
            usernameFeedback.className = 'pu-username-feedback';
            usernameInput.classList.remove('pu-input-warn', 'pu-input-success', 'pu-input-error');
            usernameValid = true;

            if (rsBox) { rsBox.hidden = true; }
            if (employeeSelect) { employeeSelect.value = ''; refreshEmployeeOptions(0); }
        };

    })();
</script>

</body>

</html>
