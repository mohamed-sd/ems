<?php
/**
 * get_open_orders_count.php — عدد أوامر الصيانة «التلقائية المفتوحة» لشركة المستخدم (لشارة الجرس).
 * تلقائية = is_auto = 1 (واردة من صفحة الحركة). مفتوحة = state IN (بلاغ/تنفيذ/فحص) وغير محذوفة.
 * يُستدعى عبر XHR فقط (نمط get_breakdown_count.php).
 * (لعدّ كل المفتوحة بصرف النظر عن المصدر: احذف شرط is_auto = 1.)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once '../config.php';

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

$count = 0;
if ($company_id > 0) {
    $sql = "SELECT COUNT(*) AS c FROM mnt_order
            WHERE company_id = ? AND is_auto = 1
              AND state IN ('بلاغ', 'تنفيذ', 'فحص') AND COALESCE(is_deleted, 0) = 0";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $count = intval($row['c']);
        }
        mysqli_stmt_close($stmt);
    }
}

// إيقاف أي output buffers قبل إرجاع JSON (نمط chats/get_unread_count.php).
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE);
exit;
