<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
$page_title = "إيكوبيشن | التشغيل ";
include("../inheader.php");
include '../config.php';

// التحقق من وجود مشروع محدد
$selected_project_id = 0;
$selected_project = null;

// التحقق من GET parameter أو SESSION
if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id'])) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
}

// إذا لم يتم تحديد مشروع، إعادة التوجيه لصفحة الاختيار
if ($selected_project_id == 0) {
    header("Location: select_project.php");
    exit();
}

// جلب بيانات المشروع المحدد
$project_query = "SELECT id, name, project_code, location FROM project WHERE id = $selected_project_id AND status = 1";
$project_result = mysqli_query($conn, $project_query);

if (mysqli_num_rows($project_result) > 0) {
    $selected_project = mysqli_fetch_assoc($project_result);
} else {
    // المشروع غير موجود أو غير نشط
    unset($_SESSION['operations_project_id']);
    header("Location: select_project.php");
    exit();
}

// انهاء خدمة من الموديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'end_service') {
    $operation_id = intval($_POST['operation_id']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if (!empty($operation_id) && !empty($end_date)) {
        $days_value = "NULL";
        $start_res = mysqli_query($conn, "SELECT `start` FROM operations WHERE id = $operation_id");
        if ($start_res && mysqli_num_rows($start_res) > 0) {
            $start_row = mysqli_fetch_assoc($start_res);
            $start_date = $start_row['start'];
            if (!empty($start_date)) {
                $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
                $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
                if ($start_dt && $end_dt) {
                    $diff = $start_dt->diff($end_dt);
                    $days_value = intval($diff->days);
                }
            }
        }

        mysqli_query(
            $conn,
            "UPDATE operations SET status = 0, `end` = '$end_date', reason = '$reason', days = $days_value WHERE id = $operation_id"
        );
    }

    // الحفاظ على المشروع المحدد بعد إنهاء الخدمة
    $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
    echo "<script>window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
    exit();
}

?>

<?php include('../insidebar.php'); ?>

<style>
    .page-header {
        background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%);
        padding: 1.5rem;
        border-radius: 18px;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        color: #fff;
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-title i {
        color: #e2ae03;
        font-size: 1.8rem;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.75);
        margin: 0.25rem 0 0 0;
        font-size: 0.95rem;
        font-weight: 600;
    }

    .page-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .page-actions .add {
        background: linear-gradient(135deg, #e2ae03 0%, #debf0f 100%);
        color: #01072a;
        padding: 0.7rem 1.2rem;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(226, 174, 3, 0.4);
        transition: all 0.3s ease;
    }

    .page-actions .add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(226, 174, 3, 0.5);
    }

    .contract-stats {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1.5rem;
        border-radius: 15px;
        margin-top: 1.5rem;
        border: 2px solid #e2ae03;
        display: none;
        animation: fadeInUp 0.5s ease;
    }

    .stats-title {
        color: #01072a;
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.8rem;
        border-bottom: 3px solid #e2ae03;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: #fff;
        padding: 1.2rem;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        border-color: #e2ae03;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .stat-card-value {
        font-size: 2rem;
        font-weight: 900;
        color: #01072a;
        margin: 0.5rem 0;
    }

    .stat-card-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 600;
    }

    .stat-card-icon {
        font-size: 2.5rem;
        color: #e2ae03;
        margin-bottom: 0.5rem;
    }

    .suppliers-table {
        width: 100%;
        margin-top: 1rem;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .suppliers-table thead th {
        background: #01072a;
        color: #fff;
        padding: 12px;
        text-align: center;
        font-weight: 600;
        border: none;
    }

    .suppliers-table tbody tr {
        background: #fff;
        transition: all 0.3s ease;
    }

    .suppliers-table tbody td {
        padding: 12px;
        text-align: center;
        border: none;
        font-weight: 500;
    }

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
    
    .project-header {
        background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%);
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        border: 2px solid #e2ae03;
    }
    
    .project-header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .project-title {
        color: #fff;
        font-size: 1.8rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .project-title i {
        color: #e2ae03;
    }
    
    .project-code-display {
        color: rgba(255, 255, 255, 0.8);
        font-size: 1rem;
        margin: 0.5rem 0 0 0;
        font-family: monospace;
    }
    
    .change-project-btn {
        background: linear-gradient(135deg, #e2ae03 0%, #debf0f 100%);
        color: #01072a;
        padding: 0.7rem 1.2rem;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(226, 174, 3, 0.4);
        transition: all 0.3s ease;
    }
    
    .change-project-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(226, 174, 3, 0.5);
        text-decoration: none;
        color: #01072a;
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
            <a href="select_project.php" class="change-project-btn">
                <i class="fas fa-exchange-alt"></i>
                تغيير المشروع
            </a>
        </div>
    </div>
    
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fas fa-cogs"></i> إدارة التشغيل</h1>
            <p class="page-subtitle">تنظيم تشغيل المعدات وربطها بالمشاريع والمناجم والعقود</p>
        </div>
        <div class="page-actions">
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fa fa-plus"></i> اضافة تشغيل
            </a>
        </div>
    </div>

    <!-- فورم إضافة تشغيل -->
    <form id="projectForm" action="" method="post" style="display:none; margin-top:20px;">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"> اضافة/ تعديل تشغيل آلية </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <!-- المشروع مخفي لأنه محدد مسبقاً -->
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $selected_project_id; ?>">

                    <!-- المناجم -->
                    <select name="mine_id" id="mine_id" required>
                        <option value="">-- اختر المنجم --</option>
                        <?php
                        // تحميل المناجم للمشروع المحدد مباشرة
                        $mines_query = "SELECT id, mine_name FROM mines WHERE project_id = $selected_project_id AND status='1' ORDER BY mine_name";
                        $mines_result = mysqli_query($conn, $mines_query);
                        while ($mine = mysqli_fetch_assoc($mines_result)) {
                            echo "<option value='" . $mine['id'] . "'>" . htmlspecialchars($mine['mine_name']) . "</option>";
                        }
                        ?>
                    </select>

                    <!-- العقود -->
                    <select name="contract_id" id="contract_id" required>
                        <option value="">-- اختر العقد --</option>
                    </select>

                    <!-- المورد -->
                    <select name="supplier_id" id="supplier_id" required>
                        <option value="">-- اختر المورد --</option>
                    </select>

                    <select name="type" id="type" required>
                        <option value=""> -- حدد نوع المعدة --- </option>
                        <option value="1"> حفار </option>
                        <option value="2"> قلاب </option>
                    </select>

                    <select name="equipment" id="equipment" required>
                        <option value="">-- اختر المعدة --</option>
                        <!-- سيتم ملؤها ديناميكيًا عبر AJAX -->
                    </select>

                    <input type="date" name="start" required placeholder="تاريخ البداية" />
                    <input type="date" name="end" id="end_date" required placeholder="تاريخ النهاية" />
                    <input type="hidden" step="0.01" name="hours" placeholder="عدد الساعات" value="0" />
                    <select name="status" required>
                        <option value="1">نشط</option>
                        <option value="0">منتهي</option>
                    </select>
                    <input type="hidden" name="action" value="save_operation" />
                    <button type="submit">حفظ التشغيل</button>
                </div>
            </div>
        </div>
    </form>

    <!-- قسم الإحصائيات -->
    <div id="contractStats" class="contract-stats">
        <h5 class="stats-title">
            <i class="fas fa-chart-line"></i>
            إحصائيات عقد المنجم
        </h5>

        <div id="suppliersSection" style="display: none;">
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
                <div class="stat-card-label">عدد المعدات المشغلة</div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"> قائمة التشغيل</h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="text-align:right;">المعدة</th>

                        <th style="text-align:right;">السائقين</th>

                        <th style="text-align:right;">المورد</th>

                        <th style="text-align:right;">تاريخ البداية</th>
                        <th style="text-align:right;">تاريخ النهاية</th>
                        <!-- <th style="text-align:right;">عدد الساعات</th> -->
                        <th style="text-align:right;">الحالة</th>
                        <th style="text-align:right;">إجراءات</th>

                    </tr>
                </thead>
                <tbody>
                    <?php
                    // إضافة تشغيل جديد
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_operation' && !empty($_POST['equipment'])) {
                        $equipment = intval($_POST['equipment']);
                        $project_id = intval($_POST['project_id']);
                        $mine_id = intval($_POST['mine_id']);
                        $contract_id = intval($_POST['contract_id']);
                        $supplier_id = intval($_POST['supplier_id']);
                        $equipment_type = intval($_POST['type']);


                        
                        $start = mysqli_real_escape_string($conn, $_POST['start']);
                        $end = mysqli_real_escape_string($conn, $_POST['end']);
                        $hours = floatval($_POST['hours']);
                        $status = mysqli_real_escape_string($conn, $_POST['status']);

                        mysqli_query($conn, "INSERT INTO operations (equipment, equipment_type, project_id, mine_id, contract_id, supplier_id, start, end, days, status) 
                                     VALUES ('$equipment', '$equipment_type', '$project_id', '$mine_id', '$contract_id', '$supplier_id', '$start', '$end', '$hours', '$status')");

                        echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
                    }

                    // جلب بيانات التشغيل للمشروع المحدد فقط
                    $query = "SELECT o.id, o.start, o.end, o.days , o.status, 
                             e.code AS equipment_code, e.name AS equipment_name,
                             p.name AS project_name ,s.name AS suppliers_name,
                             IFNULL(GROUP_CONCAT(DISTINCT d.name SEPARATOR ', '), '') AS driver_names
                      FROM operations o
                      LEFT JOIN equipments e ON o.equipment = e.id
                      LEFT JOIN project p ON o.project_id = p.id
                      LEFT JOIN suppliers s ON e.suppliers = s.id
                      LEFT JOIN equipment_drivers ed ON o.equipment = ed.equipment_id
                      LEFT JOIN drivers d ON ed.driver_id = d.id
                      WHERE o.project_id = $selected_project_id
                      GROUP BY o.id
                      ORDER BY o.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['equipment_code'] . " - " . $row['equipment_name'] . "</td>";
                        echo "<td>" . (!empty($row['driver_names']) ? $row['driver_names'] : "-") . "</td>";

                        echo "<td>" . $row['suppliers_name'] . "</td>";

                        echo "<td>" . $row['start'] . "</td>";
                        echo "<td>" . $row['end'] . "</td>";
                        // echo "<td>" . $row['hours'] . "</td>";
                        echo $row['status'] == "1" ? "<td style='color:green'> تعمل </td>" : "<td style='color:red'> متوقفة </td>";
                                                echo "<td>
                                                                                                <a href='#' style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                                                                                                <a href='#' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                                                                                                <a href='#' class='end-service-btn' data-bs-toggle='modal' data-bs-target='#endServiceModal' data-id='" . $row['id'] . "'> إنهاء خدمة </a>
                                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- موديل إنهاء الخدمة -->
<div class="modal fade" id="endServiceModal" tabindex="-1" aria-labelledby="endServiceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="endServiceLabel">إنهاء الخدمة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="end_service" />
                    <input type="hidden" name="operation_id" id="operation_id" />
                    <div class="mb-3">
                        <label for="service_end_date" class="form-label">تاريخ الإنهاء</label>
                        <input type="date" class="form-control" name="end_date" id="service_end_date" required />
                    </div>
                    <div class="mb-3">
                        <label for="service_reason" class="form-label">سبب الإنهاء</label>
                        <textarea class="form-control" name="reason" id="service_reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-danger">تأكيد الإنهاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Bootstrap Bundle (Modal يحتاج هذا الملف) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
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
    (function () {
        // تشغيل DataTable بالعربية
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip', // Buttons + Search + Pagination
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

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const form = document.getElementById('projectForm');

        toggleFormBtn.addEventListener('click', function () {
            form.style.display = form.style.display === "none" ? "block" : "none";
        });
    })();

    $(document).ready(function () {
        function resetEquipment() {
            $("#equipment").html("<option value=''>-- اختر المعدة --</option>");
        }

        function resetSupplier() {
            $("#supplier_id").html("<option value=''>-- اختر المورد --</option>");
        }

        function resetStats() {
            $("#contractStats").hide();
            $("#suppliersSection").hide();
            $("#suppliersTableBody").html("<tr><td colspan='7' style='text-align: center; color: #6c757d; padding: 2rem;'><i class='fas fa-info-circle'></i> لا توجد بيانات</td></tr>");
            $("#stat_total_hours").text("0");
            $("#stat_equipment_count").text("0");
            $("#total_supplier_hours").text("0");
            $("#total_supplier_equipment").text("0");
            $("#total_added_equipment").text("0");
            $("#total_remaining_equipment").text("0");
        }

        function renderStats(response) {
            if (!response || !response.success) {
                resetStats();
                return;
            }

            $("#contractStats").show();
            $("#stat_total_hours").text(parseFloat(response.contract.total_hours || 0).toLocaleString());
            $("#stat_equipment_count").text(parseInt(response.contract.equipment_count || 0, 10).toLocaleString());

            if (response.suppliers && response.suppliers.length > 0) {
                $("#suppliersSection").show();
                var rows = "";
                var totalAdded = 0;
                var totalRemaining = 0;

                response.suppliers.forEach(function (supplier, index) {
                    var breakdownHtml = "";
                    if (supplier.equipment_breakdown && supplier.equipment_breakdown.length > 0) {
                        breakdownHtml = supplier.equipment_breakdown.map(function (item) {
                            var addedCount = item.added_count || 0;
                            var remaining = item.remaining || 0;
                            var statusIcon = '';
                            var statusStyle = '';

                            if (remaining === 0) {
                                statusIcon = '<i class="fas fa-check-circle" style="color: #28a745;"></i>';
                                statusStyle = 'background: rgba(40, 167, 69, 0.1); border-right: 3px solid #28a745;';
                            } else if (addedCount > 0) {
                                statusIcon = '<i class="fas fa-exclamation-circle" style="color: #ffc107;"></i>';
                                statusStyle = 'background: rgba(255, 193, 7, 0.1); border-right: 3px solid #ffc107;';
                            } else {
                                statusIcon = '<i class="fas fa-times-circle" style="color: #dc3545;"></i>';
                                statusStyle = 'background: rgba(220, 53, 69, 0.1); border-right: 3px solid #dc3545;';
                            }

                            return '<div style="margin: 3px 0; padding: 8px; ' + statusStyle + ' border-radius: 4px;">' +
                                statusIcon +
                                ' <i class="fas fa-tools" style="color: #e2ae03;"></i> <strong>' + (item.type || 'غير محدد') + '</strong>: ' +
                                item.count + ' متعاقد | ' +
                                '<span style="color: #28a745; font-weight: bold;">' + addedCount + ' مضاف</span> | ' +
                                '<span style="color: #dc3545; font-weight: bold;">' + remaining + ' متبقي</span> | ' +
                                '<i class="fas fa-clock"></i> ' + parseFloat(item.hours || 0).toLocaleString() + ' ساعة' +
                                '</div>';
                        }).join('');
                    } else {
                        breakdownHtml = '<span style="color: #6c757d;">لا توجد تفاصيل</span>';
                    }

                    var addedEquipment = supplier.added_to_equipments || 0;
                    var remainingEquipment = supplier.remaining_to_add || 0;
                    totalAdded += addedEquipment;
                    totalRemaining += remainingEquipment;

                    var addedBadgeClass = 'badge-available';
                    var remainingBadgeClass = 'badge-busy';

                    if (remainingEquipment === 0) {
                        addedBadgeClass = 'badge-available';
                        remainingBadgeClass = 'badge-available';
                    } else if (addedEquipment > 0) {
                        addedBadgeClass = 'badge-working';
                        remainingBadgeClass = 'badge-working';
                    }

                    rows += '<tr>' +
                        '<td style="text-align: center;">' + (index + 1) + '</td>' +
                        '<td><strong>' + (supplier.supplier_name || '-') + '</strong></td>' +
                        '<td style="text-align: center;">' + parseFloat(supplier.hours || 0).toLocaleString() + '</td>' +
                        '<td style="text-align: center;">' + (supplier.equipment_count || 0) + '</td>' +
                        '<td style="text-align: center;">' +
                        '<span class="' + addedBadgeClass + '"><i class="fas fa-check"></i> ' + addedEquipment + '</span>' +
                        '</td>' +
                        '<td style="text-align: center;">' +
                        '<span class="' + remainingBadgeClass + '"><i class="fas fa-' + (remainingEquipment === 0 ? 'check-circle' : 'exclamation-triangle') + '"></i> ' + remainingEquipment + '</span>' +
                        '</td>' +
                        '<td style="text-align: right; font-size: 0.9rem;">' + breakdownHtml + '</td>' +
                        '</tr>';
                });

                $("#suppliersTableBody").html(rows);
                $("#total_supplier_hours").text(parseFloat(response.summary.total_supplier_hours || 0).toLocaleString());
                $("#total_supplier_equipment").text(response.summary.total_supplier_equipment || 0);
                $("#total_added_equipment").text(totalAdded);
                $("#total_remaining_equipment").text(totalRemaining);
            } else {
                $("#suppliersSection").hide();
            }
        }

        function loadEquipments() {
            var type = $("#type").val();
            var supplierId = $("#supplier_id").val();
            if (type !== "" && supplierId !== "") {
                $.ajax({
                    url: "getoprator.php",
                    type: "GET",
                    data: { type: type, supplier_id: supplierId },
                    success: function (response) {
                        $("#equipment").html(response);
                    },
                    error: function (xhr, status, error) {
                        console.error("❌ AJAX Error:", error);
                    }
                });
            } else {
                resetEquipment();
            }
        }

        // لم نعد بحاجة لـ event listener للمشروع لأنه محدد مسبقاً من الصفحة السابقة
        
        $("#mine_id").change(function () {
            var mineId = $(this).val();
            $("#contract_id").html("<option value=''>-- اختر العقد --</option>");
            resetSupplier();
            $("#type").val("");
            resetEquipment();
            resetStats();
            $("#end_date").val("");

            if (mineId !== "") {
                $.ajax({
                    url: "get_mine_contracts.php",
                    type: "POST",
                    dataType: "json",
                    data: { mine_id: mineId },
                    success: function (response) {
                        if (response.success) {
                            var options = "<option value=''>-- اختر العقد --</option>";
                            response.contracts.forEach(function (contract) {
                                options += "<option value='" + contract.id + "' data-end='" + contract.end_date + "'>" + contract.display_name + "</option>";
                            });
                            $("#contract_id").html(options);
                        }
                    }
                });
            }
        });

        $("#contract_id").change(function () {
            var contractId = $(this).val();
            var endDate = $(this).find(":selected").data("end") || "";
            resetSupplier();
            $("#type").val("");
            resetEquipment();
            resetStats();
            if (endDate !== "") {
                $("#end_date").val(endDate);
            }

            if (contractId !== "") {
                $.ajax({
                    url: "get_contract_suppliers.php",
                    type: "POST",
                    dataType: "json",
                    data: { contract_id: contractId },
                    success: function (response) {
                        if (response.success) {
                            var options = "<option value=''>-- اختر المورد --</option>";
                            response.suppliers.forEach(function (supplier) {
                                options += "<option value='" + supplier.id + "'>" + supplier.name + "</option>";
                            });
                            $("#supplier_id").html(options);
                        }
                    }
                });

                $.ajax({
                    url: "get_contract_stats.php",
                    type: "GET",
                    dataType: "json",
                    data: { contract_id: contractId },
                    success: function (response) {
                        renderStats(response);
                    },
                    error: function () {
                        resetStats();
                    }
                });
            }
        });

        $("#type").change(function () {
            loadEquipments();
        });

        $("#supplier_id").change(function () {
            loadEquipments();
        });

        $(document).on("click", ".end-service-btn", function (e) {
            e.preventDefault();
        });

        $("#endServiceModal").on("show.bs.modal", function (event) {
            var button = $(event.relatedTarget);
            var opId = button.data("id") || "";
            $("#operation_id").val(opId);
            $("#service_end_date").val("");
            $("#service_reason").val("");
        });
    });

</script>

</body>

</html>