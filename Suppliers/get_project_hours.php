<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_contract_id'])) {
    $project_contract_id = intval($_POST['project_contract_id']); // معرف العقد من جدول contracts
    $supplier_contract_id = isset($_POST['supplier_contract_id']) ? intval($_POST['supplier_contract_id']) : 0;
    
    // جلب إجمالي ساعات العقد المحدد من جدول contracts
    $contract_query = "SELECT 
        c.forecasted_contracted_hours as contract_total_hours,
        m.project_id
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        WHERE c.id = $project_contract_id
        LIMIT 1";
    $contract_result = mysqli_query($conn, $contract_query);
    $contract_data = mysqli_fetch_assoc($contract_result);
    
    if (!$contract_data) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }
    
    // جلب تفصيل ساعات المعدات حسب النوع للعقد المحدد
    $equipment_details_query = "SELECT 
        ce.equip_type,
        et.type AS equip_type_name,
        COALESCE(SUM(ce.equip_total_contract), 0) as total_hours,
        COALESCE(SUM(ce.equip_count), 0) as equipment_count
        FROM contractequipments ce
        LEFT JOIN equipments_types et ON ce.equip_type = et.id
        WHERE ce.contract_id = $project_contract_id
        GROUP BY ce.equip_type, et.type
        ORDER BY et.type";
    $equipment_result = mysqli_query($conn, $equipment_details_query);
    $equipment_breakdown = [];
    while ($row = mysqli_fetch_assoc($equipment_result)) {
        $equipment_breakdown[] = [
            'type' => $row['equip_type_name'] ? $row['equip_type_name'] : $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count'])
        ];
    }
    
    // جلب مجموع ساعات عقود الموردين لهذا العقد المحدد (باستثناء العقد الحالي عند التعديل)
    $suppliers_query = "SELECT 
        COALESCE(SUM(forecasted_contracted_hours), 0) as suppliers_contracted_hours
        FROM supplierscontracts 
        WHERE project_contract_id = $project_contract_id";
    
    // استثناء عقد المورد الحالي عند التعديل
    if ($supplier_contract_id > 0) {
        $suppliers_query .= " AND id != $supplier_contract_id";
    }
    
    $suppliers_result = mysqli_query($conn, $suppliers_query);
    $suppliers_data = mysqli_fetch_assoc($suppliers_result);
    
    $contract_hours = floatval($contract_data['contract_total_hours']);
    $suppliers_hours = floatval($suppliers_data['suppliers_contracted_hours']);
    $remaining = $contract_hours - $suppliers_hours;
    
    echo json_encode([
        'success' => true,
        'contract_total_hours' => $contract_hours,
        'equipment_breakdown' => $equipment_breakdown,
        'suppliers_contracted_hours' => $suppliers_hours,
        'remaining_hours' => $remaining
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
