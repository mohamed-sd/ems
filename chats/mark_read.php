<?php
/**
 * mark_read.php - تعليم الرسائل كمقروءة
 * POST: sender_id (الشخص الذي فتحنا محادثته)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false]));
}

require_once '../config.php';

$my_id      = intval($_SESSION['user']['id']);
$company_id = intval($_SESSION['user']['company_id']);
$sender_id  = isset($_POST['sender_id']) ? intval($_POST['sender_id']) : 0;

if ($sender_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'المرسل غير محدد']));
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

echo json_encode([
    'success'       => $result ? true : false,
    'rows_affected' => $result ? mysqli_affected_rows($conn) : 0
]);
exit;
