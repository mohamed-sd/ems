<?php
/**
 * get_unread_count.php - جلب عدد الرسائل غير المقروءة
 * لعرض الشارة في شريط التنقل
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once '../config.php';

$my_id      = intval($_SESSION['user']['id']);
$company_id = intval($_SESSION['user']['company_id']);

$sql = "SELECT COUNT(*) AS cnt
        FROM messages
        WHERE receiver_id = $my_id
          AND company_id  = $company_id
          AND is_read     = 0
          AND is_deleted_receiver = 0";

$result = mysqli_query($conn, $sql);
$count  = 0;
if ($result) {
    $row   = mysqli_fetch_assoc($result);
    $count = intval($row['cnt']);
}

echo json_encode(['count' => $count]);
exit;
