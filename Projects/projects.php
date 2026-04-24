<?php
// تحميل الإعدادات والأمان
include '../config.php';
require_once '../includes/approval_workflow.php';
require_once '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('projects_fix_mojibake_output')) {
    function projects_fix_mojibake_output($buffer)
    {
        $pattern = '/[\x{00C2}\x{00C3}\x{00D8}\x{00D9}\x{00E2}\x{00F0}][^\s<>{}\[\]"\'=]{0,24}/u';
        $fixed = preg_replace_callback($pattern, function ($m) {
            if (function_exists('mb_convert_encoding')) {
                $decoded = @mb_convert_encoding($m[0], 'UTF-8', 'Windows-1252');
                if ($decoded !== false && $decoded !== '') {
                    return $decoded;
                }
            }

            return $m[0];
        }, $buffer);

        return is_string($fixed) ? $fixed : $buffer;
    }
}

ob_start('projects_fix_mojibake_output');

// التحقق من تسجيل الدخول
require_login();

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$clients_has_company_id = db_table_has_column($conn, 'clients', 'company_id');
$project_has_is_deleted = db_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = db_table_has_column($conn, 'project', 'deleted_at');
$project_has_deleted_by = db_table_has_column($conn, 'project', 'deleted_by');
$clients_has_is_deleted = db_table_has_column($conn, 'clients', 'is_deleted');
$clients_has_deleted_at = db_table_has_column($conn, 'clients', 'deleted_at');
$mines_has_is_deleted = db_table_has_column($conn, 'mines', 'is_deleted');
$mines_has_deleted_at = db_table_has_column($conn, 'mines', 'deleted_at');

if (!$project_has_is_deleted || !$project_has_deleted_at || !$project_has_deleted_by) {
    $alter_parts = array();
    if (!$project_has_is_deleted) {
        $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!$project_has_deleted_at) {
        $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    }
    if (!$project_has_deleted_by) {
        $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
    }
    if (!empty($alter_parts)) {
        @mysqli_query($conn, "ALTER TABLE project " . implode(', ', $alter_parts));
    }

    $project_has_is_deleted = db_table_has_column($conn, 'project', 'is_deleted');
    $project_has_deleted_at = db_table_has_column($conn, 'project', 'deleted_at');
    $project_has_deleted_by = db_table_has_column($conn, 'project', 'deleted_by');
}

$project_not_deleted_sql = '1=1';
if ($project_has_is_deleted) {
    $project_not_deleted_sql = 'op.is_deleted = 0';
} elseif ($project_has_deleted_at) {
    $project_not_deleted_sql = 'op.deleted_at IS NULL';
}

$project_not_deleted_plain_sql = '1=1';
if ($project_has_is_deleted) {
    $project_not_deleted_plain_sql = 'is_deleted = 0';
} elseif ($project_has_deleted_at) {
    $project_not_deleted_plain_sql = 'deleted_at IS NULL';
}

$client_not_deleted_sql = '1=1';
if ($clients_has_is_deleted) {
    $client_not_deleted_sql = 'c.is_deleted = 0';
} elseif ($clients_has_deleted_at) {
    $client_not_deleted_sql = 'c.deleted_at IS NULL';
}

$mines_not_deleted_sql = '1=1';
if ($mines_has_is_deleted) {
    $mines_not_deleted_sql = 'm.is_deleted = 0';
} elseif ($mines_has_deleted_at) {
    $mines_not_deleted_sql = 'm.deleted_at IS NULL';
}

$project_scope_sql = $project_has_company_id
    ? "op.company_id = $company_id"
    : "(
        EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = op.created_by AND scope_u.company_id = $company_id)
        OR EXISTS (
            SELECT 1
            FROM clients scope_c
            INNER JOIN users scope_uc ON scope_uc.id = scope_c.created_by
            WHERE scope_c.id = op.$project_client_column AND scope_uc.company_id = $company_id
        )
    )";

$project_scope_plain_sql = $project_has_company_id
    ? "company_id = $company_id"
    : "(
        EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = project.created_by AND scope_u.company_id = $company_id)
        OR EXISTS (
            SELECT 1
            FROM clients scope_c
            INNER JOIN users scope_uc ON scope_uc.id = scope_c.created_by
            WHERE scope_c.id = project.$project_client_column AND scope_uc.company_id = $company_id
        )
    )";

$client_scope_sql = $clients_has_company_id
    ? "c.company_id = $company_id"
    : "EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = c.created_by AND scope_u.company_id = $company_id)";
$client_scope_sql .= " AND $client_not_deleted_sql";

function projects_redirect_with_msg($msg)
{
    header('Location: projects.php?msg=' . urlencode($msg));
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ”’ التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Projects/projects.php');
if (!isset($page_permissions['can_view']) || !$page_permissions['can_view']) {
    $legacy_page_permissions = check_page_permissions($conn, 'Projects/oprationprojects.php');
    if (isset($legacy_page_permissions['can_view']) && $legacy_page_permissions['can_view']) {
        $page_permissions = $legacy_page_permissions;
    }
}
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض المشاريع ❌'));
    exit();
}

// معالجة حذف المشروع
if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (!$can_delete) {
        projects_redirect_with_msg('لا توجد صلاحية حذف المشاريع ❌');
    }

    // التحقق من CSRF Token
    if (!verify_csrf_token($_GET['csrf_token'])) {
        header('Location: projects.php?error=' . urlencode('خطأ أمني'));
        exit();
    }
    
    $delete_id = intval($_GET['delete_id']);

    $old_project = null;
    $old_res = mysqli_query($conn, "SELECT op.* FROM project op WHERE op.id = $delete_id AND $project_scope_sql AND $project_not_deleted_sql LIMIT 1");
    if ($old_res) {
        $old_project = mysqli_fetch_assoc($old_res);
    }

    if (!$old_project) {
        projects_redirect_with_msg('المشروع غير موجود أو لا يتبع لشركتك ❌');
    }

    if (!$project_has_is_deleted && !$project_has_deleted_at) {
        projects_redirect_with_msg('تعذر تفعيل الحذف الناعم للمشاريع حالياً ❌');
    }

    $delete_set = array("status = '0'");
    if ($project_has_is_deleted) {
        $delete_set[] = "is_deleted = 1";
    }
    if ($project_has_deleted_at) {
        $delete_set[] = "deleted_at = NOW()";
    }
    if ($project_has_deleted_by) {
        $deleted_by = approval_get_user_id();
        $delete_set[] = "deleted_by = " . intval($deleted_by);
    }

    $delete_query = "UPDATE project SET " . implode(', ', $delete_set) . " WHERE id = $delete_id AND $project_scope_plain_sql AND $project_not_deleted_plain_sql";
    if (mysqli_query($conn, $delete_query)) {
        log_security_event('PROJECT_SOFT_DELETED', "Soft deleted project ID: $delete_id");
        projects_redirect_with_msg('تم حذف المشروع (حذف ناعم) بنجاح ✅');
    }

    projects_redirect_with_msg('حدث خطأ أثناء حذف المشروع ❌');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project_name'])) {
    // التحقق من CSRF Token
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        die('خطأ في التحقق من الأمان - CSRF Token غير صحيح');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        projects_redirect_with_msg('لا توجد صلاحية تعديل المشاريع ❌');
    } elseif (!$is_editing && !$can_add) {
        projects_redirect_with_msg('لا توجد صلاحية إضافة مشاريع جديدة ❌');
    }

    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id <= 0 && !empty($_POST['company_client_id'])) {
        $client_id = intval($_POST['company_client_id']);
    }

    // جلب البيانات المدخولة بشكل آمن
    $name = sanitize_input($_POST['project_name']);
    $project_code = sanitize_input(isset($_POST['project_code']) ? $_POST['project_code'] : '');
    $category = sanitize_input(isset($_POST['category']) ? $_POST['category'] : '');
    $sub_sector = sanitize_input(isset($_POST['sub_sector']) ? $_POST['sub_sector'] : '');
    $state = sanitize_input(isset($_POST['state']) ? $_POST['state'] : '');
    $region = sanitize_input(isset($_POST['region']) ? $_POST['region'] : '');
    $nearest_market = sanitize_input(isset($_POST['nearest_market']) ? $_POST['nearest_market'] : '');
    $latitude = sanitize_input(isset($_POST['latitude']) ? $_POST['latitude'] : '');
    $longitude = sanitize_input(isset($_POST['longitude']) ? $_POST['longitude'] : '');
    $location = sanitize_input(isset($_POST['location']) ? $_POST['location'] : '');
    $status = sanitize_input($_POST['status']);
    $total = floatval(isset($_POST['total']) ? $_POST['total'] : 0);

    // جلب اسم العميل إذا تم اختياره
    $client = '';
    if ($client_id > 0) {
        $stmt = query_safe(
            "SELECT c.client_name FROM clients c WHERE c.id = ? AND $client_scope_sql",
            [$client_id],
            'i'
        );
        if ($stmt) {
            $stmt_result = mysqli_stmt_get_result($stmt);
            if ($client_row = mysqli_fetch_assoc($stmt_result)) {
                $client = sanitize_input($client_row['client_name']);
            }
        }

        if ($client === '') {
            projects_redirect_with_msg('العميل المحدد لا يتبع لشركتك ❌');
        }
    } else {
        $client = sanitize_input(isset($_POST['client_name']) ? $_POST['client_name'] : '');
    }

    $created_by = intval(isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0);
    if ($created_by <= 0) {
        projects_redirect_with_msg('هوية المستخدم غير صالحة ❌');
    }

    if ($id > 0) {
        $old_project = null;
        $old_res = mysqli_query($conn, "SELECT op.* FROM project op WHERE op.id = $id AND $project_scope_sql AND $project_not_deleted_sql LIMIT 1");
        if ($old_res) {
            $old_project = mysqli_fetch_assoc($old_res);
        }

        if (!$old_project) {
            projects_redirect_with_msg('المشروع غير موجود أو لا يتبع لشركتك ❌');
        }

        $update_sql = "UPDATE project SET
            $project_client_column = ?,
            name = ?,
            client = ?,
            location = ?,
            project_code = ?,
            category = ?,
            sub_sector = ?,
            state = ?,
            region = ?,
            nearest_market = ?,
            latitude = ?,
            longitude = ?,
            total = ?,
            status = ?,
            updated_at = NOW()";

        $update_values = array(
            $client_id,
            $name,
            $client,
            $location,
            $project_code,
            $category,
            $sub_sector,
            $state,
            $region,
            $nearest_market,
            $latitude,
            $longitude,
            $total,
            $status
        );
        $update_types = 'isssssssssssds';

        if ($project_has_company_id) {
            $update_sql .= ", company_id = ?";
            $update_values[] = $company_id;
            $update_types .= 'i';
        }

        $update_sql .= " WHERE id = ? AND $project_scope_plain_sql AND $project_not_deleted_plain_sql";
        $update_values[] = $id;
        $update_types .= 'i';

        $stmt = mysqli_prepare($conn, $update_sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $update_types, ...$update_values);
            $executed = mysqli_stmt_execute($stmt);
        } else {
            $executed = false;
        }

        if ($stmt && $executed) {
            log_security_event('PROJECT_UPDATED', "Updated project: $name (ID: $id)");
            projects_redirect_with_msg('تم تعديل المشروع بنجاح ✅');
        }

        $error_message = $stmt ? mysqli_stmt_error($stmt) : mysqli_error($conn);
        projects_redirect_with_msg('حدث خطأ أثناء تعديل المشروع ❌' . (!empty($error_message) ? ' - ' . $error_message : ''));
    } else {
        // إضافة مع Prepared Statement
        $insert_columns = "$project_client_column, name, client, location, project_code, category, sub_sector, state, region, nearest_market, latitude, longitude, total, status, created_by";
        $insert_values = array($client_id, $name, $client, $location, $project_code, $category, $sub_sector, $state, $region, $nearest_market, $latitude, $longitude, $total, $status, $created_by);

        if ($project_has_company_id) {
            $insert_columns .= ", company_id";
            $insert_values[] = $company_id;
        }

        $placeholders = implode(', ', array_fill(0, count($insert_values), '?'));
        $insert_sql = "INSERT INTO project ($insert_columns, create_at) VALUES ($placeholders, NOW())";

        $stmt = query_safe($insert_sql, $insert_values);
        
        if ($stmt) {
            log_security_event('PROJECT_CREATED', "Created project: $name");
            projects_redirect_with_msg('تم إضافة المشروع بنجاح ✅');
        } else {
            projects_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}
?>


<?php
$page_title = "إيكوبيشن | المشاريع";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<style>
    .link-alert-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 6px;
        padding: 2px 9px;
        border-radius: 999px;
        background: linear-gradient(135deg, #fff7d6, #ffe8bf);
        color: #7c2d12;
        border: 1px solid rgba(217, 119, 6, 0.28);
        font-size: .72rem;
        font-weight: 800;
        box-shadow: 0 1px 4px rgba(217, 119, 6, 0.18);
        animation: linkAlertPulse 1.6s ease-in-out infinite;
        vertical-align: middle;
    }

    .link-alert-chip i {
        color: #b45309;
        font-size: .75rem;
    }

    @keyframes linkAlertPulse {
        0%, 100% { transform: translateY(0); box-shadow: 0 1px 4px rgba(217, 119, 6, 0.18); }
        50% { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(217, 119, 6, 0.28); }
    }
</style>

<div class="main">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-project-diagram"></i></div>
            <h1 class="page-title">إدارة المشاريع</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add">
                    <i class="fas fa-plus-circle"></i> إضافة مشروع
                </a>
            <?php else: ?>
                <button class="add" disabled style="opacity: .6; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحية)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>



    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مشروع</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user-tie"></i> اسم العميل (اختياري)</label>
                        <select name="client_id" id="client_id" required>
                            <option value="">-- اختر العميل  --</option>
                            <?php
                            $clients_query = mysqli_query($conn, "SELECT c.id, c.client_code, c.client_name FROM clients c WHERE c.status = 'نشط' AND $client_scope_sql ORDER BY c.client_name ASC");
                            while ($cli = mysqli_fetch_assoc($clients_query)) {
                                echo "<option value='" . intval($cli['id']) . "'>[" . e($cli['client_code']) . "] " . e($cli['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-barcode"></i> كود المشروع</label>
                        <input type="text" name="project_code" placeholder="كود المشروع" id="project_code" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-signature"></i> اسم المشروع</label>
                        <input type="text" name="project_name" id="project_name" placeholder="أدخل اسم المشروع"
                            required />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker-alt"></i> موقع المشروع</label>
                        <input type="text" name="location" placeholder="أدخل موقع المشروع" id="project_location" />
                        <input type="hidden" name="total" value="0" />
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> الفئة</label>
                        <input type="text" name="category" placeholder="الفئة" id="project_category" />
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> القطاع الفرعي</label>
                        <input type="text" name="sub_sector" placeholder="القطاع الفرعي" id="project_sub_sector" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marked-alt"></i> الولاية</label>
                        <input type="text" name="state" placeholder="الولاية" id="project_state" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-pin"></i> المنطقة</label>
                        <input type="text" name="region" placeholder="المنطقة" id="project_region" />
                    </div>
                    <div>
                        <label><i class="fas fa-store"></i> أقرب سوق</label>
                        <input type="text" name="nearest_market" placeholder="أقرب سوق" id="project_nearest_market" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> خط العرض</label>
                        <input type="text" name="latitude" placeholder="خط العرض" id="project_latitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> خط الطول</label>
                        <input type="text" name="longitude" placeholder="خط الطول" id="project_longitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة المشروع</label>
                        <select name="status" id="project_status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1">✅ نشط</option>
                            <option value="0">❌ غير نشط</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fas fa-save"></i> <span>حفظ المشروع</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المشاريع -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0;"><i class="fas fa-list"></i> قائمة المشاريع</h5>

                <?php

                if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                    $client_id = intval($_GET['client_id']);
                    $client_result = mysqli_query($conn, "SELECT c.client_name FROM clients c WHERE c.id = $client_id AND $client_scope_sql");
                    if ($client_row = mysqli_fetch_assoc($client_result)) {
                        echo "للعميل: <strong>" . htmlspecialchars($client_row['client_name']) . "</strong>";
                    }
                }

                ?>

            </h5>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-sm btn-success" id="exportBtn" title="تحميل النموذج">
                    <i class="fas fa-download"></i> تحميل النموذج
                </button>
                <?php if ($can_add): ?>
                    <button class="btn btn-sm btn-info" id="importBtn" title="استيراد ملف">
                        <i class="fas fa-upload"></i> استيراد من Excel
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> تاريخ الإضافة</th>
                            <th><i class="fas fa-user-tie"></i> العميل</th>
                            <th><i class="fas fa-file-contract"></i> كود المشروع</th>
                            <th><i class="fas fa-project-diagram"></i> المشروع</th>
                            <th><i class="fas fa-truck"></i> عدد الموردين</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <!-- <th><i class="fas fa-file-contract"></i> عقود المشروع</th> -->
                            <th> المناجم</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // جلب جميع المشاريع من جدول project

                        $where_clauses = array($project_scope_sql, $project_not_deleted_sql);

                        if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                            $client_id = intval($_GET['client_id']);
                            $where_clauses[] = "op.$project_client_column = $client_id";
                        }

                        $client_filter = ' WHERE ' . implode(' AND ', $where_clauses);

                        // جلب جميع المشاريع من جدول project مع البيانات المدخولة يدويًا
                        $query = "SELECT op.`id`, op.`name`, op.`client`, op.`location`, op.`total`, op.`status`, op.`create_at`, 
                      op.`project_code`, op.`category`, op.`sub_sector`, op.`state`, op.`region`, 
                                            op.`nearest_market`, op.`latitude`, op.`longitude`, op.`$project_client_column` AS `client_id`,
                      cc.`client_name`,
                      (SELECT COUNT(*) 
                       FROM contracts c 
                       INNER JOIN mines m ON c.mine_id = m.id 
                       WHERE m.project_id = op.id AND $mines_not_deleted_sql) as 'contracts',
                      (SELECT COUNT(DISTINCT pm.suppliers) 
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE m.project_id = op.id) as 'total_suppliers',
                          (SELECT COUNT(*) FROM mines m WHERE m.project_id = op.id AND $mines_not_deleted_sql) as mines_count
                      FROM project op
                          LEFT JOIN clients cc ON op.$project_client_column = cc.id
                      $client_filter
                      ORDER BY op.id DESC";

                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $project_name_cell = "<strong>" . e($row['name']) . "</strong>";
                            if (intval($row['mines_count']) === 0) {
                                $project_name_cell .= " <span class='link-alert-chip' title='المشروع ليس بداخله منجم'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                            }

                            echo "<tr>";
                            echo "<td>" . e($row['create_at']) . "</td>";
                            echo "<td>" . e(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "</td>";
                            echo "<td>" . e(isset($row['project_code']) && $row['project_code'] !== '' ? $row['project_code'] : '-') . "</td>";
                            echo "<td>" . $project_name_cell . "</td>";
                            echo "<td><span class='count-badge'>" . intval($row['total_suppliers']) . "</span></td>";
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                            }

                            echo "<td>
                           

                             <a href='project_mines.php?project_id=" . intval($row['id']) . "' 
                                       class='mines-count-link' 
                                       title='عرض المناجم'>
                                        <i class='fas fa-mountain'></i>
                                        <span class='mines-count-badge'>" . intval($row['mines_count']) . "</span>
                             </a>

                        </td>";

                            echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)' 
                                   class='action-btn view viewBtn' 
                                   data-id='" . $row['id'] . "' 
                                   data-client-id='" . intval(isset($row['client_id']) ? $row['client_id'] : 0) . "' 
                                   data-project-name='" . htmlspecialchars($row['name']) . "' 
                                   data-client-name='" . htmlspecialchars(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "' 
                                   data-location='" . htmlspecialchars($row['location']) . "' 
                                   data-project-code='" . htmlspecialchars(isset($row['project_code']) ? $row['project_code'] : '') . "' 
                                   data-category='" . htmlspecialchars(isset($row['category']) ? $row['category'] : '') . "' 
                                   data-sub-sector='" . htmlspecialchars(isset($row['sub_sector']) ? $row['sub_sector'] : '') . "' 
                                   data-state='" . htmlspecialchars(isset($row['state']) ? $row['state'] : '') . "' 
                                   data-region='" . htmlspecialchars(isset($row['region']) ? $row['region'] : '') . "' 
                                   data-nearest-market='" . htmlspecialchars(isset($row['nearest_market']) ? $row['nearest_market'] : '') . "' 
                                   data-latitude='" . htmlspecialchars(isset($row['latitude']) ? $row['latitude'] : '') . "' 
                                   data-longitude='" . htmlspecialchars(isset($row['longitude']) ? $row['longitude'] : '') . "' 
                                   data-status='" . $row['status'] . "' 
                                   data-contracts='" . $row['contracts'] . "' 
                                   data-suppliers='" . $row['total_suppliers'] . "'
                                   title='عرض التفاصيل'>
                                   <i class='fas fa-eye'></i>
                                          </a>";

                                          if ($can_edit) {
                                                echo "<a href='javascript:void(0)' 
                                                    class='action-btn edit editBtn' 
                                                    data-id='" . intval($row['id']) . "' 
                                                    data-client-id='" . intval(isset($row['client_id']) ? $row['client_id'] : 0) . "' 
                                                    data-project-name='" . htmlspecialchars($row['name']) . "' 
                                                    data-location='" . htmlspecialchars($row['location']) . "' 
                                                    data-project-code='" . htmlspecialchars(isset($row['project_code']) ? $row['project_code'] : '') . "' 
                                                    data-category='" . htmlspecialchars(isset($row['category']) ? $row['category'] : '') . "' 
                                                    data-sub-sector='" . htmlspecialchars(isset($row['sub_sector']) ? $row['sub_sector'] : '') . "' 
                                                    data-state='" . htmlspecialchars(isset($row['state']) ? $row['state'] : '') . "' 
                                                    data-region='" . htmlspecialchars(isset($row['region']) ? $row['region'] : '') . "' 
                                                    data-nearest-market='" . htmlspecialchars(isset($row['nearest_market']) ? $row['nearest_market'] : '') . "' 
                                                    data-latitude='" . htmlspecialchars(isset($row['latitude']) ? $row['latitude'] : '') . "' 
                                                    data-longitude='" . htmlspecialchars(isset($row['longitude']) ? $row['longitude'] : '') . "' 
                                                    data-status='" . htmlspecialchars($row['status']) . "'
                                                    title='تعديل'>
                                                    <i class='fas fa-edit'></i>
                                                </a>";
                                          }

                                          if ($can_delete) {
                                                echo "<a href='projects.php?delete_id=" . intval($row['id']) . "&csrf_token=" . urlencode(generate_csrf_token()) . "' 
                                                    class='action-btn delete' 
                                                    onclick='return confirm(\"هل أنت متأكد من حذف هذا المشروع؟\")'
                                                    title='حذف'>
                                                    <i class='fas fa-trash-alt'></i>
                                                </a>";
                                          }
                              
                                     echo "</div>
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

<!-- Modal عرض تفاصيل المشروع -->
<div id="viewProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض تفاصيل المشروع</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i>  العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المشروع</div>
                    <div class="view-item-value" id="view_project_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> اسم المشروع</div>
                    <div class="view-item-value" id="view_project_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-layer-group"></i> الفئة</div>
                    <div class="view-item-value" id="view_category">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> القطاع الفرعي</div>
                    <div class="view-item-value" id="view_sub_sector">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marked-alt"></i> الولاية</div>
                    <div class="view-item-value" id="view_state">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-pin"></i> المنطقة</div>
                    <div class="view-item-value" id="view_region">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> موقع المشروع</div>
                    <div class="view-item-value" id="view_location">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-store"></i> أقرب سوق</div>
                    <div class="view-item-value" id="view_nearest_market">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker"></i> الإحداثيات (خط العرض / خط الطول)
                    </div>
                    <div class="view-item-value" id="view_coordinates">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-contract"></i> عدد العقود</div>
                    <div class="view-item-value" id="view_contracts">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-truck"></i> عدد الموردين</div>
                    <div class="view-item-value" id="view_suppliers">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> حالة المشروع</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a id="viewMinesBtn" class="btn-modal btn-modal-save" style="text-decoration: none;">
                <i class="fas fa-mountain"></i> مناجم المشروع
            </a>
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> تعديل المشروع
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS (Bundle includes Popper) -->
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
    // إغلاق Modal عرض المشروع - تعريف عام
    function closeViewModal() {
        $('#viewProjectModal').fadeOut(300);
    }

    (function () {
        // تشغيل DataTable
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'نسخ (Copy)' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة (Print)' }
                ],
                "language": {
                    "url": "/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

        // اظهار/اخفاء الفورم
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        if (toggleProjectFormBtn) {
            toggleProjectFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                // تنظيف الحقول عند الإضافة
                $("#project_id").val("");
                $("#project_name").val("");
                $("#client_id").val("");
                $("#project_location").val("");
                $("#project_code").val("");
                $("#project_category").val("");
                $("#project_sub_sector").val("");
                $("#project_state").val("");
                $("#project_region").val("");
                $("#project_nearest_market").val("");
                $("#project_latitude").val("");
                $("#project_longitude").val("");
                $("#project_status").val("");
            });
        }

        // عرض Modal عند الضغط على زر العرض
        $(document).on("click", ".viewBtn", function () {
            const projectData = {
                id: $(this).data('id'),
                projectName: $(this).data('project-name'),
                clientName: $(this).data('client-name'),
                location: $(this).data('location'),
                projectCode: $(this).data('project-code'),
                category: $(this).data('category'),
                subSector: $(this).data('sub-sector'),
                state: $(this).data('state'),
                region: $(this).data('region'),
                nearestMarket: $(this).data('nearest-market'),
                latitude: $(this).data('latitude'),
                longitude: $(this).data('longitude'),
                status: $(this).data('status'),
                contracts: $(this).data('contracts'),
                suppliers: $(this).data('suppliers')
            };

            // ملء بيانات العرض
            $('#view_project_name').text(projectData.projectName || '-');
            $('#view_client_name').text(projectData.clientName || '-');
            $('#view_project_code').text(projectData.projectCode || '-');
            $('#view_category').text(projectData.category || '-');
            $('#view_sub_sector').text(projectData.subSector || '-');
            $('#view_state').text(projectData.state || '-');
            $('#view_region').text(projectData.region || '-');
            $('#view_location').text(projectData.location || '-');
            $('#view_nearest_market').text(projectData.nearestMarket || '-');

            // عرض الإحداثيات
            let coordsText = '-';
            if (projectData.latitude && projectData.longitude) {
                coordsText = projectData.latitude + ' / ' + projectData.longitude;
            }
            $('#view_coordinates').text(coordsText);

            $('#view_contracts').text(projectData.contracts || '0');
            $('#view_suppliers').text(projectData.suppliers || '0');

            // عرض الحالة بألوان
            let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
            if (projectData.status === '1' || projectData.status === 1) {
                statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
            } else {
                statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
            }
            statusHtml += ' display: inline-block;">';
            statusHtml += '<i class="fas fa-circle" style="margin-left: 6px; font-size: 8px;"></i> ' + (projectData.status === '1' || projectData.status === 1 ? 'نشط' : 'غير نشط') + '</span>';
            $('#view_status').html(statusHtml);

            // تحضير زر التعديل
            const editBtn = $('#viewEditBtn');
            editBtn.data('id', projectData.id);
            editBtn.data('company-project-id', $(this).data('company-project-id'));
            editBtn.data('client-id', $(this).data('client-id'));
            editBtn.data('name', $(this).data('name'));
            editBtn.data('location', projectData.location);
            editBtn.data('status', projectData.status);

            // تحضير زر مناجم المشروع
            $('#viewMinesBtn').attr('href', 'project_mines.php?project_id=' + projectData.id);

            $('#viewProjectModal').fadeIn(300);
        });

        // إغلاق عند الضغط خارج Modal
        $(window).on('click', function (e) {
            if (e.target.id === 'viewProjectModal') {
                closeViewModal();
            }
        });

        // إغلاق عند الضغط على ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#viewProjectModal').is(':visible')) {
                closeViewModal();
            }
        });

        // التعامل مع زر التعديل من Modal العرض
        $('#viewEditBtn').on('click', function () {
            $("#project_id").val($(this).data('id'));
            $("#company_project_id").val($(this).data('company-project-id'));
            $("#client_id").val($(this).data('client-id'));
            $("#project_location").val($(this).data('location'));
            $("#project_status").val($(this).data('status'));

            closeViewModal();
            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند الضغط على زر تعديل من الجدول
        $(document).on("click", ".editBtn:not(#viewEditBtn)", function () {
            $("#project_id").val($(this).data("id"));
            $("#project_name").val($(this).data("project-name"));
            $("#client_id").val($(this).data("client-id"));
            $("#project_location").val($(this).data("location"));
            $("#project_code").val($(this).data("project-code"));
            $("#project_category").val($(this).data("category"));
            $("#project_sub_sector").val($(this).data("sub-sector"));
            $("#project_state").val($(this).data("state"));
            $("#project_region").val($(this).data("region"));
            $("#project_nearest_market").val($(this).data("nearest-market"));
            $("#project_latitude").val($(this).data("latitude"));
            $("#project_longitude").val($(this).data("longitude"));
            $("#project_status").val($(this).data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند تمرير رقم العميل قي ال url
        $(document).ready(function () {
            // إذا تم تمرير client_id في الرابط، افتح الفورم تلقائيًا
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');

            if (clientId) {
                $('#projectForm').show();
                $('#client_id').val(clientId);
            }
        });

        // ===== معالجات الاستيراد والتصدير =====
        
        // زر تحميل النموذج
        $('#exportBtn').on('click', function() {
            window.location.href = 'download_projects_template.php';
        });

        // زر الاستيراد من Excel
        $('#importBtn').on('click', function() {
            $('#importModal').modal('show');
        });

        // معالج رفع الملف
        $('#importFileForm').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#projectFile')[0];
            if (!fileInput.files.length) {
                alert('يرجى اختيار ملف');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            $.ajax({
                url: 'import_projects_excel.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('تم استيراد ' + response.imported_count + ' مشروع بنجاح!');
                        $('#importModal').modal('hide');
                        $('#projectsTable').DataTable().ajax.reload();
                        location.reload(); // إعادة تحميل الصفحة لتحديث الجدول
                    } else {
                        let errorMsg = 'حدث خطأ أثناء الاستيراد:\n\n';
                        if (response.errors && response.errors.length > 0) {
                            response.errors.forEach(function(error) {
                                errorMsg += error + '\n';
                            });
                        }
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    alert('حدث خطأ في الاتصال: ' + error);
                }
            });
        });

    })();
</script>

<!-- Modal لاستيراد الملفات -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel"><i class="fas fa-upload"></i> استيراد المشاريع من ملف Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="importFileForm">
                    <div class="form-group">
                        <label for="projectFile">اختر ملف Excel:</label>
                        <input type="file" class="form-control" id="projectFile" name="file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">الملفات المقبولة: Excel (.xlsx, .xls)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <?php if ($can_add): ?>
                    <button type="submit" form="importFileForm" class="btn btn-primary">
                        <i class="fas fa-upload"></i> استيراد
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>

</html>



