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