<?php
session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    exit;
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    while (ob_get_level()) ob_end_clean();
    exit;
}

// العزل عبر بوابة المستأجر — نطاق EXISTS القديم استُبدل بحقن company_id المباشر؛
// والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$gdr_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet drivers endpoint super') : ems_tenant_db();

if (isset($_GET['operation_id'])) {
    $operation_id = intval($_GET['operation_id']);
    $shift_type = isset($_GET['shift']) ? trim($_GET['shift']) : '';

    // جلب الآلية من جدول التشغيل ضمن نطاق الشركة (البوابة تحقن العزل)
    $op = null;
    try {
        $op = $gdr_gate->selectOne('operations', array('columns' => array('equipment'), 'where' => array('id' => $operation_id)));
    } catch (\Throwable $t) { error_log('get_drivers op: ' . $t->getMessage()); }
    if (!$op || !isset($op['equipment'])) {
        while (ob_get_level()) ob_end_clean();
        echo "<option value=''>-- اختر السائق --</option>";
        exit;
    }
    $equipment_id = intval($op['equipment']);

    $gdr_filter = '';
    $gdr_params = array($equipment_id);
    if ($shift_type === 'D' || $shift_type === 'N') {
        $gdr_filter .= " AND (ed.shift_type = 'B' OR ed.shift_type = ?) ";
        $gdr_params[] = $shift_type;
    }

    // جلب السائقين المرتبطين بهذه الآلية (النشطين فقط) — قصرًا على أنواع الموظفين المشغّلة
    $result = array();
    try {
        $result = $gdr_gate->scopedQuery(
            array('scope' => array('ed' => 'equipment_drivers', 'd' => 'employees')),
            "SELECT d.id, d.name
            FROM equipment_drivers ed
            JOIN employees d ON ed.employee_id = d.id
            WHERE ed.equipment_id = ?
              AND ed.status = 1$gdr_filter AND d.status = 1" . ems_operation_types_in_sql($conn, 'd') . " AND {TENANT_SCOPE}", $gdr_params);
    } catch (\Throwable $t) { error_log('get_drivers main: ' . $t->getMessage()); }

    while (ob_get_level()) ob_end_clean();
    echo "<option value=''>-- اختر السائق --</option>";
    foreach ($result as $row) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
}
?>
