<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرّف الشركة غير متوفر']);
    exit;
}

$mines_has_company = db_table_has_column($conn, 'mines', 'company_id');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');

$project_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($project_has_company) {
        $project_scope_sql = "p.company_id = $company_id";
    } else {
        $project_scope_sql = "(
            EXISTS (SELECT 1 FROM users su WHERE su.id = p.created_by AND su.company_id = $company_id)
            OR EXISTS (
                SELECT 1
                FROM clients sc
                JOIN users scu ON scu.id = sc.created_by
                WHERE sc.id = p.company_client_id AND scu.company_id = $company_id
            )
        )";
    }
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المشروع غير صحيح']);
    exit;
}

$mine_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($mines_has_company) {
        $mine_scope_sql = "m.company_id = $company_id";
    } else {
        $mine_scope_sql = $project_scope_sql;
    }
}

$query = "SELECT m.id, m.mine_name, m.mine_code
          FROM mines m
          JOIN project p ON p.id = m.project_id
          WHERE m.project_id = $project_id
            AND m.status = 1
            AND $mine_scope_sql
            AND $project_scope_sql
          ORDER BY m.mine_name";
$result = mysqli_query($conn, $query);

$mines = [];
while ($row = mysqli_fetch_assoc($result)) {
    $mines[] = [
        'id' => $row['id'],
        'name' => $row['mine_name'] . ' (' . $row['mine_code'] . ')'
    ];
}

echo json_encode(['success' => true, 'mines' => $mines]);
?>
