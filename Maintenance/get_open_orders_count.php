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
    // عبر البوابة: العزل بالشركة يُحقن؛ is_deleted مستبعَد تلقائيًّا (mnt_order soft)
    $count = ems_tenant_db()->count('mnt_order', array(
        'whereRaw' => "is_auto = 1 AND state IN ('بلاغ', 'تنفيذ', 'فحص')"));
}

// إيقاف أي output buffers قبل إرجاع JSON (نمط chats/get_unread_count.php).
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE);
exit;
