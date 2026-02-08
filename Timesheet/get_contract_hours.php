<?php
            include '../config.php';

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);

    // جلب المشروع المرتبط من التشغيل
    $op = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT project_id FROM operations WHERE id = $operation_id
    "));

    if ($op) {
        $project_id = $op['project_id'];

        // جلب إجمالي ساعات العقد للمشروع
        $contract = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT daily_work_hours
            FROM contracts
            WHERE project = $project_id
            LIMIT 1
        "));

        if ($contract) {
            echo $contract['daily_work_hours']/2; // نرجع فقط الرقم
        } else {
            echo 10;
        }
    } else {
        echo 0;
    }
}
?>
