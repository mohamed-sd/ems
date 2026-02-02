<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    
    // جلب عقود المشروع المحدد
    $contracts_query = "SELECT 
        id, 
        CONCAT('عقد رقم ', id, ' - ', DATE_FORMAT(actual_start, '%Y/%m/%d'), ' - ', forecasted_contracted_hours, ' ساعة') as display_name,
        forecasted_contracted_hours
        FROM contracts 
        WHERE project = $project_id AND status = 1
        ORDER BY actual_start DESC";
    
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
