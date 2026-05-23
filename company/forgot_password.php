<?php
require_once __DIR__ . '/auth.php';

if (company_is_logged_in()) {
    $to = isset($_SESSION['company_user']['dashboard']) ? $_SESSION['company_user']['dashboard'] : ems_url('main/dashboard.php');
    header('Location: ' . $to);
    exit();
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح.';
    } else {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if (!validate_email($email) || !validate_length($email, 5, 150)) {
            $error = 'أدخل بريداً إلكترونياً صحيحاً.';
        } else {
            $message = 'تم إيقاف إعادة التعيين التلقائي. يرجى التواصل مباشرة مع إدارة الشركة عبر البريد الإلكتروني أو رقم الهاتف المعتمد لاستعادة كلمة المرور.';
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
    <title>نسيت كلمة المرور | EMS Company</title>
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:linear-gradient(135deg,#edf2f8,#f7f2e8); font-family:'Cairo',sans-serif; color:#102443; }
        .card { width:100%; max-width:500px; background:#fff; border-radius:22px; padding:30px; box-shadow:0 22px 56px rgba(16,36,67,0.14); }
        h1 { margin:0 0 8px; }
        p { margin:0 0 18px; color:#627791; }
        .alert { border-radius:12px; padding:11px 13px; margin-bottom:12px; font-size:.9rem; }
        .ok { background:rgba(15,138,95,.1); color:#0f8a5f; border:1px solid rgba(15,138,95,.2); }
        .err { background:rgba(192,57,43,.1); color:#c0392b; border:1px solid rgba(192,57,43,.2); }
        label { display:block; margin-bottom:6px; font-weight:700; font-size:.84rem; color:#30527f; }
        input { width:100%; border:1px solid rgba(16,36,67,.12); border-radius:12px; padding:11px 12px; font-family:inherit; font-size:.95rem; }
        input:focus { outline:none; border-color:rgba(214,167,0,.8); box-shadow:0 0 0 4px rgba(214,167,0,.12); }
        .actions { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:14px; }
        button { border:none; border-radius:12px; background:#102443; color:#fff; padding:11px 14px; font-family:inherit; font-weight:800; cursor:pointer; }
        a { color:#30527f; text-decoration:none; font-weight:700; font-size:.88rem; }
    </style>
    <link rel="stylesheet" href="/ems/assets/css/site-identity.css">
</head>
<body class="standalone-brand">
    <div class="card">
        <h1>نسيت كلمة المرور</h1>
        <p>تم إلغاء الروابط الآلية لإعادة التعيين. استعادة كلمة المرور تتم فقط عبر التواصل المباشر مع إدارة الشركة.</p>

        <?php if ($message !== ''): ?><div class="alert ok"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert err"><?php echo e($error); ?></div><?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <label for="email">البريد الإلكتروني</label>
            <input type="email" id="email" name="email" maxlength="150" required value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
            <div class="actions">
                <a href="<?php echo e(company_url('login.php')); ?>">العودة لتسجيل الدخول</a>
                <button type="submit">عرض تعليمات التواصل</button>
            </div>
        </form>
    </div>
</body>
</html>
