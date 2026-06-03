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
if (!function_exists('dashboard_two_digits')) {
  function dashboard_two_digits($value)
  {
    $num = intval(round($value));
    if ($num < 0) {
      return '00';
    }
    return str_pad((string) $num, 2, '0', STR_PAD_LEFT);
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
$contractsProjectCol = dashboard_has_column($conn, 'contracts', 'project_id') ? 'project_id' : (dashboard_has_column($conn, 'contracts', 'project') ? 'project' : 'project_id');
$contractsHasIsDeleted = dashboard_has_column($conn, 'contracts', 'is_deleted');
$contractsHasDeletedAt = dashboard_has_column($conn, 'contracts', 'deleted_at');

$sessionMineId = isset($_SESSION['user']['mine_id']) ? intval($_SESSION['user']['mine_id']) : 0;
$sessionContractId = isset($_SESSION['user']['contract_id']) ? intval($_SESSION['user']['contract_id']) : 0;

$stats = [];
$role6SupplierBreakdown = [];
$role6ContextText = '';
$opsProjectCol = dashboard_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

if ($dashboardRole == "0" || $dashboardRole == "1" || $dashboardRole == "12") {
  $c = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND $sc", 't');
  $p = dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM project WHERE status='1' AND $sp", 't');
  $contractsNotDeletedSql = "1=1";
  if ($contractsHasIsDeleted) {
    $contractsNotDeletedSql = "c.is_deleted = 0";
  } elseif ($contractsHasDeletedAt) {
    $contractsNotDeletedSql = "c.deleted_at IS NULL";
  }
  $activeContracts = dashboard_table_exists($conn, 'contracts')
    ? dashboard_scalar(
      $conn,
      "SELECT COUNT(*) AS t
       FROM contracts c
       WHERE (c.status='1' OR c.status=1)
         AND $contractsNotDeletedSql
         AND c.$contractsProjectCol IN(SELECT id FROM project WHERE $sp)",
      't'
    )
    : 0;
  $u = $companyId > 0 ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id=$companyId AND role!='-1'", 't') : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'", 't');
  $stats = [['fa-users', $c, 'العملاء', 'or'], ['fa-project-diagram', $p, 'المشاريع', 'or'], ['fa-file-contract', $activeContracts, 'العقود النشطة', 'ok'], ['fa-user-shield', $u, 'المستخدمون', 'or']];
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
/* ─── Dashboard font: IBM Plex Sans Arabic ────────────────────
   --dash-font must live on :root so body.ems-site (an ancestor
   of .ems-dash.main) can resolve var(--dash-font).
   CSS custom properties only inherit DOWN the DOM tree.
   Using a literal font-family avoids any CSS variable failures.
   ──────────────────────────────────────────────────────────── */
:root {
  --dash-font: "IBM Plex Sans Arabic", "Tajawal", "Cairo", "Segoe UI", Tahoma, Arial, sans-serif;
}

/* Dashboard redesign to match the provided yellow/gray visual identity */
.ems-dash.main {
  --dash-yellow: #f3be00;
  --dash-gray: #e2e2e2;
  --dash-line: #bdbdbd;
  --dash-ink: #121212;
  background: #ffffff;
  color: var(--dash-ink);
  flex: 1 1 auto;
  min-height: 100vh;
  margin-right: 0;
  font-family: var(--dash-font);
  overflow-x: hidden;
}

body.ems-site,
body.ems-site .sidebar,
body.ems-site .sidebar a,
body.ems-site .sidebar span,
body.ems-site .sidebar li,
body.ems-site .sidebar .logo,
body.ems-site .sidebar .toggle-btn,
body.ems-site .sidebar .logout,
.ems-dash.main,
.ems-dash.main h1,
.ems-dash.main h2,
.ems-dash.main h3,
.ems-dash.main h4,
.ems-dash.main h5,
.ems-dash.main h6,
.ems-dash.main p,
.ems-dash.main span,
.ems-dash.main a,
.ems-dash.main button,
.ems-dash.main input,
.ems-dash.main select,
.ems-dash.main textarea,
.ems-dash.main th,
.ems-dash.main td,
.ems-dash.main label,
.ems-dash.main li,
.ems-dash.main div {
  font-family: var(--dash-font) !important;
}

.ems-dash * {
  box-sizing: border-box;
}

/* Right vertical icon sidebar (desktop) */
body.ems-site .mobile-menu-btn,
body.ems-site .sidebar-overlay {
  display: none;
}

body.ems-site .sidebar {
  position: fixed;
  top: 50px;
  right: 0;
  left: auto;
  width: 286px;
  height: calc(100vh - 50px);
  background: #efefef;
  border-right: 1px solid #c8c8c8;
  border-left: 1px solid #c8c8c8;
  box-shadow: none;
  z-index: 50;
  transition: width .3s ease;
  overflow-y: auto !important;
  overflow-x: hidden !important;
}

body.ems-site .sidebar.closed {
  width: 68px;
}

body.ems-site .sidebar .toggle-btn {
  display: flex;
  width: 100%;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  color: #333;
  border-bottom: 1px solid #d0d0d0;
  background: #e5e5e5;
}

body.ems-site .sidebar .logo {
  display: block;
  padding: 10px 12px;
  color: #151515;
  font-size: 1.2rem;
  border-bottom: 1px solid #d0d0d0;
  background: #ececec;
}

body.ems-site .sidebar.closed .logo {
  display: none;
}

body.ems-site .sidebar ul {
  padding: 8px 0 12px;
  overflow-y: visible !important;
  max-height: none !important;
}

body.ems-site .sidebar ul li {
  margin: 6px 8px;
  width: auto;
  min-height: 42px;
  padding: 0 8px;
  border-radius: 10px;
  background: transparent;
  display: flex;
  justify-content: flex-start;
}

body.ems-site .sidebar ul li::before {
  display: none;
}

body.ems-site .sidebar ul li a {
  justify-content: flex-start;
  align-items: center;
  padding: 0;
}

body.ems-site .sidebar ul li i {
  margin: 0;
  color: #6e6e6e;
  font-size: 1.05rem;
}

body.ems-site .sidebar ul li span {
  display: inline;
  color: #222;
  margin-right: 10px;
  font-size: .95rem;
  font-weight: 700;
}

body.ems-site .sidebar.closed ul li {
  margin: 6px auto;
  width: 48px;
  justify-content: center;
  padding: 0;
}

body.ems-site .sidebar.closed ul li a {
  justify-content: center;
}

body.ems-site .sidebar.closed ul li span {
  display: none;
}

body.ems-site .sidebar ul li.active,
body.ems-site .sidebar ul li:hover {
  background: #efefef;
  border: 1px solid #d4d4d4;
}

body.ems-site .sidebar ul li.active i,
body.ems-site .sidebar ul li:hover i {
  color: #111;
}

body.ems-site .sidebar .logout {
  margin: 8px 8px 12px;
  width: auto;
  min-height: 42px;
  padding: 0 10px;
  justify-content: flex-start;
  border-radius: 10px;
  background: transparent;
  border: 1px solid #cfcfcf;
  color: #a12727;
}

body.ems-site .sidebar .logout i {
  margin: 0;
}

body.ems-site .sidebar.closed .logout {
  width: 48px;
  margin: 8px auto 12px;
  justify-content: center;
  padding: 0;
}

body.ems-site .sidebar.closed .logout span {
  display: none;
}

.shot-topbar {
  height: 50px;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 120;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  padding: 0 18px;
  direction: ltr;
  background: var(--dash-yellow);
  border: none !important;
  border-bottom: none !important;
  box-shadow: none !important;
  outline: none !important;
  background-image: none !important;
}

body.ems-site .ems-site .main .shot-topbar,
body.ems-site .main .shot-topbar,
body.ems-site .ems-dash.main .shot-topbar,
body.ems-site .ems-dash.main [class*="topbar"],
body.ems-site .ems-dash.main .shot-topbar[class*="topbar"] {
  border: none !important;
  border-bottom: 0 !important;
  box-shadow: none !important;
  outline: none !important;
  background-image: none !important;
}

.shot-topbar::before,
.shot-topbar::after {
  content: none !important;
  display: none !important;
}

body.ems-site .ems-dash.main .shot-topbar::before,
body.ems-site .ems-dash.main .shot-topbar::after,
body.ems-site .ems-dash.main [class*="topbar"]::before,
body.ems-site .ems-dash.main [class*="topbar"]::after {
  content: none !important;
  display: none !important;
  border: 0 !important;
  box-shadow: none !important;
  background: none !important;
}

.ems-dash.main::before,
.ems-dash.main::after {
  content: none !important;
  display: none !important;
}

.shot-logo {
  display: flex;
  align-items: center;
  gap: 3px;
  font-weight: 800;
  font-size: .9rem;
  letter-spacing: .4px;
  color: #111;
  grid-column: 1;
  justify-self: start;
  direction: ltr;
  w
}

.shot-logo image {
  width: 28px;
  height: 30px;
}

.shot-logo span{
  font-size: 1.1rem;
}

.shot-logo-mark {
  width: 28px;
  height: 22px;
  clip-path: polygon(18% 4%, 82% 4%, 100% 50%, 82% 96%, 18% 96%, 0 50%);
  background: #111;
  position: relative;
}

.shot-logo-mark::before,
.shot-logo-mark::after {
  content: '';
  position: absolute;
  border-top: 6px solid transparent;
  border-bottom: 6px solid transparent;
  border-left: 8px solid var(--dash-yellow);
  top: 5px;
  width: 0;
  height: 0;
}

.shot-logo-mark::before {
  left: 7px;
}

.shot-logo-mark::after {
  left: 14px;
}

.shot-top-center {
  display: flex;
  align-items: center;
  gap: 8px;
  grid-column: 2;
  justify-self: center;
  direction: rtl;
}

.shot-nav-pill {
  height: 32px;
  min-width: 120px;
  padding: 0 18px;
  border: 1px solid #cfcfcf;
  background: #ffffff;
  color: #121212;
  border-radius: 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  font-weight: 700;
  white-space: nowrap;
}

.shot-user {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.1rem;
  font-weight: 700;
  grid-column: 3;
  justify-self: end;
  direction: rtl;
}

.shot-user-icons {
  display: flex;
  gap: 8px;
}

.shot-user-icons a.shot-icon-pill {
  text-decoration: none;
}

.shot-icon-pill {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid #666;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .82rem;
  color: #2b2b2b;
  flex-shrink: 0;
}

.shot-icon-pill.shot-power {
  background: #d42f2f;
  border-color: #b31f1f;
  color: #fff;
}

.shot-icon-pill i {
  line-height: 1;
}

.shot-body {
  padding: 51px 0px 14px;
  background: #e2e2e2;
  margin-right: 68px;
  margin-top: -1px;
}

body.ems-site .sidebar:not(.closed) ~ .ems-dash .shot-body {
  margin-right: 286px;
}

.shot-breadcrumb {
  position: fixed;
  top: 51px;
  left: 50%;
  transform: translateX(-50%);
  margin: 0;
  min-width: 205px;
  width: max-content;
  text-align: center;
  background: #d7d7d7;
  border: 1px solid #bdbdbd;
  border-top: none;
  padding: 5px 34px;
  font-weight: 700;
  font-size: .78rem;
  line-height: 1;
  z-index: 115;
  pointer-events: none;
  border-radius: 0 0 35px 35px;
}

.shot-section-title {
  margin: 0 0 12px;
  font-size: 1.08rem;
  font-weight: 700;
  text-align: right;
  font-family: "IBM Plex Sans Arabic", "Tajawal", "Cairo", "Segoe UI", Tahoma, Arial, sans-serif !important;
}

.shot-quick-zone {
  width: 100%;
  background: #fff;
  border: none;
  border-radius: 0;
  padding: 25px 50px 50px;
  font-family: "IBM Plex Sans Arabic", "Tajawal", "Cairo", "Segoe UI", Tahoma, Arial, sans-serif !important;
}

.shot-hex-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px 10px;
}

.shot-hex-grid.cols-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.shot-hex-link {
  min-height: 88px;
  border: 1px solid #b7b7b7;
  border-radius: 30px;
  color: #111;
  text-decoration: none;
  background: #ececec;
  display: grid;
  grid-template-columns: 72px 1fr;
  align-items: center;
  gap: 12px;
  padding: 8px 14px;
  font-size: 2rem;
  font-weight: 700;
  transition: transform .2s ease, background .2s ease, border-color .2s ease;
  direction: rtl;
  position: relative;
  font-family: "IBM Plex Sans Arabic", "Tajawal", "Cairo", "Segoe UI", Tahoma, Arial, sans-serif !important;
}

.shot-hex-bg {
  display: none;
}

.shot-hex-bg path {
  display: none;
}

.shot-hex-link:hover {
  transform: translateY(-1px);
}

.shot-hex-link:hover {
  background: var(--dash-yellow);
  border-color: #9f8500;
}

.shot-hex-link > span {
  position: relative;
  z-index: 1;
}

.shot-hex-title {
  display: inline-block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 1.3rem;
  font-family: "IBM Plex Sans Arabic", "Tajawal", "Cairo", "Segoe UI", Tahoma, Arial, sans-serif !important;
}

.shot-hex-icon {
  width: 66px;
  height: 66px;
  border-radius: 18px;
  border: 1px solid #a9a9a9;
  background: #f7f7f7;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.5rem;
  color: #252525;
}

.shot-hex-icon svg {
  display: none;
}

.shot-hex-icon polygon {
  display: none;
}

.shot-hex-icon i {
  line-height: 1;
  font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "Font Awesome 5 Free", "Font Awesome 5 Brands", FontAwesome !important;
}

.shot-session {
  margin-top: 0;
  border-top: none;
  border-bottom: none;
  padding: 10px 40px;
}

.shot-lower-zone {
  margin-top: 12px;
  background: #e4e4e4;
  border: 1px solid #cfcfcf;
  border-radius: 12px;
  padding: 10px 10px 14px;
}

.shot-session-title {
  font-size: .92rem;
  font-weight: 700;
  margin-bottom: 8px;
  text-align: right;
}

.shot-session-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(180px, 1fr));
  gap: 10px;
}

.shot-session-chip {
  background: #f5f3f3;
  border: 1px solid rgb(191, 191, 191);
  border-radius: 22px;
  min-height: 55px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  direction: rtl;
  gap: 10px;
  padding: 0 8px;
  font-size: 1.1rem;
  color: rgb(33, 33, 33);
  transition: background .2s ease, border-color .2s ease;
}

.shot-session-chip:hover {
  background: var(--dash-yellow);
  border-color: #9d7e00;
}

.shot-session-chip strong {
  font-weight: 700;
  flex: 1;
  text-align: center;
}

.shot-session-chip .chip-icon {
  width: 60px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #4c4c4c;
  flex-shrink: 0;
  border: 1px solid #b2b2b2;
  border-radius: 18px;
  background: #f7f7f7;
}

.shot-session-chip .chip-icon svg {
  display: none;
}

.shot-session-chip .chip-icon polygon {
  display: none;
}

.shot-session-chip .chip-icon i {
  font-size: 1.3rem;
  line-height: 1;
  position: relative;
  z-index: 1;
}

.shot-session-chip:hover .chip-icon {
  border-color: #333;
}

.shot-quick-zone,
.shot-session,
.shot-session-title,
.shot-session-row,
.shot-session-chip,
.shot-session-chip strong,
.shot-stat-panel,
.shot-stat-grid,
.shot-stat-card,
.shot-stat-label,
.shot-stat-value,
.shot-chart-card,
.shot-chart-head,
.shot-chart-title,
.shot-chart-note,
.shot-hex-link,
.shot-hex-title,
.shot-section-title,
.shot-breadcrumb {
  font-family: var(--dash-font) !important;
}

.shot-stat-panel {
  margin-top: 14px;
  position: relative;
  padding: 58px 150px 44px;
  isolation: isolate;
}

.shot-stat-panel .shot-stat-label {
  font-size: 1.5rem;
  font-weight: 700;
}

.shot-stat-panel .shot-stat-value {
  font-size: 6.5rem;
  font-weight: 50;
}

.shot-stat-panel:not(.shot-stat-panel-secondary)::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--dash-yellow);
  border: 1px solid #d4a500;
  border-radius: 68px 68px 0 0;
  z-index: 0;
  pointer-events: none;
}

.shot-stat-panel > * {
  position: relative;
  z-index: 1;
}

.shot-stat-panel-secondary {
  margin-top: 12px;
    background: #ffffff;
  border: 1px solid #cfcfcf;
  border-radius: 20px;
  padding: 16px 18px 14px;
}

.shot-stat-panel-secondary .shot-stat-grid {
  gap: 12px;
}

.shot-stat-panel-secondary .shot-stat-label {
  font-size: 1.0rem;
  font-weight: 700;
}

.shot-stat-panel-secondary .shot-stat-value {
  font-size: 3.1rem;
  font-weight: lighter;
}

.shot-charts {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.shot-chart-card {
  background: #ffffff;
  border: 1px solid #cfcfcf;
  border-radius: 20px;
  padding: 12px;
}

.shot-chart-card.wide {
  grid-column: 1 / -1;
}

.shot-chart-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.shot-chart-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1f1f1f;
}

.shot-chart-note {
  font-size: .78rem;
  color: #666;
}

.shot-chart-wrap {
  position: relative;
  height: 220px;
}

.shot-chart-wrap.tall {
  height: 265px;
}


.shot-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.shot-stat-card {
  text-align: center;
}

.shot-stat-label {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 6px;
}

.shot-stat-value {
  font-size: 5.1rem;
  line-height: 1;
  font-weight: 100;
}

.shot-logout {
  display: none;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  background: #cf2626;
  color: #fff;
  border: 1px solid #a91f1f;
  border-radius: 18px;
  padding: 6px 18px 6px 22px;
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  float: left;
  clear: both;
  margin-right: 0;
  margin-left: 0;
  position: relative;
  z-index: 2;
}

.shot-logout i {
  font-size: .95rem;
}

.shot-logout:hover {
  color: #fff;
}

@media (max-width: 1280px) {
  .shot-hex-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .shot-session-row,
  .shot-stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .ems-dash.main {
    margin-right: 0;
  }

  body.ems-site .mobile-menu-btn {
    display: inline-flex;
  }

  body.ems-site .sidebar {
    top: 50px;
    width: 280px;
    height: calc(100vh - 50px);
    right: auto;
    left: 0;
    background: #fff;
    transform: translateX(-110%);
    transition: transform .3s ease;
  }

  body.ems-site .sidebar.active {
    transform: translateX(0);
  }

  body.ems-site .sidebar ul li span,
  body.ems-site .sidebar .logout span,
  body.ems-site .sidebar .logo {
    display: initial;
  }

  body.ems-site .sidebar .toggle-btn,
  body.ems-site .sidebar .logo,
  body.ems-site .sidebar .logout span,
  body.ems-site .sidebar ul li span {
    display: initial;
  }

  .shot-topbar {
    grid-template-columns: auto 1fr auto;
    padding: 0 10px;
    gap: 8px;
  }

  .shot-top-center {
    justify-self: center;
    gap: 6px;
  }

  .shot-nav-pill {
    min-width: 86px;
    font-size: .85rem;
    height: 27px;
    padding: 0 12px;
  }

  .shot-body {
    padding-top: 10px;
    margin-top: 0;
    margin-right: 0;
  }

  .shot-breadcrumb {
    min-width: 168px;
    padding: 4px 24px;
    font-size: .72rem;
  }

  .shot-hex-grid,
  .shot-session-row,
  .shot-stat-grid,
  .shot-charts {
    grid-template-columns: 1fr;
  }

  .shot-stat-value {
    font-size: 4.2rem;
  }
}
</style>

<div class="ems-dash main">

  <div class="shot-topbar">
    <div class="shot-logo">
      <img src="../assets/images/logo 2.svg" alt="Equipation Logo" width="150" height="35">
      <!-- <span class="shot-logo-mark"></span> -->
      <!-- <span>EQUIPATION</span> -->
    </div>

    <div class="shot-top-center">
      <span class="shot-nav-pill"><?= htmlspecialchars($roleText) ?></span>
      <span class="shot-nav-pill"><?= htmlspecialchars($userName) ?></span>
    </div>

    <div class="shot-user">
      <div class="shot-user-icons">
        <a href="../logout.php" class="shot-icon-pill shot-power" title="تسجيل الخروج" aria-label="تسجيل الخروج"><i class="fas fa-power-off"></i></a>
        <span class="shot-icon-pill"><i class="far fa-user"></i></span>
        <span class="shot-icon-pill"><i class="fas fa-gear"></i></span>
      </div>
      <span></span>
    </div>
</div>

  <div class="shot-body">
    <div class="shot-breadcrumb" id="emsClock"><?= date('Y F d, l') ?></div>

    <div class="shot-quick-zone">
    <h2 class="shot-section-title">الوصول السريع</h2>

    <?php
    $quickTiles = array_slice($links, 0, 8);
    if (empty($quickTiles)) {
      $quickTiles = [
        ['../main/dashboard.php', 'لوحة التحكم', 'fa-solid fa-house'],
      ];
    }
    $quickTilesCount = count($quickTiles);
    $quickGridClass = ($quickTilesCount > 2 && ($quickTilesCount % 2 === 1)) ? 'cols-3' : 'cols-4';
    ?>

    <div class="shot-hex-grid <?= $quickGridClass ?>">
      <?php foreach ($quickTiles as $i => $lk): ?>
      <a href="<?= htmlspecialchars($lk[0]) ?>" class="shot-hex-link">
        <span class="shot-hex-icon"><i class="<?= htmlspecialchars($lk[2]) ?>"></i></span>
        <span class="shot-hex-title"><?= htmlspecialchars($lk[1]) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    </div>

    <div class="shot-lower-zone">
    <div class="shot-session">
      <div class="shot-session-title">بيانات الجلسة</div>
      <div class="shot-session-row">
        <div class="shot-session-chip">
          <span class="chip-icon"><i class="far fa-calendar"></i></span>
          <strong><?= date('Y M d') ?></strong>
        </div>
        <div class="shot-session-chip">
          <span class="chip-icon"><i class="fas fa-building"></i></span>
          <strong><?= $companyName ? htmlspecialchars($companyName) : 'اكويشن' ?></strong>
        </div>
        <div class="shot-session-chip">
          <span class="chip-icon"><i class="fas fa-gear"></i></span>
          <strong><?= $projectName ? htmlspecialchars($projectName) : 'ادارة التشغيل' ?></strong>
        </div>
        <div class="shot-session-chip">
          <span class="chip-icon"><i class="far fa-user"></i></span>
          <strong><?= htmlspecialchars($userName) ?></strong>
        </div>
      </div>
    </div>

    <?php
    $displayStats = !empty($stats) ? $stats : [
      ['fa-users', 0, 'العمـــــلاء', 'or'],
      ['fa-project-diagram', 0, 'المشــــاريع', 'or'],
      ['fa-file-contract', 0, 'العقود', 'or'],
      ['fa-user-shield', 0, 'المستخدمون', 'or'],
    ];
    ?>
    <div class="shot-stat-panel">
      <div class="shot-stat-grid">
        <?php foreach ($displayStats as $st): ?>
        <div class="shot-stat-card">
          <div class="shot-stat-label"><?= htmlspecialchars($st[2]) ?></div>
          <div class="shot-stat-value" data-count="<?= intval($st[1]) ?>">00</div>
        </div>
        <?php endforeach; ?>
      </div>

      <a href="../logout.php" class="shot-logout">
        خروج <i class="fas fa-power-off"></i>
      </a>
    </div>

    <?php if (!empty($analyticsSummaryCards)): ?>
    <div class="shot-stat-panel shot-stat-panel-secondary">
      <div class="shot-session-title">إحصائيات الأداء</div>
      <div class="shot-stat-grid">
        <?php foreach ($analyticsSummaryCards as $kpi): ?>
        <div class="shot-stat-card">
          <div class="shot-stat-label"><?= htmlspecialchars($kpi['label']) ?></div>
          <div class="shot-stat-value" data-count="<?= floatval($kpi['value']) ?>">00</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="shot-charts">
      <div class="shot-chart-card">
        <div class="shot-chart-head">
          <span class="shot-chart-title">حالة المعدات</span>
          <span class="shot-chart-note">إجمالي <?= dashboard_two_digits($analyticsTotalEquip) ?></span>
        </div>
        <div class="shot-chart-wrap">
          <canvas id="chartEquipStatus"></canvas>
        </div>
      </div>

      <div class="shot-chart-card">
        <div class="shot-chart-head">
          <span class="shot-chart-title">ساعات الشهر الحالي</span>
          <span class="shot-chart-note"><?= htmlspecialchars($analyticsPayload['monthName']) ?></span>
        </div>
        <div class="shot-chart-wrap">
          <canvas id="chartHoursBar"></canvas>
        </div>
      </div>

      <div class="shot-chart-card wide">
        <div class="shot-chart-head">
          <span class="shot-chart-title">مسار الأداء اليومي</span>
          <span class="shot-chart-note"><?= htmlspecialchars($analyticsPayload['monthName']) ?></span>
        </div>
        <div class="shot-chart-wrap tall">
          <canvas id="chartTrend"></canvas>
        </div>
      </div>
    </div>
    </div>
  </div>

</div><!-- /.ems-dash -->

<!-- Chart.js -->
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
(function () {
  const AP = <?= json_encode($analyticsPayload) ?>;

  function formatTwoDigits(value, isFloat) {
    if (isFloat) {
      const safeValue = isFinite(value) ? value : 0;
      const fixed = safeValue.toFixed(1);
      const parts = fixed.split('.');
      const intPart = String(Math.max(0, parseInt(parts[0], 10) || 0)).padStart(2, '0');
      return intPart + '.' + parts[1];
    }
    const n = Math.max(0, Math.round(isFinite(value) ? value : 0));
    return String(n).padStart(2, '0');
  }

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
        el.textContent = formatTwoDigits(target, isFloat);
        clearInterval(t);
      } else {
        el.textContent = formatTwoDigits(cur, isFloat);
      }
    }, 1000 / fps);
  }
  document.querySelectorAll('[data-count]').forEach(countUp);

  /* ── Chart defaults ── */
  Chart.defaults.color = 'rgba(25,25,25,.75)';
  Chart.defaults.font.family = getComputedStyle(document.documentElement)
    .getPropertyValue('--font-ar')
    .trim() || "'IBM Plex Sans Arabic','Tajawal','Cairo',sans-serif";
  Chart.defaults.plugins.legend.display = false;

  const gridColor = 'rgba(0,0,0,.08)';
  const tickColor = 'rgba(25,25,25,.70)';

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
        responsive: true,
        maintainAspectRatio: false,
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
        responsive: true,
        maintainAspectRatio: false,
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
        responsive: true,
        maintainAspectRatio: false,
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
    el.textContent = now.toLocaleDateString('en-GB', {
      year: 'numeric', month: 'long', day: 'numeric', weekday: 'long'
    });
  }
  updateClock();
  setInterval(updateClock, 60000);

})();
</script>

</body>
</html>
