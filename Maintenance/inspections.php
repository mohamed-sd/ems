<?php
/**
 * Maintenance/inspections.php — التفتيش الفني (نظام تأكيدات مقولب).
 * يحمّل بنود الاستمارة جاهزةً من القالب (mnt_inspection_template) حسب نوع التفتيش،
 * ويفلترها حسب فئة المعدة، فيؤكّد الفنّي حالة كل بند وتُحسب الدرجة تلقائيًّا.
 * عند الإكمال (مكتمل) تُكتب الحالة الفنية على كرت المعدة (DEC-12).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/mnt_helpers.php';

$current_role    = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin  = ($current_role === '-1');
$company_id      = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$page_permissions = check_page_permissions($conn, 'Maintenance/inspections.php');
$can_view   = $is_super_admin ? true : $page_permissions['can_view'];
$can_add    = $is_super_admin ? true : $page_permissions['can_add'];
$can_edit   = $is_super_admin ? true : $page_permissions['can_edit'];
$can_delete = $is_super_admin ? true : $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+التفتيش+❌"); exit(); }

$company_scope_sql = $is_super_admin ? "1=1" : "i.company_id = " . intval($company_id);

// ── الحالات وقوائم القيم ───────────────────────────────────────────────
$states     = array('جديد', 'مجدول', 'قيد التنفيذ', 'مكتمل', 'مغلق');
$conditions = array('ممتازة', 'جيدة', 'متوسطة', 'ضعيفة', 'حرجة');
$readiness  = array('جاهزة', 'جاهزة بتحفّظ', 'غير جاهزة');

// رموز حالة البند حسب مخطّط الاستمارة (condition_scale)
$SCALES = array(
    'default'  => array('سليم', 'ملاحظة', 'حرج', 'لا ينطبق'),
    'accident' => array('سليم', 'ضرر طفيف', 'ضرر متوسط', 'ضرر بالغ', 'يحتاج استبدال'),
    'overhaul' => array('صالح', 'تآكل ضمن الحد', 'يحتاج عمرة', 'يحتاج استبدال'),
);
$GOOD_STATES = array('سليم', 'صالح');
$NA_STATES   = array('لا ينطبق');
$CRIT_STATES = array('حرج', 'ضرر بالغ', 'يحتاج استبدال', 'يحتاج عمرة');

// استمارات «تقييم» (أسئلة وقرار): تُبقي حقول التفاصيل ظاهرة بلا افتراض «سليم».
// الباقي «فحص سريع» (checklist): افتراض ذكي + إخفاء التفاصيل حتى الحاجة.
$ASSESSMENT_CODES = array('EQUIP-MNT-PUR', 'EQUIP-MNT-SUP', 'EQUIP-MNT-OVH', 'EQUIP-MNT-DSP', 'EQUIP-MNT-PERF');

function mnt_scale_options($scale, $SCALES) {
    return isset($SCALES[$scale]) ? $SCALES[$scale] : $SCALES['default'];
}
function mnt_form_kind($type_code, $ASSESSMENT_CODES) {
    return in_array((string) $type_code, $ASSESSMENT_CODES, true) ? 'assessment' : 'checklist';
}
function mnt_default_state($scale, $kind, $SCALES) {
    if ($kind !== 'checklist') return '';
    $opts = isset($SCALES[$scale]) ? $SCALES[$scale] : $SCALES['default'];
    return isset($opts[0]) ? $opts[0] : ''; // سليم/صالح
}

// ── تطبيع فئة المعدة ومطابقة «ينطبق على» ───────────────────────────────
function mnt_norm_cat($s) {
    $s = trim((string) $s);
    $s = str_replace("\xD9\x91", '', $s); // إزالة الشدّة ّ
    $map = array('دريل' => 'خرامة', 'خرامه' => 'خرامة', 'حفاره' => 'حفار', 'لودر' => 'لودر');
    return isset($map[$s]) ? $map[$s] : $s;
}
function mnt_line_applies($applies_to, $eq_cat_norm) {
    $applies_to = trim((string) $applies_to);
    if ($applies_to === '' || strpos($applies_to, 'عام') !== false) return true;
    if ($eq_cat_norm === '') return true;
    foreach (preg_split('/[·\/,]+/u', $applies_to) as $tok) {
        if ($eq_cat_norm !== '' && mnt_norm_cat($tok) === $eq_cat_norm) return true;
    }
    return false;
}

// ── تحميل القوالب (عامة + خاصة بالشركة) ────────────────────────────────
$templates = array();
$templates_by_id = array();
if (db_table_has_column($conn, 'mnt_inspection_template', 'id')) {
    $tscope = $is_super_admin ? "company_id IS NULL" : "(company_id IS NULL OR company_id = " . intval($company_id) . ")";
    if ($tr = mysqli_query($conn, "SELECT id, type_code, name, inspection_type, header_type, condition_scale
                                      FROM mnt_inspection_template
                                     WHERE $tscope AND is_active = 1 ORDER BY sort_order, id")) {
        while ($t = mysqli_fetch_assoc($tr)) {
            $templates[$t['inspection_type']] = $t;
            $templates_by_id[intval($t['id'])] = $t;
        }
    }
}
$legacy_types = array('دوري', 'زيارة ميدانية', 'استلام', 'بعد حادث');
$valid_types  = array_values(array_unique(array_merge(array_keys($templates), $legacy_types)));

function mnt_fetch_inspection($conn, $id, $company_id, $is_super_admin) {
    $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $res = mysqli_query($conn, "SELECT * FROM mnt_inspection WHERE id = " . intval($id) . " AND COALESCE(is_deleted,0)=0" . $scope . " LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}
function mnt_ins_line_count($conn, $iid, $company_id) {
    $c = 0;
    if ($cs = mysqli_prepare($conn, "SELECT COUNT(*) c FROM mnt_inspection_line WHERE inspection_id=? AND company_id=?")) {
        mysqli_stmt_bind_param($cs, 'ii', $iid, $company_id); mysqli_stmt_execute($cs);
        $cr = mysqli_stmt_get_result($cs); if ($cr && ($x = mysqli_fetch_assoc($cr))) { $c = intval($x['c']); }
        mysqli_stmt_close($cs);
    }
    return $c;
}
function mnt_ins_json($data) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function mnt_equipment_category_norm($conn, $equipment_id, $company_id) {
    $equipment_id = intval($equipment_id);
    if ($equipment_id <= 0) return '';
    $cat = '';
    $q = mysqli_query($conn, "SELECT et.type AS cat
                                FROM equipments e
                                LEFT JOIN equipments_types et ON et.id = e.type
                               WHERE e.id = $equipment_id LIMIT 1");
    if ($q && ($r = mysqli_fetch_assoc($q))) { $cat = (string) ($r['cat'] ?? ''); }
    return mnt_norm_cat($cat);
}
function mnt_seed_lines_from_template($conn, $iid, $company_id, $template_id, $equipment_id, $header_type, $default_state = '') {
    $template_id = intval($template_id);
    if ($template_id <= 0) return 0;
    $eq_cat = ($header_type === 'equipment') ? mnt_equipment_category_norm($conn, $equipment_id, $company_id) : '';
    $q = mysqli_query($conn, "SELECT id, section, seq, item, applies_to, check_method, reference_limit
                                FROM mnt_inspection_template_line
                               WHERE template_id = $template_id ORDER BY seq, id");
    if (!$q) return 0;
    $n = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO mnt_inspection_line
            (company_id, inspection_id, template_line_id, component, section, applies_to, check_method, measured_value, note, seq, condition_state, recommendation, is_template)
            VALUES (?,?,?,?,?,?,?,'','',?,?,'',1)");
    if (!$stmt) return 0;
    while ($l = mysqli_fetch_assoc($q)) {
        if (!mnt_line_applies($l['applies_to'], $eq_cat)) continue;
        $tlid = intval($l['id']); $seq = intval($l['seq']);
        mysqli_stmt_bind_param($stmt, 'iiissssis', $company_id, $iid, $tlid,
            $l['item'], $l['section'], $l['applies_to'], $l['check_method'], $seq, $default_state);
        mysqli_stmt_execute($stmt); $n++;
    }
    mysqli_stmt_close($stmt);
    return $n;
}

// ══ AJAX: بند إضافي يدوي (إضافة/حذف) ══
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax && in_array($_POST['action'] ?? '', array('add_line', 'del_line'), true)) {
    if (!$can_edit) { mnt_ins_json(array('success' => false, 'message' => 'لا توجد صلاحية للتعديل')); }
    $iid = intval($_POST['inspection_id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if (!$ins) { mnt_ins_json(array('success' => false, 'message' => 'التفتيش غير موجود')); }
    if ($ins['state'] === 'مكتمل' || $ins['state'] === 'مغلق') { mnt_ins_json(array('success' => false, 'message' => 'لا يمكن تعديل بنود تفتيش مكتمل/مغلق')); }

    if ($_POST['action'] === 'add_line') {
        $component = trim($_POST['component'] ?? '');
        if ($component === '') { mnt_ins_json(array('success' => false, 'message' => 'اسم البند مطلوب')); }
        $cond = trim($_POST['condition_state'] ?? '');
        $mv   = trim($_POST['measured_value'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $rec  = trim($_POST['recommendation'] ?? '');
        $lineId = 0;
        if ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_inspection_line
                (company_id, inspection_id, component, section, applies_to, check_method, measured_value, note, condition_state, recommendation, is_template)
                VALUES (?,?,?,?, 'عام','', ?,?,?,?, 0)")) {
            $section = 'بنود إضافية';
            mysqli_stmt_bind_param($stmt, 'iissssss', $company_id, $iid, $component, $section, $mv, $note, $cond, $rec);
            mysqli_stmt_execute($stmt); $lineId = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
        }
        mnt_ins_json(array('success' => true,
            'line' => array('id' => $lineId, 'component' => $component, 'condition_state' => $cond,
                            'measured_value' => $mv, 'note' => $note, 'recommendation' => $rec),
            'count' => mnt_ins_line_count($conn, $iid, $company_id)));
    }

    if ($_POST['action'] === 'del_line') {
        $lid = intval($_POST['line_id'] ?? 0);
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_inspection_line WHERE id=? AND inspection_id=? AND company_id=? AND is_template=0")) {
            mysqli_stmt_bind_param($stmt, 'iii', $lid, $iid, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mnt_ins_json(array('success' => true, 'count' => mnt_ins_line_count($conn, $iid, $company_id)));
    }
}

// ── إنشاء تفتيش جديد + نسخ بنود القالب ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_inspection') {
    if (!$can_add) { header("Location: inspections.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: inspections.php?msg=لا+يمكن+الإنشاء+بلا+شركة+❌"); exit(); }

    $inspection_type = in_array($_POST['inspection_type'] ?? '', $valid_types, true) ? $_POST['inspection_type'] : (count($valid_types) ? $valid_types[0] : 'دوري');
    $tpl = isset($templates[$inspection_type]) ? $templates[$inspection_type] : null;
    $header_type = $tpl ? $tpl['header_type'] : 'equipment';

    $equipment_id   = ($header_type === 'equipment' && !empty($_POST['equipment_id'])) ? intval($_POST['equipment_id']) : null;
    $supplier_id    = (in_array($header_type, array('supplier', 'external'), true) && !empty($_POST['supplier_id'])) ? intval($_POST['supplier_id']) : null;
    $external_eq    = ($header_type === 'external') ? trim($_POST['external_equipment'] ?? '') : '';
    $project_id     = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $inspector_id   = !empty($_POST['inspector_id']) ? intval($_POST['inspector_id']) : null;
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
    $template_id    = $tpl ? intval($tpl['id']) : null;

    $code = mnt_next_code($conn, 'mnt_inspection', 'INS', $company_id);
    $new_id = 0;
    $sql = "INSERT INTO mnt_inspection (company_id, code, inspection_type, template_id, equipment_id, supplier_id, external_equipment, project_id, inspector_id, scheduled_date, state, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'جديد', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'issiiisiisi', $company_id, $code, $inspection_type, $template_id, $equipment_id, $supplier_id, $external_eq, $project_id, $inspector_id, $scheduled_date, $current_user_id);
        mysqli_stmt_execute($stmt); $new_id = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    }
    if ($new_id && $template_id) {
        $seed_kind  = mnt_form_kind($tpl ? $tpl['type_code'] : '', $ASSESSMENT_CODES);
        $seed_scale = $tpl ? $tpl['condition_scale'] : 'default';
        $seed_def   = mnt_default_state($seed_scale, $seed_kind, $SCALES);
        mnt_seed_lines_from_template($conn, $new_id, $company_id, $template_id, $equipment_id, $header_type, $seed_def);
    }
    header("Location: inspections.php?id=" . intval($new_id) . "&msg=تم+إنشاء+التفتيش+وتحميل+بنوده+✅"); exit();
}

// ── حفظ رأس التفتيش + بنوده دفعةً + حساب الدرجة + منطق الإكمال ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_inspection') {
    if (!$can_edit) { header("Location: inspections.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    $iid = intval($_POST['id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if (!$ins) { header("Location: inspections.php?msg=التفتيش+غير+موجود+❌"); exit(); }
    $locked = ($ins['state'] === 'مكتمل' || $ins['state'] === 'مغلق');

    $tpl   = isset($templates_by_id[intval($ins['template_id'])]) ? $templates_by_id[intval($ins['template_id'])] : null;
    $scale = $tpl ? $tpl['condition_scale'] : 'default';
    $header_type = $tpl ? $tpl['header_type'] : 'equipment';

    $inspection_type = in_array($_POST['inspection_type'] ?? '', $valid_types, true) ? $_POST['inspection_type'] : $ins['inspection_type'];
    $equipment_id  = ($header_type === 'equipment' && !empty($_POST['equipment_id'])) ? intval($_POST['equipment_id']) : null;
    $supplier_id   = (in_array($header_type, array('supplier', 'external'), true) && !empty($_POST['supplier_id'])) ? intval($_POST['supplier_id']) : null;
    $external_eq   = ($header_type === 'external') ? trim($_POST['external_equipment'] ?? '') : (string) ($ins['external_equipment'] ?? '');
    $project_id    = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $inspector_id  = !empty($_POST['inspector_id']) ? intval($_POST['inspector_id']) : null;
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
    $overall_result = trim($_POST['overall_result'] ?? '');
    $tech_readiness = trim($_POST['tech_readiness_state'] ?? '');
    $equipment_condition = trim($_POST['equipment_condition'] ?? '');
    $engine_condition    = trim($_POST['engine_condition'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $requested_state = in_array($_POST['state'] ?? '', $states, true) ? $_POST['state'] : $ins['state'];

    // (أ) حفظ بنود الفحص المؤكَّدة
    if (!$locked && isset($_POST['line']) && is_array($_POST['line'])) {
        $up = mysqli_prepare($conn, "UPDATE mnt_inspection_line SET condition_state=?, measured_value=?, note=?, recommendation=? WHERE id=? AND inspection_id=? AND company_id=?");
        if ($up) {
            foreach ($_POST['line'] as $lid => $ld) {
                $lid = intval($lid);
                $cs  = isset($ld['condition_state']) ? trim($ld['condition_state']) : '';
                $mv  = isset($ld['measured_value']) ? trim($ld['measured_value']) : '';
                $nt  = isset($ld['note']) ? trim($ld['note']) : '';
                $rc  = isset($ld['recommendation']) ? trim($ld['recommendation']) : '';
                mysqli_stmt_bind_param($up, 'ssssiii', $cs, $mv, $nt, $rc, $lid, $iid, $company_id);
                mysqli_stmt_execute($up);
            }
            mysqli_stmt_close($up);
        }
    }

    // (ب) حساب الدرجة والعدّادات من البنود الفعلية
    $good = $applicable = $critical = 0;
    if ($lr = mysqli_query($conn, "SELECT condition_state FROM mnt_inspection_line WHERE inspection_id=" . intval($iid) . " AND company_id=" . intval($company_id))) {
        while ($lx = mysqli_fetch_assoc($lr)) {
            $cs = trim((string) $lx['condition_state']);
            if ($cs === '' || in_array($cs, $NA_STATES, true)) continue;
            $applicable++;
            if (in_array($cs, $GOOD_STATES, true)) $good++;
            if (in_array($cs, $CRIT_STATES, true)) $critical++;
        }
    }
    $score = $applicable > 0 ? intval(round(100 * $good / $applicable)) : null;

    $completing_now = ($requested_state === 'مكتمل' && $ins['state'] !== 'مكتمل');

    $sql = "UPDATE mnt_inspection SET
                inspection_type=?, equipment_id=?, supplier_id=?, external_equipment=?, project_id=?, inspector_id=?, scheduled_date=?,
                score=?, overall_result=?, tech_readiness_state=?, equipment_condition=?, engine_condition=?,
                notes=?, state=?"
            . ($completing_now ? ", completed_at=NOW()" : "")
            . " WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // s i i s i i | s | i | s s s s s s | i i  = 16
        $tp = 'siisii' . 's' . 'i' . 'ssssss' . 'ii';
        mysqli_stmt_bind_param($stmt, $tp,
            $inspection_type, $equipment_id, $supplier_id, $external_eq, $project_id, $inspector_id, $scheduled_date,
            $score, $overall_result, $tech_readiness, $equipment_condition, $engine_condition,
            $notes, $requested_state, $iid, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }

    if ($completing_now && $equipment_id) {
        mnt_apply_inspection_to_equipment($conn, $equipment_id, $company_id, $equipment_condition, $engine_condition);
    }

    header("Location: inspections.php?id=" . intval($iid) . "&msg=تم+حفظ+التفتيش+✅"); exit();
}

// ── إضافة بند إضافي (مسار non-AJAX احتياطي) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_line') {
    if (!$can_edit) { header("Location: inspections.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $iid = intval($_POST['inspection_id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if ($ins && ($ins['state'] === 'مكتمل' || $ins['state'] === 'مغلق')) {
        header("Location: inspections.php?id=" . intval($iid) . "&msg=" . urlencode('لا يمكن تعديل بنود تفتيش مكتمل/مغلق ❌')); exit();
    }
    if ($ins) {
        $component = trim($_POST['component'] ?? '');
        $cond = trim($_POST['condition_state'] ?? '');
        $rec = trim($_POST['recommendation'] ?? '');
        if ($component !== '' && ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_inspection_line (company_id, inspection_id, component, section, applies_to, condition_state, recommendation, is_template) VALUES (?,?,?, 'بنود إضافية','عام', ?,?, 0)"))) {
            mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $iid, $component, $cond, $rec);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
    }
    header("Location: inspections.php?id=" . intval($iid) . "&msg=تمت+إضافة+البند+✅"); exit();
}
if (isset($_GET['del_line'], $_GET['inspection_id'])) {
    if ($can_edit) {
        $lid = intval($_GET['del_line']); $iid = intval($_GET['inspection_id']);
        $ins_lock = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
        if ($ins_lock && ($ins_lock['state'] === 'مكتمل' || $ins_lock['state'] === 'مغلق')) {
            header("Location: inspections.php?id=" . $iid . "&msg=" . urlencode('لا يمكن حذف بنود تفتيش مكتمل/مغلق ❌')); exit();
        }
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_inspection_line WHERE id=? AND inspection_id=? AND company_id=? AND is_template=0")) {
            mysqli_stmt_bind_param($stmt, 'iii', $lid, $iid, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: inspections.php?id=" . $iid . "&msg=تم+حذف+البند+✅"); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: inspections.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $did = intval($_GET['delete_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE mnt_inspection SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $did, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: inspections.php?msg=تم+حذف+التفتيش+✅"); exit();
}

$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$ins = $edit_id > 0 ? mnt_fetch_inspection($conn, $edit_id, $company_id, $is_super_admin) : null;

$equipments = array(); $projects = array(); $users_list = array(); $suppliers = array();
if ($ins || $edit_id === 0) {
    $cscope = $is_super_admin ? "1=1" : "company_id = " . intval($company_id);
    if ($r = mysqli_query($conn, "SELECT id, name, code FROM equipments WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $equipments[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM project WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $projects[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM users WHERE $cscope AND is_deleted=0 ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $users_list[] = $x; }
    if (db_table_has_column($conn, 'suppliers', 'id')) {
        if ($r = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $suppliers[] = $x; }
    }
}

$page_title = 'إيكوبيشن | التفتيش الفني';
include '../inheader.php';
include '../insidebar.php';
function mnt_opt($value, $label, $selected, $data = '') {
    return "<option value='" . htmlspecialchars((string) $value, ENT_QUOTES) . "'" . ($selected ? " selected" : "") . ($data !== '' ? ' ' . $data : '') . ">" . htmlspecialchars((string) $label) . "</option>";
}
function mnt_cond_class($c) {
    if (in_array($c, array('حرج', 'ضرر بالغ', 'يحتاج استبدال', 'يحتاج عمرة'), true)) return 'mnt-cond mnt-cond--crit';
    if (in_array($c, array('ملاحظة', 'ضرر طفيف', 'ضرر متوسط', 'تآكل ضمن الحد'), true)) return 'mnt-cond mnt-cond--note';
    if (in_array($c, array('لا ينطبق', ''), true)) return 'mnt-cond mnt-cond--na';
    return 'mnt-cond mnt-cond--ok';
}
function mnt_seg_kind($c) {
    if (in_array($c, array('حرج', 'ضرر بالغ', 'يحتاج استبدال', 'يحتاج عمرة'), true)) return 'crit';
    if (in_array($c, array('ملاحظة', 'ضرر طفيف', 'ضرر متوسط', 'تآكل ضمن الحد'), true)) return 'note';
    if (in_array($c, array('لا ينطبق'), true)) return 'na';
    return 'ok';
}
?>
<div class="main mnt-inspections-main ems-unified-page-shell">

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>" style="margin-bottom:12px;">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

<?php if ($ins): // ── صفحة تحرير تفتيش ──
    $tpl   = isset($templates_by_id[intval($ins['template_id'])]) ? $templates_by_id[intval($ins['template_id'])] : null;
    $scale = $tpl ? $tpl['condition_scale'] : 'default';
    $header_type = $tpl ? $tpl['header_type'] : 'equipment';
    $scale_opts  = mnt_scale_options($scale, $SCALES);
    $kind        = mnt_form_kind($tpl ? $tpl['type_code'] : '', $ASSESSMENT_CODES);
    $is_assess   = ($kind === 'assessment');

    $lines = array();
    if ($s = mysqli_prepare($conn, "SELECT l.id, l.component, l.section, l.applies_to, l.check_method, l.measured_value, l.note, l.condition_state, l.recommendation, l.is_template, l.seq, tl.reference_limit AS ref_hint
                                       FROM mnt_inspection_line l
                                       LEFT JOIN mnt_inspection_template_line tl ON tl.id = l.template_line_id
                                      WHERE l.inspection_id=? AND l.company_id=? ORDER BY l.is_template DESC, l.seq, l.id")) {
        mysqli_stmt_bind_param($s, 'ii', $edit_id, $company_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($rr && $x = mysqli_fetch_assoc($rr)) $lines[] = $x; mysqli_stmt_close($s);
    }
    $cnt_good = $cnt_note = $cnt_crit = $cnt_na = $cnt_app = 0;
    foreach ($lines as $l) {
        $cs = trim((string) $l['condition_state']);
        if ($cs === '' || in_array($cs, $NA_STATES, true)) { if (in_array($cs, $NA_STATES, true)) $cnt_na++; continue; }
        $cnt_app++;
        if (in_array($cs, $GOOD_STATES, true)) $cnt_good++;
        elseif (in_array($cs, $CRIT_STATES, true)) $cnt_crit++;
        else $cnt_note++;
    }
    $cnt_score = $cnt_app > 0 ? intval(round(100 * $cnt_good / $cnt_app)) : 0;
    $st = (string) $ins['state'];
    $locked = ($st === 'مكتمل' || $st === 'مغلق');
?>
    <?php
    $header_title_html = 'تفتيش: <strong>' . htmlspecialchars((string) $ins['code']) . '</strong> <span class="action-btn">' . htmlspecialchars($st) . '</span>' . ($tpl ? ' <span class="action-btn" style="background:#eef4fb;color:#1f4f7a;">' . htmlspecialchars((string) $tpl['name']) . '</span>' : '');
    $header_icon = 'fa fa-clipboard-check';
    $header_actions = array();
    $header_actions[] = array('id' => 'toggleInspForm', 'class' => 'add-btn', 'icon' => 'fas fa-pen-to-square', 'label' => 'أعلى الصفحة');
    $header_back = array(
        array('tag' => 'a', 'href' => 'inspections.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'كل عمليات التفتيش'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>
    <form method="post" action="" class="allforms allforms-visible" id="inspForm">
        <input type="hidden" name="action" value="save_inspection">
        <input type="hidden" name="id" value="<?php echo intval($ins['id']); ?>">

        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> بيانات التفتيش</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>نوع التفتيش</label>
                    <input type="text" value="<?php echo htmlspecialchars((string) $ins['inspection_type']); ?>" readonly>
                    <input type="hidden" name="inspection_type" value="<?php echo htmlspecialchars((string) $ins['inspection_type'], ENT_QUOTES); ?>">
                </div>

                <?php if ($header_type === 'equipment'): ?>
                <div class="form-group"><label>المشروع</label>
                    <select name="project_id" class="mnt-proj"><option value="">— اختر المشروع —</option>
                        <?php foreach ($projects as $p) echo mnt_opt($p['id'], $p['name'], intval($ins['project_id']) === intval($p['id'])); ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدة</label>
                    <select name="equipment_id" class="mnt-eq" data-selected="<?php echo intval($ins['equipment_id']); ?>"><option value="">— اختر المشروع أولاً —</option>
                        <?php foreach ($equipments as $e) echo mnt_opt($e['id'], $e['name'] . (!empty($e['code']) ? ' (' . $e['code'] . ')' : ''), intval($ins['equipment_id']) === intval($e['id'])); ?>
                    </select>
                </div>
                <?php elseif ($header_type === 'supplier'): ?>
                <div class="form-group"><label>المورّد</label>
                    <select name="supplier_id"><option value="">— اختر المورّد —</option>
                        <?php foreach ($suppliers as $sp) echo mnt_opt($sp['id'], $sp['name'], intval($ins['supplier_id']) === intval($sp['id'])); ?>
                    </select>
                </div>
                <?php else: // external ?>
                <div class="form-group"><label>المعدة المعروضة (وصف)</label>
                    <input type="text" name="external_equipment" value="<?php echo htmlspecialchars((string) ($ins['external_equipment'] ?? '')); ?>" placeholder="النوع/الماركة/الموديل">
                </div>
                <div class="form-group"><label>المالك / البائع</label>
                    <select name="supplier_id"><option value="">— اختر —</option>
                        <?php foreach ($suppliers as $sp) echo mnt_opt($sp['id'], $sp['name'], intval($ins['supplier_id']) === intval($sp['id'])); ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group"><label>الفاحص</label>
                    <select name="inspector_id"><option value="">-- اختر --</option>
                        <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], intval($ins['inspector_id']) === intval($u['id'])); ?>
                    </select>
                </div>
                <div class="form-group"><label>تاريخ التفتيش</label>
                    <input type="date" name="scheduled_date" value="<?php echo htmlspecialchars((string) $ins['scheduled_date']); ?>">
                </div>
                <div class="form-group"><label>الدرجة (تُحسب تلقائيًا)</label>
                    <input type="text" id="scoreReadout" value="<?php echo $cnt_app > 0 ? $cnt_score . '%' : '—'; ?>" readonly>
                </div>
                <div class="form-group"><label>الجاهزية الفنية</label>
                    <select name="tech_readiness_state"><option value="">-- اختر --</option>
                        <?php foreach ($readiness as $rd) echo mnt_opt($rd, $rd, $ins['tech_readiness_state'] === $rd); ?>
                    </select>
                </div>
                <div class="form-group"><label>النتيجة العامة</label>
                    <input type="text" name="overall_result" value="<?php echo htmlspecialchars((string) $ins['overall_result']); ?>">
                </div>
                <div class="form-group"><label>حالة المعدة (تُكتب للكرت عند الإكمال)</label>
                    <select name="equipment_condition"><option value="">-- اختر --</option>
                        <?php foreach ($conditions as $c) echo mnt_opt($c, $c, $ins['equipment_condition'] === $c); ?>
                    </select>
                </div>
                <div class="form-group"><label>حالة المحرك (تُكتب للكرت عند الإكمال)</label>
                    <select name="engine_condition"><option value="">-- اختر --</option>
                        <?php foreach ($conditions as $c) echo mnt_opt($c, $c, $ins['engine_condition'] === $c); ?>
                    </select>
                </div>
                <div class="form-group"><label>الحالة (المرحلة)</label>
                    <select name="state"><?php foreach ($states as $s) echo mnt_opt($s, $s, $st === $s); ?></select>
                </div>
                <div class="form-group allforms-span-full"><label>ملاحظات</label>
                    <textarea name="notes" rows="2"><?php echo htmlspecialchars((string) $ins['notes']); ?></textarea>
                </div>
            </div></div>
        </div></div>

        <!-- شريط الخلاصة المحسوب تلقائيًا -->
        <div class="mnt-summary" id="mntSummary">
            <div class="mnt-stat mnt-stat--ok"><span class="mnt-stat-label">سليم</span><span class="mnt-stat-num" data-stat="good"><?php echo $cnt_good; ?></span></div>
            <div class="mnt-stat mnt-stat--note"><span class="mnt-stat-label">ملاحظة</span><span class="mnt-stat-num" data-stat="note"><?php echo $cnt_note; ?></span></div>
            <div class="mnt-stat mnt-stat--crit"><span class="mnt-stat-label">حرج</span><span class="mnt-stat-num" data-stat="crit"><?php echo $cnt_crit; ?></span></div>
            <div class="mnt-stat mnt-stat--na"><span class="mnt-stat-label">لا ينطبق</span><span class="mnt-stat-num" data-stat="na"><?php echo $cnt_na; ?></span></div>
            <div class="mnt-stat mnt-stat--score"><span class="mnt-stat-label">الدرجة</span><span class="mnt-stat-num" data-stat="score"><?php echo $cnt_app > 0 ? $cnt_score . '%' : '—'; ?></span></div>
        </div>

        <!-- جدول التأكيدات -->
        <div class="card mnt-lines-card">
            <div class="card-header mnt-lines-head">
                <h5><i class="fas fa-list-check"></i> بنود الفحص <span class="mnt-count" id="lineCount"><?php echo count($lines); ?></span></h5>
                <?php if (!$locked && $can_edit): ?><button type="button" class="mnt-add-toggle" data-target="lineForm"><i class="fas fa-plus"></i> بند إضافي</button><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$locked && $can_edit): ?>
                <div class="mnt-line-form" id="lineForm" style="display:none;">
                    <div class="mnt-line-grid">
                        <div class="form-group"><label>البند</label><input type="text" id="xl_component" placeholder="بند خارج القالب"></div>
                        <div class="form-group"><label>الحالة</label><select id="xl_condition"><?php foreach ($scale_opts as $lc) echo mnt_opt($lc, $lc, false); ?></select></div>
                        <div class="form-group"><label>القيمة المقاسة</label><input type="text" id="xl_measured"></div>
                        <div class="form-group"><label>الملاحظة</label><input type="text" id="xl_note"></div>
                        <div class="form-group"><label>التوصية</label><input type="text" id="xl_rec"></div>
                    </div>
                    <div class="mnt-line-actions">
                        <button type="button" class="btn-save" id="xlAdd"><i class="fas fa-plus"></i> إضافة البند</button>
                        <button type="button" class="btn-cancel mnt-line-cancel" data-target="lineForm"><i class="fas fa-times"></i> إلغاء</button>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                $colspan = (!$locked && $can_edit) ? 4 : 3;
                // بناء قائمة المنظومات (تبويبات) بالترتيب
                $section_list = array(); $section_index = array();
                foreach ($lines as $l) {
                    $key = ((string) ($l['section'] ?? '')) !== '' ? (string) $l['section'] : 'بنود';
                    if (!isset($section_index[$key])) { $section_index[$key] = count($section_list); $section_list[] = $key; }
                }
                ?>
                <?php if (!empty($section_list)): ?>
                <div class="mnt-tabs" id="mntTabs">
                    <?php foreach ($section_list as $i => $sname): ?>
                        <button type="button" class="mnt-tab<?php echo $i === 0 ? ' is-active' : ''; ?>" data-sec="<?php echo $i; ?>">
                            <span class="mnt-tab-label"><?php echo htmlspecialchars($sname); ?></span>
                            <span class="mnt-tab-badge" data-tabbadge="<?php echo $i; ?>"></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="table-container"><table class="alltables no-datatable mnt-line-table" id="lineTable" data-kind="<?php echo htmlspecialchars($kind, ENT_QUOTES); ?>" style="width:100%">
                    <thead><tr>
                        <th>البند</th><th style="min-width:150px">الحالة</th><th>التفاصيل والتوصية</th>
                        <?php if (!$locked && $can_edit) echo '<th></th>'; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l):
                        $key = ((string) ($l['section'] ?? '')) !== '' ? (string) $l['section'] : 'بنود';
                        $sidx = $section_index[$key];
                        $cs = (string) $l['condition_state'];
                        $ref = (string) ($l['ref_hint'] ?? '');
                        $hintParts = array_filter(array(($l['applies_to'] ?: 'عام'), (string) ($l['check_method'] ?? ''), $ref));
                        $isT = intval($l['is_template']) === 1;
                    ?>
                        <tr class="mnt-item-row" data-sec="<?php echo $sidx; ?>" data-line="<?php echo intval($l['id']); ?>" data-tpl="<?php echo $isT ? 1 : 0; ?>">
                            <td class="mnt-item">
                                <?php echo htmlspecialchars((string) $l['component']); ?>
                                <?php if ($hintParts): ?><div class="mnt-hint"><?php echo htmlspecialchars(implode('  ·  ', $hintParts)); ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$locked && $can_edit): ?>
                                    <div class="mnt-cond-seg" data-line="<?php echo intval($l['id']); ?>">
                                        <input type="hidden" class="mnt-cond-input" name="line[<?php echo intval($l['id']); ?>][condition_state]" value="<?php echo htmlspecialchars($cs, ENT_QUOTES); ?>">
                                        <?php foreach ($scale_opts as $opt): ?>
                                            <button type="button" class="mnt-seg-btn seg-<?php echo mnt_seg_kind($opt); ?><?php echo $cs === $opt ? ' is-active' : ''; ?>" data-val="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"><?php echo htmlspecialchars($opt); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="<?php echo mnt_cond_class($cs); ?>"><?php echo htmlspecialchars($cs !== '' ? $cs : '—'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mnt-detail-cell">
                                <?php if (!$locked && $can_edit): ?>
                                    <div class="mnt-detail">
                                        <input type="text" class="mnt-d-measured" name="line[<?php echo intval($l['id']); ?>][measured_value]" value="<?php echo htmlspecialchars((string) ($l['measured_value'] ?? '')); ?>" placeholder="<?php echo $ref !== '' ? 'القيمة المقاسة (الحد: ' . htmlspecialchars($ref, ENT_QUOTES) . ')' : 'القيمة المقاسة'; ?>">
                                        <input type="text" name="line[<?php echo intval($l['id']); ?>][note]" value="<?php echo htmlspecialchars((string) ($l['note'] ?? '')); ?>" placeholder="ملاحظة">
                                        <input type="text" name="line[<?php echo intval($l['id']); ?>][recommendation]" value="<?php echo htmlspecialchars((string) ($l['recommendation'] ?? '')); ?>" placeholder="توصية">
                                    </div>
                                <?php else:
                                    $det = array_filter(array((string) ($l['measured_value'] ?? ''), (string) ($l['note'] ?? ''), (string) ($l['recommendation'] ?? '')));
                                    echo htmlspecialchars($det ? implode(' — ', $det) : '');
                                endif; ?>
                            </td>
                            <?php if (!$locked && $can_edit): ?>
                                <td class="mnt-act"><?php if (!$isT): ?><button type="button" class="action-btn delete mnt-del-line" data-line="<?php echo intval($l['id']); ?>" title="حذف"><i class="fas fa-trash-alt"></i></button><?php else: ?><span class="mnt-lock" title="بند قالب"><i class="fas fa-lock"></i></span><?php endif; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="mnt-empty-line" id="lineEmpty" style="<?php echo empty($lines) ? '' : 'display:none'; ?>"><i class="fas fa-list-check"></i><span>لا توجد بنود — اختر نوع تفتيش له قالب</span></div>

                <?php if (count($section_list) > 1): ?>
                <div class="mnt-tabnav">
                    <button type="button" class="mnt-nav-btn" id="tabPrev"><i class="fas fa-chevron-right"></i> السابق</button>
                    <span class="mnt-tabnav-pos" id="tabPos"></span>
                    <button type="button" class="mnt-nav-btn mnt-nav-next" id="tabNext">التالي <i class="fas fa-chevron-left"></i></button>
                </div>
                <?php endif; ?>

                <div class="mnt-cond-legend">
                    <span><i class="mnt-dot mnt-dot--ok"></i> سليم/صالح</span>
                    <span><i class="mnt-dot mnt-dot--note"></i> ملاحظة/طفيف</span>
                    <span><i class="mnt-dot mnt-dot--crit"></i> حرج/بالغ/استبدال</span>
                    <span><i class="mnt-dot mnt-dot--na"></i> لا ينطبق</span>
                </div>
            </div>
        </div>

        <?php if (!$locked && $can_edit): ?>
        <div class="form-actions" style="margin-top:14px;">
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ التفتيش والبنود</button>
        </div>
        <?php endif; ?>
    </form>

<?php else: // ── قائمة التفتيش ──
    $header_title  = 'التفتيش الفني';
    $header_icon   = 'fa fa-clipboard-check';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleCreateForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'تفتيش جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
?>
    <?php if ($can_add): ?>
    <form method="post" action="" class="allforms" id="inspCreateForm">
        <input type="hidden" name="action" value="new_inspection">
        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> إنشاء تفتيش جديد</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>نوع التفتيش</label>
                    <select name="inspection_type" id="createType">
                        <?php $first = true; foreach ($templates as $itype => $t) {
                            echo mnt_opt($itype, $t['name'], $first, "data-header='" . htmlspecialchars($t['header_type'], ENT_QUOTES) . "'");
                            $first = false;
                        } ?>
                    </select>
                </div>
                <div class="form-group hdr-equipment"><label>المشروع</label>
                    <select name="project_id" class="mnt-proj"><option value="">— اختر المشروع —</option>
                        <?php foreach ($projects as $p) echo mnt_opt($p['id'], $p['name'], false); ?>
                    </select>
                </div>
                <div class="form-group hdr-equipment"><label>المعدة</label>
                    <select name="equipment_id" class="mnt-eq" data-selected=""><option value="">— اختر المشروع أولاً —</option></select>
                </div>
                <div class="form-group hdr-supplier hdr-external" style="display:none"><label>المورّد / البائع</label>
                    <select name="supplier_id"><option value="">— اختر المورّد —</option>
                        <?php foreach ($suppliers as $sp) echo mnt_opt($sp['id'], $sp['name'], false); ?>
                    </select>
                </div>
                <div class="form-group hdr-external" style="display:none"><label>المعدة المعروضة (وصف)</label>
                    <input type="text" name="external_equipment" placeholder="النوع/الماركة/الموديل">
                </div>
                <div class="form-group"><label>الفاحص</label>
                    <select name="inspector_id"><option value="">-- اختر --</option>
                        <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], false); ?>
                    </select>
                </div>
                <div class="form-group"><label>تاريخ التفتيش</label>
                    <input type="date" name="scheduled_date">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إنشاء وتحميل البنود</button>
                <button type="button" class="btn-cancel" id="cancelCreateForm"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>
    <div class="card"><div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>تصفية حسب الحالة</label>
                <select id="filterState"><option value="">كل الحالات</option>
                    <?php foreach ($states as $s) echo "<option value='" . htmlspecialchars($s, ENT_QUOTES) . "'>" . htmlspecialchars($s) . "</option>"; ?>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table id="mntTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>المرجع</th><th>النوع</th><th>المعدة/الجهة</th><th>الفاحص</th><th>التاريخ</th><th>الدرجة</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT i.id, i.code, i.inspection_type, i.scheduled_date, i.completed_at,
                                   i.score, i.overall_result, i.tech_readiness_state,
                                   i.equipment_condition, i.engine_condition, i.notes, i.state, i.external_equipment,
                                   e.name AS equipment_name, p.name AS project_name, u.name AS inspector_name, sp.name AS supplier_name
                              FROM mnt_inspection i
                              LEFT JOIN equipments e ON e.id = i.equipment_id
                              LEFT JOIN project p    ON p.id = i.project_id
                              LEFT JOIN users u ON u.id = i.inspector_id
                              LEFT JOIN suppliers sp ON sp.id = i.supplier_id
                             WHERE $company_scope_sql AND COALESCE(i.is_deleted,0)=0
                             ORDER BY i.id DESC";
                    $insp_ids = array();
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $insp_ids[] = intval($row['id']);
                        $st = (string) $row['state'];
                        $subject = $row['equipment_name'] ?: ($row['supplier_name'] ?: ($row['external_equipment'] ?: '-'));
                        $da =
                            "data-id='"        . intval($row['id']) . "' " .
                            "data-code='"      . htmlspecialchars((string) $row['code'], ENT_QUOTES) . "' " .
                            "data-type='"      . htmlspecialchars((string) $row['inspection_type'], ENT_QUOTES) . "' " .
                            "data-equipment='" . htmlspecialchars((string) $subject, ENT_QUOTES) . "' " .
                            "data-project='"   . htmlspecialchars((string) ($row['project_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-inspector='" . htmlspecialchars((string) ($row['inspector_name'] ?? ''), ENT_QUOTES) . "' " .
                            "data-scheduled='" . htmlspecialchars((string) ($row['scheduled_date'] ?? ''), ENT_QUOTES) . "' " .
                            "data-completed='" . htmlspecialchars((string) ($row['completed_at'] ?? ''), ENT_QUOTES) . "' " .
                            "data-score='"     . htmlspecialchars((string) ($row['score'] ?? ''), ENT_QUOTES) . "' " .
                            "data-overall='"   . htmlspecialchars((string) ($row['overall_result'] ?? ''), ENT_QUOTES) . "' " .
                            "data-readiness='" . htmlspecialchars((string) ($row['tech_readiness_state'] ?? ''), ENT_QUOTES) . "' " .
                            "data-eqcond='"    . htmlspecialchars((string) ($row['equipment_condition'] ?? ''), ENT_QUOTES) . "' " .
                            "data-engcond='"   . htmlspecialchars((string) ($row['engine_condition'] ?? ''), ENT_QUOTES) . "' " .
                            "data-notes='"     . htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES) . "' " .
                            "data-state='"     . htmlspecialchars($st, ENT_QUOTES) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $da title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                        if ($can_edit) echo "<a href='inspections.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
                        if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف التفتيش؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        echo "</div></td>";
                        echo "<td><strong>" . htmlspecialchars((string) $row['code']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars((string) $row['inspection_type']) . "</td>";
                        echo "<td>" . htmlspecialchars((string) $subject) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['inspector_name'] ?? '-')) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['scheduled_date'] ?? '')) . "</td>";
                        echo "<td>" . ($row['score'] !== null && $row['score'] !== '' ? intval($row['score']) . '%' : '-') . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string) $row['state']) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php
    $mnt_insp_lines_map = array();
    if (!empty($insp_ids)) {
        $ids_csv = implode(',', array_map('intval', $insp_ids));
        $cid     = intval($company_id);
        $lq = "SELECT inspection_id, component, condition_state, measured_value, note, recommendation
                 FROM mnt_inspection_line
                WHERE inspection_id IN ($ids_csv) AND company_id = $cid
                ORDER BY is_template DESC, seq, id";
        if ($lr = mysqli_query($conn, $lq)) {
            while ($x = mysqli_fetch_assoc($lr)) {
                $iid = intval($x['inspection_id']);
                $mnt_insp_lines_map[$iid][] = array(
                    (string) $x['component'],
                    (string) ($x['condition_state'] ?? ''),
                    (string) ($x['measured_value'] ?? ''),
                    (string) ($x['note'] ?? ''),
                    (string) ($x['recommendation'] ?? '')
                );
            }
        }
    }
    echo '<script>window.MNT_INSP_LINES = ' . json_encode($mnt_insp_lines_map, JSON_UNESCAPED_UNICODE) . ';</script>';
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
    var GOOD = ['سليم','صالح'];
    var NA   = ['لا ينطبق'];
    var CRIT = ['حرج','ضرر بالغ','يحتاج استبدال','يحتاج عمرة'];

    $(document).ready(function () {
        if ($('#mntTable').length && $('#filterState').length) {
            var table = $('#mntTable').DataTable({
                scrollX: true, autoWidth: false, stateSave: false, order: [[1, 'desc']],
                dom: 'Bfrtip',
                buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
                "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
            });
            $('#filterState').on('change', function () {
                var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
                table.column(7).search(v, true, false).draw();
            });
        }

        $(document).on('click', '.viewBtn', function () {
            var d = $(this).data();
            var linesMap = window.MNT_INSP_LINES || {};
            var lines = linesMap[String(d.id)] || [];
            EmsDetailsModal.open({
                title: 'تفاصيل التفتيش',
                icon: 'fas fa-clipboard-check',
                fields: [
                    { label: 'المرجع', value: d.code, icon: 'fas fa-hashtag' },
                    { label: 'الحالة', value: d.state, icon: 'fas fa-flag', type: 'status' },
                    { label: 'النوع', value: d.type, icon: 'fas fa-list' },
                    { label: 'المعدة/الجهة', value: d.equipment, icon: 'fas fa-tractor' },
                    { label: 'المشروع', value: d.project, icon: 'fas fa-folder-open' },
                    { label: 'الفاحص', value: d.inspector, icon: 'fas fa-user-gear' },
                    { label: 'التاريخ', value: d.scheduled, icon: 'fas fa-calendar' },
                    { label: 'تاريخ الإكمال', value: d.completed, icon: 'fas fa-calendar-check' },
                    { label: 'الدرجة', value: (d.score!==''&&d.score!=null)? d.score+'%':'', icon: 'fas fa-star' },
                    { label: 'النتيجة العامة', value: d.overall, icon: 'fas fa-clipboard-check' },
                    { label: 'الجاهزية الفنية', value: d.readiness, icon: 'fas fa-gauge-high' },
                    { label: 'حالة المعدة', value: d.eqcond, icon: 'fas fa-tractor' },
                    { label: 'حالة المحرك', value: d.engcond, icon: 'fas fa-gears' },
                    { label: 'ملاحظات', value: d.notes, icon: 'fas fa-note-sticky', size: 'full' }
                ],
                sections: [
                    { title: 'بنود الفحص', icon: 'fas fa-list-check',
                      pills: [ { label: 'عدد البنود', value: lines.length } ],
                      table: { columns: ['البند', 'الحالة', 'القيمة المقاسة', 'الملاحظة', 'التوصية'], rows: lines },
                      empty: 'لا توجد بنود فحص' }
                ]
            });
        });
    });

    var IS_ASSESS = ($('#lineTable').data('kind') === 'assessment');

    // قراءة قيمة الحالة لصفٍّ: من المربّعات (الإدخال المخفي) أو من شارة العرض المقفلة
    function rowVal($row){
        var $inp = $row.find('.mnt-cond-input');
        if ($inp.length) return $inp.val() || '';
        var t = $row.find('.mnt-cond').text().trim();
        return t === '—' ? '' : t;
    }
    function recomputeSummary() {
        var good=0, note=0, crit=0, na=0, app=0;
        $('#lineTable .mnt-item-row').each(function(){
            var v = rowVal($(this));
            if (v === '') return;
            if (NA.indexOf(v) >= 0) { na++; return; }
            app++;
            if (GOOD.indexOf(v) >= 0) good++;
            else if (CRIT.indexOf(v) >= 0) crit++;
            else note++;
        });
        var score = app > 0 ? Math.round(100*good/app) : null;
        $('[data-stat=good]').text(good);
        $('[data-stat=note]').text(note);
        $('[data-stat=crit]').text(crit);
        $('[data-stat=na]').text(na);
        $('[data-stat=score]').text(score===null?'—':score+'%');
        $('#scoreReadout').val(score===null?'—':score+'%');
    }
    // إظهار حقول التفاصيل فقط عند الحاجة (غير «سليم») — أو دائمًا في استمارات التقييم
    function toggleDetail($row){
        var v = rowVal($row);
        var show = IS_ASSESS || (v !== '' && GOOD.indexOf(v) < 0 && NA.indexOf(v) < 0);
        $row.toggleClass('mnt-show-detail', !!show);
    }
    // ── تبويبات المنظومات: مؤشّر حالة لكل تبويب + تنقّل موجّه ──
    var TAB_COUNT = $('#mntTabs .mnt-tab').length;
    var curTab = 0;
    function updateTabBadges(){
        $('#mntTabs .mnt-tab').each(function(){
            var sec = $(this).data('sec');
            var prob=0, empty=0;
            $('.mnt-item-row[data-sec="'+sec+'"]').each(function(){
                var v = rowVal($(this));
                if (v===''){ empty++; return; }
                if (NA.indexOf(v)>=0) return;
                if (GOOD.indexOf(v)<0) prob++;
            });
            var $b = $(this).find('.mnt-tab-badge');
            $(this).removeClass('tab-ok tab-prob tab-todo');
            if (prob>0){ $b.text('⚠ '+prob); $(this).addClass('tab-prob'); }
            else if (empty>0){ $b.text(empty+'…'); $(this).addClass('tab-todo'); }
            else { $b.text('✓'); $(this).addClass('tab-ok'); }
        });
    }
    function activateTab(idx){
        if (TAB_COUNT===0) return;
        idx = Math.max(0, Math.min(idx, TAB_COUNT-1));
        curTab = idx;
        $('#mntTabs .mnt-tab').removeClass('is-active').filter('[data-sec="'+idx+'"]').addClass('is-active');
        $('.mnt-item-row').hide().filter('[data-sec="'+idx+'"]').show();
        $('#tabPrev').prop('disabled', idx===0);
        $('#tabNext').prop('disabled', idx===TAB_COUNT-1);
        var name = $('#mntTabs .mnt-tab[data-sec="'+idx+'"] .mnt-tab-label').text();
        $('#tabPos').text((idx+1)+' / '+TAB_COUNT+' — '+name);
        var el = $('#mntTabs .mnt-tab[data-sec="'+idx+'"]')[0];
        if (el && el.scrollIntoView) try { el.scrollIntoView({inline:'center', block:'nearest'}); } catch(e){}
    }
    // تلوين مباشر (inline + important) يتجاوز أي تعارض في تنسيقات النظام
    function paintBtn(el, active){
        if (active){
            el.style.setProperty('background', '#C9920E', 'important');
            el.style.setProperty('color', '#ffffff', 'important');
            el.style.setProperty('border-color', '#A6790B', 'important');
        } else {
            el.style.setProperty('background', '#fafafa', 'important');
            el.style.setProperty('color', '#6b7280', 'important');
            el.style.setProperty('border-color', '#d9d6cc', 'important');
        }
    }
    function paintSeg($seg){
        $seg.find('.mnt-seg-btn').each(function(){ paintBtn(this, $(this).hasClass('is-active')); });
    }
    // نقر مربّع الحالة: يضبط القيمة ويُفعّل المربّع
    $(document).on('click', '.mnt-seg-btn', function(){
        var $btn = $(this), $seg = $btn.closest('.mnt-cond-seg');
        $seg.find('.mnt-seg-btn').removeClass('is-active');
        $btn.addClass('is-active');
        $seg.find('.mnt-cond-input').val($btn.data('val'));
        paintSeg($seg);
        var $row = $btn.closest('.mnt-item-row');
        toggleDetail($row);
        recomputeSummary();
        updateTabBadges();
    });
    $('.mnt-cond-seg').each(function(){ paintSeg($(this)); });
    $('.mnt-item-row').each(function(){ toggleDetail($(this)); });
    updateTabBadges();
    activateTab(0);

    // تبويبات المنظومات: نقر التبويب + أزرار السابق/التالي
    $(document).on('click', '#mntTabs .mnt-tab', function(){ activateTab($(this).data('sec')); });
    $('#tabPrev').on('click', function(){ activateTab(curTab-1); $('html,body').animate({scrollTop:$('#mntTabs').offset().top-90},250); });
    $('#tabNext').on('click', function(){ activateTab(curTab+1); $('html,body').animate({scrollTop:$('#mntTabs').offset().top-90},250); });

    function postLine(payload){ return fetch('inspections.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: payload }).then(function(r){ return r.json(); }); }

    $('#xlAdd').on('click', function () {
        var comp = ($('#xl_component').val()||'').trim();
        if (!comp) { alert('اسم البند مطلوب'); return; }
        var iid = $('input[name=id]').val();
        var fd = new URLSearchParams({ ajax:'1', action:'add_line', inspection_id: iid,
            component: comp, condition_state: $('#xl_condition').val()||'',
            measured_value: $('#xl_measured').val()||'', note: $('#xl_note').val()||'', recommendation: $('#xl_rec').val()||'' });
        postLine(fd).then(function(res){
            if(!res.success){ alert(res.message || 'تعذّر إضافة البند'); return; }
            location.reload();
        }).catch(function(){ alert('خطأ في الاتصال'); });
    });

    $(document).on('click', '.mnt-del-line', function () {
        if (!confirm('حذف البند الإضافي؟')) return;
        var $btn = $(this), lineId = $btn.data('line');
        var iid = $('input[name=id]').val();
        var body = new URLSearchParams({ ajax:'1', action:'del_line', inspection_id: iid, line_id: lineId });
        postLine(body).then(function(res){
            if(!res.success){ alert(res.message || 'تعذّر الحذف'); return; }
            $btn.closest('tr').remove();
            $('#lineCount').text(res.count);
            recomputeSummary();
        }).catch(function(){ alert('خطأ في الاتصال'); });
    });

    $('#toggleInspForm').on('click', function(){ $('html, body').animate({ scrollTop: 0 }, 300); });

    $(document).on('click', '.mnt-add-toggle', function(){
        var $f = $('#' + $(this).data('target'));
        if ($f.is(':visible')) { $f.stop(true, true).slideUp(180); }
        else { $f.stop(true, true).slideDown(180); }
    });
    $(document).on('click', '.mnt-line-cancel', function(){ $('#' + $(this).data('target')).stop(true, true).slideUp(180); });

    var $createForm = $('#inspCreateForm');
    function closeCreateForm(){ $createForm.stop(true, true).slideUp(220, function(){ $createForm.removeClass('allforms-visible'); }); }
    $('#toggleCreateForm').on('click', function(){
        if ($createForm.hasClass('allforms-visible')) { closeCreateForm(); }
        else { $createForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); $('html, body').animate({ scrollTop: $createForm.offset().top - 90 }, 360); }
    });
    $('#cancelCreateForm').on('click', closeCreateForm);

    function applyHeaderType(){
        var h = $('#createType option:selected').data('header') || 'equipment';
        $('.hdr-equipment, .hdr-supplier, .hdr-external').hide();
        if (h === 'equipment') $('.hdr-equipment').show();
        else if (h === 'supplier') $('.hdr-supplier').show();
        else if (h === 'external') $('.hdr-external, .hdr-supplier').show();
    }
    if ($('#createType').length) { applyHeaderType(); $('#createType').on('change', applyHeaderType); }

    function inspLoadProjectEquipment($proj){
        var $form = $proj.closest('form');
        var $eq = $form.find('.mnt-eq');
        if (!$eq.length) return;
        var projectId = $proj.val();
        var current = $eq.val() || $eq.attr('data-selected') || '';
        if (!projectId) { $eq.html('<option value="">— اختر المشروع أولاً —</option>'); return; }
        $eq.html('<option value="">جارٍ التحميل…</option>');
        var url = '/ems/Maintenance/get_project_equipment.php?mode=all&project_id=' + encodeURIComponent(projectId)
                + (current ? '&include_id=' + encodeURIComponent(current) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var list = res.equipment || [];
                if (list.length === 0) { $eq.html('<option value="">لا توجد معدات في هذا المشروع</option>'); return; }
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
    $(document).on('change', '.mnt-proj', function(){ inspLoadProjectEquipment($(this)); });
    $('.mnt-proj').each(function(){ if ($(this).val()) inspLoadProjectEquipment($(this)); });
})();
</script>
<style>
    .mnt-inspections-main .mnt-lines-card { overflow:hidden; }
    .mnt-inspections-main .mnt-lines-card > .card-header.mnt-lines-head {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:linear-gradient(135deg,#1f4f7a,#2f6fa5); color:#fff; padding:13px 16px; border:none;
    }
    .mnt-inspections-main .mnt-lines-head h5 { display:flex; align-items:center; gap:8px; margin:0; color:#fff; font-weight:800; font-size:1rem; }
    .mnt-inspections-main .mnt-lines-head h5 i { color:#ffd98a; }
    .mnt-inspections-main .mnt-count { display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:24px; padding:0 8px; border-radius:999px; background:rgba(255,255,255,.22); color:#fff; font-size:.76rem; font-weight:800; }
    .mnt-inspections-main .mnt-add-toggle {
        display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;
        padding:7px 15px; border-radius:999px; font-weight:800; font-size:.82rem; color:#1a1208;
        background:linear-gradient(135deg,#E0AE2E,#f5d27e); box-shadow:0 2px 8px rgba(224,174,46,.4); transition:transform .15s, box-shadow .15s;
    }
    .mnt-inspections-main .mnt-add-toggle:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(224,174,46,.5); }
    .mnt-inspections-main .mnt-line-form { background:linear-gradient(180deg,#fffdf7,#fbf6ea); border:1px solid var(--bdr,#e7dcc4); border-radius:16px; padding:14px; margin-bottom:14px; box-shadow:inset 0 1px 0 #fff, 0 2px 8px rgba(26,18,8,.05); }
    .mnt-inspections-main .mnt-line-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end; }
    .mnt-inspections-main .mnt-line-grid .form-group { margin:0; }
    .mnt-inspections-main .mnt-line-actions { display:flex; align-items:center; gap:10px; margin-top:14px; flex-wrap:wrap; padding-top:12px; border-top:1px dashed var(--bdr,#e7dcc4); }
    .mnt-inspections-main .mnt-line-cancel { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

    .mnt-inspections-main .mnt-summary { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin:14px 0; }
    .mnt-inspections-main .mnt-stat { display:flex; flex-direction:column; gap:4px; padding:12px 14px; border-radius:14px; background:#f5f6f8; }
    .mnt-inspections-main .mnt-stat-label { font-size:.78rem; color:#6b7280; font-weight:700; }
    .mnt-inspections-main .mnt-stat-num { font-size:1.5rem; font-weight:800; }
    .mnt-inspections-main .mnt-stat--ok .mnt-stat-num { color:#15803d; }
    .mnt-inspections-main .mnt-stat--note .mnt-stat-num { color:#b45309; }
    .mnt-inspections-main .mnt-stat--crit .mnt-stat-num { color:#b91c1c; }
    .mnt-inspections-main .mnt-stat--na .mnt-stat-num { color:#6b7280; }
    .mnt-inspections-main .mnt-stat--score { background:#eef4fb; }
    .mnt-inspections-main .mnt-stat--score .mnt-stat-num { color:#1f4f7a; }

    .mnt-inspections-main .mnt-line-table { width:100%; border-collapse:separate; border-spacing:0; }
    .mnt-inspections-main .mnt-line-table thead th { background:#f3ede0; color:#6b5d3e; font-weight:800; font-size:.8rem; padding:9px 10px; border-bottom:2px solid #e7dcc4; }
    .mnt-inspections-main .mnt-line-table tbody td { font-size:.84rem; padding:8px 10px; border-bottom:1px solid #f0e9da; vertical-align:middle; }
    .mnt-inspections-main .mnt-line-table tbody tr:hover { background:rgba(224,174,46,.05); }
    .mnt-inspections-main .mnt-sec-row { cursor:pointer; }
    .mnt-inspections-main .mnt-sec-row td { background:#eef4fb !important; color:#1f4f7a; font-weight:800; font-size:.84rem; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .mnt-inspections-main .mnt-sec-row:hover td { background:#e3eefa !important; }
    .mnt-inspections-main .mnt-sec-toggle { display:inline-flex; align-items:center; gap:8px; }
    .mnt-inspections-main .mnt-sec-chev { transition:transform .15s; font-size:.72rem; color:#2f6fa5; }
    .mnt-inspections-main .mnt-sec-meta { font-size:.74rem; font-weight:800; color:#15803d; white-space:nowrap; }
    .mnt-inspections-main .mnt-sec-meta.has-prob { color:#b45309; }
    .mnt-inspections-main .mnt-item { font-weight:600; min-width:200px; }
    .mnt-inspections-main .mnt-hint { font-size:.72rem; color:#9a8f78; margin-top:4px; font-weight:600; }
    .mnt-inspections-main .mnt-detail { display:none; flex-direction:column; gap:6px; }
    .mnt-inspections-main .mnt-item-row.mnt-show-detail .mnt-detail { display:flex; }
    .mnt-inspections-main .mnt-act { text-align:center; width:44px; }

    /* تبويبات المنظومات */
    .mnt-inspections-main .mnt-tabs { display:flex; gap:6px; overflow-x:auto; padding:4px 2px 10px; margin-bottom:6px; -webkit-overflow-scrolling:touch; }
    .mnt-inspections-main .mnt-tab {
        flex:0 0 auto; cursor:pointer; border:1px solid #e2e0d7; background:#fafafa; color:#5f5e5a;
        padding:8px 14px; border-radius:999px; font-size:.82rem; font-weight:800; white-space:nowrap;
        display:inline-flex; align-items:center; gap:7px; transition:background .12s, border-color .12s, color .12s;
    }
    .mnt-inspections-main .mnt-tab:hover { border-color:#c9b98f; }
    .mnt-inspections-main .mnt-tab.is-active { background:#1f4f7a; color:#fff; border-color:#1f4f7a; }
    .mnt-inspections-main .mnt-tab-badge { font-size:.7rem; font-weight:800; padding:1px 7px; border-radius:999px; background:rgba(0,0,0,.08); color:inherit; }
    .mnt-inspections-main .mnt-tab.tab-ok   .mnt-tab-badge { background:rgba(22,163,74,.18);  color:#15803d; }
    .mnt-inspections-main .mnt-tab.tab-prob .mnt-tab-badge { background:rgba(220,38,38,.18);  color:#b91c1c; }
    .mnt-inspections-main .mnt-tab.tab-todo .mnt-tab-badge { background:rgba(217,119,6,.18);  color:#b45309; }
    .mnt-inspections-main .mnt-tab.is-active .mnt-tab-badge { background:#fff; color:#C9920E !important; }
    .mnt-inspections-main .mnt-tabnav { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:12px; }
    .mnt-inspections-main .mnt-tabnav-pos { font-size:.8rem; font-weight:700; color:#6b7280; }
    .mnt-inspections-main .mnt-nav-btn {
        cursor:pointer; border:1px solid #d9d6cc; background:#fff; color:#1f4f7a;
        padding:8px 18px; border-radius:10px; font-weight:800; font-size:.84rem;
        display:inline-flex; align-items:center; gap:7px;
    }
    .mnt-inspections-main .mnt-nav-btn.mnt-nav-next { background:#1f4f7a; color:#fff; border-color:#1f4f7a; }
    .mnt-inspections-main .mnt-nav-btn:disabled { opacity:.4; cursor:default; }
    .mnt-inspections-main .mnt-line-table input[type=text] { width:100%; min-width:90px; padding:5px 8px; border:1px solid #e2e0d7; border-radius:8px; font-size:.82rem; }
    /* مربّعات اختيار الحالة (بدل القائمة المنسدلة) */
    .mnt-inspections-main .mnt-cond-seg { display:flex; flex-wrap:wrap; gap:5px; min-width:150px; }
    .mnt-inspections-main .mnt-line-table .mnt-seg-btn {
        cursor:pointer; border:1px solid #d9d6cc !important; background:#fafafa !important; color:#6b7280 !important;
        padding:5px 11px !important; border-radius:8px !important; font-size:.78rem !important; font-weight:700 !important; line-height:1.2 !important;
        box-shadow:none !important; transition:background .12s, border-color .12s, color .12s;
    }
    .mnt-inspections-main .mnt-line-table .mnt-seg-btn:hover { border-color:#bdb9ad !important; }
    .mnt-inspections-main .mnt-line-table .mnt-seg-btn.is-active { background:#C9920E !important; color:#fff !important; border-color:#A6790B !important; }
    .mnt-inspections-main .mnt-lock { color:#c7bfa8; }

    .mnt-cond { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:999px; font-size:.78rem; font-weight:800; }
    .mnt-cond--ok   { background:rgba(22,163,74,.14);  color:#15803d; }
    .mnt-cond--note { background:rgba(217,119,6,.16);  color:#b45309; }
    .mnt-cond--crit { background:rgba(220,38,38,.14);  color:#b91c1c; }
    .mnt-cond--na   { background:#f1efe8; color:#6b7280; }

    .mnt-cond-legend { display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; font-size:.76rem; color:#6b7280; }
    .mnt-cond-legend .mnt-dot { display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; margin-left:4px; }
    .mnt-dot--ok{background:#16a34a}.mnt-dot--note{background:#d97706}.mnt-dot--crit{background:#dc2626}.mnt-dot--na{background:#b4b2a9}

    .mnt-inspections-main .mnt-empty-line { display:flex; flex-direction:column; align-items:center; gap:8px; color:#b0a489; padding:24px 10px; }
    .mnt-inspections-main .mnt-empty-line i { font-size:1.9rem; opacity:.5; }
    @media(max-width:780px){ .mnt-inspections-main .mnt-summary{grid-template-columns:repeat(2,1fr)} }
</style>
</body>
</html>
