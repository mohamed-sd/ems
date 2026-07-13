<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);

    // جلب عقود المشروع المحدد — معزولةً عبر البوابة (كان الاستعلام بلا عزل شركةٍ
    // إطلاقًا: تسرّبٌ كامنٌ أُغلق). السوبر → عابرٌ مُسجَّل.
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $gmc_gate = ($role === '-1') ? ems_tenant_db()->forAllTenants('project contracts super') : ems_tenant_db();
    try {
        $gmc_rows = $gmc_gate->scopedQuery(array(
            'scope' => array('c' => 'contracts'),
        ), "SELECT
        c.id,
        CONCAT('عقد رقم ', c.id, ' - ', DATE_FORMAT(c.actual_start, '%Y/%m/%d'), ' - ', c.forecasted_contracted_hours, ' ساعة') as display_name,
        c.forecasted_contracted_hours
        FROM contracts c
        WHERE {TENANT_SCOPE} AND c.project_id = ? AND c.status = 1
        ORDER BY c.actual_start DESC", array($project_id));
    } catch (\Throwable $t) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }

    $contracts = [];
    foreach ($gmc_rows as $row) {
        $contracts[] = [
            'id' => intval($row['id']),
            'display_name' => $row['display_name'],
            'hours' => floatval($row['forecasted_contracted_hours'])
        ];
    }

    echo json_encode([
        'success' => true,
        'contracts' => $contracts
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
