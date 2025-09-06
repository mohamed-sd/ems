<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | المشاريع</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة مشروع
    </a>

    
    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" style="display:none">
        <input type="hidden" name="id" id="project_id" value="">
        <h3>إضافة / تعديل مشروع</h3>
        <div class="form-grid">
            <div>
                <label>اسم المشروع</label>
                <input type="text" name="name" id="project_name" required />
            </div>
            <div>
                <label>اسم العميل</label>
                <input type="text" name="client" id="project_client" required />
            </div>
            <div>
                <label>موقع المشروع</label>
                <input type="text" name="location" id="project_location" required />
                <input type="hidden" name="total" value="100" required />
            </div>
            <div>
                <label>حالة المشروع</label>
                <select name="status" id="project_status">
                    <option value=""> -- حدد الحالة -- </option>
                    <option value="1">نشط</option>
                    <option value="0">غير نشط</option>
                </select>
            </div>
            <button type="submit">حفظ</button>
        </div>
    </form>

    <br/><br/><br/>

    <!-- جدول المشاريع -->
    <h3>قائمة المشاريع</h3>
    <br/>
    <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">اسم المشروع</th>
                <th style="text-align: right;">عدد الاليات</th>
                <th style="text-align: right;">العقود</th>
                <th style="text-align: right;">العميل</th>
                <th style="text-align: right;">الموقع</th>
                <th style="text-align: right;">عدد الموردين</th>
                <th style="text-align: right;">تاريخ الإضافة</th>
                <th style="text-align:right">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';

            // إضافة أو تعديل مشروع
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $client = mysqli_real_escape_string($conn, $_POST['client']);
                $location = mysqli_real_escape_string($conn, $_POST['location']);
                $total = floatval($_POST['total']);
                $status = mysqli_real_escape_string($conn , $_POST['status']);
                $date = date('Y-m-d H:i:s');

                if ($id > 0) {
                    // تحديث
                    $sql = "UPDATE projects SET 
                                name='$name',
                                client='$client',
                                location='$location',
                                total='$total',
                                status='$status'
                            WHERE id=$id";
                    mysqli_query($conn, $sql);
                } else {
                    // إضافة
                    $sql = "INSERT INTO projects (name, client, location, total, status, create_at) 
                            VALUES ('$name', '$client', '$location', '$total', '$status', '$date')";
                    mysqli_query($conn, $sql);
                }

                echo "<script>window.location.href='projects.php';</script>";
                exit;
            }

            // جلب المشاريع
            $query = "SELECT `id`, `name`, `client`, `location`, `total` , `status` , `create_at`, 
                      (SELECT COUNT(*) FROM contracts WHERE contracts.project = projects.id) as 'contracts',
                      (SELECT COUNT(*) FROM operations WHERE operations.project = projects.id) as 'operations',
                      (SELECT COUNT(DISTINCT pm.suppliers) 
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE m.project = projects.id) as 'total_suppliers'
                      FROM projects ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['operations']."</td>";
                echo "<td>".$row['contracts']."</td>";
                echo "<td>".$row['client']."</td>";
                echo "<td>".$row['location']."</td>";
                echo "<td>".$row['total_suppliers']."</td>";
                echo "<td>".$row['create_at']."</td>";
                echo $row['status'] == "1" ?  "<td style='color:green'>نشط</td>" : "<td style='color:red'>غير نشط</td>" ;
                echo "<td>
                        <a href='javascript:void(0)' 
                           class='editBtn' 
                           data-id='".$row['id']."' 
                           data-name='".$row['name']."' 
                           data-client='".$row['client']."' 
                           data-location='".$row['location']."' 
                           data-status='".$row['status']."'
                           style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='projects_details.php?id=".$row['id']."' style='color: #28a745'> <i class='fa fa-eye'></i> </a>
                      </td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>

</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
(function() {
    // تشغيل DataTable
    $(document).ready(function() {
        $('#projectsTable').DataTable({
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
    });

    // اظهار/اخفاء الفورم
    const toggleProjectFormBtn = document.getElementById('toggleForm');
    const projectForm = document.getElementById('projectForm');
    toggleProjectFormBtn.addEventListener('click', function() {
        projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
        // تنظيف الحقول عند الإضافة
        $("#project_id").val("");
        $("#project_name").val("");
        $("#project_client").val("");
        $("#project_location").val("");
        $("#project_status").val("");
    });

    // عند الضغط على زر تعديل
    $(document).on("click", ".editBtn", function() {
        $("#project_id").val($(this).data("id"));
        $("#project_name").val($(this).data("name"));
        $("#project_client").val($(this).data("client"));
        $("#project_location").val($(this).data("location"));
        $("#project_status").val($(this).data("status"));

        $("#projectForm").show();
        $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });
})();
</script>

</body>
</html>
