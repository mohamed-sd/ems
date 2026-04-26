<?php
/**
 * معالج طلب إيقاف/تعطيل آلية (معدة)
 * يقوم مدير الحركة والتشغيل (Role 10) بتقديم طلب
 * يوافق عليه مدير الأسطول (Role 4)
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

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرّف الشركة غير متوفر']));
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$equipments_has_availability_state = db_table_has_column($conn, 'equipments', 'availability_state');

enforce_module_permission_json($conn, 'equipments', 'edit', 'لا توجد صلاحية تعديل حالة الآليات');

// الحصول على البيانات
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
$deactivation_reason = isset($_POST['deactivation_reason']) ? trim($_POST['deactivation_reason']) : '';
$deactivation_date = isset($_POST['deactivation_date']) ? trim($_POST['deactivation_date']) : date('Y-m-d');

// التحقق من صحة البيانات
if (!in_array($action, ['deactivate_equipment', 'reactivate_equipment'])) {
    die(json_encode(['success' => false, 'message' => 'الإجراء المطلوب غير معروف']));
}

if ($equipment_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف الآلية غير صحيح']));
}

// الحصول على بيانات الآلية الحالية
$equipment_scope = ($is_super_admin || !$equipments_has_company) ? '' : " AND company_id = $company_id";
$availability_state_select = $equipments_has_availability_state ? ', availability_state' : '';
$equipment_query = "SELECT id, code, name, availability_status$availability_state_select FROM equipments WHERE id = $equipment_id$equipment_scope";
$equipment_result = mysqli_query($conn, $equipment_query);

if (!$equipment_result || mysqli_num_rows($equipment_result) === 0) {
    die(json_encode(['success' => false, 'message' => 'الآلية غير موجودة']));
}

$equipment = mysqli_fetch_assoc($equipment_result);
$current_availability_state = ($equipments_has_availability_state && isset($equipment['availability_state']) && $equipment['availability_state'] !== '')
    ? $equipment['availability_state']
    : ((empty($equipment['availability_status']) || $equipment['availability_status'] === 'متاحة للعمل' || $equipment['availability_status'] === 'قيد الاستخدام') ? 'متوفرة' : 'غير متوفرة');
$current_availability_status = $equipment['availability_status'];
if ($current_availability_status === 'موقوفة للصيانة') {
    $current_availability_status = 'تحت الصيانة';
} elseif ($current_availability_status === 'مبيعة/مسحوبة') {
    $current_availability_status = 'مسحوبة';
} elseif ($current_availability_status === 'معطلة مؤقتاً') {
    $current_availability_status = 'معطلة';
}
if ($current_availability_state === 'متوفرة') {
    $current_availability_status = 'قيد الاستخدام';
}

// التحقق من التصاريح
$user_role = approval_get_user_role();
$user_id = approval_get_user_id();

// فقط مدير الحركة والتشغيل (Role 10) يمكنه تقديم الطلب
if ($user_role !== '10') {
    die(json_encode(['success' => false, 'message' => 'ليس لديك صلاحيات لتقديم طلب إيقاف آلية']));
}

// التحقق من حالة الآلية الحالية
if ($action === 'deactivate_equipment' && $current_availability_state === 'غير متوفرة') {
    die(json_encode(['success' => false, 'message' => 'الآلية غير متوفرة بالفعل']));
}

if ($action === 'reactivate_equipment' && $current_availability_state !== 'غير متوفرة') {
    die(json_encode(['success' => false, 'message' => 'الآلية متوفرة بالفعل']));
}

$updated_equipment_fields = [
    'availability_status' => $action === 'deactivate_equipment' ? 'تحت الصيانة' : 'قيد الاستخدام'
];
if ($equipments_has_availability_state) {
    $updated_equipment_fields['availability_state'] = $action === 'deactivate_equipment' ? 'غير متوفرة' : 'متوفرة';
}

// بناء payload الطلب
$payload = [
    'summary' => [
        'equipment_id' => $equipment_id,
        'equipment_code' => $equipment['code'],
        'equipment_name' => $equipment['name'],
        'current_availability_state' => $current_availability_state,
        'current_status' => $current_availability_status,
        'new_availability_state' => $action === 'deactivate_equipment' ? 'غير متوفرة' : 'متوفرة',
        'new_status' => $action === 'deactivate_equipment' ? 'تحت الصيانة' : 'قيد الاستخدام',
        'reason' => $deactivation_reason,
        'deactivation_date' => $deactivation_date,
        'action_type' => $action
    ],
    'operations' => [
        [
            'type' => 'UPDATE',
            'table' => 'equipments',
            'where' => "id = $equipment_id" . (($is_super_admin || !$equipments_has_company) ? '' : " AND company_id = $company_id"),
            'fields' => $updated_equipment_fields
        ]
    ]
];

// تحديد الإجراء للموافقة
$approval_action = $action === 'deactivate_equipment' ? 'deactivate_equipment' : 'reactivate_equipment';

// إنشاء طلب الموافقة
$result = approval_create_request(
    'equipment',
    $equipment_id,
    $approval_action,
    json_encode($payload, JSON_UNESCAPED_UNICODE),
    $user_id,
    $conn
);

if ($result['status'] === 'approved') {
    // تم الموافقة على الفور (Auto-approval)
    die(json_encode([
        'success' => true,
        'message' => 'تم ' . ($action === 'deactivate_equipment' ? 'إيقاف' : 'إعادة تفعيل') . ' الآلية بنجاح',
        'request_id' => $result['request_id'],
        'auto_approved' => true
    ]));
}

die(json_encode([
    'success' => true,
    'message' => 'تم تقديم طلب ' . ($action === 'deactivate_equipment' ? 'إيقاف' : 'إعادة تفعيل') . ' الآلية. ينتظر موافقة مدير الأسطول',
    'request_id' => $result['request_id'],
    'auto_approved' => false
]));
