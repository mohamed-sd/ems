<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include "../config.php";
require_once dirname(__FILE__) . '/../includes/dynamic_nav.php';
if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }

/* ════════════════  DATA LAYER  ════════════════ */
$role        = $_SESSION['user']['role'];
$userName    = $_SESSION['user']['name'];
$roleText    = "غير معروف";
$companyId   = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$companyName = '';

if (!function_exists('dashboard_has_column')) {
  function dashboard_has_column($conn,$t,$c){
    $t=preg_replace('/[^a-zA-Z0-9_]/','', $t);
    $c=preg_replace('/[^a-zA-Z0-9_]/','', $c);
    $r=@mysqli_query($conn,"SHOW COLUMNS FROM $t LIKE '".mysqli_real_escape_string($conn,$c)."'");
    return $r && mysqli_num_rows($r)>0;
  }
}
if (!function_exists('dashboard_scalar')) {
  function dashboard_scalar($conn,$sql,$key){
    $r=$conn->query($sql); if(!$r) return 0;
    $row=$r->fetch_assoc(); return ($row&&isset($row[$key]))?$row[$key]:0;
  }
}

$projectClientColumn = dashboard_has_column($conn,'project','client_id') ? 'client_id' : 'company_client_id';

if ($companyId > 0) {
  $cols = [];
  foreach (['company_name_ar','company_name','name'] as $c)
    if (dashboard_has_column($conn,'admin_companies',$c)) $cols[] = $c;
  if ($cols) {
    $r = @mysqli_query($conn,"SELECT ".implode(',',$cols)." FROM admin_companies WHERE id=$companyId LIMIT 1");
    if ($r) { $row = mysqli_fetch_assoc($r); foreach ($cols as $c) if (isset($row[$c])&&trim($row[$c])!=='') { $companyName=trim($row[$c]); break; } }
  }
}

$roleId = intval($role);
$s = $conn->prepare("SELECT name FROM roles WHERE id=? LIMIT 1");
if ($s) { $s->bind_param("i",$roleId); $s->execute(); if ($r=$s->get_result()) if ($rw=$r->fetch_assoc()) $roleText=$rw['name']; $s->close(); }

$dashboardRole = strval($role);
$s2 = $conn->prepare("SELECT parent_role_id FROM roles WHERE id=? LIMIT 1");
if ($s2) { $s2->bind_param("i",$roleId); $s2->execute(); if ($r=$s2->get_result()) if ($rw=$r->fetch_assoc()) { $pid=intval($rw['parent_role_id']??0); if($pid>0) $dashboardRole=strval($pid); } $s2->close(); }

$projectId   = isset($_SESSION['user']['project_id'])   ? intval($_SESSION['user']['project_id'])   : 0;
$projectName = '';
if ($projectId > 0) {
  $psc = $companyId > 0
    ? " AND (EXISTS(SELECT 1 FROM users su WHERE su.id=project.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=project.$projectClientColumn AND scu.company_id=$companyId))"
    : "";
  $pq = $conn->query("SELECT name FROM project WHERE id=$projectId $psc LIMIT 1");
  if ($pq && $prow=$pq->fetch_assoc()) $projectName = $prow['name'];
}

$dynamicLinks = getDynamicNavLinks($conn,$role);
$links = [];
foreach ($dynamicLinks as $l) {
  $links[] = [
    '../'.$l['code'],
    $l['name'],
    !empty($l['icon']) ? $l['icon'] : 'fa fa-link'
  ];
}

$sc = $companyId > 0 ? "EXISTS(SELECT 1 FROM users su WHERE su.id=clients.created_by AND su.company_id=$companyId)" : "1=1";
$sp = $companyId > 0 ? "(EXISTS(SELECT 1 FROM users su WHERE su.id=project.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=project.$projectClientColumn AND scu.company_id=$companyId))" : "1=1";
$so = $companyId > 0 ? "operations.project_id IN(SELECT p.id FROM project p WHERE EXISTS(SELECT 1 FROM users su WHERE su.id=p.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=p.$projectClientColumn AND scu.company_id=$companyId))" : "1=1";

$hasMineId = dashboard_has_column($conn,'operations','mine_id');
$hasSuppId = dashboard_has_column($conn,'operations','supplier_id');
$hasAvail  = dashboard_has_column($conn,'equipments','availability_status');
$hasDrvSt  = dashboard_has_column($conn,'drivers','driver_status');
$hasSCMine = dashboard_has_column($conn,'supplierscontracts','mine_id');
$hasSCPCId = dashboard_has_column($conn,'supplierscontracts','project_contract_id');

$sessionMineId     = isset($_SESSION['user']['mine_id'])     ? intval($_SESSION['user']['mine_id'])     : 0;
$sessionContractId = isset($_SESSION['user']['contract_id']) ? intval($_SESSION['user']['contract_id']) : 0;

$stats = []; $role6SupplierBreakdown = []; $role6ContextText = '';

if ($dashboardRole=="0"||$dashboardRole=="1") {
  $c=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND $sc",'t');
  $p=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM project WHERE status='1' AND $sp",'t');
  $m=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM mines WHERE status='1' AND project_id IN(SELECT id FROM project WHERE $sp)",'t');
  $u=$companyId>0?dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM users WHERE company_id=$companyId AND role!='-1'",'t'):dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'",'t');
  $stats=[['fa-users',$c,'العملاء','or'],['fa-project-diagram',$p,'المشاريع','or'],['fa-mountain',$m,'المناجم','or'],['fa-user-shield',$u,'المستخدمون','or']];
} elseif ($dashboardRole=="2") {
  $s=dashboard_scalar($conn,"SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id=$companyId",'t');
  $e=dashboard_scalar($conn,"SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id=$companyId",'t');
  $co=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM supplierscontracts WHERE project_id IN(SELECT id FROM project WHERE $sp)",'t');
  $stats=[['fa-truck',$s,'الموردون','or'],['fa-tools',$e,'الآليات','or'],['fa-file-contract',$co,'العقود','ok']];
} elseif ($dashboardRole=="3") {
  $s=dashboard_scalar($conn,"SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id=$companyId",'t');
  $eq=dashboard_scalar($conn,"SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id=$companyId",'t');
  $ao=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM operations WHERE status='1' AND company_id=$companyId AND $so",'t');
  $bo=dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM equipments WHERE status='3' AND company_id=$companyId",'t');
  $stats=[['fa-tools',$eq,'إجمالي المعدات','or'],['fa-play-circle',$ao,'تعمل الآن','ok'],['fa-exclamation-triangle',$bo,'معطلة','err'],['fa-truck',$s,'الموردون','or']];
} elseif ($dashboardRole=="4") {
  $dr=dashboard_scalar($conn,"SELECT COUNT(DISTINCT d.id) AS t FROM drivers d WHERE company_id=$companyId",'t');
  $ad=dashboard_scalar($conn,"SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON d.id=ed.driver_id WHERE ed.status='1' AND d.company_id=$companyId",'t');
  $stats=[['fa-id-badge',$dr,'إجمالي المشغلين','or'],['fa-user-check',$ad,'يعملون الآن','ok'],['fa-user-clock',$dr-$ad,'خاملون','warn']];
} elseif ($dashboardRole=="5") {
  $sv=$companyId>0?dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM users WHERE company_id=$companyId AND role IN('6','7','8','9')",'t'):dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM users WHERE role IN('6','7','8','9')",'t');
  $h=dashboard_scalar($conn,"SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE o.company_id=$companyId",'t');
  $ah=dashboard_scalar($conn,"SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN timesheet_approvals ta ON t.id=ta.timesheet_id AND approval_level='4' WHERE t.company_id=$companyId",'t');
  $stats=[['fa-users-cog',$sv,'المشرفون','or'],['fa-clock',(int)$h,'ساعات العمل','or'],['fa-check-circle',(int)$ah,'الساعات المعتمدة','ok']];
} elseif ($dashboardRole=="6") {
  $pSql=$projectId>0?"o.project_id='$projectId'":"1=0";
  $mSql=($sessionMineId>0&&$hasMineId)?" AND o.mine_id='$sessionMineId'":"";
  $role6ContextText=$projectId>0?'المشروع الحالي'.($sessionMineId>0?' · المنجم المحدد':''):'لا يوجد مشروع محدد';
  $stopList="'معطلة','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة'";
  $wCond=$hasAvail?" AND(e.availability_status IS NULL OR e.availability_status NOT IN($stopList))":"";
  $totEq=dashboard_scalar($conn,"SELECT COUNT(DISTINCT o.equipment) AS t FROM operations o WHERE $pSql$mSql AND o.equipment IS NOT NULL AND o.equipment<>'' AND o.equipment<>'0'",'t');
  $wrkEq=dashboard_scalar($conn,"SELECT COUNT(DISTINCT o.equipment) AS t FROM operations o LEFT JOIN equipments e ON e.id=o.equipment WHERE $pSql$mSql AND o.status='1' AND(e.status='1' OR e.status IS NULL)$wCond AND o.equipment IS NOT NULL AND o.equipment<>'' AND o.equipment<>'0'",'t');
  $stpEq=max(0,intval($totEq)-intval($wrkEq));
  $dCond=$hasDrvSt?" AND(d.driver_status IS NULL OR d.driver_status NOT IN('موقوف','متوقف'))":"";
  $totOp=dashboard_scalar($conn,"SELECT COUNT(DISTINCT ed.driver_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN drivers d ON d.id=ed.driver_id WHERE $pSql$mSql",'t');
  $wrkOp=dashboard_scalar($conn,"SELECT COUNT(DISTINCT ed.driver_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN drivers d ON d.id=ed.driver_id WHERE $pSql$mSql AND ed.status='1' AND d.status='1'$dCond",'t');
  $stpOp=max(0,intval($totOp)-intval($wrkOp));
  $scMine=($sessionMineId>0&&$hasSCMine)?" AND sc.mine_id=$sessionMineId":"";
  $scCid=($sessionContractId>0&&$hasSCPCId)?" AND sc.project_contract_id=$sessionContractId":"";
  $supCnt=dashboard_scalar($conn,"SELECT COUNT(DISTINCT sc.supplier_id) AS t FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scMine$scCid",'t');
  if ($projectId>0&&$hasSuppId) {
    $subq="SELECT DISTINCT sc.supplier_id FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scMine$scCid";
    $br=$conn->query("SELECT o.supplier_id,COALESCE(s.name,CONCAT('مورد #',o.supplier_id)) AS supplier_name,COUNT(DISTINCT o.equipment) AS equipments_count FROM operations o LEFT JOIN suppliers s ON s.id=o.supplier_id WHERE $pSql$mSql AND o.supplier_id IS NOT NULL AND o.supplier_id<>'' AND o.supplier_id<>'0' AND o.supplier_id IN($subq) GROUP BY o.supplier_id,supplier_name ORDER BY equipments_count DESC,supplier_name ASC");
    if ($br) while ($row=$br->fetch_assoc()) $role6SupplierBreakdown[]=['supplier_name'=>$row['supplier_name'],'equipments_count'=>intval($row['equipments_count'])];
  }
  $stats=[
    ['fa-tools',intval($totEq),'إجمالي الآليات','or'],
    ['fa-play-circle',intval($wrkEq),'تعمل الآن','ok'],
    ['fa-wrench',intval($stpEq),'صيانة / متوقفة','err'],
    ['fa-id-badge',intval($totOp),'إجمالي المشغلين','or'],
    ['fa-user-check',intval($wrkOp),'يعملون الآن','ok'],
    ['fa-user-times',intval($stpOp),'متوقفون','warn'],
    ['fa-truck',intval($supCnt),'موردو العقد','or'],
  ];
} elseif ($dashboardRole=="10") {
  $eq=dashboard_scalar($conn,"SELECT COUNT(DISTINCT e.id) AS t FROM equipments e JOIN operations o ON o.equipment=e.id WHERE e.status='1' AND $so",'t');
  $dr=dashboard_scalar($conn,"SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON ed.driver_id=d.id JOIN operations o ON o.equipment=ed.equipment_id WHERE d.status='1' AND $so",'t');
  $h=dashboard_scalar($conn,"SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE $so",'t');
  $stats=[['fa-tools',$eq,'الآليات','or'],['fa-id-badge',$dr,'المشغلون','or'],['fa-clock',(int)$h,'الساعات','or']];
}

$AC = [
  'or'  => ['bg'=>'#F7931A','soft'=>'#FFF4E6','text'=>'#B45309','ico'=>'#F7931A'],
  'ok'  => ['bg'=>'#16A34A','soft'=>'#F0FDF4','text'=>'#15803D','ico'=>'#16A34A'],
  'warn'=> ['bg'=>'#D97706','soft'=>'#FFFBEB','text'=>'#B45309','ico'=>'#D97706'],
  'err' => ['bg'=>'#DC2626','soft'=>'#FEF2F2','text'=>'#B91C1C','ico'=>'#DC2626'],
];

$page_title = 'Equipation | الرئيسية';
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* ═══════════════════════════════════════════════════════════
   EQUIPATION EMS — Dashboard v7
   Direction: WARM LIGHT — cream base, orange brand identity
   Layout: horizontal split — LEFT wide content | RIGHT stat rail
   Typography: large, readable, confident
═══════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  /* Warm cream surfaces */
  --bg:   #F5F0E8;   /* page background — warm cream      */
  --s0:   #1A1208;   /* topbar — warm near-black          */
  --s1:   #FFFFFF;   /* card surface                      */
  --s2:   #FDF8F0;   /* rail background                   */
  --s3:   #FFF4E6;   /* hover tint                        */
  --bdr:  #E8DCC8;   /* border warm beige                 */
  --bdr2: #F0E8D8;   /* subtle divider                    */

  /* Brand */
  --or:   #F7931A;
  --or2:  #E67E00;
  --or3:  #C96A00;   /* dark orange — on light bg         */
  --ord:  rgba(247,147,26,.15);
  --orb:  rgba(247,147,26,.08);

  /* Text */
  --t1:   #1A1208;   /* primary — warm black              */
  --t2:   #6B4E2A;   /* secondary — warm brown            */
  --t3:   #A07848;   /* tertiary — muted tan              */

  /* Semantic — muted to work on light */
  --ok:   #16A34A;
  --warn: #D97706;
  --err:  #DC2626;

  /* Geometry */
  --r:    8px;
  --rl:   12px;
  --hex:  polygon(8% 0,92% 0,100% 50%,92% 100%,8% 100%,0 50%);
  --sh:   0 1px 3px rgba(26,18,8,.08), 0 4px 12px rgba(26,18,8,.06);
  --sh2:  0 2px 8px rgba(26,18,8,.1),  0 8px 24px rgba(26,18,8,.08);
}

html,body{
  font-family:'Tajawal',sans-serif;
  background:var(--bg);
  color:var(--t1);
  min-height:100%;
}
a{text-decoration:none;color:inherit}

/* ══════════════════════════════════
   SHELL
══════════════════════════════════ */
.dash{
  display:flex;flex-direction:column;
  height:calc(100vh - 4px);
  overflow:hidden;
}

/* ── TOPBAR ── */
.d-top{
  height:54px;flex-shrink:0;
  display:flex;align-items:center;justify-content:space-between;gap:14px;
  padding:0 20px;
  background:var(--s0);
  position:relative;overflow:hidden;
}
/* orange glow bottom edge */
.d-top::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent 0%,var(--or) 30%,var(--or) 70%,transparent 100%);
  opacity:.7;
}
/* subtle hex pattern */
.d-top::before{
  content:'';position:absolute;inset:0;
  background-image:radial-gradient(rgba(247,147,26,.06) 1px,transparent 1px);
  background-size:22px 22px;pointer-events:none;
}
.d-brand{display:flex;align-items:center;gap:12px;position:relative;z-index:1}
.d-logo{
  width:36px;height:36px;clip-path:var(--hex);
  background:linear-gradient(135deg,var(--or),var(--or2));
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:.9rem;flex-shrink:0;
  box-shadow:0 0 16px rgba(247,147,26,.4);
}
.d-brand-name{font-size:.82rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:var(--or)}
.d-brand-name small{display:block;font-size:.55rem;letter-spacing:.08em;color:rgba(255,255,255,.35);font-weight:600;margin-top:1px}
.d-top-mid{display:flex;align-items:center;gap:7px;position:relative;z-index:1}
.d-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 12px;border-radius:50px;
  font-size:.73rem;font-weight:700;white-space:nowrap;
}
.d-badge-role{background:rgba(247,147,26,.18);border:1px solid rgba(247,147,26,.35);color:var(--or)}
.d-badge-proj{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.25);color:#4ADE80}
.d-badge-comp{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.6)}
.d-badge i{font-size:.55rem}
.d-top-right{display:flex;align-items:center;gap:10px;position:relative;z-index:1}
.d-time{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.5);font-size:.8rem;font-weight:700}
.d-time-val{color:rgba(255,255,255,.85);font-variant-numeric:tabular-nums}
.d-live-dot{width:7px;height:7px;border-radius:50%;background:var(--ok);box-shadow:0 0 8px var(--ok);animation:livepulse 2s ease-in-out infinite;flex-shrink:0}
@keyframes livepulse{0%,100%{opacity:1}50%{opacity:.35}}
.d-btn-out{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 16px;border-radius:var(--r);
  background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.3);
  color:#FCA5A5;font-size:.75rem;font-weight:800;
  transition:all .15s;
}
.d-btn-out:hover{background:var(--err);color:#fff;border-color:var(--err)}

/* ══════════════════════════════════
   BODY — LEFT content + RIGHT rail
══════════════════════════════════ */
.d-body{
  flex:1;display:grid;
  grid-template-columns:1fr 300px;
  overflow:hidden;min-height:0;
}

/* ══════════════════════════════════
   LEFT — main content
══════════════════════════════════ */
.d-left{
  display:flex;flex-direction:column;
  overflow:hidden;background:var(--bg);
}

/* hero banner */
.d-hero{
  flex-shrink:0;
  background:var(--s0);
  padding:16px 22px;
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  border-bottom:2px solid var(--or);
  position:relative;overflow:hidden;
}
.d-hero-fog{
  position:absolute;inset:0;
  background:radial-gradient(ellipse at 0% 50%, rgba(247,147,26,.12) 0%, transparent 55%);
  pointer-events:none;
}
.d-hero-body{position:relative;z-index:1}
.d-hero-sup{
  font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:rgba(255,255,255,.35);margin-bottom:5px;
  display:flex;align-items:center;gap:7px;
}
.d-hero-sup::before{content:'';width:20px;height:2px;background:var(--or);border-radius:1px;flex-shrink:0}
.d-hero-name{
  font-size:clamp(1.25rem,2.2vw,1.9rem);
  font-weight:900;color:#fff;line-height:1.1;
}
.d-cursor{display:inline-block;width:3px;height:.85em;background:var(--or);border-radius:2px;animation:blink .75s step-end infinite;vertical-align:middle;margin-right:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.d-hero-date{margin-top:7px;font-size:.76rem;color:rgba(255,255,255,.35);display:flex;align-items:center;gap:6px}
.d-hero-date i{color:var(--or)}
/* big hex deco */
.d-hero-deco{position:relative;z-index:1;flex-shrink:0}
.d-hex-stack{display:flex;align-items:center;gap:5px}
.d-hx{clip-path:var(--hex);display:flex;align-items:center;justify-content:center;color:#fff}
.d-hx-xl{width:58px;height:58px;font-size:1.5rem;background:linear-gradient(135deg,var(--or),var(--or2));box-shadow:0 0 24px rgba(247,147,26,.45)}
.d-hx-md{width:32px;height:32px;font-size:.8rem;background:rgba(247,147,26,.25)}
.d-hx-sm{width:20px;height:20px;background:rgba(247,147,26,.12)}

/* scrollable area */
.d-scroll{flex:1;overflow-y:auto;padding:14px 18px 16px;scrollbar-width:thin;scrollbar-color:var(--bdr) transparent}
.d-scroll::-webkit-scrollbar{width:5px}
.d-scroll::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:3px}

/* section header */
.d-sec{
  display:flex;align-items:center;gap:8px;margin-bottom:10px;
  font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--t3);
}
.d-sec i{color:var(--or);font-size:.72rem}
.d-sec::after{content:'';flex:1;height:1px;background:var(--bdr)}

/* link tiles — 4-col */
.d-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px}
.d-tile{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:6px;padding:14px 8px;min-height:76px;
  background:var(--s1);border:1.5px solid var(--bdr);border-radius:var(--rl);
  font-size:.78rem;font-weight:700;color:var(--t2);text-align:center;
  box-shadow:var(--sh);transition:all .15s ease;
  animation:tileUp .32s cubic-bezier(.4,0,.2,1) both;
}
@keyframes tileUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.d-tile-ico{
  width:38px;height:38px;clip-path:var(--hex);
  background:var(--orb);color:var(--or);
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;transition:all .15s;flex-shrink:0;
}
.d-tile:hover{
  border-color:var(--or);transform:translateY(-3px);
  box-shadow:var(--sh2),0 0 0 3px rgba(247,147,26,.08);
  color:var(--t1);
}
.d-tile:hover .d-tile-ico{background:var(--or);color:#fff}

/* info row */
.d-info-row{display:flex;flex-wrap:wrap;gap:7px}
.d-itag{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 14px;border-radius:var(--r);
  background:var(--s1);border:1px solid var(--bdr);
  font-size:.8rem;font-weight:700;color:var(--t2);
  box-shadow:var(--sh);
}
.d-itag i{font-size:.72rem;color:var(--or)}
.d-itag.green{border-color:rgba(22,163,74,.25);color:var(--ok)}
.d-itag.green i{color:var(--ok)}

/* breakdown */
.d-bd-wrap{margin-top:2px}
.d-bd{width:100%;border-collapse:collapse}
.d-bd thead th{
  padding:8px 12px;text-align:right;
  font-size:.72rem;font-weight:800;letter-spacing:.05em;
  color:var(--t3);border-bottom:2px solid var(--bdr);
  background:var(--s2);
}
.d-bd tbody td{
  padding:9px 12px;text-align:right;
  font-size:.85rem;color:var(--t2);
  border-bottom:1px solid var(--bdr2);
  background:var(--s1);
}
.d-bd tbody tr:last-child td{border-bottom:none}
.d-bd tbody tr:hover td{background:var(--s3);color:var(--t1)}
.d-bd strong{color:var(--or3);font-weight:800}
.d-bd-empty{font-size:.8rem;color:var(--t3);font-weight:600;display:flex;align-items:center;gap:7px;padding:10px 0}
.d-bd-empty i{color:var(--or)}

/* status bar (non role-6) */
.d-sbar{
  display:flex;align-items:center;flex-wrap:wrap;
  gap:0;padding:10px 0 0;border-top:1px solid var(--bdr);
  margin-top:14px;
}
.d-si{display:flex;align-items:center;gap:6px;padding:3px 14px;font-size:.8rem;font-weight:700;color:var(--t2);border-left:1px solid var(--bdr)}
.d-si:first-child{border-left:none;padding-right:0}
.d-si i{font-size:.7rem;color:var(--or)}
.d-si.gr{color:var(--ok)}.d-si.gr i{color:var(--ok)}

/* ══════════════════════════════════
   RIGHT — stat rail
══════════════════════════════════ */
.d-rail{
  background:var(--s2);
  border-right:1px solid var(--bdr);
  display:flex;flex-direction:column;
  overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:var(--bdr) transparent;
}
.d-rail::-webkit-scrollbar{width:4px}
.d-rail::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:2px}

.d-rail-hd{
  padding:12px 16px 10px;border-bottom:1px solid var(--bdr);flex-shrink:0;
  display:flex;align-items:center;justify-content:space-between;
}
.d-rail-title{
  font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--t3);
  display:flex;align-items:center;gap:7px;
}
.d-rail-title::before{content:'';width:3px;height:13px;border-radius:2px;background:linear-gradient(180deg,var(--or),transparent);flex-shrink:0}
.d-rail-live{display:flex;align-items:center;gap:5px;font-size:.65rem;font-weight:700;color:var(--ok)}
.d-rl-dot{width:6px;height:6px;border-radius:50%;background:var(--ok);animation:livepulse 2s ease-in-out infinite}

/* ctx */
.d-ctx{
  margin:8px 14px 0;padding:6px 11px;border-radius:var(--r);
  background:var(--orb);border:1px solid var(--ord);
  font-size:.74rem;font-weight:700;color:var(--or3);
  display:flex;align-items:center;gap:6px;flex-shrink:0;
}
.d-ctx i{color:var(--or);flex-shrink:0}

/* stat card */
.d-stat{
  margin:8px 12px 0;
  background:var(--s1);border-radius:var(--rl);border:1px solid var(--bdr);
  box-shadow:var(--sh);overflow:hidden;
  transition:all .15s;cursor:default;
  animation:cardIn .32s cubic-bezier(.4,0,.2,1) both;
  flex-shrink:0;
}
@keyframes cardIn{from{opacity:0;transform:translateX(6px)}to{opacity:1;transform:translateX(0)}}
.d-stat:last-of-type{margin-bottom:12px}
.d-stat:hover{box-shadow:var(--sh2);transform:translateX(-2px)}
/* top accent bar */
.d-stat-bar{height:3px;width:100%}
.d-stat-body{padding:10px 14px 12px;display:flex;align-items:flex-start;gap:10px}
.d-stat-ico{
  width:42px;height:42px;clip-path:var(--hex);
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;margin-top:2px;
}
.d-stat-info{flex:1;min-width:0}
.d-stat-num{
  font-size:2rem;font-weight:900;line-height:1;
  font-variant-numeric:tabular-nums;
}
.d-stat-lbl{font-size:.8rem;font-weight:600;color:var(--t3);margin-top:4px;line-height:1.3}
/* progress strip */
.d-stat-track{height:3px;border-radius:2px;background:var(--bdr2);overflow:hidden;margin-top:8px}
.d-stat-fill{height:100%;border-radius:2px;width:0;transition:width 1.1s cubic-bezier(.4,0,.2,1)}

/* ── RESPONSIVE ── */
@media(max-width:960px){
  .d-body{grid-template-columns:1fr;overflow:visible}
  .dash{height:auto;overflow:visible}
  .d-left{overflow:visible}
  .d-scroll{overflow:visible}
  .d-rail{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
    overflow:visible;border-right:none;border-top:1px solid var(--bdr);
    padding-bottom:12px;
  }
  .d-rail-hd,.d-ctx{grid-column:1/-1}
  .d-stat{margin-top:0}
  .d-tiles{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:560px){
  .d-tiles{grid-template-columns:1fr 1fr}
  .d-top-mid .d-badge:not(.d-badge-role){display:none}
  .d-hero-deco{display:none}
}
</style>

<div class="dash main">

  <!-- ══ TOPBAR ══ -->
  <div class="d-top">
    <div class="d-brand">
      <div class="d-logo"><i class="fas fa-layer-group"></i></div>
      <div class="d-brand-name">
        EQUIPATION
        <small>نظام إدارة المعدات والعمليات</small>
      </div>
    </div>

    <div class="d-top-mid">
      <span class="d-badge d-badge-role"><i class="fas fa-id-badge"></i><?= htmlspecialchars($roleText) ?></span>
      <?php if($projectName): ?>
      <span class="d-badge d-badge-proj"><i class="fas fa-project-diagram"></i><?= htmlspecialchars($projectName) ?></span>
      <?php endif; ?>
      <?php if($companyName!==''): ?>
      <span class="d-badge d-badge-comp"><i class="fas fa-building"></i><?= htmlspecialchars($companyName) ?></span>
      <?php endif; ?>
    </div>

    <div class="d-top-right">
      <div class="d-time">
        <span class="d-live-dot"></span>
        <span class="d-time-val" id="d-clk">--:--</span>
      </div>
      <a href="../logout.php" class="d-btn-out"><i class="fas fa-power-off"></i> خروج</a>
    </div>
  </div>

  <!-- ══ BODY ══ -->
  <div class="d-body">

    <!-- ── LEFT CONTENT ── -->
    <div class="d-left">

      <!-- hero -->
      <div class="d-hero">
        <div class="d-hero-fog"></div>
        <div class="d-hero-body">
          <div class="d-hero-sup">مرحباً بك في إيكوبيشن</div>
          <div class="d-hero-name"><span id="d-typed"></span><span class="d-cursor"></span></div>
          <div class="d-hero-date"><i class="fas fa-calendar-alt"></i><?= date('l، j F Y') ?></div>
        </div>
        <div class="d-hero-deco">
          <div class="d-hex-stack">
            <div class="d-hx d-hx-sm"></div>
            <div style="display:flex;flex-direction:column;gap:5px">
              <div class="d-hx d-hx-md"><i class="fas fa-mountain"></i></div>
              <div class="d-hx d-hx-md"><i class="fas fa-cog"></i></div>
            </div>
            <div class="d-hx d-hx-xl"><i class="fas fa-hard-hat"></i></div>
          </div>
        </div>
      </div>

      <!-- scrollable content -->
      <div class="d-scroll">

        <!-- quick links -->
        <?php if(!empty($links)): ?>
        <div class="d-sec"><i class="fas fa-bolt"></i>الوصول السريع</div>
        <div class="d-tiles">
          <?php
          foreach($links as $i=>$lnk):
            $href=$lnk[0]??'#'; $lbl=$lnk[1]??'رابط';
            $ico=$lnk[2]??'fa fa-link';
          ?>
          <a href="<?= htmlspecialchars($href) ?>" class="d-tile" style="animation-delay:<?= $i*.035 ?>s">
            <div class="d-tile-ico"><i class="<?= htmlspecialchars($ico) ?>"></i></div>
            <span><?= htmlspecialchars($lbl) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- session info -->
        <div class="d-sec"><i class="fas fa-info-circle"></i>بيانات الجلسة</div>
        <div class="d-info-row">
          <span class="d-itag"><i class="fas fa-user"></i><?= htmlspecialchars($userName) ?></span>
          <span class="d-itag"><i class="fas fa-shield-alt"></i><?= htmlspecialchars($roleText) ?></span>
          <?php if($companyName!==''): ?>
          <span class="d-itag"><i class="fas fa-building"></i><?= htmlspecialchars($companyName) ?></span>
          <?php endif; ?>
          <?php if($projectName): ?>
          <span class="d-itag green"><i class="fas fa-project-diagram"></i><?= htmlspecialchars($projectName) ?></span>
          <?php endif; ?>
          <span class="d-itag"><i class="fas fa-calendar"></i><?= date('j F Y') ?></span>
        </div>

        <!-- breakdown or status bar -->
        <?php if($role=='6'): ?>
        <div class="d-bd-wrap">
          <div class="d-sec" style="margin-top:14px"><i class="fas fa-truck-loading"></i>الموردون التابعون للعقد</div>
          <?php if(!empty($role6SupplierBreakdown)): ?>
          <div style="border-radius:var(--rl);overflow:hidden;border:1px solid var(--bdr);box-shadow:var(--sh)">
            <table class="d-bd">
              <thead><tr><th>المورد</th><th>عدد الآليات</th></tr></thead>
              <tbody>
                <?php foreach($role6SupplierBreakdown as $sr): ?>
                <tr>
                  <td><?= htmlspecialchars($sr['supplier_name']) ?></td>
                  <td><strong><?= number_format(intval($sr['equipments_count'])) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="d-bd-empty"><i class="fas fa-info-circle"></i>لا توجد بيانات موردين لهذا النطاق.</div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="d-sbar">
          <span class="d-si"><i class="fas fa-user"></i><?= htmlspecialchars($userName) ?></span>
          <span class="d-si"><i class="fas fa-shield-alt"></i><?= htmlspecialchars($roleText) ?></span>
          <?php if($companyName!==''): ?><span class="d-si"><i class="fas fa-building"></i><?= htmlspecialchars($companyName) ?></span><?php endif; ?>
          <?php if($projectName): ?><span class="d-si gr"><i class="fas fa-project-diagram"></i><?= htmlspecialchars($projectName) ?></span><?php endif; ?>
          <span class="d-si"><i class="fas fa-calendar-alt"></i><?= date('l، j F Y') ?></span>
        </div>
        <?php endif; ?>

      </div><!-- .d-scroll -->
    </div><!-- .d-left -->

    <!-- ── RIGHT STAT RAIL ── -->
    <div class="d-rail">
      <div class="d-rail-hd">
        <div class="d-rail-title">الإحصائيات</div>
        <div class="d-rail-live"><span class="d-rl-dot"></span>مباشر</div>
      </div>

      <?php if($role=="6"&&$role6ContextText!==''): ?>
      <div class="d-ctx"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($role6ContextText) ?></div>
      <?php endif; ?>

      <?php if(!empty($stats)):
        $maxVal = max(array_map(fn($s)=>(int)$s[1],$stats)) ?: 1;
        foreach($stats as $idx=>$st):
          $ico=$st[0]; $val=(int)$st[1]; $lbl=$st[2]; $acc=$st[3]??'or';
          $a=$AC[$acc]??$AC['or'];
          $barPct=min(100,round($val/$maxVal*100));
      ?>
      <div class="d-stat" style="animation-delay:<?= $idx*45 ?>ms">
        <div class="d-stat-bar" style="background:<?= $a['bg'] ?>"></div>
        <div class="d-stat-body">
          <div class="d-stat-ico" style="background:<?= $a['soft'] ?>;color:<?= $a['ico'] ?>">
            <i class="fas <?= $ico ?>"></i>
          </div>
          <div class="d-stat-info">
            <div class="d-stat-num" style="color:<?= $a['text'] ?>" data-to="<?= $val ?>">0</div>
            <div class="d-stat-lbl"><?= htmlspecialchars($lbl) ?></div>
            <div class="d-stat-track">
              <div class="d-stat-fill" style="background:<?= $a['bg'] ?>" data-w="<?= $barPct ?>"></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach;
      else: ?>
      <div style="padding:18px 16px;font-size:.82rem;color:var(--t3)">لا توجد إحصائيات.</div>
      <?php endif; ?>

    </div><!-- .d-rail -->
  </div><!-- .d-body -->
</div><!-- .dash -->

<script>
document.addEventListener('DOMContentLoaded', function() {

  /* Clock */
  (function tick(){
    var e = document.getElementById('d-clk'), n = new Date();
    if(e) e.textContent = String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');
    setTimeout(tick, 15000);
  })();

  /* Typewriter */
  var tEl  = document.getElementById('d-typed');
  var tName = <?= json_encode($userName) ?>;
  if(tEl && tName) {
    var ci = 0;
    (function type(){
      tEl.textContent = tName.slice(0, ci++);
      if(ci <= tName.length) setTimeout(type, 65);
    })();
  }

  /* Count-up — handles 0 correctly, no arrow functions */
  document.querySelectorAll('.d-stat-num[data-to]').forEach(function(el) {
    var target = parseInt(el.dataset.to, 10);
    if(isNaN(target)) return;
    if(target === 0) { el.textContent = '0'; return; }
    var steps = 30, dur = 800, i = 0;
    var iv = setInterval(function() {
      i++;
      el.textContent = Math.round(target * Math.min(i / steps, 1)).toLocaleString('ar-EG');
      if(i >= steps) { el.textContent = target.toLocaleString('ar-EG'); clearInterval(iv); }
    }, dur / steps);
  });

  /* Bar fill after slight delay so CSS transition fires */
  setTimeout(function() {
    document.querySelectorAll('.d-stat-fill[data-w]').forEach(function(el) {
      el.style.width = el.dataset.w + '%';
    });
  }, 400);

});
</script>
</body>
</html>