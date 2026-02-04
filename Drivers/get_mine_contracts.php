<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mine_id'])) {
    $mine_id = intval($_POST['mine_id']);
    
    // جلب عقود المنجم المحدد
    $contracts_query = "SELECT 
        c.id, 
        CONCAT('عقد رقم ', c.id, ' - ', DATE_FORMAT(c.actual_start, '%Y/%m/%d'), ' - ', c.forecasted_contracted_hours, ' ساعة') as display_name,
        c.forecasted_contracted_hours
        FROM contracts c
        WHERE c.mine_id = $mine_id AND c.status = 1
        ORDER BY c.actual_start DESC";
    
    $result = mysqli_query($conn, $contracts_query);
    
    if (!$result) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }
    
    $contracts = [];
    while ($row = mysqli_fetch_assoc($result)) {
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
