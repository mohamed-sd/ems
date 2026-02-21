<?php
session_start();
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_mine_id = $is_role10 ? intval($_SESSION['user']['mine_id']) : 0;
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if ($is_role10 && $user_contract_id > 0 && $contract_id !== $user_contract_id) {
    echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا العقد']);
    exit;
}

if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit;
}

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
$contract_mine_id = intval($contract['mine_id']);

if ($is_role10 && $user_mine_id > 0 && $contract_mine_id !== $user_mine_id) {
    echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا المنجم']);
    exit;
}
$project_id = intval($contract['project_id']);

$active_equipment_query = "SELECT COUNT(*) as active_count
                          FROM operations o
                          JOIN equipments e ON o.equipment = e.id
                          WHERE o.status = 1
                          AND e.status = 1
                          AND o.contract_id = $contract_id";
$active_equipment_result = mysqli_query($conn, $active_equipment_query);
$active_equipment_count = 0;
if ($active_equipment_result && mysqli_num_rows($active_equipment_result) > 0) {
    $active_equipment_row = mysqli_fetch_assoc($active_equipment_result);
    $active_equipment_count = intval($active_equipment_row['active_count']);
}

$suppliers_query = "SELECT 
    sc.id,
    sc.supplier_id,
    s.name as supplier_name,
    sc.forecasted_contracted_hours,
    (SELECT COALESCE(SUM(sce.equip_count), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count,
    (SELECT COALESCE(SUM(sce.equip_count_basic), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_basic,
    (SELECT COALESCE(SUM(sce.equip_count_backup), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_backup
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
        $total_added_to_operations = 0;

        while ($equip = mysqli_fetch_assoc($equip_details_result)) {
            $equip_type_id = intval($equip['equip_type']);
            $contracted_count = intval($equip['total_count']);

            $added_query = "SELECT COUNT(*) as added_count
                           FROM operations o
                           JOIN equipments e ON o.equipment = e.id
                           WHERE o.status = 1
                           AND e.status = 1
                           AND o.project_id = $project_id
                           AND o.supplier_id = " . intval($row['supplier_id']) . "
                           AND (o.equipment_type = $equip_type_id OR e.type = $equip_type_id)";
            $added_result = mysqli_query($conn, $added_query);
            $added_row = mysqli_fetch_assoc($added_result);
            $added_count = intval($added_row['added_count']);
            $total_added_to_operations += $added_count;

            $remaining = $contracted_count - $added_count;
            if ($remaining < 0) {
                $remaining = 0;
            }

            $equipment_breakdown[] = [
                'type' => $equip['type_name'] ?: 'غير محدد',
                'type_id' => $equip_type_id,
                'count' => $contracted_count,
                'hours' => floatval($equip['total_hours']),
                'added_count' => $added_count,
                'remaining' => $remaining
            ];
        }

        $equipment_count = intval($row['equipment_count']);
        $remaining_to_add = $equipment_count - $total_added_to_operations;
        if ($remaining_to_add < 0) {
            $remaining_to_add = 0;
        }

        $suppliers[] = [
            'id' => $row['id'],
            'supplier_id' => $row['supplier_id'],
            'supplier_name' => $row['supplier_name'] ?: 'غير محدد',
            'hours' => floatval($row['forecasted_contracted_hours']),
            'equipment_count' => $equipment_count,
            'equipment_count_basic' => intval($row['equipment_count_basic']),
            'equipment_count_backup' => intval($row['equipment_count_backup']),
            'added_to_equipments' => $total_added_to_operations,
            'remaining_to_add' => $remaining_to_add,
            'equipment_breakdown' => $equipment_breakdown
        ];
        $total_supplier_hours += floatval($row['forecasted_contracted_hours']);
        $total_supplier_equipment += $equipment_count;
    }
}

$response = [
    'success' => true,
    'contract' => [
        'contract_date' => $contract['contract_signing_date'],
        'duration' => $contract['contract_duration_months'] . ' شهر',
        'total_hours' => floatval($contract['forecasted_contracted_hours']),
        'equipment_count' => $active_equipment_count,
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
