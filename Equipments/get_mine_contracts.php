<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرّف الشركة غير متوفر']);
    exit;
}

$mine_id = isset($_GET['mine_id']) ? intval($_GET['mine_id']) : 0;

if ($mine_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المنجم غير صحيح']);
    exit;
}

// ملاحظة صيانة (هجرة البوابة · 2026-07-13): ميزة «عقود المنجم» معطّلةٌ بنيويًّا —
// جدول mines محذوفٌ وعمود contracts.mine_id غير موجود، فكان الاستعلام الأصلي يفشل
// دائمًا ويعيد «خطأ في الاستعلام» لكل مستدعٍ (Oprators/Reports/الحركة...). أُبدل
// برسالةٍ صادقة بنفس عقد الاستجابة حتى يُعاد تعريف رابط العقد↔المنجم؛ عندها
// تُبنى القراءة عبر بوابة العزل (ems_tenant_db) لا استعلامًا خامًّا.
echo json_encode(['success' => false, 'message' => 'ميزة عقود المنجم معلّقة (بنية المناجم أُزيلت من النظام)']);
?>
