<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    
    // جلب إجمالي ساعات المشروع من جدول contracts
    $project_query = "SELECT 
        COALESCE(SUM(forecasted_contracted_hours), 0) as project_total_hours
        FROM contracts 
        WHERE project = $project_id";
    $project_result = mysqli_query($conn, $project_query);
    $project_data = mysqli_fetch_assoc($project_result);
    
    // جلب تفصيل ساعات المعدات حسب النوع
    $equipment_details_query = "SELECT 
        ce.equip_type,
        COALESCE(SUM(ce.equip_total_contract), 0) as total_hours,
        COUNT(DISTINCT ce.id) as equipment_count
        FROM contractequipments ce
        INNER JOIN contracts c ON ce.contract_id = c.id
        WHERE c.project = $project_id
        GROUP BY ce.equip_type
        ORDER BY ce.equip_type";
    $equipment_result = mysqli_query($conn, $equipment_details_query);
    $equipment_breakdown = [];
    while ($row = mysqli_fetch_assoc($equipment_result)) {
        $equipment_breakdown[] = [
            'type' => $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count'])
        ];
    }
    
    // جلب مجموع ساعات عقود الموردين لهذا المشروع
    $suppliers_query = "SELECT 
        COALESCE(SUM(forecasted_contracted_hours), 0) as suppliers_contracted_hours
        FROM supplierscontracts 
        WHERE project_id = $project_id";
    $suppliers_result = mysqli_query($conn, $suppliers_query);
    $suppliers_data = mysqli_fetch_assoc($suppliers_result);
    
    $project_hours = floatval($project_data['project_total_hours']);
    $suppliers_hours = floatval($suppliers_data['suppliers_contracted_hours']);
    $remaining = $project_hours - $suppliers_hours;
    
    echo json_encode([
        'success' => true,
        'project_total_hours' => $project_hours,
        'equipment_breakdown' => $equipment_breakdown,
        'suppliers_contracted_hours' => $suppliers_hours,
        'remaining_hours' => $remaining
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
