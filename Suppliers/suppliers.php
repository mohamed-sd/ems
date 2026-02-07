<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // المعلومات الأساسية
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $supplier_code = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
    $dealing_nature = mysqli_real_escape_string($conn, $_POST['dealing_nature']);
    $equipment_types = isset($_POST['equipment_types']) ? implode(', ', $_POST['equipment_types']) : '';
    $equipment_types = mysqli_real_escape_string($conn, $equipment_types);
    
    // البيانات القانونية
    $commercial_registration = mysqli_real_escape_string($conn, trim($_POST['commercial_registration']));
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date']) : null;
    
    // البيانات التواصلية
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $full_address = mysqli_real_escape_string($conn, trim($_POST['full_address']));
    $contact_person_name = mysqli_real_escape_string($conn, trim($_POST['contact_person_name']));
    $contact_person_phone = mysqli_real_escape_string($conn, trim($_POST['contact_person_phone']));
    $financial_registration_status = mysqli_real_escape_string($conn, $_POST['financial_registration_status']);
    
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // تحديث
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
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
            WHERE id=$id";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=تم+تعديل+المورد+بنجاح+✅");
        exit;
    } else {
        // إضافة
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $sql = "INSERT INTO suppliers 
            (name, supplier_code, supplier_type, dealing_nature, equipment_types, 
             commercial_registration, identity_type, identity_number, identity_expiry_date,
             email, phone, phone_alternative, full_address, contact_person_name, 
             contact_person_phone, financial_registration_status, status) 
            VALUES 
            ('$name', '$supplier_code', '$supplier_type', '$dealing_nature', '$equipment_types',
             '$commercial_registration', '$identity_type', '$identity_number', $identity_expiry_sql,
             '$email', '$phone', '$phone_alternative', '$full_address', '$contact_person_name',
             '$contact_person_phone', '$financial_registration_status', '$status')";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=تمت+إضافة+المورد+بنجاح+✅");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | الموردين</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link rel="stylesheet" type="text/css" href="../assets/css/admin-style.css" />
    
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
        
        body {
            background: var(--light-color);
        }
        
        .main {
            padding: 2rem;
            background: var(--light-color);
            width: calc(100% - 250px);
        }

        @media (max-width: 768px) {
            .main {
                margin-right: 0;
                padding: 15px 10px;
            }
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
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
            background: var(--secondary-color);
            color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px var(--shadow-color);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-grid button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px var(--shadow-color);
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
            font-size: 15px;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px) scale(1.15);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: white !important;
        }
        
        .btn-contracts {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #b89302 100%);
            color: white !important;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.2);
            color: #155724;
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.2);
            color: #721c24;
        }
        
        /* Stats in Table */
        .stat-cell {
            background-color: var(--accent-color);
            padding: 4px 14px;
            border-radius: 30px;
            width: fit-content;
            font-weight: 800;
            color: var(--secondary-color);
            font-size: 1rem;
        }
        
        /* DataTables Buttons */
        .dt-buttons {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .dt-button {
            background: var(--secondary-color) !important;
            color: var(--primary-color) !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px var(--shadow-color) !important;
            font-family: 'Cairo', sans-serif !important;
        }
        
        .dt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px var(--shadow-color) !important;
        }

        .dt-button.active {
            background: var(--primary-color) !important;
            color: white !important;
        }

        /* Pagination */
        .dataTables_paginate .paginate_button {
            padding: 8px 12px;
            margin: 2px;
            border-radius: 6px;
            background: white;
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        /* Search Box */
        .dataTables_filter input {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .dataTables_filter input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(226, 174, 3, 0.15);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main {
                padding: 1rem;
            }

            .main h2 {
                font-size: 18px;
                margin-bottom: 1rem;
            }

            .aligin {
                padding: 1rem;
            }

            .aligin .add {
                width: 100%;
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1rem;
            }

            table.dataTable {
                font-size: 12px;
            }

            table.dataTable thead th {
                padding: 10px 5px;
            }

            table.dataTable tbody td {
                padding: 10px 5px;
            }

            .action-btn {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }
        }
        
        /* Form Sections */
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 2px solid var(--border-color);
        }
        
        .form-section h6 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 3px solid var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h6 i {
            color: var(--secondary-color);
            font-size: 1.2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: #dc3545;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(226, 174, 3, 0.15);
            outline: none;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Checkbox Grid */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 2px solid var(--border-color);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: var(--light-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .checkbox-label:hover {
            background: rgba(226, 174, 3, 0.1);
            border-color: var(--secondary-color);
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--secondary-color);
        }
        
        .checkbox-label span {
            font-weight: 500;
            color: var(--text-color);
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border-color);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 2px solid var(--border-color);
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 3px solid var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--secondary-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .btn-view:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
        }
    </style>
</head>
<body>
<?php include('../insidebar.php'); ?>

<div class="main">
    <h2><i class="fas fa-truck-loading"></i> إدارة الموردين</h2>
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> إضافة مورد جديد
        </a>
    </div>

    
    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-edit"></i> إضافة / تعديل مورد
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="supplier_id" value="">
                
                <!-- 1. المعلومات الأساسية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-info-circle"></i> المعلومات الأساسية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم المورد <span class="required">*</span></label>
                            <input type="text" name="name" id="supplier_name" required />
                        </div>
                        
                        <div class="form-group">
                            <label>الرمز/الكود للمورد</label>
                            <input type="text" name="supplier_code" id="supplier_code" />
                        </div>
                        
                        <div class="form-group">
                            <label>نوع المورد <span class="required">*</span></label>
                            <select name="supplier_type" id="supplier_type" required>
                                <option value="">-- اختر --</option>
                                <option value="فرد">فرد</option>
                                <option value="شركة">شركة</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مالك">مالك</option>
                                <option value="جهة حكومية">جهة حكومية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>طبيعة التعامل <span class="required">*</span></label>
                            <select name="dealing_nature" id="dealing_nature" required>
                                <option value="">-- اختر --</option>
                                <option value="متعاقد مباشر">متعاقد مباشر</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مورد معدات مباشر (مالك)">مورد معدات مباشر (مالك)</option>
                                <option value="وكيل توزيع">وكيل توزيع</option>
                                <option value="تاجر وسيط">تاجر وسيط</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>المعدات (يمكن اختيار أكثر من نوع)</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="حفارات">
                                <span>حفارات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="مكنات تخريم">
                                <span>مكنات تخريم</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="دوازر">
                                <span>دوازر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات قلابة">
                                <span>شاحنات قلابة</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات تناكر">
                                <span>شاحنات تناكر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="جرافات">
                                <span>جرافات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="معدات معالجة">
                                <span>معدات معالجة</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. البيانات القانونية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-file-contract"></i> البيانات القانونية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>رقم التسجيل التجاري/الرخصة</label>
                            <input type="text" name="commercial_registration" id="commercial_registration" />
                        </div>
                        
                        <div class="form-group">
                            <label>نوع الهوية</label>
                            <select name="identity_type" id="identity_type">
                                <option value="">-- اختر --</option>
                                <option value="بطاقة هوية وطنية">بطاقة هوية وطنية</option>
                                <option value="جواز سفر">جواز سفر</option>
                                <option value="رقم تسجيل تجاري">رقم تسجيل تجاري</option>
                                <option value="رقم ضريبة دخل">رقم ضريبة دخل</option>
                                <option value="رخصة عمل">رخصة عمل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهوية/التسجيل</label>
                            <input type="text" name="identity_number" id="identity_number" />
                        </div>
                        
                        <div class="form-group">
                            <label>تاريخ انتهاء الهوية</label>
                            <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                        </div>
                    </div>
                </div>

                <!-- 3. البيانات التواصلية -->
                <div class="form-section">
                    <h6><i class="fas fa-address-book"></i> البيانات التواصلية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>البريد الإلكتروني الرئيسي</label>
                            <input type="email" name="email" id="supplier_email" />
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهاتف الأساسي <span class="required">*</span></label>
                            <input type="text" name="phone" id="supplier_phone" required />
                        </div>
                        
                        <div class="form-group">
                            <label>رقم هاتف بديل</label>
                            <input type="text" name="phone_alternative" id="phone_alternative" />
                        </div>
                        
                        <div class="form-group">
                            <label>اسم جهة الاتصال الأساسية</label>
                            <input type="text" name="contact_person_name" id="contact_person_name" />
                        </div>
                        
                        <div class="form-group">
                            <label>هاتف جهة الاتصال</label>
                            <input type="text" name="contact_person_phone" id="contact_person_phone" />
                        </div>
                        
                        <div class="form-group">
                            <label>حالة التسجيل المالي</label>
                            <select name="financial_registration_status" id="financial_registration_status">
                                <option value="">-- اختر --</option>
                                <option value="مسجل رسميا">مسجل رسميا</option>
                                <option value="غير مسجل">غير مسجل</option>
                                <option value="تحت التسجيل">تحت التسجيل</option>
                                <option value="معفى من التسجيل">معفى من التسجيل</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>العنوان الكامل</label>
                            <textarea name="full_address" id="full_address" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>الحالة <span class="required">*</span></label>
                            <select name="status" id="supplier_status" required>
                                <option value="">اختر الحالة</option>
                                <option value="1">نشط</option>
                                <option value="0">معلق</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        حفظ المورد
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleForm()">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i> قائمة الموردين
            </h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-truck-loading"></i> اسم المورد</th>
                        <th><i class="fas fa-cogs"></i> عدد الآليات</th>
                        <th><i class="fas fa-file-contract"></i> عدد العقود</th>
                        <th><i class="fas fa-clock"></i> الساعات المتعاقد عليها</th>
                        <th><i class="fas fa-phone"></i> رقم الهاتف</th>
                        <th><i class="fas fa-info-circle"></i> الحالة</th>
                        <th><i class="fas fa-sliders-h"></i> الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب الموردين مع إجمالي الساعات
                    $query = "SELECT s.*, 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = s.id ) as 'equipments' ,
                      (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id ) as 'num_contracts',
                      (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id ) as 'total_hours'
                      FROM `suppliers` s ORDER BY s.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        // إعداد data attributes للتعديل
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
                        echo "<td><strong style='color:var(--primary-color)'>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['num_contracts'] . "</span></td>";
                        echo "<td><strong style='color:#28a745; font-size:1rem'>" . number_format($row['total_hours']) . " ساعة</strong></td>";
                        echo "<td><i class='fas fa-phone' style='color:var(--secondary-color); margin-left:6px;'></i>" . htmlspecialchars($row['phone']) . "</td>";

                        // الحالة بالألوان
                        if ($row['status'] == "1") {
                            echo "<td><span class='status-badge status-active'><i class='fas fa-check-circle' style='margin-left:6px;'></i>نشط</span></td>";
                        } else {
                            echo "<td><span class='status-badge status-inactive'><i class='fas fa-times-circle' style='margin-left:6px;'></i>معلق</span></td>";
                        }

                        echo "<td>
                        <a href='javascript:void(0)' 
                           class='viewBtn action-btn btn-view' 
                           $data_attrs
                           title='عرض التفاصيل'><i class='fas fa-eye'></i></a>
                        <a href='javascript:void(0)' 
                           class='editBtn action-btn btn-edit' 
                           $data_attrs
                           title='تعديل'><i class='fas fa-edit'></i></a>
                        <a href='supplierscontracts.php?id=" . $row['id'] . "' class='action-btn btn-contracts' title='العقود'><i class='fas fa-file-contract'></i></a>
                      </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal عرض تفاصيل المورد -->
    <div id="viewSupplierModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%); color: white; padding: 1.5rem; border-radius: 15px 15px 0 0;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-truck-loading" style="color: #debf0f;"></i>
                    <span>تفاصيل المورد</span>
                </h3>
                <button onclick="closeViewModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 28px; cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <!-- المعلومات الأساسية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">اسم المورد:</span>
                            <span class="info-value" id="view_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الرمز/الكود:</span>
                            <span class="info-value" id="view_supplier_code">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع المورد:</span>
                            <span class="info-value" id="view_supplier_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">طبيعة التعامل:</span>
                            <span class="info-value" id="view_dealing_nature">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">المعدات:</span>
                            <span class="info-value" id="view_equipment_types">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- البيانات القانونية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-file-contract"></i> البيانات القانونية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">رقم التسجيل التجاري:</span>
                            <span class="info-value" id="view_commercial_registration">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع الهوية:</span>
                            <span class="info-value" id="view_identity_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهوية:</span>
                            <span class="info-value" id="view_identity_number">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ انتهاء الهوية:</span>
                            <span class="info-value" id="view_identity_expiry_date">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- البيانات التواصلية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-address-book"></i> البيانات التواصلية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">البريد الإلكتروني:</span>
                            <span class="info-value" id="view_email">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهاتف الأساسي:</span>
                            <span class="info-value" id="view_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم هاتف بديل:</span>
                            <span class="info-value" id="view_phone_alternative">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">هاتف جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">حالة التسجيل المالي:</span>
                            <span class="info-value" id="view_financial_registration_status">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">العنوان الكامل:</span>
                            <span class="info-value" id="view_full_address">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value" id="view_status">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem; border-top: 2px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeViewModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> إغلاق
                </button>
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
        // تشغيل DataTable
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: '📋 نسخ' },
                    { extend: 'excel', text: '📊 Excel' },
                    { extend: 'csv', text: '📄 CSV' },
                    { extend: 'pdf', text: '📕 PDF' },
                    { extend: 'print', text: '🖨️ طباعة' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // اظهار/اخفاء الفورم
        const toggleSupplierFormBtn = document.getElementById('toggleForm');
        const supplierForm = document.getElementById('projectForm');
        toggleSupplierFormBtn.addEventListener('click', function () {
            supplierForm.style.display = supplierForm.style.display === "none" ? "block" : "none";
            // تنظيف الحقول عند الإضافة
            $("#supplier_id").val("");
            $("#supplier_name").val("");
            $("#supplier_phone").val("");
            $("#supplier_status").val("");
        });

        // عند الضغط على زر تعديل
        $(document).on("click", ".editBtn", function () {
            const $this = $(this);
            
            // البيانات الأساسية
            $("#supplier_id").val($this.data("id"));
            $("#supplier_name").val($this.data("name"));
            $("#supplier_code").val($this.data("supplier_code"));
            $("#supplier_type").val($this.data("supplier_type"));
            $("#dealing_nature").val($this.data("dealing_nature"));
            
            // المعدات (checkbox)
            const equipmentTypes = $this.data("equipment_types") ? $this.data("equipment_types").toString().split(', ') : [];
            $("input[name='equipment_types[]']").prop("checked", false);
            equipmentTypes.forEach(function(type) {
                $("input[name='equipment_types[]'][value='" + type.trim() + "']").prop("checked", true);
            });
            
            // البيانات القانونية
            $("#commercial_registration").val($this.data("commercial_registration"));
            $("#identity_type").val($this.data("identity_type"));
            $("#identity_number").val($this.data("identity_number"));
            $("#identity_expiry_date").val($this.data("identity_expiry_date"));
            
            // البيانات التواصلية
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
        
        // عند الضغط على زر عرض التفاصيل
        $(document).on("click", ".viewBtn", function () {
            const $this = $(this);
            
            // البيانات الأساسية
            $("#view_name").text($this.data("name") || "-");
            $("#view_supplier_code").text($this.data("supplier_code") || "-");
            $("#view_supplier_type").text($this.data("supplier_type") || "-");
            $("#view_dealing_nature").text($this.data("dealing_nature") || "-");
            $("#view_equipment_types").text($this.data("equipment_types") || "-");
            
            // البيانات القانونية
            $("#view_commercial_registration").text($this.data("commercial_registration") || "-");
            $("#view_identity_type").text($this.data("identity_type") || "-");
            $("#view_identity_number").text($this.data("identity_number") || "-");
            $("#view_identity_expiry_date").text($this.data("identity_expiry_date") || "-");
            
            // البيانات التواصلية
            $("#view_email").text($this.data("email") || "-");
            $("#view_phone").text($this.data("phone") || "-");
            $("#view_phone_alternative").text($this.data("phone_alternative") || "-");
            $("#view_full_address").text($this.data("full_address") || "-");
            $("#view_contact_person_name").text($this.data("contact_person_name") || "-");
            $("#view_contact_person_phone").text($this.data("contact_person_phone") || "-");
            $("#view_financial_registration_status").text($this.data("financial_registration_status") || "-");
            
            const status = $this.data("status") === "1" ? "نشط ✅" : "معلق ⏸️";
            $("#view_status").text(status);
            
            $("#viewSupplierModal").fadeIn(300);
        });
        
        // دالة closeViewModal لإغلاق Modal
        window.closeViewModal = function() {
            $("#viewSupplierModal").fadeOut(300);
        };
        
        // إغلاق Modal عند الضغط خارج المحتوى
        $(document).on("click", "#viewSupplierModal", function(e) {
            if (e.target.id === "viewSupplierModal") {
                closeViewModal();
            }
        });
        
        // دالة toggleForm لإظهار/إخفاء النموذج
        window.toggleForm = function() {
            var form = $("#projectForm");
            if (form.is(":visible")) {
                form.slideUp();
            } else {
                // مسح جميع الحقول
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
    })();
</script>

</body>

</html>