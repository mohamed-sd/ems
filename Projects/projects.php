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
        $map = array(
            'Ø§' => 'ا', 'Ø¨' => 'ب', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ø­' => 'ح',
            'Ø®' => 'خ', 'Ø¯' => 'د', 'Ø°' => 'ذ', 'Ø±' => 'ر', 'Ø²' => 'ز', 'Ø³' => 'س',
            'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط', 'Ø¸' => 'ظ', 'Ø¹' => 'ع',
            'Øº' => 'غ', 'Ù' => 'ف', 'Ù‚' => 'ق', 'Ùƒ' => 'ك', 'Ù„' => 'ل', 'Ù…' => 'م',
            'Ù†' => 'ن', 'Ù‡' => 'ه', 'Ùˆ' => 'و', 'ÙŠ' => 'ي', 'Ù‰' => 'ى', 'Ø©' => 'ة',
            'Ø¡' => 'ء', 'Ø£' => 'أ', 'Ø¥' => 'إ', 'Ø¢' => 'آ', 'Ø¤' => 'ؤ', 'Ø¦' => 'ئ',
            'ØŒ' => '،', 'Ø›' => '؛', 'ØŸ' => '؟', 'âœ…' => '✅', 'âŒ' => '❌', 'â¸' => '⏸',
            'ðŸ”’' => '🔒', 'ðŸ‘‹' => '👋', 'ðŸš€' => '🚀', 'ðŸ†' => '🏆'
        );

        return strtr($buffer, $map);
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

$client_scope_sql = $clients_has_company_id
    ? "c.company_id = $company_id"
    : "EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = c.created_by AND scope_u.company_id = $company_id)";
$client_scope_sql .= " AND $client_not_deleted_sql";

function projects_redirect_with_msg($msg)
{
    header('Location: projects.php?msg=' . urlencode($msg));
    exit();
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ”’ Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
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

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù‡Ù†Ø§Ùƒ ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض المشاريع ❌'));
    exit();
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø­Ø°Ù Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (!$can_delete) {
        projects_redirect_with_msg('لا توجد صلاحية حذف المشاريع ❌');
    }

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† CSRF Token
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

    $delete_query = "UPDATE project SET " . implode(', ', $delete_set) . " WHERE id = $delete_id AND $project_scope_sql AND $project_not_deleted_sql";
    if (mysqli_query($conn, $delete_query)) {
        log_security_event('PROJECT_SOFT_DELETED', "Soft deleted project ID: $delete_id");
        projects_redirect_with_msg('تم حذف المشروع (حذف ناعم) بنجاح ✅');
    }

    projects_redirect_with_msg('حدث خطأ أثناء حذف المشروع ❌');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project_name'])) {
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† CSRF Token
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

    // Ø¬Ù„Ø¨ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¯Ø®ÙˆÙ„Ø© Ø¨Ø´ÙƒÙ„ Ø¢Ù…Ù†
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

    // Ø¬Ù„Ø¨ Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø¥Ø°Ø§ ØªÙ… Ø§Ø®ØªÙŠØ§Ø±Ù‡
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

        $new_data = [
            $project_client_column => $client_id,
            'name' => $name,
            'client' => $client,
            'location' => $location,
            'project_code' => $project_code,
            'category' => $category,
            'sub_sector' => $sub_sector,
            'state' => $state,
            'region' => $region,
            'nearest_market' => $nearest_market,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'total' => $total,
            'status' => $status,
            'updated_at' => approval_now()
        ];

        if ($project_has_company_id) {
            $new_data['company_id'] = $company_id;
        }

        $payload = approval_build_simple_update_payload('project', ['id' => $id], $new_data, $old_project);
        $approval_action = ($old_project['status'] == '1' && $status == '0') ? 'deactivate' : 'update';
        $result = approval_create_request('project', $id, $approval_action, $payload, approval_get_user_id(), $conn);

        if (!empty($result['success'])) {
            log_security_event('PROJECT_UPDATE_REQUESTED', "Update approval requested for project: $name (ID: $id)");
            $msg = ((isset($result['status']) ? $result['status'] : 'pending') === 'approved') ? 'تم اعتماد التعديل وتنفيذه ✅' : 'تم إرسال طلب تعديل المشروع للمواففقة ✅';
            projects_redirect_with_msg($msg);
        }

        projects_redirect_with_msg((isset($result['message']) ? $result['message'] : 'حدث خطأ أثناء إنشاء طلب التعديل') . ' ❌');
    } else {
        // Ø¥Ø¶Ø§ÙØ© Ù…Ø¹ Prepared Statement
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<div class="main">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-project-diagram"></i></div>
            <h1 class="page-title">Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ø´Ø±ÙˆØ¹
                </a>
            <?php else: ?>
                <button class="add" disabled style="opacity: .6; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© (Ø¨Ø¯ÙˆÙ† ØµÙ„Ø§Ø­ÙŠØ©)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>



    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø´Ø±ÙˆØ¹ -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø´Ø±ÙˆØ¹</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user-tie"></i> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ (Ø§Ø®ØªÙŠØ§Ø±ÙŠ)</label>
                        <select name="client_id" id="client_id" required>
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø¹Ù…ÙŠÙ„  --</option>
                            <?php
                            $clients_query = mysqli_query($conn, "SELECT c.id, c.client_code, c.client_name FROM clients c WHERE c.status = 'نشط' AND $client_scope_sql ORDER BY c.client_name ASC");
                            while ($cli = mysqli_fetch_assoc($clients_query)) {
                                echo "<option value='" . intval($cli['id']) . "'>[" . e($cli['client_code']) . "] " . e($cli['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                        <input type="text" name="project_code" placeholder="ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹" id="project_code" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-signature"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                        <input type="text" name="project_name" id="project_name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹"
                            required />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker-alt"></i> Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                        <input type="text" name="location" placeholder="Ø£Ø¯Ø®Ù„ Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹" id="project_location" />
                        <input type="hidden" name="total" value="0" />
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> Ø§Ù„ÙØ¦Ø©</label>
                        <input type="text" name="category" placeholder="Ø§Ù„ÙØ¦Ø©" id="project_category" />
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> Ø§Ù„Ù‚Ø·Ø§Ø¹ Ø§Ù„ÙØ±Ø¹ÙŠ</label>
                        <input type="text" name="sub_sector" placeholder="Ø§Ù„Ù‚Ø·Ø§Ø¹ Ø§Ù„ÙØ±Ø¹ÙŠ" id="project_sub_sector" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marked-alt"></i> Ø§Ù„ÙˆÙ„Ø§ÙŠØ©</label>
                        <input type="text" name="state" placeholder="Ø§Ù„ÙˆÙ„Ø§ÙŠØ©" id="project_state" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-pin"></i> Ø§Ù„Ù…Ù†Ø·Ù‚Ø©</label>
                        <input type="text" name="region" placeholder="Ø§Ù„Ù…Ù†Ø·Ù‚Ø©" id="project_region" />
                    </div>
                    <div>
                        <label><i class="fas fa-store"></i> Ø£Ù‚Ø±Ø¨ Ø³ÙˆÙ‚</label>
                        <input type="text" name="nearest_market" placeholder="Ø£Ù‚Ø±Ø¨ Ø³ÙˆÙ‚" id="project_nearest_market" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> Ø®Ø· Ø§Ù„Ø¹Ø±Ø¶</label>
                        <input type="text" name="latitude" placeholder="Ø®Ø· Ø§Ù„Ø¹Ø±Ø¶" id="project_latitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> Ø®Ø· Ø§Ù„Ø·ÙˆÙ„</label>
                        <input type="text" name="longitude" placeholder="Ø®Ø· Ø§Ù„Ø·ÙˆÙ„" id="project_longitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                        <select name="status" id="project_status" required>
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                            <option value="1">âœ… Ù†Ø´Ø·</option>
                            <option value="0">âŒ ØºÙŠØ± Ù†Ø´Ø·</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fas fa-save"></i> <span>Ø­ÙØ¸ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0;"><i class="fas fa-list"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹</h5>

                <?php

                if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                    $client_id = intval($_GET['client_id']);
                    $client_result = mysqli_query($conn, "SELECT c.client_name FROM clients c WHERE c.id = $client_id AND $client_scope_sql");
                    if ($client_row = mysqli_fetch_assoc($client_result)) {
                        echo "Ù„Ù„Ø¹Ù…ÙŠÙ„: <strong>" . htmlspecialchars($client_row['client_name']) . "</strong>";
                    }
                }

                ?>

            </h5>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-sm btn-success" id="exportBtn" title="ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬">
                    <i class="fas fa-download"></i> ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬
                </button>
                <?php if ($can_add): ?>
                    <button class="btn btn-sm btn-info" id="importBtn" title="Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù„Ù">
                        <i class="fas fa-upload"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¥Ø¶Ø§ÙØ©</th>
                            <th><i class="fas fa-user-tie"></i> Ø§Ù„Ø¹Ù…ÙŠÙ„</th>
                            <th><i class="fas fa-file-contract"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
                            <th><i class="fas fa-project-diagram"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
                            <th><i class="fas fa-truck"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</th>
                            <th><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
                            <!-- <th><i class="fas fa-file-contract"></i> Ø¹Ù‚ÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th> -->
                            <th> Ø§Ù„Ù…Ù†Ø§Ø¬Ù…</th>
                            <th><i class="fas fa-cogs"></i> Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Ø¬Ù„Ø¨ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ Ù…Ù† Ø¬Ø¯ÙˆÙ„ project

                        $where_clauses = array($project_scope_sql, $project_not_deleted_sql);

                        if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                            $client_id = intval($_GET['client_id']);
                            $where_clauses[] = "op.$project_client_column = $client_id";
                        }

                        $client_filter = ' WHERE ' . implode(' AND ', $where_clauses);

                        // Ø¬Ù„Ø¨ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ Ù…Ù† Ø¬Ø¯ÙˆÙ„ project Ù…Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¯Ø®ÙˆÙ„Ø© ÙŠØ¯ÙˆÙŠÙ‹Ø§
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
                            echo "<tr>";
                            echo "<td>" . e($row['create_at']) . "</td>";
                            echo "<td>" . e(isset($row['client_name']) && $row['client_name'] !== '' ? $row['client_name'] : $row['client']) . "</td>";
                            echo "<td>" . e(isset($row['project_code']) && $row['project_code'] !== '' ? $row['project_code'] : '-') . "</td>";
                            echo "<td><strong>" . e($row['name']) . "</strong></td>";
                            echo "<td><span class='count-badge'>" . intval($row['total_suppliers']) . "</span></td>";
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                            }

                            echo "<td>
                           

                             <a href='project_mines.php?project_id=" . intval($row['id']) . "' 
                                       class='mines-count-link' 
                                       title='Ø¹Ø±Ø¶ Ø§Ù„Ù…Ù†Ø§Ø¬Ù…'>
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
                                   title='Ø¹Ø±Ø¶ Ø§Ù„ØªÙØ§ØµÙŠÙ„'>
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
                                                    title='ØªØ¹Ø¯ÙŠÙ„'>
                                                    <i class='fas fa-edit'></i>
                                                </a>";
                                          }

                                          if ($can_delete) {
                                                echo "<a href='projects.php?delete_id=" . intval($row['id']) . "&csrf_token=" . urlencode(generate_csrf_token()) . "' 
                                                    class='action-btn delete' 
                                                    onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ØŸ\")'
                                                    title='Ø­Ø°Ù'>
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

<!-- Modal Ø¹Ø±Ø¶ ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ -->
<div id="viewProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> Ø¹Ø±Ø¶ ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
                    <div class="view-item-value" id="view_project_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
                    <div class="view-item-value" id="view_project_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-layer-group"></i> Ø§Ù„ÙØ¦Ø©</div>
                    <div class="view-item-value" id="view_category">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> Ø§Ù„Ù‚Ø·Ø§Ø¹ Ø§Ù„ÙØ±Ø¹ÙŠ</div>
                    <div class="view-item-value" id="view_sub_sector">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marked-alt"></i> Ø§Ù„ÙˆÙ„Ø§ÙŠØ©</div>
                    <div class="view-item-value" id="view_state">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-pin"></i> Ø§Ù„Ù…Ù†Ø·Ù‚Ø©</div>
                    <div class="view-item-value" id="view_region">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
                    <div class="view-item-value" id="view_location">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-store"></i> Ø£Ù‚Ø±Ø¨ Ø³ÙˆÙ‚</div>
                    <div class="view-item-value" id="view_nearest_market">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker"></i> Ø§Ù„Ø¥Ø­Ø¯Ø§Ø«ÙŠØ§Øª (Ø®Ø· Ø§Ù„Ø¹Ø±Ø¶ / Ø®Ø· Ø§Ù„Ø·ÙˆÙ„)
                    </div>
                    <div class="view-item-value" id="view_coordinates">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-contract"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯</div>
                    <div class="view-item-value" id="view_contracts">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-truck"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</div>
                    <div class="view-item-value" id="view_suppliers">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a id="viewMinesBtn" class="btn-modal btn-modal-save" style="text-decoration: none;">
                <i class="fas fa-mountain"></i> Ù…Ù†Ø§Ø¬Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
            </a>
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
            </button>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS (Bundle includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
    // Ø¥ØºÙ„Ø§Ù‚ Modal Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ - ØªØ¹Ø±ÙŠÙ Ø¹Ø§Ù…
    function closeViewModal() {
        $('#viewProjectModal').fadeOut(300);
    }

    (function () {
        // ØªØ´ØºÙŠÙ„ DataTable
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'Ù†Ø³Ø® (Copy)' },
                    { extend: 'excel', text: 'ØªØµØ¯ÙŠØ± Excel' },
                    { extend: 'csv', text: 'ØªØµØ¯ÙŠØ± CSV' },
                    { extend: 'pdf', text: 'ØªØµØ¯ÙŠØ± PDF' },
                    { extend: 'print', text: 'Ø·Ø¨Ø§Ø¹Ø© (Print)' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // Ø§Ø¸Ù‡Ø§Ø±/Ø§Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        if (toggleProjectFormBtn) {
            toggleProjectFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                // ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø¹Ù†Ø¯ Ø§Ù„Ø¥Ø¶Ø§ÙØ©
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

        // Ø¹Ø±Ø¶ Modal Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø²Ø± Ø§Ù„Ø¹Ø±Ø¶
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

            // Ù…Ù„Ø¡ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ø±Ø¶
            $('#view_project_name').text(projectData.projectName || '-');
            $('#view_client_name').text(projectData.clientName || '-');
            $('#view_project_code').text(projectData.projectCode || '-');
            $('#view_category').text(projectData.category || '-');
            $('#view_sub_sector').text(projectData.subSector || '-');
            $('#view_state').text(projectData.state || '-');
            $('#view_region').text(projectData.region || '-');
            $('#view_location').text(projectData.location || '-');
            $('#view_nearest_market').text(projectData.nearestMarket || '-');

            // Ø¹Ø±Ø¶ Ø§Ù„Ø¥Ø­Ø¯Ø§Ø«ÙŠØ§Øª
            let coordsText = '-';
            if (projectData.latitude && projectData.longitude) {
                coordsText = projectData.latitude + ' / ' + projectData.longitude;
            }
            $('#view_coordinates').text(coordsText);

            $('#view_contracts').text(projectData.contracts || '0');
            $('#view_suppliers').text(projectData.suppliers || '0');

            // Ø¹Ø±Ø¶ Ø§Ù„Ø­Ø§Ù„Ø© Ø¨Ø£Ù„ÙˆØ§Ù†
            let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
            if (projectData.status === '1' || projectData.status === 1) {
                statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
            } else {
                statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
            }
            statusHtml += ' display: inline-block;">';
            statusHtml += '<i class="fas fa-circle" style="margin-left: 6px; font-size: 8px;"></i> ' + (projectData.status === '1' || projectData.status === 1 ? 'Ù†Ø´Ø·' : 'ØºÙŠØ± Ù†Ø´Ø·') + '</span>';
            $('#view_status').html(statusHtml);

            // ØªØ­Ø¶ÙŠØ± Ø²Ø± Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
            const editBtn = $('#viewEditBtn');
            editBtn.data('id', projectData.id);
            editBtn.data('company-project-id', $(this).data('company-project-id'));
            editBtn.data('client-id', $(this).data('client-id'));
            editBtn.data('name', $(this).data('name'));
            editBtn.data('location', projectData.location);
            editBtn.data('status', projectData.status);

            // ØªØ­Ø¶ÙŠØ± Ø²Ø± Ù…Ù†Ø§Ø¬Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
            $('#viewMinesBtn').attr('href', 'project_mines.php?project_id=' + projectData.id);

            $('#viewProjectModal').fadeIn(300);
        });

        // Ø¥ØºÙ„Ø§Ù‚ Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø®Ø§Ø±Ø¬ Modal
        $(window).on('click', function (e) {
            if (e.target.id === 'viewProjectModal') {
                closeViewModal();
            }
        });

        // Ø¥ØºÙ„Ø§Ù‚ Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#viewProjectModal').is(':visible')) {
                closeViewModal();
            }
        });

        // Ø§Ù„ØªØ¹Ø§Ù…Ù„ Ù…Ø¹ Ø²Ø± Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ Ù…Ù† Modal Ø§Ù„Ø¹Ø±Ø¶
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

        // Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø²Ø± ØªØ¹Ø¯ÙŠÙ„ Ù…Ù† Ø§Ù„Ø¬Ø¯ÙˆÙ„
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

        // Ø¹Ù†Ø¯ ØªÙ…Ø±ÙŠØ± Ø±Ù‚Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù‚ÙŠ Ø§Ù„ url
        $(document).ready(function () {
            // Ø¥Ø°Ø§ ØªÙ… ØªÙ…Ø±ÙŠØ± client_id ÙÙŠ Ø§Ù„Ø±Ø§Ø¨Ø·ØŒ Ø§ÙØªØ­ Ø§Ù„ÙÙˆØ±Ù… ØªÙ„Ù‚Ø§Ø¦ÙŠÙ‹Ø§
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');

            if (clientId) {
                $('#projectForm').show();
                $('#client_id').val(clientId);
            }
        });

        // ===== Ù…Ø¹Ø§Ù„Ø¬Ø§Øª Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ ÙˆØ§Ù„ØªØµØ¯ÙŠØ± =====
        
        // Ø²Ø± ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬
        $('#exportBtn').on('click', function() {
            window.location.href = 'download_projects_template.php';
        });

        // Ø²Ø± Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel
        $('#importBtn').on('click', function() {
            $('#importModal').modal('show');
        });

        // Ù…Ø¹Ø§Ù„Ø¬ Ø±ÙØ¹ Ø§Ù„Ù…Ù„Ù
        $('#importFileForm').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#projectFile')[0];
            if (!fileInput.files.length) {
                alert('ÙŠØ±Ø¬Ù‰ Ø§Ø®ØªÙŠØ§Ø± Ù…Ù„Ù');
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
                        alert('ØªÙ… Ø§Ø³ØªÙŠØ±Ø§Ø¯ ' + response.imported_count + ' Ù…Ø´Ø±ÙˆØ¹ Ø¨Ù†Ø¬Ø§Ø­!');
                        $('#importModal').modal('hide');
                        $('#projectsTable').DataTable().ajax.reload();
                        location.reload(); // Ø¥Ø¹Ø§Ø¯Ø© ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø© Ù„ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¬Ø¯ÙˆÙ„
                    } else {
                        let errorMsg = 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯:\n\n';
                        if (response.errors && response.errors.length > 0) {
                            response.errors.forEach(function(error) {
                                errorMsg += error + '\n';
                            });
                        }
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Ø­Ø¯Ø« Ø®Ø·Ø£ ÙÙŠ Ø§Ù„Ø§ØªØµØ§Ù„: ' + error);
                }
            });
        });

    })();
</script>

<!-- Modal Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ù„ÙØ§Øª -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel"><i class="fas fa-upload"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ Ù…Ù† Ù…Ù„Ù Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Ø¥ØºÙ„Ø§Ù‚"></button>
            </div>
            <div class="modal-body">
                <form id="importFileForm">
                    <div class="form-group">
                        <label for="projectFile">Ø§Ø®ØªØ± Ù…Ù„Ù Excel:</label>
                        <input type="file" class="form-control" id="projectFile" name="file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">Ø§Ù„Ù…Ù„ÙØ§Øª Ø§Ù„Ù…Ù‚Ø¨ÙˆÙ„Ø©: Excel (.xlsx, .xls)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ø¥ØºÙ„Ø§Ù‚</button>
                <?php if ($can_add): ?>
                    <button type="submit" form="importFileForm" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>

</html>

