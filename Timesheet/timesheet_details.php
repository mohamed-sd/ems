<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تفاصيل ساعات العمل</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
 <!--    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"> -->

    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <!-- <h2>تفاصيل المشروع</h2> -->


    <h3> تفاصير ساعات العمي : </h3>
    <br/>
    <table class="table">
    <thead>
<?php
include '../config.php';

$project = intval($_GET['id']);

$sql = "SELECT t.id,
               d.name AS driver_name,
               e.name AS equipment_name,
               p.name AS project_name,
               t.shift,
               t.work_hours,
               t.damage_hours,
               t.date,
               t.movies,
               t.jackhamr
        FROM timesheet t
        JOIN drivers d ON t.driver = d.id
        JOIN operations o ON t.operator = o.id
        JOIN equipments e ON o.equipment = e.id
        JOIN projects p ON o.project = p.id
        WHERE p.id = $project
        ORDER BY t.date DESC";

$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    // echo "<tr>";
    // echo "<td>".$row['driver_name']."</td>";
    // echo "<td>".$row['equipment_name']."</td>";
    // echo "<td>".$row['project_name']."</td>";
    // echo "<td>".$row['shift']."</td>";
    // echo "<td>".$row['work_hours']."</td>";
    // echo "<td>".$row['damage_hours']."</td>";
    // echo "<td>".$row['date']."</td>";
    // echo "<td>".$row['movies']."</td>";
    // echo "<td>".$row['jackhamr']."</td>";
    // echo "</tr>";
?>

<tr class="o"> 
    <th> اسم المشغل </th>
    <th><?php echo $row['driver_name']; ?></th>
</tr>
<tr class="t"> 
    <th> اسم المعدة </th>
    <th><?php echo $row['equipment_name']; ?></th>
</tr>
<tr class="o"> 
    <th> الوردية </th>
    <th><?php echo $row['equipment_name'] == "D" ? "صباح" : "مساء" ; ?></th>
</tr>
<tr class="t"> 
    <th> اسم المشروع </th>
    <th><?php echo $row['project_name']; ?></th>
</tr>
<tr class="o"> 
    <th> ساعات العمل </th>
    <th><?php echo $row['work_hours'] ; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات التعطل </th>
    <th><?php echo $row['damage_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> التاريخ </th>
    <th><?php echo $row['date']; ?></th>
</tr>
<tr class="t"> 
    <th> عدد النقلات ( قلام ) </th>
    <th><?php echo $row['movies']; ?></th>
</tr>
<tr class="o"> 
    <th> ساعات الجاكهمر ( حفار )  </th>
    <th><?php echo $row['jackhamr']; ?></th>
</tr>

<?php } ?>
</thead>
</tbody>
</table>

    <br/> <br/> <br/>


</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<!-- <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> -->

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
