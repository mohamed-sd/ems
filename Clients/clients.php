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

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

$clients_has_company_id = clients_table_has_column($conn, 'clients', 'company_id');
$scope_clients_sql = $clients_has_company_id
    ? "cc.company_id = $company_id"
    : "EXISTS (SELECT 1 FROM users scope_u WHERE scope_u.id = cc.created_by AND scope_u.company_id = $company_id)";

function clients_redirect_with_msg($msg)
{
    header('Location: clients.php?msg=' . urlencode($msg));
    exit();
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ù„Ù‰ ÙˆØ­Ø¯Ø© Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù…Ø¹Ø±Ù ÙˆØ­Ø¯Ø© Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡ Ù…Ù† Ø¬Ø¯ÙˆÙ„ modules
$module_query = "SELECT id FROM modules 
                      WHERE code = 'Clients/clients.php' 
                          OR code = 'clients' 
                          OR code LIKE '%clients.php%'
                          OR name LIKE '%عملاء%'
                      LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

// Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ù„Ù‰ Ù‡Ø°Ù‡ Ø§Ù„ÙˆØ­Ø¯Ø©
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

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù‡Ù†Ø§Ùƒ ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض العملاء ❌'));
    exit();
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø¥Ø¶Ø§ÙØ©/ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„ Ø¹Ø¨Ø± POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['client_name'])) {
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ Ø£Ùˆ Ø§Ù„Ø¥Ø¶Ø§ÙØ©
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $is_editing = $client_id > 0;
    
    if ($is_editing && !$can_edit) {
        clients_redirect_with_msg('لا توجد صلاحية تعديل العملاء ❌');
    } elseif (!$is_editing && !$can_add) {
        clients_redirect_with_msg('لا توجد صلاحية إضافة عملاء جدد ❌');
    }

    $client_code = mysqli_real_escape_string($conn, trim($_POST['client_code']));
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = intval($_SESSION['user']['id']);

    if ($client_id > 0) {
        $owner_check_query = "SELECT cc.id FROM clients cc WHERE cc.id = $client_id AND $scope_clients_sql LIMIT 1";
        $owner_check_result = mysqli_query($conn, $owner_check_query);
        if (!$owner_check_result || mysqli_num_rows($owner_check_result) === 0) {
            clients_redirect_with_msg('لا يمكنك تعديل عميل لا يتبع لشركتك ❌');
        }

        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND cc.id != $client_id AND $scope_clients_sql";
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
            WHERE id = $client_id AND $scope_clients_sql";

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
            WHERE id = $client_id AND $scope_clients_sql";
        }
        
        if (mysqli_query($conn, $update_query)) {
            clients_redirect_with_msg('تم تعديل العميل بنجاح ✅');
        } else {
            clients_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        $check_query = "SELECT cc.id FROM clients cc WHERE cc.client_code = '$client_code' AND $scope_clients_sql";
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
            clients_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
    }
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø­Ø°Ù Ø§Ù„Ø¹Ù…ÙŠÙ„
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ø­Ø°Ù
    if (!$can_delete) {
        clients_redirect_with_msg('لا توجد صلاحية حذف العملاء ❌');
    }

    $can_delete_scope_result = mysqli_query($conn, "SELECT cc.id FROM clients cc WHERE cc.id = $delete_id AND $scope_clients_sql LIMIT 1");
    if (!$can_delete_scope_result || mysqli_num_rows($can_delete_scope_result) === 0) {
        clients_redirect_with_msg('لا يمكنك حذف عميل لا يتبع لشركتك ❌');
    }
    
    clients_redirect_with_msg('تم تعطيل الحذف مؤقتاً ❌');
    //************************************* Ø§Ø²Ù„ Ø§Ù„ØªØ¹Ù„ÙŠÙ‚ Ù„ØªÙØ¹ÙŠÙ„ Ø¹Ù…Ù„ÙŠØ© Ø§Ù„Ø­Ø°Ù ***************************** */
    // $check_usage = mysqli_query($conn, "SELECT COUNT(*) as count FROM operationproject WHERE company_client_id = $delete_id");
    // $usage = mysqli_fetch_assoc($check_usage);
    // if ($usage['count'] > 0) {
    //     header("Location: clients.php?msg=Ù„Ø§+ÙŠÙ…ÙƒÙ†+Ø­Ø°Ù+Ø§Ù„Ø¹Ù…ÙŠÙ„+Ù„Ø£Ù†Ù‡+Ù…Ø³ØªØ®Ø¯Ù…+ÙÙŠ+Ù…Ø´Ø§Ø±ÙŠØ¹+Ù…ÙˆØ¬ÙˆØ¯Ø©+âŒ");
    //     exit();
    // } else {
    //     $delete_query = "DELETE FROM clients WHERE id = $delete_id";
    //     if (mysqli_query($conn, $delete_query)) {
    //         header("Location: clients.php?msg=ØªÙ…+Ø­Ø°Ù+Ø§Ù„Ø¹Ù…ÙŠÙ„+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
    //         exit();
    //     } else {
    //         header("Location: clients.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø­Ø°Ù+âŒ");
    //         exit();
    //     }
    // }
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
<!-- Font Awesome Ù…Ù† CDN Ù„Ø¶Ù…Ø§Ù† Ø¸Ù‡ÙˆØ± Ø§Ù„Ø£ÙŠÙ‚ÙˆÙ†Ø§Øª Ø¨Ø´ÙƒÙ„ ØµØ­ÙŠØ­ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ø¹Ù…ÙŠÙ„ Ø¬Ø¯ÙŠØ¯
                </a>
                <a href="javascript:void(0)" id="openImportModal" class="add-btn"
                    style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-color:transparent;">
                    <i class="fas fa-file-excel"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel
                </a>
            <?php else: ?>
                <button class="add-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© (Ø¨Ø¯ÙˆÙ† ØµÙ„Ø§Ø­ÙŠØ§Øª)
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
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„ -->
    <form id="clientForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù…ÙŠÙ„</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="client_id" id="client_id" value="">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„ *</label>
                        <input type="text" name="client_code" id="client_code" placeholder="Ù…Ø«Ø§Ù„: CL-001" required 
                               pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ *</label>
                        <input type="text" name="client_name" id="client_name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„" required />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù†</label>
                        <select name="entity_type" id="entity_type">
                            <option value="">-- Ø§Ø®ØªØ± Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù† --</option>
                            <option value="Ø­ÙƒÙˆÙ…ÙŠ">Ø­ÙƒÙˆÙ…ÙŠ</option>
                            <option value="Ø®Ø§Øµ">Ø®Ø§Øµ</option>
                            <option value="Ù…Ø®ØªÙ„Ø·">Ù…Ø®ØªÙ„Ø·</option>
                            <option value="Ø¯ÙˆÙ„ÙŠ">Ø¯ÙˆÙ„ÙŠ</option>
                            <option value="ØºÙŠØ± Ø±Ø¨Ø­ÙŠ">ØºÙŠØ± Ø±Ø¨Ø­ÙŠ</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ Ø§Ù„Ù‚Ø·Ø§Ø¹</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„ØªØµÙ†ÙŠÙ --</option>
                            <option value="Ø¨Ù†ÙŠØ© ØªØ­ØªÙŠØ©">Ø¨Ù†ÙŠØ© ØªØ­ØªÙŠØ©</option>
                            <option value="Ù†ÙØ· ÙˆØºØ§Ø²">Ù†ÙØ· ÙˆØºØ§Ø²</option>
                            <option value="ØªØ¹Ø¯ÙŠÙ†">ØªØ¹Ø¯ÙŠÙ†</option>
                            <option value="Ø²Ø±Ø§Ø¹Ø©">Ø²Ø±Ø§Ø¹Ø©</option>
                            <option value="Ø®Ø¯Ù…Ø§Øª">Ø®Ø¯Ù…Ø§Øª</option>
                            <option value="ØªØ¬Ø§Ø±Ø©">ØªØ¬Ø§Ø±Ø©</option>
                            <option value="ØµÙ†Ø§Ø¹Ø©">ØµÙ†Ø§Ø¹Ø©</option>
                            <option value="Ø·Ø§Ù‚Ø©">Ø·Ø§Ù‚Ø©</option>
                            <option value="Ù…ÙŠØ§Ù‡ ÙˆØµØ±Ù ØµØ­ÙŠ">Ù…ÙŠØ§Ù‡ ÙˆØµØ±Ù ØµØ­ÙŠ</option>
                            <option value="Ù†Ù‚Ù„ ÙˆÙ…ÙˆØ§ØµÙ„Ø§Øª">Ù†Ù‚Ù„ ÙˆÙ…ÙˆØ§ØµÙ„Ø§Øª</option>
                            <option value="Ø£Ø®Ø±Ù‰">Ø£Ø®Ø±Ù‰</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ</label>
                        <input type="tel" name="phone" id="phone" placeholder="Ù…Ø«Ø§Ù„: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-envelope"></i> Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com" />
                    </div>
                    <div>
                        <label><i class="fab fa-whatsapp"></i> ÙˆØ§ØªØ³Ø§Ø¨</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="Ù…Ø«Ø§Ù„: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ø¹Ù…ÙŠÙ„ *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>            
                    <button type="submit">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ø¹Ù…ÙŠÙ„
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th width="100"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„</th>
                            <th><i class="fas fa-user"></i> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„</th>
                            <th><i class="fas fa-building"></i> Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù†</th>
                            <th><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ Ø§Ù„Ù‚Ø·Ø§Ø¹</th>
                            <th><i class="fas fa-phone"></i> Ø§Ù„Ù‡Ø§ØªÙ</th>
                            <th><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
                            <th><i class="fas fa-cogs"></i> Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT cc.*, u.name as creator_name 
                                  FROM clients cc 
                                  LEFT JOIN users u ON cc.created_by = u.id 
                                  WHERE $scope_clients_sql
                                  ORDER BY cc.id DESC";
                        $result = mysqli_query($conn, $query);
                        $counter = 1;

                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td><strong style='font-family:monospace;letter-spacing:.03em'>" . htmlspecialchars($row['client_code']) . "</strong></td>";
                            echo "<td><a class='client-name-link' href='../Projects/projects.php?client_id=" . urlencode($row['id']) . "'>" . htmlspecialchars($row['client_name']) . "</a></td>";
                            echo "<td>" . htmlspecialchars($row['entity_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['sector_category']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
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
                                       data-code='" . htmlspecialchars($row['client_code']) . "'
                                       data-name='" . htmlspecialchars($row['client_name']) . "'
                                       data-entity='" . htmlspecialchars($row['entity_type']) . "'
                                       data-sector='" . htmlspecialchars($row['sector_category']) . "'
                                       data-phone='" . htmlspecialchars($row['phone']) . "'
                                       data-email='" . htmlspecialchars($row['email']) . "'
                                       data-whatsapp='" . htmlspecialchars($row['whatsapp']) . "'
                                       data-status='" . $row['status'] . "'
                                       data-created='" . htmlspecialchars(isset($row['creator_name']) ? $row['creator_name'] : 'غير محدد') . "'
                                       title='Ø¹Ø±Ø¶ Ø§Ù„ØªÙØ§ØµÙŠÙ„'>
                                        <i class='fas fa-eye'></i>
                                    </a>";
                                    
                                    if ($can_edit) {
                                        echo "<a href='javascript:void(0)' 
                                           class='action-btn edit editClientBtn' 
                                           data-id='" . $row['id'] . "'
                                           data-code='" . htmlspecialchars($row['client_code']) . "'
                                           data-name='" . htmlspecialchars($row['client_name']) . "'
                                           data-entity='" . htmlspecialchars($row['entity_type']) . "'
                                           data-sector='" . htmlspecialchars($row['sector_category']) . "'
                                           data-phone='" . htmlspecialchars($row['phone']) . "'
                                           data-email='" . htmlspecialchars($row['email']) . "'
                                           data-whatsapp='" . htmlspecialchars($row['whatsapp']) . "'
                                           data-status='" . $row['status'] . "'
                                           title='ØªØ¹Ø¯ÙŠÙ„'>
                                            <i class='fas fa-edit'></i>
                                        </a>";
                                    }
                                    
                                    if ($can_delete) {
                                        echo "<a href='?delete_id=" . $row['id'] . "' class='action-btn delete' 
                                           onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ø¹Ù…ÙŠÙ„ØŸ\")' title='Ø­Ø°Ù'>
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

<!-- Modal Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel -->
<div id="importExcelModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h5><i class="fas fa-file-excel"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø¹Ù…Ù„Ø§Ø¡ Ù…Ù† Excel</h5>
            <button class="close-modal" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div
                    style="background:var(--blue-soft);border:1px solid rgba(37,99,235,.18);padding:16px 18px;border-radius:var(--radius);margin-bottom:18px;">
                    <h6 style="color:var(--blue);font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-info-circle"></i> ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯:
                    </h6>
                    <ul style="color:var(--navy);line-height:2;margin:0;padding-right:20px;font-size:.82rem;">
                        <li>Ù‚Ù… Ø¨ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel Ø£Ùˆ CSV Ø£ÙˆÙ„Ø§Ù‹</li>
                        <li>Ø§Ù…Ù„Ø£ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø­Ø³Ø¨ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©</li>
                        <li>ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† ÙØ±ÙŠØ¯Ø§Ù‹</li>
                        <li>Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©: ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„ØŒ Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ØŒ Ø§Ù„Ø­Ø§Ù„Ø©</li>
                        <li>ØµÙŠØºØ© Ø§Ù„Ù…Ù„Ù Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx, .xls, .csv</li>
                        <li><strong>Ù…Ù„Ø§Ø­Ø¸Ø©:</strong> Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù…ÙƒØªØ¨Ø© PhpSpreadsheet Ù…Ø«Ø¨ØªØ©ØŒ Ø§Ø³ØªØ®Ø¯Ù… Ù…Ù„Ù CSV</li>
                    </ul>
                </div>

                <div class="form-group-modal">
                    <label><i class="fas fa-file-upload"></i> Ø§Ø®ØªØ± Ù…Ù„Ù Excel Ø£Ùˆ CSV (.xlsx, .xls, .csv) *</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                        style="padding:14px;border:2px dashed rgba(22,163,74,.4);border-radius:var(--radius);background:rgba(22,163,74,.04);cursor:pointer;width:100%;transition:border-color var(--ease);">
                </div>

                <div id="importProgress" style="display: none; margin-top: 18px;">
                    <div style="background:var(--blue-soft);border-radius:var(--radius);padding:16px;text-align:center;border:1px solid rgba(37,99,235,.18);">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--blue);"></i>
                        <p style="margin:10px 0 0;color:var(--blue);font-weight:700;">Ø¬Ø§Ø±ÙŠ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯...</p>
                    </div>
                </div>

                <div id="importResult" style="display: none; margin-top: 18px;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save"
                    style="background:linear-gradient(135deg,#064e3b,#059669)!important;">
                    <i class="fas fa-upload"></i> Ø±ÙØ¹ ÙˆØ§Ø³ØªÙŠØ±Ø§Ø¯
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ø¹Ø±Ø¶ Ø§Ù„Ø¹Ù…ÙŠÙ„ -->
<div id="viewClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> Ø¹Ø±Ø¶ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù…ÙŠÙ„</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„</div>
                    <div class="view-item-value" id="view_client_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù†</div>
                    <div class="view-item-value" id="view_entity_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> ØªØµÙ†ÙŠÙ Ø§Ù„Ù‚Ø·Ø§Ø¹</div>
                    <div class="view-item-value" id="view_sector_category">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> Ø§Ù„Ù‡Ø§ØªÙ</div>
                    <div class="view-item-value" id="view_phone">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ</div>
                    <div class="view-item-value" id="view_email">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> ÙˆØ§ØªØ³Ø§Ø¨</div>
                    <div class="view-item-value" id="view_whatsapp">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-plus"></i> Ø£Ø¶ÙŠÙ Ø¨ÙˆØ§Ø³Ø·Ø©</div>
                    <div class="view-item-value" id="view_created_by">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editClientBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
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

        // Ù…Ù„Ø¡ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ø±Ø¶
        $('#view_client_code').text(clientData.code || '-');
        $('#view_client_name').text(clientData.name || '-');
        $('#view_entity_type').text(clientData.entity || '-');
        $('#view_sector_category').text(clientData.sector || '-');
        $('#view_phone').text(clientData.phone || '-');
        $('#view_email').text(clientData.email || '-');
        $('#view_whatsapp').text(clientData.whatsapp || '-');

        // Ø¹Ø±Ø¶ Ø§Ù„Ø­Ø§Ù„Ø© Ø¨Ø£Ù„ÙˆØ§Ù†
        let statusHtml = '';
        if (clientData.status === 'Ù†Ø´Ø·') {
            statusHtml = '<span class="status-active"><i class="fas fa-check-circle"></i> Ù†Ø´Ø·</span>';
        } else {
            statusHtml = '<span class="status-inactive"><i class="fas fa-times-circle"></i> Ù…ØªÙˆÙ‚Ù</span>';
        }
        $('#view_status').html(statusHtml);

        $('#view_created_by').text(clientData.created || '-');

        // ØªØ­Ø¶ÙŠØ± Ø²Ø± Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
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
    // ÙØªØ­ Modal Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
    $('#openImportModal').on('click', function () {
        $('#importExcelModal').fadeIn(300);
    });

    // Ø¥ØºÙ„Ø§Ù‚ Modal Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
    function closeImportModal() {
        $('#importExcelModal').fadeOut(300);
        $('#importExcelForm')[0].reset();
        $('#importProgress').hide();
        $('#importResult').hide();
    }

    // Ø¥ØºÙ„Ø§Ù‚ Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø®Ø§Ø±Ø¬ Modal
    $(window).on('click', function (e) {
        if (e.target.id === 'importExcelModal') {
            closeImportModal();
        }
    });

    // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø±ÙØ¹ Ù…Ù„Ù Excel
    $('#importExcelForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('Ø§Ù„Ø±Ø¬Ø§Ø¡ Ø§Ø®ØªÙŠØ§Ø± Ù…Ù„Ù Excel');
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
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-check-circle"></i> ØªÙ… Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­!</h6>';
                    resultHtml += '<p style="margin:4px 0;">âœ… ØªÙ… Ø¥Ø¶Ø§ÙØ©: <strong>' + response.added + '</strong> Ø¹Ù…ÙŠÙ„</p>';
                    if (response.skipped > 0) {
                        resultHtml += '<p style="margin:4px 0;color:#854d0e;">âš ï¸ ØªÙ… ØªØ®Ø·ÙŠ: <strong>' + response.skipped + '</strong> Ø¹Ù…ÙŠÙ„ (Ù…ÙƒØ±Ø±)</p>';
                    }
                    if (response.errors.length > 0) {
                        resultHtml += '<p style="margin:8px 0 4px;"><strong>Ø§Ù„Ø£Ø®Ø·Ø§Ø¡:</strong></p><ul style="margin:0;padding-right:20px;">';
                        response.errors.forEach(function (error) {
                            resultHtml += '<li>' + error + '</li>';
                        });
                        resultHtml += '</ul>';
                    }
                    resultHtml += '</div>';
                    setTimeout(function () { location.reload(); }, 3000);
                } else {
                    resultHtml += 'background:var(--red-soft);border-color:rgba(220,38,38,.22);color:var(--red)">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> ÙØ´Ù„ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯</h6>';
                    resultHtml += '<p style="margin:0;">' + response.message + '</p>';
                    resultHtml += '</div>';
                }

                $('#importResult').html(resultHtml).fadeIn(300);
            },
            error: function (xhr, status, error) {
                $('#importProgress').hide();

                let errorMsg = 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø±ÙØ¹ Ø§Ù„Ù…Ù„Ù. Ø§Ù„Ø±Ø¬Ø§Ø¡ Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø© Ù…Ø±Ø© Ø£Ø®Ø±Ù‰.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) { errorMsg = response.message; }
                    } catch (e) {
                        errorMsg += '<br><small>ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø®Ø·Ø£: ' + status + '</small>';
                    }
                }

                const errorHtml = '<div style="padding:16px;border-radius:var(--radius);background:var(--red-soft);color:var(--red);border:1.5px solid rgba(220,38,38,.22);">' +
                    '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> Ø­Ø¯Ø« Ø®Ø·Ø£</h6>' +
                    '<p style="margin:0;">' + errorMsg + '</p>' +
                    '<p style="margin:10px 0 4px;"><strong>Ù†ØµØ§Ø¦Ø­:</strong></p>' +
                    '<ul style="font-size:.8rem;margin:0;padding-right:20px;">' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ù„Ù Ø¨ØµÙŠØºØ© .xlsx, .xls Ø£Ùˆ .csv</li>' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù Ø£Ù‚Ù„ Ù…Ù† 5 Ù…ÙŠØ¬Ø§</li>' +
                    '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ù„Ù ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø¨ÙŠØ§Ù†Ø§Øª ØµØ­ÙŠØ­Ø©</li>' +
                    '<li>Ø¥Ø°Ø§ ÙƒÙ†Øª ØªØ³ØªØ®Ø¯Ù… ExcelØŒ Ø¬Ø±Ø¨ Ø­ÙØ¸ Ø§Ù„Ù…Ù„Ù ÙƒÙ€ CSV</li>' +
                    '</ul></div>';
                $('#importResult').html(errorHtml).fadeIn(300);
            }
        });
    });
</script>

</body>
</html>
