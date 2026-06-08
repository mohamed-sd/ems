<?php
session_start();

while (ob_get_level()) ob_end_clean();

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
        c.project_id
        FROM contracts c
        WHERE c.id = $project_contract_id
        LIMIT 1";
    $contract_result = mysqli_query($conn, $contract_query);
    $contract_data = $contract_result ? mysqli_fetch_assoc($contract_result) : null;

    if (!$contract_data) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    // جلب تفصيل ساعات المعدات حسب النوع للعقد المحدد
    $equipment_details_query = "SELECT
        ce.equip_type,
        et.type AS equip_type_name,
        COALESCE(SUM(ce.equip_total_contract), 0) as total_hours,
        COALESCE(SUM(ce.equip_count), 0) as equipment_count,
        COALESCE(SUM(ce.equip_count_basic), 0) as equipment_count_basic,
        COALESCE(SUM(ce.equip_count_backup), 0) as equipment_count_backup
        FROM contractequipments ce
        LEFT JOIN equipments_types et ON ce.equip_type = et.id
        WHERE ce.contract_id = $project_contract_id
        GROUP BY ce.equip_type, et.type
        ORDER BY et.type";
    $equipment_result = mysqli_query($conn, $equipment_details_query);
    $equipment_breakdown = [];
    if ($equipment_result) { while ($row = mysqli_fetch_assoc($equipment_result)) {
        $equipment_breakdown[] = [
            'type_id' => $row['equip_type'],
            'type' => $row['equip_type_name'] ? $row['equip_type_name'] : $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count']),
            'count_basic' => intval($row['equipment_count_basic']) ?: 0,
            'count_backup' => intval($row['equipment_count_backup']) ?: 0
        ];
    } }

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
    $suppliers_data = $suppliers_result ? mysqli_fetch_assoc($suppliers_result) : null;

    // جلب تفصيل ساعات الموردين حسب نوع المعدة
    $suppliers_breakdown_query = "SELECT
        sce.equip_type,
        et.type AS equip_type_name,
        COALESCE(SUM(sce.equip_total_contract), 0) as suppliers_hours,
        COALESCE(SUM(sce.equip_count), 0) as equipment_count
        FROM supplierscontracts sc
        JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
        LEFT JOIN equipments_types et ON sce.equip_type = et.id
        WHERE sc.project_contract_id = $project_contract_id";

    if ($supplier_contract_id > 0) {
        $suppliers_breakdown_query .= " AND sc.id != $supplier_contract_id";
    }

    $suppliers_breakdown_query .= " GROUP BY sce.equip_type, et.type ORDER BY et.type";

    $suppliers_breakdown_result = mysqli_query($conn, $suppliers_breakdown_query);
    $suppliers_by_type = [];
    if ($suppliers_breakdown_result) { while ($row = mysqli_fetch_assoc($suppliers_breakdown_result)) {
        $type_key = $row['equip_type'];
        $suppliers_by_type[$type_key] = floatval($row['suppliers_hours']);
    } }

    // جلب تفصيل الموردين مع ساعاتهم التعاقدية
    $suppliers_list_query = "SELECT
        sc.id,
        s.name AS supplier_name,
        sc.forecasted_contracted_hours,
        sc.contract_signing_date,
        sc.actual_start,
        sc.actual_end
        FROM supplierscontracts sc
        JOIN suppliers s ON sc.supplier_id = s.id
        WHERE sc.project_contract_id = $project_contract_id";

    if ($supplier_contract_id > 0) {
        $suppliers_list_query .= " AND sc.id != $supplier_contract_id";
    }

    $suppliers_list_query .= " ORDER BY s.name ASC";

    $suppliers_list_result = mysqli_query($conn, $suppliers_list_query);
    $suppliers_list = [];
    if ($suppliers_list_result) { while ($row = mysqli_fetch_assoc($suppliers_list_result)) {
        $suppliers_list[] = [
            'id' => intval($row['id']),
            'name' => $row['supplier_name'],
            'hours' => floatval($row['forecasted_contracted_hours']),
            'contract_date' => $row['contract_signing_date'],
            'start_date' => $row['actual_start'],
            'end_date' => $row['actual_end']
        ];
    } }

    // حساب الساعات المتبقية لكل نوع معدة
    $remaining_breakdown = [];
    foreach ($equipment_breakdown as $equip) {
        $type_id = $equip['type_id'];
        $type_name = $equip['type'];
        $total_hours = $equip['hours'];
        $suppliers_hours_for_type = isset($suppliers_by_type[$type_id]) ? $suppliers_by_type[$type_id] : 0;
        $remaining_hours_for_type = $total_hours - $suppliers_hours_for_type;

        // إضافة التفصيل فقط إذا كانت هناك ساعات متبقية أو متعاقد عليها
        if ($total_hours > 0) {
            $remaining_breakdown[] = [
                'type' => $type_name,
                'total_hours' => $total_hours,
                'suppliers_hours' => $suppliers_hours_for_type,
                'remaining_hours' => $remaining_hours_for_type,
                'count' => $equip['count']
            ];
        }
    }

    $contract_hours = floatval($contract_data['contract_total_hours']);
    $suppliers_hours = floatval($suppliers_data['suppliers_contracted_hours']);
    $remaining = $contract_hours - $suppliers_hours;

    echo json_encode([
        'success' => true,
        'contract_total_hours' => $contract_hours,
        'equipment_breakdown' => $equipment_breakdown,
        'suppliers_contracted_hours' => $suppliers_hours,
        'suppliers_list' => $suppliers_list,
        'remaining_hours' => $remaining,
        'remaining_breakdown' => $remaining_breakdown
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
