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
      t.executed_hours,
      t.total_fault_hours,
      t.standby_hours
  FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
  JOIN drivers d ON t.driver = d.id
  JOIN project p ON o.project_id = p.id
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
$total_sql = "SELECT SUM(t.executed_hours) AS executed_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN operations p ON o.project_id = p.id
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
$executed_hours  = $total_row['executed_hours'];
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
	<link rel="stylesheet" href="../assets/css/admin-style.css">
	<link rel="stylesheet" href="../assets/css/main_admin_style.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
	<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

	<style>
		.main { font-family: 'Cairo', sans-serif; }

		.report-table thead th {
			background: #f8fafc;
			color: #0c1c3e;
			font-weight: 800;
			border-color: rgba(12, 28, 62, 0.1);
		}

		.report-table td {
			border-color: rgba(12, 28, 62, 0.08);
			color: #0c1c3e;
		}

		.stats-box {
			background: linear-gradient(135deg, rgba(13, 148, 136, 0.12), rgba(13, 148, 136, 0.06));
			border: 1px solid rgba(13, 148, 136, 0.25);
			border-radius: 14px;
			padding: 16px 18px;
			color: #0f766e;
			font-weight: 800;
			box-shadow: 0 4px 14px rgba(15, 118, 110, 0.12);
		}

		.form-grid { align-items: end; }
	</style>
</head>
<body class="bg-light">

<?php include('../insidebar.php'); ?>

<div class="main">

	<div class="page-header">
		<h1 class="page-title">
			<div class="title-icon"><i class="fa-solid fa-chart-line"></i></div>
			تقرير ساعات العمل اليومية
		</h1>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<a href="reports.php" class="back-btn">
				<i class="fas fa-arrow-right"></i> رجوع
			</a>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-header">
			<h5><i class="fas fa-filter"></i> فلاتر التقرير</h5>
		</div>
		<div class="card-body">
			<form method="GET" class="form-grid">
				<div>
					<label><i class="fas fa-calendar-day"></i> التاريخ</label>
					<input type="date" name="date" value="<?php echo $date_filter; ?>">
				</div>

				<div>
					<label><i class="fas fa-cogs"></i> الآلية</label>
					<select name="equipment">
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

				<div>
					<label><i class="fas fa-diagram-project"></i> المشروع</label>
					<select name="project">
						<option value="">-- الكل --</option>
						<?php
						$prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
						while($row = mysqli_fetch_assoc($prj)){
							$selected = ($project_filter == $row['id']) ? "selected" : "";
							echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
						}
						?>
					</select>
				</div>

				<button type="submit"><i class="fa fa-search"></i> بحث</button>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="card-header">
			<h5><i class="fas fa-table"></i> جدول البيانات</h5>
		</div>
		<div class="card-body table-container">
			<div class="table-responsive" id="projectsTable">
				<table class="table table-striped table-hover align-middle report-table">
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
						<td style="color:#0d9488; font-weight:700;"><?php echo $row['executed_hours']; ?></td>
						<td style="color:#dc2626; font-weight:700;"><?php echo $row['total_fault_hours']; ?></td>
						<td style="color:#e8b800; font-weight:700;"><?php echo $row['standby_hours']; ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>

			<div class="stats-box mt-3">
				<i class="fas fa-check-circle"></i>
			مجموع ساعات العمل: <?php echo $executed_hours ? $executed_hours : 0; ?> ساعة
			</div>
		</div>
	</div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
