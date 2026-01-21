<?php
            include '../config.php';

if (isset($_GET['id']) && isset($_GET['equipment_id'])) {
    $id = intval($_GET['id']);
    $equipment_id = intval($_GET['equipment_id']);


    // الحصول على الحالة الحالية
    $res = mysqli_query($conn, "SELECT status FROM equipment_drivers WHERE id=$id");
    $row = mysqli_fetch_assoc($res);
    $newStatus = ($row['status'] == 1) ? 0 : 1;

        mysqli_query($conn, "UPDATE equipment_drivers SET status=$newStatus WHERE id=$id");

    // mysqli_query($conn, "DELETE FROM equipment_drivers WHERE id = $id");

    header("Location: add_drivers.php?equipment_id=$equipment_id");
    exit;
}
?>
