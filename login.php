<?php
require_once "config.php";
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
$max_attempts = 5; $lockout_minutes = 15;
if (!isset($_SESSION['login_attempts'])) { $_SESSION['login_attempts'] = 0; $_SESSION['last_attempt_time'] = null; }
function is_locked() { global $max_attempts,$lockout_minutes; if (!empty($_SESSION['last_attempt_time'])&&$_SESSION['login_attempts']>=$max_attempts) { if ((time()-$_SESSION['last_attempt_time'])<($lockout_minutes*60)) return true; $_SESSION['login_attempts']=0; $_SESSION['last_attempt_time']=null; } return false; }
$error=''; if (isset($_GET['msg'])&&trim($_GET['msg'])!=='') $error=trim($_GET['msg']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (is_locked()) { $error="تم تجاوز عدد محاولات الدخول. حاول بعد {$lockout_minutes} دقيقة."; }
  elseif (!verify_csrf_token(isset($_POST['csrf_token'])?$_POST['csrf_token']:'')) { $error="رمز الحماية غير صالح."; }
  else {
    $u=trim(isset($_POST['username'])?$_POST['username']:''); $p=trim(isset($_POST['password'])?$_POST['password']:'');
    if ($u===''||$p==='') { $error="يرجى إدخال اسم المستخدم وكلمة المرور."; }
    elseif (mb_strlen($u)>50||mb_strlen($p)>128) { $error="بيانات الاعتماد أطول من المسموح."; }
    else {
      $stmt=mysqli_prepare($conn,"SELECT id,name,username,password,phone,role,project_id,mine_id,contract_id,company_id,parent_id,created_at,updated_at FROM users WHERE username=? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt,"s",$u); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
        if ($res&&mysqli_num_rows($res)===1) {
          $user=mysqli_fetch_assoc($res);
          if (isset($user['password'])&&password_verify($p,$user['password'])) {
            $sup=isset($user['role'])&&strval($user['role'])==='-1'; $cid=(isset($user['company_id'])&&intval($user['company_id'])>0)?intval($user['company_id']):0;
            if (!$sup&&$cid<=0) { $_SESSION['login_attempts']++;$_SESSION['last_attempt_time']=time();$error="المستخدم غير مرتبط بشركة.";mysqli_stmt_close($stmt);goto le; }
            if (!$sup&&$cid>0&&db_table_has_column($conn,'admin_companies','status')) {
              $cs='active';$ss=@mysqli_prepare($conn,'SELECT status FROM admin_companies WHERE id=? LIMIT 1');
              if ($ss){mysqli_stmt_bind_param($ss,'i',$cid);mysqli_stmt_execute($ss);$sr=mysqli_stmt_get_result($ss);$sw=$sr?mysqli_fetch_assoc($sr):null;mysqli_stmt_close($ss);if($sw&&isset($sw['status'])&&trim($sw['status'])!=='')$cs=strtolower(trim($sw['status']));}
              if ($cs!=='active'){$_SESSION['login_attempts']++;$_SESSION['last_attempt_time']=time();$error="حالة الشركة غير نشطة.";mysqli_stmt_close($stmt);goto le;}
            }
            $_SESSION['login_attempts']=0;$_SESSION['last_attempt_time']=null;session_regenerate_id(true);
            $_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'username'=>$user['username'],'phone'=>$user['phone'],'role'=>$user['role'],'project_id'=>$user['project_id'],'mine_id'=>$user['mine_id']??null,'contract_id'=>$user['contract_id']??null,'company_id'=>$cid>0?$cid:null,'parent'=>$user['parent_id'],'created_at'=>$user['created_at'],'updated_at'=>$user['updated_at'],'last_login'=>date('Y-m-d H:i:s')];
            header("Location: main/dashboard.php"); exit();
          } else { $_SESSION['login_attempts']++;$_SESSION['last_attempt_time']=time();$error="اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: ".max(0,$max_attempts-$_SESSION['login_attempts']); }
        } else { $_SESSION['login_attempts']++;$_SESSION['last_attempt_time']=time();$error="اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: ".max(0,$max_attempts-$_SESSION['login_attempts']); }
        mysqli_stmt_close($stmt);
      } else { $error="حدث خطأ أثناء التحقق."; }
    }
  }
} le:
$csrf=generate_csrf_token();
$errH=!empty($error)?htmlspecialchars($error,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):'';
$postU=isset($_POST['username'])?htmlspecialchars($_POST['username'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):'';
$csrfH=htmlspecialchars($csrf,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إيكوبيشن · تسجيل الدخول</title>
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<style>
/* @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&family=Barlow+Condensed:wght@700;800;900&display=swap'); */

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --or:#F7931A; --or2:#D4700A; --or3:#FFB347; --orx:#FFF0D8;
  --ink:#0F1115; --ink2:#1A1F28;
  --white:#FFFFFF; --cream:#FAFAF8;
  --tx1:#0F1115; --tx2:#444038; --tx3:#857870; --tx4:#B8AFA8;
  --ok:#16A34A; --err:#DC2626; --err-lt:#FEF2F2; --err-bd:#FECACA;
  --hex-clip:polygon(8% 0,92% 0,100% 50%,92% 100%,8% 100%,0 50%);
  --ease:cubic-bezier(.16,1,.3,1);
}

html,body{height:100%;font-family:'Tajawal',sans-serif;-webkit-font-smoothing:antialiased;overflow:hidden}
a{text-decoration:none;color:inherit}

/* ══════════════════════════════════════════
   FULL-PAGE HEX CANVAS — transparent hex grid above CSS backgrounds
══════════════════════════════════════════ */
#hexCanvas{
  position:fixed;inset:0;z-index:1;
  width:100%;height:100%;
  display:block;
  pointer-events:none;
}

/* ══════════════════════════════════════════
   PAGE SPLIT — on top of canvas
══════════════════════════════════════════ */
.page{
  position:relative;z-index:2;
  display:grid;grid-template-columns:1fr 1fr;
  height:100vh;overflow:hidden;
}

/* Left side — transparent so canvas hex grid shows */
.panel{
  position:relative;overflow:hidden;
  background:transparent;
  border-left:1px solid rgba(247,147,26,.18);
  display:flex;flex-direction:column;
}

/* Right side — transparent, canvas draws the background */
.form-side{
  position:relative;overflow:hidden;
  background:transparent;
  border-right:1px solid rgba(247,147,26,.1);
  display:flex;align-items:center;justify-content:center;
  padding:32px 28px;
}
.form-side::before{ display:none; }

/* Gradient overlay: deeper at top/bottom, lighter in center */
.panel::before{ display:none; }

/* Orange glow — top right */
.panel-glow{
  position:absolute;top:-60px;left:-60px;
  width:360px;height:360px;border-radius:50%;
  background:radial-gradient(circle,rgba(247,147,26,.22) 0%,transparent 65%);
  pointer-events:none;z-index:0;
  animation:glowPulse 5s ease-in-out infinite;
}
.panel-glow2{
  position:absolute;bottom:-40px;right:-40px;
  width:240px;height:240px;border-radius:50%;
  background:radial-gradient(circle,rgba(247,147,26,.14) 0%,transparent 65%);
  pointer-events:none;z-index:0;
  animation:glowPulse 7s ease-in-out infinite reverse;
}
@keyframes glowPulse{0%,100%{opacity:.7;transform:scale(1)}50%{opacity:1;transform:scale(1.1)}}

/* Orange stripe top */
.panel-stripe{
  position:absolute;top:0;left:0;right:0;height:4px;
  background:linear-gradient(90deg,var(--or2),var(--or),var(--or3));
  z-index:10;
}

/* Sudan flag bottom */
.sudan{position:absolute;bottom:0;left:0;right:0;height:4px;display:flex;z-index:10}
.sudan span:nth-child(1){flex:1;background:#DA121A}
.sudan span:nth-child(2){flex:1;background:rgba(255,255,255,.85)}
.sudan span:nth-child(3){flex:1;background:#111}
.sudan span:nth-child(4){flex:.6;background:#007229}

/* Panel content */
.panel-body{
  position:relative;z-index:2;
  flex:1;display:flex;flex-direction:column;
  justify-content:space-between;
  padding:46px 50px 40px;
}

/* Brand */
.brand{display:flex;align-items:center;gap:13px;animation:slideR .65s var(--ease) both}
.brand-hex{
  width:46px;height:46px;clip-path:var(--hex-clip);
  background:linear-gradient(135deg,var(--or),var(--or2));
  display:flex;align-items:center;justify-content:center;
  color:var(--ink);font-size:.92rem;flex-shrink:0;
  box-shadow:0 0 26px rgba(247,147,26,.55);
}
.brand-name{font-size:.96rem;font-weight:900;color:#fff;letter-spacing:.04em}
.brand-sub{font-size:.48rem;font-weight:700;color:rgba(255,255,255,.32);letter-spacing:.18em;text-transform:uppercase;margin-top:2px}

/* Headline */
.panel-mid{animation:slideR .65s .08s var(--ease) both}
.headline{
  font-family:'Barlow Condensed','Tajawal',sans-serif;
  font-size:clamp(2.6rem,3.8vw,4rem);font-weight:900;
  line-height:1.05;letter-spacing:-.02em;color:#fff;
  margin-bottom:16px;
  text-shadow:0 2px 24px rgba(0,0,0,.4);
}
.hl-or{
  color:transparent;
  background:linear-gradient(135deg,var(--or3),var(--or));
  -webkit-background-clip:text;background-clip:text;
  display:inline-block;
}
.panel-desc{font-size:.88rem;line-height:1.92;color:rgba(255,255,255,.48);max-width:370px}

/* Stats */
.stats-row{
  display:flex;gap:1px;
  background:rgba(247,147,26,.12);border:1px solid rgba(247,147,26,.2);
  border-radius:12px;overflow:hidden;
  animation:slideR .65s .16s var(--ease) both;
}
.stat-cell{
  flex:1;padding:14px 16px;
  background:rgba(10,12,16,.45);
  transition:background .2s;
  position:relative;
}
.stat-cell:hover{background:rgba(247,147,26,.12)}
.stat-n{
  font-family:'Barlow Condensed','Tajawal',sans-serif;
  font-size:2rem;font-weight:900;color:var(--or);
  display:block;line-height:1;
}
.stat-n.g{color:#4ADE80}
.stat-n.w{color:rgba(255,255,255,.9)}
.stat-l{font-size:.6rem;color:rgba(255,255,255,.36);font-weight:600;margin-top:3px;display:block}

/* Features */
.feats{display:flex;flex-direction:column;gap:7px;animation:slideR .65s .24s var(--ease) both}
.feat{
  display:flex;align-items:center;gap:12px;padding:11px 14px;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;
  transition:all .2s;cursor:default;
}
.feat:hover{background:rgba(247,147,26,.12);border-color:rgba(247,147,26,.3);transform:translateX(-4px)}
.feat-ico{
  width:32px;height:32px;clip-path:var(--hex-clip);
  background:rgba(247,147,26,.18);color:var(--or3);
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;flex-shrink:0;transition:all .2s;
}
.feat:hover .feat-ico{background:var(--or);color:var(--ink)}
.feat h5{font-size:.8rem;font-weight:800;color:rgba(255,255,255,.9);margin-bottom:1px}
.feat p{font-size:.64rem;color:rgba(255,255,255,.32)}
.panel-foot{font-size:.58rem;color:rgba(255,255,255,.18);letter-spacing:.06em;animation:slideR .65s .32s var(--ease) both}

/* ════════════════════════════
   RIGHT — FORM SIDE
════════════════════════════ */
.form-side{
  position:relative;overflow:hidden;
  background:transparent;
  border-right:1px solid rgba(247,147,26,.1);
  display:flex;align-items:center;justify-content:center;
  padding:32px 28px;
}

/* Light overlay on the right side so hex is visible but subtle */
.form-side::before{
  content:'';position:absolute;inset:0;
  background:rgba(248,246,242,.72);
  pointer-events:none;z-index:0;
}

/* Orange accent — top */
.form-side::after{
  content:'';position:absolute;top:0;left:0;right:0;height:4px;
  background:linear-gradient(90deg,var(--or2),var(--or),var(--or3));
  z-index:10;
}

/* ── CARD ── */
.card{
  position:relative;z-index:2;
  background:rgba(255,255,255,.95);
  border-radius:20px;
  border:1.5px solid rgba(247,147,26,.18);
  padding:40px 38px 34px;
  width:100%;max-width:422px;
  box-shadow:
    0 0 0 1px rgba(247,147,26,.06),
    0 8px 32px rgba(15,17,21,.12),
    0 24px 72px rgba(15,17,21,.1),
    0 0 60px rgba(247,147,26,.07);
  animation:cardIn .6s var(--ease) both;
}
@keyframes cardIn{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}

/* Orange glow line top */
.card::before{
  content:'';
  position:absolute;top:0;left:20%;right:20%;height:2px;
  background:linear-gradient(90deg,transparent,var(--or),var(--or2),transparent);
  border-radius:0 0 3px 3px;
  box-shadow:0 0 16px rgba(247,147,26,.5);
}

/* ── Card header ── */
.card-hd{
  text-align:center;margin-bottom:28px;
  animation:fadeUp .5s .1s var(--ease) both;
}
.logo-wrap{
  display:inline-flex;flex-direction:column;align-items:center;gap:10px;
  margin-bottom:16px;
}
.logo-hex{
  width:58px;height:58px;clip-path:var(--hex-clip);
  background:linear-gradient(135deg,var(--or),var(--or2));
  display:flex;align-items:center;justify-content:center;
  font-size:1.25rem;color:#fff;
  box-shadow:0 0 32px rgba(247,147,26,.5),0 0 8px rgba(247,147,26,.2);
  position:relative;
}
/* Pulsing outer ring */
.logo-hex::before{
  content:'';position:absolute;inset:-5px;
  clip-path:var(--hex-clip);
  background:rgba(247,147,26,.18);
  animation:ring 2.8s ease-in-out infinite;
}
@keyframes ring{0%,100%{opacity:0;transform:scale(1)}50%{opacity:1;transform:scale(1.06)}}

.logo-name{font-size:.96rem;font-weight:900;color:var(--tx1);letter-spacing:.04em}
.logo-sub{font-size:.48rem;font-weight:700;color:var(--tx4);letter-spacing:.18em;text-transform:uppercase;margin-top:2px}

.card-hd h2{font-size:1.38rem;font-weight:900;color:var(--tx1);margin-bottom:5px;letter-spacing:-.02em}
.card-hd p{font-size:.76rem;color:var(--tx3)}

.c-div{
  height:1.5px;
  background:linear-gradient(90deg,transparent,rgba(247,147,26,.15),rgba(15,17,21,.06),transparent);
  margin:0 -4px 24px;
}

/* ── Error ── */
.err-box{
  display:flex;align-items:flex-start;gap:10px;
  background:var(--err-lt);border:1.5px solid var(--err-bd);
  border-radius:10px;padding:11px 13px;margin-bottom:18px;
  animation:shake .35s ease;
}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
.err-box i{color:var(--err);flex-shrink:0;margin-top:2px;font-size:.85rem}
.err-box span{font-size:.8rem;font-weight:700;color:var(--err);line-height:1.55}

/* ── Fields ── */
.fields{animation:fadeUp .5s .15s var(--ease) both}
.field{margin-bottom:15px}
.field label{
  display:block;font-size:.65rem;font-weight:800;color:var(--tx2);
  letter-spacing:.08em;text-transform:uppercase;margin-bottom:7px;
}
.input-wrap{position:relative}
.inp-ico{
  position:absolute;right:13px;top:50%;transform:translateY(-50%);
  width:32px;height:32px;clip-path:var(--hex-clip);
  background:rgba(247,147,26,.1);color:var(--or2);
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;pointer-events:none;
  transition:background .18s,color .18s;
}
.input-wrap input{
  width:100%;
  padding:13px 52px 13px 46px;
  background:rgba(250,250,248,.8);
  border:1.5px solid rgba(15,17,21,.1);border-radius:10px;
  font-family:'Tajawal',sans-serif;font-size:.92rem;font-weight:500;color:var(--tx1);
  outline:none;
  transition:border-color .18s,box-shadow .18s,background .18s;
}
.input-wrap input::placeholder{color:var(--tx4);font-weight:400}
.input-wrap input:focus{
  background:#fff;border-color:var(--or);
  box-shadow:0 0 0 3.5px rgba(247,147,26,.14),0 2px 8px rgba(247,147,26,.1);
}
.input-wrap input:focus ~ .inp-ico{background:var(--or);color:#fff}

.pw-btn{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:var(--tx4);font-size:.82rem;padding:5px;
  transition:color .18s;border-radius:4px;
}
.pw-btn:hover{color:var(--or)}

/* Strength bar */
.pw-bar{height:3px;border-radius:2px;background:rgba(15,17,21,.08);margin-top:6px;overflow:hidden;display:none}
.pw-fill{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}

/* ── Submit ── */
.btn-submit{
  width:100%;margin-top:6px;padding:14px;
  border-radius:10px;border:none;
  background:linear-gradient(135deg,var(--or) 0%,var(--or2) 100%);
  color:#fff;font-family:'Tajawal',sans-serif;
  font-size:.95rem;font-weight:900;letter-spacing:.04em;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
  position:relative;overflow:hidden;
  box-shadow:0 4px 18px rgba(247,147,26,.4),0 8px 32px rgba(247,147,26,.2);
  transition:transform .18s,box-shadow .18s;
}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(247,147,26,.55),0 12px 40px rgba(247,147,26,.25)}
.btn-submit:active{transform:scale(.98)}
.btn-submit .bt,.btn-submit .bi{position:relative;z-index:1}
.btn-submit .bi{transition:transform .2s}
.btn-submit:hover .bi{transform:translateX(-4px)}
.btn-submit::after{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent);animation:shimmer 2.4s ease-in-out infinite}
@keyframes shimmer{0%{left:-100%}55%,100%{left:150%}}
.ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.28);transform:scale(0);animation:rippleA .55s linear;pointer-events:none}
@keyframes rippleA{to{transform:scale(4);opacity:0}}
.btn-submit.ld .bt,.btn-submit.ld .bi{opacity:0}
.btn-submit.ld::before{content:'';position:absolute;width:20px;height:20px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Bottom ── */
.card-foot{margin-top:16px;animation:fadeUp .5s .22s var(--ease) both}
.sec-note{
  display:flex;align-items:center;gap:9px;
  background:var(--orx);border:1.5px solid rgba(247,147,26,.22);
  border-radius:9px;padding:9px 13px;margin-bottom:10px;
}
.sec-note i{color:var(--or2);font-size:.76rem;flex-shrink:0}
.sec-note p{font-size:.68rem;color:var(--or2);font-weight:600;line-height:1.5}
.sec-note strong{font-weight:900}
.portals{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.p-link{
  display:flex;align-items:center;justify-content:center;gap:6px;
  padding:9px 10px;border-radius:9px;
  border:1.5px solid rgba(15,17,21,.09);background:rgba(250,250,248,.7);
  font-size:.74rem;font-weight:700;color:var(--tx2);
  transition:all .18s;
}
.p-link i{font-size:.68rem;color:var(--or);transition:transform .18s}
.p-link:hover{border-color:var(--or);background:var(--orx);color:var(--or2);transform:translateY(-2px);box-shadow:0 4px 12px rgba(247,147,26,.2)}
.p-link:hover i{transform:scale(1.15)}

/* ── Animations ── */
@keyframes slideR{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:none}}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ── Responsive ── */
@media(max-width:900px){
  .page{grid-template-columns:1fr;height:auto}
  html,body{overflow:auto}
  .panel{display:none}
  .form-side{min-height:100vh;background:rgba(252,250,248,.96)}
  .form-side::before{background:rgba(255,255,255,.6)}
}
@media(max-width:460px){
  .card{padding:30px 22px 26px;border-radius:16px}
  .card-hd h2{font-size:1.2rem}
  .portals{grid-template-columns:1fr}
}
@media(max-height:680px){
  .card-hd{margin-bottom:18px}
  .field{margin-bottom:11px}
  .feats{display:none}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
<link rel="stylesheet" href="/ems/assets/css/site-identity.css">
</head>
<body class="standalone-brand">

<!-- FULL-PAGE HEX CANVAS — behind everything -->
<canvas id="hexCanvas"></canvas>

<!-- PAGE SPLIT -->
<div class="page">

  <!-- ══ LEFT — DARK PANEL ══ -->
  <div class="panel">
    <div class="panel-stripe"></div>
    <div class="sudan"><span></span><span></span><span></span><span></span></div>

    <div class="panel-body">
      <!-- Brand -->
      <div class="brand">
        <div class="brand-hex"><i class="fas fa-layer-group"></i></div>
        <div>
          <div class="brand-name">إيكوبيشن</div>
          <div class="brand-sub">Equipment Planning System</div>
        </div>
      </div>

      <!-- Headline -->
      <div class="panel-mid">
        <h1 class="headline">
          نظام إدارة<br>
          المعدات<br>
          <span class="hl-or">الثقيلة</span>
        </h1>
        <p class="panel-desc">منصة SaaS متكاملة تربط المشاريع والمعدات والعقود وساعات التشغيل في بيئة واحدة موثوقة تواكب ضخامة عملياتك الميدانية.</p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-cell">
          <span class="stat-n" data-to="123">0</span>
          <span class="stat-l">معدة مسجّلة</span>
        </div>
        <div class="stat-cell">
          <span class="stat-n g" data-to="48">0</span>
          <span class="stat-l">مشروع نشط</span>
        </div>
        <div class="stat-cell">
          <span class="stat-n w" data-to="92" data-sfx="%">0</span>
          <span class="stat-l">كفاءة التشغيل</span>
        </div>
      </div>

      <!-- Features -->
      <div class="feats">
        <div class="feat">
          <div class="feat-ico"><i class="fas fa-tools"></i></div>
          <div>
            <h5>إدارة المعدات الثقيلة</h5>
            <p>تتبع كامل لكل آلية — الموقع، الحالة، ساعات التشغيل</p>
          </div>
        </div>
        <div class="feat">
          <div class="feat-ico"><i class="fas fa-project-diagram"></i></div>
          <div>
            <h5>المشاريع والعقود</h5>
            <p>ربط المشاريع بمناجمها وموردّيها وعقودها</p>
          </div>
        </div>
        <div class="feat">
          <div class="feat-ico"><i class="fas fa-chart-bar"></i></div>
          <div>
            <h5>تقارير وتحليلات فورية</h5>
            <p>قرارات مبنية على بيانات دقيقة في الوقت الفعلي</p>
          </div>
        </div>
      </div>

      <div class="panel-foot">© <?= date('Y') ?> إيكوبيشن · جميع الحقوق محفوظة</div>
    </div>
  </div>

  <!-- ══ RIGHT — FORM ══ -->
  <div class="form-side">
    <div class="card">
      <div class="card-hd">
        <div class="logo-wrap">
          <div class="logo-hex"><i class="fas fa-layer-group"></i></div>
          <div>
            <div class="logo-name">إيكوبيشن</div>
            <div class="logo-sub">Equipment Planning System</div>
          </div>
        </div>
        <h2>مرحباً بعودتك</h2>
        <p>أدخل بياناتك للوصول إلى لوحة التحكم</p>
      </div>

      <div class="c-div"></div>

      <?php if ($errH): ?>
      <div class="err-box">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $errH ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="" autocomplete="off" id="lf" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfH ?>">
        <div class="fields">
          <div class="field">
            <label for="un">اسم المستخدم</label>
            <div class="input-wrap">
              <input id="un" name="username" type="text" maxlength="50"
                placeholder="أدخل اسم المستخدم" required autocomplete="username"
                value="<?= $postU ?>">
              <div class="inp-ico"><i class="fas fa-user"></i></div>
            </div>
          </div>
          <div class="field">
            <label for="pw">كلمة المرور</label>
            <div class="input-wrap">
              <input id="pw" name="password" type="password" maxlength="128"
                placeholder="أدخل كلمة المرور" required autocomplete="current-password">
              <div class="inp-ico"><i class="fas fa-lock"></i></div>
              <button type="button" class="pw-btn" id="pwt" aria-label="إظهار">
                <i class="fas fa-eye" id="eyi"></i>
              </button>
            </div>
            <div class="pw-bar" id="pwBar"><div class="pw-fill" id="pwFill"></div></div>
          </div>
          <button type="submit" class="btn-submit" id="sb">
            <span class="bt">دخول النظام</span>
            <i class="fas fa-arrow-left bi"></i>
          </button>
        </div>
      </form>

      <div class="card-foot">
        <div class="sec-note">
          <i class="fas fa-shield-alt"></i>
          <p>حد المحاولات: <strong><?= (int)$max_attempts ?> محاولة</strong> · مدة القفل: <strong><?= (int)$lockout_minutes ?> دقيقة</strong></p>
        </div>
        <div class="portals">
          <a class="p-link" href="company/login.php"><i class="fas fa-building"></i>بوابة الشركات</a>
          <a class="p-link" href="company/register.php"><i class="fas fa-plus-circle"></i>تسجيل شركة</a>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

  /* ══════════════════════════════════════════
     ANIMATED HEX CANVAS — full page
  ══════════════════════════════════════════ */
  var canvas = document.getElementById('hexCanvas');
  var ctx    = canvas.getContext('2d');
  var W, H, cols, rows, hexes = [];
  var HEX_SIZE = 38;   /* hex radius */
  var HW = HEX_SIZE * Math.sqrt(3);
  var HH = HEX_SIZE * 2;

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
    buildGrid();
  }

  function hexPath(cx, cy, r) {
    ctx.beginPath();
    for (var i = 0; i < 6; i++) {
      var angle = (Math.PI / 180) * (60 * i - 30);
      var x = cx + r * Math.cos(angle);
      var y = cy + r * Math.sin(angle);
      if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    }
    ctx.closePath();
  }

  function buildGrid() {
    hexes = [];
    var colW  = HW;
    var rowH  = HH * 0.75;
    cols = Math.ceil(W / colW) + 2;
    rows = Math.ceil(H / rowH) + 2;

    for (var row = -1; row < rows; row++) {
      for (var col = -1; col < cols; col++) {
        var cx = col * colW + (row % 2 === 0 ? 0 : colW / 2);
        var cy = row * rowH;
        /* left side = dark style, right side = light style */
        var isRight = cx > W / 2;
        hexes.push({
          cx: cx, cy: cy,
          phase:  Math.random() * Math.PI * 2,
          speed:  0.004 + Math.random() * 0.006,
          bright: Math.random(),   /* 0–1 base brightness */
          isRight: isRight,
          /* random special hexes that pulse orange */
          special: Math.random() < 0.07,
          specialPhase: Math.random() * Math.PI * 2,
          specialSpeed: 0.015 + Math.random() * 0.02,
        });
      }
    }
  }

  var t = 0;
  function draw() {
    ctx.clearRect(0, 0, W, H);

    /* ── Side backgrounds — RTL: dark panel RIGHT, light form LEFT ── */
    ctx.fillStyle = '#F5F3EF';
    ctx.fillRect(0, 0, W / 2, H);
    ctx.fillStyle = '#0C0F14';
    ctx.fillRect(W / 2, 0, W / 2, H);

    /* ── Center divider glow ── */
    var dg = ctx.createLinearGradient(W/2 - 3, 0, W/2 + 3, 0);
    dg.addColorStop(0,   'rgba(247,147,26,0)');
    dg.addColorStop(0.5, 'rgba(247,147,26,.9)');
    dg.addColorStop(1,   'rgba(247,147,26,0)');
    ctx.fillStyle = dg;
    ctx.fillRect(W/2 - 3, 0, 6, H);

    /* ── Hex grid — bold and visible on both sides ── */
    hexes.forEach(function(h) {
      var pulse = 0.5 + Math.sin(t * h.speed + h.phase) * 0.5;
      hexPath(h.cx, h.cy, HEX_SIZE - 1.5);

      /* isRight in canvas = physical right = dark side in RTL layout */
      var isDark = h.cx > W / 2;

      if (isDark) {
        /* Dark side — bright orange strokes on black — VERY VISIBLE */
        if (h.special) {
          var sa = 0.55 + Math.abs(Math.sin(t * h.specialSpeed + h.specialPhase)) * 0.45;
          ctx.strokeStyle = 'rgba(247,147,26,' + sa + ')';
          ctx.lineWidth   = 2.5;
          ctx.stroke();
          ctx.fillStyle   = 'rgba(247,147,26,' + (sa * 0.28) + ')';
          ctx.fill();
        } else {
          var a = 0.18 + pulse * 0.14;
          ctx.strokeStyle = 'rgba(247,147,26,' + a + ')';
          ctx.lineWidth   = 1.4;
          ctx.stroke();
        }
      } else {
        /* Light side — dark orange-brown strokes on cream — VERY VISIBLE */
        if (h.special) {
          var sa = 0.40 + Math.abs(Math.sin(t * h.specialSpeed + h.specialPhase)) * 0.50;
          ctx.strokeStyle = 'rgba(190,85,0,' + sa + ')';
          ctx.lineWidth   = 2.5;
          ctx.stroke();
          ctx.fillStyle   = 'rgba(247,147,26,' + (sa * 0.18) + ')';
          ctx.fill();
        } else {
          var a = 0.14 + pulse * 0.10;
          ctx.strokeStyle = 'rgba(150,80,10,' + a + ')';
          ctx.lineWidth   = 1.4;
          ctx.stroke();
        }
      }
    });

    t++;
    requestAnimationFrame(draw);
  }

  window.addEventListener('resize', resize);
  resize();
  draw();

  /* ══════════════════════════════════════════
     COUNT-UP STATS
  ══════════════════════════════════════════ */
  function countUp(el, target, sfx, dur) {
    var step = target / (dur / 16), v = 0;
    var iv = setInterval(function() {
      v += step;
      if (v >= target) { v = target; clearInterval(iv); }
      el.textContent = Math.floor(v) + (sfx || '');
    }, 16);
  }

  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (!e.isIntersecting) return;
      var el = e.target;
      countUp(el, parseInt(el.dataset.to), el.dataset.sfx || '', 1400);
      io.unobserve(el);
    });
  }, { threshold: 0.3 });
  document.querySelectorAll('.stat-n[data-to]').forEach(function(el) { io.observe(el); });

  /* ══ Password toggle ══ */
  var pw  = document.getElementById('pw');
  var eye = document.getElementById('eyi');
  document.getElementById('pwt').addEventListener('click', function() {
    var s = pw.type === 'password';
    pw.type = s ? 'text' : 'password';
    eye.className = s ? 'fas fa-eye-slash' : 'fas fa-eye';
  });

  /* ══ Password strength ══ */
  var pwBar  = document.getElementById('pwBar');
  var pwFill = document.getElementById('pwFill');
  pw.addEventListener('input', function() {
    var v = pw.value;
    if (!v) { pwBar.style.display = 'none'; return; }
    pwBar.style.display = 'block';
    var s = 0;
    if (v.length >= 6)  s++;
    if (v.length >= 10) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9\u0600-\u06FF]/.test(v)) s++;
    var c = ['#EF4444','#F97316','#EAB308','#22C55E'][Math.min(s - 1, 3)] || '#EF4444';
    var w = ['25%','50%','75%','100%'][Math.min(s - 1, 3)] || '10%';
    pwFill.style.width = w;
    pwFill.style.background = c;
  });

  /* ══ Ripple ══ */
  var btn = document.getElementById('sb');
  btn.addEventListener('click', function(e) {
    var r = btn.getBoundingClientRect();
    var rip = document.createElement('span');
    rip.className = 'ripple';
    var sz = Math.max(r.width, r.height);
    rip.style.cssText = 'width:'+sz+'px;height:'+sz+'px;left:'+(e.clientX-r.left-sz/2)+'px;top:'+(e.clientY-r.top-sz/2)+'px';
    btn.appendChild(rip);
    setTimeout(function() { rip.remove(); }, 600);
  });

  /* ══ Submit loading ══ */
  document.getElementById('lf').addEventListener('submit', function(e) {
    var un = document.getElementById('un');
    var pw = document.getElementById('pw');
    if (!un.value.trim() || !pw.value.trim()) { e.preventDefault(); return; }
    btn.classList.add('ld'); btn.disabled = true;
  });

  /* ══ Auto-focus ══ */
  var un = document.getElementById('un');
  if (un && !un.value) un.focus();

  /* ══ Stagger feat items ══ */
  document.querySelectorAll('.feat').forEach(function(el, i) {
    el.style.opacity = '0'; el.style.transform = 'translateX(14px)';
    setTimeout(function() {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity = '1'; el.style.transform = 'none';
    }, 450 + i * 90);
  });

});
</script>
</body>
</html>