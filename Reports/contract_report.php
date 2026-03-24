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
	<title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ø¹Ù‚ÙˆØ¯</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
include '../config.php';

$contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

$sql_contracts = "SELECT c.id, p.name AS project_name 
                  FROM contracts c 
                  JOIN project p ON c.mine_id = p.id";
$contracts = mysqli_query($conn, $sql_contracts);

$contract_data = null;
$monthly_stats = null;

if (!empty($contract_filter)) {
    // Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
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
    LEFT JOIN operations o ON o.project_id = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY c.id, m.mine_name, p.name, c.contract_signing_date, c.contract_duration_months";
    
    $contract_data = mysqli_fetch_assoc(mysqli_query($conn, $sql_info));

    // Ø¥Ø­ØµØ§Ø¦ÙŠØ© Ø´Ù‡Ø±ÙŠØ©
    $sql_monthly = "
    SELECT 
        YEAR(t.date) AS year,
        MONTH(t.date) AS month,
        SUM(t.executed_hours) AS actual_hours,
        c.hours_monthly_target
    FROM contracts c
    LEFT JOIN mines m ON c.mine_id = m.id
    LEFT JOIN project p ON m.project_id = p.id
    LEFT JOIN operations o ON o.project_id = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY YEAR(t.date), MONTH(t.date), c.hours_monthly_target
    ORDER BY year, month";
    
    $monthly_stats = mysqli_query($conn, $sql_monthly);
}
?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fa-solid fa-file-contract"></i></div>
            ØªÙ‚Ø±ÙŠØ± Ø¥Ø­ØµØ§Ø¦ÙŠØ© Ø§Ù„Ø¹Ù‚ÙˆØ¯
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="reports.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ø¹Ù‚Ø¯</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid" style="align-items:end;">
                <div>
                    <label><i class="fas fa-file-signature"></i> Ø§Ø®ØªØ± Ø§Ù„Ø¹Ù‚Ø¯</label>
                    <select name="contract">
                        <option value="">-- Ø§Ø®ØªØ± --</option>
                        <?php while($row = mysqli_fetch_assoc($contracts)) {
                            $selected = ($contract_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>Ø¹Ù‚Ø¯ #{$row['id']} - {$row['project_name']}</option>";
                        } ?>
                    </select>
                </div>
                <button type="submit"><i class="fa fa-eye"></i> Ø¹Ø±Ø¶ Ø§Ù„ØªÙ‚Ø±ÙŠØ±</button>
            </form>
        </div>
    </div>

    <?php if ($contract_data) { ?>
        <!-- ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯ -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-thumbtack"></i> ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯</h5>
            </div>
            <div class="card-body">
                <div class="report-summary">
                    <div class="summary-item"><div class="label">Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div><div class="value"><?php echo $contract_data['project_name']; ?></div></div>
                    <div class="summary-item"><div class="label">ØªØ§Ø±ÙŠØ® Ø§Ù„ØªÙˆÙ‚ÙŠØ¹</div><div class="value"><?php echo $contract_data['contract_signing_date']; ?></div></div>
                    <div class="summary-item"><div class="label">Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯</div><div class="value"><?php echo $contract_data['contract_duration_months']; ?> Ø´Ù‡ÙˆØ±</div></div>
                    <div class="summary-item"><div class="label">Ø§Ù„Ù‡Ø¯Ù Ø§Ù„Ø´Ù‡Ø±ÙŠ</div><div class="value"><?php echo $contract_data['hours_monthly_target']; ?></div></div>
                    <div class="summary-item"><div class="label">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªÙˆÙ‚Ø¹Ø©</div><div class="value"><?php echo $contract_data['forecasted_contracted_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">Ø§Ù„Ù…Ù†ÙØ° ÙØ¹Ù„ÙŠØ§Ù‹</div><div class="value"><?php echo $contract_data['actual_hours']; ?></div></div>
                    <div class="summary-item"><div class="label">Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ</div><div class="value"><?php echo $contract_data['remaining_hours']; ?></div></div>
                </div>
                <div class="report-progress">
                    <div style="font-weight:700; margin-bottom:8px; color:#0c1c3e;">Ù†Ø³Ø¨Ø© Ø§Ù„Ø¥Ù†Ø¬Ø§Ø² Ø§Ù„ÙƒÙ„ÙŠØ©</div>
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

        <!-- Ø§Ù„Ø£Ø¯Ø§Ø¡ Ø§Ù„Ø´Ù‡Ø±ÙŠ -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-column"></i> Ø§Ù„Ø£Ø¯Ø§Ø¡ Ø§Ù„Ø´Ù‡Ø±ÙŠ</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead>
                        <tr>
                            <th>Ø§Ù„Ø³Ù†Ø©</th>
                            <th>Ø§Ù„Ø´Ù‡Ø±</th>
                            <th>Ø§Ù„Ù…Ù†ÙØ°</th>
                            <th>Ø§Ù„Ù‡Ø¯Ù Ø§Ù„Ø´Ù‡Ø±ÙŠ</th>
                            <th>Ù†Ø³Ø¨Ø© Ø§Ù„Ø¥Ù†Ø¬Ø§Ø²</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $labels = array();
                        $actual = array();
                        $target = array();
                        $percentages = array();

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
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ø§Ù„Ø±Ø³Ù… Ø§Ù„Ø¨ÙŠØ§Ù†ÙŠ -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line"></i> Ø§Ù„Ø±Ø³Ù… Ø§Ù„Ø¨ÙŠØ§Ù†ÙŠ</h5>
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
                        label: 'Ø§Ù„Ù…Ù†ÙØ°',
                        data: <?php echo json_encode($actual); ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)'
                    },
                    {
                        label: 'Ø§Ù„Ù‡Ø¯Ù Ø§Ù„Ø´Ù‡Ø±ÙŠ',
                        data: <?php echo json_encode($target); ?>,
                        backgroundColor: 'rgba(232, 184, 0, 0.75)'
                    },
                    {
                        label: 'Ù†Ø³Ø¨Ø© Ø§Ù„Ø¥Ù†Ø¬Ø§Ø² (%)',
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
                        title: { display: true, text: 'Ø³Ø§Ø¹Ø§Øª' }
                    },
                    percentage: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'Ù†Ø³Ø¨Ø© Ø§Ù„Ø¥Ù†Ø¬Ø§Ø² %' },
                        ticks: { callback: (value) => value + "%" }
                    }
                }
            }
        });
        </script>
    <?php } ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

