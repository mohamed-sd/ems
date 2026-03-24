<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+Ø¨ÙŠØ¦Ø©+Ø´Ø±ÙƒØ©+ØµØ§Ù„Ø­Ø©+Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù…+âŒ");
    exit();
}

$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$supplierscontracts_has_company = db_table_has_column($conn, 'supplierscontracts', 'company_id');

$supplier_scope_insert_col = (!$is_super_admin && $suppliers_has_company)
    ? ", company_id"
    : "";
$supplier_scope_insert_val = (!$is_super_admin && $suppliers_has_company)
    ? ", '$company_id'"
    : "";

$supplier_scope_update_where = "id = %d";
if (!$is_super_admin) {
    if ($suppliers_has_company) {
        $supplier_scope_update_where .= " AND company_id = $company_id";
    } else {
        $supplier_scope_update_where .= " AND EXISTS (
            SELECT 1
            FROM supplierscontracts ssc
            INNER JOIN project sp ON sp.id = ssc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE ssc.supplier_id = suppliers.id
              AND su.company_id = $company_id
        )";
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
$page_permissions = check_page_permissions($conn, 'suppliers');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†+âŒ");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© (Ø¥Ø¶Ø§ÙØ© Ø£Ùˆ ØªØ¹Ø¯ÙŠÙ„)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    
    if ($is_editing && !$can_edit) {
        header("Location: suppliers.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†+âŒ");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: suppliers.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¥Ø¶Ø§ÙØ©+Ù…ÙˆØ±Ø¯ÙŠÙ†+Ø¬Ø¯Ø¯+âŒ");
        exit();
    }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $supplier_code = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
    $dealing_nature = mysqli_real_escape_string($conn, $_POST['dealing_nature']);
    $equipment_types = isset($_POST['equipment_types']) ? implode(', ', $_POST['equipment_types']) : '';
    $equipment_types = mysqli_real_escape_string($conn, $equipment_types);
    
    // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©
    $commercial_registration = mysqli_real_escape_string($conn, trim($_POST['commercial_registration']));
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date']) : null;
    
    // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $full_address = mysqli_real_escape_string($conn, trim($_POST['full_address']));
    $contact_person_name = mysqli_real_escape_string($conn, trim($_POST['contact_person_name']));
    $contact_person_phone = mysqli_real_escape_string($conn, trim($_POST['contact_person_phone']));
    $financial_registration_status = mysqli_real_escape_string($conn, $_POST['financial_registration_status']);
    
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // ØªØ­Ø¯ÙŠØ«
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $scope_where = sprintf($supplier_scope_update_where, $id);
        $sql = "UPDATE suppliers SET 
            name='$name', 
            supplier_code='$supplier_code',
            supplier_type='$supplier_type',
            dealing_nature='$dealing_nature',
            equipment_types='$equipment_types',
            commercial_registration='$commercial_registration',
            identity_type='$identity_type',
            identity_number='$identity_number',
            identity_expiry_date=$identity_expiry_sql,
            email='$email',
            phone='$phone',
            phone_alternative='$phone_alternative',
            full_address='$full_address',
            contact_person_name='$contact_person_name',
            contact_person_phone='$contact_person_phone',
            financial_registration_status='$financial_registration_status',
            status='$status' 
            WHERE $scope_where";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=ØªÙ…+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…ÙˆØ±Ø¯+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit;
    } else {
        // Ø¥Ø¶Ø§ÙØ©
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $sql = "INSERT INTO suppliers 
            (name, supplier_code, supplier_type, dealing_nature, equipment_types, 
             commercial_registration, identity_type, identity_number, identity_expiry_date,
             email, phone, phone_alternative, full_address, contact_person_name, 
             contact_person_phone, financial_registration_status, status$supplier_scope_insert_col) 
            VALUES 
            ('$name', '$supplier_code', '$supplier_type', '$dealing_nature', '$equipment_types',
             '$commercial_registration', '$identity_type', '$identity_number', $identity_expiry_sql,
             '$email', '$phone', '$phone_alternative', '$full_address', '$contact_person_name',
             '$contact_person_phone', '$financial_registration_status', '$status'$supplier_scope_insert_val)";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=ØªÙ…Øª+Ø¥Ø¶Ø§ÙØ©+Ø§Ù„Ù…ÙˆØ±Ø¯+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit;
    }
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø­Ø°Ù Ø§Ù„Ù…ÙˆØ±Ø¯
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ø­Ø°Ù
    if (!$can_delete) {
        header("Location: suppliers.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø­Ø°Ù+Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†+âŒ");
        exit();
    }

    $scope_where = sprintf($supplier_scope_update_where, $delete_id);
    $scope_check_query = "SELECT id FROM suppliers WHERE $scope_where LIMIT 1";
    $scope_check_result = mysqli_query($conn, $scope_check_query);
    if (!$scope_check_result || mysqli_num_rows($scope_check_result) === 0) {
        header("Location: suppliers.php?msg=Ù„Ø§+ÙŠÙ…ÙƒÙ†+Ø­Ø°Ù+Ù…ÙˆØ±Ø¯+Ù„Ø§+ÙŠØªØ¨Ø¹+Ù„Ø´Ø±ÙƒØªÙƒ+âŒ");
        exit();
    }

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ÙˆØ¬ÙˆØ¯ Ù…Ø¹Ø¯Ø§Øª Ø£Ùˆ Ø¹Ù‚ÙˆØ¯ Ù…Ø±ØªØ¨Ø·Ø©
    $check_equip = mysqli_query($conn, "SELECT COUNT(*) as count FROM equipments WHERE suppliers = $delete_id");
    $equip_count = mysqli_fetch_assoc($check_equip)['count'];

    $check_contracts = mysqli_query($conn, "SELECT COUNT(*) as count FROM supplierscontracts WHERE supplier_id = $delete_id");
    $contracts_count = mysqli_fetch_assoc($check_contracts)['count'];

    if ($equip_count > 0 || $contracts_count > 0) {
        header("Location: suppliers.php?msg=Ù„Ø§+ÙŠÙ…ÙƒÙ†+Ø­Ø°Ù+Ø§Ù„Ù…ÙˆØ±Ø¯+Ù„Ø£Ù†Ù‡+Ù…Ø±ØªØ¨Ø·+Ø¨Ù…Ø¹Ø¯Ø§Øª+Ø£Ùˆ+Ø¹Ù‚ÙˆØ¯+Ù…ÙˆØ¬ÙˆØ¯Ø©+âŒ");
        exit();
    }

    $delete_query = "DELETE FROM suppliers WHERE $scope_where";
    if (mysqli_query($conn, $delete_query)) {
        header("Location: suppliers.php?msg=ØªÙ…+Ø­Ø°Ù+Ø§Ù„Ù…ÙˆØ±Ø¯+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit();
    } else {
        header("Location: suppliers.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø­Ø°Ù+âŒ");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <!-- CSS Ø§Ù„Ù…ÙˆÙ‚Ø¹ -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link rel="stylesheet" type="text/css" href="../assets/css/admin-style.css" />
    <link rel="stylesheet" href="../assets/css/main_admin_style.css" />
    
    <style>
        .form-section {
            background: var(--bg);
            padding: 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1.2rem;
            border: 1.5px solid var(--border);
        }

        .form-section h6 {
            color: var(--txt);
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section h6 i {
            color: var(--gold);
            font-size: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-weight: 700;
            color: var(--sub);
            font-size: .8rem;
        }

        .form-group label .required {
            color: var(--red);
            font-weight: 700;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: .88rem;
            font-weight: 500;
            color: var(--txt);
            background: var(--bg);
            transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
            outline: none;
            font-family: 'Cairo', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(232,184,0,.14);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            padding: 12px;
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: var(--bg);
            border-radius: 10px;
            cursor: pointer;
            transition: all var(--ease);
            border: 1.5px solid transparent;
        }

        .checkbox-label:hover {
            border-color: rgba(232,184,0,.3);
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--gold);
        }

        .checkbox-label span {
            font-weight: 500;
            color: var(--txt);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 18px;
            padding-top: 12px;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--navy), var(--navy-l));
            color: #fff;
            border: none;
            padding: 12px 26px;
            border-radius: 50px;
            font-weight: 700;
            font-size: .9rem;
            cursor: pointer;
            transition: all var(--ease);
            box-shadow: 0 4px 16px rgba(12,28,62,.22);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(12,28,62,.28);
        }

        .btn-cancel {
            background: rgba(100,116,139,.12);
            color: var(--sub);
            border: none;
            padding: 12px 26px;
            border-radius: 50px;
            font-weight: 700;
            font-size: .9rem;
            cursor: pointer;
            transition: all var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: var(--sub);
            color: #fff;
            transform: translateY(-2px);
        }

        .info-section {
            background: var(--bg);
            padding: 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1.2rem;
            border: 1.5px solid var(--border);
        }

        .section-title {
            color: var(--txt);
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--gold);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px;
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
        }

        .info-label {
            font-weight: 700;
            color: var(--sub);
            font-size: .75rem;
            text-transform: uppercase;
        }

        .info-value {
            font-weight: 700;
            color: var(--txt);
            font-size: .9rem;
        }

        .stat-cell {
            background: var(--navy);
            padding: 4px 12px;
            border-radius: 50px;
            width: fit-content;
            font-weight: 800;
            color: var(--gold);
            font-size: .85rem;
        }

        .phone-icon {
            color: var(--gold);
            margin-left: 6px;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }

            .checkbox-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php 
include('../insidebar.php'); 
?>

<div class="main">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-truck-loading"></i></div>
            <h1 class="page-title">Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fa fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…ÙˆØ±Ø¯ Ø¬Ø¯ÙŠØ¯
            </a>
            <a href="javascript:void(0)" id="openImportModal" class="add"
                style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-color:transparent;">
                <i class="fas fa-file-excel"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel
            </a>
            <?php endif; ?>
            <a href="download_suppliers_template.php" class="add"
                style="background:linear-gradient(135deg,var(--orange),#f59e0b);color:#fff;border-color:transparent;">
                <i class="fas fa-download"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel
            </a>
            <a href="download_suppliers_template_csv.php" class="add"
                style="background:linear-gradient(135deg,var(--blue),#3b82f6);color:#fff;border-color:transparent;">
                <i class="fas fa-file-csv"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ CSV
            </a>
        </div>
    </div>

    
    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…ÙˆØ±Ø¯ -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-edit"></i> Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…ÙˆØ±Ø¯
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="supplier_id" value="">
                
                <!-- 1. Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ© -->
                <div class="form-section">
                    <h6><i class="fas fa-info-circle"></i> Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯ <span class="required">*</span></label>
                            <input type="text" name="name" id="supplier_name" required />
                        </div>
                        
                        <div class="form-group">
                            <label>Ø§Ù„Ø±Ù…Ø²/Ø§Ù„ÙƒÙˆØ¯ Ù„Ù„Ù…ÙˆØ±Ø¯</label>
                            <input type="text" name="supplier_code" id="supplier_code" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ù†ÙˆØ¹ Ø§Ù„Ù…ÙˆØ±Ø¯ <span class="required">*</span></label>
                            <select name="supplier_type" id="supplier_type" required>
                                <option value="">-- Ø§Ø®ØªØ± --</option>
                                <option value="ÙØ±Ø¯">ÙØ±Ø¯</option>
                                <option value="Ø´Ø±ÙƒØ©">Ø´Ø±ÙƒØ©</option>
                                <option value="ÙˆØ³ÙŠØ·">ÙˆØ³ÙŠØ·</option>
                                <option value="Ù…Ø§Ù„Ùƒ">Ù…Ø§Ù„Ùƒ</option>
                                <option value="Ø¬Ù‡Ø© Ø­ÙƒÙˆÙ…ÙŠØ©">Ø¬Ù‡Ø© Ø­ÙƒÙˆÙ…ÙŠØ©</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù…Ù„ <span class="required">*</span></label>
                            <select name="dealing_nature" id="dealing_nature" required>
                                <option value="">-- Ø§Ø®ØªØ± --</option>
                                <option value="Ù…ØªØ¹Ø§Ù‚Ø¯ Ù…Ø¨Ø§Ø´Ø±">Ù…ØªØ¹Ø§Ù‚Ø¯ Ù…Ø¨Ø§Ø´Ø±</option>
                                <option value="ÙˆØ³ÙŠØ·">ÙˆØ³ÙŠØ·</option>
                                <option value="Ù…ÙˆØ±Ø¯ Ù…Ø¹Ø¯Ø§Øª Ù…Ø¨Ø§Ø´Ø± (Ù…Ø§Ù„Ùƒ)">Ù…ÙˆØ±Ø¯ Ù…Ø¹Ø¯Ø§Øª Ù…Ø¨Ø§Ø´Ø± (Ù…Ø§Ù„Ùƒ)</option>
                                <option value="ÙˆÙƒÙŠÙ„ ØªÙˆØ²ÙŠØ¹">ÙˆÙƒÙŠÙ„ ØªÙˆØ²ÙŠØ¹</option>
                                <option value="ØªØ§Ø¬Ø± ÙˆØ³ÙŠØ·">ØªØ§Ø¬Ø± ÙˆØ³ÙŠØ·</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Ø§Ù„Ù…Ø¹Ø¯Ø§Øª (ÙŠÙ…ÙƒÙ† Ø§Ø®ØªÙŠØ§Ø± Ø£ÙƒØ«Ø± Ù…Ù† Ù†ÙˆØ¹)</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ø­ÙØ§Ø±Ø§Øª">
                                <span>Ø­ÙØ§Ø±Ø§Øª</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ù…ÙƒÙ†Ø§Øª ØªØ®Ø±ÙŠÙ…">
                                <span>Ù…ÙƒÙ†Ø§Øª ØªØ®Ø±ÙŠÙ…</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ø¯ÙˆØ§Ø²Ø±">
                                <span>Ø¯ÙˆØ§Ø²Ø±</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ø´Ø§Ø­Ù†Ø§Øª Ù‚Ù„Ø§Ø¨Ø©">
                                <span>Ø´Ø§Ø­Ù†Ø§Øª Ù‚Ù„Ø§Ø¨Ø©</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ø´Ø§Ø­Ù†Ø§Øª ØªÙ†Ø§ÙƒØ±">
                                <span>Ø´Ø§Ø­Ù†Ø§Øª ØªÙ†Ø§ÙƒØ±</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ø¬Ø±Ø§ÙØ§Øª">
                                <span>Ø¬Ø±Ø§ÙØ§Øª</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="Ù…Ø¹Ø¯Ø§Øª Ù…Ø¹Ø§Ù„Ø¬Ø©">
                                <span>Ù…Ø¹Ø¯Ø§Øª Ù…Ø¹Ø§Ù„Ø¬Ø©</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ© -->
                <div class="form-section">
                    <h6><i class="fas fa-file-contract"></i> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Ø±Ù‚Ù… Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„ØªØ¬Ø§Ø±ÙŠ/Ø§Ù„Ø±Ø®ØµØ©</label>
                            <input type="text" name="commercial_registration" id="commercial_registration" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ©</label>
                            <select name="identity_type" id="identity_type">
                                <option value="">-- Ø§Ø®ØªØ± --</option>
                                <option value="Ø¨Ø·Ø§Ù‚Ø© Ù‡ÙˆÙŠØ© ÙˆØ·Ù†ÙŠØ©">Ø¨Ø·Ø§Ù‚Ø© Ù‡ÙˆÙŠØ© ÙˆØ·Ù†ÙŠØ©</option>
                                <option value="Ø¬ÙˆØ§Ø² Ø³ÙØ±">Ø¬ÙˆØ§Ø² Ø³ÙØ±</option>
                                <option value="Ø±Ù‚Ù… ØªØ³Ø¬ÙŠÙ„ ØªØ¬Ø§Ø±ÙŠ">Ø±Ù‚Ù… ØªØ³Ø¬ÙŠÙ„ ØªØ¬Ø§Ø±ÙŠ</option>
                                <option value="Ø±Ù‚Ù… Ø¶Ø±ÙŠØ¨Ø© Ø¯Ø®Ù„">Ø±Ù‚Ù… Ø¶Ø±ÙŠØ¨Ø© Ø¯Ø®Ù„</option>
                                <option value="Ø±Ø®ØµØ© Ø¹Ù…Ù„">Ø±Ø®ØµØ© Ø¹Ù…Ù„</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ©/Ø§Ù„ØªØ³Ø¬ÙŠÙ„</label>
                            <input type="text" name="identity_number" id="identity_number" />
                        </div>
                        
                        <div class="form-group">
                            <label>ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ù‡ÙˆÙŠØ©</label>
                            <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                        </div>
                    </div>
                </div>

                <!-- 3. Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ© -->
                <div class="form-section">
                    <h6><i class="fas fa-address-book"></i> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠ</label>
                            <input type="email" name="email" id="supplier_email" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ <span class="required">*</span></label>
                            <input type="text" name="phone" id="supplier_phone" required />
                        </div>
                        
                        <div class="form-group">
                            <label>Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø¨Ø¯ÙŠÙ„</label>
                            <input type="text" name="phone_alternative" id="phone_alternative" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ø§Ø³Ù… Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©</label>
                            <input type="text" name="contact_person_name" id="contact_person_name" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ù‡Ø§ØªÙ Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„</label>
                            <input type="text" name="contact_person_phone" id="contact_person_phone" />
                        </div>
                        
                        <div class="form-group">
                            <label>Ø­Ø§Ù„Ø© Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø§Ù„ÙŠ</label>
                            <select name="financial_registration_status" id="financial_registration_status">
                                <option value="">-- Ø§Ø®ØªØ± --</option>
                                <option value="Ù…Ø³Ø¬Ù„ Ø±Ø³Ù…ÙŠØ§">Ù…Ø³Ø¬Ù„ Ø±Ø³Ù…ÙŠØ§</option>
                                <option value="ØºÙŠØ± Ù…Ø³Ø¬Ù„">ØºÙŠØ± Ù…Ø³Ø¬Ù„</option>
                                <option value="ØªØ­Øª Ø§Ù„ØªØ³Ø¬ÙŠÙ„">ØªØ­Øª Ø§Ù„ØªØ³Ø¬ÙŠÙ„</option>
                                <option value="Ù…Ø¹ÙÙ‰ Ù…Ù† Ø§Ù„ØªØ³Ø¬ÙŠÙ„">Ù…Ø¹ÙÙ‰ Ù…Ù† Ø§Ù„ØªØ³Ø¬ÙŠÙ„</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙƒØ§Ù…Ù„</label>
                            <textarea name="full_address" id="full_address" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Ø§Ù„Ø­Ø§Ù„Ø© <span class="required">*</span></label>
                            <select name="status" id="supplier_status" required>
                                <option value="">Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø©</option>
                                <option value="1">Ù†Ø´Ø·</option>
                                <option value="0">Ù…Ø¹Ù„Ù‚</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Ø­ÙØ¸ Ø§Ù„Ù…ÙˆØ±Ø¯
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleForm()">
                        <i class="fas fa-times"></i>
                        Ø¥Ù„ØºØ§Ø¡
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†
            </h5>
        </div>
        <div class="card-body">
            <div class="table-container">
            <table id="projectsTable" class="display" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-truck-loading"></i> Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯</th>
                        <th><i class="fas fa-cogs"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ø¢Ù„ÙŠØ§Øª</th>
                        <th><i class="fas fa-file-contract"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯</th>
                        <th><i class="fas fa-clock"></i> Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§</th>
                        <th><i class="fas fa-phone"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ</th>
                        <th><i class="fas fa-info-circle"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
                        <th><i class="fas fa-sliders-h"></i> Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ø¬Ù„Ø¨ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† Ù…Ø¹ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª
                    $supplier_scope_sql = "1=1";
                    if (!$is_super_admin) {
                        if ($suppliers_has_company) {
                            $supplier_scope_sql = "s.company_id = $company_id";
                        } else {
                            $supplier_scope_sql = "EXISTS (
                                SELECT 1
                                FROM supplierscontracts ssc
                                INNER JOIN project sp ON sp.id = ssc.project_id
                                INNER JOIN users su ON su.id = sp.created_by
                                WHERE ssc.supplier_id = s.id
                                  AND su.company_id = $company_id
                            )";
                        }
                    }

                    $contracts_count_scope = (!$is_super_admin && $supplierscontracts_has_company)
                        ? " AND supplierscontracts.company_id = $company_id"
                        : "";

                    $query = "SELECT s.*, 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = s.id ) as 'equipments' ,
                      (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id$contracts_count_scope ) as 'num_contracts',
                      (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id$contracts_count_scope ) as 'total_hours'
                      FROM `suppliers` s
                      WHERE $supplier_scope_sql
                      ORDER BY s.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Ø¥Ø¹Ø¯Ø§Ø¯ data attributes Ù„Ù„ØªØ¹Ø¯ÙŠÙ„
                        $data_attrs = "data-id='" . $row['id'] . "' " .
                            "data-name='" . htmlspecialchars($row['name'], ENT_QUOTES) . "' " .
                            "data-supplier_code='" . htmlspecialchars($row['supplier_code'], ENT_QUOTES) . "' " .
                            "data-supplier_type='" . htmlspecialchars($row['supplier_type'], ENT_QUOTES) . "' " .
                            "data-dealing_nature='" . htmlspecialchars($row['dealing_nature'], ENT_QUOTES) . "' " .
                            "data-equipment_types='" . htmlspecialchars($row['equipment_types'], ENT_QUOTES) . "' " .
                            "data-commercial_registration='" . htmlspecialchars($row['commercial_registration'], ENT_QUOTES) . "' " .
                            "data-identity_type='" . htmlspecialchars($row['identity_type'], ENT_QUOTES) . "' " .
                            "data-identity_number='" . htmlspecialchars($row['identity_number'], ENT_QUOTES) . "' " .
                            "data-identity_expiry_date='" . htmlspecialchars($row['identity_expiry_date'], ENT_QUOTES) . "' " .
                            "data-email='" . htmlspecialchars($row['email'], ENT_QUOTES) . "' " .
                            "data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES) . "' " .
                            "data-phone_alternative='" . htmlspecialchars($row['phone_alternative'], ENT_QUOTES) . "' " .
                            "data-full_address='" . htmlspecialchars($row['full_address'], ENT_QUOTES) . "' " .
                            "data-contact_person_name='" . htmlspecialchars($row['contact_person_name'], ENT_QUOTES) . "' " .
                            "data-contact_person_phone='" . htmlspecialchars($row['contact_person_phone'], ENT_QUOTES) . "' " .
                            "data-financial_registration_status='" . htmlspecialchars($row['financial_registration_status'], ENT_QUOTES) . "' " .
                            "data-status='" . $row['status'] . "'";
                        
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><span class='client-name-link'>" . htmlspecialchars($row['name']) . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['num_contracts'] . "</span></td>";
                        echo "<td><span class='status-active'>" . number_format($row['total_hours']) . " Ø³Ø§Ø¹Ø©</span></td>";
                        echo "<td><i class='fas fa-phone phone-icon'></i>" . htmlspecialchars($row['phone']) . "</td>";

                        // Ø§Ù„Ø­Ø§Ù„Ø© Ø¨Ø§Ù„Ø£Ù„ÙˆØ§Ù†
                        if ($row['status'] == "1") {
                                     echo "<td><span class='status-active'><i class='fas fa-check-circle' style='margin-left:6px;'></i>Ù†Ø´Ø·</span></td>";
                        } else {
                                     echo "<td><span class='status-inactive'><i class='fas fa-times-circle' style='margin-left:6px;'></i>Ù…Ø¹Ù„Ù‚</span></td>";
                        }

                                $action_btns = "<td><div class='action-btns'>";
                                $action_btns .= "<a href='javascript:void(0)' class='viewBtn action-btn view' $data_attrs title='Ø¹Ø±Ø¶ Ø§Ù„ØªÙØ§ØµÙŠÙ„'><i class='fas fa-eye'></i></a>";
                                if ($can_edit) {
                                    $action_btns .= "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='ØªØ¹Ø¯ÙŠÙ„'><i class='fas fa-edit'></i></a>";
                                }
                                $action_btns .= "<a href='supplierscontracts.php?id=" . $row['id'] . "' class='action-btn contracts' title='Ø§Ù„Ø¹Ù‚ÙˆØ¯'><i class='fas fa-file-contract'></i></a>";
                                if ($can_delete) {
                                    $action_btns .= "<a href='?delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ù…ÙˆØ±Ø¯ØŸ\")' title='Ø­Ø°Ù'><i class='fas fa-trash-alt'></i></a>";
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
    
    <!-- Modal Ø¹Ø±Ø¶ ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯ -->
    <div id="viewSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>
                    <i class="fas fa-truck-loading"></i>
                    ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯
                </h5>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-info-circle"></i> Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯:</span>
                            <span class="info-value" id="view_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø§Ù„Ø±Ù…Ø²/Ø§Ù„ÙƒÙˆØ¯:</span>
                            <span class="info-value" id="view_supplier_code">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ù†ÙˆØ¹ Ø§Ù„Ù…ÙˆØ±Ø¯:</span>
                            <span class="info-value" id="view_supplier_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù…Ù„:</span>
                            <span class="info-value" id="view_dealing_nature">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">Ø§Ù„Ù…Ø¹Ø¯Ø§Øª:</span>
                            <span class="info-value" id="view_equipment_types">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-file-contract"></i> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Ø±Ù‚Ù… Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„ØªØ¬Ø§Ø±ÙŠ:</span>
                            <span class="info-value" id="view_commercial_registration">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ©:</span>
                            <span class="info-value" id="view_identity_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ©:</span>
                            <span class="info-value" id="view_identity_number">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ù‡ÙˆÙŠØ©:</span>
                            <span class="info-value" id="view_identity_expiry_date">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ© -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-address-book"></i> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ:</span>
                            <span class="info-value" id="view_email">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ:</span>
                            <span class="info-value" id="view_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø¨Ø¯ÙŠÙ„:</span>
                            <span class="info-value" id="view_phone_alternative">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„:</span>
                            <span class="info-value" id="view_contact_person_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ù‡Ø§ØªÙ Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„:</span>
                            <span class="info-value" id="view_contact_person_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø­Ø§Ù„Ø© Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø§Ù„ÙŠ:</span>
                            <span class="info-value" id="view_financial_registration_status">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙƒØ§Ù…Ù„:</span>
                            <span class="info-value" id="view_full_address">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ø§Ù„Ø­Ø§Ù„Ø©:</span>
                            <span class="info-value" id="view_status">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem; border-top: 2px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeViewModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel -->
    <div id="importExcelModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h5><i class="fas fa-file-excel"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…ÙˆØ±Ø¯ÙŠÙ† Ù…Ù† Excel</h5>
                <button class="close-modal" onclick="closeImportModal()">&times;</button>
            </div>
            <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div style="background:linear-gradient(135deg,rgba(102,126,234,.06),rgba(118,75,162,.06));padding:18px;border-radius:var(--radius);margin-bottom:18px;border:1.5px solid rgba(102,126,234,.18);">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <i class="fas fa-info-circle" style="color:var(--navy);font-size:1.2rem;"></i>
                        <strong style="color:var(--navy);font-size:.92rem;">ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯:</strong>
                    </div>
                    <ul style="margin:0;padding-right:28px;color:var(--sub);font-size:.84rem;line-height:1.8;">
                        <li>Ù‚Ù… Ø¨ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel Ø£Ùˆ CSV Ø£ÙˆÙ„Ø§Ù‹</li>
                        <li>Ø§Ù…Ù„Ø£ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙÙŠ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬ (ÙƒÙˆØ¯ Ø§Ù„Ù…ÙˆØ±Ø¯ØŒ Ø§Ù„Ø§Ø³Ù…ØŒ Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ Ù…Ø·Ù„ÙˆØ¨Ø©)</li>
                        <li>Ø§Ø­ÙØ¸ Ø§Ù„Ù…Ù„Ù Ø«Ù… Ù‚Ù… Ø¨Ø±ÙØ¹Ù‡ Ù‡Ù†Ø§</li>
                        <li>Ø§Ù„ØµÙŠØº Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx, .xls, .csv</li>
                        <li>Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰: 1000 ØµÙØŒ 5 Ù…ÙŠØ¬Ø§</li>
                    </ul>
                </div>
                
                <div style="margin-bottom:18px;">
                    <label style="display:block;font-weight:700;color:var(--txt);margin-bottom:8px;font-size:.88rem;">
                        <i class="fas fa-file-upload"></i> Ø§Ø®ØªØ± Ù…Ù„Ù Excel Ø£Ùˆ CSV
                    </label>
                    <input type="file" name="excel_file" id="excelFileInput" accept=".xlsx,.xls,.csv" 
                        style="width:100%;padding:12px;border:2px dashed var(--border);border-radius:var(--radius);background:var(--bg);cursor:pointer;font-family:'Cairo',sans-serif;" required>
                </div>
                
                <div id="importProgress" style="display: none; margin-top: 18px;">
                    <div style="background:linear-gradient(90deg,var(--navy),var(--gold));height:6px;border-radius:50px;overflow:hidden;animation:progressAnim 1.5s ease-in-out infinite;">
                    </div>
                    <p style="margin:10px 0 0;color:var(--blue);font-weight:700;">Ø¬Ø§Ø±ÙŠ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯...</p>
                </div>
                
                <div id="importResult" style="display: none; margin-top: 18px;"></div>
            </div>
            <div class="modal-footer" style="display:flex;gap:10px;justify-content:flex-end;padding:1.5rem;border-top:2px solid #e0e0e0;">
                <button type="submit" class="btn-save" 
                    style="background:linear-gradient(135deg,#064e3b,#059669)!important;">
                    <i class="fas fa-upload"></i> Ø±ÙØ¹ ÙˆØ§Ø³ØªÙŠØ±Ø§Ø¯
                </button>
                <button type="button" class="btn-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes progressAnim {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
    (function () {
        // ØªØ´ØºÙŠÙ„ DataTable
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'ðŸ“‹ Ù†Ø³Ø®' },
                    { extend: 'excel', text: 'ðŸ“Š Excel' },
                    { extend: 'csv', text: 'ðŸ“„ CSV' },
                    { extend: 'pdf', text: 'ðŸ“• PDF' },
                    { extend: 'print', text: 'ðŸ–¨ï¸ Ø·Ø¨Ø§Ø¹Ø©' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // Ø§Ø¸Ù‡Ø§Ø±/Ø§Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
        const toggleSupplierFormBtn = document.getElementById('toggleForm');
        const supplierForm = document.getElementById('projectForm');
        if (toggleSupplierFormBtn && supplierForm) {
            toggleSupplierFormBtn.addEventListener('click', function () {
                supplierForm.style.display = supplierForm.style.display === "none" ? "block" : "none";
                // ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø¹Ù†Ø¯ Ø§Ù„Ø¥Ø¶Ø§ÙØ©
                $("#supplier_id").val("");
                $("#supplier_name").val("");
                $("#supplier_phone").val("");
                $("#supplier_status").val("");
            });
        }

        // Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø²Ø± ØªØ¹Ø¯ÙŠÙ„
        $(document).on("click", ".editBtn", function () {
            const $this = $(this);
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
            $("#supplier_id").val($this.data("id"));
            $("#supplier_name").val($this.data("name"));
            $("#supplier_code").val($this.data("supplier_code"));
            $("#supplier_type").val($this.data("supplier_type"));
            $("#dealing_nature").val($this.data("dealing_nature"));
            
            // Ø§Ù„Ù…Ø¹Ø¯Ø§Øª (checkbox)
            const equipmentTypes = $this.data("equipment_types") ? $this.data("equipment_types").toString().split(', ') : [];
            $("input[name='equipment_types[]']").prop("checked", false);
            equipmentTypes.forEach(function(type) {
                $("input[name='equipment_types[]'][value='" + type.trim() + "']").prop("checked", true);
            });
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©
            $("#commercial_registration").val($this.data("commercial_registration"));
            $("#identity_type").val($this.data("identity_type"));
            $("#identity_number").val($this.data("identity_number"));
            $("#identity_expiry_date").val($this.data("identity_expiry_date"));
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©
            $("#supplier_email").val($this.data("email"));
            $("#supplier_phone").val($this.data("phone"));
            $("#phone_alternative").val($this.data("phone_alternative"));
            $("#full_address").val($this.data("full_address"));
            $("#contact_person_name").val($this.data("contact_person_name"));
            $("#contact_person_phone").val($this.data("contact_person_phone"));
            $("#financial_registration_status").val($this.data("financial_registration_status"));
            $("#supplier_status").val($this.data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });
        
        // Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø²Ø± Ø¹Ø±Ø¶ Ø§Ù„ØªÙØ§ØµÙŠÙ„
        $(document).on("click", ".viewBtn", function () {
            const $this = $(this);
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
            $("#view_name").text($this.data("name") || "-");
            $("#view_supplier_code").text($this.data("supplier_code") || "-");
            $("#view_supplier_type").text($this.data("supplier_type") || "-");
            $("#view_dealing_nature").text($this.data("dealing_nature") || "-");
            $("#view_equipment_types").text($this.data("equipment_types") || "-");
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©
            $("#view_commercial_registration").text($this.data("commercial_registration") || "-");
            $("#view_identity_type").text($this.data("identity_type") || "-");
            $("#view_identity_number").text($this.data("identity_number") || "-");
            $("#view_identity_expiry_date").text($this.data("identity_expiry_date") || "-");
            
            // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©
            $("#view_email").text($this.data("email") || "-");
            $("#view_phone").text($this.data("phone") || "-");
            $("#view_phone_alternative").text($this.data("phone_alternative") || "-");
            $("#view_full_address").text($this.data("full_address") || "-");
            $("#view_contact_person_name").text($this.data("contact_person_name") || "-");
            $("#view_contact_person_phone").text($this.data("contact_person_phone") || "-");
            $("#view_financial_registration_status").text($this.data("financial_registration_status") || "-");
            
            const status = $this.data("status") === "1" ? "Ù†Ø´Ø· âœ…" : "Ù…Ø¹Ù„Ù‚ â¸ï¸";
            $("#view_status").text(status);
            
            $("#viewSupplierModal").fadeIn(300);
        });
        
        // Ø¯Ø§Ù„Ø© closeViewModal Ù„Ø¥ØºÙ„Ø§Ù‚ Modal
        window.closeViewModal = function() {
            $("#viewSupplierModal").fadeOut(300);
        };
        
        // Ø¥ØºÙ„Ø§Ù‚ Modal Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø®Ø§Ø±Ø¬ Ø§Ù„Ù…Ø­ØªÙˆÙ‰
        $(document).on("click", "#viewSupplierModal", function(e) {
            if (e.target.id === "viewSupplierModal") {
                closeViewModal();
            }
        });
        
        // Ø¯Ø§Ù„Ø© toggleForm Ù„Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬
        window.toggleForm = function() {
            var form = $("#projectForm");
            if (form.is(":visible")) {
                form.slideUp();
            } else {
                // Ù…Ø³Ø­ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ù‚ÙˆÙ„
                $("#supplier_id").val("");
                $("#supplier_name").val("");
                $("#supplier_code").val("");
                $("#supplier_type").val("");
                $("#dealing_nature").val("");
                $("input[name='equipment_types[]']").prop("checked", false);
                $("#commercial_registration").val("");
                $("#identity_type").val("");
                $("#identity_number").val("");
                $("#identity_expiry_date").val("");
                $("#supplier_email").val("");
                $("#supplier_phone").val("");
                $("#phone_alternative").val("");
                $("#full_address").val("");
                $("#contact_person_name").val("");
                $("#contact_person_phone").val("");
                $("#financial_registration_status").val("");
                $("#supplier_status").val("");
                form.slideDown();
                $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
            }
        };
        
        // ÙØªØ­ Modal Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
        $('#openImportModal').on('click', function () {
            $('#importExcelModal').fadeIn(300);
            $('#importResult').hide();
            $('#excelFileInput').val('');
        });
        
        // Ø¥ØºÙ„Ø§Ù‚ Modal Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
        window.closeImportModal = function() {
            $('#importExcelModal').fadeOut(300);
            $('#importExcelForm')[0].reset();
            $('#importProgress').hide();
            $('#importResult').hide();
        };
        
        // Ø¥ØºÙ„Ø§Ù‚ Modal Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø®Ø§Ø±Ø¬ Ø§Ù„Ù…Ø­ØªÙˆÙ‰
        $(document).on('click', '#importExcelModal', function(e) {
            if (e.target.id === 'importExcelModal') {
                closeImportModal();
            }
        });
        
        // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø±ÙØ¹ ÙˆØ§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ù„Ù
        $('#importExcelForm').on('submit', function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('excelFileInput');
            if (!fileInput.files.length) {
                alert('Ø§Ù„Ø±Ø¬Ø§Ø¡ Ø§Ø®ØªÙŠØ§Ø± Ù…Ù„Ù Ø£ÙˆÙ„Ø§Ù‹');
                return;
            }

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('action', 'import_excel');

            $('#importProgress').show();
            $('#importResult').hide();

            $.ajax({
                url: 'import_suppliers_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    $('#importProgress').hide();

                    let resultHtml = '<div style="padding:16px;border-radius:var(--radius);border:1.5px solid;';

                    if (response.success) {
                        resultHtml += 'border-color:#10b981;background:rgba(16,185,129,.06);">';
                        resultHtml += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">';
                        resultHtml += '<i class="fas fa-check-circle" style="color:#10b981;font-size:1.4rem;"></i>';
                        resultHtml += '<strong style="color:#059669;font-size:1rem;">' + response.message + '</strong>';
                        resultHtml += '</div>';
                        resultHtml += '<div style="color:#047857;font-size:.88rem;line-height:1.6;">';
                        resultHtml += '<p style="margin:6px 0;"><strong>âœ… ØªÙ…Øª Ø§Ù„Ø¥Ø¶Ø§ÙØ©:</strong> ' + response.added + ' Ù…ÙˆØ±Ø¯</p>';
                        if (response.skipped > 0) {
                            resultHtml += '<p style="margin:6px 0;"><strong>â­ï¸ ØªÙ… Ø§Ù„ØªØ®Ø·ÙŠ:</strong> ' + response.skipped + ' Ù…ÙˆØ±Ø¯</p>';
                        }
                        if (response.errors && response.errors.length > 0) {
                            resultHtml += '<details style="margin-top:12px;padding:10px;background:#fff;border-radius:8px;">';
                            resultHtml += '<summary style="cursor:pointer;font-weight:700;color:#dc2626;">Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø®Ø·Ø§Ø¡ (' + response.errors.length + ')</summary>';
                            resultHtml += '<ul style="margin:10px 0 0;padding-right:20px;">';
                            response.errors.forEach(function (error) {
                                resultHtml += '<li style="margin:4px 0;color:#b91c1c;font-size:.82rem;">' + error + '</li>';
                            });
                            resultHtml += '</ul></details>';
                        }
                        resultHtml += '</div>';
                        resultHtml += '<button onclick="location.reload()" style="margin-top:14px;padding:10px 20px;background:#10b981;color:#fff;border:none;border-radius:50px;cursor:pointer;font-weight:700;font-family:Cairo,sans-serif;">ØªØ­Ø¯ÙŠØ« Ø§Ù„ØµÙØ­Ø©</button>';
                    } else {
                        resultHtml += 'border-color:#ef4444;background:rgba(239,68,68,.06);">';
                        resultHtml += '<div style="display:flex;align-items:center;gap:10px;">';
                        resultHtml += '<i class="fas fa-exclamation-circle" style="color:#ef4444;font-size:1.4rem;"></i>';
                        resultHtml += '<div><strong style="color:#dc2626;font-size:1rem;display:block;margin-bottom:6px;">ÙØ´Ù„ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯</strong>';
                        resultHtml += '<p style="color:#b91c1c;font-size:.88rem;margin:0;">' + response.message + '</p></div>';
                        resultHtml += '</div>';
                    }

                    resultHtml += '</div>';
                    $('#importResult').html(resultHtml).fadeIn();
                },
                error: function (xhr, status, error) {
                    $('#importProgress').hide();
                    let errorMsg = 'Ø­Ø¯Ø« Ø®Ø·Ø£ ØºÙŠØ± Ù…ØªÙˆÙ‚Ø¹';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $('#importResult').html(
                        '<div style="padding:16px;border-radius:var(--radius);border:1.5px solid #ef4444;background:rgba(239,68,68,.06);">' +
                        '<div style="display:flex;align-items:center;gap:10px;">' +
                        '<i class="fas fa-times-circle" style="color:#ef4444;font-size:1.4rem;"></i>' +
                        '<div><strong style="color:#dc2626;">Ø®Ø·Ø£ ÙÙŠ Ø§Ù„Ø§ØªØµØ§Ù„</strong>' +
                        '<p style="color:#b91c1c;margin:6px 0 0;font-size:.88rem;">' + errorMsg + '</p></div>' +
                        '</div></div>'
                    ).fadeIn();
                }
            });
        });
    })();
</script>

</body>

</html>
