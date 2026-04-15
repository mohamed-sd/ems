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
    <title> إيكوبيشن | التقارير </title>

    <!-- Bootstrap 5 -->
    <link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- أيقونات -->
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <!-- استايلك القديم -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

    <style>
        .main {
            font-family: 'Cairo', sans-serif;
        }

        .report-tabs {
            gap: 8px;
            flex-wrap: wrap;
        }

        .report-tabs .nav-link {
            border-radius: 999px;
            border: 1px solid rgba(12, 28, 62, 0.12);
            color: #0c1c3e;
            background: #fff;
            font-weight: 700;
            padding: 8px 14px;
            transition: all 0.2s ease;
        }

        .report-tabs .nav-link:hover {
            border-color: rgba(232, 184, 0, 0.45);
            background: rgba(232, 184, 0, 0.12);
            color: #0c1c3e;
        }

        .report-tabs .nav-link.active {
            background: linear-gradient(135deg, #0c1c3e, #1b2f6e);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 8px 22px rgba(12, 28, 62, 0.22);
        }

        .tab-card {
            border: 1px solid rgba(12, 28, 62, 0.08);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(12, 28, 62, 0.08);
            padding: 18px;
        }

        .report-table thead th {
            background: #f8fafc;
            color: #0c1c3e;
            font-weight: 800;
        }

        .summary-list .list-group-item {
            border-color: rgba(12, 28, 62, 0.08);
            font-weight: 600;
            color: #0c1c3e;
        }

        .metric-box {
            border: 1px solid rgba(12, 28, 62, 0.09);
            background: rgba(12, 28, 62, 0.03);
            border-radius: 12px;
            padding: 12px 14px;
            color: #0c1c3e;
            font-weight: 700;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include('../insidebar.php');

    $operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

    $contract_filter = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

    $sql_contracts = "SELECT c.id, m.mine_name, p.name AS project_name 
                      FROM contracts c 
                      LEFT JOIN mines m ON c.mine_id = m.id
                      LEFT JOIN project p ON m.project_id = p.id";
    $contracts = mysqli_query($conn, $sql_contracts);

    $contract_data = $time_vs_progress = $faults = $suppliers = $equipments = $drivers = $variance = null;

    if ($contract_filter > 0) {
        // تفاصيل العقد
        $sql_info = "
        SELECT 
            c.id AS contract_id,
            m.mine_name,
            p.name AS project_name,
            c.contract_signing_date,
            c.contract_duration_months,
            c.hours_monthly_target,
            c.forecasted_contracted_hours,
            IFNULL(SUM(t.executed_hours),0) AS actual_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter
        GROUP BY c.id, m.mine_name, p.name";
        $contract_data_res = mysqli_query($conn, $sql_info);
        if ($contract_data_res) {
            $contract_data = mysqli_fetch_assoc($contract_data_res);
        } else {
            error_log('contractall.php sql_info failed: ' . mysqli_error($conn));
        }

        // الزمن مقابل الإنجاز
        $sql_time = "
        SELECT 
            (TIMESTAMPDIFF(MONTH, c.contract_signing_date, CURDATE()) / c.contract_duration_months) * 100 AS time_progress,
            (IFNULL(SUM(t.executed_hours),0) / c.forecasted_contracted_hours) * 100 AS work_progress
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter";
        $time_vs_progress_res = mysqli_query($conn, $sql_time);
        if ($time_vs_progress_res) {
            $time_vs_progress = mysqli_fetch_assoc($time_vs_progress_res);
        } else {
            error_log('contractall.php sql_time failed: ' . mysqli_error($conn));
        }

        // الأعطال
        $sql_faults = "
        SELECT SUM(t.total_fault_hours) AS total_fault_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter";
        $faults_res = mysqli_query($conn, $sql_faults);
        if ($faults_res) {
            $faults = mysqli_fetch_assoc($faults_res);
        } else {
            error_log('contractall.php sql_faults failed: ' . mysqli_error($conn));
        }

        // الموردين
        $sql_suppliers = "
        SELECT s.name AS supplier_name, SUM(t.executed_hours) AS total_work_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o." . $operations_project_column . " = p.id
        JOIN equipments e ON e.id = o.equipment
        JOIN suppliers s ON e.suppliers = s.id
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter
        GROUP BY s.name";
        $suppliers = mysqli_query($conn, $sql_suppliers);
        if (!$suppliers) {
            error_log('contractall.php sql_suppliers failed: ' . mysqli_error($conn));
        }

        // الآليات
        $sql_equipments = "
        SELECT e.name AS equipment_name,
               SUM(t.executed_hours) AS work_hours,
               SUM(t.total_fault_hours) AS fault_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o." . $operations_project_column . " = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter
        GROUP BY e.name";
        $equipments = mysqli_query($conn, $sql_equipments);
        if (!$equipments) {
            error_log('contractall.php sql_equipments failed: ' . mysqli_error($conn));
        }

        // السائقين
        $sql_drivers = "
        SELECT d.name AS driver_name, SUM(t.executed_hours) AS driver_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o." . $operations_project_column . " = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        JOIN drivers d ON t.driver = d.id
        WHERE c.id = $contract_filter
        GROUP BY d.name";
        $drivers = mysqli_query($conn, $sql_drivers);
        if (!$drivers) {
            error_log('contractall.php sql_drivers failed: ' . mysqli_error($conn));
        }

        // الانحراف
        $sql_variance = "
        SELECT c.forecasted_contracted_hours AS planned_hours,
               IFNULL(SUM(t.executed_hours),0) AS actual_hours,
               (IFNULL(SUM(t.executed_hours),0) - c.forecasted_contracted_hours) AS variance
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = $contract_filter
        GROUP BY c.id";
        $variance_res = mysqli_query($conn, $sql_variance);
        if ($variance_res) {
            $variance = mysqli_fetch_assoc($variance_res);
        } else {
            error_log('contractall.php sql_variance failed: ' . mysqli_error($conn));
        }
    }
    ?>

    <div class="main">
        <div class="page-header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fa-solid fa-chart-line"></i></div>
                تقارير تفصيلية للعقد
            </h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="reports.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> اختيار العقد</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="form-grid" style="align-items:end;">
                    <div>
                        <label><i class="fas fa-file-contract"></i> اختر العقد</label>
                        <select name="contract">
                            <option value="">-- اختر --</option>
                            <?php while($row = mysqli_fetch_assoc($contracts)) {
                                $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['mine_name']} ({$row['project_name']})</option>";
                            } ?>
                        </select>
                    </div>
                    <button type="submit"><i class="fa fa-eye"></i> عرض التقرير</button>
                </form>
            </div>
        </div>

        <?php if ($contract_data) { ?>
        <ul class="nav nav-pills report-tabs mb-3" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#basic"><i class="fas fa-file-lines"></i> التفاصيل الأساسية</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#time"><i class="fas fa-hourglass-half"></i> الزمن مقابل الإنجاز</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#faults"><i class="fas fa-triangle-exclamation"></i> الأعطال</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suppliers"><i class="fas fa-truck"></i> الموردين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#equipments"><i class="fas fa-tractor"></i> الآليات</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#drivers"><i class="fas fa-helmet-safety"></i> السائقين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#variance"><i class="fas fa-chart-line"></i> الانحراف</button></li>
        </ul>

        <div class="tab-content">
            <!-- التفاصيل الأساسية -->
            <div class="tab-pane fade show active" id="basic">
                <div class="tab-card">
                    <ul class="list-group summary-list">
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
                <div class="tab-card">
                    <div class="metric-box">التقدم الزمني: <?= round($time_vs_progress['time_progress'], 2) ?> %</div>
                    <div class="metric-box">التقدم الفعلي: <?= round($time_vs_progress['work_progress'], 2) ?> %</div>
                </div>
            </div>

            <!-- الأعطال -->
            <div class="tab-pane fade" id="faults">
                <div class="tab-card">
                   <p><?php echo isset($faults['total_fault_hours']) ? $faults['total_fault_hours'] : 0; ?> ساعة</p>
                </div>
            </div>

            <!-- الموردين -->
            <div class="tab-pane fade" id="suppliers">
                <div class="tab-card table-responsive">
                <table class="table table-striped report-table">
                    <thead>
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
            </div>

            <!-- الآليات -->
            <div class="tab-pane fade" id="equipments">
                <div class="tab-card table-responsive">
                <table class="table table-bordered report-table">
                    <thead>
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
            </div>

            <!-- السائقين -->
            <div class="tab-pane fade" id="drivers">
                <div class="tab-card table-responsive">
                <table class="table table-hover report-table">
                    <thead>
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
            </div>

            <!-- الانحراف -->
            <div class="tab-pane fade" id="variance">
                <div class="tab-card">
                    <div class="metric-box">المخطط: <?= $variance['planned_hours'] ?> ساعة</div>
                    <div class="metric-box">المنفذ: <?= $variance['actual_hours'] ?> ساعة</div>
                    <div class="metric-box">الانحراف: <?= $variance['variance'] ?> ساعة</div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>



