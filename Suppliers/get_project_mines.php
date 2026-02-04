<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    
    // جلب مناجم المشروع المحدد
    $mines_query = "SELECT 
        id, 
        mine_name,
        mine_code
        FROM mines 
        WHERE project_id = $project_id AND status = 1
        ORDER BY mine_name ASC";
    
    $result = mysqli_query($conn, $mines_query);
    
    if (!$result) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب المناجم']));
    }
    
    $mines = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $mines[] = [
            'id' => intval($row['id']),
            'name' => $row['mine_name'],
            'code' => $row['mine_code'],
            'display_name' => $row['mine_name'] . ' (' . $row['mine_code'] . ')'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'mines' => $mines
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
