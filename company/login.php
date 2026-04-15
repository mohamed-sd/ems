<?php
require_once __DIR__ . '/auth.php';

if (company_is_logged_in()) {
    $to = isset($_SESSION['company_user']['dashboard']) ? $_SESSION['company_user']['dashboard'] : ems_url('main/dashboard.php');
    header('Location: ' . $to);
    exit();
}

$error = '';
$statusMessage = '';

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $statusMessage = 'تم تحديث كلمة المرور. يمكنك تسجيل الدخول الآن.';
}
if (isset($_GET['security']) && $_GET['security'] === '1') {
    $statusMessage = 'تم إنهاء الجلسة السابقة لأسباب أمنية. يرجى تسجيل الدخول مجدداً.';
}
if (isset($_GET['msg']) && trim($_GET['msg']) !== '') {
    $error = trim($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
    } else {
        $hasEmailCol = company_users_has_column('email');
        $hasUsernameCol = company_users_has_column('username');

        if (!$hasEmailCol && !$hasUsernameCol) {
            $error = 'جدول المستخدمين لا يحتوي أعمدة دخول مدعومة (email/username).';
        } else {
            $identifier = trim(isset($_POST['email']) ? $_POST['email'] : '');
            $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
            $isEmailIdentifier = validate_email($identifier);

            if (!validate_length($identifier, 2, 150) || $password === '') {
                $error = 'بيانات الدخول غير صحيحة.';
            } else {
                $fields = 'u.id, u.name, u.password, u.phone, u.role';
                if ($hasUsernameCol) {
                    $fields .= ', u.username';
                } else {
                    $fields .= ', "" AS username';
                }
                if ($hasEmailCol) {
                    $fields .= ', u.email';
                } else {
                    $fields .= ', "" AS email';
                }
                if (company_users_has_column('project_id')) {
                    $fields .= ', u.project_id';
                }
                if (company_users_has_column('mine_id')) {
                    $fields .= ', u.mine_id';
                }
                if (company_users_has_column('contract_id')) {
                    $fields .= ', u.contract_id';
                }
                if (company_users_has_column('company_id')) {
                    $fields .= ', u.company_id';
                }
                if (company_users_has_column('status')) {
                    $fields .= ', u.status';
                }
                if (company_users_has_column('is_deleted')) {
                    $fields .= ', u.is_deleted';
                }
                if (company_users_has_column('deleted_at')) {
                    $fields .= ', u.deleted_at';
                }

                $hasCompaniesTable = company_table_exists('admin_companies');
                $hasCompanyIdOnUsers = company_users_has_column('company_id');

                $companyNameExpr = "''";
                if ($hasCompaniesTable) {
                    $hasCompanyName = company_table_has_column('admin_companies', 'company_name');
                    $hasName = company_table_has_column('admin_companies', 'name');
                    $hasCompanyNameAr = company_table_has_column('admin_companies', 'company_name_ar');

                    if ($hasCompanyNameAr && $hasName && $hasCompanyName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.company_name_ar, ""), NULLIF(c.name, ""), NULLIF(c.company_name, ""), c.email)';
                    } elseif ($hasCompanyNameAr && $hasName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.company_name_ar, ""), NULLIF(c.name, ""), c.email)';
                    } elseif ($hasCompanyNameAr && $hasCompanyName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.company_name_ar, ""), NULLIF(c.company_name, ""), c.email)';
                    } elseif ($hasName && $hasCompanyName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.name, ""), NULLIF(c.company_name, ""), c.email)';
                    } elseif ($hasName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.name, ""), c.email)';
                    } elseif ($hasCompanyName) {
                        $companyNameExpr = 'COALESCE(NULLIF(c.company_name, ""), c.email)';
                    } else {
                        $companyNameExpr = 'c.email';
                    }
                }

                $whereLogin = '';
                $bindTypes = '';
                $bindValues = array();

                if ($hasEmailCol && $hasUsernameCol) {
                    $whereLogin = '(u.email = ? OR u.username = ?)';
                    $bindTypes = 'ss';
                    $bindValues[] = $identifier;
                    $bindValues[] = $identifier;
                } elseif ($hasEmailCol) {
                    if (!$isEmailIdentifier) {
                        $error = 'صيغة البريد الإلكتروني غير صحيحة.';
                    }
                    $whereLogin = 'u.email = ?';
                    $bindTypes = 's';
                    $bindValues[] = $identifier;
                } else {
                    $whereLogin = 'u.username = ?';
                    $bindTypes = 's';
                    $bindValues[] = $identifier;
                }

                if ($error === '' && $hasCompaniesTable && $hasCompanyIdOnUsers) {
                    $companySubStart = company_table_has_column('admin_companies', 'subscription_start') ? 'c.subscription_start' : 'NULL';
                    $companySubEnd = company_table_has_column('admin_companies', 'subscription_end') ? 'c.subscription_end' : 'NULL';

                    $sql = 'SELECT ' . $fields . ', c.id AS cid, ' . $companyNameExpr . ' AS company_name, c.status AS company_status, ' . $companySubStart . ' AS subscription_start, ' . $companySubEnd . ' AS subscription_end FROM users u LEFT JOIN admin_companies c ON c.id = u.company_id WHERE ' . $whereLogin . ' LIMIT 1';
                } elseif ($error === '') {
                    $fallbackCompanyId = $hasCompanyIdOnUsers ? 'u.company_id' : '0';
                    $sql = 'SELECT ' . $fields . ', ' . $fallbackCompanyId . ' AS cid, "" AS company_name, "active" AS company_status, NULL AS subscription_start, NULL AS subscription_end FROM users u WHERE ' . $whereLogin . ' LIMIT 1';
                }

                if ($error !== '') {
                    $sql = '';
                }

                $stmt = $sql !== '' ? mysqli_prepare($conn, $sql) : false;

                if (!$stmt) {
                    $error = 'تعذر تسجيل الدخول حالياً.';
                } else {
                    if ($bindTypes === 'ss') {
                        mysqli_stmt_bind_param($stmt, $bindTypes, $bindValues[0], $bindValues[1]);
                    } else {
                        mysqli_stmt_bind_param($stmt, $bindTypes, $bindValues[0]);
                    }
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $user = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);

                    $passOk = false;
                    $needsRehash = false;

                    if ($user) {
                        if (password_verify($password, $user['password'])) {
                            $passOk = true;
                            if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                                $needsRehash = true;
                            }
                        } elseif (hash_equals((string)$user['password'], $password)) {
                            // Backward compatibility for old plain passwords, migrate immediately.
                            $passOk = true;
                            $needsRehash = true;
                        }
                    }

                    if (!$user || !$passOk) {
                        $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
                    } else {
                        $isDeletedUser = false;
                        if (company_users_has_column('is_deleted') && isset($user['is_deleted']) && intval($user['is_deleted']) === 1) {
                            $isDeletedUser = true;
                        }
                        if (company_users_has_column('deleted_at') && isset($user['deleted_at']) && trim((string)$user['deleted_at']) !== '') {
                            $isDeletedUser = true;
                        }
                        if ($isDeletedUser) {
                            $error = 'لم يعد حسابك نشط بعد الان';
                        }

                        // Check #1: users.status = active
                        if ($error === '' && company_users_has_column('status') && isset($user['status']) && strtolower((string)$user['status']) !== 'active') {
                            $error = 'الحساب معطّل، يرجى التواصل مع مدير شركتك.';
                        }

                        // Check #2: companies.status = active
                        if ($error === '') {
                            if ($hasCompaniesTable && $hasCompanyIdOnUsers) {
                                if (!isset($user['cid']) || intval($user['cid']) <= 0) {
                                    $error = 'الحساب غير مرتبط بشركة. يرجى مراجعة الإدارة.';
                                } elseif (!isset($user['company_status']) || strtolower((string)$user['company_status']) !== 'active') {
                                    $error = 'الشركة موقوفة حالياً. يرجى التواصل مع الدعم.';
                                } elseif (!empty($user['subscription_end']) && strtotime($user['subscription_end']) < strtotime(date('Y-m-d'))) {
                                    $error = 'انتهت صلاحية اشتراك الشركة. يرجى التجديد.';
                                }
                            }
                        }

                        if ($error === '') {
                            if ($needsRehash) {
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $uStmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
                                if ($uStmt) {
                                    $uid = intval($user['id']);
                                    mysqli_stmt_bind_param($uStmt, 'si', $newHash, $uid);
                                    mysqli_stmt_execute($uStmt);
                                    mysqli_stmt_close($uStmt);
                                }
                            }

                            if (company_users_has_column('last_login_at')) {
                                $updLogin = mysqli_prepare($conn, 'UPDATE users SET last_login_at = NOW() WHERE id = ?');
                                if ($updLogin) {
                                    $uid = intval($user['id']);
                                    mysqli_stmt_bind_param($updLogin, 'i', $uid);
                                    mysqli_stmt_execute($updLogin);
                                    mysqli_stmt_close($updLogin);
                                }
                            }

                            $companyRow = array(
                                'id' => intval($user['cid']),
                                'company_name' => isset($user['company_name']) ? $user['company_name'] : ''
                            );

                            company_login_success($user, $companyRow);

                            $to = company_dashboard_for_role($user['role']);
                            header('Location: ' . $to);
                            exit();
                        }
                    }
                }
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول مستخدمي الشركات | EMS</title>
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <style>
        :root {
            --ink: #102443;
            --ink-2: #30527f;
            --gold: #d6a700;
            --line: rgba(16,36,67,0.09);
            --ok: #0f8a5f;
            --danger: #c0392b;
            --muted: #627791;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Cairo', sans-serif;
            background: radial-gradient(circle at top right, rgba(214,167,0,0.16), transparent 28%), linear-gradient(135deg, #edf2f8, #f8fafd 58%, #edf1f8);
            display: grid;
            place-items: center;
            padding: 22px;
            color: var(--ink);
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.8);
            padding: 32px 28px;
            box-shadow: 0 22px 60px rgba(16,36,67,0.14);
        }
        .badge {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            border-radius: 999px;
            padding: 8px 12px;
            background: rgba(214,167,0,0.14);
            border: 1px solid rgba(214,167,0,0.3);
            color: #9b7705;
            font-size: 0.84rem;
            font-weight: 800;
        }
        h1 { margin: 14px 0 6px; font-size: 1.58rem; }
        .sub { margin: 0 0 20px; color: var(--muted); font-size: 0.9rem; }
        .alert {
            border-radius: 12px;
            padding: 11px 13px;
            margin-bottom: 14px;
            font-size: 0.9rem;
        }
        .ok { background: rgba(15,138,95,0.1); border: 1px solid rgba(15,138,95,0.2); color: var(--ok); }
        .err { background: rgba(192,57,43,0.1); border: 1px solid rgba(192,57,43,0.2); color: var(--danger); }

        .field { margin-bottom: 12px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--ink-2);
        }
        .wrap { position: relative; }
        .wrap i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 38px 12px 12px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        input:focus {
            outline: none;
            border-color: rgba(214,167,0,0.8);
            box-shadow: 0 0 0 4px rgba(214,167,0,0.12);
        }
        .actions {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .link {
            color: var(--ink-2);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.86rem;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--ink), #1f4f77);
            cursor: pointer;
            width: 100%;
            margin-top: 14px;
            box-shadow: 0 12px 24px rgba(16,36,67,0.2);
        }
        .foot {
            margin-top: 14px;
            color: var(--muted);
            font-size: 0.83rem;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge"><i class="fas fa-building-shield"></i> بوابة مستخدمي الشركات</span>
        <h1>تسجيل الدخول</h1>
        <p class="sub">دخول بالبريد الإلكتروني أو اسم المستخدم وكلمة المرور.</p>

        <?php if ($statusMessage !== ''): ?>
            <div class="alert ok"><?php echo e($statusMessage); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <div class="field">
                <label for="email">البريد الإلكتروني أو اسم المستخدم</label>
                <div class="wrap">
                    <i class="fas fa-at"></i>
                    <input type="text" id="email" name="email" maxlength="150" required value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                </div>
            </div>

            <div class="field">
                <label for="password">كلمة المرور</label>
                <div class="wrap">
                    <i class="fas fa-key"></i>
                    <input type="password" id="password" name="password" maxlength="255" required>
                </div>
            </div>

            <div class="actions">
                <a class="link" href="<?php echo e(company_url('forgot_password.php')); ?>">نسيت كلمة المرور؟</a>
                <a class="link" href="<?php echo e(company_url('register.php')); ?>">تسجيل شركة جديدة</a>
            </div>

            <button class="btn" type="submit">دخول</button>
        </form>

        <div class="foot">بعد النجاح سيتم تحميل صلاحيات الدور، نطاق المشروع للمستخدم، وبيانات باقة الشركة ثم التوجيه تلقائياً للوحة المناسبة.</div>
    </div>
</body>
</html>


