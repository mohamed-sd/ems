<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">

    <!-- ملف التصميم القديم -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

    <style>
        .main { font-family: 'Cairo', sans-serif; }

        .report-table thead th {
            background: #f8fafc;
            color: #0c1c3e;
            font-weight: 800;
            border-color: rgba(12, 28, 62, 0.1);
        }

        .report-table td {
            border-color: rgba(12, 28, 62, 0.08);
            color: #0c1c3e;
            font-weight: 600;
        }

        .total-hours-box {
            background: linear-gradient(135deg, rgba(13, 148, 136, 0.12), rgba(13, 148, 136, 0.06));
            border: 1px solid rgba(13, 148, 136, 0.25);
            border-radius: 14px;
            padding: 14px 16px;
            color: #0f766e;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(15, 118, 110, 0.12);
        }

        .form-grid { align-items: end; }
    </style>
</head>

<body>

<?php
include('../insidebar.php');

$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

$project_filter = isset($_GET['project']) ? intval($_GET['project']) : 0;
$driver_filter  = isset($_GET['driver']) ? intval($_GET['driver']) : 0;
$start_date     = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$end_date       = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';

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
JOIN project p ON o." . $operations_project_column . " = p.id
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
if (!$result) {
    error_log('driverAndsupplerscontract.php report query failed: ' . mysqli_error($conn));
}
?>

<div class="main">

    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fa-solid fa-truck"></i></div>
            تقرير ساعات عمل السائقين
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="reports.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلاتر التقرير</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid">
                <div>
                    <label><i class="fas fa-diagram-project"></i> المشروع</label>
                    <select name="project">
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

                <div>
                    <label><i class="fas fa-user-gear"></i> السائق</label>
                    <select name="driver">
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

                <div>
                    <label><i class="fas fa-calendar-day"></i> من</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>

                <div>
                    <label><i class="fas fa-calendar-check"></i> إلى</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>

                <button type="submit"><i class="fa fa-search"></i> بحث</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> نتائج التقرير</h5>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle report-table">
                    <thead>
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

            <div class="total-hours-box mt-3">
                <i class="fas fa-check-circle"></i>
                إجمالي الساعات: <?php echo $grand_total; ?> ساعة
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

