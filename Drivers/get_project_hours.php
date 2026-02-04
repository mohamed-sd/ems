<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_contract_id'])) {
    $project_contract_id = intval($_POST['project_contract_id']); // معرف العقد من جدول contracts
    $driver_contract_id = isset($_POST['driver_contract_id']) ? intval($_POST['driver_contract_id']) : 0;
    
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
        equip_type,
        COALESCE(SUM(equip_total_contract), 0) as total_hours,
        COUNT(DISTINCT id) as equipment_count
        FROM contractequipments
        WHERE contract_id = $project_contract_id
        GROUP BY equip_type
        ORDER BY equip_type";
    $equipment_result = mysqli_query($conn, $equipment_details_query);
    $equipment_breakdown = [];
    while ($row = mysqli_fetch_assoc($equipment_result)) {
        $equipment_breakdown[] = [
            'type' => $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count'])
        ];
    }
    
    // جلب مجموع ساعات عقود السائقين لهذا العقد المحدد (باستثناء العقد الحالي عند التعديل)
    $drivers_query = "SELECT 
        COALESCE(SUM(forecasted_contracted_hours), 0) as drivers_contracted_hours
        FROM drivercontracts 
        WHERE project_contract_id = $project_contract_id";
    
    // استثناء عقد السائق الحالي عند التعديل
    if ($driver_contract_id > 0) {
        $drivers_query .= " AND id != $driver_contract_id";
    }
    
    $drivers_result = mysqli_query($conn, $drivers_query);
    $drivers_data = mysqli_fetch_assoc($drivers_result);
    
    $contract_hours = floatval($contract_data['contract_total_hours']);
    $drivers_hours = floatval($drivers_data['drivers_contracted_hours']);
    $remaining = $contract_hours - $drivers_hours;
    
    echo json_encode([
        'success' => true,
        'contract_total_hours' => $contract_hours,
        'equipment_breakdown' => $equipment_breakdown,
        'drivers_contracted_hours' => $drivers_hours,
        'remaining_hours' => $remaining
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
