<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// H-20: نقطةٌ تغذّي نموذجَ إنشاء عقدِ مورد (فعلُ كتابة) — بوابةُ المشرف قراءةٌ
// حصرًا فتُحجب عنها كليًّا (لا نطاقَ يبرّرها)
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
if (\App\Services\Portal\SupplierPortalGuard::isRestricted($_SESSION['user'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'خارج نطاق بوابة مشرف المورد'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // project_id مباشرةً؛ مسار mine_id القديم ميّتٌ بنيويًّا (جدول mines أُزيل من
    // النظام — سلوك الأصل الفعلي: فشل الاستعلام بصمت ⇒ project_id=0 ⇒
    // «Invalid request») ويُحفَظ حرفيًّا هنا دون استعلامٍ على جدولٍ غير موجود.
    if (isset($_POST['project_id']) && intval($_POST['project_id']) > 0) {
        $project_id = intval($_POST['project_id']);
    } else {
        $project_id = 0;
    }

    if ($project_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // جلب عقود المشروع المحدد — العزل عبر البوابة (الأصل كان بلا عزل شركةٍ إطلاقًا)
    $gmc_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $gmc_gate = ($gmc_role === '-1') ? ems_tenant_db()->forAllTenants('mine contracts super') : ems_tenant_db();
    try {
        $contracts_rows = $gmc_gate->scopedQuery(array(
            'scope' => array('c' => 'contracts'),
        ), "SELECT
            c.id,
            CONCAT('عقد رقم ', c.id, ' - ', DATE_FORMAT(c.actual_start, '%Y/%m/%d'), ' - ', c.forecasted_contracted_hours, ' ساعة') as display_name,
            c.forecasted_contracted_hours
            FROM contracts c
            WHERE {TENANT_SCOPE} AND c.project_id = ? AND c.status = 1
            ORDER BY c.actual_start DESC", array($project_id));
    } catch (\Throwable $t) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }

    $contracts = [];
    foreach ($contracts_rows as $row) {
        $contracts[] = [
            'id' => intval($row['id']),
            'display_name' => $row['display_name'],
            'hours' => floatval($row['forecasted_contracted_hours'])
        ];
    }

    echo json_encode([
        'success' => true,
        'contracts' => $contracts
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
