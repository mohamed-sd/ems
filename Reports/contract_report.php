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
	<title>إيكوبيشن | تقرير العقود</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    LEFT JOIN operations o ON o.project_id = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY c.id, m.mine_name, p.name, c.contract_signing_date, c.contract_duration_months";
    
    $contract_data = mysqli_fetch_assoc(mysqli_query($conn, $sql_info));

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
    LEFT JOIN operations o ON o.project_id = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY YEAR(t.date), MONTH(t.date), c.hours_monthly_target
    ORDER BY year, month";
    
    $monthly_stats = mysqli_query($conn, $sql_monthly);
}
?>

<div class="main container-fluid py-4">

    <h2 class="fw-bold mb-4"><i class="fa-solid fa-file-contract text-primary me-2"></i> تقرير إحصائية العقود</h2>

    <!-- فورم اختيار العقد -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
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
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-eye me-2"></i>عرض</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($contract_data) { ?>
        <!-- تفاصيل العقد -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white fw-bold">
                📌 تفاصيل العقد
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><b>المشروع:</b> <?php echo $contract_data['project_name']; ?></li>
                    <li><b>تاريخ التوقيع:</b> <?php echo $contract_data['contract_signing_date']; ?></li>
                    <li><b>مدة العقد:</b> <?php echo $contract_data['contract_duration_months']; ?> شهور</li>
                    <li><b>الهدف الشهري للساعات:</b> <?php echo $contract_data['hours_monthly_target']; ?></li>
                    <li><b>إجمالي الساعات المتوقعة:</b> <?php echo $contract_data['forecasted_contracted_hours']; ?></li>
                    <li><b>الساعات المنفذة فعليًا:</b> <?php echo $contract_data['actual_hours']; ?></li>
                    <li><b>المتبقي:</b> <?php echo $contract_data['remaining_hours']; ?></li>
                    <li class="mt-3">
                        <b>نسبة الإنجاز الكلية:</b>
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
                        <div class="progress mt-2" style="max-width:400px;">
                            <div class="progress-bar <?php echo $color; ?>" 
                                 role="progressbar" style="width: <?php echo $overall_percent; ?>%;">
                                <?php echo $overall_percent; ?> %
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- الأداء الشهري -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">
                📊 الأداء الشهري
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead class="table-secondary">
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
                                <div class="progress" style="height: 20px;">
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

        <!-- الرسم البياني -->
        <div class="card shadow-sm">
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
                        backgroundColor: 'rgba(75, 192, 192, 0.7)'
                    },
                    {
                        label: 'الهدف الشهري',
                        data: <?php echo json_encode($target); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)'
                    },
                    {
                        label: 'نسبة الإنجاز (%)',
                        data: <?php echo json_encode($percentages); ?>,
                        type: 'line',
                        borderColor: 'blue',
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
