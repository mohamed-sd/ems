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
    $check_query = "SELECT id FROM company_clients WHERE client_code = '$client_code'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود العميل موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
    }
    
    $insert_query = "INSERT INTO company_clients 
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
    $check_query = "SELECT id FROM company_clients WHERE client_code = '$client_code' AND id != $client_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود العميل موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
    }
    
    $update_query = "UPDATE company_clients SET 
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
    
    // التحقق من عدم استخدام العميل في جدول operationproject
    $check_usage = mysqli_query($conn, "SELECT COUNT(*) as count FROM operationproject WHERE company_client_id = $delete_id");
    $usage = mysqli_fetch_assoc($check_usage);
    
    if ($usage['count'] > 0) {
        header("Location: view_clients.php?msg=لا+يمكن+حذف+العميل+لأنه+مستخدم+في+مشاريع+موجودة+❌");
        exit();
    } else {
        $delete_query = "DELETE FROM company_clients WHERE id = $delete_id";
        if (mysqli_query($conn, $delete_query)) {
            header("Location: view_clients.php?msg=تم+حذف+العميل+بنجاح+✅");
            exit();
        } else {
            header("Location: view_clients.php?msg=حدث+خطأ+أثناء+الحذف+❌");
            exit();
        }
    }
}

$page_title = "قائمة العملاء";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<style>
.main {
    margin-right: 10px;
    padding: 30px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
    font-family: 'Cairo', sans-serif;
}

.add-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 28px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    font-family: 'Cairo', sans-serif;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    color: white;
}

.success-message {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
    font-weight: 600;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    background: white;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px 12px;
    font-weight: 700;
    text-align: center;
    border: none;
    font-size: 14px;
    white-space: nowrap;
}

#clientsTable tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

#clientsTable tbody tr:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

#clientsTable tbody td {
    padding: 14px 12px;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
}

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
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.action-btn.view:hover {
    transform: translateY(-2px) scale(1.1);
}

.action-btn.edit {
    background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
    color: white;
}

.action-btn.edit:hover {
    transform: translateY(-2px) scale(1.1);
}

.action-btn.delete {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
}

.action-btn.delete:hover {
    transform: translateY(-2px) scale(1.1);
}

.dt-buttons {
    margin-bottom: 15px;
    display: flex;
    gap: 8px;
}

.dt-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border: none !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    font-family: 'Cairo', sans-serif !important;
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
    background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
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
    color: #2c3e50;
    font-size: 14px;
    font-family: 'Cairo', sans-serif;
}

.form-group-modal label i {
    margin-left: 8px;
    color: #f7b733;
}

.form-group-modal input,
.form-group-modal select {
    padding: 12px 16px;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f8f9fa;
    font-family: 'Cairo', sans-serif;
}

.form-group-modal input:focus,
.form-group-modal select:focus {
    outline: none;
    border-color: #f7b733;
    background: white;
    box-shadow: 0 0 0 4px rgba(247, 183, 51, 0.1);
}

.modal-footer {
    display: flex;
    gap: 15px;
    padding: 20px 25px;
    border-top: 1px solid #e1e8ed;
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
    background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(247, 183, 51, 0.3);
}

.btn-modal-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(247, 183, 51, 0.5);
}

.btn-modal-cancel {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
}

.btn-modal-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(149, 165, 166, 0.5);
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
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users"></i> قائمة العملاء</h1>
        <a href="javascript:void(0)" id="openAddModal" class="add-btn">
            <i class="fas fa-plus-circle"></i> إضافة عميل جديد
        </a>
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
                            <th><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-barcode"></i> كود العميل</th>
                            <th><i class="fas fa-user"></i> اسم العميل</th>
                            <th><i class="fas fa-building"></i> نوع الكيان</th>
                            <th><i class="fas fa-industry"></i> تصنيف القطاع</th>
                            <th><i class="fas fa-phone"></i> الهاتف</th>
                            <th><i class="fas fa-envelope"></i> البريد</th>
                            <th><i class="fab fa-whatsapp"></i> واتساب</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th><i class="fas fa-user-plus"></i> أضيف بواسطة</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT cc.*, u.name as creator_name 
                                  FROM company_clients cc 
                                  LEFT JOIN users u ON cc.created_by = u.id 
                                  ORDER BY cc.id DESC";
                        $result = mysqli_query($conn, $query);
                        $counter = 1;
                        
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $counter++ . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['client_code']) . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['client_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['entity_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['sector_category']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['whatsapp']) . "</td>";
                            
                            if ($row['status'] == 'نشط') {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> متوقف</span></td>";
                            }
                            
                            echo "<td><i class='fas fa-user' style='color:#667eea; margin-left:5px;'></i>" . ($row['creator_name'] ?? 'غير محدد') . "</td>";
                            echo "<td>
                                <div class='action-btns'>
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
                        <input type="text" id="add_client_code" name="client_code" required pattern="[A-Za-z0-9-_]+" placeholder="مثال: CL-001">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" id="add_client_name" name="client_name" required placeholder="أدخل اسم العميل">
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
                            <option value="شركة حكومية">شركة حكومية</option>
                            <option value="شركة خاصة">شركة خاصة</option>

// فتح Modal التعديل
$(document).on('click', '.editClientBtn', function() {
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

// إغلاق Modal
function closeEditModal() {
    $('#editClientModal').fadeOut(300);
}

// إغلاق عند الضغط خارج Modal
$(window).on('click', function(e) {
    if (e.target.id === 'editClientModal') {
        closeEditModal();
    }
});

// إغلاق عند الضغط على ESC
$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// معالجة إرسال نموذج التعديل
$('#editClientForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize() + '&action=update';
    
    $.ajax({
        url: 'view_clients.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                closeEditModal();
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء تعديل العميل');
        }
    });
});
                            <option value="جهة حكومية">جهة حكومية</option>
                            <option value="مؤسسة">مؤسسة</option>
                            <option value="فرد">فرد</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select id="edit_sector_category" name="sector_category" required>
                            <option value="">-- اختر تصنيف القطاع --</option>
                            <option value="النفط والغاز">النفط والغاز</option>
                            <option value="البنية التحتية">البنية التحتية</option>
                            <option value="الطرق والجسور">الطرق والجسور</option>
                            <option value="الإنشاءات">الإنشاءات</option>
                            <option value="التعدين">التعدين</option>
                            <option value="الزراعة">الزراعة</option>
                            <option value="الخدمات">الخدمات</option>
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
$(document).ready(function() {
    $('#clientsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: 'نسخ' },
            { extend: 'excel', text: 'تصدير Excel' },
            { extend: 'csv', text: 'تصدير CSV' },
            { extend: 'pdf', text: 'تصدير PDF' },
            { extend: 'print', text: 'طباعة' }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        }
    });
});

// فتح Modal الإضافة
$('#openAddModal').on('click', function() {
    $('#addClientModal').fadeIn(300);
});

// إغلاق Modal الإضافة
function closeAddModal() {
    $('#addClientModal').fadeOut(300);
    $('#addClientForm')[0].reset();
}

// إغلاق عند الضغط خارج Modal الإضافة
$(window).on('click', function(e) {
    if (e.target.id === 'addClientModal') {
        closeAddModal();
    }
});

// إغلاق عند الضغط على ESC للإضافة
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#addClientModal').is(':visible')) {
        closeAddModal();
    }
});

// معالجة إرسال نموذج الإضافة
$('#addClientForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize() + '&action=create';
    
    $.ajax({
        url: 'view_clients.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                closeAddModal();
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء إضافة العميل');
        }
    });
});

// فتح Modal التعديل
$(document).on('click', '.editClientBtn', function() {
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

// إغلاق Modal
function closeEditModal() {
    $('#editClientModal').fadeOut(300);
}

// إغلاق عند الضغط خارج Modal
$(window).on('click', function(e) {
    if (e.target.id === 'editClientModal') {
        closeEditModal();
    }
});

// إغلاق عند الضغط على ESC
$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// معالجة إرسال نموذج التعديل
$('#editClientForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize() + '&action=update';
    
    $.ajax({
        url: 'view_clients.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                closeEditModal();
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء تعديل العميل');
        }
    });
});
</script>

</body>
</html>
