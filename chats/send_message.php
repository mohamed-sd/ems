<?php
/**
 * send_message.php - إرسال رسالة داخلية
 * POST: receiver_id, message
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE));
}

require_once '../config.php';

$sender_id   = intval($_SESSION['user']['id']);
$company_id  = intval($_SESSION['user']['company_id']);
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message     = isset($_POST['message']) ? trim($_POST['message']) : '';

// التحقق من المدخلات
if ($receiver_id <= 0) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'المستلم غير محدد'], JSON_UNESCAPED_UNICODE));
}
if (empty($message)) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'الرسالة فارغة'], JSON_UNESCAPED_UNICODE));
}
if (mb_strlen($message) > 2000) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'الرسالة طويلة جداً (الحد الأقصى 2000 حرف)'], JSON_UNESCAPED_UNICODE));
}
if ($receiver_id === $sender_id) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'لا يمكنك مراسلة نفسك'], JSON_UNESCAPED_UNICODE));
}

// التأكد من أن المستلم في نفس الشركة
$safe_receiver = intval($receiver_id);
$safe_company  = intval($company_id);
$check = mysqli_query($conn, "SELECT id FROM users WHERE id = $safe_receiver AND company_id = $safe_company AND is_deleted = 0 AND status = 'active'");
if (!$check || mysqli_num_rows($check) === 0) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'المستلم غير موجود'], JSON_UNESCAPED_UNICODE));
}

// إدراج الرسالة
$safe_message = mysqli_real_escape_string($conn, $message);
$sql = "INSERT INTO messages (company_id, sender_id, receiver_id, message, created_at)
        VALUES ($safe_company, $sender_id, $safe_receiver, '$safe_message', NOW())";

$result = mysqli_query($conn, $sql);
if (!$result) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'خطأ في إرسال الرسالة: ' . mysqli_error($conn)], JSON_UNESCAPED_UNICODE));
}

$new_id = mysqli_insert_id($conn);

// سجل إرسال واحد فقط باسم "إرسال".
\App\Services\ActivityLogService::logAction('send', 'chats', 'send_message', [
    'button_name'     => 'إرسال',
    'record_id'       => $new_id,
    'response_status' => 200,
    'new_value'       => [
        'receiver_id' => $safe_receiver,
        'message_id'  => $new_id,
    ],
]);

// إيقاف جميع output buffers قبل إرجاع JSON
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success'    => true,
    'message'    => 'تم إرسال الرسالة',
    'message_id' => $new_id,
    'created_at' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
exit;
