<?php
            include '../config.php';

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);

    // جلب الآلية من جدول التشغيل
    $op = mysqli_fetch_assoc(mysqli_query($conn, "SELECT equipment FROM operations WHERE id = $operation_id"));
    $equipment_id = $op['equipment'];

    // جلب السائقين المرتبطين بهذه الآلية
    $sql = "SELECT d.id, d.name 
            FROM equipment_drivers ed
            JOIN drivers d ON ed.driver_id = d.id
            WHERE ed.equipment_id = $equipment_id";
    $result = mysqli_query($conn, $sql);

    echo "<option value=''>-- اختر السائق --</option>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
}
?>
