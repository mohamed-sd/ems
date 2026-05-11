<?php
/**
 * mark_read.php - تعليم الرسائل كمقروءة
 * POST: sender_id (الشخص الذي فتحنا محادثته)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false], JSON_UNESCAPED_UNICODE));
}

require_once '../config.php';

$my_id      = intval($_SESSION['user']['id']);
$company_id = intval($_SESSION['user']['company_id']);
$sender_id  = isset($_POST['sender_id']) ? intval($_POST['sender_id']) : 0;

if ($sender_id <= 0) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'المرسل غير محدد'], JSON_UNESCAPED_UNICODE));
}

$safe_sender  = intval($sender_id);
$safe_company = intval($company_id);

$sql = "UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE sender_id  = $safe_sender
          AND receiver_id = $my_id
          AND company_id  = $safe_company
          AND is_read     = 0";

$result = mysqli_query($conn, $sql);

// إيقاف جميع output buffers قبل إرجاع JSON
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success'       => $result ? true : false,
    'rows_affected' => $result ? mysqli_affected_rows($conn) : 0
], JSON_UNESCAPED_UNICODE);
exit;
