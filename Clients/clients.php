<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

// معالجة إضافة عميل جديد عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    header('Content-Type: application/json; charset=utf-8');

    $client_code = mysqli_real_escape_string($conn, trim($_POST['client_code']));
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['user']['id'];

    // التحقق من عدم تكرار كود العميل
    $check_query = "SELECT id FROM clients WHERE client_code = '$client_code'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود العميل موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
    }

    $insert_query = "INSERT INTO clients 
        (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by) 
        VALUES 
        ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by')";

    if (mysqli_query($conn, $insert_query)) {
        echo json_encode(['success' => true, 'message' => 'تم إضافة العميل بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء إضافة العميل']);
    }
    exit();
}

// معالجة تعديل العميل عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json; charset=utf-8');

    $client_id = intval($_POST['client_id']);
    $client_code = mysqli_real_escape_string($conn, trim($_POST['client_code']));
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // التحقق من عدم تكرار كود العميل (مع استثناء العميل الحالي)
    $check_query = "SELECT id FROM clients WHERE client_code = '$client_code' AND id != $client_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود العميل موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
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
        WHERE id = $client_id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'تم تعديل العميل بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تعديل العميل']);
    }
    exit();
}

// معالجة حذف العميل
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    header("Location: clients.php?msg=تم+تعطيل+الحذف+مؤقتا❌");
    //************************************* ازل التعليق لتفعيل عملية الحذف ***************************** */
    // التحقق من عدم استخدام العميل في جدول operationproject
    // $check_usage = mysqli_query($conn, "SELECT COUNT(*) as count FROM operationproject WHERE company_client_id = $delete_id");
    // $usage = mysqli_fetch_assoc($check_usage);

    // if ($usage['count'] > 0) {
    //     header("Location: clients.php?msg=لا+يمكن+حذف+العميل+لأنه+مستخدم+في+مشاريع+موجودة+❌");
    //     exit();
    // } else {
    //     $delete_query = "DELETE FROM clients WHERE id = $delete_id";
    //     if (mysqli_query($conn, $delete_query)) {
    //         header("Location: clients.php?msg=تم+حذف+العميل+بنجاح+✅");
    //         exit();
    //     } else {
    //         header("Location: clients.php?msg=حدث+خطأ+أثناء+الحذف+❌");
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

<style>
  
    :root {
        --primary-color: #1a1a2e;
        --secondary-color: #16213e;
        --accent-color: #ffcc00;
        --text-color: #010326;
        --light-color: #f5f5f5;
        --gold-color: #ffcc00;
        --link-color: #a68503;
        --shadow-color: rgba(0, 0, 0, 0.1);
    }

    * {
        font-family: 'Cairo', sans-serif;
    }

    .main {
        width: calc(100% - 250px);
        padding: 20px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
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

    .page-title {
        font-size: 25px;
        font-weight: 900;
        background: linear-gradient(135deg, var(--primary-color) 0%, #16150d 50%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0;
        font-family: 'Cairo', sans-serif;
    }

    .add-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 10px 20px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 400;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px var(--shadow-color);
        font-family: 'Cairo', sans-serif;
        animation: fadeIn 0.6s ease-out 0.1s both;
        font-size: 15px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .add-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px var(--shadow-color);
        color: white;
    }

    .success-message {
        background: linear-gradient(135deg, #63ce7c 0%, #218838 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        text-align: center;
        box-shadow: 0 4px 15px var(--shadow-color);
        font-weight: 600;
        animation: slideDown 0.5s ease-out;
    }

    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 30px var(--shadow-color);
        background: white;
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
        padding: 25px;
    }

    .table-container {
        overflow-x: auto;
        max-width: 100%;
    }

    #clientsTable {
        width: 100%;
        font-family: 'Cairo', sans-serif;
    }

    #clientsTable thead th {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: var(--gold-color);
        padding: 16px 12px;
        font-weight: 700;
        text-align: center;
        border: none;
        font-size: 14px;
        white-space: nowrap;
    }

    #clientsTable tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--border-color);
        animation: popIn 0.5s ease-out backwards;
    }

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

    #clientsTable tbody tr:nth-child(1) {
        animation-delay: 0.1s;
    }

    #clientsTable tbody tr:nth-child(2) {
        animation-delay: 0.15s;
    }

    #clientsTable tbody tr:nth-child(3) {
        animation-delay: 0.2s;
    }

    #clientsTable tbody tr:nth-child(4) {
        animation-delay: 0.25s;
    }

    #clientsTable tbody tr:nth-child(5) {
        animation-delay: 0.3s;
    }

    #clientsTable tbody tr:hover {
        background: linear-gradient(135deg, rgba(255, 204, 0, 0.05) 0%, rgba(26, 26, 46, 0.05) 100%);
        transform: scale(1.01);
    }

    #clientsTable tbody td {
        padding: 14px 12px;
        text-align: center;
        vertical-align: middle;
        font-size: 14px;
        color: var(--text-color);
    }

    .status-active {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        background: linear-gradient(135deg, #63ce7c 0%, #218838 100%);
        color: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }

    .status-inactive {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        background: linear-gradient(135deg, #eb6767 0%, #b03a3a 100%);
        color: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }

    .action-btns {
        display: flex;
        gap: 10px;
        justify-content: center;
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
        background: #c7a708;
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

    .dt-buttons {
        margin-bottom: 15px;
        display: flex;
        gap: 8px;
    }

    .dt-button {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
        color: white !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-family: 'Cairo', sans-serif !important;
        transition: all 0.3s ease !important;
    }

    .dt-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px var(--shadow-color) !important;
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
        animation: fadeInModal 0.3s ease;
    }

    @keyframes fadeInModal {
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
        box-shadow: 0 10px 40px var(--shadow-color);
        animation: slideInModal 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        max-height: 90vh;
        overflow-y: auto;
    }

    @keyframes slideInModal {
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
        background: linear-gradient(135deg, var(--gold-color) 0%, var(--primary-color) 100%);
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
        animation: fadeInContent 0.6s ease-out 0.2s both;
    }

    @keyframes fadeInContent {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .form-grid-modal {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .form-group-modal {
        display: flex;
        flex-direction: column;
    }

    .form-group-modal label {
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--primary-color);
        font-size: 14px;
        font-family: 'Cairo', sans-serif;
    }

    .form-group-modal label i {
        margin-left: 8px;
        color: var(--gold-color);
    }

    .form-group-modal input,
    .form-group-modal select {
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s ease;
        background: var(--light-color);
        font-family: 'Cairo', sans-serif;
    }

    .form-group-modal input:focus,
    .form-group-modal select:focus {
        outline: none;
        border-color: var(--gold-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(255, 204, 0, 0.1);
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
        background: linear-gradient(135deg, #999 0%, #666 100%);
        color: white;
        box-shadow: 0 4px 15px var(--shadow-color);
    }

    .btn-modal-cancel:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px var(--shadow-color);
    }

    @media (max-width: 768px) {
        .form-grid-modal {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }

    /* ================== View Modal ================== */
    .view-modal-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }

    /* Item */
    .view-item {
        padding: 14px 16px;
        background: var(--light-color);
        border-radius: 12px;
        border-right: 3px solid var(--gold-color);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
        animation: fadeSlide 0.4s ease-out both;
    }

    /* Animation */
    @keyframes fadeSlide {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Stagger */
    .view-item:nth-child(1) {
        animation-delay: 0.05s;
    }

    .view-item:nth-child(2) {
        animation-delay: 0.1s;
    }

    .view-item:nth-child(3) {
        animation-delay: 0.15s;
    }

    .view-item:nth-child(4) {
        animation-delay: 0.2s;
    }

    .view-item:nth-child(5) {
        animation-delay: 0.25s;
    }

    /* Label */
    .view-item-label {
        font-size: 11px;
        font-weight: 600;
        color: #8a8a8a;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.5px;
    }

    /* Value */
    .view-item-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--primary-color);
        word-break: break-word;
    }

    /* Hover (اختياري – خفيف جداً) */
    .view-item:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
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
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    }

    /* عند الضغط */
    .dt-buttons .dt-button:active {
        transform: scale(0.96);
    }
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users"></i> إدارة العملاء</h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="javascript:void(0)" id="openAddModal" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة عميل جديد
            </a>
            <a href="javascript:void(0)" id="openImportModal" class="add-btn"
                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <i class="fas fa-file-excel"></i> استيراد من Excel
            </a>
            <a href="download_clients_template.php" class="add-btn"
                style="background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);">
                <i class="fas fa-download"></i> تحميل نموذج Excel
            </a>
            <a href="download_clients_template_csv.php" class="add-btn"
                style="background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);">
                <i class="fas fa-file-csv"></i> تحميل نموذج CSV
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> جميع العملاء</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th width="100"><i class="fas fa-barcode"></i> كود العميل</th>
                            <th><i class="fas fa-user"></i> اسم العميل</th>
                            <th><i class="fas fa-building"></i> نوع الكيان</th>
                            <th><i class="fas fa-industry"></i> تصنيف القطاع</th>
                            <th><i class="fas fa-phone"></i> الهاتف</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT cc.*, u.name as creator_name 
                                  FROM clients cc 
                                  LEFT JOIN users u ON cc.created_by = u.id 
                                  ORDER BY cc.id DESC";
                        $result = mysqli_query($conn, $query);
                        $counter = 1;

                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td><strong>" . htmlspecialchars($row['client_code']) . "</strong></td>";
                            echo "<td><b><a style='text-decoration:none;color:var(--link-color);' href='../Projects/oprationprojects.php?client_id=" . urlencode($row['id']) . "'>" . htmlspecialchars($row['client_name']) . "</a></b></td>";
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
                                       data-created='" . htmlspecialchars($row['creator_name'] ?? 'غير محدد') . "'
                                       title='عرض التفاصيل'>
                                        <i class='fas fa-eye'></i>
                                    </a>
                                    <a href='javascript:void(0)' 
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
                                       title='تعديل'>
                                        <i class='fas fa-edit'></i>
                                    </a>
                                    <a href='?delete_id=" . $row['id'] . "' class='action-btn delete' 
                                       onclick='return confirm(\"هل أنت متأكد من حذف هذا العميل؟\")' title='حذف'>
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

<!-- Modal استيراد من Excel -->
<div id="importExcelModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <h5><i class="fas fa-file-excel"></i> استيراد عملاء من Excel</h5>
            <button class="close-modal" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div
                    style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-right: 4px solid #2196f3;">
                    <h6 style="color: #1976d2; font-weight: 700; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> تعليمات الاستيراد:
                    </h6>
                    <ul style="color: #0d47a1; line-height: 2; margin: 0; padding-right: 20px;">
                        <li>قم بتحميل نموذج Excel أو CSV أولاً</li>
                        <li>املأ البيانات حسب الأعمدة المحددة</li>
                        <li>كود العميل يجب أن يكون فريداً</li>
                        <li>الحقول المطلوبة: كود العميل، اسم العميل، الحالة</li>
                        <li>صيغة الملف المدعومة: .xlsx, .xls, .csv</li>
                        <li><strong>ملاحظة:</strong> إذا لم تكن مكتبة PhpSpreadsheet مثبتة، استخدم ملف CSV</li>
                    </ul>
                </div>

                <div class="form-group-modal">
                    <label><i class="fas fa-file-upload"></i> اختر ملف Excel أو CSV (.xlsx, .xls, .csv) *</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                        style="padding: 15px; border: 3px dashed #11998e; border-radius: 12px; background: #f0fdf4;">

                </div>

                <div id="importProgress" style="display: none; margin-top: 20px;">
                    <div style="background: #e3f2fd; border-radius: 10px; padding: 15px; text-align: center;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #2196f3;"></i>
                        <p style="margin: 10px 0 0 0; color: #1976d2; font-weight: 600;">جاري الاستيراد...</p>
                    </div>
                </div>

                <div id="importResult" style="display: none; margin-top: 20px;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save"
                    style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <i class="fas fa-upload"></i> رفع واستيراد
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal إضافة عميل جديد -->
<div id="addClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-plus-circle"></i> إضافة عميل جديد</h5>
            <button class="close-modal" onclick="closeAddModal()">&times;</button>
        </div>
        <form id="addClientForm">
            <div class="modal-body">
                <div class="form-grid-modal">
                    <div class="form-group-modal">
                        <label><i class="fas fa-barcode"></i> كود العميل *</label>
                        <input type="text" id="add_client_code" name="client_code" required pattern="[A-Za-z0-9-_]+"
                            placeholder="مثال: CL-001">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" id="add_client_name" name="client_name" required
                            placeholder="أدخل اسم العميل">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-building"></i> نوع الكيان</label>
                        <select id="add_entity_type" name="entity_type">
                            <option value="">-- اختر نوع الكيان --</option>
                            <option value="حكومي">حكومي</option>
                            <option value="خاص">خاص</option>
                            <option value="مختلط">مختلط</option>
                            <option value="دولي">دولي</option>
                            <option value="غير ربحي">غير ربحي</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select id="add_sector_category" name="sector_category">
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعة">زراعة</option>
                            <option value="خدمات">خدمات</option>
                            <option value="تجارة">تجارة</option>
                            <option value="صناعة">صناعة</option>
                            <option value="طاقة">طاقة</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" id="add_phone" name="phone" placeholder="مثال: +249123456789">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" id="add_email" name="email" placeholder="example@company.com">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fab fa-whatsapp"></i> واتساب</label>
                        <input type="tel" id="add_whatsapp" name="whatsapp" placeholder="مثال: +249123456789">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select id="add_status" name="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save">
                    <i class="fas fa-save"></i> حفظ العميل
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeAddModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal عرض العميل -->
<div id="viewClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #292802 0%, #031027 100%);">
            <h5><i class="fas fa-eye"></i> عرض بيانات العميل</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود العميل</div>
                    <div class="view-item-value" id="view_client_code">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> اسم العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> نوع الكيان</div>
                    <div class="view-item-value" id="view_entity_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> تصنيف القطاع</div>
                    <div class="view-item-value" id="view_sector_category">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> الهاتف</div>
                    <div class="view-item-value" id="view_phone">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</div>
                    <div class="view-item-value" id="view_email">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> واتساب</div>
                    <div class="view-item-value" id="view_whatsapp">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-plus"></i> أضيف بواسطة</div>
                    <div class="view-item-value" id="view_created_by">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-save editClientBtn" id="viewEditBtn"
                style="background: linear-gradient(135deg, #200b44 0%, #c7a708 100%);">
                <i class="fas fa-edit"></i> تعديل البيانات
            </button>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- Modal تعديل العميل -->
<div id="editClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-user-edit"></i> تعديل بيانات العميل</h5>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editClientForm">
            <div class="modal-body">
                <input type="hidden" id="edit_client_id" name="client_id">
                <div class="form-grid-modal">
                    <div class="form-group-modal">
                        <label><i class="fas fa-barcode"></i> كود العميل *</label>
                        <input type="text" id="edit_client_code" name="client_code" required pattern="[A-Za-z0-9]+">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" id="edit_client_name" name="client_name" required>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-building"></i> نوع الكيان</label>
                        <select id="edit_entity_type" name="entity_type" required>
                            <option value="">-- اختر نوع الكيان --</option>
                            <option value="حكومي">حكومي</option>
                            <option value="خاص">خاص</option>
                            <option value="مختلط">مختلط</option>
                            <option value="دولي">دولي</option>
                            <option value="غير ربحي">غير ربحي</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select id="edit_sector_category" name="sector_category" required>
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعة">زراعة</option>
                            <option value="خدمات">خدمات</option>
                            <option value="تجارة">تجارة</option>
                            <option value="صناعة">صناعة</option>
                            <option value="طاقة">طاقة</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="text" id="edit_phone" name="phone" pattern="[0-9]{10}">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" id="edit_email" name="email">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fab fa-whatsapp"></i> رقم الواتساب</label>
                        <input type="text" id="edit_whatsapp" name="whatsapp" pattern="[0-9]{10}">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select id="edit_status" name="status" required>
                            <option value="نشط">نشط ✅</option>
                            <option value="متوقف">متوقف ❌</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save">
                    <i class="fas fa-save"></i> حفظ التعديلات
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });

    // فتح Modal الإضافة
    $('#openAddModal').on('click', function () {
        $('#addClientModal').fadeIn(300);
    });

    // إغلاق Modal الإضافة
    function closeAddModal() {
        $('#addClientModal').fadeOut(300);
        $('#addClientForm')[0].reset();
    }

    // فتح Modal عرض العميل
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

        // ملء بيانات العرض
        $('#view_client_code').text(clientData.code || '-');
        $('#view_client_name').text(clientData.name || '-');
        $('#view_entity_type').text(clientData.entity || '-');
        $('#view_sector_category').text(clientData.sector || '-');
        $('#view_phone').text(clientData.phone || '-');
        $('#view_email').text(clientData.email || '-');
        $('#view_whatsapp').text(clientData.whatsapp || '-');

        // عرض الحالة بألوان
        let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
        if (clientData.status === 'نشط') {
            statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
        } else {
            statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
        }
        statusHtml += ' display: inline-block;">';
        statusHtml += '<i class="fas ' + (clientData.status === 'نشط' ? 'fa-check-circle' : 'fa-times-circle') + '" style="margin-left: 6px;"></i> ' + clientData.status + '</span>';
        $('#view_status').html(statusHtml);

        $('#view_created_by').text(clientData.created || '-');

        // تحضير زر التعديل
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

    // إغلاق Modal عرض العميل
    function closeViewModal() {
        $('#viewClientModal').fadeOut(300);
    }

    // إغلاق عند الضغط خارج Modal الإضافة والعرض
    $(window).on('click', function (e) {
        if (e.target.id === 'addClientModal') {
            closeAddModal();
        }
        if (e.target.id === 'viewClientModal') {
            closeViewModal();
        }
    });

    // إغلاق عند الضغط على ESC للعرض
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewClientModal').is(':visible')) {
            closeViewModal();
        }
    });

    // التعامل مع زر التعديل من Modal العرض
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

        // ملء نموذج التعديل
        $('#edit_client_id').val(clientData.id);
        $('#edit_client_code').val(clientData.code);
        $('#edit_client_name').val(clientData.name);
        const entityValue = $.trim(clientData.entity || '');
        const sectorValue = $.trim(clientData.sector || '');
        $('#edit_entity_type').val(entityValue);
        if ($('#edit_entity_type').val() !== entityValue) {
            $('#edit_entity_type option').filter(function () {
                return $.trim($(this).text()) === entityValue;
            }).prop('selected', true);
        }
        $('#edit_sector_category').val(sectorValue);
        if ($('#edit_sector_category').val() !== sectorValue) {
            $('#edit_sector_category option').filter(function () {
                return $.trim($(this).text()) === sectorValue;
            }).prop('selected', true);
        }
        $('#edit_phone').val(clientData.phone);
        $('#edit_email').val(clientData.email);
        $('#edit_whatsapp').val(clientData.whatsapp);
        $('#edit_status').val(clientData.status);

        $('#editClientModal').fadeIn(300);
    });

    // إغلاق عند الضغط على ESC للإضافة
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#addClientModal').is(':visible')) {
            closeAddModal();
        }
    });

    // معالجة إرسال نموذج الإضافة
    $('#addClientForm').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize() + '&action=create';

        $.ajax({
            url: 'clients.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    closeAddModal();
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('حدث خطأ أثناء إضافة العميل');
            }
        });
    });

    // فتح Modal التعديل
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

        $('#edit_client_id').val(clientData.id);
        $('#edit_client_code').val(clientData.code);
        $('#edit_client_name').val(clientData.name);
        $('#edit_entity_type').val(clientData.entity);
        $('#edit_sector_category').val(clientData.sector);
        $('#edit_phone').val(clientData.phone);
        $('#edit_email').val(clientData.email);
        $('#edit_whatsapp').val(clientData.whatsapp);
        $('#edit_status').val(clientData.status);

        $('#editClientModal').fadeIn(300);
    });

    // إغلاق Modal التعديل
    function closeEditModal() {
        $('#editClientModal').fadeOut(300);
    }

    // إغلاق عند الضغط خارج Modal التعديل
    $(window).on('click', function (e) {
        if (e.target.id === 'editClientModal') {
            closeEditModal();
        }
    });

    // إغلاق عند الضغط على ESC للتعديل
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#editClientModal').is(':visible')) {
            closeEditModal();
        }
    });

    // معالجة إرسال نموذج التعديل
    $('#editClientForm').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize() + '&action=update';

        $.ajax({
            url: 'clients.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    closeEditModal();
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('حدث خطأ أثناء تعديل العميل');
            }
        });
    });
</script>

<script>
    // فتح Modal الاستيراد
    $('#openImportModal').on('click', function () {
        $('#importExcelModal').fadeIn(300);
    });

    // إغلاق Modal الاستيراد
    function closeImportModal() {
        $('#importExcelModal').fadeOut(300);
        $('#importExcelForm')[0].reset();
        $('#importProgress').hide();
        $('#importResult').hide();
    }

    // إغلاق عند الضغط خارج Modal
    $(window).on('click', function (e) {
        if (e.target.id === 'importExcelModal') {
            closeImportModal();
        }
    });

    // معالجة رفع ملف Excel
    $('#importExcelForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('الرجاء اختيار ملف Excel');
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

                let resultHtml = '<div style="padding: 20px; border-radius: 12px; ';

                if (response.success) {
                    resultHtml += 'background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-right: 4px solid #28a745;">';
                    resultHtml += '<h6 style="color: #155724; font-weight: 700; margin-bottom: 10px;"><i class="fas fa-check-circle"></i> تم الاستيراد بنجاح!</h6>';
                    resultHtml += '<p style="color: #155724; margin: 5px 0;">✅ تم إضافة: <strong>' + response.added + '</strong> عميل</p>';
                    if (response.skipped > 0) {
                        resultHtml += '<p style="color: #856404; margin: 5px 0;">⚠️ تم تخطي: <strong>' + response.skipped + '</strong> عميل (مكرر)</p>';
                    }
                    if (response.errors.length > 0) {
                        resultHtml += '<p style="color: #721c24; margin: 10px 0 5px 0;"><strong>الأخطاء:</strong></p><ul style="margin: 0; padding-right: 20px;">';
                        response.errors.forEach(function (error) {
                            resultHtml += '<li style="color: #721c24;">' + error + '</li>';
                        });
                        resultHtml += '</ul>';
                    }
                    resultHtml += '</div>';

                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    resultHtml += 'background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-right: 4px solid #dc3545;">';
                    resultHtml += '<h6 style="color: #721c24; font-weight: 700; margin-bottom: 10px;"><i class="fas fa-times-circle"></i> فشل الاستيراد</h6>';
                    resultHtml += '<p style="color: #721c24; margin: 0;">' + response.message + '</p>';
                    resultHtml += '</div>';
                }

                $('#importResult').html(resultHtml).fadeIn(300);
            },
            error: function (xhr, status, error) {
                $('#importProgress').hide();

                let errorMsg = 'حدث خطأ أثناء رفع الملف. الرجاء المحاولة مرة أخرى.';

                // محاولة استخراج رسالة الخطأ من الاستجابة
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // إذا لم يكن JSON، استخدم الرسالة الافتراضية
                        errorMsg += '<br><small>تفاصيل الخطأ: ' + status + '</small>';
                    }
                }

                const errorHtml = '<div style="padding: 20px; border-radius: 12px; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-right: 4px solid #dc3545;">' +
                    '<h6 style="color: #721c24; font-weight: 700;"><i class="fas fa-times-circle"></i> حدث خطأ</h6>' +
                    '<p style="color: #721c24; margin: 0;">' + errorMsg + '</p>' +
                    '<p style="color: #721c24; margin: 10px 0 0 0; font-size: 12px;"><strong>نصائح:</strong></p>' +
                    '<ul style="color: #721c24; font-size: 12px; margin: 5px 0; padding-right: 20px;">' +
                    '<li>تأكد من أن الملف بصيغة .xlsx, .xls أو .csv</li>' +
                    '<li>تأكد من أن حجم الملف أقل من 5 ميجا</li>' +
                    '<li>تأكد من أن الملف يحتوي على بيانات صحيحة</li>' +
                    '<li>إذا كنت تستخدم Excel، جرب حفظ الملف كـ CSV</li>' +
                    '</ul></div>';
                $('#importResult').html(errorHtml).fadeIn(300);
            }
        });
    });

</script>

</body>

</html>