<?php

// fix_comments.php - شغّله مرة واحدة ثم احذفه
$file ='../Clients/clients.php';
$content = file_get_contents($file);

// إعادة تفسير الترميز
$fixed = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

// إصلاح Mojibake الشائع
$map = [
    "\xC3\x98\xC2\xA7" => 'ا',
    "\xC3\x99\x84"     => 'ل',
    // أضف حسب الحاجة
];

$fixed = strtr($fixed, $map);
file_put_contents($file, $fixed);
echo "تم ✅";

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('project_mines_fix_mojibake_output')) {
    function project_mines_fix_mojibake_output($buffer)
    {
        $map = array(
            'Ø§' => 'ا', 'Ø¨' => 'ب', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ø­' => 'ح',
            'Ø®' => 'خ', 'Ø¯' => 'د', 'Ø°' => 'ذ', 'Ø±' => 'ر', 'Ø²' => 'ز', 'Ø³' => 'س',
            'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط', 'Ø¸' => 'ظ', 'Ø¹' => 'ع',
            'Øº' => 'غ', 'Ù' => 'ف', 'Ù‚' => 'ق', 'Ùƒ' => 'ك', 'Ù„' => 'ل', 'Ù…' => 'م',
            'Ù†' => 'ن', 'Ù‡' => 'ه', 'Ùˆ' => 'و', 'ÙŠ' => 'ي', 'Ù‰' => 'ى', 'Ø©' => 'ة',
            'Ø¡' => 'ء', 'Ø£' => 'أ', 'Ø¥' => 'إ', 'Ø¢' => 'آ', 'Ø¤' => 'ؤ', 'Ø¦' => 'ئ',
            'ØŒ' => '،', 'Ø›' => '؛', 'ØŸ' => '؟', 'âœ…' => '✅', 'âŒ' => '❌', 'â¸' => '⏸',
            'ðŸ”' => '🔐', 'ðŸ‘‹' => '👋', 'ðŸš€' => '🚀', 'ðŸ†' => '🏆'
        );

        return strtr($buffer, $map);
    }
}

ob_start('project_mines_fix_mojibake_output');

if (!function_exists('project_mines_table_has_column')) {
    function project_mines_table_has_column($conn, $tableName, $columnName)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res = @mysqli_query($conn, $sql);

        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('project_mines_redirect_with_msg')) {
    function project_mines_redirect_with_msg($project_id, $msg)
    {
        header('Location: project_mines.php?project_id=' . intval($project_id) . '&msg=' . urlencode($msg));
        exit();
    }
}

// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
$page_permissions = check_page_permissions($conn, 'Projects/project_mines.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$can_view) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+Ø§Ù„Ù…Ù†Ø§Ø¬Ù…+âŒ");
    exit();
}

if (empty($_SESSION['project_mines_csrf_token'])) {
    $_SESSION['project_mines_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$project_mines_csrf_token = $_SESSION['project_mines_csrf_token'];

$mines_has_is_deleted = project_mines_table_has_column($conn, 'mines', 'is_deleted');
$mines_has_deleted_at = project_mines_table_has_column($conn, 'mines', 'deleted_at');
$mines_has_deleted_by = project_mines_table_has_column($conn, 'mines', 'deleted_by');
$mines_has_company_id = project_mines_table_has_column($conn, 'mines', 'company_id');

if (!$mines_has_is_deleted || !$mines_has_deleted_at || !$mines_has_deleted_by) {
    $alter_parts = array();
    if (!$mines_has_is_deleted) {
        $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!$mines_has_deleted_at) {
        $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    }
    if (!$mines_has_deleted_by) {
        $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
    }
    if (!empty($alter_parts)) {
        @mysqli_query($conn, "ALTER TABLE mines " . implode(', ', $alter_parts));
    }

    $mines_has_is_deleted = project_mines_table_has_column($conn, 'mines', 'is_deleted');
    $mines_has_deleted_at = project_mines_table_has_column($conn, 'mines', 'deleted_at');
    $mines_has_deleted_by = project_mines_table_has_column($conn, 'mines', 'deleted_by');
}

$project_has_is_deleted = project_mines_table_has_column($conn, 'project', 'is_deleted');
$project_has_deleted_at = project_mines_table_has_column($conn, 'project', 'deleted_at');

$project_not_deleted_sql = '1=1';
if ($project_has_is_deleted) {
    $project_not_deleted_sql = 'is_deleted = 0';
} elseif ($project_has_deleted_at) {
    $project_not_deleted_sql = 'deleted_at IS NULL';
}

$mines_not_deleted_sql = '1=1';
if ($mines_has_is_deleted) {
    $mines_not_deleted_sql = 'is_deleted = 0';
} elseif ($mines_has_deleted_at) {
    $mines_not_deleted_sql = 'deleted_at IS NULL';
}

// Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù…Ø¹Ø±Ù Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ Ù…Ù† URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    header("Location: projects.php");
    exit();
}

// Ø¬Ù„Ø¨ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
$project_query = "SELECT * FROM project WHERE id = $project_id AND $project_not_deleted_sql LIMIT 1";
$project_result = mysqli_query($conn, $project_query);
$project = mysqli_fetch_assoc($project_result);

if (!$project) {
    die("Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯");
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø¥Ø¶Ø§ÙØ©/ØªØ¹Ø¯ÙŠÙ„ Ù…Ù†Ø¬Ù… Ø¹Ø¨Ø± POST (Ø¨Ø¯ÙˆÙ† AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mine_name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($project_mines_csrf_token, $posted_csrf)) {
        project_mines_redirect_with_msg($project_id, 'جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $mine_id = isset($_POST['mine_id']) ? intval($_POST['mine_id']) : 0;

    if ($mine_id > 0 && !$can_edit) {
        header("Location: project_mines.php?project_id=$project_id&msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ù†Ø§Ø¬Ù…+âŒ");
        exit();
    } elseif ($mine_id <= 0 && !$can_add) {
        header("Location: project_mines.php?project_id=$project_id&msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¥Ø¶Ø§ÙØ©+Ù…Ù†Ø§Ø¬Ù…+Ø¬Ø¯ÙŠØ¯Ø©+âŒ");
        exit();
    }

    $mine_code = mysqli_real_escape_string($conn, trim($_POST['mine_code']));
    $mine_name = mysqli_real_escape_string($conn, trim($_POST['mine_name']));
    $manager_name = mysqli_real_escape_string($conn, trim($_POST['manager_name']));
    $mineral_type = mysqli_real_escape_string($conn, trim($_POST['mineral_type']));
    $mine_type = mysqli_real_escape_string($conn, $_POST['mine_type']);
    $mine_type_other = mysqli_real_escape_string($conn, trim($_POST['mine_type_other']));
    $ownership_type = mysqli_real_escape_string($conn, $_POST['ownership_type']);
    $ownership_type_other = mysqli_real_escape_string($conn, trim($_POST['ownership_type_other']));
    $mine_area = !empty($_POST['mine_area']) ? floatval($_POST['mine_area']) : null;
    $mine_area_unit = mysqli_real_escape_string($conn, $_POST['mine_area_unit']);
    $mining_depth = !empty($_POST['mining_depth']) ? floatval($_POST['mining_depth']) : null;
    $contract_nature = mysqli_real_escape_string($conn, $_POST['contract_nature']);
    $status = intval($_POST['status']);
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));

    if ($mine_id > 0) {
        // ØªØ¹Ø¯ÙŠÙ„ Ù…Ù†Ø¬Ù… Ù…ÙˆØ¬ÙˆØ¯
        $mine_area_value = $mine_area !== null ? $mine_area : "NULL";
        $mining_depth_value = $mining_depth !== null ? $mining_depth : "NULL";

        $update_query = "UPDATE mines SET 
            mine_code = '$mine_code',
            mine_name = '$mine_name',
            manager_name = '$manager_name',
            mineral_type = '$mineral_type',
            mine_type = '$mine_type',
            mine_type_other = '$mine_type_other',
            ownership_type = '$ownership_type',
            ownership_type_other = '$ownership_type_other',
            mine_area = $mine_area_value,
            mine_area_unit = '$mine_area_unit',
            mining_depth = $mining_depth_value,
            contract_nature = '$contract_nature',
            status = $status,
            notes = '$notes'
            WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";

        if ($mines_has_company_id && $company_id > 0) {
            $update_query = "UPDATE mines SET 
            mine_code = '$mine_code',
            mine_name = '$mine_name',
            manager_name = '$manager_name',
            mineral_type = '$mineral_type',
            mine_type = '$mine_type',
            mine_type_other = '$mine_type_other',
            ownership_type = '$ownership_type',
            ownership_type_other = '$ownership_type_other',
            mine_area = $mine_area_value,
            mine_area_unit = '$mine_area_unit',
            mining_depth = $mining_depth_value,
            contract_nature = '$contract_nature',
            status = $status,
            notes = '$notes',
            company_id = $company_id
            WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";
        }
        
        if (mysqli_query($conn, $update_query)) {
            header("Location: project_mines.php?project_id=$project_id&msg=ØªÙ…+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ù†Ø¬Ù…+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit();
        } else {
            header("Location: project_mines.php?project_id=$project_id&msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„ØªØ¹Ø¯ÙŠÙ„+âŒ");
            exit();
        }
    } else {
        // Ø¥Ø¶Ø§ÙØ© Ù…Ù†Ø¬Ù… Ø¬Ø¯ÙŠØ¯
        $mine_area_value = $mine_area !== null ? $mine_area : "NULL";
        $mining_depth_value = $mining_depth !== null ? $mining_depth : "NULL";
        $created_by = $_SESSION['user']['id'];

        $insert_query = "INSERT INTO mines 
            (project_id, mine_code, mine_name, manager_name, mineral_type, mine_type, mine_type_other, 
             ownership_type, ownership_type_other, mine_area, mine_area_unit, mining_depth, contract_nature, 
             status, notes, created_by) 
            VALUES 
            ($project_id, '$mine_code', '$mine_name', '$manager_name', '$mineral_type', '$mine_type', 
             '$mine_type_other', '$ownership_type', '$ownership_type_other', $mine_area_value, '$mine_area_unit', 
             $mining_depth_value, '$contract_nature', $status, '$notes', $created_by)";

        if ($mines_has_company_id && $company_id > 0) {
            $insert_query = "INSERT INTO mines 
            (company_id, project_id, mine_code, mine_name, manager_name, mineral_type, mine_type, mine_type_other, 
             ownership_type, ownership_type_other, mine_area, mine_area_unit, mining_depth, contract_nature, 
             status, notes, created_by) 
            VALUES 
            ($company_id, $project_id, '$mine_code', '$mine_name', '$manager_name', '$mineral_type', '$mine_type', 
             '$mine_type_other', '$ownership_type', '$ownership_type_other', $mine_area_value, '$mine_area_unit', 
             $mining_depth_value, '$contract_nature', $status, '$notes', $created_by)";
        }

        if (mysqli_query($conn, $insert_query)) {
            header("Location: project_mines.php?project_id=$project_id&msg=ØªÙ…+Ø¥Ø¶Ø§ÙØ©+Ø§Ù„Ù…Ù†Ø¬Ù…+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit();
        } else {
            header("Location: project_mines.php?project_id=$project_id&msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø¥Ø¶Ø§ÙØ©+âŒ");
            exit();
        }
    }
}

// Ø­Ø°Ù Ù…Ù†Ø¬Ù…
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: project_mines.php?project_id=$project_id&msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø­Ø°Ù+Ø§Ù„Ù…Ù†Ø§Ø¬Ù…+âŒ");
        exit();
    }

    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (empty($delete_csrf) || !hash_equals($project_mines_csrf_token, $delete_csrf)) {
        project_mines_redirect_with_msg($project_id, 'جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $mine_id = intval($_GET['delete']);
    if (!$mines_has_is_deleted && !$mines_has_deleted_at) {
        project_mines_redirect_with_msg($project_id, 'تعذر تفعيل الحذف الناعم للمناجم حالياً ❌');
    }

    $delete_set = array("status = 0");
    if ($mines_has_is_deleted) {
        $delete_set[] = "is_deleted = 1";
    }
    if ($mines_has_deleted_at) {
        $delete_set[] = "deleted_at = NOW()";
    }
    if ($mines_has_deleted_by) {
        $deleted_by = intval($_SESSION['user']['id']);
        $delete_set[] = "deleted_by = $deleted_by";
    }
    $delete_query = "UPDATE mines SET " . implode(', ', $delete_set) . " WHERE id = $mine_id AND project_id = $project_id AND $mines_not_deleted_sql";

    if (mysqli_query($conn, $delete_query)) {
        project_mines_redirect_with_msg($project_id, 'تم حذف المنجم بنجاح ✅');
    } else {
        project_mines_redirect_with_msg($project_id, 'حدث خطأ أثناء الحذف ❌');
    }
    exit();
}

$page_title = "Ø§Ù„Ù…Ù†Ø§Ø¬Ù… - " . $project['name'];
include '../inheader.php';
// include '../insidebar.php';
?>

<link rel="stylesheet" href="../assets/css/main_admin_style.css" />

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(3, 16, 39, 0.6);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background-color: var(--surface);
        margin: 2% auto;
        padding: 0;
        border-radius: var(--radius-lg);
        width: 92%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--navy), var(--navy-l));
        color: #fff;
        padding: 16px 20px;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3,
    .modal-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .close,
    .close-modal {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        font-size: 26px;
        font-weight: bold;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform var(--ease), background var(--ease);
    }

    .close:hover,
    .close-modal:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 20px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        margin-bottom: 14px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 700;
        margin-bottom: 6px;
        color: var(--sub);
        font-size: .9rem;
    }

    .form-group label .required { color: var(--red); }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        font-size: .95rem;
        transition: border-color var(--ease), box-shadow var(--ease);
        font-family: 'Cairo', sans-serif;
        background: var(--bg);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(232,184,0,.14);
        background: #fff;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--navy), var(--navy-l));
        color: #fff;
        padding: 12px 20px;
        border: none;
        border-radius: var(--radius);
        cursor: pointer;
        font-size: 1rem;
        font-weight: 800;
        width: 100%;
        margin-top: 8px;
        transition: transform var(--ease), box-shadow var(--ease);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .view-modal-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }

    .view-item {
        padding: 12px 14px;
        background: var(--bg);
        border-radius: var(--radius);
        border-right: 3px solid var(--gold);
        box-shadow: var(--shadow-sm);
    }

    .view-item-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--sub);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.3px;
    }

    .view-item-value {
        font-size: .95rem;
        font-weight: 800;
        color: var(--navy);
        word-break: break-word;
    }

    .conditional-field { display: none; }

    .alert {
        padding: 12px 16px;
        border-radius: var(--radius);
        margin-bottom: 14px;
        font-weight: 700;
        box-shadow: var(--shadow-sm);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .alert-success {
        background: var(--green-soft);
        color: var(--green);
        border-right: 4px solid var(--green);
    }

    .alert-error {
        background: var(--red-soft);
        color: var(--red);
        border-right: 4px solid var(--red);
    }

    .modal-footer .action-btn {
        width: auto;
        height: auto;
        padding: 8px 14px;
        border-radius: 10px;
        gap: 6px;
    }
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-mountain"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ù†Ø§Ø¬Ù… - <?php echo htmlspecialchars($project['name']); ?>
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="projects.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ù†Ø¬Ù… Ø¬Ø¯ÙŠØ¯
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], 'âœ…') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ù†Ø¬Ù… -->
    <?php if ($can_add || $can_edit): ?>
    <form id="mineForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">Ø¥Ø¶Ø§ÙØ© Ù…Ù†Ø¬Ù… Ø¬Ø¯ÙŠØ¯</span></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="mine_id" id="mine_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($project_mines_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ù†Ø¬Ù… *</label>
                        <input type="text" name="mine_code" id="mine_code" placeholder="Ù…Ø«Ø§Ù„: MINE-001" required />
                    </div>
                    <div>
                        <label><i class="fas fa-mountain"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ù†Ø¬Ù… *</label>
                        <input type="text" name="mine_name" id="mine_name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ù…Ù†Ø¬Ù…" required />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> Ø§Ø³Ù… Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="text" name="manager_name" id="manager_name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ù…Ø¯ÙŠØ±" />
                    </div>
                    <div>
                        <label><i class="fas fa-gem"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ù†</label>
                        <input type="text" name="mineral_type" id="mineral_type" placeholder="Ù…Ø«Ø§Ù„: Ø°Ù‡Ø¨ØŒ ÙØ¶Ø©ØŒ Ù†Ø­Ø§Ø³" />
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù… *</label>
                        <select name="mine_type" id="mine_type" required onchange="toggleOtherField('mine_type')">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="Ø­ÙØ±Ø© Ù…ÙØªÙˆØ­Ø©">Ø­ÙØ±Ø© Ù…ÙØªÙˆØ­Ø©</option>
                            <option value="ØªØ­Øª Ø£Ø±Ø¶ÙŠ">ØªØ­Øª Ø£Ø±Ø¶ÙŠ</option>
                            <option value="Ø¢Ø¨Ø§Ø±">Ø¢Ø¨Ø§Ø±</option>
                            <option value="Ù…Ù‡Ø¬ÙˆØ±">Ù…Ù‡Ø¬ÙˆØ±</option>
                            <option value="Ù…Ø¬Ù…Ø¹ Ù…Ø¹Ø§Ù„Ø¬Ø©/ØªØ±ÙƒÙŠØ²">Ù…Ø¬Ù…Ø¹ Ù…Ø¹Ø§Ù„Ø¬Ø©/ØªØ±ÙƒÙŠØ²</option>
                            <option value="Ù…ÙˆÙ‚Ø¹ ØªØ®Ø²ÙŠÙ†/Ù…Ø³ØªÙˆØ¯Ø¹">Ù…ÙˆÙ‚Ø¹ ØªØ®Ø²ÙŠÙ†/Ù…Ø³ØªÙˆØ¯Ø¹</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>
                    <div id="mine_type_other_div" style="display: none;">
                        <label><i class="fas fa-info-circle"></i> ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="text" name="mine_type_other" id="mine_type_other" />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ© *</label>
                        <select name="ownership_type" id="ownership_type" required onchange="toggleOtherField('ownership_type')">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="ØªØ¹Ø¯ÙŠÙ† Ø£Ù‡Ù„ÙŠ/ØªÙ‚Ù„ÙŠØ¯ÙŠ">ØªØ¹Ø¯ÙŠÙ† Ø£Ù‡Ù„ÙŠ/ØªÙ‚Ù„ÙŠØ¯ÙŠ</option>
                            <option value="Ø´Ø±ÙƒØ© Ø³ÙˆØ¯Ø§Ù†ÙŠØ© Ø®Ø§ØµØ©">Ø´Ø±ÙƒØ© Ø³ÙˆØ¯Ø§Ù†ÙŠØ© Ø®Ø§ØµØ©</option>
                            <option value="Ø´Ø±ÙƒØ© Ø­ÙƒÙˆÙ…ÙŠØ©/Ù‚Ø·Ø§Ø¹ Ø¹Ø§Ù…">Ø´Ø±ÙƒØ© Ø­ÙƒÙˆÙ…ÙŠØ©/Ù‚Ø·Ø§Ø¹ Ø¹Ø§Ù…</option>
                            <option value="Ø´Ø±ÙƒØ© Ø£Ø¬Ù†Ø¨ÙŠØ©">Ø´Ø±ÙƒØ© Ø£Ø¬Ù†Ø¨ÙŠØ©</option>
                            <option value="Ù…Ø´Ø±ÙˆØ¹ Ù…Ø´ØªØ±Ùƒ (Ø³ÙˆØ¯Ø§Ù†ÙŠ-Ø£Ø¬Ù†Ø¨ÙŠ)">Ù…Ø´Ø±ÙˆØ¹ Ù…Ø´ØªØ±Ùƒ (Ø³ÙˆØ¯Ø§Ù†ÙŠ-Ø£Ø¬Ù†Ø¨ÙŠ)</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>
                    <div id="ownership_type_other_div" style="display: none;">
                        <label><i class="fas fa-info-circle"></i> ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ©</label>
                        <input type="text" name="ownership_type_other" id="ownership_type_other" />
                    </div>
                    <div>
                        <label><i class="fas fa-ruler-combined"></i> Ù…Ø³Ø§Ø­Ø© Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="number" step="0.01" name="mine_area" id="mine_area" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-arrows-alt-v"></i> ÙˆØ­Ø¯Ø© Ù‚ÙŠØ§Ø³ Ø§Ù„Ù…Ø³Ø§Ø­Ø©</label>
                        <select name="mine_area_unit" id="mine_area_unit">
                            <option value="Ù‡ÙƒØªØ§Ø±">Ù‡ÙƒØªØ§Ø±</option>
                            <option value="ÙƒÙ…Â²">ÙƒÙ…Â²</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-height"></i> Ø¹Ù…Ù‚ Ø§Ù„ØªØ¹Ø¯ÙŠÙ† (Ù…ØªØ±)</label>
                        <input type="number" step="0.01" name="mining_depth" id="mining_depth" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-signature"></i> Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù‚Ø¯</label>
                        <select name="contract_nature" id="contract_nature">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="Ù…ÙˆØ¸Ù Ù…Ø¨Ø§Ø´Ø± Ù„Ø¯Ù‰ Ø§Ù„Ù…Ø§Ù„Ùƒ">Ù…ÙˆØ¸Ù Ù…Ø¨Ø§Ø´Ø± Ù„Ø¯Ù‰ Ø§Ù„Ù…Ø§Ù„Ùƒ</option>
                            <option value="Ù…Ù‚Ø§ÙˆÙ„/Ø´Ø±ÙƒØ© Ù…Ù‚Ø§ÙˆÙ„Ø§Øª">Ù…Ù‚Ø§ÙˆÙ„/Ø´Ø±ÙƒØ© Ù…Ù‚Ø§ÙˆÙ„Ø§Øª</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø© *</label>
                        <select name="status" id="status" required>
                            <option value="1">Ù†Ø´Ø· âœ…</option>
                            <option value="0">ØºÙŠØ± Ù†Ø´Ø·</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label><i class="fas fa-sticky-note"></i> Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</label>
                    <textarea name="notes" id="notes" placeholder="Ø£ÙŠ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ© Ø¹Ù† Ø§Ù„Ù…Ù†Ø¬Ù…..." style="width: 100%; padding: 10px; border: 1.5px solid var(--border); border-radius: var(--radius); font-family: 'Cairo', sans-serif; resize: vertical; min-height: 100px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" style="background: linear-gradient(135deg, var(--navy), var(--navy-l)); color: #fff; padding: 12px 20px; border: none; border-radius: var(--radius); cursor: pointer; font-size: 1rem; font-weight: 800; flex: 1;">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
                    </button>
                    <button type="button" onclick="toggleForm()" style="background: #dc3545; color: #fff; padding: 12px 20px; border: none; border-radius: var(--radius); cursor: pointer; font-size: 1rem; font-weight: 800;">
                        <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
            <div class="card-header">
                <h5><i class="fas fa-list-alt"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ù†Ø§Ø¬Ù…</h5>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table id="minesTable" class="display nowrap" style="width:100%; margin-top: 10px;">
          <thead>
                <tr>
                    <th>#</th>
                    <th>ÙƒÙˆØ¯ Ø§Ù„Ù…Ù†Ø¬Ù…</th>
                    <th>Ø§Ø³Ù… Ø§Ù„Ù…Ù†Ø¬Ù…</th>
                    <th>Ø§Ù„Ù…Ø¯ÙŠØ±</th>
                    <th>Ø§Ù„Ù…Ø¹Ø¯Ù†</th>
                    <th>Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù…</th>
                    <th>Ø§Ù„Ù…Ø³Ø§Ø­Ø©</th>
                    <th>Ø§Ù„Ø¹Ù…Ù‚ (Ù…)</th>
                    <th> Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯ </th>
                    <th> Ø§Ù„Ø¹Ù‚ÙˆØ¯ </th>
                    <th>Ø§Ù„Ø­Ø§Ù„Ø©</th>
                    <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $mines_query = "SELECT m.*, 
                               (SELECT COUNT(*) FROM contracts c WHERE c.mine_id = m.id) AS contract_count
                               FROM mines m
                               WHERE m.project_id = $project_id AND " . str_replace('is_deleted', 'm.is_deleted', str_replace('deleted_at', 'm.deleted_at', $mines_not_deleted_sql)) . "
                               ORDER BY m.created_at DESC";
                $mines_result = mysqli_query($conn, $mines_query);
                $counter = 1;

                while ($mine = mysqli_fetch_assoc($mines_result)) {
                    $status_badge = $mine['status'] == 1 ?
                        '<span class="status-active">Ù†Ø´Ø·</span>' :
                        '<span class="status-inactive">ØºÙŠØ± Ù†Ø´Ø·</span>';

                    $area_display = $mine['mine_area'] ?
                        number_format($mine['mine_area'], 2) . ' ' . $mine['mine_area_unit'] :
                        '-';

                    $depth_display = $mine['mining_depth'] ?
                        number_format($mine['mining_depth'], 2) . ' Ù…' :
                        '-';

                    echo "<tr>";
                    echo "<td>{$counter}</td>";
                    echo "<td>{$mine['mine_code']}</td>";
                    echo "<td>{$mine['mine_name']}</td>";
                    echo "<td>" . ($mine['manager_name'] ?: '-') . "</td>";
                    echo "<td>" . ($mine['mineral_type'] ?: '-') . "</td>";
                    echo "<td>{$mine['mine_type']}</td>";
                    echo "<td>{$area_display}</td>";
                    echo "<td>{$depth_display}</td>";
                    $contracts_count = isset($mine['contract_count']) ? intval($mine['contract_count']) : 0;
                    echo "<td>{$contracts_count}</td>"; 
                    echo "<td> 
                     <a href='../Contracts/contracts.php?id=" . $mine['id'] . "' 
                               class='action-btn contracts'
                               title='Ø¹Ø±Ø¶ Ø¹Ù‚ÙˆØ¯ Ø§Ù„Ù…Ù†Ø¬Ù…'>
                               <i class='fas fa-file-contract'></i>
                            </a>
                    </td>";
                    echo "<td>{$status_badge}</td>";
                    echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)' class='action-btn view' onclick='openViewModal(" . json_encode($mine) . ")' title='Ø¹Ø±Ø¶'>
                                    <i class='fas fa-eye'></i>
                                </a>";

                    if ($can_edit) {
                        echo "<a href='javascript:void(0)' class='action-btn edit' onclick='editMine(" . json_encode($mine) . ")' title='ØªØ¹Ø¯ÙŠÙ„'>
                                    <i class='fas fa-edit'></i>
                                </a>";
                    }

                    if ($can_delete) {
                        echo "<a href='javascript:void(0)' class='action-btn delete' onclick='deleteMine({$mine['id']})' title='Ø­Ø°Ù'>
                                    <i class='fas fa-trash-alt'></i>
                                </a>";
                    }

                    echo "</div>
                          </td>";
                    echo "</tr>";
                    $counter++;
                }
                ?>
            </tbody>
        </table>
            </div>
        </div>

<!-- Modal Ø¹Ø±Ø¶ Ø§Ù„Ù…Ù†Ø¬Ù… -->
<div id="viewMineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> Ø¹Ø±Ø¶ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù†Ø¬Ù…</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_mine_code">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-mountain"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_mine_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_manager_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-gem"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ù†</div>
                    <div class="view-item-value" id="view_mineral_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_mine_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_mine_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ©</div>
                    <div class="view-item-value" id="view_ownership_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ©</div>
                    <div class="view-item-value" id="view_ownership_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-ruler-combined"></i> Ù…Ø³Ø§Ø­Ø© Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                    <div class="view-item-value" id="view_mine_area">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-arrows-alt-v"></i> Ø¹Ù…Ù‚ Ø§Ù„ØªØ¹Ø¯ÙŠÙ†</div>
                    <div class="view-item-value" id="view_mining_depth">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-signature"></i> Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù‚Ø¯</div>
                    <div class="view-item-value" id="view_contract_nature">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-sticky-note"></i> Ù…Ù„Ø§Ø­Ø¸Ø§Øª</div>
                    <div class="view-item-value" id="view_notes">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 0 20px 20px;">
            <a id="view_contracts_btn" class="action-btn contracts" style="text-decoration: none;">
                <i class="fas fa-file-contract"></i> Ø¹Ù‚ÙˆØ¯Ø§Øª Ø§Ù„Ù…Ù†Ø¬Ù…
            </a>
            <?php if ($can_edit): ?>
            <button type="button" class="action-btn edit" onclick="openEditFromView()">
                <i class="fas fa-edit"></i> ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†Ø¬Ù…
            </button>
            <?php endif; ?>
            <button type="button" class="action-btn delete" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
            </button>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="mineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Ø¥Ø¶Ø§ÙØ© Ù…Ù†Ø¬Ù… Ø¬Ø¯ÙŠØ¯</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="mineForm">
                <input type="hidden" id="mine_id" name="mine_id">
                <input type="hidden" id="action" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ÙƒÙˆØ¯/Ø±Ù…Ø² Ø§Ù„Ù…Ù†Ø¬Ù… <span class="required">*</span></label>
                        <input type="text" id="mine_code" name="mine_code" required>
                    </div>

                    <div class="form-group">
                        <label>Ø§Ø³Ù… Ø§Ù„Ù…Ù†Ø¬Ù… <span class="required">*</span></label>
                        <input type="text" id="mine_name" name="mine_name" required>
                    </div>

                    <div class="form-group">
                        <label>Ø§Ø³Ù… Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="text" id="manager_name" name="manager_name">
                    </div>

                    <div class="form-group">
                        <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ù†</label>
                        <input type="text" id="mineral_type" name="mineral_type" placeholder="Ù…Ø«Ø§Ù„: Ø°Ù‡Ø¨ØŒ ÙØ¶Ø©ØŒ Ù†Ø­Ø§Ø³">
                    </div>

                    <div class="form-group">
                        <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù… <span class="required">*</span></label>
                        <select id="mine_type" name="mine_type" required onchange="toggleOtherField('mine_type')">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="Ø­ÙØ±Ø© Ù…ÙØªÙˆØ­Ø©">Ø­ÙØ±Ø© Ù…ÙØªÙˆØ­Ø©</option>
                            <option value="ØªØ­Øª Ø£Ø±Ø¶ÙŠ">ØªØ­Øª Ø£Ø±Ø¶ÙŠ</option>
                            <option value="Ø¢Ø¨Ø§Ø±">Ø¢Ø¨Ø§Ø±</option>
                            <option value="Ù…Ù‡Ø¬ÙˆØ±">Ù…Ù‡Ø¬ÙˆØ±</option>
                            <option value="Ù…Ø¬Ù…Ø¹ Ù…Ø¹Ø§Ù„Ø¬Ø©/ØªØ±ÙƒÙŠØ²">Ù…Ø¬Ù…Ø¹ Ù…Ø¹Ø§Ù„Ø¬Ø©/ØªØ±ÙƒÙŠØ²</option>
                            <option value="Ù…ÙˆÙ‚Ø¹ ØªØ®Ø²ÙŠÙ†/Ù…Ø³ØªÙˆØ¯Ø¹">Ù…ÙˆÙ‚Ø¹ ØªØ®Ø²ÙŠÙ†/Ù…Ø³ØªÙˆØ¯Ø¹</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="mine_type_other_div">
                        <label>ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="text" id="mine_type_other" name="mine_type_other">
                    </div>

                    <div class="form-group">
                        <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ© <span class="required">*</span></label>
                        <select id="ownership_type" name="ownership_type" required
                            onchange="toggleOtherField('ownership_type')">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="ØªØ¹Ø¯ÙŠÙ† Ø£Ù‡Ù„ÙŠ/ØªÙ‚Ù„ÙŠØ¯ÙŠ">ØªØ¹Ø¯ÙŠÙ† Ø£Ù‡Ù„ÙŠ/ØªÙ‚Ù„ÙŠØ¯ÙŠ</option>
                            <option value="Ø´Ø±ÙƒØ© Ø³ÙˆØ¯Ø§Ù†ÙŠØ© Ø®Ø§ØµØ©">Ø´Ø±ÙƒØ© Ø³ÙˆØ¯Ø§Ù†ÙŠØ© Ø®Ø§ØµØ©</option>
                            <option value="Ø´Ø±ÙƒØ© Ø­ÙƒÙˆÙ…ÙŠØ©/Ù‚Ø·Ø§Ø¹ Ø¹Ø§Ù…">Ø´Ø±ÙƒØ© Ø­ÙƒÙˆÙ…ÙŠØ©/Ù‚Ø·Ø§Ø¹ Ø¹Ø§Ù…</option>
                            <option value="Ø´Ø±ÙƒØ© Ø£Ø¬Ù†Ø¨ÙŠØ©">Ø´Ø±ÙƒØ© Ø£Ø¬Ù†Ø¨ÙŠØ©</option>
                            <option value="Ù…Ø´Ø±ÙˆØ¹ Ù…Ø´ØªØ±Ùƒ (Ø³ÙˆØ¯Ø§Ù†ÙŠ-Ø£Ø¬Ù†Ø¨ÙŠ)">Ù…Ø´Ø±ÙˆØ¹ Ù…Ø´ØªØ±Ùƒ (Ø³ÙˆØ¯Ø§Ù†ÙŠ-Ø£Ø¬Ù†Ø¨ÙŠ)</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="ownership_type_other_div">
                        <label>ØªÙØ§ØµÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„ÙƒÙŠØ©</label>
                        <input type="text" id="ownership_type_other" name="ownership_type_other">
                    </div>

                    <div class="form-group">
                        <label>Ù…Ø³Ø§Ø­Ø© Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <input type="number" step="0.01" id="mine_area" name="mine_area" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>ÙˆØ­Ø¯Ø© Ù‚ÙŠØ§Ø³ Ø§Ù„Ù…Ø³Ø§Ø­Ø©</label>
                        <select id="mine_area_unit" name="mine_area_unit">
                            <option value="Ù‡ÙƒØªØ§Ø±">Ù‡ÙƒØªØ§Ø±</option>
                            <option value="ÙƒÙ…Â²">ÙƒÙ…Â²</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ø¹Ù…Ù‚ Ø§Ù„ØªØ¹Ø¯ÙŠÙ† (Ù…ØªØ±)</label>
                        <input type="number" step="0.01" id="mining_depth" name="mining_depth" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù‚Ø¯</label>
                        <select id="contract_nature" name="contract_nature">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <option value="Ù…ÙˆØ¸Ù Ù…Ø¨Ø§Ø´Ø± Ù„Ø¯Ù‰ Ø§Ù„Ù…Ø§Ù„Ùƒ">Ù…ÙˆØ¸Ù Ù…Ø¨Ø§Ø´Ø± Ù„Ø¯Ù‰ Ø§Ù„Ù…Ø§Ù„Ùƒ</option>
                            <option value="Ù…Ù‚Ø§ÙˆÙ„/Ø´Ø±ÙƒØ© Ù…Ù‚Ø§ÙˆÙ„Ø§Øª">Ù…Ù‚Ø§ÙˆÙ„/Ø´Ø±ÙƒØ© Ù…Ù‚Ø§ÙˆÙ„Ø§Øª</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ø§Ù„Ø­Ø§Ù„Ø© <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="1">Ù†Ø´Ø·</option>
                            <option value="0">ØºÙŠØ± Ù†Ø´Ø·</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</label>
                    <textarea id="notes" name="notes" placeholder="Ø£ÙŠ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ© Ø¹Ù† Ø§Ù„Ù…Ù†Ø¬Ù…..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        $('#minesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });

        // Toggle Form
        $('#toggleForm').click(function() {
            resetForm();
            $('#mineForm').slideDown(400);
            $('html, body').animate({
                scrollTop: $('#mineForm').offset().top - 100
            }, 500);
        });
    });

    // Toggle Form Show/Hide
    function toggleForm() {
        $('#mineForm').slideUp(300);
    }

    // Reset Form
    function resetForm() {
        $('#mineForm')[0].reset();
        $('#mine_id').val('');
        $('#formTitle').text('Ø¥Ø¶Ø§ÙØ© Ù…Ù†Ø¬Ù… Ø¬Ø¯ÙŠØ¯');
        document.querySelectorAll('[id*="_other_div"]').forEach(field => {
            field.style.display = 'none';
        });
    }

    // Edit Mine - Load data and show form
    function editMine(mine) {
        const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
        if (!canEdit) {
            alert('Ù„Ø§ ØªÙˆØ¬Ø¯ ØµÙ„Ø§Ø­ÙŠØ© ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†Ø§Ø¬Ù…');
            return;
        }

        $('#mine_id').val(mine.id);
        $('#mine_code').val(mine.mine_code);
        $('#mine_name').val(mine.mine_name);
        $('#manager_name').val(mine.manager_name || '');
        $('#mineral_type').val(mine.mineral_type || '');
        $('#mine_type').val(mine.mine_type);
        $('#mine_type_other').val(mine.mine_type_other || '');
        $('#ownership_type').val(mine.ownership_type);
        $('#ownership_type_other').val(mine.ownership_type_other || '');
        $('#mine_area').val(mine.mine_area || '');
        $('#mine_area_unit').val(mine.mine_area_unit);
        $('#mining_depth').val(mine.mining_depth || '');
        $('#contract_nature').val(mine.contract_nature || '');
        $('#status').val(mine.status);
        $('#notes').val(mine.notes || '');

        toggleOtherField('mine_type');
        toggleOtherField('ownership_type');

        $('#formTitle').text('ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù†Ø¬Ù…');
        closeViewModal();
        $('#mineForm').slideDown(400);
        $('html, body').animate({
            scrollTop: $('#mineForm').offset().top - 100
        }, 500);
    }

    // Open View Modal
    function openViewModal(mine) {
        window.currentViewMine = mine;
        $('#view_mine_code').text(mine.mine_code || '-');
        $('#view_mine_name').text(mine.mine_name || '-');
        $('#view_manager_name').text(mine.manager_name || '-');
        $('#view_mineral_type').text(mine.mineral_type || '-');
        $('#view_mine_type').text(mine.mine_type || '-');
        $('#view_mine_type_other').text(mine.mine_type_other || '-');
        $('#view_ownership_type').text(mine.ownership_type || '-');
        $('#view_ownership_type_other').text(mine.ownership_type_other || '-');

        const areaText = mine.mine_area ? `${parseFloat(mine.mine_area).toFixed(2)} ${mine.mine_area_unit || ''}` : '-';
        const depthText = mine.mining_depth ? `${parseFloat(mine.mining_depth).toFixed(2)} Ù…` : '-';

        $('#view_mine_area').text(areaText.trim() || '-');
        $('#view_mining_depth').text(depthText);
        $('#view_contract_nature').text(mine.contract_nature || '-');
        $('#view_status').text((String(mine.status) === '1') ? 'Ù†Ø´Ø·' : 'ØºÙŠØ± Ù†Ø´Ø·');
        $('#view_notes').text(mine.notes || '-');

        $('#view_contracts_btn').attr('href', '../Contracts/contracts.php?id=' + mine.id);
        $('#viewMineModal').fadeIn(300);
    }

    // Edit from View Modal
    function openEditFromView() {
        if (window.currentViewMine) {
            closeViewModal();
            editMine(window.currentViewMine);
        }
    }

    // Close View Modal
    function closeViewModal() {
        $('#viewMineModal').fadeOut(300);
    }

    // Delete Mine
    function deleteMine(id) {
        const canDelete = <?php echo $can_delete ? 'true' : 'false'; ?>;
        if (!canDelete) {
            alert('Ù„Ø§ ØªÙˆØ¬Ø¯ ØµÙ„Ø§Ø­ÙŠØ© Ø­Ø°Ù Ø§Ù„Ù…Ù†Ø§Ø¬Ù…');
            return;
        }

        if (confirm('Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ù…Ù†Ø¬Ù…ØŸ')) {
            window.location.href = 'project_mines.php?project_id=<?php echo $project_id; ?>&delete=' + id + '&csrf_token=<?php echo urlencode($project_mines_csrf_token); ?>';
        }
    }

    // Toggle Conditional Fields
    function toggleOtherField(fieldType) {
        const select = document.getElementById(fieldType);
        const otherDiv = document.getElementById(fieldType + '_other_div');

        if (select.value === 'Ø£Ø®Ø±Ù‰') {
            otherDiv.style.display = 'block';
        } else {
            otherDiv.style.display = 'none';
        }
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        const viewModal = document.getElementById('viewMineModal');
        if (event.target == viewModal) {
            closeViewModal();
        }
    }
</script>

</body>

</html>
