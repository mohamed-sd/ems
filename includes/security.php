<?php
/**
 * ملف الأمان المركزي - EMS Security Core
 * يحتوي على جميع الوظائف الأمنية للنظام
 * 
 * @version 2.0
 * @date 2026-03-01
 */

// منع الوصول المباشر للملف
if (!defined('SECURITY_INCLUDED')) {
    define('SECURITY_INCLUDED', true);
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. إعدادات الجلسة الآمنة (Secure Session Configuration)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * بدء جلسة آمنة مع إعدادات محسنة
 */
function secure_session_start() {
    // منع بدء جلسة جديدة إذا كانت موجودة
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    
    // إعدادات أمان الجلسة
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // تكوين cookie الجلسة
    $cookieParams = [
        'lifetime' => 0,              // تنتهي عند إغلاق المتصفح
        'path' => '/',                // متاح لكل المسارات
        'domain' => '',               // النطاق الحالي
        'secure' => $secure,          // HTTPS فقط إذا كان متاحاً
        'httponly' => true,           // لا يمكن الوصول عبر JavaScript
        'samesite' => 'Strict'        // حماية من CSRF
    ];
    
    session_set_cookie_params($cookieParams);
    
    // إعدادات أمان إضافية
    ini_set('session.use_strict_mode', 1);           // رفض session IDs غير معروفة
    ini_set('session.use_only_cookies', 1);          // استخدام cookies فقط
    ini_set('session.cookie_httponly', 1);           // حماية من XSS
    ini_set('session.cookie_secure', $secure ? 1 : 0);
    ini_set('session.use_trans_sid', 0);             // عدم نقل session ID في URL
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.sid_length', 48);               // طول أكبر لـ session ID
    ini_set('session.sid_bits_per_character', 6);
    
    // بدء الجلسة
    session_start();
    
    // تجديد session ID بشكل دوري للحماية من Session Fixation
    if (!isset($_SESSION['created_at'])) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    } else if (time() - $_SESSION['created_at'] > 1800) { // كل 30 دقيقة
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
    
    // تحقق من انتهاء الجلسة (Session Timeout)
    check_session_timeout();
    
    // التحقق من IP و User Agent للحماية من Session Hijacking
    validate_session_fingerprint();
    
    return true;
}

/**
 * التحقق من انتهاء صلاحية الجلسة (Session Timeout)
 */
function check_session_timeout() {
    $timeout = 3600; // 60 دقيقة بدون نشاط
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > $timeout) {
            session_unset();
            session_destroy();
            
            // إعادة توجيه لصفحة تسجيل الدخول
            header("Location: /ems/index.php?timeout=1");
            exit();
        }
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * التحقق من بصمة الجلسة (Session Fingerprint) للحماية من Session Hijacking
 */
function validate_session_fingerprint() {
    if (php_sapi_name() === 'cli') {
        return;
    }

    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $fingerprint = hash('sha256', 
        $userAgent . 
        $remoteAddr
    );
    
    if (isset($_SESSION['fingerprint'])) {
        if ($_SESSION['fingerprint'] !== $fingerprint) {
            // محاولة اختراق - تدمير الجلسة
            session_unset();
            session_destroy();
            header("Location: /ems/index.php?security=violated");
            exit();
        }
    } else {
        $_SESSION['fingerprint'] = $fingerprint;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. حماية CSRF (Cross-Site Request Forgery)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * توليد CSRF Token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (!isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token_time'] = time();
    }
    
    // تجديد التوكن كل ساعة
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * طباعة حقل CSRF مخفي في الفورم
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * الحصول على CSRF Token كـ meta tag للـ AJAX
 */
function csrf_meta() {
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . $token . '">';
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. حماية من XSS (Cross-Site Scripting)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * تنظيف النص من أكواد HTML/JavaScript الخبيثة
 */
function clean_output($data) {
    if (is_array($data)) {
        return array_map('clean_output', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * اختصار لـ clean_output
 */
function e($data) {
    return clean_output($data);
}

/**
 * تنظيف URL
 */
function clean_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * تنظيف Email
 */
function clean_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * تنظيف النص من tags HTML (مع السماح ببعض tags الآمنة)
 */
function clean_html($html, $allowed_tags = '<p><br><strong><em><u><a>') {
    return strip_tags($html, $allowed_tags);
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. التحقق من صحة المدخلات (Input Validation)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * التحقق من صحة البريد الإلكتروني
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من صحة رقم الهاتف (أرقام فقط والعلامة +)
 */
function validate_phone($phone) {
    return preg_match('/^[\+]?[0-9]{9,15}$/', $phone);
}

/**
 * التحقق من صحة التاريخ
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * التحقق من صحة الرقم الصحيح
 */
function validate_integer($value, $min = null, $max = null) {
    if (!is_numeric($value) || intval($value) != $value) {
        return false;
    }
    
    $value = intval($value);
    
    if ($min !== null && $value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}

/**
 * التحقق من طول النص
 */
function validate_length($text, $min = 0, $max = PHP_INT_MAX) {
    $length = mb_strlen($text, 'UTF-8');
    return $length >= $min && $length <= $max;
}

/**
 * تنظيف وتحويل المدخلات الآمن
 */
function sanitize_input($data, $type = 'string') {
    $data = trim($data);
    
    switch ($type) {
        case 'int':
            return intval($data);
        case 'float':
            return floatval($data);
        case 'email':
            return clean_email($data);
        case 'url':
            return clean_url($data);
        case 'string':
        default:
            return $data;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. حماية SQL Injection (Database Security)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * تنظيف المدخلات قبل استخدامها في SQL
 */
function db_escape($conn, $data) {
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return db_escape($conn, $item);
        }, $data);
    }
    
    return mysqli_real_escape_string($conn, $data);
}

/**
 * Prepared Statement Helper - بناء وتنفيذ استعلام آمن
 */
function db_query($conn, $query, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Database prepare error: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        // تخمين أنواع البيانات تلقائياً إذا لم تُحدد
        if (empty($types)) {
            $types = str_repeat('s', count($params));
            foreach ($params as $i => $param) {
                if (is_int($param)) {
                    $types[$i] = 'i';
                } else if (is_float($param)) {
                    $types[$i] = 'd';
                }
            }
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    
    return $stmt;
}

// ═══════════════════════════════════════════════════════════════════════════
// 6. Security Headers
// ═══════════════════════════════════════════════════════════════════════════

/**
 * إضافة Security Headers لكل الصفحات
 */
function set_security_headers() {
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }

    // منع الصفحة من العرض في iframe (Clickjacking Protection)
    header("X-Frame-Options: DENY");
    
    // منع المتصفح من تخمين نوع الملف (MIME-type sniffing)
    header("X-Content-Type-Options: nosniff");
    
    // تفعيل XSS Filter في المتصفح
    header("X-XSS-Protection: 1; mode=block");
    
    // Content Security Policy - حماية شاملة من XSS
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self';");
    
    // Referrer Policy - التحكم في معلومات الإحالة
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy - تقييد الميزات الخطرة
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // Strict Transport Security (HSTS) - إجبار HTTPS
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 7. التحقق من الصلاحيات (Authorization)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * التحقق من تسجيل دخول المستخدم
 */
function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: /ems/index.php");
        exit();
    }
}

/**
 * التحقق من صلاحية محددة
 */
function require_role($allowed_roles) {
    require_login();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    $user_role = $_SESSION['user']['role'] ?? null;
    
    if (!in_array($user_role, $allowed_roles) && $user_role != '-1') { // -1 = مدير النظام
        die('
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>غير مصرح</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                    a { color: #0c1c3e; text-decoration: none; background: #e8b800; padding: 10px 30px; border-radius: 5px; display: inline-block; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>⛔ غير مصرح لك بالوصول</h1>
                    <p>ليس لديك صلاحية للوصول إلى هذه الصفحة</p>
                    <a href="/ems/main/dashboard.php">← العودة للصفحة الرئيسية</a>
                </div>
            </body>
            </html>
        ');
    }
}

/**
 * التحقق من ملكية السجل (مثلاً: هل المستخدم يملك هذا المشروع)
 */
function check_ownership($resource_id, $user_field = 'project_id') {
    require_login();
    
    $user_value = $_SESSION['user'][$user_field] ?? null;
    $user_role = $_SESSION['user']['role'] ?? null;
    
    // المدير يمكنه الوصول لكل شيء
    if ($user_role == '-1') {
        return true;
    }
    
    if ($user_value != $resource_id) {
        die('
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>غير مصرح</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                    a { color: #0c1c3e; text-decoration: none; background: #e8b800; padding: 10px 30px; border-radius: 5px; display: inline-block; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>⛔ غير مصرح لك بالوصول</h1>
                    <p>لا يمكنك الوصول إلى هذا المحتوى</p>
                    <a href="/ems/main/dashboard.php">← العودة للصفحة الرئيسية</a>
                </div>
            </body>
            </html>
        ');
    }
    
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
// 8. Rate Limiting (حماية من الهجمات المتكررة)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * التحقق من عدد المحاولات (Rate Limiting)
 */
function check_rate_limit($action, $max_attempts = 10, $time_window = 60) {
    $key = 'rate_limit_' . $action . '_' . $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'start_time' => time()
        ];
    }
    
    // إعادة تعيين العداد إذا انتهى الوقت
    if (time() - $_SESSION[$key]['start_time'] > $time_window) {
        $_SESSION[$key] = [
            'count' => 0,
            'start_time' => time()
        ];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $max_attempts) {
        header('HTTP/1.1 429 Too Many Requests');
        die('
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>محاولات كثيرة</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>⏱️ محاولات كثيرة جداً</h1>
                    <p>لقد تجاوزت الحد المسموح من المحاولات. يرجى الانتظار قليلاً.</p>
                </div>
            </body>
            </html>
        ');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. تسجيل الأنشطة المشبوهة (Security Logging)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * تسجيل محاولة أمنية مشبوهة
 */
function log_security_event($event_type, $details = '') {
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    // إنشاء مجلد logs إذا لم يكن موجوداً
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $user_id = $_SESSION['user']['id'] ?? 'GUEST';
    $username = $_SESSION['user']['username'] ?? 'GUEST';
    
    $log_entry = sprintf(
        "[%s] [%s] IP: %s | User: %s (%s) | Event: %s | Details: %s | UA: %s\n",
        $timestamp,
        $event_type,
        $ip,
        $username,
        $user_id,
        $event_type,
        $details,
        $user_agent
    );
    
    error_log($log_entry, 3, $log_file);
}

// ═══════════════════════════════════════════════════════════════════════════
// 10. تنظيف وتأمين رفع الملفات (File Upload Security)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * التحقق من أمان الملف المرفوع
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 2097152) { // 2MB default
    $errors = [];
    
    // التحقق من وجود أخطاء في الرفع
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'حدث خطأ أثناء رفع الملف';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // التحقق من الحجم
    if ($file['size'] > $max_size) {
        $errors[] = 'حجم الملف كبير جداً. الحد الأقصى: ' . ($max_size / 1024 / 1024) . 'MB';
    }
    
    // التحقق من نوع الملف
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_mime = mime_content_type($file['tmp_name']);
    
    if (!empty($allowed_types) && !in_array($file_ext, $allowed_types)) {
        $errors[] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', $allowed_types);
    }
    
    // التحقق من المحتوى الفعلي للملف (ليس فقط الامتداد)
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    
    if (isset($allowed_mimes[$file_ext]) && $file_mime !== $allowed_mimes[$file_ext]) {
        $errors[] = 'محتوى الملف لا يطابق امتداده';
    }
    
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    return ['valid' => true, 'ext' => $file_ext, 'mime' => $file_mime];
}

/**
 * إنشاء اسم ملف آمن وعشوائي
 */
function generate_safe_filename($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(16));
    return $random . '.' . $ext;
}

// ═══════════════════════════════════════════════════════════════════════════
// بدء تطبيق الأمان تلقائياً
// ═══════════════════════════════════════════════════════════════════════════

// بدء الجلسة الآمنة تلقائياً
if (session_status() === PHP_SESSION_NONE) {
    secure_session_start();
}

// إضافة Security Headers تلقائياً
set_security_headers();

// توليد CSRF Token تلقائياً
generate_csrf_token();

// ═══════════════════════════════════════════════════════════════════════════
// END OF SECURITY FILE
// ═══════════════════════════════════════════════════════════════════════════
