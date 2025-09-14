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
</head>



    <?php 
    include('../insidebar.php');

  include("../config.php");
// جلب البيانات للفلاتر
$projects  = mysqli_query($conn, "SELECT id, name FROM projects");
$suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers");
$drivers   = mysqli_query($conn, "SELECT id, name FROM drivers");

// القيم القادمة من الفلاتر
$report_type = $_GET['report_type'] ?? 'supplier'; // supplier | driver
$project_id  = $_GET['project_id'] ?? '';
$supplier_id = $_GET['supplier_id'] ?? '';
$driver_id   = $_GET['driver_id'] ?? '';
$start_date  = $_GET['start_date'] ?? '';
$end_date    = $_GET['end_date'] ?? '';

$where = "1=1";

// فلترة المشروع
if ($project_id != '') {
    $where .= " AND p.id = '$project_id'";
}

// فلترة المورد
if ($report_type == 'supplier' && $supplier_id != '') {
    $where .= " AND s.id = '$supplier_id'";
}

// فلترة السائق
if ($report_type == 'driver' && $driver_id != '') {
    $where .= " AND d.id = '$driver_id'";
}

// فلترة التاريخ
$date_filter = "";
if ($start_date != '' && $end_date != '') {
    $date_filter = " AND t.date BETWEEN '$start_date' AND '$end_date'";
}

// بناء الاستعلام
if ($report_type == 'supplier') {
    $sql = "
        SELECT 
            sc.id AS contract_id,
            s.name AS supplier_name,
            p.name AS project_name,
            sc.hours_monthly_target,
            sc.forecasted_contracted_hours,
            SUM(t.total_work_hours) AS actual_hours,
            ROUND(SUM(t.total_work_hours) / sc.hours_monthly_target * 100, 2) AS monthly_achievement_percentage,
            ROUND(SUM(t.total_work_hours) / sc.forecasted_contracted_hours * 100, 2) AS total_achievement_percentage
        FROM supplierscontracts sc
        JOIN suppliers s ON sc.supplier_id = s.id
        LEFT JOIN projects p ON sc.project_id = p.id
        JOIN equipments e ON e.suppliers = s.id
        JOIN operations o ON o.equipment = e.id
        JOIN timesheet t ON t.operator = o.id $date_filter
        WHERE $where
        GROUP BY sc.id, s.name, p.name, sc.hours_monthly_target, sc.forecasted_contracted_hours
        ORDER BY s.name, p.name
    ";
} else {
    $sql = "
        SELECT 
            dc.id AS contract_id,
            d.name AS driver_name,
            p.name AS project_name,
            dc.hours_monthly_target,
            dc.forecasted_contracted_hours,
            SUM(t.total_work_hours) AS actual_hours,
            ROUND(SUM(t.total_work_hours) / dc.hours_monthly_target * 100, 2) AS monthly_achievement_percentage,
            ROUND(SUM(t.total_work_hours) / dc.forecasted_contracted_hours * 100, 2) AS total_achievement_percentage
        FROM drivercontracts dc
        JOIN drivers d ON dc.driver_id = d.id
        JOIN projects p ON dc.project_id = p.id
        LEFT JOIN timesheet t ON t.driver = d.id $date_filter
        WHERE $where
        GROUP BY dc.id, d.name, p.name, dc.hours_monthly_target, dc.forecasted_contracted_hours
        ORDER BY d.name, p.name
    ";
}

$result = mysqli_query($conn, $sql);
    ?>

<body>
    <div class="main">





        <h2>🚚 تقرير  العقودات للمورد والسائقين </h2>


        

           <form method="get" style="margin-bottom:20px;">
        <label>نوع التقرير:</label>
        <select name="report_type">
            <option value="supplier" <?= ($report_type=='supplier'?'selected':'') ?>>موردين</option>
            <option value="driver" <?= ($report_type=='driver'?'selected':'') ?>>سائقين</option>
        </select>

        <label>المشروع:</label>
        <select name="project_id">
            <option value="">الكل</option>
            <?php while($p = mysqli_fetch_assoc($projects)) { ?>
                <option value="<?= $p['id'] ?>" <?= ($project_id==$p['id']?'selected':'') ?>><?= $p['name'] ?></option>
            <?php } ?>
        </select>

        <label>المورد:</label>
        <select name="supplier_id">
            <option value="">الكل</option>
            <?php while($s = mysqli_fetch_assoc($suppliers)) { ?>
                <option value="<?= $s['id'] ?>" <?= ($supplier_id==$s['id']?'selected':'') ?>><?= $s['name'] ?></option>
            <?php } ?>
        </select>

        <label>السائق:</label>
        <select name="driver_id">
            <option value="">الكل</option>
            <?php while($d = mysqli_fetch_assoc($drivers)) { ?>
                <option value="<?= $d['id'] ?>" <?= ($driver_id==$d['id']?'selected':'') ?>><?= $d['name'] ?></option>
            <?php } ?>
        </select>

        <label>من تاريخ:</label>
        <input type="date" name="start_date" value="<?= $start_date ?>">

        <label>إلى تاريخ:</label>
        <input type="date" name="end_date" value="<?= $end_date ?>">

        <button type="submit">عرض التقرير</button>
    </form>

    <!-- جدول النتائج -->
    <table id="reportTable" class="display nowrap" style="width:100%">
        <thead>
            <tr>
                <th>#</th>
                <th><?= ($report_type == 'supplier') ? 'المورد' : 'السائق' ?></th>
                <th>المشروع</th>
                <th>المستهدف</th>
                <th>الفعلي</th>
                <th>نسبة الإنجاز %</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>
                  <td>".$i++."</td>
            <td>".($report_type == 'supplier' ? $row['supplier_name'] : $row['driver_name'])."</td>
            <td>".$row['project_name']."</td>
            <td>".$row['hours_monthly_target']."</td>
            <td>".$row['forecasted_contracted_hours']."</td>
            <td>".$row['actual_hours']."</td>
            <td>".$row['monthly_achievement_percentage']."</td>
            <td>".$row['total_achievement_percentage']."</td>
                </tr>";
            }
            ?>
        </tbody>
    </table>
    </div>

</body>

</html>