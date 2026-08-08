<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-837 · SCN-841 · SCN-842 · SCN-843 · SCN-844 · SCN-845 · SCN-846
/**
 * إدارة المعاونين الشاملة — شاشة خاصة بدور «مدير الصلاحيات».
 * بخلاف main/project_users.php (التي تعرض معاوني المدير الحالي فقط)، تعرض هذه الشاشة
 * وتدير **كل الحسابات الفرعية** (parent_id<>0) في الشركة بصرف النظر عن مديرها الأب.
 * تُختار للمعاون: المدير الأب + دورٌ يكون ابناً لدور ذلك المدير + موظفٌ مُسنَد (إلزامي).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
// الأعمدة (company_id/employee_id/is_deleted) قائمة بالترحيلات — سقطت فحوص db_table_has_column
$users_has_employee_id = true;
$users_not_deleted_sql = "(COALESCE(u.is_deleted,0)=0)";

$_currentUserRole = intval($_SESSION['user']['role']);
$is_super_admin = (strval($_SESSION['user']['role']) === '-1');
if (!$is_super_admin && $current_company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'الحساب غير مرتبط بشركة ❌', 'GOV-INFO-200', ''); exit;
}

// بوابة الوصول: تعتمد على جدول صلاحيات هذه الشاشة (موديول main/all_assistants.php)
// لا على اسم الدور — فتدوم رغم إعادة تسمية الدور. أي دورٍ مُنح هذه الشاشة يصبح المدير الشامل.
$pp = check_page_permissions($conn, 'main/all_assistants.php');
$can_view   = $is_super_admin ? true : !empty($pp['can_view']);
$can_add    = $is_super_admin ? true : !empty($pp['can_add']);
$can_edit   = $is_super_admin ? true : !empty($pp['can_edit']);
$can_delete = $is_super_admin ? true : !empty($pp['can_delete']);
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لهذه الشاشة ❌', 'GOV-PERM-403', ''); exit;
}

// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$aa_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('all assistants super') : ems_tenant_db();

// مدراء الشركة (آباء محتملون): مستخدمون عُلويون parent_id='0' بأدوارٍ عُلوية.
$managers = array();
try {
    $managers = $aa_gate->scopedQuery(array('scope' => array('u' => 'users')),
        "SELECT u.id, u.name, u.username, u.role, ro.name AS role_name
    FROM users u LEFT JOIN roles ro ON ro.id = u.role
    WHERE (u.parent_id='0' OR u.parent_id='') AND u.role <> '-1' AND $users_not_deleted_sql AND {TENANT_SCOPE}
      AND u.role IN (SELECT r.id FROM roles r WHERE (r.parent_role_id IS NULL OR r.parent_role_id=0))
    ORDER BY u.name ASC");
} catch (\Throwable $t) { error_log('all_assistants.php managers: ' . $t->getMessage()); }

// كل الأدوار التابعة (المستوى الثاني) — roles مرجع عام: قراءته عبر البوابة بلا نطاق.
$child_roles = array();
try {
    $child_roles = $aa_gate->select('roles', array(
        'columns' => array('id', 'name', 'parent_role_id'),
        'whereRaw' => "parent_role_id IS NOT NULL AND parent_role_id<>0 AND (status='1' OR status=1)",
        'orderBy' => 'name ASC'));
} catch (\Throwable $t) { error_log('all_assistants.php child roles: ' . $t->getMessage()); }

// موظفو الشركة المتاحون للربط (+ المرتبط حالياً عند التعديل).
$employees_for_link = array(); $emp_name_by_id = array();
if ($users_has_employee_id) {
    try {
        $aa_emps = $aa_gate->scopedQuery(array('scope' => array('e' => 'employees'), 'enrich' => array('u2' => 'users')),
            "SELECT e.id, e.name, e.phone,
            (SELECT u2.id FROM users u2 WHERE u2.employee_id=e.id AND COALESCE(u2.is_deleted,0)=0 LIMIT 1) AS linked_uid
        FROM employees e WHERE 1=1 AND {TENANT_SCOPE} ORDER BY e.name ASC");
        foreach ($aa_emps as $er) {
            $employees_for_link[] = array('id'=>intval($er['id']),'name'=>$er['name'],'phone'=>$er['phone'],'linked_uid'=>($er['linked_uid']!==null)?intval($er['linked_uid']):0);
            $emp_name_by_id[intval($er['id'])] = $er['name'];
        }
    } catch (\Throwable $t) { error_log('all_assistants.php employees: ' . $t->getMessage()); }
}

// خريطة دور كل مدير (للتحقق أن الدور المختار ابنٌ فعلاً لدور المدير الأب) — عبر البوابة.
function aa_user_role($g, $uid) {
    $uid = intval($uid);
    try {
        $row = $g->selectOne('users', array('columns' => array('role'),
            'where' => array('id' => $uid), 'whereRaw' => 'COALESCE(is_deleted,0)=0'));
    } catch (\Throwable $t) { $row = null; }
    return $row ? intval($row['role']) : 0;
}

// ============ معالجة الحذف ============
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $can_delete) {
    $did = intval($_GET['delete']);
    // فقط الحسابات الفرعية (لها أبٌ) — لا تمسّ المدراء الرئيسيين. (النطاق عبر البوابة)
    $aa_deleted = 0;
    try {
        $aa_deleted = intval($aa_gate->update('users', array(
            'is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => intval($_SESSION['user']['id']), 'updated_at' => date('Y-m-d H:i:s'),
        ), array('id' => $did), "parent_id<>'0' AND parent_id<>'' AND role<>'-1' AND COALESCE(is_deleted,0)=0"));
    } catch (\Throwable $t) { error_log('all_assistants.php delete: ' . $t->getMessage()); }
    if ($aa_deleted > 0) {
        ems_gov_flash_redirect('all_assistants.php', 'تم حذف المعاون بنجاح ✅', 'GOV-OK-200', '');
    } else {
        ems_gov_flash_redirect('all_assistants.php', 'تعذّر الحذف أو ليس حساباً فرعياً ❌', 'GOV-FAIL-409', '');
    }
    exit;
}

// ============ إضافة / تعديل ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $is_editing = $uid > 0;
    if (($is_editing && !$can_edit) || (!$is_editing && !$can_add)) {
        ems_gov_flash_redirect('all_assistants.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit;
    }
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $passwordRaw = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $phone = trim($_POST['phone']);
    $role = intval($_POST['role'] ?? 0);
    $parent_user = intval($_POST['parent_id'] ?? 0);
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;

    // 1) المدير الأب ضمن الشركة وعُلوي (النطاق عبر البوابة)
    $parent_role = aa_user_role($aa_gate, $parent_user);
    if ($parent_user <= 0 || $parent_role <= 0) { ems_gov_flash_redirect('all_assistants.php', 'اختر مديراً أباً صالحاً ❌', 'GOV-INFO-200', ''); exit; }

    // 2) الدور ابنٌ فعلاً لدور المدير الأب (roles مرجع عام — قراءة عبر البوابة)
    $aa_role_ok = null;
    try {
        $aa_role_ok = $aa_gate->selectOne('roles', array('columns' => array('id'),
            'where' => array('id' => $role, 'parent_role_id' => $parent_role),
            'whereRaw' => "(status='1' OR status=1)"));
    } catch (\Throwable $t) { error_log('all_assistants.php role check: ' . $t->getMessage()); }
    if ($aa_role_ok === null) { ems_gov_flash_redirect('all_assistants.php', 'الدور يجب أن يكون تابعاً للمدير الأب ❌', 'GOV-INFO-200', ''); exit; }

    // 3) ربط الموظف إلزامي + تحقّق الملكية/التفرّد
    if ($users_has_employee_id && $employee_link_id <= 0) { ems_gov_flash_redirect('all_assistants.php', 'يجب إسناد موظف للحساب ❌', 'GOV-INFO-200', ''); exit; }
    if ($employee_link_id > 0) {
        $aa_emp_ok = null; $aa_linked = null;
        try {
            $aa_emp_ok = $aa_gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employee_link_id)));
            $aa_linked = $aa_gate->selectOne('users', array('columns' => array('id'),
                'where' => array('employee_id' => $employee_link_id),
                'whereRaw' => 'COALESCE(is_deleted,0)=0' . ($is_editing ? ' AND id != ' . intval($uid) : '')));
        } catch (\Throwable $t) { error_log('all_assistants.php employee check: ' . $t->getMessage()); }
        if ($aa_emp_ok === null || $aa_linked !== null) {
            ems_gov_flash_redirect('all_assistants.php', 'الموظف غير صالح أو مرتبط بحساب آخر ❌', 'GOV-INFO-200', ''); exit;
        }
    }

    // 4) تفرّد اسم المستخدم
    // [مُستثنى موثَّق — قراءة تفرُّدٍ عالمية] اسم الدخول هوية منصّةٍ عابرة للشركات (كما في
    // main/check_username_availability.php) — تنطيقها بالشركة يسمح بتصادم أسماء الدخول.
    // تبقى خامًا بانتظار قناة القراءة العالمية في دفعة المزوّد (admin/).
    $username_esc = mysqli_real_escape_string($conn, $username);
    $dupExcl = $is_editing ? " AND id != $uid" : "";
    $dup = mysqli_query($conn, "SELECT id FROM users WHERE username='$username_esc' $dupExcl AND COALESCE(is_deleted,0)=0 LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) { ems_gov_flash_redirect('all_assistants.php', 'اسم المستخدم موجود مسبقاً ❌', 'GOV-INFO-200', ''); exit; }

    if ($is_editing) {
        $aa_data = array(
            'name' => $name, 'username' => $username, 'phone' => $phone,
            'role' => $role, 'role_id' => $role, 'parent_id' => $parent_user,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        if ($passwordRaw !== '') { $aa_data['password'] = password_hash($passwordRaw, PASSWORD_DEFAULT); }
        if ($users_has_employee_id) { $aa_data['employee_id'] = $employee_link_id > 0 ? $employee_link_id : null; }
        try {
            $aa_gate->update('users', $aa_data, array('id' => $uid),
                "parent_id<>'0' AND parent_id<>'' AND COALESCE(is_deleted,0)=0");
        } catch (\Throwable $t) { error_log('all_assistants.php update: ' . $t->getMessage()); }
        ems_gov_flash_redirect('all_assistants.php', 'تم تعديل المعاون بنجاح ✅', 'GOV-OK-200', ''); exit;
    } else {
        if ($passwordRaw === '') { ems_gov_flash_redirect('all_assistants.php', 'كلمة المرور مطلوبة ❌', 'GOV-INFO-200', ''); exit; }
        // company_id تحقنه البوابة من سياق الجلسة
        try {
            $aa_gate->insert('users', array(
                'name' => $name, 'username' => $username,
                'password' => password_hash($passwordRaw, PASSWORD_DEFAULT), 'phone' => $phone,
                'role' => $role, 'role_id' => $role, 'parent_id' => $parent_user, 'project_id' => '0',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                'employee_id' => $employee_link_id > 0 ? $employee_link_id : null,
            ));
        } catch (\Throwable $t) { error_log('all_assistants.php insert: ' . $t->getMessage()); }
        ems_gov_flash_redirect('all_assistants.php', 'تم إضافة المعاون بنجاح ✅', 'GOV-OK-200', ''); exit;
    }
}

$page_title = "إيكوبيشن | المعاونون";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main project-users-main ems-unified-page-shell">
    <?php
    $header_icon   = 'fas fa-users-cog';
    $header_title_html = 'المعاونون <p class="small mb-0" style="color:#fff;">كل الحسابات الفرعية في الشركة عبر جميع المدراء</p>';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'إضافة معاون'); }
    $header_back = array('href'=>'../main/dashboard.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <form id="aForm" action="" method="post" class="allforms">
        <input type="hidden" id="uid" name="uid" value="0">
        <div class="card shadow-sm pu-form-card">
            <div class="card-header"><h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة معاون</span></h5></div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user-tie"></i> المدير الأب *</label>
                        <select name="parent_id" id="parent_id" required>
                            <option value="">-- اختر المدير --</option>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= intval($m['id']) ?>" data-role="<?= intval($m['role']) ?>">
                                    <?= htmlspecialchars($m['name'], ENT_QUOTES,'UTF-8') ?> — <?= htmlspecialchars($m['role_name'] ?: ('دور #'.$m['role']), ENT_QUOTES,'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-shield-alt"></i> الدور (تابع للمدير) *</label>
                        <select name="role" id="role" required>
                            <option value="">-- اختر المدير أولاً --</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> الاسم ثلاثي *</label>
                        <input type="text" name="name" id="name" placeholder="الاسم ثلاثي" required>
                    </div>
                    <div>
                        <label><i class="fas fa-at"></i> اسم المستخدم *</label>
                        <input type="text" name="username" id="username" placeholder="اسم المستخدم" required autocomplete="off">
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> كلمة المرور <span id="pwReq">*</span></label>
                        <input type="password" name="password" id="password" placeholder="كلمة المرور">
                        <small id="pwHint" class="pu-password-hint pu-hidden">اتركه فارغاً للاحتفاظ بالحالية عند التعديل</small>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف *</label>
                        <input type="tel" name="phone" id="phone" placeholder="رقم الهاتف" required>
                    </div>
                    <?php if ($users_has_employee_id): ?>
                    <div>
                        <label><i class="fas fa-id-card-alt"></i> الموظف المُسنَد *</label>
                        <select name="employee_id" id="employee_id_link" required>
                            <option value="">— اختر الموظف —</option>
                            <?php foreach ($employees_for_link as $emp): ?>
                                <option value="<?= intval($emp['id']) ?>" data-linked-uid="<?= intval($emp['linked_uid']) ?>"
                                        data-name="<?= htmlspecialchars((string)$emp['name'],ENT_QUOTES,'UTF-8') ?>"
                                        data-phone="<?= htmlspecialchars((string)$emp['phone'],ENT_QUOTES,'UTF-8') ?>">
                                    <?= htmlspecialchars((string)$emp['name'],ENT_QUOTES,'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="pu-password-hint">إلزامي — لا حساب يعمل بلا موظف مُسنَد. تُعبّأ بياناته تلقائياً.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitTxt">حفظ المعاون</span></button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('aForm').classList.remove('allforms-visible');"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card"><div class="card-body">
        <table id="aTable" class="display nowrap">
            <thead><tr>
                <th>#</th><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th><th>المدير الأب</th><th>الموظف المرتبط</th><th>رقم الهاتف</th><th>الإجراءات</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم التكليف</th>
                <th class="ems-fn-th" data-fn="1">الأصيل</th>
                <th class="ems-fn-th" data-fn="1">صفته</th>
                <th class="ems-fn-th" data-fn="1">المعاون</th>
                <th class="ems-fn-th" data-fn="1">نوع النيابة</th>
                <th class="ems-fn-th" data-fn="1">النطاق المفوَّض</th>
                <th class="ems-fn-th" data-fn="1">سقف الاعتماد</th>
                <th class="ems-fn-th" data-fn="1">من تاريخ</th>
                <th class="ems-fn-th" data-fn="1">إلى تاريخ</th>
                <th class="ems-fn-th" data-fn="1">سبب النيابة</th>
                <th class="ems-fn-th" data-fn="1">مرجع تفويض الأصيل</th>
                <th class="ems-fn-th" data-fn="1">أصدره</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php
            $list = array();
            try {
                $list = $aa_gate->scopedQuery(array('scope' => array('u' => 'users'), 'enrich' => array('p' => 'users')),
                    "SELECT u.id, u.name, u.username, u.phone, u.role, u.employee_id, u.parent_id,
                        ro.name AS role_name, p.name AS parent_name
                    FROM users u
                    LEFT JOIN roles ro ON ro.id = u.role
                    LEFT JOIN users p ON p.id = u.parent_id
                    WHERE u.parent_id <> '0' AND u.parent_id <> '' AND u.role <> '-1' AND $users_not_deleted_sql AND {TENANT_SCOPE}
                    ORDER BY u.id DESC");
            } catch (\Throwable $t) { error_log('all_assistants.php list: ' . $t->getMessage()); }
            $i = 1;
            if ($list) { foreach ($list as $row):
                $roleText = $row['role_name'] ? htmlspecialchars($row['role_name'],ENT_QUOTES,'UTF-8') : '<span class="pu-text-muted">غير معروف</span>';
                $eid = intval($row['employee_id']);
                $empCell = ($eid>0 && isset($emp_name_by_id[$eid]))
                    ? "<a class='client-name-link' href='../Employees/employee_profile.php?id=$eid'><i class='fas fa-id-card-alt'></i> ".htmlspecialchars($emp_name_by_id[$eid],ENT_QUOTES,'UTF-8')."</a>"
                    : "<span class='pu-text-muted'>— غير مرتبط —</span>";
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><code class='pu-code'><?= htmlspecialchars($row['username']) ?></code></td>
                    <td><?= $roleText ?></td>
                    <td><?= htmlspecialchars($row['parent_name'] ?: ('#'.$row['parent_id'])) ?></td>
                    <td><?= $empCell ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><div class='action-btns'>
                        <?php if ($can_edit): ?>
                        <a href='javascript:void(0)' class='action-btn edit'
                           data-id='<?= intval($row['id']) ?>' data-name='<?= htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8') ?>'
                           data-username='<?= htmlspecialchars($row['username'],ENT_QUOTES,'UTF-8') ?>' data-phone='<?= htmlspecialchars($row['phone'],ENT_QUOTES,'UTF-8') ?>'
                           data-role='<?= intval($row['role']) ?>' data-parent='<?= intval($row['parent_id']) ?>' data-employee='<?= $eid ?>'
                           title='تعديل'><i class='fas fa-edit'></i></a>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                        <a href='all_assistants.php?delete=<?= intval($row['id']) ?>' class='action-btn delete' onclick="return confirm('حذف هذا المعاون؟')" title='حذف'><i class='fas fa-trash'></i></a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; } ?>
            </tbody>
        </table>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script>
(function(){
    const CHILD_ROLES = <?= json_encode($child_roles, JSON_UNESCAPED_UNICODE) ?>;
    const parentSel = document.getElementById('parent_id');
    const roleSel = document.getElementById('role');
    const empSel = document.getElementById('employee_id_link');
    const form = document.getElementById('aForm');

    function fillRoles(parentRoleId, selectRole){
        roleSel.innerHTML = '<option value="">-- اختر الدور --</option>';
        const pr = String(parentRoleId||'');
        CHILD_ROLES.filter(r => String(r.parent_role_id) === pr).forEach(r => {
            const o = document.createElement('option'); o.value = r.id; o.textContent = r.name;
            if (selectRole && String(selectRole) === String(r.id)) o.selected = true;
            roleSel.appendChild(o);
        });
        if (roleSel.options.length === 1) roleSel.innerHTML = '<option value="">لا أدوار تابعة لهذا المدير</option>';
    }
    parentSel.addEventListener('change', function(){
        const opt = this.options[this.selectedIndex];
        fillRoles(opt ? opt.dataset.role : '', null);
    });

    function refreshEmp(curUid){
        if(!empSel) return; curUid = String(curUid||0);
        Array.from(empSel.options).forEach(o=>{ if(!o.value) return; const l=String(o.dataset.linkedUid||'0'); o.disabled = (l!=='0' && l!==curUid); });
    }
    if (empSel) empSel.addEventListener('change', function(){
        const o=this.options[this.selectedIndex];
        if(o&&o.value){ if(o.dataset.name) document.getElementById('name').value=o.dataset.name; if(o.dataset.phone&&o.dataset.phone.trim()!=='') document.getElementById('phone').value=o.dataset.phone; }
    });

    document.addEventListener('DOMContentLoaded', function(){
        if (window.jQuery) $('#aTable').DataTable({ responsive:true, language:{ url:'/ems/assets/i18n/datatables/ar.json' } });
        const tgl = document.getElementById('toggleForm');
        if (tgl) tgl.addEventListener('click', function(){ resetForm(); form.classList.toggle('allforms-visible'); });

        $(document).on('click', '.action-btn.edit', function(){
            const d = this.dataset;
            document.getElementById('uid').value = d.id;
            document.getElementById('name').value = d.name;
            document.getElementById('username').value = d.username;
            document.getElementById('phone').value = d.phone;
            parentSel.value = d.parent;
            const o = parentSel.options[parentSel.selectedIndex];
            fillRoles(o ? o.dataset.role : '', d.role);
            if (empSel) { refreshEmp(d.id); empSel.value = (d.employee && parseInt(d.employee,10)>0)? String(d.employee):''; }
            document.getElementById('password').value = '';
            document.getElementById('pwReq').classList.add('pu-hidden');
            document.getElementById('pwHint').classList.remove('pu-hidden');
            document.getElementById('password').removeAttribute('required');
            document.getElementById('formTitle').textContent = 'تعديل المعاون';
            document.getElementById('submitTxt').textContent = 'تحديث المعاون';
            form.classList.add('allforms-visible');
            $('html,body').animate({ scrollTop: $(form).offset().top - 100 }, 400);
        });
    });

    window.resetForm = function(){
        form.reset(); document.getElementById('uid').value = 0;
        roleSel.innerHTML = '<option value="">-- اختر المدير أولاً --</option>';
        document.getElementById('formTitle').textContent = 'إضافة معاون';
        document.getElementById('submitTxt').textContent = 'حفظ المعاون';
        document.getElementById('pwReq').classList.remove('pu-hidden');
        document.getElementById('pwHint').classList.add('pu-hidden');
        document.getElementById('password').setAttribute('required','required');
        if (empSel) { empSel.value=''; refreshEmp(0); }
    };
})();
</script>
</body></html>
