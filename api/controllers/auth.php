<?php
/**
 * api/controllers/auth.php — المصادقة والسياق.
 *   POST /api/login   POST /api/logout   GET /api/me
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * بناء كتلة بيانات المستخدم + المشروع المرتبط (مشترك بين login و me).
 */
function auth_build_context_payload(array $userRow): array
{
    global $conn;

    $isSuper = (strval($userRow['role']) === '-1');
    $companyId = (isset($userRow['company_id']) && intval($userRow['company_id']) > 0) ? intval($userRow['company_id']) : 0;
    $projectId = intval($userRow['project_id']);

    $project = null;
    if ($projectId > 0) {
        $project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
        $scope = '1=1';
        if (!$isSuper && $project_has_company_id) {
            $scope = 'p.company_id = ' . $companyId;
        }
        $stmt = mysqli_prepare(
            $conn,
            "SELECT p.id, p.name, p.project_code, p.location, p.latitude, p.longitude, p.client
             FROM project p
             WHERE p.id = ? AND p.status = 1 AND p.is_deleted = 0 AND $scope
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $projectId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $prow = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($prow) {
                $project = api_format_project($prow);
            }
        }
    }

    return [
        'user' => [
            'id'         => intval($userRow['id']),
            'name'       => $userRow['name'],
            'username'   => $userRow['username'],
            'phone'      => $userRow['phone'] ?? null,
            'role'       => $userRow['role'],
            'company_id' => $companyId > 0 ? $companyId : null,
            'project_id' => $projectId,
        ],
        'project' => $project,
    ];
}

/** POST /api/login */
function auth_login(): void
{
    global $conn;

    $username = api_str('username');
    $password = (string) api_get('password', '');

    if ($username === '' || $password === '') {
        api_fail('يرجى إدخال اسم المستخدم وكلمة المرور', 400);
    }
    if (mb_strlen($username) > 50 || mb_strlen($password) > 128) {
        api_fail('بيانات الاعتماد أطول من المسموح', 400);
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name, username, password, phone, role, project_id, contract_id, company_id, status, is_deleted
         FROM users WHERE username = ? LIMIT 1'
    );
    if (!$stmt) {
        api_fail('حدث خطأ أثناء التحقق', 500);
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    // رسالة موحّدة لتفادي كشف وجود الاسم.
    $invalid = 'اسم المستخدم أو كلمة المرور غير صحيحة';

    if (!$user || !isset($user['password']) || !password_verify($password, $user['password'])) {
        api_fail($invalid, 401);
    }

    if (intval($user['is_deleted']) === 1 || strtolower((string)$user['status']) !== 'active') {
        api_fail('حساب المستخدم غير نشط', 403);
    }

    $isSuper = (strval($user['role']) === '-1');
    $companyId = (isset($user['company_id']) && intval($user['company_id']) > 0) ? intval($user['company_id']) : 0;

    if (!$isSuper && $companyId <= 0) {
        api_fail('المستخدم غير مرتبط بشركة', 403);
    }

    // حالة الشركة (نفس منطق login.php).
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

    $issued = api_issue_token(intval($user['id']));
    $payload = auth_build_context_payload($user);

    api_ok([
        'token'      => $issued['token'],
        'expires_at' => $issued['expires_at'],
        'user'       => $payload['user'],
        'project'    => $payload['project'],
    ], 'تم تسجيل الدخول بنجاح ✅');
}

/** POST /api/logout */
function auth_logout(): void
{
    // المصادقة تضمن صلاحية الطلب، ثم نبطل التوكن الحالي.
    api_require_auth();
    api_revoke_token(api_bearer_token());
    api_ok(null, 'تم تسجيل الخروج ✅');
}

/** GET /api/me */
function auth_me(): void
{
    global $conn;
    $ctx = api_require_auth();

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name, username, phone, role, project_id, contract_id, company_id, status, is_deleted
         FROM users WHERE id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $ctx['id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$user) {
        api_fail('المستخدم غير موجود', 404);
    }

    $payload = auth_build_context_payload($user);
    api_ok([
        'user'    => $payload['user'],
        'project' => $payload['project'],
    ], 'تم جلب السياق');
}
