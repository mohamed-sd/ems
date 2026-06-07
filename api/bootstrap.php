<?php
/**
 * api/bootstrap.php — تهيئة طبقة الـ API.
 *
 * يحمّل اتصال قاعدة البيانات وأدوات النظام من config.php، ثم يوفّر:
 *   - معالجة أخطاء مركزية (أي استثناء/خطأ فادح → JSON واضح + تسجيل).
 *   - دوال ردّ JSON موحّدة { success, message, data }.
 *   - قراءة المدخلات (JSON أو form) وأدوات التحقق.
 *   - مصادقة التوكن (Bearer) عبر جدول api_tokens.
 *   - إعادة استخدام صلاحيات النظام وعزل الشركة كما هي (بملء $_SESSION['user']).
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    define('EMS_API', true);
}

// ── تحميل نواة النظام (DB + الأمان + المساعدات) ─────────────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

/** @var mysqli $conn متاح عالمياً من config.php */
global $conn;

// منع أي تخزين مؤقت لردود الـ API.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. معالجة الأخطاء المركزية
// ═══════════════════════════════════════════════════════════════════════════

/** كتابة خطأ في سجل مركزي للتشخيص. */
function api_log_error(string $context, string $message): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents(
        $dir . '/api.log',
        '[' . date('Y-m-d H:i:s') . "] {$context}: {$message}\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * إرسال ردّ JSON موحّد وإنهاء التنفيذ.
 *
 * @param bool   $success
 * @param string $message
 * @param mixed  $data
 * @param int    $http
 */
function api_respond(bool $success, string $message, $data = null, int $http = 200): void
{
    if (!headers_sent()) {
        // تنظيف أي مخزن إخراج (مثل مرشّح ems_fix_mojibake) لضمان JSON نقيّ.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** اختصار: ردّ نجاح. */
function api_ok($data = null, string $message = 'تم بنجاح', int $http = 200): void
{
    api_respond(true, $message, $data, $http);
}

/** اختصار: ردّ خطأ. */
function api_fail(string $message, int $http = 400, $data = null): void
{
    api_respond(false, $message, $data, $http);
}

// التقاط الأخطاء الفادحة (Fatal/Parse) التي لا يطالها try/catch.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        api_log_error('FATAL', $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            api_respond(false, 'تعذّر إكمال الطلب بسبب خطأ في النظام. تم تسجيل المشكلة.', null, 500);
        }
    }
});

// تحويل الاستثناءات غير الملتقطة إلى JSON.
set_exception_handler(static function (\Throwable $e): void {
    api_log_error('EXCEPTION', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    api_respond(false, 'حدث خطأ غير متوقّع في الخادم. تم تسجيل المشكلة.', null, 500);
});

// ═══════════════════════════════════════════════════════════════════════════
// 2. قراءة المدخلات
// ═══════════════════════════════════════════════════════════════════════════

/**
 * قراءة مدخلات الطلب (JSON body أو form أو query) كمصفوفة موحّدة.
 * يُخزَّن الناتج للاستدعاءات المتكررة.
 */
function api_input(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $data = [];

    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // دمج form-data و query (JSON له الأولوية).
    $data = array_merge($_GET, $_POST, $data);

    $cache = $data;
    return $cache;
}

/** جلب قيمة من المدخلات مع قيمة افتراضية. */
function api_get($key, $default = null)
{
    $in = api_input();
    return array_key_exists($key, $in) ? $in[$key] : $default;
}

/** جلب قيمة نصية منظّفة. */
function api_str($key, string $default = ''): string
{
    $v = api_get($key, $default);
    return is_scalar($v) ? trim((string)$v) : $default;
}

/** جلب قيمة عددية صحيحة. */
function api_int($key, int $default = 0): int
{
    $v = api_get($key, $default);
    return is_scalar($v) ? intval($v) : $default;
}

/** جلب قيمة عشرية. */
function api_float($key, float $default = 0.0): float
{
    $v = api_get($key, $default);
    return is_scalar($v) ? floatval($v) : $default;
}

/** التحقق من صيغة تاريخ Y-m-d (يرمي استثناءً عند الفشل). فارغ مسموح. */
function api_validate_date(string $value, string $label): void
{
    if ($value === '') {
        return;
    }
    $obj = DateTime::createFromFormat('Y-m-d', $value);
    if (!$obj || $obj->format('Y-m-d') !== $value) {
        api_fail("صيغة {$label} غير صحيحة (المطلوب YYYY-MM-DD)", 422);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. مصادقة التوكن (Bearer)
// ═══════════════════════════════════════════════════════════════════════════

/** استخراج التوكن الخام من ترويسة Authorization من جميع المصادر الممكنة. */
function api_bearer_token(): string
{
    $header = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $header = $v;
                break;
            }
        }
    }

    if ($header !== '' && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }

    return '';
}

/** توليد توكن خام جديد (64 hex). */
function api_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

/** تجزئة توكن للتخزين/المطابقة. */
function api_hash_token(string $token): string
{
    return hash('sha256', $token);
}

/**
 * إصدار توكن جديد لمستخدم وتخزين تجزئته. يعيد التوكن الخام (يُعاد مرّة واحدة فقط).
 *
 * @param int $userId
 * @param int $days صلاحية بالأيام
 * @return array{token:string, expires_at:string}
 */
function api_issue_token(int $userId, int $days = 30): array
{
    global $conn;
    $token = api_generate_token();
    $hash = api_hash_token($token);
    $expires = date('Y-m-d H:i:s', time() + ($days * 86400));
    $device = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 150) : null;

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO api_tokens (user_id, token_hash, device, expires_at) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        api_fail('تعذّر إصدار التوكن', 500);
    }
    mysqli_stmt_bind_param($stmt, 'isss', $userId, $hash, $device, $expires);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        api_fail('تعذّر إصدار التوكن', 500);
    }
    mysqli_stmt_close($stmt);

    return ['token' => $token, 'expires_at' => $expires];
}

/** إبطال توكن (بالتجزئة). */
function api_revoke_token(string $token): void
{
    global $conn;
    if ($token === '') {
        return;
    }
    $hash = api_hash_token($token);
    $stmt = mysqli_prepare($conn, 'UPDATE api_tokens SET revoked = 1 WHERE token_hash = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $hash);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * المصادقة الإلزامية: تتحقق من التوكن وتحمّل المستخدم وتملأ $_SESSION['user']
 * لإعادة استخدام صلاحيات النظام وعزل الشركة كما هي. تعيد سياق المستخدم.
 *
 * @return array سياق المستخدم {id, name, username, role, company_id, project_id, is_super}
 */
function api_require_auth(): array
{
    global $conn;

    $token = api_bearer_token();
    if ($token === '') {
        api_fail('غير مصرّح — التوكن مفقود', 401);
    }

    $hash = api_hash_token($token);
    $stmt = mysqli_prepare(
        $conn,
        'SELECT user_id FROM api_tokens
         WHERE token_hash = ? AND revoked = 0 AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1'
    );
    if (!$stmt) {
        api_fail('تعذّر التحقق من التوكن', 500);
    }
    mysqli_stmt_bind_param($stmt, 's', $hash);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        api_fail('غير مصرّح — التوكن غير صالح أو منتهٍ', 401);
    }

    $userId = intval($row['user_id']);

    // تحديث آخر استخدام (بدون إيقاف التنفيذ عند الفشل).
    $upd = mysqli_prepare($conn, 'UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 's', $hash);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }

    // تحميل المستخدم.
    $ustmt = mysqli_prepare(
        $conn,
        'SELECT id, name, username, phone, role, project_id, contract_id, company_id, status, is_deleted
         FROM users WHERE id = ? LIMIT 1'
    );
    if (!$ustmt) {
        api_fail('تعذّر تحميل بيانات المستخدم', 500);
    }
    mysqli_stmt_bind_param($ustmt, 'i', $userId);
    mysqli_stmt_execute($ustmt);
    $ures = mysqli_stmt_get_result($ustmt);
    $user = $ures ? mysqli_fetch_assoc($ures) : null;
    mysqli_stmt_close($ustmt);

    if (!$user) {
        api_fail('المستخدم غير موجود', 401);
    }
    if (intval($user['is_deleted']) === 1 || strtolower((string)$user['status']) !== 'active') {
        api_fail('حساب المستخدم غير نشط', 403);
    }

    $isSuper = (strval($user['role']) === '-1');
    $companyId = (isset($user['company_id']) && intval($user['company_id']) > 0) ? intval($user['company_id']) : 0;

    if (!$isSuper && $companyId <= 0) {
        api_fail('المستخدم غير مرتبط بشركة', 403);
    }

    // التحقق من حالة الشركة (نفس منطق config/login).
    if (!$isSuper && $companyId > 0 && db_table_has_column($conn, 'admin_companies', 'status')) {
        $cstmt = mysqli_prepare($conn, 'SELECT status FROM admin_companies WHERE id = ? LIMIT 1');
        if ($cstmt) {
            mysqli_stmt_bind_param($cstmt, 'i', $companyId);
            mysqli_stmt_execute($cstmt);
            $cres = mysqli_stmt_get_result($cstmt);
            $crow = $cres ? mysqli_fetch_assoc($cres) : null;
            mysqli_stmt_close($cstmt);
            if ($crow && isset($crow['status']) && strtolower(trim($crow['status'])) !== 'active') {
                api_fail('حالة الشركة غير نشطة', 403);
            }
        }
    }

    // ملء جلسة المستخدم لإعادة استخدام دوال الصلاحيات/العزل القائمة دون تعديلها.
    $_SESSION['user'] = [
        'id'          => $user['id'],
        'name'        => $user['name'],
        'username'    => $user['username'],
        'phone'       => $user['phone'],
        'role'        => $user['role'],
        'project_id'  => $user['project_id'],
        'contract_id' => $user['contract_id'] ?? null,
        'company_id'  => $companyId > 0 ? $companyId : null,
    ];

    return [
        'id'         => intval($user['id']),
        'name'       => $user['name'],
        'username'   => $user['username'],
        'phone'      => $user['phone'],
        'role'       => $user['role'],
        'company_id' => $companyId,
        'project_id' => intval($user['project_id']),
        'is_super'   => $isSuper,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. أدوات العزل والنطاق (مطابقة لمنطق الشاشات)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * تحديد معرّف المشروع الفعّال للطلب: مشروع المستخدم تلقائياً،
 * مع السماح للسوبر أدمن بتمرير ?project_id=.
 */
function api_resolve_project_id(array $ctx): int
{
    $projectId = $ctx['project_id'];
    if ($ctx['is_super']) {
        $requested = api_int('project_id', 0);
        if ($requested > 0) {
            $projectId = $requested;
        }
    }
    return $projectId;
}

/**
 * جلب بيانات المشروع ضمن نطاق الشركة (يرمي 404/400 عند الفشل).
 *
 * @return array صف المشروع
 */
function api_fetch_project(array $ctx, int $projectId): array
{
    global $conn;

    if ($projectId <= 0) {
        api_fail('لا يوجد مشروع مرتبط بهذا الحساب', 400);
    }

    $project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
    $scope = '1=1';
    if (!$ctx['is_super'] && $project_has_company_id) {
        $scope = 'p.company_id = ' . intval($ctx['company_id']);
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.id, p.name, p.project_code, p.location, p.latitude, p.longitude, p.client
         FROM project p
         WHERE p.id = ? AND p.status = 1 AND p.is_deleted = 0 AND $scope
         LIMIT 1"
    );
    if (!$stmt) {
        api_fail('تعذّر جلب المشروع', 500);
    }
    mysqli_stmt_bind_param($stmt, 'i', $projectId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $project = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$project) {
        api_fail('المشروع غير متاح', 404);
    }

    return $project;
}

/** تنسيق بيانات المشروع للإخراج (إحداثيات رقمية). */
function api_format_project(array $project): array
{
    return [
        'id'           => intval($project['id']),
        'name'         => $project['name'] ?? '',
        'project_code' => $project['project_code'] ?? '',
        'location'     => $project['location'] ?? '',
        'client'       => $project['client'] ?? '',
        'latitude'     => isset($project['latitude']) && $project['latitude'] !== '' ? floatval($project['latitude']) : null,
        'longitude'    => isset($project['longitude']) && $project['longitude'] !== '' ? floatval($project['longitude']) : null,
    ];
}

/**
 * صلاحيات شاشة الحركة والتشغيل (مطابقة لـ movement_operations.php).
 *
 * @return array{can_view:bool, can_add:bool, can_edit:bool}
 */
function api_movement_perms(): array
{
    global $conn;
    $ops = check_page_permissions($conn, 'movement/move_oprators.php');
    $drv = check_page_permissions($conn, 'movement/project_drivers.php');
    return [
        'can_view' => (!empty($ops['can_view']) || !empty($drv['can_view'])),
        'can_add'  => (!empty($ops['can_add']) || !empty($drv['can_add'])),
        'can_edit' => (!empty($ops['can_edit']) || !empty($drv['can_edit'])),
    ];
}
