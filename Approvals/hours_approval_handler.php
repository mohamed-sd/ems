<?php
/**
 * hours_approval_handler.php
 * معالج API لنظام اعتماد ساعات العمل
 * يقبل طلبات POST فقط ويعيد JSON
 */

session_start();

require_once '../config.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

$role       = strval($_SESSION['user']['role']);
$user_id    = intval($_SESSION['user']['id']);
$user_name  = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);

// الأدوار المسموح لها: المدراء الرئيسيون والأدمن
$allowed_roles = EMS_ROLES_HOURS_APPROVAL_ACCESS; // فهرس ثوابت الأدوار (ADR-07)
if (!in_array($role, $allowed_roles)) {
    die(json_encode(['success' => false, 'message' => 'ليس لديك صلاحية'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE));
}

// جدولا timesheet_approvals/timesheet_approval_notes قائمان ومسجَّلان في السجل —
// سقطت الهجرتان الذاتيتان CREATE TABLE IF NOT EXISTS بسقوط نمط ems_runtime_ddl.
// العزل عبر بوابة المستأجر. (الحدّ المقدّس: خطّاف نشر الناقل في فرع الاعتماد الرابع
// يبقى بايت-مطابقًا — لا يُمَسّ.)
$th_gate = ems_tenant_db();

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// ── خريطة مستوى الاعتماد لكل دور ─────────────────────────
$role_level_map = ['1' => 1, '2' => 2, '3' => 3, '4' => 4];

function role_label_ar($role_id) {
    $role_id = strval($role_id);
    switch ($role_id) {
        case '-1': return 'الأدمن';
        case '1':  return 'مدير المشاريع';
        case '2':  return 'مدير الموردين';
        case '3':  return 'مدير الأسطول';
        case '4':  return 'مدير المشغلين';
        case '5':  return 'مدير الموقع';
        case '6':  return 'مدخل الساعات';
        case '7':  return 'مراجع الموردين';
        case '8':  return 'مراجع المشغلين';
        case '9':  return 'مراجع الأعطال';
        case '10': return 'مدير الحركة والتشغيل';
        default:   return 'غير محدد';
    }
}

// ─────────────────────────────────────────────────────────────
// اعتماد سجل / مجموعة سجلات
// ─────────────────────────────────────────────────────────────
if ($action === 'approve') {
    if (!isset($role_level_map[$role])) {
        die(json_encode(['success' => false, 'message' => 'الأدمن لا يعتمد مباشرة'], JSON_UNESCAPED_UNICODE));
    }
    $my_level = $role_level_map[$role];
    $prev_level = $my_level - 1;

    $ids_raw = isset($_POST['ids']) ? $_POST['ids'] : '';
    $ids = [];
    foreach (explode(',', $ids_raw) as $i) {
        $i = intval(trim($i));
        if ($i > 0) $ids[] = $i;
    }
    if (empty($ids)) {
        die(json_encode(['success' => false, 'message' => 'لم يتم تحديد أي سجل'], JSON_UNESCAPED_UNICODE));
    }

    $approved  = 0;
    $skipped   = 0;
    $escaped_name = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');

    foreach ($ids as $ts_id) {
        // التحقق من أن السجل ينتمي لنفس الشركة (البوابة تعزل بالشركة؛ فرع
        // company_id IS NULL الموروث ميتٌ — صفر صفوفٍ بلا شركةٍ قِيست).
        try { $chk = $th_gate->selectOne('timesheet', array('columns' => array('id'), 'where' => array('id' => $ts_id))); }
        catch (\Throwable $t) { $chk = null; error_log('hours_approval approve chk: ' . $t->getMessage()); }
        if ($chk === null) { $skipped++; continue; }

        // التحقق من اعتماد المستوى السابق (ما عدا المستوى الأول)
        if ($prev_level > 0) {
            try { $prev_chk = $th_gate->selectOne('timesheet_approvals', array('columns' => array('id'),
                'where' => array('timesheet_id' => $ts_id, 'approval_level' => $prev_level, 'status' => 1))); }
            catch (\Throwable $t) { $prev_chk = null; }
            if ($prev_chk === null) { $skipped++; continue; }
        }

        // تجنب تكرار الاعتماد
        try { $dup = $th_gate->selectOne('timesheet_approvals', array('columns' => array('id'),
            'where' => array('timesheet_id' => $ts_id, 'approval_level' => $my_level))); }
        catch (\Throwable $t) { $dup = null; }
        if ($dup !== null) { $skipped++; continue; }

        // إدراج الموافقة عبر البوابة (company_id تحقنه من سياق الجلسة، ويعيد المعرّف الوليد)
        $ins_id = 0;
        try {
            $ins_id = intval($th_gate->insert('timesheet_approvals', array(
                'timesheet_id' => $ts_id, 'approval_level' => $my_level,
                'approved_by' => $user_id, 'approved_by_name' => ($_SESSION['user']['name'] ?? 'غير معروف'))));
        } catch (\Throwable $t) { error_log('hours_approval insert: ' . $t->getMessage()); }
        if ($ins_id > 0) $approved++;

        // ── K5 (ADR-10) · إضافة معزولة — لا تمسّ منطق الموافقات الأربع ──
        // تُستدعى بعد نجاح إدراج الموافقة الرابعة الأخيرة حصرًا؛ خلف علَم
        // EMS_EVENT_HOOKS (off افتراضًا = صفر أثر)؛ تبتلع كل أخطائها داخلها —
        // الاعتماد يكتمل دائمًا مهما جرى للنشر (خطة K5 §8.2 المُقرّة).
        // [الحدّ المقدّس] المعرّف الوليد الآن من البوابة بدل $conn->insert_id، والخطّاف
        // يُستدعى بـ$conn نفسه بلا مساس — الناقل غير مَمسوس.
        if ($ins_id > 0 && $my_level === 4) {
            $__k5_gen = $ins_id;
            require_once __DIR__ . '/../includes/timesheet_event_hook.php';
            ems_timesheet_event_hook($conn, $ts_id, $__k5_gen, $user_id);
        }

        // ── §9-③ طور الكتابة المزدوجة (خلف EMS_UNIT_DUAL_WRITE، fail-closed) ──
        // مرآةُ الاعتماد إلى السجل القانوني: مسجِّلُ تاريخٍ لا مُنفِذُ قواعد —
        // تبتلع أخطاءها كخطّاف K5 أعلاه؛ الاعتمادُ الحي يكتمل مهما جرى للمرآة.
        if ($ins_id > 0) {
            try {
                require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';
                if (\App\Services\Unit\TimesheetEntryService::dualWriteOn()) {
                    // الصفُّ قد يسبق تفعيلَ العلم — المرآةُ تُستكمل عند أول اعتمادٍ بعده
                    $__dwm = \App\Services\Unit\TimesheetEntryService::mirrorFromTimesheet($conn, $th_gate, $ts_id, $user_id);
                    if (!empty($__dwm['ok'])) {
                        \App\Services\Unit\TimesheetEntryService::mirrorApproval($conn, $th_gate, $ts_id, $my_level, $user_id);
                    } else {
                        error_log('unit dual-write approve-mirror ts#' . $ts_id . ': ' . (isset($__dwm['skipped']) ? $__dwm['skipped'] : 'فشل'));
                    }
                }
            } catch (\Throwable $__dwT) {
                error_log('unit dual-write approve-mirror ts#' . $ts_id . ': ' . $__dwT->getMessage());
            }
        }
    }

    // D02 §5: باكتمال المستوى الرابع تصير الحقيقة تشغيليةً كاملة — ولا تصبح
    // استحقاقًا ماليًّا إلا بختم المالية. نُعلم المعتمِد بذلك صراحةً (لا صمت).
    $__gate_on = function_exists('ems_env')
        && strtolower((string) ems_env('EMS_UNIT_CONVERT_GATE', 'off')) === 'on';
    $__msg = "تم اعتماد $approved سجل" . ($skipped ? " (تم تخطي $skipped)" : '');
    if ($__gate_on && $my_level === 4 && $approved > 0) {
        $__msg .= ' — اكتمل الاعتماد التشغيلي، وبانتظار التحويل المالي';
    }

    echo json_encode([
        'success' => true,
        'message' => $__msg,
        'approved' => $approved,
        'skipped'  => $skipped
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────
// إضافة ملاحظة على سجل / عمود محدد
// ─────────────────────────────────────────────────────────────
if ($action === 'add_note') {
    $ts_id       = intval($_POST['timesheet_id'] ?? 0);
    $col_name    = mysqli_real_escape_string($conn, trim($_POST['column_name'] ?? ''));
    $col_label   = mysqli_real_escape_string($conn, trim($_POST['column_label'] ?? $col_name));
    $note_text   = mysqli_real_escape_string($conn, trim($_POST['note_text'] ?? ''));
    $escaped_name = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');

    if ($ts_id <= 0 || $note_text === '') {
        die(json_encode(['success' => false, 'message' => 'بيانات ناقصة'], JSON_UNESCAPED_UNICODE));
    }

    // التحقق من أن السجل ينتمي لنفس الشركة (عبر البوابة)
    try { $chk = $th_gate->selectOne('timesheet', array('columns' => array('id'), 'where' => array('id' => $ts_id))); }
    catch (\Throwable $t) { $chk = null; error_log('hours_approval note chk: ' . $t->getMessage()); }
    if ($chk === null) {
        die(json_encode(['success' => false, 'message' => 'السجل غير موجود'], JSON_UNESCAPED_UNICODE));
    }

    // القيم الخام تُمرَّر مباشرةً للبوابة (تستعمل عبارات مُجهّزة داخليًا) — company_id يُحقَن
    $note_ok = false;
    try {
        $th_gate->insert('timesheet_approval_notes', array(
            'timesheet_id' => $ts_id, 'column_name' => trim($_POST['column_name'] ?? ''),
            'column_label' => trim($_POST['column_label'] ?? ($_POST['column_name'] ?? '')),
            'note_text' => trim($_POST['note_text'] ?? ''),
            'created_by' => $user_id, 'created_by_name' => ($_SESSION['user']['name'] ?? 'غير معروف')));
        $note_ok = true;
    } catch (\Throwable $t) { error_log('hours_approval add_note: ' . $t->getMessage()); }

    if ($note_ok) {
        echo json_encode(['success' => true, 'message' => 'تمت إضافة الملاحظة'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل حفظ الملاحظة: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// جلب ملاحظات سجل معين
// ─────────────────────────────────────────────────────────────
if ($action === 'get_notes') {
    $ts_id = intval($_POST['timesheet_id'] ?? 0);
    if ($ts_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'معرف غير صحيح'], JSON_UNESCAPED_UNICODE));
    }

    // الملاحظات (نطاق n=timesheet_approval_notes، إثراء u=users LEFT) — البوابة تعزل
    $res = array();
    try {
        $res = $th_gate->scopedQuery(array('scope' => array('n' => 'timesheet_approval_notes'), 'enrich' => array('u' => 'users')),
            "SELECT n.id, n.column_label, n.note_text, n.created_by_name, n.created_at,
                COALESCE(u.role, '') AS created_by_role
         FROM timesheet_approval_notes n
         LEFT JOIN users u ON u.id = n.created_by
         WHERE n.timesheet_id = ? AND n.status = 1 AND {TENANT_SCOPE}
         ORDER BY n.created_at ASC", array($ts_id));
    } catch (\Throwable $t) { error_log('hours_approval get_notes: ' . $t->getMessage()); }

    $notes = [];
    foreach ($res as $row) {
        $row['created_by_role_label'] = role_label_ar($row['created_by_role']);
        $notes[] = $row;
    }
    echo json_encode(['success' => true, 'notes' => $notes], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────
// حذف ملاحظة (للمنشئ فقط)
// ─────────────────────────────────────────────────────────────
if ($action === 'delete_note') {
    echo json_encode(['success' => false, 'message' => 'حذف الملاحظات غير مسموح'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────
// رفض سجل مع ملاحظة — يُعيد السجل إلى أول السلسلة
// ─────────────────────────────────────────────────────────────
if ($action === 'reject') {
    if ($role === '-1') {
        die(json_encode(['success' => false, 'message' => 'الأدمن لا يرفض مباشرة'], JSON_UNESCAPED_UNICODE));
    }
    if (!isset($role_level_map[$role])) {
        die(json_encode(['success' => false, 'message' => 'ليس لديك صلاحية الرفض'], JSON_UNESCAPED_UNICODE));
    }

    $ts_id  = intval($_POST['timesheet_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($ts_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'معرّف السجل غير صحيح'], JSON_UNESCAPED_UNICODE));
    }
    if ($reason === '') {
        die(json_encode(['success' => false, 'message' => 'يجب كتابة سبب الرفض'], JSON_UNESCAPED_UNICODE));
    }

    // التحقق من أن السجل ينتمي لنفس الشركة (عبر البوابة)
    try { $chk = $th_gate->selectOne('timesheet', array('columns' => array('id'),
        'where' => array('id' => $ts_id, 'status' => 1))); }
    catch (\Throwable $t) { $chk = null; error_log('hours_approval reject chk: ' . $t->getMessage()); }
    if ($chk === null) {
        die(json_encode(['success' => false, 'message' => 'السجل غير موجود أو لا تملك صلاحية الوصول'], JSON_UNESCAPED_UNICODE));
    }

    $my_level = $role_level_map[$role];

    // حذف جميع اعتمادات هذا السجل (إعادته إلى الصفر) — عبر البوابة: جلب المعرّفات ثم
    // deleteRow لكلٍّ (معزولٌ بالشركة، مطابقٌ سلوكيًا لحذف الكل بشرط timesheet_id).
    $del_ok = false;
    try {
        $appr_ids = $th_gate->select('timesheet_approvals', array('columns' => array('id'), 'where' => array('timesheet_id' => $ts_id)));
        foreach ($appr_ids as $ar) { $th_gate->deleteRow('timesheet_approvals', $ar['id'], 'approval reset on reject'); }
        $del_ok = true;
    } catch (\Throwable $t) { error_log('hours_approval reject del: ' . $t->getMessage()); }

    // تسجيل سبب الرفض في جدول الملاحظات (عبر البوابة)
    $rej_label = 'رفض المستوى ' . $my_level . ' — ' . role_label_ar($role);
    $rej_ins_ok = false;
    try {
        $th_gate->insert('timesheet_approval_notes', array(
            'timesheet_id' => $ts_id, 'column_name' => 'rejection', 'column_label' => $rej_label,
            'note_text' => $reason, 'created_by' => $user_id, 'created_by_name' => ($_SESSION['user']['name'] ?? 'غير معروف')));
        $rej_ins_ok = true;
    } catch (\Throwable $t) { error_log('hours_approval reject note: ' . $t->getMessage()); }

    if ($del_ok && $rej_ins_ok) {
        echo json_encode([
            'success' => true,
            'message' => 'تم رفض السجل #' . $ts_id . ' وإعادته إلى أول السلسلة'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل تنفيذ الرفض: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

die(json_encode(['success' => false, 'message' => 'إجراء غير معروف'], JSON_UNESCAPED_UNICODE));
