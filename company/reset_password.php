<?php
require_once __DIR__ . '/auth.php';

if (company_is_logged_in()) {
    $to = isset($_SESSION['company_user']['dashboard']) ? $_SESSION['company_user']['dashboard'] : '/ems/main/dashboard.php';
    header('Location: ' . $to);
    exit();
}

$token = trim(isset($_REQUEST['token']) ? $_REQUEST['token'] : '');
$error = '';
$resetRow = null;

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'رابط إعادة التعيين غير صالح.';
} elseif (!company_table_exists('company_user_password_resets')) {
    $error = 'جدول reset غير موجود. نفّذ database/company_portal_auth.sql.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = mysqli_prepare(
        $conn,
        'SELECT pr.id, pr.user_id, u.name, u.email
         FROM company_user_password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
         LIMIT 1'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $tokenHash);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $resetRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }

    if (!$resetRow) {
        $error = 'الرابط منتهي أو تم استخدامه مسبقاً.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $resetRow) {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح.';
    } else {
        $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
        $confirm = trim(isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

        if (!validate_length($password, 8, 255)) {
            $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
        } elseif ($password !== $confirm) {
            $error = 'تأكيد كلمة المرور غير مطابق.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uid = intval($resetRow['user_id']);
            $rid = intval($resetRow['id']);

            $u1 = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
            $u2 = mysqli_prepare($conn, 'UPDATE company_user_password_resets SET used_at = NOW() WHERE id = ?');
            $u3 = mysqli_prepare($conn, 'UPDATE company_user_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');

            if ($u1 && $u2 && $u3) {
                mysqli_begin_transaction($conn);
                $ok = true;

                mysqli_stmt_bind_param($u1, 'si', $hash, $uid);
                $ok = $ok && mysqli_stmt_execute($u1);

                mysqli_stmt_bind_param($u2, 'i', $rid);
                $ok = $ok && mysqli_stmt_execute($u2);

                mysqli_stmt_bind_param($u3, 'i', $uid);
                $ok = $ok && mysqli_stmt_execute($u3);

                if ($ok) {
                    mysqli_commit($conn);
                    company_write_audit($uid, 0, 'password_reset_completed', 'بوابة الشركة', 'تم تعيين كلمة مرور جديدة');

                    mysqli_stmt_close($u1);
                    mysqli_stmt_close($u2);
                    mysqli_stmt_close($u3);

                    company_redirect('login.php', array('reset' => 'success'));
                } else {
                    mysqli_rollback($conn);
                }
            }

            if ($u1) mysqli_stmt_close($u1);
            if ($u2) mysqli_stmt_close($u2);
            if ($u3) mysqli_stmt_close($u3);

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
    <title>إعادة تعيين كلمة المرور | EMS Company</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:linear-gradient(135deg,#edf2f8,#f7f2e8); font-family:'Cairo',sans-serif; color:#102443; }
        .card { width:100%; max-width:520px; background:#fff; border-radius:22px; padding:30px; box-shadow:0 22px 56px rgba(16,36,67,0.14); }
        h1 { margin:0 0 8px; }
        p { margin:0 0 18px; color:#627791; }
        .alert { border-radius:12px; padding:11px 13px; margin-bottom:12px; font-size:.9rem; background:rgba(192,57,43,.1); color:#c0392b; border:1px solid rgba(192,57,43,.2); }
        label { display:block; margin-bottom:6px; font-weight:700; font-size:.84rem; color:#30527f; }
        input { width:100%; border:1px solid rgba(16,36,67,.12); border-radius:12px; padding:11px 12px; font-family:inherit; font-size:.95rem; margin-bottom:12px; }
        input:focus { outline:none; border-color:rgba(214,167,0,.8); box-shadow:0 0 0 4px rgba(214,167,0,.12); }
        button { border:none; border-radius:12px; background:#102443; color:#fff; padding:11px 14px; font-family:inherit; font-weight:800; cursor:pointer; }
        a { display:inline-block; margin-top:12px; color:#30527f; text-decoration:none; font-weight:700; font-size:.88rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>تعيين كلمة مرور جديدة</h1>
        <p>رابط إعادة التعيين صالح لمدة 60 دقيقة فقط.</p>

        <?php if ($error !== ''): ?><div class="alert"><?php echo e($error); ?></div><?php endif; ?>

        <?php if ($error === '' && $resetRow): ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="token" value="<?php echo e($token); ?>">

            <label for="password">كلمة المرور الجديدة</label>
            <input type="password" id="password" name="password" maxlength="255" required>

            <label for="confirm_password">تأكيد كلمة المرور</label>
            <input type="password" id="confirm_password" name="confirm_password" maxlength="255" required>

            <button type="submit">تحديث كلمة المرور</button>
        </form>
        <?php endif; ?>

        <a href="<?php echo e(company_url('login.php')); ?>">العودة لتسجيل الدخول</a>
    </div>
</body>
</html>
