<?php
// ملف اختبار بسيط
ob_start();

session_start();

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'معرف المشغل مفقود']));
}

$driver_id = intval($_GET['id']);
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// استعلام مبسط
$query = "SELECT * FROM drivers WHERE id = $driver_id AND company_id = $company_id LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)
    ]));
}

if (mysqli_num_rows($result) > 0) {
    $driver = mysqli_fetch_assoc($result);
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => true,
        'driver' => $driver
    ]));
} else {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'المشغل غير موجود'
    ]));
}
