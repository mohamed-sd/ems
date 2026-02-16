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
                $sql = "SELECT id, name, username, password, phone, role, project_id, parent_id ,  created_at, updated_at 
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
                                'project' => $user['project_id'],
                                'parent' => $user['parent_id'],
                                'created_at' => $user['created_at'],
                                'updated_at' => $user['updated_at'],
                                'last_login' => date('Y-m-d H:i:s')
                            );

                            header("Location: main/dashboard.php");
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: "Cairo", sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(-45deg, #1a1a2e, #16213e, #0f3460, #1a1a2e);
      background-size: 400% 400%;
      animation: gradientShift 15s ease infinite;
      position: relative;
      overflow: hidden;
    }
    
    /* Animated Background Particles */
    body::before,
    body::after {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.3;
      animation: float 20s ease-in-out infinite;
    }
    
    body::before {
      background: #ffcc00;
      top: -100px;
      right: -100px;
      animation-delay: -5s;
    }
    
    body::after {
      background: #00d4ff;
      bottom: -100px;
      left: -100px;
      animation-duration: 25s;
    }
    
    @keyframes gradientShift {
      0%, 100% {
        background-position: 0% 50%;
      }
      50% {
        background-position: 100% 50%;
      }
    }
    
    @keyframes float {
      0%, 100% {
        transform: translate(0, 0) rotate(0deg);
      }
      33% {
        transform: translate(50px, -50px) rotate(120deg);
      }
      66% {
        transform: translate(-50px, 50px) rotate(240deg);
      }
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes logoFloat {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-10px);
      }
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    @keyframes glow {
      0%, 100% {
        box-shadow: 0 10px 40px rgba(255, 204, 0, 0.3),
                    0 0 20px rgba(255, 204, 0, 0.2);
      }
      50% {
        box-shadow: 0 10px 50px rgba(255, 204, 0, 0.5),
                    0 0 30px rgba(255, 204, 0, 0.4);
      }
    }
    
    .login-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 40px 35px;
      border-radius: 20px;
      width: 420px;
      max-width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5),
                  0 0 0 1px rgba(255, 255, 255, 0.1);
      animation: fadeInUp 0.8s ease-out;
      position: relative;
      z-index: 10;
    }
    
    .logo-container {
      text-align: center;
      margin-bottom: 20px;
      animation: logoFloat 3s ease-in-out infinite;
    }
    
    h1.logo {
      font-size: 48px;
      font-weight: 900;
      background: linear-gradient(135deg, #ffcc00, #ff9900);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 5px;
      letter-spacing: 2px;
      text-shadow: 0 2px 10px rgba(255, 204, 0, 0.3);
    }
    
    .logo-subtitle {
      font-size: 13px;
      color: #666;
      font-weight: 600;
      letter-spacing: 1px;
    }
    
    hr {
      border: none;
      border-top: 2px solid #f0f0f0;
      margin: 20px 0 25px;
      background: linear-gradient(to right, transparent, #ffcc00, transparent);
      height: 2px;
    }
    
    h2 {
      text-align: center;
      margin-bottom: 25px;
      font-size: 24px;
      color: #1a1a2e;
      font-weight: 700;
    }
    
    .input-group {
      position: relative;
      margin-bottom: 20px;
    }
    
    .input-group i {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 18px;
      transition: all 0.3s ease;
      z-index: 2;
    }
    
    input {
      width: 100%;
      padding: 15px 45px 15px 15px;
      border-radius: 12px;
      border: 2px solid #e0e0e0;
      font-size: 15px;
      font-family: "Cairo", sans-serif;
      transition: all 0.3s ease;
      background: #f8f9fa;
      color: #333;
    }
    
    input:focus {
      outline: none;
      border-color: #ffcc00;
      background: #fff;
      box-shadow: 0 0 0 4px rgba(255, 204, 0, 0.1),
                  0 2px 8px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }
    
    input:focus + i {
      color: #ffcc00;
      transform: translateY(-50%) scale(1.1);
    }
    
    button {
      width: 100%;
      padding: 15px;
      border-radius: 12px;
      border: none;
      background: linear-gradient(135deg, #ffcc00, #ff9900);
      color: #1a1a2e;
      font-size: 17px;
      font-weight: 700;
      cursor: pointer;
      font-family: "Cairo", sans-serif;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      margin-top: 10px;
      box-shadow: 0 4px 15px rgba(255, 204, 0, 0.3);
    }
    
    button::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px rgba(255, 204, 0, 0.5);
      animation: glow 2s ease-in-out infinite;
    }
    
    button:active::before {
      width: 300px;
      height: 300px;
    }
    
    button:active {
      transform: translateY(0);
    }
    
    /* Ripple Effect */
    .ripple {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      animation: rippleEffect 0.6s ease-out;
      pointer-events: none;
    }
    
    @keyframes rippleEffect {
      from {
        transform: scale(0);
        opacity: 1;
      }
      to {
        transform: scale(2);
        opacity: 0;
      }
    }
    
    .error {
      color: #d32f2f;
      background: linear-gradient(135deg, #ffebee, #ffcdd2);
      padding: 15px;
      border-radius: 12px;
      margin-bottom: 20px;
      text-align: center;
      border-right: 4px solid #d32f2f;
      font-weight: 600;
      animation: shake 0.5s ease, fadeInUp 0.5s ease;
      box-shadow: 0 4px 12px rgba(211, 47, 47, 0.2);
    }
    
    .note {
      font-size: 12px;
      color: #666;
      margin-top: 15px;
      text-align: center;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      border-right: 3px solid #ffcc00;
    }
    
    /* Loading Animation */
    .btn-loading {
      pointer-events: none;
      opacity: 0.7;
    }
    
    .btn-loading::after {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      top: 50%;
      left: 50%;
      margin: -8px 0 0 -8px;
      border: 2px solid #1a1a2e;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    /* Responsive Design */
    @media (max-width: 480px) {
      .login-container {
        padding: 30px 25px;
      }
      
      h1.logo {
        font-size: 36px;
      }
      
      h2 {
        font-size: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo-container">
      <h1 class="logo">E.P.S</h1>
      <p class="logo-subtitle">نظام إدارة المعدات</p>
    </div>
    <hr>
    <h2>تسجيل الدخول</h2>

    <?php if (!empty($error)): ?>
      <div class="error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

      <div class="input-group">
        <input id="username" name="username" type="text" maxlength="50" placeholder="اسم المستخدم" required>
        <i class="fas fa-user"></i>
      </div>

      <div class="input-group">
        <input id="password" name="password" type="password" maxlength="128" placeholder="كلمة المرور" required>
        <i class="fas fa-lock"></i>
      </div>

      <button type="submit" id="loginBtn">
        <span>دخول</span>
      </button>
    </form>

    <p class="note">
      <i class="fas fa-shield-alt"></i>
      عدد محاولات الدخول المتاحة: <?php echo e($max_attempts); ?> — القفل: <?php echo e($lockout_minutes); ?> دقيقة
    </p>
  </div>

  <script>
    // Button Loading Animation
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.classList.add('btn-loading');
      btn.querySelector('span').style.opacity = '0';
    });

    // Add ripple effect
    document.getElementById('loginBtn').addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      ripple.classList.add('ripple');
      
      this.appendChild(ripple);
      
      setTimeout(() => ripple.remove(), 600);
    });
  </script>
</body>
</html>
