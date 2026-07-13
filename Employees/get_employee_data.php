<?php
// بدء output buffering من البداية لمنع أي output غير متوقع
ob_start();

session_start();

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

include '../config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'معرف المشغل مفقود'], JSON_UNESCAPED_UNICODE));
}

$employee_id = intval($_GET['id']);

// التحقق من صلاحيات الشركة وعزل البيانات
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// العزل عبر البوابة — يستبدل فحص العمود وسُلَّم created_by الاحتياطي
$emp_data_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('employee data super view') : ems_tenant_db();
try {
    $driver = $emp_data_gate->selectOne('employees', array('where' => array('id' => $employee_id)));
} catch (\Throwable $t) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE));
}

if ($driver !== null) {

    // تنظيف output buffer وطباعة JSON فقط
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => true,
        'driver' => $driver
    ], JSON_UNESCAPED_UNICODE));
} else {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'المشغل غير موجود أو ليس لديك صلاحية الوصول إليه'
    ], JSON_UNESCAPED_UNICODE));
}
