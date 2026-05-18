<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/approval_workflow.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo "<script>alert('❌ معرّف الشركة غير متوفر'); window.location.href='equipments.php';</script>";
    exit;
}

$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');

if (isset($_GET['id']) && isset($_GET['equipment_id'])) {
    $id = intval($_GET['id']);
    $equipment_id = intval($_GET['equipment_id']);
    $user_role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '';
    $is_role10 = ($user_role == "10");

    // الحصول على بيانات الربط الحالي
    $scope_sql = '1=1';
    if (!$is_super_admin) {
        if ($equipment_drivers_has_company) {
            $scope_sql = "ed.company_id = $company_id";
        } elseif ($equipments_has_company) {
            $scope_sql = "e.company_id = $company_id";
        } else {
            $scope_sql = "EXISTS (
                SELECT 1
                FROM operations so
                JOIN project sp ON sp.id = so.project_id
                WHERE so.equipment = e.id
                  AND (
                      EXISTS (SELECT 1 FROM users su WHERE su.id = sp.created_by AND su.company_id = $company_id)
                      OR EXISTS (
                          SELECT 1
                          FROM clients sc
                          JOIN users scu ON scu.id = sc.created_by
                          WHERE sc.id = sp.company_client_id AND scu.company_id = $company_id
                      )
                  )
            )";
        }
    }

    $res = mysqli_query($conn, "SELECT ed.*, d.name AS driver_name, e.code AS equipment_code, e.name AS equipment_name 
                                 FROM equipment_drivers ed
                                 JOIN drivers d ON ed.driver_id = d.id
                                 JOIN equipments e ON ed.equipment_id = e.id
                                 WHERE ed.id=$id AND $scope_sql");
    
    if (!$res || mysqli_num_rows($res) === 0) {
        echo "<script>alert('❌ الربط غير موجود'); window.location.href='add_drivers.php?equipment_id=$equipment_id';</script>";
        exit;
    }
    
    $row = mysqli_fetch_assoc($res);
    $current_status = intval($row['status']);
    $new_status = ($current_status == 1) ? 0 : 1;
    $driver_id = intval($row['driver_id']);
    $driver_name = $row['driver_name'];
    $equipment_code = $row['equipment_code'];
    $equipment_name = $row['equipment_name'];

    // إذا كان مدير الحركة والتشغيل (role 10)، إنشاء طلب موافقة
    if ($is_role10) {
        $action_type = ($new_status == 0) ? 'deactivate_driver' : 'reactivate_driver';
        $action_ar = ($new_status == 0) ? 'إيقاف' : 'تشغيل';
        
        // ضمان وجود قاعدة الموافقة (مدير المشغلين)
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
             VALUES ('driver', '$action_type', '3,-1', 1, 1, NOW())"
        );

        $payload = [
            'summary' => [
                'equipment_driver_id' => $id,
                'driver_id' => $driver_id,
                'driver_name' => $driver_name,
                'equipment_id' => $equipment_id,
                'equipment_code' => $equipment_code,
                'equipment_name' => $equipment_name,
                'current_status' => $current_status,
                'new_status' => $new_status,
                'action' => $action_ar,
                'requested_by_role' => '10',
                'reason' => "طلب $action_ar مشغل من شاشة إدارة المشغلين"
            ],
            'operations' => [
                [
                    'db_action' => 'update',
                    'table' => 'equipment_drivers',
                    'where' => ['id' => $id],
                    'data' => ['status' => $new_status]
                ]
            ]
        ];

        $approval_result = approval_create_request(
            'driver',
            $driver_id,
            $action_type,
            $payload,
            approval_get_user_id(),
            $conn
        );

        if (!empty($approval_result['success'])) {
            echo "<script>alert('✅ " . addslashes($approval_result['message']) . "'); window.location.href='add_drivers.php?equipment_id=$equipment_id';</script>";
        } else {
            echo "<script>alert('❌ " . addslashes($approval_result['message']) . "'); window.location.href='add_drivers.php?equipment_id=$equipment_id';</script>";
        }
        exit;
    }

    // المستخدمون الآخرون: تغيير الحالة مباشرة
    $update_scope = ($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id";
    mysqli_query($conn, "UPDATE equipment_drivers SET status=$new_status WHERE id=$id$update_scope");
    header("Location: add_drivers.php?equipment_id=$equipment_id");
    exit;
}
?>


