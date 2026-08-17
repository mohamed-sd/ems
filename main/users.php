<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-674 · SCN-675 · SCN-676 · SCN-678
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
// تضمين ملف الجلسات
include '../includes/sessions.php';
// تضمين قاعدة البيانات أولاً (قبل sidebar لأننا نحتاجها)
include '../config.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
// أعمدة users (company_id/is_deleted/deleted_at/deleted_by/status/employee_id) قائمة
// بالترحيلات — سقطت فحوص db_table_has_column والهجرات الذاتية (نمط ems_runtime_ddl الساقط).
$users_has_status = true;
$users_has_employee_id = true; // رابط الحساب↔الموظف (الخيار ب)
$users_not_deleted_sql = "(COALESCE(is_deleted,0)=0)";

if ($current_company_id <= 0) {
    ems_gov_flash_redirect('../login.php', '❌ الحساب غير مرتبط بشركة', 'GOV-SCOPE-403', '');
    exit;
}

// العزل عبر بوابة المستأجر (الشاشة تشترط شركةً للجلسة أصلًا — لا مسار سوبر)
$us_gate = ems_tenant_db();

// ════════════════════════════════════════════════════════════════════════════
// [ح-2] صلاحيات هذه الشاشة (module main/users.php). فحصٌ صريح هنا لأن:
//   • مسار الحذف (?delete=) أدناه يسبق insidebar (الحارس المركزي) ⇒ غير محروس.
//   • الإنشاء/التعديل يفحصان can_view مركزيًا فقط، لا can_add/can_edit.
// مؤكد بتنفيذ حقيقي: مستخدمٌ بلا can_view وصل مسار الحذف (200 بلا redirect).
// ════════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/permissions_helper.php';
$us_perms = get_current_page_permissions($conn);

// ════════════════════════════════════════════════════════════════════════════
// [ح-02] منعُ تصعيد الامتياز — الطبقةُ الكوديّة.
// كانت هذه الشاشة تعرض كلَّ الأدوار الجذرية لكل من يصلها، وتقبل أيَّ role في
// POST بلا فحصٍ للنسب. ومع صفوفِ صلاحيةٍ مفتوحةٍ لعشرة أدوار (صُحّحت بيانيًّا
// في هجرة 2026_08_06) كان حسابُ مبيعاتٍ يُنشئ حسابًا بدور «إدارة الصلاحيات».
// القاعدة: «لا تمنح ما لا تملك» — دورُك وذريّتُك فقط، وأدوارُ إدارة الحسابات
// (1 · 15) والسوبر بلا قيد. وهي القاعدةُ نفسُها المطبَّقة في project_users.php،
// نُقلت إلى governance_guard ليشترك فيها كلُّ مسارٍ يُسنِد دورًا.
// التقييدُ هنا مزدوج: القائمةُ المعروضة **و** الفحصُ الخادميُّ عند الحفظ —
// فتقييدُ الواجهة وحدَه لا يكفي (يمكن تزوير الطلب).
// ════════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/governance_guard.php';
$us_assignable = ems_assignable_role_ids($conn, isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '');

// Endpoint محلي لجلب عقود المشروع (يُستخدم عبر Ajax من نفس الصفحة)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'contracts') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }

    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if ($project_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف مشروع غير صالح']);
        exit;
    }

    $us_prj_ok = array();
    try {
        $us_prj_ok = $us_gate->scopedQuery(array('scope' => array('p' => 'project')),
            "SELECT p.id FROM project p WHERE p.id = ? AND p.status = '1' AND COALESCE(p.is_deleted,0)=0 AND {TENANT_SCOPE} LIMIT 1",
            array($project_id));
    } catch (\Throwable $t) { error_log('users.php ajax project: ' . $t->getMessage()); }
    if (empty($us_prj_ok)) {
        echo json_encode(['success' => true, 'contracts' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contract_active_sql = "(
        c.status = 1
        OR c.status = '1'
        OR TRIM(c.status) = 'نشط'
        OR TRIM(LOWER(c.status)) = 'active'
        OR TRIM(LOWER(c.status)) = 'true'
    )";

    $contracts_result = false;
    try {
        $contracts_result = $us_gate->scopedQuery(array('scope' => array('c' => 'contracts')),
            "SELECT
            c.id,
            c.contract_signing_date,
            c.actual_start,
            c.forecasted_contracted_hours
        FROM contracts c
        WHERE c.project_id = ? AND {TENANT_SCOPE}
          AND $contract_active_sql
        ORDER BY c.actual_start DESC, c.id DESC", array($project_id));
    } catch (\Throwable $t) { error_log('users.php ajax contracts: ' . $t->getMessage()); }
    if ($contracts_result === false) {
        echo json_encode(['success' => false, 'message' => 'خطأ في جلب العقود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contracts = [];
    foreach ($contracts_result as $row) {
        $contracts[] = [
            'id' => intval($row['id']),
            'display_name' => 'عقد رقم ' . intval($row['id']) . ' - ' . (isset($row['actual_start']) ? $row['actual_start'] : '-') . ' - ' . floatval(isset($row['forecasted_contracted_hours']) ? $row['forecasted_contracted_hours'] : 0) . ' ساعة',
            'contract_signing_date' => isset($row['contract_signing_date']) ? $row['contract_signing_date'] : null,
            'actual_start' => isset($row['actual_start']) ? $row['actual_start'] : null,
            'hours' => floatval(isset($row['forecasted_contracted_hours']) ? $row['forecasted_contracted_hours'] : 0)
        ];
    }

    echo json_encode([
        'success' => true,
        'contracts' => $contracts
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$roles = array();
$roles_scope = array();
$roles_result = array();
try {
    $roles_result = $us_gate->select('roles', array(
        'columns' => array('id', 'name', 'role_scope'),
        'whereRaw' => '(parent_role_id IS NULL OR parent_role_id = 0) AND status = 1',
        'orderBy' => 'level ASC, id ASC'));
} catch (\Throwable $t) { error_log('users.php roles: ' . $t->getMessage()); }
if ($roles_result) {
    foreach ($roles_result as $role_row) {
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

// [ح-02] ترشيحُ القائمة المعروضة بسياسة الإسناد (null = بلا قيد للسوبر وإدارة الحسابات).
if ($us_assignable !== null) {
    foreach (array_keys($roles) as $us_rid) {
        if (!in_array((string) $us_rid, $us_assignable, true)) {
            unset($roles[$us_rid], $roles_scope[$us_rid]);
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 🔗 الموظفون المتاحون للربط (الخيار ب: users.employee_id → employees.id)
// نحمّل موظفي الشركة مع معرّف الحساب المرتبط (إن وُجد) للتعبئة والتصفية في الواجهة.
// ════════════════════════════════════════════════════════════════════════════
$employees_for_link = array();
if ($users_has_employee_id) {
    try {
        $us_emps = $us_gate->scopedQuery(array('scope' => array('e' => 'employees'), 'enrich' => array('u' => 'users')),
            "SELECT e.id, e.name, e.phone, e.email,
                       (SELECT u.id FROM users u WHERE u.employee_id = e.id AND $users_not_deleted_sql LIMIT 1) AS linked_uid
                FROM employees e WHERE 1=1 AND {TENANT_SCOPE} ORDER BY e.name ASC");
        foreach ($us_emps as $er) {
            $employees_for_link[] = array(
                'id'         => intval($er['id']),
                'name'       => $er['name'],
                'phone'      => $er['phone'],
                'email'      => $er['email'],
                'linked_uid' => ($er['linked_uid'] !== null) ? intval($er['linked_uid']) : 0,
            );
        }
    } catch (\Throwable $t) { error_log('users.php employees: ' . $t->getMessage()); }
}

// خريطة (معرّف الموظف ⇐ الاسم) لعرض «الموظف المرتبط» في قائمة المستخدمين.
$emp_name_by_id = array();
foreach ($employees_for_link as $e) {
    $emp_name_by_id[$e['id']] = $e['name'];
}

// ════════════════════════════════════════════════════════════════════════════
// 🔗 H-20: الموردون المتاحون لربط حساب «مشرف موردين» (users.supplier_entity_id)
// «إنشاءُ مشرفٍ يرث صلاحياتِ موردِه آليًّا» (UX-05) — الربطُ هنا هو النطاقُ
// البنيويُّ الذي يقرؤه SupplierPortalGuard حصرًا، وحسابُ الدور 8 بلا مورد
// لا يرى شيئًا (fail-closed).
// ════════════════════════════════════════════════════════════════════════════
$suppliers_for_link = array();
try {
    $us_sups = $us_gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s
         WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name ASC");
    foreach ($us_sups as $sr) {
        $suppliers_for_link[] = array('id' => intval($sr['id']), 'name' => $sr['name']);
    }
} catch (\Throwable $t) { error_log('users.php suppliers: ' . $t->getMessage()); }
$sup_name_by_id = array();
foreach ($suppliers_for_link as $s) { $sup_name_by_id[$s['id']] = $s['name']; }

// تهيئة مسبقة عند القدوم من بطاقة الموظف (users.php?employee_id=N):
//   إن كان للموظف حساب مرتبط ⇒ نفتح الحساب في وضع التعديل، وإلا نفتح نموذج إضافة مهيّأً.
$prefill_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$prefill_edit_uid = 0;
if ($prefill_employee_id > 0 && $users_has_employee_id) {
    try {
        $pf_row = $us_gate->selectOne('users', array('columns' => array('id'),
            'where' => array('employee_id' => $prefill_employee_id), 'whereRaw' => $users_not_deleted_sql));
        if ($pf_row) { $prefill_edit_uid = intval($pf_row['id']); }
    } catch (\Throwable $t) { error_log('users.php prefill: ' . $t->getMessage()); }
}

// حذف ناعم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // [ح-2] هذا المسار يسبق insidebar ⇒ يجب فحص can_delete صراحةً هنا.
    if ($us_perms['id'] !== null && !$us_perms['can_delete']) {
        ems_gov_flash_redirect('users.php', '❌ لا توجد صلاحية للحذف', 'GOV-PERM-403', '');
        exit;
    }
    $delete_id = intval($_GET['delete']);
    $current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

    $us_deleted = 0;
    try {
        $us_deleted = intval($us_gate->update('users', array(
            'is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $current_user_id, 'updated_at' => date('Y-m-d H:i:s'),
        ), array('id' => $delete_id), "role != '-1' AND $users_not_deleted_sql"));
    } catch (\Throwable $t) { error_log('users.php delete: ' . $t->getMessage()); }

    if ($us_deleted > 0) {
        ems_gov_flash_redirect('users.php', '✅ تم حذف المستخدم بنجاح', 'GOV-SCOPE-403', '');
    } else {
        ems_gov_flash_redirect('users.php', '❌ حدث خطأ أثناء الحذف أو لا توجد صلاحية', 'GOV-PERM-403', '');
    }
    exit;
}
// تعريف عنوان الصفحة
$page_title = 'Equipation | المستخدمين';
// تضمين الهيدر
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
// تضمين الشريط الجانبي
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// إضافة أو تعديل مستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $passwordRaw = isset($_POST['password']) ? trim($_POST['password']) : '';
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $status_input = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    $status = (in_array($status_input, array('active', '1', 1, 'نشط', 'true'), true)) ? 'active' : 'inactive';
    $selected_role_scope = 'gloable';
    try {
        $role_lookup_row = $us_gate->selectOne('roles', array('columns' => array('role_scope'),
            'where' => array('id' => intval($role))));
        if ($role_lookup_row && isset($role_lookup_row['role_scope'])
            && ($role_lookup_row['role_scope'] === 'mine' || $role_lookup_row['role_scope'] === 'project')) {
            $selected_role_scope = 'project';
        }
    } catch (\Throwable $t) { error_log('users.php role scope: ' . $t->getMessage()); }

    $requires_project_context = ($selected_role_scope === 'project');
    $project = ($requires_project_context && !empty($_POST['project_id'])) ? intval($_POST['project_id']) : 0;
    $contract = ($requires_project_context && !empty($_POST['contract_id'])) ? intval($_POST['contract_id']) : 0;
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    // [ح-2] فحص فعل الكتابة صراحةً (can_add للإنشاء · can_edit للتعديل) — الحارس
    // المركزي يفحص can_view فقط، ومعالج POST هذا لا يفرّق بين الأفعال دونه.
    $us_write_perm = ($uid > 0) ? 'can_edit' : 'can_add';
    if ($us_perms['id'] !== null && !$us_perms[$us_write_perm]) {
        ems_gov_flash_redirect('users.php', '❌ لا توجد صلاحية لهذا الإجراء', 'GOV-PERM-403', '');
        exit;
    }

    // [ح-02] الفحصُ الخادميُّ لمنع التصعيد — يسبق أيَّ كتابة، ولا يعتمد على القائمة
    // المعروضة. أيُّ دورٍ خارج (دورِك + ذريّتِك) يُرفض ويُسجَّل حدثًا أمنيًّا.
    if (!ems_role_is_assignable($conn, isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '', $role)) {
        ems_gov_log('ROLE_ESCALATION_BLOCKED',
            'screen=main/users.php actor_role=' . (isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '?')
            . ' target_role=' . $role . ' actor_uid=' . (isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0));
        ems_gov_flash_redirect('users.php', '❌ لا يمكنك إسناد دورٍ خارج نطاق دورك', 'GOV-SCOPE-403', '');
        exit;
    }

    // 🔗 ربط الحساب بموظف (اختياري): تحقّق من الملكية والتفرّد قبل الحفظ.
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    $employee_link_valid = true;
    if ($employee_link_id > 0) {
        try {
            // (1) الموظف يجب أن يتبع شركة المستخدم الحالي (النطاق عبر البوابة)
            $us_emp_chk = $us_gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employee_link_id)));
            if ($us_emp_chk === null) {
                $employee_link_valid = false;
            } else {
                // (2) الموظف غير مرتبط بحسابٍ آخر (يستثني السجل الحالي عند التعديل)
                $us_link_chk = $us_gate->selectOne('users', array('columns' => array('id'),
                    'where' => array('employee_id' => $employee_link_id),
                    'whereRaw' => ($uid > 0 ? 'id != ' . intval($uid) . ' AND ' : '') . $users_not_deleted_sql));
                if ($us_link_chk !== null) {
                    $employee_link_valid = false;
                }
            }
        } catch (\Throwable $t) { $employee_link_valid = false; error_log('users.php employee check: ' . $t->getMessage()); }
    }

    // H-20: حسابُ «مشرف موردين» (8) يُربط بمورده — إلزامٌ وظيفيٌّ عند الحفظ
    // (الحارسُ fail-closed فبلا ربطٍ لا يرى الحسابُ شيئًا). التحقق: مورد الشركة.
    $supplier_link_id = !empty($_POST['supplier_entity_id']) ? intval($_POST['supplier_entity_id']) : 0;
    $supplier_link_valid = true;
    if (strval($role) !== '8') { $supplier_link_id = 0; }
    if ($supplier_link_id > 0) {
        try {
            $us_sup_chk = $us_gate->selectOne('suppliers', array('columns' => array('id'), 'where' => array('id' => $supplier_link_id)));
            if ($us_sup_chk === null) { $supplier_link_valid = false; }
        } catch (\Throwable $t) { $supplier_link_valid = false; error_log('users.php supplier check: ' . $t->getMessage()); }
    }

    if ($users_has_employee_id && $employee_link_id <= 0) {
        echo "<script>alert('⚠️ يجب إسناد موظف لهذا الحساب — لا يوجد حساب يعمل بلا موظف مُسنَد له');</script>";
    } elseif (strval($role) === '8' && $supplier_link_id <= 0) {
        echo "<script>alert('⚠️ حساب مشرف الموردين يجب ربطه بمورد — بلا ربطٍ لا يرى الحساب شيئًا');</script>";
    } elseif ($supplier_link_id > 0 && !$supplier_link_valid) {
        echo "<script>alert('⚠️ المورد المحدد غير صالح أو خارج نطاق الشركة');</script>";
    } elseif ($requires_project_context && ($project <= 0 || $contract <= 0)) {
        echo "<script>alert('⚠️ هذه الإدارة مرتبطة بمشروع محدد، يرجى اختيار المشروع والعقد');</script>";
    } elseif ($employee_link_id > 0 && !$employee_link_valid) {
        echo "<script>alert('⚠️ الموظف المحدد غير صالح أو مرتبط بحساب آخر');</script>";
    } elseif ($uid > 0) {

        // تحقق من التكرار عند التعديل (يتجاهل السجل الحالي) - التحقق عالمي عبر جميع الشركات
        // [مُستثنى موثَّق — قراءة تفرُّدٍ عالمية] اسم الدخول هوية منصّةٍ عابرة للشركات؛
        // تنطيقها بالشركة يسمح بتصادم أسماء الدخول. تبقى خامًا بانتظار قناة القراءة
        // العالمية في دفعة المزوّد (admin/).
        $username_esc = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username_esc' AND id != '$uid' AND $users_not_deleted_sql LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // (كان الأصل يعيد كتابة company_id بقيمة الجلسة نفسها — الصف معزول عبر البوابة
            // أصلًا فالإعادة لغو، وتمريرها في بيانات التحديث محظور تعاقديًا)
            $us_data = array(
                'name' => $name, 'username' => $username, 'phone' => $phone, 'role' => $role,
                'project_id' => $project, 'contract_id' => $contract,
                'updated_at' => date('Y-m-d H:i:s'), 'status' => $status,
            );
            // إذا تم إدخال كلمة مرور جديدة، نشفّرها ونحدّثها؛ وإلا لا نغيّرها
            if ($passwordRaw !== '') { $us_data['password'] = password_hash($passwordRaw, PASSWORD_BCRYPT); }
            if ($users_has_employee_id) { $us_data['employee_id'] = $employee_link_id > 0 ? $employee_link_id : null; }
            // H-20: نطاقُ مشرف المورد — يُكتب للدور 8 ويُصفَّر لغيره (لا نطاقَ يتيمًا)
            $us_data['supplier_entity_id'] = $supplier_link_id > 0 ? $supplier_link_id : null;

            $us_upd_ok = false;
            try { $us_gate->update('users', $us_data, array('id' => $uid), $users_not_deleted_sql); $us_upd_ok = true; }
            catch (\Throwable $t) { error_log('users.php update: ' . $t->getMessage()); }
            if ($us_upd_ok) {
                ems_gov_flash_redirect('users.php', '✅ تم التعديل بنجاح', 'GOV-SCOPE-403', '');
            } else {
                echo "<script>alert('❌ حدث خطأ أثناء التعديل');</script>";
            }
        }
    } else {
        // تحقق من التكرار عند الإضافة - التحقق عالمي عبر جميع الشركات
        // [مُستثنى موثَّق — قراءة تفرُّدٍ عالمية] (انظر تعليق التعديل أعلاه)
        $username_esc = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username_esc' AND $users_not_deleted_sql LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            if ($passwordRaw === '') {
                echo "<script>alert('⚠️ كلمة المرور مطلوبة عند إضافة مستخدم جديد');</script>";
            } else {
                // company_id تحقنه البوابة من سياق الجلسة
                $us_ins_ok = false;
                try {
                    $us_gate->insert('users', array(
                        'name' => $name, 'username' => $username,
                        'password' => password_hash($passwordRaw, PASSWORD_BCRYPT), 'phone' => $phone,
                        'role' => $role, 'project_id' => $project, 'contract_id' => $contract,
                        'parent_id' => '0', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                        'status' => $status,
                        'employee_id' => $employee_link_id > 0 ? $employee_link_id : null,
                        'supplier_entity_id' => $supplier_link_id > 0 ? $supplier_link_id : null,
                    ));
                    $us_ins_ok = true;
                } catch (\Throwable $t) { error_log('users.php insert: ' . $t->getMessage()); }
                if ($us_ins_ok) {
                    ems_gov_flash_redirect('users.php', '✅ تم الحفظ بنجاح', 'GOV-SCOPE-403', '');
                } else {
                    echo "<script>alert('❌ حدث خطأ أثناء الحفظ');</script>";
                }
            }
        }
    }
}

// ملاحظة: التعامل مع حذف المستخدم موجود في الواجهة (رابط ?delete=...) لكن كود الحذف كان معلقاً في النسخة الأصلية.
// إذا أردت أفعّل الحذف أضيفه لك هنا بأمان مع تحقق الصلاحيات.
?>

<div class="main project-users-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'إدارة المستخدمين';
    $header_icon    = 'fas fa-cogs';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'btn btn-primary', 'attrs' => 'type="button"', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مستخدم جديد'),
    );
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا مستخدمين مسجَّلين ضمن نطاقك بعدُ',
        'أضف مستخدمًا جديدًا من زرِّ «إضافة مستخدم جديد» — ولا حسابَ يعمل بلا موظفٍ مُسنَد');
    ?>

    <?php

    $userRole = $_SESSION['user']['role'];
    $userName = $_SESSION['user']['name'];
    $roleText = isset($roles[$userRole]) ? $roles[$userRole] : "غير معروف";
    ?>

    <form id="projectForm" action="" method="post" class="allforms">
         <div class="card-header">
                <h5><i class="fas fa-user-edit"></i> إضافة / تعديل مستخدم</h5>
            </div>
        <input type="hidden" name="uid" id="uid" value="0" />
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label for="name"><i class="fas fa-user"></i> الاسم الثلاثي</label>
                        <input type="text" name="name" id="name" placeholder="الاسم الثلاثي" value="مستخدم" required />
                    </div>
                    <div>
                        <label for="username"><i class="fas fa-id-badge"></i> اسم المستخدم</label>
                        <input type="text" name="username" id="username" placeholder="اسم المستخدم (الحد الأدنى 3 أحرف)"
                            value="username" required autocomplete="off" />
                        <small id="usernameFeedback" class="pu-username-feedback"></small>
                    </div>
                    <div>
                        <label for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
                        <input type="password" name="password" id="password" placeholder="كلمة المرور"
                            value="12345678" />
                        <small class="text-muted"><i class="fas fa-info-circle"></i> اتركه فارغاً إذا لا تريد تغييره
                            عند التعديل</small>
                    </div>
                    <div>
                        <label for="role"><i class="fas fa-user-shield"></i> الإدارة / الصلاحية</label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="">-- حدد الصلاحية --</option>
                            <?php foreach ($roles as $role_id => $role_name): ?>
                                <option value="<?php echo htmlspecialchars((string) $role_id, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="phone"><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required
                            value="09209303903" />
                    </div>

                    <div>
                        <label for="status"><i class="fas fa-toggle-on"></i> حالة المستخدم</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="active" selected>✅ نشط</option>
                            <option value="inactive">❌ غير نشط</option>
                        </select>
                    </div>

                    <?php if ($users_has_employee_id): ?>
                    <div>
                        <label for="employee_id_link"><i class="fas fa-id-card-alt"></i> الموظف المُسنَد <span class="pu-required-star">*</span></label>
                        <select name="employee_id" id="employee_id_link" class="form-control" required>
                            <option value="">— اختر الموظف —</option>
                            <?php foreach ($employees_for_link as $emp): ?>
                                <option value="<?php echo intval($emp['id']); ?>"
                                    data-linked-uid="<?php echo intval($emp['linked_uid']); ?>"
                                    data-name="<?php echo htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-phone="<?php echo htmlspecialchars((string) $emp['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><i class="fas fa-info-circle"></i> إلزامي — لا يوجد حساب يعمل بلا موظف مُسنَد. تُعبّأ بيانات الموظف تلقائياً عند الاختيار.</small>
                    </div>
                    <?php endif; ?>

                    <div id="supplierDiv" class="pu-hidden">
                        <label for="supplier_entity_id"><i class="fas fa-truck-field"></i> المورد المرتبط <span class="pu-required-star">*</span></label>
                        <select name="supplier_entity_id" id="supplier_entity_id" class="form-control">
                            <option value="">— اختر المورد —</option>
                            <?php foreach ($suppliers_for_link as $sup): ?>
                                <option value="<?php echo intval($sup['id']); ?>">
                                    <?php echo htmlspecialchars((string) $sup['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><i class="fas fa-info-circle"></i> إلزامي لدور «مشرف موردين» — الحسابُ يرى موردَه هذا حصرًا (H-20)، وبلا ربطٍ لا يرى شيئًا.</small>
                    </div>

                    <div id="projectDiv" class="pu-hidden">
                        <label for="project_id"><i class="fas fa-project-diagram"></i> المشروع <span
                                class="pu-required-star">*</span></label>
                        <select id="project_id" name="project_id" class="form-control">
                            <option value="">-- اختر المشروع --</option>
                            <?php
                            $result = array();
                            try {
                                $result = $us_gate->scopedQuery(array('scope' => array('project' => 'project')),
                                    "SELECT id, name, project_code FROM project WHERE status = '1' AND COALESCE(is_deleted,0)=0 AND {TENANT_SCOPE} ORDER BY name ASC");
                            } catch (\Throwable $t) { error_log('users.php project options: ' . $t->getMessage()); }
                            if ($result) {
                            foreach ($result as $row) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " ({$row['project_code']})</option>";
                            }
                            }
                            ?>
                        </select>
                    </div>

                    <div id="contractDiv" class="pu-hidden">
                        <label for="contract_id"><i class="fas fa-file-contract"></i> العقد <span
                                class="pu-required-star">*</span></label>
                        <select id="contract_id" name="contract_id" class="form-control">
                            <option value="">-- اختر العقد --</option>
                        </select>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-eraser"></i> مسح الحقول
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ المستخدم
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label for="filterRole"><i class="fa fa-user-shield"></i> الإدارة</label>
                <select id="filterRole" class="form-control">
                    <option value="">-- كل الإدارات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="filterStatus"><i class="fa fa-toggle-on"></i> الحالة</label>
                <select id="filterStatus" class="form-control">
                    <option value="">-- كل الحالات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="filterLinked"><i class="fa fa-id-card-alt"></i> الارتباط بموظف</label>
                <select id="filterLinked" class="form-control">
                    <option value="">-- الكل --</option>
                    <option value="linked">مرتبط بموظف</option>
                    <option value="unlinked">غير مرتبط</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" id="usersFilterApply" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" id="usersFilterReset" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="users-table-actions-row">
                <div class="users-table-note">عرض وإدارة المستخدمين مع التصدير السريع من الشريط العلوي</div>
            </div>
            <div class="table-container">
                <table id="projectsTable" class="display nowrap pu-table users-table-nowrap pu-w100">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th>الاسم </th>
                            <th>اسم المستخدم </th>
                            <th>كلمه المرور </th>
                            <th>الإدارة </th>
                            <th>الموظف المرتبط</th>
                            <th>رقم الهاتف</th>
                            <th>الحالة</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">كود الحساب</th>
                            <th class="ems-fn-th" data-fn="1">الشخص</th>
                            <th class="ems-fn-th" data-fn="1">رقم الهوية</th>
                            <th class="ems-fn-th" data-fn="1">الصفة</th>
                            <th class="ems-fn-th" data-fn="1">الدور</th>
                            <th class="ems-fn-th" data-fn="1">قالب الصلاحيات</th>
                            <th class="ems-fn-th" data-fn="1">النطاق</th>
                            <th class="ems-fn-th" data-fn="1">سقف الاعتماد</th>
                            <th class="ems-fn-th" data-fn="1">آخر دخول</th>
                            <th class="ems-fn-th" data-fn="1">حالة الحساب</th>
                            <th class="ems-fn-th" data-fn="1">أنشأه</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                            <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = array();
                        try {
                            $result = $us_gate->select('users', array(
                                'columns' => array('id', 'name', 'username', 'password', 'phone', 'role', 'project_id', 'contract_id', 'status', 'employee_id', 'supplier_entity_id'),
                                'whereRaw' => "parent_id='0' AND role!='-1' AND $users_not_deleted_sql",
                                'orderBy' => 'id DESC',
                                'includeDeleted' => true));
                        } catch (\Throwable $t) { error_log('users.php list: ' . $t->getMessage()); }

                        if ($result) {
                        foreach ($result as $row) {
                            $project_id = $row['project_id'];
                            $contract_id = $row['contract_id'];

                            $project_info = "";

                            $row_role_key = isset($row['role']) ? (string) $row['role'] : '';
                            $row_role_scope = isset($roles_scope[$row_role_key]) ? $roles_scope[$row_role_key] : 'gloable';

                            if ($row_role_scope === 'mine' || $row_role_scope === 'project') {
                                // جلب اسم المشروع (includeDeleted حفاظًا على سلوك الأصل غير المرشِّح)
                                if ($project_id > 0) {
                                    $project_row = null;
                                    try {
                                        $project_row = $us_gate->selectOne('project', array('columns' => array('name', 'project_code'),
                                            'where' => array('id' => intval($project_id)), 'includeDeleted' => true));
                                    } catch (\Throwable $t) { error_log('users.php row project: ' . $t->getMessage()); }
                                    if ($project_row) {
                                        $project_info = "<div class='pu-project-meta'><div class='pu-project-meta-item'><i class='fas fa-project-diagram pu-meta-icon'></i> " . htmlspecialchars($project_row['name'], ENT_QUOTES, 'UTF-8') . " (" . $project_row['project_code'] . ")";
                                    }
                                }

                                // جلب تاريخ العقد
                                if ($contract_id > 0) {
                                    $contract_row = null;
                                    try {
                                        $contract_row = $us_gate->selectOne('contracts', array('columns' => array('contract_signing_date'),
                                            'where' => array('id' => intval($contract_id)), 'includeDeleted' => true));
                                    } catch (\Throwable $t) { error_log('users.php row contract: ' . $t->getMessage()); }
                                    if ($contract_row) {
                                        $project_info .= "</div><div class='pu-project-meta-item'><i class='fas fa-file-contract pu-meta-icon'></i> عقد #" . $contract_id . " - " . $contract_row['contract_signing_date'];
                                    }
                                }

                                if (!empty($project_info)) {
                                    $project_info = $project_info . "</div></div>";
                                }
                            }

                            echo "<tr>";
                                                        $raw_status = isset($row['status']) ? trim((string) $row['status']) : 'active';
                                                        $status_is_active = in_array(strtolower($raw_status), array('1', 'active', 'true', 'نشط'), true);
                                                        $status_badge = $status_is_active
                                                                ? "<span class='status-active'><i class='fa-regular fa-circle-check'></i> نشط</span>"
                                                                : "<span class='status-inactive'><i class='fa-regular fa-circle-xmark'></i> غير نشط</span>";

                                                        echo "<td>
                                                                <div class='action-btns'>
                                                                <a href='javascript:void(0)' class='editBtn action-btn edit'
                                                                     data-id='{$row['id']}'
                                                                     data-name='" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "'
                                                                     data-username='" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "'
                                                                     data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "'
                                                                     data-role='{$row['role']}'
                                                                     data-status='" . ($status_is_active ? 'active' : 'inactive') . "'
                                                                     data-project='{$row['project_id']}'
                                                                     data-contract='{$row['contract_id']}'
                                                                     data-employee='" . intval($row['employee_id']) . "'
                                                                     data-supplier='" . intval(isset($row['supplier_entity_id']) ? $row['supplier_entity_id'] : 0) . "'
                                                                     title='تعديل'><i class='fas fa-edit'></i></a>
                                                                <a href='?delete={$row['id']}' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>
                                                                </div>
                                                            </td>";
                            echo "<td><a class='client-name-link' href='user_profile.php?id=" . intval($row['id']) . "'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</a>" . $project_info . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                            echo "<td><span class='password-cell pu-password-cell'>••••••••</span></td>";
                            echo "<td><span class='role-badge role-" . $row['role'] . "'>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</span></td>";
                            $linked_emp_id = isset($row['employee_id']) ? intval($row['employee_id']) : 0;
                            if ($linked_emp_id > 0 && isset($emp_name_by_id[$linked_emp_id])) {
                                echo "<td><a class='client-name-link' href='../Employees/employee_profile.php?id=" . $linked_emp_id . "'><i class='fas fa-id-card-alt'></i> " . htmlspecialchars($emp_name_by_id[$linked_emp_id], ENT_QUOTES, 'UTF-8') . "</a></td>";
                            } else {
                                echo "<td><span class='pu-unlinked'>— غير مرتبط —</span></td>";
                            }
                            echo "<td><i class='fas fa-phone'></i>" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                        echo "<td>" . $status_badge . "</td>";
                            echo "</tr>";
                        }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<!-- jQuery + DataTables -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
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
        const contractDiv = document.getElementById("contractDiv");

        const projectSelect = document.getElementById("project_id");
        const contractSelect = document.getElementById("contract_id");
        const employeeSelect = document.getElementById("employee_id_link");
        const supplierDiv = document.getElementById("supplierDiv");
        const supplierSelect = document.getElementById("supplier_entity_id");

        // H-20: حقلُ «المورد المرتبط» يظهر لدور مشرف الموردين (8) وحده
        function toggleSupplierLink(roleId) {
            const isPortalRole = String(roleId) === '8';
            supplierDiv.classList.toggle("pu-hidden", !isPortalRole);
            supplierSelect.required = isPortalRole;
            if (!isPortalRole) { supplierSelect.value = ""; }
        }

        const form = document.getElementById('projectForm');
        const usernameInput = document.getElementById("username");
        const usernameFeedback = document.getElementById("usernameFeedback");

        let usernameValid = true;

        function roleNeedsMineScope(roleId) {
            if (!roleId) {
                return false;
            }
            return roleScopes[String(roleId)] === 'mine' || roleScopes[String(roleId)] === 'project';
        }

        /* =============================
           ربط الحساب بموظف (الخيار ب)
        ============================== */
        // تعطيل الموظفين المرتبطين بحسابٍ آخر، مع إبقاء الموظف المرتبط بالحساب الجاري تعديله مُتاحاً.
        function refreshEmployeeOptions(currentUid) {
            if (!employeeSelect) return;
            currentUid = String(currentUid || 0);
            Array.from(employeeSelect.options).forEach(function (opt) {
                if (!opt.value) return; // خيار «بدون ربط»
                const linked = String(opt.dataset.linkedUid || '0');
                opt.disabled = (linked !== '0' && linked !== currentUid);
            });
        }

        // عند اختيار موظف: تعبئة الاسم والهاتف تلقائياً من بطاقته.
        if (employeeSelect) {
            employeeSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    if (opt.dataset.name) document.getElementById('name').value = opt.dataset.name;
                    if (opt.dataset.phone && opt.dataset.phone.trim() !== '') document.getElementById('phone').value = opt.dataset.phone;
                }
            });
        }

        /* =============================
           إظهار الحقول حسب الدور
        ============================== */
        roleSelect.addEventListener("change", function () {

            toggleSupplierLink(this.value);

            if (roleNeedsMineScope(this.value)) {

                projectDiv.classList.remove("pu-hidden");
                contractDiv.classList.remove("pu-hidden");

                projectSelect.required = true;
                contractSelect.required = true;

            } else {

                projectDiv.classList.add("pu-hidden");
                contractDiv.classList.add("pu-hidden");

                projectSelect.required = false;
                contractSelect.required = false;

                projectSelect.value = "";
                contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';
            }
        });

        /* =============================
           تحميل العقود
        ============================== */
        async function loadContracts(projectId, selectedContract = null) {

            contractSelect.innerHTML = '<option value="">-- جاري التحميل... --</option>';

            if (!projectId) {
                contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';
                return;
            }

            try {
                const response = await fetch(`users.php?ajax=contracts&project_id=${encodeURIComponent(projectId)}`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });

                const responseText = await response.text();
                let data = null;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    throw new Error('استجابة غير صالحة من الخادم');
                }

                if (data.success && data.contracts.length > 0) {
                    contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';
                    data.contracts.forEach(contract => {
                        const option = document.createElement('option');
                        option.value = contract.id;
                        if (contract.display_name) {
                            option.textContent = contract.display_name;
                        } else {
                            const start = contract.actual_start ? String(contract.actual_start) : '-';
                            option.textContent = `عقد رقم ${contract.id} - ${start}`;
                        }
                        contractSelect.appendChild(option);
                    });

                    if (selectedContract) {
                        contractSelect.value = selectedContract;
                    }
                } else {
                    contractSelect.innerHTML = '<option value="">-- لا توجد عقود لهذا المشروع --</option>';
                }

            } catch (error) {
                console.error("خطأ في تحميل العقود:", error);
                contractSelect.innerHTML = '<option value="">-- خطأ في التحميل --</option>';
            }
        }

        /* =============================
           عند تغيير المشروع
        ============================== */
        projectSelect.addEventListener("change", async function () {
            await loadContracts(this.value);
        });

        /* =============================
           DataTable
           UXW-01 ⑤: لا تهيئةَ محليةً — التهيئةُ للمكوّنِ المركزيِّ وحدَه
           (assets/js/ui-unification.js — ومعه زرُّ التصدير الموحَّد)؛
           وكلُّ ما يعتمد واجهةَ الجدولِ ينتظرها هنا.
        ============================== */
        function whenUsersTable(cb, tries) {
            if (window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable('#projectsTable')) {
                cb($('#projectsTable').DataTable());
                return;
            }
            tries = (tries === undefined) ? 60 : tries;
            if (tries > 0) { setTimeout(function () { whenUsersTable(cb, tries - 1); }, 150); }
        }

        whenUsersTable(function (usersTable) {

        /* =============================
           فلاتر المستخدمين — نفس هوية/تصميم فلاتر شاشة العملاء
           تقرأ نص الخلية مباشرةً لتجاوز شارات الـHTML (الدور/الحالة/الارتباط)
        ============================== */
        function fillUserFilter(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            usersTable.column(columnIndex).nodes().each(function (node) {
                const text = $(node).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort(function (a, b) { return a.localeCompare(b, 'ar'); });
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillUserFilter(5, '#filterRole');    // الدور
        fillUserFilter(8, '#filterStatus');  // الحالة

        $.fn.dataTable.ext.search.push(function (settings, rowData, dataIndex) {
            // تطبيق على جدول المستخدمين فقط (دالة البحث عامة لكل الجداول)
            if (settings.nTable !== usersTable.table().node()) return true;
            const roleSel = $('#filterRole').val();
            const statusSel = $('#filterStatus').val();
            const linkedSel = $('#filterLinked').val();
            if (roleSel && $(usersTable.cell(dataIndex, 5).node()).text().trim() !== roleSel) return false;
            if (statusSel && $(usersTable.cell(dataIndex, 8).node()).text().trim() !== statusSel) return false;
            if (linkedSel) {
                const linkedText = $(usersTable.cell(dataIndex, 6).node()).text();
                const isUnlinked = (linkedText.indexOf('غير مرتبط') !== -1) || (linkedText.trim() === '');
                if (linkedSel === 'linked' && isUnlinked) return false;
                if (linkedSel === 'unlinked' && !isUnlinked) return false;
            }
            return true;
        });

        $('#filterRole, #filterStatus, #filterLinked').on('change', function () { usersTable.draw(); });
        $('#usersFilterApply').on('click', function () { usersTable.draw(); });
        $('#usersFilterReset').on('click', function () {
            $('#filterRole, #filterStatus, #filterLinked').val('');
            usersTable.search('').draw();
        });

        });

        /* =============================
           تهيئة مسبقة عند القدوم من بطاقة الموظف (?employee_id=N)
        ============================== */
        const prefillEditUid = <?php echo intval($prefill_edit_uid); ?>;
        const prefillEmployeeId = <?php echo intval($prefill_employee_id); ?>;
        if (prefillEditUid > 0) {
            // للموظف حساب مرتبط: افتحه في وضع التعديل
            const $btn = $('.editBtn[data-id="' + prefillEditUid + '"]');
            if ($btn.length) { $btn.first().trigger('click'); }
        } else if (prefillEmployeeId > 0 && employeeSelect) {
            // لا حساب: افتح نموذج إضافة مهيّأً بهذا الموظف
            form.reset();
            document.getElementById("uid").value = 0;
            refreshEmployeeOptions(0);
            employeeSelect.value = String(prefillEmployeeId);
            employeeSelect.dispatchEvent(new Event('change'));
            form.classList.add("allforms-visible");
            $('html, body').animate({ scrollTop: $(form).offset().top - 20 }, 400);
        }

        /* =============================
           إظهار / إخفاء النموذج
        ============================== */
        document.getElementById('toggleForm').addEventListener('click', function () {
            form.reset();
            document.getElementById("uid").value = 0;
            usernameFeedback.innerHTML = "";
            usernameInput.classList.remove("pu-input-warn", "pu-input-success", "pu-input-error");
            usernameValid = true;
            if (employeeSelect) { employeeSelect.value = ""; refreshEmployeeOptions(0); }
            toggleSupplierLink("");
            form.classList.toggle("allforms-visible");
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
            const status = $(this).data('status');

            const projectId = $(this).data('project');
            const contractId = $(this).data('contract');
            const employeeId = $(this).data('employee');
            const supplierId = $(this).data('supplier');

            $('#uid').val(id);
            $('#name').val(name);
            $('#username').val(username);
            $('#phone').val(phone);
            $('#role').val(role).trigger('change');
            $('#status').val(status || 'active');

            // ربط الموظف: أتح خيار الموظف الحالي ثم اضبط القيمة
            if (employeeSelect) {
                refreshEmployeeOptions(id);
                employeeSelect.value = (employeeId && parseInt(employeeId, 10) > 0) ? String(employeeId) : "";
            }

            // H-20: نطاق مشرف المورد — أظهر الحقل بحسب الدور واضبط قيمته
            toggleSupplierLink(role);
            supplierSelect.value = (supplierId && parseInt(supplierId, 10) > 0) ? String(supplierId) : "";

            // إعادة تعيين حالة التحقق من اسم المستخدم
            usernameFeedback.innerHTML = '<span class="pu-feedback-ok"><i class="fas fa-check-circle"></i> اسم المستخدم الحالي</span>';
            usernameInput.classList.remove("pu-input-warn", "pu-input-success", "pu-input-error");
            usernameValid = true;

            if (roleNeedsMineScope(role)) {

                if (projectId) {
                    $('#project_id').val(projectId);
                    await loadContracts(projectId, contractId);
                }
            }

            $('#password').val("");
            form.classList.add("allforms-visible");

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
                usernameInput.classList.remove("pu-input-warn", "pu-input-success", "pu-input-error");
                usernameValid = true;
                return;
            }

            // التحقق من الطول الأدنى
            if (username.length < 3) {
                usernameFeedback.innerHTML = '<span class="pu-feedback-warn"><i class="fas fa-info-circle"></i> الحد الأدنى 3 أحرف</span>';
                usernameInput.classList.remove("pu-input-success", "pu-input-error");
                usernameInput.classList.add("pu-input-warn");
                usernameValid = false;
                return;
            }

            // إظهار رسالة التحميل
            usernameFeedback.innerHTML = '<span class="pu-feedback-loading"><i class="fas fa-spinner fa-spin"></i> جاري التحقق...</span>';

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
                    usernameFeedback.innerHTML = `<span class="pu-feedback-ok"><i class="fas fa-check-circle"></i> ${data.message}</span>`;
                    usernameValid = true;
                    usernameInput.classList.remove("pu-input-warn", "pu-input-error");
                    usernameInput.classList.add("pu-input-success");
                } else {
                    usernameFeedback.innerHTML = `<span class="pu-feedback-error"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                    usernameValid = false;
                    usernameInput.classList.remove("pu-input-warn", "pu-input-success");
                    usernameInput.classList.add("pu-input-error");
                }
            } catch (error) {
                usernameFeedback.innerHTML = '<span class="pu-feedback-error"><i class="fas fa-exclamation-triangle"></i> خطأ في التحقق</span>';
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
