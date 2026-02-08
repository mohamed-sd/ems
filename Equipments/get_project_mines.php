<?php
session_start();
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المشروع غير صحيح']);
    exit;
}

$query = "SELECT id, mine_name, mine_code FROM mines WHERE project_id = $project_id AND status = 1 ORDER BY mine_name";
$result = mysqli_query($conn, $query);

$mines = [];
while ($row = mysqli_fetch_assoc($result)) {
    $mines[] = [
        'id' => $row['id'],
        'name' => $row['mine_name'] . ' (' . $row['mine_code'] . ')'
    ];
}

echo json_encode(['success' => true, 'mines' => $mines]);
?>
