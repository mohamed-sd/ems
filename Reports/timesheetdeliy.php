<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | التقارير </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php 
include('../insidebar.php'); 
include '../config.php';

// استقبال الفلاتر
$date_filter     = isset($_GET['date']) ? $_GET['date'] : '';
$project_filter  = isset($_GET['project']) ? $_GET['project'] : '';
$supplier_filter = isset($_GET['supplier']) ? $_GET['supplier'] : '';

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
    t.total_work_hours,
    t.total_fault_hours,
    t.standby_hours,
    t.work_notes,
    t.fault_notes
FROM timesheet t
JOIN drivers d ON t.driver = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN suppliers s ON e.suppliers = s.id
JOIN projects p ON o.project = p.id
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

$sql .= " ORDER BY t.date, p.name, s.name ";
$result = mysqli_query($conn, $sql);

// إجمالي الإحصائيات
$total_sql = "
SELECT 
    SUM(t.total_work_hours) AS total_work,
    SUM(t.total_fault_hours) AS total_fault,
    SUM(t.standby_hours) AS total_standby
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN suppliers s ON e.suppliers = s.id
JOIN projects p ON o.project = p.id
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

$total_res = mysqli_query($conn, $total_sql);
$totals = mysqli_fetch_assoc($total_res);
?>

<div class="main container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fa-solid fa-chart-column me-2 text-primary"></i> تقرير التايم شيت</h2>
    </div>

    <!-- فورم الفلترة -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">📅 التاريخ:</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">🏗️ المشروع:</label>
                    <select class="form-select" name="project">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = mysqli_query($conn, "SELECT id, name FROM projects");
                        while($row = mysqli_fetch_assoc($prj)){
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">🚛 المورد:</label>
                    <select class="form-select" name="supplier">
                        <option value="">-- الكل --</option>
                        <?php
                        $sup = mysqli_query($conn, "SELECT id, name FROM suppliers");
                        while($row = mysqli_fetch_assoc($sup)){
                            $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <button class="btn btn-primary w-100"><i class="fa fa-search me-2"></i>بحث</button>
                </div>
            </form>
        </div>
    </div>

    <!-- كروت الاحصائيات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center shadow-sm border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">⏱️ إجمالي ساعات العمل</h5>
                    <p class="fs-4 fw-bold"><?php echo !empty($totals['total_work']) ? $totals['total_work'] : 0; ?> ساعة</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">⚠️ إجمالي ساعات الأعطال</h5>
                    <p class="fs-4 fw-bold"><?php echo !empty($totals['total_fault']) ? $totals['total_fault'] : 0; ?> ساعة</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning">⏸️ إجمالي ساعات الاستعداد</h5>
                    <p class="fs-4 fw-bold"><?php echo !empty($totals['total_standby']) ? $totals['total_standby'] : 0; ?> ساعة</p>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول البيانات -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead class="table-dark">
                <tr>
                    <th>التاريخ</th>
                    <th>المشروع</th>
                    <th>المورد</th>
                    <th>الآلية</th>
                    <th>كود الآلية</th>
                    <th>السائق</th>
                    <th>الشفت</th>
                    <th>⏱️ ساعات العمل</th>
                    <th>⚠️ ساعات الأعطال</th>
                    <th>⏸️ ساعات الاستعداد</th>
                    <th>📒 ملاحظات العمل</th>
                    <th>📒 ملاحظات الأعطال</th>
                </tr>
                </thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $row['project_name']; ?></td>
                    <td><?php echo $row['supplier_name']; ?></td>
                    <td><?php echo $row['equipment_name']; ?></td>
                    <td><?php echo $row['equipment_code']; ?></td>
                    <td><?php echo $row['driver_name']; ?></td>
                    <td><?php echo $row['shift']; ?></td>
                    <td class="text-success fw-bold"><?php echo $row['total_work_hours']; ?></td>
                    <td class="text-danger fw-bold"><?php echo $row['total_fault_hours']; ?></td>
                    <td class="text-warning fw-bold"><?php echo $row['standby_hours']; ?></td>
                    <td><?php echo $row['work_notes']; ?></td>
                    <td><?php echo $row['fault_notes']; ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
