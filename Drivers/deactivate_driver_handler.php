<?php
/**
 * معالج طلب إيقاف/تعطيل مشغل
 * يقوم مدير الحركة والتشغيل (Role 10) بتقديم طلب
 * يوافق عليه مدير المشغلين (Role 3)
 */
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح لك بالدخول']));
}

include '../config.php';
require_once '../includes/approval_workflow.php';
require_once '../includes/permissions_helper.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

enforce_module_permission_json($conn, 'drivers', 'edit', 'لا توجد صلاحية تعديل حالة المشغلين');

// الحصول على البيانات
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
$deactivation_reason = isset($_POST['deactivation_reason']) ? trim($_POST['deactivation_reason']) : '';
$deactivation_date = isset($_POST['deactivation_date']) ? trim($_POST['deactivation_date']) : date('Y-m-d');

// التحقق من صحة البيانات
if (!in_array($action, ['deactivate_driver', 'reactivate_driver'])) {
    die(json_encode(['success' => false, 'message' => 'الإجراء المطلوب غير معروف']));
}

if ($driver_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف المشغل غير صحيح']));
}

// الحصول على بيانات المشغل الحالية
$driver_query = "SELECT id, name, driver_code, driver_status FROM drivers WHERE id = $driver_id";
$driver_result = mysqli_query($conn, $driver_query);

if (!$driver_result || mysqli_num_rows($driver_result) === 0) {
    die(json_encode(['success' => false, 'message' => 'المشغل غير موجود']));
}

$driver = mysqli_fetch_assoc($driver_result);

// التحقق من التصاريح
$user_role = approval_get_user_role();
$user_id = approval_get_user_id();

// فقط مدير الحركة والتشغيل (Role 10) يمكنه تقديم الطلب
if ($user_role !== '10') {
    die(json_encode(['success' => false, 'message' => 'ليس لديك صلاحيات لتقديم طلب إيقاف مشغل']));
}

// التحقق من حالة المشغل الحالية
if ($action === 'deactivate_driver' && $driver['driver_status'] === 'موقوف') {
    die(json_encode(['success' => false, 'message' => 'المشغل مُيقف بالفعل']));
}

if ($action === 'reactivate_driver' && $driver['driver_status'] !== 'موقوف') {
    die(json_encode(['success' => false, 'message' => 'المشغل غير موقوف']));
}

// بناء payload الطلب
$payload = [
    'summary' => [
        'driver_id' => $driver_id,
        'driver_name' => $driver['name'],
        'driver_code' => $driver['driver_code'],
        'current_status' => $driver['driver_status'],
        'new_status' => $action === 'deactivate_driver' ? 'موقوف' : 'نشط',
        'reason' => $deactivation_reason,
        'deactivation_date' => $deactivation_date,
        'action_type' => $action
    ],
    'operations' => [
        [
            'type' => 'UPDATE',
            'table' => 'drivers',
            'where' => "id = $driver_id",
            'fields' => [
                'driver_status' => $action === 'deactivate_driver' ? 'موقوف' : 'نشط'
            ]
        ]
    ]
];

// تحديد الإجراء للموافقة
$approval_action = $action === 'deactivate_driver' ? 'deactivate_driver' : 'reactivate_driver';

// إنشاء طلب الموافقة
$result = approval_create_request(
    'driver',
    $driver_id,
    $approval_action,
    json_encode($payload, JSON_UNESCAPED_UNICODE),
    $user_id,
    $conn
);

if ($result['status'] === 'approved') {
    // تم الموافقة على الفور (Auto-approval)
    die(json_encode([
        'success' => true,
        'message' => 'تم ' . ($action === 'deactivate_driver' ? 'إيقاف' : 'إعادة تفعيل') . ' المشغل بنجاح',
        'request_id' => $result['request_id'],
        'auto_approved' => true
    ]));
}

die(json_encode([
    'success' => true,
    'message' => 'تم تقديم طلب ' . ($action === 'deactivate_driver' ? 'إيقاف' : 'إعادة تفعيل') . ' المشغل. ينتظر موافقة مدير المشغلين',
    'request_id' => $result['request_id'],
    'auto_approved' => false
]));
