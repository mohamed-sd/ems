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
	<title>إيكوبيشن | تقرير العقود</title>
	<link rel="stylesheet" href="/ems/assets/css/all.min.css">
	<link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    <script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
    <style>
        .main { font-family: 'Cairo', sans-serif; }
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-item {
            background: rgba(12, 28, 62, 0.03);
            border: 1px solid rgba(12, 28, 62, 0.08);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .summary-item .label {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .summary-item .value {
            font-size: 16px;
            color: #0c1c3e;
            font-weight: 800;
        }
        .report-progress .progress,
        .progress.report-progress {
            height: 20px;
            border-radius: 999px;
            background: #eef2f7;
        }
        .report-progress .progress-bar {
            font-weight: 700;
            font-size: 12px;
        }
        .card-header h5 { margin: 0; }
        .table thead th {
            background: #f8fafc;
            color: #0c1c3e;
            font-weight: 800;
        }
    </style>
</head>
<body>

<?php 
include('../insidebar.php'); 

$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

$contract_filter = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

$sql_contracts = "SELECT c.id, p.name AS project_name 
                  FROM contracts c 
                  LEFT JOIN mines m ON c.mine_id = m.id
                  LEFT JOIN project p ON m.project_id = p.id";
$contracts = mysqli_query($conn, $sql_contracts);

$contract_data = null;
$monthly_stats = null;

if ($contract_filter > 0) {
    // بيانات العقد الأساسية
    $sql_info = "
    SELECT 
        c.id AS contract_id,
        c.id AS contract_id,
        m.mine_name,
        m.mine_code,
        p.name AS project_name,
        c.contract_signing_date,
        c.contract_duration_months,
        c.hours_monthly_target,
        c.forecasted_contracted_hours,
        IFNULL(SUM(t.total_work_hours),0) AS actual_hours,
        (c.forecasted_contracted_hours - IFNULL(SUM(t.executed_hours),0)) AS remaining_hours
    FROM contracts c
    LEFT JOIN mines m ON c.mine_id = m.id
    LEFT JOIN project p ON m.project_id = p.id
    LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = $contract_filter
    GROUP BY c.id, m.mine_name, p.name, c.contract_signing_date, c.contract_duration_months";
    $contract_data_res = mysqli_query($conn, $sql_info);
    if ($contract_data_res) {
        $contract_data = mysqli_fetch_assoc($contract_data_res);
    } else {
        error_log('contract_report.php sql_info failed: ' . mysqli_error($conn));
    }

    // إحصائية شهرية
    $sql_monthly = "
    SELECT 
        YEAR(t.date) AS year,
        MONTH(t.date) AS month,
        SUM(t.executed_hours) AS actual_hours,
        c.hours_monthly_target
    FROM contracts c
    LEFT JOIN mines m ON c.mine_id = m.id
    LEFT JOIN project p ON m.project_id = p.id
    LEFT JOIN operations o ON o." . $operations_project_column . " = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = $contract_filter
    GROUP BY YEAR(t.date), MONTH(t.date), c.hours_monthly_target
    ORDER BY year, month";

    $monthly_stats = mysqli_query($conn, $sql_monthly);
    if (!$monthly_stats) {
        error_log('contract_report.php sql_monthly failed: ' . mysqli_error($conn));
    }
}
?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fa-solid fa-file-contract"></i></div>
            تقرير إحصائية العقود
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
                    <label><i class="fas fa-file-signature"></i> اختر العقد</label>
                    <select name="contract">
                        <option value="">-- اختر --</option>
                        <?php while($row = mysqli_fetch_assoc($contracts)) {
                            $selected = ($contract_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['project_name']}</option>";
                        } ?>
                    </select>
                </div>
                <button type="submit"><i class="fa fa-eye"></i> عرض التقرير</button>
            </form>
        </div>
    </div>

    <?php if ($contract_data) { ?>
        <!-- تفاصيل العقد -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-thumbtack"></i> تفاصيل العقد</h5>
            </div>
            <div class="card-body">
                <div class="report-summary">
                    <div class="summary-item"><div class="label">المشروع</div><div class="value"><?php echo $contract_data['project_name']; ?></div></div>
                    <div class="summary-item"><div class="label">تاريخ التوقيع</div><div class="value"><?php echo $contract_data['contract_signing_date']; ?></div></div>
                    <div class="summary-item"><div class="label">مدة العقد</div><div class="value"><?php echo $contract_data['contract_duration_months']; ?> شهور</div></div>
                    <div class="summary-item"><div class="label">الهدف الشهري</div><div class="value"><?php echo $contract_data['hours_monthly_target']; ?></div></div>
                    <div class="summary-item"><div class="label">إجمالي الساعات المتوقعة</div><div class="value"><?php echo $contract_data['forecasted_contracted_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">المنفذ فعلياً</div><div class="value"><?php echo $contract_data['actual_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">المتبقي</div><div class="value"><?php echo $contract_data['remaining_hours']; ?></div></div>
                </div>
                <div class="report-progress">
                    <div style="font-weight:700; margin-bottom:8px; color:#0c1c3e;">نسبة الإنجاز الكلية</div>
                        <?php 
                        $overall_percent = ($contract_data['forecasted_contracted_hours'] > 0) 
                            ? round(($contract_data['actual_hours'] / $contract_data['forecasted_contracted_hours']) * 100, 2) 
                            : 0;

                        $color = "bg-danger";
                        if ($overall_percent >= 80) {
                            $color = "bg-success";
                        } elseif ($overall_percent >= 50) {
                            $color = "bg-warning";
                        }
                        ?>
                        <div class="progress" style="max-width:480px;">
                            <div class="progress-bar <?php echo $color; ?>" 
                                 role="progressbar" style="width: <?php echo $overall_percent; ?>%;">
                                <?php echo $overall_percent; ?> %
                            </div>
                        </div>
                </div>
            </div>
        </div>

        <!-- الأداء الشهري -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-column"></i> الأداء الشهري</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead>
                        <tr>
                            <th>السنة</th>
                            <th>الشهر</th>
                            <th>المنفذ</th>
                            <th>الهدف الشهري</th>
                            <th>نسبة الإنجاز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $labels = array();
                        $actual = array();
                        $target = array();
                        $percentages = array();

                        if ($monthly_stats) {
                        mysqli_data_seek($monthly_stats, 0);
                        while($row = mysqli_fetch_assoc($monthly_stats)) { 
                            $labels[] = $row['year']."-".$row['month'];
                            $actual[] = isset($row['actual_hours']) ? $row['actual_hours'] : 0;
                            $target[] = isset($row['hours_monthly_target']) ? $row['hours_monthly_target'] : 0;

                            $percent = ($row['hours_monthly_target'] > 0) 
                                       ? round(($row['actual_hours'] / $row['hours_monthly_target']) * 100, 2)
                                       : 0;
                            $percentages[] = $percent;

                            $color = "bg-danger";
                            if ($percent >= 80) {
                                $color = "bg-success";
                            } elseif ($percent >= 50) {
                                $color = "bg-warning";
                            }
                        ?>
                        <tr>
                            <td><?php echo $row['year']; ?></td>
                            <td><?php echo $row['month']; ?></td>
                            <td><?php echo $row['actual_hours']; ?></td>
                            <td><?php echo $row['hours_monthly_target']; ?></td>
                            <td>
                                <div class="progress report-progress">
                                    <div class="progress-bar <?php echo $color; ?>" role="progressbar" 
                                         style="width: <?php echo $percent; ?>%;">
                                        <?php echo $percent; ?> %
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- الرسم البياني -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line"></i> الرسم البياني</h5>
            </div>
            <div class="card-body">
                <canvas id="chart" height="100"></canvas>
            </div>
        </div>

        <script>
        const ctx = document.getElementById('chart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'المنفذ',
                        data: <?php echo json_encode($actual); ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)'
                    },
                    {
                        label: 'الهدف الشهري',
                        data: <?php echo json_encode($target); ?>,
                        backgroundColor: 'rgba(232, 184, 0, 0.75)'
                    },
                    {
                        label: 'نسبة الإنجاز (%)',
                        data: <?php echo json_encode($percentages); ?>,
                        type: 'line',
                        borderColor: '#0c1c3e',
                        backgroundColor: 'transparent',
                        yAxisID: 'percentage'
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'ساعات' }
                    },
                    percentage: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'نسبة الإنجاز %' },
                        ticks: { callback: (value) => value + "%" }
                    }
                }
            }
        });
        </script>
    <?php } ?>

</div>

<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>



