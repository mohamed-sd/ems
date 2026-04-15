<?php
require_once __DIR__ . '/includes/auth.php';

if (super_admin_is_logged_in()) {
    super_admin_redirect('dashboard');
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد المحاولة.';
    } else {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');

        if (!validate_email($email) || !validate_length($email, 5, 150)) {
            $error = 'أدخل بريداً إلكترونياً صحيحاً.';
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT id, name, email FROM super_admins WHERE email = ? AND is_active = 1 LIMIT 1');

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $admin = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if ($admin) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $adminId = intval($admin['id']);

                    $cleanupStmt = mysqli_prepare($conn, 'DELETE FROM super_admin_password_resets WHERE super_admin_id = ? OR expires_at < NOW()');
                    if ($cleanupStmt) {
                        mysqli_stmt_bind_param($cleanupStmt, 'i', $adminId);
                        mysqli_stmt_execute($cleanupStmt);
                        mysqli_stmt_close($cleanupStmt);
                    }

                    $insertStmt = mysqli_prepare($conn, 'INSERT INTO super_admin_password_resets (super_admin_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, 'is', $adminId, $tokenHash);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);

                        if (!super_admin_send_reset_email($admin['email'], $admin['name'], $token)) {
                            error_log('Failed to send super admin reset email to ' . $admin['email']);
                        }
                    }
                }
            }

            $message = 'إذا كان البريد الإلكتروني مسجلاً ونشطاً فسيتم إرسال رابط إعادة التعيين إليه.';
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
    <title>الإدارة العليا | استعادة كلمة المرور</title>
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #eef2f7, #f7f4ea); font-family: 'Cairo', sans-serif; color: #102443; }
        .card { width: 100%; max-width: 480px; background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 24px 55px rgba(16,36,67,0.12); }
        h1 { margin: 0 0 10px; font-size: 1.8rem; }
        p { margin: 0 0 24px; line-height: 1.9; color: #61738f; }
        label { display: block; margin-bottom: 8px; font-weight: 700; }
        input { width: 100%; border: 1px solid rgba(16,36,67,0.1); border-radius: 14px; padding: 13px 14px; font-family: inherit; font-size: 1rem; }
        input:focus { outline: none; border-color: rgba(214,167,0,0.8); box-shadow: 0 0 0 4px rgba(214,167,0,0.12); }
        .message, .error { margin-bottom: 18px; border-radius: 14px; padding: 12px 14px; }
        .message { background: rgba(15, 138, 95, 0.08); color: #0f8a5f; border: 1px solid rgba(15,138,95,0.18); }
        .error { background: rgba(192,57,43,0.08); color: #c0392b; border: 1px solid rgba(192,57,43,0.18); }
        .actions { display: flex; gap: 12px; margin-top: 20px; }
        button, a { border-radius: 14px; padding: 13px 18px; font-family: inherit; font-weight: 800; text-decoration: none; }
        button { border: none; background: #102443; color: #fff; cursor: pointer; flex: 1; }
        a { background: #f3f5f8; color: #102443; }
    </style>
</head>
<body>
    <div class="card">
        <h1>استعادة كلمة المرور</h1>
        <p>سيتم إرسال رابط إعادة التعيين إلى البريد الإلكتروني المحفوظ في حساب الإدارة العليا.</p>
        <?php if ($message !== ''): ?>
            <div class="message"><?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <label for="email">البريد الإلكتروني</label>
            <input type="email" id="email" name="email" maxlength="150" required value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
            <div class="actions">
                <button type="submit">إرسال رابط إعادة التعيين</button>
                <a href="<?php echo e(super_admin_url('login')); ?>">العودة لتسجيل الدخول</a>
            </div>
        </form>
    </div>
</body>
</html>

