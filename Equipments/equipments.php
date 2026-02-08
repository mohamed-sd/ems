<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

// التحقق من وجود معرف المشروع
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header("Location: select_project.php");
    exit();
}

$selected_project_id = intval($_GET['project_id']);

// التحقق من صحة المشروع
$project_check_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = '1'";
$project_check_result = mysqli_query($conn, $project_check_query);

if (!$project_check_result || mysqli_num_rows($project_check_result) == 0) {
    header("Location: select_project.php");
    exit();
}

$selected_project = mysqli_fetch_assoc($project_check_result);

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
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $mine_id = isset($_POST['mine_id']) ? intval($_POST['mine_id']) : null;
    $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : null;
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code      = mysqli_real_escape_string($conn, trim($_POST['code']));
    $type      = mysqli_real_escape_string($conn, $_POST['type']);
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status    = mysqli_real_escape_string($conn, $_POST['status']);
    $edit_id   = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    $project_id_sql = $project_id ? $project_id : 'NULL';
    $mine_id_sql = $mine_id ? $mine_id : 'NULL';
    $contract_id_sql = $contract_id ? $contract_id : 'NULL';

    // التحقق من عدم تجاوز العدد المتعاقد عليه (فقط عند الإضافة)
    if ($edit_id == 0 && $project_id && $suppliers && $type) {
        // الحصول على عدد المعدات المتعاقد عليها لهذا المورد ونوع المعدة
        $supplier_contract_query = "SELECT sc.id, sce.equip_count
                                   FROM supplierscontracts sc
                                   JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
                                   WHERE sc.supplier_id = $suppliers 
                                   AND sc.project_id = $project_id
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
                                 AND project_id = $project_id
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
                SET project_id=$project_id_sql, mine_id=$mine_id_sql, contract_id=$contract_id_sql, 
                    suppliers='$suppliers', code='$code', type='$type', name='$name', status='$status' 
                WHERE id='$edit_id'";
        $msg = "تم+تعديل+المعدة+بنجاح+✅";
    } else {
        // إضافة
        $sql = "INSERT INTO equipments 
                (project_id, mine_id, contract_id, suppliers, code, type, name, status) 
                VALUES 
                ($project_id_sql, $mine_id_sql, $contract_id_sql, 
                 '$suppliers', '$code', '$type', '$name', '$status')";
        $msg = "تمت+إضافة+المعدة+بنجاح+✅";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: equipments.php?project_id=$selected_project_id&msg=$msg");
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
    
    .main {
        padding: 2rem;
        background: var(--light-color);
        min-height: 100vh;
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
        .main {
            margin-right: 0;
            padding: 15px 10px;
        }
        
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
    }
</style>

<div class="main">
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
            <a href="select_project.php" class="btn-back-to-projects">
                <i class="fas fa-arrow-right"></i>
                العودة للمشاريع
            </a>
        </div>
    </div>
    
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
                            <th>المعدات المضافة</th>
                            <th>المتبقي للإضافة</th>
                            <th>توزيع المعدات والساعات</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; color: #6c757d; padding: 2rem;">
                                <i class="fas fa-info-circle"></i> لا توجد بيانات
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: linear-gradient(135deg, #e2ae03 0%, #debf0f 100%); font-weight: bold; color: #01072a;">
                            <td colspan="2" style="text-align: right; padding: 12px;">الإجمالي</td>
                            <td id="total_supplier_hours" style="text-align: center;">0</td>
                            <td id="total_supplier_equipment" style="text-align: center;">0</td>
                            <td id="total_added_equipment" style="text-align: center;">0</td>
                            <td id="total_remaining_equipment" style="text-align: center;">0</td>
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
                        <select name="mine_id" id="mine_id" required>
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
                        <select name="contract_id" id="contract_id" required disabled>
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
                            <option value="1" <?php echo (!empty($editData) && $editData['type']=="1") ? "selected" : ""; ?>>حفار</option>
                            <option value="2" <?php echo (!empty($editData) && $editData['type']=="2") ? "selected" : ""; ?>>قلاب</option>
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
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-truck-loading"></i> المورد</th>
                        <th><i class="fas fa-barcode"></i> كود المعدة</th>
                        <th><i class="fas fa-list-alt"></i> النوع</th>
                        <th><i class="fas fa-tag"></i> الاسم</th>
                        <th><i class="fas fa-toggle-on"></i> الحالة</th>
                        <th><i class="fas fa-sliders-h"></i> إجراءات</th>
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
                            m.name , 
                            m.status,
                            o.project, 
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
                        WHERE m.project_id = $selected_project_id
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

                        // نوع المعدة
                        $type_icon = $row['type'] == "1" ? "fa-tractor" : "fa-truck-moving";
                        $type_text = $row['type'] == "1" ? "حفار" : "قلاب";
                        echo "<td><span class='badge-type'><i class='fas $type_icon'></i> $type_text</span></td>";

                        // معلومات إضافية بجانب اسم المعدة
                        $name_display = "<strong>" . htmlspecialchars($row['name']) . "</strong>";
                        
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
                        if (!empty($row['project']) && $row['operation_status'] == "1") {
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
                            echo "<a href='add_drivers.php?equipment_id=" . $row['id'] . "&project_id=$selected_project_id' class='action-btn btn-driver' title='إدارة المشغلين'>
                                    <i class='fas fa-user-cog'></i>
                                  </a>";
                        } else {
                            echo "<a href='equipments.php?project_id=$selected_project_id&edit=" . $row['id'] . "' class='action-btn btn-edit' title='تعديل'>
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
            $('#projectsTable').DataTable({
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
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        const toggleFormBtn = document.getElementById('toggleForm');
        const equipmentForm = document.getElementById('projectForm');

        toggleFormBtn.addEventListener('click', function () {
            equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
        });
        
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
                            let totalAdded = 0;
                            let totalRemaining = 0;
                            
                            response.suppliers.forEach(function(supplier, index) {
                                // بناء نص التوزيع مع حالة الإضافة
                                let breakdownHtml = '';
                                if (supplier.equipment_breakdown && supplier.equipment_breakdown.length > 0) {
                                    const breakdownList = supplier.equipment_breakdown.map(item => {
                                        const addedCount = item.added_count || 0;
                                        const remaining = item.remaining || 0;
                                        let statusIcon = '';
                                        let statusColor = '';
                                        
                                        if (remaining === 0) {
                                            statusIcon = '<i class="fas fa-check-circle" style="color: #28a745;"></i>';
                                            statusColor = 'background: rgba(40, 167, 69, 0.1); border-right: 3px solid #28a745;';
                                        } else if (addedCount > 0) {
                                            statusIcon = '<i class="fas fa-exclamation-circle" style="color: #ffc107;"></i>';
                                            statusColor = 'background: rgba(255, 193, 7, 0.1); border-right: 3px solid #ffc107;';
                                        } else {
                                            statusIcon = '<i class="fas fa-times-circle" style="color: #dc3545;"></i>';
                                            statusColor = 'background: rgba(220, 53, 69, 0.1); border-right: 3px solid #dc3545;';
                                        }
                                        
                                        return `<div style="margin: 3px 0; padding: 8px; ${statusColor} border-radius: 4px;">
                                                    ${statusIcon}
                                                    <i class="fas fa-tools" style="color: #e2ae03;"></i> 
                                                    <strong>${item.type || 'غير محدد'}</strong>: 
                                                    ${item.count} متعاقد | 
                                                    <span style="color: #28a745; font-weight: bold;">${addedCount} مضاف</span> | 
                                                    <span style="color: #dc3545; font-weight: bold;">${remaining} متبقي</span> | 
                                                    <i class="fas fa-clock"></i> ${parseFloat(item.hours).toLocaleString()} ساعة
                                                </div>`;
                                    }).join('');
                                    breakdownHtml = breakdownList;
                                } else {
                                    breakdownHtml = '<span style="color: #6c757d;">لا توجد تفاصيل</span>';
                                }
                                
                                const addedEquipment = supplier.added_to_equipments || 0;
                                const remainingEquipment = supplier.remaining_to_add || 0;
                                totalAdded += addedEquipment;
                                totalRemaining += remainingEquipment;
                                
                                // تحديد لون حالة الإضافة
                                let addedBadgeClass = 'badge-available';
                                let remainingBadgeClass = 'badge-busy';
                                
                                if (remainingEquipment === 0) {
                                    addedBadgeClass = 'badge-available';
                                    remainingBadgeClass = 'badge-available';
                                } else if (addedEquipment > 0) {
                                    addedBadgeClass = 'badge-working';
                                    remainingBadgeClass = 'badge-working';
                                }
                                
                                const row = `
                                    <tr>
                                        <td style="text-align: center;">${index + 1}</td>
                                        <td><strong>${supplier.supplier_name}</strong></td>
                                        <td style="text-align: center;">${parseFloat(supplier.hours).toLocaleString()}</td>
                                        <td style="text-align: center;">${supplier.equipment_count}</td>
                                        <td style="text-align: center;">
                                            <span class="${addedBadgeClass}">
                                                <i class="fas fa-check"></i> ${addedEquipment}
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="${remainingBadgeClass}">
                                                <i class="fas fa-${remainingEquipment === 0 ? 'check-circle' : 'exclamation-triangle'}"></i> ${remainingEquipment}
                                            </span>
                                        </td>
                                        <td style="text-align: right; font-size: 0.9rem;">${breakdownHtml}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });
                            
                            // تحديث الإجماليات
                            $('#total_supplier_hours').text(parseFloat(response.summary.total_supplier_hours).toLocaleString());
                            $('#total_supplier_equipment').text(response.summary.total_supplier_equipment);
                            $('#total_added_equipment').text(totalAdded);
                            $('#total_remaining_equipment').text(totalRemaining);
                            
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
    })();
</script>

</body>
</html>
