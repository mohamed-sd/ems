<?php
/**
 * ملف الإعدادات الرئيسي - EMS Configuration
 * @version 2.0 - Enhanced Security
 * @date 2026-03-01
 */

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

        return is_string($fixed) ? $fixed : $buffer;
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

$host = "localhost";
$user = "root";
$pass = "";
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
// END OF CONFIG
// ═══════════════════════════════════════════════════════════════════════════
?>