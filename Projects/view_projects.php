<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// التحقق من صلاحية مدير المشاريع (role = 1 أو admin = -1)
if ($_SESSION['user']['role'] != "1" && $_SESSION['user']['role'] != "-1") {
    die("غير مصرح لك بالدخول لهذه الصفحة");
}

include '../config.php';

// معالجة إضافة مشروع جديد عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    header('Content-Type: application/json; charset=utf-8');

    $project_code = mysqli_real_escape_string($conn, trim($_POST['project_code']));
    $project_name = mysqli_real_escape_string($conn, trim($_POST['project_name']));
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $sub_sector = mysqli_real_escape_string($conn, trim($_POST['sub_sector']));
    $state = mysqli_real_escape_string($conn, trim($_POST['state']));
    $region = mysqli_real_escape_string($conn, trim($_POST['region']));
    $nearest_market = mysqli_real_escape_string($conn, trim($_POST['nearest_market']));
    $latitude = mysqli_real_escape_string($conn, trim($_POST['latitude']));
    $longitude = mysqli_real_escape_string($conn, trim($_POST['longitude']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['user']['id'];

    // التحقق من عدم تكرار كود المشروع
    $check_query = "SELECT id FROM company_project WHERE project_code = '$project_code'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود المشروع موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
    }

    $insert_query = "INSERT INTO company_project 
        (project_code, project_name, category, sub_sector, state, region, nearest_market, latitude, longitude, status, created_by) 
        VALUES 
        ('$project_code', '$project_name', '$category', '$sub_sector', '$state', '$region', '$nearest_market', '$latitude', '$longitude', '$status', '$created_by')";

    if (mysqli_query($conn, $insert_query)) {
        echo json_encode(['success' => true, 'message' => 'تم إضافة المشروع بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء إضافة المشروع']);
    }
    exit();
}

// معالجة تعديل المشروع عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json; charset=utf-8');

    $project_id = intval($_POST['project_id']);
    $project_code = mysqli_real_escape_string($conn, trim($_POST['project_code']));
    $project_name = mysqli_real_escape_string($conn, trim($_POST['project_name']));
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $sub_sector = mysqli_real_escape_string($conn, trim($_POST['sub_sector']));
    $state = mysqli_real_escape_string($conn, trim($_POST['state']));
    $region = mysqli_real_escape_string($conn, trim($_POST['region']));
    $nearest_market = mysqli_real_escape_string($conn, trim($_POST['nearest_market']));
    $latitude = mysqli_real_escape_string($conn, trim($_POST['latitude']));
    $longitude = mysqli_real_escape_string($conn, trim($_POST['longitude']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // التحقق من عدم تكرار كود المشروع (مع استثناء المشروع الحالي)
    $check_query = "SELECT id FROM company_project WHERE project_code = '$project_code' AND id != $project_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود المشروع موجود مسبقاً، يرجى استخدام كود آخر']);
        exit();
    }

    $update_query = "UPDATE company_project SET 
        project_code = '$project_code',
        project_name = '$project_name',
        category = '$category',
        sub_sector = '$sub_sector',
        state = '$state',
        region = '$region',
        nearest_market = '$nearest_market',
        latitude = '$latitude',
        longitude = '$longitude',
        status = '$status'
        WHERE id = $project_id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'تم تعديل المشروع بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تعديل المشروع']);
    }
    exit();
}

// حذف مشروع
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM company_project WHERE id = $id");
    header("Location: view_projects.php?msg=تم+حذف+المشروع+بنجاح");
    exit;
}

// جلب المشاريع مع عدد المناجم
$query = "SELECT cp.*, 
         (SELECT COUNT(*) FROM mines WHERE project_id = cp.id) as mines_count 
         FROM company_project cp 
         ORDER BY cp.id DESC";
$result = mysqli_query($conn, $query);

$page_title = "إيكوبيشن | قائمة المشاريع التشغيلية";
include("../inheader.php");
include('../insidebar.php');
?>

<style>
    :root {
        --primary-color: #01072a;
        --secondary-color: #e2ae03;
        --dark-color: #2d2b22;
        --light-color: #f5f5f5;
        --border-color: #e0e0e0;
        --text-color: #010326;
        --gold-color: #debf0f;
        --shadow-color: rgba(0, 0, 0, 0.1);
    }

    /* Modern Projects List Page Styling */
    .main {
        width: calc(100%-250px);
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

    .page-header h1 {
        color: var(--primary-color);
        font-size: 20px;
        font-weight: 900;
    }

    .btn-add {
        background: var(--gold-color);
        color: var(--primary-color);
        padding: 12px 30px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        font-family: 'Cairo', sans-serif;
    }

    .btn-add:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
    }

    @media (max-width: 768px) {
        .page-header {
            padding: 20px;
            border-radius: 15px;
            flex-direction: column;
        }

        .page-header h1 {
            font-size: 22px;
        }

        .btn-add {
            width: 100%;
            justify-content: center;
        }
    }

    /* Alert Messages */
    .alert {
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        font-family: 'Cairo', sans-serif;
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

    .alert-success {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border: none;
    }

    /* Card Styling */
    .card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .card-body {
        padding: 25px;
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
        padding: 15px;
        text-align: center;
        font-weight: 600;
        border: none;
        font-size: 15px;
    }

    table.dataTable thead th:first-child {
        border-radius: 10px 0 0 10px;
    }

    table.dataTable thead th:last-child {
        border-radius: 0 10px 10px 0;
    }

    table.dataTable tbody tr {
        background: rgba(255, 255, 255, 0.7);
        transition: all 0.3s ease;
    }

    table.dataTable tbody tr:hover {
        background: rgba(222, 191, 15, 0.1);
        transform: scale(1.01);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    table.dataTable tbody td {
        padding: 15px;
        text-align: center;
        border: none;
        vertical-align: middle;
        font-size: 14px;
    }

    /* Status Badges */
    .status-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        display: inline-block;
    }

    .status-active {
        background: green;
        color: white;
    }

    .status-paused {
        background: gray;
        color: white;
    }

    .status-completed {
        background: goldenrod;
        color: white;
    }

    /* Action Buttons */
    .action-btns {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 15px;
        color: white;
    }

    .action-btn.view {
        background: linear-gradient(135deg, var(--gold-color) 0%, var(--secondary-color) 100%);
        color: white;
    }

    .action-btn.view:hover {
        transform: translateY(-2px) scale(1.15);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .action-btn.edit {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
    }

    .action-btn.edit:hover {
        transform: translateY(-2px) scale(1.15);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .action-btn.delete {
        background: linear-gradient(135deg, #d64545 0%, #b03a3a 100%);
        color: white;
    }

    .action-btn.delete:hover {
        transform: translateY(-2px) scale(1.15);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Mines Count Link */
    .mines-count-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #8e44ad 0%, #3498db 100%);
        color: white;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(142, 68, 173, 0.3);
    }

    .mines-count-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(142, 68, 173, 0.5);
        color: white;
    }

    .mines-count-badge {
        background: rgba(255, 255, 255, 0.3);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
    }

    .mines-count-link i {
        font-size: 16px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .card-body {
            padding: 15px;
            overflow-x: auto;
        }

        table.dataTable {
            font-size: 12px;
        }

        table.dataTable thead th,
        table.dataTable tbody td {
            padding: 10px 5px;
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
        margin: 2% auto;
        padding: 0;
        border-radius: 16px;
        width: 90%;
        max-width: 1000px;
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
    }

    .form-grid-modal {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .form-group-modal {
        display: flex;
        flex-direction: column;
    }

    .form-group-modal label {
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-color);
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
        box-shadow: 0 0 0 4px rgba(222, 191, 15, 0.1);
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
        box-shadow: 0 4px 15px rgba(222, 191, 15, 0.3);
    }

    .btn-modal-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(222, 191, 15, 0.5);
    }

    .btn-modal-cancel {
        background: linear-gradient(135deg, var(--text-color) 0%, var(--dark-color) 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(45, 43, 34, 0.3);
    }

    .btn-modal-cancel:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(45, 43, 34, 0.5);
    }

    /* View Modal Styles */
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

    @media (max-width: 768px) {
        .form-grid-modal {
            grid-template-columns: 1fr;
        }

        .view-modal-body {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
            margin: 5% auto;
        }
    }
</style>


<div class="main">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-list"></i> قائمة مشاريع الشركة </h1>
            <a href="javascript:void(0)" id="openAddModal" class="btn-add">
                <i class="fas fa-plus-circle"></i>
                إضافة مشروع جديد
            </a>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo urldecode($_GET['msg']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Projects Table -->
        <div class="card">
            <div class="card-body">
                <table id="projectsTable" class="display table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>كود المشروع</th>
                            <th>اسم المشروع</th>
                            <th>التصنيف</th>
                            <th>القطاع الفرعي</th>
                            <th>الولاية</th>
                            <th>المنطقة</th>
                            <th>عدد المناجم</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                            $status_class = 'status-active';
                            $status_text = $row['status'];
                            if ($row['status'] == 'متوقف')
                                $status_class = 'status-paused';
                            if ($row['status'] == 'مكتمل')
                                $status_class = 'status-completed';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['project_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars(string: $row['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['sub_sector']); ?></td>
                                <td><?php echo htmlspecialchars($row['state']); ?></td>
                                <td><?php echo htmlspecialchars($row['region']); ?></td>
                                <td>
                                    <a href="project_mines.php?project_id=<?php echo $row['id']; ?>" 
                                       class="mines-count-link" 
                                       title="عرض المناجم">
                                        <i class="fas fa-mountain"></i>
                                        <span class="mines-count-badge"><?php echo $row['mines_count']; ?></span>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewProjectBtn" 
                                           data-id="<?php echo $row['id']; ?>"
                                           data-code="<?php echo htmlspecialchars($row['project_code']); ?>"
                                           data-name="<?php echo htmlspecialchars($row['project_name']); ?>"
                                           data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                           data-subsector="<?php echo htmlspecialchars($row['sub_sector']); ?>"
                                           data-state="<?php echo htmlspecialchars($row['state']); ?>"
                                           data-region="<?php echo htmlspecialchars($row['region']); ?>"
                                           data-market="<?php echo htmlspecialchars($row['nearest_market']); ?>"
                                           data-latitude="<?php echo htmlspecialchars($row['latitude']); ?>"
                                           data-longitude="<?php echo htmlspecialchars($row['longitude']); ?>"
                                           data-status="<?php echo $row['status']; ?>"
                                           title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="action-btn edit editProjectBtn"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($row['project_code']); ?>"
                                            data-name="<?php echo htmlspecialchars($row['project_name']); ?>"
                                            data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                            data-subsector="<?php echo htmlspecialchars($row['sub_sector']); ?>"
                                            data-state="<?php echo htmlspecialchars($row['state']); ?>"
                                            data-region="<?php echo htmlspecialchars($row['region']); ?>"
                                            data-market="<?php echo htmlspecialchars($row['nearest_market']); ?>"
                                            data-latitude="<?php echo htmlspecialchars($row['latitude']); ?>"
                                            data-longitude="<?php echo htmlspecialchars($row['longitude']); ?>"
                                            data-status="<?php echo $row['status']; ?>" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete"
                                            onclick="return confirm('هل أنت متأكد من حذف هذا المشروع؟')" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal عرض تفاصيل المشروع -->
<div id="viewProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
            <h5><i class="fas fa-eye"></i> عرض تفاصيل المشروع</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="view_project_id" value="">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المشروع</div>
                    <div class="view-item-value" id="view_project_code">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> اسم المشروع</div>
                    <div class="view-item-value" id="view_project_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-layer-group"></i> الفئة</div>
                    <div class="view-item-value" id="view_category">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> القطاع الفرعي</div>
                    <div class="view-item-value" id="view_sub_sector">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marked-alt"></i> الولاية</div>
                    <div class="view-item-value" id="view_state">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> المنطقة</div>
                    <div class="view-item-value" id="view_region">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-shopping-cart"></i> أقرب سوق</div>
                    <div class="view-item-value" id="view_nearest_market">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-compass"></i> خط العرض</div>
                    <div class="view-item-value" id="view_latitude">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-compass"></i> خط الطول</div>
                    <div class="view-item-value" id="view_longitude">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> حالة المشروع</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal" onclick="window.location.href='project_mines.php?project_id=' + document.getElementById('view_project_id').value" style="background: linear-gradient(135deg, #8e44ad 0%, #3498db 100%);">
                <i class="fas fa-mountain"></i> إدارة المناجم
            </button>
            <button type="button" class="btn-modal btn-modal-save editProjectBtn" id="viewEditBtn" style="background: linear-gradient(135deg, #1a1a2e 0%, #ffcc00 100%);">
                <i class="fas fa-edit"></i> تعديل البيانات
            </button>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- Modal إضافة مشروع جديد -->
<div id="addProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-plus-circle"></i> إضافة مشروع جديد</h5>
            <button class="close-modal" onclick="closeAddModal()">&times;</button>
        </div>
        <form id="addProjectForm">
            <div class="modal-body">
                <div class="form-grid-modal">
                    <div class="form-group-modal">
                        <label><i class="fas fa-barcode"></i> كود المشروع *</label>
                        <input type="text" id="add_project_code" name="project_code" required pattern="[A-Za-z0-9-_]+"
                            placeholder="مثال: PRJ-001">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-project-diagram"></i> اسم المشروع *</label>
                        <input type="text" id="add_project_name" name="project_name" required
                            placeholder="أدخل اسم المشروع">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-list"></i> تصنيف المشروع</label>
                        <select id="add_category" name="category" required>
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="طرق وجسور">طرق وجسور</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعي">زراعي</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="طاقة">طاقة</option>
                            <option value="إنشاءات">إنشاءات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-industry"></i> القطاع الفرعي</label>
                        <input type="text" id="add_sub_sector" name="sub_sector" placeholder="أدخل القطاع الفرعي">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-map-marker-alt"></i> الولاية *</label>
                        <input type="text" id="add_state" name="state" required placeholder="أدخل الولاية">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-map"></i> المنطقة</label>
                        <input type="text" id="add_region" name="region" placeholder="أدخل المنطقة">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-store"></i> أقرب سوق</label>
                        <input type="text" id="add_nearest_market" name="nearest_market" placeholder="أدخل أقرب سوق">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-globe"></i> خط العرض (Latitude)</label>
                        <input type="text" id="add_latitude" name="latitude"
                            pattern="^-?([0-9]{1,2}|1[0-7][0-9]|180)(\.[0-9]+)?$" placeholder="مثال: 15.5007">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-globe"></i> خط الطول (Longitude)</label>
                        <input type="text" id="add_longitude" name="longitude"
                            pattern="^-?([0-9]{1,2}|1[0-7][0-9]|180)(\.[0-9]+)?$" placeholder="مثال: 32.5599">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-toggle-on"></i> حالة المشروع *</label>
                        <select id="add_status" name="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                            <option value="مكتمل">مكتمل ✔</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save">
                    <i class="fas fa-save"></i> حفظ المشروع
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeAddModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل المشروع -->
<div id="editProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-edit"></i> تعديل بيانات المشروع</h5>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editProjectForm">
            <div class="modal-body">
                <input type="hidden" id="edit_project_id" name="project_id">
                <div class="form-grid-modal">
                    <div class="form-group-modal">
                        <label><i class="fas fa-barcode"></i> كود المشروع *</label>
                        <input type="text" id="edit_project_code" name="project_code" required pattern="[A-Za-z0-9-_]+">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-project-diagram"></i> اسم المشروع *</label>
                        <input type="text" id="edit_project_name" name="project_name" required>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-list"></i> تصنيف المشروع</label>
                        <select id="edit_category" name="category" required>
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="طرق وجسور">طرق وجسور</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعي">زراعي</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="طاقة">طاقة</option>
                            <option value="إنشاءات">إنشاءات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-industry"></i> القطاع الفرعي</label>
                        <input type="text" id="edit_sub_sector" name="sub_sector">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-map-marker-alt"></i> الولاية *</label>
                        <input type="text" id="edit_state" name="state" required>
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-map"></i> المنطقة</label>
                        <input type="text" id="edit_region" name="region">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-store"></i> أقرب سوق</label>
                        <input type="text" id="edit_nearest_market" name="nearest_market">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-globe"></i> خط العرض (Latitude)</label>
                        <input type="text" id="edit_latitude" name="latitude"
                            pattern="^-?([0-9]{1,2}|1[0-7][0-9]|180)(\.[0-9]+)?$">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-globe"></i> خط الطول (Longitude)</label>
                        <input type="text" id="edit_longitude" name="longitude"
                            pattern="^-?([0-9]{1,2}|1[0-7][0-9]|180)(\.[0-9]+)?$">
                    </div>

                    <div class="form-group-modal">
                        <label><i class="fas fa-toggle-on"></i> حالة المشروع *</label>
                        <select id="edit_status" name="status" required>
                            <option value="نشط">نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                            <option value="مكتمل">مكتمل ✔</option>
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

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    $(document).ready(function () {
        $('#projectsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> طباعة',
                    className: 'btn btn-info'
                }
            ],
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true
        });
    });

    // إزالة رسالة النجاح بعد 5 ثواني
    setTimeout(function () {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);

    // فتح Modal عرض المشروع
    $(document).on('click', '.viewProjectBtn', function () {
        const projectData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            category: $(this).data('category'),
            subsector: $(this).data('subsector'),
            state: $(this).data('state'),
            region: $(this).data('region'),
            market: $(this).data('market'),
            latitude: $(this).data('latitude'),
            longitude: $(this).data('longitude'),
            status: $(this).data('status')
        };

        // حفظ معرف المشروع في حقل مخفي
        $('#view_project_id').val(projectData.id);

        // ملء بيانات العرض
        $('#view_project_code').text(projectData.code || '-');
        $('#view_project_name').text(projectData.name || '-');
        $('#view_category').text(projectData.category || '-');
        $('#view_sub_sector').text(projectData.subsector || '-');
        $('#view_state').text(projectData.state || '-');
        $('#view_region').text(projectData.region || '-');
        $('#view_nearest_market').text(projectData.market || '-');
        $('#view_latitude').text(projectData.latitude || '-');
        $('#view_longitude').text(projectData.longitude || '-');

        // عرض الحالة بألوان
        let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
        if (projectData.status === 'نشط') {
            statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
        } else if (projectData.status === 'متوقف') {
            statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
        } else if (projectData.status === 'مكتمل') {
            statusHtml += ' background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);';
        } else {
            statusHtml += ' background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);';
        }
        statusHtml += ' display: inline-block;">';
        statusHtml += '<i class="fas fa-circle" style="margin-left: 6px; font-size: 8px;"></i> ' + projectData.status + '</span>';
        $('#view_status').html(statusHtml);

        // تحضير زر التعديل
        const editBtn = $('#viewEditBtn');
        editBtn.data('id', projectData.id);
        editBtn.data('code', projectData.code);
        editBtn.data('name', projectData.name);
        editBtn.data('category', projectData.category);
        editBtn.data('subsector', projectData.subsector);
        editBtn.data('state', projectData.state);
        editBtn.data('region', projectData.region);
        editBtn.data('market', projectData.market);
        editBtn.data('latitude', projectData.latitude);
        editBtn.data('longitude', projectData.longitude);
        editBtn.data('status', projectData.status);

        $('#viewProjectModal').fadeIn(300);
    });

    // إغلاق Modal عرض المشروع
    function closeViewModal() {
        $('#viewProjectModal').fadeOut(300);
    }

    // إغلاق عند الضغط خارج Modal العرض
    $(window).on('click', function (e) {
        if (e.target.id === 'viewProjectModal') {
            closeViewModal();
        }
    });

    // إغلاق عند الضغط على ESC للعرض
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewProjectModal').is(':visible')) {
            closeViewModal();
        }
    });

    // التعامل مع زر التعديل من Modal العرض
    $('#viewEditBtn').on('click', function () {
        const projectData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            category: $(this).data('category'),
            subsector: $(this).data('subsector'),
            state: $(this).data('state'),
            region: $(this).data('region'),
            market: $(this).data('market'),
            latitude: $(this).data('latitude'),
            longitude: $(this).data('longitude'),
            status: $(this).data('status')
        };

        closeViewModal();

        // ملء نموذج التعديل
        $('#edit_project_id').val(projectData.id);
        $('#edit_project_code').val(projectData.code);
        $('#edit_project_name').val(projectData.name);
        $('#edit_category').val(projectData.category);
        $('#edit_sub_sector').val(projectData.subsector);
        $('#edit_state').val(projectData.state);
        $('#edit_region').val(projectData.region);
        $('#edit_nearest_market').val(projectData.market);
        $('#edit_latitude').val(projectData.latitude);
        $('#edit_longitude').val(projectData.longitude);
        $('#edit_status').val(projectData.status);

        $('#editProjectModal').fadeIn(300);
    });

    // فتح Modal الإضافة
    $('#openAddModal').on('click', function () {
        $('#addProjectModal').fadeIn(300);
    });

    // إغلاق Modal الإضافة
    function closeAddModal() {
        $('#addProjectModal').fadeOut(300);
        $('#addProjectForm')[0].reset();
    }

    // إغلاق عند الضغط خارج Modal الإضافة
    $(window).on('click', function (e) {
        if (e.target.id === 'addProjectModal') {
            closeAddModal();
        }
    });

    // معالجة إرسال نموذج الإضافة
    $('#addProjectForm').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize() + '&action=create';

        $.ajax({
            url: 'view_projects.php',
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
                alert('حدث خطأ أثناء إضافة المشروع');
            }
        });
    });

    // فتح Modal التعديل
    $(document).on('click', '.editProjectBtn', function () {
        const projectData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            category: $(this).data('category'),
            subsector: $(this).data('subsector'),
            state: $(this).data('state'),
            region: $(this).data('region'),
            market: $(this).data('market'),
            latitude: $(this).data('latitude'),
            longitude: $(this).data('longitude'),
            status: $(this).data('status')
        };

        $('#edit_project_id').val(projectData.id);
        $('#edit_project_code').val(projectData.code);
        $('#edit_project_name').val(projectData.name);
        $('#edit_category').val(projectData.category);
        $('#edit_sub_sector').val(projectData.subsector);
        $('#edit_state').val(projectData.state);
        $('#edit_region').val(projectData.region);
        $('#edit_nearest_market').val(projectData.market);
        $('#edit_latitude').val(projectData.latitude);
        $('#edit_longitude').val(projectData.longitude);
        $('#edit_status').val(projectData.status);

        $('#editProjectModal').fadeIn(300);
    });

    // إغلاق Modal
    function closeEditModal() {
        $('#editProjectModal').fadeOut(300);
    }

    // إغلاق عند الضغط خارج Modal
    $(window).on('click', function (e) {
        if (e.target.id === 'editProjectModal') {
            closeEditModal();
        }
    });

    // إغلاق عند الضغط على ESC
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });

    // معالجة إرسال نموذج التعديل
    $('#editProjectForm').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize() + '&action=update';

        $.ajax({
            url: 'view_projects.php',
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
                alert('حدث خطأ أثناء تعديل المشروع');
            }
        });
    });
</script>

</body>

</html>