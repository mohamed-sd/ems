<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

$selected_project_id = 0;
$show_all_projects = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_project_id'])) {
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

$page_title = "إيكوبيشن | الآليات ";
include("../inheader.php");
include("../insidebar.php");

// معالجة رسالة النجاح
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}

// معالجة الحفظ أو التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    
    // الحقول الأساسية
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code      = mysqli_real_escape_string($conn, trim($_POST['code']));
    $type      = mysqli_real_escape_string($conn, $_POST['type']);
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status    = mysqli_real_escape_string($conn, $_POST['status']);
    $edit_id   = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    
    // المعلومات الأساسية والتعريفية
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $chassis_number = mysqli_real_escape_string($conn, trim($_POST['chassis_number'] ?? ''));
    
    // بيانات الصنع والموديل
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : 'NULL';
    $import_year = !empty($_POST['import_year']) ? intval($_POST['import_year']) : 'NULL';
    
    // الحالة الفنية والمواصفات
    $equipment_condition = mysqli_real_escape_string($conn, $_POST['equipment_condition'] ?? 'في حالة جيدة');
    $operating_hours = !empty($_POST['operating_hours']) ? intval($_POST['operating_hours']) : 'NULL';
    $engine_condition = mysqli_real_escape_string($conn, $_POST['engine_condition'] ?? 'جيدة');
    $tires_condition = mysqli_real_escape_string($conn, $_POST['tires_condition'] ?? 'N/A');
    
    // بيانات الملكية
    $actual_owner_name = mysqli_real_escape_string($conn, trim($_POST['actual_owner_name'] ?? ''));
    $owner_type = mysqli_real_escape_string($conn, $_POST['owner_type'] ?? '');
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    $owner_supplier_relation = mysqli_real_escape_string($conn, $_POST['owner_supplier_relation'] ?? '');
    
    // الوثائق والتسجيلات
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number'] ?? ''));
    $license_authority = mysqli_real_escape_string($conn, trim($_POST['license_authority'] ?? ''));
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['license_expiry_date']) . "'" : 'NULL';
    $inspection_certificate_number = mysqli_real_escape_string($conn, trim($_POST['inspection_certificate_number'] ?? ''));
    $last_inspection_date = !empty($_POST['last_inspection_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_inspection_date']) . "'" : 'NULL';
    
    // الموقع والتوفر
    $current_location = mysqli_real_escape_string($conn, trim($_POST['current_location'] ?? ''));
    $availability_status = mysqli_real_escape_string($conn, $_POST['availability_status'] ?? 'متاحة للعمل');
    
    // البيانات المالية والقيمة
    $estimated_value = !empty($_POST['estimated_value']) ? floatval($_POST['estimated_value']) : 'NULL';
    $daily_rental_price = !empty($_POST['daily_rental_price']) ? floatval($_POST['daily_rental_price']) : 'NULL';
    $monthly_rental_price = !empty($_POST['monthly_rental_price']) ? floatval($_POST['monthly_rental_price']) : 'NULL';
    $insurance_status = mysqli_real_escape_string($conn, $_POST['insurance_status'] ?? '');
    
    // ملاحظات وسجل الصيانة
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes'] ?? ''));
    $last_maintenance_date = !empty($_POST['last_maintenance_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_maintenance_date']) . "'" : 'NULL';



    // التحقق من عدم تجاوز العدد المتعاقد عليه (فقط عند الإضافة)
    if ($edit_id == 0  && $suppliers && $type) {
        // الحصول على عدد المعدات المتعاقد عليها لهذا المورد ونوع المعدة
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
            
            // حساب عدد المعدات المضافة حالياً
            $added_count_query = "SELECT COUNT(*) as added_count 
                                 FROM equipments 
                                 WHERE suppliers = $suppliers 
                                 AND type = '$type'
                                 AND status = 1";
            $added_count_result = mysqli_query($conn, $added_count_query);
            $added_count_row = mysqli_fetch_assoc($added_count_result);
            $current_added = intval($added_count_row['added_count']);
            
            // التحقق من عدم تجاوز العدد المتعاقد عليه
            if ($current_added >= $contracted_count) {
                $success_msg = "⚠️ تحذير: تم الوصول للحد الأقصى! العدد المتعاقد عليه: $contracted_count | المضاف حالياً: $current_added. لا يمكن إضافة المزيد من المعدات.";
                goto skip_save;
            }
        }
    }

    if ($edit_id > 0) {
        // تعديل
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
        $msg = "تم+تعديل+المعدة+بنجاح+✅";
    } else {
        // إضافة
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
        $msg = "تمت+إضافة+المعدة+بنجاح+✅";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: equipments.php?msg=$msg");
        exit;
    } else {
        $success_msg = "خطأ في الحفظ: " . mysqli_error($conn);
    }
    
    skip_save:
}

// في حالة تعديل تجهيز البيانات
$editData = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

    :root {
        --primary-color: #01072a;
        --secondary-color: #e2ae03;
        --dark-color: #2d2b22;
        --light-color: #f5f5f5;
        --border-color: #e0e0e0;
        --text-color: #010326;
        --gold-color: #debf0f;
        --shadow-color: rgba(0, 0, 0, 0.1);
        --accent-color: #1a1a2e;
    }
    
    * {
        font-family: 'Cairo', sans-serif;
    }
    
    body {
        background: var(--light-color);
    }

    /* Project Header */
    .project-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        padding: 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 40px var(--shadow-color);
        animation: slideDown 0.5s ease;
    }
    
    .project-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    
    .project-title {
        color: white;
        font-size: 2rem;
        font-weight: 900;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .project-title i {
        color: var(--secondary-color);
        font-size: 2.2rem;
    }
    
    .project-code-display {
        color: rgba(255, 255, 255, 0.8);
        font-size: 1rem;
        margin: 0.5rem 0 0 0;
        font-family: monospace;
        font-weight: 600;
    }
    
    .project-code-display i {
        color: var(--secondary-color);
    }
    
    .btn-back-to-projects {
        background: var(--secondary-color);
        color: var(--primary-color);
        padding: 12px 30px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(226, 174, 3, 0.4);
    }
    
    .btn-back-to-projects:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(226, 174, 3, 0.6);
        background: var(--gold-color);
    }

    @media (max-width: 768px) {
        .project-header-content {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .project-title {
            font-size: 1.5rem;
        }
        
        .btn-back-to-projects {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Page Title */
    .main h2 {
        color: var(--primary-color);
        font-size: 20px;
        font-weight: 900;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .main h2 i {
        color: var(--secondary-color);
        font-size: 24px;
    }
    
    /* Action Buttons Container */
    .aligin {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px var(--shadow-color);
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    /* Modern Action Buttons */
    .aligin .add {
        padding: 12px 30px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.95rem;
        color: white;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px var(--shadow-color);
        position: relative;
        overflow: hidden;
        background: var(--gold-color);
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .aligin .add::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .aligin .add:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .aligin .add:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
    }
    
    /* Success Message */
    .success-message {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        padding: 15px 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 15px var(--shadow-color);
        font-weight: 600;
        border-right: 4px solid #28a745;
        animation: slideDown 0.4s ease;
    }
    
    /* Error/Warning Message */
    .error-message, .warning-message {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        padding: 15px 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 15px var(--shadow-color);
        font-weight: 600;
        border-right: 4px solid #dc3545;
        animation: slideDown 0.4s ease;
    }
    
    .warning-message {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
        border-right-color: #ffc107;
    }
    
    /* Form Styling */
    #projectForm {
        animation: fadeInUp 0.6s ease;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 40px var(--shadow-color);
        overflow: hidden;
        margin-bottom: 30px;
        background: white;
    }
    
    .card-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        padding: 1.5rem;
        border: none;
    }
    
    .card-header h5 {
        color: white;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header h5 i {
        color: var(--secondary-color);
        font-size: 18px;
    }
    
    .card-body {
        padding: 2rem;
    }
    
    /* Form Fields */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .form-grid > div {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-grid label {
        font-weight: 600;
        color: var(--text-color);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .form-grid label i {
        color: var(--secondary-color);
    }
    
    .form-grid input,
    .form-grid select {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        font-weight: 500;
        background: white;
        color: var(--text-color);
    }
    
    .form-grid input:focus,
    .form-grid select:focus {
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 0.2rem rgba(226, 174, 3, 0.15);
        outline: none;
    }
    
    .form-grid button {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border: none;
        padding: 14px 30px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        justify-content: center;
    }
    
    .form-grid button:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
    }
    
    .form-grid button i {
        font-size: 1.1rem;
    }
    
    /* DataTable Styling */
    .dataTables_wrapper {
        font-family: 'Cairo', sans-serif;
    }

    table.dataTable {
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0 10px;
    }

    table.dataTable thead th {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        color: white;
        font-weight: 600;
        padding: 15px;
        text-align: center;
        border: none;
        font-size: 15px;
    }

    table.dataTable thead th:first-child {
        border-radius: 10px 0 0 10px;
    }

    table.dataTable thead th:last-child {
        border-radius: 0 10px 10px 0;
    }

    table.dataTable thead th i {
        color: var(--secondary-color);
        margin-left: 8px;
    }
    
    table.dataTable tbody tr {
        background: rgba(255, 255, 255, 0.8);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    table.dataTable tbody tr:hover {
        background: rgba(226, 174, 3, 0.08);
        transform: scale(1.01);
        box-shadow: 0 4px 15px var(--shadow-color);
    }
    
    table.dataTable tbody td {
        padding: 15px;
        vertical-align: middle;
        text-align: center;
        border: none;
        font-size: 14px;
        font-weight: 500;
    }
    
    /* Status Badges */
    .badge-available {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid #28a745;
    }
    
    .badge-busy {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid #dc3545;
    }
    
    .badge-working {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid #ffc107;
    }
    
    .badge-type {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid #17a2b8;
    }
    
    /* Action Buttons */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        margin: 0 4px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: white;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .action-btn:hover {
        transform: translateY(-2px) scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    .btn-driver {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }
    
    .btn-view {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }
    
    .extra-info {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-right: 8px;
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    .extra-info i {
        color: var(--secondary-color);
    }
    
    .project-link {
        color: #007bff;
        font-weight: 600;
        text-decoration: none;
        padding: 4px 10px;
        background: rgba(0, 123, 255, 0.1);
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .project-link:hover {
        background: rgba(0, 123, 255, 0.2);
        transform: scale(1.05);
    }

    .project-picker {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 6px 20px var(--shadow-color);
        margin-bottom: 1.5rem;
    }

    .project-picker label {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
        display: block;
    }

    .project-picker select {
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }
    
    /* Contract Stats Section */
    .contract-stats {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1.5rem;
        border-radius: 15px;
        margin-top: 1.5rem;
        border: 2px solid var(--secondary-color);
        display: none;
        animation: fadeInUp 0.5s ease;
    }
    
    .stats-title {
        color: var(--primary-color);
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.8rem;
        border-bottom: 3px solid var(--secondary-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .stats-title i {
        color: var(--secondary-color);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.2rem;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--secondary-color);
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card-value {
        font-size: 2rem;
        font-weight: 900;
        color: var(--primary-color);
        margin: 0.5rem 0;
    }
    
    .stat-card-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 600;
    }
    
    .stat-card-icon {
        font-size: 2.5rem;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
    }
    
    .suppliers-table {
        width: 100%;
        margin-top: 1rem;
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    
    .suppliers-table thead th {
        background: var(--primary-color);
        color: white;
        padding: 12px;
        text-align: center;
        font-weight: 600;
        border: none;
    }
    
    .suppliers-table thead th:first-child {
        border-radius: 8px 0 0 8px;
    }
    
    .suppliers-table thead th:last-child {
        border-radius: 0 8px 8px 0;
    }
    
    .suppliers-table tbody tr {
        background: white;
        transition: all 0.3s ease;
    }
    
    .suppliers-table tbody tr:hover {
        background: rgba(226, 174, 3, 0.1);
        transform: scale(1.02);
    }
    
    .suppliers-table tbody td {
        padding: 12px;
        text-align: center;
        border: none;
        font-weight: 500;
    }
    
    .supplier-select-highlight {
        background: linear-gradient(135deg, #fff3cd 0%, #ffe5a3 100%) !important;
        border-right: 4px solid #e2ae03 !important;
        font-weight: bold !important;
        animation: pulseHighlight 1s ease-in-out;
    }
    
    @keyframes pulseHighlight {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
    
    /* Loading State for Dropdowns */
    select.loading {
        background: url('data:image/svg+xml;charset=utf-8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="%23e2ae03" stroke-width="4"><animate attributeName="stroke-dashoffset" dur="1.5s" repeatCount="indefinite" from="0" to="502"/><animate attributeName="stroke-dasharray" dur="1.5s" repeatCount="indefinite" values="150.6 100.4;1 250;150.6 100.4"/></circle></svg>') no-repeat left 10px center;
        background-size: 20px;
    }
    
    /* Cascading Dropdown Hints */
    .dropdown-hint {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 4px;
        font-style: italic;
    }
    
    .required-indicator {
        color: #dc3545;
        font-weight: bold;
        margin-right: 3px;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 3px solid rgba(226, 174, 3, 0.3);
        border-radius: 50%;
        border-top-color: var(--secondary-color);
        animation: spin 1s linear infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* DataTables Buttons */
    .dt-buttons {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .dt-button {
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--gold-color) 100%) !important;
        color: var(--primary-color) !important;
        border: 2px solid var(--primary-color) !important;
        padding: 10px 20px !important;
        border-radius: 10px !important;
        font-family: 'Cairo', sans-serif !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
        text-shadow: none !important;
    }
    
    .dt-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(226, 174, 3, 0.4) !important;
        background: linear-gradient(135deg, var(--gold-color) 0%, var(--secondary-color) 100%) !important;
    }
    
    .dt-button span {
        color: var(--primary-color) !important;
        font-weight: 700 !important;
    }
    
    /* Column Groups Toggle Buttons */
    .column-groups-toggle {
        display: flex;
        align-items: center;
        justify-content: flex-start;
    }
    
    .toggle-group-btn,
    .toggle-all-btn {
        padding: 10px 18px;
        border: 2px solid var(--primary-color);
        background: white;
        color: var(--primary-color);
        border-radius: 10px;
        font-family: 'Cairo', sans-serif;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .toggle-group-btn:hover,
    .toggle-all-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(1, 7, 42, 0.2);
    }
    
    .toggle-group-btn.active {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        color: white;
        border-color: var(--secondary-color);
    }
    
    .toggle-all-btn {
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--gold-color) 100%);
        border-color: var(--secondary-color);
        color: var(--primary-color);
        margin-right: auto;
    }
    
    .toggle-all-btn:hover {
        box-shadow: 0 4px 12px rgba(226, 174, 3, 0.4);
    }
    
    .toggle-group-btn i,
    .toggle-all-btn i {
        font-size: 1rem;
    }
    
    /* Equipment Details Modal */
    .equipment-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        animation: fadeIn 0.3s;
    }
    
    .equipment-modal-content {
        background: white;
        margin: 3% auto;
        padding: 0;
        border-radius: 20px;
        width: 90%;
        max-width: 1000px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s;
    }
    
    .equipment-modal-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 20px 20px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .equipment-modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .equipment-modal-close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
    }
    
    .equipment-modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
    }
    
    .equipment-modal-body {
        padding: 30px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .equipment-details-section {
        margin-bottom: 25px;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        border-right: 5px solid var(--secondary-color);
    }
    
    .equipment-details-section h4 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 15px;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .equipment-details-section h4 i {
        color: var(--secondary-color);
    }
    
    .equipment-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .equipment-detail-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .equipment-detail-label {
        font-weight: 700;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .equipment-detail-value {
        color: var(--primary-color);
        font-size: 1rem;
        font-weight: 500;
        padding: 8px 12px;
        background: white;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .equipment-detail-value.empty {
        color: #999;
        font-style: italic;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideDown {
        from { 
            opacity: 0;
            transform: translateY(-50px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        table.dataTable thead th {
            font-size: 12px;
            padding: 10px 5px;
        }
        
        table.dataTable tbody td {
            font-size: 12px;
            padding: 10px 5px;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            font-size: 12px;
        }
        
        .column-groups-toggle {
            justify-content: center;
        }
        
        .toggle-group-btn,
        .toggle-all-btn {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
    }
</style>

<div class="main">
    <div class="project-picker">
        <form method="post" id="projectSelectForm">
            <label for="selected_project_id">اختر المشروع</label>
            <select name="selected_project_id" id="selected_project_id" required>
                <option value="">-- اختر المشروع --</option>
                <option value="all" <?php echo $show_all_projects ? 'selected' : ''; ?>>الكل</option>
                <?php
                if ($projects_result) {
                    while ($project_row = mysqli_fetch_assoc($projects_result)) {
                        $selected = ($selected_project_id == $project_row['id']) ? 'selected' : '';
                        $project_label = htmlspecialchars($project_row['name']);
                        if (!empty($project_row['project_code'])) {
                            $project_label .= ' (' . htmlspecialchars($project_row['project_code']) . ')';
                        }
                        echo "<option value='" . intval($project_row['id']) . "' $selected>" . $project_label . "</option>";
                    }
                }
                ?>
            </select>
        </form>
    </div>

    <?php if ($show_all_projects) { ?>
        <div class="project-header">
            <div class="project-header-content">
                <div>
                    <h1 class="project-title">
                        <i class="fas fa-layer-group"></i>
                        عرض جميع المشاريع
                    </h1>
                </div>
            </div>
        </div>
    <?php } elseif (!empty($selected_project)) { ?>
        <!-- عنوان المشروع المحدد -->
        <div class="project-header">
            <div class="project-header-content">
                <div>
                    <h1 class="project-title">
                        <i class="fas fa-hard-hat"></i>
                        <?php echo htmlspecialchars($selected_project['name']); ?>
                    </h1>
                    <?php if (!empty($selected_project['project_code'])) { ?>
                        <p class="project-code-display">
                            <i class="fas fa-barcode"></i>
                            كود المشروع: <?php echo htmlspecialchars($selected_project['project_code']); ?>
                        </p>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <strong>يرجى اختيار مشروع لعرض البيانات.</strong>
        </div>
    <?php } ?>
    
    <h2>
        <i class="fas fa-cogs"></i>
        إدارة الآليات والمعدات
    </h2>

    <?php if (!empty($success_msg)): ?>
        <div class="<?php echo (strpos($success_msg, '⚠️') !== false || strpos($success_msg, 'تحذير') !== false) ? 'warning-message' : (strpos($success_msg, 'خطأ') !== false ? 'error-message' : 'success-message'); ?>">
            <i class="fas fa-<?php echo (strpos($success_msg, '⚠️') !== false || strpos($success_msg, 'تحذير') !== false) ? 'exclamation-triangle' : (strpos($success_msg, 'خطأ') !== false ? 'times-circle' : 'check-circle'); ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($selected_project) && !$show_all_projects) { ?>
        </div>
        <script>
            document.getElementById('selected_project_id').addEventListener('change', function () {
                if (this.value) {
                    document.getElementById('projectSelectForm').submit();
                }
            });
        </script>
        </body>
        </html>
        <?php exit; ?>
    <?php } ?>

    <!-- قسم الإحصائيات -->
    <div id="contractStats" class="contract-stats">
        <h5 class="stats-title">
            <i class="fas fa-chart-line"></i>
            إحصائيات عقد المنجم
        </h5>
        
        <!-- جدول الموردين -->
        <div id="suppliersSection" style="display: none;">
            <h6 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">
                <i class="fas fa-users"></i> عقود الموردين في هذا المشروع
            </h6>
            <div style="overflow-x: auto;">
                <table class="suppliers-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المورد</th>
                            <th>الساعات المتعاقد عليها</th>
                            <th>عدد المعدات المتعاقد عليها</th>
                            <th>توزيع المعدات والساعات</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #6c757d; padding: 2rem;">
                                <i class="fas fa-info-circle"></i> لا توجد بيانات
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: linear-gradient(135deg, #e2ae03 0%, #debf0f 100%); font-weight: bold; color: #01072a;">
                            <td colspan="2" style="text-align: right; padding: 12px;">الإجمالي</td>
                            <td id="total_supplier_hours" style="text-align: center;">0</td>
                            <td id="total_supplier_equipment" style="text-align: center;">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="stats-grid" style="margin-top: 2rem;">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-value" id="stat_total_hours">0</div>
                <div class="stat-card-label">إجمالي الساعات المتعاقد عليها</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-card-value" id="stat_equipment_count">0</div>
                <div class="stat-card-label">عدد المعدات المسجلة</div>
            </div>
        </div>
    </div>

    <?php if($_SESSION['user']['role'] == "4"){?>
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> <?php echo !empty($editData) ? "تعديل معدة" : "إضافة معدة جديدة"; ?>
        </a>
    </div>
    <?php } ?>
    <!-- فورم إضافة / تعديل معدة -->
    <form id="projectForm" action="" method="post" style="display:<?php echo !empty($editData) ? 'block' : 'none'; ?>;">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-<?php echo !empty($editData) ? 'edit' : 'plus-circle'; ?>"></i>
                    <?php echo !empty($editData) ? "تعديل الآلية" : "إضافة آلية جديدة"; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <?php if (!empty($editData)) { ?>
                        <input type="hidden" name="edit_id" value="<?php echo isset($editData['id']) ? $editData['id'] : ''; ?>">
                    <?php } ?>
                    
                    <!-- المشروع مخفي لأنه محدد مسبقاً -->
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $selected_project_id; ?>">
                    
                    <div>
                        <label>
                            <i class="fas fa-mountain"></i>
                            المنجم <span class="required-indicator">*</span>
                        </label>
                        <select name="mine_id" id="mine_id" >
                            <option value="">-- اختر المنجم --</option>
                            <?php
                            $mines_query = "SELECT id, mine_name FROM mines WHERE project_id = $selected_project_id AND status='1' ORDER BY mine_name";
                            $mines_result = mysqli_query($conn, $mines_query);
                            while ($mine = mysqli_fetch_assoc($mines_result)) {
                                $selected = (!empty($editData) && $editData['mine_id'] == $mine['id']) ? "selected" : "";
                                echo "<option value='" . $mine['id'] . "' $selected>" . htmlspecialchars($mine['mine_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="dropdown-hint">اختر المنجم أولاً</span>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-file-signature"></i>
                            عقد المشروع <span class="required-indicator">*</span>
                        </label>
                        <select name="contract_id" id="contract_id"  disabled>
                            <option value="">-- اختر العقد --</option>
                        </select>
                        <span class="dropdown-hint">يتم تحميله بعد اختيار المنجم</span>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-truck-loading"></i>
                            المورد <span class="required-indicator">*</span>
                        </label>
                        <select name="suppliers" id="suppliers" required>
                            <option value="">-- اختر المورد --</option>
                            <?php
                            $supplier_query = "SELECT id, name FROM suppliers WHERE status = 1 ORDER BY name";
                            $supplier_result = mysqli_query($conn, $supplier_query);
                            while($supplier = mysqli_fetch_assoc($supplier_result)) {
                                $selected = (!empty($editData) && $editData['suppliers'] == $supplier['id']) ? 'selected' : '';
                                echo "<option value='{$supplier['id']}' $selected>{$supplier['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-barcode"></i>
                            كود المعدة <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="code" id="code" placeholder="أدخل كود المعدة" 
                               value="<?php echo isset($editData['code']) ? htmlspecialchars($editData['code']) : ''; ?>" required />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-list-alt"></i>
                            نوع المعدة <span class="required-indicator">*</span>
                        </label>
                        <select name="type" id="type" required>
                            <option value="">-- حدد نوع المعدة --</option>
                            <?php
                            $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                            $type_result = mysqli_query($conn, $type_query);
                            if ($type_result) {
                                while($type_row = mysqli_fetch_assoc($type_result)) {
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
                            اسم المعدة <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="name" id="name" placeholder="أدخل اسم المعدة" 
                               value="<?php echo isset($editData['name']) ? htmlspecialchars($editData['name']) : ''; ?>" required />
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: المعلومات الأساسية والتعريفية -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-id-card"></i> المعلومات الأساسية والتعريفية</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-hashtag"></i>
                            رقم المعدة/الرقم التسلسلي
                        </label>
                        <input type="text" name="serial_number" id="serial_number" placeholder="مثال: EXC-2024-001" 
                               value="<?php echo isset($editData['serial_number']) ? htmlspecialchars($editData['serial_number']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-barcode"></i>
                            رقم الهيكل/الهيكل الأساسي (VIN/Chassis)
                        </label>
                        <input type="text" name="chassis_number" id="chassis_number" placeholder="مثال: CAT320-ABC123456" 
                               value="<?php echo isset($editData['chassis_number']) ? htmlspecialchars($editData['chassis_number']) : ''; ?>" />
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: بيانات الصنع والموديل -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-industry"></i> بيانات الصنع والموديل</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-building"></i>
                            الماركة/الشركة المصنعة
                        </label>
                        <input type="text" name="manufacturer" id="manufacturer" placeholder="مثال: كاتربيلر، كوماتسو، هيونداي" 
                               value="<?php echo isset($editData['manufacturer']) ? htmlspecialchars($editData['manufacturer']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-car"></i>
                            الموديل/الطراز
                        </label>
                        <input type="text" name="model" id="model" placeholder="مثال: 320D, PC200, HD1024" 
                               value="<?php echo isset($editData['model']) ? htmlspecialchars($editData['model']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar"></i>
                            سنة الصنع
                        </label>
                        <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="مثال: 2018" min="1950" max="2099" 
                               value="<?php echo isset($editData['manufacturing_year']) ? $editData['manufacturing_year'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar-plus"></i>
                            سنة الاستيراد/البدء
                        </label>
                        <input type="number" name="import_year" id="import_year" placeholder="مثال: 2020" min="1950" max="2099" 
                               value="<?php echo isset($editData['import_year']) ? $editData['import_year'] : ''; ?>" />
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: الحالة الفنية والمواصفات -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-wrench"></i> الحالة الفنية والمواصفات</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-cogs"></i>
                            حالة المعدة
                        </label>
                        <select name="equipment_condition" id="equipment_condition">
                            <option value="جديدة (لم تستخدم)" <?php echo (!empty($editData) && $editData['equipment_condition']=="جديدة (لم تستخدم)") ? "selected" : ""; ?>>جديدة (لم تستخدم)</option>
                            <option value="جديدة نسبياً (أقل من سنة استخدام)" <?php echo (!empty($editData) && $editData['equipment_condition']=="جديدة نسبياً (أقل من سنة استخدام)") ? "selected" : ""; ?>>جديدة نسبياً (أقل من سنة استخدام)</option>
                            <option value="في حالة جيدة" <?php echo (empty($editData) || $editData['equipment_condition']=="في حالة جيدة") ? "selected" : ""; ?>>في حالة جيدة</option>
                            <option value="في حالة متوسطة" <?php echo (!empty($editData) && $editData['equipment_condition']=="في حالة متوسطة") ? "selected" : ""; ?>>في حالة متوسطة</option>
                            <option value="في حالة ضعيفة" <?php echo (!empty($editData) && $editData['equipment_condition']=="في حالة ضعيفة") ? "selected" : ""; ?>>في حالة ضعيفة</option>
                            <option value="محتاجة إصلاح فوري" <?php echo (!empty($editData) && $editData['equipment_condition']=="محتاجة إصلاح فوري") ? "selected" : ""; ?>>محتاجة إصلاح فوري</option>
                            <option value="معطلة مؤقتاً" <?php echo (!empty($editData) && $editData['equipment_condition']=="معطلة مؤقتاً") ? "selected" : ""; ?>>معطلة مؤقتاً</option>
                            <option value="مستعملة بكثافة" <?php echo (!empty($editData) && $editData['equipment_condition']=="مستعملة بكثافة") ? "selected" : ""; ?>>مستعملة بكثافة</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-clock"></i>
                            ساعات التشغيل (للمعدات الثقيلة)
                        </label>
                        <input type="number" name="operating_hours" id="operating_hours" placeholder="مثال: 5400 ساعة" min="0" 
                               value="<?php echo isset($editData['operating_hours']) ? $editData['operating_hours'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-car-crash"></i>
                            حالة المحرك
                        </label>
                        <select name="engine_condition" id="engine_condition">
                            <option value="ممتازة" <?php echo (!empty($editData) && $editData['engine_condition']=="ممتازة") ? "selected" : ""; ?>>ممتازة</option>
                            <option value="جيدة" <?php echo (empty($editData) || $editData['engine_condition']=="جيدة") ? "selected" : ""; ?>>جيدة</option>
                            <option value="متوسطة" <?php echo (!empty($editData) && $editData['engine_condition']=="متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                            <option value="محتاجة صيانة" <?php echo (!empty($editData) && $editData['engine_condition']=="محتاجة صيانة") ? "selected" : ""; ?>>محتاجة صيانة</option>
                            <option value="محتاجة إصلاح" <?php echo (!empty($editData) && $editData['engine_condition']=="محتاجة إصلاح") ? "selected" : ""; ?>>محتاجة إصلاح</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-circle-notch"></i>
                            حالة الإطارات (للشاحنات)
                        </label>
                        <select name="tires_condition" id="tires_condition">
                            <option value="N/A" <?php echo (empty($editData) || $editData['tires_condition']=="N/A") ? "selected" : ""; ?>>N/A</option>
                            <option value="جديدة" <?php echo (!empty($editData) && $editData['tires_condition']=="جديدة") ? "selected" : ""; ?>>جديدة</option>
                            <option value="جيدة" <?php echo (!empty($editData) && $editData['tires_condition']=="جيدة") ? "selected" : ""; ?>>جيدة</option>
                            <option value="متوسطة" <?php echo (!empty($editData) && $editData['tires_condition']=="متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                            <option value="محتاجة تبديل" <?php echo (!empty($editData) && $editData['tires_condition']=="محتاجة تبديل") ? "selected" : ""; ?>>محتاجة تبديل</option>
                        </select>
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: بيانات الملكية -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-user-tie"></i> بيانات الملكية</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-user"></i>
                            اسم المالك الفعلي
                        </label>
                        <input type="text" name="actual_owner_name" id="actual_owner_name" placeholder="مثال: محمد علي أحمد" 
                               value="<?php echo isset($editData['actual_owner_name']) ? htmlspecialchars($editData['actual_owner_name']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-briefcase"></i>
                            نوع المالك
                        </label>
                        <select name="owner_type" id="owner_type">
                            <option value="">-- اختر نوع المالك --</option>
                            <option value="مالك فردي" <?php echo (!empty($editData) && $editData['owner_type']=="مالك فردي") ? "selected" : ""; ?>>مالك فردي</option>
                            <option value="شركة متخصصة" <?php echo (!empty($editData) && $editData['owner_type']=="شركة متخصصة") ? "selected" : ""; ?>>شركة متخصصة</option>
                            <option value="مؤسسة" <?php echo (!empty($editData) && $editData['owner_type']=="مؤسسة") ? "selected" : ""; ?>>مؤسسة</option>
                            <option value="أخرى" <?php echo (!empty($editData) && $editData['owner_type']=="أخرى") ? "selected" : ""; ?>>أخرى</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-phone"></i>
                            رقم هاتف المالك
                        </label>
                        <input type="text" name="owner_phone" id="owner_phone" placeholder="مثال: +249-9-123-4567" 
                               value="<?php echo isset($editData['owner_phone']) ? htmlspecialchars($editData['owner_phone']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-handshake"></i>
                            علاقة المالك بالمورد
                        </label>
                        <select name="owner_supplier_relation" id="owner_supplier_relation">
                            <option value="">-- اختر العلاقة --</option>
                            <option value="مالك مباشر (يتعاقد معنا مباشرة)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="مالك مباشر (يتعاقد معنا مباشرة)") ? "selected" : ""; ?>>مالك مباشر (يتعاقد معنا مباشرة)</option>
                            <option value="تحت وساطة المورد (المورد يدير المعدة نيابة عنه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="تحت وساطة المورد (المورد يدير المعدة نيابة عنه)") ? "selected" : ""; ?>>تحت وساطة المورد (المورد يدير المعدة نيابة عنه)</option>
                            <option value="تابع للمورد (مملوكة للمورد نفسه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="تابع للمورد (مملوكة للمورد نفسه)") ? "selected" : ""; ?>>تابع للمورد (مملوكة للمورد نفسه)</option>
                            <option value="غير محدد" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="غير محدد") ? "selected" : ""; ?>>غير محدد</option>
                        </select>
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: الوثائق والتسجيلات -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-file-contract"></i> الوثائق والتسجيلات</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-address-card"></i>
                            رقم الترخيص/التسجيل
                        </label>
                        <input type="text" name="license_number" id="license_number" placeholder="مثال: VEH-2024-12345" 
                               value="<?php echo isset($editData['license_number']) ? htmlspecialchars($editData['license_number']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-landmark"></i>
                            جهة الترخيص
                        </label>
                        <input type="text" name="license_authority" id="license_authority" placeholder="مثال: المرور، وزارة النقل" 
                               value="<?php echo isset($editData['license_authority']) ? htmlspecialchars($editData['license_authority']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar-times"></i>
                            تاريخ انتهاء الترخيص
                        </label>
                        <input type="date" name="license_expiry_date" id="license_expiry_date" 
                               value="<?php echo isset($editData['license_expiry_date']) ? $editData['license_expiry_date'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-certificate"></i>
                            رقم شهادة الفحص
                        </label>
                        <input type="text" name="inspection_certificate_number" id="inspection_certificate_number" placeholder="رقم شهادة الفحص الفنية" 
                               value="<?php echo isset($editData['inspection_certificate_number']) ? htmlspecialchars($editData['inspection_certificate_number']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar-check"></i>
                            تاريخ آخر فحص
                        </label>
                        <input type="date" name="last_inspection_date" id="last_inspection_date" 
                               value="<?php echo isset($editData['last_inspection_date']) ? $editData['last_inspection_date'] : ''; ?>" />
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: الموقع والتوفر -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-map-marker-alt"></i> الموقع والتوفر</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-location-arrow"></i>
                            الموقع الحالي
                        </label>
                        <input type="text" name="current_location" id="current_location" placeholder="مثال: منجم الذهب الشرقي، مستودع الخرطوم" 
                               value="<?php echo isset($editData['current_location']) ? htmlspecialchars($editData['current_location']) : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-traffic-light"></i>
                            حالة التوفر
                        </label>
                        <select name="availability_status" id="availability_status">
                            <option value="متاحة للعمل" <?php echo (empty($editData) || $editData['availability_status']=="متاحة للعمل") ? "selected" : ""; ?>>متاحة للعمل</option>
                            <option value="قيد الاستخدام" <?php echo (!empty($editData) && $editData['availability_status']=="قيد الاستخدام") ? "selected" : ""; ?>>قيد الاستخدام</option>
                            <option value="تحت الصيانة" <?php echo (!empty($editData) && $editData['availability_status']=="تحت الصيانة") ? "selected" : ""; ?>>تحت الصيانة</option>
                            <option value="محجوزة" <?php echo (!empty($editData) && $editData['availability_status']=="محجوزة") ? "selected" : ""; ?>>محجوزة</option>
                            <option value="معطلة" <?php echo (!empty($editData) && $editData['availability_status']=="معطلة") ? "selected" : ""; ?>>معطلة</option>
                            <option value="في المستودع" <?php echo (!empty($editData) && $editData['availability_status']=="في المستودع") ? "selected" : ""; ?>>في المستودع</option>
                            <option value="مبيعة/مسحوبة" <?php echo (!empty($editData) && $editData['availability_status']=="مبيعة/مسحوبة") ? "selected" : ""; ?>>مبيعة/مسحوبة</option>
                        </select>
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: البيانات المالية والقيمة -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-dollar-sign"></i> البيانات المالية والقيمة</h6>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-money-bill-wave"></i>
                            القيمة المقدرة للمعدة (بالدولار)
                        </label>
                        <input type="number" name="estimated_value" id="estimated_value" placeholder="مثال: 150000" min="0" step="0.01" 
                               value="<?php echo isset($editData['estimated_value']) ? $editData['estimated_value'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar-day"></i>
                            سعر التأجير اليومي (بالدولار)
                        </label>
                        <input type="number" name="daily_rental_price" id="daily_rental_price" placeholder="مثال: 500" min="0" step="0.01" 
                               value="<?php echo isset($editData['daily_rental_price']) ? $editData['daily_rental_price'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            سعر التأجير الشهري (بالدولار)
                        </label>
                        <input type="number" name="monthly_rental_price" id="monthly_rental_price" placeholder="مثال: 10000" min="0" step="0.01" 
                               value="<?php echo isset($editData['monthly_rental_price']) ? $editData['monthly_rental_price'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-shield-alt"></i>
                            التأمين/الضمان
                        </label>
                        <select name="insurance_status" id="insurance_status">
                            <option value="">-- اختر حالة التأمين --</option>
                            <option value="مؤمن بالكامل" <?php echo (!empty($editData) && $editData['insurance_status']=="مؤمن بالكامل") ? "selected" : ""; ?>>مؤمن بالكامل</option>
                            <option value="مؤمن جزئياً" <?php echo (!empty($editData) && $editData['insurance_status']=="مؤمن جزئياً") ? "selected" : ""; ?>>مؤمن جزئياً</option>
                            <option value="غير مؤمن" <?php echo (!empty($editData) && $editData['insurance_status']=="غير مؤمن") ? "selected" : ""; ?>>غير مؤمن</option>
                            <option value="جاري التأمين" <?php echo (!empty($editData) && $editData['insurance_status']=="جاري التأمين") ? "selected" : ""; ?>>جاري التأمين</option>
                        </select>
                    </div>
                    
                    <!-- ================================= -->
                    <!-- قسم: ملاحظات وسجل الصيانة -->
                    <!-- ================================= -->
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 10px 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="margin: 0; font-weight: 700;"><i class="fas fa-tools"></i> ملاحظات وسجل الصيانة</h6>
                    </div>
                    
                    <div style="grid-column: 1 / -1;">
                        <label>
                            <i class="fas fa-comment-alt"></i>
                            ملاحظات عامة
                        </label>
                        <textarea name="general_notes" id="general_notes" rows="3" placeholder="مثال: معدة موثوقة، تحتاج إلى صيانة دورية كل 3 أشهر"><?php echo isset($editData['general_notes']) ? htmlspecialchars($editData['general_notes']) : ''; ?></textarea>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-wrench"></i>
                            تاريخ آخر صيانة
                        </label>
                        <input type="date" name="last_maintenance_date" id="last_maintenance_date" 
                               value="<?php echo isset($editData['last_maintenance_date']) ? $editData['last_maintenance_date'] : ''; ?>" />
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-toggle-on"></i>
                            الحالة <span class="required-indicator">*</span>
                        </label>
                        <select name="status" id="status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1" <?php echo (!empty($editData) && $editData['status']=="1") ? "selected" : ""; ?>>متاحة</option>
                            <option value="0" <?php echo (!empty($editData) && $editData['status']=="0") ? "selected" : ""; ?>>مشغولة</option>
                        </select>
                    </div>
                    
                    <button type="submit" style="grid-column: 1 / -1;">
                        <i class="fas fa-save"></i>
                        <?php echo !empty($editData) ? "تحديث المعدة" : "حفظ المعدة"; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المعدات -->
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i>
                قائمة المعدات
            </h5>
        </div>
        <div class="card-body">
            <!-- أزرار إظهار/إخفاء المجموعات -->
            <div class="column-groups-toggle" style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; border: 2px solid var(--border-color);">
                <button type="button" class="toggle-group-btn active" data-group="basic" title="المعلومات الأساسية">
                    <i class="fas fa-info-circle"></i> أساسية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="identification" title="بيانات التعريف">
                    <i class="fas fa-id-card"></i> التعريف
                </button>
                <button type="button" class="toggle-group-btn" data-group="manufacturing" title="بيانات الصنع">
                    <i class="fas fa-industry"></i> الصنع
                </button>
                <button type="button" class="toggle-group-btn" data-group="technical" title="الحالة الفنية">
                    <i class="fas fa-wrench"></i> فنية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="ownership" title="بيانات الملكية">
                    <i class="fas fa-user-tie"></i> الملكية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="status" title="الحالة والإجراءات">
                    <i class="fas fa-toggle-on"></i> الحالة
                </button>
                <button type="button" class="toggle-all-btn" title="إظهار/إخفاء الكل">
                    <i class="fas fa-eye"></i> الكل
                </button>
            </div>
            
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th data-group="basic"><i class="fas fa-hashtag"></i> #</th>
                        <th data-group="basic"><i class="fas fa-truck-loading"></i> المورد</th>
                        <th data-group="basic"><i class="fas fa-barcode"></i> كود المعدة</th>
                        <th data-group="identification"><i class="fas fa-hashtag"></i> رقم تسلسلي</th>
                        <th data-group="basic"><i class="fas fa-list-alt"></i> النوع</th>
                        <th data-group="basic"><i class="fas fa-tag"></i> الاسم</th>
                        <th data-group="manufacturing"><i class="fas fa-car"></i> الموديل</th>
                        <th data-group="manufacturing"><i class="fas fa-calendar"></i> سنة الصنع</th>
                        <th data-group="technical"><i class="fas fa-cogs"></i> حالة المعدة</th>
                        <th data-group="ownership"><i class="fas fa-user"></i> المالك</th>
                        <th data-group="technical"><i class="fas fa-traffic-light"></i> التوفر</th>
                        <th data-group="status"><i class="fas fa-toggle-on"></i> الحالة</th>
                        <th data-group="status"><i class="fas fa-sliders-h"></i> إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $project_filter_where = '';
                    if ($selected_project_id > 0) {
                        $project_filter_where = "WHERE o.project_id = $selected_project_id";
                    }

                    $query2 = "
                        SELECT 
                            m.id, 
                            s.name AS supplier_name, 
                            m.type, 
                            m.code, 
                            m.name, 
                            m.status,
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
                        $project_filter_where
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                    $result = mysqli_query($conn, $query2);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><strong style='color:var(--primary-color)'>" . htmlspecialchars($row['supplier_name']) . "</strong></td>";
                        echo "<td><span style='font-family: monospace; font-weight: 600;'>" . htmlspecialchars($row['code']) . "</span></td>";
                        
                        // رقم تسلسلي
                        $serial = !empty($row['serial_number']) ? htmlspecialchars($row['serial_number']) : "<span style='color: #999;'>غير محدد</span>";
                        echo "<td><span style='font-family: monospace;'>" . $serial . "</span></td>";

                        // نوع المعدة
                        $type_icon = $row['type'] == "1" ? "fa-tractor" : "fa-truck-moving";
                        $type_text = $row['type'] == "1" ? "حفار" : "قلاب";
                        echo "<td><span class='badge-type'><i class='fas $type_icon'></i> $type_text</span></td>";

                        // اسم المعدة
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        
                        // المشروع النشط
                        if (!empty($row['project'])) {
                            $p_res = mysqli_query($conn, "SELECT name FROM project WHERE id='" . $row['project'] . "'");
                            if ($p_res && mysqli_num_rows($p_res) > 0) {
                                $p = mysqli_fetch_assoc($p_res);
                                $name_display .= "<br><span class='project-link'><i class='fas fa-project-diagram'></i> " . htmlspecialchars($p['name']) . "</span>";
                            }
                        }

                        // عدد السائقين النشطين
                        if ($row['drivers_count'] > 0) {
                            $name_display .= "<br><span class='extra-info'><i class='fas fa-users'></i> " . $row['drivers_count'] . " سائق</span>";
                        }

                        echo "<td>" . $name_display . "</td>";

                        // الحالة
                        if (!empty($row['project_id']) && $row['operation_status'] == "1") {
                            echo "<td><span class='badge-working'><i class='fas fa-spinner fa-spin'></i> قيد التشغيل</span></td>";
                        } else {
                            if ($row['status'] == "1") {
                                echo "<td><span class='badge-available'><i class='fas fa-check-circle'></i> متاحة</span></td>";
                            } else {
                                echo "<td><span class='badge-busy'><i class='fas fa-times-circle'></i> مشغولة</span></td>";
                            }
                        }

                        // الإجراءات
                        echo "<td>";
                        if ($_SESSION['user']['role'] == "3") {
                                                        echo "<a href='add_drivers.php?equipment_id=" . $row['id'] . "' class='action-btn btn-driver' title='إدارة المشغلين'>
                                    <i class='fas fa-user-cog'></i>
                                  </a>";
                        } else {
                                                        echo "<a href='equipments.php?edit=" . $row['id'] . "' class='action-btn btn-edit' title='تعديل'>
                                    <i class='fas fa-edit'></i>
                                  </a>";
                            // يمكن إضافة زر حذف هنا إذا لزم الأمر
                        }
                        echo "</td>";

                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Equipment Details Modal -->
<div id="equipmentModal" class="equipment-modal">
    <div class="equipment-modal-content">
        <div class="equipment-modal-header">
            <h3><i class="fas fa-tractor"></i> تفاصيل المعدة</h3>
            <span class="equipment-modal-close">&times;</span>
        </div>
        <div class="equipment-modal-body" id="equipmentModalBody">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--secondary-color);"></i>
                <p style="margin-top: 20px; color: #6c757d;">جاري تحميل البيانات...</p>
            </div>
        </div>
    </div>
</div>

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
        $(document).ready(function () {
            var table = $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
            
            // نظام إظهار/إخفاء المجموعات
            var columnGroups = {
                'basic': [0, 1, 2, 4, 5],        // #، المورد، كود المعدة، النوع، الاسم
                'identification': [3],            // رقم تسلسلي
                'manufacturing': [6, 7],          // الموديل، سنة الصنع
                'technical': [8, 10],             // حالة المعدة، التوفر
                'ownership': [9],                 // المالك
                'status': [11, 12]                // الحالة، الإجراءات
            };
            
            // حفظ حالة المجموعات (الصنع والفنية مخفيتين بشكل افتراضي)
            var groupsState = {
                'basic': true,
                'identification': true,
                'manufacturing': false,
                'technical': false,
                'ownership': true,
                'status': true
            };
            
            // إخفاء الأعمدة المخفية بشكل افتراضي عند التحميل
            columnGroups['manufacturing'].forEach(function(colIndex) {
                table.column(colIndex).visible(false);
            });
            columnGroups['technical'].forEach(function(colIndex) {
                table.column(colIndex).visible(false);
            });
            
            // وظيفة إظهار/إخفاء مجموعة
            function toggleGroup(groupName) {
                var columns = columnGroups[groupName];
                var isVisible = groupsState[groupName];
                
                columns.forEach(function(colIndex) {
                    table.column(colIndex).visible(!isVisible);
                });
                
                groupsState[groupName] = !isVisible;
            }
            
            // معالج النقر على أزرار المجموعات
            $('.toggle-group-btn').on('click', function() {
                var groupName = $(this).data('group');
                toggleGroup(groupName);
                $(this).toggleClass('active');
            });
            
            // زر إظهار/إخفاء الكل
            var allVisible = true;
            $('.toggle-all-btn').on('click', function() {
                allVisible = !allVisible;
                
                Object.keys(columnGroups).forEach(function(groupName) {
                    var columns = columnGroups[groupName];
                    columns.forEach(function(colIndex) {
                        table.column(colIndex).visible(allVisible);
                    });
                    groupsState[groupName] = allVisible;
                });
                
                if (allVisible) {
                    $('.toggle-group-btn').addClass('active');
                    $(this).html('<i class="fas fa-eye"></i> الكل');
                } else {
                    $('.toggle-group-btn').removeClass('active');
                    $(this).html('<i class="fas fa-eye-slash"></i> إخفاء الكل');
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
        
        // تحميل بيانات التعديل عند تحميل الصفحة
        <?php if (!empty($editData)) { ?>
        $(document).ready(function() {
            // عرض الفورم
            $('#projectForm').show();
            
            // تحميل المنجم والعقد
            const mineId = '<?php echo isset($editData['mine_id']) ? $editData['mine_id'] : ''; ?>';
            const contractId = '<?php echo isset($editData['contract_id']) ? $editData['contract_id'] : ''; ?>';
            
            if (mineId) {
                $('#mine_id').val(mineId);
                
                // تحميل العقود للمنجم المحدد
                $.ajax({
                    url: 'get_mine_contracts.php',
                    type: 'GET',
                    data: { mine_id: mineId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.contracts.length > 0) {
                            $('#contract_id').html('<option value="">-- اختر العقد --</option>');
                            response.contracts.forEach(function(contract) {
                                $('#contract_id').append(`<option value="${contract.id}">${contract.name}</option>`);
                            });
                            $('#contract_id').prop('disabled', false);
                            
                            // تحديد العقد المحفوظ
                            if (contractId) {
                                $('#contract_id').val(contractId);
                                $('#contract_id').trigger('change');
                            }
                        }
                    }
                });
            }
            
            // التمرير للفورم
            $('html, body').animate({
                scrollTop: $('#projectForm').offset().top - 100
            }, 500);
        });
        <?php } ?>
        
        // Cascading Dropdowns (للمنجم والعقد فقط)
        $('#mine_id').on('change', function() {
            const mineId = $(this).val();
            const contractSelect = $('#contract_id');
            
            contractSelect.html('<option value="">-- اختر العقد --</option>').prop('disabled', true).removeClass('loading');
            $('#contractStats').hide();
            $('#suppliersSection').hide();
            
            if (!mineId) return;
            
            // إضافة حالة loading
            contractSelect.addClass('loading').html('<option value="">جاري التحميل...</option>');
            
            // جلب العقود
            $.ajax({
                url: 'get_mine_contracts.php',
                type: 'GET',
                data: { mine_id: mineId },
                dataType: 'json',
                success: function(response) {
                    contractSelect.removeClass('loading');
                    if (response.success && response.contracts.length > 0) {
                        contractSelect.html('<option value="">-- اختر العقد --</option>');
                        response.contracts.forEach(function(contract) {
                            contractSelect.append(`<option value="${contract.id}">${contract.name}</option>`);
                        });
                        contractSelect.prop('disabled', false);
                    } else {
                        contractSelect.html('<option value="">⚠️ لا توجد عقود</option>');
                    }
                },
                error: function() {
                    contractSelect.removeClass('loading').html('<option value="">❌ خطأ في التحميل</option>');
                }
            });
        });
        
        $('#contract_id').on('change', function() {
            const contractId = $(this).val();
            
            $('#contractStats').hide();
            $('#suppliersSection').hide();
            
            if (!contractId) return;
            
            // إضافة حالة loading
            $('#contractStats').show().find('.stat-card-value').html('<i class="fas fa-spinner fa-spin"></i>');
            
            // جلب إحصائيات العقد
            $.ajax({
                url: 'get_contract_stats.php',
                type: 'GET',
                data: { contract_id: contractId },
                dataType: 'json',
                success: function(response) {
                    console.log('Contract Stats Response:', response);
                    
                    if (response.success) {
                        // تحديث جدول الموردين
                        const tbody = $('#suppliersTableBody');
                        tbody.empty();
                        
                        if (response.suppliers && response.suppliers.length > 0) {
                            response.suppliers.forEach(function(supplier, index) {
                                // بناء نص التوزيع
                                let breakdownHtml = '';
                                if (supplier.equipment_breakdown && supplier.equipment_breakdown.length > 0) {
                                    const breakdownList = supplier.equipment_breakdown.map(item => {
                                        const operatingColor = item.operating_count > 0 ? '#28a745' : '#6c757d';
                                        const remainingColor = item.remaining_count > 0 ? '#ffc107' : '#6c757d';
                                        return `<div style="margin: 3px 0; padding: 8px; background: rgba(226, 174, 3, 0.1); border-right: 3px solid #e2ae03; border-radius: 4px;">
                                                    <i class="fas fa-tools" style="color: #e2ae03;"></i> 
                                                    <strong>${item.type || 'غير محدد'}</strong>: 
                                                    المتعاقد ${item.count} | 
                                                    <span style="color: ${operatingColor}; font-weight: bold;">المشغّل ${item.operating_count || 0}</span> | 
                                                    <span style="color: ${remainingColor}; font-weight: bold;">المتبقي ${item.remaining_count || 0}</span> | 
                                                    <i class="fas fa-clock"></i> ${parseFloat(item.hours).toLocaleString()} ساعة
                                                </div>`;
                                    }).join('');
                                    breakdownHtml = breakdownList;
                                } else {
                                    breakdownHtml = '<span style="color: #6c757d;">لا توجد تفاصيل</span>';
                                }
                                
                                const row = `
                                    <tr>
                                        <td style="text-align: center;">${index + 1}</td>
                                        <td><strong>${supplier.supplier_name}</strong></td>
                                        <td style="text-align: center;">${parseFloat(supplier.hours).toLocaleString()}</td>
                                        <td style="text-align: center;">${supplier.equipment_count}</td>
                                        <td style="text-align: right; font-size: 0.9rem;">${breakdownHtml}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });
                            
                            // تحديث الإجماليات
                            $('#total_supplier_hours').text(parseFloat(response.summary.total_supplier_hours).toLocaleString());
                            $('#total_supplier_equipment').text(response.summary.total_supplier_equipment);
                            
                            $('#suppliersSection').fadeIn();
                        } else {
                            tbody.html(`
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #6c757d; padding: 2rem;">
                                        <i class="fas fa-info-circle"></i> لا توجد عقود موردين لهذا المشروع
                                    </td>
                                </tr>
                            `);
                            $('#total_supplier_hours').text('0');
                            $('#total_supplier_equipment').text('0');
                            $('#total_added_equipment').text('0');
                            $('#total_remaining_equipment').text('0');
                            $('#suppliersSection').fadeIn();
                        }
                        
                        // تحديث الإحصائيات الأساسية
                        $('#stat_total_hours').text(parseFloat(response.contract.total_hours).toLocaleString());
                        $('#stat_equipment_count').text(response.contract.equipment_count || '0');
                        
                        $('#contractStats').fadeIn();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    console.error('Response Text:', xhr.responseText);
                    $('#contractStats').hide();
                    alert('خطأ: ' + (xhr.responseText || error));
                }
            });
        });
        
        // Equipment Details Modal
        var modal = document.getElementById('equipmentModal');
        var modalBody = document.getElementById('equipmentModalBody');
        var closeBtn = document.getElementsByClassName('equipment-modal-close')[0];
        
        // فتح الـ modal عند النقر على زر العرض
        $(document).on('click', '.view-equipment-btn', function() {
            var equipmentId = $(this).data('id');
            modal.style.display = 'block';
            
            // جلب بيانات المعدة
            $.ajax({
                url: 'get_equipment_details.php',
                type: 'GET',
                data: { id: equipmentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayEquipmentDetails(response.data);
                    } else {
                        modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 3rem;"></i><p style="margin-top: 20px;">حدث خطأ في تحميل البيانات</p></div>';
                    }
                },
                error: function() {
                    modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 3rem;"></i><p style="margin-top: 20px;">فشل الاتصال بالخادم</p></div>';
                }
            });
        });
        
        // إغلاق الـ modal
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // وظيفة عرض تفاصيل المعدة
        function displayEquipmentDetails(data) {
            var html = '';
            
            // المعلومات الأساسية (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-info-circle"></i> المعلومات الأساسية</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('المورد', data.supplier_name);
            html += createDetailItem('كود المعدة', data.code);
            html += createDetailItem('نوع المعدة', data.type == '1' ? 'حفار' : 'قلاب');
            html += createDetailItem('اسم المعدة', data.name);
            html += createDetailItem('الحالة', data.status == '1' ? '✅ متاحة' : '❌ مشغولة');
            html += '</div></div>';
            
            // المعلومات التعريفية (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-id-card"></i> المعلومات التعريفية</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('رقم المعدة/الرقم التسلسلي', data.serial_number);
            html += createDetailItem('رقم الهيكل', data.chassis_number);
            html += '</div></div>';
            
            // بيانات الصنع والموديل (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-industry"></i> بيانات الصنع والموديل</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('الشركة المصنعة', data.manufacturer);
            html += createDetailItem('الموديل/الطراز', data.model);
            html += createDetailItem('سنة الصنع', data.manufacturing_year);
            html += createDetailItem('سنة الاستيراد/البدء', data.import_year);
            html += '</div></div>';
            
            // الحالة الفنية والمواصفات (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-wrench"></i> الحالة الفنية والمواصفات</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('حالة المعدة', data.equipment_condition);
            html += createDetailItem('ساعات التشغيل', data.operating_hours ? data.operating_hours + ' ساعة' : null);
            html += createDetailItem('حالة المحرك', data.engine_condition);
            html += createDetailItem('حالة الإطارات', data.tires_condition);
            html += '</div></div>';
            
            // بيانات الملكية (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-user-tie"></i> بيانات الملكية</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('اسم المالك الفعلي', data.actual_owner_name);
            html += createDetailItem('نوع المالك', data.owner_type);
            html += createDetailItem('رقم هاتف المالك', data.owner_phone);
            html += createDetailItem('علاقة المالك بالمورد', data.owner_supplier_relation);
            html += '</div></div>';
            
            // الوثائق والتسجيلات (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-file-contract"></i> الوثائق والتسجيلات</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('رقم الترخيص', data.license_number);
            html += createDetailItem('جهة الترخيص', data.license_authority);
            html += createDetailItem('تاريخ انتهاء الترخيص', data.license_expiry_date);
            html += createDetailItem('رقم شهادة الفحص', data.inspection_certificate_number);
            html += createDetailItem('تاريخ آخر فحص', data.last_inspection_date);
            html += '</div></div>';
            
            // الموقع والتوفر (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-map-marker-alt"></i> الموقع والتوفر</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('الموقع الحالي', data.current_location);
            html += createDetailItem('حالة التوفر', data.availability_status);
            html += '</div></div>';
            
            // البيانات المالية (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-dollar-sign"></i> البيانات المالية والقيمة</h4>';
            html += '<div class="equipment-details-grid">';
            html += createDetailItem('القيمة المقدرة', data.estimated_value ? '$' + parseFloat(data.estimated_value).toLocaleString() : null);
            html += createDetailItem('سعر التأجير اليومي', data.daily_rental_price ? '$' + parseFloat(data.daily_rental_price).toLocaleString() : null);
            html += createDetailItem('سعر التأجير الشهري', data.monthly_rental_price ? '$' + parseFloat(data.monthly_rental_price).toLocaleString() : null);
            html += createDetailItem('التأمين/الضمان', data.insurance_status);
            html += '</div></div>';
            
            // ملاحظات وسجل الصيانة (دائماً تظهر)
            html += '<div class="equipment-details-section">';
            html += '<h4><i class="fas fa-tools"></i> ملاحظات وسجل الصيانة</h4>';
            html += '<div class="equipment-details-grid">';
            if (data.general_notes) {
                html += '<div class="equipment-detail-item" style="grid-column: 1 / -1;">';
                html += '<span class="equipment-detail-label">ملاحظات عامة</span>';
                html += '<div class="equipment-detail-value">' + data.general_notes + '</div>';
                html += '</div>';
            } else {
                html += '<div class="equipment-detail-item" style="grid-column: 1 / -1;">';
                html += '<span class="equipment-detail-label">ملاحظات عامة</span>';
                html += '<div class="equipment-detail-value empty">غير محدد</div>';
                html += '</div>';
            }
            html += createDetailItem('تاريخ آخر صيانة', data.last_maintenance_date);
            html += '</div></div>';
            
            modalBody.innerHTML = html;
        }
        
        function createDetailItem(label, value) {
            if (!value || value === '' || value === 'N/A') {
                return '<div class="equipment-detail-item"><span class="equipment-detail-label">' + label + '</span><div class="equipment-detail-value empty">غير محدد</div></div>';
            }
            return '<div class="equipment-detail-item"><span class="equipment-detail-label">' + label + '</span><div class="equipment-detail-value">' + value + '</div></div>';
        }
    })();
</script>

</body>
</html>
