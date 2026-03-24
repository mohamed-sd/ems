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

$contracts_has_company = db_table_has_column($conn, 'contracts', 'company_id');
$mines_has_company = db_table_has_column($conn, 'mines', 'company_id');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');

$contract_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($contracts_has_company) {
        $contract_scope_sql = "c.company_id = $company_id";
    } elseif ($mines_has_company) {
        $contract_scope_sql = "m.company_id = $company_id";
    } elseif ($project_has_company) {
        $contract_scope_sql = "p.company_id = $company_id";
    } else {
        $contract_scope_sql = "(
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

$mine_id = isset($_GET['mine_id']) ? intval($_GET['mine_id']) : 0;

if ($mine_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المنجم غير صحيح']);
    exit;
}

// جلب عقود المنجم مباشرة
$query = "SELECT c.id, c.contract_signing_date, c.contract_duration_months, c.forecasted_contracted_hours
                    FROM contracts c
                    JOIN mines m ON m.id = c.mine_id
                    JOIN project p ON p.id = m.project_id
                    WHERE c.mine_id = $mine_id
                        AND c.status = 1
                        AND $contract_scope_sql
                    ORDER BY c.contract_signing_date DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]);
    exit;
}

$contracts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $label = 'عقد بتاريخ ' . $row['contract_signing_date'] . ' (' . $row['contract_duration_months'] . ' شهر)';
    $contracts[] = [
        'id' => $row['id'],
        'name' => $label,
        'hours' => $row['forecasted_contracted_hours']
    ];
}

echo json_encode(['success' => true, 'contracts' => $contracts]);
?>
