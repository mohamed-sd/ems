<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | التشغيل </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>
<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة تشغيل
    </a>

    <!-- فورم إضافة تشغيل -->
    <form id="operationForm" action="" method="post" style="display:none; margin-top:20px;">
        <!-- المعدة -->
        <select name="equipment" required>
            <option value="">-- اختر المعدة --</option>
            <?php
            include '../config.php';
            $eq_res = mysqli_query($conn, "SELECT id, code, name FROM equipments");
            while($eq = mysqli_fetch_assoc($eq_res)){
                echo "<option value='".$eq['id']."'>".$eq['code']." - ".$eq['name']."</option>";
            }
            ?>
        </select>

        <!-- المشروع -->
        <select name="project" required>
            <option value="">-- اختر المشروع --</option>
            <?php
            $pr_res = mysqli_query($conn, "SELECT id, name FROM projects");
            while($pr = mysqli_fetch_assoc($pr_res)){
                echo "<option value='".$pr['id']."'>".$pr['name']."</option>";
            }
            ?>
        </select>

        <input type="datetime-local" name="start" required placeholder="تاريخ البداية" />
        <input type="datetime-local" name="end" required placeholder="تاريخ النهاية" />
        <input type="number" step="0.01" name="hours" placeholder="عدد الساعات" required />
        <select name="status" required>
            <option value="active">نشط</option>
            <option value="done">منتهي</option>
        </select>
        <br/>
        <button type="submit">حفظ التشغيل</button>
    </form>

    <br/> <br/> <br/>

    <!-- جدول التشغيل -->
    <h3>قائمة التشغيل</h3>
    <br/>
    <table id="operationsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:right;">المعدة</th>
                <th style="text-align:right;">المشروع</th>
                <th style="text-align:right;">تاريخ البداية</th>
                <th style="text-align:right;">تاريخ النهاية</th>
                <th style="text-align:right;">عدد الساعات</th>
                <th style="text-align:right;">الحالة</th>
                <th style="text-align:right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // إضافة تشغيل جديد
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['equipment'])) {
                $equipment = intval($_POST['equipment']);
                $project   = intval($_POST['project']);
                $start     = mysqli_real_escape_string($conn, $_POST['start']);
                $end       = mysqli_real_escape_string($conn, $_POST['end']);
                $hours     = floatval($_POST['hours']);
                $status    = mysqli_real_escape_string($conn, $_POST['status']);

                mysqli_query($conn, "INSERT INTO operations (equipment, project, start, end, hours, status) 
                                     VALUES ('$equipment', '$project', '$start', '$end', '$hours', '$status')");
            }

            // جلب بيانات التشغيل
            $query = "SELECT o.id, o.start, o.end, o.hours, o.status, 
                             e.code AS equipment_code, e.name AS equipment_name,
                             p.name AS project_name
                      FROM operations o
                      LEFT JOIN equipments e ON o.equipment = e.id
                      LEFT JOIN projects p ON o.project = p.id
                      ORDER BY o.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['equipment_code']." - ".$row['equipment_name']."</td>";
                echo "<td>".$row['project_name']."</td>";
                echo "<td>".$row['start']."</td>";
                echo "<td>".$row['end']."</td>";
                echo "<td>".$row['hours']."</td>";
                echo "<td>".$row['status']."</td>";
                echo "<td>
                        <a href='edit_operation.php?id=".$row['id']."'>تعديل</a> | 
                        <a href='delete_operation.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a>
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
        $('#operationsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    // التحكم في إظهار وإخفاء الفورم
    const toggleFormBtn = document.getElementById('toggleForm');
    const form = document.getElementById('operationForm');

    toggleFormBtn.addEventListener('click', function() {
        form.style.display = form.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>