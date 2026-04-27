<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$suppliers_has_company_id = db_table_has_column($conn, 'suppliers', 'company_id');

// شرط عزل الشركة
$supplier_company_where = (!$is_super_admin && $suppliers_has_company_id && $current_company_id > 0)
    ? " AND s.company_id = $current_company_id"
    : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contract_id'])) {
    $contract_id = intval($_POST['contract_id']);

    if ($is_role10 && $user_contract_id > 0 && $contract_id !== $user_contract_id) {
        die(json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا العقد']));
    }

    $contract_check = mysqli_query($conn, "SELECT id FROM contracts WHERE id = $contract_id AND status = 1");
    if (!$contract_check || mysqli_num_rows($contract_check) === 0) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $suppliers_query = "SELECT DISTINCT s.id, s.name
                        FROM suppliers s
                        INNER JOIN supplierscontracts sc ON sc.supplier_id = s.id
                        WHERE sc.project_contract_id = $contract_id AND s.status = 1
                        $supplier_company_where
                        ORDER BY s.name ASC";

    $result = mysqli_query($conn, $suppliers_query);

    if (!$result) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب الموردين']));
    }

    $suppliers = [];
    while ($row = mysqli_fetch_assoc($result)) {
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
