<?php
require_once dirname(__DIR__, 2) . '/config.php';

if (!defined('SUPER_ADMIN_IDLE_TIMEOUT')) {
    define('SUPER_ADMIN_IDLE_TIMEOUT', 2700); // 45 minutes idle timeout
}

if (!defined('SUPER_ADMIN_ABSOLUTE_TIMEOUT')) {
    define('SUPER_ADMIN_ABSOLUTE_TIMEOUT', 28800); // 8 hours absolute timeout
}

function super_admin_base_url() {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/ems/admin/login.php';
    $marker = '/admin/';
    $position = strpos($scriptName, $marker);

    if ($position === false) {
        return '/ems/admin';
    }

    return substr($scriptName, 0, $position + strlen('/admin'));
}

function super_admin_url($path = '') {
    $baseUrl = rtrim(super_admin_base_url(), '/');

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function super_admin_redirect($path = 'login.php', $query = array()) {
    $target = super_admin_url($path);

    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }

    header('Location: ' . $target);
    exit();
}

function super_admin_is_logged_in() {
    return isset($_SESSION['super_admin']['id']) && intval($_SESSION['super_admin']['id']) > 0;
}

function super_admin_client_fingerprint() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    return hash('sha256', $ua . '|' . $ip);
}

function super_admin_write_audit($adminId, $actionType, $targetName, $description, $targetId = null) {
    $aid = intval($adminId);
    if ($aid <= 0) {
        return;
    }

    $stmt = @mysqli_prepare($GLOBALS['conn'], 'INSERT INTO admin_audit_log (admin_id, action_type, target_name, target_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }

    $targetIdVal = $targetId !== null ? intval($targetId) : null;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300) : '';

    mysqli_stmt_bind_param(
        $stmt,
        'ississs',
        $aid,
        $actionType,
        $targetName,
        $targetIdVal,
        $description,
        $ip,
        $ua
    );
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function super_admin_logout($reason = '') {
    if (super_admin_is_logged_in()) {
        $adminId = intval($_SESSION['super_admin']['id']);
        $desc = $reason !== '' ? $reason : 'تسجيل الخروج';
        super_admin_write_audit($adminId, 'logout', 'جلسة الإدارة العليا', $desc, $adminId);
    }

    unset($_SESSION['super_admin']);
    unset($_SESSION['super_admin_login_attempts']);
    unset($_SESSION['super_admin_last_attempt_time']);
}

function super_admin_destroy_session_and_redirect($reason = '') {
    super_admin_logout($reason);

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_regenerate_id(true);
    super_admin_redirect('login.php', array('security' => '1'));
}

function super_admin_require_login() {
    if (!super_admin_is_logged_in()) {
        super_admin_redirect('login.php');
    }

    $session = $_SESSION['super_admin'];
    $now = time();

    $issuedAt = isset($session['issued_at']) ? intval($session['issued_at']) : 0;
    $lastSeen = isset($session['last_seen']) ? intval($session['last_seen']) : 0;
    $fingerprint = isset($session['fingerprint']) ? $session['fingerprint'] : '';

    if ($issuedAt <= 0 || $lastSeen <= 0 || $fingerprint === '') {
        super_admin_destroy_session_and_redirect('جلسة غير مكتملة');
    }

    if (($now - $lastSeen) > SUPER_ADMIN_IDLE_TIMEOUT) {
        super_admin_destroy_session_and_redirect('انتهت الجلسة بسبب عدم النشاط');
    }

    if (($now - $issuedAt) > SUPER_ADMIN_ABSOLUTE_TIMEOUT) {
        super_admin_destroy_session_and_redirect('انتهت الجلسة الزمنية');
    }

    if (!hash_equals($fingerprint, super_admin_client_fingerprint())) {
        super_admin_destroy_session_and_redirect('تغير بصمة الجلسة');
    }

    $adminId = intval($session['id']);
    $stmt = mysqli_prepare($GLOBALS['conn'], 'SELECT id, name, email, is_active, last_login_at FROM super_admins WHERE id = ? LIMIT 1');
    if (!$stmt) {
        super_admin_destroy_session_and_redirect('تعذر التحقق من الحساب');
    }

    mysqli_stmt_bind_param($stmt, 'i', $adminId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dbAdmin = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$dbAdmin || intval($dbAdmin['is_active']) !== 1) {
        super_admin_destroy_session_and_redirect('الحساب غير نشط أو غير موجود');
    }

    $_SESSION['super_admin']['name'] = $dbAdmin['name'];
    $_SESSION['super_admin']['email'] = $dbAdmin['email'];
    $_SESSION['super_admin']['last_login_at'] = $dbAdmin['last_login_at'];
    $_SESSION['super_admin']['last_seen'] = $now;
}

function super_admin_require_post_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method Not Allowed');
    }

    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}

function super_admin_login_locked_out() {
    $maxAttempts = 5;
    $lockoutMinutes = 15;

    if (!isset($_SESSION['super_admin_login_attempts'])) {
        $_SESSION['super_admin_login_attempts'] = 0;
        $_SESSION['super_admin_last_attempt_time'] = null;
    }

    if (!empty($_SESSION['super_admin_last_attempt_time']) && $_SESSION['super_admin_login_attempts'] >= $maxAttempts) {
        if ((time() - $_SESSION['super_admin_last_attempt_time']) < ($lockoutMinutes * 60)) {
            return true;
        }

        $_SESSION['super_admin_login_attempts'] = 0;
        $_SESSION['super_admin_last_attempt_time'] = null;
    }

    return false;
}

function super_admin_login_error() {
    $maxAttempts = 5;

    if (!isset($_SESSION['super_admin_login_attempts'])) {
        $_SESSION['super_admin_login_attempts'] = 0;
    }

    $_SESSION['super_admin_login_attempts']++;
    $_SESSION['super_admin_last_attempt_time'] = time();

    return 'بيانات الدخول غير صحيحة. المحاولات المتبقية: ' . max(0, $maxAttempts - $_SESSION['super_admin_login_attempts']);
}

function super_admin_login_success(array $admin) {
    $_SESSION['super_admin_login_attempts'] = 0;
    $_SESSION['super_admin_last_attempt_time'] = null;
    session_regenerate_id(true);
    $now = time();
    $_SESSION['super_admin'] = array(
        'id' => intval($admin['id']),
        'name' => $admin['name'],
        'email' => $admin['email'],
        'last_login_at' => $admin['last_login_at'],
        'issued_at' => $now,
        'last_seen' => $now,
        'fingerprint' => super_admin_client_fingerprint()
    );

    super_admin_write_audit(intval($admin['id']), 'login', 'جلسة الإدارة العليا', 'تسجيل الدخول بنجاح', intval($admin['id']));
}

function super_admin_current() {
    return super_admin_is_logged_in() ? $_SESSION['super_admin'] : null;
}

function super_admin_absolute_url($path) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['SERVER_PORT']) && strval($_SERVER['SERVER_PORT']) === '443');
    $scheme = $isSecure ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    return $scheme . '://' . $host . super_admin_url($path);
}

function super_admin_send_reset_email($email, $name, $token) {
    $resetUrl = super_admin_absolute_url('reset_password.php?token=' . urlencode($token));
    $subject = 'إعادة تعيين كلمة مرور الإدارة العليا';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']) : 'localhost';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'From: EMS <noreply@' . $fromHost . ">\r\n";

    $message = "مرحباً " . $name . "،\n\n";
    $message .= "تم استلام طلب لإعادة تعيين كلمة مرور الإدارة العليا.\n";
    $message .= "استخدم الرابط التالي لإدخال كلمة مرور جديدة:\n" . $resetUrl . "\n\n";
    $message .= "ينتهي هذا الرابط خلال ساعة واحدة. إذا لم تطلب إعادة التعيين فتجاهل هذه الرسالة.\n";

    return mail($email, $encodedSubject, $message, $headers);
}
