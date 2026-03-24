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
    <title> Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ± </title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Ø£ÙŠÙ‚ÙˆÙ†Ø§Øª -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
    <!-- Ø§Ø³ØªØ§ÙŠÙ„Ùƒ Ø§Ù„Ù‚Ø¯ÙŠÙ… -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

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
    include '../config.php';

    $contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

    $sql_contracts = "SELECT c.id, m.mine_name, p.name AS project_name 
                      FROM contracts c 
                      LEFT JOIN mines m ON c.mine_id = m.id
                      LEFT JOIN project p ON m.project_id = p.id";
    $contracts = mysqli_query($conn, $sql_contracts);

    $contract_data = $time_vs_progress = $faults = $suppliers = $equipments = $drivers = $variance = null;

    if (!empty($contract_filter)) {
        // ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯
        $sql_info = "
        SELECT 
            c.id AS contract_id,
            m.mine_name,
            p.name AS project_name,
            c.contract_signing_date,
            c.contract_duration_months,
            c.hours_monthly_target,
            c.forecasted_contracted_hours,
            IFNULL(SUM(t.executed_hours),0) AS actual_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY c.id, m.mine_name, p.name";
        $contract_data = mysqli_fetch_assoc(mysqli_query($conn, $sql_info));

        // Ø§Ù„Ø²Ù…Ù† Ù…Ù‚Ø§Ø¨Ù„ Ø§Ù„Ø¥Ù†Ø¬Ø§Ø²
        $sql_time = "
        SELECT 
            (TIMESTAMPDIFF(MONTH, c.contract_signing_date, CURDATE()) / c.contract_duration_months) * 100 AS time_progress,
            (IFNULL(SUM(t.executed_hours),0) / c.forecasted_contracted_hours) * 100 AS work_progress
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'";
        $time_vs_progress = mysqli_fetch_assoc(mysqli_query($conn, $sql_time));

        // Ø§Ù„Ø£Ø¹Ø·Ø§Ù„
        $sql_faults = "
        SELECT SUM(t.total_fault_hours) AS total_fault_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'";
        $faults = mysqli_fetch_assoc(mysqli_query($conn, $sql_faults));

        // Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†
        $sql_suppliers = "
        SELECT s.name AS supplier_name, SUM(t.executed_hours) AS total_work_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        JOIN suppliers s ON e.suppliers = s.id
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY s.name";
        $suppliers = mysqli_query($conn, $sql_suppliers);

        // Ø§Ù„Ø¢Ù„ÙŠØ§Øª
        $sql_equipments = "
        SELECT e.name AS equipment_name,
               SUM(t.executed_hours) AS work_hours,
               SUM(t.total_fault_hours) AS fault_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY e.name";
        $equipments = mysqli_query($conn, $sql_equipments);

        // Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ†
        $sql_drivers = "
        SELECT d.name AS driver_name, SUM(t.executed_hours) AS driver_hours
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        JOIN operations o ON o.project_id = p.id
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        JOIN drivers d ON t.driver = d.id
        WHERE c.id = '$contract_filter'
        GROUP BY d.name";
        $drivers = mysqli_query($conn, $sql_drivers);

        // Ø§Ù„Ø§Ù†Ø­Ø±Ø§Ù
        $sql_variance = "
        SELECT c.forecasted_contracted_hours AS planned_hours,
               IFNULL(SUM(t.executed_hours),0) AS actual_hours,
               (IFNULL(SUM(t.executed_hours),0) - c.forecasted_contracted_hours) AS variance
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id
        WHERE c.id = '$contract_filter'
        GROUP BY c.id";
        $variance = mysqli_fetch_assoc(mysqli_query($conn, $sql_variance));
    }
    ?>

    <div class="main">
        <div class="page-header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fa-solid fa-chart-line"></i></div>
                ØªÙ‚Ø§Ø±ÙŠØ± ØªÙØµÙŠÙ„ÙŠØ© Ù„Ù„Ø¹Ù‚Ø¯
            </h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="reports.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ø¹Ù‚Ø¯</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="form-grid" style="align-items:end;">
                    <div>
                        <label><i class="fas fa-file-contract"></i> Ø§Ø®ØªØ± Ø§Ù„Ø¹Ù‚Ø¯</label>
                        <select name="contract">
                            <option value="">-- Ø§Ø®ØªØ± --</option>
                            <?php while($row = mysqli_fetch_assoc($contracts)) {
                                $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>Ø¹Ù‚Ø¯ #{$row['id']} - {$row['mine_name']} ({$row['project_name']})</option>";
                            } ?>
                        </select>
                    </div>
                    <button type="submit"><i class="fa fa-eye"></i> Ø¹Ø±Ø¶ Ø§Ù„ØªÙ‚Ø±ÙŠØ±</button>
                </form>
            </div>
        </div>

        <?php if ($contract_data) { ?>
        <ul class="nav nav-pills report-tabs mb-3" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#basic"><i class="fas fa-file-lines"></i> Ø§Ù„ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#time"><i class="fas fa-hourglass-half"></i> Ø§Ù„Ø²Ù…Ù† Ù…Ù‚Ø§Ø¨Ù„ Ø§Ù„Ø¥Ù†Ø¬Ø§Ø²</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#faults"><i class="fas fa-triangle-exclamation"></i> Ø§Ù„Ø£Ø¹Ø·Ø§Ù„</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suppliers"><i class="fas fa-truck"></i> Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#equipments"><i class="fas fa-tractor"></i> Ø§Ù„Ø¢Ù„ÙŠØ§Øª</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#drivers"><i class="fas fa-helmet-safety"></i> Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ†</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#variance"><i class="fas fa-chart-line"></i> Ø§Ù„Ø§Ù†Ø­Ø±Ø§Ù</button></li>
        </ul>

        <div class="tab-content">
            <!-- Ø§Ù„ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© -->
            <div class="tab-pane fade show active" id="basic">
                <div class="tab-card">
                    <ul class="list-group summary-list">
                        <li class="list-group-item">Ø§Ù„Ù…Ø´Ø±ÙˆØ¹: <?= $contract_data['project_name'] ?></li>
                        <li class="list-group-item">ØªØ§Ø±ÙŠØ® Ø§Ù„ØªÙˆÙ‚ÙŠØ¹: <?= $contract_data['contract_signing_date'] ?></li>
                        <li class="list-group-item">Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯: <?= $contract_data['contract_duration_months'] ?> Ø´Ù‡ÙˆØ±</li>
                        <li class="list-group-item">Ø§Ù„Ù‡Ø¯Ù Ø§Ù„Ø´Ù‡Ø±ÙŠ: <?= $contract_data['hours_monthly_target'] ?></li>
                        <li class="list-group-item">Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…ØªÙˆÙ‚Ø¹: <?= $contract_data['forecasted_contracted_hours'] ?></li>
                        <li class="list-group-item">Ø§Ù„Ù…Ù†ÙØ°: <?= $contract_data['actual_hours'] ?></li>
                    </ul>
                </div>
            </div>

            <!-- Ø§Ù„Ø²Ù…Ù† Ù…Ù‚Ø§Ø¨Ù„ Ø§Ù„Ø¥Ù†Ø¬Ø§Ø² -->
            <div class="tab-pane fade" id="time">
                <div class="tab-card">
                    <div class="metric-box">Ø§Ù„ØªÙ‚Ø¯Ù… Ø§Ù„Ø²Ù…Ù†ÙŠ: <?= round($time_vs_progress['time_progress'], 2) ?> %</div>
                    <div class="metric-box">Ø§Ù„ØªÙ‚Ø¯Ù… Ø§Ù„ÙØ¹Ù„ÙŠ: <?= round($time_vs_progress['work_progress'], 2) ?> %</div>
                </div>
            </div>

            <!-- Ø§Ù„Ø£Ø¹Ø·Ø§Ù„ -->
            <div class="tab-pane fade" id="faults">
                <div class="tab-card">
                   <p><?php echo isset($faults['total_fault_hours']) ? $faults['total_fault_hours'] : 0; ?> Ø³Ø§Ø¹Ø©</p>
                </div>
            </div>

            <!-- Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† -->
            <div class="tab-pane fade" id="suppliers">
                <div class="tab-card table-responsive">
                <table class="table table-striped report-table">
                    <thead>
                        <tr><th>Ø§Ù„Ù…ÙˆØ±Ø¯</th><th>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($suppliers)) { ?>
                        <tr>
                            <td><?= $row['supplier_name'] ?></td>
                            <td><?= $row['total_work_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Ø§Ù„Ø¢Ù„ÙŠØ§Øª -->
            <div class="tab-pane fade" id="equipments">
                <div class="tab-card table-responsive">
                <table class="table table-bordered report-table">
                    <thead>
                        <tr><th>Ø§Ù„Ø¢Ù„ÙŠØ©</th><th>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</th><th>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø£Ø¹Ø·Ø§Ù„</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($equipments)) { ?>
                        <tr>
                            <td><?= $row['equipment_name'] ?></td>
                            <td><?= $row['work_hours'] ?></td>
                            <td><?= $row['fault_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ† -->
            <div class="tab-pane fade" id="drivers">
                <div class="tab-card table-responsive">
                <table class="table table-hover report-table">
                    <thead>
                        <tr><th>Ø§Ù„Ø³Ø§Ø¦Ù‚</th><th>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($drivers)) { ?>
                        <tr>
                            <td><?= $row['driver_name'] ?></td>
                            <td><?= $row['driver_hours'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Ø§Ù„Ø§Ù†Ø­Ø±Ø§Ù -->
            <div class="tab-pane fade" id="variance">
                <div class="tab-card">
                    <div class="metric-box">Ø§Ù„Ù…Ø®Ø·Ø·: <?= $variance['planned_hours'] ?> Ø³Ø§Ø¹Ø©</div>
                    <div class="metric-box">Ø§Ù„Ù…Ù†ÙØ°: <?= $variance['actual_hours'] ?> Ø³Ø§Ø¹Ø©</div>
                    <div class="metric-box">Ø§Ù„Ø§Ù†Ø­Ø±Ø§Ù: <?= $variance['variance'] ?> Ø³Ø§Ø¹Ø©</div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

