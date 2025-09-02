<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | ساعات العمل </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
     <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>


<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة ساعات عمل
    </a>

    <!-- فورم إضافة ساعات عمل -->
    <form id="timesheetForm" action="" method="post" style="display:none; margin-top:20px;">
        <!-- اختيار المشغل (من جدول التشغيل) -->
        <label>المشغل</label>
        <select name="operator" required>
            <option value="">-- اختر المشغل --</option>
            <?php
            include '../config.php';
            $op_res = mysqli_query($conn, "SELECT o.id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN projects p ON o.project = p.id");
            while($op = mysqli_fetch_assoc($op_res)){
                echo "<option value='".$op['id']."'>".$op['eq_code']." - ".$op['eq_name']." | ".$op['project_name']."</option>";
            }
            ?>
        </select>

        <!-- اختيار السائق -->
<label>السائق</label>
<select name="driver" required>
    <option value="">-- اختر السائق --</option>
    <?php
    $dr_res = mysqli_query($conn, "SELECT id, name FROM drivers");
    while($dr = mysqli_fetch_assoc($dr_res)){
        echo "<option value='".$dr['id']."'>".$dr['name']."</option>";
    }
    ?>
</select>
        <select name="shift">
          <option value=""> -- اختار الوردية -- </option>
          <option value="D"> صباحية </option>
          <option value="N"> مسائية </option>
        </select>
        <input type="number" step="0.01" name="work_hours" placeholder="ساعات العمل" required />
        <input type="number" step="0.01" name="damage_hours" placeholder="ساعات التوقف/الأعطال" />
        <input type="date" name="date" required />
        <input type="text" name="movies" placeholder="ملاحظات/أفلام" />
        <input type="text" name="jackhamr" placeholder="جاك هامر" />
        <br/>
        <button type="submit">حفظ الساعات</button>
    </form>

    <br/><br/><br/>

    <!-- جدول ساعات العمل -->
    <h3>قائمة ساعات العمل</h3>
    <br/>
    <table id="timesheetTable" class="display" style="width:100%; margin-top:20px;">
        <thead>
            <tr>
                <th>#</th>
                <th>المعدة</th>
                <th>المشروع</th>
                <th>المشغل</th>
                <th>السائق</th>
                <th>الوردية</th>
                <th>ساعات العمل</th>
                <th>ساعات التوقف</th>
                <th>التاريخ</th>
                <th>ملاحظات</th>
                <th>جاك هامر</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // إضافة سجل جديد
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['operator'])) {
                $operator = intval($_POST['operator']);
                $driver = mysqli_real_escape_string($conn, $_POST['driver']);
                $shift = mysqli_real_escape_string($conn, $_POST['shift']);
                $work_hours = floatval($_POST['work_hours']);
                $damage_hours = floatval($_POST['damage_hours']);
                $date = mysqli_real_escape_string($conn, $_POST['date']);
                $movies = mysqli_real_escape_string($conn, $_POST['movies']);
                $jackhamr = mysqli_real_escape_string($conn, $_POST['jackhamr']);

                mysqli_query($conn, "INSERT INTO timesheet (operator, driver, shift, work_hours, damage_hours, date, movies, jackhamr)
                                     VALUES ('$operator', '$driver', '$shift', '$work_hours', '$damage_hours', '$date', '$movies', '$jackhamr')");
            }

            // عرض البيانات
            $query = "SELECT t.id, t.shift, t.work_hours, t.damage_hours, t.date, t.movies, t.jackhamr,
                 e.code AS eq_code, e.name AS eq_name,
                 p.name AS project_name,
                 o.id AS operation_id,
                 d.name AS driver_name
          FROM timesheet t
          JOIN operations o ON t.operator = o.id
          JOIN equipments e ON o.equipment = e.id
          JOIN projects p ON o.project = p.id
          JOIN drivers d ON t.driver = d.id
          ORDER BY t.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['eq_code']." - ".$row['eq_name']."</td>";
                echo "<td>".$row['project_name']."</td>";
                echo "<td>مشغل #".$row['operation_id']."</td>";
                echo "<td>".$row['driver_name']."</td>";
                echo $row['shift'] == "D" ? "<td> صباحية </td>" : "<td> مسائية </td>";
                echo "<td>".$row['work_hours']."</td>";
                echo "<td>".$row['damage_hours']."</td>";
                echo "<td>".$row['date']."</td>";
                echo "<td>".$row['movies']."</td>";
                echo "<td>".$row['jackhamr']."</td>";
                echo "<td>
                        <a href='edit_timesheet.php?id=".$row['id']."'>تعديل</a> | 
                        <a href='delete_timesheet.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href='timesheet_details.php?id=".$row['id']."'> عرض </a>
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
    $(document).ready(function() {
        $('#timesheetTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    const toggleFormBtn = document.getElementById('toggleForm');
    const form = document.getElementById('timesheetForm');

    toggleFormBtn.addEventListener('click', function() {
        form.style.display = form.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>