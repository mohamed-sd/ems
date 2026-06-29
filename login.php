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
      $login_has_employee_link = db_table_has_column($conn, 'users', 'employee_id');
      $login_emp_col = $login_has_employee_link ? ",employee_id" : "";
      $stmt = mysqli_prepare($conn, "SELECT id,name,username,password,phone,role,project_id,contract_id,company_id,parent_id,created_at,updated_at$login_emp_col FROM users WHERE username=? LIMIT 1");
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
            } else {
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
                }
              }

              // قاعدة: لا حساب يعمل بلا موظف مُسنَد له (عدا المدير الأعلى -1).
              if ($error === '' && !$sup && $login_has_employee_link) {
                $emp_link = isset($user['employee_id']) ? intval($user['employee_id']) : 0;
                if ($emp_link <= 0) {
                  $_SESSION['login_attempts']++;
                  $_SESSION['last_attempt_time'] = time();
                  $error = "هذا الحساب غير مرتبط بموظف. تواصل مع المسؤول لإسناد موظف للحساب.";
                }
              }

              if ($error === '') {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = null;
                session_regenerate_id(true);
                $_SESSION['user'] = [
                  'id' => $user['id'],
                  'name' => $user['name'],
                  'username' => $user['username'],
                  'phone' => $user['phone'],
                  'role' => $user['role'],
                  'employee_id' => isset($user['employee_id']) ? intval($user['employee_id']) : null,
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
          }
        }
        mysqli_stmt_close($stmt);

        if ($error === '') {
          $_SESSION['login_attempts']++;
          $_SESSION['last_attempt_time'] = time();
          $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: " . max(0, $max_attempts - $_SESSION['login_attempts']);
        }
      } else {
        $error = "حدث خطأ أثناء التحقق.";
      }
    }
  }
}

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
      --font-ui: var(--font-ar, 'IBM Plex Sans Arabic', 'Tajawal', 'Cairo', sans-serif);
      --gold: #f3be00;
      --gold-dark: #9f8500;
      --line: #c8c8c8;
      --txt-1: #121212;
      --txt-2: #4d4d4d;
      --input-bg: #ececec;
      --error: #c81f24;
    }

    html, body {
      height: 100%;
      font-family: var(--font-ui);
      overflow: hidden;
      background: #e2e2e2;
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
      background: linear-gradient(90deg, rgba(18, 18, 18, 0.26) 0%, rgba(18, 18, 18, 0.13) 45%, rgba(18, 18, 18, 0.08) 100%);
    }

    .logo{
      width: 160px;
      max-width: 350px;
      direction: rtl;
      padding: 16px 10px 10px;
      animation: cardIn .45s ease-out;
      position: fixed;
      top: 10px;
      left : 10px;
    }

    .login-card {
      width: 100%;
      max-width: 380px;
      direction: rtl;
      border-radius: 14px;
      background: #fff;
      border: 1px solid rgba(200, 200, 200, 0.96);
      box-shadow: 0 16px 42px rgba(18, 18, 18, 0.24);
      padding: 16px 10px 10px;
      animation: cardIn .45s ease-out;
    }

    .logo-wrap {
      text-align: center;
      margin-top: 4px;
      margin-bottom: 8px;
    }

    .logo-wrap img {
      width: 180px;
      max-width: 78%;
      height: auto;
      display: inline-block;
    }

    .title {
      margin-top: 6px;
      text-align: center;
      color: #121212;
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
      color: #333;
      font-size: 10px;
      font-weight: 700;
      margin-bottom: 5px;
      padding-right: 2px;
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap input {
      background: var(--input-bg);
      width: 100%;
      height: 42px;
      border-radius: 10px;
      border: 1px solid #bdbdbd;
      background: var(--input-bg);
      color: #121212;
      font-family: var(--font-ui);
      font-size: 17px;
      font-weight: 600;
      padding: 0 44px 0 44px;
      outline: none;
      transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .input-wrap input::placeholder {
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
      background: #f7f7f7;
      border: 1px solid #a9a9a9;
      color: #252525;
    }

    .icon-l {
      left: 6px;
      background: #f7f7f7;
      border: 1px solid #a9a9a9;
      color: #252525;
    }

    .eye-btn {
      left: 6px;
      border: 1px solid #a9a9a9;
      background: #f7f7f7;
      color: #252525;
      cursor: pointer;
    }

    .btn-submit {
      width: 100%;
      height: 43px;
      border: 0;
      border-radius: 10px;
      background:  #f3be00;
      color: #121212;
      font-family: var(--font-ui);
      font-size: 17px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 6px 15px rgba(159, 133, 0, 0.35);
      transition: transform .16s ease, box-shadow .16s ease;
    }

    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 18px rgba(159, 133, 0, 0.42);
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
      border: 1px solid #c8c8c8;
      background: #efefef;
      color: #333;
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
      border: 1px solid #c8c8c8;
      background: #ececec;
      color: #222;
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
      color: #6e6e6e;
      font-size: 10px;
    }

    .p-link:hover i {
      color: #111;
    }

    .p-link:hover {
      border-color: #9f8500;
      background: #f3be00;
      color: #121212;
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

 <img class="logo" src="/ems/assets/images/logo 3.png" alt="Equipation logo">

  <div class="login-card">
    <div class="logo-wrap">
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
