<?php
require_once "config.php";
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");

$max_attempts = 5;
$lockout_minutes = 15;

if (!isset($_SESSION['login_attempts'])) {
  $_SESSION['login_attempts'] = 0;
  $_SESSION['last_attempt_time'] = null;
}

function is_locked() {
  global $max_attempts, $lockout_minutes;
  if (!empty($_SESSION['last_attempt_time']) && $_SESSION['login_attempts'] >= $max_attempts) {
    if ((time() - $_SESSION['last_attempt_time']) < ($lockout_minutes * 60)) {
      return true;
    }
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = null;
  }
  return false;
}

$error = '';
if (isset($_GET['msg']) && trim($_GET['msg']) !== '') {
  $error = trim($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (is_locked()) {
    $error = "تم تجاوز عدد محاولات الدخول. حاول بعد {$lockout_minutes} دقيقة.";
  } elseif (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $error = "رمز الحماية غير صالح.";
  } else {
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p = trim(isset($_POST['password']) ? $_POST['password'] : '');

    if ($u === '' || $p === '') {
      $error = "يرجى إدخال اسم المستخدم وكلمة المرور.";
    } elseif (mb_strlen($u) > 50 || mb_strlen($p) > 128) {
      $error = "بيانات الاعتماد أطول من المسموح.";
    } else {
      $stmt = mysqli_prepare($conn, "SELECT id,name,username,password,phone,role,project_id,contract_id,company_id,parent_id,created_at,updated_at FROM users WHERE username=? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $u);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && mysqli_num_rows($res) === 1) {
          $user = mysqli_fetch_assoc($res);
          if (isset($user['password']) && password_verify($p, $user['password'])) {
            $sup = isset($user['role']) && strval($user['role']) === '-1';
            $cid = (isset($user['company_id']) && intval($user['company_id']) > 0) ? intval($user['company_id']) : 0;

            if (!$sup && $cid <= 0) {
              $_SESSION['login_attempts']++;
              $_SESSION['last_attempt_time'] = time();
              $error = "المستخدم غير مرتبط بشركة.";
              mysqli_stmt_close($stmt);
              goto login_end;
            }

            if (!$sup && $cid > 0 && db_table_has_column($conn, 'admin_companies', 'status')) {
              $company_status = 'active';
              $ss = @mysqli_prepare($conn, 'SELECT status FROM admin_companies WHERE id=? LIMIT 1');
              if ($ss) {
                mysqli_stmt_bind_param($ss, 'i', $cid);
                mysqli_stmt_execute($ss);
                $sr = mysqli_stmt_get_result($ss);
                $sw = $sr ? mysqli_fetch_assoc($sr) : null;
                mysqli_stmt_close($ss);
                if ($sw && isset($sw['status']) && trim($sw['status']) !== '') {
                  $company_status = strtolower(trim($sw['status']));
                }
              }

              if ($company_status !== 'active') {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "حالة الشركة غير نشطة.";
                mysqli_stmt_close($stmt);
                goto login_end;
              }
            }

            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = null;
            session_regenerate_id(true);
            $_SESSION['user'] = [
              'id' => $user['id'],
              'name' => $user['name'],
              'username' => $user['username'],
              'phone' => $user['phone'],
              'role' => $user['role'],
              'project_id' => $user['project_id'],
              'contract_id' => $user['contract_id'] ?? null,
              'company_id' => $cid > 0 ? $cid : null,
              'parent' => $user['parent_id'],
              'created_at' => $user['created_at'],
              'updated_at' => $user['updated_at'],
              'last_login' => date('Y-m-d H:i:s')
            ];

            // Record login event.
            if (class_exists('App\Services\ActivityLogService')) {
                \App\Services\ActivityLogService::logLogin();
            }

            header("Location: main/dashboard.php");
            exit();
          }
        }

        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: " . max(0, $max_attempts - $_SESSION['login_attempts']);
        mysqli_stmt_close($stmt);
      } else {
        $error = "حدث خطأ أثناء التحقق.";
      }
    }
  }
}

login_end:
$csrf = generate_csrf_token();
$errH = !empty($error) ? htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
$postU = isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
$csrfH = htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>إيكويبيشن - تسجيل الدخول</title>
  <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
  <link rel="stylesheet" href="/ems/assets/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --gold: #f7931a;
      --gold-dark: #da7c05;
      --line: #ededed;
      --txt-1: #1f1f1f;
      --txt-2: #9ba1a8;
      --input-bg: #eef3fb;
      --error: #c81f24;
    }

    html, body {
      height: 100%;
      font-family: 'Tajawal', sans-serif;
      overflow: hidden;
      background: #0f1114;
    }

    .stage {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      direction: ltr;
      padding: 34px 54px;
      position: relative;
      isolation: isolate;
      background: url('/ems/assets/images/loginImage.jpeg') center center / cover no-repeat;
    }

    .stage::before {
      content: '';
      position: absolute;
      inset: 0;
      z-index: -1;
      background: linear-gradient(90deg, rgba(8, 10, 14, 0.28) 0%, rgba(8, 10, 14, 0.11) 42%, rgba(8, 10, 14, 0.06) 100%);
    }

    .login-card {
      width: 100%;
      max-width: 380px;
      direction: rtl;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.85);
      box-shadow: 0 16px 42px rgba(0, 0, 0, 0.34);
      padding: 16px 10px 10px;
      animation: cardIn .45s ease-out;
    }

    .logo-wrap {
      text-align: center;
      margin-top: 4px;
      margin-bottom: 8px;
    }

    .logo-wrap img {
      width: 128px;
      max-width: 78%;
      height: auto;
      display: inline-block;
    }

    .title {
      margin-top: 6px;
      text-align: center;
      color: #262626;
      font-size: 25px;
      font-weight: 800;
      line-height: 1.1;
    }

    .subtitle {
      margin-top: 5px;
      margin-bottom: 15px;
      text-align: center;
      color: var(--txt-2);
      font-size: 16px;
      font-weight: 600;
    }

    .divider {
      height: 1px;
      margin: 5px -10px 12px;
      background: var(--line);
    }

    .err-box {
      margin-bottom: 9px;
      border: 1px solid #f1c2c4;
      background: #fff2f3;
      color: var(--error);
      border-radius: 9px;
      padding: 8px 10px;
      font-size: 14px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .err-box i { flex-shrink: 0; }

    .field {
      margin-bottom: 11px;
    }

    .field label {
      display: block;
      text-align: right;
      color: #777;
      font-size: 10px;
      font-weight: 700;
      margin-bottom: 5px;
      padding-right: 2px;
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap input {
      width: 100%;
      height: 42px;
      border-radius: 9px;
      border: 2px solid #f2b46c;
      background: var(--input-bg);
      color: #222;
      font-family: 'Tajawal', sans-serif;
      font-size: 17px;
      font-weight: 600;
      padding: 0 44px 0 44px;
      outline: none;
      transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .input-wrap input:focus {
      border-color: #f2bd80;
      box-shadow: 0 0 0 3px rgba(247, 147, 26, 0.12);
      background: #fff;
    }

    .input-wrap input::placeholder {
      color: #c7c7c7;
      font-size: 19px;
      font-weight: 500;
    }

    .icon-r,
    .icon-l,
    .eye-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 28px;
      height: 28px;
      border-radius: 7px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }

    .icon-r {
      right: 6px;
      background: var(--gold);
      color: #fff;
    }

    .icon-l {
      left: 6px;
      background: #eef2f7;
      border: 1px solid #e5eaf1;
      color: #a5adb9;
    }

    .eye-btn {
      left: 6px;
      border: 1px solid #e5eaf1;
      background: #eef2f7;
      color: #a5adb9;
      cursor: pointer;
    }

    .password-line {
      height: 3px;
      border-radius: 8px;
      margin: 1px 0 8px;
      background: linear-gradient(90deg, transparent 0%, var(--gold) 58%, var(--gold) 100%);
    }

    .btn-submit {
      width: 100%;
      height: 43px;
      border: 0;
      border-radius: 10px;
      background: linear-gradient(180deg, #f89c1c 0%, #f48900 100%);
      color: #fff;
      font-family: 'Tajawal', sans-serif;
      font-size: 17px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 6px 15px rgba(244, 137, 0, 0.35);
      transition: transform .16s ease, box-shadow .16s ease;
    }

    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 18px rgba(244, 137, 0, 0.4);
    }

    .btn-submit:disabled {
      opacity: .76;
      cursor: not-allowed;
      transform: none;
    }

    .limit-box {
      margin-top: 10px;
      height: 28px;
      border-radius: 9px;
      border: 1px solid #f0dcc0;
      background: #fff8ee;
      color: #bb8a47;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      /* justify-content: center; */
      gap: 8px;
      padding: 0 8px;
      text-align: right;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .portals {
      margin-top: 8px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px;
    }

    .p-link {
      height: 33px;
      border-radius: 8px;
      border: 1px solid #e6e6e6;
      background: #f7f7f7;
      color: #707070;
      font-size: 16px;
      font-weight: 700;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all .16s ease;
    }

    .p-link i {
      color: var(--gold);
      font-size: 10px;
    }

    .p-link:hover {
      border-color: #f2bf81;
      background: #fff;
      color: #4d4d4d;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 920px) {
      html, body {
        overflow: auto;
      }

      .stage {
        justify-content: center;
        padding: 18px;
      }

      .stage::before {
        background: linear-gradient(180deg, rgba(8, 10, 14, 0.34) 0%, rgba(8, 10, 14, 0.23) 100%);
      }
    }

    @media (max-width: 420px) {
      .login-card { max-width: 100%; }
      .title { font-size: 30px; }
      .subtitle { font-size: 14px; }
      .input-wrap input { font-size: 21px; }
      .btn-submit { font-size: 26px; }
      .p-link { font-size: 15px; }
    }
  </style>
</head>
<body>
<div class="stage">
  <div class="login-card">
    <div class="logo-wrap">
      <img src="/ems/assets/images/logo.png" alt="Equipation logo">
      <div class="title">مرحبا بعودتك</div>
      <div class="subtitle">ادخل بياناتك للوصول الي لوحة التحكم</div>
    </div>

    <div class="divider"></div>

    <?php if ($errH !== ''): ?>
    <div class="err-box">
      <i class="fas fa-exclamation-circle"></i>
      <span><?php echo $errH; ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <div class="field">
        <label for="username">اسم المستخدم</label>
        <div class="input-wrap">
          <input type="text" id="username" name="username" placeholder="اسم المستخدم" value="<?php echo $postU; ?>" required autocomplete="username">
          <span class="icon-r"><i class="fas fa-user"></i></span>
        </div>
      </div>

      <div class="field">
        <label for="password">كلمة المرور</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="........" required autocomplete="current-password">
          <button type="button" class="eye-btn" id="togglePassword" aria-label="إظهار أو إخفاء كلمة المرور">
            <i class="fas fa-eye"></i>
          </button>
          <span class="icon-r"><i class="fas fa-lock"></i></span>
        </div>
      </div>

      <div class="password-line"></div>

      <input type="hidden" name="csrf_token" value="<?php echo $csrfH; ?>">

      <button type="submit" class="btn-submit" id="submitBtn">
        <i class="fas fa-arrow-left"></i>
        <span>دخول النظام</span>
      </button>

      <div class="limit-box">
        <i class="fas fa-shield-alt"></i>
        <span>حد المحاولات: 5 محاولات، مدة القفل: 15 دقيقة</span>
      </div>

      <div class="portals">
        <a href="/ems/company/register.php" class="p-link">
          <i class="fas fa-circle"></i>
          <span>تسجيل شركة</span>
        </a>
        <a href="/ems/company/login.php" class="p-link">
          <i class="fas fa-building"></i>
          <span>بوابة الشركات</span>
        </a>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const toggle = document.getElementById('togglePassword');
  const password = document.getElementById('password');
  const form = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');

  if (toggle && password) {
    toggle.addEventListener('click', function () {
      const icon = this.querySelector('i');
      if (password.type === 'password') {
        password.type = 'text';
        if (icon) {
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        }
      } else {
        password.type = 'password';
        if (icon) {
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      }
    });
  }

  if (form && submitBtn) {
    form.addEventListener('submit', function () {
      submitBtn.disabled = true;
    });
  }
})();
</script>
</body>
</html>
