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
    $mine_code = sanitize_input(isset($_POST['mine_code']) ? $_POST['mine_code'] : '');
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
            mine_code = ?,
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
            $mine_code,
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
        $update_types = 'issssssssssssds';

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
        $insert_columns = "$project_client_column, name, client, location, project_code, mine_code, category, sub_sector, state, region, nearest_market, latitude, longitude, total, status, created_by";
        $insert_values = array($client_id, $name, $client, $location, $project_code, $mine_code, $category, $sub_sector, $state, $region, $nearest_market, $latitude, $longitude, $total, $status, $created_by);

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
$projects_total_count = 0;
$projects_active_count = 0;
$projects_inactive_count = 0;
$projects_with_contracts_count = 0;
$projects_active_contracts_count = 0;

$project_active_status_case_sql = "(status = 1 OR status = '1' OR TRIM(status) = 'نشط' OR TRIM(LOWER(status)) = 'active' OR TRIM(LOWER(status)) = 'true')";
$project_scope_stats_sql = str_replace('op.', 'p.', $project_scope_sql);
$project_not_deleted_stats_sql = str_replace('op.', 'p.', $project_not_deleted_sql);

$projects_stats_query = "SELECT
        COUNT(*) AS total_projects,
        SUM(CASE WHEN $project_active_status_case_sql THEN 1 ELSE 0 END) AS active_projects
    FROM project p
    WHERE $project_scope_stats_sql AND $project_not_deleted_stats_sql";
$projects_stats_result = mysqli_query($conn, $projects_stats_query);
if ($projects_stats_result && ($projects_stats_row = mysqli_fetch_assoc($projects_stats_result))) {
        $projects_total_count = intval($projects_stats_row['total_projects']);
        $projects_active_count = intval($projects_stats_row['active_projects']);
}
$projects_inactive_count = max(0, $projects_total_count - $projects_active_count);

$projects_with_contracts_query = "SELECT COUNT(DISTINCT c.project_id) AS projects_with_contracts
    FROM contracts c
    INNER JOIN project p ON p.id = c.project_id
    WHERE $project_scope_stats_sql AND $project_not_deleted_stats_sql AND c.status = 1";
$projects_with_contracts_result = mysqli_query($conn, $projects_with_contracts_query);
if ($projects_with_contracts_result && ($projects_with_contracts_row = mysqli_fetch_assoc($projects_with_contracts_result))) {
        $projects_with_contracts_count = intval($projects_with_contracts_row['projects_with_contracts']);
}

$projects_active_contracts_query = "SELECT COUNT(*) AS total_active_contracts
    FROM contracts c
    INNER JOIN project p ON p.id = c.project_id
    WHERE $project_scope_stats_sql AND $project_not_deleted_stats_sql AND c.status = 1";
$projects_active_contracts_result = mysqli_query($conn, $projects_active_contracts_query);
if ($projects_active_contracts_result && ($projects_active_contracts_row = mysqli_fetch_assoc($projects_active_contracts_result))) {
        $projects_active_contracts_count = intval($projects_active_contracts_row['total_active_contracts']);
}
?>


<?php
$page_title = "إيكوبيشن | المشاريع";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<div class="main projects-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'إدارة المشاريع';
    $header_icon  = 'fas fa-project-diagram';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => 'add-btn', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحية)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'projects-toggle-stats-text');
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('projects', 'المشاريع', $can_add) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <!-- <div class="header projects-header-shell">
        <a href="../main/dashboard.php" class="back-btn">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-project-diagram"></i></div>
            إدارة المشاريع
        </h1>
        <div class="header -actions">
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn projects-header-add">
                    <i class="fas fa-plus-circle"></i> إضافة مشروع
                </a>
            <?php else: ?>
                <button class="add-btn projects-btn-disabled" disabled>
                    <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحية)
                </button>
            <?php endif; ?>
        </div>
    </div> -->

    <?php if (!empty($_GET['msg'])): ?>
        <?php $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section projects-hidden" id="projectsStatsSection">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-project-diagram"></i></div>
                <div class="stats-title">إجمالي المشاريع</div>
                <div class="stats-value"><?php echo $projects_total_count; ?></div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stats-title">المشاريع النشطة</div>
                <div class="stats-value"><?php echo $projects_active_count; ?></div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stats-title">المشاريع غير النشطة</div>
                <div class="stats-value"><?php echo $projects_inactive_count; ?></div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-file-contract"></i></div>
                <div class="stats-title">المشاريع التي لها عقود</div>
                <div class="stats-value"><?php echo $projects_with_contracts_count; ?></div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stats-title">إجمالي العقود النشطة</div>
                <div class="stats-value"><?php echo $projects_active_contracts_count; ?></div>
            </div>
        </div>
    </div>



    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" class="allforms">
        <div class="card shadow-sm pu-form-card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة مشروع جديد</span></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user-tie"></i> اسم العميل (اختياري)</label>
                        <select name="client_id" id="client_id" required>
                            <option value="">-- اختر العميل --</option>
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
                        <label><i class="fas fa-mountain"></i> كود المنجم</label>
                        <input type="text" name="mine_code" placeholder="كود المنجم" id="mine_code" />
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
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">حفظ المشروع</span>
                    </button>
                    <button type="button" id="projectFormCancelBtn" class="btn-cancel">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المشاريع -->
    <div class="card">
        <div class="card-header projects-table-header">
            <h5><i class="fas fa-list"></i> قائمة المشاريع</h5>

            <?php

            if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                $client_id = intval($_GET['client_id']);
                $client_result = mysqli_query($conn, "SELECT c.client_name FROM clients c WHERE c.id = $client_id AND $client_scope_sql");
                if ($client_row = mysqli_fetch_assoc($client_result)) {
                    echo "للعميل: <strong>" . htmlspecialchars($client_row['client_name']) . "</strong>";
                }
            }

            ?>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display projects-table-nowrap" style="width:100%;">
                    <thead>
                        <tr>
                            <th> إجراءات</th>
                            <th> المشروع</th>
                            <th> تاريخ الإضافة</th>
                            <th> العميل</th>
                            <th> كود المشروع</th>
                            <th> كود المنجم</th>
                            <th> عدد الموردين</th>
                            <th> الحالة</th>
                            <th> عقود المشروع</th>
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
                      op.`project_code`, op.`mine_code`, op.`category`, op.`sub_sector`, op.`state`, op.`region`,
                                            op.`nearest_market`, op.`latitude`, op.`longitude`, op.`$project_client_column` AS `client_id`,
                      cc.`client_name`,
                      (SELECT COUNT(*)
                       FROM contracts c
                       WHERE c.project_id = op.id AND c.status = 1) as 'contracts',
                      (SELECT COUNT(DISTINCT pm.suppliers)
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE m.project_id = op.id) as 'total_suppliers'
                      FROM project op
                          LEFT JOIN clients cc ON op.$project_client_column = cc.id
                      $client_filter
                      ORDER BY op.id DESC";

                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $project_name_cell = "<a class='client-name-link' href='project_profile.php?id=" . intval($row['id']) . "'><strong>" . e($row['name']) . "</strong></a>";

                            echo "<tr>";
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
                                   data-mine-code='" . htmlspecialchars(isset($row['mine_code']) ? $row['mine_code'] : '') . "'
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
                                                    data-mine-code='" . htmlspecialchars(isset($row['mine_code']) ? $row['mine_code'] : '') . "'
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
                            echo "<td>" . $project_name_cell . "</td>";
                            echo "<td>" . e($row['create_at']) . "</td>";
                            echo "<td>" . e(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "</td>";
                            echo "<td>" . e(isset($row['project_code']) && $row['project_code'] !== '' ? $row['project_code'] : '-') . "</td>";
                            echo "<td>" . e(isset($row['mine_code']) && $row['mine_code'] !== '' ? $row['mine_code'] : '-') . "</td>";
                            echo "<td><span class='action-btn'>" . intval($row['total_suppliers']) . "</span></td>";
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fa-regular fa-circle-check'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                            }

                            echo "<td>
                             <a href='../Contracts/contracts.php?filter_project_id=" . intval($row['id']) . "'
                                       class='mines-count-link'
                                       title='عرض عقود المشروع'>
                                        <i class='fas fa-file-contract'></i>
                                        <span class='action-btn'>" . intval($row['contracts']) . "</span>
                             </a>
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
<div id="viewProjectModal" class="modal projects-view-modal">
    <div class="modal-content projects-view-modal-content">
        <div class="modal-header projects-view-modal-header">
            <h5><i class="fas fa-eye"></i> عرض تفاصيل المشروع</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body projects-view-modal-body">
            <div class="view-modal-body projects-view-grid">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المشروع</div>
                    <div class="view-item-value" id="view_project_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-mountain"></i> كود المنجم</div>
                    <div class="view-item-value" id="view_mine_code">-</div>
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
        <div class="modal-footer projects-view-modal-footer">
            <a id="viewContractsBtn" class="btn-modal btn-modal-save">
                <i class="fas fa-file-contract"></i> عقود المشروع
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
                scrollX: true,
                autoWidth: false,
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
        const projectFormCancelBtn = document.getElementById('projectFormCancelBtn');
        const projectFormTitle = document.getElementById('formTitle');
        const projectSubmitBtnText = document.getElementById('submitBtnText');
        const statsToggleBtn = $('#toggleStats');
        const statsSection = $('#projectsStatsSection');

        function setProjectFormAddMode() {
            if (projectFormTitle) {
                projectFormTitle.textContent = 'إضافة مشروع جديد';
            }
            if (projectSubmitBtnText) {
                projectSubmitBtnText.textContent = 'حفظ المشروع';
            }
        }

        function setProjectFormEditMode() {
            if (projectFormTitle) {
                projectFormTitle.textContent = 'تعديل المشروع';
            }
            if (projectSubmitBtnText) {
                projectSubmitBtnText.textContent = 'تحديث المشروع';
            }
        }

        function resetProjectForm() {
            $("#project_id").val("");
            $("#project_name").val("");
            $("#client_id").val("");
            $("#project_location").val("");
            $("#project_code").val("");
            $("#mine_code").val("");
            $("#project_category").val("");
            $("#project_sub_sector").val("");
            $("#project_state").val("");
            $("#project_region").val("");
            $("#project_nearest_market").val("");
            $("#project_latitude").val("");
            $("#project_longitude").val("");
            $("#project_status").val("");
            setProjectFormAddMode();
        }

        if (toggleProjectFormBtn) {
            toggleProjectFormBtn.addEventListener('click', function () {
                if (!projectForm) {
                    return;
                }

                const $projectForm = $('#projectForm');
                if ($projectForm.hasClass('allforms-visible')) {
                    $projectForm.removeClass('allforms-visible');
                    resetProjectForm();
                } else {
                    resetProjectForm();
                    $projectForm.addClass('allforms-visible');
                }
            });
        }

        if (projectFormCancelBtn) {
            projectFormCancelBtn.addEventListener('click', function () {
                const $projectForm = $('#projectForm');
                if (!$projectForm.hasClass('allforms-visible')) {
                    return;
                }

                $projectForm.removeClass('allforms-visible');
                resetProjectForm();
            });
        }

        function updateStatsToggleState(isVisible) {
            if (!statsToggleBtn.length) {
                return;
            }

            const icon = statsToggleBtn.find('i').first();
            const text = statsToggleBtn.find('.projects-toggle-stats-text');

            if (isVisible) {
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
                text.text('إخفاء الإحصائيات');
            } else {
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
                text.text('إظهار الإحصائيات');
            }
        }

        updateStatsToggleState(statsSection.is(':visible'));

        statsToggleBtn.on('click', function () {
            if (statsSection.is(':visible')) {
                statsSection.stop(true, true).slideUp(250, function () {
                    statsSection.addClass('projects-hidden');
                    updateStatsToggleState(false);
                });
            } else {
                statsSection.removeClass('projects-hidden').hide();
                statsSection.stop(true, true).slideDown(250, function () {
                    updateStatsToggleState(true);
                });
            }
        });

        setProjectFormAddMode();

        // عرض Modal عند الضغط على زر العرض
        $(document).on("click", ".viewBtn", function () {
            const projectData = {
                id: $(this).data('id'),
                projectName: $(this).data('project-name'),
                clientName: $(this).data('client-name'),
                location: $(this).data('location'),
                projectCode: $(this).data('project-code'),
                mineCode: $(this).data('mine-code'),
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
            $('#view_mine_code').text(projectData.mineCode || '-');
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
            editBtn.data('project-name', projectData.projectName);
            editBtn.data('project-code', projectData.projectCode);
            editBtn.data('mine-code', projectData.mineCode);
            editBtn.data('category', projectData.category);
            editBtn.data('sub-sector', projectData.subSector);
            editBtn.data('state', projectData.state);
            editBtn.data('region', projectData.region);
            editBtn.data('nearest-market', projectData.nearestMarket);
            editBtn.data('latitude', projectData.latitude);
            editBtn.data('longitude', projectData.longitude);
            editBtn.data('location', projectData.location);
            editBtn.data('status', projectData.status);

            // تحضير زر عقود المشروع
            $('#viewContractsBtn').attr('href', '../Contracts/contracts.php?filter_project_id=' + projectData.id);

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
            $("#project_name").val($(this).data('project-name'));
            $("#project_location").val($(this).data('location'));
            $("#project_code").val($(this).data('project-code'));
            $("#mine_code").val($(this).data('mine-code'));
            $("#project_category").val($(this).data('category'));
            $("#project_sub_sector").val($(this).data('sub-sector'));
            $("#project_state").val($(this).data('state'));
            $("#project_region").val($(this).data('region'));
            $("#project_nearest_market").val($(this).data('nearest-market'));
            $("#project_latitude").val($(this).data('latitude'));
            $("#project_longitude").val($(this).data('longitude'));
            $("#project_status").val($(this).data('status'));

            setProjectFormEditMode();

            closeViewModal();
            $("#projectForm").addClass('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند الضغط على زر تعديل من الجدول
        $(document).on("click", ".editBtn:not(#viewEditBtn)", function () {
            $("#project_id").val($(this).data("id"));
            $("#project_name").val($(this).data("project-name"));
            $("#client_id").val($(this).data("client-id"));
            $("#project_location").val($(this).data("location"));
            $("#project_code").val($(this).data("project-code"));
            $("#mine_code").val($(this).data("mine-code"));
            $("#project_category").val($(this).data("category"));
            $("#project_sub_sector").val($(this).data("sub-sector"));
            $("#project_state").val($(this).data("state"));
            $("#project_region").val($(this).data("region"));
            $("#project_nearest_market").val($(this).data("nearest-market"));
            $("#project_latitude").val($(this).data("latitude"));
            $("#project_longitude").val($(this).data("longitude"));
            $("#project_status").val($(this).data("status"));

            setProjectFormEditMode();

            $("#projectForm").addClass('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند تمرير رقم العميل قي ال url
        $(document).ready(function () {
            // إذا تم تمرير client_id في الرابط، افتح الفورم تلقائيًا
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');

            if (clientId) {
                setProjectFormAddMode();
                $('#projectForm').addClass('allforms-visible');
                $('#client_id').val(clientId);
            }
        });

        // ===== معالجات الاستيراد والتصدير =====

        // زر تحميل النموذج
        $('#exportBtn').on('click', function () {
            window.location.href = 'download_projects_template.php';
        });

        // زر الاستيراد من Excel
        $('#importBtn').on('click', function () {
            $('#importModal').modal('show');
        });

        // معالج رفع الملف
        $('#importFileForm').on('submit', function (e) {
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
                success: function (response) {
                    if (response.success) {
                        alert('تم استيراد ' + response.imported_count + ' مشروع بنجاح!');
                        $('#importModal').modal('hide');
                        $('#projectsTable').DataTable().ajax.reload();
                        location.reload(); // إعادة تحميل الصفحة لتحديث الجدول
                    } else {
                        let errorMsg = 'حدث خطأ أثناء الاستيراد:\n\n';
                        if (response.errors && response.errors.length > 0) {
                            response.errors.forEach(function (error) {
                                errorMsg += error + '\n';
                            });
                        }
                        alert(errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    alert('حدث خطأ في الاتصال: ' + error);
                }
            });
        });

    })();
</script>

<style>
    .projects-main .stats-section {
        border: 1px solid var(--bdr);
        border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255, 255, 255, .95) 0%, var(--s2) 100%);
        box-shadow: var(--sh);
        padding: 14px;
        margin-bottom: 14px;
    }

    .projects-main .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 12px;
    }

    .projects-main .stats-card {
        background: var(--s1);
        border: 1px solid var(--bdr);
        border-radius: 12px;
        padding: 12px;
        box-shadow: 0 2px 8px rgba(26, 18, 8, .07);
        position: relative;
        overflow: hidden;
    }

    .projects-main .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--or), var(--or2));
        opacity: .9;
    }

    .projects-main .stats-card .stats-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .projects-main .stats-card .stats-title {
        margin-top: 10px;
        color: var(--t2);
        font-size: .82rem;
        font-weight: 700;
    }

    .projects-main .stats-card .stats-value {
        margin-top: 7px;
        color: var(--t1);
        font-size: 1.62rem;
        line-height: 1;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .projects-main .stats-primary .stats-icon { background: rgba(37, 99, 235, .14); color: #1d4ed8; }
    .projects-main .stats-success .stats-icon { background: rgba(22, 163, 74, .14); color: #15803d; }
    .projects-main .stats-danger .stats-icon { background: rgba(220, 38, 38, .14); color: #b91c1c; }
    .projects-main .stats-purple .stats-icon { background: rgba(124, 58, 237, .14); color: #6d28d9; }
    .projects-main .stats-cyan .stats-icon { background: rgba(8, 145, 178, .14); color: #0e7490; }

    .table-container {
        overflow-x: auto;
    }

    #projectsTable.projects-table-nowrap,
    #projectsTable.projects-table-nowrap th,
    #projectsTable.projects-table-nowrap td {
        white-space: nowrap;
    }

    #projectsTable .action-btns {
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    @media (max-width: 900px) {
        .projects-main .stats-grid {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 560px) {
        .projects-main .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Modal لاستيراد الملفات -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel"><i class="fas fa-upload"></i> استيراد المشاريع من ملف
                    Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="importFileForm">
                    <div class="form-group">
                        <label for="projectFile">اختر ملف Excel:</label>
                        <input type="file" class="form-control" id="projectFile" name="file" accept=".xlsx,.xls"
                            required>
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
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>
