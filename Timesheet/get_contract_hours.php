<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo 0;
    exit;
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if (!$is_super_admin && $company_id <= 0) {
    echo 0;
    exit;
}

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);

    // جلب ساعات الوردية مباشرة من جدول التشغيل
    $scope = "";
    if (!$is_super_admin) {
        $scope = " AND EXISTS (
            SELECT 1
            FROM project p
            LEFT JOIN users su ON su.id = p.created_by
                        LEFT JOIN clients sc ON sc.id = p.$project_client_column
            LEFT JOIN users scu ON scu.id = sc.created_by
            WHERE p.id = operations.project_id
              AND (su.company_id = $company_id OR scu.company_id = $company_id)
        )";
    }

    $query = "SELECT shift_hours FROM operations WHERE id = $operation_id" . $scope;
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
