<?php
/**
 * ملف الإعدادات الرئيسي - EMS Configuration
 * @version 2.0 - Enhanced Security
 * @date 2026-03-01
 */

// منع الوصول المباشر لملفات الإعدادات من المتصفح.
if (PHP_SAPI !== 'cli') {
    $scriptFilename = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : '';
    if ($scriptFilename !== '' && $scriptFilename === __FILE__) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('403 Forbidden');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. تحميل نظام الأمان المركزي
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/performance.php';

ems_performance_bootstrap();

// ═══════════════════════════════════════════════════════════════════════════
// UTF-8 Global Runtime (System-wide Encoding Hardening)
// ═══════════════════════════════════════════════════════════════════════════

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}

ini_set('default_charset', 'UTF-8');

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!defined('EMS_DIGIT_SYSTEM')) {
    // Allowed values: arabic-indic | latin
    define('EMS_DIGIT_SYSTEM', 'latin');
}

if (!function_exists('ems_fix_mojibake_output')) {
    function ems_fix_mojibake_output($buffer)
    {
        $contentType = '';
        if (function_exists('headers_list')) {
            $headers = headers_list();
            foreach ($headers as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $contentType = strtolower($h);
                    break;
                }
            }
        }

        // Only normalize HTML responses.
        if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
            return $buffer;
        }

        // Remove UTF-8 BOM if present.
        if (substr($buffer, 0, 3) === "\xEF\xBB\xBF") {
            $buffer = substr($buffer, 3);
        }

        $pattern = '/[\x{00C2}\x{00C3}\x{00D8}\x{00D9}\x{00E2}\x{00F0}][^\s<>{}\[\]"\'=]{0,24}/u';
        $fixed = preg_replace_callback($pattern, function ($m) {
            if (function_exists('mb_convert_encoding')) {
                $decoded = @mb_convert_encoding($m[0], 'UTF-8', 'Windows-1252');
                if ($decoded !== false && $decoded !== '') {
                    return $decoded;
                }
            }

            return $m[0];
        }, $buffer);

        $fixed = is_string($fixed) ? $fixed : $buffer;

        if (stripos($fixed, 'number-format-unifier.js') === false) {
            $digitSystem = defined('EMS_DIGIT_SYSTEM') ? EMS_DIGIT_SYSTEM : 'latin';
            $scriptPath = function_exists('ems_url')
                ? ems_url('assets/js/number-format-unifier.js')
                : '/ems/assets/js/number-format-unifier.js';

            $inject = '<script>window.EMS_DIGIT_SYSTEM=' . json_encode($digitSystem) . ';</script>'
                . '<script src="' . htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8') . '" defer></script>';

            if (stripos($fixed, '</body>') !== false) {
                $fixed = preg_replace('/<\/body>/i', $inject . '</body>', $fixed, 1);
            } else {
                $fixed .= $inject;
            }
        }

        return $fixed;
    }
}

if (PHP_SAPI !== 'cli') {
    $handlers = function_exists('ob_list_handlers') ? ob_list_handlers() : array();
    if (!in_array('ems_fix_mojibake_output', $handlers, true)) {
        ob_start('ems_fix_mojibake_output');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. إعدادات PHP الأمنية
// ═══════════════════════════════════════════════════════════════════════════

// إخفاء معلومات PHP من الهيدر (لمنع كشف الإصدار)
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

// تعطيل عرض الأخطاء في الإنتاج (Production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// تسجيل الأخطاء في ملف log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// حماية من file inclusion attacks
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// ═══════════════════════════════════════════════════════════════════════════
// 3. إعدادات قاعدة البيانات
// ═══════════════════════════════════════════════════════════════════════════

// PHP 8 compatibility: keep legacy mysqli false-return flow instead of exceptions
// because most modules handle DB errors using mysqli_query() result checks.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// $host = "srv1986.hstgr.io";
// $user = "u359449619_ems";
// $pass = "Aaammm@1110"; // ← كلمة المرور الصحيحة
// $db   = "u359449619_ems";

 $host = "localhost";
 $user = "root";
 $pass = ""; // ← كلمة المرور الصحيحة
 $db   = "equipation_manage";

// Establish Secure Connection
$conn = new mysqli($host, $user, $pass, $db);

// Check Connection
if ($conn->connect_error) {
    // تسجيل الخطأ
    error_log("Database Connection Failed: " . $conn->connect_error);

    // عرض رسالة عامة للمستخدم (بدون كشف تفاصيل فنية)
    die('
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>خطأ في الاتصال</title>
            <style>
                body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>⚠️ خطأ في الاتصال</h1>
                <p>عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.</p>
            </div>
        </body>
        </html>
    ');
}

// تعيين charset لمنع SQL Injection عبر encoding
$conn->set_charset("utf8mb4");

// تهيئة إعدادات أداء اتصال قاعدة البيانات
ems_optimize_db_session($conn);

/**
 * Check if a table has a specific column (standalone for early auth guard).
 */
function ems_table_has_column_raw($conn, $tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);

    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $sql = "SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . mysqli_real_escape_string($conn, $safeColumn) . "'";
    $res = @mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Force logout immediately if logged-in user's company is suspended.
 */
function ems_force_logout_if_company_suspended($conn) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    $sessionCompanyUser = isset($_SESSION['company_user']) ? $_SESSION['company_user'] : null;

    if (!$sessionUser && !$sessionCompanyUser) {
        return;
    }

    if ($sessionUser && isset($sessionUser['role']) && strval($sessionUser['role']) === '-1') {
        return;
    }

    $companyId = 0;
    if ($sessionUser && isset($sessionUser['company_id'])) {
        $companyId = intval($sessionUser['company_id']);
    }
    if ($companyId <= 0 && $sessionCompanyUser && isset($sessionCompanyUser['company_id'])) {
        $companyId = intval($sessionCompanyUser['company_id']);
    }

    if ($companyId <= 0) {
        return;
    }

    $requestUri = isset($_SERVER['REQUEST_URI']) ? str_replace('\\', '/', $_SERVER['REQUEST_URI']) : '';
    $inCompanyPortal = strpos($requestUri, '/company/') !== false;
    $loginPath = $inCompanyPortal ? ems_url('company/login.php') : ems_url('login.php');

    $isOnLoginPage = false;
    if ($inCompanyPortal) {
        $isOnLoginPage = strpos($requestUri, '/company/login.php') !== false;
    } else {
        $loginPathPattern = '#^' . preg_quote(ems_url('login.php'), '#') . '(?:\?|$)#';
        $isOnLoginPage = preg_match($loginPathPattern, $requestUri) === 1;
    }

    if (!ems_table_has_column_raw($conn, 'admin_companies', 'status')) {
        return;
    }

    $stmt = @mysqli_prepare($conn, 'SELECT status FROM admin_companies WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    $companyStatus = $row && isset($row['status']) ? strtolower(trim($row['status'])) : 'active';
    if ($companyStatus === 'active') {
        return;
    }

    unset($_SESSION['company_user']);
    unset($_SESSION['user']);
    unset($_SESSION['user_project_scope']);
    unset($_SESSION['role_permissions']);
    unset($_SESSION['plan_modules']);

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();

    if (!$isOnLoginPage) {
        header('Location: ' . $loginPath . '?msg=' . urlencode('تم تسجيل الخروج: الشركة موقوفة حالياً.'));
        exit();
    }
}

ems_force_logout_if_company_suspended($conn);

function ems_is_ajax_endpoint_request()
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
    if ($scriptName === '') {
        return false;
    }

    return preg_match('/^(get_.*\.php|.*_handler\.php)$/i', $scriptName) === 1;
}

function ems_ajax_guard_response($statusCode, $message)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('success' => false, 'message' => $message), JSON_UNESCAPED_UNICODE);
    exit();
}

function ems_ajax_rate_limit_check($key, $maxAttempts, $windowSeconds)
{
    $bucketKey = 'ems_ajax_rl_' . md5($key . '|' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0'));
    $now = time();

    if (!isset($_SESSION[$bucketKey]) || !is_array($_SESSION[$bucketKey])) {
        $_SESSION[$bucketKey] = array('count' => 0, 'start' => $now);
    }

    $start = isset($_SESSION[$bucketKey]['start']) ? intval($_SESSION[$bucketKey]['start']) : $now;
    if (($now - $start) >= $windowSeconds) {
        $_SESSION[$bucketKey] = array('count' => 0, 'start' => $now);
    }

    $_SESSION[$bucketKey]['count'] = intval($_SESSION[$bucketKey]['count']) + 1;
    return intval($_SESSION[$bucketKey]['count']) <= $maxAttempts;
}

function ems_enforce_ajax_endpoint_security()
{
    if (!ems_is_ajax_endpoint_request()) {
        return;
    }

    $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower(trim($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';
    if ($requestedWith !== 'xmlhttprequest') {
        ems_ajax_guard_response(403, 'Direct endpoint access is blocked');
    }

    $hasSession = isset($_SESSION['user']) || isset($_SESSION['company_user']) || isset($_SESSION['super_admin']);
    if (!$hasSession) {
        ems_ajax_guard_response(401, 'غير مصرح');
    }

    $script = isset($_SERVER['SCRIPT_NAME']) ? strtolower(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])) : '';
    $isSensitive = (strpos($script, '_handler.php') !== false)
        || (strpos($script, '/chats/get_messages.php') !== false)
        || (strpos($script, '/chats/get_unread_count.php') !== false)
        || (strpos($script, '/timesheet/get_timesheet_data.php') !== false)
        || (strpos($script, '/timesheet/get_timesheet.php') !== false);

    $maxAttempts = $isSensitive ? 45 : 180;
    $windowSeconds = 60;
    $rateKey = $script !== '' ? $script : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'ajax');
    if (!ems_ajax_rate_limit_check($rateKey, $maxAttempts, $windowSeconds)) {
        ems_ajax_guard_response(429, 'تم تجاوز الحد المسموح للطلبات. حاول لاحقاً.');
    }
}

ems_enforce_ajax_endpoint_security();

// ═══════════════════════════════════════════════════════════════════════════
// 4. Global Security Functions Shortcuts
// ═══════════════════════════════════════════════════════════════════════════

/**
 * اختصار لتنظيف المدخلات من قاعدة البيانات
 */
function escape($data) {
    global $conn;
    return db_escape($conn, $data);
}

/**
 * اختصار لتنفيذ استعلام آمن
 */
function query_safe($query, $params = [], $types = '') {
    global $conn;
    return db_query($conn, $query, $params, $types);
}

/**
 * Check if a table has a specific column (cached).
 */
function db_table_has_column($conn, $tableName, $columnName) {
    static $cache = array();

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $key = strtolower($safeTable . '::' . $safeColumn);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if ($safeTable === '' || $safeColumn === '') {
        $cache[$key] = false;
        return false;
    }

    $sql = "SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . mysqli_real_escape_string($conn, $safeColumn) . "'";
    $res = @mysqli_query($conn, $sql);
    $cache[$key] = $res && mysqli_num_rows($res) > 0;

    return $cache[$key];
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. Activity Log System Bootstrap
// ═══════════════════════════════════════════════════════════════════════════
// Loads PSR-4 autoloader for App\\ namespace and boots ActivityLogMiddleware.
// Must be loaded AFTER $conn is established and session is active.
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/app/bootstrap.php';
}

// ═══════════════════════════════════════════════════════════════════════════
// END OF CONFIG
// ═══════════════════════════════════════════════════════════════════════════
?>
