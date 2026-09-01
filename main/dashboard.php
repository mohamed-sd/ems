<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
include "../config.php";
require_once dirname(__FILE__) . '/../includes/dynamic_nav.php';
require_once dirname(__FILE__) . '/../includes/role_board.php';

// ── لوحة الدور (UX-01 §4: «أول ما يُفتح — لوحة الدور») ─────────────────────
// الدورُ المفعَّل في EMS_ROLE_BOARD_ROLES تحوّله «الرئيسية» للوحته مباشرةً
// (قرار المالك 2026-07-26)، وسائرُ الأدوار على هذه اللوحة العامة حرفيًّا —
// والرجوعُ بحذف الدور من العلم بلا نشر كود (نمط السايدبار الموحّد نفسه).
//
// وحين تكون لوحةُ الدورِ **هذه الصفحةَ نفسَها** (إدارة التشغيل — قرار المالك
// 2026-08-21) فلا تحويلَ ولا حلقةَ إعادةِ توجيه: يُحفظ دورُ الإعدادِ في
// $dash_board_role، وتُبنى مكوّناتُه السبعةُ أدناه داخلَ الصفحةِ بلغةِ تصميمِها.
$dash_board_role = 0;
if (isset($_SESSION['user']['role']) && roleBoardEnabled($_SESSION['user']['role'])) {
  $rb_route = roleBoardRoute($_SESSION['user']['role']);
  if ($rb_route === null) {
    // الفرعيُّ يرث لوحةَ أبيه (قرار المالك) — والجلسةُ لا تحمل الأب فيُجلب هنا
    try {
      $rb_parent = ems_tenant_db()->selectOne('roles', array('columns' => array('parent_role_id'),
          'where' => array('id' => intval($_SESSION['user']['role']))));
      if ($rb_parent && !empty($rb_parent['parent_role_id'])) {
        $rb_route = roleBoardRoute($_SESSION['user']['role'], $rb_parent['parent_role_id']);
      }
    } catch (\Throwable $t) { error_log('dashboard role board parent: ' . $t->getMessage()); }
  }
  if ($rb_route === 'main/dashboard.php') {
    $dash_board_role = roleBoardConfigRole(ems_tenant_db(), $_SESSION['user']['role']);
  } elseif ($rb_route !== null) {
    header('Location: ../' . $rb_route); exit();
  }
}

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

/* ════════════════  DATA LAYER  ════════════════ */
$role = $_SESSION['user']['role'];
$userName = $_SESSION['user']['name'];
$roleText = "غير معروف";
$companyId = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$companyName = '';

// العزل عبر بوابة المستأجر (K9 · جولة اللوحة المخصصة 2026-07-16): سقطت مساعِدات
// SHOW COLUMNS/TABLES وقيَم أعلامها مثبَّتة من القاعدة (mine_id غائب عن operations،
// وmine_id غائب عن supplierscontracts — ففروعهما ميتة)، وسقط dashboard_scalar الخام
// لصالح نظيره البوّابي أدناه. السوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: 1=1).
$dash_is_super = (strval($role) === '-1');
$dash_gate = $dash_is_super ? ems_tenant_db()->forAllTenants('dashboard super') : ems_tenant_db();

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
if (!function_exists('dashboard_gate_scalar')) {
  /** نظير dashboard_scalar عبر البوابة: صفرٌ عند أي فشل (سلوك الأصل نفسه). */
  function dashboard_gate_scalar($g, array $decl, $sql, array $params = array())
  {
    try {
      $rows = $g->scopedQuery($decl, $sql, $params);
      return (isset($rows[0]) && isset($rows[0]['t'])) ? $rows[0]['t'] : 0;
    } catch (\Throwable $t) {
      error_log('dashboard.php scalar: ' . $t->getMessage());
      return 0;
    }
  }
}

$projectClientColumn = 'client_id';

if ($companyId > 0) {
  // [مُستثنى موثَّق — قراءة اسم شركة الجلسة] admin_companies جدول منصّةٍ مقيَّد تعاقديًا
  // (T_RESTRICTED بانتظار عقد دفعة المزوّد admin/)، والمقروء اسمُ عرض شركةِ المستخدم
  // نفسه بمعرّف جلسته — تبقى القراءة خامًا حتى تُعرَّف قناة المنصّة (كما في profile.php).
  $cols = ['company_name_ar', 'company_name', 'name'];
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

$roleId = intval($role);
try {
  $dash_role_row = $dash_gate->selectOne('roles', array('columns' => array('name'), 'where' => array('id' => $roleId)));
  if ($dash_role_row) { $roleText = $dash_role_row['name']; }
} catch (\Throwable $t) { error_log('dashboard.php role name: ' . $t->getMessage()); }

$dashboardRole = strval($role);
try {
  $dash_parent_row = $dash_gate->selectOne('roles', array('columns' => array('parent_role_id'), 'where' => array('id' => $roleId)));
  if ($dash_parent_row) {
    $pid = intval($dash_parent_row['parent_role_id'] ?? 0);
    if ($pid > 0)
      $dashboardRole = strval($pid);
  }
} catch (\Throwable $t) { error_log('dashboard.php parent role: ' . $t->getMessage()); }

$projectId = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$projectName = '';
if ($projectId > 0) {
  // (كان النطاق سلسلة المنشئين الموروثة قبل عمود company_id — البوابة تعزل بالعمود مباشرة)
  try {
    $dash_prj_rows = $dash_gate->scopedQuery(array('scope' => array('project' => 'project')),
      "SELECT name FROM project WHERE id=? AND {TENANT_SCOPE} LIMIT 1", array($projectId));
    if (!empty($dash_prj_rows)) { $projectName = $dash_prj_rows[0]['name']; }
  } catch (\Throwable $t) { error_log('dashboard.php project name: ' . $t->getMessage()); }
}

$dynamicLinks = getDynamicNavLinks($conn, $role);

// روابط «الوصول السريع» = فقط الصفحات المؤشَّرة بـ is_quick (تُضبط من admin/permissions/modules.php).
// العمود قائم بالترحيلات — سقط فحصه، والقراءة عبر البوابة (modules مرجع عام).
$dashHasIsQuick = true;
$dashQuickIds = [];
try {
  foreach ($dash_gate->select('modules', array('columns' => array('id'), 'where' => array('is_quick' => 1))) as $qq) {
    $dashQuickIds[(int) $qq['id']] = true;
  }
} catch (\Throwable $t) { error_log('dashboard.php quick links: ' . $t->getMessage()); }
$links = [];
foreach ($dynamicLinks as $l) {
  if ($dashHasIsQuick && empty($dashQuickIds[(int) ($l['id'] ?? 0)])) {
    continue; // ليست ضمن الوصول السريع
  }
  $links[] = [
    '../' . $l['code'],
    $l['name'],
    !empty($l['icon']) ? $l['icon'] : 'fa fa-link'
  ];
}

// ── الوصول السريع بمنطق المجموعات ──────────────────────────────────────────
// البلاطات = روابط المجموعة المختارة في السايدبار (أوّل مجموعة افتراضًا، ثم
// تتبدّل بنقر رأس أي مجموعة). تُطبع كل المجموعات خادميًّا وتُخفى إلا المختارة،
// فالتبديل فوريٌّ بلا طلبٍ للشبكة — وأكبر دورٍ عندنا 30 رابطًا فالحجم تافه.
// روابط المجموعة كلها تظهر هنا: is_quick تبقى حاكمةً للحالة القديمة وحدها
// (دورٌ بلا مجموعاتٍ إطلاقًا) فلا ينكسر شيء قبل إنشاء أوّل مجموعة.
$dashGroupTiles = [];   // key => ['name' => .., 'tiles' => [[href,label,icon], ..]]
$dashGroups = function_exists('getNavGroups') ? getNavGroups($conn, $role) : [];
if (!empty($dashGroups)) {
  $dashByGroup = [];
  foreach ($dynamicLinks as $l) {
    $gid = isset($l['group_id']) && $l['group_id'] !== null ? (int) $l['group_id'] : 0;
    if ($gid > 0) {
      $dashByGroup[$gid][] = $l;
    }
  }
  foreach ($dashGroups as $g) {
    $gid = (int) $g['id'];
    if (empty($dashByGroup[$gid])) {
      continue; // مجموعة بلا روابط مرئية — لا تُطبع (مطابقةً للسايدبار)
    }
    $tiles = [];
    foreach ($dashByGroup[$gid] as $l) {
      $tiles[] = ['../' . $l['code'], $l['name'], !empty($l['icon']) ? $l['icon'] : 'fa fa-link'];
    }
    $dashGroupTiles['g' . $gid] = ['name' => $g['name'], 'tiles' => $tiles];
  }
}

// (كانت هنا نُطُق سلسلة المنشئين الموروثة $sc/$sp/$so قبل أعمدة company_id —
// البوابة تعزل بالعمود مباشرة، وقائمة مشاريع الشركة تُجلَب مرةً لتعويض استعلامات
// «IN(SELECT id FROM project ...)» الفرعية بنمط قائمة المعرّفات المعتمد.)
$dashProjectIds = array();
try {
  foreach ($dash_gate->select('project', array('columns' => array('id'), 'includeDeleted' => true)) as $dash_p) {
    $dashProjectIds[] = intval($dash_p['id']);
  }
} catch (\Throwable $t) { error_log('dashboard.php project ids: ' . $t->getMessage()); }
$dashProjectIdsSql = !empty($dashProjectIds) ? implode(',', $dashProjectIds) : '0';

// أعلام الأعمدة مثبَّتة من القاعدة (سقطت فحوص SHOW COLUMNS):
$hasMineId = false;  // operations.mine_id غير موجود
$hasSuppId = true;
$hasAvail = true;
$hasDrvSt = true;
$hasSCMine = false;  // supplierscontracts.mine_id غير موجود
$hasSCPCId = true;
$contractsProjectCol = 'project_id';

$sessionMineId = isset($_SESSION['user']['mine_id']) ? intval($_SESSION['user']['mine_id']) : 0;
$sessionContractId = isset($_SESSION['user']['contract_id']) ? intval($_SESSION['user']['contract_id']) : 0;

$stats = [];
$role6SupplierBreakdown = [];
$role6ContextText = '';
$opsProjectCol = 'project_id';

// الدور 9 (الرئيس التنفيذي) يرى عدادات الشركة الشاملة — كان ساقطًا من السلسلة
// فيهبط على بديل الأصفار المثبَّتة (UI-DEF-02: العداد صفر والعقود 250).
if ($dashboardRole == "0" || $dashboardRole == "1" || $dashboardRole == "9" || $dashboardRole == "12") {
  $c = dashboard_gate_scalar($dash_gate, array('scope' => array('clients' => 'clients')),
    "SELECT COUNT(*) AS t FROM clients WHERE status='نشط' AND {TENANT_SCOPE}");
  $p = dashboard_gate_scalar($dash_gate, array('scope' => array('project' => 'project')),
    "SELECT COUNT(*) AS t FROM project WHERE status='1' AND {TENANT_SCOPE}");
  $activeContracts = dashboard_gate_scalar($dash_gate, array('scope' => array('c' => 'contracts')),
    "SELECT COUNT(*) AS t
       FROM contracts c
       WHERE (c.status='1' OR c.status=1)
         AND c.is_deleted = 0
         AND {TENANT_SCOPE}
         AND c.$contractsProjectCol IN($dashProjectIdsSql)");
  $u = $companyId > 0
    ? dashboard_gate_scalar($dash_gate, array('scope' => array('users' => 'users')),
        "SELECT COUNT(*) AS t FROM users WHERE role!='-1' AND {TENANT_SCOPE}")
    : dashboard_gate_scalar($dash_gate, array('scope' => array('users' => 'users')),
        "SELECT COUNT(*) AS t FROM users WHERE parent_id='0' AND role!='-1' AND {TENANT_SCOPE}");
  $stats = [['fa-users', $c, 'العملاء', 'or'], ['fa-project-diagram', $p, 'المشاريع', 'or'], ['fa-file-contract', $activeContracts, 'العقود النشطة', 'ok'], ['fa-user-shield', $u, 'المستخدمون', 'or']];
} elseif ($dashboardRole == "2") {
  $s = dashboard_gate_scalar($dash_gate, array('scope' => array('s' => 'suppliers')),
    "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE 1=1 AND {TENANT_SCOPE}");
  $e = dashboard_gate_scalar($dash_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE 1=1 AND {TENANT_SCOPE}");
  $co = dashboard_gate_scalar($dash_gate, array('scope' => array('supplierscontracts' => 'supplierscontracts')),
    "SELECT COUNT(*) AS t FROM supplierscontracts WHERE project_id IN($dashProjectIdsSql) AND {TENANT_SCOPE}");
  $stats = [['fa-truck', $s, 'الموردون', 'or'], ['fa-tools', $e, 'الآليات', 'or'], ['fa-file-contract', $co, 'العقود', 'ok']];
} elseif ($dashboardRole == "3") {
  $s = dashboard_gate_scalar($dash_gate, array('scope' => array('s' => 'suppliers')),
    "SELECT COUNT(DISTINCT s.id) AS t FROM suppliers s WHERE 1=1 AND {TENANT_SCOPE}");
  $eq = dashboard_gate_scalar($dash_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE 1=1 AND {TENANT_SCOPE}");
  $stopListRole3 = "'معطلة','معطلة مؤقتاً','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة','مبيعة/مسحوبة'";

  $ao = dashboard_gate_scalar($dash_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM equipments e
     WHERE 1=1 AND {TENANT_SCOPE}" .
    " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopListRole3))");

  $bo = max(0, intval($eq) - intval($ao));
  $stats = [['fa-tools', $eq, 'إجمالي المعدات', 'or'], ['fa-play-circle', $ao, 'تعمل الآن', 'ok'], ['fa-exclamation-triangle', $bo, 'معطلة', 'err'], ['fa-truck', $s, 'الموردون', 'or']];
} elseif ($dashboardRole == "4") {
  $dr = dashboard_gate_scalar($dash_gate, array('scope' => array('d' => 'employees')),
    "SELECT COUNT(DISTINCT d.id) AS t FROM employees d WHERE 1=1 AND {TENANT_SCOPE}");
  $ad = dashboard_gate_scalar($dash_gate, array('scope' => array('d' => 'employees', 'ed' => 'equipment_drivers')),
    "SELECT COUNT(DISTINCT d.id) AS t FROM employees d JOIN equipment_drivers ed ON d.id=ed.employee_id WHERE ed.status='1' AND {TENANT_SCOPE}");
  $stats = [['fa-id-badge', $dr, 'إجمالي المشغلين', 'or'], ['fa-user-check', $ad, 'يعملون الآن', 'ok'], ['fa-user-clock', $dr - $ad, 'خاملون', 'warn']];
} elseif ($dashboardRole == "5") {
  $sv = $companyId > 0
    ? dashboard_gate_scalar($dash_gate, array('scope' => array('users' => 'users')),
        "SELECT COUNT(*) AS t FROM users WHERE role IN('6','7','8','9') AND {TENANT_SCOPE}")
    : dashboard_gate_scalar($dash_gate, array('scope' => array('users' => 'users')),
        "SELECT COUNT(*) AS t FROM users WHERE role IN('6','7','8','9') AND {TENANT_SCOPE}");
  $h = dashboard_gate_scalar($dash_gate, array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
    "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN operations o ON t.operator=o.id WHERE 1=1 AND {TENANT_SCOPE}");
  $ah = dashboard_gate_scalar($dash_gate, array('scope' => array('t' => 'timesheet', 'ta' => 'timesheet_approvals')),
    "SELECT SUM(t.total_work_hours) AS t FROM timesheet t JOIN timesheet_approvals ta ON t.id=ta.timesheet_id AND approval_level='4' WHERE 1=1 AND {TENANT_SCOPE}");
  $stats = [['fa-users-cog', $sv, 'المشرفون', 'or'], ['fa-clock', (int) $h, 'ساعات العمل', 'or'], ['fa-check-circle', (int) $ah, 'الساعات المعتمدة', 'ok']];
} elseif ($dashboardRole == "6") {
  $pSql = $projectId > 0 ? "o.project_id='$projectId'" : "1=0";
  // (mSql الموروث سقط: operations.mine_id غير موجود أصلًا فكان الشرط فارغًا دائمًا)

  $totEq = dashboard_gate_scalar($dash_gate,
    array('scope' => array('equipments' => 'equipments'), 'enrich' => array('operations' => 'operations')),
    "SELECT COUNT(*) AS t FROM `equipments` WHERE {TENANT_SCOPE} AND id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = ? )", array($projectId));
  $wrkEq = dashboard_gate_scalar($dash_gate,
    array('scope' => array('equipments' => 'equipments'), 'enrich' => array('operations' => 'operations')),
    "SELECT COUNT(*) AS t FROM `equipments` WHERE {TENANT_SCOPE} AND id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = ? AND operations.status='1')", array($projectId));

  $stpEq = max(0, intval($totEq) - intval($wrkEq));

  $dCond = " AND(d.employee_status IS NULL OR d.employee_status NOT IN('موقوف','متوقف'))";
  $totOp = dashboard_gate_scalar($dash_gate,
    array('scope' => array('o' => 'operations', 'ed' => 'equipment_drivers', 'd' => 'employees')),
    "SELECT COUNT(DISTINCT ed.employee_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN employees d ON d.id=ed.employee_id WHERE $pSql AND {TENANT_SCOPE}");
  $wrkOp = dashboard_gate_scalar($dash_gate,
    array('scope' => array('o' => 'operations', 'ed' => 'equipment_drivers', 'd' => 'employees')),
    "SELECT COUNT(DISTINCT ed.employee_id) AS t FROM operations o JOIN equipment_drivers ed ON ed.equipment_id=o.equipment JOIN employees d ON d.id=ed.employee_id WHERE $pSql AND {TENANT_SCOPE} AND ed.status='1' AND d.status='1'$dCond");
  $stpOp = max(0, intval($totOp) - intval($wrkOp));
  // (scMine الموروث سقط: supplierscontracts.mine_id غير موجود أصلًا)
  $scCid = ($sessionContractId > 0 && $hasSCPCId) ? " AND sc.project_contract_id=$sessionContractId" : "";
  $supCnt = dashboard_gate_scalar($dash_gate, array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(DISTINCT sc.supplier_id) AS t FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scCid AND {TENANT_SCOPE}");
  if ($projectId > 0 && $hasSuppId) {
    try {
      $dash_br_rows = $dash_gate->scopedQuery(
        array('scope' => array('o' => 'operations'), 'enrich' => array('s' => 'suppliers', 'sc' => 'supplierscontracts')),
        "SELECT o.supplier_id,COALESCE(s.name,CONCAT('مورد #',o.supplier_id)) AS supplier_name,COUNT(DISTINCT o.equipment) AS equipments_count FROM operations o LEFT JOIN suppliers s ON s.id=o.supplier_id WHERE $pSql AND {TENANT_SCOPE} AND o.supplier_id IS NOT NULL AND o.supplier_id<>'' AND o.supplier_id<>'0' AND o.supplier_id IN(SELECT DISTINCT sc.supplier_id FROM supplierscontracts sc WHERE sc.status='1' AND sc.project_id=$projectId$scCid) GROUP BY o.supplier_id,supplier_name ORDER BY equipments_count DESC,supplier_name ASC");
      foreach ($dash_br_rows as $row)
        $role6SupplierBreakdown[] = ['supplier_name' => $row['supplier_name'], 'equipments_count' => intval($row['equipments_count'])];
    } catch (\Throwable $t) { error_log('dashboard.php r6 breakdown: ' . $t->getMessage()); }
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

  $eq = dashboard_gate_scalar($dash_gate, array('scope' => array('o' => 'operations', 'e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id = o.equipment
     WHERE $projectEqScope AND {TENANT_SCOPE}
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'");

  $ao = dashboard_gate_scalar($dash_gate, array('scope' => array('o' => 'operations', 'e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id = o.equipment
     WHERE $projectEqScope AND {TENANT_SCOPE}
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'" .
    " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopListRole10))");

  $bo = max(0, intval($eq) - intval($ao));
  $stats = [
    ['fa-tools', $eq, 'إجمالي المعدات', 'or'],
    ['fa-play-circle', $ao, 'تعمل الآن', 'ok'],
    ['fa-exclamation-triangle', $bo, 'معطلة', 'err']
  ];
} elseif ($dashboardRole == "17") {
  // الإدارة المالية — الجدول قائم ومسجَّل في البوابة (سقط فحص وجوده الذاتي)،
  // والعزل بالشركة تحقنه البوابة بدل نص company_id الموروث.
  $dash_fe = array('scope' => array('fin_financial_events' => 'fin_financial_events'));
  $evTotal = dashboard_gate_scalar($dash_gate, $dash_fe, "SELECT COUNT(*) AS t FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND {TENANT_SCOPE}");
  $evPend  = dashboard_gate_scalar($dash_gate, $dash_fe, "SELECT COUNT(*) AS t FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND {TENANT_SCOPE} AND state IN('dept_review','fin_review','audited')");
  $evAppr  = dashboard_gate_scalar($dash_gate, $dash_fe, "SELECT COUNT(*) AS t FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND {TENANT_SCOPE} AND state IN('approved','posted','settled')");
  $evAmt   = dashboard_gate_scalar($dash_gate, $dash_fe, "SELECT COALESCE(SUM(amount),0) AS t FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND {TENANT_SCOPE} AND state<>'rejected'");
  $stats = [
    ['fa-file-invoice-dollar', (int) $evTotal, 'إجمالي الأحداث المالية', 'or'],
    ['fa-hourglass-half', (int) $evPend, 'بانتظار الاعتماد', 'warn'],
    ['fa-check-circle', (int) $evAppr, 'معتمدة/مقيدة', 'ok'],
    ['fa-coins', number_format((float) $evAmt, 0), 'إجمالي القيمة', 'or']
  ];
}

/* ════════════════  DASHBOARD ANALYTICS  ════════════════ */
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
// نطاق العمليات: بالمشروع للجلسات المشروعية، وإلا فالعزل بالشركة تحقنه البوابة
// (كان o.company_id نصًّا — وشرطُ o.id IS NOT NULL يحفظ قلب LEFT إلى INNER الذي كان
// يحدثه شرط WHERE الموروث على o). فرع سلسلة المنشئين الموروث سقط (العمود قائم).
$opsScope = "1=1";
if ($projectId > 0) {
  $opsScope = "o.$opsProjectCol = " . intval($projectId);
} elseif ($companyId > 0) {
  $opsScope = "o.id IS NOT NULL";
}

$stopStatuses = "'معطلة','معطلة مؤقتاً','تحت الصيانة','في الصيانة','موقوفة للصيانة','متوقفة','موقوفة','مبيعة/مسحوبة'";
$analyticsTotalEquip = 0;
$analyticsActiveEquip = 0;

if ($projectId > 0) {
  $analyticsTotalEquip = dashboard_gate_scalar($dash_gate, array('scope' => array('o' => 'operations', 'e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id=o.equipment
     WHERE $opsScope AND {TENANT_SCOPE}
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'");

  $analyticsActiveEquip = dashboard_gate_scalar($dash_gate, array('scope' => array('o' => 'operations', 'e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t
     FROM operations o
     JOIN equipments e ON e.id=o.equipment
     WHERE $opsScope AND {TENANT_SCOPE}
       AND o.equipment IS NOT NULL
       AND o.equipment<>''
       AND o.equipment<>'0'" .
    " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopStatuses))");
} else {
  $analyticsTotalEquip = dashboard_gate_scalar($dash_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE 1=1 AND {TENANT_SCOPE}");
  $analyticsActiveEquip = dashboard_gate_scalar($dash_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.id) AS t FROM equipments e WHERE 1=1 AND {TENANT_SCOPE}" .
    " AND (e.availability_status IS NULL OR e.availability_status='' OR e.availability_status NOT IN($stopStatuses))");
}

$analyticsInactiveEquip = max(0, intval($analyticsTotalEquip) - intval($analyticsActiveEquip));

$dash_ts_decl = array('scope' => array('t' => 'timesheet'), 'enrich' => array('o' => 'operations'));
$analyticsMonthWorkHours = dashboard_gate_scalar($dash_gate, $dash_ts_decl,
  "SELECT COALESCE(SUM(t.total_work_hours),0) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope AND {TENANT_SCOPE}
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'");

$analyticsMonthBreakdownHours = dashboard_gate_scalar($dash_gate, $dash_ts_decl,
  "SELECT COALESCE(SUM(t.total_fault_hours),0) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope AND {TENANT_SCOPE}
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'");

$analyticsBreakdownCount = dashboard_gate_scalar($dash_gate, $dash_ts_decl,
  "SELECT COUNT(*) AS t
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope AND {TENANT_SCOPE}
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'
     AND IFNULL(t.total_fault_hours,0) > 0");

$analyticsPendingRequests = 0;
{
  // [مُستثنى موثَّق — عائلة الاعتمادات] approval_requests مصنَّفة restricted في العقد
  // (تُهاجَر أخيرًا مع الدوام بإذنٍ صريح) — عدّادُ المعلَّق يبقى خامًا كسابقات
  // approval_workflow_rules؛ نطاق الشركة اليدوي الموروث (EXISTS users) قائمٌ كما هو.
  // (شرط ar.project_id الموروث سقط: العمود غير موجود أصلًا فكان الفرع ميتًا.)
  $pendingScope = "ar.status='pending'";
  if ($companyId > 0) {
    $pendingScope .= " AND EXISTS(SELECT 1 FROM users ux WHERE ux.id=ar.requested_by AND ux.company_id=" . intval($companyId) . ")";
  }
  $dash_ar_res = $conn->query("SELECT COUNT(*) AS t FROM approval_requests ar WHERE $pendingScope");
  if ($dash_ar_res && ($dash_ar_row = $dash_ar_res->fetch_assoc())) {
    $analyticsPendingRequests = $dash_ar_row['t'];
  }
}

$analyticsTrendLabels = [];
$analyticsTrendWork = [];
$analyticsTrendFault = [];
$trendMap = [];

$trendRes = array();
try {
  $trendRes = $dash_gate->scopedQuery($dash_ts_decl,
    "SELECT DATE_FORMAT(STR_TO_DATE(t.date, '%Y-%m-%d'), '%Y-%m-%d') AS day_key,
          COALESCE(SUM(t.total_work_hours),0) AS work_sum,
          COALESCE(SUM(t.total_fault_hours),0) AS fault_sum
   FROM timesheet t
   LEFT JOIN operations o ON o.id=t.operator
   WHERE $opsScope AND {TENANT_SCOPE}
     AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$monthStart' AND '$monthEnd'
   GROUP BY day_key
   ORDER BY day_key ASC");
} catch (\Throwable $t) { error_log('dashboard.php trend: ' . $t->getMessage()); }

foreach ($trendRes as $tr) {
  $trendMap[$tr['day_key']] = [
    'work' => (float) $tr['work_sum'],
    'fault' => (float) $tr['fault_sum']
  ];
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

/* ════════  لوحةُ الدورِ داخلَ «الرئيسية» (UX-01 §5 — المكوّناتُ السبعة)  ════════
   الحسابُ كلُّه من المحرّك الموحّد roleBoardBuild، والعرضُ أدناه بلغةِ تصميمِ
   هذه الشاشةِ (‎shot-*‎) لا بقالبِ اللوحةِ العامة — فالمحتوى واحدٌ والثوبُ ثوبُها. */
$dash_board = null;
if ($dash_board_role > 0) {
  $dash_board = roleBoardBuild($conn, $dash_gate, $dash_board_role, isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0);

  /* حارسُ اللوحةِ المُضمَّنة: التضمينُ لا يُسقط قفلًا كانت الشاشةُ تحمله.
     كلُّ لوحةٍ تُعلن في إعدادها الشاشةَ التي يحكم `can_view` عليها رؤيتَها
     (`perm`) — اللوحةُ العامةُ افتراضًا، ولوحةُ المدير المالي لمن نُقلت
     لوحتُه من `Finance/cfo_daily_board_fin.php`.
     ◆ **المقيسُ الذي فرض هذا الحارس**: الأدوار 31 و34 و35 (رئيس الحسابات ·
       منفّذ المدفوعات · معدُّ المطابقة) أبناءُ الدور 17 **بلا صفٍّ إطلاقًا**
       في `role_permissions` للوحةِ المالية — فكانت الشاشةُ تردُّهم، ولولا
       هذا الحارسُ لورثوا اللوحةَ داخلَ «الرئيسية» بلا فحص. */
  if ($dash_board && !empty($dash_board['perm'])) {
    require_once __DIR__ . '/../includes/permissions_helper.php';
    $dash_board_perm = check_page_permissions($conn, $dash_board['perm']);
    if (empty($dash_board_perm['can_view'])) { $dash_board = null; }
  }
}

$AC = [
  'or' => ['bg' => 'var(--dash-tone-or-bg)', 'soft' => 'var(--dash-tone-or-soft)', 'text' => 'var(--dash-tone-or-text)', 'ico' => 'var(--dash-tone-or-bg)'],
  'ok' => ['bg' => 'var(--dash-tone-ok-bg)', 'soft' => 'var(--dash-tone-ok-soft)', 'text' => 'var(--dash-tone-ok-text)', 'ico' => 'var(--dash-tone-ok-bg)'],
  'warn' => ['bg' => 'var(--dash-tone-warn-bg)', 'soft' => 'var(--dash-tone-warn-soft)', 'text' => 'var(--dash-tone-or-text)', 'ico' => 'var(--dash-tone-warn-bg)'],
  'err' => ['bg' => 'var(--dash-tone-err-bg)', 'soft' => 'var(--dash-tone-err-soft)', 'text' => 'var(--dash-tone-err-text)', 'ico' => 'var(--dash-tone-err-bg)'],
];

$page_title = 'Equipation | الرئيسية';
// Dashboard exception: deep-yellow top bar + the wide logo (see includes/topbar.php).
$ems_topbar_variant = 'dashboard';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="ems-dash main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
/* DASH-V2 ⑦ · BEGIN — الأصل: htmlspecialchars('Dashboard', ENT_QUOTES, 'UTF-8') */
$header_title_html = htmlspecialchars('لوحة التحكم', ENT_QUOTES, 'UTF-8');
/* DASH-V2 ⑦ · END */
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا أرقام تشغيلية محسوبة لهذا الدور اليوم', 'افتح شاشة العمليات أو القيد اليومي — والمؤشرات تحسب مع أول حركة مسجلة');
?>
<style>
/* UXW-01 ①: لوحةُ ألوانِ رسومِ اللوحةِ صارت رموزًا تُقرأ من CSS لا قيمًا مثبَّتةً
   في جافاسكربت — والاحتياطيُّ الحرفيُّ لكلِّ رمزٍ يضمن صفرَ تغييرٍ مرئيّ.
   (نمطُ Employees/employee_profile.php المقيس — البادئةُ هنا --dash-*) */
:root {
  --dash-tick:            var(--c-rgba252525070);
  --dash-font-ink:        var(--c-rgba252525075);
  --dash-grid:            var(--c-rgba000008);
  --dash-eq-active:       var(--c-22c55e);
  --dash-eq-stopped:      var(--c-ef4444);
  --dash-bar-work:        var(--c-rgba2471472607);
  --dash-bar-fault:       var(--c-rgba239686806);
  --dash-line-work:       var(--c-f7931a);
  --dash-line-work-fill:  var(--c-rgba24714726008);
  --dash-line-fault:      var(--c-ef4444);
  --dash-line-fault-fill: var(--c-rgba2396868006);
  --dash-tone-or-bg:   var(--c-f7931a);
  --dash-tone-or-soft: var(--c-fff4e6);
  --dash-tone-or-text: var(--c-b45309);
  --dash-tone-ok-bg:   var(--c-16a34a);
  --dash-tone-ok-soft: var(--c-f0fdf4);
  --dash-tone-ok-text: var(--c-15803d);
  --dash-tone-warn-bg:   var(--c-d97706);
  --dash-tone-warn-soft: var(--c-fffbeb);
  --dash-tone-err-bg:   var(--c-dc2626);
  --dash-tone-err-soft: var(--c-fef2f2);
  --dash-tone-err-text: var(--c-b91c1c);
}
</style>
<!-- ══════════════════════════════════════════════════════════════════════════
     DASH-V2 · BEGIN — تصميمٌ تحت التقييم للوحةِ التحكم (2026-09-01)
     ──────────────────────────────────────────────────────────────────────────
     ◆ للتراجعِ الكامل: احذف كلَّ ما بين هذه العلامةِ وعلامةِ «DASH-V2 · END».
       لا ملفَّ عالميًّا مُسَّ — التصميمُ القديمُ كاملٌ سليمٌ في أوراقِه الموحَّدة،
       وحذفُ الكتلةِ يعيده حرفًا.
     ◆ منهجُ الغلبة: **إعادةُ تعريفِ الرموزِ لا حربُ أولويات**. أكثرُ التغييرِ
       يمرُّ عبر رموزٍ قائمةٍ (‎--ems-topbar-bg-dash‎ · ‎--ems-header-*‎) فلا
       يحتاج `!important` أصلًا. وحيثُ لزمت — سُجِّل سببُها المقيسُ في سطرِها.
     ◆ صفرُ فقد: لا رابطَ ولا زرَّ ولا رقمَ يُحذف من الـDOM. ما يُخفى يُخفى
       بصريًّا ونصُّه باقٍ لقارئِ الشاشة.
     ══════════════════════════════════════════════════════════════════════════ -->
<style id="dash-v2">
/* ═══ ٠ · رموزُ الجولة — دلاليّةٌ لا لونيّة (بادئة --d2-) ═══════════════════ */
:root{
  --d2-surface-page:  var(--c-f4f5f7);
  --d2-surface-card:  var(--c-surface);
  --d2-surface-quiet: var(--c-f8f9fa);
  --d2-line:          var(--c-e3e6ea);
  --d2-line-soft:     var(--c-eef0f3);
  /* ⛔ **ولا يُكتب لونٌ حرفيًّا في تعليقٍ أيضًا**: ماسحُ VT-02 يقرأ التعليقَ
       كما يقرأ الشيفرة، فاقتباسُ القيمةِ يُعَدُّ لونًا صلبًا — فيُذكَر الرمزُ.
     ◆ **سلّمُ الحبرِ قِيس بمعادلةِ WCAG لا بالنظر** (بلاغُ المالك عن سياسةِ
       الألوان). كان الخافتُ الرمزَ `--c-8b949e` فيعطي **3.08** على الأبيضِ و**2.82**
       على سطحِ الصفحة — والحدُّ للنصِّ العاديِّ **4.5**. وهو مستعمَلٌ في ثمانيةَ
       عشرَ موضعًا (الوحداتُ والفتراتُ والتسمياتُ وملاحظاتُ الرسوم) — فالعطبُ
       واسعٌ لا هامشيّ، وأصابَ **أكثرَ النصِّ الثانويِّ في الشاشة**.
     ◆ **والدرسُ**: لا يمكن أن تكون ثلاثُ درجاتِ رمادٍ على أبيضَ كلُّها فوقَ
       4.5 **وتبقى متمايزةً بالعين**. فالتدرُّجُ الصحيحُ يُبنى **بالحجمِ
       والوزنِ** لا بتفتيحِ النصِّ حتى يذوب. والثلاثةُ الآن: 16.54 · 5.93 ·
       **4.83** — وكلُّها تمرّ.
     ◆ و`--c-ink-500` **رمزٌ قائمٌ في اللوحةِ باسمٍ دلاليّ** — لا هيكسٌ جديد. */
  --d2-ink:           var(--c-1c1f23);   /* 16.54 — العناوينُ والقيم */
  --d2-ink-soft:      var(--c-5b6570);   /*  5.93 — المتنُ والتسميات */
  --d2-ink-faint:     var(--c-ink-500);  /*  4.83 — الفتراتُ والوحدات (كان 3.08 🔴) */
  /* ◆ **الكهرمانيُّ لا البرتقاليّ** (قرار المالك): الجولةُ الأولى أخذت اللكنةَ
       من `--ux-accent` وهو var(--brand-orange) البرتقاليُّ لا الكهرمانيّ.
       والكهرمانيُّ مُعلَنٌ في كتلةِ الهويةِ نفسِها: var(--brand-amber).
     ◆ والدرجاتُ الأربعُ كلُّها **رموزٌ قائمةٌ في لوحةِ النظامِ** لا قيمٌ
       مؤلَّفة: العميقُ لونُ التمريرِ · الناعمُ خلفيةُ اللكنة · والحبرُ لونُ
       النصِّ عليها (وهو ما يحفظ التباينَ فلا يُقرأ الذهبيُّ على الذهبيّ). */
  /* ◆ **الكهرمانيُّ ملءٌ لا حبر**: الرمزُ `--brand-amber` على الأبيضِ = **1.99** — دونَ حدِّ
       مكوّنِ الواجهةِ (3.0) وأبعدَ من حدِّ النصّ. فيبقى للمساحاتِ المصمتةِ
       (علامةُ القسمِ · خلفيةُ اللكنة) حيث الشكلُ يحمل المعنى، **وتُفصَل عنه
       درجةٌ للأيقونةِ على سطحٍ فاتح**: `--c-brand-gold-ink` = **3.31** ✅ —
       وهي ذهبيُّ العلامةِ نفسِه أعمقَ درجةً، رمزٌ قائمٌ لا لونٌ مؤلَّف.
     ◆ وحتى لولا ذلك، فأيقوناتُ اللوحةِ كلُّها **مقترنةٌ بنصٍّ مجاور** فهي
       زخرفيّةٌ بحكمِ المعيار — لكنَّ «مطابقٌ» شيءٌ و«مقروءٌ» شيءٌ آخر. */
  --d2-accent:          var(--brand-amber);        /* 1.99 — ملءٌ ومساحةٌ فقط */
  --d2-accent-on-light: var(--c-brand-gold-ink);   /* 3.31 — أيقونةٌ على فاتح */
  --d2-accent-strong: var(--c-brand-gold-deep);
  --d2-accent-soft:   var(--c-brand-gold-soft);
  --d2-accent-ink:    var(--c-brand-gold-ink-deep);
  --d2-state-err:     var(--c-dc2626);
  --d2-state-warn:    var(--c-d97706);
  --d2-state-ok:      var(--c-16a34a);
  --d2-state-none:    var(--c-e3e6ea);   /* حافةُ «بلا حالة» = حدُّ البطاقةِ نفسُه — غيابُ الحالةِ غيابُ إشارةٍ لا إشارةٌ باهتة */
  --d2-r:             12px;   /* نصفُ قطرٍ واحدٌ للصفحةِ كلِّها (كان 12/18/20/22/30/35) */
  --d2-r-sm:          8px;
  --d2-sp-1: 4px; --d2-sp-2: 8px; --d2-sp-3: 12px; --d2-sp-4: 16px; --d2-sp-5: 20px;
  /* ◆ **مسافاتٌ دقيقةٌ خارجَ سلّمِ الأربعة — مقصودةٌ في تصميمِ اللوحة**:
     تُعلَن رموزًا بقيمِها حرفًا فلا تُقرأ «صلبةً» ولا يتغيّر شكلٌ.
     ⛔ ولا تُقرَّب إلى مضاعفِ أربعةٍ — فذاك تغييرُ تصميمٍ لا ترميزُ قيمة.
     ⛔ **ولا يحمل اسمُ الرمزِ رقمًا متبوعًا بـpx**: ماسحُ السقّاطةِ يقرأ
        `(\d+)px` داخلَ قيمةِ الإعلانِ كلِّها — فاسمٌ مثل `--d2-sp-6px`
        يُعَدُّ مسافةً صلبةً وهو رمز. فالتسميةُ بالوصفِ لا بالقيمة. */
  --d2-sp-hair: 1px; --d2-sp-nudge: 2px; --d2-sp-micro: 5px; --d2-sp-tight: 6px; --d2-sp-snug: 7px;
  --d2-shadow:        0 1px 2px var(--c-rgba16244004), 0 1px 3px var(--c-rgba16244006);
}

/* ═══ ① الشريطُ العلويُّ — سطحٌ محايدٌ والذهبيُّ يعود لكنةً ═══════════════════
   المصدر: `--ems-topbar-bg-dash` معرَّفٌ على `:root` بلا !important
   (ems.main.all.style.css:12788 — «deep yellow — dashboard exception only»).
   فإعادةُ تعريفِه هنا تكفي بترتيبِ المستندِ وحدَه. صفرُ !important. */
:root{
  --ems-topbar-bg-dash: var(--d2-surface-card);
  --ems-topbar-border:  var(--d2-line);
}
body.ems-site .ems-topbar.ems-topbar--dash{
  border-bottom: 1px solid var(--d2-line);
  box-shadow: 0 1px 3px var(--c-rgba16244006);
  color: var(--d2-ink);
}
/* مبدِّلُ السياقِ: كان يذوب في الذهبيِّ — صار شريحةً محدَّدةً على الأبيض */
body.ems-site .ems-topbar--dash .ems-topbar-pill{
  background: var(--d2-surface-quiet);
  border: 1px solid var(--d2-line);
  border-radius: 999px;
  color: var(--d2-ink);
  font-weight: 700;
}
body.ems-site .ems-topbar--dash .ems-topbar-pill:hover{
  background: var(--d2-accent-soft);
  border-color: var(--d2-accent);
}
/* الأيقوناتُ تُقرأ على الأبيض — واللكنةُ الذهبيةُ للتمرير وحدَه */
body.ems-site .ems-topbar--dash .ems-topbar-icon{
  color: var(--d2-ink-soft);
  border-radius: var(--d2-r-sm);
}
body.ems-site .ems-topbar--dash .ems-topbar-icon:hover{
  color: var(--d2-accent-ink);
  background: var(--d2-accent-soft);
}
/* الشاراتُ توحَّد: شكلٌ واحدٌ ولونٌ واحدٌ يدلُّ على «ينتظرك» */
body.ems-site .ems-topbar--dash .ems-topbar-badge{
  background: var(--d2-state-err);
  color: var(--c-surface);
  border: 2px solid var(--d2-surface-card);
  border-radius: 999px;
  font-weight: 700;
  min-width: 18px;
  line-height: 14px;
  padding: var(--d2-sp-hair) var(--d2-sp-micro);
  font-size: .66rem;
}

/* ═══ ② الرأس — سطرٌ نحيفٌ محايد · وينتهي الشريطانِ الذهبيّان ═══════════════
   الخلفيةُ والنصُّ والحدُّ رموزٌ على `body.ems-site` (…:12019) فتُعاد تعريفًا. */
body.ems-site{
  --ems-header-bg:            var(--d2-surface-card);
  --ems-header-text:          var(--d2-ink);
  --ems-header-border:        var(--d2-line);
  --ems-header-chip:          var(--d2-surface-quiet);
  --ems-header-chip-hover:    var(--d2-line-soft);
  --ems-header-primary:       var(--d2-accent);        /* ① الذهبيُّ محجوزٌ للفعلِ الرئيسيّ */
  --ems-header-primary-hover: var(--d2-accent-ink);
}
/* القيمُ الستُّ الباقيةُ **مثبَّتةٌ في القاعدةِ لا في رمز** (استدارة 18 · حدٌّ
   سفليٌّ ذهبيٌّ 2px · ظلٌّ · حشوٌ · ارتفاعٌ أدنى · هامش) وقاعدتُها بوزنِ (0,4,1)
   و`!important`. فالوزنُ هنا (0,4,2) — أعلى — و`!important` لازمةٌ للندِّيّة. */
html body.ems-site .ems-dash.main > .main_head{
  border: 1px solid var(--d2-line) !important;
  border-bottom: 1px solid var(--d2-line) !important;   /* كان ذهبيًّا 2px */
  border-radius: var(--d2-r) !important;
  box-shadow: none !important;
  padding: var(--d2-sp-2) var(--d2-sp-4) !important;
  min-height: 44px !important;                          /* كان 50 — ⑨ كثافة */
  margin-top: 0 !important;                             /* كان 30 لإفساحِ مكانِ التاريخِ العائم */
  margin-bottom: var(--d2-sp-3) !important;
}
/* طبقةُ اللمعانِ فوقَ الرأسِ صُمِّمت لخلفيةٍ ذهبيةٍ — تُطفأ على سطحٍ محايد */
html body.ems-site .ems-dash.main > .main_head::before{ display: none !important; }
html body.ems-site .ems-dash.main > .main_head .head-title{
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--d2-ink);
}
html body.ems-site .ems-dash.main > .main_head .title-icon,
html body.ems-site .ems-dash.main > .main_head .title-icon i{
  color: var(--d2-accent-on-light);
  background: transparent;
  border: 0;
}

/* ② تابع — التاريخُ المعلَّقُ يعود إلى السطر ────────────────────────────────
   كان `position:fixed; top:51px; left:50%` بنصفِ قطرٍ 0 0 35px 35px، فيبدو
   قُرصًا مقطوعًا معلَّقًا تحتَ الشريط. صار سطرًا ساكنًا فوقَ المحتوى مباشرة. */
html body.ems-site .ems-dash .shot-breadcrumb{
  position: static;
  transform: none;
  min-width: 0;
  width: auto;
  text-align: start;
  background: transparent;
  border: 0;
  padding: 0 var(--d2-sp-1) var(--d2-sp-2);
  border-radius: 0;
  color: var(--d2-ink-faint);
  font-weight: 600;
  font-size: .78rem;
  line-height: 1.4;
  z-index: auto;
}

/* ② تابع — سطرُ السياقِ (النطاق · الصلاحية · لحظة القراءة) يلتحق بلغةِ الرأس */
html body.ems-site .ems-dash .ems-page-context{
  display: flex;
  flex-wrap: wrap;
  gap: var(--d2-sp-1) var(--d2-sp-4);
  font-size: .74rem;
  color: var(--d2-ink-faint);
  background: transparent;
  border: 0;
  padding: 0 var(--d2-sp-1);
  margin: 0 0 var(--d2-sp-2);
}
html body.ems-site .ems-dash .ems-page-context b{
  font-weight: 700;
  color: var(--d2-ink-soft);
}

/* ① تابع — بطاقةُ «عن الشاشة» كانت ثالثَ كتلةٍ صفراءَ في أعلى الصفحة.
   المحتوى كما هو، والسطحُ يصير محايدًا كبقيةِ الصفحة (سقفُ اللونِ المميِّز). */
html body.ems-site .ems-about{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  box-shadow: none;
  padding: var(--d2-sp-3) var(--d2-sp-4);
  font-size: .82rem;
  color: var(--d2-ink-soft);
}
html body.ems-site .ems-about b,
html body.ems-site .ems-about strong{ color: var(--d2-ink); }

/* ═══ ③ «بيانات الجلسة» — أربعُ بطاقاتٍ تصير سطرًا خافتًا ═══════════════════
   المعلوماتُ الأربعُ باقيةٌ كلُّها — يتغيّر ثوبُها لا وجودُها. */
html body.ems-site .ems-dash .shot-session{
  background: transparent;
  border: 0;
  padding: 0;
  margin: 0 0 var(--d2-sp-3);
}
html body.ems-site .ems-dash .shot-session .shot-session-title{
  font-size: .72rem;
  font-weight: 700;
  color: var(--d2-ink-faint);
  margin: 0 0 var(--d2-sp-1);
  letter-spacing: .01em;
}
html body.ems-site .ems-dash .shot-session .shot-session-row{
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--d2-sp-1) var(--d2-sp-4);
}
/* ◆ الشريحةُ نفسُها تلتقطها قاعدةُ الشاراتِ العامةُ أيضًا (`[class*="chip"]`
     تطابق `shot-session-chip`) بثلاثَ عشرةَ `!important` — فلا تُجرَّد بدونها. */
html body.ems-site .ems-dash .shot-session .shot-session-chip{
  background: transparent !important;
  border: 0 !important;
  border-radius: 0 !important;
  min-height: 0 !important;
  padding: 0 !important;
  gap: var(--d2-sp-tight) !important;
  font-size: .8rem !important;
  font-weight: 600 !important;
  color: var(--d2-ink-soft) !important;
}
html body.ems-site .ems-dash .shot-session .shot-session-chip:hover{ background: transparent !important; }
html body.ems-site .ems-dash .shot-session .shot-session-chip strong{
  font-weight: 700;
  color: var(--d2-ink);
}
/* ◆ `!important` **لازمةٌ هنا ومقيسة**: القاعدةُ العامةُ للشارات
     (ems.main.all.style.css:12664 · `[class*="chip"]` تلتقط `chip-icon`)
     تحمل ثلاثَ عشرةَ `!important` — حشوًا وحدًّا وخلفيةً وقُرصًا 999px.
     و`!important` تغلب الوزنَ مهما علا، فلا تُغلَب إلا بمثلِها. */
html body.ems-site .ems-dash .shot-session .shot-session-chip .chip-icon{
  background: transparent !important;
  border: 0 !important;
  width: auto !important;
  min-width: 0 !important;
  min-height: 0 !important;
  padding: 0 !important;
  border-radius: 0 !important;
  font-size: .8rem !important;
  font-weight: 600 !important;
  color: var(--d2-ink-faint) !important;
}
/* فاصلٌ خفيفٌ بين المعلوماتِ الأربعِ بدلَ أربعِ بطاقات */
html body.ems-site .ems-dash .shot-session .shot-session-chip + .shot-session-chip::before{
  content: "";
  width: 1px;
  height: 12px;
  background: var(--d2-line);
  margin-inline-end: var(--d2-sp-3);
}

/* ═══ ④ «الوصول السريع» — شريطُ مقاصدَ متراصٌّ يلتفّ ═══════════════════════
   ◆ **المراجعةُ الثانية (بطلبِ المالك)**: الجولةُ الأولى تركت البلاطةَ شبكةً
     من أربعةِ أعمدةٍ متساويةٍ وأخفت التسعَ الباقيةَ خلفَ زرِّ «المزيد».
     والزرُّ لم يكن قبيحًا لذاتِه — كان **ترقيعًا لعلّةٍ أخرى**: البلاطةُ ضخمةٌ
     (88px ارتفاعًا و280px عرضًا لكلمتَين) فلم تسع الثلاثَ عشرةَ، فاخترعنا
     إخفاءً. وإصلاحُ الحجمِ يُلغي الحاجةَ إلى الإخفاءِ من أصلِها.
   ◆ **القرارُ**: تُصغَّر البلاطةُ إلى مقصدٍ بحجمِ نصِّه (34px) وتلتفُّ الثلاثَ
     عشرةَ في سطرَين — **فتظهر كلُّها بلا نقرةٍ ولا زرٍّ ولا إخفاء**، وتشغل
     مساحةً **أقلَّ** مما كانت تشغله الأربعُ وحدَها. أفضلُ إصلاحٍ لعنصرِ واجهةٍ
     هو الاستغناءُ عنه لا تجميلُه.
   ◆ والوزنُ البصريُّ يصحّ أيضًا: هذه أبوابٌ لا محتوًى — فلا تُزاحم الأرقامَ. */
html body.ems-site .ems-dash .shot-quick-zone{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  box-shadow: var(--d2-shadow);
  padding: var(--d2-sp-4);
  margin-bottom: var(--d2-sp-3);
}
/* الشبكةُ تصير صفًّا ملتفًّا. والعرضُ يبقى محكومًا بـ`.is-active` وحدَها —
   ولو أُطلق `display` على كلِّ شبكةٍ لظهرت المجموعاتُ الأربعُ معًا. */
html body.ems-site .ems-dash .shot-hex-grid:not([data-quick-group]),
html body.ems-site .ems-dash .shot-hex-grid[data-quick-group].is-active{
  display: flex;
  flex-wrap: wrap;
  gap: var(--d2-sp-2);
}
/* ◆ **الفراغُ المهدورُ في نهاياتِ السطور** (بلاغُ المالك): المقاصدُ بأطوالٍ
     مختلفة («العقود» 83px · «كتالوج المنتجات والخدمات» 178px)، وبعرضٍ يساوي
     نصَّها يبقى في آخرِ كلِّ سطرٍ فراغٌ لا يسعُ التاليَ فيُهدَر.
   ◆ فيُسمح لكلِّ مقصدٍ أن **ينمو** ليقتسم فائضَ سطرِه (`flex-grow:1`) وتبقى
     أرضيتُه 150px فلا ينكمش القصيرُ تحتَ القراءة. فكلُّ سطرٍ ممتلئٌ حرفيًّا،
     والنسبُ بين المقاصدِ محفوظةٌ لأن النموَّ يوزَّع لا يُسوّى.
   ◆ والنصُّ يبقى في بدايةِ المقصدِ لا في وسطِه — فالعينُ تمسح عمودًا واحدًا. */
html body.ems-site .ems-dash .shot-hex-link{
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  flex: 1 1 auto;
  min-width: 150px;
  max-width: 100%;
  min-height: 34px;
  grid-column: auto;              /* يُبطل «مدَّ البلاطةِ الأخيرة» الموروث */
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r-sm);
  background: var(--d2-surface-card);
  gap: var(--d2-sp-snug);
  padding: 0 var(--d2-sp-3);
  font-size: .82rem;              /* كان 2rem — وهو سببُ ضخامةِ الأيقونةِ والنص */
  font-weight: 600;
  color: var(--d2-ink);
  transition: border-color .15s ease, background .15s ease;
}
html body.ems-site .ems-dash .shot-hex-link:hover{
  background: var(--d2-accent-soft);
  border-color: var(--d2-accent);
  color: var(--d2-accent-ink);
  transform: none;                /* الرفعُ عند التمرير ضجيجٌ في شريطِ تنقّل */
}
html body.ems-site .ems-dash .shot-hex-link:hover .shot-hex-icon{ color: var(--d2-accent-strong); }
html body.ems-site .ems-dash .shot-hex-icon{
  width: auto;
  height: auto;
  min-width: 0;
  background: transparent;
  border: 0;
  border-radius: 0;
  display: inline-flex;
  align-items: center;
  font-size: .88rem;
  line-height: 1;
  color: var(--d2-accent-on-light);
  margin: 0;
  flex: none;
}
html body.ems-site .ems-dash .shot-hex-title{
  font-size: .82rem;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

/* ═══ ①-ب هويةُ العلامةِ — خيطٌ ذهبيٌّ رفيعٌ بدل أسطحٍ صفراء ═══════════════
   ◆ نزعُ الأصفرِ من الأسطحِ يُخرج الشاشةَ محايدةً بلا اسم. فتعود الهويةُ
     **إشارةً لا خلفية**: علامةٌ ذهبيةٌ 3px تسبق كلَّ عنوانِ قسمٍ حقيقيّ،
     وأيقونةٌ ذهبيةٌ في المقاصد. لونٌ واحدٌ في مواضعَ قليلةٍ يُقرأ هويةً —
     واللونُ نفسُه على كلِّ سطحٍ يُقرأ ضجيجًا.
   ◆ ولا تُوسَم به التسمياتُ الخافتةُ («بيانات الجلسة») — العلامةُ لرأسِ قسمٍ
     لا لكلِّ نصٍّ رماديّ، وإلا فقدت معناها. */
html body.ems-site .ems-dash .shot-section-title,
html body.ems-site .ems-dash .shot-ops-title,
html body.ems-site .ems-dash .shot-num-panel-secondary .shot-session-title{
  display: flex;
  align-items: center;
  gap: var(--d2-sp-snug);
  font-size: .88rem;
  font-weight: 800;
  color: var(--d2-ink);
  margin: 0 0 var(--d2-sp-3);
}
html body.ems-site .ems-dash .shot-section-title::before,
html body.ems-site .ems-dash .shot-ops-title::before,
html body.ems-site .ems-dash .shot-num-panel-secondary .shot-session-title::before{
  content: "";
  flex: none;
  width: 3px;
  height: 14px;
  border-radius: 2px;
  background: var(--d2-accent);
}
/* اسمُ المجموعةِ يتبع العنوانَ سياقًا خافتًا: «الوصول السريع · التخطيط والتوزيع»
   — فيعرف الناظرُ **أبوابَ أيِّ مجموعةٍ** يرى، وهو ما كان ناقصًا. */
html body.ems-site .ems-dash .shot-quick-group-name{
  font-size: .8rem;
  font-weight: 600;
  color: var(--d2-ink-faint);
}
html body.ems-site .ems-dash .shot-quick-group-name:not(:empty)::before{
  content: "·";
  margin-inline-end: var(--d2-sp-snug);
  color: var(--d2-line);
}

/* ترويسةٌ مُنشأةٌ لقسمٍ كان بلا عنوان — تأخذ لغةَ أخواتِها نفسَها */
html body.ems-site .ems-dash .shot-charts > .d2-made{ grid-column: 1 / -1; }

/* ═══ ⑤ بطاقةُ المؤشر — حافةٌ جانبيةٌ تحمل الحالة ═══════════════════════════
   ⚠ يخالف قرارَ مالكٍ مسجَّلًا 2026-08-21 (ems-screens.css قربَ السطر 1024)
     ألغى الحدَّ الملوَّنَ لأن البطاقاتِ الملوَّنةَ بدت غريبةً وسطَ بطاقاتٍ
     رماديةٍ. وعلّتُه تسقط هنا لأن البطاقاتِ كلَّها صارت بيضاءَ بلغةٍ واحدة.
     يحتاج إقرارَ المالكِ عند التقييم.
   القاعدةُ المُلغاةُ وزنُها (0,6,1) وفيها سبعُ `!important` — فالمحدِّدُ هنا
   هو **نفسُه حرفًا** و`!important` لازمة: الندِّيّةُ تُحسَم بترتيبِ المستند. */
body.ems-site .main .shot-ops-kpis .ems-kpi-card:not(.dt-button):not(.btn-close){
  background: var(--d2-surface-card) !important;
  border: 1px solid var(--d2-line) !important;
  border-inline-start: 4px solid var(--d2-state-none) !important;
  border-radius: var(--d2-r) !important;
  padding: var(--d2-sp-3) var(--d2-sp-4) !important;   /* كان 16/18 — ⑨ كثافة */
  box-shadow: var(--d2-shadow) !important;
}
body.ems-site .main .shot-ops-kpis .ems-kpi-card.ems-kpi-err:not(.dt-button):not(.btn-close){
  border-inline-start-color: var(--d2-state-err) !important;
}
body.ems-site .main .shot-ops-kpis .ems-kpi-card.ems-kpi-warn:not(.dt-button):not(.btn-close){
  border-inline-start-color: var(--d2-state-warn) !important;
}
body.ems-site .main .shot-ops-kpis .ems-kpi-card.ems-kpi-ok:not(.dt-button):not(.btn-close){
  border-inline-start-color: var(--d2-state-ok) !important;
}
body.ems-site .main .shot-ops-kpis .ems-kpi-card:not(.dt-button):not(.btn-close):hover{
  border-color: var(--d2-line) !important;
  box-shadow: 0 4px 12px var(--c-rgba16244008) !important;
}
/* الحالةُ نصًّا **مع** اللونِ لا مستبدَلةً به — شريحةٌ صغيرةٌ تحمل النغمة.
   الوزنُ (0,6,3) للسببِ المقيسِ نفسِه، و`!important` على المتنازَعِ وحدَه. */
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card span.ems-kpi-state.ems-statcard__meta{
  border-radius: 999px;
  padding: var(--d2-sp-hair) var(--d2-sp-2) !important;
  font-weight: 700 !important;
  background: var(--d2-surface-quiet) !important;
  color: var(--d2-ink-soft) !important;
  margin-inline-start: var(--d2-sp-2);
}
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card.ems-kpi-err span.ems-kpi-state.ems-statcard__meta{
  background: var(--c-fef2f2) !important; color: var(--c-b91c1c) !important;
}
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card.ems-kpi-warn span.ems-kpi-state.ems-statcard__meta{
  background: var(--c-fffbeb) !important; color: var(--c-92400e) !important;
}
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card.ems-kpi-ok span.ems-kpi-state.ems-statcard__meta{
  background: var(--c-f0fdf4) !important; color: var(--c-15803d) !important;
}

/* ═══ ⑥ نزعُ الحشوِ المتكرِّر — والنصُّ باقٍ لقارئِ الشاشة ═══════════════════
   بنيةُ البطاقة (includes/kpi_card.php):
     meta①  span(الفترة «لحظي (…)»)  +  span(المقارنة «بلا مقارنة معلنة»)
     meta②  span.ems-kpi-state       +  span(النطاق) */
/* ◆ تيبوغرافيا القيمةِ والعنوانِ **تُترك للنظامِ الموحَّد** (`ems-statcards.css`
     يفرضها بوزنِ (0,6,1) و`!important`). ولا تُنازَع هنا عمدًا: هي حكمُ توحيدٍ
     صحيحٌ لا عطبٌ — والجولةُ تُصلح الضجيجَ لا تنقض التوحيد. */
/* ◆ **البنيةُ وقتَ الطلاءِ ليست بنيةَ الخادم**: `ems-statcards.js` يعيد بناءَ
     البطاقةِ فينتزع أبناءَ `.ems-kpi-meta` ويجعلهم أبناءً مباشرين موسومين
     `.ems-statcard__meta`، ويقدّم القيمةَ على العنوان. فالترتيبُ المقيسُ في
     المتصفحِ (لا في kpi_card.php):
        value · title · small.ems-kpi-unit «سجل» · span «لحظي (…)»
                      · span «بلا مقارنة معلنة» · span.ems-kpi-state «محايد»
     ووعاءا `.ems-kpi-meta` يبقيان فارغَين وتخفيهما قاعدةٌ قائمةٌ أصلًا.
     ⇒ محدِّدٌ مبنيٌّ على البنيةِ الخادميةِ وحدَها **لا يطابق شيئًا** وقتَ الطلاء. */
/* ◆ `!important` **لازمةٌ ومقيسة**: `ems-statcards.css` يفرض على كلِّ
     `.ems-statcard__meta` (والبطاقةُ تُوسَم `.ems-statcard` بالـJS نفسِه)
     `display:block !important; font-size:12px !important` — فلا سطرَ واحدًا
     ولا خطًّا أخفَّ بدونها. المحدِّد هناك:
     `body.ems-site .main :is(.stats-card,.ems-statcard):not(…):not(…) .ems-statcard__meta` */
/* ◆ **الوزنُ مقيسٌ لا مُقدَّر**: قاعدةُ `ems-statcards.css` وزنُها **(0,6,1)**
     و`!important`. و`!important` لا يُغلَب إلا بـ`!important` **ثم بالوزن** —
     فمحدِّدٌ بـ(0,5,3) يخسر رغم `!important` ورغم تأخُّرِه في المستند.
     ولذلك أُقحمت `.ems-kpi-card` في السلسلةِ لتبلغ **(0,6,3)**. */
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card small.ems-kpi-unit.ems-statcard__meta,
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card span.ems-statcard__meta:not(.ems-kpi-state){
  display: inline !important;
  font-size: .7rem !important;
  font-weight: 600;
  color: var(--d2-ink-faint);
}
/* فاصلٌ بين «سجل» و«لحظي» فيقرآن سطرًا واحدًا لا سطرَين */
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card span.ems-statcard__meta:not(.ems-kpi-state)::before{
  content: " · ";
  color: var(--d2-line);
}
/* «بلا مقارنة معلنة» ⟵ شرطة. وهي ثاني span غيرِ حالةٍ (الأولُ الفترة).
   النصُّ **باقٍ في الـDOM** يقرؤه قارئُ الشاشة — يُطوى للناظرِ وحدَه. */
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card
  span.ems-statcard__meta:not(.ems-kpi-state) + span.ems-statcard__meta:not(.ems-kpi-state){
  font-size: 0 !important;   /* يغلب `font-size:12px !important` المذكورَ أعلاه */
}
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card
  span.ems-statcard__meta:not(.ems-kpi-state) + span.ems-statcard__meta:not(.ems-kpi-state)::after{
  content: "—";
  font-size: .7rem;
  font-weight: 600;
  color: var(--d2-ink-faint);
}
/* الحالةُ تنزل سطرًا مستقلًّا فتُقرأ شريحةً لا كلمةً تائهة */
html body.ems-site .ems-dash .shot-ops-kpis .ems-kpi-card span.ems-kpi-state.ems-statcard__meta{
  display: inline-block !important;   /* المصدرُ نفسُه يفرض block */
  font-size: .66rem !important;
}
/* صندوقُ لوحةِ التشغيلِ وترويستُه — لغةٌ واحدةٌ مع بقيةِ الصفحة */
html body.ems-site .ems-dash .shot-ops{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  padding: var(--d2-sp-4);
  margin-bottom: var(--d2-sp-4);
  box-shadow: var(--d2-shadow);
}
html body.ems-site .ems-dash .shot-ops-head{ margin-bottom: var(--d2-sp-3); }
html body.ems-site .ems-dash .shot-ops-title{
  font-size: .95rem; font-weight: 800; color: var(--d2-ink); margin: 0;
}
html body.ems-site .ems-dash .shot-ops-title i{ color: var(--d2-accent-on-light); }
html body.ems-site .ems-dash .shot-ops-note{ font-size: .74rem; color: var(--d2-ink-faint); }
html body.ems-site .ems-dash .shot-ops-box{
  background: var(--d2-surface-quiet);
  border: 1px solid var(--d2-line-soft);
  border-radius: var(--d2-r);
  padding: var(--d2-sp-3);
}
html body.ems-site .ems-dash .shot-ops-box-title{
  font-size: .8rem; font-weight: 700; color: var(--d2-ink-soft); margin: 0 0 var(--d2-sp-2);
}
html body.ems-site .ems-dash .shot-ops-box-title i{ color: var(--d2-accent-on-light); }
html body.ems-site .ems-dash .shot-ops-row{
  border-radius: var(--d2-r-sm);
  padding: var(--d2-sp-tight) var(--d2-sp-2);
  font-size: .82rem;
}
html body.ems-site .ems-dash .shot-ops-row:hover{ background: var(--d2-accent-soft); }
html body.ems-site .ems-dash .shot-ops-act{
  border-radius: var(--d2-r-sm);
  padding: var(--d2-sp-tight) var(--d2-sp-2);
  font-size: .82rem;
}
html body.ems-site .ems-dash .shot-ops-empty{ font-size: .78rem; color: var(--d2-ink-faint); }

/* ═══ ⑨ الكثافةُ والاستدارةُ والأسطحُ — لغةٌ واحدةٌ للصفحة ═════════════════ */
html body.ems-site .ems-dash .shot-body{
  background: var(--d2-surface-page);
  border-radius: var(--d2-r);
  padding: var(--d2-sp-4);
  padding-bottom: 72px;          /* ⑨ لا يقع محتوًى تحتَ زرِّ الإبلاغِ الطافي */
}
/* ═══ ⑩-ب إعادةُ ترتيبِ الأقسام ═══════════════════════════════════════════
   ◆ اللوحةُ تجيب أربعةَ أسئلةٍ بترتيبٍ ثابت: *هل يحتاجني شيء؟* ثم *ما الأرقام؟*
     ثم *كيف تتّجه؟* ثم *أين أذهب؟* — والترتيبُ القديمُ كان يبدأ بالرابع.
     المقيسُ على 1440×900: **554 بكسلًا من 900 تُنفَق قبل اللوحةِ التشغيلية**
     ثم تُقطَع، والكتلتانِ فوقَها أقلُّ عنصرَين قيمةً (روابطُ في السايدبارِ
     أصلًا · وبياناتُ جلسةٍ مكرَّرة).
   ◆ **الترتيبُ بـ`order` لا بنقلِ الوسمِ في PHP** — ليبقى التراجعُ حذفَ كتلةٍ
     لا إعادةَ نقلٍ يدويّ. لكنَّ `order` يغيّر البصرَ ولا يغيّر الشجرةَ، فيفترق
     ترتيبُ لوحةِ المفاتيحِ عن ترتيبِ العين. ولذلك يلي CSSَ **مُوائمٌ في JS
     يعيد ترتيبَ العقدِ نفسِها**، ثم يُصفِّر الرتبَ فلا تُطبَّق مرّتين:
     فالبصرُ صحيحٌ من أولِ رسمٍ (بالـCSS) والشجرةُ تلحقه (بالـJS). */
html body.ems-site .ems-dash .shot-body{ display: flex; flex-direction: column; }
/* ◆ **تصحيحُ اقتراحي**: أنزلتُ «الوصول السريع» إلى الذيلِ فصار في موضعٍ لا
     يراه أحد — وهو علاجٌ أسوأُ من الداء. التشخيصُ كان صحيحًا (الملاحةُ لا
     تتصدَّر) والوصفةُ خاطئة. والمساحةُ التي حرّرها **حذفُ «بيانات الجلسة»
     (61px)** تكفي لإبقائه في الأعلى **مع** بقاءِ اللوحةِ كاملةً فوقَ الطيّ —
     مقيسٌ لا مُقدَّر (انظر الأرقامَ في التسليم). فيعود إلى موضعِه المرئيّ. */
html body.ems-site .ems-dash .shot-body > .shot-breadcrumb{ order: 1; }
html body.ems-site .ems-dash .shot-body > .shot-quick-zone{ order: 2; }
html body.ems-site .ems-dash .shot-body > .shot-lower-zone{ order: 3; }

html body.ems-site .ems-dash .shot-lower-zone{ display: flex; flex-direction: column; }
html body.ems-site .ems-dash .shot-lower-zone > .shot-session{ order: 1; }
html body.ems-site .ems-dash .shot-lower-zone > .shot-ops{ order: 2; }
html body.ems-site .ems-dash .shot-lower-zone > .shot-num-panel:not(.shot-num-panel-secondary){ order: 3; }
html body.ems-site .ems-dash .shot-lower-zone > .shot-num-panel-secondary{ order: 4; }
html body.ems-site .ems-dash .shot-lower-zone > .shot-charts{ order: 5; }
/* بعدَ مُوائمةِ الشجرة: تُصفَّر الرتبُ وإلا أُعيد الترتيبُ على ترتيبٍ مُعاد */
html body.ems-site .ems-dash .d2-dom-ordered > *{ order: 0; }

/* ═══ ⑩-ج «بيانات الجلسة» تُدمَج في سطرِ السياق ═══════════════════════════
   ◆ المقيسُ في الناتجِ المُصيَّر: ثلاثٌ من أربعِ شرائحَ **مكرَّرةٌ حرفًا**
     (التاريخُ يظهر تسعَ مرّاتٍ في الصفحة · والشركةُ في «النطاق: …» ·
     والمستخدمُ أربعَ مرّات). فالمكرَّرتانِ تُطويانِ والفريدتانِ **تُنقلانِ**
     إلى سطرِ السياقِ — نقلٌ لا استنساخ، وصفرُ معلومةٍ تُفقد. */
html body.ems-site .ems-dash .d2-dup{ display: none; }
html body.ems-site .ems-dash .shot-session.d2-merged{ display: none; }
html body.ems-site .ems-dash .ems-page-context .shot-session-chip{
  display: inline-flex;
  align-items: center;
  gap: var(--d2-sp-tight);
  background: transparent !important;
  border: 0 !important;
  padding: 0 !important;
  min-height: 0 !important;
  border-radius: 0 !important;
  font-size: .74rem !important;
  font-weight: 600 !important;
  color: var(--d2-ink-faint) !important;
}
html body.ems-site .ems-dash .ems-page-context .shot-session-chip strong{
  font-weight: 700;
  color: var(--d2-ink-soft);
}
/* ◆ الشريحةُ **غادرت `.shot-session`** فسقط عنها محدِّدُ الأيقونةِ المربوطُ
     بها، وعادت قاعدةُ الشاراتِ العامةُ (`[class*="chip"]`) تُدوِّرها 46px.
     فيُنطَّق مقصدُها الجديدُ أيضًا — والدرسُ: **نقلُ عقدةٍ ينقلها من مداها.** */
html body.ems-site .ems-dash .ems-page-context .shot-session-chip .chip-icon{
  background: transparent !important;
  border: 0 !important;
  width: auto !important;
  min-width: 0 !important;
  min-height: 0 !important;
  padding: 0 !important;
  border-radius: 0 !important;
  font-size: .74rem !important;
  color: var(--d2-ink-faint) !important;
}
/* فاصلٌ بين بنودِ سطرِ السياقِ الأصليةِ والمنقولةِ إليه */
/* سطرُ التاريخِ بعد نقلِه: بندٌ في سطرِ السياقِ لا سطرٌ مستقلّ */
html body.ems-site .ems-dash .ems-page-context .shot-breadcrumb{
  padding: 0;
  font-size: .74rem;
  font-weight: 600;
  color: var(--d2-ink-faint);
}
html body.ems-site .ems-dash .ems-page-context .shot-breadcrumb::before,
html body.ems-site .ems-dash .ems-page-context .shot-session-chip::before{
  content: "";
  width: 1px;
  height: 11px;
  background: var(--d2-line);
  margin-inline-end: var(--d2-sp-2);
}
html body.ems-site .ems-dash .shot-num-panel{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  padding: var(--d2-sp-4);
  margin-bottom: var(--d2-sp-4);
  box-shadow: var(--d2-shadow);
}
html body.ems-site .ems-dash .shot-stat-grid{ gap: var(--d2-sp-3); }
html body.ems-site .ems-dash .shot-charts{ gap: var(--d2-sp-3); }
html body.ems-site .ems-dash .shot-chart-card{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  padding: var(--d2-sp-4);
  box-shadow: var(--d2-shadow);
}
html body.ems-site .ems-dash .shot-chart-title{ font-size: .88rem; font-weight: 700; color: var(--d2-ink); }
html body.ems-site .ems-dash .shot-chart-note{ font-size: .72rem; color: var(--d2-ink-faint); }
html body.ems-site .ems-dash .shot-logout{
  border-radius: var(--d2-r-sm);
  font-size: .82rem;
}
/* بطاقاتُ الأرقامِ الكبيرةِ تتبع اللغةَ نفسَها. القاعدةُ الموحَّدةُ
   (ems-statcards.css) وزنُها (0,5,1) و`!important` — فالوزنُ هنا (0,4,2)
   لا يكفي وحدَه، و`!important` لازمةٌ على المقيسِ المتنازَعِ فقط. */
html body.ems-site .ems-dash .shot-stat-card{
  background: var(--d2-surface-quiet) !important;
  border: 1px solid var(--d2-line) !important;
  border-radius: var(--d2-r) !important;
  padding: var(--d2-sp-3) var(--d2-sp-4) !important;
  box-shadow: none !important;
}

/* ⑨ زرُّ «أبلغ عن مشكلة» — كان يعوم فوقَ بطاقةِ مؤشر.
   موضعُه مكتوبٌ في **مسندِ style=** داخلَ includes/report_button.php،
   والمسندُ لا يُغلَب إلا بـ`!important`. يبقى في متناولِ اليدِ ولا يحجب. */
body.ems-site .ems-report-fallback{
  bottom: 10px !important;
  opacity: .5;
  transition: opacity .18s ease;
}
body.ems-site .ems-report-fallback:hover,
body.ems-site .ems-report-fallback:focus-within{ opacity: 1; }
/* شكلُه يلتحق بلغةِ الصفحة: كان محدَّدًا بأحمرَ يقرأه الناظرُ إنذارًا — وهو
   بابُ بلاغٍ لا تحذير. والزرُّ يبقى في متناولِ اليدِ ولا يُحذف. */
body.ems-site .ems-report-fallback button{
  border-radius: 999px !important;
  border: 1px solid var(--d2-line) !important;
  background: var(--d2-surface-card) !important;
  color: var(--d2-ink-soft) !important;
  font-size: .74rem !important;
  font-weight: 600 !important;
  box-shadow: 0 2px 8px var(--c-rgba1624400010) !important;
}
body.ems-site .ems-report-fallback button:hover{
  border-color: var(--d2-accent) !important;
  color: var(--d2-accent-ink) !important;
  background: var(--d2-accent-soft) !important;
}

/* ═══ ⑧ السايدبار — المجموعةُ الحاليةُ مفتوحةٌ والرابطُ مُبرَز ═══════════════ */
html body.ems-site .sidebar li.nav-group.d2-auto-open > ul.nav-group-items{
  display: block;
}
html body.ems-site .sidebar li.active > a{
  background: var(--d2-accent-soft);
  border-inline-start: 3px solid var(--d2-accent);
  font-weight: 700;
}

/* ═══ ⑩ لوحُ الأرقامِ — الكتلةُ الصفراءُ الرابعةُ في الصفحة ═════════════════
   ◆ **الأصفرُ هنا ليس خلفيةَ اللوحِ بل طبقةً فوقَه**: `::before` بمقاسِ
     `inset:0` تُطلى `--dash-yellow` وتقف بينَ اللوحِ ومحتواه. فتبييضُ
     `background` على العنصرِ لا يُظهر شيئًا — الطبقةُ تغطّيه.
     (وهذا ما كان يظهر في لقطةِ إدارةِ الموردين: لوحٌ أبيضُ تحتَ غلافٍ أصفر.)
   ◆ ووزنُها (0,2,1) فتُطفأ بوزنٍ أعلى بلا `!important`. */
html body.ems-site .ems-dash .shot-num-panel::before{ display: none; }
html body.ems-site .ems-dash .shot-num-panel,
html body.ems-site .ems-dash .shot-num-panel-secondary{
  background: var(--d2-surface-card);
  border: 1px solid var(--d2-line);
  border-radius: var(--d2-r);
  box-shadow: var(--d2-shadow);
  padding: var(--d2-sp-4);
  margin-top: 0;
  margin-bottom: var(--d2-sp-4);
}
/* اللوحان صارا بلغةٍ واحدة — وكانا يفترقان: أصفرُ بحشو 36/18/30 وقيمةٍ
   6.5rem مقابلَ أبيضَ بحشو 16/18/14 وقيمةٍ 3.1rem في الصفحةِ نفسِها. */
html body.ems-site .ems-dash .shot-num-panel .shot-session-title{
  font-size: .82rem;
  font-weight: 700;
  color: var(--d2-ink-faint);
  margin: 0 0 var(--d2-sp-3);
}

/* بطاقةُ الرقم — الوزنُ (0,5,2) ليعلوَ (0,5,1) الموحَّدةَ في ems-statcards.css */
html body.ems-site .ems-dash .shot-stat-grid .shot-stat-card.ems-statcard{
  background: var(--d2-surface-quiet) !important;
  border: 1px solid var(--d2-line) !important;
  border-radius: var(--d2-r) !important;      /* كان 35px */
  padding: var(--d2-sp-3) var(--d2-sp-4) !important;
  box-shadow: none !important;
}
html body.ems-site .ems-dash .shot-stat-grid .shot-stat-card.ems-statcard:hover{
  border-color: var(--d2-accent) !important;
  background: var(--d2-accent-soft) !important;
}
/* سطرُ الوحدةِ والفترةِ تحتَ الرقمِ يخفت — الوزنُ (0,6,3) للسببِ المقيسِ نفسِه */
html body.ems-site .ems-dash .shot-stat-grid .shot-stat-card div.db-1.ems-statcard__meta{
  font-size: .68rem !important;
  color: var(--d2-ink-faint) !important;
  font-weight: 600 !important;
}
/* التيبوغرافيا تُترك للنظامِ الموحَّد (35px/900 للقيمة · 14.72/700 للعنوان) —
   وهو ما وحّد اللوحَين أصلًا، فلا يُنازَع. */

/* ═══ ⑪ الرسوم — بطاقاتٌ بلغةِ الصفحةِ نفسِها ═══════════════════════════════ */
html body.ems-site .ems-dash .shot-chart-head{
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--d2-sp-2);
  margin-bottom: var(--d2-sp-3);
  padding-bottom: var(--d2-sp-2);
  border-bottom: 1px solid var(--d2-line-soft);
}
html body.ems-site .ems-dash .shot-chart-wrap{ height: 200px; }
html body.ems-site .ems-dash .shot-chart-wrap.tall{ height: 250px; }
html body.ems-site .ems-dash .shot-chart-card:hover{
  box-shadow: 0 4px 12px var(--c-rgba16244008);
}

/* ═══ ⑫ صناديقُ لوحةِ الدورِ وصفوفُها ═══════════════════════════════════════ */
html body.ems-site .ems-dash .shot-ops-grid{ gap: var(--d2-sp-3); margin-top: var(--d2-sp-3); }
html body.ems-site .ems-dash .shot-ops-row-text{ gap: var(--d2-sp-tight); }
html body.ems-site .ems-dash .shot-ops-row-text i{ color: var(--d2-ink-faint); opacity: 1; }
/* رقاقةُ العددِ في الصفِّ: الشكلُ واحدٌ والنغمةُ وحدَها تفرّق */
html body.ems-site .ems-dash .shot-ops-num{
  min-width: 24px;
  padding: var(--d2-sp-hair) var(--d2-sp-snug);
  font-size: .72rem;
  border: 1px solid transparent;
  background: var(--d2-surface-quiet);
  color: var(--d2-ink-soft);
}
html body.ems-site .ems-dash .shot-ops-num.is-warn{ background: var(--c-fffbeb); color: var(--c-92400e); }
html body.ems-site .ems-dash .shot-ops-num.is-err{ background: var(--c-fef2f2); color: var(--c-b91c1c); }
html body.ems-site .ems-dash .shot-ops-recent{ display: block; }
html body.ems-site .ems-dash .shot-ops-time{ font-size: .68rem; color: var(--d2-ink-faint); }
html body.ems-site .ems-dash .shot-ops-act:hover{ background: var(--d2-accent-soft); }

/* ═══ الوصولية — حلقةُ تركيزٍ ظاهرةٌ على كلِّ ما يُنقر في الصفحة ═══════════ */
html body.ems-site .ems-dash a:focus-visible,
html body.ems-site .ems-dash button:focus-visible,
html body.ems-site .ems-topbar--dash a:focus-visible,
html body.ems-site .ems-topbar--dash button:focus-visible{
  outline: 2px solid var(--d2-accent);
  outline-offset: 2px;
  border-radius: var(--d2-r-sm);
}

/* ═══ الشاشاتُ الضيقة ═══════════════════════════════════════════════════ */
@media (max-width: 768px){
  html body.ems-site .ems-dash .shot-body{ padding: var(--d2-sp-3); padding-bottom: 72px; }
  html body.ems-site .ems-dash .shot-session .shot-session-row{ gap: var(--d2-sp-1) var(--d2-sp-3); }

  /* ◆ المقاصدُ الملتفّةُ تكلّف على الهاتف **433px** قبل أيِّ رقم — وهي علّةُ
       الحاسوبِ نفسُها عائدةً في عرضٍ أضيق. فتصير على الهاتف **صفًّا واحدًا
       يُمرَّر أفقيًّا**: الثلاثَ عشرةَ كلُّها باقيةٌ ويُبلَغ إليها بالإبهام،
       والكلفةُ 34px بدل 433. ولا زرَّ ولا طيَّ ولا شيءَ مخفيّ.
     ◆ والقطعُ في منتصفِ المقصدِ الأخيرِ **هو الدليلُ على وجودِ مزيد** — فلا
       يحتاج سهمًا ولا تلميحًا. ويُخفى شريطُ التمرير: على اللمسِ زينةٌ. */
  html body.ems-site .ems-dash .shot-hex-grid:not([data-quick-group]),
  html body.ems-site .ems-dash .shot-hex-grid[data-quick-group].is-active{
    flex-wrap: nowrap;
    overflow-x: auto;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: var(--d2-sp-nudge);
  }
  html body.ems-site .ems-dash .shot-hex-grid::-webkit-scrollbar{ display: none; }
  html body.ems-site .ems-dash .shot-hex-link{ flex: none; scroll-snap-align: start; }
}
</style>

<script>
/* DASH-V2 — سلوكانِ لا يقدر عليهما CSS وحدَه. كلاهما إضافةُ صنفٍ لا بناءُ شجرة. */
(function () {
  'use strict';

  /* ④ ◆ **زُهد**: كانت هنا آلةُ «المزيد» — تحقن زرًّا وتطوي ما زاد على أربعِ
       بلاطاتٍ وتُزامن ظهورَ الزرِّ مع مجموعتِه (ثلاثُ دوالَّ وحالةٌ ومستمعان).
       وقد زالت كلُّها: البلاطةُ صارت مقصدًا بحجمِ نصِّه فتلتفُّ الثلاثَ عشرةَ
       في سطرَين، فلا شيءَ يُخفى ولا شيءَ يُكشف. **الشيفرةُ التي لا تُكتب لا
       تُصان ولا تعطب** — وهذا الإصلاحُ نزعُ آلةٍ لا تحسينُها. */

  /* ⑧ المجموعةُ الحاليةُ في السايدبار تُفتح.
       ◆ ملاحظةٌ صريحة: الافتراضُ العامُّ في insidebar.php أن **كلَّ** المجموعاتِ
         مطويّةٌ («قرار المستخدم»). هذا الفتحُ **مقصورٌ على اللوحةِ وحدَها**
         ولا يمسُّ أيَّ شاشةٍ أخرى — ويحتاج إقرارَ المالكِ عند التقييم. */
  function openCurrentGroup() {
    var sb = document.getElementById('sidebar');
    if (!sb) { return; }
    var grp = sb.querySelector('li.nav-group.has-active')
           || sb.querySelector('li.nav-group.is-selected');
    if (!grp) { return; }
    grp.classList.add('open', 'd2-auto-open');
  }


  /* ◆ **ما بقي بعد إلغاءِ الطيّ**: قسمانِ في الصفحةِ **بلا عنوانٍ أصلًا** —
       لوحُ الأرقامِ العامةِ وشبكةُ الرسوم — بينما لكلِّ قسمٍ آخرَ ترويستُه.
       الترويستانِ ظهرتا أوّلَ مرّةٍ عرَضًا مع آلةِ الطيِّ (كانت تحتاج مرساةً
       للمقبض)، وهما نافعتانِ بذاتِهما فبقيتا. **والتسميتانِ من تأليفي**
       وتنتظرانِ إقرارَك — وحذفُهما سطرانِ إن لم تُقرّهما. */
  function addMissingHeadings() {
    [['.shot-num-panel:not(.shot-num-panel-secondary)', 'أرقام عامة'],
     ['.shot-charts', 'الرسوم البيانية']].forEach(function (s) {
      var root = document.querySelector(s[0]);
      if (!root || root.querySelector(':scope > .d2-made')) { return; }
      var h = document.createElement('div');
      h.className = 'shot-section-title d2-made';
      h.textContent = s[1];
      root.insertBefore(h, root.firstChild);
    });
  }

  /* ⑩-ج نقلُ شريحتَي الجلسةِ الفريدتَين إلى سطرِ السياقِ وطيُّ المكرَّرتَين.
       نقلُ عقدةٍ لا استنساخُها — فلا نسختانِ تتفرّقانِ لاحقًا. */
  function mergeSessionIntoContext() {
    var ctx  = document.querySelector('.ems-dash .ems-page-context')
            || document.querySelector('.ems-page-context');
    var sess = document.querySelector('.shot-session');
    if (!ctx || !sess || sess.classList.contains('d2-merged')) { return; }

    ['date', 'company'].forEach(function (k) {          /* مكرَّرتانِ في سطرِ السياقِ نفسِه */
      var c = sess.querySelector('[data-d2-chip="' + k + '"]');
      if (c) { c.classList.add('d2-dup'); }
    });
    /* ◆ الفريدتانِ تُنقلان — **إن حملتا قيمةً**. والحقلُ الاختياريُّ الفارغُ
         (مستخدمٌ بلا مشروع) لا يُنقَل: شرطةٌ عاريةٌ في شريطِ سياقٍ مضغوطٍ
         تُعلن حقلًا ولا تُفيد شيئًا. والشرطةُ موضعُها حيثُ تُنتظَر قيمةٌ —
         في بطاقةٍ أو عمودٍ — لا في سطرِ سياق. والعقدةُ تبقى في الشجرةِ
         مطويّةً فلا تُفقَد معلومة. */
    ['project', 'user'].forEach(function (k) {
      var c = sess.querySelector('[data-d2-chip="' + k + '"]');
      if (!c) { return; }
      var v = (c.textContent || '').replace(/\s+/g, '').replace(/[—–-]/g, '');
      if (v === '') { c.classList.add('d2-dup'); return; }
      ctx.appendChild(c);
    });
    sess.classList.add('d2-merged');
  }

  /* ⑩-ب مُوائمةُ الشجرةِ مع الترتيبِ البصريّ — فيتّحد مسارُ لوحةِ المفاتيحِ
       مع مسارِ العين. تُصفَّر الرتبُ بعدَها بصنفٍ واحد. */
  function syncDomOrder() {
    [['.ems-dash .shot-body',       ['.shot-breadcrumb', '.shot-quick-zone', '.shot-lower-zone']],
     ['.ems-dash .shot-lower-zone', ['.shot-session', '.shot-ops',
                                     '.shot-num-panel:not(.shot-num-panel-secondary)',
                                     '.shot-num-panel-secondary', '.shot-charts']]
    ].forEach(function (pair) {
      var host = document.querySelector(pair[0]);
      if (!host || host.classList.contains('d2-dom-ordered')) { return; }
      pair[1].forEach(function (sel) {
        var n = host.querySelector(':scope > ' + sel);
        if (n) { host.appendChild(n); }
      });
      host.classList.add('d2-dom-ordered');
    });
  }

  /* ⑩-د سطرُ التاريخِ يلتحق بسطرِ السياق.
       ◆ التاريخُ كان يُعرض مرّتَين متجاورتَين: «لحظة القراءة: 2026-09-01 19:09»
         في سطرِ السياق، و«الثلاثاء · 2026-09-01» في سطرٍ مستقلٍّ تحته.
         والمفيدُ الوحيدُ في الثاني **اسمُ اليوم**.
       ◆ فيُنقَل السطرُ إلى سطرِ السياقِ فيصير بندًا فيه — يكسب المعنى ولا
         يكلّف سطرًا. والمكسبُ ليس جماليًّا فقط: ثمانيةٌ وعشرون بكسلًا هي
         الفرقُ بين لوحةٍ تشغيليةٍ **كاملةٍ** فوقَ الطيِّ وأخرى مقطوعة. */
  function mergeDateIntoContext() {
    var ctx = document.querySelector('.ems-dash .ems-page-context')
           || document.querySelector('.ems-page-context');
    var bc  = document.querySelector('.ems-dash .shot-breadcrumb');
    if (!ctx || !bc || bc.classList.contains('d2-merged')) { return; }
    bc.classList.add('d2-merged');
    ctx.appendChild(bc);
  }

  function boot() {
    openCurrentGroup();
    addMissingHeadings();
    mergeSessionIntoContext();
    mergeDateIntoContext();
    syncDomOrder();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
<!-- ══ DASH-V2 · END ══════════════════════════════════════════════════════ -->


  <!-- التوببار المشترك يُعرض الآن من insidebar.php (includes/topbar.php) -->

  <div class="shot-body">
    <div class="shot-breadcrumb" id="emsClock"><?php
    /* DASH-V2 ⑦ · BEGIN
       الأصل حرفًا: وسمُ PHP قصيرٌ يطبع تنسيقَ `Y F d, l` من دالّةِ التاريخِ الخام.
       ⛔ ولا يُكتب الاستدعاءُ هنا نصًّا: ماسحُ VT-07 يقرأ نصَّ التعليقِ كما يقرأ
          الشيفرة، فاقتباسُ الاستدعاءِ يُعَدُّ استدعاءً — فيُوصَف ولا يُقتبَس.
       كانت الشاشةُ تحمل ثلاثَ صيغِ تاريخٍ بلغتَين. الصيغةُ الآن واحدةٌ من
       الدالةِ الموحَّدةِ (includes/date_format.php · Y-m-d) — وهي الدالةُ التي
       يقيس سجلُّ الدَّينِ VT-07 ما لم يمرَّ بها بعد. واسمُ اليومِ يبقى ولا
       يُفقد، لكنه يُعرَّب فلا تختلط لغتان في سطرٍ واحد. */
    require_once __DIR__ . '/../includes/date_format.php';
    $d2Days = array('الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت');
    echo htmlspecialchars($d2Days[(int) date('w')] . ' · ' . ems_fmt_now('date'), ENT_QUOTES, 'UTF-8');
    /* DASH-V2 ⑦ · END */
    ?></div>

    <div class="shot-quick-zone">
    <h2 class="shot-section-title">الوصول السريع<span id="quickGroupName" class="shot-quick-group-name"></span></h2>

    <?php
    // قاعدة التصميم القائمة: إذا تبقّى فراغ في آخر صف، يُمدّ الكارت الأخير ليملأ
    // الصف كاملاً. تُحسب لكل شبكةٍ على حدة لأن كل مجموعةٍ لها عدد بلاطاتٍ مختلف.
    $renderQuickGrid = function (array $tiles, $groupKey = null) {
      if (empty($tiles)) {
        return;
      }
      $n = count($tiles);
      $gridClass = ($n > 2 && ($n % 2 === 1)) ? 'cols-3' : 'cols-4';
      $spanFor = function ($cols) use ($n) {
        $rem = $n % $cols;                        // عدد الكاردات في الصف الأخير
        return ($rem === 0) ? 1 : ($cols - $rem + 1); // يملأ الأعمدة الشاغرة + عموده
      };
      $attrs = $groupKey !== null
        ? ' data-quick-group="' . htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') . '"'
        : '';
      ?>
      <div class="shot-hex-grid <?= $gridClass ?>"<?= $attrs ?> data-allow-style
           style="--last-span-desktop: <?= $spanFor($gridClass === 'cols-3' ? 3 : 4) ?>; --last-span-mid: <?= $spanFor(2) ?>;">
        <?php foreach ($tiles as $lk): ?>
        <a href="<?= htmlspecialchars($lk[0]) ?>" class="shot-hex-link">
          <span class="shot-hex-icon"><i class="<?= htmlspecialchars($lk[2]) ?>"></i></span>
          <span class="shot-hex-title"><?= htmlspecialchars($lk[1]) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php
    };

    if (!empty($dashGroupTiles)) {
      // شبكةٌ لكل مجموعة، وتُظهر إحداها فقط — الاختيار من السايدبار عبر JS.
      foreach ($dashGroupTiles as $gk => $g) {
        $renderQuickGrid($g['tiles'], $gk);
      }
    } else {
      // لا مجموعات لهذا الدور: السلوك القديم حرفيًّا (روابط is_quick بلا تجميع).
      $quickTiles = !empty($links) ? $links : [['../main/dashboard.php', 'لوحة التحكم', 'fa-solid fa-house']];
      $renderQuickGrid($quickTiles);
    }
    ?>

    <?php if (!empty($dashGroupTiles)): ?>
    <script>
      /* بلاطات الوصول السريع تتبع المجموعة المختارة في السايدبار.
         الافتراض عند أول فتح: أوّل مجموعة. والتبديل فوريٌّ بلا طلبٍ للشبكة
         لأن كل الشبكات مطبوعةٌ خادميًّا ومخفيّة إلا واحدة. */
      (function () {
        var grids = Array.prototype.slice.call(document.querySelectorAll('.shot-hex-grid[data-quick-group]'));
        if (!grids.length) return;

        var nameEl = document.getElementById('quickGroupName');
        var names  = <?= json_encode(array_map(function ($g) { return $g['name']; }, $dashGroupTiles), JSON_UNESCAPED_UNICODE) ?>;
        var keys   = grids.map(function (g) { return g.getAttribute('data-quick-group'); });

        function show(key) {
          if (keys.indexOf(key) === -1) { key = keys[0]; }
          grids.forEach(function (g) {
            g.classList.toggle('is-active', g.getAttribute('data-quick-group') === key);
          });
          if (nameEl) { nameEl.textContent = names[key] || ''; }
        }

        var saved = null;
        try { saved = window.localStorage.getItem('ems.navGroups.selected'); } catch (e) {}
        show(saved || keys[0]);

        document.addEventListener('ems:navgroup-selected', function (ev) {
          if (ev && ev.detail && ev.detail.key) { show(ev.detail.key); }
        });
      })();
    </script>
    <?php endif; ?>
    </div>

    <div class="shot-lower-zone">
    <div class="shot-session">
      <div class="shot-session-title">بيانات الجلسة</div>
      <div class="shot-session-row">
        <div class="shot-session-chip" data-d2-chip="date">
          <span class="chip-icon"><i class="far fa-calendar"></i></span>
          <strong><?php
          /* DASH-V2 ⑦ · BEGIN — الأصل حرفًا:  date('Y M d')
             الصيغةُ الموحَّدةُ نفسُها المستعملةُ في سطرِ التاريخِ أعلاه. */
          echo htmlspecialchars(ems_fmt_now('date'), ENT_QUOTES, 'UTF-8');
          /* DASH-V2 ⑦ · END */
          ?></strong>
        </div>
        <div class="shot-session-chip" data-d2-chip="company">
          <span class="chip-icon"><i class="fas fa-building"></i></span>
          <strong><?php
          /* DASH-V2 ⑨ · BEGIN — الأصل حرفًا:
               <?" . "= $companyName ? htmlspecialchars($companyName) : 'اكويشن' " */
          echo $companyName !== '' ? htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') : '&mdash;';
          /* DASH-V2 ⑨ · END */
          ?></strong>
        </div>
        <div class="shot-session-chip" data-d2-chip="project">
          <span class="chip-icon"><i class="fas fa-gear"></i></span>
          <strong><?php
          /* DASH-V2 ⑨ · BEGIN — الأصل حرفًا:
               <?" . "= $projectName ? htmlspecialchars($projectName) : 'ادارة التشغيل' "
             ◆ **احتياطيٌّ مُلفَّقٌ يناقض الصفحةَ نفسَها**: `$projectName` اسمُ
               **مشروعٍ** من جدولِ `project`. وحين لا مشروعَ للمستخدم كانت تُطبع
               العبارةُ **مثبَّتةً في الشيفرة** «ادارة التشغيل» — فتقول الشريحةُ
               إدارةً، ويقول الشريطُ العلويُّ في الصفحةِ نفسِها «ادارة المبيعات».
               ومثلُها شريحةُ الشركةِ: احتياطيُّها 'اكويشن' بهجاءٍ يخالف
               «ايكوبيشن» المعروضةَ في سطرِ السياقِ فوقَها.
             ◆ وهذا نقضٌ لقاعدةِ الصفحةِ المكتوبةِ فيها حرفًا: «لا نلفّق أصفارًا
               — تُعرض شرطة «—» بدل عدّاد كاذب». طُبِّقت على الأرقامِ ولم تُطبَّق
               على النصوص. فالشرطةُ الآن تعمُّ الاثنتَين. */
          echo $projectName !== '' ? htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') : '&mdash;';
          /* DASH-V2 ⑨ · END */
          ?></strong>
        </div>
        <div class="shot-session-chip" data-d2-chip="user">
          <span class="chip-icon"><i class="far fa-user"></i></span>
          <strong><?= htmlspecialchars($userName) ?></strong>
        </div>
      </div>
    </div>

    <?php
    // لوحةُ الدورِ ①②③④⑤ — تُصيَّر هنا لأن «أسئلةَ أولِ اليوم» تُقرأ قبلَ
    // العدّاداتِ العامةِ لا بعدَها. و⑥⑦ في شبكةِ الرسومِ أدناه.
    // (القالبُ يخرج صامتًا حين لا تكون هذه الصفحةُ لوحةَ الدور.)
    include __DIR__ . '/../includes/dash_role_board.php';
    ?>

    <?php
    // دور بلا فرع عدادات: لا نلفّق أصفارًا (UI-DEF-02 / قاعدة «لا رقم بلا بيانات») —
    // تُعرض شرطة «—» بدل عدّاد كاذب.
    $displayStats = !empty($stats) ? $stats : [
      ['fa-users', null, 'العملاء', 'or'],
      ['fa-project-diagram', null, 'المشاريع', 'or'],
      ['fa-file-contract', null, 'العقود', 'or'],
      ['fa-user-shield', null, 'المستخدمون', 'or'],
    ];
    // عدَدُ أعمدةِ الأرقامِ يُعلَن على الحاويةِ لا يُترك للافتراض: عدّاداتُ الدورِ
    // تختلف (ثلاثةٌ لإدارةِ الموردين · أربعةٌ للتشغيل · سبعةٌ لمدير الحركة)،
    // والشبكةُ الموحّدةُ تفرض أربعةً — فكان دورُ الثلاثةِ يترك خانةً فارغة.
    // (2..6 هو مدى `data-cols` المعتمَد في `ems-statcards.css`؛ وما فوقَه أربعة.)
    $dashStatCols = count($displayStats);
    if ($dashStatCols < 2 || $dashStatCols > 6) { $dashStatCols = 4; }

    // ولوحُ الشرطاتِ لا يُعرض فوقَ لوحةِ دورٍ حيّة: دورٌ بلا فرعِ عدّاداتٍ
    // (مدير الصلاحيات مثلًا) كان يُخرج أربعَ شرطاتٍ «—» تحتَ لوحةٍ فيها أرقامُه
    // الحقيقيةُ كاملةً — ضجيجٌ يقرأه الناظرُ عطبًا. والشرطاتُ تبقى حيثُ تعني
    // شيئًا: صفحةٌ بلا لوحةِ دورٍ تعلن نقصَ مصدرِها ولا تلفّق صفرًا.
    $dashShowNumPanel = !empty($stats) || empty($dash_board);
    ?>
    <?php if ($dashShowNumPanel): ?>
    <div class="shot-num-panel">
      <div class="shot-stat-grid" data-cols="<?= (int) $dashStatCols ?>">
        <?php foreach ($displayStats as $st): ?>
        <div class="shot-stat-card" title="<?= htmlspecialchars($st[2]) ?> — الوحدة: سجل · الفترة: لحظي · بلا مقارنة معلنة">
          <div class="shot-stat-label"><?= htmlspecialchars($st[2]) ?></div>
          <?php if ($st[1] === null): ?>
          <div class="shot-stat-value">&mdash;</div>
          <?php else: ?>
          <div class="shot-stat-value" data-count="<?= intval($st[1]) ?>">00</div>
          <?php // UI-07: إعلان الوحدة والفترة تحت الرقم (الثيم الإنلайн لا يُمس — سطر خافت فقط) ?>
          <div class="db-1">سجل · لحظي</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <a href="../logout.php" class="shot-logout">
        خروج <i class="fas fa-power-off"></i>
      </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($analyticsSummaryCards)): ?>
    <div class="shot-num-panel shot-num-panel-secondary">
      <div class="shot-session-title">إحصائيات الأداء</div>
      <div class="shot-stat-grid" data-cols="<?= (int) max(2, min(6, count($analyticsSummaryCards))) ?>">
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

      <?php if ($dash_board): /* ⑥⑦ من لوحة الدور — بطاقتا رسمٍ بلغةِ الشبكةِ نفسِها */ ?>
      <div class="shot-chart-card">
        <div class="shot-chart-head">
          <span class="shot-chart-title"><?= htmlspecialchars($dash_board['pulse_title']) ?></span>
          <span class="shot-chart-note">آخر 7 أيام</span>
        </div>
        <div class="shot-chart-wrap">
          <canvas id="rbPulse"></canvas>
        </div>
      </div>

      <div class="shot-chart-card">
        <div class="shot-chart-head">
          <span class="shot-chart-title">عملي الأخير</span>
          <span class="shot-chart-note">من سجل النشاط</span>
        </div>
        <div class="shot-ops-recent">
          <?php if (empty($dash_board['recent'])): ?>
            <p class="shot-ops-empty">لا نشاط مسجلا بعد</p>
          <?php else: foreach ($dash_board['recent'] as $rc):
            $rcHref = preg_replace('#^.*?/ems/#', '../', (string) ($rc['url'] ?? ''));
            if ($rcHref === '') { $rcHref = '#'; } ?>
            <a class="shot-ops-row" href="<?= htmlspecialchars($rcHref, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $rc['screen_name'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="shot-ops-row-text">
                <i class="fas fa-clock-rotate-left"></i>
                <span><?= htmlspecialchars((string) $rc['screen_name'], ENT_QUOTES, 'UTF-8') ?></span>
              </span>
              <span class="shot-ops-time"><?= htmlspecialchars(date('m/d H:i', strtotime((string) $rc['last_at'])), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    </div>
  </div>

</div><!-- /.ems-dash -->

<!-- Chart.js -->
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<?php if ($dash_board): ?>
<script>
  /* بيانات ⑥ نبضِ الأداء — تُبثُّ قبل شفرةِ الرسمِ لتُقرأ منها، والسلسلةُ
     الثانيةُ قد تكون بلا اسمٍ (دورٌ بسلسلةٍ واحدة) فتُسقَط عند الرسم. */
  window.RB_PULSE = <?= json_encode(array(
    'labels' => $dash_board['pulse']['labels'],
    'in'     => $dash_board['pulse']['in'],
    'out'    => $dash_board['pulse']['out'],
    'series' => $dash_board['pulse_series'],
  ), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
<script>
(function () {
  const AP = <?= json_encode($analyticsPayload) ?>;

  function formatTwoDigits(value, isFloat) {
    if (isFloat) {
      const safeValue = isFinite(value) ? value : 0;
      const fixed = safeValue.toFixed(1);
      const parts = fixed.split('.');
      /* DASH-V2 ⑧ · BEGIN — الأصل حرفًا:
           const intPart = String(Math.max(0, parseInt(parts[0], 10) || 0)).padStart(2, '0');
         ◆ **الصفحةُ كانت تعرض العددَ بطريقتَين**: لوحُ الأرقامِ يحشو الآحادَ
           بصفرٍ («01 العملاء» · «07 المستخدمون» · «00 التعطلات») ولوحةُ الدورِ
           لا تحشو («1 تنتهي خلال 30 يومًا»). والحشوُ يُقرأ رمزًا لا عددًا.
           فيُنزع، وتتّحد قراءةُ الرقمِ في الشاشةِ كلِّها. */
      const intPart = String(Math.max(0, parseInt(parts[0], 10) || 0));
      return intPart + '.' + parts[1];
    }
    const n = Math.max(0, Math.round(isFinite(value) ? value : 0));
    return String(n);
    /* DASH-V2 ⑧ · END */
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
  /* UXW-01 ①: ألوانُ الرسومِ تُقرأ من رموزِ CSS المعرَّفةِ في كتلةِ الأنماطِ
     أعلاه (‎--dash-*‎) بدلَ قيمٍ مثبَّتةٍ هنا — والقيمُ نفسُها لم تتغير. */
  const emsPalette = getComputedStyle(document.documentElement);
  const dashColor = function (name) {
    return (emsPalette.getPropertyValue('--dash-' + name) || '').trim();
  };
  Chart.defaults.color = dashColor('font-ink');
  Chart.defaults.font.family = emsPalette
    .getPropertyValue('--font-ar')
    .trim() || "'IBM Plex Sans Arabic','Tajawal','Cairo',sans-serif";
  Chart.defaults.plugins.legend.display = false;

  const gridColor = dashColor('grid');
  const tickColor = dashColor('tick');

  /* UI-DEF-07 (سلّم الإغلاق L3): لا رسمَ بلا بياناتٍ يعرض محاورَ افتراضية —
     حالةٌ فارغةٌ مفسَّرةٌ بدلَه (UXR-0084 · chartGuard على الشاشة المصابة نفسها).
     المغلِّفُ يستعمل EmsUI.chartGuard متى حضر (defer) وإلا بديلَه المكافئ. */
  function emsChartGuard(ctx, seriesArrays, renderFn) {
    var total = 0;
    (seriesArrays || []).forEach(function (arr) {
      (arr || []).forEach(function (v) { total += Math.abs(parseFloat(v) || 0); });
    });
    if (total > 0) { return renderFn(); }
    var host = ctx && ctx.parentNode ? ctx.parentNode : null;
    if (!host) { return null; }
    if (window.EmsUI && EmsUI.chartGuard) {
      return EmsUI.chartGuard(host, [{ data: [] }], renderFn,
        { reason: 'لا بيانات في الفترة المعروضة — الرسم لا يعرض بمحاور افتراضية' });
    }
    host.innerHTML = '<div class="db-2">' +
      'لا بيانات في الفترة المعروضة — الرسم لا يعرض بمحاور افتراضية</div>';
    return null;
  }

  /* ── Equipment Donut ── */
  const eqCtx = document.getElementById('chartEquipStatus');
  if (eqCtx) {
    emsChartGuard(eqCtx, [AP.equipmentStatus], function () {
    return new Chart(eqCtx, {
      type: 'doughnut',
      data: {
        labels: ['نشطة', 'متوقفة'],
        datasets: [{
          data: AP.equipmentStatus,
          backgroundColor: [dashColor('eq-active'), dashColor('eq-stopped')],
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
    });
  }

  /* ── Hours Bar ── */
  const hbCtx = document.getElementById('chartHoursBar');
  if (hbCtx) {
    emsChartGuard(hbCtx, [[parseFloat(AP.kpis[1]), parseFloat(AP.monthBreakdownHours)]], function () {
    return new Chart(hbCtx, {
      type: 'bar',
      data: {
        labels: ['ساعات العمل', 'ساعات التعطل'],
        datasets: [{
          data: [parseFloat(AP.kpis[1]), parseFloat(AP.monthBreakdownHours)],
          backgroundColor: [dashColor('bar-work'), dashColor('bar-fault')],
          borderColor: [dashColor('line-work'), dashColor('line-fault')],
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
    });
  }

  /* ── Daily Trend — «مسار الأداء اليومي» الرسمُ المرصودُ في UI-DEF-07 نفسُه ── */
  const trCtx = document.getElementById('chartTrend');
  if (trCtx) {
    emsChartGuard(trCtx, [AP.trendWork, AP.trendFault], function () {
    return new Chart(trCtx, {
      type: 'line',
      data: {
        labels: AP.trendLabels,
        datasets: [
          {
            label: 'ساعات العمل',
            data: AP.trendWork,
            borderColor: dashColor('line-work'),
            backgroundColor: dashColor('line-work-fill'),
            fill: true,
            tension: .35,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2
          },
          {
            label: 'ساعات التعطل',
            data: AP.trendFault,
            borderColor: dashColor('line-fault'),
            backgroundColor: dashColor('line-fault-fill'),
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
    });
  }

  /* ── ⑥ نبضُ الأداء (لوحة الدور) ──────────────────────────────────────
     يُرسم بالحارسِ نفسِه وبألوانِ اللوحةِ نفسِها (‎--dash-*‎) لا بقيمٍ مثبَّتةٍ
     تخصُّ اللوحةَ العامة — فالبطاقتان في شبكةٍ واحدةٍ فلا تختلف نغمتاهما. */
  const rbCtx = document.getElementById('rbPulse');
  if (rbCtx && window.RB_PULSE) {
    emsChartGuard(rbCtx, [RB_PULSE.in, RB_PULSE.out], function () {
    return new Chart(rbCtx, {
      type: 'bar',
      data: {
        labels: RB_PULSE.labels,
        datasets: [
          { label: RB_PULSE.series[0], data: RB_PULSE.in,  backgroundColor: dashColor('bar-work'),  borderColor: dashColor('line-work'),  borderWidth: 1, borderRadius: 6 },
          { label: RB_PULSE.series[1], data: RB_PULSE.out, backgroundColor: dashColor('bar-fault'), borderColor: dashColor('line-fault'), borderWidth: 1, borderRadius: 6 }
        ].filter(function (d) { return d.label !== ''; })
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, rtl: true, position: 'bottom', labels: { color: tickColor, font: { size: 11 }, boxWidth: 12 } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: tickColor } },
          y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } }
        }
      }
    });
    });
  }

  /* ── Live Clock ── */
  function updateClock() {
    var el = document.getElementById('emsClock');
    if (!el) return;
    var now = new Date();
    /* DASH-V2 ⑦ · BEGIN — الأصل حرفًا:
         el.textContent = now.toLocaleDateString('en-GB', {
           year: 'numeric', month: 'long', day: 'numeric', weekday: 'long'
         });
       ◆ **هنا كان مصدرُ التاريخِ الإنجليزيّ**، لا في PHP: الخادمُ يصيّره
         عربيًّا ثم يدهسه هذا السطرُ بلغةٍ مثبَّتةٍ («en-GB») عندَ التحميلِ
         وكلَّ ستينَ ثانية. فتعريبُ الخادمِ وحدَه لا يظهر في الشاشةِ أبدًا.
       ◆ والصيغةُ هنا هي الصيغةُ الخادميةُ نفسُها: يومٌ عربيٌّ + Y-m-d. */
    var d2Days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    var d2p = function (n) { return (n < 10 ? '0' : '') + n; };
    el.textContent = d2Days[now.getDay()] + ' · ' + now.getFullYear()
      + '-' + d2p(now.getMonth() + 1) + '-' + d2p(now.getDate());
    /* DASH-V2 ⑦ · END */
  }
  updateClock();
  setInterval(updateClock, 60000);

})();
</script>

</body>
</html>
