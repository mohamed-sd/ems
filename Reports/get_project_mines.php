<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف المشروع غير صحيح']));
}

$query = "SELECT id, mine_name, mine_code 
          FROM mines 
          WHERE project_id = $project_id AND status = 1 
          ORDER BY mine_name";

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]));
}

$mines = [];
while ($row = mysqli_fetch_assoc($result)) {
    $mines[] = [
        'id' => $row['id'],
        'mine_name' => $row['mine_name'],
        'mine_code' => $row['mine_code']
    ];
}

echo json_encode([
    'success' => true,
    'mines' => $mines,
    'count' => count($mines),
    'project_id' => $project_id
], JSON_UNESCAPED_UNICODE);
