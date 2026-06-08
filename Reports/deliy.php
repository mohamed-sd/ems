<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
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
	<link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
	<link rel="stylesheet" href="/ems/assets/css/all.min.css">
	<link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
	<link rel="stylesheet" href="/ems/assets/css/design-tokens.css">
	<link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
</head>
<body class="ems-site">

<?php include('../insidebar.php'); ?>

<div class="main reports-daily-main ems-unified-page-shell">

		<?php
		// Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
		$header_title   = 'تقرير ساعات العمل اليومية';
		$header_icon    = 'fa-solid fa-chart-line';
		$header_actions = array();
		$header_back    = array('href' => 'reports.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
		include('../includes/page_header.php');
		?>

	<div class="card mb-4 fc-filter-body">
		<div class="card-header fc-filter-bar">
			<h5><i class="fas fa-filter"></i> فلاتر التقرير</h5>
		</div>
		<div class="card-body">
			<form method="GET" class="form-grid fc-filter-grid">
				<div>
					<label class="fc-filter-label"><i class="fas fa-calendar-day"></i> التاريخ</label>
					<input type="date" name="date" value="<?php echo $date_filter; ?>">
				</div>

				<div>
					<label class="fc-filter-label"><i class="fas fa-cogs"></i> الآلية</label>
					<select name="equipment">
						<option value="">-- الكل --</option>
						<?php
						$eqs = mysqli_query($conn, "SELECT id, name , code FROM equipments where status = '1' AND  id IN ( SELECT operations.equipment FROM `operations` WHERE `status` LIKE '1' ) ");
						if ($eqs) {
						while($row = mysqli_fetch_assoc($eqs)){
							$selected = ($equipment_filter == $row['id']) ? "selected" : "";
							echo "<option value='{$row['id']}' $selected>{$row['name']} - {$row['code']} </option>";
						}
						}
						?>
					</select>
				</div>

				<div>
					<label class="fc-filter-label"><i class="fas fa-diagram-project"></i> المشروع</label>
					<select name="project">
						<option value="">-- الكل --</option>
						<?php
						$prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
						if ($prj) {
						while($row = mysqli_fetch_assoc($prj)){
							$selected = ($project_filter == $row['id']) ? "selected" : "";
							echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
						}
						}
						?>
					</select>
				</div>

				<button type="submit" class="add-btn"><i class="fa fa-search"></i> بحث</button>
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
						<td class="daily-hours-executed"><?php echo $row['executed_hours']; ?></td>
						<td class="daily-hours-fault"><?php echo $row['total_fault_hours']; ?></td>
						<td class="daily-hours-standby"><?php echo $row['standby_hours']; ?></td>
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
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
