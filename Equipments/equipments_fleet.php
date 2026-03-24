<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
$page_permissions = check_page_permissions($conn, 'Equipments/equipments_fleet.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+Ø§Ù„Ù…Ø¹Ø¯Ø§Øª+âŒ");
    exit();
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø­Ø°Ù Ø§Ù„Ù…Ø¹Ø¯Ø©
if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: equipments_fleet.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø­Ø°Ù+Ø§Ù„Ù…Ø¹Ø¯Ø§Øª+âŒ");
        exit();
    }
    $delete_id = intval($_GET['delete_id']);
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… Ø§Ø³ØªØ®Ø¯Ø§Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© ÙÙŠ Ø¹Ù…Ù„ÙŠØ§Øª Ù†Ø´Ø·Ø©
    $check_ops = mysqli_query($conn, "SELECT COUNT(*) as count FROM operations WHERE equipment = $delete_id AND status = '1'");
    $ops_count = mysqli_fetch_assoc($check_ops)['count'];
    
    if ($ops_count > 0) {
        header("Location: equipments_fleet.php?msg=Ù„Ø§+ÙŠÙ…ÙƒÙ†+Ø­Ø°Ù+Ø§Ù„Ù…Ø¹Ø¯Ø©+Ù„Ø£Ù†Ù‡Ø§+Ø¨ØµØ¯Ø¯+Ø§Ù„ØªØ´ØºÙŠÙ„+Ø­Ø§Ù„ÙŠØ§Ù‹+âŒ");
        exit();
    }
    
    if (mysqli_query($conn, "DELETE FROM equipments WHERE id = $delete_id")) {
        header("Location: equipments_fleet.php?msg=ØªÙ…+Ø­Ø°Ù+Ø§Ù„Ù…Ø¹Ø¯Ø©+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit();
    } else {
        header("Location: equipments_fleet.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø­Ø°Ù+âŒ");
        exit();
    }
}

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;

$selected_project_id = 0;
$show_all_projects = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_project_id'])) {
    if ($is_role10) {
        header("Location: equipments.php");
        exit();
    }
    $selected_project_value = trim($_POST['selected_project_id']);
    if ($selected_project_value === 'all') {
        $_SESSION['equipments_project_id'] = 'all';
    } elseif (is_numeric($selected_project_value) && intval($selected_project_value) > 0) {
        $_SESSION['equipments_project_id'] = intval($selected_project_value);
    } else {
        unset($_SESSION['equipments_project_id']);
    }
    header("Location: equipments.php");
    exit();
}

if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    if ($is_role10) {
        header("Location: equipments.php");
        exit();
    }
    $_SESSION['equipments_project_id'] = intval($_GET['project_id']);
    header("Location: equipments.php");
    exit();
}

if (isset($_SESSION['equipments_project_id'])) {
    if ($_SESSION['equipments_project_id'] === 'all') {
        $show_all_projects = true;
        $selected_project_id = 0;
    } else {
        $selected_project_id = intval($_SESSION['equipments_project_id']);
    }
}

if ($is_role10) {
    $show_all_projects = false;
    $selected_project_id = $user_project_id;
}

$selected_project = null;
if ($selected_project_id > 0) {
    $project_check_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = '1'";
    $project_check_result = mysqli_query($conn, $project_check_query);
    if ($project_check_result && mysqli_num_rows($project_check_result) > 0) {
        $selected_project = mysqli_fetch_assoc($project_check_result);
    } else {
        unset($_SESSION['equipments_project_id']);
        $selected_project_id = 0;
    }
}

$projects_result = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");

$page_title = "Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª";
include("../inheader.php");
include("../insidebar.php");

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù†Ø¬Ø§Ø­
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome Ù…Ù† CDN Ù„Ø¶Ù…Ø§Ù† Ø¸Ù‡ÙˆØ± Ø§Ù„Ø£ÙŠÙ‚ÙˆÙ†Ø§Øª Ø¨Ø´ÙƒÙ„ ØµØ­ÙŠØ­ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<?php

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø­ÙØ¸ Ø£Ùˆ Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    
    // ÙØ­Øµ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª
    if ($edit_id > 0 && !$can_edit) {
        $success_msg = "âŒ Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ ØµÙ„Ø§Ø­ÙŠØ© Ù„ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª";
        goto skip_save;
    }
    if ($edit_id == 0 && !$can_add) {
        $success_msg = "âŒ Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ ØµÙ„Ø§Ø­ÙŠØ© Ù„Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª";
        goto skip_save;
    }

    // Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code = mysqli_real_escape_string($conn, trim($_POST['code']));
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $chassis_number = mysqli_real_escape_string($conn, trim($_POST['chassis_number'] ?? ''));

    // Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØµÙ†Ø¹ ÙˆØ§Ù„Ù…ÙˆØ¯ÙŠÙ„
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : 'NULL';
    $import_year = !empty($_POST['import_year']) ? intval($_POST['import_year']) : 'NULL';

    // Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„ÙÙ†ÙŠØ© ÙˆØ§Ù„Ù…ÙˆØ§ØµÙØ§Øª
    $equipment_condition = mysqli_real_escape_string($conn, $_POST['equipment_condition'] ?? 'ÙÙŠ Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©');
    $operating_hours = !empty($_POST['operating_hours']) ? intval($_POST['operating_hours']) : 'NULL';
    $engine_condition = mysqli_real_escape_string($conn, $_POST['engine_condition'] ?? 'Ø¬ÙŠØ¯Ø©');
    $tires_condition = mysqli_real_escape_string($conn, $_POST['tires_condition'] ?? 'N/A');

    // Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù„ÙƒÙŠØ©
    $actual_owner_name = mysqli_real_escape_string($conn, trim($_POST['actual_owner_name'] ?? ''));
    $owner_type = mysqli_real_escape_string($conn, $_POST['owner_type'] ?? '');
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    $owner_supplier_relation = mysqli_real_escape_string($conn, $_POST['owner_supplier_relation'] ?? '');

    // Ø§Ù„ÙˆØ«Ø§Ø¦Ù‚ ÙˆØ§Ù„ØªØ³Ø¬ÙŠÙ„Ø§Øª
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number'] ?? ''));
    $license_authority = mysqli_real_escape_string($conn, trim($_POST['license_authority'] ?? ''));
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['license_expiry_date']) . "'" : 'NULL';
    $inspection_certificate_number = mysqli_real_escape_string($conn, trim($_POST['inspection_certificate_number'] ?? ''));
    $last_inspection_date = !empty($_POST['last_inspection_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_inspection_date']) . "'" : 'NULL';

    // Ø§Ù„Ù…ÙˆÙ‚Ø¹ ÙˆØ§Ù„ØªÙˆÙØ±
    $current_location = mysqli_real_escape_string($conn, trim($_POST['current_location'] ?? ''));
    $availability_status = mysqli_real_escape_string($conn, $_POST['availability_status'] ?? 'Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„');

    // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ù‚ÙŠÙ…Ø©
    $estimated_value = !empty($_POST['estimated_value']) ? floatval($_POST['estimated_value']) : 'NULL';
    $daily_rental_price = !empty($_POST['daily_rental_price']) ? floatval($_POST['daily_rental_price']) : 'NULL';
    $monthly_rental_price = !empty($_POST['monthly_rental_price']) ? floatval($_POST['monthly_rental_price']) : 'NULL';
    $insurance_status = mysqli_real_escape_string($conn, $_POST['insurance_status'] ?? '');

    // Ù…Ù„Ø§Ø­Ø¸Ø§Øª ÙˆØ³Ø¬Ù„ Ø§Ù„ØµÙŠØ§Ù†Ø©
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes'] ?? ''));
    $last_maintenance_date = !empty($_POST['last_maintenance_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_maintenance_date']) . "'" : 'NULL';



    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… ØªØ¬Ø§ÙˆØ² Ø§Ù„Ø¹Ø¯Ø¯ Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡ (ÙÙ‚Ø· Ø¹Ù†Ø¯ Ø§Ù„Ø¥Ø¶Ø§ÙØ©)
    if ($edit_id == 0 && $suppliers && $type) {
        // Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§ Ù„Ù‡Ø°Ø§ Ø§Ù„Ù…ÙˆØ±Ø¯ ÙˆÙ†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©
        $supplier_contract_query = "SELECT sc.id, sce.equip_count
                                   FROM supplierscontracts sc
                                   JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
                                   WHERE sc.supplier_id = $suppliers 
                                   AND sce.equip_type = '$type'
                                   AND sc.status = 1
                                   LIMIT 1";
        $supplier_contract_result = mysqli_query($conn, $supplier_contract_query);

        if ($supplier_contract_result && mysqli_num_rows($supplier_contract_result) > 0) {
            $supplier_contract = mysqli_fetch_assoc($supplier_contract_result);
            $contracted_count = intval($supplier_contract['equip_count']);

            // Ø­Ø³Ø§Ø¨ Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ù…Ø¶Ø§ÙØ© Ø­Ø§Ù„ÙŠØ§Ù‹
            $added_count_query = "SELECT COUNT(*) as added_count 
                                 FROM equipments 
                                 WHERE suppliers = $suppliers 
                                 AND type = '$type'
                                 AND status = 1";
            $added_count_result = mysqli_query($conn, $added_count_query);
            $added_count_row = mysqli_fetch_assoc($added_count_result);
            $current_added = intval($added_count_row['added_count']);

            // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… ØªØ¬Ø§ÙˆØ² Ø§Ù„Ø¹Ø¯Ø¯ Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡
            if ($current_added >= $contracted_count) {
                $success_msg = "âš ï¸ ØªØ­Ø°ÙŠØ±: ØªÙ… Ø§Ù„ÙˆØµÙˆÙ„ Ù„Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰! Ø§Ù„Ø¹Ø¯Ø¯ Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡: $contracted_count | Ø§Ù„Ù…Ø¶Ø§Ù Ø­Ø§Ù„ÙŠØ§Ù‹: $current_added. Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ø²ÙŠØ¯ Ù…Ù† Ø§Ù„Ù…Ø¹Ø¯Ø§Øª.";
                goto skip_save;
            }
        }
    }

    if ($edit_id > 0) {
        // ØªØ¹Ø¯ÙŠÙ„
        $sql = "UPDATE equipments 
                SET  
                    suppliers='$suppliers', 
                    code='$code', 
                    type='$type', 
                    name='$name', 
                    status='$status',
                    serial_number='$serial_number',
                    chassis_number='$chassis_number',
                    manufacturer='$manufacturer',
                    model='$model',
                    manufacturing_year=$manufacturing_year,
                    import_year=$import_year,
                    equipment_condition='$equipment_condition',
                    operating_hours=$operating_hours,
                    engine_condition='$engine_condition',
                    tires_condition='$tires_condition',
                    actual_owner_name='$actual_owner_name',
                    owner_type='$owner_type',
                    owner_phone='$owner_phone',
                    owner_supplier_relation='$owner_supplier_relation',
                    license_number='$license_number',
                    license_authority='$license_authority',
                    license_expiry_date=$license_expiry_date,
                    inspection_certificate_number='$inspection_certificate_number',
                    last_inspection_date=$last_inspection_date,
                    current_location='$current_location',
                    availability_status='$availability_status',
                    estimated_value=$estimated_value,
                    daily_rental_price=$daily_rental_price,
                    monthly_rental_price=$monthly_rental_price,
                    insurance_status='$insurance_status',
                    general_notes='$general_notes',
                    last_maintenance_date=$last_maintenance_date
                WHERE id='$edit_id'";
        $msg = "ØªÙ…+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ø¹Ø¯Ø©+Ø¨Ù†Ø¬Ø§Ø­+âœ…";
    } else {
        // Ø¥Ø¶Ø§ÙØ©
        $sql = "INSERT INTO equipments 
                (suppliers, code, type, name, status, serial_number, chassis_number, 
                 manufacturer, model, manufacturing_year, import_year, 
                 equipment_condition, operating_hours, engine_condition, tires_condition,
                 actual_owner_name, owner_type, owner_phone, owner_supplier_relation,
                 license_number, license_authority, license_expiry_date, 
                 inspection_certificate_number, last_inspection_date,
                 current_location, availability_status,
                 estimated_value, daily_rental_price, monthly_rental_price, insurance_status,
                 general_notes, last_maintenance_date) 
                VALUES 
                ('$suppliers', '$code', '$type', '$name', '$status', '$serial_number', '$chassis_number',
                 '$manufacturer', '$model', $manufacturing_year, $import_year,
                 '$equipment_condition', $operating_hours, '$engine_condition', '$tires_condition',
                 '$actual_owner_name', '$owner_type', '$owner_phone', '$owner_supplier_relation',
                 '$license_number', '$license_authority', $license_expiry_date,
                 '$inspection_certificate_number', $last_inspection_date,
                 '$current_location', '$availability_status',
                 $estimated_value, $daily_rental_price, $monthly_rental_price, '$insurance_status',
                 '$general_notes', $last_maintenance_date)";
        $msg = "ØªÙ…Øª+Ø¥Ø¶Ø§ÙØ©+Ø§Ù„Ù…Ø¹Ø¯Ø©+Ø¨Ù†Ø¬Ø§Ø­+âœ…";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: equipments.php?msg=$msg");
        exit;
    } else {
        $success_msg = "Ø®Ø·Ø£ ÙÙŠ Ø§Ù„Ø­ÙØ¸: " . mysqli_error($conn);
    }

    skip_save:
}

// ÙÙŠ Ø­Ø§Ù„Ø© ØªØ¹Ø¯ÙŠÙ„ ØªØ¬Ù‡ÙŠØ² Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
$editData = [];
if (isset($_GET['edit']) && $can_edit) {
    $editId = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}
?>

<div class="main">
    <!-- Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ØµÙØ­Ø© -->
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-cogs"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
        </h1>
        <div class="page-header-actions">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add) { ?>
                <!-- Ø£Ø²Ø±Ø§Ø± Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel -->
                <a href="download_equipments_template.php" class="btn"
                    style="background: linear-gradient(135deg, #16a34a 0%, #059669 100%); color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25); transition: all 0.3s ease;">
                    <i class="fas fa-file-excel"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel
                </a>
                <a href="download_equipments_template_csv.php" class="btn"
                    style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25); transition: all 0.3s ease;">
                    <i class="fas fa-file-csv"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ CSV
                </a>
                <a href="javascript:void(0)" id="openImportModal" class="btn"
                    style="background: linear-gradient(135deg, #e8b800 0%, #d4a800 100%); color: #0c1c3e; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(232, 184, 0, 0.25); transition: all 0.3s ease;">
                    <i class="fas fa-file-import"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel
                </a>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ø¹Ø¯Ø© Ø¬Ø¯ÙŠØ¯Ø©
                </a>
            <?php } ?>
        </div>
    </div>

    <?php if (!empty($success_msg)):
        $isSuccess = strpos($success_msg, 'âœ…') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($can_add || $can_edit) { ?>
        <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø¹Ø¯Ø© -->
        <form id="projectForm" action="" method="post" style="display:<?php echo !empty($editData) ? 'block' : 'none'; ?>;">
            <div class="card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-<?php echo !empty($editData) ? 'edit' : 'plus-circle'; ?>"></i>
                        <?php echo !empty($editData) ? "ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¢Ù„ÙŠØ©" : "Ø¥Ø¶Ø§ÙØ© Ø¢Ù„ÙŠØ© Ø¬Ø¯ÙŠØ¯Ø©"; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <?php if (!empty($editData)) { ?>
                            <input type="hidden" name="edit_id"
                                value="<?php echo isset($editData['id']) ? $editData['id'] : ''; ?>">
                        <?php } ?>

                        <div>
                            <label>
                                <i class="fas fa-truck-loading"></i>
                                Ø§Ù„Ù…ÙˆØ±Ø¯ <span class="required-indicator">*</span>
                            </label>
                            <select name="suppliers" id="suppliers" required>
                                <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù…ÙˆØ±Ø¯ --</option>
                                <?php
                                $supplier_query = "SELECT id, name FROM suppliers WHERE status = 1 ORDER BY name";
                                $supplier_result = mysqli_query($conn, $supplier_query);
                                while ($supplier = mysqli_fetch_assoc($supplier_result)) {
                                    $selected = (!empty($editData) && $editData['suppliers'] == $supplier['id']) ? 'selected' : '';
                                    echo "<option value='{$supplier['id']}' $selected>{$supplier['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-barcode"></i>
                                ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø© <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="code" id="code" placeholder="Ø£Ø¯Ø®Ù„ ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©"
                                value="<?php echo isset($editData['code']) ? htmlspecialchars($editData['code']) : ''; ?>"
                                required />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-list-alt"></i>
                                Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø© <span class="required-indicator">*</span>
                            </label>
                            <select name="type" id="type" required>
                                <option value="">-- Ø­Ø¯Ø¯ Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø© --</option>
                                <?php
                                $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                                $type_result = mysqli_query($conn, $type_query);
                                if ($type_result) {
                                    while ($type_row = mysqli_fetch_assoc($type_result)) {
                                        $selected = (!empty($editData) && $editData['type'] == $type_row['id']) ? 'selected' : '';
                                        echo "<option value='" . intval($type_row['id']) . "' $selected>" . htmlspecialchars($type_row['type']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-tag"></i>
                                Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="name" id="name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø¯Ø©"
                                value="<?php echo isset($editData['name']) ? htmlspecialchars($editData['name']) : ''; ?>"
                                required />
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ© -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-id-card"></i> Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-hashtag"></i>
                                Ø±Ù‚Ù… Ø§Ù„Ù…Ø¹Ø¯Ø©/Ø§Ù„Ø±Ù‚Ù… Ø§Ù„ØªØ³Ù„Ø³Ù„ÙŠ
                            </label>
                            <input type="text" name="serial_number" id="serial_number" placeholder="Ù…Ø«Ø§Ù„: EXC-2024-001"
                                value="<?php echo isset($editData['serial_number']) ? htmlspecialchars($editData['serial_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-barcode"></i>
                                Ø±Ù‚Ù… Ø§Ù„Ù‡ÙŠÙƒÙ„/Ø§Ù„Ù‡ÙŠÙƒÙ„ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ (VIN/Chassis)
                            </label>
                            <input type="text" name="chassis_number" id="chassis_number"
                                placeholder="Ù…Ø«Ø§Ù„: CAT320-ABC123456"
                                value="<?php echo isset($editData['chassis_number']) ? htmlspecialchars($editData['chassis_number']) : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØµÙ†Ø¹ ÙˆØ§Ù„Ù…ÙˆØ¯ÙŠÙ„ -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-industry"></i> Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØµÙ†Ø¹ ÙˆØ§Ù„Ù…ÙˆØ¯ÙŠÙ„</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-building"></i>
                                Ø§Ù„Ù…Ø§Ø±ÙƒØ©/Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù…ØµÙ†Ø¹Ø©
                            </label>
                            <input type="text" name="manufacturer" id="manufacturer"
                                placeholder="Ù…Ø«Ø§Ù„: ÙƒØ§ØªØ±Ø¨ÙŠÙ„Ø±ØŒ ÙƒÙˆÙ…Ø§ØªØ³ÙˆØŒ Ù‡ÙŠÙˆÙ†Ø¯Ø§ÙŠ"
                                value="<?php echo isset($editData['manufacturer']) ? htmlspecialchars($editData['manufacturer']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-car"></i>
                                Ø§Ù„Ù…ÙˆØ¯ÙŠÙ„/Ø§Ù„Ø·Ø±Ø§Ø²
                            </label>
                            <input type="text" name="model" id="model" placeholder="Ù…Ø«Ø§Ù„: 320D, PC200, HD1024"
                                value="<?php echo isset($editData['model']) ? htmlspecialchars($editData['model']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar"></i>
                                Ø³Ù†Ø© Ø§Ù„ØµÙ†Ø¹
                            </label>
                            <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="Ù…Ø«Ø§Ù„: 2018"
                                min="1950" max="2099"
                                value="<?php echo isset($editData['manufacturing_year']) ? $editData['manufacturing_year'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-plus"></i>
                                Ø³Ù†Ø© Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯/Ø§Ù„Ø¨Ø¯Ø¡
                            </label>
                            <input type="number" name="import_year" id="import_year" placeholder="Ù…Ø«Ø§Ù„: 2020" min="1950"
                                max="2099"
                                value="<?php echo isset($editData['import_year']) ? $editData['import_year'] : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„ÙÙ†ÙŠØ© ÙˆØ§Ù„Ù…ÙˆØ§ØµÙØ§Øª -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-wrench"></i> Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„ÙÙ†ÙŠØ© ÙˆØ§Ù„Ù…ÙˆØ§ØµÙØ§Øª</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-cogs"></i>
                                Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¹Ø¯Ø©
                            </label>
                            <select name="equipment_condition" id="equipment_condition">
                                <option value="Ø¬Ø¯ÙŠØ¯Ø© (Ù„Ù… ØªØ³ØªØ®Ø¯Ù…)" <?php echo (!empty($editData) && $editData['equipment_condition'] == "Ø¬Ø¯ÙŠØ¯Ø© (Ù„Ù… ØªØ³ØªØ®Ø¯Ù…)") ? "selected" : ""; ?>>Ø¬Ø¯ÙŠØ¯Ø© (Ù„Ù…
                                    ØªØ³ØªØ®Ø¯Ù…)</option>
                                <option value="Ø¬Ø¯ÙŠØ¯Ø© Ù†Ø³Ø¨ÙŠØ§Ù‹ (Ø£Ù‚Ù„ Ù…Ù† Ø³Ù†Ø© Ø§Ø³ØªØ®Ø¯Ø§Ù…)" <?php echo (!empty($editData) && $editData['equipment_condition'] == "Ø¬Ø¯ÙŠØ¯Ø© Ù†Ø³Ø¨ÙŠØ§Ù‹ (Ø£Ù‚Ù„ Ù…Ù† Ø³Ù†Ø© Ø§Ø³ØªØ®Ø¯Ø§Ù…)") ? "selected" : ""; ?>>Ø¬Ø¯ÙŠØ¯Ø© Ù†Ø³Ø¨ÙŠØ§Ù‹ (Ø£Ù‚Ù„ Ù…Ù† Ø³Ù†Ø© Ø§Ø³ØªØ®Ø¯Ø§Ù…)</option>
                                <option value="ÙÙŠ Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©" <?php echo (empty($editData) || $editData['equipment_condition'] == "ÙÙŠ Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©") ? "selected" : ""; ?>>ÙÙŠ Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©
                                </option>
                                <option value="ÙÙŠ Ø­Ø§Ù„Ø© Ù…ØªÙˆØ³Ø·Ø©" <?php echo (!empty($editData) && $editData['equipment_condition'] == "ÙÙŠ Ø­Ø§Ù„Ø© Ù…ØªÙˆØ³Ø·Ø©") ? "selected" : ""; ?>>ÙÙŠ Ø­Ø§Ù„Ø© Ù…ØªÙˆØ³Ø·Ø©
                                </option>
                                <option value="ÙÙŠ Ø­Ø§Ù„Ø© Ø¶Ø¹ÙŠÙØ©" <?php echo (!empty($editData) && $editData['equipment_condition'] == "ÙÙŠ Ø­Ø§Ù„Ø© Ø¶Ø¹ÙŠÙØ©") ? "selected" : ""; ?>>ÙÙŠ Ø­Ø§Ù„Ø© Ø¶Ø¹ÙŠÙØ©
                                </option>
                                <option value="Ù…Ø­ØªØ§Ø¬Ø© Ø¥ØµÙ„Ø§Ø­ ÙÙˆØ±ÙŠ" <?php echo (!empty($editData) && $editData['equipment_condition'] == "Ù…Ø­ØªØ§Ø¬Ø© Ø¥ØµÙ„Ø§Ø­ ÙÙˆØ±ÙŠ") ? "selected" : ""; ?>>Ù…Ø­ØªØ§Ø¬Ø©
                                    Ø¥ØµÙ„Ø§Ø­ ÙÙˆØ±ÙŠ</option>
                                <option value="Ù…Ø¹Ø·Ù„Ø© Ù…Ø¤Ù‚ØªØ§Ù‹" <?php echo (!empty($editData) && $editData['equipment_condition'] == "Ù…Ø¹Ø·Ù„Ø© Ù…Ø¤Ù‚ØªØ§Ù‹") ? "selected" : ""; ?>>Ù…Ø¹Ø·Ù„Ø© Ù…Ø¤Ù‚ØªØ§Ù‹
                                </option>
                                <option value="Ù…Ø³ØªØ¹Ù…Ù„Ø© Ø¨ÙƒØ«Ø§ÙØ©" <?php echo (!empty($editData) && $editData['equipment_condition'] == "Ù…Ø³ØªØ¹Ù…Ù„Ø© Ø¨ÙƒØ«Ø§ÙØ©") ? "selected" : ""; ?>>Ù…Ø³ØªØ¹Ù…Ù„Ø© Ø¨ÙƒØ«Ø§ÙØ©
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-clock"></i>
                                Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ´ØºÙŠÙ„ (Ù„Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø«Ù‚ÙŠÙ„Ø©)
                            </label>
                            <input type="number" name="operating_hours" id="operating_hours" placeholder="Ù…Ø«Ø§Ù„: 5400 Ø³Ø§Ø¹Ø©"
                                min="0"
                                value="<?php echo isset($editData['operating_hours']) ? $editData['operating_hours'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-car-crash"></i>
                                Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø±Ùƒ
                            </label>
                            <select name="engine_condition" id="engine_condition">
                                <option value="Ù…Ù…ØªØ§Ø²Ø©" <?php echo (!empty($editData) && $editData['engine_condition'] == "Ù…Ù…ØªØ§Ø²Ø©") ? "selected" : ""; ?>>Ù…Ù…ØªØ§Ø²Ø©</option>
                                <option value="Ø¬ÙŠØ¯Ø©" <?php echo (empty($editData) || $editData['engine_condition'] == "Ø¬ÙŠØ¯Ø©") ? "selected" : ""; ?>>Ø¬ÙŠØ¯Ø©</option>
                                <option value="Ù…ØªÙˆØ³Ø·Ø©" <?php echo (!empty($editData) && $editData['engine_condition'] == "Ù…ØªÙˆØ³Ø·Ø©") ? "selected" : ""; ?>>Ù…ØªÙˆØ³Ø·Ø©</option>
                                <option value="Ù…Ø­ØªØ§Ø¬Ø© ØµÙŠØ§Ù†Ø©" <?php echo (!empty($editData) && $editData['engine_condition'] == "Ù…Ø­ØªØ§Ø¬Ø© ØµÙŠØ§Ù†Ø©") ? "selected" : ""; ?>>Ù…Ø­ØªØ§Ø¬Ø© ØµÙŠØ§Ù†Ø©
                                </option>
                                <option value="Ù…Ø­ØªØ§Ø¬Ø© Ø¥ØµÙ„Ø§Ø­" <?php echo (!empty($editData) && $editData['engine_condition'] == "Ù…Ø­ØªØ§Ø¬Ø© Ø¥ØµÙ„Ø§Ø­") ? "selected" : ""; ?>>Ù…Ø­ØªØ§Ø¬Ø© Ø¥ØµÙ„Ø§Ø­
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-circle-notch"></i>
                                Ø­Ø§Ù„Ø© Ø§Ù„Ø¥Ø·Ø§Ø±Ø§Øª (Ù„Ù„Ø´Ø§Ø­Ù†Ø§Øª)
                            </label>
                            <select name="tires_condition" id="tires_condition">
                                <option value="N/A" <?php echo (empty($editData) || $editData['tires_condition'] == "N/A") ? "selected" : ""; ?>>N/A</option>
                                <option value="Ø¬Ø¯ÙŠØ¯Ø©" <?php echo (!empty($editData) && $editData['tires_condition'] == "Ø¬Ø¯ÙŠØ¯Ø©") ? "selected" : ""; ?>>Ø¬Ø¯ÙŠØ¯Ø©</option>
                                <option value="Ø¬ÙŠØ¯Ø©" <?php echo (!empty($editData) && $editData['tires_condition'] == "Ø¬ÙŠØ¯Ø©") ? "selected" : ""; ?>>Ø¬ÙŠØ¯Ø©</option>
                                <option value="Ù…ØªÙˆØ³Ø·Ø©" <?php echo (!empty($editData) && $editData['tires_condition'] == "Ù…ØªÙˆØ³Ø·Ø©") ? "selected" : ""; ?>>Ù…ØªÙˆØ³Ø·Ø©</option>
                                <option value="Ù…Ø­ØªØ§Ø¬Ø© ØªØ¨Ø¯ÙŠÙ„" <?php echo (!empty($editData) && $editData['tires_condition'] == "Ù…Ø­ØªØ§Ø¬Ø© ØªØ¨Ø¯ÙŠÙ„") ? "selected" : ""; ?>>Ù…Ø­ØªØ§Ø¬Ø© ØªØ¨Ø¯ÙŠÙ„
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù„ÙƒÙŠØ© -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-user-tie"></i> Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù„ÙƒÙŠØ©</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-user"></i>
                                Ø§Ø³Ù… Ø§Ù„Ù…Ø§Ù„Ùƒ Ø§Ù„ÙØ¹Ù„ÙŠ
                            </label>
                            <input type="text" name="actual_owner_name" id="actual_owner_name"
                                placeholder="Ù…Ø«Ø§Ù„: Ù…Ø­Ù…Ø¯ Ø¹Ù„ÙŠ Ø£Ø­Ù…Ø¯"
                                value="<?php echo isset($editData['actual_owner_name']) ? htmlspecialchars($editData['actual_owner_name']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-briefcase"></i>
                                Ù†ÙˆØ¹ Ø§Ù„Ù…Ø§Ù„Ùƒ
                            </label>
                            <select name="owner_type" id="owner_type">
                                <option value="">-- Ø§Ø®ØªØ± Ù†ÙˆØ¹ Ø§Ù„Ù…Ø§Ù„Ùƒ --</option>
                                <option value="Ù…Ø§Ù„Ùƒ ÙØ±Ø¯ÙŠ" <?php echo (!empty($editData) && $editData['owner_type'] == "Ù…Ø§Ù„Ùƒ ÙØ±Ø¯ÙŠ") ? "selected" : ""; ?>>Ù…Ø§Ù„Ùƒ ÙØ±Ø¯ÙŠ</option>
                                <option value="Ø´Ø±ÙƒØ© Ù…ØªØ®ØµØµØ©" <?php echo (!empty($editData) && $editData['owner_type'] == "Ø´Ø±ÙƒØ© Ù…ØªØ®ØµØµØ©") ? "selected" : ""; ?>>Ø´Ø±ÙƒØ© Ù…ØªØ®ØµØµØ©</option>
                                <option value="Ù…Ø¤Ø³Ø³Ø©" <?php echo (!empty($editData) && $editData['owner_type'] == "Ù…Ø¤Ø³Ø³Ø©") ? "selected" : ""; ?>>Ù…Ø¤Ø³Ø³Ø©</option>
                                <option value="Ø£Ø®Ø±Ù‰" <?php echo (!empty($editData) && $editData['owner_type'] == "Ø£Ø®Ø±Ù‰") ? "selected" : ""; ?>>Ø£Ø®Ø±Ù‰</option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-phone"></i>
                                Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø§Ù„Ù…Ø§Ù„Ùƒ
                            </label>
                            <input type="text" name="owner_phone" id="owner_phone" placeholder="Ù…Ø«Ø§Ù„: +249-9-123-4567"
                                value="<?php echo isset($editData['owner_phone']) ? htmlspecialchars($editData['owner_phone']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-handshake"></i>
                                Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ù…Ø§Ù„Ùƒ Ø¨Ø§Ù„Ù…ÙˆØ±Ø¯
                            </label>
                            <select name="owner_supplier_relation" id="owner_supplier_relation">
                                <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø¹Ù„Ø§Ù‚Ø© --</option>
                                <option value="Ù…Ø§Ù„Ùƒ Ù…Ø¨Ø§Ø´Ø± (ÙŠØªØ¹Ø§Ù‚Ø¯ Ù…Ø¹Ù†Ø§ Ù…Ø¨Ø§Ø´Ø±Ø©)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "Ù…Ø§Ù„Ùƒ Ù…Ø¨Ø§Ø´Ø± (ÙŠØªØ¹Ø§Ù‚Ø¯ Ù…Ø¹Ù†Ø§ Ù…Ø¨Ø§Ø´Ø±Ø©)") ? "selected" : ""; ?>>Ù…Ø§Ù„Ùƒ Ù…Ø¨Ø§Ø´Ø± (ÙŠØªØ¹Ø§Ù‚Ø¯ Ù…Ø¹Ù†Ø§ Ù…Ø¨Ø§Ø´Ø±Ø©)</option>
                                <option value="ØªØ­Øª ÙˆØ³Ø§Ø·Ø© Ø§Ù„Ù…ÙˆØ±Ø¯ (Ø§Ù„Ù…ÙˆØ±Ø¯ ÙŠØ¯ÙŠØ± Ø§Ù„Ù…Ø¹Ø¯Ø© Ù†ÙŠØ§Ø¨Ø© Ø¹Ù†Ù‡)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "ØªØ­Øª ÙˆØ³Ø§Ø·Ø© Ø§Ù„Ù…ÙˆØ±Ø¯ (Ø§Ù„Ù…ÙˆØ±Ø¯ ÙŠØ¯ÙŠØ± Ø§Ù„Ù…Ø¹Ø¯Ø© Ù†ÙŠØ§Ø¨Ø© Ø¹Ù†Ù‡)") ? "selected" : ""; ?>>ØªØ­Øª ÙˆØ³Ø§Ø·Ø© Ø§Ù„Ù…ÙˆØ±Ø¯ (Ø§Ù„Ù…ÙˆØ±Ø¯ ÙŠØ¯ÙŠØ± Ø§Ù„Ù…Ø¹Ø¯Ø©
                                    Ù†ÙŠØ§Ø¨Ø© Ø¹Ù†Ù‡)</option>
                                <option value="ØªØ§Ø¨Ø¹ Ù„Ù„Ù…ÙˆØ±Ø¯ (Ù…Ù…Ù„ÙˆÙƒØ© Ù„Ù„Ù…ÙˆØ±Ø¯ Ù†ÙØ³Ù‡)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "ØªØ§Ø¨Ø¹ Ù„Ù„Ù…ÙˆØ±Ø¯ (Ù…Ù…Ù„ÙˆÙƒØ© Ù„Ù„Ù…ÙˆØ±Ø¯ Ù†ÙØ³Ù‡)") ? "selected" : ""; ?>>ØªØ§Ø¨Ø¹ Ù„Ù„Ù…ÙˆØ±Ø¯ (Ù…Ù…Ù„ÙˆÙƒØ© Ù„Ù„Ù…ÙˆØ±Ø¯ Ù†ÙØ³Ù‡)</option>
                                <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "ØºÙŠØ± Ù…Ø­Ø¯Ø¯") ? "selected" : ""; ?>>ØºÙŠØ± Ù…Ø­Ø¯Ø¯
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø§Ù„ÙˆØ«Ø§Ø¦Ù‚ ÙˆØ§Ù„ØªØ³Ø¬ÙŠÙ„Ø§Øª -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-file-contract"></i> Ø§Ù„ÙˆØ«Ø§Ø¦Ù‚ ÙˆØ§Ù„ØªØ³Ø¬ÙŠÙ„Ø§Øª</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-address-card"></i>
                                Ø±Ù‚Ù… Ø§Ù„ØªØ±Ø®ÙŠØµ/Ø§Ù„ØªØ³Ø¬ÙŠÙ„
                            </label>
                            <input type="text" name="license_number" id="license_number" placeholder="Ù…Ø«Ø§Ù„: VEH-2024-12345"
                                value="<?php echo isset($editData['license_number']) ? htmlspecialchars($editData['license_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-landmark"></i>
                                Ø¬Ù‡Ø© Ø§Ù„ØªØ±Ø®ÙŠØµ
                            </label>
                            <input type="text" name="license_authority" id="license_authority"
                                placeholder="Ù…Ø«Ø§Ù„: Ø§Ù„Ù…Ø±ÙˆØ±ØŒ ÙˆØ²Ø§Ø±Ø© Ø§Ù„Ù†Ù‚Ù„"
                                value="<?php echo isset($editData['license_authority']) ? htmlspecialchars($editData['license_authority']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-times"></i>
                                ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„ØªØ±Ø®ÙŠØµ
                            </label>
                            <input type="date" name="license_expiry_date" id="license_expiry_date"
                                value="<?php echo isset($editData['license_expiry_date']) ? $editData['license_expiry_date'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-certificate"></i>
                                Ø±Ù‚Ù… Ø´Ù‡Ø§Ø¯Ø© Ø§Ù„ÙØ­Øµ
                            </label>
                            <input type="text" name="inspection_certificate_number" id="inspection_certificate_number"
                                placeholder="Ø±Ù‚Ù… Ø´Ù‡Ø§Ø¯Ø© Ø§Ù„ÙØ­Øµ Ø§Ù„ÙÙ†ÙŠØ©"
                                value="<?php echo isset($editData['inspection_certificate_number']) ? htmlspecialchars($editData['inspection_certificate_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-check"></i>
                                ØªØ§Ø±ÙŠØ® Ø¢Ø®Ø± ÙØ­Øµ
                            </label>
                            <input type="date" name="last_inspection_date" id="last_inspection_date"
                                value="<?php echo isset($editData['last_inspection_date']) ? $editData['last_inspection_date'] : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø§Ù„Ù…ÙˆÙ‚Ø¹ ÙˆØ§Ù„ØªÙˆÙØ± -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-map-marker-alt"></i> Ø§Ù„Ù…ÙˆÙ‚Ø¹ ÙˆØ§Ù„ØªÙˆÙØ±</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-location-arrow"></i>
                                Ø§Ù„Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ø­Ø§Ù„ÙŠ
                            </label>
                            <input type="text" name="current_location" id="current_location"
                                placeholder="Ù…Ø«Ø§Ù„: Ù…Ù†Ø¬Ù… Ø§Ù„Ø°Ù‡Ø¨ Ø§Ù„Ø´Ø±Ù‚ÙŠØŒ Ù…Ø³ØªÙˆØ¯Ø¹ Ø§Ù„Ø®Ø±Ø·ÙˆÙ…"
                                value="<?php echo isset($editData['current_location']) ? htmlspecialchars($editData['current_location']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-traffic-light"></i>
                                Ø­Ø§Ù„Ø© Ø§Ù„ØªÙˆÙØ±
                            </label>
                            <select name="availability_status" id="availability_status">
                                <option value="Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„" <?php echo (empty($editData) || $editData['availability_status'] == "Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„") ? "selected" : ""; ?>>Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„
                                </option>
                                <option value="Ù‚ÙŠØ¯ Ø§Ù„Ø§Ø³ØªØ®Ø¯Ø§Ù…" <?php echo (!empty($editData) && $editData['availability_status'] == "Ù‚ÙŠØ¯ Ø§Ù„Ø§Ø³ØªØ®Ø¯Ø§Ù…") ? "selected" : ""; ?>>Ù‚ÙŠØ¯ Ø§Ù„Ø§Ø³ØªØ®Ø¯Ø§Ù…
                                </option>
                                <option value="ØªØ­Øª Ø§Ù„ØµÙŠØ§Ù†Ø©" <?php echo (!empty($editData) && $editData['availability_status'] == "ØªØ­Øª Ø§Ù„ØµÙŠØ§Ù†Ø©") ? "selected" : ""; ?>>ØªØ­Øª Ø§Ù„ØµÙŠØ§Ù†Ø©
                                </option>
                                <option value="Ù…Ø­Ø¬ÙˆØ²Ø©" <?php echo (!empty($editData) && $editData['availability_status'] == "Ù…Ø­Ø¬ÙˆØ²Ø©") ? "selected" : ""; ?>>Ù…Ø­Ø¬ÙˆØ²Ø©</option>
                                <option value="Ù…Ø¹Ø·Ù„Ø©" <?php echo (!empty($editData) && $editData['availability_status'] == "Ù…Ø¹Ø·Ù„Ø©") ? "selected" : ""; ?>>Ù…Ø¹Ø·Ù„Ø©</option>
                                <option value="ÙÙŠ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹" <?php echo (!empty($editData) && $editData['availability_status'] == "ÙÙŠ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹") ? "selected" : ""; ?>>ÙÙŠ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹
                                </option>
                                <option value="Ù…Ø¨ÙŠØ¹Ø©/Ù…Ø³Ø­ÙˆØ¨Ø©" <?php echo (!empty($editData) && $editData['availability_status'] == "Ù…Ø¨ÙŠØ¹Ø©/Ù…Ø³Ø­ÙˆØ¨Ø©") ? "selected" : ""; ?>>Ù…Ø¨ÙŠØ¹Ø©/Ù…Ø³Ø­ÙˆØ¨Ø©
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ù‚ÙŠÙ…Ø© -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-dollar-sign"></i> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ù‚ÙŠÙ…Ø©</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-money-bill-wave"></i>
                                Ø§Ù„Ù‚ÙŠÙ…Ø© Ø§Ù„Ù…Ù‚Ø¯Ø±Ø© Ù„Ù„Ù…Ø¹Ø¯Ø© (Ø¨Ø§Ù„Ø¯ÙˆÙ„Ø§Ø±)
                            </label>
                            <input type="number" name="estimated_value" id="estimated_value" placeholder="Ù…Ø«Ø§Ù„: 150000"
                                min="0" step="0.01"
                                value="<?php echo isset($editData['estimated_value']) ? $editData['estimated_value'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-day"></i>
                                Ø³Ø¹Ø± Ø§Ù„ØªØ£Ø¬ÙŠØ± Ø§Ù„ÙŠÙˆÙ…ÙŠ (Ø¨Ø§Ù„Ø¯ÙˆÙ„Ø§Ø±)
                            </label>
                            <input type="number" name="daily_rental_price" id="daily_rental_price" placeholder="Ù…Ø«Ø§Ù„: 500"
                                min="0" step="0.01"
                                value="<?php echo isset($editData['daily_rental_price']) ? $editData['daily_rental_price'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-alt"></i>
                                Ø³Ø¹Ø± Ø§Ù„ØªØ£Ø¬ÙŠØ± Ø§Ù„Ø´Ù‡Ø±ÙŠ (Ø¨Ø§Ù„Ø¯ÙˆÙ„Ø§Ø±)
                            </label>
                            <input type="number" name="monthly_rental_price" id="monthly_rental_price"
                                placeholder="Ù…Ø«Ø§Ù„: 10000" min="0" step="0.01"
                                value="<?php echo isset($editData['monthly_rental_price']) ? $editData['monthly_rental_price'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-shield-alt"></i>
                                Ø§Ù„ØªØ£Ù…ÙŠÙ†/Ø§Ù„Ø¶Ù…Ø§Ù†
                            </label>
                            <select name="insurance_status" id="insurance_status">
                                <option value="">-- Ø§Ø®ØªØ± Ø­Ø§Ù„Ø© Ø§Ù„ØªØ£Ù…ÙŠÙ† --</option>
                                <option value="Ù…Ø¤Ù…Ù† Ø¨Ø§Ù„ÙƒØ§Ù…Ù„" <?php echo (!empty($editData) && $editData['insurance_status'] == "Ù…Ø¤Ù…Ù† Ø¨Ø§Ù„ÙƒØ§Ù…Ù„") ? "selected" : ""; ?>>Ù…Ø¤Ù…Ù† Ø¨Ø§Ù„ÙƒØ§Ù…Ù„
                                </option>
                                <option value="Ù…Ø¤Ù…Ù† Ø¬Ø²Ø¦ÙŠØ§Ù‹" <?php echo (!empty($editData) && $editData['insurance_status'] == "Ù…Ø¤Ù…Ù† Ø¬Ø²Ø¦ÙŠØ§Ù‹") ? "selected" : ""; ?>>Ù…Ø¤Ù…Ù† Ø¬Ø²Ø¦ÙŠØ§Ù‹</option>
                                <option value="ØºÙŠØ± Ù…Ø¤Ù…Ù†" <?php echo (!empty($editData) && $editData['insurance_status'] == "ØºÙŠØ± Ù…Ø¤Ù…Ù†") ? "selected" : ""; ?>>ØºÙŠØ± Ù…Ø¤Ù…Ù†</option>
                                <option value="Ø¬Ø§Ø±ÙŠ Ø§Ù„ØªØ£Ù…ÙŠÙ†" <?php echo (!empty($editData) && $editData['insurance_status'] == "Ø¬Ø§Ø±ÙŠ Ø§Ù„ØªØ£Ù…ÙŠÙ†") ? "selected" : ""; ?>>Ø¬Ø§Ø±ÙŠ Ø§Ù„ØªØ£Ù…ÙŠÙ†
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- Ù‚Ø³Ù…: Ù…Ù„Ø§Ø­Ø¸Ø§Øª ÙˆØ³Ø¬Ù„ Ø§Ù„ØµÙŠØ§Ù†Ø© -->
                        <!-- ================================= -->
                        <div class="form-section-header">
                            <h6><i class="fas fa-tools"></i> Ù…Ù„Ø§Ø­Ø¸Ø§Øª ÙˆØ³Ø¬Ù„ Ø§Ù„ØµÙŠØ§Ù†Ø©</h6>
                        </div>

                        <div class="form-grid-full">
                            <label>
                                <i class="fas fa-comment-alt"></i>
                                Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¹Ø§Ù…Ø©
                            </label>
                            <textarea name="general_notes" id="general_notes" rows="3"
                                placeholder="Ù…Ø«Ø§Ù„: Ù…Ø¹Ø¯Ø© Ù…ÙˆØ«ÙˆÙ‚Ø©ØŒ ØªØ­ØªØ§Ø¬ Ø¥Ù„Ù‰ ØµÙŠØ§Ù†Ø© Ø¯ÙˆØ±ÙŠØ© ÙƒÙ„ 3 Ø£Ø´Ù‡Ø±"><?php echo isset($editData['general_notes']) ? htmlspecialchars($editData['general_notes']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-wrench"></i>
                                ØªØ§Ø±ÙŠØ® Ø¢Ø®Ø± ØµÙŠØ§Ù†Ø©
                            </label>
                            <input type="date" name="last_maintenance_date" id="last_maintenance_date"
                                value="<?php echo isset($editData['last_maintenance_date']) ? $editData['last_maintenance_date'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-toggle-on"></i>
                                Ø§Ù„Ø­Ø§Ù„Ø© <span class="required-indicator">*</span>
                            </label>
                            <select name="status" id="status" required>
                                <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                                <option value="1" <?php echo (!empty($editData) && $editData['status'] == "1") ? "selected" : ""; ?>>Ù…ØªØ§Ø­Ø©</option>
                                <option value="0" <?php echo (!empty($editData) && $editData['status'] == "0") ? "selected" : ""; ?>>Ù…Ø´ØºÙˆÙ„Ø©</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit">
                                <i class="fas fa-save"></i>
                                <?php echo !empty($editData) ? "ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù…Ø¹Ø¯Ø©" : "Ø­ÙØ¸ Ø§Ù„Ù…Ø¹Ø¯Ø©"; ?>
                            </button>
                            <button type="button" class="btn-secondary"
                                onclick="document.getElementById('projectForm').style.display='none'; document.getElementById('projectForm').reset();">
                                <i class="fas fa-times"></i>
                                Ø¥Ù„ØºØ§Ø¡
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php } ?>

    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª -->
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i>
                Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
            </h5>
        </div>
        <div class="card-body">
            <!-- Ù†Ø¸Ø§Ù… Ø§Ù„ÙÙ„Ø§ØªØ± -->
            <div class="filters-container">
                <div class="filters-header">
                    <h6><i class="fas fa-filter"></i> ÙÙ„ØªØ±Ø© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</h6>
                    <button type="button" class="btn-clear-filters" id="clearFiltersBtn">
                        <i class="fas fa-times-circle"></i> Ø¥Ù„ØºØ§Ø¡ Ø§Ù„ÙÙ„Ø§ØªØ±
                    </button>
                </div>

                <div class="filters-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-truck-loading"></i> ÙÙ„ØªØ±Ø© Ø¨Ø§Ù„Ù…ÙˆØ±Ø¯</label>
                        <select id="filterSupplier" class="filter-select">
                            <option value="">â€” Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† â€”</option>
                            <?php
                            $supplier_filter_query = "SELECT id, name FROM suppliers WHERE status = 1 ORDER BY name";
                            $supplier_filter_result = mysqli_query($conn, $supplier_filter_query);
                            while ($supplier = mysqli_fetch_assoc($supplier_filter_result)) {
                                echo "<option value='" . htmlspecialchars($supplier['name']) . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-list-alt"></i> ÙÙ„ØªØ±Ø© Ø¨Ø§Ù„Ù†ÙˆØ¹</label>
                        <select id="filterType" class="filter-select">
                            <option value="">â€” Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ù†ÙˆØ§Ø¹ â€”</option>
                            <?php
                            $type_filter_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                            $type_filter_result = mysqli_query($conn, $type_filter_query);
                            while ($type_row = mysqli_fetch_assoc($type_filter_result)) {
                                echo "<option value='" . htmlspecialchars($type_row['type']) . "'>" . htmlspecialchars($type_row['type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-toggle-on"></i> ÙÙ„ØªØ±Ø© Ø¨Ø§Ù„Ø­Ø§Ù„Ø©</label>
                        <select id="filterStatus" class="filter-select">
                            <option value="">â€” Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ø§Ù„Ø§Øª â€”</option>
                            <option value="Ù†Ø´Ø·">Ù†Ø´Ø·</option>
                            <option value="ØºÙŠØ± Ù†Ø´Ø·">ØºÙŠØ± Ù†Ø´Ø·</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-traffic-light"></i> ÙÙ„ØªØ±Ø© Ø¨Ø§Ù„ØªÙˆÙØ±</label>
                        <select id="filterAvailability" class="filter-select">
                            <option value="">â€” Ø¬Ù…ÙŠØ¹ Ø­Ø§Ù„Ø§Øª Ø§Ù„ØªÙˆÙØ± â€”</option>
                            <option value="Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„">Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„</option>
                            <option value="Ù…Ø´ØºÙˆÙ„Ø© Ø­Ø§Ù„ÙŠØ§Ù‹">Ù…Ø´ØºÙˆÙ„Ø© Ø­Ø§Ù„ÙŠØ§Ù‹</option>
                            <option value="ØªØ­Øª Ø§Ù„ØµÙŠØ§Ù†Ø©">ØªØ­Øª Ø§Ù„ØµÙŠØ§Ù†Ø©</option>
                            <option value="Ù…Ø¹Ø·Ù„Ø© Ù…Ø¤Ù‚ØªØ§Ù‹">Ù…Ø¹Ø·Ù„Ø© Ù…Ø¤Ù‚ØªØ§Ù‹</option>
                        </select>
                    </div>
                </div>

                <div class="filters-summary" id="filtersSummary" style="display: none;">
                    <span class="summary-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="summary-text"></span>
                </div>
            </div>

            <!-- Ø£Ø²Ø±Ø§Ø± Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª -->
            <div class="column-groups-toggle">
                <button type="button" class="toggle-group-btn active" data-group="basic" title="Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©">
                    <i class="fas fa-info-circle"></i> Ø£Ø³Ø§Ø³ÙŠØ©
                </button>
                <button type="button" class="toggle-group-btn active" data-group="identification"
                    title="Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªØ¹Ø±ÙŠÙ">
                    <i class="fas fa-id-card"></i> Ø§Ù„ØªØ¹Ø±ÙŠÙ
                </button>
                <button type="button" class="toggle-group-btn" data-group="manufacturing" title="Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØµÙ†Ø¹">
                    <i class="fas fa-industry"></i> Ø§Ù„ØµÙ†Ø¹
                </button>
                <button type="button" class="toggle-group-btn" data-group="technical" title="Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„ÙÙ†ÙŠØ©">
                    <i class="fas fa-wrench"></i> ÙÙ†ÙŠØ©
                </button>
                <button type="button" class="toggle-group-btn active" data-group="ownership" title="Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù„ÙƒÙŠØ©">
                    <i class="fas fa-user-tie"></i> Ø§Ù„Ù…Ù„ÙƒÙŠØ©
                </button>
                <button type="button" class="toggle-group-btn active" data-group="status" title="Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª">
                    <i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©
                </button>
                <button type="button" class="toggle-all-btn" title="Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„">
                    <i class="fas fa-eye"></i> Ø§Ù„ÙƒÙ„
                </button>
            </div>

            <table id="projectsTable" class="display nowrap">
                <thead>
                    <tr>
                        <th data-group="basic"><i class="fas fa-hashtag"></i> #</th>
                        <th data-group="basic"><i class="fas fa-truck-loading"></i> Ø§Ù„Ù…ÙˆØ±Ø¯</th>
                        <th data-group="basic"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©</th>
                        <th data-group="identification"><i class="fas fa-hashtag"></i> Ø±Ù‚Ù… ØªØ³Ù„Ø³Ù„ÙŠ</th>
                        <th data-group="basic"><i class="fas fa-list-alt"></i> Ø§Ù„Ù†ÙˆØ¹</th>
                        <th data-group="basic"><i class="fas fa-tag"></i> Ø§Ù„Ø§Ø³Ù…</th>
                        <th data-group="manufacturing"><i class="fas fa-car"></i> Ø§Ù„Ù…ÙˆØ¯ÙŠÙ„</th>
                        <th data-group="manufacturing"><i class="fas fa-calendar"></i> Ø³Ù†Ø© Ø§Ù„ØµÙ†Ø¹</th>
                        <th data-group="technical"><i class="fas fa-cogs"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¹Ø¯Ø©</th>
                        <th data-group="ownership"><i class="fas fa-user"></i> Ø§Ù„Ù…Ø§Ù„Ùƒ</th>
                        <th data-group="technical"><i class="fas fa-traffic-light"></i> Ø§Ù„ØªÙˆÙØ±</th>
                        <th data-group="status"><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
                        <th data-group="status"><i class="fas fa-sliders-h"></i> Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query2 = "
                        SELECT 
                            m.id, 
                            s.name AS supplier_name, 
                            m.type, 
                            m.code, 
                            m.name, 
                            m.status,
                            m.serial_number,
                            m.model,
                            m.manufacturing_year,
                            m.equipment_condition,
                            m.actual_owner_name,
                            m.availability_status,
                            o.project_id, 
                            o.status AS operation_status,
                            COUNT(DISTINCT d.id) AS drivers_count
                        FROM equipments m
                        JOIN suppliers s ON m.suppliers = s.id
                        LEFT JOIN operations o 
                            ON o.equipment = m.id 
                            AND o.status = '1'
                        LEFT JOIN equipment_drivers ed 
                            ON ed.equipment_id = m.id
                        LEFT JOIN drivers d 
                            ON d.id = ed.driver_id 
                            AND ed.status = '1'
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                    $result = mysqli_query($conn, $query2);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><strong class='supplier-name'>" . htmlspecialchars($row['supplier_name']) . "</strong></td>";
                        echo "<td><span class='mono code-badge'>" . htmlspecialchars($row['code']) . "</span></td>";

                        // Ø±Ù‚Ù… ØªØ³Ù„Ø³Ù„ÙŠ
                        $serial = !empty($row['serial_number'])
                            ? "<span class='mono'>" . htmlspecialchars($row['serial_number']) . "</span>"
                            : "<span class='text-muted'>ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>";
                        echo "<td>" . $serial . "</td>";

                        // Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©
                        $type_icon = $row['type'] == "1" ? "fa-tractor" : "fa-truck-moving";
                        $type_text = $row['type'] == "1" ? "Ø­ÙØ§Ø±" : "Ù‚Ù„Ø§Ø¨";
                        echo "<td><span class='badge-type'><i class='fas $type_icon'></i> $type_text</span></td>";

                        // Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© (ØªÙ‡ÙŠØ¦Ø© Ø§Ù„Ù…ØªØºÙŠØ±)
                        $name_display = "<strong>" . htmlspecialchars($row['name']) . "</strong>";

                        // Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ Ø§Ù„Ù†Ø´Ø·
                        if (!empty($row['project'])) {
                            $p_res = mysqli_query($conn, "SELECT name FROM project WHERE id='" . $row['project'] . "'");
                            if ($p_res && mysqli_num_rows($p_res) > 0) {
                                $p = mysqli_fetch_assoc($p_res);
                                $name_display .= "<br><span class='project-link'><i class='fas fa-project-diagram'></i> " . htmlspecialchars($p['name']) . "</span>";
                            }
                        }

                        // Ø¹Ø¯Ø¯ Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ† Ø§Ù„Ù†Ø´Ø·ÙŠÙ†
                        if ($row['drivers_count'] > 0) {
                            $name_display .= "<br><span class='extra-info'><i class='fas fa-users'></i> " . $row['drivers_count'] . " Ø³Ø§Ø¦Ù‚</span>";
                        }

                        echo "<td>" . $name_display . "</td>";

                        // Ø§Ù„Ù…ÙˆØ¯ÙŠÙ„
                        $model = !empty($row['model']) ? htmlspecialchars($row['model']) : "<span class='text-muted'>ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>";
                        echo "<td>" . $model . "</td>";

                        // Ø³Ù†Ø© Ø§Ù„ØµÙ†Ø¹
                        $manufacturing_year = !empty($row['manufacturing_year']) ? $row['manufacturing_year'] : "<span class='text-muted'>ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>";
                        echo "<td>" . $manufacturing_year . "</td>";

                        // Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¹Ø¯Ø©
                        $equipment_condition = !empty($row['equipment_condition']) ? htmlspecialchars($row['equipment_condition']) : "<span class='text-muted'>ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>";
                        echo "<td>" . $equipment_condition . "</td>";

                        // Ø§Ù„Ù…Ø§Ù„Ùƒ
                        $owner = !empty($row['actual_owner_name']) ? htmlspecialchars($row['actual_owner_name']) : "<span class='text-muted'>ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>";
                        echo "<td>" . $owner . "</td>";

                        // Ø§Ù„ØªÙˆÙØ±
                        $availability = !empty($row['availability_status']) ? htmlspecialchars($row['availability_status']) : "Ù…ØªØ§Ø­Ø© Ù„Ù„Ø¹Ù…Ù„";
                        echo "<td>" . $availability . "</td>";

                        // Ø§Ù„Ø­Ø§Ù„Ø©
                        if (!empty($row['project_id']) && $row['operation_status'] == "1") {
                            echo "<td><span class='badge-working'><i class='fas fa-spinner fa-spin'></i> Ù‚ÙŠØ¯ Ø§Ù„ØªØ´ØºÙŠÙ„</span></td>";
                        } else {
                            if ($row['status'] == "1") {
                                echo "<td><span class='badge-available'><i class='fas fa-check-circle'></i> Ù…ØªØ§Ø­Ø©</span></td>";
                            } else {
                                echo "<td><span class='badge-busy'><i class='fas fa-times-circle'></i> Ù…Ø´ØºÙˆÙ„Ø©</span></td>";
                            }
                        }

                        // Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª
                        echo "<td>";
                        echo "<a href='javascript:void(0)' class='action-btn view viewEquipmentBtn' data-id='" . $row['id'] . "' title='Ø¹Ø±Ø¶ Ø§Ù„ØªÙØ§ØµÙŠÙ„'>
                                                        <i class='fas fa-eye'></i>
                                                    </a>";
                        if ($can_edit) {
                            echo "<a href='equipments_fleet.php?edit=" . $row['id'] . "' class='action-btn btn-edit' title='ØªØ¹Ø¯ÙŠÙ„'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </a>";
                        }
                        if ($can_delete) {
                            echo "<a href='equipments_fleet.php?delete_id=" . $row['id'] . "' class='action-btn delete' onclick=\"return confirm('Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¹Ø¯Ø©ØŸ')\" title='Ø­Ø°Ù'>
                                                                        <i class='fas fa-trash'></i>
                                                                    </a>";
                        }
                        echo "</td>";

                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Ø¹Ø±Ø¶ ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø© -->
    <div id="viewEquipmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-eye"></i> Ø¹Ø±Ø¶ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¹Ø¯Ø©</h5>
                <button class="close-modal" id="closeEquipmentModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="view-modal-body">
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-barcode"></i> ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©</div>
                        <div class="view-item-value" id="view_eq_code">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-tag"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø¯Ø©</div>
                        <div class="view-item-value" id="view_eq_name">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-tools"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©</div>
                        <div class="view-item-value" id="view_eq_type">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-truck-loading"></i> Ø§Ù„Ù…ÙˆØ±Ø¯</div>
                        <div class="view-item-value" id="view_eq_supplier">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-project-diagram"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
                        <div class="view-item-value" id="view_eq_project">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-mountain"></i> Ø§Ù„Ù…Ù†Ø¬Ù…</div>
                        <div class="view-item-value" id="view_eq_mine">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-hashtag"></i> Ø§Ù„Ø±Ù‚Ù… Ø§Ù„ØªØ³Ù„Ø³Ù„ÙŠ</div>
                        <div class="view-item-value" id="view_eq_serial">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-car"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡ÙŠÙƒÙ„</div>
                        <div class="view-item-value" id="view_eq_chassis">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-industry"></i> Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù…ØµÙ†Ø¹Ø©</div>
                        <div class="view-item-value" id="view_eq_manufacturer">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-car-side"></i> Ø§Ù„Ù…ÙˆØ¯ÙŠÙ„</div>
                        <div class="view-item-value" id="view_eq_model">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar"></i> Ø³Ù†Ø© Ø§Ù„ØµÙ†Ø¹</div>
                        <div class="view-item-value" id="view_eq_year">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar-plus"></i> Ø³Ù†Ø© Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯</div>
                        <div class="view-item-value" id="view_eq_import_year">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-cogs"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¹Ø¯Ø©</div>
                        <div class="view-item-value" id="view_eq_condition">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ´ØºÙŠÙ„</div>
                        <div class="view-item-value" id="view_eq_hours">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-car-crash"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø±Ùƒ</div>
                        <div class="view-item-value" id="view_eq_engine">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-circle-notch"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ø¥Ø·Ø§Ø±Ø§Øª</div>
                        <div class="view-item-value" id="view_eq_tires">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-user"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø§Ù„Ùƒ</div>
                        <div class="view-item-value" id="view_eq_owner">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-briefcase"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ø§Ù„Ùƒ</div>
                        <div class="view-item-value" id="view_eq_owner_type">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-phone"></i> Ù‡Ø§ØªÙ Ø§Ù„Ù…Ø§Ù„Ùƒ</div>
                        <div class="view-item-value" id="view_eq_owner_phone">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-handshake"></i> Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ù…Ø§Ù„Ùƒ Ø¨Ø§Ù„Ù…ÙˆØ±Ø¯</div>
                        <div class="view-item-value" id="view_eq_owner_relation">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-address-card"></i> Ø±Ù‚Ù… Ø§Ù„ØªØ±Ø®ÙŠØµ</div>
                        <div class="view-item-value" id="view_eq_license">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-landmark"></i> Ø¬Ù‡Ø© Ø§Ù„ØªØ±Ø®ÙŠØµ</div>
                        <div class="view-item-value" id="view_eq_license_authority">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar-times"></i> Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„ØªØ±Ø®ÙŠØµ</div>
                        <div class="view-item-value" id="view_eq_license_expiry">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-certificate"></i> Ø±Ù‚Ù… Ø´Ù‡Ø§Ø¯Ø© Ø§Ù„ÙØ­Øµ</div>
                        <div class="view-item-value" id="view_eq_inspection">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar-check"></i> Ø¢Ø®Ø± ÙØ­Øµ</div>
                        <div class="view-item-value" id="view_eq_last_inspection">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Ø§Ù„Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ø­Ø§Ù„ÙŠ</div>
                        <div class="view-item-value" id="view_eq_location">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-traffic-light"></i> Ø­Ø§Ù„Ø© Ø§Ù„ØªÙˆÙØ±</div>
                        <div class="view-item-value" id="view_eq_availability">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-money-bill-wave"></i> Ø§Ù„Ù‚ÙŠÙ…Ø© Ø§Ù„Ù…Ù‚Ø¯Ø±Ø©</div>
                        <div class="view-item-value" id="view_eq_value">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar-day"></i> Ø³Ø¹Ø± Ø§Ù„ØªØ£Ø¬ÙŠØ± Ø§Ù„ÙŠÙˆÙ…ÙŠ</div>
                        <div class="view-item-value" id="view_eq_daily">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-calendar-alt"></i> Ø³Ø¹Ø± Ø§Ù„ØªØ£Ø¬ÙŠØ± Ø§Ù„Ø´Ù‡Ø±ÙŠ</div>
                        <div class="view-item-value" id="view_eq_monthly">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-shield-alt"></i> Ø§Ù„ØªØ£Ù…ÙŠÙ†/Ø§Ù„Ø¶Ù…Ø§Ù†</div>
                        <div class="view-item-value" id="view_eq_insurance">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-comment-alt"></i> Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¹Ø§Ù…Ø©</div>
                        <div class="view-item-value" id="view_eq_notes">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-wrench"></i> Ø¢Ø®Ø± ØµÙŠØ§Ù†Ø©</div>
                        <div class="view-item-value" id="view_eq_last_maintenance">-</div>
                    </div>
                    <div class="view-item">
                        <div class="view-item-label"><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</div>
                        <div class="view-item-value" id="view_eq_status">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">

                <a id="viewEquipmentEditBtn" class="btn-modal btn-modal-save" style="text-decoration: none;">
                    <i class="fas fa-edit"></i> ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø©
                </a>

                <a id="viewEquipmentDeleteBtn" class="btn-modal btn-modal-danger" style="text-decoration: none; display: none;" onclick="return confirm('Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¹Ø¯Ø©ØŸ');">
                    <i class="fas fa-trash"></i> Ø­Ø°Ù Ø§Ù„Ù…Ø¹Ø¯Ø©
                </a>

                <button type="button" class="btn-modal btn-modal-cancel" id="closeEquipmentModalFooter">
                    <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
                </button>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
        (function () {
            $(document).ready(function () {
                var table = $('#projectsTable').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        { extend: 'copy', text: 'Ù†Ø³Ø®' },
                        { extend: 'excel', text: 'ØªØµØ¯ÙŠØ± Excel' },
                        { extend: 'csv', text: 'ØªØµØ¯ÙŠØ± CSV' },
                        { extend: 'pdf', text: 'ØªØµØ¯ÙŠØ± PDF' },
                        { extend: 'print', text: 'Ø·Ø¨Ø§Ø¹Ø©' }
                    ],
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                    }
                });

                // Ù†Ø¸Ø§Ù… Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
                var columnGroups = {
                    'basic': [0, 1, 2, 4, 5],        // #ØŒ Ø§Ù„Ù…ÙˆØ±Ø¯ØŒ ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©ØŒ Ø§Ù„Ù†ÙˆØ¹ØŒ Ø§Ù„Ø§Ø³Ù…
                    'identification': [3],            // Ø±Ù‚Ù… ØªØ³Ù„Ø³Ù„ÙŠ
                    'manufacturing': [6, 7],          // Ø§Ù„Ù…ÙˆØ¯ÙŠÙ„ØŒ Ø³Ù†Ø© Ø§Ù„ØµÙ†Ø¹
                    'technical': [8, 10],             // Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¹Ø¯Ø©ØŒ Ø§Ù„ØªÙˆÙØ±
                    'ownership': [9],                 // Ø§Ù„Ù…Ø§Ù„Ùƒ
                    'status': [11, 12]                // Ø§Ù„Ø­Ø§Ù„Ø©ØŒ Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª
                };

                // Ø­ÙØ¸ Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª (Ø§Ù„ØµÙ†Ø¹ ÙˆØ§Ù„ÙÙ†ÙŠØ© Ù…Ø®ÙÙŠØªÙŠÙ† Ø¨Ø´ÙƒÙ„ Ø§ÙØªØ±Ø§Ø¶ÙŠ)
                var groupsState = {
                    'basic': true,
                    'identification': true,
                    'manufacturing': false,
                    'technical': false,
                    'ownership': true,
                    'status': true
                };

                // Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø© Ø§Ù„Ù…Ø®ÙÙŠØ© Ø¨Ø´ÙƒÙ„ Ø§ÙØªØ±Ø§Ø¶ÙŠ Ø¹Ù†Ø¯ Ø§Ù„ØªØ­Ù…ÙŠÙ„
                columnGroups['manufacturing'].forEach(function (colIndex) {
                    table.column(colIndex).visible(false);
                });
                columnGroups['technical'].forEach(function (colIndex) {
                    table.column(colIndex).visible(false);
                });

                // Ù†Ø¸Ø§Ù… Ø§Ù„ÙÙ„ØªØ±Ø© Ø§Ù„Ø§Ø­ØªØ±Ø§ÙÙŠ
                var activeFilters = {
                    supplier: '',
                    type: '',
                    status: '',
                    availability: ''
                };

                // ØªÙ‡ÙŠØ¦Ø© Ø§Ù„ÙÙ„Ø§ØªØ±
                $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').on('change', function () {
                    var filterType = $(this).attr('id').replace('filter', '').toLowerCase();
                    activeFilters[filterType] = $(this).val();
                    applyFilters();
                    updateFiltersSummary();
                });

                // ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„ÙÙ„Ø§ØªØ±
                function applyFilters() {
                    $.fn.dataTable.ext.search.push(
                        function (settings, data, dataIndex) {
                            // data[1] = Ø§Ù„Ù…ÙˆØ±Ø¯
                            // data[4] = Ø§Ù„Ù†ÙˆØ¹ (ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ù†Øµ Ù…Ø«Ù„ "Ø­ÙØ§Ø±" Ø£Ùˆ "Ù‚Ù„Ø§Ø¨")
                            // data[11] = Ø§Ù„Ø­Ø§Ù„Ø© (ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ "Ù†Ø´Ø·" Ø£Ùˆ "ØºÙŠØ± Ù†Ø´Ø·")
                            // data[10] = Ø§Ù„ØªÙˆÙØ±

                            var supplierMatch = true;
                            var typeMatch = true;
                            var statusMatch = true;
                            var availabilityMatch = true;

                            // ÙÙ„ØªØ±Ø© Ø§Ù„Ù…ÙˆØ±Ø¯
                            if (activeFilters.supplier !== '') {
                                supplierMatch = data[1].indexOf(activeFilters.supplier) !== -1;
                            }

                            // ÙÙ„ØªØ±Ø© Ø§Ù„Ù†ÙˆØ¹
                            if (activeFilters.type !== '') {
                                typeMatch = data[4].indexOf(activeFilters.type) !== -1;
                            }

                            // ÙÙ„ØªØ±Ø© Ø§Ù„Ø­Ø§Ù„Ø©
                            if (activeFilters.status !== '') {
                                statusMatch = data[11].indexOf(activeFilters.status) !== -1;
                            }

                            // ÙÙ„ØªØ±Ø© Ø§Ù„ØªÙˆÙØ±
                            if (activeFilters.availability !== '') {
                                availabilityMatch = data[10].indexOf(activeFilters.availability) !== -1;
                            }

                            return supplierMatch && typeMatch && statusMatch && availabilityMatch;
                        }
                    );

                    table.draw();

                    // Ø¥Ø²Ø§Ù„Ø© Ø¯Ø§Ù„Ø© Ø§Ù„Ø¨Ø­Ø« Ø¨Ø¹Ø¯ Ø§Ù„ØªØ·Ø¨ÙŠÙ‚ Ù„ØªØ¬Ù†Ø¨ Ø§Ù„ØªÙƒØ±Ø§Ø±
                    $.fn.dataTable.ext.search.pop();
                }

                // ØªØ­Ø¯ÙŠØ« Ù…Ù„Ø®Øµ Ø§Ù„ÙÙ„Ø§ØªØ±
                function updateFiltersSummary() {
                    var activeCount = 0;
                    var summaryParts = [];

                    if (activeFilters.supplier) {
                        activeCount++;
                        summaryParts.push('Ø§Ù„Ù…ÙˆØ±Ø¯: ' + activeFilters.supplier);
                    }
                    if (activeFilters.type) {
                        activeCount++;
                        summaryParts.push('Ø§Ù„Ù†ÙˆØ¹: ' + activeFilters.type);
                    }
                    if (activeFilters.status) {
                        activeCount++;
                        summaryParts.push('Ø§Ù„Ø­Ø§Ù„Ø©: ' + activeFilters.status);
                    }
                    if (activeFilters.availability) {
                        activeCount++;
                        summaryParts.push('Ø§Ù„ØªÙˆÙØ±: ' + activeFilters.availability);
                    }

                    var $summary = $('#filtersSummary');
                    if (activeCount > 0) {
                        $summary.find('.summary-text').text(
                            'ØªÙ… ØªØ·Ø¨ÙŠÙ‚ ' + activeCount + ' ÙÙ„ØªØ±: ' + summaryParts.join(' | ')
                        );
                        $summary.slideDown(300);
                    } else {
                        $summary.slideUp(300);
                    }
                }

                // Ø¥Ù„ØºØ§Ø¡ Ø¬Ù…ÙŠØ¹ Ø§Ù„ÙÙ„Ø§ØªØ±
                $('#clearFiltersBtn').on('click', function () {
                    activeFilters = {
                        supplier: '',
                        type: '',
                        status: '',
                        availability: ''
                    };

                    $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').val('');
                    applyFilters();
                    updateFiltersSummary();

                    // ØªØ£Ø«ÙŠØ± Ø¨ØµØ±ÙŠ
                    $(this).addClass('btn-clear-active');
                    setTimeout(function () {
                        $('#clearFiltersBtn').removeClass('btn-clear-active');
                    }, 300);
                });

                // ÙˆØ¸ÙŠÙØ© Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ù…Ø¬Ù…ÙˆØ¹Ø©
                function toggleGroup(groupName) {
                    var columns = columnGroups[groupName];
                    var isVisible = groupsState[groupName];

                    columns.forEach(function (colIndex) {
                        table.column(colIndex).visible(!isVisible);
                    });

                    groupsState[groupName] = !isVisible;
                }

                // Ù…Ø¹Ø§Ù„Ø¬ Ø§Ù„Ù†Ù‚Ø± Ø¹Ù„Ù‰ Ø£Ø²Ø±Ø§Ø± Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
                $('.toggle-group-btn').on('click', function () {
                    var groupName = $(this).data('group');
                    toggleGroup(groupName);
                    $(this).toggleClass('active');
                });

                // Ø²Ø± Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„
                var allVisible = true;
                $('.toggle-all-btn').on('click', function () {
                    allVisible = !allVisible;

                    Object.keys(columnGroups).forEach(function (groupName) {
                        var columns = columnGroups[groupName];
                        columns.forEach(function (colIndex) {
                            table.column(colIndex).visible(allVisible);
                        });
                        groupsState[groupName] = allVisible;
                    });

                    if (allVisible) {
                        $('.toggle-group-btn').addClass('active');
                        $(this).html('<i class="fas fa-eye"></i> Ø§Ù„ÙƒÙ„');
                    } else {
                        $('.toggle-group-btn').removeClass('active');
                        $(this).html('<i class="fas fa-eye-slash"></i> Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„');
                    }
                });
            });

            const toggleFormBtn = document.getElementById('toggleForm');
            const equipmentForm = document.getElementById('projectForm');
            const projectSelect = document.getElementById('selected_project_id');

            if (toggleFormBtn && equipmentForm) {
                toggleFormBtn.addEventListener('click', function () {
                    equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
                });
            }

            if (projectSelect) {
                projectSelect.addEventListener('change', function () {
                    if (this.value) {
                        document.getElementById('projectSelectForm').submit();
                    }
                });
            }

            // ØªØ­Ù…ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù†Ø¯ ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø©
            <?php if (!empty($editData)) { ?>
                $(document).ready(function () {
                    // Ø¹Ø±Ø¶ Ø§Ù„ÙÙˆØ±Ù…
                    $('#projectForm').show();

                    // Ø§Ù„ØªÙ…Ø±ÙŠØ± Ù„Ù„ÙÙˆØ±Ù…
                    $('html, body').animate({
                        scrollTop: $('#projectForm').offset().top - 100
                    }, 500);
                });
            <?php } ?>

            // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
            const canEdit = <?php echo json_encode($can_edit); ?>;
            const canDelete = <?php echo json_encode($can_delete); ?>;

            // Equipment view modal
            const viewEquipmentModal = document.getElementById('viewEquipmentModal');
            const closeEquipmentModalBtn = document.getElementById('closeEquipmentModal');
            const closeEquipmentModalFooter = document.getElementById('closeEquipmentModalFooter');

            function setViewValue(elementId, value) {
                const el = document.getElementById(elementId);
                if (!el) return;
                const safeValue = (value !== null && value !== undefined && value !== '') ? value : 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯';
                el.textContent = safeValue;
            }

            function formatCurrency(value) {
                if (value === null || value === undefined || value === '') return 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯';
                const num = parseFloat(value);
                if (Number.isNaN(num)) return value;
                return '$' + num.toLocaleString();
            }

            function formatType(value) {
                if (!value) return 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯';
                return String(value) === '1' ? 'Ø­ÙØ§Ø±' : 'Ù‚Ù„Ø§Ø¨';
            }

            function formatStatus(value) {
                if (value === null || value === undefined || value === '') return 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯';
                return String(value) === '1' ? 'Ù…ØªØ§Ø­Ø©' : 'Ù…Ø´ØºÙˆÙ„Ø©';
            }

            $(document).on('click', '.viewEquipmentBtn', function () {
                const equipmentId = $(this).data('id');
                if (!equipmentId || !viewEquipmentModal) return;

                viewEquipmentModal.style.display = 'flex';

                const loadingText = 'Ø¬Ø§Ø± Ø§Ù„ØªØ­Ù…ÙŠÙ„...';
                [
                    'view_eq_code', 'view_eq_name', 'view_eq_type', 'view_eq_supplier', 'view_eq_project', 'view_eq_mine',
                    'view_eq_serial', 'view_eq_chassis', 'view_eq_manufacturer', 'view_eq_model', 'view_eq_year',
                    'view_eq_import_year', 'view_eq_condition', 'view_eq_hours', 'view_eq_engine', 'view_eq_tires',
                    'view_eq_owner', 'view_eq_owner_type', 'view_eq_owner_phone', 'view_eq_owner_relation',
                    'view_eq_license', 'view_eq_license_authority', 'view_eq_license_expiry', 'view_eq_inspection',
                    'view_eq_last_inspection', 'view_eq_location', 'view_eq_availability', 'view_eq_value',
                    'view_eq_daily', 'view_eq_monthly', 'view_eq_insurance', 'view_eq_notes', 'view_eq_last_maintenance',
                    'view_eq_status'
                ].forEach(id => setViewValue(id, loadingText));

                const editBtn = document.getElementById('viewEquipmentEditBtn');
                if (editBtn) {
                    editBtn.setAttribute('href', 'equipments_fleet.php?edit=' + equipmentId);
                    // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¸Ù‡ÙˆØ± Ø§Ù„Ø²Ø± Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª
                    if (canEdit) {
                        editBtn.style.display = '';
                    } else {
                        editBtn.style.display = 'none';
                    }
                }

                const deleteBtn = document.getElementById('viewEquipmentDeleteBtn');
                if (deleteBtn) {
                    deleteBtn.setAttribute('href', 'equipments_fleet.php?delete_id=' + equipmentId);
                    // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¸Ù‡ÙˆØ± Ø§Ù„Ø²Ø± Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª
                    if (canDelete) {
                        deleteBtn.style.display = '';
                    } else {
                        deleteBtn.style.display = 'none';
                    }
                }

                $.ajax({
                    url: 'get_equipment_details.php',
                    type: 'GET',
                    data: { id: equipmentId },
                    dataType: 'json',
                    success: function (response) {
                        if (!response.success || !response.data) {
                            setViewValue('view_eq_name', 'ØªØ¹Ø°Ø± ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª');
                            return;
                        }

                        const data = response.data;
                        setViewValue('view_eq_code', data.code);
                        setViewValue('view_eq_name', data.name);
                        setViewValue('view_eq_type', formatType(data.type));
                        setViewValue('view_eq_supplier', data.supplier_name);
                        setViewValue('view_eq_project', data.project_name);
                        setViewValue('view_eq_mine', data.mine_name);
                        setViewValue('view_eq_serial', data.serial_number);
                        setViewValue('view_eq_chassis', data.chassis_number);
                        setViewValue('view_eq_manufacturer', data.manufacturer);
                        setViewValue('view_eq_model', data.model);
                        setViewValue('view_eq_year', data.manufacturing_year);
                        setViewValue('view_eq_import_year', data.import_year);
                        setViewValue('view_eq_condition', data.equipment_condition);
                        setViewValue('view_eq_hours', data.operating_hours ? data.operating_hours + ' Ø³Ø§Ø¹Ø©' : 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯');
                        setViewValue('view_eq_engine', data.engine_condition);
                        setViewValue('view_eq_tires', data.tires_condition);
                        setViewValue('view_eq_owner', data.actual_owner_name);
                        setViewValue('view_eq_owner_type', data.owner_type);
                        setViewValue('view_eq_owner_phone', data.owner_phone);
                        setViewValue('view_eq_owner_relation', data.owner_supplier_relation);
                        setViewValue('view_eq_license', data.license_number);
                        setViewValue('view_eq_license_authority', data.license_authority);
                        setViewValue('view_eq_license_expiry', data.license_expiry_date);
                        setViewValue('view_eq_inspection', data.inspection_certificate_number);
                        setViewValue('view_eq_last_inspection', data.last_inspection_date);
                        setViewValue('view_eq_location', data.current_location);
                        setViewValue('view_eq_availability', data.availability_status);
                        setViewValue('view_eq_value', formatCurrency(data.estimated_value));
                        setViewValue('view_eq_daily', formatCurrency(data.daily_rental_price));
                        setViewValue('view_eq_monthly', formatCurrency(data.monthly_rental_price));
                        setViewValue('view_eq_insurance', data.insurance_status);
                        setViewValue('view_eq_notes', data.general_notes);
                        setViewValue('view_eq_last_maintenance', data.last_maintenance_date);
                        setViewValue('view_eq_status', formatStatus(data.status));
                    },
                    error: function () {
                        setViewValue('view_eq_name', 'ØªØ¹Ø°Ø± Ø§Ù„Ø§ØªØµØ§Ù„ Ø¨Ø§Ù„Ø®Ø§Ø¯Ù…');
                    }
                });
            });

            function closeEquipmentModal() {
                if (viewEquipmentModal) {
                    viewEquipmentModal.style.display = 'none';
                }
            }

            if (closeEquipmentModalBtn) {
                closeEquipmentModalBtn.addEventListener('click', closeEquipmentModal);
            }

            if (closeEquipmentModalFooter) {
                closeEquipmentModalFooter.addEventListener('click', closeEquipmentModal);
            }

            if (viewEquipmentModal) {
                viewEquipmentModal.addEventListener('click', function (event) {
                    if (event.target === viewEquipmentModal) {
                        closeEquipmentModal();
                    }
                });
            }

            // Toggle Form Functionality
        })();
    </script>

    <!-- ========================================== -->
    <!-- Modal Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel/CSV -->
    <!-- ========================================== -->
    <div id="importExcelModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center;">
        <div
            style="background:white; border-radius:16px; width:90%; max-width:650px; box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow:hidden; animation:modalSlideIn 0.3s ease;">
            <!-- Ø±Ø£Ø³ Modal -->
            <div
                style="background:linear-gradient(135deg, #0c1c3e 0%, #1e3a5f 100%); color:white; padding:24px 32px; display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0; font-size:1.4rem; font-weight:700; display:flex; align-items:center; gap:12px;">
                    <i class="fas fa-file-import" style="color:#e8b800;"></i>
                    Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ù…Ù† Excel/CSV
                </h5>
                <button onclick="closeImportModal()"
                    style="background:rgba(255,255,255,0.1); border:none; color:white; font-size:1.5rem; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Ø¬Ø³Ù… Modal -->
            <div style="padding:32px;">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <!-- Ù…Ù†Ø·Ù‚Ø© Ø±ÙØ¹ Ø§Ù„Ù…Ù„Ù -->
                    <div style="margin-bottom:24px;">
                        <label
                            style="display:block; font-weight:600; margin-bottom:12px; color:#0c1c3e; font-size:1rem;">
                            <i class="fas fa-upload" style="color:#e8b800; margin-left:6px;"></i>
                            Ø§Ø®ØªØ± Ù…Ù„Ù Excel Ø£Ùˆ CSV
                        </label>
                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                            style="width:100%; padding:14px; border:2px dashed #cbd5e1; border-radius:10px; font-size:0.95rem; cursor:pointer; transition:all 0.3s; background:#f8fafc;">
                    </div>

                    <!-- Ù…Ø¤Ø´Ø± Ø§Ù„ØªØ­Ù…ÙŠÙ„ -->
                    <div id="importProgress"
                        style="display:none; padding:16px; background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; margin-bottom:20px; text-align:center; color:#1e40af;">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem; margin-bottom:8px;"></i>
                        <p style="margin:0; font-weight:600;">Ø¬Ø§Ø±ÙŠ Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ù…Ù„Ù... ÙŠØ±Ø¬Ù‰ Ø§Ù„Ø§Ù†ØªØ¸Ø§Ø±</p>
                    </div>

                    <!-- Ù†ØªÙŠØ¬Ø© Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ -->
                    <div id="importResult" style="display:none; margin-bottom:20px;"></div>

                    <!-- Ø§Ù„ØªØ¹Ù„ÙŠÙ…Ø§Øª -->
                    <div
                        style="background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:18px; margin-bottom:24px;">
                        <h6 style="margin:0 0 12px 0; color:#1e40af; font-weight:700; font-size:0.95rem;">
                            <i class="fas fa-info-circle"></i> ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯:
                        </h6>
                        <ul style="margin:0; padding-right:20px; color:#475569; font-size:0.9rem; line-height:1.8;">
                            <li>Ù‚Ù… Ø¨ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel Ø£Ùˆ CSV Ø£ÙˆÙ„Ø§Ù‹</li>
                            <li>Ø§Ù…Ù„Ø£ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙÙŠ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬ (Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©: ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©ØŒ Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯ØŒ Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©ØŒ Ø§Ø³Ù…
                                Ø§Ù„Ù…Ø¹Ø¯Ø©)</li>
                            <li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯ ÙˆÙ†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø© Ù…ÙˆØ¬ÙˆØ¯Ø§Ù† ÙÙŠ Ø§Ù„Ù†Ø¸Ø§Ù…</li>
                            <li>Ø§Ø­Ø°Ù Ø§Ù„Ø£Ù…Ø«Ù„Ø© Ù‚Ø¨Ù„ Ø±ÙØ¹ Ø§Ù„Ù…Ù„Ù</li>
                            <li>Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù„Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù: 5 Ù…ÙŠØ¬Ø§ Ø¨Ø§ÙŠØª</li>
                            <li>Ø§Ù„ØµÙŠØº Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx, .xls, .csv</li>
                        </ul>
                    </div>

                    <!-- Ø£Ø²Ø±Ø§Ø± Ø§Ù„ØªØ­ÙƒÙ… -->
                    <div style="display:flex; gap:12px; justify-content:flex-end;">
                        <button type="button" onclick="closeImportModal()"
                            style="padding:12px 28px; border:2px solid #e2e8f0; background:white; color:#64748b; border-radius:8px; font-weight:600; cursor:pointer; transition:all 0.3s; font-size:0.95rem;">
                            <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                        </button>
                        <button type="submit"
                            style="padding:12px 28px; background:linear-gradient(135deg, #16a34a 0%, #059669 100%); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:all 0.3s; box-shadow:0 2px 8px rgba(22,163,74,0.25); font-size:0.95rem;">
                            <i class="fas fa-file-import"></i> Ø±ÙØ¹ ÙˆØ§Ø³ØªÙŠØ±Ø§Ø¯
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Ù†Ø¸Ø§Ù… Ø§Ù„ÙÙ„ØªØ±Ø© Ø§Ù„Ø§Ø­ØªØ±Ø§ÙÙŠ */
        .filters-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .filters-header h6 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-header h6 i {
            color: var(--gold);
            font-size: 1.2rem;
        }

        .btn-clear-filters {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            background: var(--red-soft);
            color: var(--red);
            border: 1.5px solid rgba(220, 38, 38, .18);
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all var(--ease);
            font-family: 'Cairo', sans-serif;
        }

        .btn-clear-filters:hover {
            background: var(--red);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(220, 38, 38, .35);
        }

        .btn-clear-active {
            animation: btnClearPulse 0.3s ease;
        }

        @keyframes btnClearPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.08);
            }
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 12px;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-item label {
            font-weight: 700;
            color: var(--txt);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
        }

        .filter-item label i {
            color: var(--gold);
        }

        .filter-select {
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.92rem;
            font-family: 'Cairo', sans-serif;
            transition: all var(--ease);
            background: var(--surface);
            color: var(--txt);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-soft);
        }

        .filter-select:hover {
            border-color: var(--navy);
        }

        .filters-summary {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: var(--blue-soft);
            border: 1.5px solid rgba(37, 99, 235, .25);
            border-radius: var(--radius);
            margin-top: 16px;
            animation: slideDown 0.3s ease;
        }

        .filters-summary .summary-icon {
            flex-shrink: 0;
            color: var(--blue);
            font-size: 1.1rem;
        }

        .filters-summary .summary-text {
            color: var(--blue);
            font-weight: 600;
            font-size: 0.9rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filters-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .btn-clear-filters {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        #importExcelModal input[type="file"]:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        #importExcelModal button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(22, 163, 74, 0.35);
        }

        #importExcelModal button[type="button"]:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
    </style>

    <script>
        // ÙØªØ­ Modal Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
        $('#openImportModal').on('click', function () {
            $('#importExcelModal').css('display', 'flex').hide().fadeIn(300);
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
                alert('Ø§Ù„Ø±Ø¬Ø§Ø¡ Ø§Ø®ØªÙŠØ§Ø± Ù…Ù„Ù Excel Ø£Ùˆ CSV');
                return;
            }

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('action', 'import_excel');

            $('#importProgress').show();
            $('#importResult').hide();

            $.ajax({
                url: 'import_equipments_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    $('#importProgress').hide();

                    let resultHtml = '<div style="padding:16px;border-radius:10px;border:1.5px solid;';

                    if (response.success) {
                        resultHtml += 'background:#dcfce7;border-color:rgba(22,163,74,.22);color:#15803d">';
                        resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-check-circle"></i> ØªÙ… Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­!</h6>';
                        resultHtml += '<p style="margin:4px 0;">âœ… ØªÙ… Ø¥Ø¶Ø§ÙØ©: <strong>' + response.added + '</strong> Ù…Ø¹Ø¯Ø©</p>';
                        if (response.skipped > 0) {
                            resultHtml += '<p style="margin:4px 0;color:#854d0e;">âš ï¸ ØªÙ… ØªØ®Ø·ÙŠ: <strong>' + response.skipped + '</strong> Ù…Ø¹Ø¯Ø©</p>';
                        }
                        if (response.errors.length > 0) {
                            resultHtml += '<p style="margin:8px 0 4px;"><strong>Ø§Ù„Ø£Ø®Ø·Ø§Ø¡:</strong></p><ul style="margin:0;padding-right:20px;max-height:200px;overflow-y:auto;">';
                            response.errors.forEach(function (error) {
                                resultHtml += '<li style="margin:4px 0;">' + error + '</li>';
                            });
                            resultHtml += '</ul>';
                        }
                        resultHtml += '</div>';
                        setTimeout(function () { location.reload(); }, 3000);
                    } else {
                        resultHtml += 'background:#fee2e2;border-color:rgba(220,38,38,.22);color:#991b1b">';
                        resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> ÙØ´Ù„ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯</h6>';
                        resultHtml += '<p style="margin:0;">' + response.message + '</p>';
                        if (response.errors && response.errors.length > 0) {
                            resultHtml += '<ul style="margin:8px 0 0;padding-right:20px;max-height:200px;overflow-y:auto;">';
                            response.errors.forEach(function (error) {
                                resultHtml += '<li style="margin:4px 0;">' + error + '</li>';
                            });
                            resultHtml += '</ul>';
                        }
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

                    const errorHtml = '<div style="padding:16px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1.5px solid rgba(220,38,38,.22);">' +
                        '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> Ø­Ø¯Ø« Ø®Ø·Ø£</h6>' +
                        '<p style="margin:0;">' + errorMsg + '</p>' +
                        '<p style="margin:10px 0 4px;"><strong>Ù†ØµØ§Ø¦Ø­:</strong></p>' +
                        '<ul style="font-size:.85rem;margin:0;padding-right:20px;">' +
                        '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ù„Ù Ø¨ØµÙŠØºØ© .xlsx, .xls Ø£Ùˆ .csv</li>' +
                        '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù Ø£Ù‚Ù„ Ù…Ù† 5 Ù…ÙŠØ¬Ø§</li>' +
                        '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ù„Ù ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø¨ÙŠØ§Ù†Ø§Øª ØµØ­ÙŠØ­Ø©</li>' +
                        '<li>ØªØ£ÙƒØ¯ Ù…Ù† Ø£Ù† Ø£Ø³Ù…Ø§Ø¡ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† ÙˆØ£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ù…ÙˆØ¬ÙˆØ¯Ø© ÙÙŠ Ø§Ù„Ù†Ø¸Ø§Ù…</li>' +
                        '<li>Ø¥Ø°Ø§ ÙƒÙ†Øª ØªØ³ØªØ®Ø¯Ù… ExcelØŒ Ø¬Ø±Ø¨ Ø­ÙØ¸ Ø§Ù„Ù…Ù„Ù ÙƒÙ€ CSV</li>' +
                        '</ul></div>';
                    $('#importResult').html(errorHtml).fadeIn(300);
                }
            });
        });
    </script>

</div> <!-- closing main div -->
</body>

</html>
