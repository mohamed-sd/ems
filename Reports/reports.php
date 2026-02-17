<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
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
    <title>إيكوبيشن | التقارير</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>


<body>

    <?php
    include('../insidebar.php');
    ?>

    <div class="main">

        <div class="bg-light">

            <div class="container py-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body">
                        <h2 class="text-center mb-4 text-primary">
                            <i class="fa-solid fa-chart-line"></i> التقارير
                        </h2>
                        <hr class="mb-4">
                        <!-- أزرار التنقل -->
                        <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                            <?php // صلاحيات مدير الموقع === 5
                            if ($_SESSION['user']['role'] == "5") { ?>
                                <a href="deliy.php" class="btn btn-primary"><i class="fa fa-clock"></i> ساعات اليوم</a>
                                <a href="deriver.php" class="btn btn-info"><i class="fa fa-clock"></i> ساعات السائق</a>
                                <a href="timesheetdeliy.php" class="btn btn-success"><i class="fa fa-clock"></i> ساعات العمل
                                    اليومية</a>
                            <?php } ?>
                            <?php // صلاحيات مدير المشغلين === 3
                            if ($_SESSION['user']['role'] == "3") { ?>
                                <a href="deriver.php" class="btn btn-info"><i class="fa fa-clock"></i> ساعات السائق</a>
                            <?php } ?>
                            <?php // صلاحيات مدير الموردين === 2
                            if ($_SESSION['user']['role'] == "2") { ?>
                                <a href="timesheetdeliy.php" class="btn btn-success"><i class="fa fa-clock"></i> ساعات العمل
                                    اليومية</a>
                            <?php } ?>
                            <?php // صلاحيات مدير الاسطول === 4
                            if ($_SESSION['user']['role'] == "4") { ?>
                                <a href="deliy.php" class="btn btn-primary"><i class="fa fa-clock"></i> ساعات اليوم</a>
                            <?php } ?>
                            <?php // صلاحيات مدير المشاريع === 1
                            if ($_SESSION['user']['role'] == "1") { ?>
                                <a href="contract_report.php" class="btn btn-warning"><i class="fa fa-file-contract"></i>
                                    العقد</a>
                                <a href="contractall.php" class="btn btn-danger"><i class="fa fa-chart-pie"></i> إحصائيات
                                    العقد</a>
                                <a href="driverAndsupplerscontract.php" class="btn btn-dark"><i class="fa fa-users"></i>
                                    إحصائيات العقود</a>
                            <?php } ?>
                        </div>

                        <!-- الفلترة -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-truck-loading"></i> المورد</label>
                                <select name="supplier" class="form-select">
                                    <option value="">-- الكل --</option>
                                    <?php
                                    $sup = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE status = '1' ORDER BY name");
                                    while ($row = mysqli_fetch_assoc($sup)) {
                                        $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-project-diagram"></i> المشروع</label>
                                <select name="project" id="projectSelect" class="form-select">
                                    <option value="">-- الكل --</option>
                                    <?php
                                    $prj = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");
                                    while ($row = mysqli_fetch_assoc($prj)) {
                                        $selected = ($project_filter == $row['id']) ? "selected" : "";
                                        echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['project_code']})</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-mountain"></i> المنجم</label>
                                <select name="mine" id="mineSelect" class="form-select">
                                    <option value="">-- الكل --</option>
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
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-file-contract"></i> العقد</label>
                                <select name="contract" id="contractSelect" class="form-select">
                                    <option value="">-- الكل --</option>
                                    <?php
                                    if (!empty($mine_filter)) {
                                        $contracts = mysqli_query($conn, "SELECT id, contract_signing_date FROM contracts WHERE mine_id = '$mine_filter' AND status = 1 ORDER BY contract_signing_date DESC");
                                        while ($row = mysqli_fetch_assoc($contracts)) {
                                            $selected = ($contract_filter == $row['id']) ? "selected" : "";
                                            echo "<option value='{$row['id']}' $selected>عقد #{$row['id']} - {$row['contract_signing_date']}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-12 d-flex justify-content-center gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-filter"></i> تطبيق الفلتر
                                </button>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fa fa-redo"></i> إعادة تعيين
                                </a>
                            </div>
                        </form>

                        <div class="card-body">

                            <!-- جدول -->
                            <div id="projectsTable" class="display">
                                <table class="table table-bordered table-hover text-center align-middle">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> #</th>
                                            <th><i class="fas fa-project-diagram"></i> المشروع</th>
                                            <th><i class="fas fa-mountain"></i> المنجم</th>
                                            <th><i class="fas fa-file-contract"></i> العقد</th>
                                            <th><i class="fas fa-truck-loading"></i> المورد</th>
                                            <th><i class="fas fa-clock"></i> إجمالي ساعات التشغيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;
                                        while ($row = mysqli_fetch_assoc($result)) { 
                                            $mine_display = !empty($row['mine_name']) ? $row['mine_name'] . ' (' . $row['mine_code'] . ')' : '<span class="text-muted">غير محدد</span>';
                                            $contract_display = !empty($row['contract_id']) ? 'عقد #' . $row['contract_id'] . ' - ' . $row['contract_signing_date'] : '<span class="text-muted">غير محدد</span>';
                                        ?>
                                            <tr>
                                                <td><strong><?= $i++; ?></strong></td>
                                                <td><strong class="text-primary"><?= htmlspecialchars($row['project_name']); ?></strong></td>
                                                <td><?= $mine_display; ?></td>
                                                <td><?= $contract_display; ?></td>
                                                <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                                                <td><span class="badge bg-success fs-6"><?= number_format($row['total_hours'], 2); ?> ساعة</span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- jQuery (يجب أن يكون أولاً) -->
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
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                    }
                });

                // تحميل المناجم عند تغيير المشروع
                $('#projectSelect').on('change', function() {
                    const projectId = $(this).val();
                    const mineSelect = $('#mineSelect');
                    const contractSelect = $('#contractSelect');
                    
                    console.log('تم اختيار المشروع:', projectId);
                    
                    // إعادة تعيين قائمة المناجم والعقود
                    mineSelect.html('<option value="">-- الكل --</option>');
                    contractSelect.html('<option value="">-- الكل --</option>');
                    
                    if (projectId) {
                        console.log('جاري تحميل المناجم...');
                        $.ajax({
                            url: 'get_project_mines.php',
                            type: 'GET',
                            data: { project_id: projectId },
                            dataType: 'json',
                            success: function(response) {
                                console.log('استجابة المناجم:', response);
                                if (response.success && response.mines && response.mines.length > 0) {
                                    console.log('عدد المناجم:', response.mines.length);
                                    response.mines.forEach(function(mine) {
                                        mineSelect.append(`<option value="${mine.id}">${mine.mine_name} (${mine.mine_code})</option>`);
                                    });
                                } else {
                                    console.log('لا توجد مناجم لهذا المشروع');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('خطأ في تحميل المناجم:', error);
                                console.log('حالة الطلب:', status);
                                console.log('Response:', xhr.responseText);
                            }
                        });
                    }
                });

                // تحميل العقود عند تغيير المنجم
                $('#mineSelect').on('change', function() {
                    const mineId = $(this).val();
                    const contractSelect = $('#contractSelect');
                    
                    console.log('تم اختيار المنجم:', mineId);
                    
                    contractSelect.html('<option value="">-- الكل --</option>');
                    
                    if (mineId) {
                        console.log('جاري تحميل العقود...');
                        $.ajax({
                            url: 'get_mine_contracts.php',
                            type: 'GET',
                            data: { mine_id: mineId },
                            dataType: 'json',
                            success: function(response) {
                                console.log('استجابة العقود:', response);
                                if (response.success && response.contracts && response.contracts.length > 0) {
                                    console.log('عدد العقود:', response.contracts.length);
                                    response.contracts.forEach(function(contract) {
                                        contractSelect.append(`<option value="${contract.id}">عقد #${contract.id} - ${contract.contract_signing_date}</option>`);
                                    });
                                } else {
                                    console.log('لا توجد عقود لهذا المنجم');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('خطأ في تحميل العقود:', error);
                                console.log('حالة الطلب:', status);
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