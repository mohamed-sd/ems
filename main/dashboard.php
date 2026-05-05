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

// توحيد إحصائيات اللوحة بين صاحب الدور ومشرفيه (الأدوار الفرعية)
$dashboardRole = strval($role);
$roleParentStmt = $conn->prepare("SELECT parent_role_id FROM roles WHERE id = ? LIMIT 1");
if ($roleParentStmt) {
  $roleParentStmt->bind_param("i", $roleId);
  $roleParentStmt->execute();
  $roleParentResult = $roleParentStmt->get_result();
  if ($roleParentResult && $roleParentRow = $roleParentResult->fetch_assoc()) {
    $parentRoleId = isset($roleParentRow['parent_role_id']) ? intval($roleParentRow['parent_role_id']) : 0;
    if ($parentRoleId > 0) {
      $dashboardRole = strval($parentRoleId);
    }
  }
  $roleParentStmt->close();
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
$role6SupplierBreakdown = array();
$role6ContextText = '';

$operationsHasMineId = dashboard_has_column($conn, 'operations', 'mine_id');
$operationsHasSupplierId = dashboard_has_column($conn, 'operations', 'supplier_id');
$equipmentsHasAvailability = dashboard_has_column($conn, 'equipments', 'availability_status');
$driversHasDriverStatus = dashboard_has_column($conn, 'drivers', 'driver_status');
$supplierscontractsHasMineId = dashboard_has_column($conn, 'supplierscontracts', 'mine_id');
$supplierscontractsHasProjectContractId = dashboard_has_column($conn, 'supplierscontracts', 'project_contract_id');

$sessionMineId = isset($_SESSION['user']['mine_id']) ? intval($_SESSION['user']['mine_id']) : 0;
$sessionContractId = isset($_SESSION['user']['contract_id']) ? intval($_SESSION['user']['contract_id']) : 0;

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

if ($dashboardRole=="0"||$dashboardRole=="1") {
  // مدير المشاريع
  $c=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND $scopeClients", 't');
  $p=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM project WHERE status='1' AND $scopeProjects", 't');
  $m=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM mines WHERE status='1' AND project_id IN (SELECT id FROM project WHERE $scopeProjects)", 't');
  $u=($companyId > 0)
    ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id = $companyId AND role!='-1'", 't')
    : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1'", 't');
  $stats=[['fa-users',$c,'العملاء','gold'],['fa-project-diagram',$p,'المشاريع','blue'],['fa-mountain',$m,'المناجم','teal'],['fa-user-shield',$u,'المستخدمين','purple']];
} elseif ($dashboardRole=="2") {
  // مدير الموردين
  $s=dashboard_scalar($conn, "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id = $companyId" , 't');
  $e=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id = $companyId", 't');
  $co=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM supplierscontracts WHERE project_id IN (SELECT id FROM project WHERE $scopeProjects)", 't');
  $stats=[['fa-truck',$s,'الموردين','gold'],['fa-tools',$e,'الآليات','blue'],['fa-file-contract',$co,'العقود','teal']];
} elseif ($dashboardRole=="3") {
  // مدير الاسطول
  $s=dashboard_scalar($conn, "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE company_id = $companyId" , 't');
  $eq=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE company_id = $companyId ", 't');
  $ao=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM operations WHERE status='1' AND company_id = $companyId AND $scopeOperationsByProject", 't');
  $bo=dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM equipments WHERE status='3' AND company_id = $companyId", 't');
  $stats=[['fa-tools',$eq,'إجمالي المعدات','gold'],['fa-play-circle',$ao,'تعمل الآن','blue'],['fa-exclamation-triangle',$bo,'معطلة','orange'],['fa-truck',$s,'الموردين','gold']];
} elseif ($dashboardRole=="4") {
  // مدير المشغلين
  $dr=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d WHERE company_id = $companyId ",'t');
  $ad=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON d.id=ed.driver_id WHERE ed.status='1' AND d.company_id = $companyId", 't');
  $stats=[['fa-id-badge',$dr,'المشغلين','gold'],['fa-user-check',$ad,'يعملون الآن','blue'],['fa-user-clock',$dr-$ad,'خاملين','orange']];
} elseif ($dashboardRole=="5") {
  // مدير الموقع
  $sv=($companyId > 0)
    ? dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE company_id = $companyId AND role IN ('6','7','8','9')", 't')
    : dashboard_scalar($conn, "SELECT COUNT(*) AS t FROM users WHERE role IN ('6','7','8','9')", 't');
  $h=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE o.company_id = $companyId", 't');
  $ah=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN timesheet_approvals ta ON t.id=ta.timesheet_id and approval_level = '4'  WHERE t.company_id = $companyId", 't');
  $stats=[['fa-users-cog',$sv,'المشرفين','gold'],['fa-clock',(int)$h,'ساعات العمل','blue'],['fa-check-circle',(int)$ah,'الساعات المعتمدة','teal']];
} elseif ($dashboardRole=="6") {
  $projectScopeSql = $projectId > 0 ? "o.project_id = '$projectId'" : "1=0";
  $mineScopeSql = ($sessionMineId > 0 && $operationsHasMineId) ? " AND o.mine_id = '$sessionMineId'" : '';

  $role6ContextText = $projectId > 0
    ? 'النطاق: المشروع الحالي' . ($sessionMineId > 0 ? ' + المنجم المحدد' : '')
    : 'لا يوجد مشروع محدد في الجلسة';

  $availabilityStoppedList = "'معطلة','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة'";
  $workingAvailabilityCondition = $equipmentsHasAvailability
    ? " AND (e.availability_status IS NULL OR e.availability_status NOT IN ($availabilityStoppedList))"
    : '';

  $totalEquipments = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT o.equipment) AS t
     FROM operations o
     WHERE $projectScopeSql
       $mineScopeSql
       AND o.equipment IS NOT NULL
       AND o.equipment <> ''
       AND o.equipment <> '0'",
    't'
  );

  $workingEquipments = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT o.equipment) AS t
     FROM operations o
     LEFT JOIN equipments e ON e.id = o.equipment
     WHERE $projectScopeSql
       $mineScopeSql
       AND o.status = '1'
       AND (e.status = '1' OR e.status IS NULL)
       $workingAvailabilityCondition
       AND o.equipment IS NOT NULL
       AND o.equipment <> ''
       AND o.equipment <> '0'",
    't'
  );

  $stoppedEquipments = max(0, intval($totalEquipments) - intval($workingEquipments));

  $driverWorkingStatusCondition = $driversHasDriverStatus
    ? " AND (d.driver_status IS NULL OR d.driver_status NOT IN ('موقوف','متوقف'))"
    : '';

  $totalOperators = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT ed.driver_id) AS t
     FROM operations o
     JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
     JOIN drivers d ON d.id = ed.driver_id
     WHERE $projectScopeSql
       $mineScopeSql",
    't'
  );

  $workingOperators = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT ed.driver_id) AS t
     FROM operations o
     JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
     JOIN drivers d ON d.id = ed.driver_id
     WHERE $projectScopeSql
       $mineScopeSql
       AND ed.status = '1'
       AND d.status = '1'
       $driverWorkingStatusCondition",
    't'
  );

  $stoppedOperators = max(0, intval($totalOperators) - intval($workingOperators));

  $supplierContractMineScope = ($sessionMineId > 0 && $supplierscontractsHasMineId)
    ? " AND sc.mine_id = $sessionMineId"
    : '';
  $supplierContractIdScope = ($sessionContractId > 0 && $supplierscontractsHasProjectContractId)
    ? " AND sc.project_contract_id = $sessionContractId"
    : '';

  $suppliersOnContract = dashboard_scalar(
    $conn,
    "SELECT COUNT(DISTINCT sc.supplier_id) AS t
     FROM supplierscontracts sc
     WHERE sc.status = '1'
       AND sc.project_id = $projectId
       $supplierContractMineScope
       $supplierContractIdScope",
    't'
  );

  if ($projectId > 0 && $operationsHasSupplierId) {
    $supplierScopeSubquery = "
      SELECT DISTINCT sc.supplier_id
      FROM supplierscontracts sc
      WHERE sc.status = '1'
        AND sc.project_id = $projectId
        $supplierContractMineScope
        $supplierContractIdScope
    ";

    $supplierBreakdownSql = "
      SELECT
        o.supplier_id,
        COALESCE(s.name, CONCAT('مورد #', o.supplier_id)) AS supplier_name,
        COUNT(DISTINCT o.equipment) AS equipments_count
      FROM operations o
      LEFT JOIN suppliers s ON s.id = o.supplier_id
      WHERE $projectScopeSql
        $mineScopeSql
        AND o.supplier_id IS NOT NULL
        AND o.supplier_id <> ''
        AND o.supplier_id <> '0'
        AND o.supplier_id IN ($supplierScopeSubquery)
      GROUP BY o.supplier_id, supplier_name
      ORDER BY equipments_count DESC, supplier_name ASC
    ";

    $supplierBreakdownRes = $conn->query($supplierBreakdownSql);
    if ($supplierBreakdownRes) {
      while ($supplierRow = $supplierBreakdownRes->fetch_assoc()) {
        $role6SupplierBreakdown[] = array(
          'supplier_name' => $supplierRow['supplier_name'],
          'equipments_count' => intval($supplierRow['equipments_count'])
        );
      }
    }
  }

  $stats = [
    ['fa-tools', intval($totalEquipments), 'إجمالي الآليات بالمشروع', 'gold'],
    ['fa-play-circle', intval($workingEquipments), 'آليات تعمل الآن', 'blue'],
    ['fa-wrench', intval($stoppedEquipments), 'آليات لا تعمل/صيانة', 'orange'],
    ['fa-id-badge', intval($totalOperators), 'إجمالي المشغلين (المشروع/المنجم)', 'teal'],
    ['fa-user-check', intval($workingOperators), 'مشغلون يعملون الآن', 'blue'],
    ['fa-user-times', intval($stoppedOperators), 'مشغلون متوقفون', 'orange'],
    ['fa-truck', intval($suppliersOnContract), 'موردون تابعون للعقد', 'purple']
  ];
} elseif ($dashboardRole=="10") {
  $eq=dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e JOIN operations o ON o.equipment=e.id WHERE e.status='1' AND $scopeOperationsByProject", 't');
  $dr=dashboard_scalar($conn, "SELECT COUNT(DISTINCT d.id) AS t FROM drivers d JOIN equipment_drivers ed ON ed.driver_id=d.id JOIN operations o ON o.equipment=ed.equipment_id WHERE d.status='1' AND $scopeOperationsByProject", 't');
  $h=dashboard_scalar($conn, "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE $scopeOperationsByProject", 't');
  $stats=[['fa-tools',$eq,'الآليات','gold'],['fa-id-badge',$dr,'المشغلين','blue'],['fa-clock',(int)$h,'الساعات','teal']];
}

$statCount = count($stats);
$linkCount = count($links);
$page_title = 'إيكوبيشن | الرئيسية';
/* ── accent colour map ── */
$accentMap = [
  'gold'   => ['bg'=>'#f7931a','soft'=>'rgba(247,147,26,.13)','grad'=>'linear-gradient(135deg,#f7931a,#e67e00)'],
  'blue'   => ['bg'=>'#2563eb','soft'=>'rgba(37,99,235,.12)',  'grad'=>'linear-gradient(135deg,#2563eb,#1d4ed8)'],
  'teal'   => ['bg'=>'#0d9488','soft'=>'rgba(13,148,136,.12)', 'grad'=>'linear-gradient(135deg,#0d9488,#0f766e)'],
  'purple' => ['bg'=>'#7c3aed','soft'=>'rgba(124,58,237,.12)', 'grad'=>'linear-gradient(135deg,#7c3aed,#6d28d9)'],
  'orange' => ['bg'=>'#ea580c','soft'=>'rgba(234,88,12,.12)',  'grad'=>'linear-gradient(135deg,#ea580c,#c2410c)'],
];

include '../inheader.php';
include '../insidebar.php';
?>
  <style>
    /* ══════════════════════════════════════════════════
       EMS Dashboard — Full brand redesign
       Palette: Black #0F1115 · Orange #F7931A
    ══════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --b:  #0f1115;
      --bm: #1a1f28;
      --bs: #252b38;
      --or: #f7931a;
      --or2:#e67e00;
      --ors:rgba(247,147,26,.13);
      --wh: #ffffff;
      --bg: #f4f6fb;
      --card:#ffffff;
      --bdr:rgba(15,17,21,.08);
      --sub:#64748b;
      --txt:#131722;
      --blue:#2563eb;
      --teal:#0d9488;
      --purp:#7c3aed;
      --deor:#ea580c;
      --danger:#dc2626;
      --r:14px; --rl:20px;
      --s1:0 2px 8px rgba(15,17,21,.07);
      --s2:0 8px 24px rgba(15,17,21,.11);
      --s3:0 18px 48px rgba(15,17,21,.15);
      --ease:.2s cubic-bezier(.4,0,.2,1);
      --hex:polygon(8% 0,92% 0,100% 50%,92% 100%,8% 100%,0 50%);
    }

    html, body {
      min-height: 100%;
      font-family: 'Tajawal','Cairo',sans-serif;
      background:
        radial-gradient(circle at 94% 8%, rgba(247,147,26,.14) 0%, transparent 28%),
        radial-gradient(circle at 6% 90%, rgba(15,17,21,.06) 0%, transparent 32%),
        var(--bg);
      color: var(--txt);
    }
    a { text-decoration: none; color: inherit; }

    /* ────────────────────────────────
       Wrapper
    ──────────────────────────────── */
    .ed {
      display: flex;
      flex-direction: column;
      gap: 20px;
      padding: 14px 18px 28px;
      min-height: calc(100vh - 20px);
    }

    /* ────────────────────────────────
       TOP BAR
    ──────────────────────────────── */
    .ed-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: var(--b);
      border-radius: var(--rl);
      padding: 10px 18px;
      box-shadow: var(--s3);
      border: 1px solid rgba(247,147,26,.2);
      position: relative;
      overflow: hidden;
      flex-shrink: 0;
    }
    .ed-top::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.03) 1px, transparent 1px);
      background-size: 18px 18px;
      pointer-events: none;
    }
    .ed-top-brand {
      display: flex;
      align-items: center;
      gap: 11px;
      position: relative;
      z-index: 1;
    }
    .ed-top-hex {
      width: 40px;
      height: 40px;
      clip-path: var(--hex);
      background: linear-gradient(135deg, var(--or), var(--or2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: #fff;
      flex-shrink: 0;
      box-shadow: 0 6px 18px rgba(247,147,26,.35);
    }
    .ed-top-sys {
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: rgba(255,255,255,.45);
    }
    .ed-top-greet {
      font-size: .9rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
    }
    .ed-top-r {
      display: flex;
      align-items: center;
      gap: 10px;
      position: relative;
      z-index: 1;
    }
    .ed-clock {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 50px;
      font-size: .8rem;
      font-weight: 700;
      color: rgba(255,255,255,.75);
    }
    .ed-clock i { color: var(--or); font-size: .72rem; }
    .ed-exit {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 7px 16px;
      background: rgba(220,38,38,.15);
      border: 1px solid rgba(220,38,38,.3);
      border-radius: 50px;
      color: #f87171;
      font-weight: 700;
      font-size: .82rem;
      transition: background var(--ease), color var(--ease), box-shadow var(--ease);
    }
    .ed-exit:hover {
      background: var(--danger);
      color: #fff;
      box-shadow: 0 6px 18px rgba(220,38,38,.3);
    }

    /* ────────────────────────────────
       HERO CARD
    ──────────────────────────────── */
    .ed-hero {
      position: relative;
      overflow: hidden;
      border-radius: var(--rl);
      background: linear-gradient(125deg, #0f1115 0%, #1a1f28 55%, #252b38 100%);
      border: 1px solid rgba(247,147,26,.3);
      padding: 24px 28px;
      box-shadow: var(--s3);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      animation: fadeSlide .5s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes fadeSlide { from { opacity:0; transform:translateY(-10px) } to { opacity:1; transform:translateY(0) } }

    .ed-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 22px 22px;
      pointer-events: none;
    }
    /* orange glow top-left */
    .ed-hero::after {
      content: '';
      position: absolute;
      left: -70px;
      top: -70px;
      width: 240px;
      height: 240px;
      background: radial-gradient(circle, rgba(247,147,26,.2) 0%, transparent 68%);
      pointer-events: none;
    }
    .ed-hero-body { position: relative; z-index: 1; }
    .ed-hero-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .ed-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 50px;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .06em;
    }
    .ed-badge-role {
      background: rgba(247,147,26,.18);
      border: 1px solid rgba(247,147,26,.4);
      color: #ffb347;
    }
    .ed-badge-role i { font-size: .5rem; color: var(--or); }
    .ed-badge-proj {
      background: rgba(37,99,235,.18);
      border: 1px solid rgba(37,99,235,.35);
      color: #93c5fd;
    }
    .ed-badge-proj i { font-size: .62rem; }

    .ed-hero-label {
      font-size: .72rem;
      color: rgba(255,255,255,.45);
      margin-bottom: 4px;
      letter-spacing: .04em;
    }
    .ed-hero-name {
      font-size: clamp(1.45rem, 3vw, 2.1rem);
      font-weight: 900;
      color: #fff;
      line-height: 1.15;
      min-height: 1.5em;
    }
    .ed-cursor {
      display: inline-block;
      width: 3px;
      height: 1.1em;
      background: var(--or);
      border-radius: 2px;
      animation: blink .75s step-end infinite;
      vertical-align: middle;
      margin-right: 3px;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
    .ed-hero-sub {
      margin-top: 8px;
      font-size: .78rem;
      color: rgba(255,255,255,.42);
    }

    /* Decorative hex cluster */
    .ed-hero-deco {
      position: relative;
      z-index: 1;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 96px;
      height: 96px;
    }
    .ed-deco-hex {
      position: absolute;
      clip-path: var(--hex);
    }
    .ed-deco-hex-outer {
      inset: 0;
      background: rgba(247,147,26,.1);
      border: 2px solid rgba(247,147,26,.25);
    }
    .ed-deco-hex-inner {
      inset: 14px;
      background: linear-gradient(135deg, var(--or), var(--or2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      box-shadow: 0 8px 24px rgba(247,147,26,.38);
    }
    /* floating sparkle particles */
    .ed-spark {
      position: absolute;
      color: #ffb347;
      pointer-events: none;
      z-index: 0;
      animation: spark-drift 3s linear forwards;
    }
    @keyframes spark-drift {
      0%  { opacity:.9; transform:translateY(0) scale(1) rotate(0deg); }
      100%{ opacity:0;  transform:translateY(80px) scale(.1) rotate(360deg); }
    }

    /* ────────────────────────────────
       QUICK LINKS
    ──────────────────────────────── */
    .ed-links-section { display: flex; flex-direction: column; gap: 8px; }
    .ed-section-hd {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: .65rem;
      font-weight: 800;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--sub);
    }
    .ed-section-hd::before {
      content: '';
      width: 4px;
      height: 14px;
      border-radius: 3px;
      background: linear-gradient(180deg, var(--or), var(--b));
      display: block;
      flex-shrink: 0;
    }
    .ed-section-hd i { color: var(--or); }

    .ed-links-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .ed-link-btn {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      padding: 9px 16px;
      background: var(--card);
      border: 1.5px solid var(--bdr);
      border-radius: 50px;
      font-size: .82rem;
      font-weight: 700;
      color: var(--txt);
      box-shadow: var(--s1);
      transition: all var(--ease);
      animation: popIn .4s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes popIn { from{opacity:0;transform:scale(.88)} to{opacity:1;transform:scale(1)} }
    .ed-link-hex {
      width: 28px;
      height: 28px;
      clip-path: var(--hex);
      background: var(--ors);
      color: var(--or);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .72rem;
      flex-shrink: 0;
      transition: background var(--ease), color var(--ease);
    }
    .ed-link-btn:hover {
      border-color: rgba(247,147,26,.35);
      background: #fffbf5;
      box-shadow: var(--s2), 0 0 0 3px rgba(247,147,26,.06);
      transform: translateY(-2px);
      color: var(--b);
    }
    .ed-link-btn:hover .ed-link-hex {
      background: var(--or);
      color: #fff;
    }

    /* ────────────────────────────────
       STAT CARDS
    ──────────────────────────────── */
    .ed-stats-section { display: flex; flex-direction: column; gap: 10px; }
    .ed-context-pill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 14px;
      border-radius: 50px;
      border: 1px solid rgba(37,99,235,.22);
      background: rgba(37,99,235,.08);
      color: #1d4ed8;
      font-size: .78rem;
      font-weight: 700;
      width: fit-content;
    }

    .ed-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
      gap: 12px;
    }

    .ed-card {
      background: var(--card);
      border-radius: var(--rl);
      border: 1.5px solid var(--bdr);
      box-shadow: var(--s1);
      overflow: hidden;
      position: relative;
      display: flex;
      flex-direction: column;
      transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
      animation: cardPop .45s cubic-bezier(.4,0,.2,1) both;
      min-height: 130px;
    }
    @keyframes cardPop { from{opacity:0;transform:translateY(12px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }
    .ed-card:nth-child(1){animation-delay:.06s}
    .ed-card:nth-child(2){animation-delay:.11s}
    .ed-card:nth-child(3){animation-delay:.16s}
    .ed-card:nth-child(4){animation-delay:.21s}
    .ed-card:nth-child(5){animation-delay:.26s}
    .ed-card:nth-child(6){animation-delay:.31s}
    .ed-card:nth-child(7){animation-delay:.36s}
    .ed-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--s2);
    }

    /* top accent strip */
    .ed-card-strip {
      height: 4px;
      width: 100%;
      flex-shrink: 0;
      border-radius: var(--rl) var(--rl) 0 0;
    }

    .ed-card-body {
      flex: 1;
      padding: 14px 14px 16px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      position: relative;
      z-index: 1;
    }

    /* hexagonal icon */
    .ed-card-ico {
      width: 46px;
      height: 46px;
      clip-path: var(--hex);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: #fff;
      flex-shrink: 0;
      transition: transform var(--ease);
    }
    .ed-card:hover .ed-card-ico { transform: scale(1.08); }

    .ed-card-info { flex: 1; min-width: 0; }
    .ed-card-num {
      font-size: clamp(1.6rem, 3.5vw, 2.3rem);
      font-weight: 900;
      line-height: 1;
      color: var(--txt);
      font-variant-numeric: tabular-nums;
    }
    .ed-card-lbl {
      margin-top: 5px;
      font-size: .75rem;
      font-weight: 600;
      color: var(--sub);
      line-height: 1.3;
    }

    /* ghost watermark */
    .ed-card-ghost {
      position: absolute;
      left: 50%;
      bottom: -4px;
      transform: translateX(-50%);
      font-size: clamp(3.5rem, 7vw, 5.5rem);
      font-weight: 900;
      line-height: 1;
      white-space: nowrap;
      pointer-events: none;
      user-select: none;
      opacity: .045;
    }

    /* ────────────────────────────────
       SUPPLIER BREAKDOWN
    ──────────────────────────────── */
    .ed-breakdown {
      background: var(--card);
      border: 1px solid rgba(247,147,26,.2);
      border-radius: var(--rl);
      box-shadow: var(--s1);
      overflow: hidden;
    }
    .ed-breakdown-hd {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 12px 16px;
      font-size: .9rem;
      font-weight: 800;
      color: var(--txt);
      background: linear-gradient(90deg, rgba(247,147,26,.1), rgba(247,147,26,.04));
      border-bottom: 1px solid rgba(247,147,26,.14);
    }
    .ed-breakdown-hd i { color: var(--or); }
    .ed-breakdown table {
      width: 100%;
      border-collapse: collapse;
    }
    .ed-breakdown th,
    .ed-breakdown td {
      padding: 10px 14px;
      text-align: right;
      font-size: .83rem;
      border-bottom: 1px solid var(--bdr);
    }
    .ed-breakdown thead th {
      background: #fffaf3;
      color: var(--sub);
      font-weight: 800;
    }
    .ed-breakdown tbody tr:last-child td { border-bottom: none; }
    .ed-breakdown tbody tr:hover td { background: #fffaf3; }
    .ed-breakdown-empty {
      padding: 16px;
      color: var(--sub);
      font-size: .83rem;
      font-weight: 700;
    }

    /* ────────────────────────────────
       RESPONSIVE
    ──────────────────────────────── */
    @media (max-width: 768px) {
      .ed { padding: 10px 10px 20px; gap: 14px; }
      .ed-top { padding: 9px 14px; }
      .ed-clock { display: none; }
      .ed-hero { padding: 18px 16px; }
      .ed-hero-deco { display: none; }
      .ed-hero-name { font-size: 1.4rem; }
      .ed-cards { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
      .ed-hero-name { font-size: 1.2rem; }
      .ed-cards { grid-template-columns: 1fr 1fr; gap: 9px; }
      .ed-card-num { font-size: 1.5rem; }
      .ed-link-btn { font-size: .78rem; padding: 8px 13px; }
    }
  </style>

<div class="ed main">

  <!-- ══ TOPBAR ══ -->
  <div class="ed-top">
    <div class="ed-top-brand">
      <div class="ed-top-hex"><i class="fas fa-layer-group"></i></div>
      <div>
        <div class="ed-top-sys">Equipation EPS</div>
        <div class="ed-top-greet">
          مرحباً، <?= htmlspecialchars($userName) ?>
          <?php if ($companyName !== ''): ?> · <?= htmlspecialchars($companyName) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="ed-top-r">
      <div class="ed-clock"><i class="fas fa-clock"></i><span id="clk">--:--</span></div>
      <a href="../logout.php" class="ed-exit"><i class="fas fa-sign-out-alt"></i><span>خروج</span></a>
    </div>
  </div>

  <!-- ══ HERO ══ -->
  <div class="ed-hero" id="heroEl">
    <div class="ed-hero-body">
      <div class="ed-hero-badges">
        <span class="ed-badge ed-badge-role"><i class="fas fa-circle"></i><?= htmlspecialchars($roleText) ?></span>
        <?php if ($projectName): ?>
        <span class="ed-badge ed-badge-proj"><i class="fas fa-project-diagram"></i><?= htmlspecialchars($projectName) ?></span>
        <?php endif; ?>
      </div>
      <div class="ed-hero-label">مرحباً بك في نظام إيكوبيشن</div>
      <div class="ed-hero-name"><span id="typed"></span><span class="ed-cursor"></span></div>
      <div class="ed-hero-sub">نتمنى لك يوماً مليئاً بالإنجازات — <?= date('l، j F Y') ?></div>
    </div>
    <div class="ed-hero-deco">
      <div class="ed-deco-hex ed-deco-hex-outer"></div>
      <div class="ed-deco-hex ed-deco-hex-inner">🏆</div>
    </div>
  </div>

  <!-- ══ QUICK LINKS ══ -->
  <?php if (!empty($links)): ?>
  <div class="ed-links-section">
    <div class="ed-section-hd"><i class="fas fa-bolt"></i>الوصول السريع</div>
    <div class="ed-links-grid">
      <?php foreach ($links as $i => $lnk):
        $href = $lnk[0] ?? '#';
        $ico  = $lnk[1] ?? 'fa-link';
        $lbl  = $lnk[2] ?? 'رابط';
      ?>
      <a href="<?= htmlspecialchars($href) ?>" class="ed-link-btn" style="animation-delay:<?= $i * .055 ?>s">
        <span class="ed-link-hex"><i class="fas <?= $ico ?>"></i></span>
        <span><?= htmlspecialchars($lbl) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ STATS ══ -->
  <?php if (!empty($stats)): ?>
  <div class="ed-stats-section">
    <div class="ed-section-hd"><i class="fas fa-chart-bar"></i>الإحصائيات الحالية</div>

    <?php if ($role == "6" && $role6ContextText !== ''): ?>
    <div class="ed-context-pill"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($role6ContextText) ?></div>
    <?php endif; ?>

    <div class="ed-cards">
      <?php foreach ($stats as $st):
        $ico    = $st[0] ?? 'fa-chart-bar';
        $val    = $st[1] ?? 0;
        $lbl    = $st[2] ?? 'إحصائية';
        $accent = $st[3] ?? 'blue';
        $num    = (int) str_replace(',', '', $val);
        $disp   = number_format($num);
        $ac     = $accentMap[$accent] ?? $accentMap['blue'];
      ?>
      <div class="ed-card" style="border-top-color:<?= $ac['bg'] ?>20">
        <div class="ed-card-strip" style="background:<?= $ac['grad'] ?>"></div>
        <div class="ed-card-body">
          <div class="ed-card-ico" style="background:<?= $ac['grad'] ?>; box-shadow:0 6px 18px <?= $ac['bg'] ?>40">
            <i class="fas <?= $ico ?>"></i>
          </div>
          <div class="ed-card-info">
            <div class="ed-card-num" data-to="<?= $num ?>"><?= $disp ?></div>
            <div class="ed-card-lbl"><?= htmlspecialchars($lbl) ?></div>
          </div>
        </div>
        <div class="ed-card-ghost" style="color:<?= $ac['bg'] ?>"><?= $disp ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ SUPPLIER BREAKDOWN (role 6) ══ -->
  <?php if ($role == "6"): ?>
  <div class="ed-breakdown">
    <div class="ed-breakdown-hd"><i class="fas fa-truck-loading"></i>الموردون التابعون للعقد · الآليات لكل مورد</div>
    <?php if (!empty($role6SupplierBreakdown)): ?>
    <table>
      <thead>
        <tr><th>المورد</th><th>عدد الآليات المقدمة</th></tr>
      </thead>
      <tbody>
        <?php foreach ($role6SupplierBreakdown as $sr): ?>
        <tr>
          <td><?= htmlspecialchars($sr['supplier_name']) ?></td>
          <td><strong><?= number_format(intval($sr['equipments_count'])) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="ed-breakdown-empty"><i class="fas fa-info-circle"></i> لا توجد بيانات مورّدين مرتبطة بهذا النطاق.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- .ed -->

<script>
/* Clock */
(function tick(){
  const n=new Date(),el=document.getElementById('clk');
  if(el) el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
  setTimeout(tick,15000);
})();

/* Typewriter */
const typedEl=document.getElementById('typed');
const fullName=<?= json_encode($userName) ?>;
let ci=0;
(function t(){if(!typedEl)return;typedEl.textContent=fullName.slice(0,ci++);if(ci<=fullName.length)setTimeout(t,65);})();

/* Count-up */
document.querySelectorAll('.ed-card-num[data-to]').forEach(el=>{
  const target=parseInt(el.dataset.to);if(!target)return;
  const steps=32,dur=900;let i=0;
  const iv=setInterval(()=>{
    i++;el.textContent=Math.round(target*Math.min(i/steps,1)).toLocaleString('ar-EG');
    if(i>=steps){el.textContent=target.toLocaleString('ar-EG');clearInterval(iv);}
  },dur/steps);
});

/* Sparkle particles on hero */
const heroEl=document.getElementById('heroEl');
function spark(){
  if(!heroEl)return;
  const s=document.createElement('i');
  s.className='fas fa-star ed-spark';
  s.style.cssText=`left:${5+Math.random()*90}%;top:${5+Math.random()*55}%;font-size:${5+Math.random()*7}px;animation-duration:${1.8+Math.random()*2}s`;
  heroEl.appendChild(s);setTimeout(()=>s.remove(),3600);
}
setInterval(spark,950);
</script>
</body>
</html>

