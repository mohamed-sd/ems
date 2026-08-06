<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالدخول']);
    exit();
}

include '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'movement/movement_operations.php', 'edit');


// إعداد header للـ JSON
header('Content-Type: application/json; charset=utf-8');

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'لا توجد بيئة شركة صالحة']);
    exit();
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// استقبال البيانات
$relation_id = isset($_POST['relation_id']) ? intval($_POST['relation_id']) : 0;
$shift_type = isset($_POST['shift_type']) ? trim($_POST['shift_type']) : '';

// التحقق من البيانات
if ($relation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف السجل غير صحيح']);
    exit();
}

// التحقق من صحة نوع الوردية
$allowed_shift_types = ['D', 'N', 'B'];
if (!in_array($shift_type, $allowed_shift_types, true)) {
    echo json_encode(['success' => false, 'message' => 'نوع الوردية غير صحيح']);
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشف الأعمدة أُسقط (shift_type/
// company_id مضمونان بالترحيلات)، وفرع audit_log الميّت أُسقط (الجدول غير موجود —
// كان الفرع لا يُنفَّذ إطلاقًا). السوبر عبر forAllTenants المسجَّل (سلوك الأصل).
$ust_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('shift type update super') : ems_tenant_db();

// التحقق من وجود السجل والصلاحية
try {
    $check_row = $ust_gate->selectOne('equipment_drivers', array(
        'columns'  => array('id', 'shift_type'),
        'where'    => array('id' => $relation_id),
        'whereRaw' => 'status = 1',
    ));
} catch (\Throwable $t) { $check_row = null; }

if (!$check_row) {
    echo json_encode(['success' => false, 'message' => 'السجل غير موجود أو ليس لديك صلاحية للتعديل']);
    exit();
}

// تحديث نظام الوردية
try {
    $affected = $ust_gate->update('equipment_drivers',
        array('shift_type' => $shift_type),
        array('id' => $relation_id), 'status = 1');
} catch (\Throwable $t) {
    echo json_encode([
        'success' => false,
        'message' => 'فشل التحديث: ' . $t->getMessage()
    ]);
    mysqli_close($conn);
    exit();
}

if ($affected > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث نظام الوردية بنجاح',
        'shift_type' => $shift_type
    ]);
} else {
    echo json_encode(['success' => true, 'message' => 'لم يتم إجراء أي تغيير (القيمة نفسها)']);
}

mysqli_close($conn);
