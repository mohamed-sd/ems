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

$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$drivercontracts_has_company = db_table_has_column($conn, 'drivercontracts', 'company_id');

$driver_scope_where = "id = %d";
if (!$is_super_admin) {
    if ($drivers_has_company) {
        $driver_scope_where .= " AND company_id = $company_id";
    } else {
        $driver_scope_where .= " AND EXISTS (
            SELECT 1
            FROM drivercontracts dsc
            INNER JOIN project sp ON sp.id = dsc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE dsc.driver_id = drivers.id
              AND su.company_id = $company_id
        )";
    }
}

$driver_insert_col = (!$is_super_admin && $drivers_has_company) ? ", company_id" : "";
$driver_insert_val = (!$is_super_admin && $drivers_has_company) ? ", '$company_id'" : "";

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
$page_permissions = check_page_permissions($conn, 'drivers');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†+âŒ");
    exit();
}

$page_title = "Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†";

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø¥Ø¶Ø§ÙØ©/ØªØ¹Ø¯ÙŠÙ„ Ù…Ø´ØºÙ„ Ø¹Ù†Ø¯ Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙÙˆØ±Ù…
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© (Ø¥Ø¶Ø§ÙØ© Ø£Ùˆ ØªØ¹Ø¯ÙŠÙ„)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    
    if ($is_editing && !$can_edit) {
        header("Location: drivers.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†+âŒ");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: drivers.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¥Ø¶Ø§ÙØ©+Ù…Ø´ØºÙ„ÙŠÙ†+Ø¬Ø¯Ø¯+âŒ");
        exit();
    }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // 1. Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $driver_code = mysqli_real_escape_string($conn, trim($_POST['driver_code']));
    $nickname = mysqli_real_escape_string($conn, trim($_POST['nickname']));
    
    // 2. Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‡ÙˆÙŠØ© ÙˆØ§Ù„ØªÙˆØ«ÙŠÙ‚
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date']) : NULL;
    
    // 3. Ø±Ø®ØµØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø© ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number']));
    $license_type = mysqli_real_escape_string($conn, $_POST['license_type']);
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['license_expiry_date']) : NULL;
    $license_issuer = mysqli_real_escape_string($conn, trim($_POST['license_issuer']));
    
    // 4. Ø§Ù„ØªØ®ØµØµ ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª
    $specialized_equipment = isset($_POST['specialized_equipment']) ? implode(', ', $_POST['specialized_equipment']) : '';
    $specialized_equipment = mysqli_real_escape_string($conn, $specialized_equipment);
    
    // 5. Ø³Ù†ÙˆØ§Øª Ø§Ù„Ø®Ø¨Ø±Ø© ÙˆØ§Ù„ÙƒÙØ§Ø¡Ø©
    $years_in_field = !empty($_POST['years_in_field']) ? intval($_POST['years_in_field']) : NULL;
    $years_on_equipment = !empty($_POST['years_on_equipment']) ? intval($_POST['years_on_equipment']) : NULL;
    $skill_level = mysqli_real_escape_string($conn, $_POST['skill_level']);
    $certificates = mysqli_real_escape_string($conn, trim($_POST['certificates']));
    
    // 6. Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø¹Ù…Ù„ ÙˆØ§Ù„ØªØ¨Ø¹ÙŠØ©
    $owner_supervisor = mysqli_real_escape_string($conn, trim($_POST['owner_supervisor']));
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $employment_affiliation = mysqli_real_escape_string($conn, $_POST['employment_affiliation']);
    $salary_type = mysqli_real_escape_string($conn, $_POST['salary_type']);
    $monthly_salary = !empty($_POST['monthly_salary']) ? floatval($_POST['monthly_salary']) : NULL;
    
    // 7. Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    
    // 8. ØªÙ‚ÙŠÙŠÙ… Ø§Ù„Ø£Ø¯Ø§Ø¡ ÙˆØ§Ù„Ø³Ù„ÙˆÙƒ
    $performance_rating = mysqli_real_escape_string($conn, $_POST['performance_rating']);
    $behavior_record = mysqli_real_escape_string($conn, $_POST['behavior_record']);
    $accident_record = mysqli_real_escape_string($conn, $_POST['accident_record']);
    
    // 9. Ø§Ù„ØµØ­Ø© ÙˆØ§Ù„Ø³Ù„Ø§Ù…Ø©
    $health_status = mysqli_real_escape_string($conn, $_POST['health_status']);
    $health_issues = mysqli_real_escape_string($conn, trim($_POST['health_issues']));
    $vaccinations_status = mysqli_real_escape_string($conn, $_POST['vaccinations_status']);
    
    // 10. Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹ ÙˆØ§Ù„Ø³Ø¬Ù„
    $previous_employer = mysqli_real_escape_string($conn, trim($_POST['previous_employer']));
    $employment_duration = mysqli_real_escape_string($conn, trim($_POST['employment_duration']));
    $reference_contact = mysqli_real_escape_string($conn, trim($_POST['reference_contact']));
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes']));
    
    // 11. Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„ØªÙØ¹ÙŠÙ„
    $driver_status = mysqli_real_escape_string($conn, $_POST['driver_status']);
    $start_date = !empty($_POST['start_date']) ? mysqli_real_escape_string($conn, $_POST['start_date']) : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // ØªØ­Ø¯ÙŠØ«
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $license_expiry_sql = $license_expiry_date ? "'$license_expiry_date'" : "NULL";
        $start_date_sql = $start_date ? "'$start_date'" : "NULL";
        $years_in_field_sql = $years_in_field !== NULL ? $years_in_field : "NULL";
        $years_on_equipment_sql = $years_on_equipment !== NULL ? $years_on_equipment : "NULL";
        $supplier_id_sql = $supplier_id !== NULL ? $supplier_id : "NULL";
        $monthly_salary_sql = $monthly_salary !== NULL ? $monthly_salary : "NULL";
        
        $scope_where = sprintf($driver_scope_where, $id);
        $update_query = "UPDATE drivers SET 
            name='$name', driver_code='$driver_code', nickname='$nickname',
            identity_type='$identity_type', identity_number='$identity_number', identity_expiry_date=$identity_expiry_sql,
            license_number='$license_number', license_type='$license_type', license_expiry_date=$license_expiry_sql, license_issuer='$license_issuer',
            specialized_equipment='$specialized_equipment',
            years_in_field=$years_in_field_sql, years_on_equipment=$years_on_equipment_sql, skill_level='$skill_level', certificates='$certificates',
            owner_supervisor='$owner_supervisor', supplier_id=$supplier_id_sql, employment_affiliation='$employment_affiliation', 
            salary_type='$salary_type', monthly_salary=$monthly_salary_sql,
            email='$email', phone='$phone', phone_alternative='$phone_alternative', address='$address',
            performance_rating='$performance_rating', behavior_record='$behavior_record', accident_record='$accident_record',
            health_status='$health_status', health_issues='$health_issues', vaccinations_status='$vaccinations_status',
            previous_employer='$previous_employer', employment_duration='$employment_duration', reference_contact='$reference_contact', general_notes='$general_notes',
            driver_status='$driver_status', start_date=$start_date_sql, status='$status' 
            WHERE $scope_where";
            
        if (mysqli_query($conn, $update_query)) {
            header("Location: drivers.php?msg=ØªÙ…+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ø´ØºÙ„+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit;
        } else {
            header("Location: drivers.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„ØªØ¹Ø¯ÙŠÙ„+âŒ: " . mysqli_error($conn));
            exit;
        }
    } else {
        // Ø¥Ø¶Ø§ÙØ©
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $license_expiry_sql = $license_expiry_date ? "'$license_expiry_date'" : "NULL";
        $start_date_sql = $start_date ? "'$start_date'" : "NULL";
        $years_in_field_sql = $years_in_field !== NULL ? $years_in_field : "NULL";
        $years_on_equipment_sql = $years_on_equipment !== NULL ? $years_on_equipment : "NULL";
        $supplier_id_sql = $supplier_id !== NULL ? $supplier_id : "NULL";
        $monthly_salary_sql = $monthly_salary !== NULL ? $monthly_salary : "NULL";
        
        $insert_query = "INSERT INTO drivers (
            name, driver_code, nickname,
            identity_type, identity_number, identity_expiry_date,
            license_number, license_type, license_expiry_date, license_issuer,
            specialized_equipment,
            years_in_field, years_on_equipment, skill_level, certificates,
            owner_supervisor, supplier_id, employment_affiliation, salary_type, monthly_salary,
            email, phone, phone_alternative, address,
            performance_rating, behavior_record, accident_record,
            health_status, health_issues, vaccinations_status,
            previous_employer, employment_duration, reference_contact, general_notes,
            driver_status, start_date, status$driver_insert_col
        ) VALUES (
            '$name', '$driver_code', '$nickname',
            '$identity_type', '$identity_number', $identity_expiry_sql,
            '$license_number', '$license_type', $license_expiry_sql, '$license_issuer',
            '$specialized_equipment',
            $years_in_field_sql, $years_on_equipment_sql, '$skill_level', '$certificates',
            '$owner_supervisor', $supplier_id_sql, '$employment_affiliation', '$salary_type', $monthly_salary_sql,
            '$email', '$phone', '$phone_alternative', '$address',
            '$performance_rating', '$behavior_record', '$accident_record',
            '$health_status', '$health_issues', '$vaccinations_status',
            '$previous_employer', '$employment_duration', '$reference_contact', '$general_notes',
            '$driver_status', $start_date_sql, '$status'$driver_insert_val
        )";
        
        if (mysqli_query($conn, $insert_query)) {
            header("Location: drivers_comprehensive.php?msg=ØªÙ…+Ø¥Ø¶Ø§ÙØ©+Ø§Ù„Ù…Ø´ØºÙ„+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit;
        } else {
            header("Location: drivers_comprehensive.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø¥Ø¶Ø§ÙØ©+âŒ: " . mysqli_error($conn));
            exit;
        }
    }
}

include("../inheader.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<style>
.form-section {
    background: var(--bg);
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    border: 2px solid var(--border);
}

.form-section-header {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: #fff;
    padding: 12px 15px;
    border-radius: var(--radius);
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
    transition: all var(--ease);
}

.form-section-header:hover {
    transform: translateX(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.form-section-header i {
    font-size: 1.2rem;
}

.form-section-header .toggle-icon {
    margin-right: auto;
    transition: transform 0.3s ease;
}

.form-section-header.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.form-section-body {
    padding: 5px;
    max-height: 1000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.form-section-body.collapsed {
    max-height: 0;
    padding: 0;
}

.checkbox-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    margin-bottom: 5px;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-group label:hover {
    background: var(--gold-light);
    transform: translateX(-3px);
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--gold);
}
</style>

<?php 
include('../insidebar.php'); 
?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-id-card"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† - Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ø´Ø§Ù…Ù„
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯
            </a>
            <a href="download_drivers_template.php" class="btn btn-success" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px;">
                <i class="fas fa-file-excel"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ Excel
            </a>
            <a href="download_drivers_template_csv.php" class="btn btn-info" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px;">
                <i class="fas fa-file-csv"></i> ØªØ­Ù…ÙŠÙ„ Ù†Ù…ÙˆØ°Ø¬ CSV
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-upload"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel/CSV
            </button>
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

    <!-- Ù…ÙˆØ¯Ø§Ù„ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel/CSV -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff;">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-upload"></i> Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ù…Ù† Ù…Ù„Ù Excel Ø£Ùˆ CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="text-align: right; direction: rtl;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ù‡Ø§Ù…Ø©:</strong>
                        <ul style="margin-top: 10px; padding-right: 20px;">
                            <li>ÙŠØ¯Ø¹Ù… Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ù…Ù„ÙØ§Øª Ù…Ù† Ù†ÙˆØ¹: <code>.xlsx</code>ØŒ <code>.xls</code>ØŒ <code>.csv</code></li>
                            <li>Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù„Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù: <strong>5 Ù…ÙŠØ¬Ø§</strong></li>
                            <li>Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù„Ø¹Ø¯Ø¯ Ø§Ù„ØµÙÙˆÙ: <strong>1000 ØµÙ</strong></li>
                            <li>ØªØ£ÙƒØ¯ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙˆØªØ·Ø§Ø¨Ù‚Ù‡Ø§ Ù…Ø¹ Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯</li>
                            <li>Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø¥Ù„Ø²Ø§Ù…ÙŠØ©: <strong>Ø§Ø³Ù… Ø§Ù„Ù…Ø´ØºÙ„ØŒ Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙØŒ Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø´ØºÙ„ØŒ Ø­Ø§Ù„Ø© Ø§Ù„Ù†Ø¸Ø§Ù…</strong></li>
                            <li>ÙŠÙ…ÙƒÙ† ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ø¬Ø§Ù‡Ø² Ø£Ø¹Ù„Ø§Ù‡ ÙˆÙ…Ù„Ø¡ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙÙŠÙ‡</li>
                        </ul>
                    </div>

                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="importFile" class="form-label" style="font-weight: 600;">
                                <i class="fas fa-file-alt"></i> Ø§Ø®ØªØ± Ù…Ù„Ù Excel Ø£Ùˆ CSV
                            </label>
                            <input type="file" class="form-control" id="importFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>

                        <div id="importProgress" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                            </div>
                            <p class="text-center mt-2">Ø¬Ø§Ø±ÙŠ Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ù…Ù„ÙØŒ ÙŠØ±Ø¬Ù‰ Ø§Ù„Ø§Ù†ØªØ¸Ø§Ø±...</p>
                        </div>

                        <div id="importResult"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Ø¥ØºÙ„Ø§Ù‚
                    </button>
                    <button type="button" class="btn btn-primary" id="startImportBtn">
                        <i class="fas fa-upload"></i> Ø¨Ø¯Ø¡ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø´ØºÙ„ -->
    <form id="projectForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff;">
                <h5><i class="fas fa-edit"></i> Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø´ØºÙ„ - Ù†Ù…ÙˆØ°Ø¬ Ø´Ø§Ù…Ù„</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="drivers_id" value="">
                
                <!-- 1. Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ© -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-info-circle"></i>
                        <span>1. Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© ÙˆØ§Ù„ØªØ¹Ø±ÙŠÙÙŠØ©</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø´ØºÙ„/Ø§Ù„Ø³Ø§Ø¦Ù‚ <span style="color: red;">*</span></label>
                                <input type="text" name="name" id="name" placeholder="Ù…Ø«Ø§Ù„: Ù…Ø­Ù…Ø¯ Ø£Ø­Ù…Ø¯ Ø¹Ù„ÙŠ" required />
                            </div>
                            <div>
                                <label><i class="fas fa-barcode"></i> Ø§Ù„Ø±Ù…Ø²/Ø§Ù„ÙƒÙˆØ¯ Ø§Ù„ÙØ±ÙŠØ¯</label>
                                <input type="text" name="driver_code" id="driver_code" placeholder="Ù…Ø«Ø§Ù„: OPR-001-2026" />
                            </div>
                            <div>
                                <label><i class="fas fa-signature"></i> Ø§Ø³Ù… Ø§Ù„Ø´Ù‡Ø±Ø©/Ø§Ù„ÙƒÙ†ÙŠØ©</label>
                                <input type="text" name="nickname" id="nickname" placeholder="Ù…Ø«Ø§Ù„: Ø£Ø¨Ùˆ Ù…Ø­Ù…Ø¯" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‡ÙˆÙŠØ© ÙˆØ§Ù„ØªÙˆØ«ÙŠÙ‚ -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-id-card"></i>
                        <span>2. Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‡ÙˆÙŠØ© ÙˆØ§Ù„ØªÙˆØ«ÙŠÙ‚</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-address-card"></i> Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ©</label>
                                <select name="identity_type" id="identity_type">
                                    <option value="">-- Ø§Ø®ØªØ± Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ© --</option>
                                    <option value="Ø¨Ø·Ø§Ù‚Ø© Ù‡ÙˆÙŠØ© ÙˆØ·Ù†ÙŠØ©">Ø¨Ø·Ø§Ù‚Ø© Ù‡ÙˆÙŠØ© ÙˆØ·Ù†ÙŠØ©</option>
                                    <option value="Ø¬ÙˆØ§Ø² Ø³ÙØ±">Ø¬ÙˆØ§Ø² Ø³ÙØ±</option>
                                    <option value="Ø¨Ø·Ø§Ù‚Ø© Ù„Ø§Ø¬Ø¦">Ø¨Ø·Ø§Ù‚Ø© Ù„Ø§Ø¬Ø¦</option>
                                    <option value="Ø±Ø®ØµØ© Ù‚ÙŠØ§Ø¯Ø©">Ø±Ø®ØµØ© Ù‚ÙŠØ§Ø¯Ø©</option>
                                    <option value="Ø¨Ø·Ø§Ù‚Ø© Ø£Ø®Ø±Ù‰">Ø¨Ø·Ø§Ù‚Ø© Ø£Ø®Ø±Ù‰</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-hashtag"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ©</label>
                                <input type="text" name="identity_number" id="identity_number" placeholder="Ù…Ø«Ø§Ù„: 123456789123" />
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-times"></i> ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ù‡ÙˆÙŠØ©</label>
                                <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Ø±Ø®ØµØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø© ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-car"></i>
                        <span>3. Ø±Ø®ØµØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø© ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-id-badge"></i> Ø±Ù‚Ù… Ø±Ø®ØµØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø©</label>
                                <input type="text" name="license_number" id="license_number" placeholder="Ù…Ø«Ø§Ù„: DL-2024-456789" />
                            </div>
                            <div>
                                <label><i class="fas fa-certificate"></i> Ù†ÙˆØ¹ Ø±Ø®ØµØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø©</label>
                                <select name="license_type" id="license_type">
                                    <option value="">-- Ø§Ø®ØªØ± Ù†ÙˆØ¹ Ø§Ù„Ø±Ø®ØµØ© --</option>
                                    <option value="ÙØ¦Ø© Ø£ (Ø¯Ø±Ø§Ø¬Ø§Øª Ù†Ø§Ø±ÙŠØ©)">ÙØ¦Ø© Ø£ (Ø¯Ø±Ø§Ø¬Ø§Øª Ù†Ø§Ø±ÙŠØ©)</option>
                                    <option value="ÙØ¦Ø© Ø¨ (Ø³ÙŠØ§Ø±Ø§Øª Ø®ØµÙˆØµÙŠØ©)">ÙØ¦Ø© Ø¨ (Ø³ÙŠØ§Ø±Ø§Øª Ø®ØµÙˆØµÙŠØ©)</option>
                                    <option value="ÙØ¦Ø© Ø¬ (Ø´Ø§Ø­Ù†Ø§Øª Ø®ÙÙŠÙØ©)">ÙØ¦Ø© Ø¬ (Ø´Ø§Ø­Ù†Ø§Øª Ø®ÙÙŠÙØ©)</option>
                                    <option value="ÙØ¦Ø© Ø¯ (Ø´Ø§Ø­Ù†Ø§Øª Ø«Ù‚ÙŠÙ„Ø©)">ÙØ¦Ø© Ø¯ (Ø´Ø§Ø­Ù†Ø§Øª Ø«Ù‚ÙŠÙ„Ø©)</option>
                                    <option value="ÙØ¦Ø© Ù‡Ù€ (Ø­Ø§ÙÙ„Ø§Øª)">ÙØ¦Ø© Ù‡Ù€ (Ø­Ø§ÙÙ„Ø§Øª)</option>
                                    <option value="Ù…ØªØ¹Ø¯Ø¯Ø© Ø§Ù„ÙØ¦Ø§Øª">Ù…ØªØ¹Ø¯Ø¯Ø© Ø§Ù„ÙØ¦Ø§Øª</option>
                                    <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-times"></i> ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ø±Ø®ØµØ©</label>
                                <input type="date" name="license_expiry_date" id="license_expiry_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-building"></i> Ø¬Ù‡Ø© Ø¥ØµØ¯Ø§Ø± Ø§Ù„Ø±Ø®ØµØ©</label>
                                <input type="text" name="license_issuer" id="license_issuer" placeholder="Ù…Ø«Ø§Ù„: Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø±ÙˆØ± - Ø§Ù„Ø®Ø±Ø·ÙˆÙ…" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Ø§Ù„ØªØ®ØµØµ ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-cogs"></i>
                        <span>4. Ø§Ù„ØªØ®ØµØµ ÙˆØ§Ù„Ù…Ù‡Ø§Ø±Ø§Øª</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div>
                            <label style="display: block; margin-bottom: 10px; font-weight: 700;">
                                <i class="fas fa-tools"></i> Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø© Ø§Ù„Ù…ØªØ®ØµØµ ÙÙŠÙ‡Ø§ (ÙŠÙ…ÙƒÙ† Ø§Ø®ØªÙŠØ§Ø± Ø£ÙƒØ«Ø± Ù…Ù† ÙˆØ§Ø­Ø¯)
                            </label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ø­ÙØ§Ø±Ø© (Excavator)"> Ø­ÙØ§Ø±Ø© (Excavator)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ù…Ø«Ù‚Ø§Ø¨/Ù…ÙƒÙ†Ø© ØªØ®Ø±ÙŠÙ… (Drill Machine)"> Ù…Ø«Ù‚Ø§Ø¨/Ù…ÙƒÙ†Ø© ØªØ®Ø±ÙŠÙ… (Drill Machine)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ø¯ÙˆØ²Ø± (Dozer)"> Ø¯ÙˆØ²Ø± (Dozer)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ø´Ø§Ø­Ù†Ø© Ù‚Ù„Ø§Ø¨Ø© (Dump Truck)"> Ø´Ø§Ø­Ù†Ø© Ù‚Ù„Ø§Ø¨Ø© (Dump Truck)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ø´Ø§Ø­Ù†Ø© ØªÙ†Ø§ÙƒØ±/ØµÙ‡Ø±ÙŠØ¬ (Tanker Truck)"> Ø´Ø§Ø­Ù†Ø© ØªÙ†Ø§ÙƒØ±/ØµÙ‡Ø±ÙŠØ¬ (Tanker Truck)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ø¬Ø±Ø§ÙØ© (Loader)"> Ø¬Ø±Ø§ÙØ© (Loader)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ù…Ù…Ù‡Ø¯Ø© (Grader)"> Ù…Ù…Ù‡Ø¯Ø© (Grader)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="Ù…Ø¹Ø¯Ø§Øª Ø£Ø®Ø±Ù‰"> Ù…Ø¹Ø¯Ø§Øª Ø£Ø®Ø±Ù‰</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Ø³Ù†ÙˆØ§Øª Ø§Ù„Ø®Ø¨Ø±Ø© ÙˆØ§Ù„ÙƒÙØ§Ø¡Ø© -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-medal"></i>
                        <span>5. Ø³Ù†ÙˆØ§Øª Ø§Ù„Ø®Ø¨Ø±Ø© ÙˆØ§Ù„ÙƒÙØ§Ø¡Ø©</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-briefcase"></i> Ø³Ù†ÙˆØ§Øª Ø§Ù„Ø¹Ù…Ù„ ÙÙŠ Ø§Ù„Ù…Ø¬Ø§Ù„</label>
                                <input type="number" name="years_in_field" id="years_in_field" placeholder="Ù…Ø«Ø§Ù„: 8" min="0" max="50" />
                            </div>
                            <div>
                                <label><i class="fas fa-wrench"></i> Ø³Ù†ÙˆØ§Øª Ø§Ù„Ø¹Ù…Ù„ Ø¹Ù„Ù‰ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</label>
                                <input type="number" name="years_on_equipment" id="years_on_equipment" placeholder="Ù…Ø«Ø§Ù„: 5" min="0" max="50" />
                            </div>
                            <div>
                                <label><i class="fas fa-star"></i> Ù…Ø³ØªÙˆÙ‰ Ø§Ù„ÙƒÙØ§Ø¡Ø© Ø§Ù„Ù…Ù‡Ù†ÙŠØ©</label>
                                <select name="skill_level" id="skill_level">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù…Ø³ØªÙˆÙ‰ --</option>
                                    <option value="Ù…Ø¨ØªØ¯Ø¦ (Ø£Ù‚Ù„ Ù…Ù† Ø³Ù†Ø©)">Ù…Ø¨ØªØ¯Ø¦ (Ø£Ù‚Ù„ Ù…Ù† Ø³Ù†Ø©)</option>
                                    <option value="Ù…ØªØ¯Ø±Ø¨ (1-2 Ø³Ù†Ø©)">Ù…ØªØ¯Ø±Ø¨ (1-2 Ø³Ù†Ø©)</option>
                                    <option value="ÙƒÙØ¡ (3-5 Ø³Ù†ÙˆØ§Øª)">ÙƒÙØ¡ (3-5 Ø³Ù†ÙˆØ§Øª)</option>
                                    <option value="Ø®Ø¨ÙŠØ± (5-10 Ø³Ù†ÙˆØ§Øª)">Ø®Ø¨ÙŠØ± (5-10 Ø³Ù†ÙˆØ§Øª)</option>
                                    <option value="Ø³ÙŠØ¯ Ø­Ø±ÙØ© (Ø£ÙƒØ«Ø± Ù…Ù† 10 Ø³Ù†ÙˆØ§Øª)">Ø³ÙŠØ¯ Ø­Ø±ÙØ© (Ø£ÙƒØ«Ø± Ù…Ù† 10 Ø³Ù†ÙˆØ§Øª)</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-graduation-cap"></i> Ø§Ù„Ø´Ù‡Ø§Ø¯Ø§Øª ÙˆØ§Ù„ØªØ¯Ø±ÙŠØ¨Ø§Øª</label>
                                <textarea name="certificates" id="certificates" rows="3" placeholder="Ù…Ø«Ø§Ù„: Ø´Ù‡Ø§Ø¯Ø© ØªØ´ØºÙŠÙ„ Ø­ÙØ§Ø±Ø§Øª Ù…Ù† Ù…Ø¹Ù‡Ø¯ Ø§Ù„ØªØ¹Ø¯ÙŠÙ†ØŒ Ø¯ÙˆØ±Ø© Ø§Ù„Ø³Ù„Ø§Ù…Ø© Ø§Ù„ØµÙ†Ø§Ø¹ÙŠØ©"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø¹Ù…Ù„ ÙˆØ§Ù„ØªØ¨Ø¹ÙŠØ© -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-users"></i>
                        <span>6. Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ø¹Ù…Ù„ ÙˆØ§Ù„ØªØ¨Ø¹ÙŠØ©</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user-tie"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø§Ù„Ùƒ/Ø§Ù„Ù…Ø´Ø±Ù Ø§Ù„Ù…Ø¨Ø§Ø´Ø±</label>
                                <input type="text" name="owner_supervisor" id="owner_supervisor" placeholder="Ù…Ø«Ø§Ù„: Ù…Ø­Ù…Ø¯ Ø¹Ù„ÙŠ (Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©)" />
                            </div>
                            <div>
                                <label><i class="fas fa-building"></i> Ø§Ù„Ù…ÙˆØ±Ø¯ Ø§Ù„Ø°ÙŠ ÙŠØ¹Ù…Ù„ Ù…Ø¹Ù‡</label>
                                <select name="supplier_id" id="supplier_id">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù…ÙˆØ±Ø¯ --</option>
                                    <?php
                                    $supplier_scope_sql = "1=1";
                                    if (!$is_super_admin && $suppliers_has_company) {
                                        $supplier_scope_sql = "company_id = $company_id";
                                    }
                                    $suppliers_query = "SELECT id, name FROM suppliers WHERE $supplier_scope_sql ORDER BY name";
                                    $suppliers_result = mysqli_query($conn, $suppliers_query);
                                    while ($supplier = mysqli_fetch_assoc($suppliers_result)) {
                                        echo "<option value='" . $supplier['id'] . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-sitemap"></i> ØªØ¨Ø¹ÙŠØ© Ø§Ù„Ù…Ø´ØºÙ„</label>
                                <select name="employment_affiliation" id="employment_affiliation">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„ØªØ¨Ø¹ÙŠØ© --</option>
                                    <option value="ØªØ§Ø¨Ø¹ Ù„Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø© Ù…Ø¨Ø§Ø´Ø±Ø©">ØªØ§Ø¨Ø¹ Ù„Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø© Ù…Ø¨Ø§Ø´Ø±Ø©</option>
                                    <option value="ØªØ§Ø¨Ø¹ Ù„Ù„Ù…ÙˆØ±Ø¯/Ø§Ù„ÙˆØ³ÙŠØ·">ØªØ§Ø¨Ø¹ Ù„Ù„Ù…ÙˆØ±Ø¯/Ø§Ù„ÙˆØ³ÙŠØ·</option>
                                    <option value="ØªØ§Ø¨Ø¹ Ù„Ø´Ø±ÙƒØ© Ù…ØªØ®ØµØµØ© ÙÙŠ Ø§Ù„ØªØ´ØºÙŠÙ„">ØªØ§Ø¨Ø¹ Ù„Ø´Ø±ÙƒØ© Ù…ØªØ®ØµØµØ© ÙÙŠ Ø§Ù„ØªØ´ØºÙŠÙ„</option>
                                    <option value="Ù…Ù‚Ø§ÙˆÙ„ Ù…Ø³ØªÙ‚Ù„">Ù…Ù‚Ø§ÙˆÙ„ Ù…Ø³ØªÙ‚Ù„</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-money-bill-wave"></i> Ù†ÙˆØ¹ Ø§Ù„Ø±Ø§ØªØ¨/Ø§Ù„Ø£Ø¬Ø±</label>
                                <select name="salary_type" id="salary_type">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù†ÙˆØ¹ --</option>
                                    <option value="ÙŠÙˆÙ…ÙŠ">ÙŠÙˆÙ…ÙŠ</option>
                                    <option value="Ø£Ø³Ø¨ÙˆØ¹ÙŠ">Ø£Ø³Ø¨ÙˆØ¹ÙŠ</option>
                                    <option value="Ø´Ù‡Ø±ÙŠ">Ø´Ù‡Ø±ÙŠ</option>
                                    <option value="Ø­Ø³Ø¨ Ø§Ù„Ø¥Ù†ØªØ§Ø¬ÙŠØ©">Ø­Ø³Ø¨ Ø§Ù„Ø¥Ù†ØªØ§Ø¬ÙŠØ©</option>
                                    <option value="Ø­Ø³Ø¨ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹">Ø­Ø³Ø¨ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-dollar-sign"></i> Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ø´Ù‡Ø±ÙŠ Ø§Ù„ØªÙ‚Ø±ÙŠØ¨ÙŠ</label>
                                <input type="number" step="0.01" name="monthly_salary" id="monthly_salary" placeholder="Ù…Ø«Ø§Ù„: 1500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ© -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-address-book"></i>
                        <span>7. Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„ÙŠØ©</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-envelope"></i> Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ</label>
                                <input type="email" name="email" id="email" placeholder="operator@example.com" />
                            </div>
                            <div>
                                <label><i class="fas fa-phone"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ <span style="color: red;">*</span></label>
                                <input type="tel" name="phone" id="phone" placeholder="+249-9-123-4567" required />
                            </div>
                            <div>
                                <label><i class="fas fa-phone-alt"></i> Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø¨Ø¯ÙŠÙ„</label>
                                <input type="tel" name="phone_alternative" id="phone_alternative" placeholder="+249-9-765-4321" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-map-marker-alt"></i> Ø§Ù„Ø¹Ù†ÙˆØ§Ù†</label>
                                <textarea name="address" id="address" rows="2" placeholder="Ù…Ø«Ø§Ù„: Ø´Ø§Ø±Ø¹ Ø§Ù„Ù†ÙŠÙ„ØŒ Ø§Ù„Ø®Ø±Ø·ÙˆÙ…"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. ØªÙ‚ÙŠÙŠÙ… Ø§Ù„Ø£Ø¯Ø§Ø¡ ÙˆØ§Ù„Ø³Ù„ÙˆÙƒ -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-star-half-alt"></i>
                        <span>8. ØªÙ‚ÙŠÙŠÙ… Ø§Ù„Ø£Ø¯Ø§Ø¡ ÙˆØ§Ù„Ø³Ù„ÙˆÙƒ</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-chart-line"></i> ØªÙ‚ÙŠÙŠÙ… Ø§Ù„ÙƒÙØ§Ø¡Ø© Ø§Ù„ØªØ´ØºÙŠÙ„ÙŠØ©</label>
                                <select name="performance_rating" id="performance_rating">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„ØªÙ‚ÙŠÙŠÙ… --</option>
                                    <option value="Ù…Ù…ØªØ§Ø²">â­â­â­â­â­ Ù…Ù…ØªØ§Ø²</option>
                                    <option value="Ø¬ÙŠØ¯ Ø¬Ø¯Ø§Ù‹">â­â­â­â­ Ø¬ÙŠØ¯ Ø¬Ø¯Ø§Ù‹</option>
                                    <option value="Ø¬ÙŠØ¯">â­â­â­ Ø¬ÙŠØ¯</option>
                                    <option value="Ù…Ù‚Ø¨ÙˆÙ„">â­â­ Ù…Ù‚Ø¨ÙˆÙ„</option>
                                    <option value="Ø¶Ø¹ÙŠÙ">â­ Ø¶Ø¹ÙŠÙ</option>
                                    <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-user-check"></i> Ø³Ø¬Ù„ Ø§Ù„Ø³Ù„ÙˆÙƒ ÙˆØ§Ù„Ø§Ù†Ø¶Ø¨Ø§Ø·</label>
                                <select name="behavior_record" id="behavior_record">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø³Ø¬Ù„ --</option>
                                    <option value="Ù…Ù…ØªØ§Ø² (Ù„Ø§ ØªÙˆØ¬Ø¯ Ø´ÙƒØ§ÙˆÙ‰)">âœ… Ù…Ù…ØªØ§Ø² (Ù„Ø§ ØªÙˆØ¬Ø¯ Ø´ÙƒØ§ÙˆÙ‰)</option>
                                    <option value="Ø¬ÙŠØ¯ (Ø´ÙƒØ§ÙˆÙ‰ Ù†Ø§Ø¯Ø±Ø©)">ðŸ‘ Ø¬ÙŠØ¯ (Ø´ÙƒØ§ÙˆÙ‰ Ù†Ø§Ø¯Ø±Ø©)</option>
                                    <option value="Ù…Ù‚Ø¨ÙˆÙ„ (Ø¨Ø¹Ø¶ Ø§Ù„Ø´ÙƒØ§ÙˆÙ‰)">âš ï¸ Ù…Ù‚Ø¨ÙˆÙ„ (Ø¨Ø¹Ø¶ Ø§Ù„Ø´ÙƒØ§ÙˆÙ‰)</option>
                                    <option value="Ø¶Ø¹ÙŠÙ (Ø´ÙƒØ§ÙˆÙ‰ Ù…ØªÙƒØ±Ø±Ø©)">âŒ Ø¶Ø¹ÙŠÙ (Ø´ÙƒØ§ÙˆÙ‰ Ù…ØªÙƒØ±Ø±Ø©)</option>
                                    <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-exclamation-triangle"></i> Ø³Ø¬Ù„ Ø§Ù„Ø­ÙˆØ§Ø¯Ø« ÙˆØ§Ù„Ø£Ø¹Ø·Ø§Ù„</label>
                                <select name="accident_record" id="accident_record">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø³Ø¬Ù„ --</option>
                                    <option value="Ù†Ø¸ÙŠÙ (Ù„Ø§ ØªÙˆØ¬Ø¯ Ø­ÙˆØ§Ø¯Ø«)">âœ… Ù†Ø¸ÙŠÙ (Ù„Ø§ ØªÙˆØ¬Ø¯ Ø­ÙˆØ§Ø¯Ø«)</option>
                                    <option value="Ø­Ø§Ø¯Ø« ÙˆØ§Ø­Ø¯ (Ø·ÙÙŠÙ)">âš ï¸ Ø­Ø§Ø¯Ø« ÙˆØ§Ø­Ø¯ (Ø·ÙÙŠÙ)</option>
                                    <option value="Ø­Ø§Ø¯Ø«Ø§Ù† (Ù…ØªÙˆØ³Ø·)">ðŸš¨ Ø­Ø§Ø¯Ø«Ø§Ù† (Ù…ØªÙˆØ³Ø·)</option>
                                    <option value="Ø«Ù„Ø§Ø«Ø© Ø­ÙˆØ§Ø¯Ø« ÙØ£ÙƒØ«Ø± (Ø®Ø·ÙŠØ±)">â˜ ï¸ Ø«Ù„Ø§Ø«Ø© Ø­ÙˆØ§Ø¯Ø« ÙØ£ÙƒØ«Ø± (Ø®Ø·ÙŠØ±)</option>
                                    <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. Ø§Ù„ØµØ­Ø© ÙˆØ§Ù„Ø³Ù„Ø§Ù…Ø© -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-heartbeat"></i>
                        <span>9. Ø§Ù„ØµØ­Ø© ÙˆØ§Ù„Ø³Ù„Ø§Ù…Ø©</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-heart"></i> Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„ØµØ­ÙŠØ©</label>
                                <select name="health_status" id="health_status">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                                    <option value="Ø³Ù„ÙŠÙ… ØªÙ…Ø§Ù…Ø§Ù‹">âœ… Ø³Ù„ÙŠÙ… ØªÙ…Ø§Ù…Ø§Ù‹</option>
                                    <option value="Ø¨Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©">ðŸ‘ Ø¨Ø­Ø§Ù„Ø© Ø¬ÙŠØ¯Ø©</option>
                                    <option value="Ø¨Ø­Ø§Ù„Ø© Ù…Ù‚Ø¨ÙˆÙ„Ø©">âš ï¸ Ø¨Ø­Ø§Ù„Ø© Ù…Ù‚Ø¨ÙˆÙ„Ø©</option>
                                    <option value="Ù…Ø­ØªØ§Ø¬ Ù…ØªØ§Ø¨Ø¹Ø© Ø·Ø¨ÙŠØ©">ðŸ¥ Ù…Ø­ØªØ§Ø¬ Ù…ØªØ§Ø¨Ø¹Ø© Ø·Ø¨ÙŠØ©</option>
                                    <option value="ØºÙŠØ± Ù…Ø­Ø¯Ø¯">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-syringe"></i> Ø§Ù„ØªØ·Ø¹ÙŠÙ…Ø§Øª ÙˆØ§Ù„ÙØ­ÙˆØµØ§Øª</label>
                                <select name="vaccinations_status" id="vaccinations_status">
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                                    <option value="Ù…Ø­Ø¯Ø«Ø©">âœ… Ù…Ø­Ø¯Ø«Ø©</option>
                                    <option value="Ù‚Ø¯ÙŠÙ…Ø©">â° Ù‚Ø¯ÙŠÙ…Ø©</option>
                                    <option value="Ù„Ø§ ÙŠÙˆØ¬Ø¯ ÙØ­Øµ">âŒ Ù„Ø§ ÙŠÙˆØ¬Ø¯ ÙØ­Øµ</option>
                                    <option value="Ù‚ÙŠØ¯ Ø§Ù„ÙØ­Øµ">â³ Ù‚ÙŠØ¯ Ø§Ù„ÙØ­Øµ</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-notes-medical"></i> Ø§Ù„Ù…Ø´Ø§ÙƒÙ„ Ø§Ù„ØµØ­ÙŠØ© Ø§Ù„Ù…Ø¹Ø±ÙˆÙØ©</label>
                                <textarea name="health_issues" id="health_issues" rows="2" placeholder="Ù…Ø«Ø§Ù„: Ø¶Ø¹Ù Ø§Ù„Ø¨ØµØ± Ø§Ù„Ø·ÙÙŠÙØŒ Ù…Ø´Ø§ÙƒÙ„ Ø§Ù„Ø¸Ù‡Ø±"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 10. Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹ ÙˆØ§Ù„Ø³Ø¬Ù„ -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-history"></i>
                        <span>10. Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹ ÙˆØ§Ù„Ø³Ø¬Ù„</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-building"></i> Ø§Ø³Ù… Ø¬Ù‡Ø© Ø§Ù„ØªÙˆØ¸ÙŠÙ Ø§Ù„Ø³Ø§Ø¨Ù‚Ø©</label>
                                <input type="text" name="previous_employer" id="previous_employer" placeholder="Ù…Ø«Ø§Ù„: Ø´Ø±ÙƒØ© Ø§Ù„Ø°Ù‡Ø¨ Ù„Ù„ØªØ¹Ø¯ÙŠÙ†" />
                            </div>
                            <div>
                                <label><i class="fas fa-clock"></i> Ù…Ø¯Ø© Ø§Ù„Ø¹Ù…Ù„ Ù…Ø¹Ù‡Ù…</label>
                                <input type="text" name="employment_duration" id="employment_duration" placeholder="Ù…Ø«Ø§Ù„: 3 Ø³Ù†ÙˆØ§Øª" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-user-friends"></i> Ù…Ø±Ø¬Ø¹ Ù„Ù„Ø§ØªØµØ§Ù„</label>
                                <input type="text" name="reference_contact" id="reference_contact" placeholder="Ù…Ø«Ø§Ù„: Ù…Ø­Ù…ÙˆØ¯ Ø£Ø­Ù…Ø¯ - Ù…Ø¯ÙŠØ± Ø§Ù„Ø£Ø³Ø·ÙˆÙ„ (09-123-4567)" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-comment-dots"></i> Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¹Ø§Ù…Ø©</label>
                                <textarea name="general_notes" id="general_notes" rows="3" placeholder="Ù…Ø«Ø§Ù„: Ù…Ø´ØºÙ„ Ù…ÙˆØ«ÙˆÙ‚ ÙˆØ°Ùˆ ÙƒÙØ§Ø¡Ø© Ø¹Ø§Ù„ÙŠØ©ØŒ ÙŠØ­ØªØ§Ø¬ Ø¥Ù„Ù‰ ØªØ¯Ø±ÙŠØ¨ Ø¹Ù„Ù‰ Ø§Ù„Ø³Ù„Ø§Ù…Ø©"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 11. Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„ØªÙØ¹ÙŠÙ„ -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-toggle-on"></i>
                        <span>11. Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„ØªÙØ¹ÙŠÙ„</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-info-circle"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø´ØºÙ„ <span style="color: red;">*</span></label>
                                <select name="driver_status" id="driver_status" required>
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                                    <option value="Ù†Ø´Ø·">ðŸŸ¢ Ù†Ø´Ø·</option>
                                    <option value="Ù…Ø¹Ù„Ù‚">â¸ï¸ Ù…Ø¹Ù„Ù‚</option>
                                    <option value="Ù…ÙØµÙˆÙ„">ðŸ”´ Ù…ÙØµÙˆÙ„</option>
                                    <option value="ÙÙŠ Ø¥Ø¬Ø§Ø²Ø©">ðŸ–ï¸ ÙÙŠ Ø¥Ø¬Ø§Ø²Ø©</option>
                                    <option value="ØªØ­Øª Ø§Ù„ØªÙ‚ÙŠÙŠÙ…">â³ ØªØ­Øª Ø§Ù„ØªÙ‚ÙŠÙŠÙ…</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-check"></i> ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø¡ Ø§Ù„ÙØ¹Ù„ÙŠ</label>
                                <input type="date" name="start_date" id="start_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-power-off"></i> Ø­Ø§Ù„Ø© Ø§Ù„Ù†Ø¸Ø§Ù… <span style="color: red;">*</span></label>
                                <select name="status" id="status" required>
                                    <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                                    <option value="1">ðŸŸ¢ Ù…ÙØ¹Ù‘Ù„</option>
                                    <option value="0">ðŸ”´ Ù…ÙˆÙ‚Ù</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border); justify-content: center;">
                    <button type="submit" class="btn-submit" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ù…Ø´ØºÙ„
                    </button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('projectForm').style.display='none';">
                        <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                    </button>
                    <button type="button" class="btn-submit" style="background: linear-gradient(135deg, #10b981, #059669);" onclick="expandAllSections()">
                        <i class="fas fa-expand-alt"></i> ÙØªØ­ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
                    </button>
                    <button type="button" class="btn-submit" style="background: linear-gradient(135deg, #f59e0b, #d97706);" onclick="collapseAllSections()">
                        <i class="fas fa-compress-alt"></i> Ø¥ØºÙ„Ø§Ù‚ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†</h5>
        </div>
        <div class="card-body">
            <table id="driversTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ø§Ù„ÙƒÙˆØ¯</th>
                        <th>Ø§Ø³Ù… Ø§Ù„Ù…Ø´ØºÙ„</th>
                        <th>Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ</th>
                        <th>Ø§Ù„Ù…ÙˆØ±Ø¯</th>
                        <th>Ù…Ø³ØªÙˆÙ‰ Ø§Ù„ÙƒÙØ§Ø¡Ø©</th>
                        <th>Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯</th>
                        <th>Ø§Ù„Ø­Ø§Ù„Ø©</th>
                        <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ù…Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ©
                    $drivers_scope_sql = "1=1";
                    if (!$is_super_admin) {
                        if ($drivers_has_company) {
                            $drivers_scope_sql = "d.company_id = $company_id";
                        } else {
                            $drivers_scope_sql = "EXISTS (
                                SELECT 1
                                FROM drivercontracts dsc
                                INNER JOIN project sp ON sp.id = dsc.project_id
                                INNER JOIN users su ON su.id = sp.created_by
                                WHERE dsc.driver_id = d.id
                                  AND su.company_id = $company_id
                            )";
                        }
                    }

                    $drivercontracts_scope_sql = (!$is_super_admin && $drivercontracts_has_company)
                        ? " AND drivercontracts.company_id = $company_id"
                        : "";

                    $query = "SELECT d.*, s.name as supplier_name,
                             (SELECT COUNT(*) FROM drivercontracts WHERE driver_id = d.id$drivercontracts_scope_sql) as numcontracts
                             FROM drivers d
                             LEFT JOIN suppliers s ON d.supplier_id = s.id
                             WHERE $drivers_scope_sql
                             ORDER BY d.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $statusBadge = $row['status'] == "1" ? '<span class="status-pill status-active">ðŸŸ¢ Ù…ÙØ¹Ù‘Ù„</span>' : '<span class="status-pill status-inactive">ðŸ”´ Ù…ÙˆÙ‚Ù</span>';
                        
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><code>" . htmlspecialchars($row['driver_code'] ?: 'N/A') . "</code></td>";
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['supplier_name'] ?: '-') . "</td>";
                        echo "<td>" . htmlspecialchars($row['skill_level'] ?: 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯') . "</td>";
                        echo "<td><span class='badge badge-info'>" . $row['numcontracts'] . " Ø¹Ù‚Ø¯</span></td>";
                        echo "<td>" . $statusBadge . "</td>";
                        echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)' 
                                       class='action-btn edit editBtn' 
                                       data-id='" . $row['id'] . "' 
                                       title='ØªØ¹Ø¯ÙŠÙ„'>
                                        <i class='fas fa-edit'></i>
                                    </a>
                                    <a href='drivercontracts.php?id=" . $row['id'] . "' 
                                       class='action-btn view' 
                                       title='Ø¹Ø±Ø¶ Ø§Ù„Ø¹Ù‚ÙˆØ¯'>
                                        <i class='fas fa-file-contract'></i>
                                    </a>
                                    <a href='driver_truck_history.php?id=" . $row['id'] . "' 
                                       class='action-btn history' 
                                       title='ØªØ§Ø±ÙŠØ® Ø§Ù„Ù‚ÙŠØ§Ø¯Ø©'>
                                        <i class='fas fa-history'></i>
                                    </a>
                                </div>
                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    // Ø¯Ø§Ù„Ø© Ø·ÙŠ/ÙØªØ­ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
    function toggleSection(header) {
        const body = header.nextElementSibling;
        header.classList.toggle('collapsed');
        body.classList.toggle('collapsed');
    }

    // ÙØªØ­ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
    function expandAllSections() {
        document.querySelectorAll('.form-section-header').forEach(header => {
            header.classList.remove('collapsed');
            header.nextElementSibling.classList.remove('collapsed');
        });
    }

    // Ø¥ØºÙ„Ø§Ù‚ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
    function collapseAllSections() {
        document.querySelectorAll('.form-section-header').forEach(header => {
            header.classList.add('collapsed');
            header.nextElementSibling.classList.add('collapsed');
        });
    }

    (function () {
        // ØªØ´ØºÙŠÙ„ DataTable Ø¨Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©
        $(document).ready(function () {
            $('#driversTable').DataTable({
                responsive: true,
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
        });

        // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø± ÙˆØ¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        
        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                
                if (projectForm.style.display === "block") {
                    // ØªÙ†Ø¸ÙŠÙ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ù‚ÙˆÙ„
                    projectForm.reset();
                    $("#drivers_id").val("");
                    // ÙØªØ­ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø© Ø§Ù„Ø£ÙˆÙ„Ù‰ ÙÙ‚Ø·
                    collapseAllSections();
                    document.querySelector('.form-section-header').classList.remove('collapsed');
                    document.querySelector('.form-section-body').classList.remove('collapsed');
                    
                    // Ø§Ù„ØªÙ…Ø±ÙŠØ± Ù„Ù„ÙÙˆØ±Ù…
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                    $("#name").focus();
                }
            });
        }

        // Ø¹Ù†Ø¯ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø²Ø± ØªØ¹Ø¯ÙŠÙ„ - ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø¹Ø¨Ø± AJAX
        $(document).on("click", ".editBtn", function () {
            const id = $(this).data("id");
            
            // Ø¬Ù„Ø¨ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ÙƒØ§Ù…Ù„Ø© Ø¹Ø¨Ø± AJAX
            $.ajax({
                url: 'get_driver_data.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        const driver = data.driver;
                        
                        // Ù…Ù„Ø¡ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ù‚ÙˆÙ„
                        $("#drivers_id").val(driver.id);
                        $("#name").val(driver.name);
                        $("#driver_code").val(driver.driver_code);
                        $("#nickname").val(driver.nickname);
                        $("#identity_type").val(driver.identity_type);
                        $("#identity_number").val(driver.identity_number);
                        $("#identity_expiry_date").val(driver.identity_expiry_date);
                        $("#license_number").val(driver.license_number);
                        $("#license_type").val(driver.license_type);
                        $("#license_expiry_date").val(driver.license_expiry_date);
                        $("#license_issuer").val(driver.license_issuer);
                        
                        // Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ù…ØªØ®ØµØµØ© (checkboxes)
                        const equipment = driver.specialized_equipment ? driver.specialized_equipment.split(', ') : [];
                        $("input[name='specialized_equipment[]']").prop("checked", false);
                        equipment.forEach(function(eq) {
                            $("input[name='specialized_equipment[]'][value='" + eq.trim() + "']").prop("checked", true);
                        });
                        
                        $("#years_in_field").val(driver.years_in_field);
                        $("#years_on_equipment").val(driver.years_on_equipment);
                        $("#skill_level").val(driver.skill_level);
                        $("#certificates").val(driver.certificates);
                        $("#owner_supervisor").val(driver.owner_supervisor);
                        $("#supplier_id").val(driver.supplier_id);
                        $("#employment_affiliation").val(driver.employment_affiliation);
                        $("#salary_type").val(driver.salary_type);
                        $("#monthly_salary").val(driver.monthly_salary);
                        $("#email").val(driver.email);
                        $("#phone").val(driver.phone);
                        $("#phone_alternative").val(driver.phone_alternative);
                        $("#address").val(driver.address);
                        $("#performance_rating").val(driver.performance_rating);
                        $("#behavior_record").val(driver.behavior_record);
                        $("#accident_record").val(driver.accident_record);
                        $("#health_status").val(driver.health_status);
                        $("#health_issues").val(driver.health_issues);
                        $("#vaccinations_status").val(driver.vaccinations_status);
                        $("#previous_employer").val(driver.previous_employer);
                        $("#employment_duration").val(driver.employment_duration);
                        $("#reference_contact").val(driver.reference_contact);
                        $("#general_notes").val(driver.general_notes);
                        $("#driver_status").val(driver.driver_status);
                        $("#start_date").val(driver.start_date);
                        $("#status").val(driver.status);
                        
                        // Ø¹Ø±Ø¶ Ø§Ù„ÙÙˆØ±Ù… ÙˆÙØªØ­ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
                        projectForm.style.display = "block";
                        expandAllSections();
                        $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                    } else {
                        alert('Ø®Ø·Ø£ ÙÙŠ ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª');
                    }
                },
                error: function() {
                    alert('Ø­Ø¯Ø« Ø®Ø·Ø£ ÙÙŠ Ø§Ù„Ø§ØªØµØ§Ù„ Ø¨Ø§Ù„Ø®Ø§Ø¯Ù…');
                }
            });
        });

    })();

    // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ù…Ù† Excel/CSV
    $(document).ready(function() {
        $('#startImportBtn').on('click', function() {
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('ÙŠØ±Ø¬Ù‰ Ø§Ø®ØªÙŠØ§Ø± Ù…Ù„Ù Ù„Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯');
                return;
            }
            
            // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„Ù
            const allowedExtensions = ['xlsx', 'xls', 'csv'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                alert('Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„Ù ØºÙŠØ± Ù…Ø¯Ø¹ÙˆÙ…. ÙŠØ±Ø¬Ù‰ Ø±ÙØ¹ Ù…Ù„Ù Excel Ø£Ùˆ CSV');
                return;
            }
            
            // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù (5 Ù…ÙŠØ¬Ø§)
            if (file.size > 5 * 1024 * 1024) {
                alert('Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù ÙƒØ¨ÙŠØ± Ø¬Ø¯Ø§Ù‹ (Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ 5 Ù…ÙŠØ¬Ø§)');
                return;
            }
            
            // Ø¥Ù†Ø´Ø§Ø¡ FormData
            const formData = new FormData();
            formData.append('file', file);
            
            // Ø¥Ø®ÙØ§Ø¡ Ø²Ø± Ø§Ù„Ø¨Ø¯Ø¡ ÙˆØ¹Ø±Ø¶ Ø´Ø±ÙŠØ· Ø§Ù„ØªÙ‚Ø¯Ù…
            $('#startImportBtn').prop('disabled', true);
            $('#importProgress').show();
            $('#importResult').html('');
            
            // Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø·Ù„Ø¨ Ø¹Ø¨Ø± AJAX
            $.ajax({
                url: 'import_drivers_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    $('#importProgress').hide();
                    $('#startImportBtn').prop('disabled', false);
                    
                    if (response.success) {
                        // Ø¹Ø±Ø¶ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù†Ø¬Ø§Ø­
                        let resultHTML = '<div class="alert alert-success" style="text-align: right; direction: rtl;">';
                        resultHTML += '<i class="fas fa-check-circle"></i> <strong>' + response.message + '</strong>';
                        
                        // Ø¥Ø°Ø§ ÙƒØ§Ù†Øª Ù‡Ù†Ø§Ùƒ Ø£Ø®Ø·Ø§Ø¡ØŒ Ø¹Ø±Ø¶Ù‡Ø§
                        if (response.errors && response.errors.length > 0) {
                            resultHTML += '<hr><strong>ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø£Ø®Ø·Ø§Ø¡:</strong><ul class="mb-0">';
                            response.errors.forEach(function(error) {
                                resultHTML += '<li>Ø§Ù„ØµÙ ' + error.row + ': ' + error.error + '</li>';
                            });
                            resultHTML += '</ul>';
                        }
                        
                        resultHTML += '</div>';
                        resultHTML += '<button type="button" class="btn btn-success" onclick="location.reload();">';
                        resultHTML += '<i class="fas fa-sync-alt"></i> ØªØ­Ø¯ÙŠØ« Ø§Ù„ØµÙØ­Ø© Ù„Ø¹Ø±Ø¶ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø©';
                        resultHTML += '</button>';
                        
                        $('#importResult').html(resultHTML);
                        
                        // Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† Ø§Ù„ÙÙˆØ±Ù…
                        $('#importForm')[0].reset();
                        
                    } else {
                        // Ø¹Ø±Ø¶ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ø®Ø·Ø£
                        let resultHTML = '<div class="alert alert-danger" style="text-align: right; direction: rtl;">';
                        resultHTML += '<i class="fas fa-exclamation-triangle"></i> <strong>' + response.message + '</strong>';
                        resultHTML += '</div>';
                        
                        $('#importResult').html(resultHTML);
                    }
                },
                error: function(xhr, status, error) {
                    $('#importProgress').hide();
                    $('#startImportBtn').prop('disabled', false);
                    
                    let resultHTML = '<div class="alert alert-danger" style="text-align: right; direction: rtl;">';
                    resultHTML += '<i class="fas fa-exclamation-triangle"></i> <strong>Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„Ø§ØªØµØ§Ù„ Ø¨Ø§Ù„Ø®Ø§Ø¯Ù…</strong>';
                    resultHTML += '<br>ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø®Ø·Ø£: ' + error;
                    resultHTML += '</div>';
                    
                    $('#importResult').html(resultHTML);
                }
            });
        });
        
        // Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† Ø§Ù„Ù…ÙˆØ¯Ø§Ù„ Ø¹Ù†Ø¯ Ø¥ØºÙ„Ø§Ù‚Ù‡
        $('#importModal').on('hidden.bs.modal', function() {
            $('#importForm')[0].reset();
            $('#importProgress').hide();
            $('#importResult').html('');
            $('#startImportBtn').prop('disabled', false);
        });
    });
</script>

</body>
</html>

