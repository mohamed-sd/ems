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

  <?php 
  include('../insidebar.php'); 
  include '../config.php';

  // استقبال الفلاتر
  $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
  $equipment_filter = isset($_GET['equipment']) ? $_GET['equipment'] : '';
  $project_filter = isset($_GET['project']) ? $_GET['project'] : '';

  $sql = "
  SELECT 
      p.name AS project_name,
      e.name AS equipment_name,
      d.name AS driver_name,
      t.`date`,
      t.shift,
      t.total_work_hours,
      t.total_fault_hours,
      t.standby_hours
  FROM timesheet t
  JOIN equipments e ON t.operator = e.id
  JOIN drivers d ON t.driver = d.id
  JOIN operations o ON e.id = o.equipment
  JOIN projects p ON o.project = p.id
  WHERE 1=1
  ";

  // تطبيق الفلاتر
  if (!empty($date_filter)) {
      $sql .= " AND t.`date` = '$date_filter' ";
  }
  if (!empty($equipment_filter)) {
      $sql .= " AND e.id = '$equipment_filter' ";
  }
  if (!empty($project_filter)) {
      $sql .= " AND p.id = '$project_filter' ";
  }

  // تنفيذ الاستعلام مع اظهار الأخطاء
  $result = mysqli_query($conn, $sql) or die("خطأ في الاستعلام: " . mysqli_error($conn));

  // طباعة الاستعلام للتجربة (احذف بعد التأكد)
//   echo "<pre>$sql</pre>";

  // استعلام المجموع
  $total_sql = "SELECT SUM(t.total_work_hours) AS total_hours
  FROM timesheet t
  JOIN equipments e ON t.operator = e.id
  JOIN operations o ON e.id = o.equipment
  JOIN projects p ON o.project = p.id
  WHERE 1=1";

  if (!empty($date_filter)) {
      $total_sql .= " AND t.`date` = '$date_filter' ";
  }
  if (!empty($equipment_filter)) {
      $total_sql .= " AND e.id = '$equipment_filter' ";
  }
  if (!empty($project_filter)) {
      $total_sql .= " AND p.id = '$project_filter' ";
  }

  $total_result = mysqli_query($conn, $total_sql) or die("خطأ في استعلام المجموع: " . mysqli_error($conn));
  $total_row = mysqli_fetch_assoc($total_result);
  $total_hours = $total_row['total_hours'];

  // طباعة استعلام المجموع للتجربة (احذف بعد التأكد)
//   echo "<pre>$total_sql</pre>";
  ?>

  <div class="main">

    <h2>📊 تقرير ساعات العمل اليومية</h2>
    <br/><br/><hr/>
    
    <form method="GET">
        <label>📅 التاريخ:</label>
        <input type="date" name="date" value="<?php echo $date_filter; ?>">

        <label>⚙️ الآلية:</label>
        <select name="equipment">
            <option value="">-- الكل --</option>
            <?php
            $eqs = mysqli_query($conn, "SELECT id, name FROM equipments");
            while($row = mysqli_fetch_assoc($eqs)){
                $selected = ($equipment_filter == $row['id']) ? "selected" : "";
                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
            }
            ?>
        </select>

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

        <button class="add" type="submit">🔍 بحث</button>
    </form>

    <br>

    <table id="projectsTable" class="display">
        <thead>
        <tr>
            <th>المشروع</th>
            <th>الآلية</th>
            <th>السائق</th>
            <th>التاريخ</th>
            <th>الشفت</th>
            <th>⏱️ ساعات العمل</th>
            <th>⚠️ ساعات الأعطال</th>
            <th>⏸️ Standby</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $row['project_name']; ?></td>
            <td><?php echo $row['equipment_name']; ?></td>
            <td><?php echo $row['driver_name']; ?></td>
            <td><?php echo $row['date']; ?></td>
            <td><?php echo $row['shift']; ?></td>
            <td><?php echo $row['total_work_hours']; ?></td>
            <td><?php echo $row['total_fault_hours']; ?></td>
            <td><?php echo $row['standby_hours']; ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>

    <h3>✅ مجموع ساعات العمل: <?php echo $total_hours ? $total_hours : 0; ?> ساعة</h3>

  </div>

</body>
</html>
