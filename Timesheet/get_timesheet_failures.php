<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$gtf_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet failures super') : ems_tenant_db();

$data = [];
try {
    $data = $gtf_gate->select('timesheet_failure_hours', array(
        'columns' => array('id', 'timesheet_id', 'operation_id', 'equipment_id', 'failure_code_id', 'equipment_type',
                           'event_type_code', 'event_type_name', 'main_category_code', 'main_category_name',
                           'sub_category', 'failure_detail', 'full_code', 'timesheet_date'),
        'where'   => array('timesheet_id' => $timesheet_id, 'status' => 1),
        'orderBy' => 'id ASC',
    ));
} catch (\Throwable $t) {
    echo json_encode(['success' => false, 'message' => $t->getMessage(), 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
exit;
