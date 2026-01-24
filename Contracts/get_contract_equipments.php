<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

include '../config.php';

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if (!$contract_id) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit();
}

// جلب معدات العقد
$sql = "SELECT equip_type, equip_size, equip_count, equip_target_per_month FROM contractequipments WHERE contract_id = $contract_id ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام']);
    exit();
}

$equipments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $equipments[] = $row;
}

echo json_encode(['success' => true, 'equipments' => $equipments]);
?>
