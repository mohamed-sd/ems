<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
?>
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
      <!-- Bootstrab 5 -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <!-- <h2>تفاصيل المشروع</h2> -->


    <h3> تفاصيل ساعات العميل : </h3>
    <br/>

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
?>

<div class="report">
    <div class="row">
        <div class="col-lg-2 col-5">اسم المشغل </div>
        <div class="col-lg-4 col-7"><?php echo $row['driver_name']; ?></div>
        <div class="col-lg-2 col-5">اسم المعدة </div>
        <div class="col-lg-4 col-7"><?php echo $row['equipment_name']; ?></div>
        <div class="col-lg-2 col-5"> الوردية </div>
        <div class="col-lg-4 col-7"><?php echo $row['equipment_name'] == "D" ? "صباح" : "مساء" ; ?></div>
        <div class="col-lg-2 col-5">اسم المشروع </div>
        <div class="col-lg-4 col-7"><?php echo $row['project_name']; ?></div>
        <div class="col-lg-2 col-5"> التاريخ </div>
        <div class="col-lg-4 col-7"><?php echo $row['date']; ?></div>
        <div class="col-lg-2 col-5"> ساعات الوردية </div>
        <div class="col-lg-4 col-7"><?php echo $row['shift_hours']; ?></div>
        <div class="col-lg-2 col-5"> الساعات المنفذة </div>
        <div class="col-lg-4 col-7"><?php echo $row['executed_hours']; ?></div>
        <div class="col-lg-2 col-5"> ساعات الجردل </div>
        <div class="col-lg-4 col-7"><?php echo $row['bucket_hours']; ?></div>
        <div class="col-lg-2 col-5"> ساعات الجردل </div>
        <div class="col-lg-4 col-7"><?php echo $row['bucket_hours']; ?></div>
        <div class="col-lg-2 col-5"> ساعات الجاكمر </div>
        <div class="col-lg-4 col-7"><?php echo $row['jackhammer_hours']; ?></div>
        <div class="col-lg-2 col-5"> الساعات الاضافية </div>
        <div class="col-lg-4 col-7"><?php echo $row['extra_hours']; ?></div>
        <div class="col-lg-2 col-5"> مجموع الساعات الإضافية </div>
        <div class="col-lg-4 col-7"><?php echo $row['extra_hours_total']; ?></div>
        <div class="col-lg-2 col-5">  ساعات الاستعداد (العميل) </div>
        <div class="col-lg-4 col-7"><?php echo $row['standby_hours']; ?></div>
        <div class="col-lg-2 col-5">  ساعات الاستعادا (اعتماد) </div>
        <div class="col-lg-4 col-7"><?php echo $row['dependence_hours']; ?></div>
        <div class="col-lg-2 col-5">  مجموع ساعات العمل </div>
        <div class="col-lg-4 col-7"><?php echo $row['total_work_hours']; ?></div>
        <div class="col-lg-2 col-5">  مجموع ساعات العمل </div>
        <div class="col-lg-4 col-7"><?php echo $row['total_work_hours']; ?></div>
        <div class="col-lg-2 col-5">  ملاحظات ساعات العمل </div>
        <div class="col-lg-4 col-7"><?php echo $row['work_notes']; ?></div>
        <div class="col-lg-2 col-5">  عطل HR</div>
        <div class="col-lg-4 col-7"><?php echo $row['hr_fault']; ?></div>
        <div class="col-lg-2 col-5">  عطل صيانة  </div>
        <div class="col-lg-4 col-7"><?php echo $row['maintenance_fault']; ?></div>
        <div class="col-lg-2 col-5">  عطل تسويق   </div>
        <div class="col-lg-4 col-7"><?php echo $row['marketing_fault']; ?></div>
        <div class="col-lg-2 col-5">  عطل اعتماد   </div>
        <div class="col-lg-4 col-7"><?php echo $row['approval_fault']; ?></div>
        <div class="col-lg-2 col-5">  ساعات أعطال أخرى   </div>
        <div class="col-lg-4 col-7"><?php echo $row['other_fault_hours']; ?></div>
        <div class="col-lg-2 col-5">  مجموع ساعات التعطل   </div>
        <div class="col-lg-4 col-7"><?php echo $row['total_fault_hours']; ?></div>
        <div class="col-lg-2 col-5">  ملاحظات ساعات التعطل   </div>
        <div class="col-lg-4 col-7"><?php echo $row['fault_notes']; ?></div>
        <div class="col-lg-2 col-5">  عداد البداية   </div>
        <div class="col-lg-4 col-7"><?php echo $row['start_hours'].":".$row['start_minutes'].":".$row['start_seconds']; ?></div>
        <div class="col-lg-2 col-5"> عداد النهاية </div>
        <div class="col-lg-4 col-7"><?php echo $row['end_hours'].":".$row['end_minutes'].":".$row['end_seconds']; ?></div>
        <div class="col-lg-2 col-5">  فرق العداد   </div>
        <div class="col-lg-4 col-7"><?php echo $row['counter_diff']; ?></div>
        <div class="col-lg-2 col-5"> نوع العطل   </div>
        <div class="col-lg-4 col-7"><?php echo $row['fault_type']; ?></div>
        <div class="col-lg-2 col-5"> الجزء المعطل   </div>
        <div class="col-lg-4 col-7"><?php echo $row['fault_part']; ?></div>
        <div class="col-lg-2 col-5"> تفاصيل العطل   </div>
        <div class="col-lg-4 col-7"><?php echo $row['fault_details']; ?></div>
        <div class="col-lg-2 col-5">  ملاحظات عامة   </div>
        <div class="col-lg-4 col-7"><?php echo $row['general_notes']; ?></div>
        <div class="col-lg-2 col-5"> ساعات عمل المشغل </div>
        <div class="col-lg-4 col-7"><?php echo $row['operator_hours']; ?></div>
        <div class="col-lg-2 col-5">  ساعات استعداد الآليه   </div>
        <div class="col-lg-4 col-7"><?php echo $row['machine_standby_hours']; ?></div>
        <div class="col-lg-2 col-5">  ساعات استعداد الجاك همر   </div>
        <div class="col-lg-4 col-7"><?php echo $row['jackhammer_standby_hours']; ?></div>
        <div class="col-lg-2 col-5"> ساعات استعداد الجردل  </div>
        <div class="col-lg-4 col-7"><?php echo $row['bucket_standby_hours']; ?></div>
        <div class="col-lg-2 col-5"> الساعات الاضافية للمشغل  </div>
        <div class="col-lg-4 col-7"><?php echo $row['extra_operator_hours']; ?></div>
        <div class="col-lg-2 col-5">  ساعات استعداد المشغل  </div>
        <div class="col-lg-4 col-7"><?php echo $row['operator_standby_hours']; ?></div>
        <div class="col-lg-2 col-5"> ملاحظات المشغل  </div>
        <div class="col-lg-4 col-7"><?php echo $row['operator_notes']; ?></div>
        <div class="col-lg-2 col-5"> ملاحظات مشرفين الساعات   </div>
        <div class="col-lg-4 col-7"><?php echo $row['time_notes']; ?></div>
    </div>
</div>




<?php } ?>


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
