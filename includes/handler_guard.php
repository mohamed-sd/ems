<?php
/**
 * حارس المعالجات — includes/handler_guard.php (إغلاق فئة B من مسح دَين الحارس)
 * ───────────────────────────────────────────────────────────────────────────
 * المعالجُ (AJAX/POST) لا تظلّه مظلةُ insidebar — فيرث صلاحيةَ **شاشته الأم**
 * صراحةً: قراءةُ بياناتها تتطلب رؤيتَها، وأفعالُها تتطلب كتابتَها.
 * الرفضُ JSON بعقد المعالجات (لا Location) ويُسجَّل HANDLER_DENY.
 *
 *   require_once __DIR__ . '/../includes/handler_guard.php';
 *   ems_guard_handler($conn, 'Contracts/contracts.php', 'edit');
 */

require_once __DIR__ . '/permissions_helper.php';

if (!function_exists('ems_guard_handler')) {
    /** @param string $need view|add|edit|delete — والسوبر يمرّ */
    function ems_guard_handler(mysqli $conn, $parentScreen, $need = 'view')
    {
        if (!isset($_SESSION['user'])) {
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(401);
            die(json_encode(array('success' => false, 'error' => 'الجلسة منتهية'), JSON_UNESCAPED_UNICODE));
        }
        if (strval($_SESSION['user']['role'] ?? '') === '-1') { return true; }
        $pp = check_page_permissions($conn, $parentScreen);
        $flag = 'can_' . (in_array($need, array('view', 'add', 'edit', 'delete'), true) ? $need : 'view');
        // «الكتابة» تُقبل بأيٍّ من أعلام الكتابة حين تكون الشاشةُ بابَ الفعل نفسه
        $ok = !empty($pp[$flag]) || ($need === 'edit' && !empty($pp['can_add']));
        if (!$ok) {
            if (function_exists('log_security_event')) {
                log_security_event('HANDLER_DENY',
                    'handler=' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? '?'))
                    . ' parent=' . $parentScreen . ' need=' . $need
                    . ' role=' . strval($_SESSION['user']['role'] ?? '?'));
            }
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(403);
            die(json_encode(array('success' => false,
                'error' => 'لا صلاحيةَ ' . ($need === 'view' ? 'عرضٍ' : 'كتابةٍ') . ' على شاشة هذا المعالج'),
                JSON_UNESCAPED_UNICODE));
        }
        return true;
    }
}
