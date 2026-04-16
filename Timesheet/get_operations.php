<?php
session_start();
if (!isset($_SESSION['user'])) {
    exit;
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    exit;
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
if ($type !== '1' && $type !== '2') {
    echo "<option value=''>-- اختر نوع الآلية أولاً --</option>";
    exit;
}

if ($session_project_id <= 0 && !$is_super_admin) {
    $project_check = mysqli_query($conn, "SELECT id FROM project WHERE company_id = $company_id AND status = 1 LIMIT 1");
    if ($project_check && mysqli_num_rows($project_check) > 0) {
        $proj = mysqli_fetch_assoc($project_check);
        $session_project_id = intval($proj['id']);
    }
}

$scope_sql = "";
if (!$is_super_admin) {
    $scope_sql = " AND EXISTS (
        SELECT 1
        FROM project p
        LEFT JOIN users su ON su.id = p.created_by
        LEFT JOIN clients sc ON sc.id = p.$project_client_column
        LEFT JOIN users scu ON scu.id = sc.created_by
        WHERE p.id = o.$operations_project_column
          AND (su.company_id = $company_id OR scu.company_id = $company_id)
    )";

    if ($session_project_id > 0) {
        $scope_sql .= " AND o.$operations_project_column = $session_project_id";
    }
}

$type_filter_sql = " AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE '$type' AND status = 'active')";

$query = "SELECT o.id, e.code, e.name
          FROM operations o
          JOIN equipments e ON o.equipment = e.id
          WHERE o.status = '1' $scope_sql $type_filter_sql
          ORDER BY e.code ASC, e.name ASC";

$result = mysqli_query($conn, $query);

echo "<option value=''>-- اختر الآلية --</option>";
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $id = intval($row['id']);
        $label = htmlspecialchars($row['code'] . ' - ' . $row['name']);
        echo "<option value='{$id}'>{$label}</option>";
    }
}
