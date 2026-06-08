<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$project_filter = isset($_GET['project']) ? $_GET['project'] : '';
$driver_filter  = isset($_GET['driver']) ? $_GET['driver'] : '';
$start_date     = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date       = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$shift_filter   = isset($_GET['shift']) ? $_GET['shift'] : '';
$equipment_id   = isset($_GET['equipment_id']) ? $_GET['equipment_id'] : '';

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
JOIN project p ON o.project_id = p.id
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
    <link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
    <link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main reports-main driver-report-main">

        <?php
        // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
        $header_title   = 'تقرير ساعات عمل السائقين';
        $header_icon    = 'fas fa-user-clock';
        $header_actions = array();
        $header_back    = array('href' => 'reports.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
        include('../includes/page_header.php');
        ?>

    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-filter"></i> فلاتر البحث
            </h5>
        </div>
        <div class="card-body fc-filter-body">

            <!-- فورم الفلاتر -->
            <form method="GET" class="form-grid fc-filter-bar">
                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-project-diagram" style="margin-left: 5px;"></i> المشروع</label>
                    <div class="control"><select name="project">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
                        if ($prj) {
                        while ($prjRow = mysqli_fetch_assoc($prj)) {
                            $selected = ($project_filter == $prjRow['id']) ? "selected" : "";
                            echo "<option value='{$prjRow['id']}' $selected>{$prjRow['name']}</option>";
                        }
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-user-tie" style="margin-left: 5px;"></i> السائق</label>
                    <div class="control"><select name="driver">
                        <option value="">-- الكل --</option>
                        <?php
                        $drv = mysqli_query($conn, "SELECT id, name FROM drivers where status = '1' ");
                        if ($drv) {
                        while ($drvRow = mysqli_fetch_assoc($drv)) {
                            $selected = ($driver_filter == $drvRow['id']) ? "selected" : "";
                            echo "<option value='{$drvRow['id']}' $selected>{$drvRow['name']}</option>";
                        }
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-calendar-day" style="margin-left: 5px;"></i> من تاريخ</label>
                    <div class="control"><input type="date" name="start_date" value="<?php echo $start_date; ?>"></div>
                </div>

                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-calendar-day" style="margin-left: 5px;"></i> إلى تاريخ</label>
                    <div class="control"><input type="date" name="end_date" value="<?php echo $end_date; ?>"></div>
                </div>

                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-moon" style="margin-left: 5px;"></i> الوردية</label>
                    <div class="control"><select name="shift">
                        <option value="">-- الكل --</option>
                        <option value="D" <?php if ($shift_filter == "D") echo "selected"; ?>>صباحية</option>
                        <option value="N" <?php if ($shift_filter == "N") echo "selected"; ?>>مسائية</option>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label class="fc-filter-label"><i class="fas fa-cogs" style="margin-left: 5px;"></i> الآلية</label>
                    <div class="control"><select name="equipment_id">
                        <option value="">-- الكل --</option>
                        <?php
                        $res = mysqli_query($conn, "
                            SELECT DISTINCT e.id, e.name
                            FROM operations o
                            JOIN equipments e ON o.equipment = e.id
                        ");
                        if ($res) {
                        while ($equipRow = mysqli_fetch_assoc($res)) {
                            $sel = ($equipment_id == $equipRow['id']) ? "selected" : "";
                            echo "<option value='{$equipRow['id']}' $sel>{$equipRow['name']}</option>";
                        }
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-12 fc-filter-actions driver-filter-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fa fa-search"></i> بحث
                    </button>
                    <a href="deriver.php" class="fc-clear-link">
                        <i class="fa fa-redo"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-table"></i> نتائج التقرير
            </h5>
        </div>
        <div class="card-body table-container driver-table-wrap">
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> التاريخ</th>
                        <th><i class="fas fa-project-diagram"></i> المشروع</th>
                        <th><i class="fas fa-user-tie"></i> السائق</th>
                        <th><i class="fas fa-cogs"></i> الآلية</th>
                        <th><i class="fas fa-clock"></i> مجموع الساعات</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $grand_total = 0;
                if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $grand_total += $row['total_hours'];
                ?>
                    <tr>
                        <td><?php echo $row['date']; ?></td>
                        <td><?php echo $row['project_name']; ?></td>
                        <td><?php echo $row['driver_name']; ?></td>
                        <td><?php echo $row['equipment_name']; ?></td>
                        <td><span class="status-active"><?php echo $row['total_hours']; ?> ساعة</span></td>
                    </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- المجموع -->
    <div class="card driver-kpi-card">
        <div class="card-body">
            <div class="totals">
                <div class="kpi">
                    <div class="v"><?php echo $grand_total; ?></div>
                    <div class="t">إجمالي ساعات العمل</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
