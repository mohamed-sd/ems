<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | الآليات </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة معدة
    </a>

    <!-- فورم إضافة معدة -->
    <form id="equipmentForm" action="" method="post" style="display:none;">
        <?php if(isset($_GET['id'])){ ?>
        <input type="text" name="suppliers" value="<?php echo $_GET['id']; ?>" placeholder="المورد" required />
        <?php } ?>
        <input type="text" name="code" placeholder="كود المعدة" required />
        <input type="text" name="type" placeholder="نوع المعدة" required />
        <input type="text" name="name" placeholder="اسم المعدة" required />
        <select name="status" required>
            <option value="">اختر الحالة</option>
            <option value="متاحة">متاحة</option>
            <option value="مشغولة">مشغولة</option>
            <option value="صيانة">صيانة</option>
        </select>
        <br/>
        <button type="submit">حفظ المعدة</button>
    </form>

    <br/><br/><br/>

    <!-- جدول المعدات -->
    <h3>قائمة المعدات</h3>
    <br/>
    <table id="equipmentsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">المورد</th>
                <th style="text-align: right;">كود المعدة</th>
                <th style="text-align: right;">النوع</th>
                <th style="text-align: right;">الاسم</th>
                <th style="text-align: right;">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة معدة جديدة عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
                $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
                $code = mysqli_real_escape_string($conn, $_POST['code']);
                $type = mysqli_real_escape_string($conn, $_POST['type']);
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                mysqli_query($conn, "INSERT INTO equipments (suppliers, code, type, name, status) VALUES ('$suppliers', '$code', '$type', '$name', '$status')");
            }

            // جلب المعدات
            $query = "SELECT `id`, `suppliers`, `code`, `type`, `name`, `status` FROM `equipments` ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['suppliers']."</td>";
                echo "<td>".$row['code']."</td>";
                echo "<td>".$row['type']."</td>";
                echo "<td>".$row['name']."</td>";
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
        $('#equipmentsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    // التحكم في إظهار وإخفاء الفورم
    const toggleFormBtn = document.getElementById('toggleForm');
    const equipmentForm = document.getElementById('equipmentForm');

    toggleFormBtn.addEventListener('click', function() {
        equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>