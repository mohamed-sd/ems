<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>إيكوبيشن | العقود</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <!-- <h2>العقود</h2> -->

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة عقد
    </a>

    <!-- فورم إضافة عقد -->
    <form id="contractForm" action="" method="post" style="display:none;">
        <input type="text" name="project" placeholder="اسم المشروع" value="<?php echo $_GET['id'] ?>" required />
        <input type="date" name="start" placeholder="تاريخ البداية" required />
        <input type="date" name="end" placeholder="تاريخ النهاية" required />
        <select name="status" required>
            <option value="">حالة العقد</option>
            <option value="نشط">نشط</option>
            <option value="مغلق">مغلق</option>
            <option value="مؤجل">مؤجل</option>
        </select>
        <br/>
        <button type="submit">حفظ العقد</button>
    </form>

    <br/><br/><br/>

    <!-- جدول العقود -->
    <h3>قائمة العقود</h3>
    <br/>
    <table id="contractsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">المشروع</th>
                <th style="text-align: right;">تاريخ البداية</th>
                <th style="text-align: right;">تاريخ النهاية</th>
                <th style="text-align: right;">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة عقد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project'])) {
                // $project = mysqli_real_escape_string($conn, $_POST['project']);
                $project = $_GET['id'];
                $start = mysqli_real_escape_string($conn, $_POST['start']);
                $end = mysqli_real_escape_string($conn, $_POST['end']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                mysqli_query($conn, "INSERT INTO contracts (project, start, end, status) VALUES ('$project', '$start', '$end', '1')");
            }

            // جلب العقود
            $query = "SELECT `id`, `project`, `start`, `end`, `status` FROM `contracts` ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['project']."</td>";
                echo "<td>".$row['start']."</td>";
                echo "<td>".$row['end']."</td>";
                echo "<td>".$row['status']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | 
                        <a href='contracts_details.php?id=".$row['id']."'>عرض</a>
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
        $('#contractsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    // التحكم في إظهار وإخفاء الفورم
    const toggleContractFormBtn = document.getElementById('toggleForm');
    const contractForm = document.getElementById('contractForm');

    toggleContractFormBtn.addEventListener('click', function() {
        contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>
