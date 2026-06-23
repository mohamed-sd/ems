<?php
/**
 * Maintenance/orders.php — أوامر الصيانة (المحور).
 * دورة الحياة: بلاغ → تنفيذ → فحص → إغلاق (+ ملغى). بلا اعتماد (DEC-14).
 *
 * التصميم: نفس هوية بقية الشاشات (العملاء/الموردين/الأسطول):
 *   - القائمة: بطاقات إحصائية + جدول DataTables + نافذة عرض موحّدة EmsDetailsModal.
 *   - التحرير: فورم مقسّم بهوية .allforms + إدارة أسطر العمالة/القطع عبر AJAX
 *     (دون إعادة تحميل الصفحة) — يمنع فقدان البيانات المُدخَلة عند إضافة سطر.
 *
 * منطق الحالة (القسم 6): فتح الأمر ⇒ operations.equipment_health='معطلة' (للتشغيل الساري
 *   فقط، دون لمس status/الإتاحة). الإغلاق (actions_taken + root_cause + inspection_result='ناجح')
 *   ⇒ 'سليمة' + availability_status='متاحة للعمل' + last_maintenance_date + إعادة جدولة الخطة.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/mnt_helpers.php';

$current_role    = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin  = ($current_role === '-1');
$company_id      = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$page_permissions = check_page_permissions($conn, 'Maintenance/orders.php');
$can_view   = $is_super_admin ? true : $page_permissions['can_view'];
$can_add    = $is_super_admin ? true : $page_permissions['can_add'];
$can_edit   = $is_super_admin ? true : $page_permissions['can_edit'];
$can_delete = $is_super_admin ? true : $page_permissions['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أوامر+الصيانة+❌");
    exit();
}

$company_scope_sql = $is_super_admin ? "1=1" : "o.company_id = " . intval($company_id);

$states       = array('بلاغ', 'تنفيذ', 'فحص', 'إغلاق', 'ملغى');
$active_states = array('بلاغ', 'تنفيذ', 'فحص');
$sources      = array('بلاغ', 'وقائي', 'تفتيش');
$cost_parties = array('داخلي', 'خارجي');
$priorities   = array('عادية', 'متوسطة', 'عالية', 'عاجلة');
$maint_types  = array('إصلاح عطل', 'صيانة وقائية', 'فحص فني', 'استبدال قطعة', 'أخرى');

// ── دالة جلب أمر مقيّد بالشركة ──
function mnt_fetch_order($conn, $id, $company_id, $is_super_admin)
{
    $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $sql = "SELECT * FROM mnt_order WHERE id = " . intval($id) . " AND COALESCE(is_deleted,0)=0" . $scope . " LIMIT 1";
    $res = mysqli_query($conn, $sql);
    return $res ? mysqli_fetch_assoc($res) : null;
}

// ── مجاميع تكاليف أمر (لردود AJAX) ──
function mnt_order_totals($conn, $oid, $company_id)
{
    $out = array('labor' => 0.0, 'parts' => 0.0, 'external' => 0.0, 'total' => 0.0);
    if ($s = mysqli_prepare($conn, "SELECT labor_cost, parts_cost, external_cost, total_cost FROM mnt_order WHERE id=? AND company_id=?")) {
        mysqli_stmt_bind_param($s, 'ii', $oid, $company_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        if ($r && ($a = mysqli_fetch_assoc($r))) {
            $out = array('labor' => (float) $a['labor_cost'], 'parts' => (float) $a['parts_cost'], 'external' => (float) $a['external_cost'], 'total' => (float) $a['total_cost']);
        }
        mysqli_stmt_close($s);
    }
    return $out;
}

function mnt_json($data)
{
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// AJAX: إدارة أسطر العمالة/القطع دون إعادة تحميل (يحافظ على بيانات الفورم)
// ══════════════════════════════════════════════════════════════════════════════
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax
    && in_array($_POST['action'] ?? '', array('add_labor', 'add_part', 'del_labor', 'del_part'), true)) {

    if (!$can_edit) { mnt_json(array('success' => false, 'message' => 'لا توجد صلاحية للتعديل')); }
    $oid = intval($_POST['order_id'] ?? 0);
    $order = mnt_fetch_order($conn, $oid, $company_id, $is_super_admin);
    if (!$order) { mnt_json(array('success' => false, 'message' => 'الأمر غير موجود')); }
    if ($order['state'] === 'إغلاق' || $order['state'] === 'ملغى') {
        mnt_json(array('success' => false, 'message' => 'لا يمكن تعديل أسطر أمر مغلق/ملغى'));
    }
    $act = $_POST['action'];

    if ($act === 'add_labor') {
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $role  = trim($_POST['labor_role'] ?? '');
        $hours = floatval($_POST['hours'] ?? 0);
        $rate  = floatval($_POST['hourly_rate'] ?? 0);
        $cost  = $hours * $rate;
        $lineId = 0;
        if ($s = mysqli_prepare($conn, "INSERT INTO mnt_order_labor (company_id, order_id, employee_id, role, hours, hourly_rate, cost) VALUES (?,?,?,?,?,?,?)")) {
            mysqli_stmt_bind_param($s, 'iiisddd', $company_id, $oid, $employee_id, $role, $hours, $rate, $cost);
            mysqli_stmt_execute($s); $lineId = mysqli_insert_id($conn); mysqli_stmt_close($s);
        }
        mnt_recalc_order_totals($conn, $oid, $company_id);
        $emp = '';
        if ($employee_id && ($s = mysqli_prepare($conn, "SELECT name FROM users WHERE id=?"))) {
            mysqli_stmt_bind_param($s, 'i', $employee_id); mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s); if ($r && ($x = mysqli_fetch_assoc($r))) { $emp = $x['name']; }
            mysqli_stmt_close($s);
        }
        mnt_json(array('success' => true, 'line' => array('id' => $lineId, 'emp' => $emp, 'role' => $role, 'hours' => $hours, 'hourly_rate' => $rate, 'cost' => $cost), 'totals' => mnt_order_totals($conn, $oid, $company_id)));
    }

    if ($act === 'add_part') {
        $part_name = trim($_POST['part_name'] ?? '');
        if ($part_name === '') { mnt_json(array('success' => false, 'message' => 'اسم القطعة مطلوب')); }
        $category = trim($_POST['category'] ?? '');
        $qty  = floatval($_POST['quantity'] ?? 1);
        $unit = floatval($_POST['unit_cost'] ?? 0);
        $subtotal = $qty * $unit;
        $is_major = isset($_POST['is_major_component']) ? 1 : 0;
        $lineId = 0;
        if ($s = mysqli_prepare($conn, "INSERT INTO mnt_order_part (company_id, order_id, part_name, category, quantity, unit_cost, subtotal, is_major_component) VALUES (?,?,?,?,?,?,?,?)")) {
            mysqli_stmt_bind_param($s, 'iissdddi', $company_id, $oid, $part_name, $category, $qty, $unit, $subtotal, $is_major);
            mysqli_stmt_execute($s); $lineId = mysqli_insert_id($conn); mysqli_stmt_close($s);
        }
        mnt_recalc_order_totals($conn, $oid, $company_id);
        mnt_json(array('success' => true, 'line' => array('id' => $lineId, 'part_name' => $part_name, 'category' => $category, 'quantity' => $qty, 'unit_cost' => $unit, 'subtotal' => $subtotal, 'is_major' => $is_major), 'totals' => mnt_order_totals($conn, $oid, $company_id)));
    }

    if ($act === 'del_labor' || $act === 'del_part') {
        $lid = intval($_POST['line_id'] ?? 0);
        $tbl = ($act === 'del_labor') ? 'mnt_order_labor' : 'mnt_order_part';
        if ($s = mysqli_prepare($conn, "DELETE FROM `$tbl` WHERE id=? AND order_id=? AND company_id=?")) {
            mysqli_stmt_bind_param($s, 'iii', $lid, $oid, $company_id);
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
        }
        mnt_recalc_order_totals($conn, $oid, $company_id);
        mnt_json(array('success' => true, 'totals' => mnt_order_totals($conn, $oid, $company_id)));
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// إنشاء أمر جديد (يُحفظ فقط عند إرسال الفورم — لا سجلّ فارغ عند فتح الفورم) ← يفتح صفحة التحرير
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_order') {
    if (!$can_add) { header("Location: orders.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: orders.php?msg=لا+يمكن+الإنشاء+بلا+شركة+صالحة+❌"); exit(); }

    $equipment_id = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id   = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $source       = in_array($_POST['source'] ?? '', $sources, true) ? $_POST['source'] : 'بلاغ';
    $maint_type   = trim($_POST['maint_type'] ?? '');
    $priority     = trim($_POST['priority'] ?? '');
    $cost_party   = in_array($_POST['cost_party'] ?? '', $cost_parties, true) ? $_POST['cost_party'] : null;

    $code = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
    $new_id = 0;
    $sql = "INSERT INTO mnt_order (company_id, code, equipment_id, project_id, source, maint_type, priority, cost_party, state, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'بلاغ', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // company_id,i code,s equipment,i project,i source,s maint_type,s priority,s cost_party,s created_by,i
        mysqli_stmt_bind_param($stmt, 'isiissssi', $company_id, $code, $equipment_id, $project_id, $source, $maint_type, $priority, $cost_party, $current_user_id);
        mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }
    header("Location: orders.php?id=" . intval($new_id) . "&msg=تم+إنشاء+أمر+صيانة+✅"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// حفظ رأس الأمر + تطبيق منطق الحالة
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_order') {
    if (!$can_edit) { header("Location: orders.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    $oid = intval($_POST['id'] ?? 0);
    $order = mnt_fetch_order($conn, $oid, $company_id, $is_super_admin);
    if (!$order) { header("Location: orders.php?msg=الأمر+غير+موجود+❌"); exit(); }

    $equipment_id  = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id    = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $source        = in_array($_POST['source'] ?? '', $sources, true) ? $_POST['source'] : 'بلاغ';
    $maint_type    = trim($_POST['maint_type'] ?? '');
    $priority      = trim($_POST['priority'] ?? '');
    $cost_party    = in_array($_POST['cost_party'] ?? '', $cost_parties, true) ? $_POST['cost_party'] : null;
    $vendor_id     = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
    $workshop      = trim($_POST['workshop'] ?? '');
    $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
    $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;
    $failure_code  = !empty($_POST['failure_code_id']) ? intval($_POST['failure_code_id']) : null;
    $diagnosis     = trim($_POST['diagnosis'] ?? '');
    $root_cause_id = !empty($_POST['root_cause_id']) ? intval($_POST['root_cause_id']) : null;
    $actions_taken = trim($_POST['actions_taken'] ?? '');
    $work_start    = !empty($_POST['work_start']) ? str_replace('T', ' ', $_POST['work_start']) : null;
    $work_end      = !empty($_POST['work_end']) ? str_replace('T', ' ', $_POST['work_end']) : null;
    $downtime      = isset($_POST['downtime_hours']) ? floatval($_POST['downtime_hours']) : 0;
    $external_cost = isset($_POST['external_cost']) ? floatval($_POST['external_cost']) : 0;
    $inspection_result = trim($_POST['inspection_result'] ?? '');
    $requested_state   = in_array($_POST['state'] ?? '', $states, true) ? $_POST['state'] : $order['state'];

    // منع إعادة فتح أمر مُغلق أو ملغى إلى حالة نشطة (سلامة آلة الحالة)
    if (in_array($order['state'], array('إغلاق', 'ملغى'), true)
        && in_array($requested_state, $active_states, true)) {
        header("Location: orders.php?id=" . intval($oid) . "&msg=" . urlencode('لا يمكن إعادة فتح أمر مُغلق أو ملغى ❌'));
        exit();
    }

    // التحقق أن المعدة المختارة تتبع شركة المستخدم (منع ربط معدة شركة أخرى)
    if ($equipment_id !== null && !$is_super_admin) {
        $eq_ok = false;
        if ($eq_vstmt = mysqli_prepare($conn, "SELECT 1 FROM equipments WHERE id = ? AND (company_id = ? OR company_id IS NULL) LIMIT 1")) {
            mysqli_stmt_bind_param($eq_vstmt, 'ii', $equipment_id, $company_id);
            mysqli_stmt_execute($eq_vstmt);
            $eq_vres = mysqli_stmt_get_result($eq_vstmt);
            $eq_ok = ($eq_vres && mysqli_num_rows($eq_vres) > 0);
            mysqli_stmt_close($eq_vstmt);
        }
        if (!$eq_ok) {
            header("Location: orders.php?id=" . intval($oid) . "&msg=" . urlencode('المعدة المختارة لا تتبع شركتك ❌'));
            exit();
        }
    }

    $close_error = '';
    $effective_state = $requested_state;
    if ($requested_state === 'إغلاق') {
        if ($actions_taken === '' || $root_cause_id === null || $inspection_result !== 'ناجح') {
            $close_error = 'تعذّر الإغلاق: يلزم (الإجراءات المتخذة + السبب الجذري + نتيجة الفحص «ناجح»).';
            $effective_state = 'فحص';
        }
    }

    $was_closed = ($order['state'] === 'إغلاق');
    $closing_now = ($effective_state === 'إغلاق' && !$was_closed);

    $sql = "UPDATE mnt_order SET
                equipment_id = ?, project_id = ?, source = ?, maint_type = ?, priority = ?,
                cost_party = ?, vendor_id = ?, workshop = ?, technician_id = ?, supervisor_id = ?,
                failure_code_id = ?, diagnosis = ?, root_cause_id = ?, actions_taken = ?,
                work_start = ?, work_end = ?, downtime_hours = ?, external_cost = ?,
                inspection_result = ?, state = ?"
            . ($closing_now ? ", closed_at = NOW(), closed_by = " . intval($current_user_id) : "")
            . " WHERE id = ? AND company_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $types = 'iisss' . 'sisii' . 'isis' . 'ssdd' . 'ss' . 'ii';
        mysqli_stmt_bind_param(
            $stmt, $types,
            $equipment_id, $project_id, $source, $maint_type, $priority,
            $cost_party, $vendor_id, $workshop, $technician_id, $supervisor_id,
            $failure_code, $diagnosis, $root_cause_id, $actions_taken,
            $work_start, $work_end, $downtime, $external_cost,
            $inspection_result, $effective_state,
            $oid, $company_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mnt_recalc_order_totals($conn, $oid, $company_id);

    // عند تغيير معدة الأمر: أعد المعدة القديمة «سليمة» و«متاحة» حتى لا تبقى معطلة بلا أمر
    $old_equipment_id = intval($order['equipment_id'] ?? 0);
    if ($old_equipment_id > 0 && $old_equipment_id !== intval($equipment_id)) {
        mnt_mark_equipment_healthy($conn, $old_equipment_id, $company_id, $current_user_id);
        mnt_return_equipment_available($conn, $old_equipment_id, $company_id);
    }

    if (in_array($effective_state, $active_states, true) && $equipment_id) {
        mnt_mark_equipment_unhealthy($conn, $equipment_id, $company_id, $current_user_id);
    }
    if ($closing_now && $equipment_id) {
        mnt_mark_equipment_healthy($conn, $equipment_id, $company_id, $current_user_id);
        mnt_return_equipment_available($conn, $equipment_id, $company_id);
        if (!empty($order['plan_id'])) {
            $meter = mnt_equipment_actual_hours($conn, $equipment_id, $company_id);
            mnt_reschedule_plan($conn, intval($order['plan_id']), $company_id, $meter);
        }
        if (!empty($order['breakdown_id'])) {
            if ($s2 = mysqli_prepare($conn, "UPDATE mnt_breakdown SET state='مغلق' WHERE id=? AND company_id=?")) {
                $bid = intval($order['breakdown_id']);
                mysqli_stmt_bind_param($s2, 'ii', $bid, $company_id);
                mysqli_stmt_execute($s2);
                mysqli_stmt_close($s2);
            }
        }
    }
    if ($effective_state === 'ملغى' && $equipment_id) {
        mnt_mark_equipment_healthy($conn, $equipment_id, $company_id, $current_user_id);
        // إلغاء الأمر يُعيد المعدة للعمل تماماً كالإغلاق (وإلا بقيت «تحت الصيانة» عالقة)
        mnt_return_equipment_available($conn, $equipment_id, $company_id);
    }

    if ($close_error !== '') {
        $msg = urlencode($close_error . ' ❌');
    } elseif ($closing_now) {
        $msg = urlencode('تم إغلاق الأمر وإعادة المعدة إلى «متاحة للعمل» ✅');
    } else {
        $msg = 'تم+حفظ+الأمر+بنجاح+✅';
    }
    header("Location: orders.php?id=" . intval($oid) . "&msg=" . $msg); exit();
}

// ── أسطر العمالة/القطع: مسار احتياطي بلا JS (POST عادي يُعيد التوجيه) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', array('add_labor', 'add_part'), true)) {
    if ($can_edit) {
        $oid = intval($_POST['order_id'] ?? 0);
        $order = mnt_fetch_order($conn, $oid, $company_id, $is_super_admin);
        if ($order) {
            if ($_POST['action'] === 'add_labor') {
                $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
                $role = trim($_POST['labor_role'] ?? ''); $hours = floatval($_POST['hours'] ?? 0); $rate = floatval($_POST['hourly_rate'] ?? 0); $cost = $hours * $rate;
                if ($s = mysqli_prepare($conn, "INSERT INTO mnt_order_labor (company_id, order_id, employee_id, role, hours, hourly_rate, cost) VALUES (?,?,?,?,?,?,?)")) {
                    mysqli_stmt_bind_param($s, 'iiisddd', $company_id, $oid, $employee_id, $role, $hours, $rate, $cost);
                    mysqli_stmt_execute($s); mysqli_stmt_close($s);
                }
            } else {
                $part_name = trim($_POST['part_name'] ?? ''); $category = trim($_POST['category'] ?? '');
                $qty = floatval($_POST['quantity'] ?? 1); $unit = floatval($_POST['unit_cost'] ?? 0); $subtotal = $qty * $unit; $is_major = isset($_POST['is_major_component']) ? 1 : 0;
                if ($part_name !== '' && ($s = mysqli_prepare($conn, "INSERT INTO mnt_order_part (company_id, order_id, part_name, category, quantity, unit_cost, subtotal, is_major_component) VALUES (?,?,?,?,?,?,?,?)"))) {
                    mysqli_stmt_bind_param($s, 'iissdddi', $company_id, $oid, $part_name, $category, $qty, $unit, $subtotal, $is_major);
                    mysqli_stmt_execute($s); mysqli_stmt_close($s);
                }
            }
            mnt_recalc_order_totals($conn, $oid, $company_id);
        }
        header("Location: orders.php?id=" . intval($oid)); exit();
    }
}
if (isset($_GET['del_labor'], $_GET['order_id']) && $can_edit) {
    $lid = intval($_GET['del_labor']); $oid = intval($_GET['order_id']);
    if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_order_labor WHERE id=? AND order_id=? AND company_id=?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $lid, $oid, $company_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    mnt_recalc_order_totals($conn, $oid, $company_id);
    header("Location: orders.php?id=" . $oid); exit();
}
if (isset($_GET['del_part'], $_GET['order_id']) && $can_edit) {
    $pid = intval($_GET['del_part']); $oid = intval($_GET['order_id']);
    if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_order_part WHERE id=? AND order_id=? AND company_id=?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $pid, $oid, $company_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    mnt_recalc_order_totals($conn, $oid, $company_id);
    header("Location: orders.php?id=" . $oid); exit();
}

// ── حذف ناعم لأمر ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: orders.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $did = intval($_GET['delete_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE mnt_order SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $did, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: orders.php?msg=تم+حذف+الأمر+✅"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// تحضير العرض
// ══════════════════════════════════════════════════════════════════════════════
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$order = $edit_id > 0 ? mnt_fetch_order($conn, $edit_id, $company_id, $is_super_admin) : null;

$equipments = array(); $projects = array(); $vendors = array(); $users_list = array();
$root_causes = array(); $failure_codes = array();
if ($order || $edit_id === 0) {
    $cscope = $is_super_admin ? "1=1" : "company_id = " . intval($company_id);
    if ($r = mysqli_query($conn, "SELECT id, name, code FROM equipments WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $equipments[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM project WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $projects[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE $cscope AND COALESCE(is_deleted,0)=0 ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $vendors[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM users WHERE $cscope AND is_deleted=0 ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $users_list[] = $x; }
    $root_causes = mnt_lookup_options($conn, $company_id, 'سبب عطل');
    if ($r = mysqli_query($conn, "SELECT id, full_code, failure_detail FROM failure_codes WHERE status=1 ORDER BY full_code")) { while ($x = mysqli_fetch_assoc($r)) $failure_codes[] = $x; }
}

// خيارات المعدة لفورم التحرير: معدات «تحت الصيانة» لمشروع الأمر + المعدة الحالية دائماً (تُعرض حسب المشروع).
$edit_eq_options = $order
    ? mnt_maint_equipment_in_project($conn, $company_id, intval($order['project_id'] ?? 0), intval($order['equipment_id'] ?? 0))
    : array();

$page_title = 'إيكوبيشن | أوامر الصيانة';
include '../inheader.php';
include '../insidebar.php';

function mnt_opt($value, $label, $selected) {
    return "<option value='" . htmlspecialchars((string) $value, ENT_QUOTES) . "'" . ($selected ? " selected" : "") . ">" . htmlspecialchars((string) $label) . "</option>";
}
function mnt_state_class($st) {
    if ($st === 'إغلاق') return 'mnt-pill mnt-pill--green';
    if ($st === 'ملغى') return 'mnt-pill mnt-pill--gray';
    if ($st === 'فحص')  return 'mnt-pill mnt-pill--purple';
    if ($st === 'تنفيذ') return 'mnt-pill mnt-pill--blue';
    return 'mnt-pill mnt-pill--gold';
}
?>

<div class="main mnt-orders-main ems-unified-page-shell">

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>" style="margin-bottom:12px;">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

<?php if ($order): // ═══════════════════════════ صفحة تحرير أمر ═══════════════════════════
    $labor_rows = array(); $part_rows = array();
    if ($s = mysqli_prepare($conn, "SELECT l.id, l.role, l.hours, l.hourly_rate, l.cost, u.name AS emp FROM mnt_order_labor l LEFT JOIN users u ON u.id=l.employee_id WHERE l.order_id=? AND l.company_id=? ORDER BY l.id")) {
        mysqli_stmt_bind_param($s, 'ii', $edit_id, $company_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($rr && $x = mysqli_fetch_assoc($rr)) $labor_rows[] = $x; mysqli_stmt_close($s);
    }
    if ($s = mysqli_prepare($conn, "SELECT id, part_name, category, quantity, unit_cost, subtotal, is_major_component FROM mnt_order_part WHERE order_id=? AND company_id=? ORDER BY id")) {
        mysqli_stmt_bind_param($s, 'ii', $edit_id, $company_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($rr && $x = mysqli_fetch_assoc($rr)) $part_rows[] = $x; mysqli_stmt_close($s);
    }
    $st = (string) $order['state'];
    $st_locked = ($st === 'إغلاق' || $st === 'ملغى');
?>
    <?php
    $header_title_html = 'أمر صيانة <strong>' . htmlspecialchars((string) $order['code']) . '</strong> '
        . '<span class="' . mnt_state_class($st) . '">' . htmlspecialchars($st) . '</span>';
    $header_icon = 'fa fa-wrench';
    $header_actions = array();
    $header_actions[] = array('id' => 'toggleOrderForm', 'class' => 'add-btn', 'icon' => 'fas fa-pen-to-square', 'label' => 'بيانات الأمر');
    $header_back = array(
        array('tag' => 'a', 'href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'كل الأوامر'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>

    <!-- شريط مراحل الدورة (Stepper) -->
    <div class="card mnt-stepper-card"><div class="card-body">
        <div class="mnt-stepper">
            <?php
            $flow = array('بلاغ', 'تنفيذ', 'فحص', 'إغلاق');
            $curIdx = array_search($st, $flow, true);
            if ($st === 'ملغى') { $curIdx = -1; }
            foreach ($flow as $i => $stp):
                $done = ($curIdx !== false && $curIdx !== -1 && $i < $curIdx);
                $cur  = ($i === $curIdx);
                $cls = $cur ? 'mnt-step is-current' : ($done ? 'mnt-step is-done' : 'mnt-step'); ?>
                <div class="<?php echo $cls; ?>">
                    <span class="mnt-step-dot"><?php echo $done ? '<i class="fas fa-check"></i>' : ($i + 1); ?></span>
                    <span class="mnt-step-label"><?php echo htmlspecialchars($stp); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($st === 'ملغى'): ?><div class="mnt-step is-cancel"><span class="mnt-step-dot"><i class="fas fa-ban"></i></span><span class="mnt-step-label">ملغى</span></div><?php endif; ?>
        </div>
    </div></div>

    <!-- فورم رأس الأمر -->
    <form method="post" action="" class="allforms allforms-visible" id="orderForm">
        <input type="hidden" name="action" value="save_order">
        <input type="hidden" name="id" value="<?php echo intval($order['id']); ?>">
        <div class="card-header"><h5><i class="fas fa-clipboard-list"></i> بيانات أمر الصيانة</h5></div>
        <div class="card"><div class="card-body">

            <div class="form-section">
                <h6><i class="fas fa-info-circle"></i> الأساسيات</h6>
                <div class="form-grid">
                    <div class="form-group"><label>المشروع / الموقع</label>
                        <select name="project_id" class="mnt-proj"><option value="">— اختر المشروع —</option>
                            <?php foreach ($projects as $p) echo mnt_opt($p['id'], $p['name'], intval($order['project_id']) === intval($p['id'])); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>المعدة <span class="mnt-req-hint">(تحت الصيانة في المشروع)</span></label>
                        <select name="equipment_id" class="mnt-eq" data-selected="<?php echo intval($order['equipment_id']); ?>"><option value="">— اختر المعدة —</option>
                            <?php foreach ($edit_eq_options as $e) echo mnt_opt($e['id'], $e['name'] . (!empty($e['code']) ? ' (' . $e['code'] . ')' : ''), intval($order['equipment_id']) === intval($e['id'])); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>المصدر</label>
                        <select name="source"><?php foreach ($sources as $s) echo mnt_opt($s, $s, $order['source'] === $s); ?></select>
                    </div>
                    <div class="form-group"><label>نوع الصيانة</label>
                        <select name="maint_type"><option value="">— اختر —</option><?php foreach ($maint_types as $m) echo mnt_opt($m, $m, $order['maint_type'] === $m); ?></select>
                    </div>
                    <div class="form-group"><label>الأولوية</label>
                        <select name="priority"><option value="">— اختر —</option><?php foreach ($priorities as $p) echo mnt_opt($p, $p, $order['priority'] === $p); ?></select>
                    </div>
                    <div class="form-group"><label>نوع العطل (التصنيف)</label>
                        <select name="failure_code_id"><option value="">— اختر —</option>
                            <?php foreach ($failure_codes as $f) echo mnt_opt($f['id'], $f['full_code'] . ' — ' . $f['failure_detail'], intval($order['failure_code_id']) === intval($f['id'])); ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-coins"></i> جهة التكلفة والتنفيذ</h6>
                <div class="form-grid">
                    <div class="form-group"><label>جهة التكلفة</label>
                        <select name="cost_party"><option value="">— اختر —</option><?php foreach ($cost_parties as $c) echo mnt_opt($c, $c, $order['cost_party'] === $c); ?></select>
                    </div>
                    <div class="form-group"><label>المورّد / الورشة الخارجية</label>
                        <select name="vendor_id"><option value="">— داخلي —</option>
                            <?php foreach ($vendors as $v) echo mnt_opt($v['id'], $v['name'], intval($order['vendor_id']) === intval($v['id'])); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>اسم الورشة (نصّي)</label>
                        <input type="text" name="workshop" value="<?php echo htmlspecialchars((string) $order['workshop']); ?>" placeholder="ورشة داخلية / خارجية">
                    </div>
                    <div class="form-group"><label>الفني المسؤول</label>
                        <select name="technician_id"><option value="">— اختر —</option>
                            <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], intval($order['technician_id']) === intval($u['id'])); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>المشرف</label>
                        <select name="supervisor_id"><option value="">— اختر —</option>
                            <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], intval($order['supervisor_id']) === intval($u['id'])); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>تكلفة خارجية (إدخال يدوي)</label>
                        <input type="number" step="0.01" name="external_cost" value="<?php echo htmlspecialchars((string) $order['external_cost']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="fas fa-stethoscope"></i> التشخيص والإجراءات والمرحلة</h6>
                <div class="form-grid">
                    <div class="form-group"><label>وقت بدء العمل</label>
                        <input type="datetime-local" name="work_start" value="<?php echo $order['work_start'] ? str_replace(' ', 'T', substr((string) $order['work_start'], 0, 16)) : ''; ?>">
                    </div>
                    <div class="form-group"><label>وقت انتهاء العمل</label>
                        <input type="datetime-local" name="work_end" value="<?php echo $order['work_end'] ? str_replace(' ', 'T', substr((string) $order['work_end'], 0, 16)) : ''; ?>">
                    </div>
                    <div class="form-group"><label>ساعات التوقّف</label>
                        <input type="number" step="0.01" name="downtime_hours" value="<?php echo htmlspecialchars((string) $order['downtime_hours']); ?>">
                    </div>
                    <div class="form-group"><label>السبب الجذري <span class="mnt-req-hint">(للإغلاق)</span></label>
                        <select name="root_cause_id"><option value="">— اختر —</option>
                            <?php foreach ($root_causes as $id => $name) echo mnt_opt($id, $name, intval($order['root_cause_id']) === intval($id)); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>نتيجة الفحص <span class="mnt-req-hint">(للإغلاق)</span></label>
                        <select name="inspection_result">
                            <option value="">— اختر —</option>
                            <?php foreach (array('ناجح', 'راسب') as $ir) echo mnt_opt($ir, $ir, $order['inspection_result'] === $ir); ?>
                        </select>
                    </div>
                    <div class="form-group"><label>المرحلة (الحالة)</label>
                        <select name="state" id="orderState"><?php foreach ($states as $s) echo mnt_opt($s, $s, $st === $s); ?></select>
                    </div>
                    <div class="form-group allforms-span-full"><label>التشخيص</label>
                        <textarea name="diagnosis" rows="2"><?php echo htmlspecialchars((string) $order['diagnosis']); ?></textarea>
                    </div>
                    <div class="form-group allforms-span-full"><label>الإجراءات المتخذة <span class="mnt-req-hint">(مطلوبة للإغلاق)</span></label>
                        <textarea name="actions_taken" rows="2"><?php echo htmlspecialchars((string) $order['actions_taken']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ بيانات الأمر</button>
                <button type="button" class="btn-cancel" id="collapseOrderForm"><i class="fas fa-chevron-up"></i> طيّ النموذج</button>
            </div>
        </div></div>
    </form>

    <!-- ملخص التكاليف (يتحدّث فورياً عند تعديل الأسطر) -->
    <div class="card"><div class="card-body">
        <div class="mnt-cost-summary">
            <div class="mnt-cost-box"><span><i class="fas fa-user-gear"></i> العمالة</span><strong id="sumLabor"><?php echo number_format((float) $order['labor_cost'], 2); ?></strong></div>
            <div class="mnt-cost-box"><span><i class="fas fa-gears"></i> القطع</span><strong id="sumParts"><?php echo number_format((float) $order['parts_cost'], 2); ?></strong></div>
            <div class="mnt-cost-box"><span><i class="fas fa-money-bill"></i> خارجية</span><strong id="sumExternal"><?php echo number_format((float) $order['external_cost'], 2); ?></strong></div>
            <div class="mnt-cost-box mnt-cost-total"><span><i class="fas fa-sack-dollar"></i> الإجمالي</span><strong id="sumTotal"><?php echo number_format((float) $order['total_cost'], 2); ?></strong></div>
        </div>
    </div></div>

    <div class="mnt-lines-grid">
        <!-- أسطر العمالة -->
        <div class="card mnt-lines-card">
            <div class="card-header mnt-lines-head">
                <h5><i class="fas fa-user-gear"></i> أسطر العمالة <span class="mnt-count" id="laborCount"><?php echo count($labor_rows); ?></span></h5>
                <?php if (!$st_locked && $can_edit): ?><button type="button" class="mnt-add-toggle" data-target="laborForm"><i class="fas fa-plus"></i> إضافة سطر</button><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$st_locked && $can_edit): ?>
                <form class="mnt-line-form" id="laborForm" onsubmit="return false;" style="display:none;">
                    <input type="hidden" name="action" value="add_labor">
                    <input type="hidden" name="order_id" value="<?php echo intval($order['id']); ?>">
                    <div class="mnt-line-grid">
                        <div class="form-group"><label>الموظف</label><select name="employee_id"><option value="">— اختر —</option><?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], false); ?></select></div>
                        <div class="form-group"><label>الدور</label><input type="text" name="labor_role" placeholder="فني / مساعد"></div>
                        <div class="form-group"><label>الساعات</label><input type="number" step="0.01" name="hours" value="0"></div>
                        <div class="form-group"><label>تكلفة الساعة</label><input type="number" step="0.01" name="hourly_rate" value="0"></div>
                    </div>
                    <div class="mnt-line-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إضافة السطر</button>
                        <button type="button" class="btn-cancel mnt-line-cancel" data-target="laborForm"><i class="fas fa-times"></i> إلغاء</button>
                    </div>
                </form>
                <?php endif; ?>
                <div class="table-container"><table class="alltables no-datatable mnt-line-table" id="laborTable" style="width:100%">
                    <thead><tr><th>الموظف</th><th>الدور</th><th>الساعات</th><th>تكلفة الساعة</th><th>التكلفة</th><?php if (!$st_locked && $can_edit) echo '<th></th>'; ?></tr></thead>
                    <tbody>
                        <?php foreach ($labor_rows as $l): ?>
                        <tr data-line="<?php echo intval($l['id']); ?>">
                            <td><?php echo htmlspecialchars((string) ($l['emp'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($l['role'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) $l['hours']); ?></td>
                            <td><?php echo htmlspecialchars((string) $l['hourly_rate']); ?></td>
                            <td class="mnt-num"><?php echo number_format((float) $l['cost'], 2); ?></td>
                            <?php if (!$st_locked && $can_edit): ?><td><button type="button" class="action-btn delete mnt-del-line" data-kind="labor" data-line="<?php echo intval($l['id']); ?>" title="حذف"><i class="fas fa-trash-alt"></i></button></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="4">إجمالي العمالة</th><th class="mnt-num" id="laborFoot"><?php echo number_format((float) $order['labor_cost'], 2); ?></th><?php if (!$st_locked && $can_edit) echo '<th></th>'; ?></tr></tfoot>
                </table></div>
                <div class="mnt-empty-line" id="laborEmpty" style="<?php echo empty($labor_rows) ? '' : 'display:none'; ?>"><i class="fas fa-user-gear"></i><span>لا توجد أسطر عمالة بعد</span></div>
            </div>
        </div>

        <!-- أسطر القطع -->
        <div class="card mnt-lines-card">
            <div class="card-header mnt-lines-head">
                <h5><i class="fas fa-gears"></i> أسطر القطع <span class="mnt-count" id="partCount"><?php echo count($part_rows); ?></span></h5>
                <?php if (!$st_locked && $can_edit): ?><button type="button" class="mnt-add-toggle" data-target="partForm"><i class="fas fa-plus"></i> إضافة سطر</button><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$st_locked && $can_edit): ?>
                <form class="mnt-line-form" id="partForm" onsubmit="return false;" style="display:none;">
                    <input type="hidden" name="action" value="add_part">
                    <input type="hidden" name="order_id" value="<?php echo intval($order['id']); ?>">
                    <div class="mnt-line-grid">
                        <div class="form-group"><label>اسم القطعة</label><input type="text" name="part_name" placeholder="اسم القطعة"></div>
                        <div class="form-group"><label>التصنيف</label><input type="text" name="category"></div>
                        <div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="quantity" value="1"></div>
                        <div class="form-group"><label>سعر الوحدة</label><input type="number" step="0.01" name="unit_cost" value="0"></div>
                        <div class="form-group"><label>مكوّن رئيسي؟</label><label class="mnt-major-chk"><input type="checkbox" name="is_major_component" value="1"><span>نعم، مكوّن رئيسي</span></label></div>
                    </div>
                    <div class="mnt-line-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إضافة السطر</button>
                        <button type="button" class="btn-cancel mnt-line-cancel" data-target="partForm"><i class="fas fa-times"></i> إلغاء</button>
                    </div>
                </form>
                <?php endif; ?>
                <div class="table-container"><table class="alltables no-datatable mnt-line-table" id="partTable" style="width:100%">
                    <thead><tr><th>القطعة</th><th>التصنيف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th>رئيسي</th><?php if (!$st_locked && $can_edit) echo '<th></th>'; ?></tr></thead>
                    <tbody>
                        <?php foreach ($part_rows as $pt): ?>
                        <tr data-line="<?php echo intval($pt['id']); ?>">
                            <td><?php echo htmlspecialchars((string) $pt['part_name']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pt['category'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) $pt['quantity']); ?></td>
                            <td><?php echo htmlspecialchars((string) $pt['unit_cost']); ?></td>
                            <td class="mnt-num"><?php echo number_format((float) $pt['subtotal'], 2); ?></td>
                            <td class="mnt-center"><?php echo intval($pt['is_major_component']) ? '<i class="fas fa-star" style="color:#E0AE2E"></i>' : '—'; ?></td>
                            <?php if (!$st_locked && $can_edit): ?><td><button type="button" class="action-btn delete mnt-del-line" data-kind="part" data-line="<?php echo intval($pt['id']); ?>" title="حذف"><i class="fas fa-trash-alt"></i></button></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="4">إجمالي القطع</th><th class="mnt-num" id="partsFoot"><?php echo number_format((float) $order['parts_cost'], 2); ?></th><th></th><?php if (!$st_locked && $can_edit) echo '<th></th>'; ?></tr></tfoot>
                </table></div>
                <div class="mnt-empty-line" id="partEmpty" style="<?php echo empty($part_rows) ? '' : 'display:none'; ?>"><i class="fas fa-gears"></i><span>لا توجد أسطر قطع بعد</span></div>
            </div>
        </div>
    </div>

<?php else: // ═══════════════════════════ قائمة الأوامر ═══════════════════════════
    // إحصائيات
    $stats = array('total' => 0, 'open' => 0, 'closed' => 0, 'cost' => 0);
    if ($sq = mysqli_query($conn, "SELECT COUNT(*) total, SUM(state IN ('بلاغ','تنفيذ','فحص')) open_c, SUM(state='إغلاق') closed_c, COALESCE(SUM(total_cost),0) cost FROM mnt_order o WHERE $company_scope_sql AND COALESCE(o.is_deleted,0)=0")) {
        if ($sr = mysqli_fetch_assoc($sq)) { $stats = array('total' => intval($sr['total']), 'open' => intval($sr['open_c']), 'closed' => intval($sr['closed_c']), 'cost' => floatval($sr['cost'])); }
    }

    $header_title  = 'أوامر الصيانة';
    $header_icon   = 'fa fa-wrench';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleOrderCreateForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'أمر صيانة جديد');
    }
    $header_actions[] = array('tag' => 'a', 'href' => 'breakdowns.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-triangle-exclamation', 'label' => 'البلاغات');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
?>
    <div class="mnt-bell-wrap">
        <span class="mnt-bell" title="أوامر صيانة تلقائية مفتوحة">
            <i class="fas fa-bell"></i>
            <span class="mnt-bell-badge" id="openOrdersBadge" style="display:none">0</span>
        </span>
    </div>
    <?php if ($can_add): ?>
    <!-- فورم إنشاء أمر (نمط العملاء/المشاريع: يُفتح بزر «أمر صيانة جديد»، ولا يُحفظ شيء إلا عند الإرسال) -->
    <form method="post" action="" class="allforms" id="orderCreateForm">
        <input type="hidden" name="action" value="new_order">
        <div class="card-header"><h5><i class="fas fa-wrench"></i> إنشاء أمر صيانة جديد</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>المشروع / الموقع <span class="required">*</span></label>
                    <select name="project_id" class="mnt-proj" required><option value="">— اختر المشروع —</option>
                        <?php foreach ($projects as $p) echo mnt_opt($p['id'], $p['name'], false); ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدة <span class="mnt-req-hint">(تحت الصيانة في المشروع)</span></label>
                    <select name="equipment_id" class="mnt-eq" data-selected=""><option value="">— اختر المشروع أولاً —</option></select>
                </div>
                <div class="form-group"><label>المصدر</label>
                    <select name="source"><?php foreach ($sources as $s) echo mnt_opt($s, $s, $s === 'بلاغ'); ?></select>
                </div>
                <div class="form-group"><label>نوع الصيانة</label>
                    <select name="maint_type"><option value="">— اختر —</option><?php foreach ($maint_types as $m) echo mnt_opt($m, $m, false); ?></select>
                </div>
                <div class="form-group"><label>الأولوية</label>
                    <select name="priority"><option value="">— اختر —</option><?php foreach ($priorities as $p) echo mnt_opt($p, $p, false); ?></select>
                </div>
                <div class="form-group"><label>جهة التكلفة</label>
                    <select name="cost_party"><option value="">— اختر —</option><?php foreach ($cost_parties as $c) echo mnt_opt($c, $c, false); ?></select>
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إنشاء الأمر</button>
                <button type="button" class="btn-cancel" id="cancelOrderCreateForm"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>
    <div class="stats-section" id="ordersStats">
        <div class="stats-grid">
            <div class="stats-card stats-primary"><div class="stats-icon"><i class="fas fa-wrench"></i></div><div class="stats-title">إجمالي الأوامر</div><div class="stats-value"><?php echo $stats['total']; ?></div></div>
            <div class="stats-card stats-orange"><div class="stats-icon"><i class="fas fa-spinner"></i></div><div class="stats-title">أوامر مفتوحة</div><div class="stats-value"><?php echo $stats['open']; ?></div></div>
            <div class="stats-card stats-success"><div class="stats-icon"><i class="fas fa-check-circle"></i></div><div class="stats-title">أوامر مغلقة</div><div class="stats-value"><?php echo $stats['closed']; ?></div></div>
            <div class="stats-card stats-purple"><div class="stats-icon"><i class="fas fa-sack-dollar"></i></div><div class="stats-title">إجمالي التكلفة</div><div class="stats-value"><?php echo number_format($stats['cost'], 0); ?></div></div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>تصفية حسب الحالة</label>
                <select id="filterState"><option value="">كل الحالات</option>
                    <?php foreach ($states as $s) echo "<option value='" . htmlspecialchars($s, ENT_QUOTES) . "'>" . htmlspecialchars($s) . "</option>"; ?>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table id="ordersTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>المرجع</th><th>المعدة</th><th>المصدر</th><th>نوع الصيانة</th>
                    <th>جهة التكلفة</th><th>الإجمالي</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT o.*, e.name AS eq_name, e.code AS eq_code, p.name AS proj_name,
                                   s.name AS vendor_name, ut.name AS tech_name, us.name AS sup_name,
                                   lk.name AS root_name, fc.full_code, fc.failure_detail
                              FROM mnt_order o
                              LEFT JOIN equipments e ON e.id = o.equipment_id
                              LEFT JOIN project p ON p.id = o.project_id
                              LEFT JOIN suppliers s ON s.id = o.vendor_id
                              LEFT JOIN users ut ON ut.id = o.technician_id
                              LEFT JOIN users us ON us.id = o.supervisor_id
                              LEFT JOIN mnt_lookup lk ON lk.id = o.root_cause_id
                              LEFT JOIN failure_codes fc ON fc.id = o.failure_code_id
                             WHERE $company_scope_sql AND COALESCE(o.is_deleted,0)=0
                             ORDER BY o.id DESC";
                    $order_ids = array();
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $order_ids[] = intval($row['id']);
                        $st = (string) $row['state'];
                        $failure = trim(((string) ($row['full_code'] ?? '')) . ' ' . ((string) ($row['failure_detail'] ?? '')));
                        $da =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-code='" . htmlspecialchars((string) $row['code'], ENT_QUOTES) . "' " .
                            "data-equipment='" . htmlspecialchars((string) ($row['eq_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-project='" . htmlspecialchars((string) ($row['proj_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-source='" . htmlspecialchars((string) $row['source'], ENT_QUOTES) . "' " .
                            "data-maint_type='" . htmlspecialchars((string) ($row['maint_type'] ?? ''), ENT_QUOTES) . "' " .
                            "data-priority='" . htmlspecialchars((string) ($row['priority'] ?? ''), ENT_QUOTES) . "' " .
                            "data-cost_party='" . htmlspecialchars((string) ($row['cost_party'] ?? ''), ENT_QUOTES) . "' " .
                            "data-vendor='" . htmlspecialchars((string) ($row['vendor_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-workshop='" . htmlspecialchars((string) ($row['workshop'] ?? ''), ENT_QUOTES) . "' " .
                            "data-tech='" . htmlspecialchars((string) ($row['tech_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-sup='" . htmlspecialchars((string) ($row['sup_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-failure='" . htmlspecialchars($failure, ENT_QUOTES) . "' " .
                            "data-diagnosis='" . htmlspecialchars((string) ($row['diagnosis'] ?? ''), ENT_QUOTES) . "' " .
                            "data-root='" . htmlspecialchars((string) ($row['root_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-actions='" . htmlspecialchars((string) ($row['actions_taken'] ?? ''), ENT_QUOTES) . "' " .
                            "data-downtime='" . htmlspecialchars((string) $row['downtime_hours'], ENT_QUOTES) . "' " .
                            "data-labor='" . htmlspecialchars((string) $row['labor_cost'], ENT_QUOTES) . "' " .
                            "data-parts='" . htmlspecialchars((string) $row['parts_cost'], ENT_QUOTES) . "' " .
                            "data-external='" . htmlspecialchars((string) $row['external_cost'], ENT_QUOTES) . "' " .
                            "data-total='" . htmlspecialchars((string) $row['total_cost'], ENT_QUOTES) . "' " .
                            "data-inspection='" . htmlspecialchars((string) ($row['inspection_result'] ?? ''), ENT_QUOTES) . "' " .
                            "data-state='" . htmlspecialchars($st, ENT_QUOTES) . "'";

                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $da title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                        if ($can_edit) {
                            echo "<a href='orders.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف الأمر؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td><strong>" . htmlspecialchars((string) $row['code']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars((string) ($row['eq_name'] ?? '-'));
                        if (!empty($row['is_auto'])) { echo " <span class='mnt-auto-badge'>auto</span>"; }
                        echo "</td>";
                        echo "<td>" . htmlspecialchars((string) $row['source']) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['maint_type'] ?? '-')) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['cost_party'] ?? '-')) . "</td>";
                        echo "<td>" . number_format((float) $row['total_cost'], 2) . "</td>";
                        echo "<td><span class='" . mnt_state_class($st) . "'>" . htmlspecialchars($st) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php
    // ════════ خريطة أسطر العمالة/القطع لكل أمر (لعرضها داخل نافذة التفاصيل) ════════
    $mnt_labor_map = array();
    $mnt_parts_map = array();
    if (!empty($order_ids)) {
        $ids_csv = implode(',', array_map('intval', $order_ids));
        $cid     = intval($company_id);

        $lq = "SELECT l.order_id, u.name AS emp, l.role, l.hours, l.hourly_rate, l.cost
                 FROM mnt_order_labor l LEFT JOIN users u ON u.id = l.employee_id
                WHERE l.order_id IN ($ids_csv) AND l.company_id = $cid
                ORDER BY l.id";
        if ($lr = mysqli_query($conn, $lq)) {
            while ($x = mysqli_fetch_assoc($lr)) {
                $oid = intval($x['order_id']);
                $mnt_labor_map[$oid][] = array(
                    ($x['emp'] !== null && $x['emp'] !== '') ? $x['emp'] : '—',
                    (string) $x['hours'],
                    number_format((float) $x['hourly_rate'], 2),
                    number_format((float) $x['cost'], 2)
                );
            }
        }

        $pq = "SELECT order_id, part_name, category, quantity, unit_cost, subtotal, is_major_component
                 FROM mnt_order_part
                WHERE order_id IN ($ids_csv) AND company_id = $cid
                ORDER BY id";
        if ($pr = mysqli_query($conn, $pq)) {
            while ($x = mysqli_fetch_assoc($pr)) {
                $oid = intval($x['order_id']);
                $mnt_parts_map[$oid][] = array(
                    (string) $x['part_name'],
                    (string) ($x['category'] ?? ''),
                    (string) $x['quantity'],
                    number_format((float) $x['unit_cost'], 2),
                    number_format((float) $x['subtotal'], 2),
                    intval($x['is_major_component']) ? 'نعم' : 'لا'
                );
            }
        }
    }
    echo '<script>window.MNT_ORDER_LINES = '
        . json_encode(array('labor' => $mnt_labor_map, 'parts' => $mnt_parts_map), JSON_UNESCAPED_UNICODE)
        . ';</script>';
    ?>
<?php endif; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
(function () {
    // ════════ قائمة الأوامر: DataTable + فلتر + نافذة العرض الموحّدة ════════
    $(document).ready(function () {
        var $t = $('#ordersTable');
        if ($t.length) {
            var table = $t.DataTable({
                scrollX: true, autoWidth: false, stateSave: false, order: [[1, 'desc']],
                dom: 'Bfrtip',
                buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
                "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
            });
            $('#filterState').on('change', function () {
                var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
                table.column(7).search(v, true, false).draw();
            });

            $(document).on('click', '.viewBtn', function () {
                var d = $(this).data();
                var lines  = window.MNT_ORDER_LINES || { labor: {}, parts: {} };
                var oid    = String(d.id);
                var labor  = (lines.labor && lines.labor[oid]) || [];
                var parts  = (lines.parts && lines.parts[oid]) || [];
                EmsDetailsModal.open({
                    title: 'تفاصيل أمر الصيانة',
                    icon: 'fas fa-wrench',
                    fields: [
                        { label: 'المرجع', value: d.code, icon: 'fas fa-hashtag' },
                        { label: 'الحالة', value: d.state, icon: 'fas fa-flag', type: 'status' },
                        { label: 'المعدة', value: d.equipment, icon: 'fas fa-tractor' },
                        { label: 'المشروع', value: d.project, icon: 'fas fa-folder-open' },
                        { label: 'المصدر', value: d.source, icon: 'fas fa-diagram-project' },
                        { label: 'نوع الصيانة', value: d.maint_type, icon: 'fas fa-screwdriver-wrench' },
                        { label: 'الأولوية', value: d.priority, icon: 'fas fa-fire' },
                        { label: 'جهة التكلفة', value: d.cost_party, icon: 'fas fa-scale-balanced' },
                        { label: 'المورّد/الورشة', value: d.vendor || d.workshop, icon: 'fas fa-truck' },
                        { label: 'الفني', value: d.tech, icon: 'fas fa-user-gear' },
                        { label: 'المشرف', value: d.sup, icon: 'fas fa-user-tie' },
                        { label: 'نوع العطل', value: d.failure, icon: 'fas fa-triangle-exclamation' },
                        { label: 'السبب الجذري', value: d.root, icon: 'fas fa-magnifying-glass' },
                        { label: 'نتيجة الفحص', value: d.inspection, icon: 'fas fa-clipboard-check' },
                        { label: 'ساعات التوقّف', value: d.downtime, icon: 'fas fa-hourglass-half' },
                        { label: 'تكلفة العمالة', value: d.labor, icon: 'fas fa-user-gear' },
                        { label: 'تكلفة القطع', value: d.parts, icon: 'fas fa-gears' },
                        { label: 'تكلفة خارجية', value: d.external, icon: 'fas fa-money-bill' },
                        { label: 'الإجمالي', value: d.total, icon: 'fas fa-sack-dollar', size: 'lg' },
                        { label: 'التشخيص', value: d.diagnosis, icon: 'fas fa-stethoscope', size: 'full' },
                        { label: 'الإجراءات المتخذة', value: d.actions, icon: 'fas fa-list-check', size: 'full' }
                    ],
                    sections: [
                        { title: 'أسطر العمالة', icon: 'fas fa-user-gear',
                          pills: [ { label: 'عدد الأسطر', value: labor.length }, { label: 'إجمالي العمالة', value: d.labor } ],
                          table: { columns: ['الموظف', 'الساعات', 'تكلفة الساعة', 'التكلفة'], rows: labor },
                          empty: 'لا توجد أسطر عمالة' },
                        { title: 'أسطر القطع', icon: 'fas fa-gears',
                          pills: [ { label: 'عدد الأسطر', value: parts.length }, { label: 'إجمالي القطع', value: d.parts } ],
                          table: { columns: ['اسم القطعة', 'الفئة', 'الكمية', 'تكلفة الوحدة', 'الإجمالي', 'مكوّن رئيسي'], rows: parts },
                          empty: 'لا توجد أسطر قطع' }
                    ]
                });
            });
        }
    });

    // ════════ صفحة التحرير: إدارة الأسطر عبر AJAX (بلا إعادة تحميل = بلا فقدان بيانات) ════════
    function esc(v){ return $('<div>').text(v == null ? '' : v).html(); }
    function fmt(n){ return Number(n).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function setTotals(t){
        if(!t) return;
        $('#sumLabor').text(fmt(t.labor)); $('#sumParts').text(fmt(t.parts));
        $('#sumExternal').text(fmt(t.external)); $('#sumTotal').text(fmt(t.total));
        $('#laborFoot').text(fmt(t.labor)); $('#partsFoot').text(fmt(t.parts));
    }
    function bumpCounts(){
        $('#laborCount').text($('#laborTable tbody tr').length);
        $('#partCount').text($('#partTable tbody tr').length);
    }
    function postLine(payload){
        return fetch('orders.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: payload })
            .then(function(r){ return r.json(); });
    }

    var $laborForm = $('#laborForm');
    if ($laborForm.length) {
        $laborForm.on('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this); fd.append('ajax','1');
            postLine(new URLSearchParams(fd)).then(function(res){
                if(!res.success){ alert(res.message || 'تعذّر الإضافة'); return; }
                var l = res.line;
                var row = '<tr data-line="'+l.id+'">'
                    + '<td>'+esc(l.emp||'—')+'</td><td>'+esc(l.role||'')+'</td><td>'+esc(l.hours)+'</td>'
                    + '<td>'+esc(l.hourly_rate)+'</td><td class="mnt-num">'+fmt(l.cost)+'</td>'
                    + '<td><button type="button" class="action-btn delete mnt-del-line" data-kind="labor" data-line="'+l.id+'" title="حذف"><i class="fas fa-trash-alt"></i></button></td></tr>';
                $('#laborTable tbody').append(row);
                $('#laborEmpty').hide();
                setTotals(res.totals);
                bumpCounts();
                $laborForm[0].reset();
            }).catch(function(){ alert('خطأ في الاتصال'); });
        });
    }

    var $partForm = $('#partForm');
    if ($partForm.length) {
        $partForm.on('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this); fd.append('ajax','1');
            postLine(new URLSearchParams(fd)).then(function(res){
                if(!res.success){ alert(res.message || 'تعذّر الإضافة'); return; }
                var l = res.line;
                var major = (l.is_major==1 || l.is_major===true) ? '<i class="fas fa-star" style="color:#E0AE2E"></i>' : '—';
                var row = '<tr data-line="'+l.id+'">'
                    + '<td>'+esc(l.part_name)+'</td><td>'+esc(l.category||'')+'</td><td>'+esc(l.quantity)+'</td>'
                    + '<td>'+esc(l.unit_cost)+'</td><td class="mnt-num">'+fmt(l.subtotal)+'</td><td class="mnt-center">'+major+'</td>'
                    + '<td><button type="button" class="action-btn delete mnt-del-line" data-kind="part" data-line="'+l.id+'" title="حذف"><i class="fas fa-trash-alt"></i></button></td></tr>';
                $('#partTable tbody').append(row);
                $('#partEmpty').hide();
                setTotals(res.totals);
                bumpCounts();
                $partForm[0].reset();
            }).catch(function(){ alert('خطأ في الاتصال'); });
        });
    }

    $(document).on('click', '.mnt-del-line', function () {
        if (!confirm('حذف السطر؟')) return;
        var $btn = $(this), kind = $btn.data('kind'), lineId = $btn.data('line');
        var orderId = ($('#laborForm input[name=order_id]').val() || $('#partForm input[name=order_id]').val() || $('input[name=id]').val());
        var body = new URLSearchParams({ ajax:'1', action:(kind==='labor'?'del_labor':'del_part'), order_id: orderId, line_id: lineId });
        postLine(body).then(function(res){
            if(!res.success){ alert(res.message || 'تعذّر الحذف'); return; }
            var $tr = $btn.closest('tr'); var $tbody = $tr.closest('tbody'); $tr.remove();
            setTotals(res.totals);
            bumpCounts();
            if ($tbody.find('tr').length === 0) { $tbody.closest('.card-body').find('.mnt-empty-line').show(); }
        }).catch(function(){ alert('خطأ في الاتصال'); });
    });

    // ════════ فتح/إغلاق فورم بيانات الأمر (نمط العملاء/المشاريع) ════════
    var $orderForm = $('#orderForm');
    function openOrderForm(){ $orderForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); }
    function closeOrderForm(){ $orderForm.stop(true, true).slideUp(220, function(){ $orderForm.removeClass('allforms-visible'); }); }
    $('#toggleOrderForm').on('click', function(){
        if ($orderForm.hasClass('allforms-visible')) { closeOrderForm(); }
        else { openOrderForm(); $('html, body').animate({ scrollTop: $orderForm.offset().top - 90 }, 360); }
    });
    $('#collapseOrderForm').on('click', closeOrderForm);

    // ════════ تأكيد الإغلاق: إعادة المعدة «متاحة للعمل» وعودتها للعمل ════════
    $orderForm.on('submit', function(e){
        if ($('#orderState').val() === 'إغلاق') {
            var $eq = $orderForm.find('.mnt-eq');
            var eqText = $eq.length ? ($eq.find('option:selected').text() || '').trim() : '';
            var msg = 'سيتم إغلاق أمر الصيانة وإعادة المعدة'
                    + (eqText ? ' «' + eqText + '» ' : ' ')
                    + 'إلى حالة «متاحة للعمل» وعودتها للعمل،\nوستختفي من قائمة معدات أوامر الصيانة.\n\nهل تريد المتابعة؟';
            if (!window.confirm(msg)) { e.preventDefault(); return false; }
        }
    });

    // ════════ فتح/إغلاق فورم إضافة سطر داخل لوحتي العمالة/القطع ════════
    $(document).on('click', '.mnt-add-toggle', function(){
        var $f = $('#' + $(this).data('target'));
        if ($f.is(':visible')) { $f.stop(true, true).slideUp(180); }
        else { $f.stop(true, true).slideDown(180); $f.find('select, input').not('[type=hidden]').first().trigger('focus'); }
    });
    $(document).on('click', '.mnt-line-cancel', function(){
        $('#' + $(this).data('target')).stop(true, true).slideUp(180);
    });

    // ════════ قائمة الأوامر: فتح/إغلاق فورم الإنشاء (بلا حفظ سجل فارغ) ════════
    var $orderCreateForm = $('#orderCreateForm');
    function closeOrderCreateForm(){ $orderCreateForm.stop(true, true).slideUp(220, function(){ $orderCreateForm.removeClass('allforms-visible'); }); }
    $('#toggleOrderCreateForm').on('click', function(){
        if ($orderCreateForm.hasClass('allforms-visible')) { closeOrderCreateForm(); }
        else { $orderCreateForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); $('html, body').animate({ scrollTop: $orderCreateForm.offset().top - 90 }, 360); }
    });
    $('#cancelOrderCreateForm').on('click', closeOrderCreateForm);

    // ════════ ربط متسلسل: عند اختيار المشروع تُجلب معداته «تحت الصيانة» فقط ════════
    function mntLoadProjectEquipment($proj){
        var $form = $proj.closest('form');
        var $eq = $form.find('.mnt-eq');
        if (!$eq.length) return;
        var projectId = $proj.val();
        var current = $eq.val() || $eq.attr('data-selected') || '';
        if (!projectId) { $eq.html('<option value="">— اختر المشروع أولاً —</option>'); return; }
        $eq.html('<option value="">جارٍ التحميل…</option>');
        var url = '/ems/Maintenance/get_project_equipment.php?project_id=' + encodeURIComponent(projectId) + (current ? '&include_id=' + encodeURIComponent(current) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var list = res.equipment || [];
                if (list.length === 0) { $eq.html('<option value="">لا توجد معدات تحت الصيانة في هذا المشروع</option>'); return; }
                var opts = '<option value="">— اختر المعدة —</option>';
                list.forEach(function(e){
                    var label = e.name + (e.code ? ' (' + e.code + ')' : '');
                    var sel = (String(e.id) === String(current)) ? ' selected' : '';
                    opts += '<option value="' + e.id + '"' + sel + '>' + $('<div>').text(label).html() + '</option>';
                });
                $eq.html(opts);
            })
            .catch(function(){ $eq.html('<option value="">تعذّر تحميل المعدات</option>'); });
    }
    // عند تغيير المشروع: حمّل معدات الصيانة التابعة له (المُحدّد مسبقاً يبقى عبر data-selected)
    $(document).on('change', '.mnt-proj', function(){ mntLoadProjectEquipment($(this)); });
})();
</script>
<script>
    // شارة جرس الأوامر التلقائية المفتوحة (الواردة من صفحة الحركة).
    function refreshOpenOrdersBadge() {
        var b = document.getElementById('openOrdersBadge');
        if (!b) return;
        fetch('get_open_orders_count.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.count > 0) { b.textContent = d.count; b.style.display = 'inline-block'; }
                else { b.style.display = 'none'; }
            })
            .catch(function () {});
    }
    refreshOpenOrdersBadge();
    setInterval(refreshOpenOrdersBadge, 60000);
</script>
<style>
    /* شارة «auto» بجوار اسم المعدة للأوامر التلقائية */
    .mnt-auto-badge {
        display: inline-block;
        margin-inline-start: 6px;
        padding: 1px 7px;
        font-size: 11px;
        font-weight: 700;
        border-radius: 6px;
        background: #6d28d9;
        color: #fff;
        letter-spacing: .5px;
    }

    /* أيقونة جرس الأوامر التلقائية المفتوحة + شارتها الحمراء */
    .mnt-bell-wrap {
        display: flex;
        justify-content: flex-end;
        margin: 6px 2px 0;
    }

    .mnt-bell {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f3f4f6;
        color: #374151;
        font-size: 17px;
        cursor: default;
    }

    .mnt-bell-badge {
        position: absolute;
        top: -4px;
        inset-inline-end: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 9px;
        background: #dc2626;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
    }

    /* بطاقات الإحصائيات — نفس تصميم إحصائيات المشاريع والعملاء حرفياً */
    .mnt-orders-main .stats-section {
        border: 1px solid var(--bdr);
        border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255, 255, 255, .95) 0%, var(--s2) 100%);
        box-shadow: var(--sh);
        padding: 14px;
        margin-bottom: 14px;
    }
    .mnt-orders-main .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 12px;
    }
    .mnt-orders-main .stats-card {
        background: #eee;
        border: 1px solid #aaa;
        border-radius: 35px;
        padding: 18px;
        box-shadow: 0 2px 8px rgba(26, 18, 8, .07);
        position: relative;
        overflow: hidden;
    }
    .mnt-orders-main .stats-card .stats-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 10px;
        float: left;
        vertical-align: middle;
        margin-top: 15px;
        border: 1px solid #999;
        background-color: #fff;
        color: #000;
    }
    .mnt-orders-main .stats-card .stats-title {
        color: #555;
        font-size: 0.92rem;
        font-weight: 700;
        margin-top: 5px;
        line-height: 1.3;
    }
    .mnt-orders-main .stats-card .stats-value {
        color: #222;
        line-height: 1;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        margin-top: 10px;
        font-size: 35px;
    }
    @media (max-width: 900px) {
        .mnt-orders-main .stats-grid { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
    }
    @media (max-width: 560px) {
        .mnt-orders-main .stats-grid { grid-template-columns: 1fr; }
    }

    /* الهوية البصرية لأوامر الصيانة — متّسقة مع باقي الموقع (navy/gold) */
    .mnt-orders-main .mnt-pill { display:inline-flex; align-items:center; padding:4px 12px; border-radius:999px; font-size:.78rem; font-weight:800; }
    .mnt-pill--gold { background:rgba(224,174,46,.16); color:#9a6f10; }
    .mnt-pill--blue { background:rgba(37,99,235,.14); color:#1d4ed8; }
    .mnt-pill--purple { background:rgba(124,58,237,.14); color:#6d28d9; }
    .mnt-pill--green { background:rgba(22,163,74,.16); color:#15803d; }
    .mnt-pill--gray { background:rgba(107,114,128,.16); color:#4b5563; }

    /* Stepper مراحل الأمر */
    .mnt-stepper-card { margin-bottom:14px; }
    .mnt-stepper { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .mnt-step { display:flex; align-items:center; gap:8px; padding:6px 14px; border-radius:999px; background:var(--s2,#f3f0e8); color:var(--t2,#8a7a5c); font-weight:700; font-size:.86rem; flex:1; min-width:120px; justify-content:center; }
    .mnt-step-dot { width:26px; height:26px; border-radius:50%; background:#fff; border:2px solid currentColor; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:900; }
    .mnt-step.is-done { background:rgba(22,163,74,.12); color:#15803d; }
    .mnt-step.is-current { background:linear-gradient(135deg,#1f4f7a,#2f6fa5); color:#fff; }
    .mnt-step.is-current .mnt-step-dot { background:#fff; color:#1f4f7a; border-color:#fff; }
    .mnt-step.is-cancel { background:rgba(220,38,38,.12); color:#b91c1c; }

    /* ملخص التكاليف */
    .mnt-orders-main .mnt-cost-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
    .mnt-cost-box { background:var(--s1,#fff); border:1px solid var(--bdr,#ece6d8); border-radius:14px; padding:14px; text-align:center; box-shadow:0 2px 8px rgba(26,18,8,.06); }
    .mnt-cost-box span { display:block; color:var(--t2,#8a7a5c); font-size:.8rem; font-weight:700; margin-bottom:7px; }
    .mnt-cost-box strong { font-size:1.4rem; font-variant-numeric:tabular-nums; color:var(--t1,#1a1208); }
    .mnt-cost-total { background:linear-gradient(135deg,#1f4f7a,#2f6fa5); border:none; }
    .mnt-cost-total span, .mnt-cost-total strong { color:#fff; }

    /* ══ لوحتا أسطر العمالة والقطع — تصميم قوي متّسق مع هوية الفورمات ══ */
    .mnt-lines-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .mnt-lines-card { overflow:hidden; }
    .mnt-lines-card > .card-header.mnt-lines-head {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:linear-gradient(135deg,#1f4f7a,#2f6fa5); color:#fff; padding:13px 16px; border:none;
    }
    .mnt-lines-head h5 { display:flex; align-items:center; gap:8px; margin:0; color:#fff; font-weight:800; font-size:1rem; }
    .mnt-lines-head h5 i { color:#ffd98a; }
    .mnt-count { display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:24px; padding:0 8px; border-radius:999px; background:rgba(255,255,255,.22); color:#fff; font-size:.76rem; font-weight:800; }
    .mnt-add-toggle {
        display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;
        padding:7px 15px; border-radius:999px; font-weight:800; font-size:.82rem; color:#1a1208;
        background:linear-gradient(135deg,#E0AE2E,#f5d27e); box-shadow:0 2px 8px rgba(224,174,46,.4); transition:transform .15s, box-shadow .15s;
    }
    .mnt-add-toggle:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(224,174,46,.5); }
    .mnt-line-form { background:linear-gradient(180deg,#fffdf7,#fbf6ea); border:1px solid var(--bdr,#e7dcc4); border-radius:16px; padding:14px; margin-bottom:14px; box-shadow:inset 0 1px 0 #fff, 0 2px 8px rgba(26,18,8,.05); }
    .mnt-line-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; align-items:end; }
    .mnt-line-grid .form-group { margin:0; }
    .mnt-line-actions { display:flex; align-items:center; gap:10px; margin-top:14px; flex-wrap:wrap; padding-top:12px; border-top:1px dashed var(--bdr,#e7dcc4); }
    .mnt-line-cancel { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
    .mnt-major-chk { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; font-weight:700; font-size:.85rem; margin:0; cursor:pointer; }

    /* جداول الأسطر — قوية وواضحة */
    .mnt-line-table { width:100%; border-collapse:separate; border-spacing:0; }
    .mnt-line-table thead th { background:#f3ede0; color:#6b5d3e; font-weight:800; font-size:.82rem; padding:10px 12px; border-bottom:2px solid #e7dcc4; }
    .mnt-line-table tbody td { font-size:.88rem; padding:10px 12px; border-bottom:1px solid #f0e9da; }
    .mnt-line-table tbody tr:hover { background:rgba(224,174,46,.07); }
    .mnt-line-table .mnt-num { font-variant-numeric:tabular-nums; font-weight:700; text-align:start; }
    .mnt-line-table .mnt-center, .mnt-line-table td:last-child, .mnt-line-table th:last-child { text-align:center; }
    .mnt-line-table tfoot th { background:#1f4f7a; color:#fff; font-weight:800; font-variant-numeric:tabular-nums; font-size:.92rem; padding:11px 12px; }
    .mnt-line-table tfoot th:first-child { text-align:start; }
    .mnt-empty-line { display:flex; flex-direction:column; align-items:center; gap:8px; color:#b0a489; padding:22px 10px; }
    .mnt-empty-line i { font-size:1.9rem; opacity:.5; }
    .mnt-empty-line span { font-size:.9rem; font-weight:600; }
    .mnt-req-hint { color:#b45309; font-size:.72rem; font-weight:700; }
    @media (max-width:992px){ .mnt-lines-grid{ grid-template-columns:1fr; } .mnt-orders-main .mnt-cost-summary{ grid-template-columns:repeat(2,1fr);} }
</style>
</body>
</html>
