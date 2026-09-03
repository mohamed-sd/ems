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
// 0. متغيرات البيئة (.env) — أول ما يُحمَّل: كل الأسرار منه حصريًا (ADR-04)
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/env.php';

/* ── INJ-0492 · نزعُ نصِّ الرسالةِ من الرابطِ **في أوّلِ ما يُحمَّل** ──────────────
     في الشجرةِ ١٥٦ ملفًّا فيها كتلٌ قديمةٌ تطبع `$_GET['msg']` كما ورد. وقد صار
     الماصُّ في `permissions_helper.php` ينزعه — لكنَّ ذلك الملفَّ لا يبلغ كلَّ
     مسار. و`config.php` يبلغها كلَّها. فالنزعُ هنا يجعل تلك الكتلَ **خاملةً
     بنيويًّا** لا بالاعتمادِ على ترتيبِ تضمين. (والرسائلُ الحقيقيةُ تُودَع
     الجلسةَ بـ`ems_flash_set()` قبل التحويل، أو تُمرَّر برمزٍ من قائمةٍ مغلقة.) */
if (PHP_SAPI !== 'cli' && isset($_GET['msg'])) { unset($_GET['msg'], $_REQUEST['msg']); }

// فهرس ثوابت الأدوار — المصدر الوحيد لأرقام الأدوار (ADR-07؛ لا أرقام سحرية جديدة)
require_once __DIR__ . '/includes/roles.php';

// ═══════════════════════════════════════════════════════════════════════════
// 1. تحميل نظام الأمان المركزي
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/security.php';
// UI-DEF-11: تنسيقُ التاريخِ الموحَّدُ للعرض — دالةٌ واحدةٌ بدل استدعاءٍ متفرق
require_once __DIR__ . '/includes/date_format.php';
require_once __DIR__ . '/includes/performance.php';
require_once __DIR__ . '/includes/employee_types.php'; // أنواع الموظفين + فلتر أنواع التشغيل (الموجة 1)
require_once __DIR__ . '/includes/actor_helper.php';   // تسمية الفاعل الموحّدة (الموظف خلف الحساب)

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
    // حقن حقل csrf_token المخفي تلقائياً في كل فورم POST مُولّد من الخادم.
    if (function_exists('ems_inject_csrf_fields') && !in_array('ems_inject_csrf_fields', $handlers, true)) {
        ob_start('ems_inject_csrf_fields');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// CSRF: وضع التطبيق (false = مراقبة/تسجيل فقط، true = حجب فعلي 403)
// المرحلة 1 الحالية: مراقبة فقط حتى نتأكد من تغطية كل المسارات من السجلات.
// ═══════════════════════════════════════════════════════════════════════════
if (!defined('EMS_CSRF_ENFORCE')) {
    define('EMS_CSRF_ENFORCE', false);
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

// بيانات الاتصال من .env حصريًا (ADR-04 · P0-4 2026-07-08): لا قيمة اتصالٍ في
// الكود إطلاقًا، ولا fallback صامت — أُزيل root كهدفٍ افتراضيٍّ نهائيًّا (السقوط
// الصامت إليه كان يُلغي مكاسب المرحلة 0 كلها عند أي خللٍ في تحميل .env).
// غياب/تلف .env أو نقص مفاتيح الاتصال = فشلٌ فوريٌّ واضح (Fail-Fast) في كل
// مسارات الدخول: الويب (500 + صفحة عامة) والـ CLI (STDERR + exit 1).
if (!function_exists('ems_db_config_fail')) {
    function ems_db_config_fail($reason)
    {
        error_log('EMS FATAL [ADR-04/P0-4]: ' . $reason);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[EMS] FATAL [ADR-04/P0-4]: " . $reason . "\n");
            exit(1);
        }
        http_response_code(500);
        die('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>خطأ في إعدادات النظام</title><style>body{font-family:Cairo,Arial;text-align:center;padding:50px;background:#f5f5f5}.error{background:#fff;padding:40px;border-radius:10px;max-width:500px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,.1)}h1{color:#dc2626}</style></head><body><div class="error"><h1>⚠️ خطأ في إعدادات النظام</h1><p>تعذر تحميل إعدادات الاتصال. يرجى إبلاغ مسؤول النظام.</p></div></body></html>');
    }
}

if (!ems_env_loaded()) {
    ems_db_config_fail('.env مفقود أو غير مقروء — أنشئه من القالب .env.example (لا fallback بعد P0-4)');
}

$host = ems_env('DB_HOST');
$user = ems_env('DB_USER');
$pass = ems_env('DB_PASS'); // يجوز أن تكون فارغةً صراحةً (خيار مشغّلٍ مقصود) — لا افتراض كود
$db   = ems_env('DB_NAME');

if ($host === null || $host === '' || $user === null || $user === '' || $db === null || $db === '' || $pass === null) {
    ems_db_config_fail('مفاتيح الاتصال ناقصة في .env — المطلوب: DB_HOST وDB_USER وDB_PASS (ولو فارغة) وDB_NAME');
}

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
                <p>عذرا، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقا.</p>
            </div>
        </body>
        </html>
    ');
}

// تعيين charset لمنع SQL Injection عبر encoding
$conn->set_charset("utf8mb4");
// مواءمة ترتيب الاتصال مع ترتيب الأعمدة الموحّد (utf8mb4_unicode_ci) — يمنع خطأ
// «Illegal mix of collations» عند مقارنة عمود بنتيجة CAST/تعبير تأخذ ترتيب الاتصال.
// ◆ 2026-08-06: SET NAMES ... COLLATE لا SET collation_connection وحدها — فالأخيرة
// تترك معاملات prepared على ترتيب utf8mb4 الافتراضي (general_ci على MariaDB 11.4)
// فينفجر كل «DATE_FORMAT(..)=?» بالخلط نفسه (شاشة المدير المالي 500). SET NAMES
// COLLATE يوحّد ترتيب المعاملات مع الاتصال — وset_charset قبله يُبقي escaping سليمًا.
mysqli_query($conn, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

// N-24 (update0004 · NFR-09): حارس الخمس ثوان — «لا احتساب يتجاوز خمس ثوان
// داخل الطلب». يرصد كل طلب ويب تجاوزها في guard_denials بكود request_over_5s
// (مراقبة كاشفة — والمنع البنيوي عبر JobQueueService::mustQueue في الخدمات
// الثقيلة التي تصرح بتقديرها). لا يمس CLI ولا الكرون.
if (PHP_SAPI !== 'cli' && !defined('EMS_REQUEST_GUARD_SET')) {
    define('EMS_REQUEST_GUARD_SET', 1);
    register_shutdown_function(function () use ($conn) {
        $elapsed = microtime(true) - (isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true));
        if ($elapsed <= 5.0 || !($conn instanceof mysqli)) { return; }
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . basename($_SERVER['SCRIPT_NAME']) : 'unknown';
        $co = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
        $uid = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        @$conn->query("INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code)
            VALUES ({$co}, 'request_over_5s', {$uid}, '" . $conn->real_escape_string(mb_substr($script, 0, 118)) . "', '"
            . $conn->real_escape_string(round($elapsed, 1) . 's — يمر عبر الطابور') . "')");
    });
}

// N-25 (update0004 · NFR-02): توحيد ساعة التطبيق على ساعة القاعدة — القياس
// الحاكم أثبت أن التخزين محلي (MySQL SYSTEM = توقيت الخادم) وPHP كان UTC،
// فكانت ساعتان مختلطتان (فارق موسمي 2-3 ساعات بالتوقيت الصيفي). التوحيد
// إعدادٌ لا ترحيل (الملحق §12.6-⑤) — والإزاحة الموسمية تمنع الترحيل المسطح.
// الرجوع: EMS_APP_TIMEZONE=UTC في .env. وخطة ترحيل UTC الكاملة جاهزة بلا
// تشغيل في docs/nfr/N-25_utc_migration_plan_ar.md — قرار مالك.
date_default_timezone_set(function_exists('ems_env') ? (string) ems_env('EMS_APP_TIMEZONE', 'Africa/Cairo') : 'Africa/Cairo');

// تهيئة إعدادات أداء اتصال قاعدة البيانات
ems_optimize_db_session($conn);

// حارس مطابقة فهرس الأدوار (ADR-07): مرة لكل جلسة — أي انحرافٍ بين الثوابت
// وجدول roles الحي يُسجَّل في security.log (role_constant_mismatch).
if (PHP_SAPI !== 'cli'
    && session_status() === PHP_SESSION_ACTIVE
    && empty($_SESSION['ems_roles_verified'])) {
    ems_roles_verify_against_db($conn);
    $_SESSION['ems_roles_verified'] = 1;
}

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
        /* INJ-0492: الجلسةُ **هُدمت** قبل هذا السطر — فوميضُ الجلسةِ يضيع.
           والرسالةُ هنا تُمرَّر **برمزٍ** لا بنصّ: `login.php` يملك نصَّه، فلا
           يُطبع شيءٌ يأتي من الرابط. (رمزٌ لا يعرفه المستقبِلُ لا يُظهر شيئًا.) */
        header('Location: ' . $loginPath . '?m=suspended');
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

    /* ══ تجميدُ الشركةِ الواحدة (FREEZE · single-company) ═══════════════════
       جلسةُ المزوّد `$_SESSION['super_admin']` لم تعد تُنشأ (بابُها 403)، فبقاؤها
       في فحصِ الجلسةِ سطحُ قبولٍ لجلسةٍ متبقّيةٍ في متصفّح — تُنزع. */
    $hasSession = isset($_SESSION['user']) || isset($_SESSION['company_user']);
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
        ems_ajax_guard_response(429, 'تم تجاوز الحد المسموح للطلبات. حاول لاحقا.');
    }
}

ems_enforce_ajax_endpoint_security();

// حارس صلاحية الفعل لمعالجات AJAX (ADR-06): يسأل «هل يحق لك هذا الفعل؟» بعد
// أن تأكّد الحارس أعلاه «من أنت». يعمل فقط على معالجات AJAX (get_*/*_handler)
// بنفس بوابة الحارس أعلاه — لا على الصفحات العادية. مراقبة/إنفاذ من .env.
if (ems_is_ajax_endpoint_request()) {
    require_once __DIR__ . '/includes/action_guard.php';
    if (function_exists('ems_enforce_action_permission')) {
        ems_enforce_action_permission($conn);
    }
}

// الحارس المركزي لـ CSRF (يستثني /api/ و/admin/؛ مراقبة أو حجب حسب EMS_CSRF_ENFORCE)
if (function_exists('ems_enforce_csrf_protection')) {
    ems_enforce_csrf_protection();
}

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
// تجميد DDL وقت التشغيل (ADR-03 · المرحلة 0) — نفس نمط EMS_CSRF_ENFORCE:
// false = مراقبة: يُنفَّذ كما كان، مع تسجيل كل تنفيذٍ فعليٍّ في logs/security.log.
// true  = تجميد: لا يُنفَّذ؛ تُسجَّل المحاولة فقط (RUNTIME_DDL_BLOCKED).
// يُقلَب إلى true بعد أسبوع مراقبةٍ خالٍ من RUNTIME_DDL_EXECUTED.
// ═══════════════════════════════════════════════════════════════════════════
if (!defined('EMS_DDL_FREEZE')) {
    // ADR-03 · المرحلة 0 (قُلب 2026-07-08): يُقرأ من .env قابلًا للتراجع — الافتراض false
    // (fail-safe: لا حجب إن غاب المفتاح/الملف). القلب إلى true بعد إثبات تحييد الـ21
    // (تدقيق ساكن: كل نداء no-op على المخطّط الحالي). Rollback: EMS_DDL_FREEZE=false في .env.
    define('EMS_DDL_FREEZE', ems_env('EMS_DDL_FREEZE', 'false') === 'true');
}

/**
 * الغلاف المركزي الوحيد لأي DDL وقت التشغيل — كل تغيير مخطّطٍ جديدٍ يُكتب
 * ترحيلًا في database/migrations ويُطبَّق بـ migrate.php، لا استدعاءً جديدًا هنا
 * (قائمة التجميد · EQUIP-ARC-R02 §5).
 */
function ems_runtime_ddl($conn, $sql, $origin = '')
{
    if (EMS_DDL_FREEZE === true) {
        if (function_exists('log_security_event')) {
            log_security_event('RUNTIME_DDL_BLOCKED', $origin . ' :: ' . substr(trim($sql), 0, 300));
        }
        return false;
    }

    $ok = @mysqli_query($conn, $sql);

    // CREATE ... IF NOT EXISTS على جدولٍ قائم = لا-شيء (تحذير 1050 فقط) — لا يُسجَّل
    // حتى لا يمتلئ السجل من الصفحات التي تستدعيه غير مشروطٍ مع كل طلب.
    $isNoop = $ok && stripos($sql, 'IF NOT EXISTS') !== false && mysqli_warning_count($conn) > 0;
    if (!$isNoop && function_exists('log_security_event')) {
        log_security_event($ok ? 'RUNTIME_DDL_EXECUTED' : 'RUNTIME_DDL_FAILED', $origin . ' :: ' . substr(trim($sql), 0, 300));
    }

    return $ok;
}

// ═══════════════════════════════════════════════════════════════════════════
// بوابة العزل / DAO (ADR-02 · المرحلة 1) — للشاشات المُهاجَرة والوحدات الجديدة.
// العقد: docs/TENANT_GATE_CONTRACT_ar.md — الشاشات القديمة تبقى على mysqli
// الخام حتى دور هجرتها (لا تغيير في سلوكها).
// ═══════════════════════════════════════════════════════════════════════════

/**
 * مصنع كسول لبوابة العزل بهوية الجلسة الحالية. الاستخدام في شاشةٍ مُهاجَرة:
 *   $gate = ems_tenant_db();
 *   $rows = $gate->select('mnt_breakdown', ['orderBy' => 'id DESC', 'limit' => 50]);
 * تحميل الأصناف مباشر (لا اعتماد على المُحمِّل التلقائي — يعمل في CLI أيضًا).
 */
function ems_tenant_db()
{
    static $gate = null;
    if ($gate === null) {
        require_once __DIR__ . '/app/Core/TenantGateException.php';
        require_once __DIR__ . '/app/Core/TenantRegistry.php';
        require_once __DIR__ . '/app/Core/TenantContext.php';
        require_once __DIR__ . '/app/Core/TenantDb.php';
        global $conn;
        $gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
    }
    return $gate;
}

/**
 * بوابة كونسول المزوّد (دفعة هـ-0 · 2026-07-16) — عابرة الشركات من جلسة المدير
 * الأعلى ($_SESSION['super_admin']) حصرًا، fail-closed عند غيابها (استثناء).
 * تفتح جداول T_PLATFORM وتُبقي رفض بوابة المستأجر لها كما هو. كل إنشاءٍ يُقيَّد
 * (tenant_gate_platform_context) ككل عبورٍ عابرٍ في العقد.
 */
function ems_platform_db()
{
    /* ══ تجميدُ الشركةِ الواحدة (FREEZE · single-company) ═══════════════════
       ◆ **البوابةُ مُعطَّلةٌ لا محذوفة**: 21 ملفًّا تحت `admin/` يستدعيها —
         وصفرٌ خارجَه (مقيسٌ بمسحِ الشجرةِ كلِّها) — فالحذفُ يكسر التحليلَ
         بينما الرميُ يُبقي التوقيعَ ويُفشِل الاستدعاءَ صراحةً.
       ◆ **ورميٌ لا ردُّ null**: null يمرُّ صامتًا إلى `->fetchAll()` فيصير
         خطأً غامضًا؛ والاستثناءُ يُسمّي السببَ في السجلِّ من أوّلِ سطر.
       ⛔ لاستعادةِ طبقةِ المنصّة: احذفْ الرميَ وأعِدْ الجسمَ المحفوظَ أدناه.
       ── الجسمُ الأصليُّ (محفوظًا للنقض) ──
          static $pgate = null;
          if ($pgate === null) {
              require_once __DIR__ . '/app/Core/TenantGateException.php';
              require_once __DIR__ . '/app/Core/TenantRegistry.php';
              require_once __DIR__ . '/app/Core/TenantContext.php';
              require_once __DIR__ . '/app/Core/TenantDb.php';
              global $conn;
              $pgate = new \App\Core\TenantDb($conn,
                  \App\Core\TenantContext::fromSuperAdminSession(), true);
          }
          return $pgate;                                                     */
    throw new \RuntimeException('Platform DB disabled: single-company mode');
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. Activity Log System Bootstrap
// ═══════════════════════════════════════════════════════════════════════════
// Loads PSR-4 autoloader for App\\ namespace and boots ActivityLogMiddleware.
// Must be loaded AFTER $conn is established and session is active.
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/app/bootstrap.php';
}

/* ══ FR-SEC-008 — العنوانُ المباشرُ يسأل عن مساحتِه ═══════════════════════
   ◆ أربعُ قنواتٍ كانت تنفّذ عزلَ المساحةِ والعنوانُ المباشرُ **لا يسأل أصلًا**
     — قِيس حيًّا: الدورُ 4 يفتح `Equipments/equipments.php` بـ200 وهو مُعلَنٌ
     FORBIDDEN في مساحتِه. وعزلٌ يُلتفُّ عليه بكتابةِ الرابطِ ليس عزلًا.
   ◆ **والوضعُ الافتراضيُّ ظلٌّ لا إنفاذ**: يُقاس ما كان سيُمنَع ولا يُمنع أحد،
     والقلبُ بـ`EMS_SPACE_URL_GUARD=enforce` بقرارِ مالكٍ على أثرٍ مقيس. */
if (php_sapi_name() !== 'cli') {
    $__sug = __DIR__ . '/includes/space_url_guard.php';
    if (is_file($__sug)) { require_once $__sug; ems_space_url_guard(); }
}

// ═══════════════════════════════════════════════════════════════════════════
// END OF CONFIG
// ═══════════════════════════════════════════════════════════════════════════
?>
