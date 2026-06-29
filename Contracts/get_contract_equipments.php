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

// عزل الشركة: تأكّد أن العقد الأب يتبع شركة المستخدم قبل إرجاع معداته (المدير الأعلى -1 معفى).
$_is_super = (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1');
$_cid = intval($_SESSION['user']['company_id'] ?? 0);
if (!$_is_super && db_table_has_column($conn, 'contracts', 'company_id')) {
    $_own = mysqli_query($conn, "SELECT 1 FROM contracts WHERE id = $contract_id AND company_id = $_cid LIMIT 1");
    if (!$_own || !mysqli_num_rows($_own)) {
        echo json_encode(['success' => false, 'message' => 'خارج نطاق الشركة']);
        exit();
    }
}

// جلب معدات العقد مع جميع الحقول المطلوبة
$sql = "SELECT
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
WHERE ce.contract_id = $contract_id
ORDER BY ce.id ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]);
    exit();
}

$equipments = [];
while ($row = mysqli_fetch_assoc($result)) {
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
