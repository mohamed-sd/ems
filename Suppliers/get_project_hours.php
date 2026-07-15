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

    // العزل عبر البوابة — القراءات الخمس كانت بلا عزل شركةٍ إطلاقًا (تسرّبٌ كامنٌ أُغلق)
    $sph_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $sph_gate = ($sph_role === '-1') ? ems_tenant_db()->forAllTenants('supplier project hours super') : ems_tenant_db();

    // جلب إجمالي ساعات العقد المحدد من جدول contracts
    try {
        $contract_rows = $sph_gate->scopedQuery(array(
            'scope' => array('c' => 'contracts'),
        ), "SELECT
            c.forecasted_contracted_hours as contract_total_hours,
            c.project_id
            FROM contracts c
            WHERE {TENANT_SCOPE} AND c.id = ?
            LIMIT 1", array($project_contract_id));
    } catch (\Throwable $t) { $contract_rows = array(); }
    $contract_data = !empty($contract_rows) ? $contract_rows[0] : null;

    if (!$contract_data) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    // جلب تفصيل ساعات المعدات حسب النوع للعقد المحدد (equipments_types جدول عام)
    try {
        $eq_rows = $sph_gate->scopedQuery(array(
            'scope' => array('ce' => 'contractequipments'),
        ), "SELECT
            ce.equip_type,
            et.type AS equip_type_name,
            COALESCE(SUM(ce.equip_total_contract), 0) as total_hours,
            COALESCE(SUM(ce.equip_count), 0) as equipment_count,
            COALESCE(SUM(ce.equip_count_basic), 0) as equipment_count_basic,
            COALESCE(SUM(ce.equip_count_backup), 0) as equipment_count_backup
            FROM contractequipments ce
            LEFT JOIN equipments_types et ON ce.equip_type = et.id
            WHERE {TENANT_SCOPE} AND ce.contract_id = ?
            GROUP BY ce.equip_type, et.type
            ORDER BY et.type", array($project_contract_id));
    } catch (\Throwable $t) { $eq_rows = array(); }
    $equipment_breakdown = [];
    foreach ($eq_rows as $row) {
        $equipment_breakdown[] = [
            'type_id' => $row['equip_type'],
            'type' => $row['equip_type_name'] ? $row['equip_type_name'] : $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count']),
            'count_basic' => intval($row['equipment_count_basic']) ?: 0,
            'count_backup' => intval($row['equipment_count_backup']) ?: 0
        ];
    }

    // جلب مجموع ساعات عقود الموردين لهذا العقد (باستثناء العقد الحالي عند التعديل)
    $sph_extra = ''; $sph_params = array($project_contract_id);
    if ($supplier_contract_id > 0) {
        $sph_extra = " AND sc.id != ?";
        $sph_params[] = $supplier_contract_id;
    }
    try {
        $sup_rows = $sph_gate->scopedQuery(array(
            'scope' => array('sc' => 'supplierscontracts'),
        ), "SELECT
            COALESCE(SUM(sc.forecasted_contracted_hours), 0) as suppliers_contracted_hours
            FROM supplierscontracts sc
            WHERE {TENANT_SCOPE} AND sc.project_contract_id = ?$sph_extra", $sph_params);
    } catch (\Throwable $t) { $sup_rows = array(); }
    $suppliers_data = !empty($sup_rows) ? $sup_rows[0] : null;

    // جلب تفصيل ساعات الموردين حسب نوع المعدة
    try {
        $sbd_rows = $sph_gate->scopedQuery(array(
            'scope' => array('sc' => 'supplierscontracts', 'sce' => 'suppliercontractequipments'),
        ), "SELECT
            sce.equip_type,
            et.type AS equip_type_name,
            COALESCE(SUM(sce.equip_total_contract), 0) as suppliers_hours,
            COALESCE(SUM(sce.equip_count), 0) as equipment_count
            FROM supplierscontracts sc
            JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
            LEFT JOIN equipments_types et ON sce.equip_type = et.id
            WHERE {TENANT_SCOPE} AND sc.project_contract_id = ?$sph_extra
            GROUP BY sce.equip_type, et.type ORDER BY et.type", $sph_params);
    } catch (\Throwable $t) { $sbd_rows = array(); }
    $suppliers_by_type = [];
    foreach ($sbd_rows as $row) {
        $type_key = $row['equip_type'];
        $suppliers_by_type[$type_key] = floatval($row['suppliers_hours']);
    }

    // جلب تفصيل الموردين مع ساعاتهم التعاقدية
    try {
        $slist_rows = $sph_gate->scopedQuery(array(
            'scope' => array('sc' => 'supplierscontracts', 's' => 'suppliers'),
        ), "SELECT
            sc.id,
            s.name AS supplier_name,
            sc.forecasted_contracted_hours,
            sc.contract_signing_date,
            sc.actual_start,
            sc.actual_end
            FROM supplierscontracts sc
            JOIN suppliers s ON sc.supplier_id = s.id
            WHERE {TENANT_SCOPE} AND sc.project_contract_id = ?$sph_extra
            ORDER BY s.name ASC", $sph_params);
    } catch (\Throwable $t) { $slist_rows = array(); }
    $suppliers_list = [];
    foreach ($slist_rows as $row) {
        $suppliers_list[] = [
            'id' => intval($row['id']),
            'name' => $row['supplier_name'],
            'hours' => floatval($row['forecasted_contracted_hours']),
            'contract_date' => $row['contract_signing_date'],
            'start_date' => $row['actual_start'],
            'end_date' => $row['actual_end']
        ];
    }

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
