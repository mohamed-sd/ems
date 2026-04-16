<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['error' => 'Unauthorized company context']));
}

$tenant_scope = "";
if (!$is_super_admin) {
    if (db_table_has_column($conn, 'timesheet', 'company_id')) {
        $tenant_scope = " AND t.company_id = $company_id";
    } else {
        $tenant_scope = " AND EXISTS (
            SELECT 1
            FROM project p2
            LEFT JOIN users su2 ON su2.id = p2.created_by
            LEFT JOIN clients sc2 ON sc2.id = p2.company_client_id
            LEFT JOIN users scu2 ON scu2.id = sc2.created_by
            WHERE p2.id = o.project_id
              AND (su2.company_id = $company_id OR scu2.company_id = $company_id)
        )";
    }
}

// Get parameters from DataTable
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
$search = isset($_GET['search']['value']) ? mysqli_real_escape_string($conn, $_GET['search']['value']) : '';
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$today_only = isset($_GET['today_only']) ? $_GET['today_only'] : '0';
$today_filter = '';
if ($today_only === '1') {
    $today = date('Y-m-d');
    $today_filter = " AND t.date = '$today'";
}

// Column mapping for ordering
$columns = ['', 't.id', 'e.code', 't.date', 't.shift', 't.executed_hours', 't.bucket_hours',
            't.jackhammer_hours', 't.extra_hours', 't.standby_hours', 't.total_fault_hours',
            't.total_work_hours', '', 't.status', ''];

$orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
$orderDir = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
$orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 't.id';

// Build WHERE clause
$where = "WHERE t.type LIKE '$type'" . $tenant_scope . $today_filter;

if ($_SESSION['user']['role'] == "6") {
    $user_filter = $_SESSION['user']['id'];
    $where .= " AND t.user_id = '$user_filter'";
}

// Add search filter
if (!empty($search)) {
    $where .= " AND (e.code LIKE '%$search%' 
                OR e.name LIKE '%$search%' 
                OR d.name LIKE '%$search%' 
                OR t.date LIKE '%$search%'
                OR p.name LIKE '%$search%')";
}

// Count total records (without filtering)
$totalQuery = "SELECT COUNT(*) as total 
               FROM timesheet t
               JOIN operations o ON t.operator = o.id
               JOIN equipments e ON o.equipment = e.id
               JOIN project p ON o.project_id = p.id
               JOIN drivers d ON t.driver = d.id
               WHERE t.type LIKE '$type'" . $tenant_scope . $today_filter;

if ($_SESSION['user']['role'] == "6") {
    $totalQuery .= " AND t.user_id = '$user_filter'";
}

$totalResult = mysqli_query($conn, $totalQuery);
$totalRecords = mysqli_fetch_assoc($totalResult)['total'];

// Count filtered records
$filteredQuery = "SELECT COUNT(*) as total 
                  FROM timesheet t
                  JOIN operations o ON t.operator = o.id
                  JOIN equipments e ON o.equipment = e.id
                  JOIN project p ON o.project_id = p.id
                  JOIN drivers d ON t.driver = d.id
                  $where";

$filteredResult = mysqli_query($conn, $filteredQuery);
$filteredRecords = mysqli_fetch_assoc($filteredResult)['total'];

// Fetch data
$query = "SELECT t.id, t.shift, t.date, t.executed_hours,
          t.standby_hours, t.total_fault_hours, t.bucket_hours, t.jackhammer_hours,
          t.extra_hours, t.extra_hours_total, t.dependence_hours, t.total_work_hours, 
          t.status, t.work_notes, t.hr_fault,
          e.code AS eq_code, e.name AS eq_name,
          p.name AS project_name,
          o.id AS operation_id,
          d.name AS driver_name 
          FROM timesheet t
          JOIN operations o ON t.operator = o.id
          JOIN equipments e ON o.equipment = e.id
          JOIN project p ON o.project_id = p.id
          JOIN drivers d ON t.driver = d.id
          $where
          ORDER BY $orderColumn $orderDir
          LIMIT $start, $length";

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['error' => mysqli_error($conn)]));
}

$data = [];
$i = $start + 1;

while ($row = mysqli_fetch_assoc($result)) {
    $totalwork = $row['standby_hours'] + $row['bucket_hours'] + $row['jackhammer_hours'] + 
                 $row['extra_hours'] + $row['dependence_hours'];
    $totalall = $row['total_work_hours'] + $row['total_fault_hours'];

    // Status badge
    switch ($row['status']) {
        case "1":
            $status = "<font color='grey'>تحت المراجعة</font>";
            break;
        case "2":
            $status = "<font color='green'>تم الاعتماد</font>";
            break;
        case "3":
            $status = "<font color='red'>تم الرفض</font>";
            break;
        default:
            $status = "غير معروف";
    }

    // Shift badge
    $shiftBadge = $row['shift'] == "D" 
        ? "<span style='background: #ffeaa7; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #2d3436;'><i class='fas fa-sun'></i> صباحية</span>" 
        : "<span style='background: #2d3436; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #fff;'><i class='fas fa-moon'></i> مسائية</span>";

    // Action buttons
    $actions = "
        <a href='aprovment.php?t=$type&type=1&id={$row['id']}' title='قبول' style='color: #27ae60; font-size: 1.1rem; margin: 0 3px;'>
            <i class='fas fa-check-circle'></i>
        </a>
        <a href='aprovment.php?t=$type&type=2&id={$row['id']}' title='رفض' style='color: #e74c3c; font-size: 1.1rem; margin: 0 3px;'>
            <i class='fas fa-times-circle'></i>
        </a>
        <a href='javascript:void(0)' class='editBtn' data-id='{$row['id']}' title='تعديل' style='color:#3498db; font-size: 1.1rem; margin: 0 3px;'>
            <i class='fas fa-edit'></i>
        </a>
        <a href='delete_timesheet.php?id={$row['id']}' onclick='return confirm(\"هل أنت متأكد؟\")' title='حذف' style='color: #e74c3c; font-size: 1.1rem; margin: 0 3px;'>
            <i class='fas fa-trash'></i>
        </a>
        <a href='timesheet_details.php?id={$row['id']}' title='عرض التفاصيل' style='color: #8e44ad; font-size: 1.1rem; margin: 0 3px;'>
            <i class='fas fa-eye'></i>
        </a>";

    $data[] = [
        "<span style='font-weight: 600;'>$i</span>",
        "<span style='font-weight: 700; color: #1f2937;'>{$row['id']}</span>",
        "<span style='font-weight: 600; color: #2980b9;'>{$row['eq_code']} - {$row['eq_name']}</span>",
        $row['date'],
        $shiftBadge,
        "<span style='background: #e8f5e9; font-weight: 600; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['executed_hours']}</span>",
        "<span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['bucket_hours']}</span>",
        "<span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['jackhammer_hours']}</span>",
        "<span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['extra_hours']}</span>",
        "<span style='background: #fff3e0; font-weight: 600; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['standby_hours']}</span>",
        "<span style='background: #fff3e0; font-weight: 600; color: #d63031; padding: 4px 8px; display: inline-block; border-radius: 4px;'>{$row['total_fault_hours']}</span>",
        "<span style='background: #e3f2fd; font-weight: 700; color: #2980b9; font-size: 1.05rem; padding: 4px 8px; display: inline-block; border-radius: 4px;'>$totalwork</span>",
        "<span style='background: #ffebee; font-weight: 700; color: #c0392b; font-size: 1.05rem; padding: 4px 8px; display: inline-block; border-radius: 4px;'>$totalall</span>",
        "<div style='text-align: center;'>$status</div>",
        "<div style='white-space: nowrap; text-align: center;'>$actions</div>"
    ];
    $i++;
}

// Return JSON response
$response = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
