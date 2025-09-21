<?php
            include '../config.php';

if (isset($_GET['type'])) {
    $type = intval($_GET['type']);

    // جلب الآلية من جدول التشغيل
    $result = mysqli_query($conn, "SELECT id, code, name FROM equipments WHERE id NOT IN ( SELECT operations.equipment FROM `operations` WHERE `status` LIKE '1' ) AND status = '1' AND type = $type ");

   


    echo "<option value=''>-- اختر الالية --</option>";
    while ($eq = mysqli_fetch_assoc($result)) {
     echo "<option value='" . $eq['id'] . "'>" . $eq['code'] . " - " . $eq['name'] . "</option>";
    }
}
?>
