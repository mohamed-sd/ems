<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

$roleId     = intval($_POST['role_id'] ?? 0);
$reportCode = mysqli_real_escape_string($conn, trim($_POST['report_code'] ?? ''));
$action     = trim($_POST['action'] ?? '');

if (!$roleId || !$reportCode || !in_array($action, ['enable', 'disable'])) {
    die(json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']));
}

if ($action === 'enable') {
    // كتابة مرجعٍ عام عبر بوابة المزوّد (سياقها المدير الأعلى — الكتابة الشرعية للعقد):
    // فحصٌ ثم إدراج يكافئ INSERT IGNORE الأصلية (تفعيلٌ idempotent).
    try {
        $upq_pg = ems_platform_db();
        $upq_exists = $upq_pg->selectOne('report_role_permissions', array('columns' => array('role_id'),
            'where' => array('role_id' => $roleId, 'report_code' => $reportCode)));
        if ($upq_exists === null) {
            $upq_pg->insert('report_role_permissions', array('role_id' => $roleId, 'report_code' => $reportCode));
        }
        echo json_encode(['success' => true, 'message' => 'تم تفعيل الصلاحية']);
    } catch (\Throwable $t) {
        error_log('admin/update_permission_quick enable: ' . $t->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
    }
} else {
    // [مُستثنى موثَّق — حذف صف مرجعٍ عام] deleteRow حكرٌ تعاقديًا على جداول المستأجر،
    // ولا قناة حذفٍ للمراجع العامة بعد — يبقى الحذف خامًا بهوية الكونسول المصادَق عليها
    // حتى تُعرَّف قناة deleteGlobal (مرشَّحة مع قرار تحصين RBAC).
    $result = mysqli_query($conn, "DELETE FROM report_role_permissions WHERE role_id = $roleId AND report_code = '$reportCode'");
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم إلغاء الصلاحية']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)]);
    }
}
exit;
