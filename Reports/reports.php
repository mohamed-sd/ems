<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

$supplier_filter = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$project_filter = isset($_GET['project']) ? $_GET['project'] : '';

$sql = "
SELECT 
    s.name AS supplier_name,
    p.name AS project_name,
    SUM(t.executed_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id   
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o.project_id = p.id
WHERE 1=1
";

if (!empty($supplier_filter)) {
    $sql .= " AND s.id = '$supplier_filter' ";
}
if (!empty($project_filter)) {
    $sql .= " AND p.id = '$project_filter' ";
}
$sql .= " GROUP BY s.name, p.name ";
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
                            <div class="col-md-5">
                                <label class="form-label">المورد</label>
                                <select name="supplier" class="form-select">
                                    <option value="">-- اختر المورد --</option>
                                    <?php
                                    $sup = mysqli_query($conn, "SELECT id, name FROM suppliers where status = '1' ");
                                    while ($row = mysqli_fetch_assoc($sup)) {
                                        $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">المشروع</label>
                                <select name="project" class="form-select">
                                    <option value="">-- اختر المشروع --</option>
                                    <?php
                                    $prj = mysqli_query($conn, "SELECT id, name FROM project where status = '1' ");
                                    while ($row = mysqli_fetch_assoc($prj)) {
                                        $selected = ($project_filter == $row['id']) ? "selected" : "";
                                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fa fa-filter"></i> عرض
                                </button>
                            </div>
                        </form>

                        <div class="card-body">

                            <!-- جدول -->
                            <div id="projectsTable" class="display">
                                <table class="table table-bordered table-hover text-center align-middle">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>المورد</th>
                                            <th>المشروع</th>
                                            <th>إجمالي ساعات التشغيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                            <tr>
                                                <td><?= $row['supplier_name']; ?></td>
                                                <td><?= $row['project_name']; ?></td>
                                                <td><span class="badge bg-success fs-6"><?= $row['total_hours']; ?></span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Bootstrap JS -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            <!-- DataTables JS -->
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>



        </div>

    </div>


    <script>
        (function () {
            $(document).ready(function () {
                $('#projectsTable').DataTable({
                    responsive: true,
                    dom: 'Bfrtip', // Buttons + Search + Pagination
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
            });
        })();
    </script>
</body>

</html>