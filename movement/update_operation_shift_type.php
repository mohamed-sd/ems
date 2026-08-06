<?php
/**
 * AJAX Endpoint: تحديث نظام الوردية للتشغيل
 * Updates shift_type for operations table
 *
 * @table operations
 * @field shift_type ENUM('D', 'N', 'B')
 * @access Authorized users only
 */

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
require_once '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'movement/movement_operations.php', 'edit');

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
$shift_type = trim($_POST['shift_type']);

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

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): شرط الشركة اليدوي زال —
// البوابة تحقنه؛ وفرع audit_log الميّت أُسقط (الجدول غير موجود — لم يكن يُنفَّذ).
$uost_gate = ems_tenant_db();
try {
    $check_row = $uost_gate->selectOne('operations', array(
        'columns'  => array('id'),
        'where'    => array('id' => $operation_id),
        'whereRaw' => 'status = 1',
    ));
} catch (\Throwable $t) { $check_row = null; }

if (!$check_row) {
    die(json_encode([
        'success' => false,
        'message' => 'التشغيل غير موجود أو ليس لديك صلاحية الوصول إليه'
    ]));
}

// ═══════════════════════════════════════════════════════════════
// 4. Update Shift Type
// ═══════════════════════════════════════════════════════════════

try {
    $uost_gate->update('operations',
        array('shift_type' => $shift_type),
        array('id' => $operation_id), 'status = 1');
} catch (\Throwable $t) {
    die(json_encode([
        'success' => false,
        'message' => 'فشل التحديث: ' . $t->getMessage()
    ]));
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
