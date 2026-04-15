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
$module_info   = $module_result ? $module_result->fetch_assoc() : null;
$module_id     = $module_info ? $module_info['id'] : null;

// إذا لم يوجد سجل خاص بهذا الدور، افترض جميع الصلاحيات (للتوافق مع الأدوار القديمة)
if (!$module_id) {
    $can_view = $can_add = $can_edit = $can_delete = true;
} else {
    $can_view   = false;
    $can_add    = false;
    $can_edit   = false;
    $can_delete = false;

    $perms      = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
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
    
    if (mysqli_num_rows($verifyResult) > 0) {
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
    $userId   = intval($_POST['user_id']);
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = !empty($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : '';
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role     = mysqli_real_escape_string($conn, $_POST['role']);
    $userid   = $_SESSION['user']['id'];
    
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
    
    if (mysqli_num_rows($verifyResult) === 0) {
        header("Location: project_users.php?msg=ليس+لديك+صلاحية+لتعديل+هذا+المستخدم+❌");
        exit;
    }
    
    // تحقق من تكرار اسم المستخدم (ما عدا المستخدم الحالي)
    $check_query = "SELECT id FROM users WHERE username = '$username' AND id != $userId AND COALESCE(is_deleted,0)=0";
    if ($users_has_company_id) {
        $check_query .= " AND company_id = $current_company_id";
    }
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=اسم+المستخدم+موجود+مسبقاً+❌");
        exit;
    }

    // تحديث المستخدم
    $passwordUpdate = '';
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $passwordUpdate = ", password = '$hashedPassword'";
    }
    
    $updateSQL = "UPDATE users SET name = '$name', username = '$username', phone = '$phone', role = '$role', updated_at = NOW() $passwordUpdate";
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
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password  = mysqli_real_escape_string($conn, $_POST['password']);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $phone     = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $project   = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    $parent_id = intval($_SESSION['user']['id']);

    // تحقق من تكرار اسم المستخدم
    $check_query = "SELECT id FROM users WHERE username = '$username' AND COALESCE(is_deleted,0)=0";
    if ($users_has_company_id) {
        $check_query .= " AND company_id = $current_company_id";
    }
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
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
    $sql = "INSERT INTO users ($insert_columns) VALUES ($insert_values)";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: project_users.php?msg=تم+إضافة+المستخدم+بنجاح+✅");
        exit;
    } else {
        header("Location: project_users.php?msg=حدث+خطأ+أثناء+الإضافة+❌");
        exit;
    }
}

$page_title = "إيكوبيشن | المشرفون";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users-cog"></i></div>
            إدارة مشرفين <?php echo !empty($roleName) ? '- ' . $roleName : ''; ?>
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة مشرف جديد
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): 
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مستخدم -->
    <form id="projectForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <input type="hidden" id="action"  name="action"  value="add">
        <input type="hidden" id="user_id" name="user_id" value="">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة مستخدم جديد</span></h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user"></i> الاسم ثلاثي *</label>
                        <input type="text" name="name" id="name" placeholder="أدخل الاسم ثلاثي" value="محمد سيد" required />
                    </div>
                    <div>
                        <label><i class="fas fa-at"></i> اسم المستخدم *</label>
                        <input type="text" name="username" id="username" placeholder="أدخل اسم المستخدم" value=" medo " required />
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> كلمة المرور <span id="passwordRequired">*</span></label>
                        <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" value="12345678"/>
                        <small id="passwordHint" style="color: #999; display:none;">اتركه فارغاً للاحتفاظ بكلمة المرور الحالية</small>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف *</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" required value="09144760109" />
                    </div>
                    <div>
                        <label><i class="fas fa-shield-alt"></i> الصلاحية / الدور *</label>
                        <select name="role" id="role" required>
                            <option value="">-- اختر الصلاحية --</option>
                            <?php 
                            // جلب الأدوار التابعة للدور الحالي من قاعدة البيانات
                            $currentRole = $_SESSION['user']['role'];
                            $rolesQuery  = "SELECT id, name FROM roles 
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
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ المستخدم</span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('projectForm').style.display='none';">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة المستخدمين</h5>
        </div>
        <div class="card-body">
            <table id="usersTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>رقم الهاتف</th>
                        <th>الصلاحية</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المستخدمين التابعين للمدير الحالي
                    // 1. المستخدمون الذين parent_id = المستخدم الحالي
                    // 2. المستخدمون من الأدوار التابعة للدور الحالي
                    $userid      = $_SESSION['user']['id'];
                    $currentRole = $_SESSION['user']['role'];

                    $roles = [
                        "6" => "ðŸ“ مدخل ساعات عمل",
                        "7" => "✔ مراجع ساعات مورد",
                        "8" => "✔ مراجع ساعات مشغل",
                        "9" => "ðŸ”§ مراجع الأعطال",
                    ];

                          $query = "SELECT DISTINCT u.id, u.name, u.username, u.phone, u.role, u.created_at
                             FROM users u
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

                    while ($row = mysqli_fetch_assoc($result)) {
                        $roleText    = isset($roles[$row['role']]) 
                                       ? $roles[$row['role']] 
                                       : '<span style="color: #999;">غير معروف</span>';
                        $createdDate = date('Y-m-d', strtotime($row['created_at']));

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td><code style=\"background: #f0f2f8; padding: 4px 8px; border-radius: 6px;\">" . htmlspecialchars($row['username']) . "</code></td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . $roleText . "</td>";
                        echo "<td>" . $createdDate . "</td>";

                        $action_btns = "<td><div class='action-btns'>";
                        if ($can_edit) {
                            $action_btns .= "<a href='javascript:void(0)' 
                                       class='action-btn edit' 
                                       onclick='editUser({$row['id']}, \"" . htmlspecialchars($row['name'],     ENT_QUOTES, 'UTF-8') . "\", \"" 
                                                                           . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "\", \"" 
                                                                           . htmlspecialchars($row['phone'],    ENT_QUOTES, 'UTF-8') . "\", {$row['role']})'
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
                    { extend: 'copy',  text: 'نسخ'         },
                    { extend: 'excel', text: 'تصدير Excel'  },
                    { extend: 'csv',   text: 'تصدير CSV'    },
                    { extend: 'pdf',   text: 'تصدير PDF'    },
                    { extend: 'print', text: 'طباعة'        }
                ],
                "language": {
                    "url": "https:/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm   = document.getElementById('projectForm');

        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                // تنظيف الحقول عند الإضافة
                if (projectForm.style.display === "block") {
                    resetForm();
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                }
            });
        }

        // دالة تعديل المستخدم — تملأ الفورم ببيانات المستخدم المحدد
        window.editUser = function(userId, name, username, phone, role) {
            document.getElementById('user_id').value  = userId;
            document.getElementById('name').value     = name;
            document.getElementById('username').value = username;
            document.getElementById('phone').value    = phone;
            document.getElementById('role').value     = role;
            document.getElementById('password').value = '';
            
            // تغيير نص الفورم والزر ليدل على التعديل
            document.getElementById('formTitle').textContent     = 'تعديل المستخدم';
            document.getElementById('submitBtnText').textContent = 'تحديث المستخدم';
            document.getElementById('action').value              = 'edit';

            // كلمة المرور اختيارية عند التعديل
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('passwordHint').style.display     = 'block';
            document.getElementById('password').removeAttribute('required');
            
            // عرض الفورم والتمرير إليه
            projectForm.style.display = 'block';
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
        };

        // دالة إعادة تعيين الفورم لحالة الإضافة
        window.resetForm = function() {
            document.getElementById('projectForm').reset();
            document.getElementById('user_id').value             = '';
            document.getElementById('action').value              = 'add';
            document.getElementById('formTitle').textContent     = 'إضافة مستخدم جديد';
            document.getElementById('submitBtnText').textContent = 'حفظ المستخدم';
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('passwordHint').style.display     = 'none';
            document.getElementById('password').setAttribute('required', 'required');
        };

    })();
</script>

</body>
</html>

