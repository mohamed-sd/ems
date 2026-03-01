<?php
// تحميل config.php الذي يحمل security.php تلقائياً
require_once "config.php";

// الآن يتم بدء الجلسة الآمنة من security.php تلقائياً
// إعدادات الأمان الإضافية
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");

// إعدادات تسجيل الدخول
$max_attempts = 5;
$lockout_minutes = 15;

// تهيئة محاولات تسجيل الدخول في الجلسة
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = null;
}

// دالة للتحقق من قفل الحساب
function is_login_locked_out() {
    global $max_attempts, $lockout_minutes;
    if (!empty($_SESSION['last_attempt_time']) && $_SESSION['login_attempts'] >= $max_attempts) {
        if ((time()-$_SESSION['last_attempt_time']) < ($lockout_minutes*60)) {
            return true;
        }
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = null;
    }
    return false;
}

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (is_login_locked_out()) {
    $mins = $lockout_minutes;
    $error="تم تجميد الحساب مؤقتاً. حاول بعد {$mins} دقيقة.";
  } elseif (!verify_csrf_token($_POST['csrf_token']??'')) {
    $error="رمز الأمان غير صحيح. أعد تحميل الصفحة.";
  } else {
    $u=trim($_POST['username']??''); $p=trim($_POST['password']??'');
    if ($u===''||$p==='') { $error="الرجاء إدخال بيانات الدخول."; }
    elseif (mb_strlen($u)>50||mb_strlen($p)>128) { $error="المدخلات تتجاوز الحد المسموح."; }
    else {
      $stmt=mysqli_prepare($conn,"SELECT id,name,username,password,phone,role,project_id,parent_id,created_at,updated_at FROM users WHERE username=? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt,"s",$u); mysqli_stmt_execute($stmt);
        $res=mysqli_stmt_get_result($stmt);
        if ($res&&mysqli_num_rows($res)===1) {
          $user=mysqli_fetch_assoc($res);
          if (isset($user['password'])&&$user['password']===$p) {
            $_SESSION['login_attempts']=0; $_SESSION['last_attempt_time']=null;
            session_regenerate_id(true);
            $_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'username'=>$user['username'],
              'phone'=>$user['phone'],'role'=>$user['role'],'project_id'=>$user['project_id'],
              'mine_id'=>$user['mine_id']??null,'contract_id'=>$user['contract_id']??null,
              'parent'=>$user['parent_id'],'created_at'=>$user['created_at'],
              'updated_at'=>$user['updated_at'],'last_login'=>date('Y-m-d H:i:s')];
            header("Location: main/dashboard.php"); exit();
          } else { $_SESSION['login_attempts']++; $_SESSION['last_attempt_time']=time(); $error="بيانات الدخول غير صحيحة. المحاولات المتبقية: ".max(0,$max_attempts-$_SESSION['login_attempts']); }
        } else { $_SESSION['login_attempts']++; $_SESSION['last_attempt_time']=time(); $error="بيانات الدخول غير صحيحة. المحاولات المتبقية: ".max(0,$max_attempts-$_SESSION['login_attempts']); }
        mysqli_stmt_close($stmt);
      } else { $error="خطأ داخلي، حاول لاحقاً."; }
    }
  }
}
$csrf = generate_csrf_token(); // من security.php
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إيكوبيشن | تسجيل الدخول</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
/* ─── TOKENS (identical to dashboard) ─── */
:root{
  --navy:    #0c1c3e;
  --navy-m:  #132050;
  --navy-l:  #1b2f6e;
  --gold:    #e8b800;
  --gold-l:  #ffd740;
  --gold-d:  rgba(232,184,0,.14);
  --bg:      #f0f2f8;
  --card:    #ffffff;
  --bdr:     rgba(12,28,62,.08);
  --txt:     #0c1c3e;
  --sub:     #64748b;
  --danger:  #dc2626;
  --danger-d:rgba(220,38,38,.09);
  --s2: 0 5px 20px rgba(12,28,62,.09);
  --s3: 0 14px 44px rgba(12,28,62,.14);
  --ease:.22s cubic-bezier(.4,0,.2,1);
  --font:'Cairo',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);color:var(--txt)}
a{text-decoration:none;color:inherit}

/* ─── SPLIT LAYOUT ─── */
.page{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

/* ═══ LEFT PANEL ═══ */
.panel{
  position:relative;overflow:hidden;
  background:linear-gradient(130deg,var(--navy) 0%,var(--navy-m) 55%,var(--navy-l) 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:56px 44px;gap:36px;
}
/* dot-grid */
.panel::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.055) 1px,transparent 1px);background-size:22px 22px;pointer-events:none}
/* gold orb top-right */
.panel::after{content:'';position:absolute;top:-80px;right:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,rgba(232,184,0,.28) 0%,transparent 65%);pointer-events:none}
.orb-bl{position:absolute;bottom:-70px;left:-70px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(27,47,110,.6) 0%,transparent 70%);pointer-events:none}

/* brand */
.p-brand{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:12px;animation:fadeUp .5s cubic-bezier(.4,0,.2,1) both}
.p-icon{width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,var(--gold),var(--gold-l));display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--navy);box-shadow:0 8px 28px rgba(232,184,0,.4);animation:bob 4s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0) rotate(-3deg)}50%{transform:translateY(-9px) rotate(3deg)}}
.p-name{font-size:2rem;font-weight:900;color:#fff}
.p-name em{color:var(--gold);font-style:normal}
.p-tag{font-size:.75rem;font-weight:500;color:rgba(255,255,255,.45);letter-spacing:.1em;text-transform:uppercase}

/* features */
.features{position:relative;z-index:1;display:flex;flex-direction:column;gap:11px;width:100%;animation:fadeUp .5s .1s cubic-bezier(.4,0,.2,1) both}
.feat{display:flex;align-items:center;gap:13px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:12px 15px;transition:background var(--ease),border-color var(--ease)}
.feat:hover{background:rgba(255,255,255,.1);border-color:rgba(232,184,0,.22)}
.fi-ico{width:36px;height:36px;flex-shrink:0;border-radius:9px;background:var(--gold-d);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:var(--gold)}
.fi-txt h4{font-size:.85rem;font-weight:700;color:#fff}
.fi-txt p{font-size:.72rem;color:rgba(255,255,255,.42);margin-top:1px}

.p-foot{position:relative;z-index:1;font-size:.68rem;color:var(--gold);letter-spacing:.05em;animation:fadeUp .5s .2s cubic-bezier(.4,0,.2,1) both}

/* ═══ RIGHT SIDE ═══ */
.login-side{background:var(--bg);display:flex;align-items:center;justify-content:center;padding:40px 24px;position:relative}
.login-side::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(12,28,62,.04) 1px,transparent 1px);background-size:24px 24px;pointer-events:none}

/* ─── CARD ─── */
.card{
  position:relative;z-index:1;
  background:var(--card);border-radius:24px;
  border:1.5px solid var(--bdr);
  padding:38px 34px;width:100%;max-width:410px;
  box-shadow:var(--s3);
  animation:fadeUp .45s .05s cubic-bezier(.4,0,.2,1) both;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}

/* card header */
.card-head{text-align:center;margin-bottom:26px}
.ch-logo{
  width:52px;height:52px;border-radius:15px;
  background:linear-gradient(135deg,var(--navy),var(--navy-l));
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.2rem;color:var(--gold);box-shadow:var(--s2);margin-bottom:14px;
}
.card-head h2{font-size:1.4rem;font-weight:900;color:var(--txt);margin-bottom:4px}
.card-head p{font-size:.8rem;color:var(--sub)}

/* gradient divider */
.div-line{height:1px;background:linear-gradient(90deg,transparent,var(--bdr),var(--gold-d),var(--bdr),transparent);margin:0 -6px 24px}

/* error */
.err{display:flex;align-items:flex-start;gap:10px;background:var(--danger-d);border:1px solid rgba(220,38,38,.18);border-radius:12px;padding:12px 14px;margin-bottom:18px;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
.err i{color:var(--danger);font-size:.88rem;flex-shrink:0;margin-top:1px}
.err span{font-size:.82rem;font-weight:600;color:var(--danger);line-height:1.5}

/* fields */
.field{margin-bottom:15px}
.field label{display:block;font-size:.7rem;font-weight:700;color:var(--sub);letter-spacing:.07em;text-transform:uppercase;margin-bottom:5px}
.fw{position:relative}
.fw .ico{position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:.88rem;color:var(--sub);pointer-events:none;transition:color var(--ease)}
.fw input{
  width:100%;padding:11px 38px 11px 40px;
  border-radius:11px;border:1.5px solid var(--bdr);
  background:var(--bg);font-family:var(--font);
  font-size:.9rem;font-weight:500;color:var(--txt);
  outline:none;transition:border-color var(--ease),box-shadow var(--ease),background var(--ease);
}
.fw input::placeholder{color:#b0b8cc;font-weight:400}
.fw input:focus{background:#fff;border-color:var(--gold);box-shadow:0 0 0 3px rgba(232,184,0,.15)}
.fw input:focus~.ico{color:var(--gold)}
.pw-toggle{position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--sub);font-size:.85rem;padding:4px;transition:color var(--ease)}
.pw-toggle:hover{color:var(--navy)}

/* submit button */
.btn-submit{
  width:100%;margin-top:6px;padding:13px;border-radius:12px;border:none;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy-l) 100%);
  color:#fff;font-family:var(--font);font-size:.95rem;font-weight:800;
  cursor:pointer;position:relative;overflow:hidden;
  transition:transform var(--ease),box-shadow var(--ease);
  box-shadow:0 5px 18px rgba(12,28,62,.24);
  display:flex;align-items:center;justify-content:center;gap:9px;
}
/* gold hover overlay */
.btn-submit::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,var(--gold),var(--gold-l));opacity:0;transition:opacity var(--ease)}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(12,28,62,.28)}
.btn-submit:hover::after{opacity:1}
.btn-submit:active{transform:none}
.btn-submit .bt,.btn-submit .bi{position:relative;z-index:1;transition:color var(--ease)}
.btn-submit:hover .bt,.btn-submit:hover .bi{color:var(--navy)}
/* loading */
.btn-submit.ld .bt,.btn-submit.ld .bi{opacity:0}
.btn-submit.ld::before{content:'';position:absolute;width:18px;height:18px;border:2.5px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* security note */
.sec-note{margin-top:18px;display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--bdr);border-radius:10px;padding:10px 13px}
.sec-note i{color:var(--gold);font-size:.8rem;flex-shrink:0}
.sec-note p{font-size:.71rem;color:var(--sub);line-height:1.5}
.sec-note strong{color:var(--navy)}

/* ─── RESPONSIVE ─── */
@media(max-width:860px){
  .page{grid-template-columns:1fr}
  .panel{display:none}
  .login-side{min-height:100vh}
}
@media(max-width:460px){
  .card{padding:26px 18px;border-radius:20px}
  .card-head h2{font-size:1.2rem}
}
  </style>
</head>
<body>
<div class="page">

  <!-- ████ LEFT PANEL ████ -->
  <div class="panel">
    <div class="orb-bl"></div>

    <div class="p-brand">
      <div class="p-icon"><i class="fas fa-layer-group"></i></div>
      <div class="p-name">إيكو<em>بيشن</em></div>
      <div class="p-tag">Equipment Planning System</div>
    </div>

    <div class="features">
      <div class="feat">
        <div class="fi-ico"><i class="fas fa-project-diagram"></i></div>
        <div class="fi-txt"><h4>إدارة المشاريع</h4><p>تتبع شامل للمشاريع والمناجم</p></div>
      </div>
      <div class="feat">
        <div class="fi-ico"><i class="fas fa-tools"></i></div>
        <div class="fi-txt"><h4>إدارة الأسطول</h4><p>مراقبة المعدات والمشغلين في الوقت الفعلي</p></div>
      </div>
      <div class="feat">
        <div class="fi-ico"><i class="fas fa-clock"></i></div>
        <div class="fi-txt"><h4>ساعات العمل</h4><p>تسجيل ومراجعة ساعات المشغلين</p></div>
      </div>
      <div class="feat">
        <div class="fi-ico"><i class="fas fa-chart-line"></i></div>
        <div class="fi-txt"><h4>التقارير</h4><p>إحصائيات دقيقة لدعم القرار</p></div>
      </div>
    </div>

    <div class="p-foot">© <?= date('Y') ?> إيكوبيشن — جميع الحقوق محفوظة</div>
  </div>

  <!-- ████ RIGHT LOGIN ████ -->
  <div class="login-side">
    <div class="card">

      <div class="card-head">
        <div class="ch-logo"><i class="fas fa-layer-group"></i></div>
        <h2>تسجيل الدخول</h2>
        <p>أدخل بياناتك للوصول إلى النظام</p>
      </div>

      <div class="div-line"></div>

      <?php if (!empty($error)): ?>
      <div class="err">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="" autocomplete="off" id="lf">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <div class="field">
          <label for="un">اسم المستخدم</label>
          <div class="fw">
            <input id="un" name="username" type="text" maxlength="50"
              placeholder="أدخل اسم المستخدم" required autocomplete="username"
              value="<?= isset($_POST['username']) ? e($_POST['username']) : '' ?>">
            <i class="fas fa-user ico"></i>
          </div>
        </div>

        <div class="field">
          <label for="pw">كلمة المرور</label>
          <div class="fw">
            <input id="pw" name="password" type="password" maxlength="128"
              placeholder="أدخل كلمة المرور" required autocomplete="current-password">
            <i class="fas fa-lock ico"></i>
            <button type="button" class="pw-toggle" id="pwt">
              <i class="fas fa-eye" id="eyi"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="sb">
          <span class="bt">دخول إلى النظام</span>
          <i class="fas fa-arrow-left bi"></i>
        </button>
      </form>

      <div class="sec-note">
        <i class="fas fa-shield-alt"></i>
        <p>
          الحد الأقصى: <strong><?= e($max_attempts) ?> محاولات</strong> —
          التجميد: <strong><?= e($lockout_minutes) ?> دقيقة</strong>
        </p>
      </div>

    </div>
  </div>

</div>

<script>
  /* toggle password */
  const pwEl=document.getElementById('pw'),eyeEl=document.getElementById('eyi');
  document.getElementById('pwt').addEventListener('click',()=>{
    const v=pwEl.type==='password';
    pwEl.type=v?'text':'password';
    eyeEl.className=v?'fas fa-eye-slash':'fas fa-eye';
  });
  /* loading on submit */
  document.getElementById('lf').addEventListener('submit',()=>{
    const b=document.getElementById('sb');b.classList.add('ld');b.disabled=true;
  });
  /* focus */
  document.getElementById('un').focus();
</script>
</body>
</html>