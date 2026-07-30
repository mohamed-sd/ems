<?php
/**
 * Workforce/contract_registry.php — سجلُّ العقود الموحّد (H-08-① · CON-01 §6 «سجل العقود»)
 * ───────────────────────────────────────────────────────────────────────────
 * قائمةُ UI-01: العقودُ بحالاتها ومددها وتنبيهاتِ الانتهاء · فلاترُ الفئة
 * والحالة والمشروع. الرأسُ يُنشأ هنا (مسودةً — المعالجُ التسعُ خطواتٍ يكتمل مع
 * الشرائح ②③④)، والانتقالاتُ تُبنى من قائمة سماح الآلة **ولا تُعرض إلا
 * المشروعة** — والحكمُ في الخدمة لا هنا.
 *
 * **الصلاحيةُ صارمة** (نمطُ التسويات): الوحدةُ بكودها الحرفي وغيابُها منع.
 * **المرحَّلُ قراءةً** (source_table): مصدرُه القديمُ كاتبُه — شارةٌ ولا أزرار.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/EmployeeContractStateMachine.php';
require_once __DIR__ . '/../app/Services/Contract/EmployeeContractService.php';

use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي وغيابُها منع ─────────────────────
$MODULE_CODE = 'Workforce/contract_registry.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+سجل+العقود+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contract registry super') : ems_tenant_db();

$CATEGORIES = array(
    'permanent'       => 'موظف دائم',
    'project'         => 'موظف مشروع (مؤقت)',
    'operator'        => 'مشغّل وسائق',
    'supplier_worker' => 'عامل مورد (تسجيلٌ تشغيلي)',
);
$SOURCES = array(
    'worker_contract'       => 'عقود العاملين (القديم)',
    'drivercontracts'       => 'عقود السائقين (القديم)',
    'fin_operator_policies' => 'سياسات المشغّلين',
);

// ── إنشاءُ رأسِ عقدٍ (مسودة) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'create') {
    if (!$can_add) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $r = ECS::createHead($conn, $gate, $company_id, array(
        'employee_id'   => intval($_POST['employee_id'] ?? 0),
        'category'      => strval($_POST['category'] ?? ''),
        'pay_model_id'  => intval($_POST['pay_model_id'] ?? 0),
        'project_id'    => intval($_POST['project_id'] ?? 0),
        'start_date'    => strval($_POST['start_date'] ?? ''),
        'end_date'      => strval($_POST['end_date'] ?? ''),
        'probation_end' => strval($_POST['probation_end'] ?? ''),
        'relation_type' => strval($_POST['relation_type'] ?? ''),
        'currency'      => strval($_POST['currency'] ?? ''),
    ), $uid);
    $msg = $r['ok'] ? ('أُنشئ رأسُ العقد #' . intval($r['id']) . ' مسودةً ✅')
                    : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?msg=" . rawurlencode($msg)); exit();
}

// ── انتقالُ حالةٍ / تعليقٌ-إعارةٌ / استئناف ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('transition', 'hold', 'resume'), true)) {
    if (!$can_edit) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $cid  = intval($_POST['contract_id'] ?? 0);
    $note = trim(strval($_POST['note'] ?? ''));
    $ver  = intval($_POST['version'] ?? 0);
    $act  = strval($_POST['do']);
    if ($act === 'transition') {
        $r = ECSM::transition($conn, $gate, $company_id, $cid, strval($_POST['to_state'] ?? ''), $note, $uid, $ver > 0 ? $ver : null);
    } elseif ($act === 'hold') {
        $r = ECSM::hold($conn, $gate, $company_id, $cid, strval($_POST['hold_kind'] ?? ''), $note, $uid);
    } else {
        $r = ECSM::resume($conn, $gate, $company_id, $cid, $note, $uid);
    }
    $msg = $r['ok'] ? ($r['changed'] ? 'نُفّذ ✅' : ($r['reason'] . ' ✅')) : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?msg=" . rawurlencode($msg)); exit();
}

// ── القراءة ────────────────────────────────────────────────────────────────
$f_category = strval($_GET['category'] ?? '');
$f_state    = strval($_GET['state'] ?? '');
$f_project  = intval($_GET['project_id'] ?? 0);

$where = "COALESCE(ec.is_deleted,0)=0";
$params = array();
if (isset($CATEGORIES[$f_category])) { $where .= " AND ec.category = ?"; $params[] = $f_category; }
if (in_array($f_state, ECSM::ALL, true)) { $where .= " AND ec.state = ?"; $params[] = $f_state; }
if ($f_project > 0) { $where .= " AND ec.project_id = ?"; $params[] = $f_project; }

$rows = array();
try {
    // pay_models كتالوجٌ عام (T_GLOBAL) — يُقرأ بلا إعلان نطاق
    $rows = $gate->scopedQuery(array(
        'scope'  => array('ec' => 'employee_contracts'),
        'enrich' => array('e' => 'employees', 'p' => 'project'),
    ), "SELECT ec.*, e.name AS employee_name, p.name AS project_name,
               pm.code AS pay_code, pm.label_ar AS pay_label,
               DATEDIFF(ec.end_date, CURDATE()) AS days_left
        FROM employee_contracts ec
        LEFT JOIN employees e ON e.id = ec.employee_id
        LEFT JOIN project p ON p.id = ec.project_id
        LEFT JOIN pay_models pm ON pm.id = ec.pay_model_id
        WHERE {TENANT_SCOPE} AND {$where}
        ORDER BY ec.state = 'active' DESC, ec.end_date IS NULL, ec.end_date, ec.id", $params);
} catch (\Throwable $t) { $rows = array(); }

$employees_options = array();
try {
    // گوتشا مقيسة: employees بلا عمود is_deleted (soft=false في السجل) — لا تُصفِّ به
    $employees_options = $gate->scopedQuery(array('scope' => array('e' => 'employees')),
        "SELECT e.id, e.name FROM employees e
         WHERE {TENANT_SCOPE} ORDER BY e.name");
} catch (\Throwable $t) { $employees_options = array(); }

$projects_options = array();
try {
    $projects_options = $gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT p.id, p.name FROM project p
         WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 ORDER BY p.name");
} catch (\Throwable $t) { $projects_options = array(); }

$pay_models = array();
$pmRes = $conn->query("SELECT id, code, label_ar, calc_path FROM pay_models WHERE is_active = 1 ORDER BY id");
if ($pmRes) { while ($pmRow = $pmRes->fetch_assoc()) { $pay_models[] = $pmRow; } }

$page_title = 'إيكوبيشن | سجل العقود الموحّد';
include '../inheader.php';
include '../insidebar.php';

$stateChip = function ($state) {
    $label = ECSM::labelAr($state);
    $cls = 'badge-secondary';
    if (ECSM::isReadable($state)) { $cls = 'badge-success'; }
    elseif (in_array($state, array(ECSM::REJECTED, ECSM::DECLINED, ECSM::TERMINATED), true)) { $cls = 'badge-danger'; }
    elseif ($state === ECSM::SUSPENDED) { $cls = 'badge-warning'; }
    elseif (in_array($state, array(ECSM::EXPIRED, ECSM::SETTLED, ECSM::CLOSED, ECSM::ARCHIVED), true)) { $cls = 'badge-dark'; }
    elseif ($state !== ECSM::DRAFT) { $cls = 'badge-info'; }
    return "<span class='badge {$cls}'>" . htmlspecialchars($label) . "</span>";
};
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل العقود الموحّد'; $header_icon = 'fa fa-file-signature';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleForm',
            'icon' => 'fa fa-plus', 'label' => 'رأس عقد جديد', 'class' => 'add');
    }
    $header_back = array('href' => 'worker_contract.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'عقود العاملين (القديم)');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="headForm">
        <input type="hidden" name="do" value="create">
        <div class="card"><div class="card-header"><h5><i class="fa fa-file-signature"></i> رأسُ عقدٍ جديد (مسودة — المكوّناتُ والحوافزُ والتحمّل مع الشرائح التالية)</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label>الشخص <span style="color:#c00">*</span></label>
                <select name="employee_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($employees_options as $e): ?>
                        <option value="<?php echo intval($e['id']); ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>الفئة <span style="color:#c00">*</span></label>
                <select name="category" required>
                    <?php foreach ($CATEGORIES as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>نموذج الأجر <span style="color:#c00">*</span> <small>(اختيارٌ مستقلٌّ لا يُشتق من الوظيفة)</small></label>
                <select name="pay_model_id" required>
                    <option value="">— من القائمة الخمس عشرة —</option>
                    <?php foreach ($pay_models as $pm): ?>
                        <option value="<?php echo intval($pm['id']); ?>"><?php echo htmlspecialchars($pm['label_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>المشروع (لفئة «مشروع»)</label>
                <select name="project_id">
                    <option value="">— بلا —</option>
                    <?php foreach ($projects_options as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>بداية المدة</label><input type="date" name="start_date"></div>
            <div class="form-group"><label>نهاية المدة</label><input type="date" name="end_date"></div>
            <div class="form-group"><label>نهاية التجربة</label><input type="date" name="probation_end"></div>
            <div class="form-group"><label>طبيعة الارتباط</label><input type="text" name="relation_type" maxlength="50"></div>
            <div class="form-group"><label>العملة</label><input type="text" name="currency" maxlength="8" placeholder="SDG"></div>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ مسودة</button>
        </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <strong>الفئة:</strong>
            <select name="category" onchange="this.form.submit()">
                <option value="">الكل</option>
                <?php foreach ($CATEGORIES as $k => $lbl): ?>
                    <option value="<?php echo $k; ?>" <?php echo $f_category === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
            <strong>الحالة:</strong>
            <select name="state" onchange="this.form.submit()">
                <option value="">الكل</option>
                <?php foreach (ECSM::ALL as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $f_state === $s ? 'selected' : ''; ?>><?php echo ECSM::labelAr($s); ?></option>
                <?php endforeach; ?>
            </select>
            <strong>المشروع:</strong>
            <select name="project_id" onchange="this.form.submit()">
                <option value="0">الكل</option>
                <?php foreach ($projects_options as $p): ?>
                    <option value="<?php echo intval($p['id']); ?>" <?php echo $f_project === intval($p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="table-container">
            <!-- no-datatable: فلاترُ الشاشة خارجيةٌ (GET) — التهيئةُ يدويةٌ بstateSave:false
                 (درسُ tn/18 والفلاتر الخارجية: حالةٌ محفوظةٌ قديمةٌ تُخفي الصفوف N→0) -->
            <table class="alltables display nowrap no-datatable" id="contractRegistryTable" style="width:100%">
                <thead><tr>
                    <th>#</th><th>الشخص</th><th>الفئة</th><th>نموذج الأجر</th><th>المشروع</th>
                    <th>المدة</th><th>الحالة</th><th>المصدر</th><th>الإجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $state = strval($r['state']);
                    $isMigrated = trim(strval($r['source_table'] ?? '')) !== '';
                    $daysLeft = ($r['days_left'] !== null) ? intval($r['days_left']) : null;
                ?>
                    <tr>
                        <td><?php echo intval($r['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($r['employee_name'] ?? ('#' . intval($r['employee_id']))); ?></strong></td>
                        <td><?php echo htmlspecialchars($CATEGORIES[$r['category']] ?? $r['category']); ?></td>
                        <td><?php echo htmlspecialchars($r['pay_label'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['project_name'] ?? '—'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($r['start_date'] ?: '؟'); ?> → <?php echo htmlspecialchars($r['end_date'] ?: 'مفتوح'); ?>
                            <?php if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 30 && ECSM::isReadable($state)): ?>
                                <span class="badge badge-warning" title="CON-01 §6: شارةُ عقدٍ ينتهي خلال ثلاثين يومًا">ينتهي بعد <?php echo $daysLeft; ?> يومًا</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $stateChip($state); ?></td>
                        <td>
                            <?php if ($isMigrated): ?>
                                <span class="badge badge-light" title="الترحيلُ قراءةً — الكتابةُ في مصدره القديم حتى إقفاله بمطابقة (N-04)">
                                    مرحَّل قراءةً · <?php echo htmlspecialchars($SOURCES[$r['source_table']] ?? $r['source_table']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-primary">السجل الموحّد</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isMigrated || !$can_edit): ?>
                                —
                            <?php else: ?>
                                <?php $allowed = ECSM::allowedFrom($state); ?>
                                <?php if ($allowed): ?>
                                <form method="post" style="display:inline-flex;gap:4px;align-items:center">
                                    <input type="hidden" name="do" value="transition">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="version" value="<?php echo intval($r['version']); ?>">
                                    <select name="to_state">
                                        <?php foreach ($allowed as $to): ?>
                                            <option value="<?php echo $to; ?>">← <?php echo ECSM::labelAr($to); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="note" placeholder="ملاحظة" style="width:90px">
                                    <button type="submit" class="action-btn edit" title="نقل الحالة"><i class="fas fa-arrow-left"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($state, ECSM::HOLDABLE, true)): ?>
                                <form method="post" style="display:inline-flex;gap:4px;align-items:center">
                                    <input type="hidden" name="do" value="hold">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($r['id']); ?>">
                                    <select name="hold_kind">
                                        <option value="suspended">تعليق</option>
                                        <option value="seconded">إعارة</option>
                                    </select>
                                    <input type="text" name="note" placeholder="السبب (إلزامي)" style="width:110px">
                                    <button type="submit" class="action-btn" title="تعليق/إعارة"><i class="fas fa-pause"></i></button>
                                </form>
                                <?php elseif ($state === ECSM::SUSPENDED || $state === ECSM::SECONDED): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="do" value="resume">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="note" value="استئناف">
                                    <button type="submit" class="action-btn edit" title="استئناف — يعود إلى حيث كان"><i class="fas fa-play"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('toggleForm');
        var form = document.getElementById('headForm');
        if (toggleBtn && form) {
            toggleBtn.addEventListener('click', function () {
                form.reset();
                form.classList.toggle('allforms-visible');
            });
        }
        // تهيئةٌ يدوية (no-datatable): stateSave:false لأن الفلاتر خارجية
        if (window.jQuery && jQuery.fn.dataTable) {
            try {
                jQuery('#contractRegistryTable').DataTable({
                    stateSave: false,
                    autoWidth: false,
                    order: [],
                    language: { url: '/ems/assets/i18n/datatables/ar.json' }
                });
            } catch (e) { /* جدولٌ فارغٌ أو مهيأ سلفًا */ }
        }
    });
})();
</script>
</body>
</html>
