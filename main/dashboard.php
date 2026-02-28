<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../index.php"); exit(); }
include "../config.php";

/* ══════════════════════════════
   DATA LAYER
══════════════════════════════ */
$roles = [
  "0"=>"مدير النظام","1"=>"مدير المشاريع","2"=>"مدير الموردين",
  "3"=>"مدير المشغلين","4"=>"مدير الأسطول","5"=>"مدير موقع",
  "6"=>"مدخل ساعات","7"=>"مراجع مورد","8"=>"مراجع مشغل",
  "9"=>"مراجع الأعطال","10"=>"مدير حركة وتشغيل",
];
$role     = $_SESSION['user']['role'];
$userName = $_SESSION['user']['name'];
$roleText = $roles[$role] ?? "غير معروف";

/* جلب اسم المشروع للمستخدم */
$userId = $_SESSION['user']['id'];
$projectId = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$projectName = '';
if ($projectId > 0) {
  $projectQuery = $conn->query("SELECT name FROM project WHERE id = $projectId LIMIT 1");
  if ($projectQuery && $projectRow = $projectQuery->fetch_assoc()) {
    $projectName = $projectRow['name'];
  }
}

/* Quick links */
$allLinks = [
  "0"  => [['../Clients/clients.php','fa-users','العملاء'],['../Projects/oprationprojects.php','fa-project-diagram','المشاريع'],['../main/users.php','fa-user-shield','المستخدمين'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "1"  => [['../Clients/clients.php','fa-users','العملاء'],['../Projects/oprationprojects.php','fa-project-diagram','المشاريع'],['../main/users.php','fa-user-shield','المستخدمين'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Equipments/equipments_types.php','fa-screwdriver-wrench','الأنواع'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "2"  => [['../Suppliers/suppliers.php','fa-truck','الموردين'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "3"  => [['../Equipments/equipments.php','fa-tractor','المعدات'],['../Drivers/drivers.php','fa-id-badge','المشغلين'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "4"  => [['../Equipments/equipments.php','fa-tools','المعدات'],['../Oprators/oprators.php','fa-cogs','التشغيل'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "5"  => [['../main/project_users.php','fa-users-cog','المشرفين'],['../Timesheet/timesheet.php','fa-clock','الساعات'],['../Timesheet/view_timesheet.php','fa-clock','ساعات اليوم'],['../Reports/reports.php','fa-chart-line','التقارير'],['../Settings/settings.php','fa-cog','الإعدادات']],
  "10" => [['../Oprators/oprators.php','fa-play-circle','التشغيل'],['../Equipments/equipments.php','fa-tools','المعدات'],['../Settings/settings.php','fa-cog','الإعدادات']],
];
$links = $allLinks[$role] ?? [];

/* Stat cards — [icon, raw_value, label, accent] */
$stats = [];
if ($role=="0"||$role=="1") {
  $c=$conn->query("SELECT COUNT(*) AS t FROM clients WHERE status='نشط'")->fetch_assoc()['t'];
  $p=$conn->query("SELECT COUNT(*) AS t FROM project WHERE status='1'")->fetch_assoc()['t'];
  $m=$conn->query("SELECT COUNT(*) AS t FROM mines WHERE status='1'")->fetch_assoc()['t'];
  $u=$conn->query("SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'")->fetch_assoc()['t'];
  $stats=[['fa-users',$c,'العملاء','gold'],['fa-project-diagram',$p,'المشاريع','blue'],['fa-mountain',$m,'المناجم','teal'],['fa-user-shield',$u,'المستخدمين','purple']];
} elseif ($role=="2") {
  $s=$conn->query("SELECT COUNT(*) AS t FROM suppliers WHERE status='1'")->fetch_assoc()['t'];
  $e=$conn->query("SELECT COUNT(*) AS t FROM equipments WHERE status='1'")->fetch_assoc()['t'];
  $co=$conn->query("SELECT COUNT(*) AS t FROM supplierscontracts WHERE status='1'")->fetch_assoc()['t'];
  $stats=[['fa-truck',$s,'الموردين','gold'],['fa-tools',$e,'الآليات','blue'],['fa-file-contract',$co,'العقود','teal']];
} elseif ($role=="4") {
  $eq=$conn->query("SELECT COUNT(*) AS t FROM equipments WHERE status='1'")->fetch_assoc()['t'];
  $ao=$conn->query("SELECT COUNT(*) AS t FROM operations WHERE status='1'")->fetch_assoc()['t'];
  $bo=$conn->query("SELECT COUNT(*) AS t FROM operations WHERE status='0'")->fetch_assoc()['t'];
  $stats=[['fa-tools',$eq,'إجمالي المعدات','gold'],['fa-play-circle',$ao,'تعمل الآن','blue'],['fa-exclamation-triangle',$bo,'معطلة','orange']];
} elseif ($role=="3") {
  $dr=$conn->query("SELECT COUNT(*) AS t FROM drivers WHERE status='1'")->fetch_assoc()['t'];
  $ad=$conn->query("SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN timesheet t ON d.id=t.driver WHERE t.status='1'")->fetch_assoc()['t'];
  $stats=[['fa-id-badge',$dr,'المشغلين','gold'],['fa-user-check',$ad,'يعملون الآن','blue'],['fa-user-clock',$dr-$ad,'خاملين','orange']];
} elseif ($role=="5") {
  $sv=$conn->query("SELECT COUNT(*) AS t FROM users WHERE role IN ('6','7','8','9')")->fetch_assoc()['t'];
  $h=$conn->query("SELECT SUM(total_work_hours) AS t FROM timesheet")->fetch_assoc()['t'];
  $ah=$conn->query("SELECT SUM(total_work_hours) AS t FROM timesheet WHERE status='1'")->fetch_assoc()['t'];
  $stats=[['fa-users-cog',$sv,'المشرفين','gold'],['fa-clock',(int)$h,'ساعات العمل','blue'],['fa-check-circle',(int)$ah,'الساعات المعتمدة','teal']];
} elseif ($role=="10") {
  $eq=$conn->query("SELECT COUNT(*) AS t FROM equipments WHERE status='1'")->fetch_assoc()['t'];
  $dr=$conn->query("SELECT COUNT(*) AS t FROM drivers WHERE status='1'")->fetch_assoc()['t'];
  $h=$conn->query("SELECT SUM(total_work_hours) AS t FROM timesheet")->fetch_assoc()['t'];
  $stats=[['fa-tools',$eq,'الآليات','gold'],['fa-id-badge',$dr,'المشغلين','blue'],['fa-clock',(int)$h,'الساعات','teal']];
}

$statCount = count($stats);
$linkCount = count($links);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إيكوبيشن | الرئيسية</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  <style>
/* ════════════════════════════════════════════════════
   TOKENS
════════════════════════════════════════════════════ */
:root{
  --navy:      #0c1c3e;
  --navy-m:    #132050;
  --navy-l:    #1b2f6e;
  --gold:      #e8b800;
  --gold-l:    #ffd740;
  --gold-d:    rgba(232,184,0,.13);
  --blue:      #2563eb;
  --blue-d:    rgba(37,99,235,.12);
  --teal:      #0d9488;
  --teal-d:    rgba(13,148,136,.12);
  --purple:    #7c3aed;
  --purple-d:  rgba(124,58,237,.12);
  --orange:    #ea6f00;
  --orange-d:  rgba(234,111,0,.12);
  --bg:        #f0f2f8;
  --card:      #ffffff;
  --bdr:       rgba(12,28,62,.07);
  --txt:       #0c1c3e;
  --sub:       #64748b;
  --danger:    #dc2626;
  --danger-d:  rgba(220,38,38,.09);
  --r:  14px;
  --rl: 20px;
  --rx: 26px;
  --s1: 0 1px 5px rgba(12,28,62,.06);
  --s2: 0 5px 20px rgba(12,28,62,.09);
  --s3: 0 14px 44px rgba(12,28,62,.13);
  --ease:.22s cubic-bezier(.4,0,.2,1);
  --font:'Cairo',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);color:var(--txt);background:var(--bg)}
a{text-decoration:none;color:inherit}

/* ════════════════════════════════════════════════════
   LAYOUT — 3-row grid that fills 100vh exactly
   row1: topbar   (auto)
   row2: hero     (auto)
   row3: stats    (1fr — grows to fill all remaining)
════════════════════════════════════════════════════ */
.main{
  display:grid;
  grid-template-rows:auto auto 1fr;
  height:100vh;
  padding:16px 20px 16px;
  gap:13px;
  overflow:hidden;
  width:100%;
}

/* ════════════════════════════════════════════════════
   TOP BAR
════════════════════════════════════════════════════ */
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0}

.brand{display:flex;align-items:center;gap:10px}
.brand-icon{
  width:40px;height:40px;border-radius:11px;
  background:linear-gradient(135deg,var(--navy),var(--navy-l));
  display:flex;align-items:center;justify-content:center;
  box-shadow:var(--s2);flex-shrink:0;
}
.brand-icon i{color:var(--gold);font-size:1rem}
.brand-info .sys{font-size:.67rem;font-weight:600;color:var(--sub);letter-spacing:.07em;text-transform:uppercase}
.brand-info .greet{font-size:1rem;font-weight:800;line-height:1.2}

.topbar-r{display:flex;align-items:center;gap:8px}
.clock{
  display:flex;align-items:center;gap:5px;
  padding:6px 14px;background:var(--card);
  border:1px solid var(--bdr);border-radius:50px;
  font-size:.78rem;font-weight:700;color:var(--sub);box-shadow:var(--s1);
}
.clock i{color:var(--gold);font-size:.72rem}
.btn-out{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 16px;background:var(--danger-d);color:var(--danger);
  border-radius:50px;font-weight:700;font-size:.82rem;
  transition:background var(--ease),box-shadow var(--ease),color var(--ease);
}
.btn-out:hover{background:var(--danger);color:#fff;box-shadow:0 5px 16px rgba(220,38,38,.3)}

/* ════════════════════════════════════════════════════
   HERO ROW — banner + quick links side by side
════════════════════════════════════════════════════ */
.hero-row{
  display:grid;
  grid-template-columns:1fr 230px;
  gap:13px;
  flex-shrink:0;
}

/* ── Banner ── */
.banner{
  position:relative;overflow:hidden;
  border-radius:var(--rx);
  background:linear-gradient(125deg,var(--navy) 0%,var(--navy-m) 50%,var(--navy-l) 100%);
  padding:20px 26px;
  box-shadow:var(--s3);
  display:flex;align-items:center;justify-content:space-between;gap:14px;
  animation:fadeUp .45s cubic-bezier(.4,0,.2,1) both;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

.banner::before{
  content:'';position:absolute;inset:0;
  background-image:radial-gradient(rgba(255,255,255,.055) 1px,transparent 1px);
  background-size:20px 20px;pointer-events:none;
}
.banner::after{
  content:'';position:absolute;
  right:-55px;top:-55px;width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(232,184,0,.26) 0%,transparent 68%);
  pointer-events:none;
}

.banner-body{position:relative;z-index:1}
.banner-role{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(232,184,0,.15);border:1px solid rgba(232,184,0,.3);
  color:var(--gold-l);font-size:.66rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;
  padding:2px 10px;border-radius:50px;margin-bottom:7px;
}
.banner-role i{font-size:.38rem}
.banner-project{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.3);
  color:#60a5fa;font-size:.72rem;font-weight:700;
  padding:3px 12px;border-radius:50px;margin-bottom:7px;
}
.banner-project i{font-size:.6rem}
.banner-name{font-size:1.4rem;font-weight:900;color:#fff;line-height:1.2;min-height:1.7rem}
.cursor{
  display:inline-block;width:2px;height:1.1rem;
  background:var(--gold);border-radius:2px;
  animation:blink .75s step-end infinite;vertical-align:middle;margin-right:2px;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.banner-sub{margin-top:4px;font-size:.8rem;color:rgba(255,255,255,.5)}

.banner-emoji{
  position:relative;z-index:1;font-size:2.8rem;flex-shrink:0;
  animation:bob 4s ease-in-out infinite;
  filter:drop-shadow(0 3px 10px rgba(232,184,0,.35));
}
@keyframes bob{0%,100%{transform:translateY(0)rotate(-4deg)}50%{transform:translateY(-8px)rotate(4deg)}}

/* star particles */
.sfx{position:absolute;color:var(--gold-l);pointer-events:none;z-index:0;animation:drift 3s linear forwards}
@keyframes drift{0%{opacity:.85;transform:translateY(0)scale(1)rotate(0)}100%{opacity:0;transform:translateY(70px)scale(.2)rotate(330deg)}}

/* ── Quick Links Panel ── */
.ql-panel{
  background:var(--card);
  border:1.5px solid var(--bdr);
  border-radius:var(--rx);
  padding:13px 11px;
  box-shadow:var(--s1);
  display:flex;flex-direction:column;gap:6px;
  animation:fadeUp .45s .05s cubic-bezier(.4,0,.2,1) both;
}
.ql-title{
  font-size:.64rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--sub);display:flex;align-items:center;gap:5px;
  padding-bottom:6px;border-bottom:1px solid var(--bdr);white-space:nowrap;
}
.ql-title i{color:var(--gold)}

/* 2-col icon grid — never overflows vertically */
.ql-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;flex:1}
.ql-btn{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:3px;padding:7px 3px;border-radius:var(--r);
  border:1.5px solid transparent;transition:all var(--ease);text-align:center;
}
.ql-btn i{
  width:30px;height:30px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  background:var(--gold-d);color:var(--gold);
  font-size:.82rem;transition:all var(--ease);
}
.ql-btn span{font-size:.68rem;font-weight:700;color:var(--sub);white-space:nowrap;transition:color var(--ease)}
.ql-btn:hover{background:var(--gold-d);border-color:rgba(232,184,0,.24)}
.ql-btn:hover i{background:var(--gold);color:#fff}
.ql-btn:hover span{color:var(--navy)}
.ql-btn:last-child:nth-child(odd){grid-column:1/-1}

/* ════════════════════════════════════════════════════
   STATS SECTION — fills ALL remaining vertical space
   Cards stretch to fill via flex + min-height:0
════════════════════════════════════════════════════ */
.stats-wrap{
  display:flex;flex-direction:column;gap:8px;
  min-height:0; /* let it shrink */
}

.stats-label{
  display:flex;align-items:center;gap:7px;
  font-size:.66rem;font-weight:700;letter-spacing:.08em;
  text-transform:uppercase;color:var(--sub);flex-shrink:0;
}
.stats-label::before{
  content:'';width:3px;height:13px;
  background:linear-gradient(180deg,var(--gold),var(--navy));
  border-radius:3px;display:block;
}
.stats-label i{color:var(--gold)}

/* The grid stretches to fill remaining height */
.cards-grid{
  display:grid;
  grid-template-columns:repeat(<?= $statCount ?>,1fr);
  gap:12px;
  flex:1;
  min-height:0;
}

/* ── Stat Card ── */
.scard{
  background:var(--card);
  border-radius:var(--rl);
  border:1.5px solid var(--bdr);
  box-shadow:var(--s1);
  padding:0;
  display:flex;flex-direction:column;
  overflow:hidden;
  position:relative;
  transition:transform var(--ease),box-shadow var(--ease),border-color var(--ease);
  animation:popCard .45s cubic-bezier(.4,0,.2,1) both;
  cursor:default;
  height: 200px;
}
@keyframes popCard{from{opacity:0;transform:scale(.93)translateY(6px)}to{opacity:1;transform:scale(1)translateY(0)}}
.scard:nth-child(1){animation-delay:.07s}
.scard:nth-child(2){animation-delay:.12s}
.scard:nth-child(3){animation-delay:.17s}
.scard:nth-child(4){animation-delay:.22s}
.scard:hover{transform:translateY(-4px);box-shadow:var(--s2)}

/* coloured top-band */
.scard-band{height:4px;width:100%;flex-shrink:0;border-radius:var(--rl) var(--rl) 0 0}

/* accent colours */
.scard.gold  .scard-band{background:linear-gradient(90deg,var(--gold),var(--gold-l))}
.scard.gold:hover{border-color:rgba(232,184,0,.3)}
.scard.gold  .scard-icon{background:var(--gold-d);color:var(--gold)}
.scard.gold:hover .scard-icon{background:var(--gold);color:#fff}

.scard.blue  .scard-band{background:linear-gradient(90deg,var(--blue),#60a5fa)}
.scard.blue:hover{border-color:rgba(37,99,235,.22)}
.scard.blue  .scard-icon{background:var(--blue-d);color:var(--blue)}
.scard.blue:hover .scard-icon{background:var(--blue);color:#fff}

.scard.teal  .scard-band{background:linear-gradient(90deg,var(--teal),#2dd4bf)}
.scard.teal:hover{border-color:rgba(13,148,136,.22)}
.scard.teal  .scard-icon{background:var(--teal-d);color:var(--teal)}
.scard.teal:hover .scard-icon{background:var(--teal);color:#fff}

.scard.purple .scard-band{background:linear-gradient(90deg,var(--purple),#a78bfa)}
.scard.purple:hover{border-color:rgba(124,58,237,.22)}
.scard.purple .scard-icon{background:var(--purple-d);color:var(--purple)}
.scard.purple:hover .scard-icon{background:var(--purple);color:#fff}

.scard.orange .scard-band{background:linear-gradient(90deg,var(--orange),#fb923c)}
.scard.orange:hover{border-color:rgba(234,111,0,.22)}
.scard.orange .scard-icon{background:var(--orange-d);color:var(--orange)}
.scard.orange:hover .scard-icon{background:var(--orange);color:#fff}

/* card inner layout — centred, grows to fill height */
.scard-inner{
  flex:1;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:20px 16px;
  text-align:center;
  gap:14px;
  position:relative;z-index:1;
}

.scard-icon{
  width:52px;height:52px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;
  transition:all var(--ease);
  flex-shrink:0;
}

.scard-value{
  font-size:clamp(2rem,4vw,3.2rem);
  font-weight:900;line-height:1;color:var(--txt);
  font-variant-numeric:tabular-nums;
}
.scard-label{
  font-size:.82rem;font-weight:600;color:var(--sub);margin-top:2px;
}

/* large ghost number — decorative */
.scard-ghost{
  position:absolute;
  bottom:50px;left:76%;transform:translateX(-50%);
  font-size:clamp(4rem,9vw,7rem);font-weight:900;line-height:1;
  color:rgba(12,28,62,.04);pointer-events:none;user-select:none;white-space:nowrap;
}

/* ════════════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════════════ */
@media(max-width:980px){
  .main{height:auto;overflow:visible;padding-bottom:28px}
  .hero-row{grid-template-columns:1fr}
  .ql-panel{display:none}
  /* show links as horizontal pills under banner */
  .mobile-links{display:flex;flex-wrap:wrap;gap:8px;padding-top:2px}
  .ml-btn{
    display:inline-flex;align-items:center;gap:7px;
    padding:8px 14px;background:var(--card);
    border:1.5px solid var(--bdr);border-radius:50px;
    font-size:.82rem;font-weight:700;color:var(--txt);
    box-shadow:var(--s1);transition:all var(--ease);
  }
  .ml-btn i{color:var(--gold)}
  .ml-btn:hover{border-color:var(--gold);background:var(--gold-d)}
  .cards-grid{grid-template-columns:repeat(2,1fr)!important;gap:10px}
  .scard-value{font-size:2rem!important}
}
@media(min-width:981px){.mobile-links{display:none}}

@media(max-width:600px){
  .main{padding:11px 12px 18px;gap:9px}
  .brand-info .greet{font-size:.9rem}
  .clock{display:none}
  .banner-name{font-size:1.15rem}
  .banner-emoji{font-size:2rem}
  .banner-project{font-size:.65rem;padding:2px 8px}
  .banner-project i{font-size:.52rem}
  .cards-grid{grid-template-columns:1fr 1fr!important}
}
  </style>
</head>
<body>
<?php include('../insidebar.php'); ?>

<div class="main">

  <!-- ▌TOP BAR ▌-->
  <div class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-layer-group"></i></div>
      <div class="brand-info">
        <div class="sys">إيكوبيشن EPS</div>
        <div class="greet">مرحباً، <?= htmlspecialchars($userName) ?> 👋</div>
      </div>
    </div>
    <div class="topbar-r">
      <div class="clock"><i class="fas fa-clock"></i><span id="clk">--:--</span></div>
      <a href="../logout.php" class="btn-out"><i class="fas fa-sign-out-alt"></i><span>خروج</span></a>
    </div>
  </div>

  <!-- ▌HERO ROW ▌-->
  <div class="hero-row">

    <!-- Banner -->
    <div class="banner" id="bannerEl">
      <div class="banner-body">
        <div class="banner-role"><i class="fas fa-circle"></i><?= htmlspecialchars($roleText) ?></div>
        <?php if ($projectName): ?>
        <div class="banner-project">
          <i class="fas fa-project-diagram"></i>
          <span><?= htmlspecialchars($projectName) ?></span>
        </div>
        <?php endif; ?>
        <div class="banner-name"><span id="typed"></span><span class="cursor"></span></div>
        <div class="banner-sub">نتمنى لك يوماً مليئاً بالإنجازات 🚀</div>
      </div>
      <div class="banner-emoji">🏆</div>
    </div>

    <!-- Desktop quick links panel -->
    <div class="ql-panel">
      <div class="ql-title"><i class="fas fa-bolt"></i>الوصول السريع</div>
      <div class="ql-grid">
        <?php foreach ($links as $i=>[$href,$ico,$lbl]): ?>
        <a href="<?=$href?>" class="ql-btn" style="animation:popCard .4s <?=$i*.06?>s cubic-bezier(.4,0,.2,1) both">
          <i class="fas <?=$ico?>"></i><span><?=$lbl?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mobile quick links (below banner, horizontal pills) -->
  <div class="mobile-links">
    <?php foreach ($links as [$href,$ico,$lbl]): ?>
    <a href="<?=$href?>" class="ml-btn"><i class="fas <?=$ico?>"></i><?=$lbl?></a>
    <?php endforeach; ?>
  </div>

  <!-- ▌STATS ▌-->
  <div class="stats-wrap">
    <div class="stats-label"><i class="fas fa-chart-bar"></i>الإحصائيات الحالية</div>
    <div class="cards-grid">
      <?php foreach ($stats as [$ico,$val,$lbl,$accent]):
        $num = (int) str_replace(',','',$val);
        $display = number_format($num);
      ?>
      <div class="scard <?=$accent?>">
        <div class="scard-band"></div>
        <div class="scard-inner">
          <div class="scard-icon"><i class="fas <?=$ico?>"></i></div>
          <div>
            <div class="scard-value" data-to="<?=$num?>"><?=$display?></div>
            <div class="scard-label"><?=$lbl?></div>
          </div>
        </div>
        <div class="scard-ghost"><?=$display?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div><!-- .main -->

<script>
/* Clock */
const clkEl=document.getElementById('clk');
(function tick(){const n=new Date();clkEl.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');setTimeout(tick,15000);})();

/* Typewriter */
const typedEl=document.getElementById('typed');
const name=<?=json_encode($userName)?>;
let ci=0;
(function t(){typedEl.textContent=name.slice(0,ci++);if(ci<=name.length)setTimeout(t,65);})();

/* Count-up */
document.querySelectorAll('.scard-value[data-to]').forEach(el=>{
  const target=parseInt(el.dataset.to);if(!target)return;
  const steps=30,dur=900;let i=0;
  const iv=setInterval(()=>{
    i++;const v=Math.round(target*Math.min(i/steps,1));
    el.textContent=v.toLocaleString('ar-EG');
    if(i>=steps){el.textContent=target.toLocaleString('ar-EG');clearInterval(iv);}
  },dur/steps);
});

/* Stars */
const bEl=document.getElementById('bannerEl');
function star(){
  const s=document.createElement('i');
  s.className='fas fa-star sfx';
  s.style.cssText=`left:${4+Math.random()*92}%;top:${4+Math.random()*58}%;font-size:${6+Math.random()*8}px;animation-duration:${2+Math.random()*2}s`;
  bEl.appendChild(s);setTimeout(()=>s.remove(),3500);
}
setInterval(star,900);
</script>
</body>
</html>