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
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙ‚Ø§Ø±ÙŠØ± Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ†</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <link rel="stylesheet" href="../assets/css/main_admin_style.css" />
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <div class="page-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div class="title-icon"><i class="fas fa-user-clock"></i></div>
        <h1 class="page-title">ØªÙ‚Ø±ÙŠØ± Ø³Ø§Ø¹Ø§Øª Ø¹Ù…Ù„ Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ†</h1>
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="reports.php" class="back-btn">
          <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
        </a>
      </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-filter"></i> ÙÙ„Ø§ØªØ± Ø§Ù„Ø¨Ø­Ø«
            </h5>
        </div>
        <div class="card-body">

            <!-- ÙÙˆØ±Ù… Ø§Ù„ÙÙ„Ø§ØªØ± -->
            <form method="GET" class="form-grid" style="margin-bottom: 2rem;">
                <div class="field md-3 sm-6">
                    <label><i class="fas fa-project-diagram" style="margin-left: 5px;"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                    <div class="control"><select name="project">
                        <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                        <?php
                        $prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
                        while ($prjRow = mysqli_fetch_assoc($prj)) {
                            $selected = ($project_filter == $prjRow['id']) ? "selected" : "";
                            echo "<option value='{$prjRow['id']}' $selected>{$prjRow['name']}</option>";
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label><i class="fas fa-user-tie" style="margin-left: 5px;"></i> Ø§Ù„Ø³Ø§Ø¦Ù‚</label>
                    <div class="control"><select name="driver">
                        <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                        <?php
                        $drv = mysqli_query($conn, "SELECT id, name FROM drivers where status = '1' ");
                        while ($drvRow = mysqli_fetch_assoc($drv)) {
                            $selected = ($driver_filter == $drvRow['id']) ? "selected" : "";
                            echo "<option value='{$drvRow['id']}' $selected>{$drvRow['name']}</option>";
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label><i class="fas fa-calendar-day" style="margin-left: 5px;"></i> Ù…Ù† ØªØ§Ø±ÙŠØ®</label>
                    <div class="control"><input type="date" name="start_date" value="<?php echo $start_date; ?>"></div>
                </div>

                <div class="field md-3 sm-6">
                    <label><i class="fas fa-calendar-day" style="margin-left: 5px;"></i> Ø¥Ù„Ù‰ ØªØ§Ø±ÙŠØ®</label>
                    <div class="control"><input type="date" name="end_date" value="<?php echo $end_date; ?>"></div>
                </div>

                <div class="field md-3 sm-6">
                    <label><i class="fas fa-moon" style="margin-left: 5px;"></i> Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</label>
                    <div class="control"><select name="shift">
                        <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                        <option value="D" <?php if ($shift_filter == "D") echo "selected"; ?>>ØµØ¨Ø§Ø­ÙŠØ©</option>
                        <option value="N" <?php if ($shift_filter == "N") echo "selected"; ?>>Ù…Ø³Ø§Ø¦ÙŠØ©</option>
                    </select></div>
                </div>

                <div class="field md-3 sm-6">
                    <label><i class="fas fa-cogs" style="margin-left: 5px;"></i> Ø§Ù„Ø¢Ù„ÙŠØ©</label>
                    <div class="control"><select name="equipment_id">
                        <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                        <?php
                        $res = mysqli_query($conn, "
                            SELECT DISTINCT e.id, e.name 
                            FROM operations o
                            JOIN equipments e ON o.equipment = e.id
                        ");
                        while ($equipRow = mysqli_fetch_assoc($res)) {
                            $sel = ($equipment_id == $equipRow['id']) ? "selected" : "";
                            echo "<option value='{$equipRow['id']}' $sel>{$equipRow['name']}</option>";
                        }
                        ?>
                    </select></div>
                </div>

                <div class="field md-12" style="text-align: center; margin-top: 1rem;">
                    <button class="primary" type="submit" style="padding: 10px 40px;">
                        <i class="fa fa-search"></i> Ø¨Ø­Ø«
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-table"></i> Ù†ØªØ§Ø¦Ø¬ Ø§Ù„ØªÙ‚Ø±ÙŠØ±
            </h5>
        </div>
        <div class="card-body" style="padding: 2rem; overflow-x: auto;">
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> Ø§Ù„ØªØ§Ø±ÙŠØ®</th>
                        <th><i class="fas fa-project-diagram"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
                        <th><i class="fas fa-user-tie"></i> Ø§Ù„Ø³Ø§Ø¦Ù‚</th>
                        <th><i class="fas fa-cogs"></i> Ø§Ù„Ø¢Ù„ÙŠØ©</th>
                        <th><i class="fas fa-clock"></i> Ù…Ø¬Ù…ÙˆØ¹ Ø§Ù„Ø³Ø§Ø¹Ø§Øª</th>
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
                        <td><span class="status-active"><?php echo $row['total_hours']; ?> Ø³Ø§Ø¹Ø©</span></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹ -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-body">
            <div class="totals">
                <div class="kpi">
                    <div class="v"><?php echo $grand_total; ?></div>
                    <div class="t">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
  /* Form Grid */
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    align-items: start;
  }

  .field.md-12 {
    grid-column: 1 / -1;
  }

  /* DataTable styling */
  table.dataTable {
    border-collapse: separate;
    border-spacing: 0;
    border-radius: var(--radius);
    overflow: hidden;
  }
  
  table.dataTable thead th {
    background: linear-gradient(125deg, var(--navy) 0%, var(--navy-l) 100%);
    color: white;
    font-weight: 700;
    padding: 1rem;
    text-align: center;
    border-left: 1px solid rgba(255,255,255,0.1);
    white-space: nowrap;
    font-size: 0.9rem;
  }
  
  table.dataTable thead th:first-child {
    border-left: none;
  }
  
  table.dataTable tbody tr {
    transition: all 0.3s ease;
  }
  
  table.dataTable tbody tr:hover {
    background: var(--gold-soft);
    transform: scale(1.002);
    box-shadow: var(--shadow-sm);
  }
  
  table.dataTable tbody td {
    padding: 12px;
    text-align: center;
    vertical-align: middle;
    font-size: .83rem;
    color: var(--txt);
    border: none;
  }

  /* Totals KPI */
  .totals {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .kpi {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border-right: 5px solid var(--gold);
    min-width: 250px;
  }
  
  .kpi:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
  }
  
  .kpi .v {
    font-weight: 900;
    font-size: 2.5rem;
    color: var(--navy);
    margin-bottom: 0.5rem;
  }
  
  .kpi .t {
    color: var(--sub);
    font-size: 0.9rem;
    font-weight: 600;
  }
</style>

</body>
</html>

