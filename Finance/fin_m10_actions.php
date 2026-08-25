<?php
/**
 * Finance/fin_m10_actions.php — معالجُ أفعالِ M-10 المستكملة (AJAX/POST)
 * ─────────────────────────────────────────────────────────────────────────
 * الأفعالُ التي كانت declared_unbuilt حصرًا — والمبنيُّ سلفًا في صفحاته لا
 * يُكرر هنا (pay.execute · je.post · fin.close ... بمعالجاتها الحية).
 * كل فعل بثلاث طبقات: صلاحيةُ الشاشة ثم حرّاسُ FinanceM10Service (البوابةُ
 * الرباعية · العطالة · فصلُ الواجبات · لا حذف). الردود JSON برمزٍ محكوم (UI-13).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    exit(json_encode(array('ok' => false, 'code' => 'FIN-401', 'msg' => 'انتهت الجلسة — سجل الدخول')));
}
include_once __DIR__ . '/../config.php';
// بعد config حصرًا: config يفرض text/html وحاقنُ الأرقام يلوث غيرَ JSON —
// الترويسة هنا تعزل الرد JSON صافيًا (گوتشا update0011 المثبتة).
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Finance/FinanceM10Service.php';
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';

use App\Services\Finance\FinanceM10Service as M10;
use App\Services\Risk\RiskService;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$role       = strval($_SESSION['user']['role'] ?? '');
$is_super   = ($role === '-1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(array('ok' => false, 'code' => 'FIN-405', 'msg' => 'POST فقط')));
}
if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(array('ok' => false, 'code' => 'FIN-CSRF', 'msg' => 'رمز الجلسة غير صالح — حدث الصفحة')));
}

/* صلاحيةُ الكتابة من سجل الشاشات — أيُّ شاشةِ ماليةٍ كاتبةٍ تكفي للأفعال
   العامة، والحسمُ الدقيقُ في حرّاس الخدمة (فصلُ الواجبات بالحساب لا بالشاشة). */
$writeScreens = array('Finance/entitlement.php', 'Finance/entitlement_gate.php',
                      'Finance/budget_master.php', 'Finance/budget_dept.php',
                      'Finance/payments_fin.php', 'Finance/journal_form_fin.php');
$canWrite = $is_super;
if (!$canWrite) {
    foreach ($writeScreens as $ws) {
        $pp = check_page_permissions($conn, $ws);
        if (!empty($pp['can_add']) || !empty($pp['can_edit'])) { $canWrite = true; break; }
    }
}
$canView = $is_super || $canWrite;
if (!$canView) {
    foreach (array_merge($writeScreens, array('Finance/cfo_daily_board_fin.php', 'Finance/gov_dept_fin.php')) as $vs) {
        $pp = check_page_permissions($conn, $vs);
        if (!empty($pp['can_view'])) { $canView = true; break; }
    }
}

/* §9-1 «المُنشئ — الاسمُ والصفة»: الصفةُ من المسمى الوظيفي الحي لا من الدور */
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
        case 'gate_pass': // gate.pass — البوابةُ الرباعية: فحصٌ ومحضرٌ بسببٍ محكوم
            if (!$canWrite) { throw new \RuntimeException('FIN-403: البوابة للمالية'); }
            $r = M10::gatePass($conn, $company_id, (int) $_POST['unit_id'], $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'entitle_generate': // fin.entitle — التوليدُ عبر المروحة القائمة
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التوليد للمدير المالي'); }
            $unitId = (int) $_POST['unit_id'];
            $res = null;
            // الذرّية: المروحةُ داخل معاملة TenantDb (نمط cron_events المثبت)
            $gate->runInTransaction(function ($g) use (&$res, $conn, $company_id, $unitId, $uid) {
                $res = M10::generateEntitlement($conn, $g, $company_id, $unitId, $uid);
            }, 'M-10 entitle unit ' . $unitId);
            $out = array('ok' => true) + $res;
            break;

        case 'budget_commit': // budget.commit — المتاحُ ينخفض قبل الصرف
            if (!$canWrite) { throw new \RuntimeException('FIN-403: الحجز للمالية'); }
            $lineId = !empty($_POST['budget_line_id']) ? (int) $_POST['budget_line_id'] : null;
            $r = M10::budgetCommit($conn, $company_id, (int) $_POST['budget_id'], $lineId,
                (string) ($_POST['source_kind'] ?? 'other'), trim((string) $_POST['source_ref']),
                (float) $_POST['amount'], $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'budget_release': // عكسُ الحجز — تحريرٌ بسببه
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التحرير للمالية'); }
            $r = M10::budgetRelease($conn, $company_id, (int) $_POST['commit_id'],
                trim((string) $_POST['reason']), $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'budget_approve': // budget.approve — بفصل الواجبات
            if (!$canWrite) { throw new \RuntimeException('FIN-403: الاعتماد للمخول'); }
            $r = M10::budgetApprove($conn, $company_id, (int) $_POST['budget_id'], $uid,
                $actorCapacity, 'اعتماد الموازنة ضمن سقف الدور — M-10 §7-1');
            $out = array('ok' => true) + $r;
            break;

        case 'budget_change_request': // budget.request — ببيان أثرٍ إلزامي
            if (!$canView) { throw new \RuntimeException('FIN-403: لا صلاحية'); }
            $lineId = !empty($_POST['budget_line_id']) ? (int) $_POST['budget_line_id'] : null;
            $r = M10::budgetChangeRequest($conn, $company_id, (int) $_POST['budget_id'], $lineId,
                (string) ($_POST['dept_module'] ?? ''), (float) ($_POST['current_amount'] ?? 0),
                (float) $_POST['requested_amount'], trim((string) $_POST['impact_note']), $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'budget_change_withdraw': // عكسُ الطلب — سحبٌ قبل الاعتماد
            if (!$canView) { throw new \RuntimeException('FIN-403: لا صلاحية'); }
            $r = M10::budgetChangeWithdraw($conn, $company_id, (int) $_POST['req_id'], $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'stmt_client_issue': // stmt.client.issue — تثبيتُ الرصيد التراكمي
            if (!$canWrite) { throw new \RuntimeException('FIN-403: الإصدار للمالية'); }
            $r = M10::issueClientStatement($conn, $gate, $company_id, (int) $_POST['client_id'],
                (string) $_POST['from'], (string) $_POST['to'], $uid, $actorCapacity);
            $out = array('ok' => true) + $r;
            break;

        case 'margin_compute': // margin.compute — من الاعترافات الثلاثة
            if (!$canWrite) { throw new \RuntimeException('FIN-403: الاحتساب للمالية'); }
            $cid = !empty($_POST['contract_id']) ? (int) $_POST['contract_id'] : null;
            $r = M10::computeMargin($conn, $company_id, (string) $_POST['period'], $cid, $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'cycle_measure': // cycle.measure — مواضعُ الاختناق بالحلقة
            if (!$canWrite) { throw new \RuntimeException('FIN-403: القياس للمالية'); }
            $r = M10::measureCycleTime($conn, $company_id, (string) $_POST['period'], $uid);
            $out = array('ok' => true) + $r;
            break;

        case 'gov_attest': // gov.fin.attest — يشهد ولا يمنح (إعادةُ استخدام M-16)
            if (!$canView) { throw new \RuntimeException('FIN-403: التصديق لمدير الإدارة'); }
            $r = RiskService::attestAccessReview($conn, $company_id,
                'gov_dept_fin:' . gmdate('Y-m'), (int) ($_POST['headcount'] ?? 0),
                trim((string) ($_POST['note'] ?? '')) . ' — بصفة: ' . $actorCapacity, $uid);
            $out = array('ok' => true) + $r;
            break;

        default:
            http_response_code(400);
            $out = array('ok' => false, 'code' => 'FIN-400', 'msg' => 'فعل غير معرف — لا زر بلا عقد');
    }
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $code = 'FIN-500';
    if (preg_match('/^([A-Z]+-[A-Z0-9-]+):/u', $msg, $mm)) { $code = $mm[1]; }
    http_response_code(strpos($code, '403') !== false ? 403 : (strpos($code, '404') !== false ? 404 : 422));
    $out = array('ok' => false, 'code' => $code, 'msg' => $msg);
    // سجلُّ الرفض: المستخدمُ والفعلُ والرمزُ — «ويُسجَّل الرفضُ بالمستخدم والوقت»
    if (isset($conn) && $conn instanceof \mysqli) {
        $st = $conn->prepare("INSERT INTO action_execution_log
            (company_id, action_code, person_id, subject_ref, result, denied_by_guard, at, ip)
            VALUES (?,?,?,?, 'denied', ?, NOW(), ?)");
        if ($st) {
            $ac = 'm10:' . $action;
            $subject = mb_substr($msg, 0, 118);
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $st->bind_param('isisss', $company_id, $ac, $uid, $subject, $code, $ip);
            @$st->execute();
            $st->close();
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
