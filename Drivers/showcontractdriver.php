<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
           <!-- Bootstrab 5 -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Ø§Ù„Ù…ÙˆÙ‚Ø¹ -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <link rel="stylesheet" href="../assets/css/main_admin_style.css" />
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-id-card"></i></div>
            <h1 class="page-title">ØªÙØ§ØµÙŠÙ„ Ø¹Ù‚Ø¯ Ø§Ù„Ø³Ø§Ø¦Ù‚</h1>
        </div>
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
        </a>
    </div>

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
            <div class="col-lg-2 col-5">Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</div>
            <div class="col-lg-4 col-7"><?php echo $row['project_id']; ?></div>

            <div class="col-lg-2 col-5">ØªØ§Ø±ÙŠØ® ØªÙˆÙ‚ÙŠØ¹ Ø§Ù„Ø¹Ù‚Ø¯</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_signing_date']; ?></div>

            <div class="col-lg-2 col-5">ÙØªØ±Ø© Ø§Ù„Ø³Ù…Ø§Ø­ (Ø£ÙŠØ§Ù…)</div>
            <div class="col-lg-4 col-7"><?php echo $row['grace_period_days']; ?></div>

            <div class="col-lg-2 col-5">Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ (Ø´Ù‡ÙˆØ±)</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_duration_months']; ?></div>

            <div class="col-lg-2 col-5">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø¡ Ø§Ù„ÙØ¹Ù„ÙŠ</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_start']; ?></div>

            <div class="col-lg-2 col-5">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„ÙØ¹Ù„ÙŠ</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_end']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ù†Ù‚Ù„</div>
            <div class="col-lg-4 col-7"><?php echo $row['transportation']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø³ÙƒÙ†</div>
            <div class="col-lg-4 col-7"><?php echo $row['accommodation']; ?></div>

            <div class="col-lg-2 col-5">Ù…ÙƒØ§Ù† Ø§Ù„Ø³ÙƒÙ†</div>
            <div class="col-lg-4 col-7"><?php echo $row['place_for_living']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„ÙˆØ±Ø´Ø©</div>
            <div class="col-lg-4 col-7"><?php echo $row['workshop']; ?></div>

            <div class="col-lg-2 col-5">Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_type']; ?></div>

            <div class="col-lg-2 col-5">Ø­Ø¬Ù… Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_size']; ?></div>

            <div class="col-lg-2 col-5">Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_count']; ?></div>

            <div class="col-lg-2 col-5">Ù‡Ø¯Ù Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø´Ù‡Ø±ÙŠÙ‹Ø§</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_target_per_month']; ?></div>

            <div class="col-lg-2 col-5">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø´Ù‡Ø±ÙŠÙ‹Ø§</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_month']; ?></div>

            <div class="col-lg-2 col-5">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø¹Ù‚Ø¯ Ù„Ù„Ù…Ø¹Ø¯Ø§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['equip_total_contract']; ?></div>

            <div class="col-lg-2 col-5">Ù†ÙˆØ¹ Ø§Ù„Ø¢Ù„ÙŠØ©</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_type']; ?></div>

            <div class="col-lg-2 col-5">Ø­Ø¬Ù… Ø§Ù„Ø¢Ù„ÙŠØ©</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_size']; ?></div>

            <div class="col-lg-2 col-5">Ø¹Ø¯Ø¯ Ø§Ù„Ø¢Ù„ÙŠØ§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_count']; ?></div>

            <div class="col-lg-2 col-5">Ù‡Ø¯Ù Ø§Ù„Ø¢Ù„ÙŠØ§Øª Ø´Ù‡Ø±ÙŠÙ‹Ø§</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_target_per_month']; ?></div>

            <div class="col-lg-2 col-5">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø¢Ù„ÙŠØ§Øª Ø´Ù‡Ø±ÙŠÙ‹Ø§</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_month']; ?></div>

            <div class="col-lg-2 col-5">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø¹Ù‚Ø¯ Ù„Ù„Ø¢Ù„ÙŠØ§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['mach_total_contract']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ù‡Ø¯Ù Ø§Ù„Ø´Ù‡Ø±ÙŠ Ù„Ù„Ø³Ø§Ø¹Ø§Øª</div>
            <div class="col-lg-4 col-7"><?php echo $row['hours_monthly_target']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ¹Ø§Ù‚Ø¯ÙŠØ© Ø§Ù„Ù…ØªÙˆÙ‚Ø¹Ø©</div>
            <div class="col-lg-4 col-7"><?php echo $row['forecasted_contracted_hours']; ?></div>

            <div class="col-lg-2 col-5">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡</div>
            <div class="col-lg-4 col-7"><?php echo $row['created_at']; ?></div>

            <div class="col-lg-2 col-5">Ø¢Ø®Ø± ØªØ­Ø¯ÙŠØ«</div>
            <div class="col-lg-4 col-7"><?php echo $row['updated_at']; ?></div>

            <div class="col-lg-2 col-5">Ø¹Ø¯Ø¯ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_work_hours']; ?></div>

            <div class="col-lg-2 col-5">Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ù„Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_operators']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø£ÙˆÙ„ (Ù…Ù…Ø«Ù„ Ø§Ù„Ø´Ø±ÙƒØ©)</div>
            <div class="col-lg-4 col-7"><?php echo $row['first_party']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ (Ù…Ù…Ø«Ù„ Ø§Ù„Ø¹Ù…ÙŠÙ„)</div>
            <div class="col-lg-4 col-7"><?php echo $row['second_party']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø£ÙˆÙ„</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_one']; ?></div>

            <div class="col-lg-2 col-5">Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø«Ø§Ù†ÙŠ</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_two']; ?></div>
        </div>
    </div>
<?php } ?>


    <br/><br/><br/>

</div>

</body>
</html>

