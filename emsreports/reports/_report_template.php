<?php
/* ═══════════════════════════════════════════════════════════════════════════
   قالب التقارير الاحترافي — EMS Professional Reports Template v2
   يتضمن: SaaS scoping · Dropdown filters · Charts · Export · RTL design
═══════════════════════════════════════════════════════════════════════════ */

session_start();
if (!isset($_SESSION['user'])) { header('Location: ../../index.php'); exit; }
if (!isset($REPORT_CODE) || $REPORT_CODE === '') { die('REPORT_CODE is required'); }

require_once '../../config.php';
require_once '../includes/functions.php';
require_once '../includes/export.php';

// ─── بيانات الجلسة ───────────────────────────────────────────────────────
$roleId       = intval($_SESSION['user']['role']);
$companyId    = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$isSuperAdmin = ($roleId === -1);
$userName     = htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8');

// ─── AJAX: إرجاع المعدات حسب النوع لفلتر الاسم ──────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1'
    && isset($_GET['action']) && $_GET['action'] === 'get_equipments_by_type') {
    header('Content-Type: application/json; charset=utf-8');
    $sc_e = rptCompanyScope($conn, 'e', 'equipments', $companyId, $isSuperAdmin);
    $ajaxWhere = ["($sc_e)"];
    if (isset($_GET['type']) && $_GET['type'] !== '') {
        $ajaxWhere[] = "e.type=" . intval($_GET['type']);
    }
    if (isset($_GET['supplier_id']) && intval($_GET['supplier_id']) > 0) {
        $ajaxWhere[] = "e.suppliers=" . intval($_GET['supplier_id']);
    }
    $ajaxSql = "SELECT e.id, e.name, e.code FROM equipments e WHERE " . implode(' AND ', $ajaxWhere) . " ORDER BY e.name ASC";
    $ajaxRes = mysqli_query($conn, $ajaxSql);
    $ajaxData = [];
    if ($ajaxRes) while ($ar = mysqli_fetch_assoc($ajaxRes)) $ajaxData[] = $ar;
    echo json_encode($ajaxData);
    exit;
}

// ─── التحقق من الصلاحية ──────────────────────────────────────────────────
if (!checkReportPermission($conn, $REPORT_CODE, $roleId)) {
    ?><!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">
    <title>غير مصرح</title>
    <link rel="stylesheet" href="/ems/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
    </head><body style="background:#f0f2f8;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Cairo,sans-serif;">
    <div style="text-align:center;background:#fff;padding:48px 36px;border-radius:20px;box-shadow:0 14px 44px rgba(12,28,62,.13);max-width:440px;">
        <div style="width:72px;height:72px;border-radius:50%;background:rgba(220,38,38,.08);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fas fa-lock" style="font-size:2rem;color:#dc2626;"></i></div>
        <h2 style="color:#0c1c3e;font-weight:900;margin-bottom:8px;">غير مصرح بالوصول</h2>
        <p style="color:#64748b;margin-bottom:24px;">لا تملك صلاحية لعرض هذا التقرير. تواصل مع مدير النظام.</p>
        <a href="../index.php" style="background:#2563eb;color:#fff;padding:10px 24px;border-radius:10px;text-decoration:none;font-weight:700;">
            <i class="fas fa-arrow-right"></i> العودة للتقارير</a>
    </div></body></html><?php exit;
}

// ─── بيانات الكتالوج ─────────────────────────────────────────────────────
$catalog        = getReportsCatalog();
$meta           = $catalog[$REPORT_CODE] ?? ['name_ar' => 'تقرير', 'icon' => 'fa-chart-line', 'description' => '', 'category' => ''];
$page_title     = $meta['name_ar'];
$reportCategory = $meta['category'] ?? '';

// ─── فلاتر GET ───────────────────────────────────────────────────────────
$fDateFrom       = isset($_GET['date_from'])       ? trim($_GET['date_from'])       : '';
$fDateTo         = isset($_GET['date_to'])         ? trim($_GET['date_to'])         : '';
$fProjectId      = isset($_GET['project_id'])      ? intval($_GET['project_id'])    : 0;
$fSupplierId     = isset($_GET['supplier_id'])     ? intval($_GET['supplier_id'])   : 0;
$fDriverId       = isset($_GET['driver_id'])       ? intval($_GET['driver_id'])     : 0;
$fStatus         = (isset($_GET['status']) && $_GET['status'] !== '') ? intval($_GET['status']) : -1;
$fSearch         = isset($_GET['search'])          ? trim($_GET['search'])          : '';
// فلاتر إضافية للتقارير التفصيلية
$fShift          = isset($_GET['shift'])           ? trim($_GET['shift'])           : '';
$fEquipId        = isset($_GET['equip_id'])        ? intval($_GET['equip_id'])      : 0;
$fContractStatus = isset($_GET['contract_status']) ? trim($_GET['contract_status']) : '';
$fCategory       = isset($_GET['category'])        ? trim($_GET['category'])        : '';

// ─── سياسة التحميل الافتراضي: آخر 500 سجل قبل أي فلترة ────────────────
$INITIAL_LOAD_LIMIT = 1000;
$hasAnyFilter = (
    $fDateFrom !== '' ||
    $fDateTo !== '' ||
    $fProjectId > 0 ||
    $fSupplierId > 0 ||
    $fDriverId > 0 ||
    $fStatus >= 0 ||
    $fSearch !== '' ||
    $fShift !== '' ||
    $fEquipId > 0 ||
    $fContractStatus !== '' ||
    $fCategory !== ''
);
$applyInitialLimit = ($_SERVER['REQUEST_METHOD'] === 'GET' && !$hasAnyFilter);

if (!function_exists('rptApplyInitialLimit')) {
    function rptApplyInitialLimit($sql, $applyLimit, $limit = 500) {
        if (!$applyLimit) return $sql;
        if (preg_match('/\\blimit\\s+\\d+/i', $sql)) return $sql;
        return rtrim($sql, " \t\n\r;") . " LIMIT " . intval($limit);
    }
}

// ─── بيانات القوائم المنسدلة (company-scoped) ────────────────────────────
$projectsList  = getProjectsForDropdown($conn, $companyId, $isSuperAdmin);
$suppliersList = getSuppliersForDropdown($conn, $companyId, $isSuperAdmin);
$driversList   = getDriversForDropdown($conn, $companyId, $isSuperAdmin);

// ─── دالة مساعدة للـ output ──────────────────────────────────────────────
if (!function_exists('rr')) {
    function rr($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// ─── نطاق الشركة لكل جدول ────────────────────────────────────────────────
$sc = [
    't'  => rptCompanyScope($conn, 't',  'timesheet',          $companyId, $isSuperAdmin),
    'o'  => rptCompanyScope($conn, 'o',  'operations',         $companyId, $isSuperAdmin),
    'p'  => rptCompanyScope($conn, 'p',  'project',            $companyId, $isSuperAdmin),
    's'  => rptCompanyScope($conn, 's',  'suppliers',          $companyId, $isSuperAdmin),
    'e'  => rptCompanyScope($conn, 'e',  'equipments',         $companyId, $isSuperAdmin),
    'd'  => rptCompanyScope($conn, 'd',  'drivers',            $companyId, $isSuperAdmin),
    'c'  => rptCompanyScope($conn, 'c',  'contracts',          $companyId, $isSuperAdmin),
    'sc' => rptCompanyScope($conn, 'sc', 'supplierscontracts', $companyId, $isSuperAdmin),
    'dc' => rptCompanyScope($conn, 'dc', 'drivercontracts',    $companyId, $isSuperAdmin),
    'm'  => rptCompanyScope($conn, 'm',  'mines',              $companyId, $isSuperAdmin),
];

// ─── متغيرات البيانات الرئيسية ───────────────────────────────────────────
$headers   = [];
$rows      = [];
$kpi       = [];
$chartData = null;
$chart2    = null;

// ═══════════════════════════════════════════════════════════════════════════
// بناء الاستعلامات حسب كود التقرير
// ═══════════════════════════════════════════════════════════════════════════
switch ($REPORT_CODE) {

/* ─────────────────────────────────────────────────────────────────────────
   TIMESHEET REPORTS  (ساعات العمل)
───────────────────────────────────────────────────────────────────────── */
case 'timesheet_summary':
case 'timesheet_detailed':
case 'timesheet_by_project':
case 'timesheet_by_equipment':
case 'timesheet_by_driver':
case 'supplier_timesheet':
case 'fleet_timesheet':
case 'drivers_timesheet': {
    $where = ["({$sc['t']})", "({$sc['o']})"];
    if ($fDateFrom) $where[] = "t.date >= '" . mysqli_real_escape_string($conn, $fDateFrom) . "'";
    if ($fDateTo)   $where[] = "t.date <= '" . mysqli_real_escape_string($conn, $fDateTo) . "'";
    if ($fProjectId  > 0) $where[] = "o.project_id = $fProjectId";
    if ($fSupplierId > 0) $where[] = "s.id = $fSupplierId";
    if ($fDriverId   > 0) $where[] = "d.id = $fDriverId";
    $ws = implode(' AND ', $where);

    $executedField = 't.executed_hours';
    $standbyField  = 't.standby_hours';
    $workField     = '(IFNULL(t.executed_hours,0) + IFNULL(t.standby_hours,0))';

    // KPI aggregate
    $kpiSql = "SELECT COUNT(t.id) AS cnt,
                      ROUND(IFNULL(SUM($executedField),0),2) AS executed_hours,
                      ROUND(IFNULL(SUM($standbyField),0),2) AS standby_hours,
                      ROUND(IFNULL(SUM($workField),0),2) AS twh,
                      ROUND(IFNULL(SUM(t.total_fault_hours),0),2) AS tfh,
                      COUNT(DISTINCT o.project_id) AS proj_cnt,
                      COUNT(DISTINCT t.driver) AS driver_cnt
               FROM timesheet t
               LEFT JOIN operations o ON o.id = t.operator
               LEFT JOIN suppliers  s ON s.id = o.supplier_id
               LEFT JOIN drivers    d ON d.id = t.driver
               WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];
    $executedHours = floatval($kpiRow['executed_hours'] ?? 0);
    $standbyHours = floatval($kpiRow['standby_hours'] ?? 0);
    $twh = floatval($kpiRow['twh'] ?? 0);
    $tfh = floatval($kpiRow['tfh'] ?? 0);
    $eff = ($twh + $tfh) > 0 ? round($twh / ($twh + $tfh) * 100, 1) : 0;

    $kpi = [
        ['icon'=>'fa-list-ul',       'value'=> number_format($kpiRow['cnt'] ?? 0),     'label'=>'إجمالي السجلات',  'color'=>'blue'],
        ['icon'=>'fa-play-circle',   'value'=> number_format($executedHours,1) . ' س', 'label'=>'الساعات المنفذة', 'color'=>'green'],
        ['icon'=>'fa-pause-circle',  'value'=> number_format($standbyHours,1) . ' س',  'label'=>'ساعات استعداد العميل', 'color'=>'orange'],
        ['icon'=>'fa-clock',         'value'=> number_format($twh,1) . ' س',           'label'=>'ع.العمل',         'color'=>'teal'],
        ['icon'=>'fa-exclamation-triangle','value'=> number_format($tfh,1) . ' س',     'label'=>'ساعات الأعطال',   'color'=>'red'],
    ];
    $kpi[] = ['icon'=>'fa-percentage',    'value'=> $eff . '%',                              'label'=>'كفاءة التشغيل',  'color'=>'gold'];
    $kpi[] = ['icon'=>'fa-project-diagram','value'=> number_format($kpiRow['proj_cnt'] ?? 0),'label'=>'المشاريع',        'color'=>'teal'];

    if ($REPORT_CODE === 'timesheet_by_project') {
        $headers = ['المشروع','عدد السجلات','executed_hours','standby_hours','ع.العمل','ساعات الأعطال','الكفاءة%'];
        $sql = "SELECT IFNULL(p.name,'غير محدد') AS project_name,
                       COUNT(t.id) AS entries_count,
                       ROUND(IFNULL(SUM(t.executed_hours),0),2)    AS executed_hours,
                       ROUND(IFNULL(SUM(t.standby_hours),0),2)     AS standby_hours,
                       ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0),2) AS twh,
                       ROUND(IFNULL(SUM(t.total_fault_hours),0),2) AS tfh,
                       CASE WHEN (IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))>0
                            THEN ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0)/(IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))*100,1)
                            ELSE 0 END AS eff_pct
                FROM timesheet t
                LEFT JOIN operations o ON o.id=t.operator
                LEFT JOIN project    p ON p.id=o.project_id
                LEFT JOIN suppliers  s ON s.id=o.supplier_id
                LEFT JOIN drivers    d ON d.id=t.driver
                WHERE $ws GROUP BY p.id,p.name ORDER BY twh DESC";
    } elseif (in_array($REPORT_CODE,['timesheet_by_equipment','fleet_timesheet'])) {
           $headers = ['الكود','المعدة','المورد','عدد السجلات','executed_hours','standby_hours','ع.العمل','ساعات الأعطال','الكفاءة%'];
        $sql = "SELECT IFNULL(e.code,'-') AS code,
                       IFNULL(e.name,'غير محدد') AS equipment_name,
                       IFNULL(s.name,'—') AS supplier_name,
                       COUNT(t.id) AS entries_count,
                       ROUND(IFNULL(SUM(t.executed_hours),0),2)    AS executed_hours,
                       ROUND(IFNULL(SUM(t.standby_hours),0),2)     AS standby_hours,
                       ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0),2) AS twh,
                       ROUND(IFNULL(SUM(t.total_fault_hours),0),2) AS tfh,
                       CASE WHEN (IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))>0
                           THEN ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0)/(IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))*100,1)
                            ELSE 0 END AS eff_pct
                FROM timesheet t
                LEFT JOIN operations o ON o.id=t.operator
                LEFT JOIN equipments e ON e.id=o.equipment
                LEFT JOIN suppliers  s ON s.id=o.supplier_id
                LEFT JOIN drivers    d ON d.id=t.driver
                WHERE $ws GROUP BY e.id ORDER BY twh DESC";
    } elseif (in_array($REPORT_CODE,['timesheet_by_driver','drivers_timesheet'])) {
           $headers = ['المشغل','المورد','عدد الورديات','executed_hours','standby_hours','ع.العمل','ساعات الأعطال','الكفاءة%'];
        $sql = "SELECT IFNULL(d.name,'غير محدد') AS driver_name,
                       IFNULL(s.name,'—') AS supplier_name,
                       COUNT(t.id) AS entries_count,
                       ROUND(IFNULL(SUM(t.executed_hours),0),2)    AS executed_hours,
                       ROUND(IFNULL(SUM(t.standby_hours),0),2)     AS standby_hours,
                       ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0),2) AS twh,
                       ROUND(IFNULL(SUM(t.total_fault_hours),0),2) AS tfh,
                       CASE WHEN (IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))>0
                           THEN ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0)/(IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))*100,1)
                            ELSE 0 END AS eff_pct
                FROM timesheet t
                LEFT JOIN operations o ON o.id=t.operator
                LEFT JOIN suppliers  s ON s.id=o.supplier_id
                LEFT JOIN drivers    d ON d.id=t.driver
                WHERE $ws GROUP BY d.id ORDER BY twh DESC";
    } elseif ($REPORT_CODE === 'supplier_timesheet') {
           $headers = ['المورد','عدد السجلات','executed_hours','standby_hours','ع.العمل','ساعات الأعطال','الكفاءة%'];
        $sql = "SELECT IFNULL(s.name,'غير محدد') AS supplier_name,
                       COUNT(t.id) AS entries_count,
                       ROUND(IFNULL(SUM(t.executed_hours),0),2)    AS executed_hours,
                       ROUND(IFNULL(SUM(t.standby_hours),0),2)     AS standby_hours,
                       ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0),2) AS twh,
                       ROUND(IFNULL(SUM(t.total_fault_hours),0),2) AS tfh,
                       CASE WHEN (IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))>0
                           THEN ROUND(IFNULL(SUM(t.executed_hours + t.standby_hours),0)/(IFNULL(SUM(t.executed_hours + t.standby_hours),0)+IFNULL(SUM(t.total_fault_hours),0))*100,1)
                            ELSE 0 END AS eff_pct
                FROM timesheet t
                LEFT JOIN operations o ON o.id=t.operator
                LEFT JOIN suppliers  s ON s.id=o.supplier_id
                LEFT JOIN drivers    d ON d.id=t.driver
                WHERE $ws GROUP BY s.id ORDER BY twh DESC";
    } else {
        // timesheet_summary / timesheet_detailed
        if ($REPORT_CODE === 'timesheet_detailed') {
            // ── تقرير التفاصيل الكامل مع كل الحقول ──
            if ($fShift !== '')  $where[] = "t.shift = '" . mysqli_real_escape_string($conn, $fShift) . "'";
            if ($fEquipId > 0)   $where[] = "o.equipment = $fEquipId";
            $ws = implode(' AND ', $where);

              $headers = ['#','التاريخ','الوردية','البداية','النهاية','ع.المنفذة','ع.الاستعداد','ع.العمل','ع.أعطال','ع.تنفيذ مشغل','ع.استعداد مشغل','ع.وردية','كفاءة%','المشروع','المعدة','كود المعدة','المورد','المشغل','نوع الخلل','ملاحظات'];
            $sql = "SELECT t.id,
                           t.date,
                           CASE WHEN t.shift='D' THEN 'D' WHEN t.shift='N' THEN 'N' ELSE IFNULL(t.shift,'-') END AS shift_txt,
                           CONCAT(LPAD(IFNULL(t.start_hours,0),2,'0'),':',LPAD(IFNULL(t.start_minutes,0),2,'0')) AS start_t,
                           CONCAT(LPAD(IFNULL(t.end_hours,0),2,'0'),':',LPAD(IFNULL(t.end_minutes,0),2,'0'))   AS end_t,
                          ROUND(IFNULL(t.executed_hours,0),2)     AS executed_hours,
                          ROUND(IFNULL(t.standby_hours,0),2)      AS standby_hours,
                          ROUND(IFNULL(t.executed_hours + t.standby_hours,0),2) AS twh,
                           ROUND(IFNULL(t.total_fault_hours,0),2)  AS tfh,
                           ROUND(IFNULL(t.operator_hours,0),2)     AS op_h,
                           ROUND(IFNULL(t.operator_standby_hours,0),2) AS op_sb,
                           ROUND(IFNULL(t.shift_hours,0),2)        AS sh_h,
                          CASE WHEN (IFNULL(t.executed_hours + t.standby_hours,0)+IFNULL(t.total_fault_hours,0))>0
                              THEN ROUND(IFNULL(t.executed_hours + t.standby_hours,0)/(IFNULL(t.executed_hours + t.standby_hours,0)+IFNULL(t.total_fault_hours,0))*100,1)
                                ELSE 0 END AS eff_pct,
                           IFNULL(p.name,'—')  AS project_name,
                           IFNULL(e.name,'—')  AS equipment_name,
                           IFNULL(e.code,'-')  AS equipment_code,
                           IFNULL(s.name,'—')  AS supplier_name,
                           IFNULL(d.name,'—')  AS driver_name,
                           IFNULL(t.fault_type,'-') AS fault_type,
                           COALESCE(NULLIF(t.general_notes,''),NULLIF(t.work_notes,''),'-') AS notes
                    FROM timesheet t
                    LEFT JOIN operations o ON o.id=t.operator
                    LEFT JOIN project    p ON p.id=o.project_id
                    LEFT JOIN equipments e ON e.id=o.equipment
                    LEFT JOIN suppliers  s ON s.id=o.supplier_id
                    LEFT JOIN drivers    d ON d.id=t.driver
                    WHERE $ws ORDER BY t.date DESC, t.id DESC";
        } else {
            // timesheet_summary
              $headers = ['التاريخ','البداية','النهاية','executed_hours','standby_hours','ع.العمل','ساعات الأعطال','الكفاءة%','المشروع','المعدة','المورد','المشغل'];
            $sql = "SELECT t.date,
                           CONCAT(LPAD(IFNULL(t.start_hours,0),2,'0'),':',LPAD(IFNULL(t.start_minutes,0),2,'0')) AS start_t,
                           CONCAT(LPAD(IFNULL(t.end_hours,0),2,'0'),':',LPAD(IFNULL(t.end_minutes,0),2,'0'))   AS end_t,
                          ROUND(IFNULL(t.executed_hours,0),2)    AS executed_hours,
                          ROUND(IFNULL(t.standby_hours,0),2)     AS standby_hours,
                          ROUND(IFNULL(t.executed_hours + t.standby_hours,0),2) AS twh,
                           ROUND(IFNULL(t.total_fault_hours,0),2) AS tfh,
                          CASE WHEN (IFNULL(t.executed_hours + t.standby_hours,0)+IFNULL(t.total_fault_hours,0))>0
                              THEN ROUND(IFNULL(t.executed_hours + t.standby_hours,0)/(IFNULL(t.executed_hours + t.standby_hours,0)+IFNULL(t.total_fault_hours,0))*100,1)
                                ELSE 0 END AS eff_pct,
                           IFNULL(p.name,'—') AS project_name,
                           IFNULL(e.name,'—') AS equipment_name,
                           IFNULL(s.name,'—') AS supplier_name,
                           IFNULL(d.name,'—') AS driver_name
                    FROM timesheet t
                    LEFT JOIN operations o ON o.id=t.operator
                    LEFT JOIN project    p ON p.id=o.project_id
                    LEFT JOIN equipments e ON e.id=o.equipment
                    LEFT JOIN suppliers  s ON s.id=o.supplier_id
                    LEFT JOIN drivers    d ON d.id=t.driver
                    WHERE $ws ORDER BY t.date DESC, t.id DESC";
        }
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ في الاستعلام: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    // بيانات الرسم البياني
    if (count($rows) > 0) {
        $sliceCount = min(count($rows), 10);
        $cl = []; $cv1 = []; $cv2 = [];
        if ($REPORT_CODE === 'timesheet_by_project') {
            for ($i = 0; $i < $sliceCount; $i++) {
                $cl[]  = mb_substr($rows[$i]['project_name'],0,18,'UTF-8');
                $cv1[] = floatval($rows[$i]['twh']);
                $cv2[] = floatval($rows[$i]['tfh']);
            }
            $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
                ['label'=>'ساعات العمل',  'data'=>$cv1,'color'=>'rgba(37,99,235,0.82)'],
                ['label'=>'ساعات الأعطال','data'=>$cv2,'color'=>'rgba(220,38,38,0.65)'],
            ],'title'=>'ساعات العمل حسب المشروع'];
        } elseif (in_array($REPORT_CODE,['timesheet_by_equipment','fleet_timesheet'])) {
            for ($i = 0; $i < $sliceCount; $i++) {
                $cl[]  = mb_substr($rows[$i]['equipment_name'],0,15,'UTF-8');
                $cv1[] = floatval($rows[$i]['twh']);
                $cv2[] = floatval($rows[$i]['tfh']);
            }
            $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
                ['label'=>'ساعات العمل',  'data'=>$cv1,'color'=>'rgba(13,148,136,0.82)'],
                ['label'=>'ساعات الأعطال','data'=>$cv2,'color'=>'rgba(220,38,38,0.65)'],
            ],'title'=>'ساعات التشغيل حسب المعدة'];
        } elseif (in_array($REPORT_CODE,['timesheet_by_driver','drivers_timesheet'])) {
            for ($i = 0; $i < $sliceCount; $i++) {
                $cl[]  = mb_substr($rows[$i]['driver_name'],0,14,'UTF-8');
                $cv1[] = floatval($rows[$i]['twh']);
            }
            $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
                ['label'=>'ساعات العمل','data'=>$cv1,'color'=>'rgba(124,58,237,0.82)'],
            ],'title'=>'ساعات العمل حسب المشغل'];
        } elseif ($REPORT_CODE === 'supplier_timesheet') {
            for ($i = 0; $i < $sliceCount; $i++) {
                $cl[]  = mb_substr($rows[$i]['supplier_name'],0,15,'UTF-8');
                $cv1[] = floatval($rows[$i]['twh']);
                $cv2[] = floatval($rows[$i]['tfh']);
            }
            $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
                ['label'=>'ساعات العمل',  'data'=>$cv1,'color'=>'rgba(234,111,0,0.82)'],
                ['label'=>'ساعات الأعطال','data'=>$cv2,'color'=>'rgba(220,38,38,0.65)'],
            ],'title'=>'ساعات الموردين'];
        } else {
            // تجميع يومي للتقرير التفصيلي
            $dayMap = [];
            foreach ($rows as $r) { $dayMap[$r['date']] = ($dayMap[$r['date']] ?? 0) + floatval($r['twh']); }
            ksort($dayMap);
            $dSlice = array_slice($dayMap, -14, 14, true);
            foreach ($dSlice as $dt => $h) { $cl[] = $dt; $cv1[] = $h; }
            if (count($cl) > 1) $chartData = ['type'=>'line','labels'=>$cl,'datasets'=>[
                ['label'=>'ساعات العمل اليومية','data'=>$cv1,'color'=>'rgba(37,99,235,0.88)'],
            ],'title'=>'تطور ساعات العمل اليومية'];
        }
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   PROJECTS (المشاريع)
───────────────────────────────────────────────────────────────────────── */
case 'project_summary':
case 'project_detailed': {
    $where = ["({$sc['p']})", "p.is_deleted = 0"];
    if ($fStatus >= 0) $where[] = "p.status = $fStatus";
    if ($fSearch !== '') {
        $safe = mysqli_real_escape_string($conn, $fSearch);
        $where[] = "(p.name LIKE '%$safe%' OR p.project_code LIKE '%$safe%' OR p.client LIKE '%$safe%' OR p.state LIKE '%$safe%')";
    }
    if ($fCategory !== '') {
        $where[] = "p.category = '" . mysqli_real_escape_string($conn, $fCategory) . "'";
    }
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(p.id) AS total_p,
                      SUM(CASE WHEN p.status=1 THEN 1 ELSE 0 END) AS active_p,
                      COUNT(DISTINCT p.state) AS states_cnt,
                      COUNT(DISTINCT p.category) AS cat_cnt
               FROM project p WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $totalContracts = 0;
    $contractsSql = mysqli_query($conn, "SELECT COUNT(*) AS c FROM contracts c WHERE c.status=1 AND c.project_id IN (SELECT id FROM project p WHERE $ws)");
    if ($contractsSql) { $cr2 = mysqli_fetch_assoc($contractsSql); $totalContracts = $cr2['c'] ?? 0; }

    $kpi = [
        ['icon'=>'fa-project-diagram','value'=> number_format($kpiRow['total_p']    ?? 0),'label'=>'إجمالي المشاريع','color'=>'blue'],
        ['icon'=>'fa-check-circle',   'value'=> number_format($kpiRow['active_p']   ?? 0),'label'=>'مشاريع نشطة',   'color'=>'green'],
        ['icon'=>'fa-file-contract',  'value'=> number_format($totalContracts),           'label'=>'العقود النشطة',  'color'=>'gold'],
        ['icon'=>'fa-map-marker-alt', 'value'=> number_format($kpiRow['states_cnt'] ?? 0),'label'=>'الولايات',       'color'=>'purple'],
    ];

    if ($REPORT_CODE === 'project_detailed') {
        $headers = ['الكود','المشروع','العميل','الولاية','المنطقة','الموقع','الفئة','القطاع الفرعي','أقرب سوق','الإحداثيات','العقود النشطة','ساعات العمل','الوردية المنفذة','الحالة','تاريخ الإنشاء'];
        $sql = "SELECT p.project_code,
                       p.name,
                       IFNULL(p.client,'—') AS client,
                       IFNULL(p.state,'—') AS state,
                       IFNULL(p.region,'—') AS region,
                       IFNULL(p.location,'—') AS location,
                       IFNULL(p.category,'—') AS category,
                       IFNULL(p.sub_sector,'—') AS sub_sector,
                       IFNULL(p.nearest_market,'—') AS nearest_market,
                       CASE WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL
                            THEN CONCAT(p.latitude,' / ',p.longitude) ELSE '—' END AS coords,
                       (SELECT COUNT(*) FROM contracts ct WHERE ct.project_id=p.id AND ct.status=1 AND ct.is_deleted=0) AS contracts_cnt,
                       IFNULL((SELECT ROUND(SUM(t2.total_work_hours),1) FROM timesheet t2 JOIN operations o2 ON o2.id=t2.operator WHERE o2.project_id=p.id),0) AS total_wh,
                       IFNULL((SELECT COUNT(*) FROM timesheet t3 JOIN operations o3 ON o3.id=t3.operator WHERE o3.project_id=p.id),0) AS shifts_cnt,
                       CASE WHEN p.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt,
                       DATE_FORMAT(p.create_at,'%Y-%m-%d') AS created_date
                FROM project p WHERE $ws ORDER BY p.status DESC, p.name ASC";
    } else {
        $headers = ['الكود','المشروع','العميل','الموقع','ساعات العمل','الحالة'];
        $sql = "SELECT p.project_code, p.name,
                       IFNULL(p.client,'—') AS client,
                       IFNULL(p.location,'—') AS location,
                       IFNULL((SELECT ROUND(SUM(t2.total_work_hours),1) FROM timesheet t2 JOIN operations o2 ON o2.id=t2.operator WHERE o2.project_id=p.id),0) AS total_wh,
                       CASE WHEN p.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt
                FROM project p WHERE $ws ORDER BY p.status DESC, p.name ASC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 1) {
        $sorted = $rows;
        usort($sorted, function($a,$b){ return floatval($b['total_wh']) - floatval($a['total_wh']); });
        $slice = array_slice($sorted, 0, 8);
        $cl = []; $cv = [];
        foreach ($slice as $r) { $cl[] = mb_substr($r['name'],0,16,'UTF-8'); $cv[] = floatval($r['total_wh']); }
        $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
            ['label'=>'ساعات العمل','data'=>$cv,'color'=>'rgba(37,99,235,0.82)'],
        ],'title'=>'أعلى المشاريع في ساعات العمل'];

        $actCnt = intval($kpiRow['active_p'] ?? 0);
        $inact  = intval($kpiRow['total_p'] ?? 0) - $actCnt;
        if ($actCnt + $inact > 0) $chart2 = ['type'=>'doughnut','labels'=>['نشط','غير نشط'],'datasets'=>[
            ['label'=>'توزيع المشاريع','data'=>[$actCnt,$inact],'color'=>['rgba(22,163,74,0.85)','rgba(220,38,38,0.72)']],
        ],'title'=>'توزيع حالات المشاريع'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   CONTRACTS (عقود المشاريع)
───────────────────────────────────────────────────────────────────────── */
case 'contracts_summary':
case 'contracts_detailed': {
    $where = ["({$sc['c']})", "c.is_deleted = 0"];
    if ($fStatus >= 0)       $where[] = "c.status = $fStatus";
    if ($fProjectId > 0)     $where[] = "c.project_id = $fProjectId";
    if ($fContractStatus !== '') {
        $safeCst = mysqli_real_escape_string($conn, $fContractStatus);
        $where[] = "c.contract_status = '$safeCst'";
    }
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(c.id) AS total_c,
                      SUM(CASE WHEN c.status=1 THEN 1 ELSE 0 END) AS active_c,
                      SUM(CASE WHEN c.contract_status='paused' THEN 1 ELSE 0 END) AS paused_c,
                      SUM(CASE WHEN c.contract_status='terminated' THEN 1 ELSE 0 END) AS term_c,
                      ROUND(IFNULL(SUM(c.forecasted_contracted_hours),0),0) AS total_hrs,
                      ROUND(AVG(c.contract_duration_months),1) AS avg_dur
               FROM contracts c LEFT JOIN project p ON p.id=c.project_id WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-file-contract','value'=> number_format($kpiRow['total_c']  ?? 0),        'label'=>'إجمالي العقود',  'color'=>'blue'],
        ['icon'=>'fa-check-circle', 'value'=> number_format($kpiRow['active_c'] ?? 0),        'label'=>'عقود نشطة',     'color'=>'green'],
        ['icon'=>'fa-pause-circle', 'value'=> number_format($kpiRow['paused_c'] ?? 0),        'label'=>'موقوفة',         'color'=>'gold'],
        ['icon'=>'fa-times-circle', 'value'=> number_format($kpiRow['term_c']  ?? 0),         'label'=>'منتهية',         'color'=>'red'],
        ['icon'=>'fa-clock',        'value'=> number_format($kpiRow['total_hrs'] ?? 0) . ' س','label'=>'ساعات مستهدفة', 'color'=>'teal'],
        ['icon'=>'fa-calendar-alt', 'value'=> ($kpiRow['avg_dur'] ?? 0) . ' شهر',            'label'=>'متوسط المدة',   'color'=>'purple'],
    ];

    if ($REPORT_CODE === 'contracts_detailed') {
        $headers = ['رقم العقد','المشروع','تاريخ التوقيع','تاريخ البدء الفعلي','تاريخ الانتهاء','المدة','ايام الفترة','ساعات/يوم','الورديات/معدة','مشغلين/يوم','الساعات الشهرية','الإجمالي المستهدف','الطرف الأول','الطرف الثاني','العملة','المبلغ المدفوع','وقت الدفع','الضمانات','حالة العقد','ملاحظة التوقف'];
        $sql = "SELECT c.id,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(c.contract_signing_date,'—') AS signing_date,
                       IFNULL(c.actual_start,'—') AS actual_start,
                       IFNULL(c.actual_end,'—') AS actual_end,
                       CONCAT(IFNULL(c.contract_duration_months,0),' شهر + ',IFNULL(c.contract_duration_days,0),' يوم') AS duration_txt,
                       IFNULL(c.grace_period_days,0) AS grace_days,
                       IFNULL(c.daily_work_hours,'—') AS daily_work_hours,
                       IFNULL(c.equip_shifts_contract,0) AS shifts,
                       IFNULL(c.daily_operators,0) AS daily_operators,
                       IFNULL(c.hours_monthly_target,0) AS monthly_target,
                       IFNULL(c.forecasted_contracted_hours,0) AS total_target,
                       IFNULL(c.first_party,'—') AS first_party,
                       IFNULL(c.second_party,'—') AS second_party,
                       IFNULL(c.price_currency_contract,'—') AS currency,
                       IFNULL(c.paid_contract,'—') AS paid_amount,
                       IFNULL(c.payment_time,'—') AS payment_time,
                       IFNULL(c.guarantees,'—') AS guarantees,
                       CASE WHEN c.contract_status IS NULL OR c.contract_status='' THEN 'نشط'
                            WHEN c.contract_status='paused' THEN 'موقوف'
                            WHEN c.contract_status='terminated' THEN 'منتهي'
                            WHEN c.contract_status='merged' THEN 'مدموج'
                            ELSE c.contract_status END AS status_txt,
                       IFNULL(c.pause_reason,'—') AS pause_reason
                FROM contracts c
                LEFT JOIN project p ON p.id=c.project_id
                WHERE $ws ORDER BY c.contract_signing_date DESC";
    } else {
        $headers = ['رقم العقد','المشروع','تاريخ التوقيع','المدة (شهر)','الساعات الشهرية','الإجمالي المستهدف','الحالة'];
        $sql = "SELECT c.id,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(c.contract_signing_date,'—') AS signing_date,
                       IFNULL(c.contract_duration_months,0) AS duration_months,
                       IFNULL(c.hours_monthly_target,0) AS monthly_target,
                       IFNULL(c.forecasted_contracted_hours,0) AS total_target,
                       CASE WHEN c.status=1 THEN 'نشط' ELSE 'غير ساري' END AS status_txt
                FROM contracts c
                LEFT JOIN project p ON p.id=c.project_id
                WHERE $ws ORDER BY c.contract_signing_date DESC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 0) {
        $actC = intval($kpiRow['active_c'] ?? 0);
        $inac = intval($kpiRow['total_c']  ?? 0) - $actC;
        if ($actC + $inac > 0) $chartData = ['type'=>'doughnut','labels'=>['نشط','غير ساري'],'datasets'=>[
            ['label'=>'العقود','data'=>[$actC,$inac],'color'=>['rgba(22,163,74,0.85)','rgba(220,38,38,0.75)']],
        ],'title'=>'توزيع حالات العقود'];

        $projMap = [];
        foreach ($rows as $r) { $pn=$r['project_name']; $projMap[$pn] = ($projMap[$pn]??0) + floatval($r['total_target']); }
        arsort($projMap);
        $topP = array_slice($projMap,0,8,true);
        $cl2=[]; $cv2=[];
        foreach ($topP as $k=>$v) { $cl2[]=mb_substr($k,0,16,'UTF-8'); $cv2[]=$v; }
        if ($cl2) $chart2 = ['type'=>'bar','labels'=>$cl2,'datasets'=>[
            ['label'=>'الساعات المستهدفة','data'=>$cv2,'color'=>'rgba(37,99,235,0.82)'],
        ],'title'=>'الساعات المستهدفة حسب المشروع'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   SUPPLIER CONTRACTS (عقود الموردين)
───────────────────────────────────────────────────────────────────────── */
case 'supplier_contracts_summary':
case 'supplier_contracts_detailed':
case 'supplier_equipment_performance': {
    $where = ["({$sc['sc']})"];
    if ($fStatus >= 0)    $where[] = "sc.status = $fStatus";
    if ($fSupplierId > 0) $where[] = "sc.supplier_id = $fSupplierId";
    if ($fProjectId  > 0) $where[] = "sc.project_id = $fProjectId";
    if ($fContractStatus !== '') {
        $safeCst = mysqli_real_escape_string($conn, $fContractStatus);
        $where[] = "sc.termination_type IS " . ($safeCst === 'terminated' ? "NOT NULL" : "NULL");
    }
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(sc.id) AS total,
                      COUNT(DISTINCT sc.supplier_id) AS total_supp,
                      ROUND(IFNULL(SUM(sc.forecasted_contracted_hours),0),0) AS total_hrs,
                      ROUND(AVG(sc.contract_duration_months),1) AS avg_dur,
                      IFNULL(SUM(sc.equip_count),0) AS total_equip,
                      IFNULL(SUM(sc.mach_count),0) AS total_mach
               FROM supplierscontracts sc WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-handshake',  'value'=> number_format($kpiRow['total']      ?? 0),        'label'=>'إجمالي العقود',  'color'=>'blue'],
        ['icon'=>'fa-truck',      'value'=> number_format($kpiRow['total_supp'] ?? 0),        'label'=>'عدد الموردين',   'color'=>'gold'],
        ['icon'=>'fa-clock',      'value'=> number_format($kpiRow['total_hrs']  ?? 0) . ' س','label'=>'ساعات مستهدفة', 'color'=>'green'],
        ['icon'=>'fa-calendar',   'value'=> ($kpiRow['avg_dur'] ?? 0) . ' شهر',             'label'=>'متوسط المدة',   'color'=>'teal'],
        ['icon'=>'fa-tractor',    'value'=> number_format($kpiRow['total_equip'] ?? 0),       'label'=>'معدات العقود',   'color'=>'purple'],
    ];

    if ($REPORT_CODE === 'supplier_contracts_detailed') {
        $headers = ['المورد','الكود','المشروع','تاريخ التوقيع','تاريخ البدء','تاريخ الانتهاء','المدة','فترة السماح','نوع المعدة','عدد المعدات','نوع الآلة','عدد الآلات','ساعات/يوم','مشغلين/يوم','الساعات الشهرية','الإجمالي','الطرف الأول','الطرف الثاني','العملة','المبلغ المدفوع','وقت الدفع','الضمانات','الحالة'];
        $sql = "SELECT IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(s.supplier_code,'-') AS supplier_code,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(sc.contract_signing_date,'—') AS signing_date,
                       IFNULL(sc.actual_start,'—') AS actual_start,
                       IFNULL(sc.actual_end,'—') AS actual_end,
                       CONCAT(IFNULL(sc.contract_duration_months,0),' شهر + ',IFNULL(sc.contract_duration_days,0),' يوم') AS duration_txt,
                       IFNULL(sc.grace_period_days,0) AS grace_days,
                       IFNULL(sc.equip_type,'—') AS equip_type,
                       IFNULL(sc.equip_count,0) AS equip_count,
                       IFNULL(sc.mach_type,'—') AS mach_type,
                       IFNULL(sc.mach_count,0) AS mach_count,
                       IFNULL(sc.daily_work_hours,'—') AS daily_work_hours,
                       IFNULL(sc.daily_operators,0) AS daily_operators,
                       IFNULL(sc.hours_monthly_target,0) AS monthly_target,
                       IFNULL(sc.forecasted_contracted_hours,0) AS total_target,
                       IFNULL(sc.first_party,'—') AS first_party,
                       IFNULL(sc.second_party,'—') AS second_party,
                       IFNULL(sc.price_currency_contract,'—') AS currency,
                       IFNULL(sc.paid_contract,'—') AS paid_amount,
                       IFNULL(sc.payment_time,'—') AS payment_time,
                       IFNULL(sc.guarantees,'—') AS guarantees,
                       CASE WHEN sc.termination_type IS NOT NULL THEN 'منتهي'
                            WHEN sc.pause_reason IS NOT NULL AND sc.resume_date IS NULL THEN 'موقوف'
                            WHEN sc.status=1 THEN 'نشط' ELSE 'غير ساري' END AS status_txt
                FROM supplierscontracts sc
                LEFT JOIN suppliers s  ON s.id=sc.supplier_id
                LEFT JOIN project   p  ON p.id=sc.project_id
                WHERE $ws ORDER BY sc.contract_signing_date DESC";
    } else {
        $headers = ['المورد','المشروع','تاريخ التوقيع','المدة (شهر)','المستهدف الشهري','الإجمالي المستهدف','عدد المعدات','الحالة'];
        $sql = "SELECT IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(sc.contract_signing_date,'—') AS signing_date,
                       IFNULL(sc.contract_duration_months,0) AS duration_months,
                       IFNULL(sc.hours_monthly_target,0) AS monthly_target,
                       IFNULL(sc.forecasted_contracted_hours,0) AS total_target,
                       (SELECT COUNT(*) FROM suppliercontractequipments sce WHERE sce.contract_id=sc.id) AS equip_cnt,
                       CASE WHEN sc.status=1 THEN 'نشط' ELSE 'غير ساري' END AS status_txt
                FROM supplierscontracts sc
                LEFT JOIN suppliers s ON s.id=sc.supplier_id
                LEFT JOIN project   p ON p.id=sc.project_id
                WHERE $ws ORDER BY sc.contract_signing_date DESC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 0) {
        $chartSql = "SELECT IFNULL(s.name,'—') AS sn,
                            ROUND(IFNULL(SUM(sc.forecasted_contracted_hours),0),0) AS hrs
                     FROM supplierscontracts sc LEFT JOIN suppliers s ON s.id=sc.supplier_id
                     WHERE $ws GROUP BY sc.supplier_id ORDER BY hrs DESC LIMIT 8";
        $chartRes = mysqli_query($conn, $chartSql);
        $cl=[]; $cv=[];
        if ($chartRes) while ($cr=mysqli_fetch_assoc($chartRes)) { $cl[]=mb_substr($cr['sn'],0,15,'UTF-8'); $cv[]=floatval($cr['hrs']); }
        if ($cl) $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
            ['label'=>'الساعات المستهدفة','data'=>$cv,'color'=>'rgba(234,111,0,0.82)'],
        ],'title'=>'ساعات عقود الموردين'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   FLEET — Equipment (المعدات)
───────────────────────────────────────────────────────────────────────── */
case 'fleet_equipment_summary':
case 'fleet_equipment_detailed': {
    $where = ["({$sc['e']})"];
    if ($fSupplierId > 0) $where[] = "e.suppliers = $fSupplierId";
    if ($fSearch !== '') {
        $safe = mysqli_real_escape_string($conn, $fSearch);
        $where[] = "(e.name LIKE '%$safe%' OR e.code LIKE '%$safe%' OR s.name LIKE '%$safe%' OR e.manufacturer LIKE '%$safe%' OR e.model LIKE '%$safe%')";
    }
    if ($fStatus >= 0) $where[] = "e.status = $fStatus";
    if ($fCategory !== '') {
        $where[] = "e.type = " . intval($fCategory);
    }
    if ($fEquipId > 0) $where[] = "e.id = $fEquipId";
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(e.id) AS total,
                      SUM(CASE WHEN e.status=1 THEN 1 ELSE 0 END) AS active,
                      COUNT(DISTINCT e.suppliers) AS total_suppliers,
                      ROUND(IFNULL(SUM(e.operating_hours),0),0) AS total_op_hrs
               FROM equipments e LEFT JOIN suppliers s ON s.id=e.suppliers WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-tractor',      'value'=> number_format($kpiRow['total']           ?? 0),'label'=>'إجمالي المعدات', 'color'=>'blue'],
        ['icon'=>'fa-check-circle', 'value'=> number_format($kpiRow['active']          ?? 0),'label'=>'معدات نشطة',    'color'=>'green'],
        ['icon'=>'fa-truck',        'value'=> number_format($kpiRow['total_suppliers'] ?? 0),'label'=>'الموردين',       'color'=>'gold'],
        ['icon'=>'fa-tachometer-alt','value'=> number_format($kpiRow['total_op_hrs']  ?? 0) . ' س','label'=>'إجمالي ساعات التشغيل','color'=>'teal'],
    ];

    if ($REPORT_CODE === 'fleet_equipment_detailed') {
        $headers = ['الكود','المعدة','النوع','المورد','الصانع','الموديل','سنة الصنع','الرقم التسلسلي','رقم الهيكل','حالة المعدة','حالة التوفر','ساعات التشغيل','رقم الترخيص','تاريخ انتهاء الرخصة','القيمة التقديرية','سعر الإيجار اليومي','عدد العمليات','ساعات العمل الفعلية','الحالة'];
        $sql = "SELECT e.code,
                       e.name AS equipment_name,
                       IFNULL(e.type,'—') AS equipment_type,
                       IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(e.manufacturer,'—') AS manufacturer,
                       IFNULL(e.model,'—') AS model,
                       IFNULL(e.manufacturing_year,'—') AS manufacturing_year,
                       IFNULL(e.serial_number,'—') AS serial_number,
                       IFNULL(e.chassis_number,'—') AS chassis_number,
                       IFNULL(e.equipment_condition,'—') AS equipment_condition,
                       IFNULL(e.availability_status,'—') AS availability_status,
                       IFNULL(e.operating_hours,0) AS operating_hours,
                       IFNULL(e.license_number,'—') AS license_number,
                       IFNULL(e.license_expiry_date,'—') AS license_expiry,
                       IFNULL(e.estimated_value,0) AS estimated_value,
                       IFNULL(e.daily_rental_price,0) AS daily_rental_price,
                       (SELECT COUNT(*) FROM operations o WHERE o.equipment=e.id) AS ops_cnt,
                       IFNULL((SELECT ROUND(SUM(t.total_work_hours),1) FROM timesheet t JOIN operations o2 ON o2.id=t.operator WHERE o2.equipment=e.id),0) AS total_wh,
                       CASE WHEN e.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt
                FROM equipments e LEFT JOIN suppliers s ON s.id=e.suppliers
                WHERE $ws ORDER BY total_wh DESC, e.id DESC";
    } else {
        $headers = ['الكود','المعدة','المورد','الحالة','عدد العمليات','ساعات التشغيل'];
        $sql = "SELECT e.code,
                       e.name AS equipment_name,
                       IFNULL(s.name,'—') AS supplier_name,
                       CASE WHEN e.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt,
                       (SELECT COUNT(*) FROM operations o WHERE o.equipment=e.id) AS ops_cnt,
                       IFNULL((SELECT ROUND(SUM(t.total_work_hours),1) FROM timesheet t JOIN operations o2 ON o2.id=t.operator WHERE o2.equipment=e.id),0) AS total_wh
                FROM equipments e LEFT JOIN suppliers s ON s.id=e.suppliers
                WHERE $ws ORDER BY total_wh DESC, e.id DESC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 1) {
        $sorted = $rows;
        usort($sorted, function($a,$b){ return floatval($b['total_wh'])-floatval($a['total_wh']); });
        $slice = array_slice($sorted, 0, 8);
        $cl=[]; $cv=[];
        foreach ($slice as $r) { $cl[]=mb_substr($r['equipment_name'],0,14,'UTF-8'); $cv[]=floatval($r['total_wh']); }
        $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
            ['label'=>'ساعات التشغيل','data'=>$cv,'color'=>'rgba(13,148,136,0.85)'],
        ],'title'=>'أعلى المعدات في ساعات التشغيل'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   OPERATIONS (التشغيل)
───────────────────────────────────────────────────────────────────────── */
case 'operations_summary':
case 'operations_detailed':
case 'fleet_operations': {
    $where = ["({$sc['o']})"];
    if ($fDateFrom)       $where[] = "o.start >= '" . mysqli_real_escape_string($conn, $fDateFrom) . "'";
    if ($fDateTo)         $where[] = "o.start <= '" . mysqli_real_escape_string($conn, $fDateTo)   . "'";
    if ($fStatus >= 0)    $where[] = "o.status = $fStatus";
    if ($fProjectId  > 0) $where[] = "o.project_id = $fProjectId";
    if ($fSupplierId > 0) $where[] = "o.supplier_id = $fSupplierId";
    if ($fEquipId    > 0) $where[] = "o.equipment = $fEquipId";
    if ($fCategory  !== '') $where[] = "e.type = " . intval($fCategory);
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(o.id) AS total,
                      SUM(CASE WHEN o.status=1 THEN 1 ELSE 0 END) AS active,
                      COUNT(DISTINCT o.equipment)  AS equip_cnt,
                      COUNT(DISTINCT o.project_id) AS proj_cnt,
                      ROUND(IFNULL(SUM(o.total_equipment_hours),0),1) AS total_eh,
                      ROUND(IFNULL(SUM(o.days),0),0) AS total_days
               FROM operations o
               LEFT JOIN equipments e ON e.id=o.equipment
               WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-cogs',           'value'=> number_format($kpiRow['total']      ?? 0),        'label'=>'إجمالي العمليات', 'color'=>'blue'],
        ['icon'=>'fa-play-circle',    'value'=> number_format($kpiRow['active']     ?? 0),        'label'=>'عمليات نشطة',    'color'=>'green'],
        ['icon'=>'fa-tractor',        'value'=> number_format($kpiRow['equip_cnt']  ?? 0),        'label'=>'معدات مُشغَّلة', 'color'=>'teal'],
        ['icon'=>'fa-project-diagram','value'=> number_format($kpiRow['proj_cnt']   ?? 0),        'label'=>'المشاريع',        'color'=>'gold'],
        ['icon'=>'fa-hourglass-half', 'value'=> number_format($kpiRow['total_eh']   ?? 0) . ' س','label'=>'ساعات المعدات',  'color'=>'purple'],
        ['icon'=>'fa-calendar-day',   'value'=> number_format($kpiRow['total_days'] ?? 0) . ' ي','label'=>'إجمالي الأيام',  'color'=>'red'],
    ];

    if ($REPORT_CODE === 'operations_detailed') {
        $headers = ['رقم العملية','المشروع','المورد','المعدة','كود المعدة','نوع المعدة','تاريخ البداية','تاريخ النهاية','الأيام','ساعات الوردية','إجمالي ساعات المعدة','إجمالي ساعات المشغل','إجمالي ورديات','سبب الانتهاء','الحالة'];
        $sql = "SELECT o.id,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(e.name,'—') AS equipment_name,
                       IFNULL(e.code,'-') AS equipment_code,
                       IFNULL(o.equipment_type,IFNULL(e.type,'—')) AS equipment_type,
                       IFNULL(o.start,'—') AS start_date,
                       IFNULL(o.end,'—') AS end_date,
                       IFNULL(o.days,0) AS days,
                       IFNULL(o.shift_hours,0) AS shift_hours,
                       IFNULL(o.total_equipment_hours,0) AS total_equip_hours,
                       IFNULL((SELECT ROUND(SUM(t2.operator_hours),1) FROM timesheet t2 WHERE t2.operator=o.id),0) AS total_op_hours,
                       IFNULL((SELECT COUNT(*) FROM timesheet t3 WHERE t3.operator=o.id),0) AS shifts_cnt,
                       IFNULL(o.reason,'—') AS end_reason,
                       CASE WHEN o.status=1 THEN 'نشط' ELSE 'منتهي' END AS status_txt
                FROM operations o
                LEFT JOIN project    p  ON p.id=o.project_id
                LEFT JOIN suppliers  s  ON s.id=o.supplier_id
                LEFT JOIN equipments e  ON e.id=o.equipment
                WHERE $ws ORDER BY o.id DESC";
    } else {
        $headers = ['رقم العملية','المشروع','المورد','المعدة','تاريخ البداية','تاريخ النهاية','ساعات الوردية','إجمالي الساعات','الحالة'];
        $sql = "SELECT o.id,
                       IFNULL(p.name,'—') AS project_name,
                       IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(e.name,'—') AS equipment_name,
                       IFNULL(o.start,'—') AS start_date,
                       IFNULL(o.end,'—') AS end_date,
                       IFNULL(o.shift_hours,0) AS shift_hours,
                       IFNULL(o.total_equipment_hours,0) AS total_equip_hours,
                       CASE WHEN o.status=1 THEN 'نشط' ELSE 'منتهي' END AS status_txt
                FROM operations o
                LEFT JOIN project    p ON p.id=o.project_id
                LEFT JOIN suppliers  s ON s.id=o.supplier_id
                LEFT JOIN equipments e ON e.id=o.equipment
                WHERE $ws ORDER BY o.id DESC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 0) {
        $actO = intval($kpiRow['active'] ?? 0);
        $endO = intval($kpiRow['total']  ?? 0) - $actO;
        if ($actO + $endO > 0) $chartData = ['type'=>'doughnut','labels'=>['نشط','منتهي'],'datasets'=>[
            ['label'=>'العمليات','data'=>[$actO,$endO],'color'=>['rgba(22,163,74,0.85)','rgba(100,116,139,0.72)']],
        ],'title'=>'توزيع حالات العمليات'];

        $chartSql2 = "SELECT IFNULL(p.name,'—') AS pn,
                             ROUND(IFNULL(SUM(o.total_equipment_hours),0),1) AS eh
                      FROM operations o LEFT JOIN project p ON p.id=o.project_id
                      WHERE $ws GROUP BY o.project_id ORDER BY eh DESC LIMIT 8";
        $cr2 = mysqli_query($conn, $chartSql2);
        $cl2=[]; $cv2=[];
        if ($cr2) while ($rr2=mysqli_fetch_assoc($cr2)) { $cl2[]=mb_substr($rr2['pn'],0,14,'UTF-8'); $cv2[]=floatval($rr2['eh']); }
        if ($cl2) $chart2 = ['type'=>'bar','labels'=>$cl2,'datasets'=>[
            ['label'=>'ساعات المعدات','data'=>$cv2,'color'=>'rgba(37,99,235,0.82)'],
        ],'title'=>'ساعات المعدات حسب المشروع'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   DRIVERS (المشغلون)
───────────────────────────────────────────────────────────────────────── */
case 'drivers_summary':
case 'drivers_detailed': {
    $where = ["({$sc['d']})"];
    if ($fStatus >= 0)    $where[] = "d.status = $fStatus";
    if ($fSupplierId > 0) $where[] = "d.supplier_id = $fSupplierId";
    if ($fSearch !== '') {
        $safe = mysqli_real_escape_string($conn, $fSearch);
        $where[] = "(d.name LIKE '%$safe%' OR d.driver_code LIKE '%$safe%' OR d.phone LIKE '%$safe%')";
    }
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(d.id) AS total,
                      SUM(CASE WHEN d.status=1 THEN 1 ELSE 0 END) AS active,
                      COUNT(DISTINCT d.supplier_id) AS total_suppliers
               FROM drivers d WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-id-badge',     'value'=> number_format($kpiRow['total']           ?? 0),'label'=>'إجمالي المشغلين','color'=>'blue'],
        ['icon'=>'fa-user-check',   'value'=> number_format($kpiRow['active']          ?? 0),'label'=>'مشغلون نشطون',  'color'=>'green'],
        ['icon'=>'fa-truck',        'value'=> number_format($kpiRow['total_suppliers'] ?? 0),'label'=>'الموردين',       'color'=>'gold'],
    ];

    if ($REPORT_CODE === 'drivers_detailed') {
        $headers = ['الكود','الاسم','المورد','الهاتف','نوع الهوية','رقم الهوية','مستوى المهارة','سنوات الخبرة','المعدات المتخصصة','جهة العمل','نوع الراتب','الراتب الشهري','تقييم الأداء','عدد الورديات','ساعات التنفيذ','ساعات الاستعداد','الحالة'];
        $sql = "SELECT d.driver_code,
                       d.name,
                       IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(d.phone,'—') AS phone,
                       IFNULL(d.identity_type,'—') AS identity_type,
                       IFNULL(d.identity_number,'—') AS identity_number,
                       IFNULL(d.skill_level,'—') AS skill_level,
                       IFNULL(d.years_in_field,0) AS years_in_field,
                       IFNULL(d.specialized_equipment,'—') AS specialized_equipment,
                       IFNULL(d.employment_affiliation,'—') AS employment_affiliation,
                       IFNULL(d.salary_type,'—') AS salary_type,
                       IFNULL(d.monthly_salary,0) AS monthly_salary,
                       IFNULL(d.performance_rating,'—') AS performance_rating,
                       IFNULL((SELECT COUNT(*) FROM timesheet t WHERE t.driver=d.id),0) AS shifts_cnt,
                       IFNULL((SELECT ROUND(SUM(t.operator_hours),1) FROM timesheet t WHERE t.driver=d.id),0) AS total_wh,
                       IFNULL((SELECT ROUND(SUM(t.operator_standby_hours),1) FROM timesheet t WHERE t.driver=d.id),0) AS standby_wh,
                       CASE WHEN d.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt
                FROM drivers d LEFT JOIN suppliers s ON s.id=d.supplier_id
                WHERE $ws ORDER BY total_wh DESC, d.id DESC";
    } else {
        $headers = ['الكود','الاسم','المورد','الهاتف','الحالة','ساعات التنفيذ','ساعات الاستعداد'];
        $sql = "SELECT d.driver_code,
                       d.name,
                       IFNULL(s.name,'—') AS supplier_name,
                       IFNULL(d.phone,'—') AS phone,
                       CASE WHEN d.status=1 THEN 'نشط' ELSE 'غير نشط' END AS status_txt,
                       IFNULL((SELECT ROUND(SUM(t.operator_hours),1) FROM timesheet t WHERE t.driver=d.id),0) AS total_wh,
                       IFNULL((SELECT ROUND(SUM(t.operator_standby_hours),1) FROM timesheet t WHERE t.driver=d.id),0) AS standby_wh
                FROM drivers d LEFT JOIN suppliers s ON s.id=d.supplier_id
                WHERE $ws ORDER BY total_wh DESC, d.id DESC";
    }
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $sumOperatorHours = 0;
    $sumStandbyHours  = 0;
    foreach ($rows as $r) {
        $sumOperatorHours += floatval($r['total_wh'] ?? 0);
        $sumStandbyHours  += floatval($r['standby_wh'] ?? 0);
    }
    $kpi[] = ['icon'=>'fa-clock', 'value'=> number_format($sumOperatorHours,1) . ' س', 'label'=>'ساعات التنفيذ', 'color'=>'teal'];
    $kpi[] = ['icon'=>'fa-hourglass-half', 'value'=> number_format($sumStandbyHours,1) . ' س', 'label'=>'ساعات الاستعداد', 'color'=>'purple'];

    if (count($rows) > 1) {
        $sorted = $rows;
        usort($sorted, function($a,$b){ return floatval($b['total_wh'])-floatval($a['total_wh']); });
        $slice = array_slice($sorted, 0, 8);
        $cl=[]; $cv=[];
        foreach ($slice as $r) { $cl[]=mb_substr($r['name'],0,14,'UTF-8'); $cv[]=floatval($r['total_wh']); }
        $chartData = ['type'=>'bar','labels'=>$cl,'datasets'=>[
            ['label'=>'ساعات العمل','data'=>$cv,'color'=>'rgba(124,58,237,0.82)'],
        ],'title'=>'أعلى المشغلين في ساعات العمل'];

        $actD  = intval($kpiRow['active'] ?? 0);
        $inact = intval($kpiRow['total']  ?? 0) - $actD;
        if ($actD + $inact > 0) $chart2 = ['type'=>'doughnut','labels'=>['نشط','غير نشط'],'datasets'=>[
            ['label'=>'المشغلون','data'=>[$actD,$inact],'color'=>['rgba(22,163,74,0.85)','rgba(220,38,38,0.72)']],
        ],'title'=>'توزيع حالات المشغلين'];
    }
    break;
}

case 'drivers_contracts': {
    $where = ["({$sc['dc']})"];
    if ($fStatus >= 0)    $where[] = "dc.status = $fStatus";
    if ($fDriverId  > 0)  $where[] = "dc.driver_id = $fDriverId";
    if ($fProjectId > 0)  $where[] = "dc.project_id = $fProjectId";
    if ($fContractStatus !== '') {
        $safeCst = mysqli_real_escape_string($conn, $fContractStatus);
        if ($safeCst === 'terminated')   $where[] = "dc.termination_type IS NOT NULL";
        elseif ($safeCst === 'paused')   $where[] = "dc.pause_reason IS NOT NULL AND dc.resume_date IS NULL";
        elseif ($safeCst === 'active')   $where[] = "dc.termination_type IS NULL AND (dc.pause_reason IS NULL OR dc.resume_date IS NOT NULL)";
    }
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(dc.id) AS total,
                      SUM(CASE WHEN dc.status=1 THEN 1 ELSE 0 END) AS active,
                      ROUND(IFNULL(SUM(dc.forecasted_contracted_hours),0),0) AS total_hrs,
                      ROUND(AVG(dc.contract_duration_months),1) AS avg_dur,
                      COUNT(DISTINCT dc.driver_id) AS distinct_drivers
               FROM drivercontracts dc WHERE $ws";
    $kpiRes = mysqli_query($conn, $kpiSql);
    $kpiRow = $kpiRes ? mysqli_fetch_assoc($kpiRes) : [];

    $kpi = [
        ['icon'=>'fa-file-alt',    'value'=> number_format($kpiRow['total']             ?? 0),        'label'=>'إجمالي العقود',   'color'=>'blue'],
        ['icon'=>'fa-check-circle','value'=> number_format($kpiRow['active']            ?? 0),        'label'=>'عقود نشطة',       'color'=>'green'],
        ['icon'=>'fa-users',       'value'=> number_format($kpiRow['distinct_drivers']  ?? 0),        'label'=>'عدد المشغلين',    'color'=>'gold'],
        ['icon'=>'fa-clock',       'value'=> number_format($kpiRow['total_hrs']         ?? 0) . ' س','label'=>'ساعات مستهدفة',  'color'=>'teal'],
        ['icon'=>'fa-calendar',    'value'=> ($kpiRow['avg_dur'] ?? 0) . ' شهر',                     'label'=>'متوسط المدة',    'color'=>'purple'],
    ];

    // عرض تفصيلي كامل لهذا القسم دائمًا
    $headers = ['المشغل','الكود','المشروع','تاريخ التوقيع','تاريخ البدء','تاريخ الانتهاء','المدة','فترة السماح','نوع المعدة','عدد المعدات','نوع الآلة','عدد الآلات','الساعات الشهرية','الإجمالي المتوقع','الطرف الأول','الطرف الثاني','العملة','المبلغ المدفوع','وقت الدفع','الضمانات','الحالة'];
    $sql = "SELECT IFNULL(d.name,'—') AS driver_name,
                   IFNULL(d.driver_code,'-') AS driver_code,
                   IFNULL(p.name,'—') AS project_name,
                   IFNULL(dc.contract_signing_date,'—') AS signing_date,
                   IFNULL(dc.actual_start,'—') AS actual_start,
                   IFNULL(dc.actual_end,'—') AS actual_end,
                   CONCAT(IFNULL(dc.contract_duration_months,0),' شهر + ',IFNULL(dc.contract_duration_days,0),' يوم') AS duration_txt,
                   IFNULL(dc.grace_period_days,0) AS grace_days,
                   IFNULL(dc.equip_type,'—') AS equip_type,
                   IFNULL(dc.equip_count,0) AS equip_count,
                   IFNULL(dc.mach_type,'—') AS mach_type,
                   IFNULL(dc.mach_count,0) AS mach_count,
                   IFNULL(dc.hours_monthly_target,0) AS monthly_target,
                   IFNULL(dc.forecasted_contracted_hours,0) AS total_target,
                   IFNULL(dc.first_party,'—') AS first_party,
                   IFNULL(dc.second_party,'—') AS second_party,
                   IFNULL(dc.price_currency_contract,'—') AS currency,
                   IFNULL(dc.paid_contract,'—') AS paid_amount,
                   IFNULL(dc.payment_time,'—') AS payment_time,
                   IFNULL(dc.guarantees,'—') AS guarantees,
                   CASE WHEN dc.termination_type IS NOT NULL THEN 'منتهي'
                        WHEN dc.pause_reason IS NOT NULL AND dc.resume_date IS NULL THEN 'موقوف'
                        WHEN dc.status=1 THEN 'نشط' ELSE 'غير ساري' END AS status_txt
            FROM drivercontracts dc
            LEFT JOIN drivers d  ON d.id=dc.driver_id
            LEFT JOIN project p  ON p.id=dc.project_id
            WHERE $ws ORDER BY dc.contract_signing_date DESC";
            $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    if (count($rows) > 0) {
        $actC = intval($kpiRow['active'] ?? 0);
        $inac = intval($kpiRow['total']  ?? 0) - $actC;
        if ($actC + $inac > 0) $chartData = ['type'=>'doughnut','labels'=>['نشط','غير ساري'],'datasets'=>[
            ['label'=>'عقود المشغلين','data'=>[$actC,$inac],'color'=>['rgba(22,163,74,0.85)','rgba(220,38,38,0.75)']],
        ],'title'=>'توزيع حالات عقود المشغلين'];
    }
    break;
}

/* ─────────────────────────────────────────────────────────────────────────
   MAINTENANCE (الصيانة) — مؤشرات الصيانة لكل معدة (معزول بالشركة)
───────────────────────────────────────────────────────────────────────── */
case 'maintenance_summary': {
    $scMo  = rptCompanyScope($conn, 'mo', 'mnt_order', $companyId, $isSuperAdmin);
    $where = ["($scMo)", "COALESCE(mo.is_deleted,0)=0"];
    if ($fDateFrom)    $where[] = "DATE(mo.created_at) >= '" . mysqli_real_escape_string($conn, $fDateFrom) . "'";
    if ($fDateTo)      $where[] = "DATE(mo.created_at) <= '" . mysqli_real_escape_string($conn, $fDateTo) . "'";
    if ($fEquipId > 0) $where[] = "mo.equipment_id = $fEquipId";
    $ws = implode(' AND ', $where);

    $kpiSql = "SELECT COUNT(*) total,
                      SUM(mo.state='إغلاق') closed,
                      SUM(mo.state IN ('بلاغ','تنفيذ','فحص')) open_cnt,
                      SUM(mo.source='بلاغ') failures,
                      ROUND(IFNULL(SUM(mo.downtime_hours),0),1) downtime,
                      ROUND(IFNULL(SUM(mo.total_cost),0),2) cost
               FROM mnt_order mo WHERE $ws";
    $kr = mysqli_query($conn, $kpiSql);
    $ka = $kr ? mysqli_fetch_assoc($kr) : [];
    $kpi = [
        ['icon'=>'fa-wrench',               'value'=> number_format($ka['total']    ?? 0),          'label'=>'إجمالي الأوامر', 'color'=>'blue'],
        ['icon'=>'fa-spinner',              'value'=> number_format($ka['open_cnt'] ?? 0),          'label'=>'أوامر مفتوحة',   'color'=>'gold'],
        ['icon'=>'fa-check-circle',         'value'=> number_format($ka['closed']   ?? 0),          'label'=>'أوامر مغلقة',    'color'=>'green'],
        ['icon'=>'fa-triangle-exclamation', 'value'=> number_format($ka['failures'] ?? 0),          'label'=>'أعطال',          'color'=>'red'],
        ['icon'=>'fa-hourglass-half',       'value'=> number_format($ka['downtime'] ?? 0,1) . ' س', 'label'=>'ساعات التوقّف',  'color'=>'teal'],
        ['icon'=>'fa-coins',                'value'=> number_format($ka['cost']     ?? 0,0),        'label'=>'إجمالي التكلفة', 'color'=>'purple'],
    ];

    $headers = ['الكود','المعدة','إجمالي الأوامر','أعطال','مغلقة','ساعات التوقّف','التكلفة','ساعات التشغيل','MTBF','MTTR','نسبة الجاهزية%'];
    $sql = "SELECT IFNULL(e.code,'-') AS code,
                   IFNULL(e.name,'غير محدد') AS equipment_name,
                   COUNT(mo.id) AS total_orders,
                   SUM(mo.source='بلاغ') AS failures,
                   SUM(mo.state='إغلاق') AS closed_orders,
                   ROUND(IFNULL(SUM(mo.downtime_hours),0),1) AS downtime,
                   ROUND(IFNULL(SUM(mo.total_cost),0),2) AS cost,
                   IFNULL((SELECT ROUND(SUM(t.operator_hours),1) FROM timesheet t JOIN operations o2 ON o2.id=t.operator WHERE o2.equipment=e.id),0) AS op_hours
            FROM mnt_order mo
            LEFT JOIN equipments e ON e.id = mo.equipment_id
            WHERE $ws
            GROUP BY e.id, e.code, e.name
            ORDER BY total_orders DESC";
    $sql = rptApplyInitialLimit($sql, $applyInitialLimit, $INITIAL_LOAD_LIMIT);
    $result = mysqli_query($conn, $sql);
    if (!$result) die('خطأ: ' . mysqli_error($conn));
    while ($r = mysqli_fetch_assoc($result)) {
        $f  = floatval($r['failures']);
        $cl = floatval($r['closed_orders']);
        $dt = floatval($r['downtime']);
        $oh = floatval($r['op_hours']);
        $rows[] = [
            'code'          => $r['code'],
            'equipment_name'=> $r['equipment_name'],
            'total_orders'  => $r['total_orders'],
            'failures'      => $r['failures'],
            'closed_orders' => $r['closed_orders'],
            'downtime'      => $r['downtime'],
            'cost'          => $r['cost'],
            'op_hours'      => $r['op_hours'],
            'mtbf'          => $f  > 0 ? round($oh / $f, 1) : 0,
            'mttr'          => $cl > 0 ? round($dt / $cl, 1) : 0,
            'availability'  => ($oh + $dt) > 0 ? round($oh / ($oh + $dt) * 100, 1) . '%' : '—',
        ];
    }
    if (count($rows) > 0) {
        $cl2 = []; $cv = [];
        foreach (array_slice($rows, 0, 8) as $r) { $cl2[] = mb_substr($r['equipment_name'], 0, 14, 'UTF-8'); $cv[] = floatval($r['downtime']); }
        $chartData = ['type'=>'bar','labels'=>$cl2,'datasets'=>[
            ['label'=>'ساعات التوقّف','data'=>$cv,'color'=>'rgba(220,38,38,0.70)'],
        ],'title'=>'أعلى المعدات في ساعات التوقّف'];
    }
    break;
}

default:
    $kpi     = [];
    $headers = ['رسالة'];
    $rows    = [['هذا التقرير غير مدعوم: ' . htmlspecialchars($REPORT_CODE, ENT_QUOTES, 'UTF-8')]];
}

// ─── توحيد الأعمدة: ضمان أن th = td في كل التقارير ─────────────────────
if (!empty($rows) && is_array($rows)) {
    $firstRow = reset($rows);
    if (is_array($firstRow) && !empty($firstRow)) {
        $rowKeys   = array_keys($firstRow);
        $rowCount  = count($rowKeys);
        $headCount = count($headers);

        // عند اختلاف العدد، أنشئ عناوين مكافئة لحقول الصف الأول.
        if ($headCount !== $rowCount) {
            $headers = [];
            foreach ($rowKeys as $k) {
                $label = is_string($k) ? trim(str_replace('_', ' ', $k)) : '';
                $headers[] = ($label !== '') ? $label : 'عمود';
            }
        }

        // ترتيب الحقول بنفس ترتيب الصف الأول لضمان التطابق التام مع العناوين.
        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $ordered = [];
            foreach ($rowKeys as $k) {
                $ordered[] = array_key_exists($k, $row) ? $row[$k] : '';
            }
            $normalizedRows[] = $ordered;
        }
        $rows = $normalizedRows;
    }
}

// ─── معالجة التصدير (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $fmt        = $_POST['export_format'];
    $exportRows = [];
    foreach ($rows as $r) $exportRows[] = array_values($r);
    if ($fmt === 'excel') exportToExcel($REPORT_CODE . '_' . date('Y-m-d'), $page_title, $headers, $exportRows);
    if ($fmt === 'pdf')   exportToPDF($REPORT_CODE . '_' . date('Y-m-d'), $page_title, createHTMLTable($headers, $exportRows));
}

// query string للتصدير (لحفظ الفلاتر)
$exportQs = http_build_query(array_filter($_GET, function($v){ return $v !== ''; }));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo rr($page_title); ?> — إيكوبيشن</title>
<link rel="stylesheet" href="/ems/assets/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.bootstrap5.min.css">
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<style>
/* ════════════════════════════════════════
   DESIGN TOKENS — matching system identity
════════════════════════════════════════ */
:root {
    --navy:   #1a1208; --navy-l: #2d200a;
    --gold:   #f7931a; --gold-l: #ffb347;
    --blue:   #f7931a; --blue-l: #e67e00;
    --teal:   #6b4e2a; --green:  #16a34a;
    --red:    #dc2626; --purple: #7c3aed; --orange: #c96a00;
    --bg:     #f5f0e8; --surface:#ffffff;
    --line:   rgba(26,18,8,.10);
    --txt:    #1a1208; --ink:    #2d200a; --muted:  #6b4e2a;
    --r:      12px;    --rl:     18px;
    --s1: 0 2px 8px rgba(12,28,62,.07);
    --s2: 0 8px 24px rgba(12,28,62,.11);
    --s3: 0 14px 44px rgba(0,0,34,.22);
}
*,*::before,*::after { box-sizing: border-box; }
body {
    margin: 0; font-family: 'Cairo', sans-serif;
    background:
        radial-gradient(circle at 88% 5%,  rgba(247,147,26,.16),  transparent 26%),
        radial-gradient(circle at 5%  92%,  rgba(26,18,8,.08),  transparent 28%),
        var(--bg);
    min-height: 100vh;
}

/* ── Topbar: يستخدم الشريط العلوي المشترك (.ems-topbar) من includes/topbar.php ── */

/* ── Page ───────────────────────────────── */
.rpt-page {
    max-width: 1560px; margin: 0 auto; padding: 18px 16px;
    animation: rptFadeIn .42s ease;
}
@keyframes rptFadeIn { from{opacity:0;transform:translateY(9px)} to{opacity:1;transform:translateY(0)} }

/* ── Header subtitle (وصف التقرير + التصنيف أسفل شريط الرأس الموحّد) ─── */
.rpt-subtitle {
    margin: -4px 4px 14px; font-size: .85rem; color: var(--muted);
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.rpt-subtitle-cat {
    display: inline-flex; align-items: center; gap: 6px;
    background: #efefef;
    color: #2d200a; border-radius: 999px; padding: 4px 12px;
    font-size: .78rem; font-weight: 800;
}

/* ── Filter Card ────────────────────────── */
.rpt-filter {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--r); padding: 14px 16px; margin-bottom: 14px;
    box-shadow: var(--s1);
}
.rpt-filter-hd {
    font-size: .88rem;
    font-weight: 900;
    color: #2d200a;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 10px;
    background: #efefef;
    border: 1px solid rgba(247,147,26,.24);
}
.rpt-filter .fc-filter-label { min-width: 100%; }
.btn-filter {
    background: linear-gradient(120deg, var(--blue), var(--blue-l));
    border: none; color: #fff; border-radius: 10px; font-weight: 700;
    min-height: 38px; padding: 0 20px; font-size: .84rem;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-reset {
    background: #f1f5f9; border: 1px solid var(--line); color: var(--muted);
    border-radius: 10px; font-weight: 700; min-height: 38px;
    padding: 0 14px; font-size: .84rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px; transition: all .18s;
}
.btn-reset:hover { background: #e2e8f0; color: var(--txt); }

/* ── KPI Grid — مطابقة لكاردات إحصائيات صفحة العملاء (.clients-main .stats-card) ── */
.rpt-kpi-grid {
    display: flex; flex-wrap: wrap;
    gap: 12px; margin-bottom: 14px;
}
.rpt-kpi {
    /* 4 كاردات كحد أقصى في السطر؛ والسطر الأخير الناقص يتمدد ليملأ العرض كاملًا */
    flex: 1 1 calc(25% - 9px);
    min-width: 190px;
    background: #eee;
    border: 1px solid #aaa;
    border-radius: 35px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(26,18,8,.07);
    position: relative; overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.rpt-kpi:hover { transform: translateY(-2px); box-shadow: var(--s2); }
.rpt-kpi::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; opacity: .9;
}

.rpt-kpi-ico {
    width: 55px; height: 55px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 10px; margin-top: 6px;
    float: left; vertical-align: middle;
    border: 1px solid #999; background-color: #fff;
}

.rpt-kpi-val {
    color: #222; font-size: 35px; line-height: 1; font-weight: 900;
    font-variant-numeric: tabular-nums; margin-top: 10px;
}
.rpt-kpi-lbl { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }

/* ── Charts ─────────────────────────────── */
.rpt-charts-row {
    display: grid; grid-template-columns: 1fr;
    gap: 12px; margin-bottom: 14px;
}
.rpt-charts-row.two-cols { grid-template-columns: 2fr 1fr; }
.rpt-chart-card {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--r); padding: 16px; box-shadow: var(--s1);
}
.rpt-chart-title {
    font-size: .9rem;
    font-weight: 900;
    color: #2d200a;
    margin-bottom: 14px; display: flex; align-items: center; gap: 7px;
}
.rpt-chart-title::before {
    content: ''; width: 3px; height: 16px;
    background: linear-gradient(180deg, var(--blue), var(--teal));
    border-radius: 999px; display: block;
}
.chart-wrap { position: relative; width: 100%; }
.chart-wrap.bar-h    { height: 220px; }
.chart-wrap.donut-h  { height: 220px; display: flex; align-items: center; justify-content: center; }

/* ── Data Card ──────────────────────────── */
.rpt-data-card {
    background: #efefef;
    border: 1px solid rgba(247,147,26,.2);
    border-radius: var(--r); box-shadow: var(--s1);
    overflow: hidden; margin-bottom: 20px;
}
.rpt-data-head {
    padding: 12px 16px; border-bottom: 1px solid var(--line);
    background:#efefef;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.rpt-table-meta {
    font-size: .9rem;
    font-weight: 900;
    color: #2d200a;
    display: flex; align-items: center; gap: 8px;
}
.rpt-table-meta i { color: var(--blue); }
.cnt-badge {
    background: linear-gradient(120deg, var(--blue), var(--blue-l));
    color: #fff; border-radius: 999px; padding: 3px 11px;
    font-size: .74rem; font-weight: 900;
}
.rpt-export-group { display: flex; gap: 6px; }
.rpt-export-group .btn { border-radius: 999px; font-weight: 700; font-size: .8rem; padding: 5px 14px; }
.btn-excel { background: linear-gradient(120deg, #16a34a, #4ade80); border: none; color: #fff; }
.btn-excel:hover { background: linear-gradient(120deg, #15803d, #22c55e); color: #fff; }
.btn-pdf   { background: linear-gradient(120deg, var(--red), #f87171); border: none; color: #fff; }
.btn-pdf:hover { background: linear-gradient(120deg, #b91c1c, #ef4444); color: #fff; }
.rpt-data-body { padding: 6px 8px 8px; }
.rpt-table-wrap { width: 100%; }

/* ── Table ──────────────────────────────── */
#rTable { margin-bottom: 0 !important; width: 100% !important; }
#rTable thead th {
    background: linear-gradient(120deg, #120d07, #24180d);
    color: #f7931a; font-size: .82rem; font-weight: 900;
    border: none; white-space: nowrap; padding: 11px 14px;
    text-align: center !important;
}
#rTable tbody td {
    font-size: .82rem; color: var(--ink);
    vertical-align: middle; border-color: rgba(12,28,62,.06); padding: 9px 14px;
    text-align: center !important;
    white-space: nowrap;
}
#rTable.dataTable thead th,
#rTable.dataTable tbody td,
.dataTables_scrollHead table.dataTable thead th,
.dataTables_scrollBody table.dataTable tbody td {
    text-align: center !important;
}
#rTable tbody tr:nth-child(even) { background: #f8fafd; }
#rTable tbody tr:hover { background: #fff4e6; }
/* ── DataTables toolbars — نفس مواضع شاشة العملاء ──
   أعلى الجدول: عدد المدخلات (يسارًا) + حقل البحث (يمينًا) والأزرار في الوسط.
   أسفل الجدول: معلومات السجلات (يسارًا) + الترقيم (يمينًا).
   نستخدم direction:ltr على الحاوية لتثبيت المواضع الفيزيائية، ونعيد rtl
   داخل كل خلية ليبقى النص العربي صحيحًا. */
.rpt-dt-top, .rpt-dt-bottom {
    display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px;
    direction: ltr;
}
.rpt-dt-top { margin-bottom: 12px; }
.rpt-dt-bottom { margin-top: 12px; }
.rpt-dt-top > div, .rpt-dt-bottom > div { direction: rtl; }
.rpt-dt-btns { margin-left: auto; margin-right: auto; }   /* الأزرار في الوسط */
.rpt-dt-page { margin-left: auto; }                       /* الترقيم إلى أقصى اليمين */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    float: none !important; margin: 0 !important; width: auto !important; white-space: nowrap;
}

/* ── DataTables controls — تنسيق دائري مطابق لشاشة العملاء (مرجع: ems-tables.css) ── */
.dataTables_wrapper :is(.dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate) {
    color: #242424; font-size: .9rem; font-weight: 700;
}
.dataTables_wrapper .dataTables_filter label,
.dataTables_wrapper .dataTables_length label {
    display: inline-flex; align-items: center; gap: 8px; margin: 0;
    font-size: .9rem; font-weight: 700; color: #242424;
}
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
    background: #f3f3f3 !important; border: 1px solid #c5c5c5 !important;
    border-radius: 999px !important; min-height: 34px; padding: 6px 12px;
    color: #181818; font-size: .85rem; width: auto;
}
.dataTables_wrapper .dataTables_filter input:focus,
.dataTables_wrapper .dataTables_length select:focus {
    border-color: #9b9b9b !important; box-shadow: 0 0 0 2px rgba(0,0,0,.08); outline: none;
}
.dataTables_wrapper .dataTables_info { font-size: .85rem; color: #242424; }
/* أزرار الترقيم — شكل حبّة دائرية، والصفحة الحالية بلون الثيم (ذهبي) كما في العملاء.
   ندعم نمط DataTables القديم (.paginate_button) ونمط Bootstrap5 (.page-link). */
.dataTables_wrapper .dataTables_paginate .paginate_button,
.dataTables_wrapper .pagination .page-link {
    border: 1px solid #c3c3c3 !important; border-radius: 999px !important;
    background: #f3f3f3 !important; color: #222 !important;
    margin: 0 2px !important; min-width: 32px !important; text-align: center;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover,
.dataTables_wrapper .pagination .page-item.active .page-link {
    background: linear-gradient(180deg, var(--gold), var(--gold-l)) !important;
    border-color: rgba(247,147,26,.55) !important; color: #161616 !important; font-weight: 900 !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover,
.dataTables_wrapper .pagination .page-link:hover {
    background: #e9e9e9 !important; border-color: #b4b4b4 !important; color: #111 !important;
}
/* ── أزرار التصدير (نسخ / Excel / PDF / طباعة) — حبّة رمادية كما في العملاء ── */
.dt-buttons { display: flex; flex-wrap: wrap; gap: 6px; }
.dt-button { font-family: Cairo, sans-serif !important; }
.dataTables_wrapper .dt-buttons .dt-button {
    border: 1px solid #8f8f8f !important; border-radius: 999px !important;
    background: #f5f5f5 !important; color: #202020 !important;
    font-weight: 800 !important; font-size: .8rem !important; padding: 5px 14px !important;
    box-shadow: none !important;
}
.dataTables_wrapper .dt-buttons .dt-button:hover {
    background: #ebebeb !important; border-color: #7e7e7e !important;
}
.dt-button-collection { z-index: 9999 !important; }
/* ── Scroll fix ─────────────────────────── */
.dataTables_wrapper { width: 100%; }
.rpt-table-wrap { overflow-x: auto; width: 100%; }
#rTable thead th,
#rTable tbody td { min-width: 90px; }

@media (max-width: 768px) {
    .rpt-page { padding: 10px; }
    .rpt-charts-row.two-cols { grid-template-columns: 1fr; }
    .rpt-kpi-val { font-size: 1.22rem; }
}
</style>
</head>
<body class="ems-site">

<!-- ═══ TOPBAR — الشريط العلوي المشترك لكل الموقع (.ems-topbar) ═══════════ -->
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<!-- ═══ MAIN ══════════════════════════════════════════════════════════════ -->
<main class="main">
<div class="rpt-page">

    <!-- HEADER — نفس شريط رأس الصفحة الموحّد في بقية الموقع (.main_head) ─── -->
    <div class="main_head emsreports-head">
        <div class="head_actions">
            <a href="../index.php" class="add-btn">
                <i class="fas fa-th-large"></i> قائمة التقارير
            </a>
            <a href="/ems/main/dashboard.php" class="back-btn">
                <i class="fas fa-home"></i> لوحة التحكم
            </a>
        </div>
        <h1 class="head-title">
            <div class="title-icon"><i class="fas <?php echo rr($meta['icon']); ?>"></i></div>
            <?php echo rr($page_title); ?>
        </h1>
        <div class="head_back">
            <a href="../index.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <?php if (!empty($meta['description']) || $reportCategory !== ''): ?>
    <p class="rpt-subtitle">
        <?php if ($reportCategory !== ''): ?>
        <span class="rpt-subtitle-cat">
            <i class="fas <?php echo rr(getCategoryIcon($reportCategory)); ?>"></i>
            <?php echo rr(getCategoryLabel($reportCategory)); ?>
        </span>
        <?php endif; ?>
        <?php echo rr($meta['description']); ?>
    </p>
    <?php endif; ?>

    <!-- KPI ──────────────────────────────────────────────────────────── -->
    <?php if (!empty($kpi)): ?>
    <div class="rpt-kpi-grid">
        <?php foreach ($kpi as $k): ?>
        <div class="rpt-kpi <?php echo rr($k['color']); ?>">
            <div class="rpt-kpi-ico"><i class="fas <?php echo rr($k['icon']); ?>"></i></div>
            <div class="rpt-kpi-val"><?php echo rr($k['value']); ?></div>
            <div class="rpt-kpi-lbl"><?php echo rr($k['label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FILTERS ──────────────────────────────────────────────────────── -->
    <?php
    // ─── تهيئة الفلاتر المرئية حسب كود التقرير ───────────────────────────
    $_hasTs         = strpos($REPORT_CODE, 'timesheet') !== false;
    $showDates      = $_hasTs || in_array($REPORT_CODE, ['operations_summary','operations_detailed','fleet_operations']);
    $showProject    = in_array($reportCategory, ['timesheet','projects','contracts','suppliers','operations'])
                      || in_array($REPORT_CODE, ['drivers_contracts','fleet_operations']);
    $showSupplier   = in_array($reportCategory, ['timesheet','suppliers','fleet','operations','drivers']);
    $showDriver     = $_hasTs || in_array($REPORT_CODE, ['drivers_contracts','drivers_timesheet']);
    $showSearch     = in_array($reportCategory, ['projects','fleet'])
                      || in_array($REPORT_CODE, ['drivers_summary','drivers_detailed']);
    $showStatus     = !$_hasTs && (
                          in_array($reportCategory, ['projects','contracts','suppliers','fleet','operations'])
                          || in_array($REPORT_CODE, ['drivers_summary','drivers_detailed','drivers_contracts'])
                      );
    // فلاتر إضافية للتقارير التفصيلية
    $showShift          = in_array($REPORT_CODE, ['timesheet_detailed','timesheet_by_driver','drivers_timesheet']);
    $showContractStatus = in_array($REPORT_CODE, ['contracts_detailed','supplier_contracts_detailed','drivers_contracts']);
    $showEquipType      = in_array($REPORT_CODE, ['fleet_equipment_detailed','fleet_equipment_summary','fleet_operations']);
    $showEquip          = in_array($REPORT_CODE, ['operations_detailed','fleet_operations','timesheet_detailed']);
    $showEquipName      = in_array($REPORT_CODE, ['fleet_equipment_detailed','fleet_equipment_summary']);

    // جلب المعدات للقائمة المنسدلة (عمليات/تايمشيت)
    $equipsList = [];
    if ($showEquip) {
        $equQ = "SELECT e.id, e.name, e.code FROM equipments e WHERE ({$sc['e']}) ORDER BY e.name ASC";
        if ($fSupplierId > 0) $equQ = str_replace('ORDER BY', "AND e.suppliers=$fSupplierId ORDER BY", $equQ);
        $equR = mysqli_query($conn, $equQ);
        if ($equR) while ($er = mysqli_fetch_assoc($equR)) $equipsList[] = $er;
    }
    // جلب المعدات لتقارير الأسطول (مفلترة بالنوع إن وُجد)
    $fleetEquipsList = [];
    if ($showEquipName) {
        $feQ = "SELECT e.id, e.name, e.code FROM equipments e WHERE ({$sc['e']})";
        if ($fSupplierId > 0) $feQ .= " AND e.suppliers=$fSupplierId";
        if ($fCategory !== '') $feQ .= " AND e.type=" . intval($fCategory);
        $feQ .= " ORDER BY e.name ASC";
        $feR = mysqli_query($conn, $feQ);
        if ($feR) while ($fer = mysqli_fetch_assoc($feR)) $fleetEquipsList[] = $fer;
    }
    ?>
    <div class="rpt-filter fc-filter-body">
        <div class="rpt-filter-hd"><i class="fas fa-sliders-h"></i> فلاتر التقرير</div>
        <form method="GET" class="fc-filter-bar row g-2 align-items-end form-grid allforms allforms-visible" style="display: flex;">

            <?php if ($showDates): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-calendar-alt me-1" style="color:var(--blue)"></i>من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo rr($fDateFrom); ?>">
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-calendar-check me-1" style="color:var(--teal)"></i>إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo rr($fDateTo); ?>">
            </div>
            <?php endif; ?>

            <?php if ($showProject): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-project-diagram me-1" style="color:var(--blue)"></i>المشروع</label>
                <select name="project_id" class="form-select">
                    <?php echo rptSelectOptions($projectsList, 'id', 'name', $fProjectId, 'project_code'); ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showSupplier): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-truck me-1" style="color:var(--teal)"></i>المورد</label>
                <select name="supplier_id" class="form-select">
                    <?php echo rptSelectOptions($suppliersList, 'id', 'name', $fSupplierId); ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showEquip && !empty($equipsList)): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-tractor me-1" style="color:var(--gold)"></i>المعدة</label>
                <select name="equip_id" class="form-select">
                    <option value="0">— كل المعدات —</option>
                    <?php foreach ($equipsList as $eq): ?>
                    <option value="<?php echo intval($eq['id']); ?>" <?php echo $fEquipId === intval($eq['id']) ? 'selected' : ''; ?>>
                        <?php if (!empty($eq['code'])) echo rr($eq['code']) . ' - '; echo rr($eq['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showDriver): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-id-badge me-1" style="color:var(--purple)"></i>المشغل</label>
                <select name="driver_id" class="form-select">
                    <?php echo rptSelectOptions($driversList, 'id', 'name', $fDriverId, 'driver_code'); ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showShift): ?>
            <div class="col-xl-1 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-sun me-1" style="color:var(--gold)"></i>الوردية</label>
                <select name="shift" class="form-select">
                    <option value="" <?php echo $fShift === '' ? 'selected' : ''; ?>>الكل</option>
                    <option value="D" <?php echo $fShift === 'D' ? 'selected' : ''; ?>>نهاري</option>
                    <option value="N" <?php echo $fShift === 'N' ? 'selected' : ''; ?>>ليلي</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showContractStatus): ?>
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-info-circle me-1" style="color:var(--blue)"></i>حالة العقد</label>
                <select name="contract_status" class="form-select">
                    <option value="" <?php echo $fContractStatus === '' ? 'selected' : ''; ?>>— الكل —</option>
                    <option value="active"     <?php echo $fContractStatus === 'active'     ? 'selected' : ''; ?>>نشط</option>
                    <option value="paused"     <?php echo $fContractStatus === 'paused'     ? 'selected' : ''; ?>>موقوف</option>
                    <option value="terminated" <?php echo $fContractStatus === 'terminated' ? 'selected' : ''; ?>>منتهي</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showEquipType): ?>
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-tag me-1" style="color:var(--teal)"></i>نوع المعدة</label>
                <select name="category" id="equip_type_filter" class="form-select">
                    <option value="" <?php echo $fCategory === '' ? 'selected' : ''; ?>>— الكل —</option>
                    <?php
                    $typesQ = "SELECT et.id, et.type FROM equipments_types et
                               INNER JOIN equipments e ON e.type = et.id AND ({$sc['e']})
                               WHERE et.status='active'
                               GROUP BY et.id, et.type ORDER BY et.type ASC";
                    $typesR = mysqli_query($conn, $typesQ);
                    if ($typesR) while ($tr = mysqli_fetch_assoc($typesR)):
                    ?>
                    <option value="<?php echo intval($tr['id']); ?>" <?php echo $fCategory === strval($tr['id']) ? 'selected' : ''; ?>>
                        <?php echo rr($tr['type']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showEquipName): ?>
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-tractor me-1" style="color:var(--gold)"></i>المعدة</label>
                <select name="equip_id" id="equip_name_filter" class="form-select">
                    <option value="0">— كل المعدات —</option>
                    <?php foreach ($fleetEquipsList as $feq): ?>
                    <option value="<?php echo intval($feq['id']); ?>" <?php echo $fEquipId === intval($feq['id']) ? 'selected' : ''; ?>>
                        <?php if (!empty($feq['code'])) echo rr($feq['code']) . ' - '; echo rr($feq['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($REPORT_CODE === 'project_detailed'): ?>
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-layer-group me-1" style="color:var(--blue)"></i>الفئة</label>
                <select name="category" class="form-select">
                    <option value="" <?php echo $fCategory === '' ? 'selected' : ''; ?>>— الكل —</option>
                    <?php
                    $catQ = "SELECT DISTINCT p.category FROM project p WHERE ({$sc['p']}) AND p.category IS NOT NULL AND p.category!='' ORDER BY p.category";
                    $catR = mysqli_query($conn, $catQ);
                    if ($catR) while ($cr = mysqli_fetch_assoc($catR)):
                    ?>
                    <option value="<?php echo rr($cr['category']); ?>" <?php echo $fCategory === $cr['category'] ? 'selected' : ''; ?>>
                        <?php echo rr($cr['category']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showSearch): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-search me-1"></i>بحث</label>
                <input type="text" name="search" class="form-control"
                       placeholder="اسم أو كود..." value="<?php echo rr($fSearch); ?>">
            </div>
            <?php endif; ?>

            <?php if ($showStatus): ?>
            <div class="col-xl-1 col-md-3 col-sm-6">
                <label class="fc-filter-label"><i class="fas fa-toggle-on me-1"></i>الحالة</label>
                <select name="status" class="form-select">
                    <option value="" <?php echo $fStatus < 0 ? 'selected' : ''; ?>>الكل</option>
                    <option value="1" <?php echo $fStatus === 1 ? 'selected' : ''; ?>>نشط</option>
                    <option value="0" <?php echo $fStatus === 0 ? 'selected' : ''; ?>>غير نشط</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-auto d-flex gap-2 align-items-end fc-filter-actions">
                <button type="submit" class="btn-filter btn">
                    <i class="fas fa-search"></i> تطبيق
                </button>
                <a href="<?php echo rr(strtok($_SERVER['REQUEST_URI'], '?')); ?>" class="btn-reset">
                    <i class="fas fa-redo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- CHARTS ───────────────────────────────────────────────────────── -->
    <?php if ($chartData && count($rows) > 1): ?>
    <div class="rpt-charts-row <?php echo $chart2 ? 'two-cols' : ''; ?>">

        <div class="rpt-chart-card">
            <div class="rpt-chart-title"><?php echo rr($chartData['title']); ?></div>
            <div class="chart-wrap <?php echo $chartData['type']==='doughnut' ? 'donut-h' : 'bar-h'; ?>">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <?php if ($chart2): ?>
        <div class="rpt-chart-card">
            <div class="rpt-chart-title"><?php echo rr($chart2['title']); ?></div>
            <div class="chart-wrap <?php echo $chart2['type']==='doughnut' ? 'donut-h' : 'bar-h'; ?>">
                <canvas id="chart2"></canvas>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- DATA TABLE ───────────────────────────────────────────────────── -->
    <div class="rpt-data-card">
        <div class="rpt-data-head">
            <div class="rpt-table-meta">
                <i class="fas fa-table"></i>
                <?php echo rr($page_title); ?>
                <span class="cnt-badge"><?php echo count($rows); ?> سجل</span>
                <?php if ($applyInitialLimit): ?>
                <span class="cnt-badge" style="background:linear-gradient(120deg,#0d9488,#14b8a6)">عرض آخر <?php echo intval($INITIAL_LOAD_LIMIT); ?> سجل (افتراضيًا)</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($rows) && !empty($headers)): ?>
            <form method="POST" action="?<?php echo rr($exportQs); ?>" class="rpt-export-group">
                <button name="export_format" value="excel" type="submit" class="btn btn-excel">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button name="export_format" value="pdf" type="submit" class="btn btn-pdf">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="rpt-data-body">
            <div class="rpt-table-wrap table-container">
                <table class="table table-hover nowrap display" id="rTable" style="width:100%">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $h): ?>
                            <th><?php echo rr($h); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php $rowVals = array_values($row); ?>
                            <?php for ($i = 0; $i < count($headers); $i++): ?>
                            <?php
                            $cell = isset($rowVals[$i]) ? $rowVals[$i] : '—';
                            $cv = ($cell === null || $cell === '') ? '—' : (string)$cell;
                            if ($cv === 'نشط') {
                                echo '<td><span class="badge rounded-pill" style="background:rgba(22,163,74,.1);color:#15803d;border:1px solid rgba(22,163,74,.25);font-size:.78rem;font-weight:800;padding:4px 10px;">نشط</span></td>';
                            } elseif (in_array($cv, ['غير نشط','غير ساري','منتهي'])) {
                                echo '<td><span class="badge rounded-pill" style="background:rgba(220,38,38,.08);color:#b91c1c;border:1px solid rgba(220,38,38,.22);font-size:.78rem;font-weight:800;padding:4px 10px;">' . rr($cv) . '</span></td>';
                            } else {
                                echo '<td>' . rr($cv) . '</td>';
                            }
                            ?>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /rpt-page -->
</main>

<!-- ═══ SCRIPTS ══════════════════════════════════════════════════════════ -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script>
$(function(){
    var tbl = $('#rTable');

    if (tbl.length) {
        var dt = tbl.DataTable({
            language: { url: '/ems/assets/i18n/datatables/ar.json' },
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1],[10, 25, 50, 100, 'الكل']],
            order: [],
            autoWidth: false,
            processing: true,
            dom: '<"rpt-dt-top"<"rpt-dt-len"l><"rpt-dt-btns"B><"rpt-dt-search"f>>'
               + 'rt'
               + '<"rpt-dt-bottom"<"rpt-dt-info"i><"rpt-dt-page"p>>',
            buttons: [
                {
                    extend: 'copyHtml5',
                    text: '<i class="fas fa-copy me-1"></i> نسخ',
                    className: 'btn btn-sm btn-outline-secondary',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i> Excel',
                    className: 'btn btn-sm btn-success',
                    exportOptions: { columns: ':visible' },
                    title: '<?php echo addslashes($page_title); ?>'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                    className: 'btn btn-sm btn-danger',
                    exportOptions: { columns: ':visible' },
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: '<?php echo addslashes($page_title); ?>'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print me-1"></i> طباعة',
                    className: 'btn btn-sm btn-outline-secondary',
                    exportOptions: { columns: ':visible' }
                }
            ]
        });

        setTimeout(function(){ dt.columns.adjust().draw(false); }, 120);
        $(window).on('resize', function(){ dt.columns.adjust(); });
    }
});

<?php if ($chartData && count($rows) > 1): ?>
/* ── Main Chart ───────────────────────────────────────── */
(function() {
    var ctx = document.getElementById('mainChart');
    if (!ctx) return;
    var cfg = <?php echo json_encode($chartData); ?>;
    var datasets = cfg.datasets.map(function(ds) {
        return {
            label:           ds.label,
            data:            ds.data,
            backgroundColor: ds.color,
            borderColor:     Array.isArray(ds.color) ? ds.color : ds.color,
            borderWidth:     cfg.type === 'line' ? 2 : 0,
            fill:            false,
            tension:         0.4,
            pointRadius:     cfg.type === 'line' ? 4 : 0,
            pointHoverRadius:cfg.type === 'line' ? 6 : 0,
            borderRadius:    cfg.type === 'bar'  ? 6 : 0,
            borderSkipped:   false,
        };
    });
    new Chart(ctx, {
        type: cfg.type,
        data: { labels: cfg.labels, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: datasets.length > 1 || cfg.type === 'doughnut',
                    position: cfg.type === 'doughnut' ? 'bottom' : 'top',
                    labels: { font: { family: 'Cairo', size: 12 }, color: '#1e293b', padding: 16 }
                },
                tooltip: {
                    rtl: true, bodyFont: { family: 'Cairo' }, titleFont: { family: 'Cairo' },
                    callbacks: { label: function(c){ return ' ' + c.dataset.label + ': ' + c.formattedValue; } }
                }
            },
            scales: cfg.type !== 'doughnut' ? {
                x: { ticks: { font: { family: 'Cairo', size: 11 }, color: '#475569', maxRotation: 38 }, grid: { color: 'rgba(12,28,62,.06)' } },
                y: { ticks: { font: { family: 'Cairo', size: 11 }, color: '#475569' }, grid: { color: 'rgba(12,28,62,.06)' }, beginAtZero: true }
            } : {}
        }
    });
})();
<?php endif; ?>

<?php if ($chart2 && count($rows) > 1): ?>
/* ── Chart 2 ──────────────────────────────────────────── */
(function() {
    var ctx = document.getElementById('chart2');
    if (!ctx) return;
    var cfg = <?php echo json_encode($chart2); ?>;
    var datasets = cfg.datasets.map(function(ds) {
        return {
            label: ds.label, data: ds.data,
            backgroundColor: ds.color, borderColor: ds.color,
            borderWidth: cfg.type === 'line' ? 2 : 0,
            borderRadius: cfg.type === 'bar' ? 6 : 0,
        };
    });
    new Chart(ctx, {
        type: cfg.type,
        data: { labels: cfg.labels, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: cfg.type === 'doughnut',
                    position: 'bottom',
                    labels: { font: { family: 'Cairo', size: 11 }, color: '#1e293b', padding: 14 }
                },
                tooltip: {
                    rtl: true, bodyFont: { family: 'Cairo' }, titleFont: { family: 'Cairo' }
                }
            },
            scales: cfg.type !== 'doughnut' ? {
                x: { ticks: { font: { family: 'Cairo', size: 10 }, color: '#475569', maxRotation: 38 }, grid: { color: 'rgba(12,28,62,.05)' } },
                y: { ticks: { font: { family: 'Cairo', size: 10 }, color: '#475569' }, beginAtZero: true, grid: { color: 'rgba(12,28,62,.05)' } }
            } : {}
        }
    });
})();
<?php endif; ?>

<?php if ($showEquipName ?? false): ?>
/* ── فلتر نوع المعدة → اسم المعدة (AJAX dependent dropdown) ─── */
(function() {
    var typeSelect = document.getElementById('equip_type_filter');
    var nameSelect = document.getElementById('equip_name_filter');
    if (!typeSelect || !nameSelect) return;

    typeSelect.addEventListener('change', function() {
        var selectedType = this.value;
        var currentUrl   = new URL(window.location.href);
        var supplierId   = currentUrl.searchParams.get('supplier_id') || '0';

        // إعادة تعيين قائمة الأسماء
        nameSelect.innerHTML = '<option value="0">جارٍ التحميل...</option>';
        nameSelect.disabled = true;

        var params = new URLSearchParams({
            action:      'get_equipments_by_type',
            type:        selectedType,
            supplier_id: supplierId
        });

        fetch('<?php echo htmlspecialchars(strtok($_SERVER['REQUEST_URI'],'?'), ENT_QUOTES); ?>?' + params.toString() + '&ajax=1')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                nameSelect.innerHTML = '<option value="0">— كل المعدات —</option>';
                data.forEach(function(eq) {
                    var opt = document.createElement('option');
                    opt.value = eq.id;
                    opt.textContent = (eq.code ? eq.code + ' - ' : '') + eq.name;
                    nameSelect.appendChild(opt);
                });
                nameSelect.disabled = false;
            })
            .catch(function() {
                nameSelect.innerHTML = '<option value="0">— كل المعدات —</option>';
                nameSelect.disabled = false;
            });
    });
})();
<?php endif; ?>
</script>
</body>
</html>
