<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | التقارير </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <?php include('../insidebar.php'); 

include '../config.php';
$contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

$sql_contracts = "SELECT c.id, p.name AS project_name 
                  FROM contracts c 
                  JOIN projects p ON c.project = p.id";
$contracts = mysqli_query($conn, $sql_contracts);

$contract_data = null;
$monthly_stats = [];

if (!empty($contract_filter)) {
    // بيانات العقد الأساسية
    $sql_info = "
    SELECT 
        c.id AS contract_id,
        p.name AS project_name,
        c.contract_signing_date,
        c.contract_duration_months,
        c.hours_monthly_target,
        c.forecasted_contracted_hours,
        IFNULL(SUM(t.total_work_hours),0) AS actual_hours,
        (c.forecasted_contracted_hours - IFNULL(SUM(t.total_work_hours),0)) AS remaining_hours
    FROM contracts c
    JOIN projects p ON c.project = p.id
    LEFT JOIN operations o ON o.project = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
   LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY c.id, p.name, c.contract_signing_date, c.contract_duration_months";
    
    $contract_data = mysqli_fetch_assoc(mysqli_query($conn, $sql_info));

    // إحصائية شهرية
    $sql_monthly = "
    SELECT 
        YEAR(t.date) AS year,
        MONTH(t.date) AS month,
        SUM(t.total_work_hours) AS actual_hours,
        c.hours_monthly_target
    FROM contracts c
    JOIN projects p ON c.project = p.id
    LEFT JOIN operations o ON o.project = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
LEFT JOIN timesheet t ON t.operator = o.id
WHERE c.id = '$contract_filter'
    GROUP BY YEAR(t.date), MONTH(t.date), c.hours_monthly_target
    ORDER BY year, month";
    
    $monthly_stats = mysqli_query($conn, $sql_monthly);
}
 
 ?>

  <div class="main">


    <h2>📊 تقرير إحصائية العقود</h2>

    <form method="GET">
        <label>اختر العقد:</label>
        <select name="contract">
            <option value="">-- اختر --</option>
            <?php while($row = mysqli_fetch_assoc($contracts)) { 
                $selected = ($contract_filter == $row['id']) ? "selected" : "";
                echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['project_name']}</option>";
            } ?>
        </select>
        <button type="submit">عرض</button>
    </form>

    <?php if ($contract_data) { ?>
        <h3>📌 تفاصيل العقد</h3>
        <ul>
            <li>المشروع: <?php echo $contract_data['project_name']; ?></li>
            <li>تاريخ التوقيع: <?php echo $contract_data['contract_signing_date']; ?></li>
            <li>مدة العقد: <?php echo $contract_data['contract_duration_months']; ?> شهور</li>
            <li>الهدف الشهري للساعات: <?php echo $contract_data['hours_monthly_target']; ?></li>
            <li>إجمالي الساعات المتوقعة: <?php echo $contract_data['forecasted_contracted_hours']; ?></li>
            <li>الساعات المنفذة فعليًا: <?php echo $contract_data['actual_hours']; ?></li>
            <li>المتبقي: <?php echo $contract_data['remaining_hours']; ?></li>
            <li>
                نسبة الإنجاز الكلية:
                <?php 
                $overall_percent = ($contract_data['forecasted_contracted_hours'] > 0) 
                    ? round(($contract_data['actual_hours'] / $contract_data['forecasted_contracted_hours']) * 100, 2) 
                    : 0;

                // تحديد اللون حسب النسبة
                $color = "red";
                if ($overall_percent >= 80) {
                    $color = "green";
                } elseif ($overall_percent >= 50) {
                    $color = "orange";
                }
                ?>
                <div style="width: 100%; background: #eee; border-radius: 8px; overflow: hidden; max-width: 400px;">
                    <div style="width: <?php echo $overall_percent; ?>%; background: <?php echo $color; ?>; 
                                color: #fff; text-align: center; padding: 5px 0;">
                        <?php echo $overall_percent; ?> %
                    </div>
                </div>
            </li>
        </ul>

        <h3>📊 الأداء الشهري</h3>
        <table border="1" cellpadding="5" cellspacing="0">
            <tr>
                <th>السنة</th>
                <th>الشهر</th>
                <th>المنفذ</th>
                <th>الهدف الشهري</th>
                <th>نسبة الإنجاز</th>
            </tr>
            <?php 
            $labels = [];
            $actual = [];
            $target = [];
            $percentages = [];

            mysqli_data_seek($monthly_stats, 0); // إعادة المؤشر لبداية النتائج
            while($row = mysqli_fetch_assoc($monthly_stats)) { 
                $labels[] = $row['year']."-".$row['month'];
                $actual[] = $row['actual_hours'] ?? 0;
                $target[] = $row['hours_monthly_target'] ?? 0;

                $percent = ($row['hours_monthly_target'] > 0) 
                           ? round(($row['actual_hours'] / $row['hours_monthly_target']) * 100, 2)
                           : 0;
                $percentages[] = $percent;

                // تحديد اللون حسب النسبة
                $color = "red";
                if ($percent >= 80) {
                    $color = "green";
                } elseif ($percent >= 50) {
                    $color = "orange";
                }
            ?>
            <tr>
                <td><?php echo $row['year']; ?></td>
                <td><?php echo $row['month']; ?></td>
                <td><?php echo $row['actual_hours']; ?></td>
                <td><?php echo $row['hours_monthly_target']; ?></td>
                <td>
                    <div style="width: 120px; background: #eee; border-radius: 8px; overflow: hidden;">
                        <div style="width: <?php echo $percent; ?>%; background: <?php echo $color; ?>; 
                                    color: #fff; text-align: center; font-size: 12px; padding: 2px 0;">
                            <?php echo $percent; ?> %
                        </div>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>

        <canvas id="chart" width="600" height="300"></canvas>
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

</body>
</html>