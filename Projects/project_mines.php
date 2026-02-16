<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// التحقق من الصلاحية
if ($_SESSION['user']['role'] != "1" && $_SESSION['user']['role'] != "-1") {
    die("غير مصرح لك بالدخول لهذه الصفحة");
}

include '../config.php';

// الحصول على معرف المشروع من URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    header("Location: oprationprojects.php");
    exit();
}

// جلب بيانات المشروع
$project_query = "SELECT * FROM project WHERE id = $project_id LIMIT 1";
$project_result = mysqli_query($conn, $project_query);
$project = mysqli_fetch_assoc($project_result);

if (!$project) {
    die("المشروع غير موجود");
}

// معالجة إضافة منجم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    header('Content-Type: application/json; charset=utf-8');

    $mine_code = mysqli_real_escape_string($conn, trim($_POST['mine_code']));
    $mine_name = mysqli_real_escape_string($conn, trim($_POST['mine_name']));
    $manager_name = mysqli_real_escape_string($conn, trim($_POST['manager_name']));
    $mineral_type = mysqli_real_escape_string($conn, trim($_POST['mineral_type']));
    $mine_type = mysqli_real_escape_string($conn, $_POST['mine_type']);
    $mine_type_other = mysqli_real_escape_string($conn, trim($_POST['mine_type_other']));
    $ownership_type = mysqli_real_escape_string($conn, $_POST['ownership_type']);
    $ownership_type_other = mysqli_real_escape_string($conn, trim($_POST['ownership_type_other']));
    $mine_area = !empty($_POST['mine_area']) ? floatval($_POST['mine_area']) : null;
    $mine_area_unit = mysqli_real_escape_string($conn, $_POST['mine_area_unit']);
    $mining_depth = !empty($_POST['mining_depth']) ? floatval($_POST['mining_depth']) : null;
    $contract_nature = mysqli_real_escape_string($conn, $_POST['contract_nature']);
    $status = intval($_POST['status']);
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));
    $created_by = $_SESSION['user']['id'];

    // التحقق من عدم تكرار كود المنجم
    $check_query = "SELECT id FROM mines WHERE mine_code = '$mine_code'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود المنجم موجود مسبقاً']);
        exit();
    }

    $mine_area_value = $mine_area !== null ? "'$mine_area'" : "NULL";
    $mining_depth_value = $mining_depth !== null ? "'$mining_depth'" : "NULL";

    $insert_query = "INSERT INTO mines 
        (project_id, mine_code, mine_name, manager_name, mineral_type, mine_type, mine_type_other, 
         ownership_type, ownership_type_other, mine_area, mine_area_unit, mining_depth, contract_nature, 
         status, notes, created_by) 
        VALUES 
        ($project_id, '$mine_code', '$mine_name', '$manager_name', '$mineral_type', '$mine_type', 
         '$mine_type_other', '$ownership_type', '$ownership_type_other', $mine_area_value, '$mine_area_unit', 
         $mining_depth_value, '$contract_nature', $status, '$notes', $created_by)";

    if (mysqli_query($conn, $insert_query)) {
        echo json_encode(['success' => true, 'message' => 'تم إضافة المنجم بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . mysqli_error($conn)]);
    }
    exit();
}

// معالجة تعديل منجم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json; charset=utf-8');

    $mine_id = intval($_POST['mine_id']);
    $mine_code = mysqli_real_escape_string($conn, trim($_POST['mine_code']));
    $mine_name = mysqli_real_escape_string($conn, trim($_POST['mine_name']));
    $manager_name = mysqli_real_escape_string($conn, trim($_POST['manager_name']));
    $mineral_type = mysqli_real_escape_string($conn, trim($_POST['mineral_type']));
    $mine_type = mysqli_real_escape_string($conn, $_POST['mine_type']);
    $mine_type_other = mysqli_real_escape_string($conn, trim($_POST['mine_type_other']));
    $ownership_type = mysqli_real_escape_string($conn, $_POST['ownership_type']);
    $ownership_type_other = mysqli_real_escape_string($conn, trim($_POST['ownership_type_other']));
    $mine_area = !empty($_POST['mine_area']) ? floatval($_POST['mine_area']) : null;
    $mine_area_unit = mysqli_real_escape_string($conn, $_POST['mine_area_unit']);
    $mining_depth = !empty($_POST['mining_depth']) ? floatval($_POST['mining_depth']) : null;
    $contract_nature = mysqli_real_escape_string($conn, $_POST['contract_nature']);
    $status = intval($_POST['status']);
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));

    // التحقق من عدم تكرار كود المنجم
    $check_query = "SELECT id FROM mines WHERE mine_code = '$mine_code' AND id != $mine_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'كود المنجم موجود مسبقاً']);
        exit();
    }

    $mine_area_value = $mine_area !== null ? "'$mine_area'" : "NULL";
    $mining_depth_value = $mining_depth !== null ? "'$mining_depth'" : "NULL";

    $update_query = "UPDATE mines SET 
        mine_code = '$mine_code',
        mine_name = '$mine_name',
        manager_name = '$manager_name',
        mineral_type = '$mineral_type',
        mine_type = '$mine_type',
        mine_type_other = '$mine_type_other',
        ownership_type = '$ownership_type',
        ownership_type_other = '$ownership_type_other',
        mine_area = $mine_area_value,
        mine_area_unit = '$mine_area_unit',
        mining_depth = $mining_depth_value,
        contract_nature = '$contract_nature',
        status = $status,
        notes = '$notes'
        WHERE id = $mine_id AND project_id = $project_id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'تم تعديل المنجم بنجاح ✅']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . mysqli_error($conn)]);
    }
    exit();
}

// حذف منجم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $mine_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM mines WHERE id = $mine_id AND project_id = $project_id";

    if (mysqli_query($conn, $delete_query)) {
        echo "<script>alert('تم حذف المنجم بنجاح'); window.location.href='project_mines.php?project_id=$project_id';</script>";
    } else {
        echo "<script>alert('حدث خطأ أثناء الحذف'); window.location.href='project_mines.php?project_id=$project_id';</script>";
    }
    exit();
}

$page_title = "المناجم - " . $project['name'];
include '../inheader.php';
include '../insidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap');

    * {
        font-family: 'Cairo', sans-serif;
    }

    :root {
        --primary-color: #1a1a2e;
        --secondary-color: #16213e;
        --gold-color: #ffcc00;
        --text-color: #010326;
        --light-color: #f5f5f5;
        --border-color: #e0e0e0;
        --shadow-color: rgba(0, 0, 0, 0.1);
    }

    .main {
        /* margin-right: 10px; */
        padding: 30px;
        transition: margin 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
        background: var(--border-color);
        max-width: 100vw;
        overflow-x: hidden;
    }

    .sidebar.closed~.main {
        /* margin-right: 5px; */
    }

    @media (max-width: 1366px) {
        .main {
            /* margin-right: 280px; */
            padding: 20px 15px;
        }

        .sidebar.closed~.main {
            /* margin-right: 75px; */
        }
    }

    @media (max-width: 1024px) {
        .main {
            /* margin-right: 280px; */
            padding: 15px 10px;
        }

        .sidebar.closed~.main {
            /* margin-right: 75px; */
        }
    }

    @media (max-width: 768px) {
        .main {
            margin-right: 0 !important;
            padding: 20px 15px;
            padding-top: 80px;
        }
    }

    .mines-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        padding: 2rem;
        border-radius: 15px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px var(--shadow-color);
    }

    .mines-header h2 {
        margin: 0 0 0.5rem 0;
        font-size: 1.8rem;
    }

    .project-info {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        margin-top: 1rem;
        font-size: 0.95rem;
    }

    .project-info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-add-mine {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 0.8rem 2rem;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px var(--shadow-color);
        transition: all 0.3s ease;
    }

    .btn-add-mine:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px var(--shadow-color);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: #fff;
        margin: 2% auto;
        padding: 0;
        border-radius: 20px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s ease;
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
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 20px 20px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
    }

    .close {
        color: white;
        font-size: 2rem;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
        transition: transform 0.2s;
    }

    .close:hover {
        transform: scale(1.2);
    }

    .modal-body {
        padding: 2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
        font-size: 0.95rem;
    }

    .form-group label .required {
        color: #e74c3c;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 0.75rem;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: 'Cairo', sans-serif;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        margin-top: 1rem;
        transition: all 0.3s ease;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px var(--shadow-color);
    }

    .table-container {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px var(--shadow-color);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table thead {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
    }

    table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
    }

    table td {
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    table tbody tr:hover {
        background-color: #f8f9ff;
    }

    .badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-active {
        background: #d4edda;
        color: #155724;
    }

    .badge-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        margin: 0 0.25rem;
        transition: all 0.2s ease;
    }

    .btn-view {
        background: #292802;
        color: #fff;
    }

    .btn-edit {
        background: #3498db;
        color: white;
    }

    .btn-delete {
        background: #e74c3c;
        color: white;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    /* ================== View Modal ================== */
    .view-modal-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }

    .view-item {
        padding: 14px 16px;
        background: var(--light-color);
        border-radius: 12px;
        border-right: 3px solid var(--gold-color);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
        animation: fadeSlide 0.4s ease-out both;
    }

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

    .view-item-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--primary-color);
        word-break: break-word;
    }

    .view-item:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
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

    .btn-back {
        background: #95a5a6;
        color: white;
        padding: 0.7rem 1.5rem;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1rem;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }

    .conditional-field {
        display: none;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        font-weight: 600;
        animation: slideDown 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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

    .alert-success {
        background: #d4edda;
        color: #155724;
        border-right: 4px solid #28a745;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border-right: 4px solid #dc3545;
    }

    .action-btn.contracts {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--gold-color) 100%);
        color: white;
    }

    .action-btn.contracts:hover {
        transform: translateY(-2px) scale(1.15);
        box-shadow: 0 4px 12px var(--shadow-color);
    }
</style>

<div class="main">
    <a href="oprationprojects.php" class="btn-back">
        <i class="fas fa-arrow-right"></i> العودة للمشاريع
    </a>

    <div class="mines-header">
        <h2><i class="fas fa-mountain"></i> إدارة المناجم</h2>
        <div class="project-info">
            <div class="project-info-item">
                <i class="fas fa-id-badge"></i>
                <strong> العميل:</strong> <?php echo $project['client']; ?>
            </div>
            <div class="project-info-item">
                <i class="fas fa-project-diagram"></i>
                <strong>المشروع:</strong> <?php echo $project['name']; ?>
            </div>
            <div class="project-info-item">
                <i class="fas fa-code"></i>
                <strong>الكود:</strong> <?php echo $project['project_code']; ?>
            </div>
            <div class="project-info-item">
                <i class="fas fa-map-marker-alt"></i>
                <strong>الموقع:</strong> <?php echo $project['region'] . ' - ' . $project['state']; ?>
            </div>
        </div>
    </div>

    <button class="btn-add-mine" onclick="openModal()">
        <i class="fas fa-plus-circle"></i> إضافة منجم جديد
    </button>

    <!-- Alert Container -->
    <div id="alertContainer" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; min-width: 300px;"></div>

    <div id="table-container" style="background-color: #fff;;">
      <div class="card-body" style="padding: 2rem; overflow-x: auto;">
        <table id="minesTable" class="display nowrap" style="width:100%; margin-top: 20px;">
          <thead>
                <tr>
                    <th>#</th>
                    <th>كود المنجم</th>
                    <th>اسم المنجم</th>
                    <th>المدير</th>
                    <th>المعدن</th>
                    <th>نوع المنجم</th>
                    <th>المساحة</th>
                    <th>العمق (م)</th>
                    <th> عدد العقود </th>
                    <th> العقود </th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $mines_query = "SELECT * FROM mines WHERE project_id = $project_id ORDER BY created_at DESC";
                $mines_result = mysqli_query($conn, $mines_query);
                $counter = 1;

                while ($mine = mysqli_fetch_assoc($mines_result)) {
                    $status_badge = $mine['status'] == 1 ?
                        '<span class="badge badge-active">نشط</span>' :
                        '<span class="badge badge-inactive">غير نشط</span>';

                    $area_display = $mine['mine_area'] ?
                        number_format($mine['mine_area'], 2) . ' ' . $mine['mine_area_unit'] :
                        '-';

                    $depth_display = $mine['mining_depth'] ?
                        number_format($mine['mining_depth'], 2) . ' م' :
                        '-';

                    echo "<tr>";
                    echo "<td>{$counter}</td>";
                    echo "<td>{$mine['mine_code']}</td>";
                    echo "<td>{$mine['mine_name']}</td>";
                    echo "<td>" . ($mine['manager_name'] ?: '-') . "</td>";
                    echo "<td>" . ($mine['mineral_type'] ?: '-') . "</td>";
                    echo "<td>{$mine['mine_type']}</td>";
                    echo "<td>{$area_display}</td>";
                    echo "<td>{$depth_display}</td>";
                    // جلب عدد العقود المرتبطة بالمنجم  
                    $contracts_count_query = "SELECT COUNT(*) AS contract_count FROM contracts WHERE mine_id = " . $mine['id'];
                    $contracts_count_result = mysqli_query($conn, $contracts_count_query);
                    $contracts_count = mysqli_fetch_assoc($contracts_count_result)['contract_count'];
                    echo "<td>{$contracts_count}</td>"; 
                    echo "<td> 
                     <a href='../Contracts/contracts.php?id=" . $mine['id'] . "' 
                               class='action-btn contracts'
                               title='عرض عقود المنجم'>
                               <i class='fas fa-file-contract'></i>
                            </a>
                    </td>";
                    echo "<td>{$status_badge}</td>";
                    echo "<td>
                            <button class='btn-action btn-view' onclick='openViewModal(" . json_encode($mine) . ")'>
                                <i class='fas fa-eye'></i> 
                            </button>
                            <button class='btn-action btn-edit' onclick='editMine(" . json_encode($mine) . ")'>
                                <i class='fas fa-edit'></i> 
                            </button>
                            <button class='btn-action btn-delete' onclick='deleteMine({$mine['id']})'>
                                <i class='fas fa-trash'></i> 
                            </button>
                          </td>";
                    echo "</tr>";
                    $counter++;
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal عرض المنجم -->
<div id="viewMineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #292802 0%, #031027 100%);">
            <h5><i class="fas fa-eye"></i> عرض بيانات المنجم</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المنجم</div>
                    <div class="view-item-value" id="view_mine_code">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-mountain"></i> اسم المنجم</div>
                    <div class="view-item-value" id="view_mine_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> مدير المنجم</div>
                    <div class="view-item-value" id="view_manager_name">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-gem"></i> نوع المعدن</div>
                    <div class="view-item-value" id="view_mineral_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> نوع المنجم</div>
                    <div class="view-item-value" id="view_mine_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> تفاصيل نوع المنجم</div>
                    <div class="view-item-value" id="view_mine_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> نوع الملكية</div>
                    <div class="view-item-value" id="view_ownership_type">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-info-circle"></i> تفاصيل نوع الملكية</div>
                    <div class="view-item-value" id="view_ownership_type_other">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-ruler-combined"></i> مساحة المنجم</div>
                    <div class="view-item-value" id="view_mine_area">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-arrows-alt-v"></i> عمق التعدين</div>
                    <div class="view-item-value" id="view_mining_depth">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-signature"></i> طبيعة التعاقد</div>
                    <div class="view-item-value" id="view_contract_nature">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-sticky-note"></i> ملاحظات</div>
                    <div class="view-item-value" id="view_notes">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 0 25px 25px;">
            <a id="view_contracts_btn" class="btn-action" style="background: #1a1a2e; color: #fff; text-decoration: none;">
                <i class="fas fa-file-contract"></i> عقودات المنجم
            </a>
            <button type="button" class="btn-action btn-edit" onclick="openEditFromView()">
                <i class="fas fa-edit"></i> تعديل المنجم
            </button>
            <button type="button" class="btn-action btn-delete" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="mineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">إضافة منجم جديد</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="mineForm">
                <input type="hidden" id="mine_id" name="mine_id">
                <input type="hidden" id="action" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label>كود/رمز المنجم <span class="required">*</span></label>
                        <input type="text" id="mine_code" name="mine_code" required>
                    </div>

                    <div class="form-group">
                        <label>اسم المنجم <span class="required">*</span></label>
                        <input type="text" id="mine_name" name="mine_name" required>
                    </div>

                    <div class="form-group">
                        <label>اسم مدير المنجم</label>
                        <input type="text" id="manager_name" name="manager_name">
                    </div>

                    <div class="form-group">
                        <label>نوع المعدن</label>
                        <input type="text" id="mineral_type" name="mineral_type" placeholder="مثال: ذهب، فضة، نحاس">
                    </div>

                    <div class="form-group">
                        <label>نوع المنجم <span class="required">*</span></label>
                        <select id="mine_type" name="mine_type" required onchange="toggleOtherField('mine_type')">
                            <option value="">-- اختر --</option>
                            <option value="حفرة مفتوحة">حفرة مفتوحة</option>
                            <option value="تحت أرضي">تحت أرضي</option>
                            <option value="آبار">آبار</option>
                            <option value="مهجور">مهجور</option>
                            <option value="مجمع معالجة/تركيز">مجمع معالجة/تركيز</option>
                            <option value="موقع تخزين/مستودع">موقع تخزين/مستودع</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="mine_type_other_div">
                        <label>تفاصيل نوع المنجم</label>
                        <input type="text" id="mine_type_other" name="mine_type_other">
                    </div>

                    <div class="form-group">
                        <label>نوع الملكية <span class="required">*</span></label>
                        <select id="ownership_type" name="ownership_type" required
                            onchange="toggleOtherField('ownership_type')">
                            <option value="">-- اختر --</option>
                            <option value="تعدين أهلي/تقليدي">تعدين أهلي/تقليدي</option>
                            <option value="شركة سودانية خاصة">شركة سودانية خاصة</option>
                            <option value="شركة حكومية/قطاع عام">شركة حكومية/قطاع عام</option>
                            <option value="شركة أجنبية">شركة أجنبية</option>
                            <option value="مشروع مشترك (سوداني-أجنبي)">مشروع مشترك (سوداني-أجنبي)</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="ownership_type_other_div">
                        <label>تفاصيل نوع الملكية</label>
                        <input type="text" id="ownership_type_other" name="ownership_type_other">
                    </div>

                    <div class="form-group">
                        <label>مساحة المنجم</label>
                        <input type="number" step="0.01" id="mine_area" name="mine_area" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>وحدة قياس المساحة</label>
                        <select id="mine_area_unit" name="mine_area_unit">
                            <option value="هكتار">هكتار</option>
                            <option value="كم²">كم²</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>عمق التعدين (متر)</label>
                        <input type="number" step="0.01" id="mining_depth" name="mining_depth" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>طبيعة التعاقد</label>
                        <select id="contract_nature" name="contract_nature">
                            <option value="">-- اختر --</option>
                            <option value="موظف مباشر لدى المالك">موظف مباشر لدى المالك</option>
                            <option value="مقاول/شركة مقاولات">مقاول/شركة مقاولات</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>الحالة <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="1">نشط</option>
                            <option value="0">غير نشط</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>ملاحظات إضافية</label>
                    <textarea id="notes" name="notes" placeholder="أي معلومات إضافية عن المنجم..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> حفظ البيانات
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        $('#minesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });
    });

    function openModal() {
        document.getElementById('mineModal').style.display = 'block';
        document.getElementById('modalTitle').textContent = 'إضافة منجم جديد';
        document.getElementById('mineForm').reset();
        document.getElementById('action').value = 'create';
        document.getElementById('mine_id').value = '';
        hideConditionalFields();
    }

    function closeModal() {
        document.getElementById('mineModal').style.display = 'none';
    }

    function editMine(mine) {
        document.getElementById('mineModal').style.display = 'block';
        document.getElementById('modalTitle').textContent = 'تعديل بيانات المنجم';
        document.getElementById('action').value = 'update';

        document.getElementById('mine_id').value = mine.id;
        document.getElementById('mine_code').value = mine.mine_code;
        document.getElementById('mine_name').value = mine.mine_name;
        document.getElementById('manager_name').value = mine.manager_name || '';
        document.getElementById('mineral_type').value = mine.mineral_type || '';
        document.getElementById('mine_type').value = mine.mine_type;
        document.getElementById('mine_type_other').value = mine.mine_type_other || '';
        document.getElementById('ownership_type').value = mine.ownership_type;
        document.getElementById('ownership_type_other').value = mine.ownership_type_other || '';
        document.getElementById('mine_area').value = mine.mine_area || '';
        document.getElementById('mine_area_unit').value = mine.mine_area_unit;
        document.getElementById('mining_depth').value = mine.mining_depth || '';
        document.getElementById('contract_nature').value = mine.contract_nature || '';
        document.getElementById('status').value = mine.status;
        document.getElementById('notes').value = mine.notes || '';

        toggleOtherField('mine_type');
        toggleOtherField('ownership_type');
    }

    function openViewModal(mine) {
        window.currentViewMine = mine;
        document.getElementById('view_mine_code').textContent = mine.mine_code || '-';
        document.getElementById('view_mine_name').textContent = mine.mine_name || '-';
        document.getElementById('view_manager_name').textContent = mine.manager_name || '-';
        document.getElementById('view_mineral_type').textContent = mine.mineral_type || '-';
        document.getElementById('view_mine_type').textContent = mine.mine_type || '-';
        document.getElementById('view_mine_type_other').textContent = mine.mine_type_other || '-';
        document.getElementById('view_ownership_type').textContent = mine.ownership_type || '-';
        document.getElementById('view_ownership_type_other').textContent = mine.ownership_type_other || '-';

        const areaText = mine.mine_area ? `${parseFloat(mine.mine_area).toFixed(2)} ${mine.mine_area_unit || ''}` : '-';
        const depthText = mine.mining_depth ? `${parseFloat(mine.mining_depth).toFixed(2)} م` : '-';

        document.getElementById('view_mine_area').textContent = areaText.trim() || '-';
        document.getElementById('view_mining_depth').textContent = depthText;
        document.getElementById('view_contract_nature').textContent = mine.contract_nature || '-';
        document.getElementById('view_status').textContent = (String(mine.status) === '1') ? 'نشط' : 'غير نشط';
        document.getElementById('view_notes').textContent = mine.notes || '-';

        const contractsBtn = document.getElementById('view_contracts_btn');
        if (contractsBtn) {
            contractsBtn.href = '../Contracts/contracts.php?id=' + mine.id;
        }

        $('#viewMineModal').fadeIn(300);
    }

    function openEditFromView() {
        if (window.currentViewMine) {
            closeViewModal();
            editMine(window.currentViewMine);
        }
    }

    function closeViewModal() {
        $('#viewMineModal').fadeOut(300);
    }

    function deleteMine(id) {
        if (confirm('هل أنت متأكد من حذف هذا المنجم؟')) {
            window.location.href = 'project_mines.php?project_id=<?php echo $project_id; ?>&delete=' + id;
        }
    }

    function toggleOtherField(fieldType) {
        const select = document.getElementById(fieldType);
        const otherDiv = document.getElementById(fieldType + '_other_div');

        if (select.value === 'أخرى') {
            otherDiv.style.display = 'block';
        } else {
            otherDiv.style.display = 'none';
        }
    }

    function hideConditionalFields() {
        document.querySelectorAll('.conditional-field').forEach(field => {
            field.style.display = 'none';
        });
    }

    document.getElementById('mineForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        
        console.log('Form data being sent:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        fetch('project_mines.php?project_id=<?php echo $project_id; ?>', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    showAlert(data.message, data.success ? 'success' : 'error');

                    if (data.success) {
                        closeModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } catch (error) {
                    console.error('JSON parse error:', error);
                    showAlert('خطأ في معالجة الاستجابة: ' + text.substring(0, 100), 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showAlert('حدث خطأ في الاتصال: ' + error.message, 'error');
            });
    });

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;

        const container = document.getElementById('alertContainer');
        container.innerHTML = '';
        container.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    window.onclick = function (event) {
        const modal = document.getElementById('mineModal');
        const viewModal = document.getElementById('viewMineModal');
        if (event.target == modal) {
            closeModal();
        }
        if (event.target == viewModal) {
            closeViewModal();
        }
    }
</script>

</body>

</html>