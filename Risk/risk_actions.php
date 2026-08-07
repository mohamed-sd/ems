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

use App\Services\Risk\RiskService;

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
            $out = array('ok' => true, 'id' => (int) $conn->insert_id, 'control_code' => $code);
            $st->close();
            break;

        case 'control_link':
            if (!$canWrite) { throw new \RuntimeException('RSK-403'); }
            $st = $conn->prepare('INSERT IGNORE INTO risk_control_links (company_id, risk_id, control_id, linked_by) VALUES (?,?,?,?)');
            $rid = (int) $_POST['risk_id']; $cid = (int) $_POST['control_id'];
            $st->bind_param('iiii', $company_id, $rid, $cid, $uid);
            $st->execute();
            $up = $conn->prepare("UPDATE risk_register SET state = 'controls_linked' WHERE id = ? AND company_id = ? AND state = 'inherent_assessed'");
            $up->bind_param('ii', $rid, $company_id); $up->execute(); $up->close();
            $out = array('ok' => true);
            $st->close();
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
            $out = array('ok' => true, 'id' => (int) $conn->insert_id);
            $st->close();
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
            $out = array('ok' => true, 'id' => $newTreatId);
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
            $out = array('ok' => true);
            $st->close();
            break;

        case 'treatment_verify': // المتحقق يقبل الدليل
            if (!$canWrite) { throw new \RuntimeException('RSK-403: القبول للمتحقق'); }
            $tid = (int) $_POST['treatment_id'];
            $st = $conn->prepare("UPDATE risk_treatments SET state = 'verified', verified_by = ?, verified_at = NOW()
                                   WHERE id = ? AND company_id = ? AND state = 'done' AND done_evidence IS NOT NULL");
            $st->bind_param('iii', $uid, $tid, $company_id);
            $st->execute();
            if ($conn->affected_rows === 0) { throw new \RuntimeException('RSK-409: لا قبول قبل دليل إنجاز مقدم'); }
            $out = array('ok' => true);
            $st->close();
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
            $rid = (int) $_POST['risk_id'];
            $st = $conn->prepare("UPDATE risk_register SET state = 'reopened' WHERE id = ? AND company_id = ? AND state = 'closed' AND merged_into_id IS NULL");
            $st->bind_param('ii', $rid, $company_id);
            $st->execute();
            $out = array('ok' => $conn->affected_rows > 0);
            $st->close();
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
            $st = $conn->prepare('UPDATE risk_kris SET current_value = ?, kri_state = ?, last_read_at = NOW() WHERE id = ? AND company_id = ?');
            $st->bind_param('ssii', $val, $stt, $kid, $company_id);
            $st->execute(); $st->close();
            if ($stt === 'critical') {
                RiskService::createSignal($conn, $company_id, array(
                    'sg_code' => 'SG-15', 'source' => 'auto',
                    'title' => 'مؤشر خطر بلغ حده الحرج #' . $kid,
                    'details' => 'القيمة: ' . $val, 'root_cause' => 'تجاوز مؤشر خطر',
                ), $uid);
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
