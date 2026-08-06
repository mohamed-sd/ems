<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
    <title>إيكوبيشن | تقرير وحدات المشغّلين</title>

    <!-- Bootstrap 5 -->
    <link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">

    <!-- ملف التصميم القديم -->
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
            font-weight: 600;
        }

        .total-hours-box {
            background: linear-gradient(135deg, rgba(13, 148, 136, 0.12), rgba(13, 148, 136, 0.06));
            border: 1px solid rgba(13, 148, 136, 0.25);
            border-radius: 14px;
            padding: 14px 16px;
            color: #0f766e;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(15, 118, 110, 0.12);
        }

        .form-grid { align-items: end; }
    </style>
</head>

<body>

<?php
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$dsc_gate = $is_super ? ems_tenant_db()->forAllTenants('report super') : ems_tenant_db();

$project_filter = isset($_GET['project']) ? intval($_GET['project']) : 0;
$driver_filter  = isset($_GET['driver']) ? intval($_GET['driver']) : 0;
$start_date     = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$start_date_raw = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date       = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';
$end_date_raw   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// فلاتر مُمعلَمة (?) — العزل يُحقن عبر {TENANT_SCOPE} (الأصل كان بلا تنطيق شركةٍ = تسريب تعزله البوابة الآن).
$dsc_filter = '';
$dsc_params = array();

// فلترة بالتاريخ
if (!empty($start_date) && !empty($end_date)) {
    $dsc_filter .= " AND t.date BETWEEN ? AND ? "; $dsc_params[] = $start_date_raw; $dsc_params[] = $end_date_raw;
} elseif (!empty($start_date)) {
    $dsc_filter .= " AND t.date = ? "; $dsc_params[] = $start_date_raw;
}

// فلترة بالمشروع
if (!empty($project_filter)) {
    $dsc_filter .= " AND p.id = ? "; $dsc_params[] = $project_filter;
}

// فلترة بالسائق
if (!empty($driver_filter)) {
    $dsc_filter .= " AND d.id = ? "; $dsc_params[] = $driver_filter;
}

$result = array();
try {
    $result = $dsc_gate->scopedQuery(
        array('scope' => array('t' => 'timesheet', 'd' => 'employees', 'o' => 'operations', 'e' => 'equipments', 'p' => 'project')),
        "
SELECT
    d.name AS driver_name,
    p.name AS project_name,
    e.name AS equipment_name,
    t.date,
    SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN employees d ON t.employee_id = d.id
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN project p ON o.project_id = p.id
WHERE 1=1$dsc_filter AND {TENANT_SCOPE} GROUP BY d.name, p.name, e.name, t.date ORDER BY t.date, d.name", $dsc_params);
} catch (\Throwable $t) {
    error_log('driverAndsupplerscontract.php report query failed: ' . $t->getMessage());
}
?>

<div class="main">

    <div class="header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fa-solid fa-truck"></i></div>
            تقرير ساعات عمل السائقين
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
                    <label><i class="fas fa-diagram-project"></i> المشروع</label>
                    <select name="project">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = array();
                        try {
                            $prj = $dsc_gate->select('project', array('columns' => array('id', 'name')));
                        } catch (\Throwable $t) { error_log('driverAndsupplerscontract.php projects: ' . $t->getMessage()); }
                        foreach ($prj as $row) {
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label><i class="fas fa-user-gear"></i> السائق</label>
                    <select name="driver">
                        <option value="">-- الكل --</option>
                        <?php
                        $drv = array();
                        try {
                            $drv = $dsc_gate->select('employees', array('columns' => array('id', 'name')));
                        } catch (\Throwable $t) { error_log('driverAndsupplerscontract.php drivers: ' . $t->getMessage()); }
                        foreach ($drv as $row) {
                            $selected = ($driver_filter == $row['id']) ? "selected" : "";
                            echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label><i class="fas fa-calendar-day"></i> من</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>

                <div>
                    <label><i class="fas fa-calendar-check"></i> إلى</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>

                <button type="submit"><i class="fa fa-search"></i> بحث</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> نتائج التقرير</h5>
        </div>
        <div class="card-body table-container">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle report-table">
                    <thead>
                    <tr>
                        <th>السائق</th>
                        <th>المشروع</th>
                        <th>الآلية</th>
                        <th>التاريخ</th>
                        <th>⏱️ مجموع الساعات</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grand_total = 0;
                    if ($result) {
                    foreach ($result as $row) {
                        $grand_total += $row['total_hours'];
                        echo "<tr>";
                        echo "<td>" . $row['driver_name'] . "</td>";
                        echo "<td>" . $row['project_name'] . "</td>";
                        echo "<td>" . $row['equipment_name'] . "</td>";
                        echo "<td>" . $row['date'] . "</td>";
                        echo "<td>" . $row['total_hours'] . "</td>";
                        echo "</tr>";
                    }
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="total-hours-box mt-3">
                <i class="fas fa-check-circle"></i>
                إجمالي الساعات: <?php echo $grand_total; ?> ساعة
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>



