<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
$_ts_current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$_ts_is_super_admin = ($_ts_current_role === '-1');
$_ts_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$_ts_suppliers_has_company_id = db_table_has_column($conn, 'suppliers', 'company_id');
$_ts_supplier_company_where = (!$_ts_is_super_admin && $_ts_suppliers_has_company_id && $_ts_company_id > 0)
    ? " AND company_id = '$_ts_company_id'"
    : "";

$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

    // استقبال الفلاتر
    $date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';
    $project_filter = isset($_GET['project']) ? intval($_GET['project']) : 0;
    $supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;

    $shift_filter = isset($_GET['shift']) ? mysqli_real_escape_string($conn, $_GET['shift']) : '';
    $type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;


    $sql = "
SELECT
    t.id,
    t.date,
    t.shift,
    d.name AS driver_name,
    e.name AS equipment_name,
    e.code AS equipment_code,
    s.name AS supplier_name,
    p.name AS project_name,
    t.executed_hours,
    t.total_fault_hours,
    t.standby_hours,
    t.work_notes,
    t.fault_notes
FROM timesheet t
JOIN drivers d ON t.driver = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o." . $operations_project_column . " = p.id
WHERE 1=1
";

    if (!empty($date_filter)) {
        $sql .= " AND t.date = '$date_filter' ";
    }
    if (!empty($project_filter)) {
        $sql .= " AND p.id = '$project_filter' ";
    }
    if (!empty($supplier_filter)) {
        $sql .= " AND s.id = '$supplier_filter' ";
    }

    if (!empty($shift_filter)) {
        $sql .= " AND t.shift = '$shift_filter' ";
    }

    if (!empty($type_filter)) {
        $sql .= " AND e.type = '$type_filter' ";
    }

    $sql .= " ORDER BY t.date, p.name, s.name ";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('timesheetdeliy.php details query failed: ' . mysqli_error($conn));
    }

    // إجمالي الإحصائيات
    $total_sql = "
SELECT
    SUM(t.executed_hours) AS executed_hours,
    SUM(t.total_fault_hours) AS total_fault,
    SUM(t.standby_hours) AS total_standby
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o." . $operations_project_column . " = p.id
WHERE 1=1
";

    if (!empty($date_filter)) {
        $total_sql .= " AND t.date = '$date_filter' ";
    }
    if (!empty($project_filter)) {
        $total_sql .= " AND p.id = '$project_filter' ";
    }
    if (!empty($supplier_filter)) {
        $total_sql .= " AND s.id = '$supplier_filter' ";
    }

    if (!empty($shift_filter)) {
        $total_sql .= " AND t.shift = '$shift_filter' ";
    }


$total_res = mysqli_query($conn, $total_sql);
$totals = $total_res ? mysqli_fetch_assoc($total_res) : ['executed_hours' => 0, 'total_fault' => 0, 'total_standby' => 0];
if (!$total_res) {
    error_log('timesheetdeliy.php totals query failed: ' . mysqli_error($conn));
}

$page_title = "إيكوبيشن | تقرير التايم شيت اليومي";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main ems-unified-page-shell reports-main timesheet-daily-main">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'تقرير التايم شيت اليومي';
    $header_icon    = 'fa-solid fa-chart-column';
    $header_actions = array();
    $header_back    = array('href' => 'reports.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلاتر التقرير</h5>
        </div>
        <div class="card-body fc-filter-body">
            <form method="GET" class="fc-filter-bar">
                <div>
                    <label class="fc-filter-label"><i class="fas fa-calendar-day"></i> التاريخ:</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                </div>

                <div>
                    <label class="fc-filter-label"><i class="fas fa-diagram-project"></i> المشروع</label>
                    <select name="project" class="form-select">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
                        if ($prj) {
                        while ($row = mysqli_fetch_assoc($prj)) {
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="fc-filter-label"><i class="fas fa-truck"></i> المورد</label>
                    <select name="supplier" class="form-select">
                        <option value="">-- الكل --</option>
                        <?php
                        $sup = mysqli_query($conn, "SELECT id, name FROM suppliers where status = '1'$_ts_supplier_company_where ");
                        if ($sup) {
                        while ($row = mysqli_fetch_assoc($sup)) {
                            $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="fc-filter-label"><i class="fas fa-clock"></i> الوردية</label>
                    <select name="shift" class="form-select">
                        <option value="">-- الكل --</option>
                        <option value="D" <?php if ($shift_filter == "D") echo "selected"; ?>>صباحية</option>
                        <option value="N" <?php if ($shift_filter == "N") echo "selected"; ?>>مسائية</option>
                    </select>
                </div>

                <div>
                    <label class="fc-filter-label"><i class="fas fa-cogs"></i> نوع الآلية</label>
                    <select name="type" class="form-select">
                        <option value="">-- الكل --</option>
                        <option value="1" <?php if ($type_filter == "1") echo "selected"; ?>>حفار</option>
                        <option value="2" <?php if ($type_filter == "2") echo "selected"; ?>>قلاب</option>
                    </select>
                </div>

                <div class="fc-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                </div>
            </form>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="stats-grid">
        <div class="stat-card executed">
            <div class="stat-icon">⏱️</div>
            <div class="stat-label">إجمالي ساعات العمل</div>
            <div class="stat-value"><?php echo !empty($totals['executed_hours']) ? $totals['executed_hours'] : 0; ?></div>
        </div>
        <div class="stat-card fault">
            <div class="stat-icon">⚠️</div>
            <div class="stat-label">إجمالي ساعات الأعطال</div>
            <div class="stat-value"><?php echo !empty($totals['total_fault']) ? $totals['total_fault'] : 0; ?></div>
        </div>
        <div class="stat-card standby">
            <div class="stat-icon">⏸️</div>
            <div class="stat-label">إجمالي ساعات الاستعداد</div>
            <div class="stat-value"><?php echo !empty($totals['total_standby']) ? $totals['total_standby'] : 0; ?></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> تفاصيل التايم شيت</h5>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle report-table alltable" id="projectsTable">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المشروع</th>
                        <th>المورد</th>
                        <th>الآلية</th>
                        <th>كود الآلية</th>
                        <th>السائق</th>
                        <th>الشفت</th>
                        <th><i class="fas fa-clock"></i> ساعات العمل</th>
                        <th><i class="fas fa-exclamation-triangle"></i> ساعات الأعطال</th>
                        <th><i class="fas fa-pause-circle"></i> ساعات الاستعداد</th>
                        <th><i class="fas fa-sticky-note"></i> ملاحظات العمل</th>
                        <th><i class="fas fa-sticky-note"></i> ملاحظات الأعطال</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result) { while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['equipment_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                            <td><?php echo ($row['shift'] === 'D') ? 'صباحية' : (($row['shift'] === 'N') ? 'مسائية' : htmlspecialchars($row['shift'])); ?></td>
                            <td style="color:#0d9488; font-weight:700;"><?php echo $row['executed_hours']; ?></td>
                            <td style="color:#dc2626; font-weight:700;"><?php echo $row['total_fault_hours']; ?></td>
                            <td style="color:#e8b800; font-weight:700;"><?php echo $row['standby_hours']; ?></td>
                            <td><?php echo htmlspecialchars($row['work_notes']); ?></td>
                            <td><?php echo htmlspecialchars($row['fault_notes']); ?></td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    </div>

    <!-- jQuery (يجب أن يكون أولاً) -->
    <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
    <script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy',  text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv',   text: 'تصدير CSV' },
                    { extend: 'pdf',   text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                language: {
                    url: '/ems/assets/i18n/datatables/ar.json'
                }
            });
        });
    </script>
</body>
</html>
