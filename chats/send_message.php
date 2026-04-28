<?php
/**
 * send_message.php - إرسال رسالة داخلية
 * POST: receiver_id, message
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

require_once '../config.php';

$sender_id   = intval($_SESSION['user']['id']);
$company_id  = intval($_SESSION['user']['company_id']);
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message     = isset($_POST['message']) ? trim($_POST['message']) : '';

// التحقق من المدخلات
if ($receiver_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'المستلم غير محدد']));
}
if (empty($message)) {
    die(json_encode(['success' => false, 'message' => 'الرسالة فارغة']));
}
if (mb_strlen($message) > 2000) {
    die(json_encode(['success' => false, 'message' => 'الرسالة طويلة جداً (الحد الأقصى 2000 حرف)']));
}
if ($receiver_id === $sender_id) {
    die(json_encode(['success' => false, 'message' => 'لا يمكنك مراسلة نفسك']));
}

// التأكد من أن المستلم في نفس الشركة
$safe_receiver = intval($receiver_id);
$safe_company  = intval($company_id);
$check = mysqli_query($conn, "SELECT id FROM users WHERE id = $safe_receiver AND company_id = $safe_company AND is_deleted = 0 AND status = 'active'");
if (!$check || mysqli_num_rows($check) === 0) {
    die(json_encode(['success' => false, 'message' => 'المستلم غير موجود']));
}

// إدراج الرسالة
$safe_message = mysqli_real_escape_string($conn, $message);
$sql = "INSERT INTO messages (company_id, sender_id, receiver_id, message, created_at)
        VALUES ($safe_company, $sender_id, $safe_receiver, '$safe_message', NOW())";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في إرسال الرسالة: ' . mysqli_error($conn)]));
}

$new_id = mysqli_insert_id($conn);

echo json_encode([
    'success'    => true,
    'message'    => 'تم إرسال الرسالة',
    'message_id' => $new_id,
    'created_at' => date('Y-m-d H:i:s')
]);
exit;
