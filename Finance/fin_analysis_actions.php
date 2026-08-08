<?php
/**
 * Finance/fin_analysis_actions.php — معالجُ أفعالِ مرحلةِ التحليلِ العشرة
 * ─────────────────────────────────────────────────────────────────────────
 * fin.ratio.compute · fin.ratio.drill · fin.ratio.target · fin.unit.economics ·
 * fin.contract.margin · fin.project.pl · fin.cashflow.generate ·
 * fin.equity.generate · fin.signal.raise · fin.posting.matrix
 * كلٌّ بحارسِه الخادميِّ ورمزِ رفضِه المحكوم — والرفضُ يُسجَّل.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    exit(json_encode(array('ok' => false, 'code' => 'FIN-401', 'msg' => 'انتهت الجلسة — سجل الدخول')));
}
include_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8'); // بعد config حصرًا
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Finance/FinAnalysisService.php';
require_once __DIR__ . '/../app/Services/Finance/CoaService.php';

use App\Services\Finance\FinAnalysisService as FA;
use App\Services\Finance\CoaService;

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
    exit(json_encode(array('ok' => false, 'code' => 'FIN-CSRF', 'msg' => 'رمز الجلسة غير صالح — حدّث الصفحة')));
}

/* الصلاحيات: الشاشاتُ العشرُ نفسُها هي مصدرُ الحكم */
$analysisScreens = array('Finance/fin_ratios.php', 'Finance/fin_ratio_detail.php',
    'Finance/fin_ratio_targets.php', 'Finance/fin_early_warning.php',
    'Finance/fin_unit_economics.php', 'Finance/fin_contract_margin.php',
    'Finance/fin_project_pl.php', 'Finance/fin_cashflow_stmt.php',
    'Finance/fin_equity_stmt.php', 'Finance/fin_posting_matrix.php');
$canView = $is_super; $canWrite = $is_super;
foreach ($analysisScreens as $s) {
    $pp = check_page_permissions($conn, $s);
    if (!empty($pp['can_view'])) { $canView = true; }
    if (!empty($pp['can_add']) || !empty($pp['can_edit'])) { $canWrite = true; }
}
/* ◆ اعتمادُ حدِّ نسبةٍ للنائبِ الماليِّ حصرًا (M-10 §7-1) — والدورُ 17 يمثّله
   حتى تُبنى طبقةُ النواب (الحكمُ المؤقتُ المؤرخُ في update0011). */
$ppTargets = check_page_permissions($conn, 'Finance/fin_ratio_targets.php');
$canApproveTarget = $is_super || !empty($ppTargets['can_edit']);

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
$period = (string) ($_POST['period'] ?? date('Y-m'));

try {
    if (!preg_match('/^\d{4}-\d{2}$/', $period) && $action !== 'posting_matrix_set'
        && $action !== 'ratio_target_set' && $action !== 'ratio_drill') {
        throw new \RuntimeException('FIN-422: الفترةُ YYYY-MM إلزامًا');
    }
    switch ($action) {
        case 'ratio_compute': // fin.ratio.compute
            if (!$canWrite) { throw new \RuntimeException('FIN-403: الحسابُ للمالية'); }
            $scope = array();
            foreach (array('project_id', 'contract_id', 'equipment_id') as $k) {
                if (!empty($_POST[$k])) { $scope[$k] = (int) $_POST[$k]; }
            }
            $out = array('ok' => true) + FA::computeRatios($conn, $company_id, $period, $uid, $scope);
            break;

        case 'ratio_drill': // fin.ratio.drill — قارئٌ يعرض الحساباتِ ثم القيود
            if (!$canView) { throw new \RuntimeException('FIN-403: لا صلاحية'); }
            $code = (string) $_POST['ratio_code'];
            $st = $conn->prepare("SELECT * FROM fin_ratio_targets WHERE company_id = ? AND ratio_code = ?
                                   ORDER BY version_no DESC LIMIT 1");
            $st->bind_param('is', $company_id, $code);
            $st->execute();
            $t = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$t) { throw new \RuntimeException('FIN-404: نسبةٌ غيرُ معرَّفة'); }
            $accounts = array();
            foreach (array('numerator_codes' => 'بسط', 'denominator_codes' => 'مقام') as $f => $side) {
                foreach (array_filter(array_map('trim', explode(',', (string) $t[$f]))) as $c) {
                    $b = CoaService::balance($conn, $company_id, array($c), $period);
                    $acc = CoaService::account($conn, $company_id, $c);
                    $accounts[] = array('side' => $side, 'code' => $c,
                        'name' => $acc['name'] ?? '—', 'balance' => $b ? $b['balance'] : null,
                        'entries' => $b ? $b['n'] : 0);
                }
            }
            $out = array('ok' => true, 'ratio' => $t, 'accounts' => $accounts, 'period' => $period);
            break;

        case 'ratio_target_set': // fin.ratio.target — بسلطةِ النائب المالي
            if (!$canApproveTarget) {
                throw new \RuntimeException('FIN-403: اعتمادُ الحدِّ لنائبِ الرئيسِ للشؤون المالية');
            }
            $code = (string) $_POST['ratio_code'];
            $st = $conn->prepare("SELECT * FROM fin_ratio_targets WHERE company_id = ? AND ratio_code = ?
                                   ORDER BY version_no DESC LIMIT 1");
            $st->bind_param('is', $company_id, $code);
            $st->execute();
            $cur = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$cur) { throw new \RuntimeException('FIN-404: نسبةٌ غيرُ معرَّفة'); }
            $ver = (int) $cur['version_no'] + 1;
            $warn = $_POST['warn_value'] !== '' ? (float) $_POST['warn_value'] : null;
            $crit = $_POST['critical_value'] !== '' ? (float) $_POST['critical_value'] : null;
            $tgt  = $_POST['target_value'] !== '' ? (float) $_POST['target_value'] : null;
            $owner = trim((string) ($_POST['owner_role'] ?? $cur['owner_role']));
            $cad = trim((string) ($_POST['cadence'] ?? $cur['cadence']));
            $auth = 'اعتمادُ حدِّ نسبةٍ — نائبُ الرئيس للشؤون المالية والاستثمار · بصفة: ' . $actorCapacity;
            $parent = $cur['ratio_code'] . '@v' . $cur['version_no'];
            $st = $conn->prepare("INSERT INTO fin_ratio_targets
                (company_id, ratio_code, group_code, name_ar, name_en, formula_ar, numerator_codes,
                 denominator_codes, unit_ar, warn_op, warn_value, critical_value, target_value,
                 limit_text, cadence, owner_role, approved_by, approved_at, authority_ref,
                 parent_ref, version_no, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?)");
            $st->bind_param('isssssssssdddsssissii', $company_id, $code, $cur['group_code'],
                $cur['name_ar'], $cur['name_en'], $cur['formula_ar'], $cur['numerator_codes'],
                $cur['denominator_codes'], $cur['unit_ar'], $cur['warn_op'], $warn, $crit, $tgt,
                $cur['limit_text'], $cad, $owner, $uid, $auth, $parent, $ver, $uid);
            $st->execute();
            if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
            $st->close();
            $conn->query("UPDATE fin_ratio_targets SET active = 0
                           WHERE company_id = {$company_id} AND ratio_code = '"
                           . $conn->real_escape_string($code) . "' AND version_no < {$ver}");
            $out = array('ok' => true, 'ratio_code' => $code, 'version' => $ver);
            break;

        case 'unit_economics': // fin.unit.economics — قارئ
            if (!$canView) { throw new \RuntimeException('FIN-403: لا صلاحية'); }
            $eq = (int) ($_POST['equipment_id'] ?? 0);
            $scope = $eq > 0 ? array('equipment_id' => $eq) : array();
            $m = FA::margins($conn, $company_id, $period, $scope);
            $out = array('ok' => true, 'equipment_id' => $eq, 'period' => $period, 'margins' => $m);
            break;

        case 'contract_margin': // fin.contract.margin — قارئ
            if (!$canView) { throw new \RuntimeException('FIN-403: لا صلاحية'); }
            $ct = (int) ($_POST['contract_id'] ?? 0);
            $scope = $ct > 0 ? array('contract_id' => $ct) : array();
            if (!empty($_POST['business_model'])) { $scope['business_model'] = (string) $_POST['business_model']; }
            $out = array('ok' => true, 'contract_id' => $ct, 'period' => $period,
                'margins' => FA::margins($conn, $company_id, $period, $scope));
            break;

        case 'project_pl': // fin.project.pl
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التوليدُ للمالية'); }
            $out = array('ok' => true) + FA::generateProjectPL($conn, $company_id,
                (int) $_POST['project_id'], $period, $uid, (string) ($_POST['basis'] ?? ''));
            break;

        case 'cashflow_generate': // fin.cashflow.generate — تتوازن أو تُرفض
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التوليدُ للمدير المالي'); }
            $out = array('ok' => true) + FA::generateCashflow($conn, $company_id, $period, $uid);
            break;

        case 'equity_generate': // fin.equity.generate — تتوازن أو تُرفض
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التوليدُ للمدير المالي'); }
            $out = array('ok' => true) + FA::generateEquity($conn, $company_id, $period, $uid);
            break;

        case 'signal_raise': // fin.signal.raise — تُنشر للمخاطر
            if (!$canWrite) { throw new \RuntimeException('FIN-403: التقييمُ للمالية'); }
            $out = array('ok' => true) + FA::evaluateSignals($conn, $company_id, $period, $uid);
            break;

        case 'posting_matrix_set': // fin.posting.matrix — بمراجعةِ الحوكمة
            if (!$canWrite) { throw new \RuntimeException('FIN-403: المصفوفةُ للمدير المالي'); }
            $rule = (string) $_POST['rule_code'];
            $st = $conn->prepare("SELECT * FROM fin_posting_matrix WHERE company_id = ? AND rule_code = ?
                                   ORDER BY version_no DESC LIMIT 1");
            $st->bind_param('is', $company_id, $rule);
            $st->execute();
            $cur = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$cur) { throw new \RuntimeException('FIN-404: صفٌّ غيرُ معرَّف'); }
            $ver = (int) $cur['version_no'] + 1;
            $rev = trim((string) ($_POST['revenue_accounts'] ?? $cur['revenue_accounts']));
            $cost = trim((string) ($_POST['cost_accounts'] ?? $cur['cost_accounts']));
            // الحارس: كلُّ كودٍ يجب أن يكون حسابًا قانونيًّا يُقيَّد عليه
            foreach (array_filter(array_map('trim', preg_split('/[,·\s]+/u', $rev . ',' . $cost))) as $c) {
                if (!preg_match('/^\d{2,4}$/', $c)) { continue; }
                $acc = CoaService::account($conn, $company_id, $c);
                if (!$acc) { throw new \RuntimeException('COA-404: كودٌ خارجَ الشجرةِ القانونية — ' . $c); }
            }
            $st = $conn->prepare("INSERT INTO fin_posting_matrix
                (company_id, rule_code, dept_ar, source_event, revenue_accounts, cost_accounts,
                 required_dims, gate_ar, governing_rule, version_no, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param('issssssssii', $company_id, $rule, $cur['dept_ar'], $cur['source_event'],
                $rev, $cost, $cur['required_dims'], $cur['gate_ar'], $cur['governing_rule'], $ver, $uid);
            $st->execute();
            if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
            $st->close();
            $conn->query("UPDATE fin_posting_matrix SET active = 0
                           WHERE company_id = {$company_id} AND rule_code = '"
                           . $conn->real_escape_string($rule) . "' AND version_no < {$ver}");
            $out = array('ok' => true, 'rule_code' => $rule, 'version' => $ver);
            break;

        default:
            http_response_code(400);
            $out = array('ok' => false, 'code' => 'FIN-400', 'msg' => 'فعلٌ غيرُ معرَّف — لا زرَّ بلا عقد');
    }
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $code = 'FIN-500';
    if (preg_match('/^([A-Z]+-[A-Z0-9-]+):/u', $msg, $mm)) { $code = $mm[1]; }
    http_response_code(strpos($code, '403') !== false ? 403 : (strpos($code, '404') !== false ? 404 : 422));
    $out = array('ok' => false, 'code' => $code, 'msg' => $msg);
    if (isset($conn) && $conn instanceof \mysqli) {
        $st = $conn->prepare("INSERT INTO action_execution_log
            (company_id, action_code, person_id, subject_ref, result, denied_by_guard, at, ip)
            VALUES (?,?,?,?, 'denied', ?, NOW(), ?)");
        if ($st) {
            $ac = 'fin.analysis:' . $action;
            $subject = mb_substr($msg, 0, 118);
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $st->bind_param('isisss', $company_id, $ac, $uid, $subject, $code, $ip);
            @$st->execute();
            $st->close();
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
