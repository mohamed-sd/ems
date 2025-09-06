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

<?php include('../insidebar.php'); ?>

<div class="main">

    <!-- <h2>تفاصيل المشروع</h2> -->


    <h3> تفاصير ساعات العمي : </h3>
    <br/>
    <table class="table">
    <thead>
<?php
include '../config.php';

$project = intval($_GET['id']);

$sql = "SELECT  * , t.id,
               d.name AS driver_name,
               e.code AS equipment_name,
               p.name AS project_name,
               t.shift,
               t.date
        FROM timesheet t
        JOIN drivers d ON t.driver = d.id
        JOIN operations o ON t.operator = o.id
        JOIN equipments e ON o.equipment = e.id
        JOIN projects p ON o.project = p.id
        WHERE t.id = $project
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
    <th> التاريخ </th>
    <th><?php echo $row['date']; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات الوردية </th>
    <th><?php echo $row['shift_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> الساعات المنفذة  </th>
    <th><?php echo $row['executed_hours']; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات الجردل </th>
    <th><?php echo $row['bucket_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> الساعات المنفذة  </th>
    <th><?php echo $row['jackhammer_hours']; ?></th>
</tr>

<tr class="t"> 
    <th> الساعات الاضافية </th>
    <th><?php echo $row['extra_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> مجموع الساعات الإضافة  </th>
    <th><?php echo $row['extra_hours_total']; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات الاستعداد (بسبب العميل() </th>
    <th><?php echo $row['standby_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> ساعات الاستعادا (اعتماد)  </th>
    <th><?php echo $row['dependence_hours']; ?></th>
</tr>
<tr class="t"> 
    <th> مجموع ساعات العمل </th>
    <th><?php echo $row['total_work_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> ملاحظات ساعات العمل  </th>
    <th><?php echo $row['work_notes']; ?></th>
</tr>
<tr class="t"> 
    <th> عطل HR </th>
    <th><?php echo $row['hr_fault']; ?></th>
</tr>
<tr class="o"> 
    <th> عطل صيانة </th>
    <th><?php echo $row['maintenance_fault']; ?></th>
</tr>
<tr class="t"> 
    <th> عطل تسويق </th>
    <th><?php echo $row['marketing_fault']; ?></th>
</tr>
<tr class="o"> 
    <th> عطل اعتماد  </th>
    <th><?php echo $row['approval_fault']; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات أعطال أخرى </th>
    <th><?php echo $row['other_fault_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> مجموع ساعات التعطل  </th>
    <th><?php echo $row['total_fault_hours']; ?></th>
</tr>
<tr class="t"> 
    <th> ملاحظات ساعات التعطل </th>
    <th><?php echo $row['fault_notes']; ?></th>
</tr>
<tr class="o"> 
    <th> عداد البداية  </th>
    <th><?php echo $row['start_hours'].":".$row['start_minutes'].":".$row['start_seconds']; ?></th>
</tr>
<tr class="t"> 
    <th> عداد النهاية </th>
    <th><?php echo $row['end_hours'].":".$row['end_minutes'].":".$row['end_seconds']; ?></th>
</tr>
<tr class="o"> 
    <th> فرق العداد  </th>
    <th><?php echo $row['counter_diff']; ?></th>
</tr>
<tr class="t"> 
    <th> نوع العطل </th>
    <th><?php echo $row['fault_type']; ?></th>
</tr>
<tr class="o"> 
    <th> قسم العطل  </th>
    <th><?php echo $row['fault_department']; ?></th>
</tr>
<tr class="t"> 
    <th> الجزء المعطل </th>
    <th><?php echo $row['fault_part']; ?></th>
</tr>
<tr class="o"> 
    <th> تفاصيل العطل  </th>
    <th><?php echo $row['fault_details']; ?></th>
</tr>
<tr class="t"> 
    <th> ملاحظات عامة </th>
    <th><?php echo $row['general_notes']; ?></th>
</tr>
<tr class="o"> 
    <th> ساعات عمل المشغل </th>
    <th><?php echo $row['operator_hours']; ?></th>
</tr>
<tr class="t"> 
    <th> ساعات استعداد الآليه </th>
    <th><?php echo $row['machine_standby_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> ساعات استعداد الجاك همر  </th>
    <th><?php echo $row['jackhammer_standby_hours']; ?></th>
</tr>

<tr class="t"> 
    <th> ساعات استعداد الجردل </th>
    <th><?php echo $row['bucket_standby_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> الساعات الاضافية للمشغل </th>
    <th><?php echo $row['extra_operator_hours']; ?></th>
</tr>
<tr class="t"> 
    <th>  ساعات استعداد المشغل </th>
    <th><?php echo $row['operator_standby_hours']; ?></th>
</tr>
<tr class="o"> 
    <th> ملاحظات المشغل  </th>
    <th><?php echo $row['operator_notes']; ?></th>
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
