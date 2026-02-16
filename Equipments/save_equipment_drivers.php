<?php
include '../config.php';

if (isset($_POST['equipment_id']) && isset($_POST['drivers'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $drivers = $_POST['drivers'];

  $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
  $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

  $start_valid = false;
  if ($start_date !== '') {
    $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
    $start_valid = $start_dt && $start_dt->format('Y-m-d') === $start_date;
  }

  $end_valid = false;
  if ($end_date !== '') {
    $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
    $end_valid = $end_dt && $end_dt->format('Y-m-d') === $end_date;
  }

  if (!$start_valid) {
    echo "❌ تاريخ البداية غير صحيح.";
    exit;
  }

  if ($end_date !== '' && !$end_valid) {
    echo "❌ تاريخ النهاية غير صحيح.";
    exit;
  }

  if ($end_date !== '' && strtotime($start_date) > strtotime($end_date)) {
    echo "❌ تاريخ البداية يجب أن يكون قبل تاريخ النهاية.";
    exit;
  }

  $start_sql = "'" . mysqli_real_escape_string($conn, $start_date) . "'";
  $end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "NULL";

    
    // سجل الجديد
    foreach ($drivers as $driver_id) {
        $driver_id = intval($driver_id);
        
        // التحقق من عدم وجود ربط نشط بالفعل
        $check = mysqli_query($conn, "SELECT id FROM equipment_drivers WHERE equipment_id=$equipment_id AND driver_id=$driver_id AND status=1");
        if (mysqli_num_rows($check) > 0) {
            continue; // تخطي السائق إذا كان مرتبط بالفعل بشكل نشط
        }
        
        mysqli_query(
            $conn,
            "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status) 
             VALUES ($equipment_id, $driver_id, $start_sql, $end_sql, 1)"
        );
    }

    echo "✅ تم تحديث السائقين للآلية.";
    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='equipments.php';</script>";
}
?>
