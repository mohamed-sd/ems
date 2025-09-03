<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | التقارير </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

  <?php include('../includes/insidebar.php'); 

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
JOIN equipments e ON t.operator = e.id
JOIN operations o ON e.id = o.equipment
JOIN projects p ON o.project = p.id
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

  <div class="main">


    

  
    <h2>🚚 تقرير ساعات عمل السائقين</h2>

    <form method="GET">
        <label>🏗️ المشروع:</label>
        <select name="project">
            <option value="">-- الكل --</option>
            <?php
            $prj = mysqli_query($conn, "SELECT id, name FROM projects");
            while($row = mysqli_fetch_assoc($prj)){
                $selected = ($project_filter == $row['id']) ? "selected" : "";
                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
            }
            ?>
        </select>

        <label>👨‍🔧 السائق:</label>
        <select name="driver">
            <option value="">-- الكل --</option>
            <?php
            $drv = mysqli_query($conn, "SELECT id, name FROM drivers");
            while($row = mysqli_fetch_assoc($drv)){
                $selected = ($driver_filter == $row['id']) ? "selected" : "";
                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
            }
            ?>
        </select>

        <label>📅 من:</label>
        <input type="date" name="start_date" value="<?php echo $start_date; ?>">

        <label>📅 إلى:</label>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>">

        <button type="submit">🔍 بحث</button>
    </form>

    <br>

    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>السائق</th>
            <th>المشروع</th>
            <th>الآلية</th>
            <th>التاريخ</th>
            <th>⏱️ مجموع الساعات</th>
        </tr>
        <?php 
        $grand_total = 0;
        while($row = mysqli_fetch_assoc($result)) { 
            $grand_total += $row['total_hours'];
        ?>
        <tr>
            <td><?php echo $row['driver_name']; ?></td>
            <td><?php echo $row['project_name']; ?></td>
            <td><?php echo $row['equipment_name']; ?></td>
            <td><?php echo $row['date']; ?></td>
            <td><?php echo $row['total_hours']; ?></td>
        </tr>
        <?php } ?>
    </table>

    <h3>✅ إجمالي الساعات: <?php echo $grand_total; ?> ساعة</h3>
  </div>

</body>
</html>