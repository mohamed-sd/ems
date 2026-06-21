<?php
/**
 * get_project_equipment.php — المعدات «تحت الصيانة» التابعة لمشروع معيّن (لقائمة المعدات المتسلسلة).
 * يُستدعى عبر XHR فقط (حارس config.php يفرض X-Requested-With + جلسة صالحة).
 * المدخلات: project_id (إجباري) + include_id (اختياري، يضمن ظهور معدة محدّدة في فورم التحرير).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(array('equipment' => array()));
    exit;
}

require_once '../config.php';
require_once __DIR__ . '/mnt_helpers.php';

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$include_id = isset($_GET['include_id']) ? intval($_GET['include_id']) : 0;
$mode       = isset($_GET['mode']) ? $_GET['mode'] : '';

// mode=all ⇒ كل معدات المشروع (للتفتيش)؛ الافتراضي ⇒ معدات «تحت الصيانة» فقط (لأوامر الصيانة).
$list = ($company_id > 0 && $project_id > 0)
    ? ($mode === 'all'
        ? mnt_all_equipment_in_project($conn, $company_id, $project_id, $include_id)
        : mnt_maint_equipment_in_project($conn, $company_id, $project_id, $include_id))
    : array();

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('equipment' => $list), JSON_UNESCAPED_UNICODE);
exit;
