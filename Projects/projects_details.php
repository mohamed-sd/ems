<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تفاصيل المشروع</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <!-- <h2>تفاصيل المشروع</h2> -->

    <a href="#" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة آلية
    </a>
    <a href="../Contracts/contracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> العقودات
    </a>

    <h3> تفاصير المشروع : </h3>
    <br/>
    <table class="table">
    <thead>
    <tr> 
    <?php
    include '../config.php';

    $project = $_GET['id'];

        $suppliers = mysqli_query( $conn , "SELECT COUNT(DISTINCT pm.suppliers) AS total_suppliers
FROM equipments pm
JOIN operations m ON pm.id = m.equipment
WHERE m.project =  $project;");



$rowsuppliers = mysqli_fetch_assoc($suppliers);
$total_suppliers = $rowsuppliers['total_suppliers'];


     


    $select = mysqli_query( $conn , "SELECT * FROM `projects` WHERE `id` = $project");
    while ($row = mysqli_fetch_array($select)) {
    ?>
    <tr class="o"> 
        <th> اسم المشروع </th>
        <th><?php echo $row['name']; ?></th>
    </tr>
    <tr class="t">
        <th> اسم العميل </th>
        <th><?php echo $row['client']; ?></th>
    </tr>
    <tr class="o">
        <th> موقع المشروع </th>
        <th><?php echo $row['location']; ?></th>
    </tr>
    <tr class="t">
        <th>  عدد الموردين </th>
        <th><?php echo $total_suppliers; ?></th>
    </tr>
    <tr class="t">
        <th> اجمالي الساعات </th>
        <th><?php echo $row['total']; ?></th>
    </tr>
    <?php
    } // end while loop
    ?>
    </thead>
    </table>

    <br/> <br/> <br/>

    <!-- جدول المشاريع -->
    <h3> الآليات </h3>
    <br/>
    <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">المورد</th>
                <th style="text-align: right;">النوع</th>
                 <th style="text-align: right;">اسم الالية</th>

                <!-- <th style="text-align: right;">إجراءات</th> -->
            </tr>
        </thead>
        <tbody>
            <?php
           
            
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
            $query = "SELECT m.id,m.suppliers,m.type,m.code
FROM equipments m
JOIN operations pm ON m.id = pm.equipment
WHERE pm.project = $project";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['suppliers']."</td>";
                echo "<td>".$row['type']."</td>";
                echo "<td>".$row['code']."</td>";
        
                // echo "<td>
                //         <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> 
                //       </td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>

    <br/>
    <h3> العقود </h3>
    <br/>
    <table id="projectsTable1" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">تاريخ البداية</th>
                <th style="text-align: right;"> تاريخ النهاية </th>
                <th style="text-align: right;">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            $query = "SELECT * FROM `contracts` where project LIKE '$project' ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['start']."</td>";
                echo "<td>".$row['end']."</td>";
                echo "<td>".$row['status']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href=''> عرض </a>
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

<script>
(function() {
    // تشغيل DataTable بالعربية
    $(document).ready(function() {
        $('#projectsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });
    $(document).ready(function() {
        $('#projectsTable1').DataTable({
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
