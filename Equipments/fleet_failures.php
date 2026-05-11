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

$is_export_request = (isset($_GET['export']) && $_GET['export'] == 'excel');

// معالجة تصدير Excel
if ($is_export_request) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="failures_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
}

include '../inheader.php';
include '../insidebar.php';

?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<?php

// استعلام البيانات مع ربط الجداول
$where_conditions = ["tfh.status = 1"];

// فلتر الشركة للمستخدمين غير المدراء العامين
if (!$is_super_admin && $company_id > 0) {
    $where_conditions[] = "tfh.company_id = " . $company_id;
}

// فلاتر البحث
$filter_equipment_type = isset($_GET['equipment_type']) ? intval($_GET['equipment_type']) : 0;
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$filter_main_category = isset($_GET['main_category']) ? mysqli_real_escape_string($conn, $_GET['main_category']) : '';
$filter_event_type = isset($_GET['event_type']) ? mysqli_real_escape_string($conn, $_GET['event_type']) : '';
$filter_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

$has_active_filters = (
    $filter_equipment_type > 0 ||
    !empty($filter_date_from) ||
    !empty($filter_date_to) ||
    !empty($filter_main_category) ||
    !empty($filter_event_type) ||
    $filter_project_id > 0
);

$default_rows_limit = 1000;
$apply_default_limit = (!$has_active_filters && !$is_export_request);
$limit_clause = $apply_default_limit ? " LIMIT $default_rows_limit" : '';

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
    $limit_clause
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("خطأ في الاستعلام: " . mysqli_error($conn));
}

// الحصول على قوائم الفلاتر
$suppliers_has_company = (function_exists('db_table_has_column') && db_table_has_column($conn, 'suppliers', 'company_id'));

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

<div class="main failures-page fleet-failures-main">
    <div class="main_head">
        <div class="head_actions">
            <button type="button" class="add-btn btn" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> تصدير Excel
            </button>
        </div>

        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-tools"></i></div>
            تقرير الأعطال - إدارة الأسطول
        </h1>

        <div class="head_back">
            <a href="manage_failure_codes.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="hero-note">
        <i class="fas fa-chart-line"></i>
        شاشة متابعة وتحليل أعطال الأسطول مع فلترة ذكية وسريعة
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-filter"></i> فلاتر البحث</h5>
                <span class="filter-counter">
                    <i class="fas fa-sliders-h"></i>
                    الفلاتر النشطة: <?php echo $has_active_filters ? 'نعم' : 'لا'; ?>
                </span>
            </div>
        </div>
        <div class="card-body fc-filter-body">
            <form method="GET" action="" id="filterForm">
            <div class="row fc-filter-bar">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">نوع المعدة</label>
                        <select name="equipment_type" class="form-select">
                            <option value="">-- الكل --</option>
                            <option value="1" <?php echo ($filter_equipment_type == 1) ? 'selected' : ''; ?>>حفار</option>
                            <option value="2" <?php echo ($filter_equipment_type == 2) ? 'selected' : ''; ?>>قلاب</option>
                            <option value="3" <?php echo ($filter_equipment_type == 3) ? 'selected' : ''; ?>>خرامة</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">المشروع</label>
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

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">نوع الحدث</label>
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

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">الفئة الرئيسية</label>
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

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">التاريخ من</label>
                        <input type="date" name="date_from" class="form-control"
                            value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label fc-filter-label">التاريخ إلى</label>
                        <input type="date" name="date_to" class="form-control"
                            value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>

                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <div class="filter-actions fc-filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <a href="fleet_failures.php" class="btn btn-light border">
                                <i class="fas fa-redo"></i> إعادة تعيين
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <?php
        mysqli_data_seek($result, 0);
        $total_failures = mysqli_num_rows($result);
        $equipment_types_count = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $eq_type = $row['equipment_type'];
            if (!isset($equipment_types_count[$eq_type])) {
                $equipment_types_count[$eq_type] = 0;
            }
            $equipment_types_count[$eq_type]++;
        }

        mysqli_data_seek($result, 0);

        $equipment_type_names = [1 => 'حفار', 2 => 'قلاب', 3 => 'خرامة'];
        ?>

        <div class="col-md-3 mb-3">
            <div class="summary-card summary-primary">
                <h6>إجمالي الأعطال</h6>
                <div class="summary-value"><?php echo number_format($total_failures); ?></div>
            </div>
        </div>

        <?php foreach ($equipment_types_count as $type => $count): ?>
            <div class="col-md-3 mb-3">
                <div class="summary-card summary-gold">
                    <h6>أعطال <?php echo $equipment_type_names[$type] ?? 'غير محدد'; ?></h6>
                    <div class="summary-value"><?php echo number_format($count); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-table"></i> قائمة الأعطال</h5>
                <?php if ($apply_default_limit): ?>
                    <span class="initial-load-badge">
                        <i class="fas fa-bolt"></i>
                        عرض آخر <?php echo number_format($default_rows_limit); ?> عطل (فتح أولي)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="search-toolbar">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="quickTableSearch" class="form-control" placeholder="بحث سريع داخل النتائج المعروضة...">
                </div>
            </div>
            <div class="table-responsive">
                <table id="failuresTable" class="display table table-bordered table-striped table-hover" style="width:100%">
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

<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
$(document).ready(function() {
    var table = $('#failuresTable').DataTable({
        language: {
            url: "/ems/assets/i18n/datatables/ar.json"
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
        order: [[1, 'desc']],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        searchDelay: 350,
        deferRender: true,
        stateSave: true,
        responsive: true
    });

    $('#quickTableSearch').on('keyup', function() {
        table.search(this.value).draw();
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
