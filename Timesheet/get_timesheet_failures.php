<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
}

include '../config.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$timesheet_id = isset($_GET['timesheet_id']) ? intval($_GET['timesheet_id']) : 0;
if ($timesheet_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'timesheet_id is required', 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

$scope_sql = '';
if (!$is_super_admin && db_table_has_column($conn, 'timesheet_failure_hours', 'company_id')) {
    $scope_sql = ' AND company_id = ' . intval($company_id);
}

$sql = "SELECT id, timesheet_id, operation_id, equipment_id, failure_code_id, equipment_type,
               event_type_code, event_type_name, main_category_code, main_category_name,
               sub_category, failure_detail, full_code, timesheet_date
        FROM timesheet_failure_hours
        WHERE timesheet_id = $timesheet_id AND status = 1 $scope_sql
        ORDER BY id ASC";

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn), 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
exit;
