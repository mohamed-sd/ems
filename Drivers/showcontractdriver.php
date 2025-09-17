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
    <title>إيكوبيشن | تفاصيل العقد</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
           <!-- Bootstrab 5 -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <h3> 📑 تفاصيل عقد السائق </h3>
    <br/>

<?php
include '../config.php';

$contract_id = intval($_GET['id']);

$sql = "SELECT 
            id, driver_id, contract_signing_date, grace_period_days, contract_duration_months, 
            actual_start, actual_end, transportation, accommodation, place_for_living, 
            workshop, equip_type, equip_size, equip_count, equip_target_per_month, 
            equip_total_month, equip_total_contract, mach_type, mach_size, mach_count, 
            mach_target_per_month, mach_total_month, mach_total_contract, 
            hours_monthly_target, forecasted_contracted_hours, created_at, updated_at ,
            daily_work_hours , daily_operators ,first_party ,second_party , witness_one , 
            witness_two,project_id
        FROM drivercontracts
        WHERE id = $contract_id
        LIMIT 1";

$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
?>
    <div class="report">
        <div class="row">
            <div class="col-lg-2 col-5">المشروع</div>
            <div class="col-lg-4 col-7"><?php echo $row['project_id']; ?></div>

            <div class="col-lg-2 col-5">تاريخ توقيع العقد</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_signing_date']; ?></div>

            <div class="col-lg-2 col-5">فترة السماح (أيام)</div>
            <div class="col-lg-4 col-7"><?php echo $row['grace_period_days']; ?></div>

            <div class="col-lg-2 col-5">مدة العقد (شهور)</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_duration_months']; ?></div>

            <div class="col-lg-2 col-5">تاريخ البدء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_start']; ?></div>

            <div class="col-lg-2 col-5">تاريخ الانتهاء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_end']; ?></div>

            <div class="col-lg-2 col-5">النقل</div>
            <div class="col-lg-4 col-7"><?php echo $row['transportation']; ?></div>

            <div class="col-lg-2 col-5">السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['accommodation']; ?></div>

            <div class="col-lg-2 col-5">مكان السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['place_for_living']; ?></div>

            <div class="col-lg-2 col-5">الورشة</div>
            <div class="col-lg-4 col-7"><?php echo $row['workshop']; ?></div>

            <div class="col-lg-2 col-5">نوع المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_type']; ?></div>

            <div class="col-lg-2 col-5">حجم المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_size']; ?></div>

            <div class="col-lg-2 col-5">عدد المعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_count']; ?></div>

            <div class="col-lg-2 col-5">هدف المعدات شهريًا</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_target_per_month']; ?></div>

            <div class="col-lg-2 col-5">إجمالي المعدات شهريًا</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_month']; ?></div>

            <div class="col-lg-2 col-5">إجمالي العقد للمعدات</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_contract']; ?></div>

            <div class="col-lg-2 col-5">نوع الآلية</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_type']; ?></div>

            <div class="col-lg-2 col-5">حجم الآلية</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_size']; ?></div>

            <div class="col-lg-2 col-5">عدد الآليات</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_count']; ?></div>

            <div class="col-lg-2 col-5">هدف الآليات شهريًا</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_target_per_month']; ?></div>

            <div class="col-lg-2 col-5">إجمالي الآليات شهريًا</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_month']; ?></div>

            <div class="col-lg-2 col-5">إجمالي العقد للآليات</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_contract']; ?></div>

            <div class="col-lg-2 col-5">الهدف الشهري للساعات</div>
            <div class="col-lg-4 col-7"><?php echo $row['hours_monthly_target']; ?></div>

            <div class="col-lg-2 col-5">الساعات التعاقدية المتوقعة</div>
            <div class="col-lg-4 col-7"><?php echo $row['forecasted_contracted_hours']; ?></div>

            <div class="col-lg-2 col-5">تاريخ الإنشاء</div>
            <div class="col-lg-4 col-7"><?php echo $row['created_at']; ?></div>

            <div class="col-lg-2 col-5">آخر تحديث</div>
            <div class="col-lg-4 col-7"><?php echo $row['updated_at']; ?></div>

            <div class="col-lg-2 col-5">عدد ساعات العمل اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_work_hours']; ?></div>

            <div class="col-lg-2 col-5">عدد المشغلين للساعات اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_operators']; ?></div>

            <div class="col-lg-2 col-5">الطرف الأول (ممثل الشركة)</div>
            <div class="col-lg-4 col-7"><?php echo $row['first_party']; ?></div>

            <div class="col-lg-2 col-5">الطرف الثاني (ممثل العميل)</div>
            <div class="col-lg-4 col-7"><?php echo $row['second_party']; ?></div>

            <div class="col-lg-2 col-5">الشاهد الأول</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_one']; ?></div>

            <div class="col-lg-2 col-5">الشاهد الثاني</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_two']; ?></div>
        </div>
    </div>
<?php } ?>


    <br/><br/><br/>

</div>

</body>
</html>
