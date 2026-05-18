<?php
session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

include '../config.php';
header('Content-Type: application/json; charset=utf-8');

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';
$operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');

if (!$is_super_admin && $company_id <= 0) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['success' => false, 'message' => 'بيئة شركة غير صالحة']);
    exit;
}

$operation_id = isset($_GET['operation_id']) ? intval($_GET['operation_id']) : 0;
if ($operation_id <= 0) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['success' => false, 'message' => 'رقم تشغيل غير صحيح']);
    exit;
}

$scope = "";
if (!$is_super_admin) {
    $scope = " AND EXISTS (
        SELECT 1
        FROM project p
        LEFT JOIN users su ON su.id = p.created_by
        LEFT JOIN clients sc ON sc.id = p.$project_client_column
        LEFT JOIN users scu ON scu.id = sc.created_by
        WHERE p.id = operations.$operations_project_column
          AND (su.company_id = $company_id OR scu.company_id = $company_id)
    )";
}

$shift_type_select = $operations_has_shift_type ? "shift_type" : "'B' AS shift_type";
$query = "SELECT shift_hours, $shift_type_select FROM operations WHERE id = $operation_id" . $scope . " LIMIT 1";
$result = mysqli_query($conn, $query);

while (ob_get_level()) {
    ob_end_clean();
}

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم العثور على التشغيل',
        'shift_hours' => 0,
        'shift_type' => 'B',
        'allowed_shifts' => ['D', 'N']
    ]);
    exit;
}

$op = mysqli_fetch_assoc($result);
$shift_hours = isset($op['shift_hours']) ? floatval($op['shift_hours']) : 0;
$shift_type = isset($op['shift_type']) ? strtoupper(trim((string) $op['shift_type'])) : 'B';
if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
    $shift_type = 'B';
}

$allowed_shifts = ['D', 'N'];
if ($shift_type === 'D') {
    $allowed_shifts = ['D'];
} elseif ($shift_type === 'N') {
    $allowed_shifts = ['N'];
}

echo json_encode([
    'success' => true,
    'shift_hours' => $shift_hours,
    'shift_type' => $shift_type,
    'allowed_shifts' => $allowed_shifts
]);
exit;
?>
