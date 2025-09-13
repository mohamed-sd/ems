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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />

     <style>
        body { direction: rtl; font-family: Tahoma, Arial; }
        .tabs { margin-top: 20px; }
        .tab { display: none; }
        .tab.active { display: block; }
        .tab-buttons button { margin: 5px; padding: 8px 15px; }
    </style>
</head>

<body>

    <?php include('../insidebar.php');

    include '../config.php';
  // استقبال الفلتر
$contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

$sql_contracts = "SELECT c.id, p.name AS project_name 
                  FROM contracts c 
                  JOIN projects p ON c.project = p.id";
$contracts = mysqli_query($conn, $sql_contracts);

// متغيرات التقارير
$contract_data = $time_vs_progress = $faults = $suppliers = $equipments = $drivers = $variance = null;

if (!empty($contract_filter)) {
    // تفاصيل العقد الأساسية
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
    JOIN projects p ON c.project = p.id
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
    LEFT JOIN projects p ON c.project = p.id
    LEFT JOIN operations o ON o.project = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
   LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'";
    $time_vs_progress = mysqli_fetch_assoc(mysqli_query($conn, $sql_time));

    // الأعطال
    $sql_faults = "
    SELECT 
        SUM(t.total_fault_hours) AS total_fault_hours
    FROM contracts c
    JOIN projects p ON c.project = p.id
    LEFT JOIN operations o ON o.project = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'";
    $faults = mysqli_fetch_assoc(mysqli_query($conn, $sql_faults));

    // الموردين
    $sql_suppliers = "
    SELECT 
        s.name AS supplier_name,
        SUM(t.total_work_hours) AS total_work_hours
    FROM contracts c
    JOIN projects p ON c.project = p.id
    JOIN operations o ON o.project = p.id
    JOIN equipments e ON e.id = o.equipment
    JOIN suppliers s ON e.suppliers = s.id
   LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY s.name";
    $suppliers = mysqli_query($conn, $sql_suppliers);

    // الآليات
    $sql_equipments = "
    SELECT 
        e.name AS equipment_name,
        SUM(t.total_work_hours) AS work_hours,
        SUM(t.total_fault_hours) AS fault_hours
    FROM contracts c
    JOIN projects p ON c.project = p.id
    JOIN operations o ON o.project = p.id
    JOIN equipments e ON e.id = o.equipment
   LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY e.name";
    $equipments = mysqli_query($conn, $sql_equipments);

    // السائقين
    $sql_drivers = "
    SELECT 
        d.name AS driver_name,
        SUM(t.total_work_hours) AS driver_hours
    FROM contracts c
    JOIN projects p ON c.project = p.id
    JOIN operations o ON o.project = p.id
    JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    JOIN drivers d ON t.driver = d.id
    WHERE c.id = '$contract_filter'
    GROUP BY d.name";
    $drivers = mysqli_query($conn, $sql_drivers);

    // الانحراف عن الخطة
    $sql_variance = "
    SELECT 
        c.forecasted_contracted_hours AS planned_hours,
        IFNULL(SUM(t.total_work_hours),0) AS actual_hours,
        (IFNULL(SUM(t.total_work_hours),0) - c.forecasted_contracted_hours) AS variance
    FROM contracts c
    JOIN projects p ON c.project = p.id
    LEFT JOIN operations o ON o.project = p.id
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN timesheet t ON t.operator = o.id
    WHERE c.id = '$contract_filter'
    GROUP BY c.id";
    $variance = mysqli_fetch_assoc(mysqli_query($conn, $sql_variance));
}
    ?>

    <div class="main">

  <h2>📊 تقارير تفصيلية للعقد</h2>

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
        <div class="tabs">
            <div class="tab-buttons">
                <button type="button" onclick="showTab('basic')">📝 التفاصيل الأساسية</button>
                <button type="button" onclick="showTab('time')">⏳ الزمن مقابل الإنجاز</button>
                <button type="button" onclick="showTab('faults')">⚠️ الأعطال</button>
                <button type="button" onclick="showTab('suppliers')">🚛 الموردين</button>
                <button type="button" onclick="showTab('equipments')">🏗️ الآليات</button>
                <button type="button" onclick="showTab('drivers')">👷 السائقين</button>
                <button type="button" onclick="showTab('variance')">📉 الانحراف</button>
            </div>

            <!-- التفاصيل الأساسية -->
            <div id="basic" class="tab active">
                <h3>📝 التفاصيل الأساسية</h3>
                <ul>
                    <li>المشروع: <?php echo $contract_data['project_name']; ?></li>
                    <li>تاريخ التوقيع: <?php echo $contract_data['contract_signing_date']; ?></li>
                    <li>مدة العقد: <?php echo $contract_data['contract_duration_months']; ?> شهور</li>
                    <li>الهدف الشهري: <?php echo $contract_data['hours_monthly_target']; ?></li>
                    <li>الإجمالي المتوقع: <?php echo $contract_data['forecasted_contracted_hours']; ?></li>
                    <li>المنفذ: <?php echo $contract_data['actual_hours']; ?></li>
                </ul>
            </div>

            <!-- الزمن مقابل الإنجاز -->
            <div id="time" class="tab">
                <h3>⏳ الزمن مقابل الإنجاز</h3>
                <p>التقدم الزمني: <?php echo round($time_vs_progress['time_progress'], 2); ?> %</p>
                <p>التقدم الفعلي: <?php echo round($time_vs_progress['work_progress'], 2); ?> %</p>
            </div>

            <!-- الأعطال -->
            <div id="faults" class="tab">
                <h3>⚠️ إجمالي ساعات الأعطال</h3>
                <p><?php echo $faults['total_fault_hours'] ?? 0; ?> ساعة</p>
            </div>

            <!-- الموردين -->
            <div id="suppliers" class="tab">
                <h3>🚛 ساعات الموردين</h3>
                <table border="1" cellpadding="5">
                    <tr><th>المورد</th><th>إجمالي الساعات</th></tr>
                    <?php while($row = mysqli_fetch_assoc($suppliers)) { ?>
                        <tr>
                            <td><?php echo $row['supplier_name']; ?></td>
                            <td><?php echo $row['total_work_hours']; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

            <!-- الآليات -->
            <div id="equipments" class="tab">
                <h3>🏗️ أداء الآليات</h3>
                <table border="1" cellpadding="5">
                    <tr><th>الآلية</th><th>ساعات العمل</th><th>ساعات الأعطال</th></tr>
                    <?php while($row = mysqli_fetch_assoc($equipments)) { ?>
                        <tr>
                            <td><?php echo $row['equipment_name']; ?></td>
                            <td><?php echo $row['work_hours']; ?></td>
                            <td><?php echo $row['fault_hours']; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

            <!-- السائقين -->
            <div id="drivers" class="tab">
                <h3>👷 ساعات السائقين</h3>
                <table border="1" cellpadding="5">
                    <tr><th>السائق</th><th>إجمالي الساعات</th></tr>
                    <?php while($row = mysqli_fetch_assoc($drivers)) { ?>
                        <tr>
                            <td><?php echo $row['driver_name']; ?></td>
                            <td><?php echo $row['driver_hours']; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

            <!-- الانحراف -->
            <div id="variance" class="tab">
                <h3>📉 الانحراف عن الخطة</h3>
                <p>المخطط: <?php echo $variance['planned_hours']; ?> ساعة</p>
                <p>المنفذ: <?php echo $variance['actual_hours']; ?> ساعة</p>
                <p>الانحراف: <?php echo $variance['variance']; ?> ساعة</p>
            </div>
        </div>
    <?php } ?>

<script>
function showTab(id) {
    document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
    document.getElementById(id).classList.add("active");
}
</script>

    </div>

</body>

</html>