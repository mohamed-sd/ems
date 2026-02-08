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
    <title>إيكوبيشن | التقارير</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- ملف التصميم القديم -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>

<body>

<?php
include('../insidebar.php');
include '../config.php';

$project_filter = isset($_GET['project']) ? $_GET['project'] : '';
$driver_filter  = isset($_GET['driver']) ? $_GET['driver'] : '';
$start_date     = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date       = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$sql = "
SELECT 
    d.name AS driver_name,
    p.name AS project_name,
    e.name AS equipment_name,
    t.date,
    SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN drivers d ON t.driver = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN project p ON o.project_id = p.id
WHERE 1=1
";

// فلترة بالتاريخ
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND t.date BETWEEN '$start_date' AND '$end_date' ";
} elseif (!empty($start_date)) {
    $sql .= " AND t.date = '$start_date' ";
}

// فلترة بالمشروع
if (!empty($project_filter)) {
    $sql .= " AND p.id = '$project_filter' ";
}

// فلترة بالسائق
if (!empty($driver_filter)) {
    $sql .= " AND d.id = '$driver_filter' ";
}

$sql .= " GROUP BY d.name, p.name, e.name, t.date ORDER BY t.date, d.name";
$result = mysqli_query($conn, $sql);
?>

<div class="main p-4">

    <div class="container">
        <h2 class="mb-4 text-primary"><i class="fa-solid fa-truck"></i> تقرير ساعات عمل السائقين</h2>

        <!-- فورم الفلترة -->
        <form method="GET" class="row g-3 align-items-center mb-4">
            <div class="col-md-3">
                <label class="form-label">🏗️ المشروع:</label>
                <select name="project" class="form-select">
                    <option value="">-- الكل --</option>
                    <?php
                    $prj = mysqli_query($conn, "SELECT id, name FROM project");
                    while ($row = mysqli_fetch_assoc($prj)) {
                        $selected = ($project_filter == $row['id']) ? "selected" : "";
                        echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">👨‍🔧 السائق:</label>
                <select name="driver" class="form-select">
                    <option value="">-- الكل --</option>
                    <?php
                    $drv = mysqli_query($conn, "SELECT id, name FROM drivers");
                    while ($row = mysqli_fetch_assoc($drv)) {
                        $selected = ($driver_filter == $row['id']) ? "selected" : "";
                        echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">📅 من:</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">📅 إلى:</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit"><i class="fa fa-search"></i> بحث</button>
            </div>
        </form>

        <!-- جدول التقرير -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                <tr>
                    <th>السائق</th>
                    <th>المشروع</th>
                    <th>الآلية</th>
                    <th>التاريخ</th>
                    <th>⏱️ مجموع الساعات</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $grand_total = 0;
                while ($row = mysqli_fetch_assoc($result)) {
                    $grand_total += $row['total_hours'];
                    echo "<tr>";
                    echo "<td>" . $row['driver_name'] . "</td>";
                    echo "<td>" . $row['project_name'] . "</td>";
                    echo "<td>" . $row['equipment_name'] . "</td>";
                    echo "<td>" . $row['date'] . "</td>";
                    echo "<td>" . $row['total_hours'] . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-success mt-3">
            <h5 class="mb-0">✅ إجمالي الساعات: <?php echo $grand_total; ?> ساعة</h5>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
