<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

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

echo json_encode([
    'success' => true,
    'data' => $equipment
]);
?>
