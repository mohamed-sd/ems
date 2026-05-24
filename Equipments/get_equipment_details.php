<?php
session_start();
while (ob_get_level()) ob_end_clean();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('normalize_equipment_availability_state')) {
    function normalize_equipment_availability_state($availability_state, $availability_status)
    {
        $availability_state = trim((string) $availability_state);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة' || $availability_state === 'غير متوفرة') {
            return $availability_state;
        }

        if ($availability_status === '' || $availability_status === 'متاحة للعمل' || $availability_status === 'قيد الاستخدام') {
            return 'متوفرة';
        }

        return 'غير متوفرة';
    }
}

if (!function_exists('normalize_equipment_availability_status')) {
    function normalize_equipment_availability_status($availability_state, $availability_status)
    {
        $availability_state = normalize_equipment_availability_state($availability_state, $availability_status);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة') {
            return 'قيد الاستخدام';
        }

        $legacy_map = [
            'موقوفة للصيانة' => 'تحت الصيانة',
            'مبيعة/مسحوبة' => 'مسحوبة',
            'معطلة مؤقتاً' => 'معطلة'
        ];

        if (isset($legacy_map[$availability_status])) {
            return $legacy_map[$availability_status];
        }

        $valid_statuses = ['تحت الصيانة', 'محجوزة', 'مسحوبة', 'في المستودع', 'معطلة'];
        if (in_array($availability_status, $valid_statuses, true)) {
            return $availability_status;
        }

        return 'تحت الصيانة';
    }
}

$equipments_has_machine_number = db_table_has_column($conn, 'equipments', 'machine_number');
$equipments_has_document_type = db_table_has_column($conn, 'equipments', 'document_type');
$equipments_has_site_supervisor_name = db_table_has_column($conn, 'equipments', 'site_supervisor_name');
$equipments_has_site_supervisor_contact = db_table_has_column($conn, 'equipments', 'site_supervisor_contact');
$equipments_has_availability_state = db_table_has_column($conn, 'equipments', 'availability_state');
$equipments_has_company_id = db_table_has_column($conn, 'equipments', 'company_id');
$operations_has_project_id = db_table_has_column($conn, 'operations', 'project_id');
$operations_has_project = db_table_has_column($conn, 'operations', 'project');
$operations_project_col = $operations_has_project_id ? 'project_id' : ($operations_has_project ? 'project' : '');
$operations_has_mine_id = db_table_has_column($conn, 'operations', 'mine_id');
$operations_has_status = db_table_has_column($conn, 'operations', 'status');
$mines_has_mine_name = db_table_has_column($conn, 'mines', 'mine_name');
$mines_has_name = db_table_has_column($conn, 'mines', 'name');

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'معرف المعدة مطلوب']));
}

$equipment_id = intval($_GET['id']);

$company_scope = ($equipments_has_company_id && $current_company_id > 0)
    ? " AND e.company_id = $current_company_id"
    : "";

// جلب جميع بيانات المعدة
$query = "
    SELECT
        e.*,
        et.type AS equipment_type_name,
        s.name AS supplier_name
    FROM equipments e
    LEFT JOIN suppliers s ON e.suppliers = s.id
    LEFT JOIN equipments_types et ON et.id = e.type
    WHERE e.id = $equipment_id $company_scope
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
$equipment['project_name'] = '';
$equipment['mine_name'] = '';

// جلب آخر تشغيل مرتبط بالمعدة بشكل مرن حسب بنية الجدول
if (($operations_project_col !== '' || $operations_has_mine_id) && db_table_has_column($conn, 'operations', 'equipment')) {
    $opsSelect = [];
    if ($operations_project_col !== '') {
        $opsSelect[] = "o.$operations_project_col AS op_project_id";
    }
    if ($operations_has_mine_id) {
        $opsSelect[] = "o.mine_id AS op_mine_id";
    }

    if (!empty($opsSelect)) {
        $opsQuery = "SELECT " . implode(', ', $opsSelect) . " FROM operations o WHERE o.equipment = $equipment_id";
        if ($operations_has_status) {
            $opsQuery .= " AND o.status = '1'";
        }
        $opsQuery .= " ORDER BY o.id DESC LIMIT 1";

        $opsResult = mysqli_query($conn, $opsQuery);
        if ($opsResult && mysqli_num_rows($opsResult) > 0) {
            $opsRow = mysqli_fetch_assoc($opsResult);

            $opProjectId = isset($opsRow['op_project_id']) ? intval($opsRow['op_project_id']) : 0;
            if ($opProjectId > 0) {
                $pResult = mysqli_query($conn, "SELECT name FROM project WHERE id = $opProjectId LIMIT 1");
                if ($pResult && mysqli_num_rows($pResult) > 0) {
                    $pRow = mysqli_fetch_assoc($pResult);
                    $equipment['project_name'] = isset($pRow['name']) ? $pRow['name'] : '';
                }
            }

            $opMineId = isset($opsRow['op_mine_id']) ? intval($opsRow['op_mine_id']) : 0;
            if ($opMineId > 0) {
                $mineNameCol = $mines_has_mine_name ? 'mine_name' : ($mines_has_name ? 'name' : '');
                if ($mineNameCol !== '') {
                    $mResult = mysqli_query($conn, "SELECT $mineNameCol AS mine_name FROM mines WHERE id = $opMineId LIMIT 1");
                    if ($mResult && mysqli_num_rows($mResult) > 0) {
                        $mRow = mysqli_fetch_assoc($mResult);
                        $equipment['mine_name'] = isset($mRow['mine_name']) ? $mRow['mine_name'] : '';
                    }
                }
            }
        }
    }
}
$equipment['machine_number'] = $equipments_has_machine_number && isset($equipment['machine_number']) ? $equipment['machine_number'] : '';
$equipment['document_type'] = $equipments_has_document_type && isset($equipment['document_type']) ? $equipment['document_type'] : '';
$equipment['site_supervisor_name'] = $equipments_has_site_supervisor_name && isset($equipment['site_supervisor_name']) ? $equipment['site_supervisor_name'] : '';
$equipment['site_supervisor_contact'] = $equipments_has_site_supervisor_contact && isset($equipment['site_supervisor_contact']) ? $equipment['site_supervisor_contact'] : '';
$equipment['availability_state'] = normalize_equipment_availability_state(
    $equipments_has_availability_state && isset($equipment['availability_state']) ? $equipment['availability_state'] : '',
    isset($equipment['availability_status']) ? $equipment['availability_status'] : ''
);
$equipment['availability_status'] = normalize_equipment_availability_status(
    $equipment['availability_state'],
    isset($equipment['availability_status']) ? $equipment['availability_status'] : ''
);

echo json_encode([
    'success' => true,
    'data' => $equipment
]);
?>
