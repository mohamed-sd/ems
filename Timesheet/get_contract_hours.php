<?php
include '../config.php';

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);

    // جلب ساعات الوردية مباشرة من جدول التشغيل
    $query = "SELECT shift_hours FROM operations WHERE id = $operation_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $op = mysqli_fetch_assoc($result);
        
        // إذا كانت القيمة موجودة ولها قيمة، نرجعها
        if (isset($op['shift_hours']) && $op['shift_hours'] > 0) {
            echo $op['shift_hours'];
        } else {
            // إذا لم تكن موجودة، نضع قيمة افتراضية 10 ساعات
            echo 10;
        }
    } else {
        // في حالة عدم وجود السجل
        echo 10;
    }
} else {
    echo 0;
}
?>
