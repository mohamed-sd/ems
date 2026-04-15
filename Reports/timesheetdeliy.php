<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> إيكوبيشن | التقارير </title>
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-card {
            border-radius: 14px;
            border: 1px solid rgba(12, 28, 62, 0.08);
            background: #fff;
            padding: 16px 18px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(12, 28, 62, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(12, 28, 62, 0.14);
        }

        .stat-card .stat-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
        }

        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 900;
            color: #0c1c3e;
        }

        .stat-card.executed { border-top: 3px solid #0d9488; }
        .stat-card.fault { border-top: 3px solid #dc2626; }
        .stat-card.standby { border-top: 3px solid #e8b800; }

        .form-grid { align-items: end; }
    </style>
</head>

<body>

    <?php
    include('../insidebar.php');

    $operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

    // استقبال الفلاتر
    $date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';
    $project_filter = isset($_GET['project']) ? intval($_GET['project']) : 0;
    $supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;

    $shift_filter = isset($_GET['shift']) ? mysqli_real_escape_string($conn, $_GET['shift']) : '';
    $type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;


    $sql = "
SELECT 
    t.id,
    t.date,
    t.shift,
    d.name AS driver_name,
    e.name AS equipment_name,
    e.code AS equipment_code,
    s.name AS supplier_name,
    p.name AS project_name,
    t.executed_hours,
    t.total_fault_hours,
    t.standby_hours,
    t.work_notes,
    t.fault_notes
FROM timesheet t
JOIN drivers d ON t.driver = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o." . $operations_project_column . " = p.id
WHERE 1=1
";

    if (!empty($date_filter)) {
        $sql .= " AND t.date = '$date_filter' ";
    }
    if (!empty($project_filter)) {
        $sql .= " AND p.id = '$project_filter' ";
    }
    if (!empty($supplier_filter)) {
        $sql .= " AND s.id = '$supplier_filter' ";
    }

    if (!empty($shift_filter)) {
        $sql .= " AND t.shift = '$shift_filter' ";
    }

    if (!empty($type_filter)) {
        $sql .= " AND e.type = '$type_filter' ";
    }

    $sql .= " ORDER BY t.date, p.name, s.name ";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log('timesheetdeliy.php details query failed: ' . mysqli_error($conn));
    }

    // إجمالي الإحصائيات
    $total_sql = "
SELECT 
    SUM(t.executed_hours) AS executed_hours,
    SUM(t.total_fault_hours) AS total_fault,
    SUM(t.standby_hours) AS total_standby
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id 
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o." . $operations_project_column . " = p.id
WHERE 1=1
";

    if (!empty($date_filter)) {
        $total_sql .= " AND t.date = '$date_filter' ";
    }
    if (!empty($project_filter)) {
        $total_sql .= " AND p.id = '$project_filter' ";
    }
    if (!empty($supplier_filter)) {
        $total_sql .= " AND s.id = '$supplier_filter' ";
    }

    if (!empty($shift_filter)) {
        $total_sql .= " AND t.shift = '$shift_filter' ";
    }


    $total_res = mysqli_query($conn, $total_sql);
    $totals = $total_res ? mysqli_fetch_assoc($total_res) : ['executed_hours' => 0, 'total_fault' => 0, 'total_standby' => 0];
    if (!$total_res) {
        error_log('timesheetdeliy.php totals query failed: ' . mysqli_error($conn));
    }
    ?>

    <div class="main">

        <div class="page-header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fa-solid fa-chart-column"></i></div>
                تقرير التايم شيت اليومي
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
                    <div class="">
                        <label class="form-label">ðŸ“… التاريخ:</label>
                        <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                    </div>

                    <div>
                        <label><i class="fas fa-diagram-project"></i> المشروع</label>
                        <select name="project">
                            <option value="">-- الكل --</option>
                            <?php
                            $prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
                            while ($row = mysqli_fetch_assoc($prj)) {
                                $selected = ($project_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label><i class="fas fa-truck"></i> المورد</label>
                        <select name="supplier">
                            <option value="">-- الكل --</option>
                            <?php
                            $sup = mysqli_query($conn, "SELECT id, name FROM suppliers where status = '1' ");
                            while ($row = mysqli_fetch_assoc($sup)) {
                                $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label><i class="fas fa-clock"></i> الوردية</label>
                        <select name="shift">
                            <option value="">-- الكل --</option>
                            <option value="D" <?php if ($shift_filter == "D") echo "selected"; ?>>صباحية</option>
                            <option value="N" <?php if ($shift_filter == "N") echo "selected"; ?>>مسائية</option>
                        </select>
                    </div>

                    <div>
                        <label><i class="fas fa-cogs"></i> نوع الآلية</label>
                        <select name="type">
                            <option value="">-- الكل --</option>
                            <option value="1" <?php if ($type_filter == "1") echo "selected"; ?>>حفار</option>
                            <option value="2" <?php if ($type_filter == "2") echo "selected"; ?>>قلاب</option>
                        </select>
                    </div>

                    <button type="submit"><i class="fa fa-search"></i> بحث</button>
                </form>
            </div>
        </div>


        <!-- بطاقات الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card executed">
                <div class="stat-icon">⏱️</div>
                <div class="stat-label">إجمالي ساعات العمل</div>
                <div class="stat-value"><?php echo !empty($totals['executed_hours']) ? $totals['executed_hours'] : 0; ?></div>
            </div>
            <div class="stat-card fault">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">إجمالي ساعات الأعطال</div>
                <div class="stat-value"><?php echo !empty($totals['total_fault']) ? $totals['total_fault'] : 0; ?></div>
            </div>
            <div class="stat-card standby">
                <div class="stat-icon">⏸️</div>
                <div class="stat-label">إجمالي ساعات الاستعداد</div>
                <div class="stat-value"><?php echo !empty($totals['total_standby']) ? $totals['total_standby'] : 0; ?></div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> تفاصيل التايم شيت</h5>
            </div>
            <div class="card-body table-container">
                <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle report-table" id="projectsTable">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المشروع</th>
                            <th>المورد</th>
                            <th>الآلية</th>
                            <th>كود الآلية</th>
                            <th>السائق</th>
                            <th>الشفت</th>
                            <th>⏱️ ساعات العمل</th>
                            <th>⚠️ ساعات الأعطال</th>
                            <th>⏸️ ساعات الاستعداد</th>
                            <th>ðŸ“’ ملاحظات العمل</th>
                            <th>ðŸ“’ ملاحظات الأعطال</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo $row['project_name']; ?></td>
                                <td><?php echo $row['supplier_name']; ?></td>
                                <td><?php echo $row['equipment_name']; ?></td>
                                <td><?php echo $row['equipment_code']; ?></td>
                                <td><?php echo $row['driver_name']; ?></td>
                                <td><?php echo $row['shift']; ?></td>
                                <td style="color:#0d9488; font-weight:700;"><?php echo $row['executed_hours']; ?></td>
                                <td style="color:#dc2626; font-weight:700;"><?php echo $row['total_fault_hours']; ?></td>
                                <td style="color:#e8b800; font-weight:700;"><?php echo $row['standby_hours']; ?></td>
                                <td><?php echo $row['work_notes']; ?></td>
                                <td><?php echo $row['fault_notes']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </div>

    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>


