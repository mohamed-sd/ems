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
$users_has_status = db_table_has_column($conn, 'users', 'status');
$users_has_employee_id = db_table_has_column($conn, 'users', 'employee_id'); // رابط الحساب↔الموظف (الخيار ب)

if (!$users_has_is_deleted) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
}
if (!$users_has_deleted_at) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL");
}
if (!$users_has_deleted_by) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN deleted_by INT NULL");
}
if (!$users_has_status) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
}

$users_has_is_deleted = db_table_has_column($conn, 'users', 'is_deleted');
$users_has_deleted_at = db_table_has_column($conn, 'users', 'deleted_at');
$users_has_status = db_table_has_column($conn, 'users', 'status');
$users_not_deleted_sql = $users_has_is_deleted ? "(COALESCE(is_deleted,0)=0)" : "1=1";

if ($users_has_company_id && $current_company_id <= 0) {
    echo "<script>alert('❌ الحساب غير مرتبط بشركة'); window.location.href='../login.php';</script>";
    exit;
}

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

    $project_scope = $users_has_company_id ? " AND p.company_id = $current_company_id" : "";
    $project_not_deleted = db_table_has_column($conn, 'project', 'is_deleted') ? " AND COALESCE(p.is_deleted,0)=0" : "";

    $project_check_sql = "SELECT p.id FROM project p WHERE p.id = $project_id AND p.status = '1' $project_not_deleted $project_scope LIMIT 1";
    $project_check_res = mysqli_query($conn, $project_check_sql);
    if (!$project_check_res || mysqli_num_rows($project_check_res) === 0) {
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

    $contracts_sql = "SELECT
            c.id,
            c.contract_signing_date,
            c.actual_start,
            c.forecasted_contracted_hours
        FROM contracts c
        WHERE c.project_id = $project_id
          AND $contract_active_sql
        ORDER BY c.actual_start DESC, c.id DESC";

    $contracts_result = mysqli_query($conn, $contracts_sql);
    if (!$contracts_result) {
        echo json_encode(['success' => false, 'message' => 'خطأ في جلب العقود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contracts = [];
    while ($row = mysqli_fetch_assoc($contracts_result)) {
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

// ════════════════════════════════════════════════════════════════════════════
// 🔗 الموظفون المتاحون للربط (الخيار ب: users.employee_id → employees.id)
// نحمّل موظفي الشركة مع معرّف الحساب المرتبط (إن وُجد) للتعبئة والتصفية في الواجهة.
// ════════════════════════════════════════════════════════════════════════════
$employees_for_link = array();
if ($users_has_employee_id) {
    $emp_has_company = db_table_has_column($conn, 'employees', 'company_id');
    $emp_scope = ($emp_has_company && $current_company_id > 0) ? " WHERE e.company_id = $current_company_id" : "";
    $emp_sql = "SELECT e.id, e.name, e.phone, e.email,
                       (SELECT u.id FROM users u WHERE u.employee_id = e.id AND $users_not_deleted_sql LIMIT 1) AS linked_uid
                FROM employees e $emp_scope ORDER BY e.name ASC";
    $emp_res = mysqli_query($conn, $emp_sql);
    if ($emp_res) {
        while ($er = mysqli_fetch_assoc($emp_res)) {
            $employees_for_link[] = array(
                'id'         => intval($er['id']),
                'name'       => $er['name'],
                'phone'      => $er['phone'],
                'email'      => $er['email'],
                'linked_uid' => ($er['linked_uid'] !== null) ? intval($er['linked_uid']) : 0,
            );
        }
    }
}

// خريطة (معرّف الموظف ⇐ الاسم) لعرض «الموظف المرتبط» في قائمة المستخدمين.
$emp_name_by_id = array();
foreach ($employees_for_link as $e) {
    $emp_name_by_id[$e['id']] = $e['name'];
}

// تهيئة مسبقة عند القدوم من بطاقة الموظف (users.php?employee_id=N):
//   إن كان للموظف حساب مرتبط ⇒ نفتح الحساب في وضع التعديل، وإلا نفتح نموذج إضافة مهيّأً.
$prefill_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$prefill_edit_uid = 0;
if ($prefill_employee_id > 0 && $users_has_employee_id) {
    $pf_res = mysqli_query($conn, "SELECT id FROM users WHERE employee_id = $prefill_employee_id AND $users_not_deleted_sql LIMIT 1");
    if ($pf_res && ($pf_row = mysqli_fetch_assoc($pf_res))) {
        $prefill_edit_uid = intval($pf_row['id']);
    }
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
    $status_input = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    $status = (in_array($status_input, array('active', '1', 1, 'نشط', 'true'), true)) ? 'active' : 'inactive';
    $selected_role_scope = 'gloable';
    $role_lookup_sql = "SELECT role_scope FROM roles WHERE id='" . mysqli_real_escape_string($conn, $role) . "' LIMIT 1";
    $role_lookup_result = mysqli_query($conn, $role_lookup_sql);
    if ($role_lookup_result && mysqli_num_rows($role_lookup_result) > 0) {
        $role_lookup_row = mysqli_fetch_assoc($role_lookup_result);
        if (isset($role_lookup_row['role_scope']) && ($role_lookup_row['role_scope'] === 'mine' || $role_lookup_row['role_scope'] === 'project')) {
            $selected_role_scope = 'project';
        }
    }

    $requires_project_context = ($selected_role_scope === 'project');
    $project = ($requires_project_context && !empty($_POST['project_id'])) ? intval($_POST['project_id']) : 0;
    $contract = ($requires_project_context && !empty($_POST['contract_id'])) ? intval($_POST['contract_id']) : 0;
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    // 🔗 ربط الحساب بموظف (اختياري): تحقّق من الملكية والتفرّد قبل الحفظ.
    $employee_link_id = ($users_has_employee_id && !empty($_POST['employee_id'])) ? intval($_POST['employee_id']) : 0;
    $employee_link_valid = true;
    if ($employee_link_id > 0) {
        // (1) الموظف يجب أن يتبع شركة المستخدم الحالي
        $emp_company_cond = (db_table_has_column($conn, 'employees', 'company_id') && $current_company_id > 0)
            ? " AND company_id = $current_company_id" : "";
        $emp_chk = mysqli_query($conn, "SELECT id FROM employees WHERE id = $employee_link_id $emp_company_cond LIMIT 1");
        if (!$emp_chk || mysqli_num_rows($emp_chk) === 0) {
            $employee_link_valid = false;
        } else {
            // (2) الموظف غير مرتبط بحسابٍ آخر (يستثني السجل الحالي عند التعديل)
            $excl = $uid > 0 ? " AND id != $uid" : "";
            $link_chk = mysqli_query($conn, "SELECT id FROM users WHERE employee_id = $employee_link_id $excl AND $users_not_deleted_sql LIMIT 1");
            if ($link_chk && mysqli_num_rows($link_chk) > 0) {
                $employee_link_valid = false;
            }
        }
    }
    // جملة العمود الجزئية لإعادة الاستخدام في INSERT/UPDATE
    $sql_employee = $users_has_employee_id
        ? ", employee_id=" . ($employee_link_id > 0 ? "'$employee_link_id'" : "NULL")
        : "";

    if ($users_has_employee_id && $employee_link_id <= 0) {
        echo "<script>alert('⚠️ يجب إسناد موظف لهذا الحساب — لا يوجد حساب يعمل بلا موظف مُسنَد له');</script>";
    } elseif ($requires_project_context && ($project <= 0 || $contract <= 0)) {
        echo "<script>alert('⚠️ هذا الدور مرتبط بمشروع محدد، يرجى اختيار المشروع والعقد');</script>";
    } elseif ($employee_link_id > 0 && !$employee_link_valid) {
        echo "<script>alert('⚠️ الموظف المحدد غير صالح أو مرتبط بحساب آخر');</script>";
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

                $sql_status = $users_has_status ? ", status='$status'" : "";

                $sql = "UPDATE users
                    SET name='$name', username='$username', phone='$phone', role='$role', project_id='$project', contract_id='$contract', updated_at=NOW() $sql_status $sql_pass $sql_employee
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

                $insert_columns = "name, username, password, phone, role, project_id, contract_id, parent_id, created_at, updated_at";
                $insert_values = "'$name', '$username', '$hashedPass', '$phone', '$role', '$project', '$contract', '0', NOW(), NOW()";

                if ($users_has_status) {
                    $insert_columns .= ", status";
                    $insert_values .= ", '$status'";
                }

                if ($users_has_company_id && $current_company_id > 0) {
                    $insert_columns .= ", company_id";
                    $insert_values .= ", '$current_company_id'";
                }

                if ($users_has_employee_id) {
                    $insert_columns .= ", employee_id";
                    $insert_values .= $employee_link_id > 0 ? ", '$employee_link_id'" : ", NULL";
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
                        <label><i class="fas fa-user"></i> الاسم الثلاثي</label>
                        <input type="text" name="name" id="name" placeholder="الاسم الثلاثي" value="مستخدم" required />
                    </div>
                    <div>
                        <label><i class="fas fa-id-badge"></i> اسم المستخدم</label>
                        <input type="text" name="username" id="username" placeholder="اسم المستخدم (الحد الأدنى 3 أحرف)"
                            value="username" required autocomplete="off" />
                        <small id="usernameFeedback" class="pu-username-feedback"></small>
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> كلمة المرور</label>
                        <input type="password" name="password" id="password" placeholder="كلمة المرور"
                            value="12345678" />
                        <small class="text-muted"><i class="fas fa-info-circle"></i> اتركه فارغاً إذا لا تريد تغييره
                            عند التعديل</small>
                    </div>
                    <div>
                        <label><i class="fas fa-user-shield"></i> الدور / الصلاحية</label>
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
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required
                            value="09209303903" />
                    </div>

                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة المستخدم</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="active" selected>✅ نشط</option>
                            <option value="inactive">❌ غير نشط</option>
                        </select>
                    </div>

                    <?php if ($users_has_employee_id): ?>
                    <div>
                        <label><i class="fas fa-id-card-alt"></i> الموظف المُسنَد <span class="pu-required-star">*</span></label>
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

                    <div id="projectDiv" class="pu-hidden">
                        <label><i class="fas fa-project-diagram"></i> المشروع <span
                                class="pu-required-star">*</span></label>
                        <select id="project_id" name="project_id" class="form-control">
                            <option value="">-- اختر المشروع --</option>
                            <?php
                            $project_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
                            $project_not_deleted = db_table_has_column($conn, 'project', 'is_deleted') ? " AND COALESCE(is_deleted,0)=0" : "";
                            $sql = "SELECT id, name, project_code FROM project WHERE status = '1' $project_not_deleted $project_scope ORDER BY name ASC";
                            $result = mysqli_query($conn, $sql);
                            if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " ({$row['project_code']})</option>";
                            }
                            }
                            ?>
                        </select>
                    </div>

                    <div id="contractDiv" class="pu-hidden">
                        <label><i class="fas fa-file-contract"></i> العقد <span
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
                    <button type="submit" class="btn btn-success">
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
                <label><i class="fa fa-user-shield"></i> الدور</label>
                <select id="filterRole" class="form-control">
                    <option value="">-- كل الأدوار --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-toggle-on"></i> الحالة</label>
                <select id="filterStatus" class="form-control">
                    <option value="">-- كل الحالات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-id-card-alt"></i> الارتباط بموظف</label>
                <select id="filterLinked" class="form-control">
                    <option value="">-- الكل --</option>
                    <option value="linked">مرتبط بموظف</option>
                    <option value="unlinked">غير مرتبط</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" id="usersFilterApply" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" id="usersFilterReset" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="users-table-actions-row">
                <div class="users-table-note">عرض وإدارة المستخدمين مع التصدير السريع من الشريط العلوي</div>
            </div>
            <div class="table-container">
                <table id="projectsTable" class="display nowrap pu-table users-table-nowrap" style="width:100%;">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th>#</th>
                            <th>الاسم </th>
                            <th>اسم المستخدم </th>
                            <th>كلمه المرور </th>
                            <th>الدور </th>
                            <th>الموظف المرتبط</th>
                            <th>رقم الهاتف</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $list_scope = $users_has_company_id ? " AND company_id = $current_company_id" : "";
                        $select_status_column = $users_has_status ? "status" : "'active' AS status";
                        $select_employee_column = $users_has_employee_id ? "employee_id" : "NULL AS employee_id";
                        $query = "SELECT id, name, username, password, phone, role, project_id, contract_id, $select_status_column, $select_employee_column FROM users WHERE parent_id='0' AND role!='-1' AND $users_not_deleted_sql $list_scope ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        $i = 1;
                        if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $project_id = $row['project_id'];
                            $contract_id = $row['contract_id'];

                            $project_info = "";

                            $row_role_key = isset($row['role']) ? (string) $row['role'] : '';
                            $row_role_scope = isset($roles_scope[$row_role_key]) ? $roles_scope[$row_role_key] : 'gloable';

                            if ($row_role_scope === 'mine' || $row_role_scope === 'project') {
                                // جلب اسم المشروع
                                if ($project_id > 0) {
                                    $select_project = mysqli_query($conn, "SELECT name, project_code FROM `project` WHERE `id` = $project_id");
                                    if ($select_project && ($project_row = mysqli_fetch_array($select_project))) {
                                        $project_info = "<div class='pu-project-meta'><div class='pu-project-meta-item'><i class='fas fa-project-diagram pu-meta-icon'></i> " . htmlspecialchars($project_row['name'], ENT_QUOTES, 'UTF-8') . " (" . $project_row['project_code'] . ")";
                                    }
                                }

                                // جلب تاريخ العقد
                                if ($contract_id > 0) {
                                    $select_contract = mysqli_query($conn, "SELECT contract_signing_date FROM `contracts` WHERE `id` = $contract_id");
                                    if ($select_contract && ($contract_row = mysqli_fetch_array($select_contract))) {
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
                                                                     title='تعديل'><i class='fas fa-edit'></i></a>
                                                                <a href='?delete={$row['id']}' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>
                                                                </div>
                                                            </td>";
                            echo "<td><strong>" . $i++ . "</strong></td>";
                            echo "<td><a class='client-name-link' href='user_profile.php?id=" . intval($row['id']) . "'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</a>" . $project_info . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                            echo "<td><span class='password-cell pu-password-cell'>••••••••</span></td>";
                            echo "<td><span class='role-badge role-" . $row['role'] . "'>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</span></td>";
                            $linked_emp_id = isset($row['employee_id']) ? intval($row['employee_id']) : 0;
                            if ($linked_emp_id > 0 && isset($emp_name_by_id[$linked_emp_id])) {
                                echo "<td><a class='client-name-link' href='../Employees/employee_profile.php?id=" . $linked_emp_id . "'><i class='fas fa-id-card-alt'></i> " . htmlspecialchars($emp_name_by_id[$linked_emp_id], ENT_QUOTES, 'UTF-8') . "</a></td>";
                            } else {
                                echo "<td><span style='color:#999;'>— غير مرتبط —</span></td>";
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

<style>
    /* قاعدة ems-forms.css ':is(.allforms,.ems-form) .form-grid > div { display:block !important }'
       تتغلّب على '.pu-hidden' بسبب خصوصية أعلى، فتُظهر حقلي المشروع والعقد لكل الأدوار.
       نعيد الإخفاء بخصوصية أعلى ليظهرا فقط للأدوار ذات role_scope='mine' (مدير الموقع / حركة وتشغيل). */
    .project-users-main .allforms .form-grid > div.pu-hidden {
        display: none !important;
    }

    .project-users-main .table-container {
        overflow-x: auto;
    }

    #projectsTable.users-table-nowrap,
    #projectsTable.users-table-nowrap th,
    #projectsTable.users-table-nowrap td {
        white-space: nowrap;
    }

    #projectsTable .action-btns {
        flex-wrap: nowrap;
        white-space: nowrap;
    }
</style>

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
        ============================== */
        const usersTable = $('#projectsTable').DataTable({
            dom: 'Bfrtip',
            scrollX: true,
            autoWidth: false,
            // الفلاتر الخارجية تُدار يدوياً؛ نُعطّل حفظ الحالة لتفادي استعادة بحثٍ محفوظ يُخفي الصفوف
            stateSave: false,
            buttons: [
                { extend: 'copy', text: '<i class="fas fa-copy"></i> نسخ', className: 'users-table-action' },
                { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'users-table-action' },
                { extend: 'csv', text: '<i class="fas fa-file-csv"></i> CSV', className: 'users-table-action' },
                { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'users-table-action' },
                { extend: 'print', text: '<i class="fas fa-print"></i> طباعة', className: 'users-table-action' }
            ],
            language: {
                url: "/ems/assets/i18n/datatables/ar.json"
            }
        });

        usersTable.buttons().container().appendTo('#usersExportButtons');

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
