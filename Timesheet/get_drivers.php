<?php
session_start();
if (!isset($_SESSION['user'])) {
    exit;
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if (!$is_super_admin && $company_id <= 0) {
    exit;
}

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);

    $operation_scope = "";
    if (!$is_super_admin) {
        $operation_scope = " AND EXISTS (
            SELECT 1
            FROM project p
            LEFT JOIN users su ON su.id = p.created_by
                        LEFT JOIN clients sc ON sc.id = p.$project_client_column
            LEFT JOIN users scu ON scu.id = sc.created_by
            WHERE p.id = operations.project_id
              AND (su.company_id = $company_id OR scu.company_id = $company_id)
        )";
    }

    // جلب الآلية من جدول التشغيل ضمن نطاق الشركة
    $op = mysqli_fetch_assoc(mysqli_query($conn, "SELECT equipment FROM operations WHERE id = $operation_id" . $operation_scope));
    if (!$op || !isset($op['equipment'])) {
        echo "<option value=''>-- اختر السائق --</option>";
        exit;
    }
    $equipment_id = $op['equipment'];

    // جلب السائقين المرتبطين بهذه الآلية
    $sql = "SELECT d.id, d.name 
            FROM equipment_drivers ed
            JOIN drivers d ON ed.driver_id = d.id
            WHERE ed.equipment_id = $equipment_id";

    if (!$is_super_admin) {
        if (db_table_has_column($conn, 'drivers', 'company_id')) {
            $sql .= " AND d.company_id = $company_id";
        } else {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM equipment_drivers ed2
                JOIN operations o2 ON o2.equipment = ed2.equipment_id
                JOIN project p2 ON p2.id = o2.project_id
                LEFT JOIN users su2 ON su2.id = p2.created_by
                                LEFT JOIN clients sc2 ON sc2.id = p2.$project_client_column
                LEFT JOIN users scu2 ON scu2.id = sc2.created_by
                WHERE ed2.driver_id = d.id
                  AND (su2.company_id = $company_id OR scu2.company_id = $company_id)
            )";
        }
    }

    $result = mysqli_query($conn, $sql);

    echo "<option value=''>-- اختر السائق --</option>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
}
?>
