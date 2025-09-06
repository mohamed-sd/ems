<?php
include '../config.php';

if (isset($_POST['equipment_id']) && isset($_POST['drivers'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $drivers = $_POST['drivers'];

    
    // سجل الجديد
    foreach ($drivers as $driver_id) {
        $driver_id = intval($driver_id);
        mysqli_query($conn, "INSERT INTO equipment_drivers (equipment_id, driver_id) VALUES ($equipment_id, $driver_id)");
    }

    echo "✅ تم تحديث السائقين للآلية.";
    echo "<br><a href='equipments.php'>🔙 الرجوع للآليات</a>";
}
?>
