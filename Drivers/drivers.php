<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | المشغلين </title>
	
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

   <!--  <h2>المشاريع</h2> -->

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة سائق
    </a>

    <!-- فورم إضافة مشروع -->
    <form id="projectForm" action="" method="post">
        <input type="text" name="name" placeholder="اسم السائق" required />
        <input type="text" name="phone" placeholder="رقم الهاتف " required />
        <br/>
        <button type="submit">حفظ السائق</button>
    </form>

    <br/> <br/> <br/>

    <!-- جدول المشاريع -->
    <h3>قائمة السائقين</h3>
    <br/>
    <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">اسم السائق</th>
                <th style="text-align: right;"> الهاتف </th>
            
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة سائق جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $phone = mysqli_real_escape_string($conn, $_POST['phone']);
              
           
                mysqli_query($conn, "INSERT INTO drivers (name, phone) VALUES ('$name', '$phone')");
            }

            // جلب المشاريع
            $query = "SELECT `id`, `name`, `phone` FROM drivers ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['phone']."</td>";
             
                echo "<td>
                        <a  href='edit.php?id=".$row['id']."'   id='toggleForm' class='add'>تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")' id='toggleForm' class='add'>حذف</a> 
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