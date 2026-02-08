<?php
session_start();
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit;
}

// جلب بيانات العقد مع عدد المعدات من contractequipments
$contract_query = "SELECT c.*, m.mine_name, m.project_id, p.name as project_name,
                   (SELECT COALESCE(SUM(ce.equip_count), 0) FROM contractequipments ce WHERE ce.contract_id = c.id) as equipment_count
                   FROM contracts c 
                   LEFT JOIN mines m ON c.mine_id = m.id
                   LEFT JOIN project p ON m.project_id = p.id
                   WHERE c.id = $contract_id";
$contract_result = mysqli_query($conn, $contract_query);

if (!$contract_result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($contract_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'العقد غير موجود']);
    exit;
}

$contract = mysqli_fetch_assoc($contract_result);
$mine_id = $contract['mine_id'];
$project_id = $contract['project_id'];

// جلب عقود الموردين المرتبطة بنفس المشروع مع عدد المعدات
$project_id = $contract['project_id'];
$suppliers_query = "SELECT 
    sc.id,
    sc.supplier_id,
    s.name as supplier_name,
    sc.forecasted_contracted_hours,
    (SELECT COALESCE(SUM(sce.equip_count), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count
FROM supplierscontracts sc
LEFT JOIN suppliers s ON sc.supplier_id = s.id
WHERE sc.project_id = $project_id AND sc.status = 1
ORDER BY s.name";

$suppliers_result = mysqli_query($conn, $suppliers_query);
$suppliers = [];
$total_supplier_hours = 0;
$total_supplier_equipment = 0;

if ($suppliers_result) {
    while ($row = mysqli_fetch_assoc($suppliers_result)) {
        // جلب تفاصيل المعدات لهذا المورد
        $equip_details_query = "SELECT 
                                        et.type as type_name,
                                        sce.equip_type,
                                        SUM(sce.equip_count) as total_count,
                                        SUM(sce.equip_total_contract) as total_hours
                                 FROM suppliercontractequipments sce
                                 LEFT JOIN equipments_types et ON sce.equip_type = et.id
                                 WHERE sce.contract_id = " . $row['id'] . "
                                 GROUP BY sce.equip_type, et.type";
        $equip_details_result = mysqli_query($conn, $equip_details_query);
        
        $equipment_breakdown = [];
        $total_added_to_equipments = 0;
        
        while ($equip = mysqli_fetch_assoc($equip_details_result)) {
            $equip_type_id = $equip['equip_type'];
            $contracted_count = intval($equip['total_count']);
            
            // حساب عدد المعدات المضافة فعلياً في جدول equipments لهذا المورد
            $added_query = "SELECT COUNT(*) as added_count 
                           FROM equipments 
                           WHERE suppliers = " . $row['supplier_id'] . " 
                           AND project_id = $project_id
                           AND type = '$equip_type_id'
                           AND status = 1";
            $added_result = mysqli_query($conn, $added_query);
            $added_row = mysqli_fetch_assoc($added_result);
            $added_count = intval($added_row['added_count']);
            $total_added_to_equipments += $added_count;
            
            $remaining = $contracted_count - $added_count;
            
            $equipment_breakdown[] = [
                'type' => $equip['type_name'] ?: 'غير محدد',
                'type_id' => $equip_type_id,
                'count' => $contracted_count,
                'hours' => floatval($equip['total_hours']),
                'added_count' => $added_count,
                'remaining' => $remaining
            ];
        }
        
        $suppliers[] = [
            'id' => $row['id'],
            'supplier_id' => $row['supplier_id'],
            'supplier_name' => $row['supplier_name'] ?: 'غير محدد',
            'hours' => floatval($row['forecasted_contracted_hours']),
            'equipment_count' => intval($row['equipment_count']),
            'added_to_equipments' => $total_added_to_equipments,
            'remaining_to_add' => intval($row['equipment_count']) - $total_added_to_equipments,
            'equipment_breakdown' => $equipment_breakdown
        ];
        $total_supplier_hours += floatval($row['forecasted_contracted_hours']);
        $total_supplier_equipment += intval($row['equipment_count']);
    }
}

$response = [
    'success' => true,
    'contract' => [
        'contract_date' => $contract['contract_signing_date'],
        'duration' => $contract['contract_duration_months'] . ' شهر',
        'total_hours' => floatval($contract['forecasted_contracted_hours']),
        'equipment_count' => intval($contract['equipment_count']),
        'project_name' => $contract['project_name'],
        'mine_name' => $contract['mine_name']
    ],
    'suppliers' => $suppliers,
    'summary' => [
        'suppliers_count' => count($suppliers),
        'total_supplier_hours' => $total_supplier_hours,
        'total_supplier_equipment' => $total_supplier_equipment
    ]
];

echo json_encode($response);
?>
