<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit();
}

include '../config.php';
include 'contractequipments_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contract_id'])) {
    $contract_id = intval($_POST['contract_id']);
    
    // جلب المعدات للعقد
    $equipments = getContractEquipments($contract_id, $conn);
    
    // إرجاع البيانات كـ JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($equipments);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>
