<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'Employees/employee_contracts.php', 'view');


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_contract_id'])) {
    $project_contract_id = intval($_POST['project_contract_id']); // معرف العقد من جدول contracts
    $driver_contract_id = isset($_POST['driver_contract_id']) ? intval($_POST['driver_contract_id']) : 0;

    // العزل عبر البوابة — القراءات الثلاث كانت بلا عزل شركةٍ إطلاقًا (تسرّبٌ كامنٌ أُغلق)
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $gph_gate = ($role === '-1') ? ems_tenant_db()->forAllTenants('project hours super') : ems_tenant_db();

    // جلب إجمالي ساعات العقد المحدد من جدول contracts
    try {
        $contract_data = $gph_gate->selectOne('contracts', array(
            'columns' => array('forecasted_contracted_hours', 'project_id'),
            'where'   => array('id' => $project_contract_id),
        ));
    } catch (\Throwable $t) {
        $contract_data = null;
    }

    if (!$contract_data) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    // تفصيل ساعات المعدات حسب النوع (تجميع scopedQuery — بعد تعبئة M4)
    $equipment_breakdown = [];
    try {
        $eq_rows = $gph_gate->scopedQuery(array(
            'scope' => array('ce' => 'contractequipments'),
        ), "SELECT
        ce.equip_type,
        COALESCE(SUM(ce.equip_total_contract), 0) as total_hours,
        COUNT(DISTINCT ce.id) as equipment_count
        FROM contractequipments ce
        WHERE {TENANT_SCOPE} AND ce.contract_id = ?
        GROUP BY ce.equip_type
        ORDER BY ce.equip_type", array($project_contract_id));
    } catch (\Throwable $t) {
        $eq_rows = array();
    }
    foreach ($eq_rows as $row) {
        $equipment_breakdown[] = [
            'type' => $row['equip_type'],
            'hours' => floatval($row['total_hours']),
            'count' => intval($row['equipment_count'])
        ];
    }

    // مجموع ساعات عقود السائقين (باستثناء العقد الحالي عند التعديل)
    $drv_extra = ''; $drv_params = array($project_contract_id);
    if ($driver_contract_id > 0) {
        $drv_extra = " AND dc.id != ?";
        $drv_params[] = $driver_contract_id;
    }
    try {
        $drv_rows = $gph_gate->scopedQuery(array(
            'scope' => array('dc' => 'drivercontracts'),
        ), "SELECT COALESCE(SUM(dc.forecasted_contracted_hours), 0) as drivers_contracted_hours
        FROM drivercontracts dc
        WHERE {TENANT_SCOPE} AND dc.project_contract_id = ?$drv_extra", $drv_params);
    } catch (\Throwable $t) {
        $drv_rows = array(array('drivers_contracted_hours' => 0));
    }
    $drivers_data = $drv_rows[0] ?? array('drivers_contracted_hours' => 0);

    $contract_hours = floatval($contract_data['forecasted_contracted_hours']);
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
