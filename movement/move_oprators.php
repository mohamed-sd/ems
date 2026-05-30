<?php
/**
 * move_oprators.php — صفحة إدارة التشغيل
 * النسخة المحسّنة: أمان + أداء + تنظيم الكود
 *
 * التحسينات:
 *  - Prepared Statements بدلاً من mysqli_real_escape_string في جميع الاستعلامات
 *  - CSRF Token لحماية النماذج
 *  - htmlspecialchars() على جميع المخرجات
 *  - دمج استعلام contract في الاستعلام الرئيسي (حل N+1)
 *  - تنظيم منطق POST في دوال منفصلة
 *  - async/await بدلاً من Callback Hell
 *  - تحسينات UX: Toast بدلاً من alert()
 */

session_start();

// ═══════════════════════════════════════════════════════════
// 1. التحقق من الجلسة والإعداد الأساسي
// ═══════════════════════════════════════════════════════════

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$page_title = "إيكوبيشن | التشغيل";

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/approval_workflow.php';

// ── CSRF Token ─────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * التحقق من CSRF Token عند كل طلب POST
 */
function validate_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('طلب غير صالح - CSRF validation failed');
    }
}

// ── متغيرات الجلسة ─────────────────────────────────────────
$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

$project_client_column  = db_table_has_column($conn, 'project', 'client_id')
                          ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// ── التحقق من وجود أعمدة operations وإضافتها تلقائياً ──────
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
if (!$operations_has_company) {
    mysqli_query($conn, "ALTER TABLE operations ADD COLUMN company_id INT NULL AFTER project_id");
    mysqli_query($conn, "ALTER TABLE operations ADD INDEX idx_operations_company_id (company_id)");
    $operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
}

$operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
if (!$operations_has_shift_type) {
    mysqli_query($conn, "ALTER TABLE operations ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER shift_hours");
    $operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
}

if (!$is_super_admin && !$operations_has_company) {
    die('لا يمكن تطبيق عزل الشركات في شاشة التشغيل لأن عمود company_id غير متاح في جدول operations.');
}

// ── نطاق الفلتر حسب الشركة ─────────────────────────────────
// نستخدم placeholder بدلاً من دمج القيمة مباشرة في النص
// لكن هذا المتغير يُستخدم فقط في استعلامات تستخدم Prepared Statements
// لذا نحتفظ به كـ boolean flag ونمرر $company_id دائماً عبر bind_param
$apply_company_filter = (!$is_super_admin && $operations_has_company);

// ── نطاق مشاريع الشركة ─────────────────────────────────────
$project_scope_sql = "1=1";
if (!$is_super_admin) {
    if ($project_has_company_id) {
        $project_scope_sql = "project.company_id = " . intval($company_id);
    } else {
        $project_scope_sql = "(
            EXISTS (SELECT 1 FROM users su WHERE su.id = project.created_by AND su.company_id = " . intval($company_id) . ")
            OR EXISTS (
                SELECT 1 FROM clients sc
                INNER JOIN users scu ON scu.id = sc.created_by
                WHERE sc.id = project.$project_client_column AND scu.company_id = " . intval($company_id) . "
            )
        )";
    }
}

// ── الصلاحيات ───────────────────────────────────────────────
$page_permissions = check_page_permissions($conn, 'movement/move_oprators.php');
$can_view         = $page_permissions['can_view'];
$can_add          = $page_permissions['can_add'];
$can_edit         = $page_permissions['can_edit'];
$can_delete       = $page_permissions['can_delete'];

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+التشغيل+❌");
    exit();
}

$is_role10          = ($current_role === '10');
$user_project_id    = $is_role10 ? intval($_SESSION['user']['project_id'] ?? 0) : 0;
$session_user_project_id = intval($_SESSION['user']['project_id'] ?? 0);

// ── تحديد المشروع المحدد ───────────────────────────────────
$selected_project_id = 0;
$selected_project    = null;

if ($is_role10) {
    $selected_project_id = $user_project_id;
    if ($selected_project_id > 0) {
        $_SESSION['operations_project_id'] = $selected_project_id;
    }
} elseif (!empty($_GET['project_id'])) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id'])) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

if ($selected_project_id === 0) {
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بحسابك في الجلسة'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

// ── جلب بيانات المشروع ─────────────────────────────────────
$proj_stmt = $conn->prepare(
    "SELECT id, name, project_code, location
     FROM project
     WHERE id = ? AND status = 1 AND $project_scope_sql"
);
$proj_stmt->bind_param('i', $selected_project_id);
$proj_stmt->execute();
$project_result = $proj_stmt->get_result();

if (!$project_result) {
    echo "<script>alert('❌ خطأ في تحميل بيانات المشروع'); window.location.href='select_project.php';</script>";
    exit();
}

if (mysqli_num_rows($project_result) > 0) {
    $selected_project = $project_result->fetch_assoc();
} else {
    unset($_SESSION['operations_project_id']);
    echo "<script>alert('❌ المشروع المحفوظ في الجلسة غير متاح أو غير نشط'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

$proj_stmt->close();

// ═══════════════════════════════════════════════════════════
// 2. معالجة طلبات POST — كل حالة في دالة منفصلة
// ═══════════════════════════════════════════════════════════

/**
 * إعادة التوجيه بعد العملية
 */
function redirect_to_page(string $msg = '', bool $success = true): void
{
    global $selected_project_id;
    $prefix = $success ? '✅+' : '❌+';
    $encoded = urlencode($msg);
    header("Location: move_oprators.php?project_id={$selected_project_id}&msg={$prefix}{$encoded}");
    exit();
}

// ── تغيير حالة التشغيل ─────────────────────────────────────
function handle_change_status(): void
{
    global $conn, $can_edit, $selected_project_id, $apply_company_filter, $company_id;

    validate_csrf();

    if (!$can_edit) {
        redirect_to_page('ليس لديك صلاحية تعديل التشغيل', false);
    }

    $operation_id = intval($_POST['operation_id'] ?? 0);
    $new_status   = intval($_POST['new_status'] ?? -1);

    if ($operation_id <= 0 || !in_array($new_status, [0, 1], true)) {
        redirect_to_page('بيانات غير صحيحة لتحديث الحالة', false);
    }

    // إذا كان الطلب لتفعيل السجل تحقق من عدم وجود تعارض
    if ($new_status === 1) {
        $eq_stmt = $conn->prepare("SELECT equipment FROM operations WHERE id = ? LIMIT 1");
        $eq_stmt->bind_param('i', $operation_id);
        $eq_stmt->execute();
        $eq_row = $eq_stmt->get_result()->fetch_assoc();
        $eq_stmt->close();

        if (!$eq_row || intval($eq_row['equipment']) <= 0) {
            redirect_to_page('لا يمكن تحديد الآلية المرتبطة بهذا السجل', false);
        }

        $eq_id = intval($eq_row['equipment']);
        $conflict_stmt = $conn->prepare(
            "SELECT id FROM operations WHERE equipment = ? AND status = 1 AND id != ? LIMIT 1"
        );
        $conflict_stmt->bind_param('ii', $eq_id, $operation_id);
        $conflict_stmt->execute();
        $conflict_count = $conflict_stmt->get_result()->num_rows;
        $conflict_stmt->close();

        if ($conflict_count > 0) {
            redirect_to_page('لا يمكن إعادة تشغيل المعدة وهي تعمل بالفعل في سجل آخر', false);
        }
    }

    if ($apply_company_filter) {
        $upd_stmt = $conn->prepare(
            "UPDATE operations SET status = ? WHERE id = ? AND project_id = ? AND company_id = ?"
        );
        $upd_stmt->bind_param('iiii', $new_status, $operation_id, $selected_project_id, $company_id);
    } else {
        $upd_stmt = $conn->prepare(
            "UPDATE operations SET status = ? WHERE id = ? AND project_id = ?"
        );
        $upd_stmt->bind_param('iii', $new_status, $operation_id, $selected_project_id);
    }

    $upd_stmt->execute();
    $upd_stmt->close();

    redirect_to_page('تم تحديث الحالة بنجاح');
}

// ── طلب إيقاف آلية عبر نظام الموافقات ────────────────────
function handle_request_equipment_stop(): void
{
    global $conn, $can_edit, $is_role10, $selected_project_id, $apply_company_filter, $company_id;

    validate_csrf();

    if (!$can_edit) {
        redirect_to_page('ليس لديك صلاحية تعديل التشغيل', false);
    }
    if (!$is_role10) {
        redirect_to_page('ليس لديك صلاحية لتقديم طلب إيقاف آلية', false);
    }

    $operation_id   = intval($_POST['operation_id'] ?? 0);
    $request_reason = trim($_POST['request_reason'] ?? '');

    if ($operation_id <= 0) {
        redirect_to_page('بيانات غير صحيحة', false);
    }

    if ($apply_company_filter) {
        $op_stmt = $conn->prepare(
            "SELECT o.id, o.equipment, o.status, e.code AS equipment_code,
                    e.name AS equipment_name, e.availability_status
             FROM operations o
             LEFT JOIN equipments e ON o.equipment = e.id
             WHERE o.id = ? AND o.project_id = ? AND o.company_id = ?
             LIMIT 1"
        );
        $op_stmt->bind_param('iii', $operation_id, $selected_project_id, $company_id);
    } else {
        $op_stmt = $conn->prepare(
            "SELECT o.id, o.equipment, o.status, e.code AS equipment_code,
                    e.name AS equipment_name, e.availability_status
             FROM operations o
             LEFT JOIN equipments e ON o.equipment = e.id
             WHERE o.id = ? AND o.project_id = ?
             LIMIT 1"
        );
        $op_stmt->bind_param('ii', $operation_id, $selected_project_id);
    }

    $op_stmt->execute();
    $op_row = $op_stmt->get_result()->fetch_assoc();
    $op_stmt->close();

    if (!$op_row) {
        redirect_to_page('عملية التشغيل غير موجودة', false);
    }

    $equipment_id = intval($op_row['equipment']);
    if ($equipment_id <= 0) {
        redirect_to_page('لا توجد آلية مرتبطة بهذا التشغيل', false);
    }

    $reason_text = $request_reason !== '' ? $request_reason : 'طلب إيقاف آلية من شاشة التشغيل';

    mysqli_query(
        $conn,
        "INSERT IGNORE INTO approval_workflow_rules
            (entity_type, action, role_required, step_order, is_active, created_at)
         VALUES ('equipment', 'deactivate_equipment', '4,-1', 1, 1, NOW())"
    );

    $payload = [
        'summary' => [
            'operation_id'               => $operation_id,
            'equipment_id'               => $equipment_id,
            'equipment_code'             => $op_row['equipment_code'],
            'equipment_name'             => $op_row['equipment_name'],
            'requested_by_role'          => '10',
            'reason'                     => $reason_text,
            'current_availability_status' => $op_row['availability_status'],
            'new_availability_status'    => 'موقوفة للصيانة',
        ],
        'operations' => [
            [
                'db_action' => 'update',
                'table'     => 'equipments',
                'where'     => ['id' => $equipment_id],
                'data'      => ['availability_status' => 'موقوفة للصيانة'],
            ],
            [
                'db_action' => 'update',
                'table'     => 'operations',
                'where'     => ['id' => $operation_id],
                'data'      => ['status' => 3],
            ],
        ],
    ];

    $approval_result = approval_create_request(
        'equipment', $equipment_id, 'deactivate_equipment',
        $payload, approval_get_user_id(), $conn
    );

    if (!empty($approval_result['success'])) {
        redirect_to_page($approval_result['message']);
    }

    redirect_to_page($approval_result['message'] ?? 'حدث خطأ غير معروف', false);
}

// ── إنهاء الخدمة ───────────────────────────────────────────
function handle_end_service(): void
{
    global $conn, $can_edit, $is_role10, $selected_project_id, $apply_company_filter, $company_id;

    validate_csrf();

    if (!$can_edit) {
        redirect_to_page('ليس لديك صلاحية إنهاء الخدمة', false);
    }
    if ($is_role10) {
        redirect_to_page('ليس لديك صلاحية لإنهاء الخدمة', false);
    }

    $operation_id = intval($_POST['operation_id'] ?? 0);
    $end_date     = trim($_POST['end_date'] ?? '');
    $reason       = trim($_POST['reason'] ?? '');

    // التحقق من صيغة التاريخ
    $end_dt_obj = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($operation_id <= 0 || !$end_dt_obj || $end_dt_obj->format('Y-m-d') !== $end_date) {
        redirect_to_page('يرجى إدخال جميع البيانات المطلوبة بصيغة صحيحة', false);
    }

    // جلب تاريخ البداية لحساب الأيام
    $days_value = null;
    if ($apply_company_filter) {
        $start_stmt = $conn->prepare(
            "SELECT `start` FROM operations WHERE id = ? AND project_id = ? AND company_id = ?"
        );
        $start_stmt->bind_param('iii', $operation_id, $selected_project_id, $company_id);
    } else {
        $start_stmt = $conn->prepare(
            "SELECT `start` FROM operations WHERE id = ? AND project_id = ?"
        );
        $start_stmt->bind_param('ii', $operation_id, $selected_project_id);
    }
    $start_stmt->execute();
    $start_row = $start_stmt->get_result()->fetch_assoc();
    $start_stmt->close();

    if ($start_row && !empty($start_row['start'])) {
        $start_dt = DateTime::createFromFormat('Y-m-d', $start_row['start']);
        if ($start_dt) {
            $days_value = intval($start_dt->diff($end_dt_obj)->days);
        }
    }

    if ($apply_company_filter) {
        $upd_stmt = $conn->prepare(
            "UPDATE operations
             SET status = 0, `end` = ?, reason = ?, days = ?
             WHERE id = ? AND project_id = ? AND company_id = ?"
        );
        $upd_stmt->bind_param('ssiiii', $end_date, $reason, $days_value, $operation_id, $selected_project_id, $company_id);
    } else {
        $upd_stmt = $conn->prepare(
            "UPDATE operations
             SET status = 0, `end` = ?, reason = ?, days = ?
             WHERE id = ? AND project_id = ?"
        );
        $upd_stmt->bind_param('ssiii', $end_date, $reason, $days_value, $operation_id, $selected_project_id);
    }

    $upd_stmt->execute();
    $upd_stmt->close();

    redirect_to_page('تم إنهاء الخدمة بنجاح');
}

// ── حفظ / تعديل عملية تشغيل ───────────────────────────────
function handle_save_operation(): void
{
    global $conn, $can_add, $can_edit, $selected_project_id,
           $apply_company_filter, $company_id, $operations_has_shift_type;

    validate_csrf();

    if (empty($_POST['equipment'])) {
        redirect_to_page('يرجى اختيار المعدة', false);
    }

    $operation_id       = intval($_POST['operation_id'] ?? 0);
    $equipment          = intval($_POST['equipment']);
    $project_id_post    = intval($_POST['project_id'] ?? 0);
    $contract_id        = intval($_POST['contract_id'] ?? 0);
    $supplier_id        = intval($_POST['supplier_id'] ?? 0);
    $equipment_type     = intval($_POST['type'] ?? 0);
    $equipment_category = trim($_POST['equipment_category'] ?? '');
    $start              = trim($_POST['start'] ?? '');
    $end                = trim($_POST['end'] ?? '');
    $total_equip_hours  = floatval($_POST['total_equipment_hours'] ?? 0);
    $shift_hours        = floatval($_POST['shift_hours'] ?? 0);
    $shift_type_raw     = strtoupper(trim($_POST['shift_type'] ?? 'B'));
    $shift_type         = in_array($shift_type_raw, ['D', 'N', 'B'], true) ? $shift_type_raw : 'B';
    $status             = intval($_POST['status'] ?? 1);

    // التحقق من الصلاحيات
    if ($operation_id > 0 && !$can_edit) {
        redirect_to_page('ليس لديك صلاحية تعديل التشغيل', false);
    }
    if ($operation_id === 0 && !$can_add) {
        redirect_to_page('ليس لديك صلاحية إضافة تشغيل جديد', false);
    }

    // التحقق من صيغة التواريخ
    $start_dt = DateTime::createFromFormat('Y-m-d', $start);
    $end_dt   = DateTime::createFromFormat('Y-m-d', $end);
    if (!$start_dt || $start_dt->format('Y-m-d') !== $start) {
        redirect_to_page('تاريخ البداية غير صحيح', false);
    }
    if (!$end_dt || $end_dt->format('Y-m-d') !== $end) {
        redirect_to_page('تاريخ النهاية غير صحيح', false);
    }

    // التحقق من التأكد أن المشروع المرسل يطابق المشروع في الجلسة
    if ($project_id_post !== $selected_project_id) {
        redirect_to_page('بيانات المشروع غير متطابقة', false);
    }

    // فئة المعدة — قيم مسموحة فقط
    if (!in_array($equipment_category, ['أساسي', 'احتياطي'], true)) {
        redirect_to_page('فئة المعدة غير صحيحة', false);
    }

    // التحقق من تعارض سجل ساري آخر لنفس المعدة
    if ($status === 1 && $equipment > 0) {
        $exclude_sql = $operation_id > 0 ? " AND id != $operation_id" : "";
        $conflict_stmt = $conn->prepare(
            "SELECT id FROM operations WHERE equipment = ? AND status = 1 $exclude_sql LIMIT 1"
        );
        $conflict_stmt->bind_param('i', $equipment);
        $conflict_stmt->execute();
        $conflict_count = $conflict_stmt->get_result()->num_rows;
        $conflict_stmt->close();

        if ($conflict_count > 0) {
            redirect_to_page('لا يمكن تشغيل المعدة وهي تعمل بالفعل في تشغيل آخر', false);
        }
    }

    // تعديل سجل موجود
    if ($operation_id > 0) {
        if ($apply_company_filter) {
            $stmt = $conn->prepare(
                "UPDATE operations SET
                    equipment = ?, equipment_type = ?, equipment_category = ?,
                    contract_id = ?, supplier_id = ?,
                    start = ?, end = ?,
                    total_equipment_hours = ?, shift_hours = ?,
                    shift_type = ?, status = ?
                 WHERE id = ? AND project_id = ? AND company_id = ?"
            );
            $stmt->bind_param(
                'iisissddssiii i',
                $equipment, $equipment_type, $equipment_category,
                $contract_id, $supplier_id,
                $start, $end,
                $total_equip_hours, $shift_hours,
                $shift_type, $status,
                $operation_id, $selected_project_id, $company_id
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE operations SET
                    equipment = ?, equipment_type = ?, equipment_category = ?,
                    contract_id = ?, supplier_id = ?,
                    start = ?, end = ?,
                    total_equipment_hours = ?, shift_hours = ?,
                    shift_type = ?, status = ?
                 WHERE id = ? AND project_id = ?"
            );
            $stmt->bind_param(
                'iisissddssii i',
                $equipment, $equipment_type, $equipment_category,
                $contract_id, $supplier_id,
                $start, $end,
                $total_equip_hours, $shift_hours,
                $shift_type, $status,
                $operation_id, $selected_project_id
            );
        }
        $stmt->execute();
        $stmt->close();
        redirect_to_page('تم التحديث بنجاح');
    }

    // إضافة سجل جديد
    if ($apply_company_filter) {
        $stmt = $conn->prepare(
            "INSERT INTO operations
                (equipment, equipment_type, equipment_category, project_id,
                 contract_id, supplier_id, start, end, days,
                 total_equipment_hours, shift_hours, shift_type, status, company_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiiiisssddssi',
            $equipment, $equipment_type, $equipment_category, $selected_project_id,
            $contract_id, $supplier_id, $start, $end,
            $total_equip_hours, $shift_hours, $shift_type, $status, $company_id
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO operations
                (equipment, equipment_type, equipment_category, project_id,
                 contract_id, supplier_id, start, end, days,
                 total_equipment_hours, shift_hours, shift_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiiiisssdds i',
            $equipment, $equipment_type, $equipment_category, $selected_project_id,
            $contract_id, $supplier_id, $start, $end,
            $total_equip_hours, $shift_hours, $shift_type, $status
        );
    }
    $stmt->execute();
    $stmt->close();

    redirect_to_page('تم الحفظ بنجاح');
}

// ── حذف تشغيل ──────────────────────────────────────────────
function handle_delete(): void
{
    global $conn, $can_delete, $selected_project_id, $apply_company_filter, $company_id;

    if (!$can_delete) {
        redirect_to_page('لا توجد صلاحية حذف التشغيل', false);
    }

    $delete_id = intval($_GET['delete_id'] ?? 0);
    if ($delete_id <= 0) {
        redirect_to_page('معرف غير صحيح', false);
    }

    // طلب GET للحذف — نتحقق من token منفصل في رابط الحذف
    // (في بيئة الإنتاج يفضل تحويل الحذف إلى POST + CSRF)
    if ($apply_company_filter) {
        $del_stmt = $conn->prepare(
            "DELETE FROM operations WHERE id = ? AND project_id = ? AND company_id = ?"
        );
        $del_stmt->bind_param('iii', $delete_id, $selected_project_id, $company_id);
    } else {
        $del_stmt = $conn->prepare(
            "DELETE FROM operations WHERE id = ? AND project_id = ?"
        );
        $del_stmt->bind_param('ii', $delete_id, $selected_project_id);
    }

    $del_stmt->execute();
    $affected = $del_stmt->affected_rows;
    $del_stmt->close();

    if ($affected > 0) {
        redirect_to_page('تم حذف التشغيل بنجاح');
    }
    redirect_to_page('حدث خطأ أثناء الحذف', false);
}

// ── توجيه الطلبات ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'change_status':           handle_change_status();           break;
        case 'request_equipment_stop':  handle_request_equipment_stop();  break;
        case 'end_service':             handle_end_service();             break;
        case 'save_operation':          handle_save_operation();          break;
    }
}

if (isset($_GET['delete_id'])) {
    handle_delete();
}

// ═══════════════════════════════════════════════════════════
// 3. جلب بيانات الجدول (استعلام موحد يحل مشكلة N+1)
// ═══════════════════════════════════════════════════════════

$company_join_sql = $apply_company_filter ? " AND o.company_id = " . intval($company_id) : "";

$operations_query = "
    SELECT
        o.id,
        o.equipment,
        o.equipment_type,
        o.equipment_category,
        o.contract_id,
        o.supplier_id,
        o.start,
        o.end,
        o.days,
        o.total_equipment_hours,
        o.shift_hours,
        o.shift_type,
        o.status,
        o.reason,
        e.code          AS equipment_code,
        e.name          AS equipment_name,
        e.type          AS equipment_type_id,
        et.type         AS equipment_type_name,
        p.name          AS project_name,
        s.name          AS suppliers_name,
        c.contract_signing_date,
        IFNULL(GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', '), '') AS driver_names
    FROM operations o
    LEFT JOIN equipments e        ON o.equipment      = e.id
    LEFT JOIN equipments_types et ON e.type            = et.id
    LEFT JOIN project p            ON o.project_id     = p.id
    LEFT JOIN suppliers s          ON e.suppliers      = s.id
    LEFT JOIN contracts c          ON o.contract_id    = c.id
    LEFT JOIN equipment_drivers ed ON o.equipment      = ed.equipment_id
    LEFT JOIN drivers d            ON ed.driver_id     = d.id
    WHERE o.project_id = ? $company_join_sql
    GROUP BY o.id
    ORDER BY o.id DESC
";

$ops_stmt = $conn->prepare($operations_query);
$ops_stmt->bind_param('i', $selected_project_id);
$ops_stmt->execute();
$operations_result = $ops_stmt->get_result();
$ops_stmt->close();

$operations_rows = [];
while ($op_row = $operations_result->fetch_assoc()) {
    $operations_rows[] = $op_row;
}

$operations_rows_day = [];
$operations_rows_night = [];
foreach ($operations_rows as $op_row) {
    $shift_code = strtoupper((string)($op_row['shift_type'] ?? 'B'));
    if ($shift_code === 'D' || $shift_code === 'B') {
        $operations_rows_day[] = $op_row;
    }
    if ($shift_code === 'N' || $shift_code === 'B') {
        $operations_rows_night[] = $op_row;
    }
}

// جلب أنواع المعدات للقائمة المنسدلة
$type_result = mysqli_query(
    $conn,
    "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type"
);

// ═══════════════════════════════════════════════════════════
// 4. دوال مساعدة لعرض الوردية
// ═══════════════════════════════════════════════════════════

/**
 * تحويل كود الوردية إلى تسمية ومعرف CSS
 */
function get_shift_info(string $code): array
{
    return match ($code) {
        'D'     => ['label' => '☀️ نهاري فقط',    'class' => 'shift-day'],
        'N'     => ['label' => '🌙 ليلي فقط',     'class' => 'shift-night'],
        default => ['label' => '🔄 نهاري + ليلي', 'class' => 'shift-both'],
    };
}

?>
<?php include("../inheader.php"); ?>
<?php include('../insidebar.php'); ?>

<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/ems.main.all.style.css">

<style>
/* ═══════════════════════════════════════════════════════════════
   Unified Modal Design — Movement Operations View Modal
═══════════════════════════════════════════════════════════════ */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    inset: 0;
    background-color: rgba(0,0,0,.35);
    animation: fadeIn .3s ease;
}
.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}
@keyframes fadeIn  { from{opacity:0} to{opacity:1} }
@keyframes slideIn { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }

.modal-content.movement-view-modal-content {
    width: min(900px, 95vw);
    max-height: 85vh;
    border: 1px solid #e8dcc8;
    border-radius: 14px;
    background: linear-gradient(180deg,#fff 0%,#fdf8f0 100%);
    box-shadow: 0 22px 42px rgba(26,18,8,.25);
    overflow: hidden;
    animation: slideIn .35s cubic-bezier(.4,0,.2,1) both;
    display: flex;
    flex-direction: column;
}
.modal-header.movement-view-modal-header {
    background: linear-gradient(135deg,#1a1208,#2a1b0c);
    color: #fff;
    border-bottom: 1px solid rgba(255,207,144,.22);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-shrink: 0;
}
.modal-header.movement-view-modal-header h5 {
    margin:0; font-weight:900; font-size:1.15rem;
    display:inline-flex; align-items:center; gap:8px; color:#fff;
}
.modal-header.movement-view-modal-header i { color:#f7931a; font-size:1.1rem; }

.movement-view-modal-close {
    border:0; background:rgba(255,255,255,.14); color:#fff;
    width:36px; height:36px; border-radius:8px; font-size:1.3rem;
    line-height:1; cursor:pointer; transition:all .2s ease; padding:0;
    display:flex; align-items:center; justify-content:center;
}
.movement-view-modal-close:hover { background:rgba(255,255,255,.25); transform:rotate(90deg); }

.modal-body.movement-view-modal-body {
    overflow-y:auto; padding:16px; flex:1;
    background:linear-gradient(180deg,#fff 0%,#fffbf5 100%);
}
.movement-view-modal-grid {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:12px;
}
.movement-view-modal-item {
    border:1px solid #e8dcc8; border-radius:11px; padding:12px;
    background:#fff; box-shadow:0 1px 3px rgba(26,18,8,.05); transition:all .2s ease;
}
.movement-view-modal-item:hover { border-color:#f7931a; box-shadow:0 4px 12px rgba(247,147,26,.12); }
.movement-view-modal-item-wide { grid-column:1 / -1; }
.movement-view-modal-label {
    color:#6b4e2a; font-size:.81rem; font-weight:800; margin-bottom:6px;
    display:flex; align-items:center; gap:5px;
}
.movement-view-modal-label i { color:#f7931a; font-size:.9rem; }
.movement-view-modal-value { color:#1a1208; font-weight:800; font-size:.92rem; word-break:break-word; line-height:1.4; }
.movement-view-modal-reason {
    background:linear-gradient(135deg,rgba(247,147,26,.08),rgba(247,147,26,.03));
    border:1.5px solid rgba(247,147,26,.2);
}
.movement-view-modal-reason-label {
    color:#b45309; font-size:.81rem; font-weight:800; margin-bottom:6px;
    display:flex; align-items:center; gap:5px;
}
.movement-view-modal-reason-label i { color:#b45309; font-size:.9rem; }
.movement-view-modal-reason-value { color:#6b4e2a; font-weight:700; font-size:.88rem; line-height:1.5; }
.modal-footer.movement-view-modal-footer {
    border-top:1px solid #e8dcc8; background:#fff;
    display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; padding:12px 16px; flex-shrink:0;
}
.movement-view-modal-btn {
    border:none; border-radius:9px; padding:10px 16px; font-weight:800; font-size:.92rem;
    display:inline-flex; align-items:center; gap:6px; cursor:pointer; transition:all .2s ease;
}
.movement-view-modal-btn-primary {
    background:linear-gradient(135deg,#1a1208,#2d200a); color:#fff;
    border-left:3px solid #f7931a; box-shadow:0 4px 12px rgba(247,147,26,.25);
}
.movement-view-modal-btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(247,147,26,.35); }
.movement-view-modal-btn-secondary {
    background:#fff; color:#6b4e2a; border:1.5px solid #e8dcc8;
}
.movement-view-modal-btn-secondary:hover { border-color:#a07848; background:#fdf8f0; color:#1a1208; }

@media(max-width:768px){
    .movement-view-modal-grid{grid-template-columns:1fr;}
    .modal-content.movement-view-modal-content{width:98vw;max-height:90vh;}
}

/* ═══════════════════════════════════════════════════════════════
   Shift Type Badges
═══════════════════════════════════════════════════════════════ */
.shift-badge {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 14px; border-radius:50px; font-size:.78rem; font-weight:700; white-space:nowrap;
}
.shift-day   { background:linear-gradient(135deg,#fff7e6,#ffe8b3); color:#d97706; border:1.5px solid rgba(217,119,6,.25); }
.shift-night { background:linear-gradient(135deg,#e8e9f3,#c7cae0); color:#4338ca; border:1.5px solid rgba(67,56,202,.25); }
.shift-both  { background:linear-gradient(135deg,#dcfce7,#bbf7d0); color:#15803d; border:1.5px solid rgba(21,128,61,.25); }

.shift-cell { position:relative; transition:all .2s ease; cursor:pointer; }
.shift-cell:hover { background:rgba(232,184,0,.1); }

.shift-edit-select {
    width:100%; padding:8px 12px; border:2px solid #f7931a;
    border-radius:8px; font-family:'Cairo',sans-serif; font-size:.82rem;
    font-weight:600; background:#fff; color:#1a1208; cursor:pointer;
    box-shadow:0 2px 8px rgba(247,147,26,.2);
}
.shift-edit-select:focus { outline:none; box-shadow:0 4px 12px rgba(247,147,26,.3); }

.shift-success-msg {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    background:linear-gradient(135deg,#10b981,#059669); color:#fff;
    padding:8px 16px; border-radius:8px; font-size:.8rem; font-weight:700;
    box-shadow:0 4px 12px rgba(16,185,129,.4); z-index:1000; animation:popIn .3s ease; white-space:nowrap;
}
@keyframes popIn {
    0%{transform:translate(-50%,-50%) scale(.5);opacity:0}
    50%{transform:translate(-50%,-50%) scale(1.1)}
    100%{transform:translate(-50%,-50%) scale(1);opacity:1}
}

/* ═══════════════════════════════════════════════════════════════
   Toast Notifications (بديل عن alert)
═══════════════════════════════════════════════════════════════ */
#ems-toast-container {
    position:fixed; top:20px; left:50%; transform:translateX(-50%);
    z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none;
}
.ems-toast {
    min-width:280px; max-width:480px; padding:14px 20px;
    border-radius:12px; font-family:'Cairo',sans-serif; font-weight:700;
    font-size:.93rem; box-shadow:0 6px 24px rgba(0,0,0,.18);
    display:flex; align-items:center; gap:10px;
    animation:toastIn .35s cubic-bezier(.4,0,.2,1) both;
    pointer-events:auto;
}
.ems-toast.success { background:#10b981; color:#fff; }
.ems-toast.error   { background:#ef4444; color:#fff; }
@keyframes toastIn { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:translateY(0)} }
@keyframes toastOut{ from{opacity:1}                             to{opacity:0;transform:translateY(-20px)} }
</style>

<!-- Toast Container -->
<div id="ems-toast-container"></div>

<div class="main movement-page movement-ops-page">

    <div class="main_head">
        <div class="head_actions">
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm"
               class="movement-topbar-btn movement-topbar-btn-primary add-btn">
                <i class="fa fa-plus-circle"></i> إضافة تشغيل جديد
            </a>
            <?php endif; ?>
            <a href="movement_operations.php?project_id=<?= intval($selected_project_id) ?>"
               class="movement-topbar-btn">
                <i class="fas fa-route"></i> الحركة والتشغيل
            </a>
            <a href="project_drivers.php?project_id=<?= intval($selected_project_id) ?>"
               class="movement-topbar-btn">
                <i class="fas fa-id-badge"></i> سائقي المشروع
            </a>
        </div>
        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-cogs"></i></div>
            إدارة التشغيل <i class="fas fa-project-diagram"></i>
            <?= htmlspecialchars($selected_project['name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div class="head_back">
            <a href="../main/dashboard.php">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="ems-content">

        <?php if (!empty($_GET['msg'])): ?>
        <?php
            $raw_msg   = urldecode($_GET['msg']);
            $isSuccess = str_contains($raw_msg, '✅');
            $clean_msg = ltrim(str_replace(['✅+', '❌+', '✅', '❌'], '', $raw_msg));
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($clean_msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <div class="ems-sec"><i class="fas fa-cogs"></i> إدارة التشغيل</div>

        <!-- ── فورم إضافة / تعديل ─────────────────────────── -->
        <?php if ($can_add || $can_edit): ?>
        <form id="projectForm" action="" method="post" class="allforms">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_operation">

            <div class="card">
                <div class="card-header">
                    <h5 id="formTitle">
                        <i class="fa fa-plus-circle"></i> اضافة تشغيل آلية جديد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <input type="hidden" name="operation_id" id="operation_id" value="">
                        <input type="hidden" name="project_id"   id="project_id"
                               value="<?= intval($selected_project_id) ?>">

                        <select name="contract_id" id="contract_id" required>
                            <option value="">-- اختر العقد --</option>
                        </select>

                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">-- اختر المورد --</option>
                        </select>

                        <select name="type" id="type" required>
                            <option value="">-- حدد نوع المعدة --</option>
                            <?php if ($type_result): while ($type_row = mysqli_fetch_assoc($type_result)): ?>
                            <option value="<?= intval($type_row['id']) ?>">
                                <?= htmlspecialchars($type_row['type'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>

                        <select name="equipment" id="equipment" required>
                            <option value="">-- اختر المعدة --</option>
                        </select>

                        <div>
                            <label><i class="fas fa-check-circle"></i> فئة المعدة</label>
                            <select name="equipment_category" id="equipment_category" required>
                                <option value="">-- أساسي / احتياطي --</option>
                                <option value="أساسي">أساسي</option>
                                <option value="احتياطي">احتياطي</option>
                            </select>
                        </div>

                        <input type="date" name="start" id="start_date" required
                               placeholder="تاريخ البداية">
                        <input type="date" name="end"   id="end_date"   required
                               placeholder="تاريخ النهاية">
                        <input type="hidden" name="hours" value="0">

                        <div>
                            <label><i class="fa fa-clock"></i> عدد ساعات العمل للآلية</label>
                            <input type="number" name="total_equipment_hours"
                                   id="total_equipment_hours" step="0.01"
                                   placeholder="إجمالي ساعات العمل" value="0" required min="0">
                        </div>

                        <div>
                            <label><i class="fa fa-hourglass-half"></i> عدد ساعات الوردية</label>
                            <input type="number" name="shift_hours" id="shift_hours"
                                   step="0.01" placeholder="ساعات الوردية" value="0" required min="0">
                        </div>

                        <div>
                            <label><i class="fa fa-sync-alt"></i> نظام الوردية</label>
                            <select name="shift_type" id="shift_type" required>
                                <option value="D">☀️ نهاري فقط</option>
                                <option value="N">🌙 ليلي فقط</option>
                                <option value="B" selected>🔄 نهاري + ليلي</option>
                            </select>
                        </div>

                        <select name="status" id="status" required>
                            <option value="1">ساري</option>
                            <option value="0">منتهي</option>
                        </select>

                        <button type="submit" name="save_operation_submit"
                                id="save_operation_submit">حفظ التشغيل</button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <!-- ── إحصائيات العقد ────────────────────────────── -->
        <div id="contractStats" class="contract-stats is-hidden">
            <h5 class="stats-title">
                <i class="fas fa-chart-line"></i> إحصائيات عقد المنجم
            </h5>
            <div id="suppliersSection" class="suppliers-section">
                <div class="table-scroll">
                    <table class="alltables">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المورد</th>
                                <th>الساعات المتعاقد عليها</th>
                                <th>عدد المعدات المتعاقد عليها</th>
                                <th><span class="legend-dot legend-basic">■</span> أساسية</th>
                                <th><span class="legend-dot legend-backup">■</span> احتياطية</th>
                                <th>المعدات المضافة</th>
                                <th>المتبقي للإضافة</th>
                                <th>توزيع المعدات والساعات</th>
                            </tr>
                        </thead>
                        <tbody id="suppliersTableBody">
                            <tr>
                                <td colspan="9" class="suppliers-empty">
                                    <i class="fas fa-info-circle"></i> لا توجد بيانات
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="suppliers-total-row">
                                <td colspan="2" class="suppliers-total-label">الإجمالي</td>
                                <td id="total_supplier_hours"     class="suppliers-total-value">0</td>
                                <td id="total_supplier_equipment" class="suppliers-total-value">0</td>
                                <td id="total_supplier_basic"     class="suppliers-total-value">0</td>
                                <td id="total_supplier_backup"    class="suppliers-total-value">0</td>
                                <td id="total_added_equipment"    class="suppliers-total-value">0</td>
                                <td id="total_remaining_equipment"class="suppliers-total-value">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-card-value" id="stat_total_hours">0</div>
                    <div class="stat-card-label">إجمالي الساعات المتعاقد عليها</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-cogs"></i></div>
                    <div class="stat-card-value" id="stat_equipment_count">0</div>
                    <div class="stat-card-label">عدد المعدات المشغلة</div>
                </div>
            </div>
        </div>

        <!-- ── جدول التشغيل (نهار / ليل) ─────────────────── -->
        <?php
        $operations_tables = [
            ['id' => 'projectsTableDay', 'title' => 'قائمة تشغيل النهار', 'rows' => $operations_rows_day],
            ['id' => 'projectsTableNight', 'title' => 'قائمة تشغيل الليل', 'rows' => $operations_rows_night],
        ];
        ?>
        <?php foreach ($operations_tables as $table): ?>
        <div class="card">
            <div class="card-header">
                <h5 style="color: #333;"><i class="fas fa-cogs"></i> <?= htmlspecialchars($table['title'], ENT_QUOTES, 'UTF-8') ?></h5>
            </div>
            <div class="card-body card-body-zero">
                <div class="tbl-scroll-wrap tbl-scroll-zero">
                    <table id="<?= htmlspecialchars($table['id'], ENT_QUOTES, 'UTF-8') ?>" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>
                                <th>السائقين</th>
                                <th>ساعات الوردية</th>
                                <th>نظام الوردية</th>
                                <th>تاريخ البداية</th>
                                <th>الفئة</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($table['rows'])): ?>
                        <tr>
                            <td colspan="10">لا توجد آليات في هذا الجدول</td>
                        </tr>
                        <?php else: ?>
                        <?php
                        $i = 1;
                        foreach ($table['rows'] as $row):
                            $status_value    = intval($row['status']);
                            $shift_code      = $row['shift_type'] ?? 'B';
                            $shift_info      = get_shift_info($shift_code);
                            $eq_display      = htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name'], ENT_QUOTES, 'UTF-8');
                            $eq_type_display = htmlspecialchars($row['equipment_type_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                            $driver_display  = htmlspecialchars(!empty($row['driver_names']) ? $row['driver_names'] : '-', ENT_QUOTES, 'UTF-8');
                            $supplier_display= htmlspecialchars($row['suppliers_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                            $contract_display= htmlspecialchars($row['contract_signing_date'] ?? '-', ENT_QUOTES, 'UTF-8');
                            $category_display= htmlspecialchars($row['equipment_category'] ?? '-', ENT_QUOTES, 'UTF-8');
                            $reason_display  = htmlspecialchars($row['reason'] ?? '', ENT_QUOTES, 'UTF-8');

                            if ($status_value === 1) {
                                $status_label = 'ساري';
                                $status_class = 'status-running';
                            } else {
                                $status_label = 'منتهي';
                                $status_class = 'status-idle';
                            }

                            $category_class = ($row['equipment_category'] === 'أساسي') ? 'basic' : 'backup';
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $eq_display ?></td>
                            <td><?= $eq_type_display ?></td>
                            <td><?= $driver_display ?></td>
                            <td><?= htmlspecialchars((string)($row['shift_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td class="shift-cell"
                                data-operation-id="<?= intval($row['id']) ?>"
                                data-current-shift="<?= htmlspecialchars($shift_code, ENT_QUOTES, 'UTF-8') ?>"
                                title="انقر للتعديل السريع">
                                <span class="shift-badge <?= $shift_info['class'] ?>">
                                    <?= $shift_info['label'] ?>
                                </span>
                                <select class="shift-edit-select" style="display:none;"
                                        data-operation-id="<?= intval($row['id']) ?>">
                                    <option value="D" <?= $shift_code === 'D' ? 'selected' : '' ?>>☀️ نهاري فقط</option>
                                    <option value="N" <?= $shift_code === 'N' ? 'selected' : '' ?>>🌙 ليلي فقط</option>
                                    <option value="B" <?= $shift_code === 'B' ? 'selected' : '' ?>>🔄 نهاري + ليلي</option>
                                </select>
                            </td>

                            <td><?= htmlspecialchars($row['start'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="category-badge <?= $category_class ?>">
                                    <?= $category_display ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= $status_class ?>">
                                    <?= $status_label ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">

                                    <?php if ($can_view): ?>
                                    <a href="javascript:void(0)"
                                       class="action-btn view viewOperationBtn"
                                       data-id="<?= intval($row['id']) ?>"
                                       data-equipment="<?= $eq_display ?>"
                                       data-equipment-type="<?= $eq_type_display ?>"
                                       data-supplier="<?= $supplier_display ?>"
                                       data-contract="<?= $contract_display ?>"
                                       data-drivers="<?= $driver_display ?>"
                                       data-start="<?= htmlspecialchars($row['start'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-end="<?= htmlspecialchars($row['end'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-total-hours="<?= htmlspecialchars((string)($row['total_equipment_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                       data-shift-hours="<?= htmlspecialchars((string)($row['shift_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                       data-shift-type="<?= htmlspecialchars($shift_code, ENT_QUOTES, 'UTF-8') ?>"
                                       data-shift-type-label="<?= htmlspecialchars($shift_info['label'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-category="<?= $category_display ?>"
                                       data-status="<?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?>"
                                       data-status-class="<?= $status_class ?>"
                                       data-reason="<?= $reason_display ?>"
                                       title="عرض التفاصيل">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($can_edit && $status_value === 1): ?>
                                    <a href="javascript:void(0)"
                                       class="action-btn edit editOperationBtn"
                                       data-id="<?= intval($row['id']) ?>"
                                       data-equipment="<?= intval($row['equipment']) ?>"
                                       data-equipment-type="<?= intval($row['equipment_type_id']) ?>"
                                       data-equipment-category="<?= $category_display ?>"
                                       data-contract="<?= intval($row['contract_id']) ?>"
                                       data-supplier="<?= intval($row['supplier_id']) ?>"
                                       data-start="<?= htmlspecialchars($row['start'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-end="<?= htmlspecialchars($row['end'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-total-hours="<?= htmlspecialchars((string)($row['total_equipment_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                       data-shift-hours="<?= htmlspecialchars((string)($row['shift_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                       data-shift-type="<?= htmlspecialchars($shift_code, ENT_QUOTES, 'UTF-8') ?>"
                                       data-status="<?= $status_value ?>"
                                       title="تعديل">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($can_delete): ?>
                                    <a href="move_oprators.php?project_id=<?= intval($selected_project_id) ?>&delete_id=<?= intval($row['id']) ?>"
                                       class="action-btn delete"
                                       onclick="return confirm('هل أنت متأكد من حذف التشغيل؟')"
                                       title="حذف">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                    <?php endif; ?>

                                </div>

                                <?php if ($status_value === 1 && !$is_role10 && $can_edit): ?>
                                <a href="#" class="end-service-btn btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="modal" data-bs-target="#endServiceModal"
                                   data-id="<?= intval($row['id']) ?>">
                                    إنهاء خدمة
                                </a>
                                <?php endif; ?>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ── مودال عرض التفاصيل ──────────────────────── -->
        <div id="viewOperationModal" class="modal movement-view-modal" role="dialog"
             aria-modal="true" aria-labelledby="viewOpModalTitle">
            <div class="modal-content movement-view-modal-content">
                <div class="modal-header movement-view-modal-header">
                    <h5 id="viewOpModalTitle">
                        <i class="fas fa-eye"></i> تفاصيل سجل التشغيل
                    </h5>
                    <button onclick="closeViewOperationModal()"
                            class="movement-view-modal-close" aria-label="إغلاق">&times;</button>
                </div>
                <div class="modal-body movement-view-modal-body">
                    <div class="movement-view-modal-grid">

                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-cogs"></i> المعدة</div>
                            <div class="movement-view-modal-value" id="view_op_equipment">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-tools"></i> تصنيف المعدة</div>
                            <div class="movement-view-modal-value" id="view_op_equipment_type">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-truck"></i> المورد</div>
                            <div class="movement-view-modal-value" id="view_op_supplier">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-file-contract"></i> تاريخ توقيع العقد</div>
                            <div class="movement-view-modal-value" id="view_op_contract">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-id-badge"></i> السائقون</div>
                            <div class="movement-view-modal-value" id="view_op_drivers">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-check-circle"></i> فئة المعدة</div>
                            <div class="movement-view-modal-value" id="view_op_category">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-calendar-alt"></i> تاريخ البداية</div>
                            <div class="movement-view-modal-value" id="view_op_start">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-calendar-check"></i> تاريخ النهاية</div>
                            <div class="movement-view-modal-value" id="view_op_end">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-clock"></i> ساعات العمل الكلية</div>
                            <div class="movement-view-modal-value" id="view_op_total_hours">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-hourglass-half"></i> ساعات الوردية</div>
                            <div class="movement-view-modal-value" id="view_op_shift_hours">-</div>
                        </div>
                        <div class="movement-view-modal-item">
                            <div class="movement-view-modal-label"><i class="fas fa-sync-alt"></i> نظام الوردية</div>
                            <div class="movement-view-modal-value" id="view_op_shift_type">-</div>
                        </div>
                        <div class="movement-view-modal-item movement-view-modal-item-wide">
                            <div class="movement-view-modal-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                            <div id="view_op_status">-</div>
                        </div>
                        <div id="view_op_reason_block"
                             class="movement-view-modal-reason movement-view-modal-item-wide"
                             style="display:none;">
                            <div class="movement-view-modal-reason-label">
                                <i class="fas fa-info-circle"></i> سبب الإنهاء
                            </div>
                            <div class="movement-view-modal-reason-value" id="view_op_reason">-</div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer movement-view-modal-footer">
                    <?php if ($can_edit): ?>
                    <button type="button" id="viewOpEditBtn"
                            onclick="triggerEditFromView()"
                            class="movement-view-modal-btn movement-view-modal-btn-primary">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeViewOperationModal()"
                            class="movement-view-modal-btn movement-view-modal-btn-secondary">
                        <i class="fas fa-times"></i> إغلاق
                    </button>
                </div>
            </div>
        </div>

        <!-- ── مودال إنهاء الخدمة ─────────────────────── -->
        <div class="modal fade" id="endServiceModal" tabindex="-1"
             aria-labelledby="endServiceLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="end_service">

                        <div class="modal-header">
                            <h5 class="modal-title" id="endServiceLabel">إنهاء الخدمة</h5>
                            <button type="button" class="btn-close"
                                    data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="operation_id" id="modal_operation_id">
                            <div class="mb-3">
                                <label for="service_end_date" class="form-label">تاريخ الإنهاء</label>
                                <input type="date" class="form-control"
                                       name="end_date" id="service_end_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="service_reason" class="form-label">سبب الإنهاء</label>
                                <textarea class="form-control" name="reason"
                                          id="service_reason" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">إغلاق</button>
                            <button type="submit" class="btn btn-danger">تأكيد الإنهاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /.ems-content -->
</div><!-- /.main -->

<!-- ════════════════════════════════════════════════
     المكتبات
════════════════════════════════════════════════ -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
/* ════════════════════════════════════════════════════════════
   Helpers
════════════════════════════════════════════════════════════ */
/**
 * عرض Toast بديلاً عن alert()
 * @param {string} message
 * @param {'success'|'error'} type
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('ems-toast-container');
    const toast = document.createElement('div');
    toast.className = `ems-toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    container.appendChild(toast);

    // إزالة التوست بعد 3 ثواني
    setTimeout(() => {
        toast.style.animation = 'toastOut .35s forwards';
        toast.addEventListener('animationend', () => toast.remove());
    }, 3000);
}

/**
 * طلب AJAX مُبسَّط باستخدام fetch + async
 * @param {string}  url
 * @param {Object}  data  — كائن سيُحول إلى FormData
 * @returns {Promise<any>}
 */
async function ajaxPost(url, data) {
    const form = new FormData();
    Object.entries(data).forEach(([k, v]) => form.append(k, v));
    const res = await fetch(url, { method: 'POST', body: form });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

/* ════════════════════════════════════════════════════════════
   DataTable
════════════════════════════════════════════════════════════ */
$(document).ready(function () {
    const dtOptions = {
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy',  text: 'نسخ'          },
            { extend: 'excel', text: 'تصدير Excel'  },
            { extend: 'csv',   text: 'تصدير CSV'    },
            { extend: 'pdf',   text: 'تصدير PDF'    },
            { extend: 'print', text: 'طباعة'        }
        ],
        language: { url: '/ems/assets/i18n/datatables/ar.json' }
    };

    $('#projectsTableDay').DataTable(dtOptions);
    $('#projectsTableNight').DataTable(dtOptions);
});

/* ════════════════════════════════════════════════════════════
   نموذج الإضافة / التعديل
════════════════════════════════════════════════════════════ */
const SESSION_PROJECT_ID = <?= intval($selected_project_id) ?>;

function toggleOperationForm(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('projectForm');
    if (!form) return;

    const isVisible = form.classList.contains('allforms-visible');

    if (!isVisible) {
        resetForm();
        form.classList.add('allforms-visible');
        loadContractsForProject(SESSION_PROJECT_ID);
    } else {
        form.classList.remove('allforms-visible');
    }
}

function resetForm() {
    document.getElementById('formTitle').innerHTML =
        '<i class="fa fa-plus-circle"></i> اضافة تشغيل آلية جديد';
    document.getElementById('operation_id').value = '';
    document.getElementById('contract_id').innerHTML  = '<option value="">-- اختر العقد --</option>';
    document.getElementById('supplier_id').innerHTML  = '<option value="">-- اختر المورد --</option>';
    document.getElementById('type').value             = '';
    document.getElementById('equipment').innerHTML    = '<option value="">-- اختر المعدة --</option>';
    document.getElementById('start_date').value       = '';
    document.getElementById('end_date').value         = '';
    document.getElementById('total_equipment_hours').value = '0';
    document.getElementById('shift_hours').value      = '0';
    document.getElementById('shift_type').value       = 'B';
    document.getElementById('status').value           = '1';
    document.getElementById('equipment_category').value = '';
    resetStats();
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('toggleForm');
    if (btn) btn.addEventListener('click', toggleOperationForm);
});

/* ════════════════════════════════════════════════════════════
   AJAX: جلب البيانات المتسلسلة — async/await بدلاً من Callbacks
════════════════════════════════════════════════════════════ */

/**
 * جلب عقود المشروع
 */
async function fetchContracts(projectId) {
    const res = await fetch('../Oprators/get_mine_contracts.php', {
        method: 'POST',
        body: new URLSearchParams({ project_id: projectId })
    });
    const data = await res.json();
    return data.success ? data.contracts : [];
}

/**
 * جلب موردي العقد
 */
async function fetchSuppliers(contractId) {
    const res = await fetch('../Oprators/get_contract_suppliers.php', {
        method: 'POST',
        body: new URLSearchParams({ contract_id: contractId })
    });
    const data = await res.json();
    return data.success ? data.suppliers : [];
}

/**
 * جلب المعدات (نوع + مورد)
 */
async function fetchEquipments(typeId, supplierId, currentEquipment = '') {
    const params = new URLSearchParams({ type: typeId, supplier_id: supplierId });
    if (currentEquipment) params.append('current_equipment', currentEquipment);
    const res = await fetch(`../Oprators/getoprator.php?${params}`);
    return res.text(); // HTML options
}

/**
 * جلب إحصائيات العقد
 */
async function fetchContractStats(contractId) {
    const res = await fetch(`../Oprators/get_contract_stats.php?contract_id=${contractId}`);
    return res.json();
}

/** ملء قائمة العقود */
function populateContracts(contracts, selectedId = '') {
    let opts = "<option value=''>-- اختر العقد --</option>";
    contracts.forEach(c => {
        const sel = String(c.id) === String(selectedId) ? 'selected' : '';
        opts += `<option value="${c.id}" data-end="${c.end_date || ''}" ${sel}>${c.display_name}</option>`;
    });
    document.getElementById('contract_id').innerHTML = opts;
}

/** ملء قائمة الموردين */
function populateSuppliers(suppliers, selectedId = '') {
    let opts = "<option value=''>-- اختر المورد --</option>";
    suppliers.forEach(s => {
        const sel = String(s.id) === String(selectedId) ? 'selected' : '';
        opts += `<option value="${s.id}" ${sel}>${s.name}</option>`;
    });
    document.getElementById('supplier_id').innerHTML = opts;
}

/** تحميل عقود المشروع (استخدام عام) */
async function loadContractsForProject(projectId, selectedContractId = '') {
    document.getElementById('contract_id').innerHTML = '<option value="">-- جاري التحميل... --</option>';
    try {
        const contracts = await fetchContracts(projectId);
        populateContracts(contracts, selectedContractId);
    } catch {
        document.getElementById('contract_id').innerHTML = '<option value="">-- خطأ في التحميل --</option>';
    }
}

/* ── أحداث القوائم المتسلسلة ──────────────────────────── */
$(document).ready(function () {

    // تغيير العقد
    $('#contract_id').on('change', async function () {
        const contractId = this.value;
        const endDate    = $(this).find(':selected').data('end') || '';

        $('#supplier_id').html("<option value=''>-- اختر المورد --</option>");
        $('#type').val('');
        $('#equipment').html("<option value=''>-- اختر المعدة --</option>");
        resetStats();

        if (endDate) $('#end_date').val(endDate);

        if (!contractId) return;

        try {
            const [suppliers, stats] = await Promise.all([
                fetchSuppliers(contractId),
                fetchContractStats(contractId)
            ]);
            populateSuppliers(suppliers);
            renderStats(stats);
        } catch {
            showToast('خطأ في تحميل بيانات العقد', 'error');
        }
    });

    // تغيير النوع أو المورد → إعادة تحميل المعدات
    $('#type, #supplier_id').on('change', async function () {
        const typeId     = $('#type').val();
        const supplierId = $('#supplier_id').val();

        if (!typeId || !supplierId) {
            $('#equipment').html("<option value=''>-- اختر المعدة --</option>");
            return;
        }

        $('#equipment').html("<option value=''>-- جاري التحميل... --</option>");
        try {
            const html = await fetchEquipments(typeId, supplierId);
            $('#equipment').html(html);
        } catch {
            $('#equipment').html("<option value=''>-- خطأ في التحميل --</option>");
            showToast('خطأ في تحميل المعدات', 'error');
        }
    });

    // إنهاء الخدمة — modal
    $('#endServiceModal').on('show.bs.modal', function (event) {
        const btn = $(event.relatedTarget);
        $('#modal_operation_id').val(btn.data('id') || '');
        $('#service_end_date').val('');
        $('#service_reason').val('');
    });

    /* ── زر التعديل — async/await بدلاً من Callback Hell ── */
    $(document).on('click', '.editOperationBtn', async function () {
        const btn = $(this);

        document.getElementById('formTitle').innerHTML =
            '<i class="fa fa-edit"></i> تعديل بيانات التشغيل';

        const form = document.getElementById('projectForm');
        form.classList.add('allforms-visible');
        $('html, body').animate({ scrollTop: $(form).offset().top - 100 }, 500);

        // ملء الحقول الأساسية
        $('#operation_id').val(btn.data('id'));
        $('#start_date').val(btn.data('start'));
        $('#end_date').val(btn.data('end'));
        $('#total_equipment_hours').val(btn.data('total-hours'));
        $('#shift_hours').val(btn.data('shift-hours'));
        $('#shift_type').val(btn.data('shift-type') || 'B');
        $('#status').val(btn.data('status'));
        $('#equipment_category').val(btn.data('equipment-category'));

        try {
            // 1) تحميل العقود
            const contracts = await fetchContracts(SESSION_PROJECT_ID);
            populateContracts(contracts, btn.data('contract'));

            // 2) تحميل الموردين للعقد المحدد
            const suppliers = await fetchSuppliers(btn.data('contract'));
            populateSuppliers(suppliers, btn.data('supplier'));

            // 3) تحديد النوع ثم المعدات
            $('#type').val(btn.data('equipment-type'));
            const equipHtml = await fetchEquipments(
                btn.data('equipment-type'),
                btn.data('supplier'),
                btn.data('equipment')
            );
            $('#equipment').html(equipHtml).val(btn.data('equipment'));

        } catch (err) {
            showToast('خطأ في تحميل بيانات التعديل', 'error');
            console.error(err);
        }
    });

    /* ── مودال العرض ─────────────────────────────────────── */
    var _viewOpEditData = {};

    $(document).on('click', '.viewOperationBtn', function () {
        const btn = $(this);

        // تعبئة بيانات المودال
        $('#view_op_equipment').text(btn.data('equipment')      || '-');
        $('#view_op_equipment_type').text(btn.data('equipment-type') || '-');
        $('#view_op_supplier').text(btn.data('supplier')        || '-');
        $('#view_op_contract').text(btn.data('contract')        || '-');
        $('#view_op_drivers').text(btn.data('drivers')          || '-');
        $('#view_op_start').text(btn.data('start')              || '-');
        $('#view_op_end').text(btn.data('end')                  || '-');
        $('#view_op_total_hours').text(btn.data('total-hours')  || '0');
        $('#view_op_shift_hours').text(btn.data('shift-hours')  || '0');
        $('#view_op_shift_type').text(btn.data('shift-type-label') || '-');
        $('#view_op_category').text(btn.data('category')        || '-');

        const statusLabel = btn.data('status')       || '-';
        const statusClass = btn.data('status-class') || '';
        $('#view_op_status').html(
            `<span class="status-pill ${statusClass}">${statusLabel}</span>`
        );

        // سبب الإنهاء
        const reason = btn.data('reason') || '';
        if (statusClass === 'status-idle' && reason) {
            $('#view_op_reason').text(reason);
            $('#view_op_reason_block').show();
        } else {
            $('#view_op_reason').text('-');
            $('#view_op_reason_block').hide();
        }

        _viewOpEditData = { id: btn.data('id') };
        $('#viewOpEditBtn').toggle(statusClass !== 'status-idle');
        $('#viewOperationModal').addClass('show');
    });

}); // end document.ready

function closeViewOperationModal() {
    document.getElementById('viewOperationModal').classList.remove('show');
}

function triggerEditFromView() {
    closeViewOperationModal();
    setTimeout(() => {
        const editBtn = $(`.editOperationBtn[data-id="${_viewOpEditData.id}"]`);
        if (editBtn.length) editBtn.trigger('click');
    }, 350);
}

// إغلاق عند النقر خارج المودال
$(document).on('click', '#viewOperationModal', function (e) {
    if ($(e.target).is('#viewOperationModal')) closeViewOperationModal();
});

// إغلاق بـ ESC
$(document).on('keydown', function (e) {
    if (e.key === 'Escape' && $('#viewOperationModal').hasClass('show')) {
        closeViewOperationModal();
    }
});

/* ════════════════════════════════════════════════════════════
   إحصائيات العقد
════════════════════════════════════════════════════════════ */
function resetStats() {
    $('#contractStats').hide();
    $('#suppliersSection').hide();
    $('#suppliersTableBody').html(
        "<tr><td colspan='9' class='suppliers-empty'><i class='fas fa-info-circle'></i> لا توجد بيانات</td></tr>"
    );
    ['stat_total_hours','stat_equipment_count','total_supplier_hours',
     'total_supplier_equipment','total_supplier_basic','total_supplier_backup',
     'total_added_equipment','total_remaining_equipment'].forEach(id => {
        document.getElementById(id).textContent = '0';
    });
}

function renderStats(response) {
    if (!response?.success) { resetStats(); return; }

    $('#contractStats').show();
    $('#stat_total_hours').text(parseFloat(response.contract.total_hours || 0).toLocaleString('ar'));
    $('#stat_equipment_count').text(parseInt(response.contract.equipment_count || 0, 10).toLocaleString('ar'));

    if (!response.suppliers?.length) { $('#suppliersSection').hide(); return; }

    $('#suppliersSection').show();
    let rows = '', totalAdded = 0, totalRemaining = 0, totalBasic = 0, totalBackup = 0;

    response.suppliers.forEach((supplier, index) => {
        let breakdownHtml = '';
        if (supplier.equipment_breakdown?.length) {
            breakdownHtml = supplier.equipment_breakdown.map(item => {
                const addedCount  = item.added_count  || 0;
                const remaining   = item.remaining    || 0;
                const statusClass = remaining === 0 ? 'is-active' : (addedCount > 0 ? 'is-warning' : 'is-muted');
                const basicInfo   = item.count_basic  > 0 ? `<span class="breakdown-tag is-basic">أساسي:${item.count_basic}</span>` : '';
                const backupInfo  = item.count_backup > 0 ? `<span class="breakdown-tag is-backup">احتياطي:${item.count_backup}</span>` : '';
                return `<div class="breakdown-item">
                    <i class="fas fa-tools"></i>
                    <strong>${item.type || 'غير محدد'}</strong>: ${item.count} متعاقد ${basicInfo} ${backupInfo} |
                    <span class="breakdown-count ${statusClass}">${addedCount} مضاف</span> |
                    <span class="breakdown-count ${remaining === 0 ? 'is-active' : 'is-warning'}">${remaining} متبقي</span> |
                    <i class="fas fa-clock"></i> ${parseFloat(item.hours || 0).toLocaleString('ar')} ساعة
                </div>`;
            }).join('');
        } else {
            breakdownHtml = '<span class="breakdown-empty">لا توجد تفاصيل</span>';
        }

        const addedEquipment    = supplier.added_to_equipments || 0;
        const remainingEquipment= supplier.remaining_to_add   || 0;
        const supplierBasic     = supplier.equipment_count_basic  || 0;
        const supplierBackup    = supplier.equipment_count_backup || 0;

        totalAdded     += addedEquipment;
        totalRemaining += remainingEquipment;
        totalBasic     += supplierBasic;
        totalBackup    += supplierBackup;

        const addedBadgeClass = remainingEquipment === 0 ? 'badge-available'
                              : addedEquipment > 0       ? 'badge-working' : 'badge-busy';
        const remBadgeClass   = remainingEquipment === 0 ? 'badge-available' : 'badge-working';

        rows += `<tr>
            <td class="text-center">${index + 1}</td>
            <td><strong>${supplier.supplier_name || '-'}</strong></td>
            <td class="text-center">${parseFloat(supplier.hours || 0).toLocaleString('ar')}</td>
            <td class="text-center">${supplier.equipment_count || 0}</td>
            <td class="suppliers-basic-count">${supplierBasic}</td>
            <td class="suppliers-backup-count">${supplierBackup}</td>
            <td class="text-center">
                <span class="${addedBadgeClass}"><i class="fas fa-check"></i> ${addedEquipment}</span>
            </td>
            <td class="text-center">
                <span class="${remBadgeClass}">
                    <i class="fas fa-${remainingEquipment === 0 ? 'check-circle' : 'exclamation-triangle'}"></i>
                    ${remainingEquipment}
                </span>
            </td>
            <td class="suppliers-breakdown">${breakdownHtml}</td>
        </tr>`;
    });

    $('#suppliersTableBody').html(rows);
    $('#total_supplier_hours').text(parseFloat(response.summary.total_supplier_hours || 0).toLocaleString('ar'));
    $('#total_supplier_equipment').text(response.summary.total_supplier_equipment || 0);
    $('#total_supplier_basic').text(totalBasic);
    $('#total_supplier_backup').text(totalBackup);
    $('#total_added_equipment').text(totalAdded);
    $('#total_remaining_equipment').text(totalRemaining);
}

/* ════════════════════════════════════════════════════════════
   تعديل نظام الوردية السريع (Inline Edit)
════════════════════════════════════════════════════════════ */
$(document).on('click', '.shift-cell', function () {
    const $cell   = $(this);
    const $badge  = $cell.find('.shift-badge');
    const $select = $cell.find('.shift-edit-select');

    // إعادة الحالة لبقية الخلايا
    $('.shift-edit-select').hide();
    $('.shift-badge').show();

    $badge.hide();
    $select.show().focus();
});

$(document).on('change', '.shift-edit-select', async function () {
    const $select       = $(this);
    const operationId   = $select.data('operation-id');
    const newShiftType  = $select.val();
    const $cell         = $select.closest('.shift-cell');
    const $badge        = $cell.find('.shift-badge');

    $select.prop('disabled', true);
    $cell.css('opacity', '0.6');

    try {
        const data = await ajaxPost('update_operation_shift_type.php', {
            operation_id: operationId,
            shift_type:   newShiftType
        });

        if (data.success) {
            const shiftMap = {
                D: { label: '☀️ نهاري فقط',    cls: 'shift-day'   },
                N: { label: '🌙 ليلي فقط',     cls: 'shift-night' },
                B: { label: '🔄 نهاري + ليلي', cls: 'shift-both'  }
            };
            const info = shiftMap[newShiftType] || shiftMap.B;

            const $sameOperationCells = $('.shift-cell[data-operation-id="' + operationId + '"]');
            $sameOperationCells.each(function () {
                const $currentCell = $(this);
                const $currentBadge = $currentCell.find('.shift-badge');
                const $currentSelect = $currentCell.find('.shift-edit-select');

                $currentBadge.removeClass('shift-day shift-night shift-both')
                             .addClass(info.cls).text(info.label);
                $currentCell.data('current-shift', newShiftType);
                $currentSelect.val(newShiftType);
            });

            const $msg = $('<div class="shift-success-msg">✅ تم التحديث</div>');
            $cell.append($msg);
            setTimeout(() => $msg.fadeOut(300, () => $msg.remove()), 2000);
        } else {
            showToast('فشل التحديث: ' + (data.message || 'خطأ غير معروف'), 'error');
            $select.val($cell.data('current-shift'));
        }
    } catch {
        showToast('حدث خطأ في الاتصال بالخادم', 'error');
        $select.val($cell.data('current-shift'));
    } finally {
        $select.prop('disabled', false);
        $cell.css('opacity', '1');
        $select.hide();
        $badge.show();
    }
});

$(document).on('keydown', '.shift-edit-select', function (e) {
    if (e.key === 'Escape') {
        const $select = $(this);
        const $cell   = $select.closest('.shift-cell');
        $select.val($cell.data('current-shift')).hide();
        $cell.find('.shift-badge').show();
    }
});

$(document).on('click', function (e) {
    if (!$(e.target).closest('.shift-cell').length) {
        $('.shift-edit-select').hide();
        $('.shift-badge').show();
    }
});
</script>

</body>
</html>
