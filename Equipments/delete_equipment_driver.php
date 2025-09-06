<?php
            include '../config.php';

if (isset($_GET['id']) && isset($_GET['equipment_id'])) {
    $id = intval($_GET['id']);
    $equipment_id = intval($_GET['equipment_id']);

    mysqli_query($conn, "DELETE FROM equipment_drivers WHERE id = $id");

    header("Location: add_drivers.php?equipment_id=$equipment_id");
    exit;
}
?>
