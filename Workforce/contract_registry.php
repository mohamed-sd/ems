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
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/EmployeeContractStateMachine.php';
require_once __DIR__ . '/../app/Services/Contract/EmployeeContractService.php';
require_once __DIR__ . '/../app/Services/Contract/EmployeeContractAmendmentService.php';

use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Contract\EmployeeContractAmendmentService as ECAS;

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

// ── H-08-②: مكوّناتُ الأجر (إضافةٌ/تعديلٌ/إنهاء — الحكمُ في الخدمة) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('comp_add', 'comp_update', 'comp_end'), true)) {
    if (!$can_edit && !$can_add) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $ctxContract = intval($_POST['contract_id'] ?? 0);
    $act = strval($_POST['do']);
    $compData = array(
        'component_type' => strval($_POST['component_type'] ?? ''),
        'calc_method'    => strval($_POST['calc_method'] ?? ''),
        'value'          => strval($_POST['value'] ?? ''),
        'rate'           => strval($_POST['rate'] ?? ''),
        'is_variable'    => intval($_POST['is_variable'] ?? 0),
        'periodicity'    => strval($_POST['periodicity'] ?? 'monthly'),
        'valid_from'     => strval($_POST['valid_from'] ?? ''),
        'valid_to'       => strval($_POST['valid_to'] ?? ''),
    );
    foreach (\App\Services\Contract\EmployeeContractService::COMPONENT_FLAGS as $flag) {
        $compData[$flag] = intval($_POST[$flag] ?? 0);
    }
    if ($act === 'comp_add') {
        $r = ECS::addComponent($conn, $gate, $company_id, $ctxContract, $compData, $uid);
    } elseif ($act === 'comp_update') {
        $r = ECS::updateComponent($conn, $gate, $company_id, intval($_POST['component_id'] ?? 0), $compData, $uid);
    } else {
        $r = ECS::endComponent($conn, $gate, $company_id, intval($_POST['component_id'] ?? 0), strval($_POST['end_date'] ?? ''), $uid);
    }
    $msg = $r['ok'] ? 'نُفّذ ✅' : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-08-③: قواعدُ الحوافز وتوزيعُها Σ=100 (الحكمُ في الخدمة) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('inc_add', 'inc_end', 'inc_alloc'), true)) {
    if (!$can_edit && !$can_add) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $ctxContract = intval($_POST['contract_id'] ?? 0);
    $act = strval($_POST['do']);
    if ($act === 'inc_add') {
        $r = ECS::addIncentiveRule($conn, $gate, $company_id, $ctxContract, array(
            'incentive_type' => strval($_POST['incentive_type'] ?? ''),
            'basis'          => strval($_POST['basis'] ?? ''),
            'rate'           => strval($_POST['rate'] ?? ''),
            'threshold'      => strval($_POST['threshold'] ?? ''),
            'cap'            => strval($_POST['cap'] ?? ''),
            'floor'          => strval($_POST['floor'] ?? ''),
            'periodicity'    => strval($_POST['periodicity'] ?? 'monthly'),
            'condition_text' => strval($_POST['condition_text'] ?? ''),
            'scope_type'     => strval($_POST['scope_type'] ?? ''),
            'scope_id'       => intval($_POST['scope_id'] ?? 0),
            'valid_from'     => strval($_POST['valid_from'] ?? ''),
            'valid_to'       => strval($_POST['valid_to'] ?? ''),
        ), $uid);
    } elseif ($act === 'inc_end') {
        $r = ECS::endIncentiveRule($conn, $gate, $company_id, intval($_POST['rule_id'] ?? 0), strval($_POST['end_date'] ?? ''), $uid);
    } else {
        // صفوفُ التوزيع تصل مصفوفاتٍ متوازية — تُجمع ثم تُسلَّم دفعةً للذرّية
        $allocRows = array();
        $types = (array) ($_POST['alloc_type'] ?? array());
        $ids   = (array) ($_POST['alloc_id'] ?? array());
        $pcts  = (array) ($_POST['alloc_pct'] ?? array());
        foreach ($types as $i => $bt) {
            if (trim(strval($bt)) === '' && trim(strval($ids[$i] ?? '')) === '') { continue; }
            $allocRows[] = array('beneficiary_type' => strval($bt),
                'beneficiary_id' => intval($ids[$i] ?? 0), 'percent' => strval($pcts[$i] ?? ''));
        }
        $r = ECS::setIncentiveAllocations($conn, $gate, $company_id, intval($_POST['rule_id'] ?? 0), $allocRows, $uid);
    }
    $msg = $r['ok'] ? 'نُفّذ ✅' : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-08-④: جهاتُ التحمّل Σ=100 (رفضُ الحفظ دون المئة — الحكمُ في الخدمة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'bearer_set') {
    if (!$can_edit && !$can_add) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $ctxContract = intval($_POST['contract_id'] ?? 0);
    $bearerRows = array();
    $types = (array) ($_POST['bearer_type'] ?? array());
    $ids   = (array) ($_POST['bearer_id'] ?? array());
    $pcts  = (array) ($_POST['bearer_pct'] ?? array());
    foreach ($types as $i => $bt) {
        if (trim(strval($bt)) === '') { continue; }
        $bearerRows[] = array('bearer_type' => strval($bt),
            'bearer_id' => intval($ids[$i] ?? 0), 'percent' => strval($pcts[$i] ?? ''));
    }
    $r = ECS::setCostBearers($conn, $gate, $company_id,
        strval($_POST['owner_type'] ?? ''), intval($_POST['owner_id'] ?? 0), $bearerRows, $uid);
    $msg = $r['ok'] ? 'نُفّذ ✅' : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-10: الملاحقُ والنسخةُ الموقَّعة (الحكمُ في الخدمة) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('amd_add', 'amd_approve', 'amd_reject', 'sign_attach'), true)) {
    if (!$can_edit && !$can_add) { header("Location: contract_registry.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }
    $ctxContract = intval($_POST['contract_id'] ?? 0);
    $act = strval($_POST['do']);
    if ($act === 'amd_add') {
        // «قبل» يُلتقط في الخدمة من الواقع الحي — الشاشةُ ترسل الحقلَ و«بعد» فقط
        $r = ECAS::createAmendment($conn, $gate, $company_id, $ctxContract, array(
            'amend_type'       => strval($_POST['amend_type'] ?? ''),
            'effective_from'   => strval($_POST['effective_from'] ?? ''),
            'expected_version' => intval($_POST['version'] ?? 0) ?: null,
        ), array(array(
            'field' => strval($_POST['amd_field'] ?? ''),
            'after' => strval($_POST['amd_after'] ?? ''),
        )), $uid);
    } elseif ($act === 'amd_approve') {
        $r = ECAS::approveAmendment($conn, $gate, $company_id, intval($_POST['amendment_id'] ?? 0), $uid);
    } elseif ($act === 'amd_reject') {
        $r = ECAS::rejectAmendment($conn, $gate, $company_id, intval($_POST['amendment_id'] ?? 0), strval($_POST['reason'] ?? ''), $uid);
    } else {
        $r = ECS::attachSignedFile($conn, $gate, $company_id, $ctxContract, strval($_POST['signed_file_ref'] ?? ''), $uid);
    }
    $msg = $r['ok'] ? 'نُفّذ ✅' . (isset($r['snapshot_invalidated_from']) && $r['snapshot_invalidated_from']
                        ? ' (أُبطلت اللقطاتُ من ' . $r['snapshot_invalidated_from'] . ')' : '')
                    : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')');
    header("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
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

// ── H-08-②: عقدٌ معروضٌ بمكوّناته (?contract_id=N) ─────────────────────────
$view_contract = null; $view_components = array(); $view_rules = array(); $view_allocs = array(); $view_bearers = array(); $view_amendments = array();
$vc_id = intval($_GET['contract_id'] ?? 0);
if ($vc_id > 0) {
    try {
        $vrows = $gate->scopedQuery(array(
            'scope'  => array('ec' => 'employee_contracts'),
            'enrich' => array('e' => 'employees'),
        ), "SELECT ec.*, e.name AS employee_name, pm.label_ar AS pay_label
            FROM employee_contracts ec
            LEFT JOIN employees e ON e.id = ec.employee_id
            LEFT JOIN pay_models pm ON pm.id = ec.pay_model_id
            WHERE {TENANT_SCOPE} AND ec.id = ?", array($vc_id));
        $view_contract = $vrows ? $vrows[0] : null;
        if ($view_contract) {
            $view_components = $gate->scopedQuery(array('scope' => array('pc' => 'pay_components')),
                "SELECT pc.* FROM pay_components pc
                 WHERE {TENANT_SCOPE} AND pc.contract_id = ? AND COALESCE(pc.is_deleted,0)=0
                 ORDER BY pc.state = 'active' DESC, pc.id", array($vc_id));
            // H-08-③: القواعدُ بتوزيعها (التوزيعُ ابنُ قاعدته — يُقرأ معها)
            $view_rules = $gate->scopedQuery(array('scope' => array('ir' => 'incentive_rules')),
                "SELECT ir.* FROM incentive_rules ir
                 WHERE {TENANT_SCOPE} AND ir.contract_id = ? AND COALESCE(ir.is_deleted,0)=0
                 ORDER BY ir.state = 'active' DESC, ir.id", array($vc_id));
            $view_allocs = array();
            foreach ($view_rules as $vr) {
                $view_allocs[intval($vr['id'])] = $gate->scopedQuery(array('scope' => array('ia' => 'incentive_allocations')),
                    "SELECT ia.* FROM incentive_allocations ia
                     WHERE {TENANT_SCOPE} AND ia.rule_id = ? ORDER BY ia.percent DESC", array(intval($vr['id'])));
            }
            // H-10: ملاحقُ العقد
            $view_amendments = $gate->scopedQuery(array('scope' => array('a' => 'employee_contract_amendments')),
                "SELECT a.* FROM employee_contract_amendments a
                 WHERE {TENANT_SCOPE} AND a.contract_id = ? AND COALESCE(a.is_deleted,0)=0
                 ORDER BY a.effective_from DESC, a.id DESC", array($vc_id));
            // H-08-④: جهاتُ التحمّل لكل مالكٍ (مكوّنٍ وقاعدة)
            $view_bearers = array();
            foreach ($view_components as $pc0) {
                $view_bearers['component#' . intval($pc0['id'])] = ECS::costBearersOf($gate, 'component', intval($pc0['id']));
            }
            foreach ($view_rules as $vr0) {
                $view_bearers['rule#' . intval($vr0['id'])] = ECS::costBearersOf($gate, 'rule', intval($vr0['id']));
            }
        }
    } catch (\Throwable $t) { $view_contract = null; $view_components = array(); }
}
$vc_editable = $view_contract
    && trim(strval($view_contract['source_table'] ?? '')) === ''
    && in_array(strval($view_contract['state']), ECS::EDITABLE_STATES, true)
    && ($can_add || $can_edit);

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

// H-08-④: خليةُ التحمّل لمالكٍ — شاراتُ الجهات + نموذجُ الحفظ الدفعي (Σ=100)
$bearerCell = function ($ownerType, $ownerId) use (&$view_bearers, &$vc_editable, &$view_contract) {
    $list = $view_bearers[$ownerType . '#' . $ownerId] ?? array();
    if (!$list) {
        echo '<span class="badge badge-secondary" title="الغائبُ = إشارةُ المالك المفردة أو جهةُ العقد">—</span> ';
    } else {
        foreach ($list as $cb) {
            echo '<span class="badge badge-warning">'
                . htmlspecialchars(ECS::COST_BEARER_TYPES[$cb['bearer_type']] ?? $cb['bearer_type'])
                . ($cb['bearer_id'] !== null ? ' #' . intval($cb['bearer_id']) : '')
                . ' · ' . htmlspecialchars($cb['percent']) . '٪</span> ';
        }
    }
    if ($vc_editable) { ?>
        <details style="display:inline-block">
            <summary style="cursor:pointer" class="action-btn edit" title="جهات التحمّل">تحمّل</summary>
            <form method="post" style="margin-top:6px">
                <input type="hidden" name="do" value="bearer_set">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <input type="hidden" name="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
                <input type="hidden" name="owner_id" value="<?php echo intval($ownerId); ?>">
                <?php for ($bi = 0; $bi < 3; $bi++): ?>
                <div style="display:flex;gap:4px;margin-bottom:4px">
                    <select name="bearer_type[]">
                        <option value="">—</option>
                        <?php foreach (ECS::COST_BEARER_TYPES as $bk => $bl): ?>
                            <option value="<?php echo $bk; ?>"><?php echo $bl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="bearer_id[]" placeholder="المعرّف" style="width:70px">
                    <input type="number" step="0.01" name="bearer_pct[]" placeholder="٪" style="width:60px">
                </div>
                <?php endfor; ?>
                <button type="submit" class="btn-save">حفظ التحمّل (Σ=100)</button>
            </form>
        </details>
    <?php }
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

    <?php if ($view_contract): ?>
    <div class="card">
        <div class="card-header"><h5><i class="fa fa-puzzle-piece"></i>
            مكوّناتُ أجر العقد #<?php echo intval($view_contract['id']); ?> —
            <?php echo htmlspecialchars($view_contract['employee_name'] ?? ''); ?>
            <?php echo $stateChip(strval($view_contract['state'])); ?>
            <?php if (trim(strval($view_contract['source_table'] ?? '')) !== ''): ?>
                <span class="badge badge-light">مرحَّل قراءةً — مكوّناتُه في مصدره القديم</span>
            <?php elseif (!$vc_editable): ?>
                <span class="badge badge-info">التغييرُ على النافذ بملحق (H-10)</span>
            <?php endif; ?>
            <a href="contract_registry.php" style="float:left">✕ إغلاق</a>
        </h5></div>
        <div class="card-body">
            <div class="table-container">
                <table class="alltables display no-datatable" style="width:100%">
                    <thead><tr>
                        <th>#</th><th>نوع العقد</th><th>الطريقة</th><th>المبلغ</th><th>المعدل</th>
                        <th>الأعلام السبعة</th><th>الدورية</th><th>السريان</th><th>التحمّل</th><th>الحالة</th>
                        <?php if ($vc_editable): ?><th>إنهاء</th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php $FLAG_LBL = array('in_insurance' => 'تأمينات', 'in_tax' => 'ضريبة', 'in_leave_pay' => 'إجازة',
                          'in_eos' => 'نهاية خدمة', 'in_hour_base' => 'ساعة', 'in_overtime' => 'إضافي', 'in_incentive_base' => 'وعاء حافز');
                    foreach ($view_components as $pc): ?>
                        <tr>
                            <td><?php echo intval($pc['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars(ECS::COMPONENT_TYPES[$pc['component_type']] ?? $pc['component_type']); ?></strong></td>
                            <td><?php echo htmlspecialchars(ECS::CALC_METHODS[$pc['calc_method']] ?? $pc['calc_method']); ?></td>
                            <td><?php echo $pc['value'] !== null ? htmlspecialchars($pc['value']) : '—'; ?></td>
                            <td><?php echo $pc['rate'] !== null ? htmlspecialchars($pc['rate']) : '—'; ?></td>
                            <td><?php
                                $on = array();
                                foreach ($FLAG_LBL as $fk => $fl) { if (intval($pc[$fk]) === 1) { $on[] = $fl; } }
                                echo $on ? htmlspecialchars(implode(' · ', $on)) : '—';
                            ?></td>
                            <td><?php echo array('monthly' => 'شهري', 'periodic' => 'دوري', 'once' => 'لمرة')[$pc['periodicity']] ?? $pc['periodicity']; ?></td>
                            <td><?php echo htmlspecialchars(($pc['valid_from'] ?: '؟') . ' → ' . ($pc['valid_to'] ?: 'مفتوح')); ?></td>
                            <td><?php $bearerCell('component', intval($pc['id'])); ?></td>
                            <td><?php echo array('active' => "<span class='badge badge-success'>ساري</span>",
                                                 'replaced' => "<span class='badge badge-secondary'>مُستبدَل</span>",
                                                 'ended' => "<span class='badge badge-dark'>منتهٍ</span>")[$pc['state']] ?? htmlspecialchars($pc['state']); ?></td>
                            <?php if ($vc_editable): ?>
                            <td>
                                <?php if ($pc['state'] === 'active'): ?>
                                <form method="post" style="display:inline-flex;gap:4px">
                                    <input type="hidden" name="do" value="comp_end">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="component_id" value="<?php echo intval($pc['id']); ?>">
                                    <input type="date" name="end_date" required>
                                    <button type="submit" class="action-btn" title="إنهاء"><i class="fas fa-stop"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$view_components): ?>
                        <tr><td colspan="11">لا مكوّناتَ بعد<?php echo $vc_editable ? ' — أضف أولَها أدناه' : ''; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($vc_editable): ?>
            <form method="post" class="allforms allforms-visible" style="margin-top:14px">
                <input type="hidden" name="do" value="comp_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>النوع <span style="color:#c00">*</span></label>
                        <select name="component_type" required>
                            <?php foreach (ECS::COMPONENT_TYPES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>طريقة الاحتساب <span style="color:#c00">*</span></label>
                        <select name="calc_method" required>
                            <?php foreach (ECS::CALC_METHODS as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>المبلغ (للمبلغ الثابت)</label><input type="number" step="0.01" min="0" name="value"></div>
                    <div class="form-group"><label>المعدل (للنسب وعن-الوحدة)</label><input type="number" step="0.01" name="rate"></div>
                    <div class="form-group">
                        <label>الدورية</label>
                        <select name="periodicity">
                            <option value="monthly">شهري</option><option value="periodic">دوري</option><option value="once">لمرة</option>
                        </select>
                    </div>
                    <div class="form-group"><label>سريان من</label><input type="date" name="valid_from"></div>
                    <div class="form-group"><label>سريان إلى</label><input type="date" name="valid_to"></div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>يدخل في:</label>
                        <?php foreach ($FLAG_LBL as $fk => $fl): ?>
                            <label style="margin-inline-end:12px;font-weight:normal">
                                <input type="checkbox" name="<?php echo $fk; ?>" value="1"> <?php echo $fl; ?>
                            </label>
                        <?php endforeach; ?>
                        <label style="margin-inline-end:12px;font-weight:normal">
                            <input type="checkbox" name="is_variable" value="1"> متغيّر
                        </label>
                    </div>
                </div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fa fa-plus"></i> إضافة مكوّن</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ── H-08-③: قواعدُ الحوافز وتوزيعُها Σ=100 (تدمج M-23) ── -->
            <h6 style="margin-top:18px"><i class="fa fa-bullseye"></i> قواعدُ الحوافز — التوزيعُ بمجموع 100٪ قيدًا</h6>
            <div class="table-container">
                <table class="alltables display no-datatable" style="width:100%">
                    <thead><tr>
                        <th>#</th><th>الحافز</th><th>الأساس</th><th>المعدل</th><th>العتبة</th>
                        <th>السقف/الأدنى</th><th>التوزيع</th><th>التحمّل</th><th>السريان</th><th>الحالة</th>
                        <?php if ($vc_editable): ?><th>إجراء</th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($view_rules as $vr): $rid = intval($vr['id']); ?>
                        <tr>
                            <td><?php echo $rid; ?></td>
                            <td><strong><?php echo htmlspecialchars($vr['incentive_type']); ?></strong></td>
                            <td><?php echo htmlspecialchars(ECS::INCENTIVE_BASES[$vr['basis']] ?? $vr['basis']); ?></td>
                            <td><?php echo $vr['rate'] !== null ? htmlspecialchars($vr['rate']) : '—'; ?></td>
                            <td><?php echo $vr['threshold'] !== null ? htmlspecialchars($vr['threshold']) : '—'; ?></td>
                            <td><?php echo ($vr['cap'] !== null ? htmlspecialchars($vr['cap']) : '—') . ' / '
                                          . ($vr['floor'] !== null ? htmlspecialchars($vr['floor']) : '—'); ?></td>
                            <td>
                                <?php $als = $view_allocs[$rid] ?? array();
                                if (!$als) { echo '<span class="badge badge-secondary" title="التوزيعُ الغائب = كلُّه لصاحب العقد">100٪ لصاحب العقد</span>'; }
                                else { foreach ($als as $al) {
                                    echo '<span class="badge badge-info">'
                                        . ($al['beneficiary_type'] === 'employee' ? 'موظف' : 'مسمًّى') . ' #'
                                        . intval($al['beneficiary_id']) . ' · ' . htmlspecialchars($al['percent']) . '٪</span> ';
                                } } ?>
                            </td>
                            <td><?php $bearerCell('rule', $rid); ?></td>
                            <td><?php echo htmlspecialchars(($vr['valid_from'] ?: '؟') . ' → ' . ($vr['valid_to'] ?: 'مفتوح')); ?></td>
                            <td><?php echo array('active' => "<span class='badge badge-success'>ساري</span>",
                                                 'replaced' => "<span class='badge badge-secondary'>مُستبدَل</span>",
                                                 'ended' => "<span class='badge badge-dark'>منتهٍ</span>")[$vr['state']] ?? htmlspecialchars($vr['state']); ?></td>
                            <?php if ($vc_editable): ?>
                            <td>
                                <?php if ($vr['state'] === 'active'): ?>
                                <details style="display:inline-block">
                                    <summary class="action-btn edit" style="cursor:pointer" title="توزيع">توزيع</summary>
                                    <form method="post" style="margin-top:6px">
                                        <input type="hidden" name="do" value="inc_alloc">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                        <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                        <?php for ($ai = 0; $ai < 3; $ai++): ?>
                                        <div style="display:flex;gap:4px;margin-bottom:4px">
                                            <select name="alloc_type[]">
                                                <option value="">—</option>
                                                <option value="employee">موظف</option>
                                                <option value="job_title">مسمًّى وظيفي</option>
                                            </select>
                                            <input type="number" name="alloc_id[]" placeholder="المعرّف" style="width:70px">
                                            <input type="number" step="0.01" name="alloc_pct[]" placeholder="٪" style="width:60px">
                                        </div>
                                        <?php endfor; ?>
                                        <button type="submit" class="btn-save">حفظ التوزيع (Σ=100)</button>
                                    </form>
                                </details>
                                <form method="post" style="display:inline-flex;gap:4px">
                                    <input type="hidden" name="do" value="inc_end">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                    <input type="date" name="end_date" required>
                                    <button type="submit" class="action-btn" title="إنهاء"><i class="fas fa-stop"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$view_rules): ?>
                        <tr><td colspan="11">لا قواعدَ حوافزَ بعد</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($vc_editable): ?>
            <form method="post" class="allforms allforms-visible" style="margin-top:10px">
                <input type="hidden" name="do" value="inc_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>اسم الحافز <span style="color:#c00">*</span></label>
                        <input type="text" name="incentive_type" required maxlength="50" placeholder="مثال: حافز إنتاج الطن"></div>
                    <div class="form-group"><label>الأساس <span style="color:#c00">*</span></label>
                        <select name="basis" required>
                            <?php foreach (ECS::INCENTIVE_BASES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>المعدل</label><input type="number" step="0.0001" min="0" name="rate"></div>
                    <div class="form-group"><label>العتبة</label><input type="number" step="0.01" name="threshold"></div>
                    <div class="form-group"><label>السقف</label><input type="number" step="0.01" name="cap"></div>
                    <div class="form-group"><label>الحد الأدنى</label><input type="number" step="0.01" name="floor"></div>
                    <div class="form-group"><label>الدورية</label>
                        <select name="periodicity">
                            <option value="monthly">شهري</option><option value="periodic">دوري</option><option value="once">لمرة</option>
                        </select></div>
                    <div class="form-group"><label>شرط الاستحقاق</label><input type="text" name="condition_text" maxlength="255"></div>
                    <div class="form-group"><label>النطاق</label>
                        <select name="scope_type">
                            <option value="">— عام —</option>
                            <option value="project">مشروع</option>
                            <option value="equipment_type">نوع معدة</option>
                            <option value="site">موقع</option>
                        </select></div>
                    <div class="form-group"><label>معرّف النطاق</label><input type="number" name="scope_id"></div>
                    <div class="form-group"><label>سريان من</label><input type="date" name="valid_from"></div>
                    <div class="form-group"><label>سريان إلى</label><input type="date" name="valid_to"></div>
                </div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fa fa-plus"></i> إضافة قاعدة حافز</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ── H-10: الملاحقُ والنسخةُ الموقَّعة المقفلة ── -->
            <h6 style="margin-top:18px"><i class="fa fa-file-medical"></i> الملاحق — «لا تعديلَ مباشرًا على نافذ؛ كلُّ تغييرٍ ملحقٌ بسريان»</h6>
            <?php $vcState = strval($view_contract['state']);
                  $isAmendable = in_array($vcState, ECAS::AMENDABLE, true)
                      && trim(strval($view_contract['source_table'] ?? '')) === '' && ($can_add || $can_edit); ?>
            <div class="table-container">
                <table class="alltables display no-datatable" style="width:100%">
                    <thead><tr>
                        <th>#</th><th>نوع الطرف</th><th>السريان</th><th>قبل ← بعد</th><th>الحالة</th><th>المعتمِد — الاسم والصفة</th>
                        <?php if ($can_edit): ?><th>إجراء</th><?php endif; ?>
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">رقم العقد</th>
                        <th class="ems-fn-th" data-fn="1">الطرف الآخر</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ التوقيع</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ البدء</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ الانتهاء</th>
                        <th class="ems-fn-th" data-fn="1">القيمة</th>
                        <th class="ems-fn-th" data-fn="1">الالتزام القائم</th>
                        <th class="ems-fn-th" data-fn="1">حالة الكفالة</th>
                        <th class="ems-fn-th" data-fn="1">المفوَّض بالتوقيع</th>
                        <th class="ems-fn-th" data-fn="1">الإدارة المالكة</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                        <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                        <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                        <th class="ems-gov-th none" data-gov="attached_doc" data-slice="3" title="مستند الإثبات المرفق">المستند المرفق</th>
                        </tr></thead>
                    <tbody>
                    <?php foreach ($view_amendments as $am): $aid = intval($am['id']);
                        $chs = json_decode(strval($am['changes_json']), true) ?: array(); ?>
                        <tr>
                            <td><?php echo $aid; ?></td>
                            <td><?php echo htmlspecialchars(ECAS::AMEND_TYPES[$am['amend_type']] ?? $am['amend_type']); ?></td>
                            <td><?php echo htmlspecialchars($am['effective_from']); ?></td>
                            <td><?php foreach ($chs as $ch) {
                                echo '<div><code>' . htmlspecialchars($ch['field']) . '</code>: '
                                    . htmlspecialchars(strval($ch['before'] ?? '—')) . ' ← <strong>'
                                    . htmlspecialchars(strval($ch['after'] ?? '—')) . '</strong></div>';
                            } ?></td>
                            <td><?php echo array('draft' => "<span class='badge badge-info'>مسودة</span>",
                                                 'approved' => "<span class='badge badge-success'>معتمد</span>",
                                                 'rejected' => "<span class='badge badge-danger' title='" . htmlspecialchars(strval($am['reject_reason'] ?? ''), ENT_QUOTES) . "'>مرفوض</span>")[$am['state']] ?? htmlspecialchars($am['state']); ?></td>
                            <td><?php echo $am['approved_by'] ? '#' . intval($am['approved_by']) : '—'; ?></td>
                            <?php if ($can_edit): ?>
                            <td>
                                <?php if ($am['state'] === 'draft'): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="do" value="amd_approve">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="amendment_id" value="<?php echo $aid; ?>">
                                    <button type="submit" class="action-btn edit" title="اعتماد (لا اعتمادَ لمن أنشأ)"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" style="display:inline-flex;gap:4px">
                                    <input type="hidden" name="do" value="amd_reject">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="amendment_id" value="<?php echo $aid; ?>">
                                    <input type="text" name="reason" placeholder="سبب الرفض (إلزامي)" style="width:120px">
                                    <button type="submit" class="action-btn" title="رفض"><i class="fas fa-times"></i></button>
                                </form>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$view_amendments): ?>
                        <tr><td colspan="7">لا ملاحقَ بعد</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($isAmendable): ?>
            <form method="post" class="allforms allforms-visible" style="margin-top:10px">
                <input type="hidden" name="do" value="amd_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <input type="hidden" name="version" value="<?php echo intval($view_contract['version']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>نوع الملحق <span style="color:#c00">*</span></label>
                        <select name="amend_type" required>
                            <?php foreach (ECAS::AMEND_TYPES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                        <input type="date" name="effective_from" required></div>
                    <div class="form-group"><label>الحقل المستهدف <span style="color:#c00">*</span>
                        <small>(head:end_date · component:ID:value · rule:ID:rate …)</small></label>
                        <input type="text" name="amd_field" required placeholder="head:end_date"></div>
                    <div class="form-group"><label>القيمة بعد («قبل» يُلتقط من الواقع)</label>
                        <input type="text" name="amd_after"></div>
                </div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fa fa-plus"></i> إنشاء ملحق (مسودة)</button>
                </div>
            </form>
            <?php elseif (trim(strval($view_contract['source_table'] ?? '')) === ''
                          && $vcState === ECSM::ACCEPTED && ($can_add || $can_edit)): ?>
            <!-- النسخةُ الموقَّعة — شرطُ Accepted → Signed · «ثابتةٌ لا تُستبدل» -->
            <form method="post" class="allforms allforms-visible" style="margin-top:10px">
                <input type="hidden" name="do" value="sign_attach">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>مرجع النسخة الموقَّعة <span style="color:#c00">*</span>
                        <small>(ثابتةٌ لا تُستبدل — التصحيحُ ملحقٌ يوضّح)</small></label>
                        <input type="text" name="signed_file_ref" required maxlength="255" placeholder="uploads/contracts/....pdf"></div>
                </div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fa fa-file-signature"></i> تثبيت النسخة الموقَّعة</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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
                        <td><a href="?contract_id=<?php echo intval($r['id']); ?>" title="مكوّنات الأجر (H-08-②)"><?php echo intval($r['id']); ?></a></td>
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
