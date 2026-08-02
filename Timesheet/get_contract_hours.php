<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

include '../config.php';
header('Content-Type: application/json; charset=utf-8');

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['success' => false, 'message' => 'بيئة شركة غير صالحة']);
    exit;
}

$operation_id = isset($_GET['operation_id']) ? intval($_GET['operation_id']) : 0;
if ($operation_id <= 0) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['success' => false, 'message' => 'رقم تشغيل غير صحيح']);
    exit;
}

// العزل عبر بوابة المستأجر — نطاق EXISTS القديم استُبدل بحقن company_id المباشر؛
// والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$gch_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet contract-hours super') : ems_tenant_db();
$op = null;
try {
    $op = $gch_gate->selectOne('operations', array(
        'columns' => array('shift_hours', 'shift_type', 'contract_id', 'equipment_type'),
        'where'   => array('id' => $operation_id),
    ));
} catch (\Throwable $t) { error_log('get_contract_hours: ' . $t->getMessage()); }

// ── وحدة الفوترة من عقد العميل (D02 §2.6) ──────────────────────────────────
// الشاشة تسأل ما يسأله المحرّك بالضبط: سطرُ معدة العقد بمفتاح (العقد + نوع
// المعدة)، وMIN(id) عند التعدد — حتميةً واحدة. ولو اختلف مصدرُ الشاشة عن مصدر
// المحرّك لظهر للمستخدم حقلٌ لا يقرؤه التسعير، وهي أسوأ من غياب الحقل.
$billing = array('unit' => null, 'label' => '', 'field' => null, 'known' => false);
if ($op && !empty($op['contract_id'])) {
    try {
        require_once __DIR__ . '/../app/Services/EffectFanout.php';
        $ceRows = $gch_gate->scopedQuery(
            array('scope' => array('ce' => 'contractequipments')),
            "SELECT ce.equip_unit FROM contractequipments ce
              WHERE {TENANT_SCOPE} AND ce.contract_id = ? AND ce.equip_type = ?
              ORDER BY ce.id ASC LIMIT 1",
            array(intval($op['contract_id']), strval($op['equipment_type']))
        );
        if ($ceRows) {
            $lbl = trim((string) $ceRows[0]['equip_unit']);
            $map = \App\Services\EffectFanout::CONTRACT_UNIT;
            $billing['label'] = ($lbl !== '' ? $lbl : 'ساعة');
            if (isset($map[$lbl])) {
                $billing['unit']  = $map[$lbl];
                $billing['known'] = true;
                // العمود الذي يقرؤه المحرّك لهذه الوحدة — مصدرٌ واحدٌ للاسمين
                $cols = array('hour' => 'executed_hours', 'ton' => 'tons_count', 'meter' => 'meters_count');
                $billing['field'] = isset($cols[$billing['unit']]) ? $cols[$billing['unit']] : null;
            }
        }
    } catch (\Throwable $t) { error_log('get_contract_hours billing: ' . $t->getMessage()); }
}

while (ob_get_level()) {
    ob_end_clean();
}

if (!$op) {
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم العثور على التشغيل',
        'shift_hours' => 0,
        'shift_type' => 'B',
        'allowed_shifts' => ['D', 'N']
    ]);
    exit;
}
$shift_hours = isset($op['shift_hours']) ? floatval($op['shift_hours']) : 0;
$shift_type = isset($op['shift_type']) ? strtoupper(trim((string) $op['shift_type'])) : 'B';
if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
    $shift_type = 'B';
}

$allowed_shifts = ['D', 'N'];
if ($shift_type === 'D') {
    $allowed_shifts = ['D'];
} elseif ($shift_type === 'N') {
    $allowed_shifts = ['N'];
}

echo json_encode([
    'success' => true,
    'shift_hours' => $shift_hours,
    'shift_type' => $shift_type,
    'allowed_shifts' => $allowed_shifts,
    'billing' => $billing
]);
exit;
?>
