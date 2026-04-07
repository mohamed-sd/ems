<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include "../config.php";
require_once dirname(__FILE__) . '/../includes/dynamic_nav.php';

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

/* ==============================
   DATA LAYER
============================== */
$role     = $_SESSION['user']['role'];
$userName = $_SESSION['user']['name'];
$roleText = "غير معروف";
$companyId = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$companyName = '';

if (!function_exists('dashboard_has_column')) {
  function dashboard_has_column($conn, $tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($conn, $safeCol) . "'";
    $res = @mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
  }
}

if (!function_exists('dashboard_scalar')) {
  function dashboard_scalar($conn, $sql, $key) {
    $res = $conn->query($sql);
    if (!$res) {
      return 0;
    }
    $row = $res->fetch_assoc();
    if (!$row || !isset($row[$key])) {
      return 0;
    }
    return $row[$key];
  }
}

$projectClientColumn = dashboard_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if ($companyId > 0) {
  $companyNameCols = array();
  if (dashboard_has_column($conn, 'admin_companies', 'company_name_ar')) {
    $companyNameCols[] = 'company_name_ar';
  }
  if (dashboard_has_column($conn, 'admin_companies', 'company_name')) {
    $companyNameCols[] = 'company_name';
  }
  if (dashboard_has_column($conn, 'admin_companies', 'name')) {
    $companyNameCols[] = 'name';
  }

  if (!empty($companyNameCols)) {
    $companyNameSql = "SELECT " . implode(', ', $companyNameCols) . " FROM admin_companies WHERE id = $companyId LIMIT 1";
    $companyNameRes = @mysqli_query($conn, $companyNameSql);
    if ($companyNameRes) {
      $companyNameRow = mysqli_fetch_assoc($companyNameRes);
      foreach ($companyNameCols as $col) {
        if (isset($companyNameRow[$col]) && trim($companyNameRow[$col]) !== '') {
          $companyName = trim($companyNameRow[$col]);
          break;
        }
      }
    }
  }
}

// جلب اسم الدور من جدول roles بدلًا من المصفوفة الثابتة
$roleId = intval($role);
$roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
if ($roleStmt) {
  $roleStmt->bind_param("i", $roleId);
  $roleStmt->execute();
  $roleResult = $roleStmt->get_result();
  if ($roleResult && $roleRow = $roleResult->fetch_assoc()) {
    $roleText = $roleRow['name'];
  }
  $roleStmt->close();
}

/* جلب اسم المشروع للمستخدم */
$userId = $_SESSION['user']['id'];
$projectId = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$projectName = '';
if ($projectId > 0) {
  $projectScopeForName = $companyId > 0
    ? " AND (EXISTS (SELECT 1 FROM users su WHERE su.id = project.created_by AND su.company_id = $companyId)
         OR EXISTS (
             SELECT 1 FROM clients sc
             INNER JOIN users scu ON scu.id = sc.created_by
             WHERE sc.id = project.$projectClientColumn AND scu.company_id = $companyId
         ))"
    : '';

  $projectQuery = $conn->query("SELECT name FROM project WHERE id = $projectId $projectScopeForName LIMIT 1");
  if ($projectQuery && $projectRow = $projectQuery->fetch_assoc()) {
    $projectName = $projectRow['name'];
  }
}

/* Quick links - Dynamic from modules table */
$dynamicLinks = getDynamicNavLinks($conn, $role);
$links = [];
foreach ($dynamicLinks as $link) {
  // Format: [href, icon, label]
  $links[] = ['../' . $link['code'], 'fas fa-bolt', $link['name']];
}

/* Stat cards - [icon, raw_value, label, accent] */
$stats = [];

$scopeClients = $companyId > 0
  ? "EXISTS (SELECT 1 FROM users su WHERE su.id = clients.created_by AND su.company_id = $companyId)"
  : '1=1';
$scopeProjects = $companyId > 0
  ? "(
      EXISTS (SELECT 1 FROM users su WHERE su.id = project.created_by AND su.company_id = $companyId)
      OR EXISTS (
          SELECT 1 FROM clients sc
          INNER JOIN users scu ON scu.id = sc.created_by
          WHERE sc.id = project.$projectClientColumn AND scu.company_id = $companyId
      )
    )"
  : '1=1';
$scopeOperationsByProject = $companyId > 0
  ? "operations.project_id IN (
      SELECT p.id
      FROM project p
      WHERE
        EXISTS (SELECT 1 FROM users su WHERE su.id = p.created_by AND su.company_id = $companyId)
        OR EXISTS (
            SELECT 1 FROM clients sc
            INNER JOIN users scu ON scu.id = sc.created_by
            WHERE sc.id = p.$projectClientColumn AND scu.company_id = $companyId
        )
    )"
  : '1=1';

if ($role=="0"||$role=="1") {
  $c=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND $scopeClients", 't');
  $p=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM project WHERE status='1' AND $scopeProjects", 't');
  $m=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM mines WHERE status='1' AND project_id IN (SELECT id FROM project WHERE $scopeProjects)", 't');
  $u=($companyId > 0)
    ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id = $companyId AND role!='-1'", 't')
    : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'", 't');
  $stats=[['fa-users',$c,'العملاء','gold'],['fa-project-diagram',$p,'المشاريع','blue'],['fa-mountain',$m,'المناجم','teal'],['fa-user-shield',$u,'المستخدمين','purple']];
} elseif ($role=="2") {
  $s=dashboard_scalar($conn, "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s JOIN equipments e ON e.suppliers=s.id JOIN operations o ON o.equipment=e.id WHERE s.status='1' AND $scopeOperationsByProject", 't');
  $e=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e JOIN operations o ON o.equipment=e.id WHERE e.status='1' AND $scopeOperationsByProject", 't');
  $co=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM supplierscontracts WHERE status='1' AND project_id IN (SELECT id FROM project WHERE $scopeProjects)", 't');
  $stats=[['fa-truck',$s,'الموردين','gold'],['fa-tools',$e,'الآليات','blue'],['fa-file-contract',$co,'العقود','teal']];
} elseif ($role=="4") {
  $eq=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e JOIN operations o ON o.equipment=e.id WHERE e.status='1' AND $scopeOperationsByProject", 't');
  $ao=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM operations WHERE status='1' AND $scopeOperationsByProject", 't');
  $bo=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM operations WHERE status='0' AND $scopeOperationsByProject", 't');
  $stats=[['fa-tools',$eq,'إجمالي المعدات','gold'],['fa-play-circle',$ao,'تعمل الآن','blue'],['fa-exclamation-triangle',$bo,'معطلة','orange']];
} elseif ($role=="3") {
  $dr=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON ed.driver_id=d.id JOIN operations o ON o.equipment=ed.equipment_id WHERE d.status='1' AND $scopeOperationsByProject", 't');
  $ad=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN timesheet t ON d.id=t.driver JOIN operations o ON t.operator=o.id WHERE t.status='1' AND $scopeOperationsByProject", 't');
  $stats=[['fa-id-badge',$dr,'المشغلين','gold'],['fa-user-check',$ad,'يعملون الآن','blue'],['fa-user-clock',$dr-$ad,'خاملين','orange']];
} elseif ($role=="5") {
  $sv=($companyId > 0)
    ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id = $companyId AND role IN ('6','7','8','9')", 't')
    : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE role IN ('6','7','8','9')", 't');
  $h=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE $scopeOperationsByProject", 't');
  $ah=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE t.status='1' AND $scopeOperationsByProject", 't');
  $stats=[['fa-users-cog',$sv,'المشرفين','gold'],['fa-clock',(int)$h,'ساعات العمل','blue'],['fa-check-circle',(int)$ah,'الساعات المعتمدة','teal']];
} elseif ($role=="10") {
  $eq=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e JOIN operations o ON o.equipment=e.id WHERE e.status='1' AND $scopeOperationsByProject", 't');
  $dr=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON ed.driver_id=d.id JOIN operations o ON o.equipment=ed.equipment_id WHERE d.status='1' AND $scopeOperationsByProject", 't');
  $h=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE $scopeOperationsByProject", 't');
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
/* ====================================================
   TOKENS
==================================================== */
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

/* ====================================================
  LAYOUT - 3-row grid that fills 100vh exactly
   row1: topbar   (auto)
   row2: hero     (auto)
  row3: stats    (1fr - grows to fill all remaining)
==================================================== */
.main{
  display:grid;
  grid-template-rows:auto auto 1fr;
  height:100vh;
  padding:16px 20px 16px;
  gap:13px;
  overflow:hidden;
  width:100%;
}

/* ====================================================
   TOP BAR
==================================================== */
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

/* ====================================================
  HERO ROW - banner + quick links side by side
==================================================== */
.hero-row{
  display:grid;
  grid-template-columns:1fr 230px;
  gap:13px;
  flex-shrink:0;
}

/* -- Banner -- */
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

/* -- Quick Links Panel -- */
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

/* 2-col icon grid - never overflows vertically */
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

/* ====================================================
  STATS SECTION - fills ALL remaining vertical space
   Cards stretch to fill via flex + min-height:0
==================================================== */
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

/* -- Stat Card -- */
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

/* card inner layout - centred, grows to fill height */
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

/* large ghost number - decorative */
.scard-ghost{
  position:absolute;
  bottom:50px;left:76%;transform:translateX(-50%);
  font-size:clamp(4rem,9vw,7rem);font-weight:900;line-height:1;
  color:rgba(12,28,62,.04);pointer-events:none;user-select:none;white-space:nowrap;
}

/* ====================================================
   RESPONSIVE
==================================================== */
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

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-layer-group"></i></div>
      <div class="brand-info">
        <div class="sys">إيكوبيشن EPS</div>
        <div class="greet">
          مرحباً، <?= htmlspecialchars($userName) ?>
          <?php if ($companyName !== ''): ?>
            | <?= htmlspecialchars($companyName) ?>
          <?php endif; ?>
          👋
        </div>
      </div>
    </div>
    <div class="topbar-r">
      <div class="clock"><i class="fas fa-clock"></i><span id="clk">--:--</span></div>
      <a href="../logout.php" class="btn-out"><i class="fas fa-sign-out-alt"></i><span>خروج</span></a>
    </div>
  </div>

  <!-- HERO ROW -->
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
        <?php foreach ($links as $i => $lnk):
          $href = isset($lnk[0]) ? $lnk[0] : '#';
          $ico  = isset($lnk[1]) ? $lnk[1] : 'fa-link';
          $lbl  = isset($lnk[2]) ? $lnk[2] : 'رابط';
        ?>
        <a href="<?=$href?>" class="ql-btn" style="animation:popCard .4s <?=$i*.06?>s cubic-bezier(.4,0,.2,1) both">
          <i class="fas <?=$ico?>"></i><span><?=$lbl?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mobile quick links (below banner, horizontal pills) -->
  <div class="mobile-links">
    <?php foreach ($links as $lnk):
      $href = isset($lnk[0]) ? $lnk[0] : '#';
      $ico  = isset($lnk[1]) ? $lnk[1] : 'fa-link';
      $lbl  = isset($lnk[2]) ? $lnk[2] : 'رابط';
    ?>
    <a href="<?=$href?>" class="ml-btn"><i class="fas <?=$ico?>"></i><?=$lbl?></a>
    <?php endforeach; ?>
  </div>

  <!-- STATS -->
  <div class="stats-wrap">
    <div class="stats-label"><i class="fas fa-chart-bar"></i>الإحصائيات الحالية</div>
    <div class="cards-grid">
      <?php foreach ($stats as $st):
        $ico = isset($st[0]) ? $st[0] : 'fa-chart-bar';
        $val = isset($st[1]) ? $st[1] : 0;
        $lbl = isset($st[2]) ? $st[2] : 'إحصائية';
        $accent = isset($st[3]) ? $st[3] : 'blue';
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
