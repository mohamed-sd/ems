<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['project_id']) || isset($_POST['mine_id']))) {
    // project_id مباشرةً؛ مسار mine_id القديم ميّتٌ بنيويًّا (جدول mines أُزيل —
    // سلوك الأصل الفعلي: فشل الاستعلام بصمت ⇒ project_id=0) ويُحفَظ حرفيًّا.
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    $contract_filter = ''; $gmc_params = array($project_id);
    if ($is_role10 && $user_contract_id > 0) {
        $contract_filter = " AND c.id = ?";
        $gmc_params[] = $user_contract_id;
    }

    // العزل عبر البوابة (الأصل كان بلا عزل شركةٍ إطلاقًا) — السوبر عبر forAllTenants
    $gmc_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $gmc_gate = ($gmc_role === '-1') ? ems_tenant_db()->forAllTenants('oprators mine contracts super') : ems_tenant_db();
    try {
        $contracts_rows = $gmc_gate->scopedQuery(array(
            'scope' => array('c' => 'contracts'),
        ), "SELECT
            c.id,
            DATE_FORMAT(c.actual_start, '%Y/%m/%d') AS start_display,
            DATE_FORMAT(c.actual_end, '%Y-%m-%d') AS end_date,
            c.forecasted_contracted_hours
        FROM contracts c
        WHERE {TENANT_SCOPE} AND c.project_id = ? AND c.status = 1$contract_filter
        ORDER BY c.actual_start DESC", $gmc_params);
    } catch (\Throwable $t) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }

    $contracts = [];
    foreach ($contracts_rows as $row) {
        $display = 'عقد رقم ' . $row['id'] . ' - ' . $row['start_display'] . ' - ' . $row['forecasted_contracted_hours'] . ' ساعة';
        $contracts[] = [
            'id' => intval($row['id']),
            'display_name' => $display,
            'hours' => floatval($row['forecasted_contracted_hours']),
            'end_date' => $row['end_date']
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
