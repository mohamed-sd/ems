<?php
/**
 * AJAX Endpoint: تحديث نظام الوردية للتشغيل
 * Updates shift_type for operations table
 *
 * @table operations
 * @field shift_type ENUM('D', 'N', 'B')
 * @access Authorized users only
 */

session_start();
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// ═══════════════════════════════════════════════════════════════
// 1. Security & Validation
// ═══════════════════════════════════════════════════════════════

// Session check
if (!isset($_SESSION['user'])) {
    die(json_encode([
        'success' => false,
        'message' => 'غير مصرح. يرجى تسجيل الدخول'
    ]));
}

// POST method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ]));
}

$user_id = intval($_SESSION['user']['id']);
$company_id = intval($_SESSION['user']['company_id']);

// Validate required parameters
if (!isset($_POST['operation_id']) || !isset($_POST['shift_type'])) {
    die(json_encode([
        'success' => false,
        'message' => 'بيانات ناقصة: operation_id أو shift_type'
    ]));
}

$operation_id = intval($_POST['operation_id']);
$shift_type = mysqli_real_escape_string($conn, trim($_POST['shift_type']));

// Validate operation_id
if ($operation_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'رقم التشغيل غير صحيح'
    ]));
}

// Validate shift_type enum
$valid_shifts = ['D', 'N', 'B'];
if (!in_array($shift_type, $valid_shifts)) {
    die(json_encode([
        'success' => false,
        'message' => 'نظام الوردية غير صحيح. يجب أن يكون: D, N, أو B'
    ]));
}

// ═══════════════════════════════════════════════════════════════
// 2. Permission Check (Optional - add if needed)
// ═══════════════════════════════════════════════════════════════

// Uncomment if permissions system is required
/*
if (!can_update('operations')) {
    die(json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية تعديل التشغيل'
    ]));
}
*/

// ═══════════════════════════════════════════════════════════════
// 3. Verify Operation Exists & Belongs to Company
// ═══════════════════════════════════════════════════════════════

$check_sql = "SELECT id FROM operations
              WHERE id = $operation_id
              AND company_id = $company_id
              AND status = 1
              LIMIT 1";

$check_result = mysqli_query($conn, $check_sql);

if (!$check_result || mysqli_num_rows($check_result) === 0) {
    die(json_encode([
        'success' => false,
        'message' => 'التشغيل غير موجود أو ليس لديك صلاحية الوصول إليه'
    ]));
}

// ═══════════════════════════════════════════════════════════════
// 4. Update Shift Type
// ═══════════════════════════════════════════════════════════════

$update_sql = "UPDATE operations
               SET shift_type = '$shift_type'
               WHERE id = $operation_id
               AND company_id = $company_id
               AND status = 1";

$update_result = mysqli_query($conn, $update_sql);

if (!$update_result) {
    die(json_encode([
        'success' => false,
        'message' => 'فشل التحديث: ' . mysqli_error($conn)
    ]));
}

// ═══════════════════════════════════════════════════════════════
// 5. Audit Log (Optional - if audit_log table exists)
// ═══════════════════════════════════════════════════════════════

// Helper function to check if table and column exist
if (!function_exists('db_table_has_column')) {
    function db_table_has_column($conn, $table, $column) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $check && mysqli_num_rows($check) > 0;
    }
}

// Log to audit_log if table exists
$audit_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'audit_log'");
if ($audit_table_check && mysqli_num_rows($audit_table_check) > 0) {

    $shift_labels = [
        'D' => 'نهاري فقط',
        'N' => 'ليلي فقط',
        'B' => 'نهاري + ليلي'
    ];

    $shift_label = isset($shift_labels[$shift_type]) ? $shift_labels[$shift_type] : $shift_type;

    $audit_action = "تم تعديل نظام الوردية للتشغيل رقم $operation_id";
    $audit_details = "نظام الوردية الجديد: $shift_label ($shift_type)";
    $audit_details = mysqli_real_escape_string($conn, $audit_details);
    $audit_action = mysqli_real_escape_string($conn, $audit_action);

    $audit_sql = "INSERT INTO audit_log
                  (user_id, action, details, created_at, company_id)
                  VALUES
                  ($user_id, '$audit_action', '$audit_details', NOW(), $company_id)";

    mysqli_query($conn, $audit_sql);
    // Ignore audit log errors (non-critical)
}

// ═══════════════════════════════════════════════════════════════
// 6. Success Response
// ═══════════════════════════════════════════════════════════════

echo json_encode([
    'success' => true,
    'message' => 'تم تحديث نظام الوردية بنجاح',
    'shift_type' => $shift_type
]);

exit;
