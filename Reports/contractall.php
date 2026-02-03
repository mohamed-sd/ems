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
    <title> إيكوبيشن | التقارير </title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- أيقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- استايلك القديم -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />

    <style>
        body { font-family: Tahoma, Arial; }
        .nav-pills .nav-link.active { background-color: #0d6efd; }
    </style>
</head>

<body>
    <?php include('../insidebar.php');
    include '../config.php';

    $contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

    $sql_contracts = "SELECT c.id, p.name AS project_name 
                      FROM contracts c 
                      JOIN project p ON c.project = p.id";
    $contracts = mysqli_query($conn, $sql_contracts);

    $contract_data = $time_vs_progress = $faults = $suppliers = $equipments = $drivers = $variance = null;

    if (!empty($contract_filter)) {
        // تفاصيل العقد
        $sql_info = "
        SELECT 
            c.id AS contract_id,
            p.name AS project_name,
            c.contract_signing_date,
            c.contract_duration_months,
            c.hours_monthly_target,
            c.forecasted_contracted_hours,
            IFNULL(SUM(t.total_work_hours),0) AS actual_hours
        FROM contracts c
        JOIN project p ON c.project = p.id
        LEFT JOIN operations o ON o.project = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY c.id, p.name";
        $contract_data = mysqli_fetch_assoc(mysqli_query($conn, $sql_info));

        // الزمن مقابل الإنجاز
        $sql_time = "
        SELECT 
            (TIMESTAMPDIFF(MONTH, c.contract_signing_date, CURDATE()) / c.contract_duration_months) * 100 AS time_progress,
            (IFNULL(SUM(t.total_work_hours),0) / c.forecasted_contracted_hours) * 100 AS work_progress
        FROM contracts c
        LEFT JOIN project p ON c.project = p.id
        LEFT JOIN operations o ON o.project = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'";
        $time_vs_progress = mysqli_fetch_assoc(mysqli_query($conn, $sql_time));

        // الأعطال
        $sql_faults = "
        SELECT SUM(t.total_fault_hours) AS total_fault_hours
        FROM contracts c
        JOIN project p ON c.project = p.id
        LEFT JOIN operations o ON o.project = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'";
        $faults = mysqli_fetch_assoc(mysqli_query($conn, $sql_faults));

        // الموردين
        $sql_suppliers = "
        SELECT s.name AS supplier_name, SUM(t.total_work_hours) AS total_work_hours
        FROM contracts c
        JOIN project p ON c.project = p.id
        JOIN operations o ON o.project = p.id
        JOIN equipments e ON e.id = o.equipment
        JOIN suppliers s ON e.suppliers = s.id
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY s.name";
        $suppliers = mysqli_query($conn, $sql_suppliers);

        // الآليات
        $sql_equipments = "
        SELECT e.name AS equipment_name,
               SUM(t.total_work_hours) AS work_hours,
               SUM(t.total_fault_hours) AS fault_hours
        FROM contracts c
        JOIN project p ON c.project = p.id
        JOIN operations o ON o.project = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY e.name";
        $equipments = mysqli_query($conn, $sql_equipments);

        // السائقين
        $sql_drivers = "
        SELECT d.name AS driver_name, SUM(t.total_work_hours) AS driver_hours
        FROM contracts c
        JOIN project p ON c.project = p.id
        JOIN operations o ON o.project = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        JOIN drivers d ON t.driver = d.id
        WHERE c.id = '$contract_filter'
        GROUP BY d.name";
        $drivers = mysqli_query($conn, $sql_drivers);

        // الانحراف
        $sql_variance = "
        SELECT c.forecasted_contracted_hours AS planned_hours,
               IFNULL(SUM(t.total_work_hours),0) AS actual_hours,
               (IFNULL(SUM(t.total_work_hours),0) - c.forecasted_contracted_hours) AS variance
        FROM contracts c
        JOIN project p ON c.project = p.id
        LEFT JOIN operations o ON o.project = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY c.id";
        $variance = mysqli_fetch_assoc(mysqli_query($conn, $sql_variance));
    }
    ?>

    <div class="main container mt-4">

        <h2 class="mb-4"><i class="fa-solid fa-chart-line"></i> تقارير تفصيلية للعقد</h2>

        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">اختر العقد:</label>
                <select name="contract" class="form-select">
                    <option value="">-- اختر --</option>
                    <?php while($row = mysqli_fetch_assoc($contracts)) { 
                        $selected = ($contract_filter == $row['id']) ? "selected" : "";
                        echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['project_name']}</option>";
                    } ?>
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary w-100">عرض</button>
            </div>
        </form>

        <?php if ($contract_data) { ?>
        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#basic">📝 التفاصيل الأساسية</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#time">⏳ الزمن مقابل الإنجاز</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#faults">⚠️ الأعطال</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suppliers">🚛 الموردين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#equipments">🏗️ الآليات</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#drivers">👷 السائقين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#variance">📉 الانحراف</button></li>
        </ul>

        <div class="tab-content">
            <!-- التفاصيل الأساسية -->
            <div class="tab-pane fade show active" id="basic">
                <div class="card card-body">
                    <ul class="list-group">
                        <li class="list-group-item">المشروع: <?= $contract_data['project_name'] ?></li>
                        <li class="list-group-item">تاريخ التوقيع: <?= $contract_data['contract_signing_date'] ?></li>
                        <li class="list-group-item">مدة العقد: <?= $contract_data['contract_duration_months'] ?> شهور</li>
                        <li class="list-group-item">الهدف الشهري: <?= $contract_data['hours_monthly_target'] ?></li>
                        <li class="list-group-item">الإجمالي المتوقع: <?= $contract_data['forecasted_contracted_hours'] ?></li>
                        <li class="list-group-item">المنفذ: <?= $contract_data['actual_hours'] ?></li>
                    </ul>
                </div>
            </div>

            <!-- الزمن مقابل الإنجاز -->
            <div class="tab-pane fade" id="time">
                <div class="alert alert-info">
                    التقدم الزمني: <?= round($time_vs_progress['time_progress'], 2) ?> % <br>
                    التقدم الفعلي: <?= round($time_vs_progress['work_progress'], 2) ?> %
                </div>
            </div>

            <!-- الأعطال -->
            <div class="tab-pane fade" id="faults">
                <div class="alert alert-warning">
                   <p><?php echo isset($faults['total_fault_hours']) ? $faults['total_fault_hours'] : 0; ?> ساعة</p>
                </div>
            </div>

            <!-- الموردين -->
            <div class="tab-pane fade" id="suppliers">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr><th>المورد</th><th>إجمالي الساعات</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($suppliers)) { ?>
                        <tr>
                            <td><?= $row['supplier_name'] ?></td>
                            <td><?= $row['total_work_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- الآليات -->
            <div class="tab-pane fade" id="equipments">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr><th>الآلية</th><th>ساعات العمل</th><th>ساعات الأعطال</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($equipments)) { ?>
                        <tr>
                            <td><?= $row['equipment_name'] ?></td>
                            <td><?= $row['work_hours'] ?></td>
                            <td><?= $row['fault_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- السائقين -->
            <div class="tab-pane fade" id="drivers">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr><th>السائق</th><th>إجمالي الساعات</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($drivers)) { ?>
                        <tr>
                            <td><?= $row['driver_name'] ?></td>
                            <td><?= $row['driver_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- الانحراف -->
            <div class="tab-pane fade" id="variance">
                <div class="card card-body">
                    <p>المخطط: <?= $variance['planned_hours'] ?> ساعة</p>
                    <p>المنفذ: <?= $variance['actual_hours'] ?> ساعة</p>
                    <p>الانحراف: <?= $variance['variance'] ?> ساعة</p>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
