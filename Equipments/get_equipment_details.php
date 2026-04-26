<?php
session_start();
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
