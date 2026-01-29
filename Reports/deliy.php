<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

// استقبال الفلاhتر
$date_filter      = isset($_GET['date']) ? $_GET['date'] : '';
$equipment_filter = isset($_GET['equipment']) ? $_GET['equipment'] : '';
$project_filter   = isset($_GET['project']) ? $_GET['project'] : '';

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
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
  JOIN drivers d ON t.driver = d.id
  JOIN operationproject p ON o.project = p.id
  WHERE 1=1
";

if (!empty($date_filter)) {
    $sql .= " AND t.`date` = '$date_filter' ";
}
if (!empty($equipment_filter)) {
    $sql .= " AND e.id = '$equipment_filter' ";
}
if (!empty($project_filter)) {
    $sql .= " AND p.id = '$project_filter' ";
}

$result = mysqli_query($conn, $sql) or die("خطأ في الاستعلام: " . mysqli_error($conn));

// استعلام المجموع
$total_sql = "SELECT SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN operationproject p ON o.project = p.id
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
$total_row    = mysqli_fetch_assoc($total_result);
$total_hours  = $total_row['total_hours'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>إيكوبيشن | التقارير</title>
	
	<!-- Bootstrap 5 -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body class="bg-light">

<?php include('../insidebar.php'); ?>

<div class="main container py-4">

	<div class="card shadow-lg border-0 rounded-4">
		<div class="card-body">
			
			<h2 class="mb-4 text-primary text-center">
				<i class="fa-solid fa-chart-line"></i> تقرير ساعات العمل اليومية
			</h2>
			<hr class="mb-4">

			<!-- فورم الفلاتر -->
			<form method="GET" class="row g-3 mb-4">
				<div class="col-md-4">
					<label class="form-label">📅 التاريخ:</label>
					<input type="date" name="date" value="<?php echo $date_filter; ?>" class="form-control">
				</div>

				<div class="col-md-4">
					<label class="form-label">⚙️ الآلية:</label>
					<select name="equipment" class="form-select">
						<option value="">-- الكل --</option>
						<?php
						$eqs = mysqli_query($conn, "SELECT id, name , code FROM equipments where status = '1' AND  id IN ( SELECT operations.equipment FROM `operations` WHERE `status` LIKE '1' ) ");
						while($row = mysqli_fetch_assoc($eqs)){
							$selected = ($equipment_filter == $row['id']) ? "selected" : "";
							echo "<option value='{$row['id']}' $selected>{$row['name']} - {$row['code']} </option>";
						}
						?>
					</select>
				</div>

				<div class="col-md-4">
					<label class="form-label">🏗️ المشروع:</label>
					<select name="project" class="form-select">
						<option value="">-- الكل --</option>
						<?php
						$prj = mysqli_query($conn, "SELECT id, name FROM operationproject where status = '1' ");
						while($row = mysqli_fetch_assoc($prj)){
							$selected = ($project_filter == $row['id']) ? "selected" : "";
							echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
						}
						?>
					</select>
				</div>

				<div class="col-12 text-center">
					<button class="btn btn-primary px-5 mt-3" type="submit">
						<i class="fa fa-search"></i> بحث
					</button>
				</div>
			</form>

			<!-- الجدول -->
			<div class="table-responsive"  id="projectsTable">
				<table class="table table-striped table-hover align-middle text-center">
					<thead class="table-primary">
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
							<td><span class="badge bg-success fs-6"><?php echo $row['total_work_hours']; ?></span></td>
							<td><span class="badge bg-danger fs-6"><?php echo $row['total_fault_hours']; ?></span></td>
							<td><span class="badge bg-secondary fs-6"><?php echo $row['standby_hours']; ?></span></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>

			<!-- المجموع -->
			<div class="alert alert-info mt-4 fs-5 text-center">
				✅ مجموع ساعات العمل: <strong><?php echo $total_hours ? $total_hours : 0; ?></strong> ساعة
			</div>

		</div>
	</div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
