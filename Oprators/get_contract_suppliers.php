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
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');

// العزل عبر البوابة (K9 · هجرة 2026-07-15) — السوبر عبر forAllTenants المسجَّل؛
// فحص العقد كان بمعرّفٍ فقط بلا عزل شركة (تسرّبٌ كامنٌ أُغلق).
$gcs_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contract suppliers super') : ems_tenant_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contract_id'])) {
    $contract_id = intval($_POST['contract_id']);

    if ($is_role10 && $user_contract_id > 0 && $contract_id !== $user_contract_id) {
        die(json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا العقد']));
    }

    try {
        $contract_check = $gcs_gate->scopedQuery(array(
            'scope' => array('c' => 'contracts'),
        ), "SELECT c.id FROM contracts c WHERE {TENANT_SCOPE} AND c.id = ? AND c.status = 1", array($contract_id));
    } catch (\Throwable $t) { $contract_check = array(); }
    if (empty($contract_check)) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    try {
        $suppliers_rows = $gcs_gate->scopedQuery(array(
            'scope' => array('s' => 'suppliers', 'sc' => 'supplierscontracts'),
        ), "SELECT DISTINCT s.id, s.name
            FROM suppliers s
            INNER JOIN supplierscontracts sc ON sc.supplier_id = s.id
            WHERE {TENANT_SCOPE} AND sc.project_contract_id = ? AND s.status = 1
            ORDER BY s.name ASC", array($contract_id));
    } catch (\Throwable $t) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب الموردين']));
    }

    $suppliers = [];
    foreach ($suppliers_rows as $row) {
        $suppliers[] = [
            'id' => intval($row['id']),
            'name' => $row['name']
        ];
    }

    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
