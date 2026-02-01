<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

// معالجة حذف المشروع
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_query = "DELETE FROM operationproject WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_query)) {
        header("Location: oprationprojects.php?msg=تم+حذف+المشروع+بنجاح+✅");
        exit();
    } else {
        header("Location: oprationprojects.php?msg=حدث+خطأ+أثناء+الحذف+❌");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['company_project_id'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $company_project_id = intval($_POST['company_project_id']);
    $company_client_id = intval($_POST['company_client_id']);
    
    // جلب بيانات المشروع من جدول company_project
    $project_data = mysqli_query($conn, "SELECT project_name FROM company_project WHERE id = $company_project_id");
    $project_row = mysqli_fetch_assoc($project_data);
    $name = $project_row['project_name'];
    
    // جلب بيانات العميل من جدول company_clients
    $client_data = mysqli_query($conn, "SELECT client_name FROM company_clients WHERE id = $company_client_id");
    $client_row = mysqli_fetch_assoc($client_data);
    $client = $client_row['client_name'];
    
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $total = floatval($_POST['total']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date = date('Y-m-d H:i:s');

    if ($id > 0) {
        // تحديث
        // التحقق من عدم تكرار نفس المشروع والعميل (مع استثناء السجل الحالي)
        $check_duplicate = mysqli_query($conn, "SELECT id FROM operationproject 
            WHERE company_project_id = $company_project_id 
            AND company_client_id = $company_client_id 
            AND id != $id");
        
        if (mysqli_num_rows($check_duplicate) > 0) {
            header("Location: oprationprojects.php?msg=هذا+المشروع+مع+هذا+العميل+موجود+بالفعل+❌");
            exit;
        }
        
        $sql = "UPDATE operationproject SET 
            company_project_id='$company_project_id',
            company_client_id='$company_client_id',
            name='$name',
            client='$client',
            location='$location',
            total='$total',
            status='$status'
        WHERE id=$id";
        mysqli_query($conn, $sql);

         header("Location: oprationprojects.php?msg=تم+تعديل+المشروع+بنجاح+✅");
                exit;
    } else {
        // التحقق من عدم تكرار نفس المشروع والعميل عند الإضافة
        $check_duplicate = mysqli_query($conn, "SELECT id FROM operationproject 
            WHERE company_project_id = $company_project_id 
            AND company_client_id = $company_client_id");
        
        if (mysqli_num_rows($check_duplicate) > 0) {
            header("Location: oprationprojects.php?msg=هذا+المشروع+مع+هذا+العميل+موجود+بالفعل+❌");
            exit;
        }
        
        // إضافة
        $sql = "INSERT INTO operationproject (company_project_id, company_client_id, name, client, location, total, status, create_at) 
        VALUES ('$company_project_id', '$company_client_id', '$name', '$client', '$location', '$total', '$status', '$date')";
        mysqli_query($conn, $sql);
         header("Location: oprationprojects.php?msg=تم+اضافه+المشروع+بنجاح+✅");
          exit;
    }
}
?>


<?php
$page_title = "إيكوبيشن | المشاريع";
include("../inheader.php");
include('../insidebar.php');
?>

<style>
:root {
    --primary-color: #1a1a2e;
    --secondary-color: #16213e;
    --gold-color: #ffcc00;
    --text-color: #010326;
    --light-color: #f5f5f5;
    --border-color: #e0e0e0;
    --shadow-color: rgba(0, 0, 0, 0.1);
}

/* Modern Projects Page Styling */
.main {
    margin-right: 10px;
    padding: 30px;
    transition: margin 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
    background: var(--border-color);
    max-width: 100vw;
    overflow-x: hidden;
}

.sidebar.closed ~ .main {
    margin-right: 5px;
}

/* Laptop screens */
@media (max-width: 1366px) {
    .main {
        margin-right: 280px;
        padding: 20px 15px;
    }
    
    .sidebar.closed ~ .main {
        margin-right: 75px;
    }
}

@media (max-width: 1024px) {
    .main {
        margin-right: 280px;
        padding: 15px 10px;
    }
    
    .sidebar.closed ~ .main {
        margin-right: 75px;
    }
}

@media (max-width: 768px) {
    .main {
        margin-right: 0 !important;
        padding: 20px 15px;
        padding-top: 80px;
    }
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 28px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
    font-family: 'Cairo', sans-serif;
    animation: slideDown 0.6s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Add Button */
.add {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 28px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px var(--shadow-color);
    border: none;
    cursor: pointer;
    font-size: 15px;
    animation: fadeIn 0.6s ease-out 0.1s both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.add:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px var(--shadow-color);
    color: white;
}

.add i {
    font-size: 16px;
}

/* Success Message */
.success-message {
    background: linear-gradient(135deg, #63ce7c 0%, #218838 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    text-align: center;
    box-shadow: 0 4px 15px var(--shadow-color);
    animation: slideDown 0.5s ease;
    font-weight: 600;
}

/* Form Card */
#projectForm {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 30px var(--shadow-color);
    background: white;
    margin-bottom: 25px;
    animation: fadeIn 0.8s ease-out 0.2s both;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 20px 25px;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    font-family: 'Cairo', sans-serif;
}

.card-body {
    padding: 30px 25px;
}

@media (max-width: 1366px) {
    .card-body {
        padding: 20px 15px;
    }
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-grid > div {
    display: flex;
    flex-direction: column;
}

.form-grid label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
    font-size: 14px;
    font-family: 'Cairo', sans-serif;
}

.form-grid input,
.form-grid select {
    padding: 12px 16px;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f8f9fa;
    font-family: 'Cairo', sans-serif;
}

.form-grid input:focus,
.form-grid select:focus {
    outline: none;
    border-color: var(--gold-color);
    background: white;
    box-shadow: 0 0 0 4px rgba(255, 204, 0, 0.1);
}

.form-grid button[type="submit"] {
    grid-column: 1 / -1;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px var(--shadow-color);
    font-family: 'Cairo', sans-serif;
}

.form-grid button[type="submit"]:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px var(--shadow-color);
}

/* Table Container */
.table-container {
    overflow-x: auto;
    overflow-y: visible;
    max-width: 100%;
    -webkit-overflow-scrolling: touch;
}

/* Table Styling */
#projectsTable {
    width: 100%;
    font-family: 'Cairo', sans-serif;
    table-layout: auto;
    min-width: 1200px;
}

@media (max-width: 1366px) {
    #projectsTable {
        min-width: 1000px;
        font-size: 13px;
    }
    
    #projectsTable thead th,
    #projectsTable tbody td {
        padding: 10px 8px;
    }
}

#projectsTable thead th {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 16px 12px;
    font-weight: 700;
    text-align: center;
    border: none;
    font-size: 14px;
    white-space: nowrap;
}

#projectsTable tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

#projectsTable tbody tr:hover {
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.03) 0%, rgba(22, 33, 62, 0.03) 100%);
    transform: scale(1.01);
    box-shadow: 0 4px 15px var(--shadow-color);
}

#projectsTable tbody td {
    padding: 14px 12px;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
    color: #2c3e50;
}

/* Status Badges */
.status-active {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(17, 153, 142, 0.4);
}

.status-inactive {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
}

/* Count Badges */
.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(79, 172, 254, 0.4);
}

/* Action Buttons */
.action-btns {
    display: flex;
    gap: 12px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

@media (max-width: 1366px) {
    .action-btns {
        gap: 8px;
    }
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 16px;
}

.action-btn.view {
    background: linear-gradient(135deg, var(--gold-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.action-btn.view:hover {
    transform: translateY(-2px) scale(1.15);
    box-shadow: 0 4px 12px var(--shadow-color);
}

.action-btn.edit {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.action-btn.edit:hover {
    transform: translateY(-2px) scale(1.15);
    box-shadow: 0 4px 12px var(--shadow-color);
}

.action-btn.delete {
    background: linear-gradient(135deg, #d64545 0%, #b03a3a 100%);
    color: white;
}

.action-btn.delete:hover {
    transform: translateY(-2px) scale(1.15);
    box-shadow: 0 4px 12px var(--shadow-color);
}

.action-btn.view {
    background: linear-gradient(135deg, var(--gold-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.action-btn.view:hover {
    transform: translateY(-2px) scale(1.15);
    box-shadow: 0 4px 12px var(--shadow-color);
}

.action-btn.contracts {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--gold-color) 100%);
    color: white;
}

.action-btn.contracts:hover {
    transform: translateY(-2px) scale(1.15);
    box-shadow: 0 4px 12px var(--shadow-color);
}
    transform: translateY(-2px) scale(1.1);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.6);
}

/* DataTables Buttons */
.dt-buttons {
    margin-bottom: 15px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.dt-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border: none !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
    font-family: 'Cairo', sans-serif !important;
}

.dt-button:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5) !important;
}

/* DataTables Search & Info */
.dataTables_wrapper .dataTables_filter input {
    border: 2px solid #e1e8ed;
    border-radius: 8px;
    padding: 8px 16px;
    margin-right: 8px;
    font-family: 'Cairo', sans-serif;
}

.dataTables_wrapper .dataTables_filter input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.dataTables_wrapper .dataTables_length select {
    border: 2px solid #e1e8ed;
    border-radius: 8px;
    padding: 6px 12px;
    margin: 0 8px;
    font-family: 'Cairo', sans-serif;
}

/* Responsive */
@media (max-width: 1366px) {
    .page-title {
        font-size: 26px;
    }
    
    .add {
        padding: 10px 20px;
        font-size: 14px;
    }
}

@media (max-width: 1024px) {
    .page-title {
        font-size: 24px;
    }
    
    .form-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .action-btns {
        gap: 8px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
}

/* Animations for table rows */
#projectsTable tbody tr {
    animation: popIn 0.4s ease-out;
    animation-fill-mode: both;
}

#projectsTable tbody tr:nth-child(1) { animation-delay: 0.05s; }
#projectsTable tbody tr:nth-child(2) { animation-delay: 0.1s; }
#projectsTable tbody tr:nth-child(3) { animation-delay: 0.15s; }
#projectsTable tbody tr:nth-child(4) { animation-delay: 0.2s; }
#projectsTable tbody tr:nth-child(5) { animation-delay: 0.25s; }
#projectsTable tbody tr:nth-child(6) { animation-delay: 0.3s; }
#projectsTable tbody tr:nth-child(7) { animation-delay: 0.35s; }
#projectsTable tbody tr:nth-child(8) { animation-delay: 0.4s; }

@keyframes popIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-content {
    position: relative;
    background: white;
    margin: 3% auto;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-width: 900px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    color: white;
    padding: 20px 25px;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h5 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    font-family: 'Cairo', sans-serif;
}

.close-modal {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px 25px;
}

.view-modal-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.view-item {
    padding: 16px;
    background: var(--light-color);
    border-radius: 10px;
    border-right: 4px solid var(--gold-color);
    animation: slideInRight 0.4s ease-out;
}

.view-item:nth-child(1) { animation-delay: 0.05s; }
.view-item:nth-child(2) { animation-delay: 0.1s; }
.view-item:nth-child(3) { animation-delay: 0.15s; }
.view-item:nth-child(4) { animation-delay: 0.2s; }
.view-item:nth-child(5) { animation-delay: 0.25s; }
.view-item:nth-child(6) { animation-delay: 0.3s; }

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.view-item-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-color);
    text-transform: uppercase;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.view-item-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--primary-color);
    word-break: break-all;
}

.modal-footer {
    display: flex;
    gap: 15px;
    padding: 20px 25px;
    border-top: 1px solid var(--border-color);
}

.btn-modal {
    flex: 1;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Cairo', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-modal-save {
    background: linear-gradient(135deg, var(--gold-color) 0%, var(--primary-color) 100%);
    color: white;
    box-shadow: 0 4px 15px var(--shadow-color);
}

.btn-modal-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px var(--shadow-color);
}

.btn-modal-cancel {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
    box-shadow: 0 4px 15px var(--shadow-color);
}

.btn-modal-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px var(--shadow-color);
}

@media (max-width: 768px) {
    .view-modal-body {
        grid-template-columns: 1fr;
    }

    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
}

/* أزرار DataTables */
.dt-buttons .dt-button {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 8px 18px !important;
    font-weight: 700 !important;
    font-family: 'Cairo', sans-serif !important;
    transition: all 0.3s ease !important;
}

/* Hover */
.dt-buttons .dt-button:hover {
    color: var(--gold-color) !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
}

/* عند الضغط */
.dt-buttons .dt-button:active {
    transform: scale(0.96);
}

</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-project-diagram"></i> إدارة المشاريع</h1>
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fas fa-plus-circle"></i> إضافة مشروع جديد
        </a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>



    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مشروع</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-file-signature"></i> اسم المشروع</label>
                        <select name="company_project_id" id="company_project_id" required>
                            <option value="">-- اختر المشروع --</option>
                            <?php
                            $projects_query = mysqli_query($conn, "SELECT id, project_code, project_name FROM company_project WHERE status = 'نشط' ORDER BY project_name ASC");
                            while ($proj = mysqli_fetch_assoc($projects_query)) {
                                echo "<option value='" . $proj['id'] . "'>[" . $proj['project_code'] . "] " . $proj['project_name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> اسم العميل</label>
                        <select name="company_client_id" id="company_client_id" required>
                            <option value="">-- اختر العميل --</option>
                            <?php
                            $clients_query = mysqli_query($conn, "SELECT id, client_code, client_name FROM company_clients WHERE status = 'نشط' ORDER BY client_name ASC");
                            while ($cli = mysqli_fetch_assoc($clients_query)) {
                                echo "<option value='" . $cli['id'] . "'>[" . $cli['client_code'] . "] " . $cli['client_name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker-alt"></i> موقع المشروع</label>
                        <input type="text" name="location" placeholder="أدخل موقع المشروع" id="project_location" required />
                        <input type="hidden" name="total" value="0" required />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة المشروع</label>
                        <select name="status" id="project_status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1">✅ نشط</option>
                            <option value="0">❌ غير نشط</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fas fa-save"></i> حفظ المشروع
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المشاريع -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> قائمة المشاريع</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> تاريخ الإضافة</th>
                        <th><i class="fas fa-project-diagram"></i> اسم المشروع</th>
                        <th><i class="fas fa-file-contract"></i> العقود</th>
                        <th><i class="fas fa-user-tie"></i> العميل</th>
                        <th><i class="fas fa-truck"></i> عدد الموردين</th>
                        <th><i class="fas fa-toggle-on"></i> الحالة</th>
                        <th><i class="fas fa-file-contract"></i> عقود المشروع</th>
                        <th><i class="fas fa-cogs"></i> إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    include '../config.php';

                    // جلب المشاريع مع الموقع من جدول company_project واسم العميل من جدول company_clients
                    $query = "SELECT op.`id`, op.`name`,cp.`project_name` , cc.`client_name`, op.`total`, op.`status`, op.`create_at`, 
                      op.`company_project_id`, op.`company_client_id`,
                      COALESCE(cp.`state`, op.`location`) as 'location',
                      (SELECT COUNT(*) FROM contracts WHERE contracts.project = op.id) as 'contracts',
                      (SELECT COUNT(DISTINCT pm.suppliers) 
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE m.project = op.id) as 'total_suppliers'
                      FROM operationproject op
                      LEFT JOIN company_project cp ON op.company_project_id = cp.id
                      LEFT JOIN company_clients cc ON op.company_client_id = cc.id
                      ORDER BY op.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $row['create_at'] . "</td>";
                        echo "<td><strong>" . $row['project_name'] . "</strong></td>";
                        echo "<td><span class='count-badge'>" . $row['contracts'] . "</span></td>";
                        echo "<td>" . ($row['client_name'] ?? $row['client']) . "</td>";
                        echo "<td><span class='count-badge'>" . $row['total_suppliers'] . "</span></td>";
                        
                        if ($row['status'] == "1") {
                            echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                        } else {
                            echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                        }
                        
                        echo "<td>
                            <a href='../Contracts/contracts.php?id=" . $row['id'] . "' 
                               class='action-btn contracts'
                               title='عرض عقود المشروع'>
                               <i class='fas fa-file-contract'></i>
                            </a>
                        </td>";
                        
                        echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)' 
                                   class='action-btn view viewBtn' 
                                   data-id='" . $row['id'] . "' 
                                   data-project-name='" . htmlspecialchars($row['project_name']) . "' 
                                   data-client-name='" . htmlspecialchars($row['client_name'] ?? $row['client']) . "' 
                                   data-location='" . htmlspecialchars($row['location']) . "' 
                                   data-status='" . $row['status'] . "' 
                                   data-contracts='" . $row['contracts'] . "' 
                                   data-suppliers='" . $row['total_suppliers'] . "'
                                   title='عرض التفاصيل'>
                                   <i class='fas fa-eye'></i>
                                </a>
                                <a href='javascript:void(0)' 
                                   class='action-btn edit editBtn' 
                                   data-id='" . $row['id'] . "' 
                                   data-company-project-id='" . ($row['company_project_id'] ?? '') . "' 
                                   data-company-client-id='" . ($row['company_client_id'] ?? '') . "' 
                                   data-name='" . $row['name'] . "' 
                                   data-location='" . $row['location'] . "' 
                                   data-status='" . $row['status'] . "'
                                   title='تعديل'>
                                   <i class='fas fa-edit'></i>
                                </a>
                                <a href='oprationprojects.php?delete_id=" . $row['id'] . "' 
                                   class='action-btn delete' 
                                   onclick='return confirm(\"هل أنت متأكد من حذف هذا المشروع؟\")'
                                   title='حذف'>
                                   <i class='fas fa-trash-alt'></i>
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
</div>

<!-- Modal عرض تفاصيل المشروع -->
<div id="viewProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
            <h5><i class="fas fa-eye"></i> عرض تفاصيل المشروع</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> اسم المشروع</div>
                    <div class="view-item-value" id="view_project_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> اسم العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> موقع المشروع</div>
                    <div class="view-item-value" id="view_location">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-contract"></i> عدد العقود</div>
                    <div class="view-item-value" id="view_contracts">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-truck"></i> عدد الموردين</div>
                    <div class="view-item-value" id="view_suppliers">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> حالة المشروع</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-save editBtn" id="viewEditBtn" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--gold-color) 100%);">
                <i class="fas fa-edit"></i> تعديل المشروع
            </button>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
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
                    { extend: 'copy', text: 'نسخ (Copy)' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة (Print)' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // اظهار/اخفاء الفورم
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        toggleProjectFormBtn.addEventListener('click', function () {
            projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
            // تنظيف الحقول عند الإضافة
            $("#project_id").val("");
            $("#company_project_id").val("");
            $("#company_client_id").val("");
            $("#project_location").val("");
            $("#project_status").val("");
        });

        // عرض Modal عند الضغط على زر العرض
        $(document).on("click", ".viewBtn", function () {
            const projectData = {
                id: $(this).data('id'),
                projectName: $(this).data('project-name'),
                clientName: $(this).data('client-name'),
                location: $(this).data('location'),
                status: $(this).data('status'),
                contracts: $(this).data('contracts'),
                suppliers: $(this).data('suppliers')
            };

            // ملء بيانات العرض
            $('#view_project_name').text(projectData.projectName || '-');
            $('#view_client_name').text(projectData.clientName || '-');
            $('#view_location').text(projectData.location || '-');
            $('#view_contracts').text(projectData.contracts || '0');
            $('#view_suppliers').text(projectData.suppliers || '0');

            // عرض الحالة بألوان
            let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
            if (projectData.status === '1' || projectData.status === 1) {
                statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
            } else {
                statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
            }
            statusHtml += ' display: inline-block;">';
            statusHtml += '<i class="fas fa-circle" style="margin-left: 6px; font-size: 8px;"></i> ' + (projectData.status === '1' || projectData.status === 1 ? 'نشط' : 'غير نشط') + '</span>';
            $('#view_status').html(statusHtml);

            // تحضير زر التعديل
            const editBtn = $('#viewEditBtn');
            editBtn.data('id', projectData.id);
            editBtn.data('company-project-id', $(this).data('company-project-id'));
            editBtn.data('company-client-id', $(this).data('company-client-id'));
            editBtn.data('name', $(this).data('name'));
            editBtn.data('location', projectData.location);
            editBtn.data('status', projectData.status);

            $('#viewProjectModal').fadeIn(300);
        });

        // إغلاق Modal عرض المشروع
        function closeViewModal() {
            $('#viewProjectModal').fadeOut(300);
        }

        // إغلاق عند الضغط خارج Modal
        $(window).on('click', function (e) {
            if (e.target.id === 'viewProjectModal') {
                closeViewModal();
            }
        });

        // إغلاق عند الضغط على ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#viewProjectModal').is(':visible')) {
                closeViewModal();
            }
        });

        // التعامل مع زر التعديل من Modal العرض
        $('#viewEditBtn').on('click', function () {
            $("#project_id").val($(this).data('id'));
            $("#company_project_id").val($(this).data('company-project-id'));
            $("#company_client_id").val($(this).data('company-client-id'));
            $("#project_location").val($(this).data('location'));
            $("#project_status").val($(this).data('status'));

            closeViewModal();
            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند الضغط على زر تعديل من الجدول
        $(document).on("click", ".editBtn:not(#viewEditBtn)", function () {
            $("#project_id").val($(this).data("id"));
            $("#company_project_id").val($(this).data("company-project-id"));
            $("#company_client_id").val($(this).data("company-client-id"));
            $("#project_location").val($(this).data("location"));
            $("#project_status").val($(this).data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });
    })();
</script>

</body>

</html>