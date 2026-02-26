<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    exit(json_encode(['success' => false, 'message' => 'معرف المشغل مفقود']));
}

$driver_id = intval($_GET['id']);

$query = "SELECT * FROM drivers WHERE id = $driver_id LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $driver = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'driver' => $driver
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'المشغل غير موجود'
    ]);
}
?>
