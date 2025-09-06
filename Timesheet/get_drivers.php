<?php
            include '../config.php';

if (isset($_GET['equipment_id'])) {
    $equipment_id = intval($_GET['equipment_id']);
    echo "📌 Debug: equipment_id = $equipment_id<br>";

    $sql = "SELECT d.id, d.name 
            FROM equipment_drivers ed
            JOIN drivers d ON ed.driver_id = d.id
            WHERE ed.equipment_id = $equipment_id";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("❌ SQL Error: " . mysqli_error($conn));
    }

    echo "<option value=''>-- اختر السائق --</option>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
}
?>
