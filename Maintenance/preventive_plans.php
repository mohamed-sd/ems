<?php
/**
 * Maintenance/preventive_plans.php — الخطة الوقائية.
 * - الاستحقاق بالساعات يُحتسب من ساعات التشغيل الفعلية في التايم‌شيت (القرار DEC-08).
 * - قائمة «مستحقة الآن» + زر «توليد أمر» يدوي ينشئ mnt_order بنوع وقائي (DEC-09، بلا cron).
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

$page_permissions = check_page_permissions($conn, 'Maintenance/preventive_plans.php');
$can_view   = $is_super_admin ? true : $page_permissions['can_view'];
$can_add    = $is_super_admin ? true : $page_permissions['can_add'];
$can_edit   = $is_super_admin ? true : $page_permissions['can_edit'];
$can_delete = $is_super_admin ? true : $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الخطة+الوقائية+❌"); exit(); }

$company_scope_sql = $is_super_admin ? "1=1" : "pl.company_id = " . intval($company_id);

$trigger_bases = array('ساعات', 'زمن');
$states = array('نشطة', 'متوقفة');

function mnt_fetch_plan($conn, $id, $company_id, $is_super_admin) {
    $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $res = mysqli_query($conn, "SELECT * FROM mnt_plan WHERE id = " . intval($id) . " AND COALESCE(is_deleted,0)=0" . $scope . " LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function mnt_pl_task_count($conn, $pid, $company_id) {
    $c = 0;
    if ($cs = mysqli_prepare($conn, "SELECT COUNT(*) c FROM mnt_plan_task WHERE plan_id=? AND company_id=?")) {
        mysqli_stmt_bind_param($cs, 'ii', $pid, $company_id); mysqli_stmt_execute($cs);
        $cr = mysqli_stmt_get_result($cs); if ($cr && ($x = mysqli_fetch_assoc($cr))) { $c = intval($x['c']); }
        mysqli_stmt_close($cs);
    }
    return $c;
}
function mnt_pl_json($data) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ══ AJAX: مهام الخطة (إضافة/حذف) دون إعادة تحميل الصفحة ══
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax && in_array($_POST['action'] ?? '', array('add_task', 'del_task'), true)) {
    if (!$can_edit) { mnt_pl_json(array('success' => false, 'message' => 'لا توجد صلاحية للتعديل')); }
    $pid = intval($_POST['plan_id'] ?? 0);
    $plan = mnt_fetch_plan($conn, $pid, $company_id, $is_super_admin);
    if (!$plan) { mnt_pl_json(array('success' => false, 'message' => 'الخطة غير موجودة')); }

    if ($_POST['action'] === 'add_task') {
        $name = trim($_POST['task_name'] ?? '');
        if ($name === '') { mnt_pl_json(array('success' => false, 'message' => 'اسم المهمة مطلوب')); }
        $task_type = !empty($_POST['task_type']) ? intval($_POST['task_type']) : null;
        $component = trim($_POST['component'] ?? '');
        $est_hours = floatval($_POST['est_hours'] ?? 0);
        $taskId = 0;
        if ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_plan_task (company_id, plan_id, name, task_type, component, est_hours) VALUES (?,?,?,?,?,?)")) {
            mysqli_stmt_bind_param($stmt, 'iisisd', $company_id, $pid, $name, $task_type, $component, $est_hours);
            mysqli_stmt_execute($stmt); $taskId = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
        }
        $type_name = '';
        if ($task_type && ($ts = mysqli_prepare($conn, "SELECT name FROM mnt_lookup WHERE id=? AND company_id=?"))) {
            mysqli_stmt_bind_param($ts, 'ii', $task_type, $company_id); mysqli_stmt_execute($ts);
            $tr = mysqli_stmt_get_result($ts); if ($tr && ($x = mysqli_fetch_assoc($tr))) { $type_name = $x['name']; }
            mysqli_stmt_close($ts);
        }
        mnt_pl_json(array('success' => true, 'task' => array('id' => $taskId, 'name' => $name, 'type_name' => $type_name, 'component' => $component, 'est_hours' => $est_hours), 'count' => mnt_pl_task_count($conn, $pid, $company_id)));
    }

    if ($_POST['action'] === 'del_task') {
        $tid = intval($_POST['task_id'] ?? 0);
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_plan_task WHERE id=? AND plan_id=? AND company_id=?")) {
            mysqli_stmt_bind_param($stmt, 'iii', $tid, $pid, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mnt_pl_json(array('success' => true, 'count' => mnt_pl_task_count($conn, $pid, $company_id)));
    }
}

// ── إنشاء خطة جديدة (يُحفظ فقط عند إرسال الفورم — لا سجلّ فارغ عند فتح الفورم) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_plan') {
    if (!$can_add) { header("Location: preventive_plans.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: preventive_plans.php?msg=لا+يمكن+الإنشاء+بلا+شركة+❌"); exit(); }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') { $name = 'خطة بلا اسم'; }
    $equipment_id   = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $category_id    = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $trigger_basis  = in_array($_POST['trigger_basis'] ?? '', $trigger_bases, true) ? $_POST['trigger_basis'] : 'ساعات';
    $interval_value = ($_POST['interval_value'] ?? '') !== '' ? intval($_POST['interval_value']) : null;

    // تمهيد موعد الاستحقاق عند الإنشاء حتى تصبح الخطة قابلة للاستحقاق بلا حفظ يدوي لاحق
    $last_done_date  = date('Y-m-d');
    $last_done_meter = null;
    $next_due_date   = null;
    $next_due_meter  = null;
    if ($interval_value !== null && $interval_value > 0) {
        if ($trigger_basis === 'زمن') {
            $next_due_date = date('Y-m-d', strtotime('+' . intval($interval_value) . ' day'));
        } elseif ($trigger_basis === 'ساعات' && $equipment_id) {
            $last_done_meter = mnt_equipment_actual_hours($conn, intval($equipment_id), $company_id);
            $next_due_meter  = $last_done_meter + intval($interval_value);
        }
    }

    $code = mnt_next_code($conn, 'mnt_plan', 'PLN', $company_id);
    $new_id = 0;
    $sql = "INSERT INTO mnt_plan (company_id, code, name, equipment_id, category_id, trigger_basis, interval_value, last_done_date, last_done_meter, next_due_date, next_due_meter, state, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'نشطة', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // i s s i i s i s d s d i
        $tp = 'issiis' . 'isdsd' . 'i';
        mysqli_stmt_bind_param($stmt, $tp,
            $company_id, $code, $name, $equipment_id, $category_id, $trigger_basis, $interval_value,
            $last_done_date, $last_done_meter, $next_due_date, $next_due_meter, $current_user_id);
        mysqli_stmt_execute($stmt); $new_id = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    }
    header("Location: preventive_plans.php?id=" . intval($new_id) . "&msg=تم+إنشاء+الخطة+✅"); exit();
}

// ── حفظ رأس الخطة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plan') {
    if (!$can_edit) { header("Location: preventive_plans.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    $pid = intval($_POST['id'] ?? 0);
    $plan = mnt_fetch_plan($conn, $pid, $company_id, $is_super_admin);
    if (!$plan) { header("Location: preventive_plans.php?msg=الخطة+غير+موجودة+❌"); exit(); }

    $name = trim($_POST['name'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $equipment_id = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $category_id  = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $trigger_basis = in_array($_POST['trigger_basis'] ?? '', $trigger_bases, true) ? $_POST['trigger_basis'] : 'ساعات';
    $interval_value = ($_POST['interval_value'] ?? '') !== '' ? intval($_POST['interval_value']) : null;
    $tolerance = ($_POST['tolerance'] ?? '') !== '' ? intval($_POST['tolerance']) : null;
    $last_done_date = !empty($_POST['last_done_date']) ? $_POST['last_done_date'] : null;
    $last_done_meter = ($_POST['last_done_meter'] ?? '') !== '' ? floatval($_POST['last_done_meter']) : null;
    $next_due_date = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
    $next_due_meter = ($_POST['next_due_meter'] ?? '') !== '' ? floatval($_POST['next_due_meter']) : null;
    $state = in_array($_POST['state'] ?? '', $states, true) ? $_POST['state'] : 'نشطة';

    if ($name === '') { $name = 'خطة بلا اسم'; }

    $sql = "UPDATE mnt_plan SET name=?, scope=?, equipment_id=?, category_id=?, trigger_basis=?,
                interval_value=?, tolerance=?, last_done_date=?, last_done_meter=?, next_due_date=?, next_due_meter=?, state=?
             WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // s s i i s | i i s d s | d s | i i
        $tp = 'ssiis' . 'iisds' . 'ds' . 'ii';
        mysqli_stmt_bind_param($stmt, $tp,
            $name, $scope, $equipment_id, $category_id, $trigger_basis,
            $interval_value, $tolerance, $last_done_date, $last_done_meter, $next_due_date, $next_due_meter, $state,
            $pid, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: preventive_plans.php?id=" . intval($pid) . "&msg=تم+حفظ+الخطة+✅"); exit();
}

// ── مهام الخطة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_task') {
    if (!$can_edit) { header("Location: preventive_plans.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $pid = intval($_POST['plan_id'] ?? 0);
    $plan = mnt_fetch_plan($conn, $pid, $company_id, $is_super_admin);
    if ($plan) {
        $name = trim($_POST['task_name'] ?? '');
        $task_type = !empty($_POST['task_type']) ? intval($_POST['task_type']) : null;
        $component = trim($_POST['component'] ?? '');
        $est_hours = floatval($_POST['est_hours'] ?? 0);
        if ($name !== '' && ($stmt = mysqli_prepare($conn, "INSERT INTO mnt_plan_task (company_id, plan_id, name, task_type, component, est_hours) VALUES (?,?,?,?,?,?)"))) {
            mysqli_stmt_bind_param($stmt, 'iisisd', $company_id, $pid, $name, $task_type, $component, $est_hours);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
    }
    header("Location: preventive_plans.php?id=" . intval($pid) . "&msg=تمت+إضافة+المهمة+✅"); exit();
}
if (isset($_GET['del_task'], $_GET['plan_id'])) {
    if ($can_edit) {
        $tid = intval($_GET['del_task']); $pid = intval($_GET['plan_id']);
        if ($stmt = mysqli_prepare($conn, "DELETE FROM mnt_plan_task WHERE id=? AND plan_id=? AND company_id=?")) {
            mysqli_stmt_bind_param($stmt, 'iii', $tid, $pid, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: preventive_plans.php?id=" . $pid . "&msg=تم+حذف+المهمة+✅"); exit();
    }
}

// ── توليد أمر صيانة وقائي من خطة (يدوي) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_order') {
    if (!$can_add) { header("Location: preventive_plans.php?msg=لا+توجد+صلاحية+توليد+أمر+❌"); exit(); }
    $pid = intval($_POST['plan_id'] ?? 0);
    $plan = mnt_fetch_plan($conn, $pid, $company_id, $is_super_admin);
    if (!$plan) { header("Location: preventive_plans.php?msg=الخطة+غير+موجودة+❌"); exit(); }
    $code = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
    $eq = $plan['equipment_id'] !== null ? intval($plan['equipment_id']) : null;
    $new_id = 0;
    $sql = "INSERT INTO mnt_order (company_id, code, plan_id, equipment_id, source, maint_type, state, created_by)
            VALUES (?, ?, ?, ?, 'وقائي', 'صيانة وقائية', 'بلاغ', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isiii', $company_id, $code, $pid, $eq, $current_user_id);
        mysqli_stmt_execute($stmt); $new_id = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    }
    header("Location: orders.php?id=" . intval($new_id) . "&msg=تم+توليد+أمر+وقائي+من+الخطة+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: preventive_plans.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $did = intval($_GET['delete_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE mnt_plan SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $did, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: preventive_plans.php?msg=تم+حذف+الخطة+✅"); exit();
}

$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$plan = $edit_id > 0 ? mnt_fetch_plan($conn, $edit_id, $company_id, $is_super_admin) : null;

$equipments = array(); $categories = array(); $task_types = array();
if ($plan || $edit_id === 0) {
    $cscope = $is_super_admin ? "1=1" : "company_id = " . intval($company_id);
    if ($r = mysqli_query($conn, "SELECT id, name, code FROM equipments WHERE $cscope ORDER BY name")) { while ($x = mysqli_fetch_assoc($r)) $equipments[] = $x; }
    if ($r = mysqli_query($conn, "SELECT id, type FROM equipments_types WHERE status='active' ORDER BY type")) { while ($x = mysqli_fetch_assoc($r)) $categories[] = $x; }
    $task_types = mnt_lookup_options($conn, $company_id, 'نوع مهمة');
}

$page_title = 'إيكوبيشن | الخطة الوقائية';
include '../inheader.php';
include '../insidebar.php';
function mnt_opt($value, $label, $selected) {
    return "<option value='" . htmlspecialchars((string) $value, ENT_QUOTES) . "'" . ($selected ? " selected" : "") . ">" . htmlspecialchars((string) $label) . "</option>";
}
?>
<div class="main mnt-plans-main ems-unified-page-shell">

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>" style="margin-bottom:12px;">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

<?php if ($plan): // ── تحرير خطة ──
    $tasks = array();
    if ($s = mysqli_prepare($conn, "SELECT t.id, t.name, t.component, t.est_hours, lk.name AS task_type_name FROM mnt_plan_task t LEFT JOIN mnt_lookup lk ON lk.id=t.task_type WHERE t.plan_id=? AND t.company_id=? ORDER BY t.id")) {
        mysqli_stmt_bind_param($s, 'ii', $edit_id, $company_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($rr && $x = mysqli_fetch_assoc($rr)) $tasks[] = $x; mysqli_stmt_close($s);
    }
    // العدّاد الحالي من التايم‌شيت
    $current_meter = $plan['equipment_id'] ? mnt_equipment_actual_hours($conn, intval($plan['equipment_id']), $company_id) : 0;
?>
    <?php
    $header_title_html = 'خطة وقائية: <strong>' . htmlspecialchars((string) $plan['code']) . '</strong>';
    $header_icon = 'fa fa-calendar-check';
    $header_actions = array();
    $header_actions[] = array('id' => 'togglePlanForm', 'class' => 'add-btn', 'icon' => 'fas fa-pen-to-square', 'label' => 'بيانات الخطة');
    $header_back = array(
        array('tag' => 'a', 'href' => 'preventive_plans.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'كل الخطط'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>
    <form method="post" action="" class="allforms allforms-visible" id="planForm">
        <input type="hidden" name="action" value="save_plan">
        <input type="hidden" name="id" value="<?php echo intval($plan['id']); ?>">
        <div class="card-header"><h5><i class="fas fa-calendar-check"></i> بيانات الخطة</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>اسم الخطة</label><input type="text" name="name" value="<?php echo htmlspecialchars((string) $plan['name']); ?>"></div>
                <div class="form-group"><label>النطاق</label>
                    <select name="scope"><option value="">-- اختر --</option>
                        <?php foreach (array('معدة', 'فئة') as $sc) echo mnt_opt($sc, $sc, $plan['scope'] === $sc); ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدة</label>
                    <select name="equipment_id"><option value="">-- اختر --</option>
                        <?php foreach ($equipments as $e) echo mnt_opt($e['id'], $e['name'] . (!empty($e['code']) ? ' (' . $e['code'] . ')' : ''), intval($plan['equipment_id']) === intval($e['id'])); ?>
                    </select>
                </div>
                <div class="form-group"><label>الفئة (نوع المعدة)</label>
                    <select name="category_id"><option value="">-- اختر --</option>
                        <?php foreach ($categories as $c) echo mnt_opt($c['id'], $c['type'], intval($plan['category_id']) === intval($c['id'])); ?>
                    </select>
                </div>
                <div class="form-group"><label>أساس التكرار</label>
                    <select name="trigger_basis"><?php foreach ($trigger_bases as $tb) echo mnt_opt($tb, $tb, $plan['trigger_basis'] === $tb); ?></select>
                </div>
                <div class="form-group"><label>الفاصل (ساعات أو أيام)</label><input type="number" name="interval_value" value="<?php echo htmlspecialchars((string) $plan['interval_value']); ?>"></div>
                <div class="form-group"><label>هامش السماح</label><input type="number" name="tolerance" value="<?php echo htmlspecialchars((string) $plan['tolerance']); ?>"></div>
                <div class="form-group"><label>آخر تنفيذ (تاريخ)</label><input type="date" name="last_done_date" value="<?php echo htmlspecialchars((string) $plan['last_done_date']); ?>"></div>
                <div class="form-group"><label>عدّاد آخر تنفيذ</label><input type="number" step="0.01" name="last_done_meter" value="<?php echo htmlspecialchars((string) $plan['last_done_meter']); ?>"></div>
                <div class="form-group"><label>الاستحقاق القادم (تاريخ)</label><input type="date" name="next_due_date" value="<?php echo htmlspecialchars((string) $plan['next_due_date']); ?>"></div>
                <div class="form-group"><label>الاستحقاق القادم (عدّاد)</label><input type="number" step="0.01" name="next_due_meter" value="<?php echo htmlspecialchars((string) $plan['next_due_meter']); ?>"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="state"><?php foreach ($states as $s) echo mnt_opt($s, $s, $plan['state'] === $s); ?></select>
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ الخطة</button>
                <button type="button" class="btn-cancel" id="collapsePlanForm"><i class="fas fa-chevron-up"></i> طيّ النموذج</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="mnt-cost-summary">
            <div class="mnt-cost-box"><span>عدّاد التشغيل الفعلي (تايم‌شيت)</span><strong><?php echo number_format((float) $current_meter, 1); ?></strong></div>
            <div class="mnt-cost-box"><span>الاستحقاق القادم (عدّاد)</span><strong><?php echo $plan['next_due_meter'] !== null ? number_format((float) $plan['next_due_meter'], 1) : '—'; ?></strong></div>
            <div class="mnt-cost-box"><span>الاستحقاق القادم (تاريخ)</span><strong><?php echo htmlspecialchars((string) ($plan['next_due_date'] ?? '—')); ?></strong></div>
        </div>
    </div></div>

    <!-- مهام الخطة -->
    <div class="card mnt-lines-card">
        <div class="card-header mnt-lines-head">
            <h5><i class="fas fa-list-check"></i> مهام الخطة <span class="mnt-count" id="taskCount"><?php echo count($tasks); ?></span></h5>
            <?php if ($can_edit): ?><button type="button" class="mnt-add-toggle" data-target="taskForm"><i class="fas fa-plus"></i> إضافة مهمة</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit): ?>
            <form class="mnt-line-form" id="taskForm" onsubmit="return false;" style="display:none;">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" name="plan_id" value="<?php echo intval($plan['id']); ?>">
                <div class="mnt-line-grid">
                    <div class="form-group"><label>المهمة</label><input type="text" name="task_name" placeholder="مثال: تغيير زيت المحرك"></div>
                    <div class="form-group"><label>نوع المهمة</label><select name="task_type"><option value="">-- اختر --</option><?php foreach ($task_types as $id => $nm) echo mnt_opt($id, $nm, false); ?></select></div>
                    <div class="form-group"><label>المكوّن</label><input type="text" name="component" placeholder="مثال: المحرك"></div>
                    <div class="form-group"><label>ساعات تقديرية</label><input type="number" step="0.01" name="est_hours" value="0"></div>
                </div>
                <div class="mnt-line-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إضافة المهمة</button>
                    <button type="button" class="btn-cancel mnt-line-cancel" data-target="taskForm"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-container"><table class="alltables no-datatable mnt-line-table" id="taskTable" style="width:100%">
                <thead><tr><th>المهمة</th><th>النوع</th><th>المكوّن</th><th>ساعات تقديرية</th><?php if ($can_edit) echo '<th></th>'; ?></tr></thead>
                <tbody>
                    <?php foreach ($tasks as $t): ?>
                    <tr data-line="<?php echo intval($t['id']); ?>">
                        <td><?php echo htmlspecialchars((string) $t['name']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['task_type_name'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['component'] ?? '')); ?></td>
                        <td class="mnt-num"><?php echo htmlspecialchars((string) $t['est_hours']); ?></td>
                        <?php if ($can_edit): ?><td><button type="button" class="action-btn delete mnt-del-line" data-line="<?php echo intval($t['id']); ?>" title="حذف"><i class="fas fa-trash-alt"></i></button></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="mnt-empty-line" id="taskEmpty" style="<?php echo empty($tasks) ? '' : 'display:none'; ?>"><i class="fas fa-list-check"></i><span>لا توجد مهام بعد</span></div>
        </div>
    </div>

<?php else: // ── قائمة الخطط + المستحقة الآن ──
    $header_title  = 'الخطة الوقائية';
    $header_icon   = 'fa fa-calendar-check';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'togglePlanCreateForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'خطة جديدة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
?>
    <?php if ($can_add): ?>
    <!-- فورم إنشاء خطة (نمط العملاء/المشاريع: يُفتح بزر «خطة جديدة»، ولا يُحفظ شيء إلا عند الإرسال) -->
    <form method="post" action="" class="allforms" id="planCreateForm">
        <input type="hidden" name="action" value="new_plan">
        <div class="card-header"><h5><i class="fas fa-calendar-check"></i> إنشاء خطة وقائية جديدة</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>اسم الخطة</label><input type="text" name="name" placeholder="مثال: تغيير زيت كل 250 ساعة"></div>
                <div class="form-group"><label>المعدة</label>
                    <select name="equipment_id"><option value="">-- اختر --</option>
                        <?php foreach ($equipments as $e) echo mnt_opt($e['id'], $e['name'] . (!empty($e['code']) ? ' (' . $e['code'] . ')' : ''), false); ?>
                    </select>
                </div>
                <div class="form-group"><label>الفئة (نوع المعدة)</label>
                    <select name="category_id"><option value="">-- اختر --</option>
                        <?php foreach ($categories as $c) echo mnt_opt($c['id'], $c['type'], false); ?>
                    </select>
                </div>
                <div class="form-group"><label>أساس التكرار</label>
                    <select name="trigger_basis"><?php foreach ($trigger_bases as $tb) echo mnt_opt($tb, $tb, $tb === 'ساعات'); ?></select>
                </div>
                <div class="form-group"><label>الفاصل (ساعات أو أيام)</label><input type="number" name="interval_value" placeholder="مثال: 250"></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-plus"></i> إنشاء الخطة</button>
                <button type="button" class="btn-cancel" id="cancelPlanCreateForm"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>
<?php
    // جلب الخطط النشطة وحساب الاستحقاق
    $rows = array();
    $sql = "SELECT pl.*, e.name AS equipment_name FROM mnt_plan pl
             LEFT JOIN equipments e ON e.id = pl.equipment_id
            WHERE $company_scope_sql AND COALESCE(pl.is_deleted,0)=0
            ORDER BY pl.id DESC";
    if ($res = mysqli_query($conn, $sql)) { while ($x = mysqli_fetch_assoc($res)) $rows[] = $x; }

    $today = date('Y-m-d');
    $due_rows = array();
    foreach ($rows as $r) {
        $is_due = false;
        if ($r['state'] === 'نشطة') {
            $tol = isset($r['tolerance']) && $r['tolerance'] !== null ? intval($r['tolerance']) : 0;
            if ($r['trigger_basis'] === 'ساعات' && $r['next_due_meter'] !== null && $r['equipment_id']) {
                $meter = mnt_equipment_actual_hours($conn, intval($r['equipment_id']), $company_id);
                // هامش السماح (ساعات) يجعل الخطة مستحقة مبكّراً قبل بلوغ العدّاد الهدف
                if ($meter >= (floatval($r['next_due_meter']) - $tol)) { $is_due = true; }
            } elseif ($r['trigger_basis'] === 'زمن' && $r['next_due_date'] !== null) {
                // هامش السماح (أيام) يقدّم تاريخ الاستحقاق للإنذار المبكر
                $threshold = $tol > 0 ? date('Y-m-d', strtotime('+' . $tol . ' day', strtotime($today))) : $today;
                if ($r['next_due_date'] <= $threshold) { $is_due = true; }
            }
        }
        if ($is_due) { $due_rows[] = $r; }
    }
?>
    <?php if (!empty($due_rows)): ?>
    <div class="card"><div class="card-header"><h5><i class="fas fa-bell"></i> خطط مستحقة الآن (<?php echo count($due_rows); ?>)</h5></div><div class="card-body">
        <div class="table-container"><table class="display nowrap alltables no-datatable" style="width:100%">
            <thead><tr><th>توليد أمر</th><th>المرجع</th><th>الخطة</th><th>المعدة</th><th>الأساس</th><th>الاستحقاق</th></tr></thead>
            <tbody>
                <?php foreach ($due_rows as $r): ?>
                <tr>
                    <td>
                        <?php if ($can_add): ?>
                        <form method="post" action="" style="display:inline" onsubmit="return confirm('توليد أمر صيانة وقائي من هذه الخطة؟')">
                            <input type="hidden" name="action" value="generate_order">
                            <input type="hidden" name="plan_id" value="<?php echo intval($r['id']); ?>">
                            <button type="submit" class="add-btn" title="توليد أمر"><i class="fas fa-wrench"></i> توليد أمر</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars((string) $r['code']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string) $r['name']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['equipment_name'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['trigger_basis']); ?></td>
                    <td><?php echo $r['trigger_basis'] === 'ساعات' ? ('عدّاد: ' . htmlspecialchars((string) $r['next_due_meter'])) : ('تاريخ: ' . htmlspecialchars((string) $r['next_due_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="mntTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>المرجع</th><th>الخطة</th><th>المعدة</th><th>الأساس</th><th>الفاصل</th><th>الاستحقاق القادم</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        echo "<a href='preventive_plans.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
                        if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف الخطة؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        echo "</div></td>";
                        echo "<td><strong>" . htmlspecialchars((string) $row['code']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars((string) $row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['equipment_name'] ?? '-')) . "</td>";
                        echo "<td>" . htmlspecialchars((string) $row['trigger_basis']) . "</td>";
                        echo "<td>" . htmlspecialchars((string) ($row['interval_value'] ?? '-')) . "</td>";
                        $due = $row['trigger_basis'] === 'ساعات' ? (string) ($row['next_due_meter'] ?? '-') : (string) ($row['next_due_date'] ?? '-');
                        echo "<td>" . htmlspecialchars($due) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string) $row['state']) . "</span></td>";
                        echo "</tr>";
                    endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
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
        if ($('#mntTable').length) {
            $('#mntTable').DataTable({
                scrollX: true, autoWidth: false, stateSave: false, order: [[1, 'desc']],
                dom: 'Bfrtip',
                buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
                "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
            });
        }
    });

    // ════════ صفحة التحرير: مهام الخطة عبر AJAX + فتح/إغلاق الفورم ════════
    function esc(v){ return $('<div>').text(v == null ? '' : v).html(); }
    function postLine(payload){ return fetch('preventive_plans.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: payload }).then(function(r){ return r.json(); }); }

    var $taskForm = $('#taskForm');
    if ($taskForm.length) {
        $taskForm.on('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this); fd.append('ajax','1');
            postLine(new URLSearchParams(fd)).then(function(res){
                if(!res.success){ alert(res.message || 'تعذّر إضافة المهمة'); return; }
                var t = res.task;
                var row = '<tr data-line="'+t.id+'">'
                    + '<td>'+esc(t.name)+'</td><td>'+esc(t.type_name||'—')+'</td><td>'+esc(t.component||'')+'</td>'
                    + '<td class="mnt-num">'+esc(t.est_hours)+'</td>'
                    + '<td><button type="button" class="action-btn delete mnt-del-line" data-line="'+t.id+'" title="حذف"><i class="fas fa-trash-alt"></i></button></td></tr>';
                $('#taskTable tbody').append(row);
                $('#taskEmpty').hide();
                $('#taskCount').text(res.count);
                $taskForm[0].reset();
            }).catch(function(){ alert('خطأ في الاتصال'); });
        });
    }

    $(document).on('click', '.mnt-del-line', function () {
        if (!confirm('حذف المهمة؟')) return;
        var $btn = $(this), taskId = $btn.data('line');
        var pid = ($('#taskForm input[name=plan_id]').val() || $('input[name=id]').val());
        var body = new URLSearchParams({ ajax:'1', action:'del_task', plan_id: pid, task_id: taskId });
        postLine(body).then(function(res){
            if(!res.success){ alert(res.message || 'تعذّر الحذف'); return; }
            var $tbody = $btn.closest('tbody'); $btn.closest('tr').remove();
            $('#taskCount').text(res.count);
            if ($tbody.find('tr').length === 0) { $('#taskEmpty').show(); }
        }).catch(function(){ alert('خطأ في الاتصال'); });
    });

    // فتح/إغلاق فورم بيانات الخطة (نمط العملاء/المشاريع)
    var $planForm = $('#planForm');
    function openPlanForm(){ $planForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); }
    function closePlanForm(){ $planForm.stop(true, true).slideUp(220, function(){ $planForm.removeClass('allforms-visible'); }); }
    $('#togglePlanForm').on('click', function(){
        if ($planForm.hasClass('allforms-visible')) { closePlanForm(); }
        else { openPlanForm(); $('html, body').animate({ scrollTop: $planForm.offset().top - 90 }, 360); }
    });
    $('#collapsePlanForm').on('click', closePlanForm);

    // فتح/إغلاق فورم إضافة مهمة
    $(document).on('click', '.mnt-add-toggle', function(){
        var $f = $('#' + $(this).data('target'));
        if ($f.is(':visible')) { $f.stop(true, true).slideUp(180); }
        else { $f.stop(true, true).slideDown(180); $f.find('select, input').not('[type=hidden]').first().trigger('focus'); }
    });
    $(document).on('click', '.mnt-line-cancel', function(){
        $('#' + $(this).data('target')).stop(true, true).slideUp(180);
    });

    // قائمة الخطط: فتح/إغلاق فورم الإنشاء (بلا حفظ سجل فارغ)
    var $createForm = $('#planCreateForm');
    function closeCreateForm(){ $createForm.stop(true, true).slideUp(220, function(){ $createForm.removeClass('allforms-visible'); }); }
    $('#togglePlanCreateForm').on('click', function(){
        if ($createForm.hasClass('allforms-visible')) { closeCreateForm(); }
        else { $createForm.addClass('allforms-visible').hide().stop(true, true).slideDown(220); $('html, body').animate({ scrollTop: $createForm.offset().top - 90 }, 360); }
    });
    $('#cancelPlanCreateForm').on('click', closeCreateForm);
})();
</script>
<style>
    /* ملخص العدّادات */
    .mnt-plans-main .mnt-cost-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .mnt-plans-main .mnt-cost-box { background:var(--s1,#fff); border:1px solid var(--bdr,#ece6d8); border-radius:14px; padding:14px; text-align:center; box-shadow:0 2px 8px rgba(26,18,8,.06); }
    .mnt-plans-main .mnt-cost-box span { display:block; color:var(--t2,#8a7a5c); font-size:.8rem; font-weight:700; margin-bottom:7px; }
    .mnt-plans-main .mnt-cost-box strong { font-size:1.4rem; font-variant-numeric:tabular-nums; color:var(--t1,#1a1208); }

    /* ══ لوحة مهام الخطة — تصميم قوي متّسق مع هوية الفورمات ══ */
    .mnt-plans-main .mnt-lines-card { overflow:hidden; }
    .mnt-plans-main .mnt-lines-card > .card-header.mnt-lines-head {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:linear-gradient(135deg,#1f4f7a,#2f6fa5); color:#fff; padding:13px 16px; border:none;
    }
    .mnt-plans-main .mnt-lines-head h5 { display:flex; align-items:center; gap:8px; margin:0; color:#fff; font-weight:800; font-size:1rem; }
    .mnt-plans-main .mnt-lines-head h5 i { color:#ffd98a; }
    .mnt-plans-main .mnt-count { display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:24px; padding:0 8px; border-radius:999px; background:rgba(255,255,255,.22); color:#fff; font-size:.76rem; font-weight:800; }
    .mnt-plans-main .mnt-add-toggle {
        display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;
        padding:7px 15px; border-radius:999px; font-weight:800; font-size:.82rem; color:#1a1208;
        background:linear-gradient(135deg,#E0AE2E,#f5d27e); box-shadow:0 2px 8px rgba(224,174,46,.4); transition:transform .15s, box-shadow .15s;
    }
    .mnt-plans-main .mnt-add-toggle:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(224,174,46,.5); }
    .mnt-plans-main .mnt-line-form { background:linear-gradient(180deg,#fffdf7,#fbf6ea); border:1px solid var(--bdr,#e7dcc4); border-radius:16px; padding:14px; margin-bottom:14px; box-shadow:inset 0 1px 0 #fff, 0 2px 8px rgba(26,18,8,.05); }
    .mnt-plans-main .mnt-line-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end; }
    .mnt-plans-main .mnt-line-grid .form-group { margin:0; }
    .mnt-plans-main .mnt-line-actions { display:flex; align-items:center; gap:10px; margin-top:14px; flex-wrap:wrap; padding-top:12px; border-top:1px dashed var(--bdr,#e7dcc4); }
    .mnt-plans-main .mnt-line-cancel { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

    /* جدول المهام */
    .mnt-plans-main .mnt-line-table { width:100%; border-collapse:separate; border-spacing:0; }
    .mnt-plans-main .mnt-line-table thead th { background:#f3ede0; color:#6b5d3e; font-weight:800; font-size:.82rem; padding:10px 12px; border-bottom:2px solid #e7dcc4; }
    .mnt-plans-main .mnt-line-table tbody td { font-size:.88rem; padding:10px 12px; border-bottom:1px solid #f0e9da; }
    .mnt-plans-main .mnt-line-table tbody tr:hover { background:rgba(224,174,46,.07); }
    .mnt-plans-main .mnt-line-table .mnt-num { font-variant-numeric:tabular-nums; font-weight:700; }
    .mnt-plans-main .mnt-line-table td:last-child, .mnt-plans-main .mnt-line-table th:last-child { text-align:center; }
    .mnt-plans-main .mnt-empty-line { display:flex; flex-direction:column; align-items:center; gap:8px; color:#b0a489; padding:24px 10px; }
    .mnt-plans-main .mnt-empty-line i { font-size:1.9rem; opacity:.5; }
    .mnt-plans-main .mnt-empty-line span { font-size:.9rem; font-weight:600; }

    @media (max-width:900px){ .mnt-plans-main .mnt-cost-summary{ grid-template-columns:1fr;} }
</style>
</body>
</html>
