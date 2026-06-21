<?php
/**
 * get_breakdown_count.php — عدد البلاغات «الجديدة» لشركة المستخدم (لشارة التوبار).
 * يُستدعى عبر XHR فقط (حارس config.php يفرض X-Requested-With + جلسة صالحة).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once '../config.php';
require_once __DIR__ . '/mnt_helpers.php';

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

$count = ($company_id > 0) ? mnt_new_breakdowns_count($conn, $company_id) : 0;

// إيقاف أي output buffers قبل إرجاع JSON (نمط chats/get_unread_count.php).
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['count' => intval($count)], JSON_UNESCAPED_UNICODE);
exit;
