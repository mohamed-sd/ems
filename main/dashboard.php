<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
include "../config.php";
require_once dirname(__FILE__) . '/../includes/dynamic_nav.php';
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

/* ════════════════  DATA LAYER  ════════════════ */
$role = $_SESSION['user']['role'];
$userName = $_SESSION['user']['name'];
$roleText = "غير معروف";
$companyId = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$companyName = '';

if (!function_exists('dashboard_has_column')) {
  function dashboard_has_column($conn, $t, $c)
  {
    static $hasColumnCache = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $c);
    $cacheKey = $t . ':' . $c;
    if (array_key_exists($cacheKey, $hasColumnCache)) {
      return $hasColumnCache[$cacheKey];
    }
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM $t LIKE '" . mysqli_real_escape_string($conn, $c) . "'");
    $hasColumnCache[$cacheKey] = ($r && mysqli_num_rows($r) > 0);
    return $hasColumnCache[$cacheKey];
  }
}
if (!function_exists('dashboard_scalar')) {
  function dashboard_scalar($conn, $sql, $key)
  {
    $r = $conn->query($sql);
    if (!$r)
      return 0;
    $row = $r->fetch_assoc();
    return ($row && isset($row[$key])) ? $row[$key] : 0;
  }
}
if (!function_exists('dashboard_table_exists')) {
  function dashboard_table_exists($conn, $t)
  {
    static $tableCache = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    if (isset($tableCache[$t])) {
      return $tableCache[$t];
    }
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    $tableCache[$t] = ($r && mysqli_num_rows($r) > 0);
    return $tableCache[$t];
  }
}

$projectClientColumn = dashboard_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if ($companyId > 0) {
  $cols = [];
  foreach (['company_name_ar', 'company_name', 'name'] as $c)
    if (dashboard_has_column($conn, 'admin_companies', $c))
      $cols[] = $c;
  if ($cols) {
    $r = @mysqli_query($conn, "SELECT " . implode(',', $cols) . " FROM admin_companies WHERE id=$companyId LIMIT 1");
    if ($r) {
      $row = mysqli_fetch_assoc($r);
      foreach ($cols as $c)
        if (isset($row[$c]) && trim($row[$c]) !== '') {
          $companyName = trim($row[$c]);
          break;
        }
    }
  }
}

$roleId = intval($role);
$s = $conn->prepare("SELECT name FROM roles WHERE id=? LIMIT 1");
if ($s) {
  $s->bind_param("i", $roleId);
  $s->execute();
  if ($r = $s->get_result()) if ($rw = $r->fetch_assoc())
    $roleText = $rw['name'];
  $s->close();
}

$dashboardRole = strval($role);
$s2 = $conn->prepare("SELECT parent_role_id FROM roles WHERE id=? LIMIT 1");
if ($s2) {
  $s2->bind_param("i", $roleId);
  $s2->execute();
  if ($r = $s2->get_result()) if ($rw = $r->fetch_assoc()) {
    $pid = intval($rw['parent_role_id'] ?? 0);
    if ($pid > 0)
      $dashboardRole = strval($pid);
  }
  $s2->close();
}

$projectId = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$projectName = '';
if ($projectId > 0) {
  $psc = $companyId > 0
    ? " AND (EXISTS(SELECT 1 FROM users su WHERE su.id=project.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=project.$projectClientColumn AND scu.company_id=$companyId))"
    : "";
  $pq = $conn->query("SELECT name FROM project WHERE id=$projectId $psc LIMIT 1");
  if ($pq && $prow = $pq->fetch_assoc())
    $projectName = $prow['name'];
}

$dynamicLinks = getDynamicNavLinks($conn, $role);
$links = [];
foreach ($dynamicLinks as $l) {
  $links[] = [
    '../' . $l['code'],
    $l['name'],
    !empty($l['icon']) ? $l['icon'] : 'fa fa-link'
  ];
}

$sc = $companyId > 0 ? "EXISTS(SELECT 1 FROM users su WHERE su.id=clients.created_by AND su.company_id=$companyId)" : "1=1";
$sp = $companyId > 0 ? "(EXISTS(SELECT 1 FROM users su WHERE su.id=project.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=project.$projectClientColumn AND scu.company_id=$companyId))" : "1=1";
$so = $companyId > 0 ? "operations.project_id IN(SELECT p.id FROM project p WHERE EXISTS(SELECT 1 FROM users su WHERE su.id=p.created_by AND su.company_id=$companyId) OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=p.$projectClientColumn AND scu.company_id=$companyId))" : "1=1";

$hasMineId = dashboard_has_column($conn, 'operations', 'mine_id');
$hasSuppId = dashboard_has_column($conn, 'operations', 'supplier_id');
$hasAvail = dashboard_has_column($conn, 'equipments', 'availability_status');
$hasDrvSt = dashboard_has_column($conn, 'drivers', 'driver_status');
$hasSCMine = dashboard_has_column($conn, 'supplierscontracts', 'mine_id');
$hasSCPCId = dashboard_has_column($conn, 'supplierscontracts', 'project_contract_id');

$sessionMineId = isset($_SESSION['user']['mine_id']) ? intval($_SESSION['user']['mine_id']) : 0;
$sessionContractId = isset($_SESSION['user']['contract_id']) ? intval($_SESSION['user']['contract_id']) : 0;

$stats = [];
$role6SupplierBreakdown = [];
$role6ContextText = '';
$opsProjectCol = dashboard_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

if ($dashboardRole == "0" || $dashboardRole == "1") {
  $c = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND $sc", 't');
  $p = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM project WHERE status='1' AND $sp", 't');
  $m = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM mines WHERE status='1' AND project_id IN(SELECT id FROM project WHERE $sp)", 't');
  $u = $companyId > 0 ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id=$companyId AND role!='-1'", 't') : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'", 't');
  $stats = [['fa-users', $c, 'العملاء', 'or'], ['fa-project-diagram', $p, 'المشاريع', 'or'], ['fa-mountain', $m, 'المناجم', 'or'], ['fa-user-shield', $u, 'المستخدمون', 'or']];
} elseif ($dashboardRole == "2") {
  $s = dashboard_scalar($conn, "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id=$companyId", 't');
  $e = dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id=$companyId", 't');
  $co = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM supplierscontracts WHERE project_id IN(SELECT id FROM project WHERE $sp)", 't');
  $stats = [['fa-truck', $s, 'الموردون', 'or'], ['fa-tools', $e, 'الآليات', 'or'], ['fa-file-contract', $co, 'العقود', 'ok']];
} elseif ($dashboardRole == "3") {
  $s = dashboard_scalar($conn, "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id=$companyId", 't');
  $eq = dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id=$companyId", 't');
  $stopListRole3 = "'معطلة','معطلة مؤقتاً','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة','مبيعة/مسحوبة'";

  $ao = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM equipments e
     WHERE e.company_id=$companyId" .
    ($hasAvail
      ? " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopListRole3))"
      : " AND (e.status='1' OR e.status=1)"),
    't'
  );

  $bo = max(0, intval($eq) - intval($ao));
  $stats = [['fa-tools', $eq, 'إجمالي المعدات', 'or'], ['fa-play-circle', $ao, 'تعمل الآن', 'ok'], ['fa-exclamation-triangle', $bo, 'معطلة', 'err'], ['fa-truck', $s, 'الموردون', 'or']];
} elseif ($dashboardRole == "4") {
  $dr = dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d WHERE company_id=$companyId", 't');
  $ad = dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON d.id=ed.driver_id WHERE ed.status='1' AND d.company_id=$companyId", 't');
  $stats = [['fa-id-badge', $dr, 'إجمالي المشغلين', 'or'], ['fa-user-check', $ad, 'يعملون الآن', 'ok'], ['fa-user-clock', $dr - $ad, 'خاملون', 'warn']];
} elseif ($dashboardRole == "5") {
  $sv = $companyId > 0 ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id=$companyId AND role IN('6','7','8','9')", 't') : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE role IN('6','7','8','9')", 't');
  $h = dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE o.company_id=$companyId", 't');
  $ah = dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN timesheet_approvals ta ON t.id=ta.timesheet_id AND approval_level='4' WHERE t.company_id=$companyId", 't');
  $stats = [['fa-users-cog', $sv, 'المشرفون', 'or'], ['fa-clock', (int) $h, 'ساعات العمل', 'or'], ['fa-check-circle', (int) $ah, 'الساعات المعتمدة', 'ok']];
} elseif ($dashboardRole == "6") {
  $pSql = $projectId > 0 ? "o.project_id='$projectId'" : "1=0";
  $mSql = ($sessionMineId > 0 && $hasMineId) ? " AND o.mine_id='$sessionMineId'" : "";

  $totEq = dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM `equipments` WHERE id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = '$projectId' );" , 't');
  $wrkEq = dashboard_scalar($conn,"SELECT COUNT(*) AS t FROM `equipments` WHERE id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = '$projectId' AND operations.status='1');" , 't');

  $stpEq = max(0, intval($totEq) - intval($wrkEq));

  $dCond = $hasDrvSt ? " AND(d.driver_status IS NULL OR d.driver_status NOT IN('موقوف','متوقف'))" : "";
  $totOp = dashboard_scalar($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN drivers d ON d.id=ed.driver_id WHERE $pSql$mSql", 't');
  $wrkOp = dashboard_scalar($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN drivers d ON d.id=ed.driver_id WHERE $pSql$mSql AND ed.status='1' AND d.status='1'$dCond", 't');
  $stpOp = max(0, intval($totOp) - intval($wrkOp));
  $scMine = ($sessionMineId > 0 && $hasSCMine) ? " AND sc.mine_id=$sessionMineId" : "";
  $scCid = ($sessionContractId > 0 && $hasSCPCId) ? " AND sc.project_contract_id=$sessionContractId" : "";
  $supCnt = dashboard_scalar($conn, "SELECT COUNT(DISTINCT sc.supplier_id) AS t FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scMine$scCid", 't');
  if ($projectId > 0 && $hasSuppId) {
    $subq = "SELECT DISTINCT sc.supplier_id FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scMine$scCid";
    $br = $conn->query("SELECT o.supplier_id,COALESCE(s.name,CONCAT('مورد #',o.supplier_id)) AS supplier_name,COUNT(DISTINCT o.equipment) AS equipments_count FROM operations o LEFT JOIN suppliers s ON s.id=o.supplier_id WHERE $pSql$mSql AND o.supplier_id IS NOT NULL AND o.supplier_id<>'' AND o.supplier_id<>'0' AND o.supplier_id IN($subq) GROUP BY o.supplier_id,supplier_name ORDER BY equipments_count DESC,supplier_name ASC");
    if ($br)
      while ($row = $br->fetch_assoc())
        $role6SupplierBreakdown[] = ['supplier_name' => $row['supplier_name'], 'equipments_count' => intval($row['equipments_count'])];
  }
  $stats = [
    ['fa-tools', intval($totEq), 'إجمالي الآليات', 'or'],
    ['fa-play-circle', intval($wrkEq), 'تعمل الآن', 'ok'],
    ['fa-wrench', intval($stpEq), 'صيانة / متوقفة', 'err'],
    ['fa-id-badge', intval($totOp), 'إجمالي المشغلين', 'or'],
    ['fa-user-check', intval($wrkOp), 'يعملون الآن', 'ok'],
    ['fa-user-times', intval($stpOp), 'متوقفون', 'warn'],
    ['fa-truck', intval($supCnt), 'موردو العقد', 'or'],
  ];
} elseif ($dashboardRole == "10") {
  $projectEqScope = $projectId > 0 ? "o.project_id = $projectId" : "1=0";
  $stopListRole10 = "'معطلة','معطلة مؤقتاً','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة','مبيعة/مسحوبة'";

  $eq = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id = o.equipment
     WHERE $projectEqScope
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'",
    't'
  );

  $ao = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id = o.equipment
     WHERE $projectEqScope
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'" .
    ($hasAvail
      ? " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopListRole10))"
      : " AND (e.status='1' OR e.status=1)"),
    't'
  );

  $bo = max(0, intval($eq) - intval($ao));
  $stats = [
    ['fa-tools', $eq, 'إجمالي المعدات', 'or'],
    ['fa-play-circle', $ao, 'تعمل الآن', 'ok'],
    ['fa-exclamation-triangle', $bo, 'معطلة', 'err']
  ];
}

/* ════════════════  DASHBOARD ANALYTICS  ════════════════ */
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$opsScope = "1=1";
if ($projectId > 0) {
  $opsScope = "o.$opsProjectCol = " . intval($projectId);
} elseif ($companyId > 0 && dashboard_has_column($conn, 'operations', 'company_id')) {
  $opsScope = "o.company_id = " . intval($companyId);
} elseif ($companyId > 0) {
  $opsScope = "o.$opsProjectCol IN(
      SELECT p.id FROM project p
      WHERE EXISTS(SELECT 1 FROM users su WHERE su.id=p.created_by AND su.company_id=$companyId)
         OR EXISTS(SELECT 1 FROM clients sc INNER JOIN users scu ON scu.id=sc.created_by WHERE sc.id=p.$projectClientColumn AND scu.company_id=$companyId)
    )";
}

$stopStatuses = "'معطلة','معطلة مؤقتاً','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة','مبيعة/مسحوبة'";
$analyticsTotalEquip = 0;
$analyticsActiveEquip = 0;

if ($projectId > 0) {
  $analyticsTotalEquip = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id=o.equipment
     WHERE $opsScope
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'",
    't'
  );

  $analyticsActiveEquip = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id=o.equipment
     WHERE $opsScope
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'" .
    ($hasAvail
      ? " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopStatuses))"
      : " AND (e.status='1' OR e.status=1)"),
    't'
  );
} else {
  $eqScope = "1=1";
  if ($companyId > 0 && dashboard_has_column($conn, 'equipments', 'company_id')) {
    $eqScope = "e.company_id=" . intval($companyId);
  }
  $analyticsTotalEquip = dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE $eqScope", 't');
  $analyticsActiveEquip = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE $eqScope" .
    ($hasAvail
      ? " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopStatuses))"
      : " AND (e.status='1' OR e.status=1)"),
    't'
  );
}

$analyticsInactiveEquip = max(0, intval($analyticsTotalEquip) - intval($analyticsActiveEquip));

$analyticsMonthWorkHours = dashboard_scalar(
  $conn,
  "SELECT COALESCE(SUM(t.total_work_hours),0) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'",
  't'
);

$analyticsMonthBreakdownHours = dashboard_scalar(
  $conn,
  "SELECT COALESCE(SUM(t.total_fault_hours),0) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'",
  't'
);

$analyticsBreakdownCount = dashboard_scalar(
  $conn,
  "SELECT COUNT(*) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'
     AND IFNULL(t.total_fault_hours,0) > 0",
  't'
);

$analyticsPendingRequests = 0;
if (dashboard_table_exists($conn, 'approval_requests')) {
  $pendingScope = "ar.status='pending'";
  if ($projectId > 0 && dashboard_has_column($conn, 'approval_requests', 'project_id')) {
    $pendingScope .= " AND ar.project_id=" . intval($projectId);
  }
  if ($companyId > 0 && dashboard_has_column($conn, 'approval_requests', 'requested_by') && dashboard_has_column($conn, 'users', 'company_id')) {
    $pendingScope .= " AND EXISTS(SELECT 1 FROM users ux WHERE ux.id=ar.requested_by AND ux.company_id=" . intval($companyId) . ")";
  }
  $analyticsPendingRequests = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM approval_requests ar WHERE $pendingScope", 't');
}

$analyticsTrendLabels = [];
$analyticsTrendWork = [];
$analyticsTrendFault = [];
$trendMap = [];

$trendRes = $conn->query(
  "SELECT DATE_FORMAT(STR_TO_DATE(t.date, '%Y-%m-%d'), '%Y-%m-%d') AS day_key,
          COALESCE(SUM(t.total_work_hours),0) AS work_sum,
          COALESCE(SUM(t.total_fault_hours),0) AS fault_sum
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'
   GROUP BY day_key
   ORDER BY day_key ASC"
);

if ($trendRes) {
  while ($tr = $trendRes->fetch_assoc()) {
    $trendMap[$tr['day_key']] = [
      'work' => (float) $tr['work_sum'],
      'fault' => (float) $tr['fault_sum']
    ];
  }
}

$cursorTs = strtotime($monthStart);
$endTs = strtotime($monthEnd);
while ($cursorTs <= $endTs) {
  $dayKey = date('Y-m-d', $cursorTs);
  $analyticsTrendLabels[] = date('d/m', $cursorTs);
  $analyticsTrendWork[] = isset($trendMap[$dayKey]) ? $trendMap[$dayKey]['work'] : 0;
  $analyticsTrendFault[] = isset($trendMap[$dayKey]) ? $trendMap[$dayKey]['fault'] : 0;
  $cursorTs = strtotime('+1 day', $cursorTs);
}

$analyticsSummaryCards = [
  ['id' => 'kpiActiveEq', 'icon' => 'fa-play-circle', 'label' => 'التشغيلات النشطة', 'value' => intval($analyticsActiveEquip), 'accent' => 'ok'],
  ['id' => 'kpiMonthHours', 'icon' => 'fa-clock', 'label' => 'ساعات هذا الشهر', 'value' => round((float) $analyticsMonthWorkHours, 2), 'accent' => 'or'],
  ['id' => 'kpiPending', 'icon' => 'fa-hourglass-half', 'label' => 'الطلبات المعلقة', 'value' => intval($analyticsPendingRequests), 'accent' => 'warn'],
  ['id' => 'kpiBreakdowns', 'icon' => 'fa-triangle-exclamation', 'label' => 'التعطلات هذا الشهر', 'value' => intval($analyticsBreakdownCount), 'accent' => 'err'],
];

$analyticsPayload = [
  'equipmentStatus' => [intval($analyticsActiveEquip), intval($analyticsInactiveEquip)],
  'kpis' => [intval($analyticsActiveEquip), round((float) $analyticsMonthWorkHours, 2), intval($analyticsPendingRequests), intval($analyticsBreakdownCount)],
  'trendLabels' => $analyticsTrendLabels,
  'trendWork' => $analyticsTrendWork,
  'trendFault' => $analyticsTrendFault,
  'monthBreakdownHours' => round((float) $analyticsMonthBreakdownHours, 2),
  'monthName' => date('m/Y'),
  'role' => strval($dashboardRole)
];

$AC = [
  'or' => ['bg' => '#F7931A', 'soft' => '#FFF4E6', 'text' => '#B45309', 'ico' => '#F7931A'],
  'ok' => ['bg' => '#16A34A', 'soft' => '#F0FDF4', 'text' => '#15803D', 'ico' => '#16A34A'],
  'warn' => ['bg' => '#D97706', 'soft' => '#FFFBEB', 'text' => '#B45309', 'ico' => '#D97706'],
  'err' => ['bg' => '#DC2626', 'soft' => '#FEF2F2', 'text' => '#B91C1C', 'ico' => '#DC2626'],
];

$page_title = 'Equipation | الرئيسية';
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* ═══════════════════════════════════════
   EMS Dashboard v2 — Inline Critical CSS
═══════════════════════════════════════ */
.ems-dash.main {
  background: #0b0f16 !important;
  color: #f0ece4 !important;
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  position: relative;
  overflow-x: hidden;
  font-family: 'Tajawal','Cairo',sans-serif;
}
/* atmospheric grid */
.ems-dash::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(247,147,26,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(247,147,26,.025) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
  z-index: 0;
}
/* ambient gold orb */
.ems-dash::after {
  content: '';
  position: fixed;
  top: -20vh; right: -10vw;
  width: 60vw; height: 60vw;
  background: radial-gradient(ellipse at center, rgba(247,147,26,.07) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
  animation: emsOrbFloat 12s ease-in-out infinite;
}
@keyframes emsOrbFloat {
  0%,100% { transform: translate(0,0) scale(1); }
  33%      { transform: translate(-3vw,4vh) scale(1.05); }
  66%      { transform: translate(2vw,-3vh) scale(.97); }
}
.ems-dash > * { position: relative; z-index: 1; }

/* ── Topbar ── */
.ems-nav {
  display: flex;
  align-items: center;
  gap: .9rem;
  padding: 0 1.5rem;
  height: 60px;
  background: rgba(6,9,16,.94);
  border-bottom: 1px solid rgba(247,147,26,.15);
  backdrop-filter: blur(12px);
  position: sticky;
  top: 0;
  z-index: 100;
  flex-shrink: 0;
}
.ems-nav-logo { display:flex; align-items:center; gap:.7rem; text-decoration:none; color:#f0ece4; }
.ems-nav-hex {
  width:38px; height:38px;
  background: linear-gradient(135deg,#f7931a,#ffb84d);
  clip-path: polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:.9rem;
}
.ems-nav-brand { font-size:1.1rem; font-weight:700; letter-spacing:.04em; color:#f0ece4; }
.ems-nav-brand span { color:#f7931a; }
.ems-nav-spacer { flex:1; }
.ems-nav-clock { font-size:.8rem; color:rgba(240,236,228,.40); font-variant-numeric:tabular-nums; }
.ems-nav-badge {
  display:inline-flex; align-items:center; gap:.35rem;
  padding:.25rem .65rem;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:20px;
  font-size:.75rem;
  color:rgba(240,236,228,.65);
}
.ems-nav-avatar {
  width:34px; height:34px;
  background:linear-gradient(135deg,rgba(247,147,26,.3),rgba(247,147,26,.1));
  border:1px solid rgba(247,147,26,.15);
  border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.85rem; color:#f7931a; flex-shrink:0;
}
.ems-nav-logout {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.3rem .75rem;
  background:rgba(239,68,68,.1);
  border:1px solid rgba(239,68,68,.3);
  border-radius:6px;
  color:#fca5a5; font-size:.78rem; text-decoration:none;
  transition:background .2s;
  white-space:nowrap;
}
.ems-nav-logout:hover { background:rgba(239,68,68,.2); color:#fca5a5; }

/* ── Hero ── */
.ems-hero {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:2rem;
  padding:2rem 2rem 1.5rem;
  background:linear-gradient(135deg,rgba(247,147,26,.05) 0%,transparent 60%);
  border-bottom:1px solid rgba(255,255,255,.08);
  flex-shrink:0;
}
.ems-hero-left { display:flex; flex-direction:column; justify-content:center; gap:.75rem; }
.ems-hero-tag {
  display:inline-flex; align-items:center; gap:.5rem;
  padding:.3rem .8rem;
  background:rgba(247,147,26,.1);
  border:1px solid rgba(247,147,26,.25);
  border-radius:20px; font-size:.75rem; color:#f7931a; width:fit-content;
}
.ems-hero-welcome { font-size:1.85rem; font-weight:800; line-height:1.25; color:#f0ece4; margin:0; }
.ems-hero-welcome em { color:#f7931a; font-style:normal; }
.ems-hero-sub { font-size:.88rem; color:rgba(240,236,228,.65); }
.ems-hero-meta { display:flex; flex-wrap:wrap; gap:.55rem; margin-top:.4rem; }
.ems-hero-chip {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.25rem .7rem;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:20px; font-size:.77rem; color:rgba(240,236,228,.35);
}
.ems-hero-chip i { color:#f7931a; font-size:.7rem; }

/* ── KPI Grid ── */
.ems-kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:.85rem; }
.ems-kpi-card {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:1rem 1.1rem;
  display:flex; flex-direction:column; gap:.5rem;
  transition:all .25s; position:relative; overflow:hidden;
  cursor:default;
}
.ems-kpi-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background:var(--kpiAccent,#f7931a); opacity:.7;
}
.ems-kpi-card:hover { background:rgba(255,255,255,.07); transform:translateY(-2px); box-shadow:0 4px 24px rgba(0,0,0,.5); }
.ems-kpi-head { display:flex; align-items:center; justify-content:space-between; }
.ems-kpi-label { font-size:.75rem; color:rgba(240,236,228,.35); font-weight:500; }
.ems-kpi-ico {
  width:28px; height:28px;
  background:rgba(var(--kpiRgb,247,147,26),.12);
  border-radius:7px; display:flex; align-items:center; justify-content:center;
  font-size:.75rem; color:var(--kpiAccent,#f7931a);
}
.ems-kpi-val { font-size:1.65rem; font-weight:800; color:#f0ece4; line-height:1; }

/* ── Body ── */
.ems-body { padding:1.5rem 2rem 3rem; display:flex; flex-direction:column; gap:2rem; flex:1; }
.ems-section-head { display:flex; align-items:center; gap:.75rem; margin-bottom:1.1rem; }
.ems-section-line { width:3px; height:18px; background:#f7931a; border-radius:2px; flex-shrink:0; }
.ems-section-title { font-size:1rem; font-weight:700; color:#f0ece4; }
.ems-section-count {
  margin-right:auto; font-size:.74rem; color:rgba(240,236,228,.35);
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:.15rem .55rem;
}

/* ── Stats ── */
.ems-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.9rem; }
.ems-stat {
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:1.1rem 1.1rem .9rem;
  display:flex; flex-direction:column; gap:.55rem;
  transition:all .25s; position:relative; overflow:hidden;
}
.ems-stat::before {
  content:''; position:absolute; bottom:0; left:0; right:0; height:1px;
  background:linear-gradient(90deg,transparent,var(--sAccent,#f7931a),transparent); opacity:.45;
}
.ems-stat:hover { background:rgba(255,255,255,.07); transform:translateY(-3px); box-shadow:0 4px 24px rgba(0,0,0,.5); }
.ems-stat-top { display:flex; align-items:center; justify-content:space-between; }
.ems-stat-ico {
  width:36px; height:36px;
  background:rgba(var(--sRgb,247,147,26),.12);
  border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-size:.9rem; color:var(--sAccent,#f7931a);
}
.ems-stat-label { font-size:.74rem; color:rgba(240,236,228,.35); }
.ems-stat-val { font-size:1.8rem; font-weight:800; color:#f0ece4; line-height:1; }

/* ── Quick Links ── */
.ems-links { display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr)); gap:.75rem; }
.ems-link-card {
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:.85rem 1rem;
  display:flex; align-items:center; gap:.7rem;
  text-decoration:none; color:#f0ece4; transition:all .25s;
}
.ems-link-card:hover {
  background:rgba(255,255,255,.07); border-color:rgba(247,147,26,.2);
  transform:translateY(-2px); box-shadow:0 4px 24px rgba(0,0,0,.5); color:#f0ece4;
}
.ems-link-ico {
  width:38px; height:38px; flex-shrink:0;
  background:rgba(247,147,26,.1); border:1px solid rgba(247,147,26,.2);
  border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-size:.9rem; color:#f7931a; transition:all .25s;
}
.ems-link-card:hover .ems-link-ico { background:rgba(247,147,26,.18); transform:scale(1.08); }
.ems-link-text { flex:1; font-size:.82rem; font-weight:600; line-height:1.3; }
.ems-link-arrow { font-size:.7rem; color:rgba(240,236,228,.35); transition:all .25s; }
.ems-link-card:hover .ems-link-arrow { color:#f7931a; transform:translateX(-3px); }

/* ── Charts ── */
.ems-charts { display:grid; grid-template-columns:1fr 1fr; gap:1.1rem; }
.ems-chart-card {
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:14px; padding:1.3rem 1.4rem;
  display:flex; flex-direction:column; gap:1rem;
}
.ems-chart-card.wide { grid-column:span 2; }
.ems-chart-head { display:flex; align-items:center; gap:.6rem; }
.ems-chart-dot { width:8px; height:8px; border-radius:50%; background:#f7931a; flex-shrink:0; }
.ems-chart-title { font-size:.88rem; font-weight:700; color:#f0ece4; }
.ems-chart-sub { font-size:.74rem; color:rgba(240,236,228,.35); margin-right:auto; }
.ems-chart-wrap { position:relative; height:200px; }
.ems-chart-wrap.tall { height:260px; }
.ems-chart-donut-wrap { display:flex; align-items:center; gap:1.5rem; }
.ems-donut-canvas { width:130px !important; height:130px !important; flex-shrink:0; }
.ems-donut-legend { display:flex; flex-direction:column; gap:.6rem; flex:1; }
.ems-legend-row { display:flex; align-items:center; gap:.5rem; }
.ems-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.ems-legend-label { font-size:.78rem; color:rgba(240,236,228,.65); flex:1; }
.ems-legend-val { font-size:.88rem; font-weight:700; color:#f0ece4; }

/* ── Supplier Table ── */
.ems-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid rgba(255,255,255,.08); }
.ems-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.ems-table th {
  background:rgba(247,147,26,.07); color:#f7931a; font-weight:700;
  padding:.65rem 1rem; text-align:right; border-bottom:1px solid rgba(247,147,26,.15); white-space:nowrap;
}
.ems-table td { padding:.6rem 1rem; border-bottom:1px solid rgba(255,255,255,.05); color:rgba(240,236,228,.65); }
.ems-table tr:last-child td { border-bottom:none; }
.ems-table tbody tr:hover td { background:rgba(255,255,255,.07); }
.ems-eq-bar { display:flex; align-items:center; gap:.6rem; }
.ems-eq-track { flex:1; height:5px; background:rgba(255,255,255,.06); border-radius:3px; overflow:hidden; }
.ems-eq-fill { height:100%; background:linear-gradient(90deg,#f7931a,#ffb84d); border-radius:3px; }

/* ── Session Strip ── */
.ems-session { display:flex; flex-wrap:wrap; gap:.55rem; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.08); }
.ems-chip2 {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.28rem .7rem;
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:20px; font-size:.74rem; color:rgba(240,236,228,.35);
}
.ems-chip2 strong { color:rgba(240,236,228,.65); }
.ems-chip2 i { color:#f7931a; font-size:.68rem; }

/* ── Responsive ── */
@media (max-width:1024px) {
  .ems-hero { grid-template-columns:1fr; gap:1.25rem; }
  .ems-charts { grid-template-columns:1fr; }
  .ems-chart-card.wide { grid-column:span 1; }
}
@media (max-width:768px) {
  .ems-hero { padding:1.25rem; }
  .ems-body { padding:1rem 1.25rem 2rem; }
  .ems-nav { padding:0 1rem; height:54px; }
  .ems-nav-badge { display:none; }
  .ems-hero-welcome { font-size:1.4rem; }
}
@media (max-width:480px) {
  .ems-nav-clock { display:none; }
  .ems-kpi-grid { grid-template-columns:1fr 1fr; }
  .ems-stats { grid-template-columns:1fr 1fr; }
}

/* ══════════════════════════════════════
   LIGHT MODE OVERRIDES
══════════════════════════════════════ */
.ems-dash.main.ems-light {
  background: #f4efe6 !important;
  color: #1a1208 !important;
}
.ems-dash.ems-light::before {
  background-image:
    linear-gradient(rgba(247,147,26,.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(247,147,26,.06) 1px, transparent 1px);
}
.ems-dash.ems-light::after {
  background: radial-gradient(ellipse at center, rgba(247,147,26,.09) 0%, transparent 70%);
}
/* nav */
.ems-dash.ems-light .ems-nav {
  background: rgba(255,250,242,.97);
  border-bottom-color: rgba(247,147,26,.25);
}
.ems-dash.ems-light .ems-nav-brand { color: #1a1208; }
.ems-dash.ems-light .ems-nav-clock { color: rgba(26,18,8,.45); }
.ems-dash.ems-light .ems-nav-badge {
  background: rgba(247,147,26,.08);
  border-color: rgba(247,147,26,.2);
  color: #5a3a0a;
}
.ems-dash.ems-light .ems-nav-avatar {
  background: linear-gradient(135deg,rgba(247,147,26,.25),rgba(247,147,26,.1));
  border-color: rgba(247,147,26,.3);
}
/* hero */
.ems-dash.ems-light .ems-hero {
  background: linear-gradient(135deg,rgba(247,147,26,.08) 0%,rgba(255,250,242,.5) 60%);
  border-bottom-color: rgba(247,147,26,.18);
}
.ems-dash.ems-light .ems-hero-welcome { color: #1a1208; }
.ems-dash.ems-light .ems-hero-sub { color: rgba(26,18,8,.65); }
.ems-dash.ems-light .ems-hero-chip {
  background: rgba(255,255,255,.75);
  border-color: rgba(247,147,26,.2);
  color: rgba(26,18,8,.55);
}
/* kpi cards */
.ems-dash.ems-light .ems-kpi-card {
  background: rgba(255,255,255,.85);
  border-color: rgba(247,147,26,.18);
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
}
.ems-dash.ems-light .ems-kpi-card:hover {
  background: #ffffff;
  box-shadow: 0 6px 24px rgba(0,0,0,.12);
}
.ems-dash.ems-light .ems-kpi-label { color: rgba(26,18,8,.45); }
.ems-dash.ems-light .ems-kpi-val { color: #1a1208; }
/* section */
.ems-dash.ems-light .ems-section-title { color: #1a1208; }
.ems-dash.ems-light .ems-section-count {
  background: rgba(255,255,255,.75);
  border-color: rgba(247,147,26,.18);
  color: rgba(26,18,8,.45);
}
/* stat cards */
.ems-dash.ems-light .ems-stat {
  background: rgba(255,255,255,.85);
  border-color: rgba(247,147,26,.15);
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
.ems-dash.ems-light .ems-stat:hover {
  background: #ffffff;
  box-shadow: 0 6px 20px rgba(0,0,0,.1);
}
.ems-dash.ems-light .ems-stat-label { color: rgba(26,18,8,.45); }
.ems-dash.ems-light .ems-stat-val { color: #1a1208; }
/* links */
.ems-dash.ems-light .ems-link-card {
  background: rgba(255,255,255,.85);
  border-color: rgba(247,147,26,.15);
  color: #1a1208;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.ems-dash.ems-light .ems-link-card:hover {
  background: #ffffff;
  border-color: rgba(247,147,26,.35);
  color: #1a1208;
  box-shadow: 0 6px 20px rgba(0,0,0,.1);
}
.ems-dash.ems-light .ems-link-text { color: #2d1f0a; }
.ems-dash.ems-light .ems-link-arrow { color: rgba(26,18,8,.3); }
.ems-dash.ems-light .ems-link-card:hover .ems-link-arrow { color: #f7931a; }
/* charts */
.ems-dash.ems-light .ems-chart-card {
  background: rgba(255,255,255,.9);
  border-color: rgba(247,147,26,.15);
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.ems-dash.ems-light .ems-chart-title { color: #1a1208; }
.ems-dash.ems-light .ems-chart-sub { color: rgba(26,18,8,.4); }
.ems-dash.ems-light .ems-legend-label { color: rgba(26,18,8,.65); }
.ems-dash.ems-light .ems-legend-val { color: #1a1208; }
/* table */
.ems-dash.ems-light .ems-table-wrap { border-color: rgba(247,147,26,.18); }
.ems-dash.ems-light .ems-table th {
  background: rgba(247,147,26,.1);
  border-bottom-color: rgba(247,147,26,.25);
}
.ems-dash.ems-light .ems-table td {
  color: rgba(26,18,8,.7);
  border-bottom-color: rgba(247,147,26,.1);
}
.ems-dash.ems-light .ems-table tbody tr:hover td { background: rgba(247,147,26,.04); }
/* session */
.ems-dash.ems-light .ems-session { border-top-color: rgba(247,147,26,.2); }
.ems-dash.ems-light .ems-chip2 {
  background: rgba(255,255,255,.75);
  border-color: rgba(247,147,26,.18);
  color: rgba(26,18,8,.5);
}
.ems-dash.ems-light .ems-chip2 strong { color: rgba(26,18,8,.75); }

/* ── Toggle Button ── */
.ems-theme-toggle {
  width: 38px;
  height: 38px;
  border: none;
  border-radius: 10px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  color: rgba(240,236,228,.7);
  font-size: .95rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .2s;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}
.ems-theme-toggle:hover {
  background: rgba(247,147,26,.15);
  border-color: rgba(247,147,26,.35);
  color: #f7931a;
  transform: scale(1.08);
}
.ems-theme-toggle .ico-dark,
.ems-theme-toggle .ico-light { transition: all .25s; }
.ems-theme-toggle .ico-light { display: none; }

/* light mode button adjustments */
.ems-dash.ems-light .ems-theme-toggle {
  background: rgba(247,147,26,.1);
  border-color: rgba(247,147,26,.25);
  color: #b45309;
}
.ems-dash.ems-light .ems-theme-toggle:hover {
  background: rgba(247,147,26,.2);
  color: #f7931a;
}
.ems-dash.ems-light .ems-theme-toggle .ico-dark { display: none; }
.ems-dash.ems-light .ems-theme-toggle .ico-light { display: inline; }
</style>

<div class="ems-dash main">

  <!-- ══════════════ TOPBAR ══════════════ -->
  <nav class="ems-nav">
    <a href="#" class="ems-nav-logo">
      <div class="ems-nav-hex"><i class="fas fa-layer-group"></i></div>
      <div class="ems-nav-brand">EQUIP<span>ATION</span></div>
    </a>

    <div class="ems-nav-spacer"></div>

    <span class="ems-nav-badge"><i class="fas fa-id-badge"></i> <?= htmlspecialchars($roleText) ?></span>
    <?php if ($companyName): ?>
      <span class="ems-nav-badge"><i class="fas fa-building"></i> <?= htmlspecialchars($companyName) ?></span>
    <?php endif; ?>
    <?php if ($projectName): ?>
      <span class="ems-nav-badge"><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($projectName) ?></span>
    <?php endif; ?>

    <span class="ems-nav-clock" id="emsClock">--:--:--</span>

    <button class="ems-theme-toggle" id="emsThemeToggle" title="تبديل بين الوضع الفاتح والداكن">
      <i class="fas fa-moon ico-dark"></i>
      <i class="fas fa-sun ico-light"></i>
    </button>

    <div class="ems-nav-avatar"><i class="fas fa-user"></i></div>

    <a href="../logout.php" class="ems-nav-logout">
      <i class="fas fa-power-off"></i> خروج
    </a>
  </nav>

  <!-- ══════════════ HERO ══════════════ -->
  <section class="ems-hero">

    <!-- Left: Welcome -->
    <div class="ems-hero-left">
      <div class="ems-hero-tag">
        <i class="fas fa-home"></i> لوحة التحكم
      </div>
      <h1 class="ems-hero-welcome">
        مرحباً، <em><?= htmlspecialchars($userName) ?></em>
      </h1>
      <div class="ems-hero-sub">
        <?= htmlspecialchars($roleText) ?> &mdash; <?= date('l، j F Y') ?>
      </div>
      <div class="ems-hero-meta">
        <?php if ($companyName): ?>
          <span class="ems-hero-chip"><i class="fas fa-building"></i> <?= htmlspecialchars($companyName) ?></span>
        <?php endif; ?>
        <?php if ($projectName): ?>
          <span class="ems-hero-chip"><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($projectName) ?></span>
        <?php endif; ?>
        <span class="ems-hero-chip"><i class="fas fa-calendar-alt"></i> <?= date('m/Y') ?></span>
        <span class="ems-hero-chip"><i class="fas fa-circle" style="color:#22c55e;font-size:.5em"></i> متصل</span>
      </div>
    </div>

    <!-- Right: KPI Cards -->
    <div>
      <div class="ems-kpi-grid">
        <?php
        $kpiColors = [
          'ok'   => ['#22c55e', '34,197,94'],
          'or'   => ['#f7931a', '247,147,26'],
          'warn' => ['#f59e0b', '245,158,11'],
          'err'  => ['#ef4444', '239,68,68'],
        ];
        foreach ($analyticsSummaryCards as $kpi):
          $acc = $kpiColors[$kpi['accent']] ?? $kpiColors['or'];
        ?>
        <div class="ems-kpi-card" id="<?= $kpi['id'] ?>"
             style="--kpiAccent:<?= $acc[0] ?>;--kpiRgb:<?= $acc[1] ?>">
          <div class="ems-kpi-head">
            <span class="ems-kpi-label"><?= $kpi['label'] ?></span>
            <span class="ems-kpi-ico"><i class="fas <?= $kpi['icon'] ?>"></i></span>
          </div>
          <div class="ems-kpi-val" data-count="<?= $kpi['value'] ?>">0</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </section>

  <!-- ══════════════ BODY ══════════════ -->
  <div class="ems-body">

    <!-- ── Stats Section ── -->
    <?php if (!empty($stats)): ?>
    <div>
      <div class="ems-section-head">
        <div class="ems-section-line"></div>
        <span class="ems-section-title">نظرة عامة</span>
        <span class="ems-section-count"><?= count($stats) ?> مؤشر</span>
      </div>
      <div class="ems-stats">
        <?php
        $accColors = [
          'or'   => ['#f7931a', '247,147,26'],
          'ok'   => ['#22c55e', '34,197,94'],
          'warn' => ['#f59e0b', '245,158,11'],
          'err'  => ['#ef4444', '239,68,68'],
        ];
        foreach ($stats as $st):
          $ac = $accColors[$st[3]] ?? $accColors['or'];
        ?>
        <div class="ems-stat" style="--sAccent:<?= $ac[0] ?>;--sRgb:<?= $ac[1] ?>">
          <div class="ems-stat-top">
            <span class="ems-stat-ico"><i class="fas <?= $st[0] ?>"></i></span>
            <span class="ems-stat-label"><?= $st[2] ?></span>
          </div>
          <div class="ems-stat-val" data-count="<?= intval($st[1]) ?>">0</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Quick Links ── -->
    <?php if (!empty($links)): ?>
    <div>
      <div class="ems-section-head">
        <div class="ems-section-line"></div>
        <span class="ems-section-title">الوصول السريع</span>
        <span class="ems-section-count"><?= count($links) ?> رابط</span>
      </div>
      <div class="ems-links">
        <?php foreach ($links as $lk): ?>
        <a href="<?= htmlspecialchars($lk[0]) ?>" class="ems-link-card">
          <span class="ems-link-ico"><i class="<?= htmlspecialchars($lk[2]) ?>"></i></span>
          <span class="ems-link-text"><?= htmlspecialchars($lk[1]) ?></span>
          <i class="fas fa-chevron-left ems-link-arrow"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Analytics Charts ── -->
    <div>
      <div class="ems-section-head">
        <div class="ems-section-line"></div>
        <span class="ems-section-title">التحليلات والأداء</span>
        <span class="ems-section-count"><?= $analyticsPayload['monthName'] ?></span>
      </div>
      <div class="ems-charts">

        <!-- Equipment Status Donut -->
        <div class="ems-chart-card">
          <div class="ems-chart-head">
            <div class="ems-chart-dot"></div>
            <span class="ems-chart-title">حالة المعدات</span>
            <span class="ems-chart-sub">إجمالي <?= $analyticsTotalEquip ?></span>
          </div>
          <div class="ems-chart-donut-wrap">
            <canvas class="ems-donut-canvas" id="chartEquipStatus"></canvas>
            <div class="ems-donut-legend">
              <div class="ems-legend-row">
                <div class="ems-legend-dot" style="background:#22c55e"></div>
                <span class="ems-legend-label">نشطة</span>
                <span class="ems-legend-val"><?= $analyticsActiveEquip ?></span>
              </div>
              <div class="ems-legend-row">
                <div class="ems-legend-dot" style="background:#ef4444"></div>
                <span class="ems-legend-label">متوقفة</span>
                <span class="ems-legend-val"><?= $analyticsInactiveEquip ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Hours Bar Chart -->
        <div class="ems-chart-card">
          <div class="ems-chart-head">
            <div class="ems-chart-dot" style="background:#3b82f6"></div>
            <span class="ems-chart-title">ساعات الشهر الحالي</span>
            <span class="ems-chart-sub"><?= $analyticsPayload['monthName'] ?></span>
          </div>
          <div class="ems-chart-wrap">
            <canvas id="chartHoursBar"></canvas>
          </div>
        </div>

        <!-- Daily Trend — full width -->
        <div class="ems-chart-card wide">
          <div class="ems-chart-head">
            <div class="ems-chart-dot" style="background:#f7931a"></div>
            <span class="ems-chart-title">مسار الأداء اليومي — <?= $analyticsPayload['monthName'] ?></span>
          </div>
          <div class="ems-chart-wrap tall">
            <canvas id="chartTrend"></canvas>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Supplier Breakdown (role 6) ── -->
    <?php if (!empty($role6SupplierBreakdown)): ?>
    <div>
      <div class="ems-section-head">
        <div class="ems-section-line"></div>
        <span class="ems-section-title">توزيع الموردين</span>
        <span class="ems-section-count"><?= count($role6SupplierBreakdown) ?> مورد</span>
      </div>
      <div class="ems-table-wrap">
        <table class="ems-table">
          <thead>
            <tr>
              <th>#</th>
              <th>المورد</th>
              <th>عدد الآليات</th>
              <th>الحصة</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $maxEq = max(array_column($role6SupplierBreakdown, 'equipments_count'));
            foreach ($role6SupplierBreakdown as $i => $sup):
              $pct = $maxEq > 0 ? round($sup['equipments_count'] / $maxEq * 100) : 0;
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars($sup['supplier_name']) ?></td>
              <td><strong style="color:var(--gold)"><?= $sup['equipments_count'] ?></strong></td>
              <td>
                <div class="ems-eq-bar">
                  <div class="ems-eq-track">
                    <div class="ems-eq-fill" style="width:<?= $pct ?>%"></div>
                  </div>
                  <span style="font-size:.74rem;color:var(--t3);min-width:32px"><?= $pct ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Session Strip ── -->
    <div class="ems-session">
      <span class="ems-chip2"><i class="fas fa-user-circle"></i> <strong><?= htmlspecialchars($userName) ?></strong></span>
      <span class="ems-chip2"><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($roleText) ?></span>
      <?php if ($companyName): ?>
        <span class="ems-chip2"><i class="fas fa-building"></i> <?= htmlspecialchars($companyName) ?></span>
      <?php endif; ?>
      <?php if ($projectName): ?>
        <span class="ems-chip2"><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($projectName) ?></span>
      <?php endif; ?>
      <span class="ems-chip2"><i class="fas fa-calendar"></i> <?= date('Y/m/d') ?></span>
    </div>

  </div><!-- /.ems-body -->

</div><!-- /.ems-dash -->

<!-- Chart.js -->
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
(function () {
  const AP = <?= json_encode($analyticsPayload) ?>;

  /* ── Count-up animation ── */
  function countUp(el) {
    const target = parseFloat(el.dataset.count) || 0;
    const isFloat = target !== Math.floor(target);
    const dur = 900, fps = 60, steps = Math.ceil(dur / (1000 / fps));
    let step = 0;
    const t = setInterval(function () {
      step++;
      const cur = target * (step / steps);
      if (step >= steps) {
        el.textContent = isFloat ? target.toFixed(1) : Math.round(target).toLocaleString('ar-EG');
        clearInterval(t);
      } else {
        el.textContent = isFloat ? cur.toFixed(1) : Math.round(cur).toLocaleString('ar-EG');
      }
    }, 1000 / fps);
  }
  document.querySelectorAll('[data-count]').forEach(countUp);

  /* ── Chart defaults ── */
  Chart.defaults.color = 'rgba(240,236,228,.55)';
  Chart.defaults.font.family = "'Tajawal','Cairo',sans-serif";
  Chart.defaults.plugins.legend.display = false;

  const gridColor = 'rgba(255,255,255,.05)';
  const tickColor = 'rgba(240,236,228,.40)';

  /* ── Equipment Donut ── */
  const eqCtx = document.getElementById('chartEquipStatus');
  if (eqCtx) {
    new Chart(eqCtx, {
      type: 'doughnut',
      data: {
        labels: ['نشطة', 'متوقفة'],
        datasets: [{
          data: AP.equipmentStatus,
          backgroundColor: ['#22c55e', '#ef4444'],
          borderColor: 'transparent',
          borderWidth: 0,
          hoverOffset: 6
        }]
      },
      options: {
        cutout: '72%',
        plugins: {
          tooltip: {
            callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw; } }
          }
        }
      }
    });
  }

  /* ── Hours Bar ── */
  const hbCtx = document.getElementById('chartHoursBar');
  if (hbCtx) {
    new Chart(hbCtx, {
      type: 'bar',
      data: {
        labels: ['ساعات العمل', 'ساعات التعطل'],
        datasets: [{
          data: [parseFloat(AP.kpis[1]), parseFloat(AP.monthBreakdownHours)],
          backgroundColor: ['rgba(247,147,26,.70)', 'rgba(239,68,68,.60)'],
          borderColor: ['#f7931a', '#ef4444'],
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          tooltip: { callbacks: { label: function(c) { return ' ' + c.raw + ' ساعة'; } } }
        },
        scales: {
          x: { grid: { color: gridColor }, ticks: { color: tickColor } },
          y: { grid: { display: false }, ticks: { color: tickColor } }
        }
      }
    });
  }

  /* ── Daily Trend ── */
  const trCtx = document.getElementById('chartTrend');
  if (trCtx) {
    new Chart(trCtx, {
      type: 'line',
      data: {
        labels: AP.trendLabels,
        datasets: [
          {
            label: 'ساعات العمل',
            data: AP.trendWork,
            borderColor: '#f7931a',
            backgroundColor: 'rgba(247,147,26,.08)',
            fill: true,
            tension: .35,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2
          },
          {
            label: 'ساعات التعطل',
            data: AP.trendFault,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,.06)',
            fill: true,
            tension: .35,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2
          }
        ]
      },
      options: {
        plugins: {
          legend: {
            display: true,
            labels: { color: tickColor, font: { size: 11 }, boxWidth: 12 }
          }
        },
        scales: {
          x: { grid: { color: gridColor }, ticks: { color: tickColor, maxTicksLimit: 10 } },
          y: { grid: { color: gridColor }, ticks: { color: tickColor } }
        }
      }
    });
  }

  /* ── Live Clock ── */
  function updateClock() {
    var el = document.getElementById('emsClock');
    if (!el) return;
    var now = new Date();
    el.textContent = now.toLocaleTimeString('ar-SA', {
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ── Theme Toggle ── */
  var THEME_KEY = 'ems_dash_theme';
  var dash = document.querySelector('.ems-dash');
  var toggleBtn = document.getElementById('emsThemeToggle');

  function applyTheme(theme) {
    if (theme === 'light') {
      dash.classList.add('ems-light');
    } else {
      dash.classList.remove('ems-light');
    }
  }

  /* restore saved preference */
  var savedTheme = localStorage.getItem(THEME_KEY) || 'dark';
  applyTheme(savedTheme);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var isLight = dash.classList.contains('ems-light');
      var newTheme = isLight ? 'dark' : 'light';
      applyTheme(newTheme);
      localStorage.setItem(THEME_KEY, newTheme);
    });
  }

})();
</script>

</body>
</html>
