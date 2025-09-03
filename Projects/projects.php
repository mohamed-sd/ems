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

   <!--  <h2>المشاريع</h2> -->

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة مشروع
    </a>

    <!-- فورم إضافة مشروع -->
    <form id="projectForm" action="" method="post">
        <h3>إضافة مشروع جديد</h3>
    <div class="form-grid">
    <div>
        <label>اسم المشروع</label>
        <input type="text" name="name" placeholder="اسم المشروع" required />
    </div>
    <div>
        <label>اسم العميل</label>
        <input type="text" name="client" placeholder="اسم العميل" required />
    </div>
    <div>
        <label>موقع المشروع</label>
        <input type="text" name="location" placeholder="موقع المشروع" required />
    </div>
    <div>
        <label>القيمة الإجمالية</label>
        <input type="number" name="total" placeholder="القيمة الإجمالية" required />
    </div>
    <button type="submit">حفظ المشروع</button>
    </div>


    </form>


    

    <br/> <br/> <br/>

    <!-- جدول المشاريع -->
    <h3>قائمة المشاريع</h3>
    <br/>
    <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">اسم المشروع</th>
                 <th style="text-align: right;"> عدد الاليات </th>

                <th style="text-align: right;"> العقود </th>
                <th style="text-align: right;">العميل</th>
                <th style="text-align: right;">الموقع</th>
                <th style="text-align: right;">القيمة الإجمالية</th>
                 <th style="text-align: right;"> عدد الموردين</th>

                <th style="text-align: right;">تاريخ الإضافة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة مشروع جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $client = mysqli_real_escape_string($conn, $_POST['client']);
                $location = mysqli_real_escape_string($conn, $_POST['location']);
                $total = floatval($_POST['total']);
                $date = date('Y-m-d H:i:s');
                mysqli_query($conn, "INSERT INTO projects (name, client, location, total, create_at) VALUES ('$name', '$client', '$location', '$total', '$date')");
            }

            // جلب المشاريع
            $query = "SELECT `id`, `name`, `client`, `location`, `total`, `create_at`, (SELECT COUNT(*) FROM contracts WHERE contracts.project = projects.id) as 'contracts' , (SELECT COUNT(*) FROM operations WHERE operations.project = projects.id) as 'operations',(SELECT COUNT(DISTINCT pm.suppliers) AS total_suppliers
FROM equipments pm
JOIN operations m ON pm.id = m.equipment
WHERE m.project =   projects.id) as 'total_suppliers'   FROM projects ORDER BY id DESC";
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
                echo "<td>".$row['total']."</td>";
                echo "<td>".$row['total_suppliers']."</td>";

                echo "<td>".$row['create_at']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."' >تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href='projects_details.php?id=".$row['id']."'> عرض </a>
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
    // تشغيل DataTable بالعربية
    $(document).ready(function() {
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

    // التحكم في إظهار وإخفاء الفورم
    const toggleProjectFormBtn = document.getElementById('toggleForm');
    const projectForm = document.getElementById('projectForm');

    toggleProjectFormBtn.addEventListener('click', function() {
        projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>
