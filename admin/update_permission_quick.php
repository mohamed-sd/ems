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
    $result = mysqli_query($conn, "INSERT IGNORE INTO report_role_permissions (role_id, report_code) VALUES ($roleId, '$reportCode')");
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم تفعيل الصلاحية']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)]);
    }
} else {
    $result = mysqli_query($conn, "DELETE FROM report_role_permissions WHERE role_id = $roleId AND report_code = '$reportCode'");
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم إلغاء الصلاحية']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)]);
    }
}
exit;
