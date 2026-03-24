<?php
require_once __DIR__ . '/includes/auth.php';

if (super_admin_is_logged_in()) {
    super_admin_redirect('dashboard');
}

$token = trim(isset($_REQUEST['token']) ? $_REQUEST['token'] : '');
$error = '';
$message = '';
$resetRow = null;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'رابط إعادة التعيين غير صالح.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = mysqli_prepare($conn, 'SELECT pr.id, pr.super_admin_id, sa.name, sa.email FROM super_admin_password_resets pr INNER JOIN super_admins sa ON sa.id = pr.super_admin_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND sa.is_active = 1 LIMIT 1');

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $tokenHash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $resetRow = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }

    if (!$resetRow) {
        $error = 'هذا الرابط منتهي الصلاحية أو تم استخدامه سابقاً.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $resetRow) {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد المحاولة.';
    } else {
        $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
        $confirmPassword = trim(isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

        if (!validate_length($password, 8, 255)) {
            $error = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.';
        } elseif ($password !== $confirmPassword) {
            $error = 'تأكيد كلمة المرور غير مطابق.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $adminId = intval($resetRow['super_admin_id']);
            $resetId = intval($resetRow['id']);

            $updatePasswordStmt = mysqli_prepare($conn, 'UPDATE super_admins SET password = ? WHERE id = ?');
            $markUsedStmt = mysqli_prepare($conn, 'UPDATE super_admin_password_resets SET used_at = NOW() WHERE id = ?');
            $expireOthersStmt = mysqli_prepare($conn, 'UPDATE super_admin_password_resets SET used_at = NOW() WHERE super_admin_id = ? AND used_at IS NULL');

            if ($updatePasswordStmt && $markUsedStmt && $expireOthersStmt) {
                mysqli_begin_transaction($conn);
                $transactionOk = true;

                mysqli_stmt_bind_param($updatePasswordStmt, 'si', $passwordHash, $adminId);
                $transactionOk = $transactionOk && mysqli_stmt_execute($updatePasswordStmt);

                mysqli_stmt_bind_param($markUsedStmt, 'i', $resetId);
                $transactionOk = $transactionOk && mysqli_stmt_execute($markUsedStmt);

                mysqli_stmt_bind_param($expireOthersStmt, 'i', $adminId);
                $transactionOk = $transactionOk && mysqli_stmt_execute($expireOthersStmt);

                if ($transactionOk) {
                    mysqli_commit($conn);
                    mysqli_stmt_close($updatePasswordStmt);
                    mysqli_stmt_close($markUsedStmt);
                    mysqli_stmt_close($expireOthersStmt);
                    super_admin_redirect('login', array('reset' => 'success'));
                }

                mysqli_rollback($conn);
            }

            if ($updatePasswordStmt) {
                mysqli_stmt_close($updatePasswordStmt);
            }
            if ($markUsedStmt) {
                mysqli_stmt_close($markUsedStmt);
            }
            if ($expireOthersStmt) {
                mysqli_stmt_close($expireOthersStmt);
            }

            $error = 'تعذر تحديث كلمة المرور حالياً.';
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
    <title>الإدارة العليا | تعيين كلمة مرور جديدة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #edf2f7, #f7f2e5); font-family: 'Cairo', sans-serif; color: #102443; }
        .card { width: 100%; max-width: 520px; background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 24px 55px rgba(16,36,67,0.12); }
        h1 { margin: 0 0 8px; }
        p { margin: 0 0 24px; color: #61738f; line-height: 1.8; }
        .error { margin-bottom: 18px; border-radius: 14px; padding: 12px 14px; background: rgba(192,57,43,0.08); color: #c0392b; border: 1px solid rgba(192,57,43,0.18); }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; }
        input { width: 100%; border: 1px solid rgba(16,36,67,0.1); border-radius: 14px; padding: 13px 14px; font-family: inherit; font-size: 1rem; }
        input:focus { outline: none; border-color: rgba(214,167,0,0.8); box-shadow: 0 0 0 4px rgba(214,167,0,0.12); }
        button, a { border-radius: 14px; padding: 13px 18px; font-family: inherit; font-weight: 800; text-decoration: none; }
        button { border: none; background: #102443; color: #fff; cursor: pointer; }
        a { display: inline-flex; margin-top: 12px; background: #f3f5f8; color: #102443; }
    </style>
</head>
<body>
    <div class="card">
        <h1>تعيين كلمة مرور جديدة</h1>
        <p>أدخل كلمة مرور جديدة لحساب الإدارة العليا. سيتم تعطيل رابط التعيين مباشرة بعد الاستخدام.</p>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($error === '' && $resetRow): ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <div class="field">
                    <label for="password">كلمة المرور الجديدة</label>
                    <input type="password" id="password" name="password" maxlength="255" required>
                </div>
                <div class="field">
                    <label for="confirm_password">تأكيد كلمة المرور</label>
                    <input type="password" id="confirm_password" name="confirm_password" maxlength="255" required>
                </div>
                <button type="submit">تحديث كلمة المرور</button>
            </form>
        <?php endif; ?>
        <a href="<?php echo e(super_admin_url('login')); ?>">العودة لتسجيل الدخول</a>
    </div>
</body>
</html>