<?php
require_once dirname(__DIR__) . '/config.php';

if (!defined('COMPANY_USER_IDLE_TIMEOUT')) {
    define('COMPANY_USER_IDLE_TIMEOUT', 3600);
}

if (!defined('COMPANY_USER_ABSOLUTE_TIMEOUT')) {
    define('COMPANY_USER_ABSOLUTE_TIMEOUT', 28800);
}

function company_base_url() {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/ems/company/login.php';
    $marker = '/company/';
    $position = strpos($scriptName, $marker);

    if ($position === false) {
        return '/ems/company';
    }

    return substr($scriptName, 0, $position + strlen('/company'));
}

function company_url($path = '') {
    $baseUrl = rtrim(company_base_url(), '/');
    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function company_redirect($path = 'login.php', $query = array()) {
    $target = company_url($path);
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }

    header('Location: ' . $target);
    exit();
}

function company_table_exists($tableName) {
    static $cache = array();
    $key = strtolower($tableName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $stmt = @mysqli_prepare($GLOBALS['conn'], "SHOW TABLES LIKE ?");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $safeTable);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);

    $cache[$key] = $exists;
    return $exists;
}

function company_users_has_column($columnName) {
    static $cache = array();
    $key = strtolower($columnName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $sql = "SHOW COLUMNS FROM users LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);
    $cache[$key] = $res && mysqli_num_rows($res) > 0;

    return $cache[$key];
}

function company_table_has_column($tableName, $columnName) {
    static $cache = array();
    $key = strtolower($tableName . '::' . $columnName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);
    $cache[$key] = $res && mysqli_num_rows($res) > 0;

    return $cache[$key];
}

function company_is_logged_in() {
    return isset($_SESSION['company_user']['id']) && intval($_SESSION['company_user']['id']) > 0;
}

function company_current_user() {
    return company_is_logged_in() ? $_SESSION['company_user'] : null;
}

function company_require_role($roleId) {
    company_require_login();
    $currentRole = isset($_SESSION['company_user']['role']) ? strval($_SESSION['company_user']['role']) : '';
    if ($currentRole !== strval($roleId)) {
        http_response_code(403);
        die('غير مصرح لك بالوصول لهذه الصفحة');
    }
}

function company_client_fingerprint() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return hash('sha256', $ua . '|' . $ip);
}

function company_write_audit($userId, $companyId, $actionType, $targetName, $description) {
    if (!company_table_exists('audit_logs')) {
        return;
    }

    $uid = intval($userId);
    $cid = intval($companyId);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300) : '';

    $stmt = @mysqli_prepare($GLOBALS['conn'], 'INSERT INTO audit_logs (user_id, company_id, action_type, target_name, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, 'iisssss', $uid, $cid, $actionType, $targetName, $description, $ip, $ua);
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function company_load_permissions($roleId) {
    $permissions = array();

    if (!company_table_exists('role_permissions') || !company_table_exists('modules')) {
        return $permissions;
    }

    $rid = intval($roleId);
    $stmt = @mysqli_prepare($GLOBALS['conn'],
        'SELECT m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
         FROM role_permissions rp
         INNER JOIN modules m ON rp.module_id = m.id
         WHERE rp.role_id = ?'
    );
    if (!$stmt) {
        return $permissions;
    }

    mysqli_stmt_bind_param($stmt, 'i', $rid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $code = isset($row['code']) ? $row['code'] : '';
            if ($code === '') {
                continue;
            }
            $permissions[$code] = array(
                'can_view' => intval($row['can_view']) === 1,
                'can_add' => intval($row['can_add']) === 1,
                'can_edit' => intval($row['can_edit']) === 1,
                'can_delete' => intval($row['can_delete']) === 1
            );
        }
    }

    mysqli_stmt_close($stmt);
    return $permissions;
}

function company_load_plan_modules($companyId) {
    $data = array(
        'plan_name' => null,
        'max_users' => null,
        'max_projects' => null,
        'max_equipments' => null,
        'features' => array()
    );

    if (!company_table_exists('admin_companies') || !company_table_exists('admin_subscription_plans')) {
        return $data;
    }

    $cid = intval($companyId);
    $stmt = @mysqli_prepare($GLOBALS['conn'],
        'SELECT p.plan_name, p.max_users, p.max_projects, p.max_equipments, p.features, c.subscription_start, c.subscription_end
         FROM admin_companies c
         LEFT JOIN admin_subscription_plans p ON c.plan_id = p.id
         WHERE c.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return $data;
    }

    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return $data;
    }

    $data['plan_name'] = $row['plan_name'];
    $data['max_users'] = isset($row['max_users']) ? intval($row['max_users']) : null;
    $data['max_projects'] = isset($row['max_projects']) ? intval($row['max_projects']) : null;
    $data['max_equipments'] = isset($row['max_equipments']) ? intval($row['max_equipments']) : null;
    $data['subscription_start'] = isset($row['subscription_start']) ? $row['subscription_start'] : null;
    $data['subscription_end'] = isset($row['subscription_end']) ? $row['subscription_end'] : null;

    $featuresRaw = isset($row['features']) ? trim($row['features']) : '';
    if ($featuresRaw !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $featuresRaw);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $data['features'][] = $line;
            }
        }
    }

    return $data;
}

function company_dashboard_for_role($roleId) {
    $rid = strval($roleId);

    // Company owner / project manager first lands on company home.
    if ($rid === '1') {
        return '/ems/company/home.php';
    }

    if (in_array($rid, array('-1','2','3','4','5','6','7','8','9','10','11'), true)) {
        return '/ems/main/dashboard.php';
    }

    return '/ems/main/dashboard.php';
}

function company_login_success(array $userRow, array $companyRow) {
    session_regenerate_id(true);
    $now = time();

    $userRole = isset($userRow['role']) ? $userRow['role'] : '0';
    $userName = isset($userRow['name']) && trim($userRow['name']) !== ''
        ? $userRow['name']
        : (isset($userRow['username']) ? $userRow['username'] : 'مستخدم');
    $userEmail = isset($userRow['email']) && trim($userRow['email']) !== ''
        ? $userRow['email']
        : (isset($userRow['username']) ? $userRow['username'] : '');
    $userUsername = isset($userRow['username']) && trim($userRow['username']) !== ''
        ? $userRow['username']
        : $userEmail;

    $scope = array(
        'project_id' => isset($userRow['project_id']) ? intval($userRow['project_id']) : 0,
        'mine_id' => isset($userRow['mine_id']) ? intval($userRow['mine_id']) : 0,
        'contract_id' => isset($userRow['contract_id']) ? intval($userRow['contract_id']) : 0,
        'company_id' => isset($companyRow['id']) ? intval($companyRow['id']) : 0
    );

    $permissions = company_load_permissions($userRole);
    $planData = company_load_plan_modules($scope['company_id']);

    $_SESSION['company_user'] = array(
        'id' => intval($userRow['id']),
        'name' => $userName,
        'email' => $userEmail,
        'role' => $userRole,
        'company_id' => $scope['company_id'],
        'company_name' => isset($companyRow['company_name']) ? $companyRow['company_name'] : '',
        'issued_at' => $now,
        'last_seen' => $now,
        'fingerprint' => company_client_fingerprint(),
        'dashboard' => company_dashboard_for_role($userRole)
    );

    // Keep legacy app session keys for compatibility with existing pages.
    $_SESSION['user'] = array(
        'id' => intval($userRow['id']),
        'name' => $userName,
        'username' => $userUsername,
        'phone' => isset($userRow['phone']) ? $userRow['phone'] : '',
        'role' => $userRole,
        'project_id' => $scope['project_id'],
        'mine_id' => $scope['mine_id'],
        'contract_id' => $scope['contract_id'],
        'company_id' => $scope['company_id'],
        'last_login' => date('Y-m-d H:i:s')
    );

    $_SESSION['user_project_scope'] = $scope;
    $_SESSION['role_permissions'] = $permissions;
    $_SESSION['plan_modules'] = $planData;

    company_write_audit($userRow['id'], $scope['company_id'], 'login', 'بوابة الشركة', 'تسجيل دخول ناجح');
}

function company_logout($reason = '') {
    if (company_is_logged_in()) {
        $u = $_SESSION['company_user'];
        company_write_audit(
            intval($u['id']),
            intval($u['company_id']),
            'logout',
            'بوابة الشركة',
            ($reason !== '' ? $reason : 'تسجيل خروج')
        );
    }

    unset($_SESSION['company_user']);
    unset($_SESSION['user']);
    unset($_SESSION['user_project_scope']);
    unset($_SESSION['role_permissions']);
    unset($_SESSION['plan_modules']);
}

function company_is_active_company($companyId) {
    $cid = intval($companyId);
    if ($cid <= 0) {
        return false;
    }

    if (!company_table_exists('admin_companies') || !company_table_has_column('admin_companies', 'status')) {
        return true;
    }

    $stmt = @mysqli_prepare($GLOBALS['conn'], 'SELECT status FROM admin_companies WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return true;
    }

    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row || !isset($row['status'])) {
        return false;
    }

    return strtolower(trim($row['status'])) === 'active';
}

function company_destroy_session_and_redirect($reason = '') {
    company_logout($reason);

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_regenerate_id(true);
    company_redirect('login.php', array('security' => '1'));
}

function company_require_login() {
    if (!company_is_logged_in()) {
        company_redirect('login.php');
    }

    $session = $_SESSION['company_user'];
    $now = time();

    $issuedAt = isset($session['issued_at']) ? intval($session['issued_at']) : 0;
    $lastSeen = isset($session['last_seen']) ? intval($session['last_seen']) : 0;
    $fingerprint = isset($session['fingerprint']) ? $session['fingerprint'] : '';

    if ($issuedAt <= 0 || $lastSeen <= 0 || $fingerprint === '') {
        company_destroy_session_and_redirect('جلسة غير مكتملة');
    }

    if (($now - $lastSeen) > COMPANY_USER_IDLE_TIMEOUT) {
        company_destroy_session_and_redirect('انتهت الجلسة بسبب عدم النشاط');
    }

    if (($now - $issuedAt) > COMPANY_USER_ABSOLUTE_TIMEOUT) {
        company_destroy_session_and_redirect('انتهت الجلسة الزمنية');
    }

    if (!hash_equals($fingerprint, company_client_fingerprint())) {
        company_destroy_session_and_redirect('تغير بصمة الجلسة');
    }

    $companyId = isset($session['company_id']) ? intval($session['company_id']) : 0;
    if ($companyId <= 0 || !company_is_active_company($companyId)) {
        company_logout('تم إنهاء الجلسة تلقائياً: الشركة موقوفة');
        company_redirect('login.php', array('msg' => 'تم تسجيل الخروج: الشركة موقوفة حالياً.'));
    }

    $_SESSION['company_user']['last_seen'] = $now;
}

function company_absolute_url($path) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['SERVER_PORT']) && strval($_SERVER['SERVER_PORT']) === '443');
    $scheme = $isSecure ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    return $scheme . '://' . $host . company_url($path);
}

function company_send_reset_email($email, $name, $token) {
    $resetUrl = company_absolute_url('reset_password.php?token=' . urlencode($token));
    $subject = 'إعادة تعيين كلمة مرور حساب الشركة';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']) : 'localhost';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'From: EMS <noreply@' . $fromHost . "\r\n";

    $message = "مرحباً " . $name . "،\n\n";
    $message .= "تم استلام طلب لإعادة تعيين كلمة مرور حساب الشركة.\n";
    $message .= "استخدم الرابط التالي خلال 60 دقيقة:\n" . $resetUrl . "\n\n";
    $message .= "إذا لم تطلب إعادة التعيين، تجاهل هذه الرسالة.\n";

    return mail($email, $encodedSubject, $message, $headers);
}
