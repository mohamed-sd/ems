<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// التحقق من صلاحية الوصول (مدير الأسطول = 4 أو المدير العام = -1)
$user_role = $_SESSION['user']['role'];
// if ($user_role != "-1" && $user_role != "4") {
//     die("غير مصرح لك بالوصول إلى هذه الصفحة");
// }

require_once '../config.php';

// الحصول على معرف الشركة للمستخدم
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = ($user_role == "-1");

// التحقق من اسم عمود المشروع في جدول operations
$ops_project_col = (function_exists('db_table_has_column') && db_table_has_column($conn, 'operations', 'project_id'))
    ? 'project_id' : 'project';

// إعداد عنوان الصفحة
$page_title = "تقرير الأعطال - إدارة الأسطول";

// معالجة تصدير Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="failures_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
}

include '../inheader.php';
include '../insidebar.php';

// استعلام البيانات مع ربط الجداول
$where_conditions = ["tfh.status = 1"];

// فلتر الشركة للمستخدمين غير المدراء العامين
if (!$is_super_admin && $company_id > 0) {
    $where_conditions[] = "tfh.company_id = " . $company_id;
}

// فلاتر البحث
$filter_equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : 0;
$filter_equipment_type = isset($_GET['equipment_type']) ? intval($_GET['equipment_type']) : 0;
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$filter_main_category = isset($_GET['main_category']) ? mysqli_real_escape_string($conn, $_GET['main_category']) : '';
$filter_event_type = isset($_GET['event_type']) ? mysqli_real_escape_string($conn, $_GET['event_type']) : '';
$filter_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($filter_equipment_id > 0) {
    $where_conditions[] = "tfh.equipment_id = " . $filter_equipment_id;
}

if ($filter_equipment_type > 0) {
    $where_conditions[] = "tfh.equipment_type = " . $filter_equipment_type;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "tfh.timesheet_date >= '" . $filter_date_from . "'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "tfh.timesheet_date <= '" . $filter_date_to . "'";
}

if (!empty($filter_main_category)) {
    $where_conditions[] = "tfh.main_category_code = '" . $filter_main_category . "'";
}

if (!empty($filter_event_type)) {
    $where_conditions[] = "tfh.event_type_code = '" . $filter_event_type . "'";
}

if ($filter_project_id > 0) {
    $where_conditions[] = "op.$ops_project_col = " . $filter_project_id;
}

$where_clause = implode(" AND ", $where_conditions);

$query = "
    SELECT 
        tfh.*,
        e.code AS equipment_code,
        e.name AS equipment_name,
        s.name AS supplier_name,
        p.name AS project_name,
        op.id AS operation_id,
        t.date AS timesheet_actual_date,
        t.shift AS timesheet_shift
    FROM timesheet_failure_hours tfh
    LEFT JOIN equipments e ON tfh.equipment_id = e.id
    LEFT JOIN suppliers s ON e.suppliers = s.id
    LEFT JOIN operations op ON tfh.operation_id = op.id
    LEFT JOIN project p ON op.$ops_project_col = p.id
    LEFT JOIN timesheet t ON tfh.timesheet_id = t.id
    WHERE $where_clause
    ORDER BY tfh.timesheet_date DESC, tfh.id DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("خطأ في الاستعلام: " . mysqli_error($conn));
}

// الحصول على قوائم الفلاتر
$equipments_query = "SELECT DISTINCT e.id, e.code, e.name FROM equipments e 
                     INNER JOIN timesheet_failure_hours tfh ON e.id = tfh.equipment_id 
                     WHERE e.status = 1";
if (!$is_super_admin && $company_id > 0) {
    $equipments_query .= " AND tfh.company_id = " . $company_id;
}
$equipments_query .= " ORDER BY e.code";
$equipments_result = mysqli_query($conn, $equipments_query);

$projects_query = "SELECT DISTINCT p.id, p.name FROM project p
                   INNER JOIN operations op ON p.id = op.$ops_project_col
                   INNER JOIN timesheet_failure_hours tfh ON op.id = tfh.operation_id
                   WHERE p.status = 1";
if (!$is_super_admin && $company_id > 0) {
    $projects_query .= " AND tfh.company_id = " . $company_id;
}
$projects_query .= " ORDER BY p.name";
$projects_result = mysqli_query($conn, $projects_query);

$main_categories_query = "SELECT DISTINCT main_category_code, main_category_name 
                          FROM timesheet_failure_hours 
                          WHERE status = 1";
if (!$is_super_admin && $company_id > 0) {
    $main_categories_query .= " AND company_id = " . $company_id;
}
$main_categories_query .= " ORDER BY main_category_name";
$main_categories_result = mysqli_query($conn, $main_categories_query);

$event_types_query = "SELECT DISTINCT event_type_code, event_type_name 
                      FROM timesheet_failure_hours 
                      WHERE status = 1";
if (!$is_super_admin && $company_id > 0) {
    $event_types_query .= " AND company_id = " . $company_id;
}
$event_types_query .= " ORDER BY event_type_name";
$event_types_result = mysqli_query($conn, $event_types_query);
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <h2 class="page-title">
                    <i class="fas fa-tools"></i> تقرير الأعطال - إدارة الأسطول
                </h2>
            </div>
        </div>

        <!-- قسم الفلاتر -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> فلاتر البحث</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <!-- فلتر المعدة -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">المعدة</label>
                            <select name="equipment_id" class="form-select">
                                <option value="">-- الكل --</option>
                                <?php 
                                if ($equipments_result && mysqli_num_rows($equipments_result) > 0):
                                    while ($eq = mysqli_fetch_assoc($equipments_result)): 
                                ?>
                                    <option value="<?php echo $eq['id']; ?>" 
                                            <?php echo ($filter_equipment_id == $eq['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>

                        <!-- فلتر نوع المعدة -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">نوع المعدة</label>
                            <select name="equipment_type" class="form-select">
                                <option value="">-- الكل --</option>
                                <option value="1" <?php echo ($filter_equipment_type == 1) ? 'selected' : ''; ?>>حفار</option>
                                <option value="2" <?php echo ($filter_equipment_type == 2) ? 'selected' : ''; ?>>قلاب</option>
                                <option value="3" <?php echo ($filter_equipment_type == 3) ? 'selected' : ''; ?>>خرامة</option>
                            </select>
                        </div>

                        <!-- فلتر المشروع -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">المشروع</label>
                            <select name="project_id" class="form-select">
                                <option value="">-- الكل --</option>
                                <?php 
                                if ($projects_result && mysqli_num_rows($projects_result) > 0):
                                    while ($proj = mysqli_fetch_assoc($projects_result)): 
                                ?>
                                    <option value="<?php echo $proj['id']; ?>" 
                                            <?php echo ($filter_project_id == $proj['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>

                        <!-- فلتر نوع الحدث -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">نوع الحدث</label>
                            <select name="event_type" class="form-select">
                                <option value="">-- الكل --</option>
                                <?php 
                                if ($event_types_result && mysqli_num_rows($event_types_result) > 0):
                                    while ($et = mysqli_fetch_assoc($event_types_result)): 
                                ?>
                                    <option value="<?php echo $et['event_type_code']; ?>" 
                                            <?php echo ($filter_event_type == $et['event_type_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($et['event_type_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>

                        <!-- فلتر الفئة الرئيسية -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">الفئة الرئيسية</label>
                            <select name="main_category" class="form-select">
                                <option value="">-- الكل --</option>
                                <?php 
                                if ($main_categories_result && mysqli_num_rows($main_categories_result) > 0):
                                    while ($mc = mysqli_fetch_assoc($main_categories_result)): 
                                ?>
                                    <option value="<?php echo $mc['main_category_code']; ?>" 
                                            <?php echo ($filter_main_category == $mc['main_category_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mc['main_category_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>

                        <!-- فلتر التاريخ من -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">التاريخ من</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>

                        <!-- فلتر التاريخ إلى -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">التاريخ إلى</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>

                        <!-- أزرار التحكم -->
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <a href="fleet_failures.php" class="btn btn-secondary me-2">
                                <i class="fas fa-redo"></i> إعادة تعيين
                            </a>
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> تصدير
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- قسم الإحصائيات -->
        <div class="row mb-3">
            <?php
            // حساب الإحصائيات
            mysqli_data_seek($result, 0); // إعادة تعيين المؤشر
            $total_failures = mysqli_num_rows($result);
            $equipment_types_count = [];
            $event_types_count = [];
            
            while ($row = mysqli_fetch_assoc($result)) {
                // عد حسب نوع المعدة
                $eq_type = $row['equipment_type'];
                if (!isset($equipment_types_count[$eq_type])) {
                    $equipment_types_count[$eq_type] = 0;
                }
                $equipment_types_count[$eq_type]++;
                
                // عد حسب نوع الحدث
                $ev_type = $row['event_type_name'];
                if (!isset($event_types_count[$ev_type])) {
                    $event_types_count[$ev_type] = 0;
                }
                $event_types_count[$ev_type]++;
            }
            
            mysqli_data_seek($result, 0); // إعادة تعيين المؤشر مرة أخرى
            
            $equipment_type_names = [1 => 'حفار', 2 => 'قلاب', 3 => 'خرامة'];
            ?>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">إجمالي الأعطال</h5>
                        <h2><?php echo number_format($total_failures); ?></h2>
                    </div>
                </div>
            </div>
            
            <?php foreach ($equipment_types_count as $type => $count): ?>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">أعطال <?php echo $equipment_type_names[$type] ?? 'غير محدد'; ?></h5>
                        <h2><?php echo number_format($count); ?></h2>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- جدول البيانات -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-table"></i> قائمة الأعطال</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="failuresTable" class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>المشروع</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>
                                <th>المورد</th>
                                <th>نوع الحدث</th>
                                <th>الفئة الرئيسية</th>
                                <th>الفئة الفرعية</th>
                                <th>تفاصيل العطل</th>
                                <th>الكود الكامل</th>
                                <th>الوردية</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            if (mysqli_num_rows($result) > 0):
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)): 
                                    $equipment_type_name = $equipment_type_names[$row['equipment_type']] ?? 'غير محدد';
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['timesheet_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['project_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment_type_name); ?></td>
                                    <td><?php echo htmlspecialchars($row['supplier_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['main_category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['sub_category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['failure_detail']); ?></td>
                                    <td><code><?php echo htmlspecialchars($row['full_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($row['timesheet_shift'] ?? '-'); ?></td>
                                </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="12" class="text-center">لا توجد بيانات متاحة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#failuresTable').DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        },
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> تصدير Excel',
                className: 'btn btn-success',
                title: 'تقرير الأعطال',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> تصدير PDF',
                className: 'btn btn-danger',
                title: 'تقرير الأعطال',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(doc) {
                    doc.defaultStyle.font = 'Amiri';
                    doc.styles.tableHeader.alignment = 'right';
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> طباعة',
                className: 'btn btn-info'
            }
        ],
        order: [[1, 'desc']], // ترتيب حسب التاريخ تنازلياً
        pageLength: 25,
        responsive: true
    });
});

function exportToExcel() {
    var currentUrl = window.location.href;
    if (currentUrl.indexOf('?') > -1) {
        window.location.href = currentUrl + '&export=excel';
    } else {
        window.location.href = currentUrl + '?export=excel';
    }
}
</script>

<?php
mysqli_close($conn);
?>
