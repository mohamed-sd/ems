<?php
// معالج إضافة/حذف سطور كرت المعدة (الوثائق/الحماية/المكوّنات/التاريخ).
// إضافي وآمن: Prepared Statements + عزل الشركة + صلاحية تعديل الأسطول + رفع محمي.
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/security.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super     = ($current_role === '-1');
$company_id   = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id      = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
$company_val  = $is_super ? null : $company_id;

$equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
$entity = isset($_POST['entity']) ? preg_replace('/[^a-z]/', '', $_POST['entity']) : '';
$action = isset($_POST['action']) ? preg_replace('/[^a-z]/', '', $_POST['action']) : 'add';
$anchor = ['compliance' => 'docs', 'protection' => 'protection', 'component' => 'components', 'history' => 'history'][$entity] ?? '';

function fleet_redirect($equipment_id, $anchor, $msg)
{
    $url = 'equipment_profile.php?id=' . intval($equipment_id) . '&msg=' . urlencode($msg);
    if ($anchor) $url .= '#sec-' . $anchor;
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $equipment_id <= 0 || $entity === '') {
    fleet_redirect($equipment_id, $anchor, '❌ طلب غير صالح');
}

// صلاحية: تعديل المعدات (دور الأسطول)
$perm = check_page_permissions($conn, 'equipments_fleet');
if (empty($perm['can_edit'])) {
    fleet_redirect($equipment_id, $anchor, '❌ لا توجد صلاحية');
}

// عزل الشركة: التأكد أن المعدة تخصّ شركة المستخدم
if ($is_super) {
    $eq = $conn->prepare("SELECT company_id FROM equipments WHERE id = ?");
    $eq->bind_param("i", $equipment_id);
} else {
    $eq = $conn->prepare("SELECT company_id FROM equipments WHERE id = ? AND company_id = ?");
    $eq->bind_param("ii", $equipment_id, $company_id);
}
$eq->execute();
$eqRow = $eq->get_result()->fetch_assoc();
if (!$eqRow) {
    fleet_redirect($equipment_id, $anchor, '❌ المعدة غير موجودة أو خارج نطاق شركتك');
}
if ($is_super) { $company_val = isset($eqRow['company_id']) ? $eqRow['company_id'] : null; }

$tables = [
    'compliance' => 'fleet_equipment_compliance',
    'protection' => 'fleet_equipment_protection',
    'component'  => 'fleet_equipment_component',
    'history'    => 'fleet_equipment_history',
];
if (!isset($tables[$entity])) {
    fleet_redirect($equipment_id, $anchor, '❌ نوع غير معروف');
}
$table = $tables[$entity];

// ── حذف ناعم (عدا التاريخ) ──
if ($action === 'delete') {
    if ($entity === 'history') {
        fleet_redirect($equipment_id, $anchor, '❌ سجل التاريخ للإضافة فقط');
    }
    $row_id = isset($_POST['row_id']) ? intval($_POST['row_id']) : 0;
    $scope = $is_super ? '' : ' AND company_id = ' . intval($company_id);
    $st = $conn->prepare("UPDATE `$table` SET is_deleted = 1 WHERE id = ? AND equipment_id = ?" . $scope);
    $st->bind_param("ii", $row_id, $equipment_id);
    $st->execute();
    fleet_redirect($equipment_id, $anchor, '🗑️ تم الحذف');
}

// ── رفع مرفق إلى التخزين المحمي storage/fleet ──
function fleet_upload_attachment($field)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $dir = __DIR__ . '/../storage/fleet/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (function_exists('validate_file_upload')) {
        $check = validate_file_upload($_FILES[$field], ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5 * 1024 * 1024);
        if (empty($check['valid'])) { return null; }
    }
    $name = function_exists('generate_safe_filename')
        ? generate_safe_filename($_FILES[$field]['name'])
        : (bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION)));
    if (@move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name)) {
        return 'storage/fleet/' . $name;
    }
    return null;
}

// أدوات قراءة
$S = function ($k) { return trim($_POST[$k] ?? ''); };
$D = function ($k) { $v = trim($_POST[$k] ?? ''); return $v === '' ? null : $v; };           // تاريخ/نص أو NULL
$N = function ($k) { $v = trim($_POST[$k] ?? ''); return $v === '' ? null : (float) $v; };    // رقم أو NULL
$I = function ($k) { $v = trim($_POST[$k] ?? ''); return $v === '' ? null : (int) $v; };      // معرف أو NULL

// ── إضافة حسب النوع ──
if ($entity === 'compliance') {
    $doc_type = $S('doc_type');
    if ($doc_type === '') fleet_redirect($equipment_id, $anchor, '❌ نوع الوثيقة مطلوب');
    $reference = $D('reference');
    $issue = $D('issue_date');
    $expiry = $D('expiry_date');
    $is_critical = !empty($_POST['is_critical']) ? 1 : 0;
    $att = fleet_upload_attachment('attachment');
    $st = $conn->prepare("INSERT INTO fleet_equipment_compliance (company_id, equipment_id, doc_type, reference, issue_date, expiry_date, is_critical, attachment_path, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param("iissssisi", $company_val, $equipment_id, $doc_type, $reference, $issue, $expiry, $is_critical, $att, $user_id);
    $st->execute();
    fleet_redirect($equipment_id, $anchor, '✅ تم إضافة الوثيقة');
}

if ($entity === 'protection') {
    $ptype = $S('protection_type');
    if ($ptype === '') fleet_redirect($equipment_id, $anchor, '❌ نوع الحماية مطلوب');
    $desc = $D('description');
    $start = $D('start_date');
    $cost = $N('cost');
    $state = $D('state');
    $renewal = $D('renewal_date');
    $partner_name = $D('partner_name'); // المنفّذ/المورد: إدخال يدوي حرّ (غير مربوط بجدول الموردين)
    $compliance = $I('compliance_id');
    $att = fleet_upload_attachment('attachment');
    $st = $conn->prepare("INSERT INTO fleet_equipment_protection (company_id, equipment_id, protection_type, description, start_date, cost, state, renewal_date, partner_name, compliance_id, attachment_path, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param("iisssdsssisi", $company_val, $equipment_id, $ptype, $desc, $start, $cost, $state, $renewal, $partner_name, $compliance, $att, $user_id);
    $st->execute();
    fleet_redirect($equipment_id, $anchor, '✅ تم إضافة تجهيز الحماية');
}

if ($entity === 'component') {
    $ctype = $S('component_type');
    if ($ctype === '') fleet_redirect($equipment_id, $anchor, '❌ نوع المكوّن مطلوب');
    $serial = $D('serial_no');
    $install = $D('install_date');
    $is_current = !empty($_POST['is_current']) ? 1 : 0;
    $st = $conn->prepare("INSERT INTO fleet_equipment_component (company_id, equipment_id, component_type, serial_no, install_date, is_current, created_by) VALUES (?,?,?,?,?,?,?)");
    $st->bind_param("iisssii", $company_val, $equipment_id, $ctype, $serial, $install, $is_current, $user_id);
    $st->execute();
    fleet_redirect($equipment_id, $anchor, '✅ تم إضافة المكوّن');
}

if ($entity === 'history') {
    $event_type = $S('event_type');
    $event_date = $S('event_date');
    if ($event_type === '' || $event_date === '') fleet_redirect($equipment_id, $anchor, '❌ نوع الحدث وتاريخه مطلوبان');
    $event_dt = str_replace('T', ' ', $event_date);
    if (strlen($event_dt) === 16) $event_dt .= ':00'; // datetime-local → Y-m-d H:i:s
    $project = $I('project_id');
    $site = $D('site_id');
    $inout = $D('in_out_date');
    $note = $D('note');
    $st = $conn->prepare("INSERT INTO fleet_equipment_history (company_id, equipment_id, event_date, event_type, project_id, site_id, in_out_date, note, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param("iississsi", $company_val, $equipment_id, $event_dt, $event_type, $project, $site, $inout, $note, $user_id);
    $st->execute();
    fleet_redirect($equipment_id, $anchor, '✅ تم تسجيل الحدث');
}

fleet_redirect($equipment_id, $anchor, '❌ تعذّر الحفظ');
