<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

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
                        INNER JOIN equipments e ON e.suppliers = s.id
                        WHERE s.status = 1 AND e.status = 1
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
