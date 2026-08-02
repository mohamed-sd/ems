<?php
/**
 * Tickets/get_tickets_count.php — عدّاد شارة البلاغات في الشريط العلوي.
 *
 * يعيد عدد البلاغات **المفتوحة ضمن نطاق رؤية المستخدم** لا كل بلاغات الشركة:
 *   • مدير البلاغات والمدير الأعلى: كل المفتوحة.
 *   • غيرهما: ما وُجِّه إلى شجرة دوره أو ما أبلغ عنه بنفسه.
 * يُستدعى عبر XHR كل دقيقة، فيبقى استعلامَ عدٍّ واحدًا رخيصًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(array('count' => 0));
    exit();
}
include __DIR__ . '/../config.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
if (!$ctx['is_super'] && $ctx['company_id'] <= 0) {
    echo json_encode(array('count' => 0));
    exit();
}

$count = 0;
try {
    $where = "stage NOT IN ('closed','cancelled')";
    if (!$ctx['is_super'] && $ctx['role'] !== EMS_ROLE_TICKETS_MGR) {
        $vis = tkt_visible_owner_role_ids(intval($ctx['role']));
        $in = implode(',', array_map('intval', $vis));
        $uid = intval($ctx['user_id']);
        $where .= " AND (owner_role_id IN ($in) OR reporter_user_id = $uid OR created_by = $uid)";
    }
    $count = (int) tkt_gate($ctx['is_super'])->count('tickets', array('whereRaw' => $where));
} catch (\Throwable $e) {
    error_log('tickets count failed: ' . $e->getMessage());
    $count = 0;
}

// إيقاف أي output buffers قبل إرجاع JSON (حاقن CSRF في config.php يفتح واحدًا)
// — نفس نمط get_breakdown_count.php وchats/get_unread_count.php.
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

echo json_encode(array('count' => intval($count)), JSON_UNESCAPED_UNICODE);
exit;
