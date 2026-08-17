<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-750 · SCN-751 · SCN-752
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض سجل العقود ❌', 'GOV-PERM-403', '');
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
    if (!$can_add) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_flash_redirect('contract_registry.php', $msg, 'GOV-INFO-200', ''); exit();
}

// ── انتقالُ حالةٍ / تعليقٌ-إعارةٌ / استئناف ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('transition', 'hold', 'resume'), true)) {
    if (!$can_edit) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_flash_redirect('contract_registry.php', $msg, 'GOV-INFO-200', ''); exit();
}

// ── H-08-②: مكوّناتُ الأجر (إضافةٌ/تعديلٌ/إنهاء — الحكمُ في الخدمة) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('comp_add', 'comp_update', 'comp_end'), true)) {
    if (!$can_edit && !$can_add) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_redirect("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-08-③: قواعدُ الحوافز وتوزيعُها Σ=100 (الحكمُ في الخدمة) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('inc_add', 'inc_end', 'inc_alloc'), true)) {
    if (!$can_edit && !$can_add) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_redirect("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-08-④: جهاتُ التحمّل Σ=100 (رفضُ الحفظ دون المئة — الحكمُ في الخدمة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'bearer_set') {
    if (!$can_edit && !$can_add) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_redirect("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
}

// ── H-10: الملاحقُ والنسخةُ الموقَّعة (الحكمُ في الخدمة) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['do'] ?? ''), array('amd_add', 'amd_approve', 'amd_reject', 'sign_attach'), true)) {
    if (!$can_edit && !$can_add) { ems_gov_flash_redirect('contract_registry.php', 'لا توجد صلاحية لهذا الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
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
    ems_gov_redirect("Location: contract_registry.php?contract_id={$ctxContract}&msg=" . rawurlencode($msg)); exit();
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
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('employee', 'العقود');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

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
        <details class="cr-inline-block">
            <summary class="action-btn edit cr-pointer" title="جهات التحمّل">تحمّل</summary>
            <form method="post" class="cr-mt6">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="bearer_set">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <input type="hidden" name="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
                <input type="hidden" name="owner_id" value="<?php echo intval($ownerId); ?>">
                <?php for ($bi = 0; $bi < 3; $bi++): ?>
                <div class="cr-flex-row">
                    <select name="bearer_type[]" aria-label="جهة التحمّل">
                        <option value="">—</option>
                        <?php foreach (ECS::COST_BEARER_TYPES as $bk => $bl): ?>
                            <option value="<?php echo $bk; ?>"><?php echo $bl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="bearer_id[]" placeholder="المعرّف" class="cr-w70" aria-label="المعرّف">
                    <input type="number" step="0.01" name="bearer_pct[]" placeholder="٪" class="cr-w60" aria-label="٪">
                </div>
                <?php endfor; ?>
                <button type="submit" class="btn-primary">حفظ التحمّل (Σ=100)</button>
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
    require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقودَ في سجلِّ العقودِ الموحَّدِ ضمن هذا النطاق', 'أنشئ رأسَ عقدٍ بزرِّ «رأس عقد جديد» أو وسِّع الفلاتر');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

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
            <a href="contract_registry.php" class="cr-close-link">✕ إغلاق</a>
        </h5></div>
        <div class="card-body">
            <div class="table-container">
                <table class="alltables display no-datatable cr-table-full" data-no-dt="hard">
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
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="comp_end">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="component_id" value="<?php echo intval($pc['id']); ?>">
                                    <input type="date" name="end_date" aria-label="تاريخ الإنهاء" required>
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
            <form method="post" class="allforms allforms-visible cr-mt14">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="comp_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emsf_1617_ebd93">النوع <span class="cr-required">*</span></label>
                        <select name="component_type" required id="emsf_1617_ebd93">
                            <?php foreach (ECS::COMPONENT_TYPES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_1618_52334">طريقة الاحتساب <span class="cr-required">*</span></label>
                        <select name="calc_method" required id="emsf_1618_52334">
                            <?php foreach (ECS::CALC_METHODS as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="emsf_1619_37074">المبلغ (للمبلغ الثابت)</label><input type="number" step="0.01" min="0" name="value" id="emsf_1619_37074"></div>
                    <div class="form-group"><label for="emsf_1620_c47db">المعدل (للنسب وعن-الوحدة)</label><input type="number" step="0.01" name="rate" id="emsf_1620_c47db"></div>
                    <div class="form-group">
                        <label for="emsf_1621_7be79">الدورية</label>
                        <select name="periodicity" id="emsf_1621_7be79">
                            <option value="monthly">شهري</option><option value="periodic">دوري</option><option value="once">لمرة</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="emsf_1622_e5161">سريان من</label><input type="date" name="valid_from" id="emsf_1622_e5161"></div>
                    <div class="form-group"><label for="emsf_1623_26ebc">سريان إلى</label><input type="date" name="valid_to" id="emsf_1623_26ebc"></div>
                    <div class="form-group cr-span-full">
                        <label for="emsf_1624_1402c">يدخل في:</label>
                        <?php foreach ($FLAG_LBL as $fk => $fl): ?>
                            <label class="cr-flag-label">
                                <input type="checkbox" aria-label="<?php echo $fl; ?>" name="<?php echo $fk; ?>" value="1"> <?php echo $fl; ?>
                            </label>
                        <?php endforeach; ?>
                        <label class="cr-flag-label">
                            <input type="checkbox" aria-label="متغيّر" name="is_variable" value="1"> متغيّر
                        </label>
                    </div>
                </div>
                <div class="cr-mt10">
                    <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> إضافة مكوّن</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ── H-08-③: قواعدُ الحوافز وتوزيعُها Σ=100 (تدمج M-23) ── -->
            <h6 class="cr-mt18"><i class="fa fa-bullseye"></i> قواعدُ الحوافز — التوزيعُ بمجموع 100٪ قيدًا</h6>
            <div class="table-container">
                <table class="alltables display no-datatable cr-table-full" data-no-dt="hard">
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
                                <details class="cr-inline-block">
                                    <summary class="action-btn edit cr-pointer" title="توزيع">توزيع</summary>
                                    <form method="post" class="cr-mt6">
        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="inc_alloc">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                        <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                        <?php for ($ai = 0; $ai < 3; $ai++): ?>
                                        <div class="cr-flex-row">
                                            <select name="alloc_type[]" aria-label="نوع المستفيد">
                                                <option value="">—</option>
                                                <option value="employee">موظف</option>
                                                <option value="job_title">مسمًّى وظيفي</option>
                                            </select>
                                            <input type="number" name="alloc_id[]" placeholder="المعرّف" class="cr-w70">
                                            <input type="number" step="0.01" name="alloc_pct[]" placeholder="٪" class="cr-w60">
                                        </div>
                                        <?php endfor; ?>
                                        <button type="submit" class="btn-primary">حفظ التوزيع (Σ=100)</button>
                                    </form>
                                </details>
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="inc_end">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                    <input type="date" name="end_date" aria-label="تاريخ الإنهاء" required>
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
            <form method="post" class="allforms allforms-visible cr-mt10">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="inc_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>اسم الحافز <span class="cr-required">*</span></label>
                        <?php foreach ($FLAG_LBL as $fk => $fl): ?>
                            <label class="cr-flag-label">
                                <input type="checkbox" aria-label="<?php echo $fl; ?>" name="<?php echo $fk; ?>" value="1"> <?php echo $fl; ?>
                            </label>
                        <?php endforeach; ?>
                        <label class="cr-flag-label">
                            <input type="checkbox" aria-label="متغيّر" name="is_variable" value="1"> متغيّر
                        </label>
                    </div>
                </div>
                <div class="cr-mt10">
                    <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> إضافة مكوّن</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ── H-08-③: قواعدُ الحوافز وتوزيعُها Σ=100 (تدمج M-23) ── -->
            <h6 class="cr-mt18"><i class="fa fa-bullseye"></i> قواعدُ الحوافز — التوزيعُ بمجموع 100٪ قيدًا</h6>
            <div class="table-container">
                <table class="alltables display no-datatable cr-table-full" data-no-dt="hard">
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
                                <details class="cr-inline-block">
                                    <summary class="action-btn edit cr-pointer" title="توزيع">توزيع</summary>
                                    <form method="post" class="cr-mt6">
        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="inc_alloc">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                        <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                        <?php for ($ai = 0; $ai < 3; $ai++): ?>
                                        <div class="cr-flex-row">
                                            <select name="alloc_type[]" aria-label="نوع المستفيد">
                                                <option value="">—</option>
                                                <option value="employee">موظف</option>
                                                <option value="job_title">مسمًّى وظيفي</option>
                                            </select>
                                            <input type="number" name="alloc_id[]" placeholder="المعرّف" class="cr-w70">
                                            <input type="number" step="0.01" name="alloc_pct[]" placeholder="٪" class="cr-w60">
                                        </div>
                                        <?php endfor; ?>
                                        <button type="submit" class="btn-primary">حفظ التوزيع (Σ=100)</button>
                                    </form>
                                </details>
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="inc_end">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="rule_id" value="<?php echo $rid; ?>">
                                    <input type="date" name="end_date" aria-label="تاريخ الإنهاء" required>
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
            <form method="post" class="allforms allforms-visible cr-mt10">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="inc_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>اسم الحافز <span class="cr-required">*</span></label>
                        <input type="text" name="incentive_type" required maxlength="50" placeholder="مثال: حافز إنتاج الطن" id="emsf_1624_1402c"></div>
                    <div class="form-group"><label for="emsf_1625_a33b5">الأساس <span class="cr-required">*</span></label>
                        <select name="basis" required id="emsf_1625_a33b5">
                            <?php foreach (ECS::INCENTIVE_BASES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="emsf_1626_1a2df">المعدل</label><input type="number" step="0.0001" min="0" name="rate" id="emsf_1626_1a2df"></div>
                    <div class="form-group"><label for="emsf_1627_0cf97">العتبة</label><input type="number" step="0.01" name="threshold" id="emsf_1627_0cf97"></div>
                    <div class="form-group"><label for="emsf_1628_70a20">السقف</label><input type="number" step="0.01" name="cap" id="emsf_1628_70a20"></div>
                    <div class="form-group"><label for="emsf_1629_02ca6">الحد الأدنى</label><input type="number" step="0.01" name="floor" id="emsf_1629_02ca6"></div>
                    <div class="form-group"><label for="emsf_1630_3435e">الدورية</label>
                        <select name="periodicity" id="emsf_1630_3435e">
                            <option value="monthly">شهري</option><option value="periodic">دوري</option><option value="once">لمرة</option>
                        </select></div>
                    <div class="form-group"><label for="emsf_1631_60df1">شرط الاستحقاق</label><input type="text" name="condition_text" maxlength="255" id="emsf_1631_60df1"></div>
                    <div class="form-group"><label for="emsf_1632_16629">النطاق</label>
                        <select name="scope_type" id="emsf_1632_16629">
                            <option value="">— عام —</option>
                            <option value="project">مشروع</option>
                            <option value="equipment_type">نوع معدة</option>
                            <option value="site">موقع</option>
                        </select></div>
                    <div class="form-group"><label for="emsf_1633_94e48">معرّف النطاق</label><input type="number" name="scope_id" id="emsf_1633_94e48"></div>
                    <div class="form-group"><label for="emsf_1634_e529f">سريان من</label><input type="date" name="valid_from" id="emsf_1634_e529f"></div>
                    <div class="form-group"><label for="emsf_1635_a81ce">سريان إلى</label><input type="date" name="valid_to" id="emsf_1635_a81ce"></div>
                </div>
                <div class="cr-mt10">
                    <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> إضافة قاعدة حافز</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ── H-10: الملاحقُ والنسخةُ الموقَّعة المقفلة ── -->
            <h6 class="cr-mt18"><i class="fa fa-file-medical"></i> الملاحق — «لا تعديلَ مباشرًا على نافذ؛ كلُّ تغييرٍ ملحقٌ بسريان»</h6>
            <?php $vcState = strval($view_contract['state']);
                  $isAmendable = in_array($vcState, ECAS::AMENDABLE, true)
                      && trim(strval($view_contract['source_table'] ?? '')) === '' && ($can_add || $can_edit); ?>
            <div class="table-container">
                <table class="alltables display no-datatable cr-table-full" data-no-dt="hard">
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
                                <form method="post" class="cr-inline">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="amd_approve">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="amendment_id" value="<?php echo $aid; ?>">
                                    <button type="submit" class="action-btn edit" title="اعتماد (لا اعتمادَ لمن أنشأ)"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="amd_reject">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                                    <input type="hidden" name="amendment_id" value="<?php echo $aid; ?>">
                                    <input type="text" name="reason" placeholder="سبب الرفض (إلزامي)" class="cr-w120" aria-label="سبب الرفض (إلزامي)">
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
            <form method="post" class="allforms allforms-visible cr-mt10">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="amd_add">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <input type="hidden" name="version" value="<?php echo intval($view_contract['version']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label for="emsf_1636_ce6c7">نوع الملحق <span class="cr-required">*</span></label>
                        <select name="amend_type" required id="emsf_1636_ce6c7">
                            <?php foreach (ECAS::AMEND_TYPES as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="emsf_1637_3c05b">سريان من <span class="cr-required">*</span></label>
                        <input type="date" name="effective_from" required id="emsf_1637_3c05b"></div>
                    <div class="form-group"><label for="emsf_1638_43c95">الحقل المستهدف <span class="cr-required">*</span>
                        <small>(head:end_date · component:ID:value · rule:ID:rate …)</small></label>
                        <input type="text" name="amd_field" required placeholder="head:end_date" id="emsf_1638_43c95"></div>
                    <div class="form-group"><label for="emsf_1639_9303d">القيمة بعد («قبل» يُلتقط من الواقع)</label>
                        <input type="text" name="amd_after" id="emsf_1639_9303d"></div>
                </div>
                <div class="cr-mt10">
                    <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> إنشاء ملحق (مسودة)</button>
                </div>
            </form>
            <?php elseif (trim(strval($view_contract['source_table'] ?? '')) === ''
                          && $vcState === ECSM::ACCEPTED && ($can_add || $can_edit)): ?>
            <!-- النسخةُ الموقَّعة — شرطُ Accepted → Signed · «ثابتةٌ لا تُستبدل» -->
            <form method="post" class="allforms allforms-visible cr-mt10">
        <?= csrf_field() ?>
                <input type="hidden" name="do" value="sign_attach">
                <input type="hidden" name="contract_id" value="<?php echo intval($view_contract['id']); ?>">
                <div class="form-grid">
                    <div class="form-group"><label for="emsf_1640_5f6ab">مرجع النسخة الموقَّعة <span class="cr-required">*</span>
                        <small>(ثابتةٌ لا تُستبدل — التصحيحُ ملحقٌ يوضّح)</small></label>
                        <input type="text" name="signed_file_ref" required maxlength="255" placeholder="uploads/contracts/....pdf" id="emsf_1640_5f6ab"></div>
                </div>
                <div class="cr-mt10">
                    <button type="submit" class="btn-primary"><i class="fa fa-file-signature"></i> تثبيت النسخة الموقَّعة</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="headForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create">
        <div class="card"><div class="card-header"><h5><i class="fa fa-file-signature"></i> رأسُ عقدٍ جديد (مسودة — المكوّناتُ والحوافزُ والتحمّل مع الشرائح التالية)</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label for="emsf_1641_fccec">الشخص <span class="cr-required">*</span></label>
                <select name="employee_id" required id="emsf_1641_fccec">
                    <option value="">— اختر —</option>
                    <?php foreach ($employees_options as $e): ?>
                        <option value="<?php echo intval($e['id']); ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1642_e2a3b">الفئة <span class="cr-required">*</span></label>
                <select name="category" required id="emsf_1642_e2a3b">
                    <?php foreach ($CATEGORIES as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1643_71877">نموذج الأجر <span class="cr-required">*</span> <small>(اختيارٌ مستقلٌّ لا يُشتق من الوظيفة)</small></label>
                <select name="pay_model_id" required id="emsf_1643_71877">
                    <option value="">— من القائمة الخمس عشرة —</option>
                    <?php foreach ($pay_models as $pm): ?>
                        <option value="<?php echo intval($pm['id']); ?>"><?php echo htmlspecialchars($pm['label_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1644_a3194">المشروع (لفئة «مشروع»)</label>
                <select name="project_id" id="emsf_1644_a3194">
                    <option value="">— بلا —</option>
                    <?php foreach ($projects_options as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="emsf_1645_ca118">بداية المدة</label><input type="date" name="start_date" id="emsf_1645_ca118"></div>
            <div class="form-group"><label for="emsf_1646_58223">نهاية المدة</label><input type="date" name="end_date" id="emsf_1646_58223"></div>
            <div class="form-group"><label for="emsf_1647_01afd">نهاية التجربة</label><input type="date" name="probation_end" id="emsf_1647_01afd"></div>
            <div class="form-group"><label for="emsf_1648_7b2f2">طبيعة الارتباط</label><input type="text" name="relation_type" maxlength="50" id="emsf_1648_7b2f2"></div>
            <div class="form-group"><label for="emsf_1649_bce68">العملة</label><input type="text" name="currency" maxlength="8" placeholder="SDG" id="emsf_1649_bce68"></div>
        </div>
        <div class="cr-mt10">
            <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ مسودة</button>
        </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
                <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
        <form method="get" class="cr-filter-form">
            <strong>الفئة:</strong>
            <select name="category" aria-label="تصفية بفئة العقد" onchange="this.form.submit()">
                <option value="">الكل</option>
                <?php foreach ($CATEGORIES as $k => $lbl): ?>
                    <option value="<?php echo $k; ?>" <?php echo $f_category === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
            <strong>الحالة:</strong>
            <select name="state" aria-label="تصفية بحالة العقد" onchange="this.form.submit()">
                <option value="">الكل</option>
                <?php foreach (ECSM::ALL as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $f_state === $s ? 'selected' : ''; ?>><?php echo ECSM::labelAr($s); ?></option>
                <?php endforeach; ?>
            </select>
            <strong>المشروع:</strong>
            <select name="project_id" aria-label="تصفية بالمشروع" onchange="this.form.submit()">
                <option value="0">الكل</option>
                <?php foreach ($projects_options as $p): ?>
                    <option value="<?php echo intval($p['id']); ?>" <?php echo $f_project === intval($p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
            </div>
        </div>
        <div class="table-container">
            <!-- فلاترُ الشاشة خارجيةٌ (GET) — التهيئةُ للمكوّنِ المركزيِّ (ui-unification)
                 مع تعطيلِ حفظِ الحالةِ بسمةِ data-state-save
                 (درسُ tn/18 والفلاتر الخارجية: حالةٌ محفوظةٌ قديمةٌ تُخفي الصفوف N→0) -->
            <table class="alltables display nowrap cr-table-full" id="contractRegistryTable"
                   data-state-save="false" data-order="[]">
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
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="transition">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="version" value="<?php echo intval($r['version']); ?>">
                                    <select name="to_state" aria-label="الحالة الهدف للانتقال">
                                        <?php foreach ($allowed as $to): ?>
                                            <option value="<?php echo $to; ?>">← <?php echo ECSM::labelAr($to); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="note" placeholder="ملاحظة" class="cr-w90" aria-label="ملاحظة">
                                    <button type="submit" class="action-btn edit" title="نقل الحالة"><i class="fas fa-arrow-left"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($state, ECSM::HOLDABLE, true)): ?>
                                <form method="post" class="cr-inline-flex">
        <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="hold">
                                    <input type="hidden" name="contract_id" value="<?php echo intval($r['id']); ?>">
                                    <select name="hold_kind" aria-label="نوع الإيقاف: تعليق أو إعارة">
                                        <option value="suspended">تعليق</option>
                                        <option value="seconded">إعارة</option>
                                    </select>
                                    <input type="text" name="note" placeholder="السبب (إلزامي)" class="cr-w110" aria-label="السبب (إلزامي)">
                                    <button type="submit" class="action-btn" title="تعليق/إعارة"><i class="fas fa-pause"></i></button>
                                </form>
                                <?php elseif ($state === ECSM::SUSPENDED || $state === ECSM::SECONDED): ?>
                                <form method="post" class="cr-inline">
        <?= csrf_field() ?>
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
        // بوابة ٥: لا تهيئةَ محليةً — المكوّنُ المركزيُّ (ui-unification) يهيّئ الجدولَ
        // ويقرأ data-state-save="false" وdata-order="[]" من وسمِه (الفلاتر خارجية GET).
    });
})();
</script>
</body>
</html>
