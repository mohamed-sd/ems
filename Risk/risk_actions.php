<?php
/**
 * Risk/risk_actions.php — معالج أفعال M-16 الموحد (AJAX/POST)
 * ─────────────────────────────────────────────────────────────────────────
 * كل فعل يمر بثلاث طبقات: حارس الأفعال المركزي (action_guard — مسجَّل) ثم
 * صلاحية الشاشة/الكتابة ثم حراس RiskService الحاكمة (السلطة · الدليل ·
 * التكرار · لا حذف). الردود JSON برمز خطأ محكوم (UI-13: لا نص تقني خام).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    exit(json_encode(array('ok' => false, 'code' => 'RSK-401', 'msg' => 'انتهت الجلسة — سجل الدخول')));
}
include_once __DIR__ . '/../config.php';
// بعد config حصرًا: config يفرض text/html على ما قبله (وحاقن الأرقام يلوث
// أي رد ليس application/json) — الترويسة هنا تعزل الرد JSON صافيًا.
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';
require_once __DIR__ . '/../app/Services/Risk/RiskEvents.php';
require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';

use App\Services\Risk\RiskService;
use App\Services\Risk\RiskEvents;
use App\Services\Work\WorkItemService;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$role       = strval($_SESSION['user']['role'] ?? '');
$is_super   = ($role === '-1');

require_once __DIR__ . '/_risk_authority.php';
$authority = risk_actor_authority_map($role);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(array('ok' => false, 'code' => 'RSK-405', 'msg' => 'POST فقط')));
}
if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(array('ok' => false, 'code' => 'RSK-CSRF', 'msg' => 'رمز الجلسة غير صالح — حدّث الصفحة')));
}

// صلاحية الكتابة: من سجل الشاشات (أي شاشة مخاطر يملك الفاعل كتابتها تكفي
// للأفعال العامة — والحسم الدقيق في حراس الخدمة بالسلطة لا بالشاشة)
$writeScreens = array('Risk/risk_register.php', 'Risk/risk_signals.php', 'Risk/risk_controls.php',
                      'Risk/risk_treatments.php', 'Risk/risk_kris.php', 'Risk/dept_risk_space.php');
$canWrite = $is_super;
if (!$canWrite) {
    foreach ($writeScreens as $ws) {
        $pp = check_page_permissions($conn, $ws);
        if (!empty($pp['can_add']) || !empty($pp['can_edit'])) { $canWrite = true; break; }
    }
}
// الإبلاغ عن إشارة حق لكل إدارة (ورقة 27) — يكفي can_view على مساحة الإدارة
$canSignal = $canWrite;
if (!$canSignal) {
    $pp = check_page_permissions($conn, 'Risk/dept_risk_space.php');
    $canSignal = !empty($pp['can_view']);
}

// التصدير (§8): قارئٌ يكتب سجلًّا — فيكفيه عرضٌ على أي شاشةِ مخاطرَ يملكها،
// ويُرفض على من لا يرى شيئًا (fail-closed لا مجاملة).
$__pp_any_view = $is_super;
if (!$__pp_any_view) {
    foreach (array_merge($writeScreens, array('Risk/risk_board.php', 'Risk/risk_reports.php',
             'Risk/risk_appetite.php', 'Risk/risk_reviews.php')) as $vs) {
        $pp = check_page_permissions($conn, $vs);
        if (!empty($pp['can_view'])) { $__pp_any_view = true; break; }
    }
}

// §9-1 «المُنشئ — الاسمُ والصفة»: الصفةُ من المسمى الوظيفيِّ الحيِّ لا من الدور
// (UI-DEF-01: المسمى ليس اسمَ الشخص) — وتُكتب في سجلِّ التصديرِ بندًا ثانيًا.
$actorCapacity = '';
if ($uid > 0) {
    $stc = $conn->prepare('SELECT jt.name FROM users u
                             LEFT JOIN employees e ON e.id = u.employee_id
                             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                            WHERE u.id = ? LIMIT 1');
    $stc->bind_param('i', $uid);
    $stc->execute();
    $rowc = $stc->get_result()->fetch_assoc();
    $stc->close();
    $actorCapacity = (string) ($rowc['name'] ?? '');
}
if ($actorCapacity === '') { $actorCapacity = 'دور ' . $role; }

$action = (string) ($_POST['do'] ?? '');
$out = array('ok' => false);

try {
    switch ($action) {
        case 'signal_create': // حق كل إدارة: يُنشئ إشارة لا خطرًا (RK-05)
            if (!$canSignal) { throw new \RuntimeException('RSK-403: لا صلاحية إبلاغ'); }
            $r = RiskService::createSignal($conn, $company_id, array(
                'source' => $_POST['source'] ?? 'manual',
                'title' => trim((string) $_POST['title']),
                'details' => $_POST['details'] ?? null,
                'ru_hint_id' => $_POST['ru_hint_id'] ?? null,
                'entity_type' => $_POST['entity_type'] ?? null, 'entity_id' => $_POST['entity_id'] ?? null,
                'scope_ref_type' => $_POST['scope_ref_type'] ?? null, 'scope_ref_id' => $_POST['scope_ref_id'] ?? null,
                'root_cause' => $_POST['root_cause'] ?? '',
                'site_id' => $_POST['site_id'] ?? null, 'shift_ar' => $_POST['shift_ar'] ?? null,
                'equipment_id' => $_POST['equipment_id'] ?? null, 'sync_uuid' => $_POST['sync_uuid'] ?? null,
            ), $uid);
            $out = array('ok' => true, 'id' => $r['id'], 'idempotent' => $r['idempotent']);
            break;

        case 'signal_triage': // محلل/مدير المخاطر
            if (!$canWrite) { throw new \RuntimeException('RSK-403: الفرز لإدارة المخاطر'); }
            $r = RiskService::triageSignal($conn, $company_id, (int) $_POST['signal_id'],
                (string) $_POST['decision'], trim((string) $_POST['reason']), $uid, array(
                    'risk_id' => $_POST['risk_id'] ?? 0, 'ru_id' => $_POST['ru_id'] ?? 0,
                    'title' => $_POST['title'] ?? '', 'root_cause' => $_POST['root_cause'] ?? '',
                    'owner_unit_id' => $_POST['owner_unit_id'] ?? null,
                    'scope_type' => $_POST['scope_type'] ?? 'إداري',
                    'force_duplicate' => !empty($_POST['force_duplicate']),
                ));
            $out = array('ok' => true) + $r;
            break;

        case 'risk_create':
            if (!$canWrite) { throw new \RuntimeException('RSK-403: إنشاء الخطر لإدارة المخاطر'); }
            $r = RiskService::createRisk($conn, $company_id, array(
                'ru_id' => (int) $_POST['ru_id'], 'title' => trim((string) $_POST['title']),
                'description' => $_POST['description'] ?? null,
                'scope_type' => $_POST['scope_type'] ?? 'إداري',
                'scope_ref_type' => $_POST['scope_ref_type'] ?? null, 'scope_ref_id' => $_POST['scope_ref_id'] ?? null,
                'entity_type' => $_POST['entity_type'] ?? null, 'entity_id' => $_POST['entity_id'] ?? null,
                'root_cause' => $_POST['root_cause'] ?? '',
                'owner_unit_id' => $_POST['owner_unit_id'] ?? null,
                'risk_owner_user_id' => $_POST['risk_owner_user_id'] ?? null,
            ), $uid, !empty($_POST['force_duplicate']));
            $out = array('ok' => ($r['id'] > 0)) + $r;
            break;

        case 'risk_assess':
            if (!$canWrite) { throw new \RuntimeException('RSK-403: التقييم بمراجعة المخاطر'); }
            $impacts = array();
            foreach (array('safety','operations','financial','legal','reputation','environment','data','people') as $dim) {
                if (isset($_POST['impact_' . $dim]) && $_POST['impact_' . $dim] !== '') {
                    $impacts[$dim] = (int) $_POST['impact_' . $dim];
                }
            }
            $r = RiskService::assess($conn, $company_id, (int) $_POST['risk_id'], (string) $_POST['assess_type'],
                (int) $_POST['likelihood'], $impacts, (string) ($_POST['confidence'] ?? 'متوسطة'),
                (string) ($_POST['technique'] ?? 'مصفوفة الخطر'), $uid, $_POST['note'] ?? null,
                !empty($_POST['challenged_by']) ? (int) $_POST['challenged_by'] : null);
            $out = array('ok' => true) + $r;
            break;

        case 'control_create':
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            $isCritical = !empty($_POST['is_critical']) ? 1 : 0;
            if ($isCritical) {
                foreach (array('hico_event','perf_criterion','verify_method','verifier_user_id','fail_action') as $f) {
                    if (trim((string) ($_POST[$f] ?? '')) === '') {
                        throw new \RuntimeException('RSK-422: الضابط الحرج بحقوله الخمسة كاملة (' . $f . ' غائب)');
                    }
                }
                if ((int) $_POST['verifier_user_id'] === (int) $_POST['owner_user_id']) {
                    throw new \RuntimeException('RSK-403: المتحقق المستقل ≠ مالك الضابط (RK-07)');
                }
            }
            $st = $conn->prepare("SELECT CONCAT('CTL-', LPAD(COALESCE(MAX(CAST(SUBSTRING(control_code,5) AS UNSIGNED)),0)+1, 4, '0')) c FROM risk_controls WHERE company_id = ?");
            $st->bind_param('i', $company_id); $st->execute();
            $code = $st->get_result()->fetch_assoc()['c']; $st->close();
            $st = $conn->prepare('INSERT INTO risk_controls
                (company_id, control_code, name_ar, ctype, owner_user_id, process_ref, frequency, evidence_spec,
                 is_critical, hico_event, perf_criterion, verify_method, verifier_user_id, fail_action)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $name = trim((string) $_POST['name_ar']); $ctype = (string) $_POST['ctype'];
            $owner = (int) $_POST['owner_user_id']; $proc = (string) ($_POST['process_ref'] ?? '');
            $freq = (string) $_POST['frequency']; $ev = (string) ($_POST['evidence_spec'] ?? '');
            $hico = $_POST['hico_event'] ?? null; $perf = $_POST['perf_criterion'] ?? null;
            $vm = $_POST['verify_method'] ?? null;
            $verifier = !empty($_POST['verifier_user_id']) ? (int) $_POST['verifier_user_id'] : null;
            $fail = $_POST['fail_action'] ?? null;
            $st->bind_param('isssisssisssis', $company_id, $code, $name, $ctype, $owner, $proc, $freq, $ev,
                $isCritical, $hico, $perf, $vm, $verifier, $fail);
            $st->execute();
            $newCtlId = (int) $conn->insert_id; // قبل أي جملة تالية (گوتشا insert_id المسروق)
            $st->close();
            $upc = $conn->prepare('UPDATE risk_controls SET created_by = ? WHERE id = ? AND company_id = ?');
            $upc->bind_param('iii', $uid, $newCtlId, $company_id); $upc->execute(); $upc->close();
            RiskEvents::fire($conn, $company_id, 'RiskControlCreated', $newCtlId, array(
                'control_code' => $code, 'ctype' => $ctype, 'is_critical' => (bool) $isCritical,
                'owner_user_id' => $owner, 'frequency' => $freq,
                'verifier_user_id' => $verifier, 'hico_event' => $hico,
            ), $uid);
            $out = array('ok' => true, 'id' => $newCtlId, 'control_code' => $code);
            break;

        case 'control_link':
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            $rid = (int) $_POST['risk_id']; $cid = (int) $_POST['control_id'];
            RiskService::requireRisk($conn, $company_id, $rid);
            $st = $conn->prepare('INSERT IGNORE INTO risk_control_links (company_id, risk_id, control_id, linked_by) VALUES (?,?,?,?)');
            $st->bind_param('iiii', $company_id, $rid, $cid, $uid);
            $st->execute();
            $linked = $conn->affected_rows > 0;
            $st->close();
            $up = $conn->prepare("UPDATE risk_register SET state = 'controls_linked' WHERE id = ? AND company_id = ? AND state = 'inherent_assessed'");
            $up->bind_param('ii', $rid, $company_id); $up->execute(); $up->close();
            if ($linked) {
                // «الضابطُ مرتبطٌ — ولا يخفض الدرجةَ قبل إثبات فعاليته» (§7-2 · RK-07):
                // الربطُ يُحدِّث عنصرَ الفعاليةِ المجمَّعةِ لا الدرجةَ، ويعيد حكمَ الشهية.
                RiskService::refreshLinkedRisks($conn, $company_id, $cid, $uid);
                RiskEvents::fire($conn, $company_id, 'RiskControlLinked', $cid, array(
                    'risk_id' => $rid, 'lowers_score' => false,
                ), $uid, 'r' . $rid);
            }
            $out = array('ok' => true, 'linked' => $linked);
            break;

        case 'control_evidence': // مالك الضابط يسجل دليل تنفيذه (حق كل إدارة)
            if (!$canSignal) { throw new \RuntimeException('RSK-403'); }
            $cid = (int) $_POST['control_id'];
            $st = $conn->prepare('SELECT owner_user_id FROM risk_controls WHERE id = ? AND company_id = ?');
            $st->bind_param('ii', $cid, $company_id); $st->execute();
            $ctl = $st->get_result()->fetch_assoc(); $st->close();
            if (!$ctl) { throw new \RuntimeException('RSK-404'); }
            if (!$canWrite && (int) $ctl['owner_user_id'] !== $uid) {
                throw new \RuntimeException('RSK-403: دليل التنفيذ يسجله مالك الضابط');
            }
            $txt = trim((string) $_POST['evidence_text']);
            if ($txt === '') { throw new \RuntimeException('RSK-422: الدليل نص مثبت لا ادعاء'); }
            $ref = $_POST['evidence_ref'] ?? null;
            $st = $conn->prepare("INSERT INTO risk_control_evidence (company_id, control_id, kind, evidence_text, evidence_ref, submitted_by) VALUES (?,?,'execution',?,?,?)");
            $st->bind_param('iissi', $company_id, $cid, $txt, $ref, $uid);
            $st->execute();
            $newEvId = (int) $conn->insert_id;
            $st->close();
            RiskEvents::fire($conn, $company_id, 'ControlEvidenceLogged', $cid, array(
                'evidence_id' => $newEvId, 'kind' => 'execution', 'evidence_ref' => $ref,
                'field' => !empty($_POST['from_field']),
                // §7-2: «الفعاليةُ يحكمها متحقِّقٌ مستقل» — الدليلُ لا يقرر فعالية
                'sets_effectiveness' => false,
            ), $uid, (string) $newEvId);
            $out = array('ok' => true, 'id' => $newEvId);
            break;

        case 'control_fail': // risk.control.fail — فعلٌ مستقلٌّ لا فرعُ تحقق (§7-1)
            if (!$canWrite) { throw new \RuntimeException('RSK-403: تسجيلُ الفشلِ للمتحقق أو إدارة المخاطر'); }
            $out = array('ok' => true) + RiskService::failCriticalControl($conn, $company_id,
                (int) $_POST['control_id'], trim((string) ($_POST['reason'] ?? '')), $uid);
            break;

        case 'control_verify': // المتحقق — RK-07 يفرض الاستقلال للحرج
            if (!$canWrite) { throw new \RuntimeException('RSK-403: التحقق لإدارة المخاطر أو المتحقق المستقل'); }
            RiskService::verifyControl($conn, $company_id, (int) $_POST['control_id'],
                (string) $_POST['result'], trim((string) $_POST['evidence_text']), $uid);
            $out = array('ok' => true);
            break;

        case 'treatment_create':
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            RiskService::requireRisk($conn, $company_id, (int) $_POST['risk_id']);
            $st = $conn->prepare('INSERT INTO risk_treatments (company_id, risk_id, ttype, plan_ar, action_owner_user_id, due_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $rid = (int) $_POST['risk_id']; $tt = (string) $_POST['ttype'];
            $plan = trim((string) $_POST['plan_ar']); $ao = (int) $_POST['action_owner_user_id'];
            $due = (string) $_POST['due_date'];
            $st->bind_param('iississ', $company_id, $rid, $tt, $plan, $ao, $due, $uid);
            $st->execute();
            $newTreatId = (int) $conn->insert_id; // قبل أي جملة تالية (گوتشا insert_id المسروق)
            $st->close();
            $up = $conn->prepare("UPDATE risk_register SET state = 'treatment_planned' WHERE id = ? AND company_id = ? AND state NOT IN ('closed')");
            $up->bind_param('ii', $rid, $company_id); $up->execute(); $up->close();
            // §9-1 المرجعُ الأب: رمزُ الخطرِ — السلسلةُ من الواقعةِ إلى الإجراء
            $st = $conn->prepare('SELECT risk_code, owner_unit_id, title FROM risk_register WHERE id = ? AND company_id = ?');
            $st->bind_param('ii', $rid, $company_id); $st->execute();
            $rk = $st->get_result()->fetch_assoc(); $st->close();
            $pref = (string) ($rk['risk_code'] ?? '');
            $up = $conn->prepare('UPDATE risk_treatments SET parent_ref = ? WHERE id = ? AND company_id = ?');
            $up->bind_param('sii', $pref, $newTreatId, $company_id); $up->execute(); $up->close();

            // §7-2 · §15-9: «إجراءٌ مسنَدٌ بمهلةٍ يظهر في مهامِّ مسؤوله».
            // يُكتب بخدمتِه المالكةِ WorkItemService لا بإدراجٍ مباشرٍ في جدولِ غيرها،
            // وfromNavAction تفرض أن يكون الفعلُ في قاموسِ NAV-09 وتحمل عطالتَها.
            $wi = null;
            try {
                $wi = WorkItemService::fromNavAction($conn, 'RSK-TREAT-CREATE', array(
                    'company_id' => $company_id,
                    'source_ref' => $pref . '/T' . $newTreatId,
                    'owner_user_id' => $uid,
                    'assigned_user_id' => $ao,
                    'org_unit_id' => !empty($rk['owner_unit_id']) ? (int) $rk['owner_unit_id'] : null,
                    'title' => 'معالجة خطر ' . $pref . ' — ' . mb_substr($plan, 0, 120),
                    'details' => 'الخطر: ' . (string) ($rk['title'] ?? '') . ' · نوع المعالجة: ' . $tt,
                    'due_at' => $due . ' 23:59:59',
                    'deliverable' => 'دليلُ إنجازٍ يقبله المتحقِّق — والإغلاقُ بقبوله لا بالتنفيذ',
                    'evidence_required' => 'دليلُ تنفيذٍ مكتوبٌ في إجراءِ المعالجة',
                    'created_by' => $uid,
                ));
            } catch (\Throwable $wx) {
                // العنصرُ الشخصيُّ أثرٌ تابعٌ لا شرطُ صحةٍ للمعالجة — الفشلُ يُسجَّل
                // ولا يُسقط الإجراءَ المجاليَّ الذي وقع (§7-3: الفشلُ يملأ الطابور).
                error_log('RSK-TREAT-CREATE work item for treatment ' . $newTreatId . ': ' . $wx->getMessage());
            }
            RiskEvents::fire($conn, $company_id, 'RiskTreatmentPlanned', $newTreatId, array(
                'risk_id' => $rid, 'risk_code' => $pref, 'ttype' => $tt,
                'action_owner_user_id' => $ao, 'due_date' => $due,
                'work_item_id' => (is_array($wi) && !empty($wi['id'])) ? (int) $wi['id'] : null,
            ), $uid);
            $out = array('ok' => true, 'id' => $newTreatId,
                'work_item_id' => (is_array($wi) && !empty($wi['id'])) ? (int) $wi['id'] : null);
            break;

        case 'treatment_progress': // مسؤول المعالجة يقدم دليل الإنجاز
            $tid = (int) $_POST['treatment_id'];
            $st = $conn->prepare('SELECT action_owner_user_id FROM risk_treatments WHERE id = ? AND company_id = ?');
            $st->bind_param('ii', $tid, $company_id); $st->execute();
            $t = $st->get_result()->fetch_assoc(); $st->close();
            if (!$t) { throw new \RuntimeException('RSK-404'); }
            if (!$canWrite && (int) $t['action_owner_user_id'] !== $uid) {
                throw new \RuntimeException('RSK-403: التنفيذ لمسؤول المعالجة المسنَد');
            }
            $newState = (string) $_POST['state'];
            if (!in_array($newState, array('in_progress', 'done'), true)) { throw new \RuntimeException('RSK-422'); }
            $evid = $_POST['done_evidence'] ?? null;
            if ($newState === 'done' && trim((string) $evid) === '') {
                throw new \RuntimeException('RSK-422: الإنجاز بدليل — والإغلاق بقبول المتحقق');
            }
            $st = $conn->prepare('UPDATE risk_treatments SET state = ?, done_evidence = COALESCE(?, done_evidence) WHERE id = ? AND company_id = ?');
            $st->bind_param('ssii', $newState, $evid, $tid, $company_id);
            $st->execute();
            $st->close();
            RiskEvents::fire($conn, $company_id, 'RiskTreatmentProgressed', $tid, array(
                'state' => $newState, 'has_evidence' => ($newState === 'done'),
                'field' => !empty($_POST['from_field']),
                'closes' => false, // §7-2: «الإغلاقُ بقبولِ المتحقِّق لا المنفِّذ»
            ), $uid, $newState);
            $out = array('ok' => true);
            break;

        case 'treatment_verify': // المتحقق يقبل الدليل
            if (!$canWrite) { throw new \RuntimeException('RSK-403: القبول للمتحقق'); }
            $tid = (int) $_POST['treatment_id'];
            // RK-07 معمَّمًا: من نفّذ لا يشهد بإنجازِ نفسِه — وإلا صار القبولُ صوريًّا.
            $st = $conn->prepare('SELECT action_owner_user_id, risk_id FROM risk_treatments WHERE id = ? AND company_id = ?');
            $st->bind_param('ii', $tid, $company_id); $st->execute();
            $tv = $st->get_result()->fetch_assoc(); $st->close();
            if (!$tv) { throw new \RuntimeException('RSK-404: الإجراء غير موجود'); }
            if ((int) $tv['action_owner_user_id'] === $uid && !$is_super) {
                throw new \RuntimeException('RSK-403: المتحقِّقُ لا المنفِّذ — من نفّذ لا يقبل دليلَ نفسِه (§9-3)');
            }
            $st = $conn->prepare("UPDATE risk_treatments SET state = 'verified', verified_by = ?, verified_at = NOW()
                                   WHERE id = ? AND company_id = ? AND state = 'done' AND done_evidence IS NOT NULL");
            $st->bind_param('iii', $uid, $tid, $company_id);
            $st->execute();
            if ($conn->affected_rows === 0) { throw new \RuntimeException('RSK-409: لا قبول قبل دليل إنجاز مقدم'); }
            $st->close();
            RiskEvents::fire($conn, $company_id, 'RiskTreatmentClosed', $tid, array(
                'risk_id' => (int) $tv['risk_id'], 'verified_by' => $uid,
                'opens_close_gate' => true,
            ), $uid, (string) $tid);
            $out = array('ok' => true);
            break;

        case 'risk_accept':
            if ($authority === '') { throw new \RuntimeException('RSK-403: لا سلطة قبول لدورك'); }
            RiskService::acceptRisk($conn, $company_id, (int) $_POST['risk_id'], $authority, $uid,
                $_POST['note'] ?? null, !empty($_POST['analyst_review_by']) ? (int) $_POST['analyst_review_by'] : null);
            $out = array('ok' => true);
            break;

        case 'risk_close':
            if ($authority === '') { throw new \RuntimeException('RSK-403: لا سلطة إغلاق لدورك'); }
            RiskService::closeRisk($conn, $company_id, (int) $_POST['risk_id'], $authority, $uid, $_POST['note'] ?? null);
            $out = array('ok' => true);
            break;

        case 'risk_reopen':
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            RiskService::reopenRisk($conn, $company_id, (int) $_POST['risk_id'],
                trim((string) ($_POST['reason'] ?? '')), $uid);
            $out = array('ok' => true);
            break;

        case 'risk_review': // risk.review — المراجعةُ الدوريةُ (المرحلة ١٣)
            if (!$canWrite) { throw new \RuntimeException('RSK-403: المراجعةُ لمحلل/مدير المخاطر'); }
            $out = array('ok' => true) + RiskService::reviewRisk($conn, $company_id,
                (int) $_POST['risk_id'], (string) ($_POST['decision'] ?? ''),
                trim((string) ($_POST['findings'] ?? '')), $uid,
                (string) ($_POST['trigger_kind'] ?? 'دورية'));
            break;

        case 'incident_log': // risk.incident.log — حقُّ الموقعِ ومركزِ البلاغات
            if (!$canSignal) { throw new \RuntimeException('RSK-403: تسجيلُ الواقعةِ يحتاج نطاقَ إدارتك'); }
            $out = array('ok' => true) + RiskService::logIncident($conn, $company_id, array(
                'itype' => $_POST['itype'] ?? 'واقعة',
                'ru_id' => $_POST['ru_id'] ?? null,
                'title' => $_POST['title'] ?? '',
                'details' => $_POST['details'] ?? null,
                'occurred_at' => $_POST['occurred_at'] ?? '',
                'site_id' => $_POST['site_id'] ?? null,
                'equipment_id' => $_POST['equipment_id'] ?? null,
                'entity_type' => $_POST['entity_type'] ?? '',
                'entity_id' => $_POST['entity_id'] ?? null,
                'root_cause' => $_POST['root_cause'] ?? '',
                'injury_count' => $_POST['injury_count'] ?? 0,
                'downtime_hours' => $_POST['downtime_hours'] ?? 0,
                'loss_estimate' => $_POST['loss_estimate'] ?? null,
                'currency' => $_POST['currency'] ?? '',
                'realized_risk_id' => $_POST['realized_risk_id'] ?? null,
                'signal_id' => $_POST['signal_id'] ?? null,
                'corrected_by_ref' => $_POST['corrected_by_ref'] ?? '',
            ), $uid);
            break;

        case 'appetite_set': // risk.appetite.set — الرئيسُ التنفيذيُّ حصرًا
            $out = array('ok' => true) + RiskService::setAppetite($conn, $company_id,
                (string) ($_POST['domain'] ?? ''), trim((string) ($_POST['appetite_ar'] ?? '')),
                (string) ($_POST['tolerance_ar'] ?? ''), (string) ($_POST['plan_mode'] ?? ''),
                $authority, $uid);
            break;

        case 'taxonomy_define': // risk.taxonomy.define — مديرُ المخاطر
            if (!$canWrite) { throw new \RuntimeException('RSK-403: التصنيفُ منهجٌ تملكه إدارةُ المخاطر'); }
            RiskService::defineTaxonomy($conn, $company_id, (int) $_POST['ru_id'], array(
                'name_ar' => $_POST['name_ar'] ?? null,
                'coverage' => $_POST['coverage'] ?? null,
                'ref_standard' => $_POST['ref_standard'] ?? null,
                'dedup_window_days' => $_POST['dedup_window_days'] ?? null,
                'active' => isset($_POST['active']) ? $_POST['active'] : null,
            ), $uid);
            $out = array('ok' => true);
            break;

        case 'kri_threshold': // risk.kri.threshold — ضبطُ الحدِّ لا قراءتُه
            if (!$canWrite) { throw new \RuntimeException('RSK-403: الحدودُ تملكها إدارةُ المخاطر'); }
            RiskService::setKriThreshold($conn, $company_id, (int) $_POST['kri_id'],
                $_POST['warn_num'] ?? null, $_POST['critical_num'] ?? null,
                (string) ($_POST['direction'] ?? 'تصاعدي'), $uid);
            $out = array('ok' => true);
            break;

        case 'report_export': // risk.report.export — يكتب سجلًّا بتسعةِ بنود
            if (empty($__pp_any_view)) { throw new \RuntimeException('RSK-403: لا صلاحيةَ تصدير'); }
            $out = array('ok' => true) + RiskService::logExport($conn, $company_id, array(
                'screen_code' => $_POST['screen_code'] ?? '',
                'actor_capacity' => $actorCapacity,
                'view_key' => $_POST['view_key'] ?? 'default',
                'columns_text' => $_POST['columns_text'] ?? '',
                'filters_text' => $_POST['filters_text'] ?? '',
                'blocked_text' => $_POST['blocked_text'] ?? '',
                'row_count' => $_POST['row_count'] ?? 0,
                'fmt' => $_POST['fmt'] ?? 'xlsx',
            ), $uid);
            break;

        case 'field_sync': // risk.field.sync — دفعةُ المعلَّقِ بمفاتيحها
            if (!$canSignal) { throw new \RuntimeException('RSK-403'); }
            $batch = json_decode((string) ($_POST['items'] ?? '[]'), true);
            if (!is_array($batch)) { throw new \RuntimeException('RSK-422: دفعةُ المزامنةِ JSON صحيحٌ'); }
            if (count($batch) > 200) { throw new \RuntimeException('RSK-422: الدفعةُ ٢٠٠ عنصرٍ حدًّا أقصى'); }
            $out = array('ok' => true) + RiskService::syncFieldSignals($conn, $company_id, $batch, $uid);
            break;

        case 'gov_attest': // gov.rsk.attest — يشهد ولا يمنح
            if (!$canWrite) { throw new \RuntimeException('RSK-403: التصديقُ لمديرِ الإدارة'); }
            $out = array('ok' => true) + RiskService::attestAccessReview($conn, $company_id,
                (string) ($_POST['scope_code'] ?? ''), (int) ($_POST['headcount'] ?? 0),
                (string) ($_POST['note'] ?? ''), $uid);
            break;

        case 'risk_merge': // بقرار محلل مسبَّب — ورقة 32
            if (!$canWrite) { throw new \RuntimeException('RSK-403: الدمج لمحلل المخاطر'); }
            $ok = RiskService::mergeRisks($conn, $company_id, (int) $_POST['src_risk_id'],
                (int) $_POST['dst_risk_id'], trim((string) $_POST['reason']), $uid);
            $out = array('ok' => $ok);
            break;

        case 'kri_update': // قراءة مؤشر — تصعيد آلي عند الحرج (SG-15)
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            $kid = (int) $_POST['kri_id'];
            $val = trim((string) $_POST['current_value']);
            $stt = (string) $_POST['kri_state'];
            if (!in_array($stt, array('ok', 'warn', 'critical'), true)) { throw new \RuntimeException('RSK-422'); }
            $st = $conn->prepare('UPDATE risk_kris SET current_value = ?, kri_state = ?, last_read_at = NOW(), last_read_by = ? WHERE id = ? AND company_id = ?');
            $st->bind_param('ssiii', $val, $stt, $uid, $kid, $company_id);
            $st->execute(); $st->close();
            if ($stt === 'critical') {
                // SG-15 بمفتاحِ قاعدةٍ باليوم — القراءةُ المتكررةُ لا تُنشئ إشاراتٍ عدة
                RiskService::createSignal($conn, $company_id, array(
                    'sg_code' => 'SG-15', 'source' => 'auto',
                    'rule_key' => 'SG-15:kri' . $kid . ':' . gmdate('Y-m-d'),
                    'title' => 'مؤشر خطر بلغ حده الحرج #' . $kid,
                    'details' => 'القيمة: ' . $val, 'root_cause' => 'تجاوز مؤشر خطر',
                ), $uid);
                RiskEvents::fire($conn, $company_id, 'KRIBreached', $kid, array(
                    'value' => $val, 'state' => $stt, 'read_mode' => 'يدوي',
                ), $uid, gmdate('Y-m-d'));
            }
            $out = array('ok' => true);
            break;

        case 'escalation_ack':
            if ($authority !== 'ceo' && !$canWrite) { throw new \RuntimeException('RSK-403'); }
            $eid = (int) $_POST['escalation_id'];
            $st = $conn->prepare('UPDATE risk_escalations SET acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ? AND company_id = ? AND acknowledged_at IS NULL');
            $st->bind_param('iii', $uid, $eid, $company_id);
            $st->execute();
            $out = array('ok' => $conn->affected_rows > 0);
            $st->close();
            break;

        default:
            http_response_code(400);
            $out = array('ok' => false, 'code' => 'RSK-400', 'msg' => 'فعل غير معروف');
    }
} catch (\RuntimeException $e) {
    http_response_code(422);
    $parts = explode(':', $e->getMessage(), 2);
    $out = array('ok' => false, 'code' => trim($parts[0]), 'msg' => trim($parts[1] ?? $e->getMessage()));
} catch (\Throwable $t) {
    error_log('risk_actions: ' . $t->getMessage());
    http_response_code(500);
    $out = array('ok' => false, 'code' => 'RSK-500', 'msg' => 'خطأ داخلي — أبلغ الدعم بالرمز');
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
