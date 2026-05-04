<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=Unauthorized+company+context");
    exit();
}

$page_permissions = check_page_permissions($conn, 'timesheet');
$can_view = $page_permissions['can_view'];
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+ساعات+العمل+❌");
    exit();
}

$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$timesheet_has_company = db_table_has_column($conn, 'timesheet', 'company_id');

$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
if ($session_project_id <= 0 && !$is_super_admin) {
    $project_check = mysqli_query($conn, "SELECT id FROM project WHERE company_id = $company_id AND status = 1 LIMIT 1");
    if ($project_check && mysqli_num_rows($project_check) > 0) {
        $proj = mysqli_fetch_assoc($project_check);
        $session_project_id = intval($proj['id']);
    }
}

$filter_date = isset($_GET['filter_date']) ? mysqli_real_escape_string($conn, trim($_GET['filter_date'])) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, trim($_GET['start_date'])) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, trim($_GET['end_date'])) : '';
$month_filter = isset($_GET['month']) ? mysqli_real_escape_string($conn, trim($_GET['month'])) : '';
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$operation_id = isset($_GET['operation_id']) ? intval($_GET['operation_id']) : 0;
$driver_id = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;
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
    $equipment_type !== '' ||
    $operation_id > 0 ||
    $driver_id > 0 ||
    $shift_filter !== '' ||
    $status_filter !== ''
);

$where_parts = ["1=1"];

if (!$is_super_admin) {
    if ($timesheet_has_company) {
        $where_parts[] = "t.company_id = $company_id";
    } else {
        $where_parts[] = "EXISTS (
            SELECT 1
            FROM project p2
            LEFT JOIN users su2 ON su2.id = p2.created_by
            LEFT JOIN clients sc2 ON sc2.id = p2.$project_client_column
            LEFT JOIN users scu2 ON scu2.id = sc2.created_by
            WHERE p2.id = o.$operations_project_column
              AND (su2.company_id = $company_id OR scu2.company_id = $company_id)
        )";
    }
}

if ((string) $_SESSION['user']['role'] === '6') {
    $where_parts[] = "t.user_id = " . intval($_SESSION['user']['id']);
}

if ($month_filter !== '') {
    $month_start = $month_filter . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    $where_parts[] = "t.date >= '$month_start'";
    $where_parts[] = "t.date <= '$month_end'";
} elseif ($filter_date !== '') {
    $where_parts[] = "t.date = '$filter_date'";
} else {
    if ($start_date !== '') {
        $where_parts[] = "t.date >= '$start_date'";
    }
    if ($end_date !== '') {
        $where_parts[] = "t.date <= '$end_date'";
    }
}

if (!$is_super_admin && $session_project_id > 0) {
    $where_parts[] = "p.id = $session_project_id";
}

if ($operation_id > 0) {
    $where_parts[] = "o.id = $operation_id";
}

if ($driver_id > 0) {
    $where_parts[] = "d.id = $driver_id";
}

if ($equipment_type === '1' || $equipment_type === '2' || $equipment_type === '3') {
    $where_parts[] = "t.type = '$equipment_type'";
}

if ($shift_filter === 'D' || $shift_filter === 'N') {
    $where_parts[] = "t.shift = '$shift_filter'";
}

if ($status_filter === '1' || $status_filter === '2' || $status_filter === '3') {
    $where_parts[] = "t.status = '$status_filter'";
}

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

$base_from_sql = "
    FROM timesheet t
    JOIN operations o ON t.operator = o.id
    JOIN equipments e ON o.equipment = e.id
    JOIN project p ON o.$operations_project_column = p.id
    LEFT JOIN drivers d ON t.driver = d.id
";

$order_sql = " ORDER BY t.date DESC, t.id DESC ";
$display_limit_sql = (!$has_filters && !$export_all) ? " LIMIT 100 " : "";

$stats = [
    'executed_sum' => 0,
    'standby_sum' => 0,
    'fault_sum' => 0,
    'work_sum' => 0
];

if (!$has_filters) {
    $stats_query = mysqli_query($conn, "SELECT
        IFNULL(SUM(x.executed_hours), 0) AS executed_sum,
        IFNULL(SUM(x.standby_hours), 0) AS standby_sum,
        IFNULL(SUM(x.total_fault_hours), 0) AS fault_sum,
        IFNULL(SUM(x.executed_hours + x.standby_hours), 0) AS work_sum
        FROM (
            SELECT t.executed_hours, t.standby_hours, t.total_fault_hours
            $base_from_sql
            $where_sql
            $order_sql
            LIMIT 100
        ) x
    ");
} else {
    $stats_query = mysqli_query($conn, "SELECT
        IFNULL(SUM(t.executed_hours), 0) AS executed_sum,
        IFNULL(SUM(t.standby_hours), 0) AS standby_sum,
        IFNULL(SUM(t.total_fault_hours), 0) AS fault_sum,
        IFNULL(SUM(t.executed_hours + t.standby_hours), 0) AS work_sum
        $base_from_sql
        $where_sql
    ");
}

if ($stats_query && mysqli_num_rows($stats_query) > 0) {
    $stats = mysqli_fetch_assoc($stats_query);
}

$scope_where_operations = "1=1";
if (!$is_super_admin) {
    $scope_where_operations .= " AND EXISTS (
        SELECT 1
        FROM project p
        LEFT JOIN users su ON su.id = p.created_by
        LEFT JOIN clients sc ON sc.id = p.$project_client_column
        LEFT JOIN users scu ON scu.id = sc.created_by
        WHERE p.id = o.$operations_project_column
          AND (su.company_id = $company_id OR scu.company_id = $company_id)
    )";
}

$operations = [];
$operation_project_filter = "";
if (!$is_super_admin && $session_project_id > 0) {
    $operation_project_filter = " AND o.$operations_project_column = $session_project_id";
}

// Same type filter logic used in entry page (timesheet.php).
$operation_type_filter = " AND 1=0";
if ($equipment_type === '1' || $equipment_type === '2') {
    $operation_type_filter = " AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE '$equipment_type' AND status = 'active')";
}

$operations_query = mysqli_query($conn, "SELECT
    o.id,
    e.code AS eq_code,
    e.name AS eq_name
    FROM operations o
    JOIN equipments e ON o.equipment = e.id
    WHERE o.status = '1' AND $scope_where_operations $operation_project_filter $operation_type_filter
    ORDER BY e.code ASC, e.name ASC
");
if ($operations_query) {
    while ($row = mysqli_fetch_assoc($operations_query)) {
        $operations[] = $row;
    }
}

$drivers = [];
if ($operation_id > 0) {
    $equipment_id = 0;
    $op_query = mysqli_query($conn, "SELECT o.equipment
        FROM operations o
        WHERE o.id = $operation_id AND $scope_where_operations
        LIMIT 1");
    if ($op_query && mysqli_num_rows($op_query) > 0) {
        $op_row = mysqli_fetch_assoc($op_query);
        $equipment_id = intval($op_row['equipment']);
    }

    if ($equipment_id > 0) {
        $driver_sql = "SELECT d.id, d.name
            FROM equipment_drivers ed
            JOIN drivers d ON ed.driver_id = d.id
            WHERE ed.equipment_id = $equipment_id";

        if (!$is_super_admin && db_table_has_column($conn, 'drivers', 'company_id')) {
            $driver_sql .= " AND d.company_id = $company_id";
        } elseif (!$is_super_admin) {
            $driver_sql .= " AND EXISTS (
                SELECT 1
                FROM equipment_drivers ed2
                INNER JOIN operations o2 ON o2.equipment = ed2.equipment_id
                INNER JOIN project p2 ON p2.id = o2.$operations_project_column
                LEFT JOIN users su2 ON su2.id = p2.created_by
                LEFT JOIN clients sc2 ON sc2.id = p2.$project_client_column
                LEFT JOIN users scu2 ON scu2.id = sc2.created_by
                WHERE ed2.driver_id = d.id
                  AND (su2.company_id = $company_id OR scu2.company_id = $company_id)
            )";
        }

        $driver_sql .= " ORDER BY d.name ASC";
        $drivers_query = mysqli_query($conn, $driver_sql);
        if ($drivers_query) {
            while ($row = mysqli_fetch_assoc($drivers_query)) {
                $drivers[] = $row;
            }
        }
    }
}

$projects = [];
$projects_query = mysqli_query($conn, "SELECT DISTINCT p.id, p.name
    FROM project p
    LEFT JOIN users su ON su.id = p.created_by
    LEFT JOIN clients sc ON sc.id = p.$project_client_column
    LEFT JOIN users scu ON scu.id = sc.created_by
    WHERE p.status = 1" . (!$is_super_admin ? " AND (su.company_id = $company_id OR scu.company_id = $company_id)" : "") . "
    ORDER BY p.name ASC");
if ($projects_query) {
    while ($row = mysqli_fetch_assoc($projects_query)) {
        $projects[] = $row;
    }
}

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
    $order_sql
";

if ($export_all) {
    $export_result = mysqli_query($conn, $select_sql);
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
        while ($row = mysqli_fetch_assoc($export_result)) {
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

$result = mysqli_query($conn, $select_sql . $display_limit_sql);

// Pre-fetch all rows into array + batch-load fault counts from bridge table
$all_rows = [];
if ($result) {
    while ($_r = mysqli_fetch_assoc($result)) {
        $all_rows[] = $_r;
    }
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
        $_fc_tbl = @$conn->query("SHOW TABLES LIKE 'timesheet_failure_hours'");
        if ($_fc_tbl && $_fc_tbl->num_rows > 0) {
            $_fc_res = $conn->query("SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_failure_hours WHERE timesheet_id IN ($_ids_in) AND status = 1 GROUP BY timesheet_id");
            if ($_fc_res) {
                while ($_fc = $_fc_res->fetch_assoc()) {
                    $fault_counts_map[intval($_fc['timesheet_id'])] = intval($_fc['cnt']);
                }
            }
        }
        
        // Load recorded notes
        $_an_tbl = @$conn->query("SHOW TABLES LIKE 'timesheet_approval_notes'");
        if ($_an_tbl && $_an_tbl->num_rows > 0) {
            $_an_res = $conn->query("SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_approval_notes WHERE timesheet_id IN ($_ids_in) AND status = 1 GROUP BY timesheet_id");
            if ($_an_res) {
                while ($_an = $_an_res->fetch_assoc()) {
                    $notes_map[intval($_an['timesheet_id'])] = intval($_an['cnt']);
                }
            }
        }
    }
}

$export_params = $_GET;
$export_params['export_all'] = '1';
$export_all_url = 'view_timesheet.php?' . http_build_query($export_params);

$page_title = "إيكوبيشن | عرض ساعات العمل";
include('../inheader.php');
include('../insidebar.php');
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<style>
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
    color: var(--txt);
}
.group-item input {
    width: 16px;
    height: 16px;
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
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-table"></i></div>
            شاشة عرض ساعات العمل
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="javascript:void(0);" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='../main/dashboard.php'; }" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="view_timesheet.php" class="back-btn" style="background: var(--green-soft); color: var(--green); border-color: rgba(22,163,74,.22);">
                <i class="fas fa-redo"></i> إعادة تعيين
            </a>
            <a href="<?= htmlspecialchars($export_all_url) ?>" class="back-btn" style="background: #0c4a6e; color: #fff; border-color: #0c4a6e;">
                <i class="fas fa-file-export"></i> تصدير كل البيانات حسب الفلترة
            </a>
        </div>
    </div>

    <?php if (!$has_filters) { ?>
        <div class="notice-box">
            يتم عرض آخر 100 سجل فقط لتحسين السرعة. استخدم الفلاتر لعرض نطاق أوسع.
        </div>
    <?php } ?>

    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-card-value"><?= number_format((float) $stats['executed_sum'], 2) ?></div>
            <div class="stat-card-label">إجمالي الساعات المنفذة</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-card-value"><?= number_format((float) $stats['standby_sum'], 2) ?></div>
            <div class="stat-card-label">إجمالي ساعات الاستعداد</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-card-value"><?= number_format((float) $stats['fault_sum'], 2) ?></div>
            <div class="stat-card-label">إجمالي ساعات الأعطال</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-sigma"></i></div>
            <div class="stat-card-value"><?= number_format((float) $stats['work_sum'], 2) ?></div>
            <div class="stat-card-label">إجمالي ساعات العمل (منفذ + استعداد)</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلترة النتائج</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="form-grid">
                <div>
                    <label><i class="fas fa-cogs"></i> نوع الآلية</label>
                    <select name="equipment_type" id="equipment_type_filter" class="form-control">
                        <option value="">-- اختر نوع الآلية --</option>
                        <option value="1" <?= $equipment_type === '1' ? 'selected' : '' ?>>معدات ثقيلة</option>
                        <option value="2" <?= $equipment_type === '2' ? 'selected' : '' ?>>شاحنات</option>
                      <option value="3" <?= $equipment_type === '3' ? 'selected' : '' ?>>خرمات</option>

                    </select>
                </div>
                <div>
                    <label><i class="fas fa-truck-moving"></i> الآلية</label>
                    <select name="operation_id" id="operation_filter" class="form-control">
                        <option value=""><?= ($equipment_type === '1' || $equipment_type === '2') ? '-- اختر الآلية --' : '-- اختر نوع الآلية أولاً --' ?></option>
                        <?php foreach ($operations as $op) { ?>
                            <option value="<?= intval($op['id']) ?>" <?= $operation_id === intval($op['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($op['eq_code'] . ' - ' . $op['eq_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-sun"></i> الوردية</label>
                    <select name="shift" class="form-control">
                        <option value="">-- الكل --</option>
                        <option value="D" <?= $shift_filter === 'D' ? 'selected' : '' ?>>☀️ صباحية</option>
                        <option value="N" <?= $shift_filter === 'N' ? 'selected' : '' ?>>🌙 مسائية</option>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-user"></i> المشغل (السائق)</label>
                    <select name="driver_id" id="driver_filter" class="form-control">
                        <option value=""><?= $operation_id > 0 ? '-- اختر السائق --' : '-- اختر الآلية أولاً --' ?></option>
                        <?php foreach ($drivers as $driver) { ?>
                            <option value="<?= intval($driver['id']) ?>" <?= $driver_id === intval($driver['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($driver['name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-calendar-day"></i> تاريخ محدد</label>
                    <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>" />
                </div>
                <div>
                    <label><i class="fas fa-calendar"></i> من تاريخ</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" />
                </div>
                <div>
                    <label><i class="fas fa-calendar"></i> إلى تاريخ</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" />
                </div>
                <div>
                    <label><i class="fas fa-calendar-alt"></i> الشهر</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month_filter) ?>" />
                </div>
                <div>
                    <label><i class="fas fa-toggle-on"></i> حالة السجل</label>
                    <select name="status" class="form-control">
                        <option value="">-- الكل --</option>
                        <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>قيد المراجعة</option>
                        <option value="2" <?= $status_filter === '2' ? 'selected' : '' ?>>معتمد</option>
                        <option value="3" <?= $status_filter === '3' ? 'selected' : '' ?>>مرفوض</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-search"></i> تطبيق
                    </button>
                    <a href="view_timesheet.php" class="btn-cancel" style="text-decoration: none; padding: 11px 26px;">
                        <i class="fas fa-redo"></i> مسح الفلاتر
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top: 16px;">
        <div class="card-header">
            <h5><i class="fas fa-layer-group"></i> إظهار وإخفاء مجموعات الحقول</h5>
        </div>
        <div class="card-body">
            <div class="group-panel">
                <div class="group-grid">
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="basic" checked>المعلومات العامة</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="work" checked>ساعات العمل</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="fault_hours" checked>ساعات الأعطال</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="counter" checked>عداد الساعات</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="fault_details" checked>تفاصيل الأعطال</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="operator" checked>ساعات المشغل</label>
                    <label class="group-item"><input type="checkbox" class="group-toggle" data-group="notes" checked>الملاحظات</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 16px;">
        <div class="card-header">
            <h5><i class="fas fa-columns"></i> اختيار الحقول المعروضة</h5>
        </div>
        <div class="card-body">
            <div class="group-panel">
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <button type="button" id="showAllFields" class="btn-submit" style="padding:8px 14px;">إظهار كل الحقول</button>
                    <button type="button" id="hideAllFields" class="btn-cancel" style="padding:8px 14px;">إخفاء كل الحقول</button>
                </div>
                <div class="group-grid" id="fieldSelectorGrid"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة ساعات العمل</h5>
        </div>
        <div class="card-body">
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

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
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
        responsive: false,
        scrollX: true,
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

    var columnMeta = [];
    table.columns().every(function (idx) {
        var header = this.header();
        var label = $(header).text().trim();
        var group = $(header).data('group') || '';
        if (label !== '') {
            columnMeta.push({ idx: idx, label: label, group: group });
        }
    });

    function setColumnVisibilityByIdx(idx, visible, redraw) {
        table.column(idx).visible(visible, false);
        if (redraw !== false) {
            table.columns.adjust().draw(false);
        }
    }

    function syncGroupToggles() {
        $('.group-toggle').each(function () {
            var groupName = $(this).data('group');
            var allVisible = true;
            var groupCols = columnMeta.filter(function (c) { return c.group === groupName; });
            groupCols.forEach(function (c) {
                if (!table.column(c.idx).visible()) {
                    allVisible = false;
                }
            });
            $(this).prop('checked', allVisible);
        });
    }

    function buildFieldSelector() {
        var container = $('#fieldSelectorGrid');
        container.empty();
        columnMeta.forEach(function (c) {
            var checkedAttr = table.column(c.idx).visible() ? 'checked' : '';
            var item = $('<label class="group-item"><input type="checkbox" class="field-toggle" ' + checkedAttr + ' data-idx="' + c.idx + '">' + c.label + '</label>');
            container.append(item);
        });
    }

    function applyGroupVisibility(groupName, visible) {
        var groupCols = columnMeta.filter(function (c) { return c.group === groupName; });
        groupCols.forEach(function (c) {
            setColumnVisibilityByIdx(c.idx, visible, false);
            $('.field-toggle[data-idx="' + c.idx + '"]').prop('checked', visible);
        });
        table.columns.adjust().draw(false);
    }

    buildFieldSelector();

    $(document).on('change', '.group-toggle', function () {
        var groupName = $(this).data('group');
        var visible = $(this).is(':checked');
        applyGroupVisibility(groupName, visible);
        syncGroupToggles();
    });

    $(document).on('change', '.field-toggle', function () {
        var idx = parseInt($(this).data('idx'), 10);
        var visible = $(this).is(':checked');
        setColumnVisibilityByIdx(idx, visible);
        syncGroupToggles();
    });

    $('#showAllFields').on('click', function () {
        $('.field-toggle').each(function () {
            $(this).prop('checked', true);
            var idx = parseInt($(this).data('idx'), 10);
            setColumnVisibilityByIdx(idx, true, false);
        });
        table.columns.adjust().draw(false);
        syncGroupToggles();
    });

    $('#hideAllFields').on('click', function () {
        $('.field-toggle').each(function () {
            $(this).prop('checked', false);
            var idx = parseInt($(this).data('idx'), 10);
            setColumnVisibilityByIdx(idx, false, false);
        });
        // Keep details column visible to avoid a blank table state.
        var detailsIdx = $('#timesheetTable thead th').filter(function () {
            return $(this).text().trim() === 'التفاصيل';
        }).first().index();
        if (detailsIdx >= 0) {
            setColumnVisibilityByIdx(detailsIdx, true, false);
            $('.field-toggle[data-idx="' + detailsIdx + '"]').prop('checked', true);
        }
        table.columns.adjust().draw(false);
        syncGroupToggles();
    });

    syncGroupToggles();
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
