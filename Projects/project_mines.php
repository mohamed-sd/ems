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
    header("Location: view_projects.php");
    exit();
}

// جلب بيانات المشروع
$project_query = "SELECT * FROM company_project WHERE id = $project_id LIMIT 1";
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

$page_title = "المناجم - " . $project['project_name'];
include '../inheader.php';
include '../insidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap');

    * {
        font-family: 'Cairo', sans-serif;
    }

    .mines-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        border-radius: 15px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
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
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 0.8rem 2rem;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
        transition: all 0.3s ease;
    }

    .btn-add-mine:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .table-container {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
</style>

<div class="main">
    <a href="view_projects.php" class="btn-back">
        <i class="fas fa-arrow-right"></i> العودة للمشاريع
    </a>

    <div class="mines-header">
        <h2><i class="fas fa-mountain"></i> إدارة المناجم</h2>
        <div class="project-info">
            <div class="project-info-item">
                <i class="fas fa-project-diagram"></i>
                <strong>المشروع:</strong> <?php echo $project['project_name']; ?>
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

    <div id="alertContainer"></div>

    <div class="table-container">
        <table id="minesTable" class="display responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>كود المنجم</th>
                    <th>اسم المنجم</th>
                    <th>المدير</th>
                    <th>المعدن</th>
                    <th>نوع المنجم</th>
                    <th>نوع الملكية</th>
                    <th>المساحة</th>
                    <th>العمق (م)</th>
                    <th>طبيعة التعاقد</th>
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
                    echo "<td>{$mine['ownership_type']}</td>";
                    echo "<td>{$area_display}</td>";
                    echo "<td>{$depth_display}</td>";
                    echo "<td>" . ($mine['contract_nature'] ?: '-') . "</td>";
                    echo "<td>{$status_badge}</td>";
                    echo "<td>
                            <button class='btn-action btn-edit' onclick='editMine(" . json_encode($mine) . ")'>
                                <i class='fas fa-edit'></i> تعديل
                            </button>
                            <button class='btn-action btn-delete' onclick='deleteMine({$mine['id']})'>
                                <i class='fas fa-trash'></i> حذف
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
                        <select id="ownership_type" name="ownership_type" required onchange="toggleOtherField('ownership_type')">
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
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(document).ready(function() {
    $('#minesTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        responsive: true,
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

document.getElementById('mineForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('project_mines.php?project_id=<?php echo $project_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'error');
        
        if (data.success) {
            closeModal();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        showAlert('حدث خطأ في الاتصال', 'error');
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

window.onclick = function(event) {
    const modal = document.getElementById('mineModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

</body>
</html>
