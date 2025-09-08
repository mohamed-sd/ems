<?php
// index.php
// صفحة تسجيل دخول محسّنة أمنيًا (بدون تشفير كلمة المرور، ومهيأة لتعمل مع PHP 5.6)

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$cookieParams = session_get_cookie_params();
session_set_cookie_params(
    0,
    $cookieParams['path'],
    $cookieParams['domain'],
    $secure,
    true // HttpOnly
);
ini_set('session.use_strict_mode', 1);
session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");

// ملف الاتصال بقاعدة البيانات
require_once "config.php";

// إعدادات الحماية ضد القوة الغاشمة
$max_attempts = 5;
$lockout_minutes = 15;
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = null;
}

function is_locked_out() {
    global $max_attempts, $lockout_minutes;
    if (!empty($_SESSION['last_attempt_time']) && $_SESSION['login_attempts'] >= $max_attempts) {
        $elapsed = time() - $_SESSION['last_attempt_time'];
        if ($elapsed < ($lockout_minutes * 60)) {
            return true;
        } else {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = null;
            return false;
        }
    }
    return false;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_locked_out()) {
        $remaining = ($lockout_minutes * 60) - (time() - $_SESSION['last_attempt_time']);
        $mins = ceil($remaining / 60);
        $error = "تم حظر المحاولات مؤقتًا. حاول مرة أخرى بعد $mins دقيقة.";
    } else {
        $posted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!verify_csrf_token($posted_token)) {
            $error = "رمز الأمان غير صحيح. أعد تحميل الصفحة وحاول مرة أخرى.";
        } else {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';

            if ($username === '' || $password === '') {
                $error = "الرجاء ملء اسم المستخدم وكلمة المرور.";
            } elseif (mb_strlen($username) > 50 || mb_strlen($password) > 128) {
                $error = "المدخلات أكبر من الحد المسموح.";
            } else {
                $sql = "SELECT id, name, username, password, phone, role, project, created_at, updated_at 
                        FROM users WHERE username = ? LIMIT 1";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $username);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    if ($res && mysqli_num_rows($res) === 1) {
                        $user = mysqli_fetch_assoc($res);
                        if (isset($user['password']) && $user['password'] === $password) {
                            $_SESSION['login_attempts'] = 0;
                            $_SESSION['last_attempt_time'] = null;
                            session_regenerate_id(true);

                            $_SESSION['user'] = array(
                                'id' => $user['id'],
                                'name' => $user['name'],
                                'username' => $user['username'],
                                'phone' => $user['phone'],
                                'role' => $user['role'],
                                'project' => $user['project'],
                                'created_at' => $user['created_at'],
                                'updated_at' => $user['updated_at'],
                                'last_login' => date('Y-m-d H:i:s')
                            );

                            header("Location: dashbourd.php");
                            exit();
                        } else {
                            $_SESSION['login_attempts'] = (isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0) + 1;
                            $_SESSION['last_attempt_time'] = time();
                            $remaining_tries = max(0, $max_attempts - $_SESSION['login_attempts']);
                            $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: $remaining_tries";
                        }
                    } else {
                        $_SESSION['login_attempts'] = (isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0) + 1;
                        $_SESSION['last_attempt_time'] = time();
                        $remaining_tries = max(0, $max_attempts - $_SESSION['login_attempts']);
                        $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: $remaining_tries";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    error_log("DB prepare failed: " . mysqli_error($conn));
                    $error = "حدث خطأ داخلي، حاول لاحقًا.";
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <title>إيكوبيشن | تسجيل الدخول</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:"Cairo",sans-serif}
    body{display:flex;align-items:center;justify-content:center;height:100vh;background:#000022;color:#111}
    .login-container{background:#fff;padding:28px;border-radius:12px;width:360px;box-shadow:0 6px 20px rgba(0,0,0,.4)}
    h1.logo{font-size:28px;text-align:center;color:#000022;margin-bottom:8px;font-weight:900}
    hr{border:none;border-top:1px solid #eee;margin:8px 0 14px}
    h2{text-align:center;margin-bottom:12px;font-size:18px;color:#333}
    input,button{width:100%;padding:12px;border-radius:8px;border:1px solid #ccc;margin-bottom:10px;font-size:15px}
    button{background:#ffcc00;color:#000022;border:0;cursor:pointer;font-weight:700}
    .error{color:#b00020;background:#ffe9ea;padding:10px;border-radius:8px;margin-bottom:12px;text-align:center}
    .note{font-size:12px;color:#666;margin-top:6px;text-align:center}
  </style>
</head>
<body>
  <div class="login-container">
    <h1 class="logo">E.M.S</h1>
    <hr>
    <h2>تسجيل الدخول</h2>

    <?php if (!empty($error)): ?>
      <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

      <input id="username" name="username" type="text" maxlength="50" placeholder="اسم المستخدم" required>
      <input id="password" name="password" type="password" maxlength="128" placeholder="كلمة المرور" required>
      <button type="submit">دخول</button>
    </form>

    <p class="note">عدد محاولات الدخول المتاحة: <?php echo e($max_attempts); ?> — القفل: <?php echo e($lockout_minutes); ?> دقيقة</p>
  </div>
</body>
</html>
