<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف المشروع غير صحيح']));
}

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$gmc_gate = $is_super ? ems_tenant_db()->forAllTenants('report super') : ems_tenant_db();

$result = null;
try {
    $result = $gmc_gate->select('contracts', array(
        'columns' => array('id', 'contract_signing_date', 'actual_start', 'actual_end'),
        'where'   => array('project_id' => $project_id, 'status' => 1),
        'orderBy' => 'contract_signing_date DESC',
    ));
} catch (\Throwable $t) { error_log('get_mine_contracts: ' . $t->getMessage()); }

if ($result === null) {
    die(json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: تعذّر الجلب']));
}

$contracts = [];
foreach ($result as $row) {
    $contracts[] = [
        'id' => $row['id'],
        'contract_signing_date' => $row['contract_signing_date'],
        'actual_start' => $row['actual_start'],
        'actual_end' => $row['actual_end']
    ];
}

echo json_encode([
    'success' => true,
    'contracts' => $contracts,
    'count' => count($contracts),
    'project_id' => $project_id
], JSON_UNESCAPED_UNICODE);
