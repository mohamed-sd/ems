<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

$project_filter = isset($_GET['project']) ? $_GET['project'] : '';
$driver_filter  = isset($_GET['driver']) ? $_GET['driver'] : '';
$start_date     = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date       = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$shift_filter = isset($_GET['shift']) ? $_GET['shift'] : '';

$equipment_id = isset($_GET['equipment_id']) ? $_GET['equipment_id'] : '';



$sql = "
SELECT 
    d.name AS driver_name,
    p.name AS project_name,
    e.name AS equipment_name,
    t.date,
    SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN drivers d ON t.driver = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN projects p ON o.project = p.id
WHERE 1=1
";

if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND t.date BETWEEN '$start_date' AND '$end_date' ";
} elseif (!empty($start_date)) {
    $sql .= " AND t.date = '$start_date' ";
}
if (!empty($project_filter)) {
    $sql .= " AND p.id = '$project_filter' ";
}
if (!empty($driver_filter)) {
    $sql .= " AND d.id = '$driver_filter' ";
}
if (!empty($shift_filter)) {
    $sql .= " AND t.shift = '$shift_filter' ";
}

if (!empty($equipment_id)) {
    $sql  .= " AND e.id = '$equipment_id'";
}


$sql .= " GROUP BY d.name, p.name, e.name, t.date ORDER BY t.date, d.name";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تقارير السائقين</title>

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
            
            <h2 class="mb-4 text-center text-primary">
                🚚 تقرير ساعات عمل السائقين
            </h2>
            <hr class="mb-4">

            <!-- فورم الفلاتر -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">🏗️ المشروع:</label>
                    <select name="project" class="form-select">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = mysqli_query($conn, "SELECT id, name FROM projects");
                        while ($row = mysqli_fetch_assoc($prj)) {
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">👨‍🔧 السائق:</label>
                    <select name="driver" class="form-select">
                        <option value="">-- الكل --</option>
                        <?php
                        $drv = mysqli_query($conn, "SELECT id, name FROM drivers");
                        while ($row = mysqli_fetch_assoc($drv)) {
                            $selected = ($driver_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">📅 من:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">📅 إلى:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>

                     <div class="col-md-2">
                    <label class="form-label">🚛 الوردية:</label>

                 <select class="form-select" name="shift">
        <option value="">-- الكل --</option>
        <option value="D" <?php if(($_GET['shift'] ?? '')=="صباحية") echo "selected"; ?>>صباحية</option>
        <option value="N" <?php if(($_GET['shift'] ?? '')=="مسائية") echo "selected"; ?>>مسائية</option>
    </select>
                    </div>


<div class="col-md-2">
    <label class="form-label">الآلية</label>
    <select name="equipment_id" class="form-select">
        <option value="">-- الكل --</option>
        <?php
        $res = mysqli_query($conn, "
            SELECT DISTINCT e.id, e.name 
            FROM operations o
            JOIN equipments e ON o.equipment = e.id
        ");
        while ($row = mysqli_fetch_assoc($res)) {
            $sel = (($_GET['equipment_id'] ?? '') == $row['id']) ? "selected" : "";
            echo "<option value='{$row['id']}' $sel>{$row['name']}</option>";
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
            <div class="table-responsive">
                <table class="table table-striped table-hover text-center align-middle">
                    <thead class="table-primary">
                        <tr>
                           <th>📅 التاريخ</th>
                            <th>🏗️ المشروع</th>

                            <th>👨‍🔧 السائق</th>
                            <th>⚙️ الآلية</th>
                            <th>⏱️ مجموع الساعات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grand_total = 0;
                    while ($row = mysqli_fetch_assoc($result)) {
                        $grand_total += $row['total_hours'];
                    ?>
                        <tr>
                             <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['project_name']; ?></td>

                            <td><?php echo $row['driver_name']; ?></td>
                            <td><?php echo $row['equipment_name']; ?></td>
                            <td><span class="badge bg-success fs-6"><?php echo $row['total_hours']; ?></span></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- المجموع -->
            <div class="alert alert-info mt-4 fs-5 text-center">
                ✅ إجمالي الساعات: <strong><?php echo $grand_total; ?></strong> ساعة
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
