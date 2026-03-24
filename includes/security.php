<?php
/**
 * Ù…Ù„Ù Ø§Ù„Ø£Ù…Ø§Ù† Ø§Ù„Ù…Ø±ÙƒØ²ÙŠ - EMS Security Core
 * ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø¬Ù…ÙŠØ¹ Ø§Ù„ÙˆØ¸Ø§Ø¦Ù Ø§Ù„Ø£Ù…Ù†ÙŠØ© Ù„Ù„Ù†Ø¸Ø§Ù…
 * 
 * @version 2.0
 * @date 2026-03-01
 */

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ù…Ø¨Ø§Ø´Ø± Ù„Ù„Ù…Ù„Ù
if (!defined('SECURITY_INCLUDED')) {
    define('SECURITY_INCLUDED', true);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 1. Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ù„Ø¬Ù„Ø³Ø© Ø§Ù„Ø¢Ù…Ù†Ø© (Secure Session Configuration)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø¨Ø¯Ø¡ Ø¬Ù„Ø³Ø© Ø¢Ù…Ù†Ø© Ù…Ø¹ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ù…Ø­Ø³Ù†Ø©
 */
function secure_session_start() {
    // Ù…Ù†Ø¹ Ø¨Ø¯Ø¡ Ø¬Ù„Ø³Ø© Ø¬Ø¯ÙŠØ¯Ø© Ø¥Ø°Ø§ ÙƒØ§Ù†Øª Ù…ÙˆØ¬ÙˆØ¯Ø©
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    
    // Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø£Ù…Ø§Ù† Ø§Ù„Ø¬Ù„Ø³Ø©
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // ØªÙƒÙˆÙŠÙ† cookie Ø§Ù„Ø¬Ù„Ø³Ø©
    $cookieParams = [
        'lifetime' => 0,              // ØªÙ†ØªÙ‡ÙŠ Ø¹Ù†Ø¯ Ø¥ØºÙ„Ø§Ù‚ Ø§Ù„Ù…ØªØµÙØ­
        'path' => '/',                // Ù…ØªØ§Ø­ Ù„ÙƒÙ„ Ø§Ù„Ù…Ø³Ø§Ø±Ø§Øª
        'domain' => '',               // Ø§Ù„Ù†Ø·Ø§Ù‚ Ø§Ù„Ø­Ø§Ù„ÙŠ
        'secure' => $secure,          // HTTPS ÙÙ‚Ø· Ø¥Ø°Ø§ ÙƒØ§Ù† Ù…ØªØ§Ø­Ø§Ù‹
        'httponly' => true,           // Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø§Ù„ÙˆØµÙˆÙ„ Ø¹Ø¨Ø± JavaScript
        'samesite' => 'Strict'        // Ø­Ù…Ø§ÙŠØ© Ù…Ù† CSRF
    ];
    
    session_set_cookie_params($cookieParams);
    
    // Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø£Ù…Ø§Ù† Ø¥Ø¶Ø§ÙÙŠØ©
    ini_set('session.use_strict_mode', 1);           // Ø±ÙØ¶ session IDs ØºÙŠØ± Ù…Ø¹Ø±ÙˆÙØ©
    ini_set('session.use_only_cookies', 1);          // Ø§Ø³ØªØ®Ø¯Ø§Ù… cookies ÙÙ‚Ø·
    ini_set('session.cookie_httponly', 1);           // Ø­Ù…Ø§ÙŠØ© Ù…Ù† XSS
    ini_set('session.cookie_secure', $secure ? 1 : 0);
    ini_set('session.use_trans_sid', 0);             // Ø¹Ø¯Ù… Ù†Ù‚Ù„ session ID ÙÙŠ URL
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.sid_length', 48);               // Ø·ÙˆÙ„ Ø£ÙƒØ¨Ø± Ù„Ù€ session ID
    ini_set('session.sid_bits_per_character', 6);
    
    // Ø¨Ø¯Ø¡ Ø§Ù„Ø¬Ù„Ø³Ø©
    session_start();
    
    // ØªØ¬Ø¯ÙŠØ¯ session ID Ø¨Ø´ÙƒÙ„ Ø¯ÙˆØ±ÙŠ Ù„Ù„Ø­Ù…Ø§ÙŠØ© Ù…Ù† Session Fixation
    if (!isset($_SESSION['created_at'])) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    } else if (time() - $_SESSION['created_at'] > 1800) { // ÙƒÙ„ 30 Ø¯Ù‚ÙŠÙ‚Ø©
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
    
    // ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ø¬Ù„Ø³Ø© (Session Timeout)
    check_session_timeout();
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† IP Ùˆ User Agent Ù„Ù„Ø­Ù…Ø§ÙŠØ© Ù…Ù† Session Hijacking
    validate_session_fingerprint();
    
    return true;
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù†ØªÙ‡Ø§Ø¡ ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ø¬Ù„Ø³Ø© (Session Timeout)
 */
function check_session_timeout() {
    $timeout = 3600; // 60 Ø¯Ù‚ÙŠÙ‚Ø© Ø¨Ø¯ÙˆÙ† Ù†Ø´Ø§Ø·
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > $timeout) {
            session_unset();
            session_destroy();
            
            // Ø¥Ø¹Ø§Ø¯Ø© ØªÙˆØ¬ÙŠÙ‡ Ù„ØµÙØ­Ø© ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯Ø®ÙˆÙ„
            header("Location: " . get_auth_login_path() . "?timeout=1");
            exit();
        }
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * ØªØ­Ø¯ÙŠØ¯ ØµÙØ­Ø© ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯Ø®ÙˆÙ„ Ø§Ù„Ù…Ù†Ø§Ø³Ø¨Ø© Ø¨Ø­Ø³Ø¨ Ø³ÙŠØ§Ù‚ Ø§Ù„Ø¬Ù„Ø³Ø© Ø§Ù„Ø­Ø§Ù„ÙŠØ©.
 */
function get_auth_login_path() {
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    if (!empty($_SESSION['super_admin']) || strpos($requestUri, '/admin/') !== false) {
        return '/ems/admin/login.php';
    }

    return '/ems/login.php';
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¨ØµÙ…Ø© Ø§Ù„Ø¬Ù„Ø³Ø© (Session Fingerprint) Ù„Ù„Ø­Ù…Ø§ÙŠØ© Ù…Ù† Session Hijacking
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
            // Ù…Ø­Ø§ÙˆÙ„Ø© Ø§Ø®ØªØ±Ø§Ù‚ - ØªØ¯Ù…ÙŠØ± Ø§Ù„Ø¬Ù„Ø³Ø©
            session_unset();
            session_destroy();
            header("Location: " . get_auth_login_path() . "?security=violated");
            exit();
        }
    } else {
        $_SESSION['fingerprint'] = $fingerprint;
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 2. Ø­Ù…Ø§ÙŠØ© CSRF (Cross-Site Request Forgery)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * ØªÙˆÙ„ÙŠØ¯ CSRF Token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (!isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token_time'] = time();
    }
    
    // ØªØ¬Ø¯ÙŠØ¯ Ø§Ù„ØªÙˆÙƒÙ† ÙƒÙ„ Ø³Ø§Ø¹Ø©
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Ø·Ø¨Ø§Ø¹Ø© Ø­Ù‚Ù„ CSRF Ù…Ø®ÙÙŠ ÙÙŠ Ø§Ù„ÙÙˆØ±Ù…
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ CSRF Token ÙƒÙ€ meta tag Ù„Ù„Ù€ AJAX
 */
function csrf_meta() {
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . $token . '">';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 3. Ø­Ù…Ø§ÙŠØ© Ù…Ù† XSS (Cross-Site Scripting)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ù†Øµ Ù…Ù† Ø£ÙƒÙˆØ§Ø¯ HTML/JavaScript Ø§Ù„Ø®Ø¨ÙŠØ«Ø©
 */
function clean_output($data) {
    if (is_array($data)) {
        return array_map('clean_output', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Ø§Ø®ØªØµØ§Ø± Ù„Ù€ clean_output
 */
function e($data) {
    return clean_output($data);
}

/**
 * ØªÙ†Ø¸ÙŠÙ URL
 */
function clean_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * ØªÙ†Ø¸ÙŠÙ Email
 */
function clean_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ù†Øµ Ù…Ù† tags HTML (Ù…Ø¹ Ø§Ù„Ø³Ù…Ø§Ø­ Ø¨Ø¨Ø¹Ø¶ tags Ø§Ù„Ø¢Ù…Ù†Ø©)
 */
function clean_html($html, $allowed_tags = '<p><br><strong><em><u><a>') {
    return strip_tags($html, $allowed_tags);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 4. Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ù…Ø¯Ø®Ù„Ø§Øª (Input Validation)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ (Ø£Ø±Ù‚Ø§Ù… ÙÙ‚Ø· ÙˆØ§Ù„Ø¹Ù„Ø§Ù…Ø© +)
 */
function validate_phone($phone) {
    return preg_match('/^[\+]?[0-9]{9,15}$/', $phone);
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„ØªØ§Ø±ÙŠØ®
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø±Ù‚Ù… Ø§Ù„ØµØ­ÙŠØ­
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
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø·ÙˆÙ„ Ø§Ù„Ù†Øµ
 */
function validate_length($text, $min = 0, $max = PHP_INT_MAX) {
    $length = mb_strlen($text, 'UTF-8');
    return $length >= $min && $length <= $max;
}

/**
 * ØªÙ†Ø¸ÙŠÙ ÙˆØªØ­ÙˆÙŠÙ„ Ø§Ù„Ù…Ø¯Ø®Ù„Ø§Øª Ø§Ù„Ø¢Ù…Ù†
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 5. Ø­Ù…Ø§ÙŠØ© SQL Injection (Database Security)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ù…Ø¯Ø®Ù„Ø§Øª Ù‚Ø¨Ù„ Ø§Ø³ØªØ®Ø¯Ø§Ù…Ù‡Ø§ ÙÙŠ SQL
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
 * Prepared Statement Helper - Ø¨Ù†Ø§Ø¡ ÙˆØªÙ†ÙÙŠØ° Ø§Ø³ØªØ¹Ù„Ø§Ù… Ø¢Ù…Ù†
 */
function db_query($conn, $query, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Database prepare error: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        // ØªØ®Ù…ÙŠÙ† Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¥Ø°Ø§ Ù„Ù… ØªÙØ­Ø¯Ø¯
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 6. Security Headers
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø¥Ø¶Ø§ÙØ© Security Headers Ù„ÙƒÙ„ Ø§Ù„ØµÙØ­Ø§Øª
 */
function set_security_headers() {
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }

    // Ù…Ù†Ø¹ Ø§Ù„ØµÙØ­Ø© Ù…Ù† Ø§Ù„Ø¹Ø±Ø¶ ÙÙŠ iframe (Clickjacking Protection)
    header("X-Frame-Options: DENY");
    
    // Ù…Ù†Ø¹ Ø§Ù„Ù…ØªØµÙØ­ Ù…Ù† ØªØ®Ù…ÙŠÙ† Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„Ù (MIME-type sniffing)
    header("X-Content-Type-Options: nosniff");
    
    // ØªÙØ¹ÙŠÙ„ XSS Filter ÙÙŠ Ø§Ù„Ù…ØªØµÙØ­
    header("X-XSS-Protection: 1; mode=block");
    
    // Content Security Policy - Ø­Ù…Ø§ÙŠØ© Ø´Ø§Ù…Ù„Ø© Ù…Ù† XSS
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self';");
    
    // Referrer Policy - Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø¥Ø­Ø§Ù„Ø©
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy - ØªÙ‚ÙŠÙŠØ¯ Ø§Ù„Ù…ÙŠØ²Ø§Øª Ø§Ù„Ø®Ø·Ø±Ø©
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // Strict Transport Security (HSTS) - Ø¥Ø¬Ø¨Ø§Ø± HTTPS
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 7. Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª (Authorization)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØªØ³Ø¬ÙŠÙ„ Ø¯Ø®ÙˆÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
 */
function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: /ems/login.php");
        exit();
    }
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ© Ù…Ø­Ø¯Ø¯Ø©
 */
function require_role($allowed_roles) {
    require_login();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    $user_role = $_SESSION['user']['role'] ?? null;
    
    if (!in_array($user_role, $allowed_roles) && $user_role != '-1') { // -1 = Ù…Ø¯ÙŠØ± Ø§Ù„Ù†Ø¸Ø§Ù…
        die('
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>ØºÙŠØ± Ù…ØµØ±Ø­</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                    a { color: #0c1c3e; text-decoration: none; background: #e8b800; padding: 10px 30px; border-radius: 5px; display: inline-block; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>â›” ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø§Ù„ÙˆØµÙˆÙ„</h1>
                    <p>Ù„ÙŠØ³ Ù„Ø¯ÙŠÙƒ ØµÙ„Ø§Ø­ÙŠØ© Ù„Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ Ù‡Ø°Ù‡ Ø§Ù„ØµÙØ­Ø©</p>
                    <a href="/ems/main/dashboard.php">â† Ø§Ù„Ø¹ÙˆØ¯Ø© Ù„Ù„ØµÙØ­Ø© Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ©</a>
                </div>
            </body>
            </html>
        ');
    }
}

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ù…Ù„ÙƒÙŠØ© Ø§Ù„Ø³Ø¬Ù„ (Ù…Ø«Ù„Ø§Ù‹: Ù‡Ù„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ…Ù„Ùƒ Ù‡Ø°Ø§ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹)
 */
function check_ownership($resource_id, $user_field = 'project_id') {
    require_login();
    
    $user_value = $_SESSION['user'][$user_field] ?? null;
    $user_role = $_SESSION['user']['role'] ?? null;
    
    // Ø§Ù„Ù…Ø¯ÙŠØ± ÙŠÙ…ÙƒÙ†Ù‡ Ø§Ù„ÙˆØµÙˆÙ„ Ù„ÙƒÙ„ Ø´ÙŠØ¡
    if ($user_role == '-1') {
        return true;
    }
    
    if ($user_value != $resource_id) {
        die('
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>ØºÙŠØ± Ù…ØµØ±Ø­</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                    a { color: #0c1c3e; text-decoration: none; background: #e8b800; padding: 10px 30px; border-radius: 5px; display: inline-block; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>â›” ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø§Ù„ÙˆØµÙˆÙ„</h1>
                    <p>Ù„Ø§ ÙŠÙ…ÙƒÙ†Ùƒ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ Ù‡Ø°Ø§ Ø§Ù„Ù…Ø­ØªÙˆÙ‰</p>
                    <a href="/ems/main/dashboard.php">â† Ø§Ù„Ø¹ÙˆØ¯Ø© Ù„Ù„ØµÙØ­Ø© Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ©</a>
                </div>
            </body>
            </html>
        ');
    }
    
    return true;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 8. Rate Limiting (Ø­Ù…Ø§ÙŠØ© Ù…Ù† Ø§Ù„Ù‡Ø¬Ù…Ø§Øª Ø§Ù„Ù…ØªÙƒØ±Ø±Ø©)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø§Øª (Rate Limiting)
 */
function check_rate_limit($action, $max_attempts = 10, $time_window = 60) {
    $key = 'rate_limit_' . $action . '_' . $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'start_time' => time()
        ];
    }
    
    // Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† Ø§Ù„Ø¹Ø¯Ø§Ø¯ Ø¥Ø°Ø§ Ø§Ù†ØªÙ‡Ù‰ Ø§Ù„ÙˆÙ‚Øª
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
                <title>Ù…Ø­Ø§ÙˆÙ„Ø§Øª ÙƒØ«ÙŠØ±Ø©</title>
                <style>
                    body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc2626; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>â±ï¸ Ù…Ø­Ø§ÙˆÙ„Ø§Øª ÙƒØ«ÙŠØ±Ø© Ø¬Ø¯Ø§Ù‹</h1>
                    <p>Ù„Ù‚Ø¯ ØªØ¬Ø§ÙˆØ²Øª Ø§Ù„Ø­Ø¯ Ø§Ù„Ù…Ø³Ù…ÙˆØ­ Ù…Ù† Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø§Øª. ÙŠØ±Ø¬Ù‰ Ø§Ù„Ø§Ù†ØªØ¸Ø§Ø± Ù‚Ù„ÙŠÙ„Ø§Ù‹.</p>
                </div>
            </body>
            </html>
        ');
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 9. ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø£Ù†Ø´Ø·Ø© Ø§Ù„Ù…Ø´Ø¨ÙˆÙ‡Ø© (Security Logging)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * ØªØ³Ø¬ÙŠÙ„ Ù…Ø­Ø§ÙˆÙ„Ø© Ø£Ù…Ù†ÙŠØ© Ù…Ø´Ø¨ÙˆÙ‡Ø©
 */
function log_security_event($event_type, $details = '') {
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    // Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø¬Ù„Ø¯ logs Ø¥Ø°Ø§ Ù„Ù… ÙŠÙƒÙ† Ù…ÙˆØ¬ÙˆØ¯Ø§Ù‹
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// 10. ØªÙ†Ø¸ÙŠÙ ÙˆØªØ£Ù…ÙŠÙ† Ø±ÙØ¹ Ø§Ù„Ù…Ù„ÙØ§Øª (File Upload Security)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø£Ù…Ø§Ù† Ø§Ù„Ù…Ù„Ù Ø§Ù„Ù…Ø±ÙÙˆØ¹
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 2097152) { // 2MB default
    $errors = [];
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ÙˆØ¬ÙˆØ¯ Ø£Ø®Ø·Ø§Ø¡ ÙÙŠ Ø§Ù„Ø±ÙØ¹
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø±ÙØ¹ Ø§Ù„Ù…Ù„Ù';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ø­Ø¬Ù…
    if ($file['size'] > $max_size) {
        $errors[] = 'Ø­Ø¬Ù… Ø§Ù„Ù…Ù„Ù ÙƒØ¨ÙŠØ± Ø¬Ø¯Ø§Ù‹. Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰: ' . ($max_size / 1024 / 1024) . 'MB';
    }
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„Ù
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_mime = mime_content_type($file['tmp_name']);
    
    if (!empty($allowed_types) && !in_array($file_ext, $allowed_types)) {
        $errors[] = 'Ù†ÙˆØ¹ Ø§Ù„Ù…Ù„Ù ØºÙŠØ± Ù…Ø³Ù…ÙˆØ­. Ø§Ù„Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø³Ù…ÙˆØ­Ø©: ' . implode(', ', $allowed_types);
    }
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ù…Ø­ØªÙˆÙ‰ Ø§Ù„ÙØ¹Ù„ÙŠ Ù„Ù„Ù…Ù„Ù (Ù„ÙŠØ³ ÙÙ‚Ø· Ø§Ù„Ø§Ù…ØªØ¯Ø§Ø¯)
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
        $errors[] = 'Ù…Ø­ØªÙˆÙ‰ Ø§Ù„Ù…Ù„Ù Ù„Ø§ ÙŠØ·Ø§Ø¨Ù‚ Ø§Ù…ØªØ¯Ø§Ø¯Ù‡';
    }
    
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    return ['valid' => true, 'ext' => $file_ext, 'mime' => $file_mime];
}

/**
 * Ø¥Ù†Ø´Ø§Ø¡ Ø§Ø³Ù… Ù…Ù„Ù Ø¢Ù…Ù† ÙˆØ¹Ø´ÙˆØ§Ø¦ÙŠ
 */
function generate_safe_filename($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(16));
    return $random . '.' . $ext;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// Ø¨Ø¯Ø¡ ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø£Ù…Ø§Ù† ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// Ø¨Ø¯Ø¡ Ø§Ù„Ø¬Ù„Ø³Ø© Ø§Ù„Ø¢Ù…Ù†Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹
if (session_status() === PHP_SESSION_NONE) {
    secure_session_start();
}

// Ø¥Ø¶Ø§ÙØ© Security Headers ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹
set_security_headers();

// ØªÙˆÙ„ÙŠØ¯ CSRF Token ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹
generate_csrf_token();

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// END OF SECURITY FILE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

