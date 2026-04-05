<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('clients_fix_mojibake_output')) {
    function clients_fix_mojibake_output($buffer)
    {
        $map = array(
            'ا' => 'ا', 'Ø¨' => 'ب', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ø­' => 'ح',
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

ob_start('clients_fix_mojibake_output');

if (!function_exists('clients_table_has_column')) {
    function clients_table_has_column($conn, $tableName, $columnName)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
        $res = @mysqli_query($conn, $sql);

        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('clients_build_scope_sql')) {
    function clients_build_scope_sql($company_id, $clients_has_company_id, $alias)
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($clients_has_company_id) {
            return $prefix . "company_id = $company_id";
        }

        return "EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = " . $prefix . "created_by AND scope_u.company_id = $company_id)";
    }
}

if (!function_exists('clients_not_deleted_sql')) {
    function clients_not_deleted_sql($alias, $has_is_deleted, $has_deleted_at)
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($has_is_deleted) {
            return $prefix . "is_deleted = 0";
        }
        if ($has_deleted_at) {
            return $prefix . "deleted_at IS NULL";
        }

        return "1=1";
    }
}

if (!function_exists('clients_e')) {
    function clients_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clients_redirect_with_msg')) {
    function clients_redirect_with_msg($msg)
    {
        header('Location: clients.php?msg=' . urlencode($msg));
        exit();
    }
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

$clients_has_company_id = clients_table_has_column($conn, 'clients', 'company_id');
$clients_has_is_deleted = clients_table_has_column($conn, 'clients', 'is_deleted');
$clients_has_deleted_at = clients_table_has_column($conn, 'clients', 'deleted_at');
$clients_has_deleted_by = clients_table_has_column($conn, 'clients', 'deleted_by');

if (!$clients_has_is_deleted || !$clients_has_deleted_at || !$clients_has_deleted_by) {
    $alter_parts = array();
    if (!$clients_has_is_deleted) {
        $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!$clients_has_deleted_at) {
        $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    }
    if (!$clients_has_deleted_by) {
        $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
    }

    if (!empty($alter_parts)) {
        @mysqli_query($conn, "ALTER TABLE clients " . implode(', ', $alter_parts));
    }

    $clients_has_is_deleted = clients_table_has_column($conn, 'clients', 'is_deleted');
    $clients_has_deleted_at = clients_table_has_column($conn, 'clients', 'deleted_at');
    $clients_has_deleted_by = clients_table_has_column($conn, 'clients', 'deleted_by');
}

$scope_clients_sql = clients_build_scope_sql($company_id, $clients_has_company_id, 'cc');
$scope_clients_update_sql = clients_build_scope_sql($company_id, $clients_has_company_id, '');
$not_deleted_cc_sql = clients_not_deleted_sql('cc', $clients_has_is_deleted, $clients_has_deleted_at);
$not_deleted_plain_sql = clients_not_deleted_sql('', $clients_has_is_deleted, $clients_has_deleted_at);

if (empty($_SESSION['clients_csrf_token'])) {
    $_SESSION['clients_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$clients_csrf_token = $_SESSION['clients_csrf_token'];

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” اÙ„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„اØ­ÙŠاØª اÙ„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ù„Ù‰ ÙˆØ­Ø¯Ø© اÙ„Ø¹Ù…Ù„اØ¡
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// اÙ„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù…Ø¹Ø±Ù ÙˆØ­Ø¯Ø© اÙ„Ø¹Ù…Ù„اØ¡ Ù…Ù† Ø¬Ø¯ÙˆÙ„ modules
$module_query = "SELECT id FROM modules 
                      WHERE code = 'Clients/clients.php' 
                          OR code = 'clients' 
                          OR code LIKE '%clients.php%'
                          OR name LIKE '%عملاء%'
                      LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

// اÙ„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ ØµÙ„اØ­ÙŠاØª اÙ„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ù„Ù‰ Ù‡Ø°Ù‡ اÙ„ÙˆØ­Ø¯Ø©
$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;

if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view = $perms['can_view'];
    $can_add = $perms['can_add'];
    $can_edit = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}

// Ù…Ù†Ø¹ اÙ„ÙˆØµÙˆÙ„ Ø¥Ø°ا Ù„Ù… ØªÙƒÙ† Ù‡Ù†اÙƒ ØµÙ„اØ­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض العملاء ❌'));
    exit();
}

// Ù…Ø¹اÙ„Ø¬Ø© Ø¥Ø¶اÙØ©/ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„ Ø¹Ø¨Ø± POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['client_name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($clients_csrf_token, $posted_csrf)) {
        clients_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    // اÙ„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„اØ­ÙŠØ© اÙ„ØªØ¹Ø¯ÙŠÙ„ Ø£Ùˆ اÙ„Ø¥Ø¶اÙØ©
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $is_editing = $client_id > 0;
    
    if ($is_editing && !$can_edit) {
        clients_redirect_with_msg('لا توجد صلاحية تعديل العملاء ❌');
    } elseif (!$is_editing && !$can_add) {
        clients_redirect_with_msg('لا توجد صلاحية إضافة عملاء جدد ❌');
    }

    $client_code_raw = isset($_POST['client_code']) ? trim($_POST['client_code']) : '';
    if ($client_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $client_code_raw)) {
        clients_redirect_with_msg('كود العميل غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    $status_raw = isset($_POST['status']) ? trim($_POST['status']) : '';
    $allowed_status = array('نشط', 'متوقف');
    if (!in_array($status_raw, $allowed_status, true)) {
        clients_redirect_with_msg('حالة العميل غير صالحة ❌');
    }

    $client_code = mysqli_real_escape_string($conn, $client_code_raw);
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $status_raw);
    $created_by = intval($_SESSION['user']['id']);

    if ($client_id > 0) {
        $owner_check_query = "SELECT cc.id FROM clients cc WHERE cc.id = $client_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1";
        $owner_check_result = mysqli_query($conn, $owner_check_query);
        if (!$owner_check_result || mysqli_num_rows($owner_check_result) === 0) {
            clients_redirect_with_msg('لا يمكنك تعديل عميل لا يتبع لشركتك ❌');
        }

        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND cc.id != $client_id AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            clients_redirect_with_msg('كود العميل موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE clients SET 
            client_code = '$client_code',
            client_name = '$client_name',
            entity_type = '$entity_type',
            sector_category = '$sector_category',
            phone = '$phone',
            email = '$email',
            whatsapp = '$whatsapp',
            status = '$status'
            WHERE id = $client_id AND $scope_clients_update_sql AND $not_deleted_plain_sql";

        if ($clients_has_company_id) {
            $update_query = "UPDATE clients SET 
            client_code = '$client_code',
            client_name = '$client_name',
            entity_type = '$entity_type',
            sector_category = '$sector_category',
            phone = '$phone',
            email = '$email',
            whatsapp = '$whatsapp',
            status = '$status',
            company_id = '$company_id'
            WHERE id = $client_id AND $scope_clients_update_sql AND $not_deleted_plain_sql";
        }
        
        if (mysqli_query($conn, $update_query)) {
            clients_redirect_with_msg('تم تعديل العميل بنجاح ✅');
        } else {
            error_log('clients.php update failed: ' . mysqli_error($conn));
            clients_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND $scope_clients_sql AND $not_deleted_cc_sql";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            clients_redirect_with_msg('كود العميل موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO clients 
            (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by) 
            VALUES 
            ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by')";

        if ($clients_has_company_id) {
            $insert_query = "INSERT INTO clients 
            (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by, company_id) 
            VALUES 
            ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by', '$company_id')";
        }

        if (mysqli_query($conn, $insert_query)) {
            clients_redirect_with_msg('تم إضافة العميل بنجاح ✅');
        } else {
            error_log('clients.php insert failed: ' . mysqli_error($conn));
            clients_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}

// Ù…Ø¹اÙ„Ø¬Ø© Ø­Ø°Ù اÙ„Ø¹Ù…ÙŠÙ„
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    
    // اÙ„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„اØ­ÙŠØ© اÙ„Ø­Ø°Ù
    if (!$can_delete) {
        clients_redirect_with_msg('لا توجد صلاحية حذف العملاء ❌');
    }

    if (!$clients_has_is_deleted && !$clients_has_deleted_at) {
        clients_redirect_with_msg('تعذر تفعيل الحذف الناعم حالياً. راجع صلاحيات قاعدة البيانات ❌');
    }

    if (empty($delete_csrf) || !hash_equals($clients_csrf_token, $delete_csrf)) {
        clients_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $can_delete_scope_result = mysqli_query($conn, "SELECT cc.id FROM clients cc WHERE cc.id = $delete_id AND $scope_clients_sql AND $not_deleted_cc_sql LIMIT 1");
    if (!$can_delete_scope_result || mysqli_num_rows($can_delete_scope_result) === 0) {
        clients_redirect_with_msg('لا يمكنك حذف عميل لا يتبع لشركتك ❌');
    }

    $delete_set = array("status = 'متوقف'");
    if ($clients_has_is_deleted) {
        $delete_set[] = "is_deleted = 1";
    }
    if ($clients_has_deleted_at) {
        $delete_set[] = "deleted_at = NOW()";
    }
    if ($clients_has_deleted_by) {
        $deleted_by = intval($_SESSION['user']['id']);
        $delete_set[] = "deleted_by = $deleted_by";
    }

    $soft_delete_query = "UPDATE clients SET " . implode(', ', $delete_set) . " WHERE id = $delete_id AND $scope_clients_update_sql";
    if ($clients_has_is_deleted) {
        $soft_delete_query .= " AND is_deleted = 0";
    } elseif ($clients_has_deleted_at) {
        $soft_delete_query .= " AND deleted_at IS NULL";
    }

    if (mysqli_query($conn, $soft_delete_query)) {
        clients_redirect_with_msg('تم حذف العميل بنجاح ✅');
    }

    error_log('clients.php soft delete failed: ' . mysqli_error($conn));
    clients_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

$page_title = "قائمة العملاء";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome Ù…Ù† CDN Ù„Ø¶Ù…اÙ† Ø¸Ù‡ÙˆØ± اÙ„Ø£ÙŠÙ‚ÙˆÙ†اØª Ø¨Ø´ÙƒÙ„ ØµØ­ÙŠØ­ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users"></i></div>
            Ø¥Ø¯اØ±Ø© اÙ„Ø¹Ù…Ù„اØ¡
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶اÙØ© Ø¹Ù…ÙŠÙ„ Ø¬Ø¯ÙŠØ¯
                </a>
                <a href="javascript:void(0)" id="openImportModal" class="add-btn"
                    style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-color:transparent;">
                    <i class="fas fa-file-excel"></i> اØ³ØªÙŠØ±اØ¯ Ù…Ù† Excel
                </a>
            <?php else: ?>
                <button class="add-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶اÙØ© (Ø¨Ø¯ÙˆÙ† ØµÙ„اØ­ÙŠاØª)
                </button>
            <?php endif; ?>
            <a href="download_clients_template.php" class="add-btn"
                style="background:linear-gradient(135deg,var(--orange),#f59e0b);color:#fff;border-color:transparent;">
                <i class="fas fa-download"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel
            </a>
            <a href="download_clients_template_csv.php" class="add-btn"
                style="background:linear-gradient(135deg,var(--blue),#3b82f6);color:#fff;border-color:transparent;">
                <i class="fas fa-file-csv"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ CSV
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], 'âœ…') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo clients_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶اÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„ -->
    <form id="clientForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> Ø¥Ø¶اÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="client_id" id="client_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo clients_e($clients_csrf_token); ?>">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ اÙ„Ø¹Ù…ÙŠÙ„ *</label>
                        <input type="text" name="client_code" id="client_code" placeholder="Ù…Ø«اÙ„: CL-001" required 
                               pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> اØ³Ù… اÙ„Ø¹Ù…ÙŠÙ„ *</label>
                        <input type="text" name="client_name" id="client_name" placeholder="Ø£Ø¯Ø®Ù„ اØ³Ù… اÙ„Ø¹Ù…ÙŠÙ„" required />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> Ù†ÙˆØ¹ اÙ„ÙƒÙŠاÙ†</label>
                        <select name="entity_type" id="entity_type">
                            <option value="">-- اØ®ØªØ± Ù†ÙˆØ¹ اÙ„ÙƒÙŠاÙ† --</option>
                            <option value="Ø­ÙƒÙˆÙ…ÙŠ">Ø­ÙƒÙˆÙ…ÙŠ</option>
                            <option value="Ø®اØµ">Ø®اØµ</option>
                            <option value="Ù…Ø®ØªÙ„Ø·">Ù…Ø®ØªÙ„Ø·</option>
                            <option value="Ø¯ÙˆÙ„ÙŠ">Ø¯ÙˆÙ„ÙŠ</option>
                            <option value="ØºÙŠØ± Ø±Ø¨Ø­ÙŠ">ØºÙŠØ± Ø±Ø¨Ø­ÙŠ</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ اÙ„Ù‚Ø·اØ¹</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- اØ®ØªØ± اÙ„ØªØµÙ†ÙŠÙ --</option>
                            <option value="Ø¨Ù†ÙŠØ© ØªØ­ØªÙŠØ©">Ø¨Ù†ÙŠØ© ØªØ­ØªÙŠØ©</option>
                            <option value="Ù†ÙØ· ÙˆØºاØ²">Ù†ÙØ· ÙˆØºاØ²</option>
                            <option value="ØªØ¹Ø¯ÙŠÙ†">ØªØ¹Ø¯ÙŠÙ†</option>
                            <option value="Ø²Ø±اØ¹Ø©">Ø²Ø±اØ¹Ø©</option>
                            <option value="Ø®Ø¯Ù…اØª">Ø®Ø¯Ù…اØª</option>
                            <option value="ØªØ¬اØ±Ø©">ØªØ¬اØ±Ø©</option>
                            <option value="ØµÙ†اØ¹Ø©">ØµÙ†اØ¹Ø©</option>
                            <option value="Ø·اÙ‚Ø©">Ø·اÙ‚Ø©</option>
                            <option value="Ù…ÙŠاÙ‡ ÙˆØµØ±Ù ØµØ­ÙŠ">Ù…ÙŠاÙ‡ ÙˆØµØ±Ù ØµØ­ÙŠ</option>
                            <option value="Ù†Ù‚Ù„ ÙˆÙ…ÙˆاØµÙ„اØª">Ù†Ù‚Ù„ ÙˆÙ…ÙˆاØµÙ„اØª</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> Ø±Ù‚Ù… اÙ„Ù‡اØªÙ</label>
                        <input type="tel" name="phone" id="phone" placeholder="Ù…Ø«اÙ„: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-envelope"></i> اÙ„Ø¨Ø±ÙŠØ¯ اÙ„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com" />
                    </div>
                    <div>
                        <label><i class="fab fa-whatsapp"></i> ÙˆاØªØ³اØ¨</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="Ù…Ø«اÙ„: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> Ø­اÙ„Ø© اÙ„Ø¹Ù…ÙŠÙ„ *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>            
                    <button type="submit">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ اÙ„Ø¹Ù…ÙŠÙ„
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> Ø¬Ù…ÙŠØ¹ اÙ„Ø¹Ù…Ù„اØ¡</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th width="100"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ اÙ„Ø¹Ù…ÙŠÙ„</th>
                            <th><i class="fas fa-user"></i> اØ³Ù… اÙ„Ø¹Ù…ÙŠÙ„</th>
                            <th><i class="fas fa-building"></i> Ù†ÙˆØ¹ اÙ„ÙƒÙŠاÙ†</th>
                            <th><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ اÙ„Ù‚Ø·اØ¹</th>
                            <th><i class="fas fa-phone"></i> اÙ„Ù‡اØªÙ</th>
                            <th><i class="fas fa-toggle-on"></i> اÙ„Ø­اÙ„Ø©</th>
                            <th><i class="fas fa-cogs"></i> Ø¥Ø¬Ø±اØ¡اØª</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT cc.*, u.name as creator_name 
                                  FROM clients cc 
                                  LEFT JOIN users u ON cc.created_by = u.id 
                                  WHERE $scope_clients_sql AND $not_deleted_cc_sql
                                  ORDER BY cc.id DESC";
                        $result = mysqli_query($conn, $query);

                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td><strong style='font-family:monospace;letter-spacing:.03em'>" . clients_e($row['client_code']) . "</strong></td>";
                            echo "<td><a class='client-name-link' href='../Projects/projects.php?client_id=" . urlencode($row['id']) . "'>" . clients_e($row['client_name']) . "</a></td>";
                            echo "<td>" . clients_e($row['entity_type']) . "</td>";
                            echo "<td>" . clients_e($row['sector_category']) . "</td>";
                            echo "<td>" . clients_e($row['phone']) . "</td>";
                            if ($row['status'] == 'نشط') {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> متوقف</span></td>";
                            }

                            echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)' 
                                       class='action-btn view viewClientBtn' 
                                       data-id='" . $row['id'] . "'
                                       data-code='" . clients_e($row['client_code']) . "'
                                       data-name='" . clients_e($row['client_name']) . "'
                                       data-entity='" . clients_e($row['entity_type']) . "'
                                       data-sector='" . clients_e($row['sector_category']) . "'
                                       data-phone='" . clients_e($row['phone']) . "'
                                       data-email='" . clients_e($row['email']) . "'
                                       data-whatsapp='" . clients_e($row['whatsapp']) . "'
                                       data-status='" . clients_e($row['status']) . "'
                                       data-created='" . clients_e(isset($row['creator_name']) ? $row['creator_name'] : 'غير محدد') . "'
                                       title='Ø¹Ø±Ø¶ اÙ„ØªÙاØµÙŠÙ„'>
                                        <i class='fas fa-eye'></i>
                                    </a>";
                                    
                                    if ($can_edit) {
                                        echo "<a href='javascript:void(0)' 
                                           class='action-btn edit editClientBtn' 
                                           data-id='" . $row['id'] . "'
                                           data-code='" . clients_e($row['client_code']) . "'
                                           data-name='" . clients_e($row['client_name']) . "'
                                           data-entity='" . clients_e($row['entity_type']) . "'
                                           data-sector='" . clients_e($row['sector_category']) . "'
                                           data-phone='" . clients_e($row['phone']) . "'
                                           data-email='" . clients_e($row['email']) . "'
                                           data-whatsapp='" . clients_e($row['whatsapp']) . "'
                                           data-status='" . clients_e($row['status']) . "'
                                           title='ØªØ¹Ø¯ÙŠÙ„'>
                                            <i class='fas fa-edit'></i>
                                        </a>";
                                    }
                                    
                                    if ($can_delete) {
                                        echo "<a href='?delete_id=" . urlencode($row['id']) . "&csrf_token=" . urlencode($clients_csrf_token) . "' class='action-btn delete' 
                                           onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°ا اÙ„Ø¹Ù…ÙŠÙ„ØŸ\")' title='Ø­Ø°Ù'>
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

<!-- Modal اØ³ØªÙŠØ±اØ¯ Ù…Ù† Excel -->
<div id="importExcelModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h5><i class="fas fa-file-excel"></i> اØ³ØªÙŠØ±اØ¯ Ø¹Ù…Ù„اØ¡ Ù…Ù† Excel</h5>
            <button class="close-modal" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div
                    style="background:var(--blue-soft);border:1px solid rgba(37,99,235,.18);padding:16px 18px;border-radius:var(--radius);margin-bottom:18px;">
                    <h6 style="color:var(--blue);font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-info-circle"></i> ØªØ¹Ù„ÙŠÙ…اØª اÙ„اØ³ØªÙŠØ±اØ¯:
                    </h6>
                    <ul style="color:var(--navy);line-height:2;margin:0;padding-right:20px;font-size:.82rem;">
                        <li>Ù‚Ù… Ø¨ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel Ø£Ùˆ CSV Ø£ÙˆÙ„اÙ‹</li>
                        <li>اÙ…Ù„Ø£ اÙ„Ø¨ÙŠاÙ†اØª Ø­Ø³Ø¨ اÙ„Ø£Ø¹Ù…Ø¯Ø© اÙ„Ù…Ø­Ø¯Ø¯Ø©</li>
                        <li>ÙƒÙˆØ¯ اÙ„Ø¹Ù…ÙŠÙ„ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† ÙØ±ÙŠØ¯اÙ‹</li>
                        <li>اÙ„Ø­Ù‚ÙˆÙ„ اÙ„Ù…Ø·Ù„ÙˆØ¨Ø©: ÙƒÙˆØ¯ اÙ„Ø¹Ù…ÙŠÙ„ØŒ اØ³Ù… اÙ„Ø¹Ù…ÙŠÙ„ØŒ اÙ„Ø­اÙ„Ø©</li>
                        <li>ØµÙŠØºØ© اÙ„Ù…Ù„Ù اÙ„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx, .xls, .csv</li>
                        <li><strong>Ù…Ù„اØ­Ø¸Ø©:</strong> Ø¥Ø°ا Ù„Ù… ØªÙƒÙ† Ù…ÙƒØªØ¨Ø© PhpSpreadsheet Ù…Ø«Ø¨ØªØ©ØŒ اØ³ØªØ®Ø¯Ù… Ù…Ù„Ù CSV</li>
                    </ul>
                </div>

                <div class="form-group-modal">
                    <label><i class="fas fa-file-upload"></i> اØ®ØªØ± Ù…Ù„Ù Excel Ø£Ùˆ CSV (.xlsx, .xls, .csv) *</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                        style="padding:14px;border:2px dashed rgba(22,163,74,.4);border-radius:var(--radius);background:rgba(22,163,74,.04);cursor:pointer;width:100%;transition:border-color var(--ease);">
                </div>

                <div id="importProgress" style="display: none; margin-top: 18px;">
                    <div style="background:var(--blue-soft);border-radius:var(--radius);padding:16px;text-align:center;border:1px solid rgba(37,99,235,.18);">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--blue);"></i>
                        <p style="margin:10px 0 0;color:var(--blue);font-weight:700;">Ø¬اØ±ÙŠ اÙ„اØ³ØªÙŠØ±اØ¯...</p>
                    </div>
                </div>

                <div id="importResult" style="display: none; margin-top: 18px;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save"
                    style="background:linear-gradient(135deg,#064e3b,#059669)!important;">
                    <i class="fas fa-upload"></i> Ø±ÙØ¹ ÙˆاØ³ØªÙŠØ±اØ¯
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> Ø¥Ù„ØºاØ¡
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ø¹Ø±Ø¶ اÙ„Ø¹Ù…ÙŠÙ„ -->
<div id="viewClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> Ø¹Ø±Ø¶ Ø¨ÙŠاÙ†اØª اÙ„Ø¹Ù…ÙŠÙ„</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ اÙ„Ø¹Ù…ÙŠÙ„</div>
                    <div class="view-item-value" id="view_client_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> اØ³Ù… اÙ„Ø¹Ù…ÙŠÙ„</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> Ù†ÙˆØ¹ اÙ„ÙƒÙŠاÙ†</div>
                    <div class="view-item-value" id="view_entity_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ اÙ„Ù‚Ø·اØ¹</div>
                    <div class="view-item-value" id="view_sector_category">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> اÙ„Ù‡اØªÙ</div>
                    <div class="view-item-value" id="view_phone">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> اÙ„Ø¨Ø±ÙŠØ¯ اÙ„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ</div>
                    <div class="view-item-value" id="view_email">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> ÙˆاØªØ³اØ¨</div>
                    <div class="view-item-value" id="view_whatsapp">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> اÙ„Ø­اÙ„Ø©</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-plus"></i> Ø£Ø¶ÙŠÙ Ø¨ÙˆاØ³Ø·Ø©</div>
                    <div class="view-item-value" id="view_created_by">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editClientBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> ØªØ¹Ø¯ÙŠÙ„ اÙ„Ø¨ÙŠاÙ†اØª
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Ø¥ØºÙ„اÙ‚
            </button>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
    $(document).ready(function () {
        $('#clientsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });

    // Toggle Form (Show/Hide)
    $('#toggleForm').on('click', function () {
        $('#clientForm').slideToggle(400);
        // Reset form when opening
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm')[0].reset();
            $('#client_id').val('');
        }
    });

    // Edit Client - Load data into form
    $(document).on('click', '.editClientBtn', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status')
        };

        // Fill form with data
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // Show form if hidden
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });


    // View Client Modal
    $(document).on('click', '.viewClientBtn', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status'),
            created: $(this).data('created')
        };

        // Ù…Ù„Ø¡ Ø¨ÙŠاÙ†اØª اÙ„Ø¹Ø±Ø¶
        $('#view_client_code').text(clientData.code || '-');
        $('#view_client_name').text(clientData.name || '-');
        $('#view_entity_type').text(clientData.entity || '-');
        $('#view_sector_category').text(clientData.sector || '-');
        $('#view_phone').text(clientData.phone || '-');
        $('#view_email').text(clientData.email || '-');
        $('#view_whatsapp').text(clientData.whatsapp || '-');

        // Ø¹Ø±Ø¶ اÙ„Ø­اÙ„Ø© Ø¨Ø£Ù„ÙˆاÙ†
        let statusHtml = '';
        if (clientData.status === 'Ù†Ø´Ø·') {
            statusHtml = '<span class="status-active"><i class="fas fa-check-circle"></i> Ù†Ø´Ø·</span>';
        } else {
            statusHtml = '<span class="status-inactive"><i class="fas fa-times-circle"></i> Ù…ØªÙˆÙ‚Ù</span>';
        }
        $('#view_status').html(statusHtml);

        $('#view_created_by').text(clientData.created || '-');

        // ØªØ­Ø¶ÙŠØ± Ø²Ø± اÙ„ØªØ¹Ø¯ÙŠÙ„
        const editBtn = $('#viewEditBtn');
        editBtn.data('id', clientData.id);
        editBtn.data('code', clientData.code);
        editBtn.data('name', clientData.name);
        editBtn.data('entity', clientData.entity);
        editBtn.data('sector', clientData.sector);
        editBtn.data('phone', clientData.phone);
        editBtn.data('email', clientData.email);
        editBtn.data('whatsapp', clientData.whatsapp);
        editBtn.data('status', clientData.status);

        $('#viewClientModal').fadeIn(300);
    });

    // Close View Modal
    function closeViewModal() {
        $('#viewClientModal').fadeOut(300);
    }

    // Close modals when clicking outside
    $(window).on('click', function (e) {
        if (e.target.id === 'viewClientModal') {
            closeViewModal();
        }
    });

    // Close modal on ESC key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewClientModal').is(':visible')) {
            closeViewModal();
        }
    });

    // Edit from view modal - Load data into form
    $('#viewEditBtn').on('click', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status')
        };

        closeViewModal();

        // Fill form with data
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // Show form if hidden
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });
</script>

<script>
    // ÙØªØ­ Modal اÙ„اØ³ØªÙŠØ±اØ¯
    $('#openImportModal').on('click', function () {
        $('#importExcelModal').fadeIn(300);
    });

    // Ø¥ØºÙ„اÙ‚ Modal اÙ„اØ³ØªÙŠØ±اØ¯
    function closeImportModal() {
        $('#importExcelModal').fadeOut(300);
        $('#importExcelForm')[0].reset();
        $('#importProgress').hide();
        $('#importResult').hide();
    }

    // Ø¥ØºÙ„اÙ‚ Ø¹Ù†Ø¯ اÙ„Ø¶ØºØ· Ø®اØ±Ø¬ Modal
    $(window).on('click', function (e) {
        if (e.target.id === 'importExcelModal') {
            closeImportModal();
        }
    });

    // Ù…Ø¹اÙ„Ø¬Ø© Ø±ÙØ¹ Ù…Ù„Ù Excel
    $('#importExcelForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('اÙ„Ø±Ø¬اØ¡ اØ®ØªÙŠاØ± Ù…Ù„Ù Excel');
            return;
        }

        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);
        formData.append('action', 'import_excel');

        $('#importProgress').show();
        $('#importResult').hide();

        $.ajax({
            url: 'import_clients_excel.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                $('#importProgress').hide();

                let resultHtml = '<div style="padding:16px;border-radius:var(--radius);border:1.5px solid;';

                if (response.success) {
                    resultHtml += 'background:var(--green-soft);border-color:rgba(22,163,74,.22);color:var(--green)">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-check-circle"></i> ØªÙ… اÙ„اØ³ØªÙŠØ±اØ¯ Ø¨Ù†Ø¬اØ­!</h6>';
                    resultHtml += '<p style="margin:4px 0;">âœ… ØªÙ… Ø¥Ø¶اÙØ©: <strong>' + response.added + '</strong> Ø¹Ù…ÙŠÙ„</p>';
                    if (response.skipped > 0) {
                        resultHtml += '<p style="margin:4px 0;color:#854d0e;">âš ï¸ ØªÙ… ØªØ®Ø·ÙŠ: <strong>' + response.skipped + '</strong> Ø¹Ù…ÙŠÙ„ (Ù…ÙƒØ±Ø±)</p>';
                    }
                    if (response.errors.length > 0) {
                        resultHtml += '<p style="margin:8px 0 4px;"><strong>اÙ„Ø£Ø®Ø·اØ¡:</strong></p><ul style="margin:0;padding-right:20px;">';
                        response.errors.forEach(function (error) {
                            resultHtml += '<li>' + error + '</li>';
                        });
                        resultHtml += '</ul>';
                    }
                    resultHtml += '</div>';
                    setTimeout(function () { location.reload(); }, 3000);
                } else {
                    resultHtml += 'background:var(--red-soft);border-color:rgba(220,38,38,.22);color:var(--red)">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> ÙØ´Ù„ اÙ„اØ³ØªÙŠØ±اØ¯</h6>';
                    resultHtml += '<p style="margin:0;">' + response.message + '</p>';
                    resultHtml += '</div>';
                }

                $('#importResult').html(resultHtml).fadeIn(300);
            },
            error: function (xhr, status, error) {
                $('#importProgress').hide();

                let errorMsg = 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†اØ¡ Ø±ÙØ¹ اÙ„Ù…Ù„Ù. اÙ„Ø±Ø¬اØ¡ اÙ„Ù…Ø­اÙˆÙ„Ø© Ù…Ø±Ø© Ø£Ø®Ø±Ù‰.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) { errorMsg = response.message; }
                    } catch (e) {
                        errorMsg += '<br><small>ØªÙاØµÙŠÙ„ اÙ„Ø®Ø·Ø£: ' + status + '</small>';
                    }
                }

                const errorHtml = '<div style="padding:16px;border-radius:var(--radius);background:var(--red-soft);color:var(--red);border:1.5px solid rgba(220,38,38,.22);">' +
                    '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> Ø­Ø¯Ø« Ø®Ø·Ø£</h6>' +
                    '<p style="margin:0;">' + errorMsg + '</p>' +
                    '<p style="margin:10px 0 4px;"><strong>Ù†ØµاØ¦Ø­:</strong></p>' +
                    '<ul style="font-size:.8rem;margin:0;padding-right:20px;">' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† اÙ„Ù…Ù„Ù Ø¨ØµÙŠØºØ© .xlsx, .xls Ø£Ùˆ .csv</li>' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø­Ø¬Ù… اÙ„Ù…Ù„Ù Ø£Ù‚Ù„ Ù…Ù† 5 Ù…ÙŠØ¬ا</li>' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† اÙ„Ù…Ù„Ù ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø¨ÙŠاÙ†اØª ØµØ­ÙŠØ­Ø©</li>' +
                    '<li>Ø¥Ø°ا ÙƒÙ†Øª ØªØ³ØªØ®Ø¯Ù… ExcelØŒ Ø¬Ø±Ø¨ Ø­ÙØ¸ اÙ„Ù…Ù„Ù ÙƒÙ€ CSV</li>' +
                    '</ul></div>';
                $('#importResult').html(errorHtml).fadeIn(300);
            }
        });
    });
</script>

</body>
</html>
