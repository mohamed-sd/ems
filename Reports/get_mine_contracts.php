<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$mine_id = isset($_GET['mine_id']) ? intval($_GET['mine_id']) : 0;

if ($mine_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف المنجم غير صحيح']));
}

$query = "SELECT id, contract_signing_date, actual_start, actual_end 
          FROM contracts 
          WHERE mine_id = $mine_id AND status = 1 
          ORDER BY contract_signing_date DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]));
}

$contracts = [];
while ($row = mysqli_fetch_assoc($result)) {
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
    'mine_id' => $mine_id
], JSON_UNESCAPED_UNICODE);
