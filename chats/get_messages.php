<?php
/**
 * get_messages.php - جلب رسائل المحادثة
 * GET/POST: with_user_id, [last_id] للتحديث التزايدي
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

require_once '../config.php';

$my_id       = intval($_SESSION['user']['id']);
$company_id  = intval($_SESSION['user']['company_id']);
$with_user   = isset($_REQUEST['with_user_id']) ? intval($_REQUEST['with_user_id']) : 0;
$last_id     = isset($_REQUEST['last_id'])      ? intval($_REQUEST['last_id'])      : 0;

if ($with_user <= 0) {
    die(json_encode(['success' => false, 'message' => 'المستخدم غير محدد']));
}

// التأكد من أن المستخدم الآخر في نفس الشركة
$safe_with    = intval($with_user);
$safe_company = intval($company_id);
$check = mysqli_query($conn, "SELECT id, name FROM users WHERE id = $safe_with AND company_id = $safe_company AND is_deleted = 0");
if (!$check || mysqli_num_rows($check) === 0) {
    die(json_encode(['success' => false, 'message' => 'المستخدم غير موجود']));
}

// بناء شرط التحديث التزايدي
$last_id_cond = $last_id > 0 ? "AND m.id > $last_id" : '';

$sql = "SELECT
            m.id,
            m.sender_id,
            m.receiver_id,
            m.message,
            m.is_read,
            m.created_at,
            u.name AS sender_name
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.company_id = $safe_company
          AND (
            (m.sender_id = $my_id   AND m.receiver_id = $safe_with AND m.is_deleted_sender = 0)
            OR
            (m.sender_id = $safe_with AND m.receiver_id = $my_id   AND m.is_deleted_receiver = 0)
          )
          $last_id_cond
        ORDER BY m.created_at ASC, m.id ASC
        LIMIT 200";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في قراءة الرسائل']));
}

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id'          => intval($row['id']),
        'sender_id'   => intval($row['sender_id']),
        'receiver_id' => intval($row['receiver_id']),
        'message'     => $row['message'],
        'is_mine'     => intval($row['sender_id']) === $my_id,
        'is_read'     => intval($row['is_read']),
        'sender_name' => $row['sender_name'],
        'created_at'  => $row['created_at'],
        'time_label'  => date('H:i', strtotime($row['created_at'])),
        'date_label'  => date('Y-m-d', strtotime($row['created_at'])),
    ];
}

echo json_encode(['success' => true, 'messages' => $messages]);
exit;
