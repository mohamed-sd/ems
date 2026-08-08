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
  if ($rb_route !== null) { header('Location: ../' . $rb_route); exit(); }
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

$AC = [
  'or' => ['bg' => '#F7931A', 'soft' => '#FFF4E6', 'text' => '#B45309', 'ico' => '#F7931A'],
  'ok' => ['bg' => '#16A34A', 'soft' => '#F0FDF4', 'text' => '#15803D', 'ico' => '#16A34A'],
  'warn' => ['bg' => '#D97706', 'soft' => '#FFFBEB', 'text' => '#B45309', 'ico' => '#D97706'],
  'err' => ['bg' => '#DC2626', 'soft' => '#FEF2F2', 'text' => '#B91C1C', 'ico' => '#DC2626'],
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

/* Sidebar: inherits the unified layout from ems.main.all.style.css
   (GLOBAL SIDEBAR section). Dashboard no longer redefines its
   position/dimensions/colors — single source of truth. */

/* ── Top bar ──────────────────────────────────────────────────
   The bar itself now lives in the shared component
   (includes/topbar.php · styling .ems-topbar* in ems.main.all.style.css).
   Only the reset that keeps .ems-dash.main free of stray pseudo borders
   is kept here. The shared `body.ems-site { padding-top }` reserves the
   50px the fixed bar needs, so .shot-body no longer adds its own top gap. */
.ems-dash.main::before,
.ems-dash.main::after {
  content: none !important;
  display: none !important;
}

.shot-body {
  padding: 0 0px 14px;
  background: #e2e2e2;
  margin-top: -1px;
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

/* اسم المجموعة التي تقود البلاطات حاليًّا — يتبدّل بنقر رأس مجموعةٍ في السايدبار */
.shot-quick-group-name {
  margin-right: 8px;
  padding: 2px 10px;
  border-radius: 999px;
  background: rgba(244, 197, 66, 0.18);
  color: #8a6a10;
  font-size: 0.82rem;
  font-weight: 700;
  vertical-align: middle;
}

.shot-quick-group-name:empty {
  display: none;
}

/* شبكةٌ لكل مجموعة، والمعروضُ منها واحدةٌ فقط */
.shot-hex-grid[data-quick-group] {
  display: none;
}

.shot-hex-grid[data-quick-group].is-active {
  display: grid;
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

/* قاعدة ملء الصف الأخير: يُمدّ الكارت الأخير على الأعمدة الشاغرة المتبقية
   حتى لا تظهر فراغات في نهاية الصف. القيمة تُحسب في PHP حسب عدد الكاردات. */
.shot-hex-grid > .shot-hex-link:last-child {
  grid-column: span var(--last-span-desktop, 1);
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

  /* عند عمودين: يُمدّ الكارت الأخير لعمودين إن كان الصف الأخير ناقصاً */
  .shot-hex-grid > .shot-hex-link:last-child {
    grid-column: span var(--last-span-mid, 1);
  }

  .shot-session-row,
  .shot-stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  /* Sidebar mobile behavior (off-canvas) is governed by the unified
     CSS breakpoint (≤768px); the dashboard only adjusts its content here. */
  .shot-body {
    padding-top: 10px;
    margin-top: 0;
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

  /* عمود واحد: لا فراغات أصلاً، فيعود الكارت الأخير لوضعه الطبيعي */
  .shot-hex-grid > .shot-hex-link:last-child {
    grid-column: auto;
  }

  .shot-stat-value {
    font-size: 4.2rem;
  }
}
</style>

<div class="ems-dash main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Dashboard', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>


  <!-- التوببار المشترك يُعرض الآن من insidebar.php (includes/topbar.php) -->

  <div class="shot-body">
    <div class="shot-breadcrumb" id="emsClock"><?= date('Y F d, l') ?></div>

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
      <div class="shot-hex-grid <?= $gridClass ?>"<?= $attrs ?>
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
    // دور بلا فرع عدادات: لا نلفّق أصفارًا (UI-DEF-02 / قاعدة «لا رقم بلا بيانات») —
    // تُعرض شرطة «—» بدل عدّاد كاذب.
    $displayStats = !empty($stats) ? $stats : [
      ['fa-users', null, 'العمـــــلاء', 'or'],
      ['fa-project-diagram', null, 'المشــــاريع', 'or'],
      ['fa-file-contract', null, 'العقود', 'or'],
      ['fa-user-shield', null, 'المستخدمون', 'or'],
    ];
    ?>
    <div class="shot-stat-panel">
      <div class="shot-stat-grid">
        <?php foreach ($displayStats as $st): ?>
        <div class="shot-stat-card" title="<?= htmlspecialchars($st[2]) ?> — الوحدة: سجل · الفترة: لحظي · بلا مقارنة معلنة">
          <div class="shot-stat-label"><?= htmlspecialchars($st[2]) ?></div>
          <?php if ($st[1] === null): ?>
          <div class="shot-stat-value">&mdash;</div>
          <?php else: ?>
          <div class="shot-stat-value" data-count="<?= intval($st[1]) ?>">00</div>
          <?php // UI-07: إعلان الوحدة والفترة تحت الرقم (الثيم الإنلайн لا يُمس — سطر خافت فقط) ?>
          <div style="font-size:10px;opacity:.55;margin-top:2px">سجل · لحظي</div>
          <?php endif; ?>
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
        { reason: 'لا بيانات في الفترة المعروضة — الرسم لا يُعرض بمحاور افتراضية' });
    }
    host.innerHTML = '<div style="padding:24px;text-align:center;opacity:.75;font-size:.85rem">' +
      'لا بيانات في الفترة المعروضة — الرسم لا يُعرض بمحاور افتراضية</div>';
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
