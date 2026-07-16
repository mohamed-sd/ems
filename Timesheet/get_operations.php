<?php
session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    exit;
}

include '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    while (ob_get_level()) ob_end_clean();
    exit;
}

// العزل عبر بوابة المستأجر — نطاق EXISTS القديم (شركة منشئ المشروع/العميل) استُبدل بحقن
// company_id المباشر على o+e (أقوى وأدق)؛ والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$gop_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet ops endpoint super') : ems_tenant_db();

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$shift_type = isset($_GET['shift']) ? trim($_GET['shift']) : '';
if ($type !== '1' && $type !== '2' && $type !== '3') {
    while (ob_get_level()) ob_end_clean();
    echo "<option value=''>-- اختر نوع الآلية أولاً --</option>";
    exit;
}

if ($session_project_id <= 0 && !$is_super_admin) {
    try {
        $proj = $gop_gate->selectOne('project', array('columns' => array('id'), 'where' => array('status' => 1)));
        if ($proj) { $session_project_id = intval($proj['id']); }
    } catch (\Throwable $t) { error_log('get_operations project fallback: ' . $t->getMessage()); }
}

$gop_filter = '';
$gop_params = array($type);
if (!$is_super_admin && $session_project_id > 0) {
    $gop_filter .= " AND o.project_id = ? ";
    $gop_params[] = $session_project_id;
}
if ($shift_type === 'D' || $shift_type === 'N') {
    $gop_filter .= " AND (o.shift_type = 'B' OR o.shift_type = ?) ";
    $gop_params[] = $shift_type;
}

$result = array();
try {
    $result = $gop_gate->scopedQuery(
        array('scope' => array('o' => 'operations', 'e' => 'equipments')),
        "SELECT o.id, e.code, e.name
          FROM operations o
          JOIN equipments e ON o.equipment = e.id
          WHERE o.status = '1' AND o.equipment_category = 'أساسي' AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE ? AND status = 'active')$gop_filter AND {TENANT_SCOPE}
          ORDER BY e.code ASC, e.name ASC", $gop_params);
} catch (\Throwable $t) { error_log('get_operations main: ' . $t->getMessage()); }

while (ob_get_level()) ob_end_clean();
echo "<option value=''>-- اختر الآلية --</option>";
foreach ($result as $row) {
    $id = intval($row['id']);
    $label = htmlspecialchars($row['code'] . ' - ' . $row['name']);
    echo "<option value='{$id}'>{$label}</option>";
}
