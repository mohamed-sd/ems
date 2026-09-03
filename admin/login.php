<?php
/* ══ تجميدُ الشركةِ الواحدة (FREEZE · single-company) ══════════════════════
   ◆ **بابُ الإدارةِ العليا مُغلقٌ لا محذوف**: تحوَّل EMS من منصّةٍ متعددةِ
     المستأجرين إلى نظامِ شركةٍ واحدة، فبوابةُ دخولِ المزوّد لم يعد لها معنًى.
   ◆ **الإغلاقُ في الأعلى قبلَ أيِّ تحميلٍ أو جلسة** — لا مسارَ يُنشئ
     `$_SESSION['super_admin']` بعد اليوم، فـ`ems_platform_db()` و
     `TenantContext::fromSuperAdminSession()` يبقيان ميتَين بنيويًّا.
   ⛔ لا يُحذف الملفُّ ولا مُلحقاتُه — الإغلاقُ قرارُ تشغيلٍ قابلٌ للنقض. */
header('HTTP/1.1 403 Forbidden');
echo 'This portal has been permanently disabled.';
exit();

require_once __DIR__ . '/includes/auth.php';

if (super_admin_is_logged_in()) {
    super_admin_redirect('dashboard');
}

$error = '';
$statusMessage = '';

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $statusMessage = 'تم تحديث كلمة المرور. يمكنك تسجيل الدخول الآن.';
}

if (isset($_GET['security']) && $_GET['security'] === '1') {
    $statusMessage = 'تم إنهاء الجلسة السابقة لأسباب أمنية. يرجى تسجيل الدخول مرة أخرى.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (super_admin_login_locked_out()) {
        $error = 'تم قفل تسجيل الدخول مؤقتا. حاول بعد 15 دقيقة.';
        log_security_event('SUPER_ADMIN_LOGIN_LOCKED', 'Too many login attempts for admin portal');
    } elseif (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
        log_security_event('SUPER_ADMIN_LOGIN_CSRF_FAIL', 'Invalid CSRF token on admin login');
    } else {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = trim(isset($_POST['password']) ? $_POST['password'] : '');

        if ($email === '' || $password === '') {
            $error = 'أدخل البريد الإلكتروني وكلمة المرور.';
        } elseif (!validate_email($email) || !validate_length($email, 5, 150) || !validate_length($password, 8, 255)) {
            $error = 'بيانات الدخول غير صحيحة.';
        } else {
            // [مُستثنى بنيويًا — مصادقة قبل-الجلسة · هـ-2] دخول المدير الأعلى يسبق وجود
            // جلسة super_admin التي تبنيها بوابة المزوّد نفسها — فقراءة super_admins بالبريد
            // لإثبات كلمة السر تبقى خامًا (لا يمكن لقناةٍ أن تصادق نفسها).
            $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, is_active, last_login_at FROM super_admins WHERE email = ? LIMIT 1');

            if (!$stmt) {
                $error = 'تعذر تنفيذ عملية تسجيل الدخول حاليا.';
            } else {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $admin = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if (!$admin || !password_verify($password, $admin['password'])) {
                    $error = super_admin_login_error();
                    log_security_event('SUPER_ADMIN_LOGIN_FAIL', 'Failed login for admin email: ' . substr($email, 0, 80));
                } elseif (intval($admin['is_active']) !== 1) {
                    $error = 'هذا الحساب موقوف حاليا.';
                    log_security_event('SUPER_ADMIN_LOGIN_DISABLED', 'Disabled admin tried to login: ' . substr($email, 0, 80));
                } else {
                    // [مُستثنى بنيويًا — مصادقة قبل-الجلسة] ختم آخر دخولٍ يجري لحظةَ نجاح
                    // المصادقة قبل بناء الجلسة — نفس عائلة قراءة الدخول أعلاه.
                    $updateStmt = mysqli_prepare($conn, 'UPDATE super_admins SET last_login_at = NOW() WHERE id = ?');
                    if ($updateStmt) {
                        $adminId = intval($admin['id']);
                        mysqli_stmt_bind_param($updateStmt, 'i', $adminId);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }

                    $admin['last_login_at'] = date('Y-m-d H:i:s');
                    super_admin_login_success($admin);
                    super_admin_redirect('dashboard');
                }
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<?php require_once __DIR__ . '/../includes/public_shell.php';
ems_public_head('الإدارة العليا | تسجيل الدخول'); ?>
    <style>
        :root {
            --font-ui: var(--font-ar, 'IBM Plex Sans Arabic', 'Tajawal', 'Cairo', sans-serif);
            --ink: #0F2240;
            --ink-soft: #27456f;
            --gold: #d6a700;
            --line: rgba(16, 36, 67, 0.10);
            --muted: #61738f;
            --danger: #c0392b;
            --success: #0f8a5f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-ui);
            background: linear-gradient(160deg, #0F2240 0%, #183152 40%, #1e4f77 100%);
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            padding: 40px 36px 36px;
        }
        .brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 56px; height: 56px;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: linear-gradient(135deg, #0F2240, #2563eb);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 24px rgba(15, 34, 64, 0.25);
        }
        .brand-icon i { color: var(--gold); font-size: 1.3rem; }
        .brand h1 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--ink);
            margin: 0 0 4px;
        }
        .brand p {
            color: var(--muted);
            font-size: 0.82rem;
            margin: 0;
        }
        .notice, .error-msg {
            border-radius: 12px;
            padding: 11px 14px;
            margin-bottom: 18px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notice {
            background: rgba(15, 138, 95, 0.08);
            border: 1px solid rgba(15, 138, 95, 0.18);
            color: var(--success);
        }
        .error-msg {
            background: rgba(192, 57, 43, 0.07);
            border: 1px solid rgba(192, 57, 43, 0.16);
            color: var(--danger);
        }
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--ink-soft);
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 0.9rem;
        }
        .input-wrap input {
            width: 100%;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            background: #f9fafb;
            padding: 12px 42px 12px 14px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .input-wrap input:focus {
            outline: none;
            background: #fff;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214, 167, 0, 0.12);
        }
        .forgot {
            display: block;
            text-align: left;
            margin-top: -8px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
        }
        .forgot:hover { color: var(--ink); }
        .submit {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #0F2240, #1e4f77);
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            padding: 13px 18px;
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.15s;
            box-shadow: 0 6px 20px rgba(15, 34, 64, 0.2);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .submit:hover {
            box-shadow: 0 10px 30px rgba(15, 34, 64, 0.3);
            transform: translateY(-1px);
        }
        .submit:active { transform: translateY(0); }
        .footer {
            text-align: center;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.75rem;
        }
        @media (max-width: 480px) {
            body { padding: 16px; align-items: flex-start; padding-top: 40px; }
            .login-card { padding: 32px 24px 28px; }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
            <h1>لوحة الإدارة العليا</h1>
            <p>منصة إنجاز</p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="notice"><i class="fas fa-check-circle"></i> <?php echo e($statusMessage); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <div class="input-wrap">
                    <i class="fas fa-at"></i>
                    <input type="email" id="email" name="email" maxlength="150" required value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>" placeholder="admin@example.com">
                </div>
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" maxlength="255" required placeholder="••••••••">
                </div>
            </div>
            <a class="forgot" href="<?php echo e(super_admin_url('forgot-password')); ?>">نسيت كلمة المرور؟</a>
            <button class="submit" type="submit"><i class="fas fa-arrow-right-to-bracket"></i> تسجيل الدخول</button>
        </form>

        <div class="footer">منصة إنجاز &copy; <?php echo date('Y'); ?></div>
    </div>
</body>
</html>
