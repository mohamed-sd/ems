<?php
/**
 * Governance/gov_m14_actions.php — معالجُ أفعالِ M-14 المستكملة (AJAX/POST)
 * ─────────────────────────────────────────────────────────────────────────
 * approval.reject / approval.return / denial.review / org.change /
 * gov.gov.attest — والمبنيُّ سلفًا في صفحاته (deleg.grant · glass.break ...)
 * لا يُكرر هنا. الردود JSON برمزٍ محكوم (UI-13) والرفضُ يُسجَّل.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    exit(json_encode(array('ok' => false, 'code' => 'GOV-401', 'msg' => 'انتهت الجلسة — سجل الدخول')));
}
include_once __DIR__ . '/../config.php';
// بعد config حصرًا — گوتشا حاقن الأرقام المثبتة
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Governance/GovernanceM14Service.php';
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';

use App\Services\Governance\GovernanceM14Service as M14;
use App\Services\Risk\RiskService;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$role       = strval($_SESSION['user']['role'] ?? '');
$is_super   = ($role === '-1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(array('ok' => false, 'code' => 'GOV-405', 'msg' => 'POST فقط')));
}
if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(array('ok' => false, 'code' => 'GOV-CSRF', 'msg' => 'رمز الجلسة غير صالح — حدّث الصفحة')));
}

/* صلاحياتُ الكتابة الحوكمية — من سجل الشاشات */
$decideScreens = array('Finance/approvals_inbox.php', 'FinRequests/dept_inbox.php');
$canDecide = $is_super;
if (!$canDecide) {
    foreach ($decideScreens as $ws) {
        $pp = check_page_permissions($conn, $ws);
        if (!empty($pp['can_edit']) || !empty($pp['can_add'])) { $canDecide = true; break; }
    }
}
$govScreens = array('Governance/guard_denials.php', 'Governance/guards.php', 'Governance/exceptions.php');
$canGovern = $is_super;
if (!$canGovern) {
    foreach ($govScreens as $ws) {
        $pp = check_page_permissions($conn, $ws);
        if (!empty($pp['can_edit']) || !empty($pp['can_add'])) { $canGovern = true; break; }
    }
}
$ppOrg = check_page_permissions($conn, 'admin/org_structure.php');
$canOrg = $is_super || !empty($ppOrg['can_edit']) || !empty($ppOrg['can_add']);
$ppGovGov = check_page_permissions($conn, 'Governance/gov_dept_gov.php');
$canAttest = $is_super || !empty($ppGovGov['can_view']);

/* §9-1: الصفةُ من المسمى الحي */
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
        case 'approval_reject': // approval.reject — رفضٌ بسببٍ محكومٍ يُقاس
        case 'approval_return': // approval.return — إعادةٌ للتصحيح والمهلةُ تتوقف
            if (!$canDecide) { throw new \RuntimeException('GOV-403: القرارُ للمعتمِد المخوَّل'); }
            $decision = $action === 'approval_reject' ? 'rejected' : 'returned';
            $r = M14::decideApproval($conn, $company_id,
                (string) ($_POST['source_kind'] ?? 'fin_request'),
                trim((string) $_POST['source_ref']), $decision,
                (string) $_POST['reason_code'], trim((string) ($_POST['reason_note'] ?? '')),
                $uid, $actorCapacity, 'قرارُ الحلقةِ ضمن سقفِ الدور — M-14 §7-1');
            $out = array('ok' => true) + $r;
            break;

        case 'denial_review': // denial.review — التصنيفُ الرباعي
            if (!$canGovern) { throw new \RuntimeException('GOV-403: المراجعةُ للحوكمة'); }
            $r = M14::reviewDenial($conn, $company_id, (int) $_POST['denial_id'],
                (string) $_POST['classification'], trim((string) ($_POST['note'] ?? '')),
                trim((string) ($_POST['follow_up_ref'] ?? '')), $uid,
                'مراجعةُ المنع — الحوكمةُ والالتزام (M-14 §7-1)');
            $out = array('ok' => true) + $r;
            break;

        case 'org_change': // org.change — نسخةٌ بقرارٍ مرجعي
            if (!$canOrg) { throw new \RuntimeException('GOV-403: تغييرُ الهيكلِ للحوكمة'); }
            $change = json_decode((string) ($_POST['change_json'] ?? '{}'), true);
            if (!is_array($change)) { $change = array(); }
            $unitId = !empty($_POST['unit_id']) ? (int) $_POST['unit_id'] : null;
            $r = M14::orgChange($conn, $company_id, (string) $_POST['change_kind'], $unitId,
                trim((string) $_POST['decision_ref']), (string) $_POST['effective_date'],
                $change, $uid, 'قرارُ الهيكل — الحوكمةُ والالتزام (M-14 §7-1)');
            $out = array('ok' => true) + $r;
            break;

        case 'gov_attest': // gov.gov.attest — يشهد ولا يمنح
            if (!$canAttest) { throw new \RuntimeException('GOV-403: التصديقُ لمدير الإدارة'); }
            $r = RiskService::attestAccessReview($conn, $company_id,
                'gov_dept_gov:' . gmdate('Y-m'), (int) ($_POST['headcount'] ?? 0),
                trim((string) ($_POST['note'] ?? '')) . ' — بصفة: ' . $actorCapacity, $uid);
            $out = array('ok' => true) + $r;
            break;

        default:
            http_response_code(400);
            $out = array('ok' => false, 'code' => 'GOV-400', 'msg' => 'فعلٌ غيرُ معرَّف — لا زرَّ بلا عقد');
    }
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $code = 'GOV-500';
    if (preg_match('/^([A-Z]+-[A-Z0-9-]+):/u', $msg, $mm)) { $code = $mm[1]; }
    http_response_code(strpos($code, '403') !== false ? 403 : (strpos($code, '404') !== false ? 404 : 422));
    $out = array('ok' => false, 'code' => $code, 'msg' => $msg);
    if (isset($conn) && $conn instanceof \mysqli) {
        $st = $conn->prepare("INSERT INTO action_execution_log
            (company_id, action_code, person_id, subject_ref, result, denied_by_guard, at, ip)
            VALUES (?,?,?,?, 'denied', ?, NOW(), ?)");
        if ($st) {
            $ac = 'm14:' . $action;
            $subject = mb_substr($msg, 0, 118);
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $st->bind_param('isisss', $company_id, $ac, $uid, $subject, $code, $ip);
            @$st->execute();
            $st->close();
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
