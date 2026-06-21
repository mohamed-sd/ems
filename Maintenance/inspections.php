<?php
/**
 * Maintenance/inspections.php — التفتيش الفني (يشمل الزيارة الميدانية كنوع مدموج).
 * عند الإكمال (مكتمل) تُكتب نتيجة الحالة الفنية على كرت المعدة
 * (equipment_condition/engine_condition) وتُخزَّن في سجل التفتيش (القرار DEC-12).
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

$types = array('دوري', 'زيارة ميدانية', 'استلام', 'بعد حادث');
$states = array('جديد', 'مجدول', 'قيد التنفيذ', 'مكتمل', 'مغلق');
$conditions = array('ممتازة', 'جيدة', 'متوسطة', 'ضعيفة', 'حرجة');
$line_conditions = array('سليم', 'ملاحظة', 'حرج');
$readiness = array('جاهزة', 'جاهزة بتحفّظ', 'غير جاهزة');

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

// ══ AJAX: بنود الفحص (إضافة/حذف) دون إعادة تحميل الصفحة ══
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax && in_array($_POST['action'] ?? '', array('add_line', 'del_line'), true)) {
    if (!$can_edit) { mnt_ins_json(array('success' => false, 'message' => 'لا توجد صلاحية للتعديل')); }
    $iid = intval($_POST['inspection_id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if (!$ins) { mnt_ins_json(array('success' => false, 'message' => 'التفتيش غير موجود')); }
    if ($ins['state'] === 'مكتمل' || $ins['state'] === 'مغلق') { mnt_ins_json(array('success' => false, 'message' => 'لا يمكن تعديل بنود تفتيش مكتمل/مغلق')); }

    if ($_POST['action'] === 'add_line') {
        $component = trim($_POST['component'] ?? '');
        if ($component === '') { mnt_ins_json(array('success' => false, 'message' => 'اسم المكوّن مطلوب')); }
        $cond = in_array($_POST['condition_state'] ?? '', $line_conditions, true) ? $_POST['condition_state'] : 'سليم';
        $rec = trim($_POST['recommendation'] ?? '');
        $lineId = 0;
        if ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_inspection_line (company_id, inspection_id, component, condition_state, recommendation) VALUES (?,?,?,?,?)")) {
            mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $iid, $component, $cond, $rec);
            mysqli_stmt_execute($stmt); $lineId = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
        }
        mnt_ins_json(array('success' => true, 'line' => array('id' => $lineId, 'component' => $component, 'condition_state' => $cond, 'recommendation' => $rec), 'count' => mnt_ins_line_count($conn, $iid, $company_id)));
    }

    if ($_POST['action'] === 'del_line') {
        $lid = intval($_POST['line_id'] ?? 0);
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_inspection_line WHERE id=? AND inspection_id=? AND company_id=?")) {
            mysqli_stmt_bind_param($stmt, 'iii', $lid, $iid, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mnt_ins_json(array('success' => true, 'count' => mnt_ins_line_count($conn, $iid, $company_id)));
    }
}

// ── إنشاء تفتيش جديد (يُحفظ فقط عند إرسال الفورم بالبيانات — لا سجلّ فارغ عند فتح الفورم) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_inspection') {
    if (!$can_add) { header("Location: inspections.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: inspections.php?msg=لا+يمكن+الإنشاء+بلا+شركة+❌"); exit(); }

    $inspection_type = in_array($_POST['inspection_type'] ?? '', $types, true) ? $_POST['inspection_type'] : 'دوري';
    $equipment_id   = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id     = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $inspector_id   = !empty($_POST['inspector_id']) ? intval($_POST['inspector_id']) : null;
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;

    $code = mnt_next_code($conn, 'mnt_inspection', 'INS', $company_id);
    $new_id = 0;
    $sql = "INSERT INTO mnt_inspection (company_id, code, inspection_type, equipment_id, project_id, inspector_id, scheduled_date, state, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'جديد', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // company_id,i code,s type,s equipment,i project,i inspector,i scheduled,s created_by,i
        mysqli_stmt_bind_param($stmt, 'issiiisi', $company_id, $code, $inspection_type, $equipment_id, $project_id, $inspector_id, $scheduled_date, $current_user_id);
        mysqli_stmt_execute($stmt); $new_id = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    }
    header("Location: inspections.php?id=" . intval($new_id) . "&msg=تم+إنشاء+التفتيش+✅"); exit();
}

// ── حفظ رأس التفتيش + منطق الإكمال ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_inspection') {
    if (!$can_edit) { header("Location: inspections.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    $iid = intval($_POST['id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if (!$ins) { header("Location: inspections.php?msg=التفتيش+غير+موجود+❌"); exit(); }

    $inspection_type = in_array($_POST['inspection_type'] ?? '', $types, true) ? $_POST['inspection_type'] : 'دوري';
    $equipment_id  = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id    = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $inspector_id  = !empty($_POST['inspector_id']) ? intval($_POST['inspector_id']) : null;
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
    $score         = ($_POST['score'] ?? '') !== '' ? intval($_POST['score']) : null;
    $overall_result = trim($_POST['overall_result'] ?? '');
    $tech_readiness = trim($_POST['tech_readiness_state'] ?? '');
    $equipment_condition = trim($_POST['equipment_condition'] ?? '');
    $engine_condition    = trim($_POST['engine_condition'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $requested_state = in_array($_POST['state'] ?? '', $states, true) ? $_POST['state'] : $ins['state'];

    $completing_now = ($requested_state === 'مكتمل' && $ins['state'] !== 'مكتمل');

    $sql = "UPDATE mnt_inspection SET
                inspection_type=?, equipment_id=?, project_id=?, inspector_id=?, scheduled_date=?,
                score=?, overall_result=?, tech_readiness_state=?, equipment_condition=?, engine_condition=?,
                notes=?, state=?"
            . ($completing_now ? ", completed_at=NOW()" : "")
            . " WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // s i i i s | i s s s s | s s | i i
        $tp = 'siiis' . 'issss' . 'ss' . 'ii';
        mysqli_stmt_bind_param($stmt, $tp,
            $inspection_type, $equipment_id, $project_id, $inspector_id, $scheduled_date,
            $score, $overall_result, $tech_readiness, $equipment_condition, $engine_condition,
            $notes, $requested_state, $iid, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }

    // عند الإكمال: اكتب الحالة الفنية على كرت المعدة
    if ($completing_now && $equipment_id) {
        mnt_apply_inspection_to_equipment($conn, $equipment_id, $company_id, $equipment_condition, $engine_condition);
    }

    header("Location: inspections.php?id=" . intval($iid) . "&msg=تم+حفظ+التفتيش+✅"); exit();
}

// ── أسطر بنود التفتيش ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_line') {
    if (!$can_edit) { header("Location: inspections.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $iid = intval($_POST['inspection_id'] ?? 0);
    $ins = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
    if ($ins && ($ins['state'] === 'مكتمل' || $ins['state'] === 'مغلق')) {
        header("Location: inspections.php?id=" . intval($iid) . "&msg=" . urlencode('لا يمكن تعديل بنود تفتيش مكتمل/مغلق ❌')); exit();
    }
    if ($ins) {
        $component = trim($_POST['component'] ?? '');
        $cond = in_array($_POST['condition_state'] ?? '', $line_conditions, true) ? $_POST['condition_state'] : 'سليم';
        $rec = trim($_POST['recommendation'] ?? '');
        if ($component !== '' && ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_inspection_line (company_id, inspection_id, component, condition_state, recommendation) VALUES (?,?,?,?,?)"))) {
            mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $iid, $component, $cond, $rec);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
    }
    header("Location: inspections.php?id=" . intval($iid) . "&msg=تمت+إضافة+البند+✅"); exit();
}
if (isset($_GET['del_line'], $_GET['inspection_id'])) {
    if ($can_edit) {
        $lid = intval($_GET['del_line']); $iid = intval($_GET['inspection_id']);
        // لا يجوز حذف بنود تفتيش مكتمل/مغلق (نفس قفل مسار AJAX)
        $ins_lock = mnt_fetch_inspection($conn, $iid, $company_id, $is_super_admin);
        if ($ins_lock && ($ins_lock['state'] === 'مكتمل' || $ins_lock['state'] === 'مغلق')) {
            header("Location: inspections.php?id=" . $iid . "&msg=" . urlencode('لا يمكن حذف بنود تفتيش مكتمل/مغلق ❌')); exit();
        }
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_inspection_line WHERE id=? AND inspection_id=? AND company_id=?")) {
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

$equipments = array(); $projects = array(); $users_list = array();
if ($ins || $edit_id === 0) {
    $cscope = $is_super_admin ? "1=1" : "company_id = " . intval($company_id);
    if ($r = mysqli_query($conn, "SELECT id, name, code FROM equipments WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $equipments[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM project WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $projects[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, name FROM users WHERE $cscope AND is_deleted=0 ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $users_list[] = $x; }
}

$page_title = 'إيكوبيشن | التفتيش الفني';
include '../inheader.php';
include '../insidebar.php';
function mnt_opt($value, $label, $selected) {
    return "<option value='" . htmlspecialchars((string) $value, ENT_QUOTES) . "'" . ($selected ? " selected" : "") . ">" . htmlspecialchars((string) $label) . "</option>";
}
function mnt_cond_class($c) {
    if ($c === 'حرج') return 'mnt-cond mnt-cond--crit';
    if ($c === 'ملاحظة') return 'mnt-cond mnt-cond--note';
    return 'mnt-cond mnt-cond--ok';
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
    $lines = array();
    if ($s = mysqli_prepare($conn, "SELECT id, component, condition_state, recommendation FROM mnt_inspection_line WHERE inspection_id=? AND company_id=? ORDER BY id")) {
        mysqli_stmt_bind_param($s, 'ii', $edit_id, $company_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($rr && $x = mysqli_fetch_assoc($rr)) $lines[] = $x; mysqli_stmt_close($s);
    }
    $st = (string) $ins['state'];
    $locked = ($st === 'مكتمل' || $st === 'مغلق');
?>
    <?php
    $header_title_html = 'تفتيش: <strong>' . htmlspecialchars((string) $ins['code']) . '</strong> <span class="action-btn">' . htmlspecialchars($st) . '</span>';
    $header_icon = 'fa fa-clipboard-check';
    $header_actions = array();
    $header_actions[] = array('id' => 'toggleInspForm', 'class' => 'add-btn', 'icon' => 'fas fa-pen-to-square', 'label' => 'بيانات التفتيش');
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
                    <select name="inspection_type"><?php foreach ($types as $t) echo mnt_opt($t, $t, $ins['inspection_type'] === $t); ?></select>
                </div>
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
                <div class="form-group"><label>الفاحص</label>
                    <select name="inspector_id"><option value="">-- اختر --</option>
                        <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], intval($ins['inspector_id']) === intval($u['id'])); ?>
                    </select>
                </div>
                <div class="form-group"><label>تاريخ الجدولة</label>
                    <input type="date" name="scheduled_date" value="<?php echo htmlspecialchars((string) $ins['scheduled_date']); ?>">
                </div>
                <div class="form-group"><label>الدرجة (0-100)</label>
                    <input type="number" name="score" min="0" max="100" value="<?php echo htmlspecialchars((string) $ins['score']); ?>">
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
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ التفتيش</button>
                <button type="button" class="btn-cancel" id="collapseInspForm"><i class="fas fa-chevron-up"></i> طيّ النموذج</button>
            </div>
        </div></div>
    </form>

    <!-- بنود الفحص -->
    <div class="card mnt-lines-card">
        <div class="card-header mnt-lines-head">
            <h5><i class="fas fa-list-check"></i> بنود الفحص <span class="mnt-count" id="lineCount"><?php echo count($lines); ?></span></h5>
            <?php if (!$locked && $can_edit): ?><button type="button" class="mnt-add-toggle" data-target="lineForm"><i class="fas fa-plus"></i> إضافة بند</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$locked && $can_edit): ?>
            <form class="mnt-line-form" id="lineForm" onsubmit="return false;" style="display:none;">
                <input type="hidden" name="action" value="add_line">
                <input type="hidden" name="inspection_id" value="<?php echo intval($ins['id']); ?>">
                <div class="mnt-line-grid">
                    <div class="form-group"><label>المكوّن</label><input type="text" name="component" placeholder="مثال: نظام الفرامل"></div>
                    <div class="form-group"><label>الحالة</label><select name="condition_state"><?php foreach ($line_conditions as $lc) echo mnt_opt($lc, $lc, false); ?></select></div>
                    <div class="form-group"><label>التوصية</label><input type="text" name="recommendation" placeholder="مثال: استبدال خلال أسبوع"></div>
                </div>
                <div class="mnt-line-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إضافة البند</button>
                    <button type="button" class="btn-cancel mnt-line-cancel" data-target="lineForm"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-container"><table class="alltables no-datatable mnt-line-table" id="lineTable" style="width:100%">
                <thead><tr><th>المكوّن</th><th>الحالة</th><th>التوصية</th><?php if (!$locked && $can_edit) echo '<th></th>'; ?></tr></thead>
                <tbody>
                    <?php foreach ($lines as $l): $c = (string) $l['condition_state']; ?>
                    <tr data-line="<?php echo intval($l['id']); ?>">
                        <td><?php echo htmlspecialchars((string) $l['component']); ?></td>
                        <td><span class="<?php echo mnt_cond_class($c); ?>"><?php echo htmlspecialchars($c); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($l['recommendation'] ?? '')); ?></td>
                        <?php if (!$locked && $can_edit): ?><td><button type="button" class="action-btn delete mnt-del-line" data-line="<?php echo intval($l['id']); ?>" title="حذف"><i class="fas fa-trash-alt"></i></button></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="mnt-empty-line" id="lineEmpty" style="<?php echo empty($lines) ? '' : 'display:none'; ?>"><i class="fas fa-list-check"></i><span>لا توجد بنود فحص بعد</span></div>
        </div>
    </div>

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
    <!-- فورم إنشاء تفتيش (نمط العملاء/المشاريع: يُفتح بزر «تفتيش جديد»، ولا يُحفظ شيء إلا عند الإرسال) -->
    <form method="post" action="" class="allforms" id="inspCreateForm">
        <input type="hidden" name="action" value="new_inspection">
        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> إنشاء تفتيش جديد</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>نوع التفتيش</label>
                    <select name="inspection_type"><?php foreach ($types as $t) echo mnt_opt($t, $t, $t === 'دوري'); ?></select>
                </div>
                <div class="form-group"><label>المشروع</label>
                    <select name="project_id" class="mnt-proj"><option value="">— اختر المشروع —</option>
                        <?php foreach ($projects as $p) echo mnt_opt($p['id'], $p['name'], false); ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدة</label>
                    <select name="equipment_id" class="mnt-eq" data-selected=""><option value="">— اختر المشروع أولاً —</option></select>
                </div>
                <div class="form-group"><label>الفاحص</label>
                    <select name="inspector_id"><option value="">-- اختر --</option>
                        <?php foreach ($users_list as $u) echo mnt_opt($u['id'], $u['name'], false); ?>
                    </select>
                </div>
                <div class="form-group"><label>تاريخ الجدولة</label>
                    <input type="date" name="scheduled_date">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إنشاء وبدء الفحص</button>
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
                <thead><tr><th>الإجراءات</th><th>المرجع</th><th>النوع</th><th>المعدة</th><th>الفاحص</th><th>التاريخ</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT i.id, i.code, i.inspection_type, i.scheduled_date, i.completed_at,
                                   i.score, i.overall_result, i.tech_readiness_state,
                                   i.equipment_condition, i.engine_condition, i.notes, i.state,
                                   e.name AS equipment_name, p.name AS project_name, u.name AS inspector_name
                              FROM mnt_inspection i
                              LEFT JOIN equipments e ON e.id = i.equipment_id
                              LEFT JOIN project p    ON p.id = i.project_id
                              LEFT JOIN users u ON u.id = i.inspector_id
                             WHERE $company_scope_sql AND COALESCE(i.is_deleted,0)=0
                             ORDER BY i.id DESC";
                    $insp_ids = array();
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $insp_ids[] = intval($row['id']);
                        $st = (string) $row['state'];
                        $da =
                            "data-id='"        . intval($row['id']) . "' " .
                            "data-code='"      . htmlspecialchars((string) $row['code'], ENT_QUOTES) . "' " .
                            "data-type='"      . htmlspecialchars((string) $row['inspection_type'], ENT_QUOTES) . "' " .
                            "data-equipment='" . htmlspecialchars((string) ($row['equipment_name'] ?? ''), ENT_QUOTES) . "' " .
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
                        echo "<td>" . htmlspecialchars((string) ($row['equipment_name'] ?? '-')) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['inspector_name'] ?? '-')) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['scheduled_date'] ?? '')) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string) $row['state']) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php
    // ════════ خريطة بنود الفحص لكل تفتيش (لعرضها داخل نافذة التفاصيل) ════════
    $mnt_insp_lines_map = array();
    if (!empty($insp_ids)) {
        $ids_csv = implode(',', array_map('intval', $insp_ids));
        $cid     = intval($company_id);
        $lq = "SELECT inspection_id, component, condition_state, recommendation
                 FROM mnt_inspection_line
                WHERE inspection_id IN ($ids_csv) AND company_id = $cid
                ORDER BY id";
        if ($lr = mysqli_query($conn, $lq)) {
            while ($x = mysqli_fetch_assoc($lr)) {
                $iid = intval($x['inspection_id']);
                $mnt_insp_lines_map[$iid][] = array(
                    (string) $x['component'],
                    (string) ($x['condition_state'] ?? ''),
                    (string) ($x['recommendation'] ?? '')
                );
            }
        }
    }
    echo '<script>window.MNT_INSP_LINES = '
        . json_encode($mnt_insp_lines_map, JSON_UNESCAPED_UNICODE)
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
                table.column(6).search(v, true, false).draw();
            });
        }

        // ════════ نافذة العرض الموحّدة ════════
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
                    { label: 'المعدة', value: d.equipment, icon: 'fas fa-tractor' },
                    { label: 'المشروع', value: d.project, icon: 'fas fa-folder-open' },
                    { label: 'الفاحص', value: d.inspector, icon: 'fas fa-user-gear' },
                    { label: 'التاريخ المجدول', value: d.scheduled, icon: 'fas fa-calendar' },
                    { label: 'تاريخ الإكمال', value: d.completed, icon: 'fas fa-calendar-check' },
                    { label: 'التقييم', value: d.score, icon: 'fas fa-star' },
                    { label: 'النتيجة العامة', value: d.overall, icon: 'fas fa-clipboard-check' },
                    { label: 'الجاهزية الفنية', value: d.readiness, icon: 'fas fa-gauge-high' },
                    { label: 'حالة المعدة', value: d.eqcond, icon: 'fas fa-tractor' },
                    { label: 'حالة المحرك', value: d.engcond, icon: 'fas fa-gears' },
                    { label: 'ملاحظات', value: d.notes, icon: 'fas fa-note-sticky', size: 'full' }
                ],
                sections: [
                    { title: 'بنود الفحص', icon: 'fas fa-list-check',
                      pills: [ { label: 'عدد البنود', value: lines.length } ],
                      table: { columns: ['المكوّن', 'الحالة', 'التوصية'], rows: lines },
                      empty: 'لا توجد بنود فحص' }
                ]
            });
        });
    });

    // ════════ صفحة التحرير: بنود الفحص عبر AJAX + فتح/إغلاق الفورم ════════
    function esc(v){ return $('<div>').text(v == null ? '' : v).html(); }
    function condClass(c){ return c === 'حرج' ? 'mnt-cond mnt-cond--crit' : (c === 'ملاحظة' ? 'mnt-cond mnt-cond--note' : 'mnt-cond mnt-cond--ok'); }
    function postLine(payload){ return fetch('inspections.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: payload }).then(function(r){ return r.json(); }); }

    var $lineForm = $('#lineForm');
    if ($lineForm.length) {
        $lineForm.on('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this); fd.append('ajax','1');
            postLine(new URLSearchParams(fd)).then(function(res){
                if(!res.success){ alert(res.message || 'تعذّر إضافة البند'); return; }
                var l = res.line;
                var row = '<tr data-line="'+l.id+'">'
                    + '<td>'+esc(l.component)+'</td>'
                    + '<td><span class="'+condClass(l.condition_state)+'">'+esc(l.condition_state)+'</span></td>'
                    + '<td>'+esc(l.recommendation||'')+'</td>'
                    + '<td><button type="button" class="action-btn delete mnt-del-line" data-line="'+l.id+'" title="حذف"><i class="fas fa-trash-alt"></i></button></td></tr>';
                $('#lineTable tbody').append(row);
                $('#lineEmpty').hide();
                $('#lineCount').text(res.count);
                $lineForm[0].reset();
            }).catch(function(){ alert('خطأ في الاتصال'); });
        });
    }

    $(document).on('click', '.mnt-del-line', function () {
        if (!confirm('حذف البند؟')) return;
        var $btn = $(this), lineId = $btn.data('line');
        var iid = ($('#lineForm input[name=inspection_id]').val() || $('input[name=id]').val());
        var body = new URLSearchParams({ ajax:'1', action:'del_line', inspection_id: iid, line_id: lineId });
        postLine(body).then(function(res){
            if(!res.success){ alert(res.message || 'تعذّر الحذف'); return; }
            var $tbody = $btn.closest('tbody'); $btn.closest('tr').remove();
            $('#lineCount').text(res.count);
            if ($tbody.find('tr').length === 0) { $('#lineEmpty').show(); }
        }).catch(function(){ alert('خطأ في الاتصال'); });
    });

    // فتح/إغلاق فورم بيانات التفتيش (نمط العملاء/المشاريع)
    var $inspForm = $('#inspForm');
    function openInspForm(){ $inspForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); }
    function closeInspForm(){ $inspForm.stop(true, true).slideUp(220, function(){ $inspForm.removeClass('allforms-visible'); }); }
    $('#toggleInspForm').on('click', function(){
        if ($inspForm.hasClass('allforms-visible')) { closeInspForm(); }
        else { openInspForm(); $('html, body').animate({ scrollTop: $inspForm.offset().top - 90 }, 360); }
    });
    $('#collapseInspForm').on('click', closeInspForm);

    // فتح/إغلاق فورم إضافة بند
    $(document).on('click', '.mnt-add-toggle', function(){
        var $f = $('#' + $(this).data('target'));
        if ($f.is(':visible')) { $f.stop(true, true).slideUp(180); }
        else { $f.stop(true, true).slideDown(180); $f.find('select, input').not('[type=hidden]').first().trigger('focus'); }
    });
    $(document).on('click', '.mnt-line-cancel', function(){
        $('#' + $(this).data('target')).stop(true, true).slideUp(180);
    });

    // ════════ قائمة التفتيش: فتح/إغلاق فورم الإنشاء (بلا حفظ سجل فارغ) ════════
    var $createForm = $('#inspCreateForm');
    function closeCreateForm(){ $createForm.stop(true, true).slideUp(220, function(){ $createForm.removeClass('allforms-visible'); }); }
    $('#toggleCreateForm').on('click', function(){
        if ($createForm.hasClass('allforms-visible')) { closeCreateForm(); }
        else { $createForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); $('html, body').animate({ scrollTop: $createForm.offset().top - 90 }, 360); }
    });
    $('#cancelCreateForm').on('click', closeCreateForm);

    // ════════ تسلسل المشروع ← المعدة: عند اختيار المشروع تُحمَّل معداته فقط ════════
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
    // تحميل أولي لفورم التحرير: لو كان المشروع محدّداً مسبقاً، حمّل معداته (مع إبقاء المعدة المختارة).
    $('.mnt-proj').each(function(){ if ($(this).val()) inspLoadProjectEquipment($(this)); });
})();
</script>
<style>
    /* ══ لوحة بنود الفحص — تصميم قوي متّسق مع هوية الفورمات ══ */
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

    /* جدول البنود */
    .mnt-inspections-main .mnt-line-table { width:100%; border-collapse:separate; border-spacing:0; }
    .mnt-inspections-main .mnt-line-table thead th { background:#f3ede0; color:#6b5d3e; font-weight:800; font-size:.82rem; padding:10px 12px; border-bottom:2px solid #e7dcc4; }
    .mnt-inspections-main .mnt-line-table tbody td { font-size:.88rem; padding:10px 12px; border-bottom:1px solid #f0e9da; }
    .mnt-inspections-main .mnt-line-table tbody tr:hover { background:rgba(224,174,46,.07); }
    .mnt-inspections-main .mnt-line-table td:last-child, .mnt-inspections-main .mnt-line-table th:last-child { text-align:center; }

    /* شارات حالة البند */
    .mnt-cond { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:999px; font-size:.78rem; font-weight:800; }
    .mnt-cond--ok   { background:rgba(22,163,74,.14);  color:#15803d; }
    .mnt-cond--note { background:rgba(217,119,6,.16);  color:#b45309; }
    .mnt-cond--crit { background:rgba(220,38,38,.14);  color:#b91c1c; }

    .mnt-inspections-main .mnt-empty-line { display:flex; flex-direction:column; align-items:center; gap:8px; color:#b0a489; padding:24px 10px; }
    .mnt-inspections-main .mnt-empty-line i { font-size:1.9rem; opacity:.5; }
    .mnt-inspections-main .mnt-empty-line span { font-size:.9rem; font-weight:600; }
</style>
</body>
</html>
