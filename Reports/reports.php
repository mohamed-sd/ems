<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$supplier_filter = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$project_filter = isset($_GET['project']) ? $_GET['project'] : '';
$mine_filter = isset($_GET['mine']) ? $_GET['mine'] : '';
$contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

$sql = "
SELECT 
    s.name AS supplier_name,
    p.name AS project_name,
    IFNULL(m.mine_name, '') AS mine_name,
    IFNULL(m.mine_code, '') AS mine_code,
    c.id AS contract_id,
    c.contract_signing_date,
    SUM(t.executed_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id   
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o.project_id = p.id
LEFT JOIN mines m ON o.mine_id = m.id
LEFT JOIN contracts c ON o.contract_id = c.id
WHERE t.status = 1 AND o.status = 1
";

if (!empty($supplier_filter)) {
    $sql .= " AND s.id = '" . mysqli_real_escape_string($conn, $supplier_filter) . "' ";
}
if (!empty($project_filter)) {
    $sql .= " AND p.id = '" . mysqli_real_escape_string($conn, $project_filter) . "' ";
}
if (!empty($mine_filter)) {
    $sql .= " AND m.id = '" . mysqli_real_escape_string($conn, $mine_filter) . "' ";
}
if (!empty($contract_filter)) {
    $sql .= " AND c.id = '" . mysqli_real_escape_string($conn, $contract_filter) . "' ";
}
$sql .= " GROUP BY s.id, s.name, p.id, p.name, m.id, m.mine_name, m.mine_code, c.id, c.contract_signing_date 
          ORDER BY p.name, m.mine_name, s.name ";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/main_admin_style.css">
</head>


<body>

    <?php
    include('../insidebar.php');
    ?>

    <div class="main">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="title-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h1 class="page-title">Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±</h1>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                 <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
                <?php // ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¯ÙŠØ± Ø§Ù„Ù…ÙˆÙ‚Ø¹ === 5
                if ($_SESSION['user']['role'] == "5") { ?>
                    <a href="deliy.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…</a>
                    <a href="deriver.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø³Ø§Ø¦Ù‚</a>
                    <a href="timesheetdeliy.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</a>
                <?php } ?>
                <?php // ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† === 3
                if ($_SESSION['user']['role'] == "3") { ?>
                    <a href="deriver.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø³Ø§Ø¦Ù‚</a>
                <?php } ?>
                <?php // ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¯ÙŠØ± Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† === 2
                if ($_SESSION['user']['role'] == "2") { ?>
                    <a href="timesheetdeliy.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</a>
                <?php } ?>
                <?php // ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¯ÙŠØ± Ø§Ù„Ø§Ø³Ø·ÙˆÙ„ === 4
                if ($_SESSION['user']['role'] == "4") { ?>
                    <a href="deliy.php" class="add-btn"><i class="fa fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…</a>
                <?php } ?>
                <?php // ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ === 1
                if ($_SESSION['user']['role'] == "1") { ?>
                    <a href="contract_report.php" class="add-btn"><i class="fa fa-file-contract"></i> Ø§Ù„Ø¹Ù‚Ø¯</a>
                    <a href="contractall.php" class="add-btn"><i class="fa fa-chart-pie"></i> Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª Ø§Ù„Ø¹Ù‚Ø¯</a>
                    <a href="driverAndsupplerscontract.php" class="add-btn"><i class="fa fa-users"></i> Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª Ø§Ù„Ø¹Ù‚ÙˆØ¯</a>
                <?php } ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> ÙÙ„Ø§ØªØ± Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="form-grid" style="margin-bottom: 18px;">
                    <div>
                        <label><i class="fas fa-truck-loading"></i> Ø§Ù„Ù…ÙˆØ±Ø¯</label>
                        <select name="supplier">
                            <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                            <?php
                            $sup = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE status = '1' ORDER BY name");
                            while ($row = mysqli_fetch_assoc($sup)) {
                                $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-project-diagram"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
                        <select name="project" id="projectSelect">
                            <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                            <?php
                            $prj = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");
                            while ($row = mysqli_fetch_assoc($prj)) {
                                $selected = ($project_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['project_code']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-mountain"></i> Ø§Ù„Ù…Ù†Ø¬Ù…</label>
                        <select name="mine" id="mineSelect">
                            <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                            <?php
                            if (!empty($project_filter)) {
                                $mines = mysqli_query($conn, "SELECT id, mine_name, mine_code FROM mines WHERE project_id = '$project_filter' AND status = 1 ORDER BY mine_name");
                                while ($row = mysqli_fetch_assoc($mines)) {
                                    $selected = ($mine_filter == $row['id']) ? "selected" : "";
                                    echo "<option value='{$row['id']}' $selected>{$row['mine_name']} ({$row['mine_code']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-file-contract"></i> Ø§Ù„Ø¹Ù‚Ø¯</label>
                        <select name="contract" id="contractSelect">
                            <option value="">-- Ø§Ù„ÙƒÙ„ --</option>
                            <?php
                            if (!empty($mine_filter)) {
                                $contracts = mysqli_query($conn, "SELECT id, contract_signing_date FROM contracts WHERE mine_id = '$mine_filter' AND status = 1 ORDER BY contract_signing_date DESC");
                                while ($row = mysqli_fetch_assoc($contracts)) {
                                    $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                    echo "<option value='{$row['id']}' $selected>Ø¹Ù‚Ø¯ #{$row['id']} - {$row['contract_signing_date']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1; display: flex; justify-content: center; gap: 10px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-filter"></i> ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„ÙÙ„ØªØ±
                        </button>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fa fa-redo"></i> Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ†
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> Ù†ØªØ§Ø¦Ø¬ Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±</h5>
            </div>
            <div class="card-body">
                <div id="projectsTable" class="table-container">
                    <table class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> #</th>
                                <th><i class="fas fa-project-diagram"></i> Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
                                <th><i class="fas fa-mountain"></i> Ø§Ù„Ù…Ù†Ø¬Ù…</th>
                                <th><i class="fas fa-file-contract"></i> Ø§Ù„Ø¹Ù‚Ø¯</th>
                                <th><i class="fas fa-truck-loading"></i> Ø§Ù„Ù…ÙˆØ±Ø¯</th>
                                <th><i class="fas fa-clock"></i> Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ´ØºÙŠÙ„</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                                $mine_display = !empty($row['mine_name']) ? $row['mine_name'] . ' (' . $row['mine_code'] . ')' : '<span class="text-muted">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>';
                                $contract_display = !empty($row['contract_id']) ? 'Ø¹Ù‚Ø¯ #' . $row['contract_id'] . ' - ' . $row['contract_signing_date'] : '<span class="text-muted">ØºÙŠØ± Ù…Ø­Ø¯Ø¯</span>';
                            ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td><span class="client-name-link"><?= htmlspecialchars($row['project_name']); ?></span></td>
                                    <td><?= $mine_display; ?></td>
                                    <td><?= $contract_display; ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                                    <td><span class="status-active"><?= number_format($row['total_hours'], 2); ?> Ø³Ø§Ø¹Ø©</span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

            <!-- jQuery (ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† Ø£ÙˆÙ„Ø§Ù‹) -->
            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <!-- Bootstrap JS -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            <!-- DataTables JS -->
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    </div>


    <script>
        (function () {
            $(document).ready(function () {
                $('#projectsTable table').DataTable({
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: [
                        { extend: 'copy', text: 'Ù†Ø³Ø®' },
                        { extend: 'excel', text: 'ØªØµØ¯ÙŠØ± Excel' },
                        { extend: 'csv', text: 'ØªØµØ¯ÙŠØ± CSV' },
                        { extend: 'pdf', text: 'ØªØµØ¯ÙŠØ± PDF' },
                        { extend: 'print', text: 'Ø·Ø¨Ø§Ø¹Ø©' }
                    ],
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                    }
                });

                // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ù†Ø§Ø¬Ù… Ø¹Ù†Ø¯ ØªØºÙŠÙŠØ± Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
                $('#projectSelect').on('change', function() {
                    const projectId = $(this).val();
                    const mineSelect = $('#mineSelect');
                    const contractSelect = $('#contractSelect');
                    
                    console.log('ØªÙ… Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ù…Ø´Ø±ÙˆØ¹:', projectId);
                    
                    // Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ù†Ø§Ø¬Ù… ÙˆØ§Ù„Ø¹Ù‚ÙˆØ¯
                    mineSelect.html('<option value="">-- Ø§Ù„ÙƒÙ„ --</option>');
                    contractSelect.html('<option value="">-- Ø§Ù„ÙƒÙ„ --</option>');
                    
                    if (projectId) {
                        console.log('Ø¬Ø§Ø±ÙŠ ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ù†Ø§Ø¬Ù…...');
                        $.ajax({
                            url: 'get_project_mines.php',
                            type: 'GET',
                            data: { project_id: projectId },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Ø§Ø³ØªØ¬Ø§Ø¨Ø© Ø§Ù„Ù…Ù†Ø§Ø¬Ù…:', response);
                                if (response.success && response.mines && response.mines.length > 0) {
                                    console.log('Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ù†Ø§Ø¬Ù…:', response.mines.length);
                                    response.mines.forEach(function(mine) {
                                        mineSelect.append(`<option value="${mine.id}">${mine.mine_name} (${mine.mine_code})</option>`);
                                    });
                                } else {
                                    console.log('Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù†Ø§Ø¬Ù… Ù„Ù‡Ø°Ø§ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Ø®Ø·Ø£ ÙÙŠ ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ù†Ø§Ø¬Ù…:', error);
                                console.log('Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨:', status);
                                console.log('Response:', xhr.responseText);
                            }
                        });
                    }
                });

                // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¹Ù‚ÙˆØ¯ Ø¹Ù†Ø¯ ØªØºÙŠÙŠØ± Ø§Ù„Ù…Ù†Ø¬Ù…
                $('#mineSelect').on('change', function() {
                    const mineId = $(this).val();
                    const contractSelect = $('#contractSelect');
                    
                    console.log('ØªÙ… Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ù…Ù†Ø¬Ù…:', mineId);
                    
                    contractSelect.html('<option value="">-- Ø§Ù„ÙƒÙ„ --</option>');
                    
                    if (mineId) {
                        console.log('Ø¬Ø§Ø±ÙŠ ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¹Ù‚ÙˆØ¯...');
                        $.ajax({
                            url: 'get_mine_contracts.php',
                            type: 'GET',
                            data: { mine_id: mineId },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Ø§Ø³ØªØ¬Ø§Ø¨Ø© Ø§Ù„Ø¹Ù‚ÙˆØ¯:', response);
                                if (response.success && response.contracts && response.contracts.length > 0) {
                                    console.log('Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯:', response.contracts.length);
                                    response.contracts.forEach(function(contract) {
                                        contractSelect.append(`<option value="${contract.id}">Ø¹Ù‚Ø¯ #${contract.id} - ${contract.contract_signing_date}</option>`);
                                    });
                                } else {
                                    console.log('Ù„Ø§ ØªÙˆØ¬Ø¯ Ø¹Ù‚ÙˆØ¯ Ù„Ù‡Ø°Ø§ Ø§Ù„Ù…Ù†Ø¬Ù…');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Ø®Ø·Ø£ ÙÙŠ ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø¹Ù‚ÙˆØ¯:', error);
                                console.log('Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨:', status);
                                console.log('Response:', xhr.responseText);
                            }
                        });
                    }
                });
            });
        })();
    </script>
</body>

</html>
