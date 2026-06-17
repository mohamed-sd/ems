<?php
// نقطة AJAX خفيفة: تُعيد بيانات موديل من سجل النوع والموديل (للوراثة في كرت المعدة)
session_start();
while (ob_get_level()) {
    ob_end_clean();
}

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرّح']);
    exit();
}

include '../config.php';
// مهم: تُضبط بعد config.php لأنه يضبط text/html — وإلا يُلحق حاقن الأرقام سكربتاً يُفسد JSON
header('Content-Type: application/json; charset=utf-8');

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

$model_id = isset($_GET['model_id']) ? intval($_GET['model_id']) : 0;
if ($model_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الموديل مطلوب']);
    exit();
}

// لا يوجد جدول/عمود؟ نرجع فشلاً متسامحاً دون كسر
if (!function_exists('db_table_has_column') || !db_table_has_column($conn, 'fleet_model', 'id')) {
    echo json_encode(['success' => false, 'message' => 'سجل الموديلات غير متاح']);
    exit();
}

$scope = '';
if (!$is_super_admin && db_table_has_column($conn, 'fleet_model', 'company_id') && $company_id > 0) {
    $scope = " AND fm.company_id = " . $company_id;
}

$sql = "SELECT fm.id, fm.equipment_type_id, fm.manufacturer, fm.model_name,
               fm.operating_category, fm.std_capacity, fm.std_capacity_uom,
               et.type AS type_name
        FROM fleet_model fm
        LEFT JOIN equipments_types et ON et.id = fm.equipment_type_id
        WHERE fm.id = ? AND fm.is_deleted = 0" . $scope . "
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'تعذّر الاستعلام']);
    exit();
}
$stmt->bind_param("i", $model_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'الموديل غير موجود']);
    exit();
}

echo json_encode([
    'success' => true,
    'data' => [
        'equipment_type_id'  => (int) $row['equipment_type_id'],
        'type_name'          => $row['type_name'],
        'manufacturer'       => $row['manufacturer'],
        'model_name'         => $row['model_name'],
        'operating_category' => $row['operating_category'],
        'std_capacity'       => $row['std_capacity'],
        'std_capacity_uom'   => $row['std_capacity_uom'],
    ],
], JSON_UNESCAPED_UNICODE);
