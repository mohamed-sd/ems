<?php
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
        $error = 'تم قفل تسجيل الدخول مؤقتاً. حاول بعد 15 دقيقة.';
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
            $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, is_active, last_login_at FROM super_admins WHERE email = ? LIMIT 1');

            if (!$stmt) {
                $error = 'تعذر تنفيذ عملية تسجيل الدخول حالياً.';
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
                    $error = 'هذا الحساب موقوف حالياً.';
                    log_security_event('SUPER_ADMIN_LOGIN_DISABLED', 'Disabled admin tried to login: ' . substr($email, 0, 80));
                } else {
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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإدارة العليا | تسجيل الدخول</title>
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <style>
        :root {
            --ink: #102443;
            --ink-soft: #27456f;
            --gold: #d6a700;
            --sand: #f5f1e8;
            --card: #ffffff;
            --line: rgba(16, 36, 67, 0.08);
            --muted: #61738f;
            --danger: #c0392b;
            --success: #0f8a5f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Cairo', sans-serif;
            background: radial-gradient(circle at top right, rgba(214, 167, 0, 0.18), transparent 28%), linear-gradient(135deg, #eef2f7, #f8fafc 60%, #eef1f5);
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .shell {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(255,255,255,0.65);
            box-shadow: 0 20px 70px rgba(16, 36, 67, 0.14);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            overflow: hidden;
        }
        .hero {
            padding: 48px;
            background: linear-gradient(155deg, var(--ink), #183152 55%, #20456d);
            color: #fff;
            position: relative;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(214, 167, 0, 0.14);
            border: 1px solid rgba(214, 167, 0, 0.35);
            color: #f5cf55;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.9rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        .hero h1 {
            margin: 26px 0 14px;
            font-size: 2.4rem;
            line-height: 1.3;
            position: relative;
            z-index: 1;
        }
        .hero p {
            margin: 0;
            color: rgba(255,255,255,0.82);
            line-height: 1.9;
            position: relative;
            z-index: 1;
        }
        .hero-list {
            margin-top: 30px;
            display: grid;
            gap: 14px;
            position: relative;
            z-index: 1;
        }
        .hero-item {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hero-item i {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(214, 167, 0, 0.16);
            color: #f5cf55;
        }
        .panel {
            padding: 42px 34px;
            background: rgba(255,255,255,0.85);
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 1.65rem;
        }
        .panel p {
            margin: 0 0 24px;
            color: var(--muted);
        }
        .notice, .error {
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 0.95rem;
        }
        .notice {
            background: rgba(15, 138, 95, 0.08);
            border: 1px solid rgba(15, 138, 95, 0.18);
            color: var(--success);
        }
        .error {
            background: rgba(192, 57, 43, 0.08);
            border: 1px solid rgba(192, 57, 43, 0.18);
            color: var(--danger);
        }
        .field { margin-bottom: 16px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.84rem;
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
            color: var(--muted);
        }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            padding: 13px 44px 13px 14px;
            font-family: inherit;
            font-size: 1rem;
            color: var(--ink);
        }
        input:focus {
            outline: none;
            border-color: rgba(214, 167, 0, 0.75);
            box-shadow: 0 0 0 4px rgba(214, 167, 0, 0.13);
        }
        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 10px;
        }
        .link {
            color: var(--ink-soft);
            font-weight: 700;
            text-decoration: none;
        }
        .submit {
            width: 100%;
            margin-top: 18px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--ink), #1e4f77);
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 800;
            padding: 14px 18px;
            cursor: pointer;
            box-shadow: 0 14px 30px rgba(16, 36, 67, 0.18);
        }
        .foot-note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.8;
        }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .hero { display: none; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="hero-badge"><i class="fas fa-shield-halved"></i> بوابة الإدارة العليا</div>
            <h1>مسار دخول مستقل لمتابعة النظام على المستوى الإداري الأعلى</h1>
            <p>هذا المسار مخصص لحسابات super_admins فقط، مع جلسة مستقلة عن مستخدمي النظام الحاليين وإمكانية إعادة تعيين كلمة المرور عبر البريد الإلكتروني المحفوظ.</p>
            <div class="hero-list">
                <div class="hero-item"><i class="fas fa-user-lock"></i><div>تسجيل دخول منفصل بحقل اسم مستخدم يعتمد على البريد الإلكتروني.</div></div>
                <div class="hero-item"><i class="fas fa-envelope-open-text"></i><div>إرسال رابط إعادة التعيين إلى البريد الإلكتروني المسجل للحساب.</div></div>
                <div class="hero-item"><i class="fas fa-chart-column"></i><div>لوحة سريعة لقياس حالة النظام والعناصر الأساسية بعد الدخول.</div></div>
            </div>
        </section>
        <section class="panel">
            <h2>تسجيل دخول الإدارة العليا</h2>
            <p>استخدم البريد الإلكتروني المسجل في جدول super_admins.</p>
            <?php if ($statusMessage !== ''): ?>
                <div class="notice"><?php echo e($statusMessage); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <div class="field">
                    <label for="email">البريد الإلكتروني</label>
                    <div class="input-wrap">
                        <i class="fas fa-at"></i>
                        <input type="email" id="email" name="email" maxlength="150" required value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="password">كلمة المرور</label>
                    <div class="input-wrap">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" maxlength="255" required>
                    </div>
                </div>
                <div class="actions">
                    <a class="link" href="<?php echo e(super_admin_url('forgot-password')); ?>">نسيت كلمة المرور</a>
                    <span style="color: var(--muted); font-size: 0.84rem;">مسار آمن مخصص للإدارة العليا</span>
                </div>
                <button class="submit" type="submit">دخول لوحة الإدارة العليا</button>
            </form>
            <div class="foot-note">إذا تعذر استلام رسائل إعادة التعيين فتأكد من إعداد البريد في خادم PHP لأن الإرسال يعتمد على mail().</div>
        </section>
    </div>
</body>
</html>

