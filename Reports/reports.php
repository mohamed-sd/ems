<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$_current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$_is_super_admin = ($_current_role === '-1');
$_report_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$_suppliers_has_company_id = db_table_has_column($conn, 'suppliers', 'company_id');
$_supplier_company_where = (!$_is_super_admin && $_suppliers_has_company_id && $_report_company_id > 0)
    ? " AND company_id = '$_report_company_id'"
    : "";

$supplier_filter = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$project_filter = isset($_GET['project']) ? $_GET['project'] : '';
$contract_filter = isset($_GET['contract']) ? $_GET['contract'] : '';

$sql = "
SELECT
    s.name AS supplier_name,
    p.name AS project_name,
    c.id AS contract_id,
    c.contract_signing_date,
    SUM(t.executed_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o.project_id = p.id
LEFT JOIN contracts c ON o.contract_id = c.id
WHERE t.status = 1 AND o.status = 1
";

if (!empty($supplier_filter)) {
    $sql .= " AND s.id = '" . mysqli_real_escape_string($conn, $supplier_filter) . "' ";
}
if (!empty($project_filter)) {
    $sql .= " AND p.id = '" . mysqli_real_escape_string($conn, $project_filter) . "' ";
}
if (!empty($contract_filter)) {
    $sql .= " AND c.id = '" . mysqli_real_escape_string($conn, $contract_filter) . "' ";
}
$sql .= " GROUP BY s.id, s.name, p.id, p.name, c.id, c.contract_signing_date
          ORDER BY p.name, s.name ";
$result = mysqli_query($conn, $sql);

$page_title = "إيكوبيشن | التقارير";
include("../inheader.php");
include('../insidebar.php');
?>
    <div class="main ems-unified-page-shell reports-main">
        <?php
        // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
        $header_title   = 'التقارير';
        $header_icon    = 'fa-solid fa-chart-line';
        $header_actions = array();
        $__role = $_SESSION['user']['role'];
        if ($__role == "5") { // مدير الموقع
            $header_actions[] = array('href' => 'deliy.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات اليوم');
            $header_actions[] = array('href' => 'deriver.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات السائق');
            $header_actions[] = array('href' => 'timesheetdeliy.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات العمل اليومية');
        }
        if ($__role == "3") { // مدير المشغلين
            $header_actions[] = array('href' => 'deriver.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات السائق');
        }
        if ($__role == "2") { // مدير الموردين
            $header_actions[] = array('href' => 'timesheetdeliy.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات العمل اليومية');
        }
        if ($__role == "4") { // مدير الاسطول
            $header_actions[] = array('href' => 'deliy.php', 'class' => 'add-btn', 'icon' => 'fa fa-clock', 'label' => 'ساعات اليوم');
        }
        if ($__role == "1") { // مدير المشاريع
            $header_actions[] = array('href' => 'contract_report.php', 'class' => 'add-btn', 'icon' => 'fa fa-file-contract', 'label' => 'العقد');
            $header_actions[] = array('href' => 'contractall.php', 'class' => 'add-btn', 'icon' => 'fa fa-chart-pie', 'label' => 'إحصائيات العقد');
            $header_actions[] = array('href' => 'driverAndsupplerscontract.php', 'class' => 'add-btn', 'icon' => 'fa fa-users', 'label' => 'إحصائيات العقود');
        }
        $header_back = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
        include('../includes/page_header.php');
        ?>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> فلاتر التقارير</h5>
            </div>
            <div class="card-body fc-filter-body">
                <form method="GET" class="fc-filter-bar" style="margin-bottom: 0;">
                    <div>
                        <label class="fc-filter-label"><i class="fas fa-truck-loading"></i> المورد</label>
                        <select name="supplier">
                            <option value="">-- الكل --</option>
                            <?php
                            $sup = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE status = '1'$_supplier_company_where ORDER BY name");
                            if ($sup) {
                            while ($row = mysqli_fetch_assoc($sup)) {
                                $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                            }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="fc-filter-label"><i class="fas fa-project-diagram"></i> المشروع</label>
                        <select name="project" id="projectSelect">
                            <option value="">-- الكل --</option>
                            <?php
                            $prj = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");
                            if ($prj) {
                            while ($row = mysqli_fetch_assoc($prj)) {
                                $selected = ($project_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['project_code']})</option>";
                            }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="fc-filter-label"><i class="fas fa-file-contract"></i> العقد</label>
                        <select name="contract" id="contractSelect">
                            <option value="">-- الكل --</option>
                            <?php
                            if (!empty($project_filter)) {
                                $contracts = mysqli_query($conn, "SELECT id, contract_signing_date FROM contracts WHERE project_id = '$project_filter' AND status = 1 ORDER BY contract_signing_date DESC");
                                if ($contracts) {
                                while ($row = mysqli_fetch_assoc($contracts)) {
                                    $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                    echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['contract_signing_date']}</option>";
                                }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="fc-filter-actions" style="gap: 10px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-filter"></i> تطبيق الفلتر
                        </button>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fa fa-redo"></i> إعادة تعيين
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> نتائج التقارير</h5>
            </div>
            <div class="card-body">
                <div id="projectsTable" class="table-container">
                    <table class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> #</th>
                                <th><i class="fas fa-project-diagram"></i> المشروع</th>
                                <th><i class="fas fa-file-contract"></i> العقد</th>
                                <th><i class="fas fa-truck-loading"></i> المورد</th>
                                <th><i class="fas fa-clock"></i> إجمالي ساعات التشغيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $contract_display = !empty($row['contract_id']) ? 'عقد #' . $row['contract_id'] . ' - ' . $row['contract_signing_date'] : '<span class="text-muted">غير محدد</span>';
                            ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td><span class="client-name-link"><?= htmlspecialchars($row['project_name']); ?></span></td>
                                    <td><?= $contract_display; ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                                    <td><span class="status-active"><?= number_format($row['total_hours'], 2); ?> ساعة</span></td>
                                </tr>
                            <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

            <!-- jQuery (يجب أن يكون أولاً) -->
            <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
            <!-- Bootstrap JS -->
            <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
            <!-- DataTables JS -->
            <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
            <script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
            <script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
            <script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
            <script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
            <script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
    </div>


    <script>
        (function () {
            $(document).ready(function () {
                $('#projectsTable table').DataTable({
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: [
                        { extend: 'copy', text: 'نسخ' },
                        { extend: 'excel', text: 'تصدير Excel' },
                        { extend: 'csv', text: 'تصدير CSV' },
                        { extend: 'pdf', text: 'تصدير PDF' },
                        { extend: 'print', text: 'طباعة' }
                    ],
                    "language": {
                        "url": "/ems/assets/i18n/datatables/ar.json"
                    }
                });

                // تحميل العقود عند تغيير المشروع
                $('#projectSelect').on('change', function() {
                    const projectId = $(this).val();
                    const contractSelect = $('#contractSelect');

                    contractSelect.html('<option value="">-- الكل --</option>');

                    if (projectId) {
                        $.ajax({
                            url: 'get_mine_contracts.php',
                            type: 'GET',
                            data: { project_id: projectId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success && response.contracts && response.contracts.length > 0) {
                                    response.contracts.forEach(function(contract) {
                                        contractSelect.append(`<option value="${contract.id}">عقد #${contract.id} - ${contract.contract_signing_date}</option>`);
                                    });
                                }
                            }
                        });
                    }
                });
            });
        })();
    </script>
</body>

</html>
