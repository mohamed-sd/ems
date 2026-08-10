<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';

// فلترة الموقع لمدير الموقع (Site Manager - Role 5)
$is_site_manager = ($current_role === '5');

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'Unauthorized company context', 'GOV-INFO-200', '');
    exit();
}

$page_permissions = check_page_permissions($conn, 'timesheet');
$can_view = $page_permissions['can_view'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض ساعات العمل ❌', 'GOV-PERM-403', '');
    exit();
}

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$vts_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('view timesheet super') : ems_tenant_db();

$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
if ($session_project_id <= 0 && !$is_super_admin) {
    try {
        $proj = $vts_gate->selectOne('project', array('columns' => array('id'), 'where' => array('status' => 1)));
        if ($proj) { $session_project_id = intval($proj['id']); }
    } catch (\Throwable $t) { error_log('view_timesheet project fallback: ' . $t->getMessage()); }
}

$filter_date = isset($_GET['filter_date']) ? mysqli_real_escape_string($conn, trim($_GET['filter_date'])) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, trim($_GET['start_date'])) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, trim($_GET['end_date'])) : '';
$month_filter = isset($_GET['month']) ? mysqli_real_escape_string($conn, trim($_GET['month'])) : '';
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$operation_id = isset($_GET['operation_id']) ? intval($_GET['operation_id']) : 0;
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$equipment_type_raw = isset($_GET['equipment_type']) ? mysqli_real_escape_string($conn, trim($_GET['equipment_type'])) : '';
$type_from_url = isset($_GET['type']) ? trim($_GET['type']) : '';
$equipment_type = ($equipment_type_raw === '1' || $equipment_type_raw === '2' || $equipment_type_raw === '3')
    ? $equipment_type_raw
    : (($type_from_url === '1' || $type_from_url === '2' || $type_from_url === '3') ? $type_from_url : '');
$shift_filter = isset($_GET['shift']) ? mysqli_real_escape_string($conn, trim($_GET['shift'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';
$export_all = isset($_GET['export_all']) && $_GET['export_all'] === '1';

$has_filters = (
    $filter_date !== '' ||
    $start_date !== '' ||
    $end_date !== '' ||
    $month_filter !== '' ||
    $project_id > 0 ||
    $equipment_type !== '' ||
    $operation_id > 0 ||
    $employee_id > 0 ||
    $shift_filter !== '' ||
    $status_filter !== ''
);

// نطاق الشركة يُحقن عبر {TENANT_SCOPE} في كل استعلام — لا شروط يدوية بعد الآن
$scope_where_parts = ["1=1"];
$filter_where_parts = [];

if ((string) $_SESSION['user']['role'] === '6') {
    $scope_where_parts[] = "t.user_id = " . intval($_SESSION['user']['id']);
}

// تقييد مدير الموقع على مشروع الجلسة فقط (لباقي الأدوار نعرض على مستوى الشركة)
if (!$is_super_admin && $is_site_manager && $session_project_id > 0) {
    $scope_where_parts[] = "p.id = $session_project_id";
}

if ($month_filter !== '') {
    $month_start = $month_filter . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    $filter_where_parts[] = "t.date >= '$month_start'";
    $filter_where_parts[] = "t.date <= '$month_end'";
} elseif ($filter_date !== '') {
    $filter_where_parts[] = "t.date = '$filter_date'";
} else {
    if ($start_date !== '') {
        $filter_where_parts[] = "t.date >= '$start_date'";
    }
    if ($end_date !== '') {
        $filter_where_parts[] = "t.date <= '$end_date'";
    }
}

if ($project_id > 0) {
    $filter_where_parts[] = "p.id = $project_id";
}

if ($operation_id > 0) {
    $filter_where_parts[] = "o.id = $operation_id";
}

if ($employee_id > 0) {
    $filter_where_parts[] = "d.id = $employee_id";
}

if ($equipment_type === '1' || $equipment_type === '2' || $equipment_type === '3') {
    $filter_where_parts[] = "t.type = '$equipment_type'";
}

if ($shift_filter === 'D' || $shift_filter === 'N') {
    $filter_where_parts[] = "t.shift = '$shift_filter'";
}

if ($status_filter === '1' || $status_filter === '2' || $status_filter === '3') {
    $filter_where_parts[] = "t.status = '$status_filter'";
}

$where_parts = array_merge($scope_where_parts, $filter_where_parts);
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);
$scope_where_sql = 'WHERE ' . implode(' AND ', $scope_where_parts);

$base_from_sql = "
    FROM timesheet t
    JOIN operations o ON t.operator = o.id
    JOIN equipments e ON o.equipment = e.id
    JOIN project p ON o.project_id = p.id
    LEFT JOIN employees d ON t.employee_id = d.id
";

$vts_decl = array(
    'scope'  => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments', 'p' => 'project'),
    'enrich' => array('d' => 'employees'),
);

// المعاملات المُهيّأة تعيد أنواعًا أصلية (int/float) بينما الشاشة تقارن نصوصًا بصرامة (===)
// — نعيد الصفوف نصوصًا كسلوك mysqli_query القديم حرفيًا مع إبقاء NULL كما هي.
if (!function_exists('vts_stringify_rows')) {
    function vts_stringify_rows($rows) {
        foreach ($rows as $i => $r) {
            foreach ($r as $k => $v) {
                if ($v !== null && !is_string($v)) { $rows[$i][$k] = strval($v); }
            }
        }
        return $rows;
    }
}

$order_sql = " ORDER BY t.date DESC, t.id DESC ";
$display_limit_sql = "";

// المطلوب: تحميل آخر 1000 سجل أولاً ضمن نطاق الصلاحية ثم تطبيق الفلاتر.
// (استعلام المعرّفات الداخلي كان منطوقًا بالشركة في الأصل — صار نداء بوابةٍ مستقلًا بنطاقه)
$recent_1000_clause = "";
$recent_ids_empty = false;
if (!$export_all) {
    $recent_ids = array();
    try {
        $recent_rows = $vts_gate->scopedQuery($vts_decl,
            "SELECT t.id
                    $base_from_sql
                    $scope_where_sql AND {TENANT_SCOPE}
                    $order_sql
                    LIMIT 1000");
        foreach ($recent_rows as $rr) { $recent_ids[] = intval($rr['id']); }
    } catch (\Throwable $t) { error_log('view_timesheet recent ids: ' . $t->getMessage()); }
    if ($recent_ids) {
        $recent_1000_clause = " AND t.id IN (" . implode(',', $recent_ids) . ")";
    } else {
        $recent_ids_empty = true; // الأصل: IN على مجموعةٍ فارغة = لا صفوف
    }
}

$stats = [
    'executed_sum' => 0,
    'standby_sum' => 0,
    'fault_sum' => 0,
    'work_sum' => 0
];

if (!$recent_ids_empty) {
    try {
        $stats_rows = $vts_gate->scopedQuery($vts_decl, "SELECT
    IFNULL(SUM(t.executed_hours), 0) AS executed_sum,
    IFNULL(SUM(t.standby_hours), 0) AS standby_sum,
    IFNULL(SUM(t.total_fault_hours), 0) AS fault_sum,
    IFNULL(SUM(t.executed_hours + t.standby_hours), 0) AS work_sum
    $base_from_sql
    $where_sql
    $recent_1000_clause AND {TENANT_SCOPE}
");
        if ($stats_rows) { $stats = $stats_rows[0]; }
    } catch (\Throwable $t) { error_log('view_timesheet stats: ' . $t->getMessage()); }
}

// إضافة فلتر المشروع لمدير الموقع في استعلام العمليات

$operations = [];
$operation_project_filter = "";
if (!$is_super_admin && $is_site_manager && $session_project_id > 0) {
    $operation_project_filter = " AND o.project_id = $session_project_id";
}

// Same type filter logic used in entry page (timesheet.php).
$operation_type_filter = " AND 1=0";
$operation_type_params = array();
if ($equipment_type === '1' || $equipment_type === '2') {
    $operation_type_filter = " AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE ? AND status = 'active')";
    $operation_type_params[] = $equipment_type;
}

try {
    $operations = $vts_gate->scopedQuery(
        array('scope' => array('o' => 'operations', 'e' => 'equipments')),
        "SELECT
    o.id,
    e.code AS eq_code,
    e.name AS eq_name
    FROM operations o
    JOIN equipments e ON o.equipment = e.id
    WHERE o.status = '1' $operation_project_filter $operation_type_filter AND {TENANT_SCOPE}
    ORDER BY e.code ASC, e.name ASC
", $operation_type_params);
} catch (\Throwable $t) { error_log('view_timesheet operations: ' . $t->getMessage()); }

$drivers = [];
if ($operation_id > 0) {
    $equipment_id = 0;
    try {
        $op_row = $vts_gate->selectOne('operations', array('columns' => array('equipment'), 'where' => array('id' => $operation_id)));
        if ($op_row) { $equipment_id = intval($op_row['equipment']); }
    } catch (\Throwable $t) { error_log('view_timesheet op lookup: ' . $t->getMessage()); }

    if ($equipment_id > 0) {
        try {
            $drivers = $vts_gate->scopedQuery(
                array('scope' => array('ed' => 'equipment_drivers', 'd' => 'employees')),
                "SELECT d.id, d.name
            FROM equipment_drivers ed
            JOIN employees d ON ed.employee_id = d.id
            WHERE ed.equipment_id = ? AND {TENANT_SCOPE} ORDER BY d.name ASC", array($equipment_id));
        } catch (\Throwable $t) { error_log('view_timesheet drivers: ' . $t->getMessage()); }
    }
}

$projects = [];
try {
    $projects = $vts_gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT DISTINCT p.id, p.name
    FROM project p
    WHERE p.status = 1 AND {TENANT_SCOPE}
    ORDER BY p.name ASC");
} catch (\Throwable $t) { error_log('view_timesheet projects: ' . $t->getMessage()); }

$select_sql = "SELECT
    t.id,
    t.type,
    t.shift,
    t.date,
    t.shift_hours,
    t.executed_hours,
    t.bucket_hours,
    t.jackhammer_hours,
    t.extra_hours,
    t.extra_hours_total,
    t.standby_hours,
    t.dependence_hours,
    t.work_notes,
    t.hr_fault,
    t.maintenance_fault,
    t.marketing_fault,
    t.approval_fault,
    t.other_fault_hours,
    t.total_fault_hours,
    t.fault_notes,
    t.start_seconds,
    t.start_minutes,
    t.start_hours,
    t.end_seconds,
    t.end_minutes,
    t.end_hours,
    t.counter_diff,
    t.fault_type,
    t.fault_department,
    t.fault_part,
    t.fault_details,
    t.general_notes,
    t.operator_hours,
    t.machine_standby_hours,
    t.jackhammer_standby_hours,
    t.bucket_standby_hours,
    t.extra_operator_hours,
    t.operator_standby_hours,
    t.operator_notes,
    t.status,
    e.code AS eq_code,
    e.name AS eq_name,
    p.name AS project_name,
    COALESCE(d.name, 'غير محدد') AS driver_name
    $base_from_sql
    $where_sql
    $recent_1000_clause AND {TENANT_SCOPE}
    $order_sql
";

if ($export_all) {
    $export_result = array();
    try {
        $export_result = vts_stringify_rows($vts_gate->scopedQuery($vts_decl, $select_sql));
    } catch (\Throwable $t) { error_log('view_timesheet export: ' . $t->getMessage()); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=timesheet_filtered_export_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'ID', 'المعدة', 'المشروع', 'السائق', 'التاريخ', 'الوردية', 'نوع المعدة', 'الحالة',
        'ساعات الوردية', 'المنفذة', 'الجردل', 'الجاكهمر', 'إضافية', 'مجموع الإضافي', 'استعداد', 'اعتماد',
        'الإجمالي (منفذ + استعداد)',
        'HR', 'صيانة', 'تسويق', 'اعتماد-عطل', 'أخرى', 'مجموع الأعطال',
        'بداية H', 'بداية M', 'بداية S', 'نهاية H', 'نهاية M', 'نهاية S', 'فرق العداد',
        'نوع العطل', 'قسم العطل', 'الجزء المعطل', 'تفاصيل العطل',
        'ساعات المشغل', 'استعداد الآلية', 'استعداد الجاكهمر', 'استعداد الجردل', 'إضافية مشغل', 'استعداد مشغل',
        'ملاحظات العمل', 'ملاحظات الأعطال', 'ملاحظات المشغل', 'ملاحظات عامة'
    ]);

    if ($export_result) {
        foreach ($export_result as $row) {
            $total_exec_standby = floatval($row['executed_hours']) + floatval($row['standby_hours']);
            $shift_text = $row['shift'] === 'D' ? 'صباحية' : 'مسائية';
            $equipment_type_text = $row['type'] === '1' ? 'حفار' : ($row['type'] === '2' ? 'قلاب' : ($row['type'] === '3' ? 'خرامة' : 'غير محدد'));
            $status_text = $row['status'] === '1' ? 'قيد المراجعة' : ($row['status'] === '2' ? 'معتمد' : ($row['status'] === '3' ? 'مرفوض' : 'غير معروف'));

            fputcsv($out, [
                $row['id'],
                $row['eq_code'] . ' - ' . $row['eq_name'],
                $row['project_name'],
                $row['driver_name'],
                $row['date'],
                $shift_text,
                $equipment_type_text,
                $status_text,
                $row['shift_hours'],
                $row['executed_hours'],
                $row['bucket_hours'],
                $row['jackhammer_hours'],
                $row['extra_hours'],
                $row['extra_hours_total'],
                $row['standby_hours'],
                $row['dependence_hours'],
                $total_exec_standby,
                $row['hr_fault'],
                $row['maintenance_fault'],
                $row['marketing_fault'],
                $row['approval_fault'],
                $row['other_fault_hours'],
                $row['total_fault_hours'],
                $row['start_hours'],
                $row['start_minutes'],
                $row['start_seconds'],
                $row['end_hours'],
                $row['end_minutes'],
                $row['end_seconds'],
                $row['counter_diff'],
                $row['fault_type'],
                $row['fault_department'],
                $row['fault_part'],
                $row['fault_details'],
                $row['operator_hours'],
                $row['machine_standby_hours'],
                $row['jackhammer_standby_hours'],
                $row['bucket_standby_hours'],
                $row['extra_operator_hours'],
                $row['operator_standby_hours'],
                $row['work_notes'],
                $row['fault_notes'],
                $row['operator_notes'],
                $row['general_notes']
            ]);
        }
    }

    fclose($out);
    exit();
}

// Pre-fetch all rows into array + batch-load fault counts from bridge table
$all_rows = [];
if (!$recent_ids_empty) {
    try {
        $all_rows = vts_stringify_rows($vts_gate->scopedQuery($vts_decl, $select_sql . $display_limit_sql));
    } catch (\Throwable $t) { error_log('view_timesheet main: ' . $t->getMessage()); }
}
$fault_counts_map = [];
// Pre-load recorded notes and failures for each timesheet
$notes_map = [];
$failures_map = [];
if (!empty($all_rows)) {
    $_ts_ids = array_filter(array_map('intval', array_column($all_rows, 'id')));
    if (!empty($_ts_ids)) {
        $_ids_in = implode(',', $_ts_ids);

        // Load failure counts
        try {
            $_fc_rows = $vts_gate->scopedQuery(array('scope' => array('f' => 'timesheet_failure_hours')),
                "SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_failure_hours f WHERE f.timesheet_id IN ($_ids_in) AND f.status = 1 AND {TENANT_SCOPE} GROUP BY timesheet_id");
            foreach ($_fc_rows as $_fc) {
                $fault_counts_map[intval($_fc['timesheet_id'])] = intval($_fc['cnt']);
            }
        } catch (\Throwable $t) { error_log('view_timesheet fault counts: ' . $t->getMessage()); }

        // Load recorded notes
        try {
            $_an_rows = $vts_gate->scopedQuery(array('scope' => array('nn' => 'timesheet_approval_notes')),
                "SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_approval_notes nn WHERE nn.timesheet_id IN ($_ids_in) AND nn.status = 1 AND {TENANT_SCOPE} GROUP BY timesheet_id");
            foreach ($_an_rows as $_an) {
                $notes_map[intval($_an['timesheet_id'])] = intval($_an['cnt']);
            }
        } catch (\Throwable $t) { error_log('view_timesheet notes counts: ' . $t->getMessage()); }
    }
}

$export_params = $_GET;
$export_params['export_all'] = '1';
$export_all_url = 'view_timesheet.php?' . http_build_query($export_params);

$page_title = "إيكوبيشن | سجل الوحدات اليومية";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/ems.main.all.style.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<style>
/* ═══════════════════════════════════════════════════════════════
   Unified Form & Cards Styling — Timesheet View
   (Matching Clients Page Design)
═══════════════════════════════════════════════════════════════ */

/* Shadow classes */
.shadow-sm {
  box-shadow: 0 1px 3px rgba(26, 18, 8, 0.08), 0 4px 12px rgba(26, 18, 8, 0.06);
}

/* Card styling */
.card {
  background: #fff;
  border: 1px solid #e8dcc8;
  border-radius: 12px;
  overflow: hidden;
}

.card-header {
  background: #f8f9fa;
  border-bottom: 1px solid #e8dcc8;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.card-header h5 {
  margin: 0;
  color: #1a1208;
  font-weight: 800;
  font-size: 1.05rem;
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.card-header h5 i {
  color: #f7931a;
  font-size: 1.15rem;
}

.card-body {
  background: linear-gradient(180deg, #fff 0%, #fffbf5 100%);
  padding: 18px;
}

/* Form card styling */
.pu-form-card {
  background: #fff;
  border: 1px solid #e8dcc8;
  border-radius: 12px;
}

/* Form actions container */
.pu-form-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  justify-content: flex-start;
  flex-wrap: wrap;
}

/* Button styling */
.btn-submit {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 120px;
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #1a1208, #2d200a);
  color: #fff;
  border-left: 3px solid #f7931a;
  font-weight: 800;
  font-size: 0.92rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(247, 147, 26, 0.25);
}

.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(247, 147, 26, 0.35);
}

.btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 120px;
  padding: 12px 20px;
  border: 1.5px solid #e8dcc8;
  border-radius: 10px;
  background: #fff;
  color: #6b4e2a;
  font-weight: 800;
  font-size: 0.92rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.btn-cancel:hover {
  border-color: #a07848;
  background: #fdf8f0;
  color: #1a1208;
}

/* Form grid styling */
.form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  row-gap: 14px;
  column-gap: 12px;
}

@media (min-width: 992px) and (max-width: 1366px) {
  .form-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 991px) {
  .form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

/* Form group */
.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 800;
  font-size: 0.88rem;
  color: #6b4e2a;
  margin-bottom: 6px;
  line-height: 1.3;
}

.form-group label i {
  color: #f7931a;
  font-size: 0.8rem;
}

.form-group input,
.form-group select {
  width: 100%;
  border: 1.4px solid #dacdb8;
  border-radius: 10px;
  background: #fffdfa;
  color: #1a1208;
  padding: 10px 14px;
  min-height: 42px;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: 'Tajawal', 'Cairo', sans-serif;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.form-group input:hover,
.form-group select:hover {
  border-color: rgba(247, 147, 26, 0.5);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #f7931a;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(247, 147, 26, 0.14), 0 3px 10px rgba(26, 18, 8, 0.07);
}

/* Group panel */
.group-panel {
  border: 1px solid rgba(12, 28, 62, 0.1);
  border-radius: 12px;
  padding: 12px;
  background: #f8fafc;
}

.group-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
}

.group-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b4e2a;
}

.group-item input {
  width: 16px;
  height: 16px;
  accent-color: #f7931a;
  cursor: pointer;
}

.col-group-hidden {
  display: none !important;
}

.notice-box {
  margin-bottom: 16px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid rgba(232, 184, 0, 0.3);
  background: rgba(232, 184, 0, 0.1);
  color: #7a5a00;
  font-size: 14px;
}

/* ═══════════════════════════════════════════════════════════════
   Stats Cards Styling (Timesheet)
   مثل صفحة العملاء
═══════════════════════════════════════════════════════════════ */

.stats-grid.ts-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 20px;
  margin-right: 20px;
  margin-left: 20px;
}

.stats-card {
  background: #eee;
  border: 1px solid #aaa;
  border-radius: 35px;
  padding: 18px;
  box-shadow: 0 2px 8px rgba(26, 18, 8, 0.07);
  position: relative;
  overflow: hidden;
}

.stats-card::before {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  height: 3px;
  opacity: 0.9;
}

.stats-card .stats-icon {
  width: 55px;
  height: 55px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  margin-bottom: 10px;
  float:left;
  vertical-align:middle;
  margin-top: 15px ;
  border: 1px solid #999;
}

.stats-card .stats-title {
  color: #555;
  font-size: 0.92rem;
  font-weight: 700;
  margin-top: 5px;
  line-height: 1.3;

}

.stats-card .stats-value {
  color: #222;
  font-size: 1.8rem;
  line-height: 1;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
   margin-top: 10px;
  /* UI-11/21: كان هنا 40px يلغي 1.8rem أعلاه — رقمٌ خارجَ السلّمِ يصير «صفرًا
     عملاقًا» حين لا ساعاتٍ مسجَّلة. القيمةُ عادت إلى السلّم، والصفرُ نفسُه صار
     حالةً مفسَّرةً لا رقمًا أصمّ (انظر ts_stat_value أدناه). */
  font-size: 1.8rem;
}
.stats-card .stats-empty {
  color: #6b7280;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
  margin-top: 10px;
}

/* Color variants */
.ts-stats-executed::before {
  /* background: linear-gradient(90deg, #1d4ed8, #2563eb); */
}

.ts-stats-executed .stats-icon {
  background: #fff;
  color: #000;
}

.ts-stats-standby::before {
  /* background: linear-gradient(90deg, #15803d, #16a34a); */
}

.ts-stats-standby .stats-icon {
   background: #fff;
  color: #000;
}

.ts-stats-fault::before {
  /* background: linear-gradient(90deg, #b91c1c, #dc2626); */
}

.ts-stats-fault .stats-icon {
  background: #fff;
  color: #000;
}

.ts-stats-total::before {
  /* background: linear-gradient(90deg, #b45309, #d97706); */
}

.ts-stats-total .stats-icon {
  background: #fff;
  color: #000;
}

/* Hover effect */
.stats-card {
  transition: all 0.25s ease;
}

.stats-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 16px rgba(26, 18, 8, 0.12);
}

/* Horizontal scroll support for wide timesheet table */
.timesheet-view-page .table-scroll-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.timesheet-view-page .table-scroll-wrap .dataTables_wrapper,
.timesheet-view-page .table-scroll-wrap .dataTables_scroll,
.timesheet-view-page .table-scroll-wrap .dataTables_scrollHead,
.timesheet-view-page .table-scroll-wrap .dataTables_scrollBody,
.timesheet-view-page .table-scroll-wrap table {
    width: 100% !important;
    min-width: 1500px;
}

</style>

<div class="main timesheet-view-page ems-unified-page-shell">

<?php
// Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
$header_title   = 'شاشة سجل الوحدات اليومية';
$header_icon    = 'fas fa-table';
$header_actions = array(
    array('href' => 'view_timesheet.php', 'class' => 'back-btn ts-reset-link', 'icon' => 'fas fa-redo', 'label' => 'إعادة تعيين'),
    array('href' => $export_all_url, 'class' => 'back-btn ts-export-link', 'icon' => 'fas fa-file-export', 'label' => 'تصدير كل البيانات حسب الفلترة'),
);
$header_back = array('href' => 'timesheet_type.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
include('../includes/page_header.php');
?>


    <?php if (!$has_filters) { ?>
        <div class="notice-box">
            يتم عرض آخر 100 سجل فقط لتحسين السرعة. استخدم الفلاتر لعرض نطاق أوسع.
        </div>
    <?php } ?>

    <?php
    /* UI-11/21 (UXR-01): «لا صفرَ عملاقًا بلا تفسير». الصفرُ هنا مُحتملُ المعنى:
       إمّا لا سطورَ في الكشفِ أصلًا، وإمّا سطورٌ لا تحمل هذا النوعَ من الساعات.
       فالبطاقةُ تقول أيَّ الحالتين — لا ترمي رقمًا أصمَّ بحجمِ أربعين نقطة. */
    if (!function_exists('ts_stat_value')) {
        function ts_stat_value($value, $rowsCount)
        {
            $v = (float) $value;
            if ($v != 0.0) {
                return '<div class="stats-value">' . number_format($v, 2) . '</div>';
            }
            $why = ((int) $rowsCount === 0)
                ? 'لا سطورَ في هذا الكشف بعد'
                : 'سطورُ الكشفِ لا تحمل ساعاتٍ من هذا النوع';
            return '<div class="stats-empty">0.00 — ' . $why . '</div>';
        }
    }
    $__tsRows = isset($rows) && is_array($rows) ? count($rows) : (isset($stats['rows_count']) ? (int) $stats['rows_count'] : 0);
    ?>
    <div class="stats-grid ts-stats-grid">
        <div class="stats-card ts-stats-executed">
            <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
            <?= ts_stat_value($stats['executed_sum'], $__tsRows) ?>
            <div class="stats-title">إجمالي الساعات المنفذة</div>
        </div>
        <div class="stats-card ts-stats-standby">
            <div class="stats-icon"><i class="fas fa-pause-circle"></i></div>
            <?= ts_stat_value($stats['standby_sum'], $__tsRows) ?>
            <div class="stats-title">إجمالي ساعات الاستعداد</div>
        </div>
        <div class="stats-card ts-stats-fault">
            <div class="stats-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <?= ts_stat_value($stats['fault_sum'], $__tsRows) ?>
            <div class="stats-title">إجمالي ساعات الأعطال</div>
        </div>
        <div class="stats-card ts-stats-total">
            <div class="stats-icon"><i class="fas fa-chart-pie"></i></div>
            <?= ts_stat_value($stats['work_sum'], $__tsRows) ?>
            <div class="stats-title">إجمالي ساعات العمل</div>
        </div>
    </div>

    <div class="card shadow-sm pu-form-card">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلترة النتائج</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid" id="timesheetFilterForm">
                <div class="form-group">
                    <label><i class="fas fa-cogs"></i> نوع الآلية</label>
                    <select name="equipment_type" id="equipment_type_filter">
                        <option value="">-- اختر نوع الآلية --</option>
                        <option value="1" <?= $equipment_type === '1' ? 'selected' : '' ?>>معدات ثقيلة</option>
                        <option value="2" <?= $equipment_type === '2' ? 'selected' : '' ?>>شاحنات</option>
                        <option value="3" <?= $equipment_type === '3' ? 'selected' : '' ?>>خرمات</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-truck-moving"></i> الآلية</label>
                    <select name="operation_id" id="operation_filter">
                        <option value=""><?= ($equipment_type === '1' || $equipment_type === '2') ? '-- اختر الآلية --' : '-- اختر نوع الآلية أولاً --' ?></option>
                        <?php foreach ($operations as $op) { ?>
                            <option value="<?= intval($op['id']) ?>" <?= $operation_id === intval($op['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($op['eq_code'] . ' - ' . $op['eq_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sun"></i> الوردية</label>
                    <select name="shift">
                        <option value="">-- الكل --</option>
                        <option value="D" <?= $shift_filter === 'D' ? 'selected' : '' ?>>☀️ صباحية</option>
                        <option value="N" <?= $shift_filter === 'N' ? 'selected' : '' ?>>🌙 مسائية</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> المشغل (السائق)</label>
                    <select name="employee_id" id="driver_filter">
                        <option value=""><?= $operation_id > 0 ? '-- اختر السائق --' : '-- اختر الآلية أولاً --' ?></option>
                        <?php foreach ($drivers as $driver) { ?>
                            <option value="<?= intval($driver['id']) ?>" <?= $employee_id === intval($driver['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($driver['name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-day"></i> تاريخ محدد</label>
                    <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" />
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> من تاريخ</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" />
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> إلى تاريخ</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" />
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> الشهر</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" />
                </div>
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> حالة السجل</label>
                    <select name="status">
                        <option value="">-- الكل --</option>
                        <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>قيد المراجعة</option>
                        <option value="2" <?= $status_filter === '2' ? 'selected' : '' ?>>معتمد</option>
                        <option value="3" <?= $status_filter === '3' ? 'selected' : '' ?>>مرفوض</option>
                    </select>
                </div>
            </form>
            <div class="pu-form-actions">
                <button type="submit" form="timesheetFilterForm" class="btn-submit">
                    <i class="fas fa-search"></i> تطبيق
                </button>
                <a href="view_timesheet.php" class="btn-cancel">
                    <i class="fas fa-redo"></i> مسح الفلاتر
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm pu-form-card">
        <div class="card-header">
            <h5><i class="fas fa-layer-group"></i> إظهار وإخفاء مجموعات الحقول</h5>
        </div>
        <div class="card-body">
            <div class="column-groups-toggle">
                <button type="button" class="btn-group-toggle active" data-group="basic"><i class="fas fa-info-circle"></i> المعلومات العامة</button>
                <button type="button" class="btn-group-toggle active" data-group="work"><i class="fas fa-clock"></i> ساعات العمل</button>
                <button type="button" class="btn-group-toggle active" data-group="fault_hours"><i class="fas fa-tools"></i> ساعات الأعطال</button>
                <button type="button" class="btn-group-toggle active" data-group="counter"><i class="fas fa-tachometer-alt"></i> عداد الساعات</button>
                <button type="button" class="btn-group-toggle active" data-group="fault_details"><i class="fas fa-exclamation-triangle"></i> تفاصيل الأعطال</button>
                <button type="button" class="btn-group-toggle active" data-group="operator"><i class="fas fa-user-cog"></i> ساعات المشغل</button>
                <button type="button" class="btn-group-toggle active" data-group="notes"><i class="fas fa-sticky-note"></i> الملاحظات</button>
                <button type="button" class="btn-group-toggle-all active"><i class="fas fa-eye"></i> الكل</button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة ساعات العمل</h5>
        </div>
        <div class="card-body">
            <div class="table-scroll-wrap">
            <table id="timesheetTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th data-group="basic">#</th>
                        <th data-group="basic">رقم السجل</th>
                        <th data-group="basic">الآلية</th>
                        <th data-group="basic">المشروع</th>
                        <th data-group="basic">المشغل</th>
                        <th data-group="basic">التاريخ</th>
                        <th data-group="basic">الوردية</th>
                        <th data-group="basic">نوع المعدة</th>

                        <th data-group="work">ساعات الوردية</th>
                        <th data-group="work">الساعات المنفذة</th>
                        <th data-group="work">ساعات الجردل</th>
                        <th data-group="work">ساعات الجاكمر</th>
                        <th data-group="work">الساعات الإضافية</th>
                        <th data-group="work">مجموع الساعات الإضافية</th>
                        <th data-group="work">ساعات الاستعداد (العميل)</th>
                        <th data-group="work">ساعات الاستعداد (اعتماد)</th>
                        <th data-group="work">الإجمالي (منفذ + استعداد)</th>

                        <th data-group="fault_hours">عطل HR</th>
                        <th data-group="fault_hours">عطل الصيانة</th>
                        <th data-group="fault_hours">عطل التسويق</th>
                        <th data-group="fault_hours">عطل الاعتماد</th>
                        <th data-group="fault_hours">ساعات أعطال أخرى</th>
                        <th data-group="fault_hours">مجموع الأعطال</th>

                        <th data-group="counter">عداد البداية</th>
                        <th data-group="counter">عداد النهاية</th>
                        <th data-group="counter">فرق العداد</th>

                        <th data-group="fault_details">الأعطال المصنفة</th>

                        <th data-group="recorded">الملاحظات المسجلة</th>

                        <th data-group="operator">ساعات عمل المشغل</th>
                        <th data-group="operator">استعداد الآلية</th>
                        <th data-group="operator">استعداد الجاكهمر</th>
                        <th data-group="operator">استعداد الجردل</th>
                        <th data-group="operator">الساعات الإضافية للمشغل</th>
                        <th data-group="operator">ساعات استعداد المشغل</th>

                        <th data-group="notes">ملاحظات ساعات العمل</th>
                        <th data-group="notes">ملاحظات ساعات الأعطال</th>
                        <th data-group="notes">ملاحظات المشغل</th>
                        <th data-group="notes">ملاحظات عامة</th>

                        <th data-group="basic">حالة السجل</th>

                        <th data-group="basic">عرض التفاصيل</th>
                                        <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ السجل وبأي صفة">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="السجل الذي تولد عنه">المرجع الأب</th>
</tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    if (!empty($all_rows)) {
                        foreach ($all_rows as $row) {
                            $status_badge = '';
                            if ($row['status'] === '1') {
                                $status_badge = '<span class="status-pill" style="background: rgba(232,184,0,.13); color: var(--gold); border: 1px solid rgba(232,184,0,.22);">قيد المراجعة</span>';
                            } elseif ($row['status'] === '2') {
                                $status_badge = '<span class="status-pill status-active">معتمد</span>';
                            } elseif ($row['status'] === '3') {
                                $status_badge = '<span class="status-pill status-inactive">مرفوض</span>';
                            } else {
                                $status_badge = '<span class="status-pill">غير معروف</span>';
                            }

                            $shift_text = $row['shift'] === 'D' ? '☀️ صباحية' : '🌙 مسائية';
                            $equipment_type_text = $row['type'] === '1' ? 'حفار' : ($row['type'] === '2' ? 'قلاب' : ($row['type'] === '3' ? 'خرامة' : 'غير محدد'));
                            $total_exec_standby = floatval($row['executed_hours']) + floatval($row['standby_hours']);

                            echo '<tr>';
                            echo '<td>' . $i++ . '</td>';
                            echo '<td>' . intval($row['id']) . '</td>';
                            echo '<td><strong>' . htmlspecialchars($row['eq_code'] . ' - ' . $row['eq_name']) . '</strong></td>';
                            echo '<td>' . htmlspecialchars($row['project_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['driver_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['date']) . '</td>';
                            echo '<td>' . $shift_text . '</td>';
                            echo '<td>' . $equipment_type_text . '</td>';

                            echo '<td>' . htmlspecialchars($row['shift_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['executed_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['bucket_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['jackhammer_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['extra_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['extra_hours_total']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['standby_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['dependence_hours']) . '</td>';
                            echo '<td><strong>' . number_format($total_exec_standby, 2) . '</strong></td>';

                            echo '<td>' . htmlspecialchars($row['hr_fault']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['maintenance_fault']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['marketing_fault']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['approval_fault']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['other_fault_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['total_fault_hours']) . '</td>';

                            $start_counter_text = intval($row['start_hours']) . ' ساعة ' . intval($row['start_minutes']) . ' دقيقة ' . intval($row['start_seconds']) . ' ثانية';
                            $end_counter_text = intval($row['end_hours']) . ' ساعة ' . intval($row['end_minutes']) . ' دقيقة ' . intval($row['end_seconds']) . ' ثانية';

                            echo '<td>' . htmlspecialchars($start_counter_text) . '</td>';
                            echo '<td>' . htmlspecialchars($end_counter_text) . '</td>';
                            echo '<td>' . htmlspecialchars($row['counter_diff']) . '</td>';

                            $_fc_cnt = intval($fault_counts_map[$row['id']] ?? 0);
                            $_legacy_has = !empty($row['fault_type']) || !empty($row['fault_part']);
                            $_badge_cnt = $_fc_cnt > 0 ? $_fc_cnt : ($_legacy_has ? 1 : 0);
                            if ($_badge_cnt > 0) {
                                echo '<td style="text-align:center;"><button class="btn-fault-badge" data-ts-id="' . intval($row['id']) . '" title="عرض الأعطال" style="background:none;border:none;cursor:pointer;padding:2px 6px;"><i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:.85rem;"></i> <span class="badge rounded-pill" style="background:#dc3545;color:#fff;font-size:.68rem;">' . $_badge_cnt . '</span></button></td>';
                            } else {
                                echo '<td style="text-align:center;" title="لا توجد أعطال"><i class="fas fa-check-circle" style="color:#059669;font-size:.9rem;"></i></td>';
                            }

                            $_notes_cnt = intval($notes_map[$row['id']] ?? 0);
                            if ($_notes_cnt > 0) {
                                echo '<td style="text-align:center;"><span class="badge rounded-pill" style="background:#0f2444;color:#fff;font-size:.68rem;"><i class="fas fa-clipboard-check"></i> ' . $_notes_cnt . '</span></td>';
                            } else {
                                echo '<td style="text-align:center;"><span style="color:#adb5bd;font-size:.75rem;">—</span></td>';
                            }

                            echo '<td>' . htmlspecialchars($row['operator_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['machine_standby_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['jackhammer_standby_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['bucket_standby_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['extra_operator_hours']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['operator_standby_hours']) . '</td>';

                            echo '<td>' . htmlspecialchars($row['work_notes']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['fault_notes']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['operator_notes']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['general_notes']) . '</td>';

                            echo '<td>' . $status_badge . '</td>';

                            echo '<td><a href="timesheet_details.php?id=' . intval($row['id']) . '" class="action-btn view" title="عرض التفاصيل"><i class="fas fa-eye"></i></a></td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>

<script>
$(document).ready(function () {
    $('#equipment_type_filter').on('change', function () {
        var typeVal = $(this).val();
        var operationSelect = $('#operation_filter');
        var driverSelect = $('#driver_filter');

        operationSelect.html("<option value=''>-- جاري تحميل الآليات... --</option>");
        driverSelect.html("<option value=''>-- اختر الآلية أولاً --</option>");

        if (typeVal !== '1' && typeVal !== '2' && typeVal !== '3') {
            operationSelect.html("<option value=''>-- اختر نوع الآلية أولاً --</option>");
            return;
        }

        $.ajax({
            url: 'get_operations.php',
            type: 'GET',
            data: { type: typeVal },
            success: function (response) {
                operationSelect.html(response);
            },
            error: function () {
                operationSelect.html("<option value=''>-- تعذر تحميل الآليات --</option>");
            }
        });
    });

    $('#operation_filter').on('change', function () {
        var operationId = $(this).val();
        var driverSelect = $('#driver_filter');

        driverSelect.html("<option value=''>-- جاري تحميل السائقين... --</option>");

        if (!operationId) {
            driverSelect.html("<option value=''>-- اختر الآلية أولاً --</option>");
            return;
        }

        $.ajax({
            url: 'get_drivers.php',
            type: 'GET',
            data: { operation_id: operationId },
            success: function (response) {
                driverSelect.html(response);
            },
            error: function () {
                driverSelect.html("<option value=''>-- تعذر تحميل السائقين --</option>");
            }
        });
    });

    var table = $('#timesheetTable').DataTable({
        scrollX: true,
        scrollCollapse: true,
        autoWidth: false,
        deferRender: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: 'نسخ الظاهر', exportOptions: { modifier: { page: 'current' } } },
            { extend: 'excel', text: 'تصدير الظاهر Excel', exportOptions: { modifier: { page: 'current' } } },
            { extend: 'csv', text: 'تصدير الظاهر CSV', exportOptions: { modifier: { page: 'current' } } },
            { extend: 'print', text: 'طباعة الظاهر', exportOptions: { modifier: { page: 'current' } } }
        ],
        language: {
            url: '/ems/assets/i18n/datatables/ar.json'
        }
    });

    // إظهار/إخفاء المجموعات — موحّد عبر assets/js/column-groups.js
    // (تُشتق فهارس الأعمدة من سمة data-group على الرؤوس).
    if (window.EmsColumnGroups) {
        EmsColumnGroups.init({
            storageKey: 'timesheetGroupStates',
            mode: 'datatable',
            table: table
        });
    }
});
</script>

<!-- ══ Modal: عرض الأعطال ══ -->
<div class="modal fade" id="faultDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fas fa-exclamation-triangle text-danger me-2"></i>
          تفاصيل الأعطال — سجل #<span id="faultModal_ts_id">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="faultModalBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).on('click', '.btn-fault-badge', function() {
    var tsId = $(this).data('ts-id');
    $('#faultModal_ts_id').text(tsId);
    $('#faultModalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    var modal = new bootstrap.Modal(document.getElementById('faultDetailModal'));
    modal.show();
    $.getJSON('get_timesheet_failures.php?timesheet_id=' + tsId, function(res) {
        if (res && res.success && res.data && res.data.length > 0) {
            var html = '<div class="table-responsive"><table class="table table-sm table-hover table-bordered">';
            html += '<thead class="table-dark"><tr><th>#</th><th>الكود الكامل</th><th>نوع الحدث</th><th>الفئة الرئيسية</th><th>الفئة الفرعية</th><th>تفصيل العطل</th></tr></thead><tbody>';
            $.each(res.data, function(i, f) {
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td><span class="badge rounded-pill bg-danger">' + (f.full_code || '—') + '</span></td>';
                html += '<td>' + (f.event_type_name || '—') + '</td>';
                html += '<td>' + (f.main_category_name || '—') + '</td>';
                html += '<td>' + (f.sub_category || '—') + '</td>';
                html += '<td>' + (f.failure_detail || '—') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            $('#faultModalBody').html(html);
        } else {
            $('#faultModalBody').html('<div class="alert alert-warning">لا توجد أعطال مصنفة من منظومة الأعطال. <small class="text-muted">قد تكون البيانات محفوظة بالنظام القديم.</small></div>');
        }
    }).fail(function() {
        $('#faultModalBody').html('<div class="alert alert-danger">تعذر تحميل بيانات الأعطال.</div>');
    });
});
</script>

</body>
</html>
