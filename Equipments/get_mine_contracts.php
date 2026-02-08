<?php
session_start();
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$mine_id = isset($_GET['mine_id']) ? intval($_GET['mine_id']) : 0;

if ($mine_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المنجم غير صحيح']);
    exit;
}

// جلب عقود المنجم مباشرة
$query = "SELECT id, contract_signing_date, contract_duration_months, forecasted_contracted_hours 
          FROM contracts 
          WHERE mine_id = $mine_id AND status = 1 
          ORDER BY contract_signing_date DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]);
    exit;
}

$contracts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $label = 'عقد بتاريخ ' . $row['contract_signing_date'] . ' (' . $row['contract_duration_months'] . ' شهر)';
    $contracts[] = [
        'id' => $row['id'],
        'name' => $label,
        'hours' => $row['forecasted_contracted_hours']
    ];
}

echo json_encode(['success' => true, 'contracts' => $contracts]);
?>
