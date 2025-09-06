<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تفاصيل العقد</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <h3> 📑 تفاصيل العقد </h3>
    <br/>
    <table class="table">
        <thead>
        <?php
        include '../config.php';

        $contract_id = intval($_GET['id']);

        $sql = "SELECT 
                    id, project, contract_signing_date, grace_period_days, contract_duration_months, 
                    actual_start, actual_end, transportation, accommodation, place_for_living, 
                    workshop, equip_type, equip_size, equip_count, equip_target_per_month, 
                    equip_total_month, equip_total_contract, mach_type, mach_size, mach_count, 
                    mach_target_per_month, mach_total_month, mach_total_contract, 
                    hours_monthly_target, forecasted_contracted_hours, created_at, updated_at ,
                    daily_work_hours , daily_operators ,first_party ,second_party , witness_one , 
                    witness_two
                FROM contracts
                WHERE id = $contract_id
                LIMIT 1";

        $result = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($result)) {
        ?>
            <tr class="o">
                <th> المشروع </th>
                <th><?php echo $row['project']; ?></th>
            </tr>
            <tr class="t">
                <th> تاريخ توقيع العقد </th>
                <th><?php echo $row['contract_signing_date']; ?></th>
            </tr>
            <tr class="o">
                <th> فترة السماح (أيام) </th>
                <th><?php echo $row['grace_period_days']; ?></th>
            </tr>
            <tr class="t">
                <th> مدة العقد (شهور) </th>
                <th><?php echo $row['contract_duration_months']; ?></th>
            </tr>
            <tr class="o">
                <th> تاريخ البدء الفعلي </th>
                <th><?php echo $row['actual_start']; ?></th>
            </tr>
            <tr class="t">
                <th> تاريخ الانتهاء الفعلي </th>
                <th><?php echo $row['actual_end']; ?></th>
            </tr>
            <tr class="o">
                <th> النقل </th>
                <th><?php echo $row['transportation']; ?></th>
            </tr>
            <tr class="t">
                <th> السكن </th>
                <th><?php echo $row['accommodation']; ?></th>
            </tr>
            <tr class="o">
                <th> مكان السكن </th>
                <th><?php echo $row['place_for_living']; ?></th>
            </tr>
            <tr class="t">
                <th> الورشة </th>
                <th><?php echo $row['workshop']; ?></th>
            </tr>
            <tr class="o">
                <th> نوع المعدات </th>
                <th><?php echo $row['equip_type']; ?></th>
            </tr>
            <tr class="t">
                <th> حجم المعدات </th>
                <th><?php echo $row['equip_size']; ?></th>
            </tr>
            <tr class="o">
                <th> عدد المعدات </th>
                <th><?php echo $row['equip_count']; ?></th>
            </tr>
            <tr class="t">
                <th> هدف المعدات شهريًا </th>
                <th><?php echo $row['equip_target_per_month']; ?></th>
            </tr>
            <tr class="o">
                <th> إجمالي المعدات شهريًا </th>
                <th><?php echo $row['equip_total_month']; ?></th>
            </tr>
            <tr class="t">
                <th> إجمالي العقد للمعدات </th>
                <th><?php echo $row['equip_total_contract']; ?></th>
            </tr>
            <tr class="o">
                <th> نوع الآلية </th>
                <th><?php echo $row['mach_type']; ?></th>
            </tr>
            <tr class="t">
                <th> حجم الآلية </th>
                <th><?php echo $row['mach_size']; ?></th>
            </tr>
            <tr class="o">
                <th> عدد الآليات </th>
                <th><?php echo $row['mach_count']; ?></th>
            </tr>
            <tr class="t">
                <th> هدف الآليات شهريًا </th>
                <th><?php echo $row['mach_target_per_month']; ?></th>
            </tr>
            <tr class="o">
                <th> إجمالي الآليات شهريًا </th>
                <th><?php echo $row['mach_total_month']; ?></th>
            </tr>
            <tr class="t">
                <th> إجمالي العقد للآليات </th>
                <th><?php echo $row['mach_total_contract']; ?></th>
            </tr>
            <tr class="o">
                <th> الهدف الشهري للساعات </th>
                <th><?php echo $row['hours_monthly_target']; ?></th>
            </tr>
            <tr class="t">
                <th> الساعات التعاقدية المتوقعة </th>
                <th><?php echo $row['forecasted_contracted_hours']; ?></th>
            </tr>
            <tr class="o">
                <th> تاريخ الإنشاء </th>
                <th><?php echo $row['created_at']; ?></th>
            </tr>
            <tr class="t">
                <th> آخر تحديث </th>
                <th><?php echo $row['updated_at']; ?></th>
            </tr>
            <tr class="o">
                <th> عدد ساعات العمل اليومية</th>
                <th><?php echo $row['daily_work_hours']; ?></th>
            </tr>
            <tr class="t">
                <th> عدد المشغلين للساعات اليومية</th>
                <th><?php echo $row['daily_operators']; ?></th>
            </tr>
             <tr class="o">
                <th> الطرف الأول (ممثل الشركة) </th>
                <th><?php echo $row['first_party']; ?></th>
            </tr>
            <tr class="t">
                <th> الطرف الثاني (ممثل العميل)</th>
                <th><?php echo $row['second_party']; ?></th>
            </tr>
             <tr class="o">
                <th> الشاهد الأول </th>
                <th><?php echo $row['witness_one']; ?></th>
            </tr>
            <tr class="t">
                <th>الشاهد الثاني </th>
                <th><?php echo $row['witness_two']; ?></th>
            </tr>
        <?php } ?>
        </thead>
    </table>

    <br/><br/><br/>

</div>

</body>
</html>
