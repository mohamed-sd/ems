<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالدخول']);
    exit();
}

include '../config.php';

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

// التحقق من وجود عمود shift_type في جدول equipment_drivers
$equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');
if (!$equipment_drivers_has_shift_type) {
    echo json_encode(['success' => false, 'message' => 'نظام الوردية غير مدعوم في قاعدة البيانات']);
    exit();
}

// التحقق من وجود عمود company_id في equipment_drivers
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');

// بناء الاستعلام للتحقق من الصلاحية
$company_scope = '';
if (!$is_super_admin && $equipment_drivers_has_company) {
    $company_scope = " AND company_id = $company_id";
}

// التحقق من وجود السجل والصلاحية
$check_sql = "SELECT id, shift_type FROM equipment_drivers
              WHERE id = $relation_id
              AND status = 1
              $company_scope
              LIMIT 1";

$check_result = mysqli_query($conn, $check_sql);

if (!$check_result || mysqli_num_rows($check_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'السجل غير موجود أو ليس لديك صلاحية للتعديل']);
    exit();
}

// تحديث نظام الوردية
$shift_type_escaped = mysqli_real_escape_string($conn, $shift_type);
$update_sql = "UPDATE equipment_drivers
               SET shift_type = '$shift_type_escaped'
               WHERE id = $relation_id
               AND status = 1
               $company_scope";

$update_result = mysqli_query($conn, $update_sql);

if ($update_result && mysqli_affected_rows($conn) > 0) {
    // تسجيل العملية في سجل التدقيق (إذا كان موجوداً)
    $user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    $user_name = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'غير معروف';

    // الحصول على اسم السائق لتسجيل التغيير
    $driver_info_sql = "SELECT d.name, e.code, e.name as equipment_name
                        FROM equipment_drivers ed
                        JOIN employees d ON ed.employee_id = d.id
                        JOIN equipments e ON ed.equipment_id = e.id
                        WHERE ed.id = $relation_id
                        LIMIT 1";
    $driver_info_result = mysqli_query($conn, $driver_info_sql);

    if ($driver_info_result && mysqli_num_rows($driver_info_result) > 0) {
        $driver_info = mysqli_fetch_assoc($driver_info_result);
        $driver_name = $driver_info['name'];
        $equipment_code = $driver_info['code'];
        $equipment_name = $driver_info['equipment_name'];

        $shift_labels = [
            'D' => 'نهاري فقط',
            'N' => 'ليلي فقط',
            'B' => 'نهاري + ليلي'
        ];
        $shift_label = isset($shift_labels[$shift_type]) ? $shift_labels[$shift_type] : $shift_type;

        // يمكن تسجيل هذا التغيير في جدول audit_log إذا كان موجوداً
        if (db_table_has_column($conn, 'audit_log', 'id')) {
            $log_message = "تم تعديل نظام الوردية للسائق $driver_name على المعدة $equipment_code - $equipment_name إلى: $shift_label";
            $log_message_escaped = mysqli_real_escape_string($conn, $log_message);

            $audit_sql = "INSERT INTO audit_log (user_id, user_name, action, details, created_at)
                          VALUES ($user_id, '" . mysqli_real_escape_string($conn, $user_name) . "', 'update_shift_type', '$log_message_escaped', NOW())";
            mysqli_query($conn, $audit_sql);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث نظام الوردية بنجاح',
        'shift_type' => $shift_type
    ]);
} elseif (mysqli_affected_rows($conn) === 0) {
    echo json_encode(['success' => true, 'message' => 'لم يتم إجراء أي تغيير (القيمة نفسها)']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'فشل التحديث: ' . mysqli_error($conn)
    ]);
}

mysqli_close($conn);
