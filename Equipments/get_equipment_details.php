<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرّف الشركة غير متوفر']));
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');

$equipment_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($equipments_has_company) {
        $equipment_scope_sql = "e.company_id = $company_id";
    } elseif ($operations_has_company) {
        $equipment_scope_sql = "EXISTS (SELECT 1 FROM operations so WHERE so.equipment = e.id AND so.company_id = $company_id)";
    } elseif ($project_has_company) {
        $equipment_scope_sql = "EXISTS (
            SELECT 1
            FROM operations so
            JOIN project sp ON sp.id = so.project_id
            WHERE so.equipment = e.id
              AND sp.company_id = $company_id
        )";
    } else {
        $equipment_scope_sql = "EXISTS (
            SELECT 1
            FROM operations so
            JOIN project sp ON sp.id = so.project_id
            WHERE so.equipment = e.id
              AND (
                  EXISTS (SELECT 1 FROM users su WHERE su.id = sp.created_by AND su.company_id = $company_id)
                  OR EXISTS (
                      SELECT 1
                      FROM clients sc
                      JOIN users scu ON scu.id = sc.created_by
                      WHERE sc.id = sp.company_client_id AND scu.company_id = $company_id
                  )
              )
        )";
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'معرف المعدة مطلوب']));
}

$equipment_id = intval($_GET['id']);

// جلب جميع بيانات المعدة
$query = "
    SELECT 
        e.*,
        s.name AS supplier_name,
        p.name AS project_name,
        m.mine_name AS mine_name
    FROM equipments e
    LEFT JOIN suppliers s ON e.suppliers = s.id
    LEFT JOIN operations o ON o.equipment = e.id AND o.status = 1
    LEFT JOIN project p ON o.project_id = p.id
    LEFT JOIN mines m ON o.mine_id = m.id
        WHERE e.id = $equipment_id
            AND $equipment_scope_sql
    LIMIT 1
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في الاستعلام']));
}

if (mysqli_num_rows($result) === 0) {
    die(json_encode(['success' => false, 'message' => 'المعدة غير موجودة']));
}

$equipment = mysqli_fetch_assoc($result);

echo json_encode([
    'success' => true,
    'data' => $equipment
]);
?>
