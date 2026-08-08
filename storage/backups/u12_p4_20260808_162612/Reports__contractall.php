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
    <title> إيكوبيشن | تقارير العقد</title>

    <!-- Bootstrap 5 -->
    <link href="/ems/assets/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- أيقونات -->
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <!-- استايلك القديم -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

    <style>
        .main {
            font-family: 'Cairo', sans-serif;
        }

        .report-tabs {
            gap: 8px;
            flex-wrap: wrap;
        }

        .report-tabs .nav-link {
            border-radius: 999px;
            border: 1px solid rgba(12, 28, 62, 0.12);
            color: #0c1c3e;
            background: #fff;
            font-weight: 700;
            padding: 8px 14px;
            transition: all 0.2s ease;
        }

        .report-tabs .nav-link:hover {
            border-color: rgba(232, 184, 0, 0.45);
            background: rgba(232, 184, 0, 0.12);
            color: #0c1c3e;
        }

        .report-tabs .nav-link.active {
            background: linear-gradient(135deg, #0c1c3e, #1b2f6e);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 8px 22px rgba(12, 28, 62, 0.22);
        }

        .tab-card {
            border: 1px solid rgba(12, 28, 62, 0.08);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(12, 28, 62, 0.08);
            padding: 18px;
        }

        .report-table thead th {
            background: #f8fafc;
            color: #0c1c3e;
            font-weight: 800;
        }

        .summary-list .list-group-item {
            border-color: rgba(12, 28, 62, 0.08);
            font-weight: 600;
            color: #0c1c3e;
        }

        .metric-box {
            border: 1px solid rgba(12, 28, 62, 0.09);
            background: rgba(12, 28, 62, 0.03);
            border-radius: 12px;
            padding: 12px 14px;
            color: #0c1c3e;
            font-weight: 700;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

    // العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
    $is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
    $ca_gate = $is_super ? ems_tenant_db()->forAllTenants('report super') : ems_tenant_db();

    $contract_filter = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

    $contracts = array();
    try {
        $contracts = $ca_gate->scopedQuery(
            array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project')),
            "SELECT c.id, p.name AS project_name
                      FROM contracts c
                      LEFT JOIN project p ON c.project_id = p.id WHERE 1=1 AND {TENANT_SCOPE}");
    } catch (\Throwable $t) { error_log('contractall.php sql_contracts failed: ' . $t->getMessage()); }

    $contract_data = $time_vs_progress = $faults = $suppliers = $equipments = $drivers = $variance = null;

    if ($contract_filter > 0) {
        // تفاصيل العقد
        try {
            $contract_data_rows = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
                "
        SELECT
            c.id AS contract_id,
            p.name AS project_name,
            c.contract_signing_date,
            c.contract_duration_months,
            c.hours_monthly_target,
            c.forecasted_contracted_hours,
            IFNULL(SUM(t.executed_hours),0) AS actual_hours
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}
        GROUP BY c.id, p.name", array($contract_filter));
            $contract_data = $contract_data_rows ? $contract_data_rows[0] : null;
        } catch (\Throwable $t) {
            error_log('contractall.php sql_info failed: ' . $t->getMessage());
        }

        // الزمن مقابل الإنجاز
        try {
            $time_vs_progress_rows = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
                "
        SELECT
            (TIMESTAMPDIFF(MONTH, c.contract_signing_date, CURDATE()) / c.contract_duration_months) * 100 AS time_progress,
            (IFNULL(SUM(t.executed_hours),0) / c.forecasted_contracted_hours) * 100 AS work_progress
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}", array($contract_filter));
            $time_vs_progress = $time_vs_progress_rows ? $time_vs_progress_rows[0] : null;
        } catch (\Throwable $t) {
            error_log('contractall.php sql_time failed: ' . $t->getMessage());
        }

        // الأعطال
        try {
            $faults_rows = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
                "
        SELECT SUM(t.total_fault_hours) AS total_fault_hours
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}", array($contract_filter));
            $faults = $faults_rows ? $faults_rows[0] : null;
        } catch (\Throwable $t) {
            error_log('contractall.php sql_faults failed: ' . $t->getMessage());
        }

        // الموردين
        try {
            $suppliers = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts', 'o' => 'operations', 'e' => 'equipments', 's' => 'suppliers'), 'enrich' => array('p' => 'project', 't' => 'timesheet')),
                "
        SELECT s.name AS supplier_name, SUM(t.executed_hours) AS total_work_hours
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        JOIN suppliers s ON e.suppliers = s.id
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}
        GROUP BY s.name", array($contract_filter));
        } catch (\Throwable $t) {
            $suppliers = null;
            error_log('contractall.php sql_suppliers failed: ' . $t->getMessage());
        }

        // الآليات
        try {
            $equipments = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts', 'o' => 'operations', 'e' => 'equipments'), 'enrich' => array('p' => 'project', 't' => 'timesheet')),
                "
        SELECT e.name AS equipment_name,
               SUM(t.executed_hours) AS work_hours,
               SUM(t.total_fault_hours) AS fault_hours
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}
        GROUP BY e.name", array($contract_filter));
        } catch (\Throwable $t) {
            $equipments = null;
            error_log('contractall.php sql_equipments failed: ' . $t->getMessage());
        }

        // السائقين
        try {
            $drivers = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts', 'o' => 'operations', 'e' => 'equipments', 'd' => 'employees'), 'enrich' => array('p' => 'project', 't' => 'timesheet')),
                "
        SELECT d.name AS driver_name, SUM(t.executed_hours) AS driver_hours
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        JOIN employees d ON t.employee_id = d.id
        WHERE c.id = ? AND {TENANT_SCOPE}
        GROUP BY d.name", array($contract_filter));
        } catch (\Throwable $t) {
            $drivers = null;
            error_log('contractall.php sql_drivers failed: ' . $t->getMessage());
        }

        // الانحراف
        try {
            $variance_rows = $ca_gate->scopedQuery(
                array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project', 'o' => 'operations', 'e' => 'equipments', 't' => 'timesheet')),
                "
        SELECT c.forecasted_contracted_hours AS planned_hours,
               IFNULL(SUM(t.executed_hours),0) AS actual_hours,
               (IFNULL(SUM(t.executed_hours),0) - c.forecasted_contracted_hours) AS variance
        FROM contracts c
        LEFT JOIN project p ON c.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = ? AND {TENANT_SCOPE}
        GROUP BY c.id", array($contract_filter));
            $variance = $variance_rows ? $variance_rows[0] : null;
        } catch (\Throwable $t) {
            error_log('contractall.php sql_variance failed: ' . $t->getMessage());
        }
    }
    ?>

    <div class="main">
        <div class="header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fa-solid fa-chart-line"></i></div>
                تقارير تفصيلية للعقد
            </h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="reports.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> اختيار العقد</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="form-grid" style="align-items:end;">
                    <div>
                        <label><i class="fas fa-file-contract"></i> اختر العقد</label>
                        <select name="contract">
                            <option value="">-- اختر --</option>
                            <?php foreach ($contracts as $row) {
                                $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['project_name']}</option>";
                            } ?>
                        </select>
                    </div>
                    <button type="submit"><i class="fa fa-eye"></i> عرض التقرير</button>
                </form>
            </div>
        </div>

        <?php if ($contract_data) { ?>
        <ul class="nav nav-pills report-tabs mb-3" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#basic"><i class="fas fa-file-lines"></i> التفاصيل الأساسية</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#time"><i class="fas fa-hourglass-half"></i> الزمن مقابل الإنجاز</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#faults"><i class="fas fa-triangle-exclamation"></i> الأعطال</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suppliers"><i class="fas fa-truck"></i> الموردين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#equipments"><i class="fas fa-tractor"></i> الآليات</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#drivers"><i class="fas fa-helmet-safety"></i> السائقين</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#variance"><i class="fas fa-chart-line"></i> الانحراف</button></li>
        </ul>

        <div class="tab-content">
            <!-- التفاصيل الأساسية -->
            <div class="tab-pane fade show active" id="basic">
                <div class="tab-card">
                    <ul class="list-group summary-list">
                        <li class="list-group-item">المشروع: <?= $contract_data['project_name'] ?></li>
                        <li class="list-group-item">تاريخ التوقيع: <?= $contract_data['contract_signing_date'] ?></li>
                        <li class="list-group-item">مدة العقد: <?= $contract_data['contract_duration_months'] ?> شهور</li>
                        <li class="list-group-item">الهدف الشهري: <?= $contract_data['hours_monthly_target'] ?></li>
                        <li class="list-group-item">الإجمالي المتوقع: <?= $contract_data['forecasted_contracted_hours'] ?></li>
                        <li class="list-group-item">المنفذ: <?= $contract_data['actual_hours'] ?></li>
                    </ul>
                </div>
            </div>

            <!-- الزمن مقابل الإنجاز -->
            <div class="tab-pane fade" id="time">
                <div class="tab-card">
                    <div class="metric-box">التقدم الزمني: <?= round($time_vs_progress['time_progress'], 2) ?> %</div>
                    <div class="metric-box">التقدم الفعلي: <?= round($time_vs_progress['work_progress'], 2) ?> %</div>
                </div>
            </div>

            <!-- الأعطال -->
            <div class="tab-pane fade" id="faults">
                <div class="tab-card">
                   <p><?php echo isset($faults['total_fault_hours']) ? $faults['total_fault_hours'] : 0; ?> ساعة</p>
                </div>
            </div>

            <!-- الموردين -->
            <div class="tab-pane fade" id="suppliers">
                <div class="tab-card table-responsive">
                <table class="table table-striped report-table">
                    <thead>
                        <tr><th>المورد</th><th>إجمالي الساعات</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($suppliers) { foreach ($suppliers as $row) { ?>
                        <tr>
                            <td><?= $row['supplier_name'] ?></td>
                            <td><?= $row['total_work_hours'] ?></td>
                        </tr>
                    <?php } } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- الآليات -->
            <div class="tab-pane fade" id="equipments">
                <div class="tab-card table-responsive">
                <table class="table table-bordered report-table">
                    <thead>
                        <tr><th>الآلية</th><th>ساعات العمل</th><th>ساعات الأعطال</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($equipments) { foreach ($equipments as $row) { ?>
                        <tr>
                            <td><?= $row['equipment_name'] ?></td>
                            <td><?= $row['work_hours'] ?></td>
                            <td><?= $row['fault_hours'] ?></td>
                        </tr>
                    <?php } } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- السائقين -->
            <div class="tab-pane fade" id="drivers">
                <div class="tab-card table-responsive">
                <table class="table table-hover report-table">
                    <thead>
                        <tr><th>السائق</th><th>إجمالي الساعات</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($drivers) { foreach ($drivers as $row) { ?>
                        <tr>
                            <td><?= $row['driver_name'] ?></td>
                            <td><?= $row['driver_hours'] ?></td>
                        </tr>
                    <?php } } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- الانحراف -->
            <div class="tab-pane fade" id="variance">
                <div class="tab-card">
                    <div class="metric-box">المخطط: <?= $variance['planned_hours'] ?> ساعة</div>
                    <div class="metric-box">المنفذ: <?= $variance['actual_hours'] ?> ساعة</div>
                    <div class="metric-box">الانحراف: <?= $variance['variance'] ?> ساعة</div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
