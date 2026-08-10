<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$_current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$_is_super_admin = ($_current_role === '-1');
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$rep_gate = $_is_super_admin ? ems_tenant_db()->forAllTenants('reports super') : ems_tenant_db();

// أعداد صحيحة فقط — يمنع حقن SQL في الفلاتر (المعرّفات أرقام دائماً).
$supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$project_filter  = isset($_GET['project'])  ? intval($_GET['project'])  : 0;
$contract_filter = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

// الاستعلام التجميعي عبر البوابة: كل الجداول المضمومة INNER مستأجرةٌ تُعلَن نطاقًا،
// وcontracts (LEFT) إثراءً. (الأصل بلا تنطيق شركةٍ = تسريب — البوابة تعزله الآن.)
$rep_filter = '';
$rep_params = array();
if ($supplier_filter > 0) { $rep_filter .= " AND s.id = ? "; $rep_params[] = $supplier_filter; }
if ($project_filter > 0)  { $rep_filter .= " AND p.id = ? "; $rep_params[] = $project_filter; }
if ($contract_filter > 0) { $rep_filter .= " AND c.id = ? "; $rep_params[] = $contract_filter; }
$result = array();
try {
    $result = $rep_gate->scopedQuery(
        array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments', 's' => 'suppliers', 'p' => 'project'),
              'enrich' => array('c' => 'contracts')),
        "SELECT
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
WHERE t.status = 1 AND o.status = 1$rep_filter AND {TENANT_SCOPE}
 GROUP BY s.id, s.name, p.id, p.name, c.id, c.contract_signing_date
 ORDER BY p.name, s.name", $rep_params);
} catch (\Throwable $t) { error_log('Reports/reports.php main: ' . $t->getMessage()); }

$page_title = "إيكوبيشن | مركز التقارير";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
    <div class="main ems-unified-page-shell reports-main">
        <?php
        // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
        $header_title   = 'مركز التقارير';
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

        <form method="GET" class="filter">
            <div class="filter-title">
                <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
                فلاتر التقارير
            </div>
            <div class="filter-body">
                <div class="filter-field">
                    <label for="emsf_1370_5561b"><i class="fas fa-truck-loading"></i> المورد</label>
                    <select name="supplier" class="form-control" id="emsf_1370_5561b">
                        <option value="">-- الكل --</option>
                        <?php
                        $sup = array();
                        try {
                            $sup = $rep_gate->scopedQuery(array('scope' => array('suppliers' => 'suppliers')),
                                "SELECT id, name FROM suppliers WHERE status = '1' AND {TENANT_SCOPE} ORDER BY name");
                        } catch (\Throwable $t) { error_log('Reports/reports.php suppliers: ' . $t->getMessage()); }
                        foreach ($sup as $row) {
                            $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="projectSelect"><i class="fas fa-project-diagram"></i> المشروع</label>
                    <select name="project" id="projectSelect" class="form-control">
                        <option value="">-- الكل --</option>
                        <?php
                        $prj = array();
                        try {
                            $prj = $rep_gate->scopedQuery(array('scope' => array('project' => 'project')),
                                "SELECT id, name, project_code FROM project WHERE status = '1' AND {TENANT_SCOPE} ORDER BY name");
                        } catch (\Throwable $t) { error_log('Reports/reports.php projects: ' . $t->getMessage()); }
                        foreach ($prj as $row) {
                            $selected = ($project_filter == $row['id']) ? "selected" : "";
                            echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['project_code']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="contractSelect"><i class="fas fa-file-contract"></i> العقد</label>
                    <select name="contract" id="contractSelect" class="form-control">
                        <option value="">-- الكل --</option>
                        <?php
                        if ($project_filter > 0) {
                            $contracts = array();
                            try {
                                $contracts = $rep_gate->scopedQuery(array('scope' => array('contracts' => 'contracts')),
                                    "SELECT id, contract_signing_date FROM contracts WHERE project_id = ? AND status = 1 AND {TENANT_SCOPE} ORDER BY contract_signing_date DESC",
                                    array($project_filter));
                            } catch (\Throwable $t) { error_log('Reports/reports.php contracts: ' . $t->getMessage()); }
                            foreach ($contracts as $row) {
                                $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['contract_signing_date']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                    <a href="reports.php" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></a>
                </div>
            </div>
        </form>

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
                            foreach ($result as $row) {
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
