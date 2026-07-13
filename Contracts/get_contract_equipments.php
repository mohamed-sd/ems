<?php
session_start();

while (ob_get_level()) ob_end_clean();

// تعيين نوع المحتوى كـ JSON
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

include '../config.php';

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if (!$contract_id) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit();
}

// عزل الشركة عبر البوابة: العقد الأب أولًا ثم سطوره (المدير الأعلى -1 عابرٌ مُسجَّل).
$_is_super = (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1');
$gce_gate = $_is_super ? ems_tenant_db()->forAllTenants('contract equipments super view') : ems_tenant_db();
try {
    $_own = $gce_gate->selectOne('contracts', array('columns' => array('id'), 'where' => array('id' => $contract_id), 'includeDeleted' => true));
} catch (\Throwable $t) {
    $_own = null;
}
if ($_own === null) {
    echo json_encode(['success' => false, 'message' => 'خارج نطاق الشركة']);
    exit();
}

// جلب معدات العقد مع جميع الحقول المطلوبة (النطاق ce بعد تعبئة M4؛ et كتالوج عام)
try {
    $gce_rows = $gce_gate->scopedQuery(array(
        'scope' => array('ce' => 'contractequipments'),
    ), "SELECT
    ce.equip_type,
    et.type AS equip_type_name,
    ce.equip_size,
    ce.equip_count,
    ce.shift_hours,
    ce.equip_total_month,
    ce.equip_total_contract,
    ce.equip_monthly_target
FROM contractequipments ce
LEFT JOIN equipments_types et ON ce.equip_type = et.id
WHERE {TENANT_SCOPE} AND ce.contract_id = ?
ORDER BY ce.id ASC", array($contract_id));
} catch (\Throwable $t) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام']);
    exit();
}

$equipments = [];
foreach ($gce_rows as $row) {
    // التأكد من وجود جميع الحقول مع قيم افتراضية
    $equipments[] = [
        'equip_type' => $row['equip_type'] ?? '',
        'equip_type_name' => $row['equip_type_name'] ?? '',
        'equip_size' => $row['equip_size'] ?? 0,
        'equip_count' => $row['equip_count'] ?? 0,
        'shift_hours' => $row['shift_hours'] ?? 0,
        'equip_total_month' => $row['equip_total_month'] ?? 0,
        'equip_total_contract' => $row['equip_total_contract'] ?? 0,
        'equip_monthly_target' => $row['equip_monthly_target'] ?? 0
    ];
}

echo json_encode(['success' => true, 'equipments' => $equipments]);
exit;
