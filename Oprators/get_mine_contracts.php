<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_mine_id = $is_role10 ? intval($_SESSION['user']['mine_id']) : 0;
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mine_id'])) {
    $mine_id = intval($_POST['mine_id']);

    if ($is_role10 && $user_mine_id > 0 && $mine_id !== $user_mine_id) {
        die(json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا المنجم']));
    }

    $contract_filter = '';
    if ($is_role10 && $user_contract_id > 0) {
        $contract_filter = " AND c.id = $user_contract_id";
    }

    $contracts_query = "SELECT
            c.id,
            DATE_FORMAT(c.actual_start, '%Y/%m/%d') AS start_display,
            DATE_FORMAT(c.actual_end, '%Y-%m-%d') AS end_date,
            c.forecasted_contracted_hours
        FROM contracts c
        WHERE c.mine_id = $mine_id AND c.status = 1$contract_filter
        ORDER BY c.actual_start DESC";

    $result = mysqli_query($conn, $contracts_query);

    if (!$result) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }

    $contracts = [];
    while ($row = mysqli_fetch_assoc($result)) {
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