<?php
/**
 * tests/dec01_decisions_test.php — قرارات المالك التسعة (DEC-01 الموقَّعة)
 * ═══════════════════════════════════════════════════════════════════════════
 * D1 حد الأثر الكبير: 5٪ أو 10,000$ أيهما أقل + الحقوق الجوهرية السبعة ·
 * D2 مدير الحركة: سقف نطاقي ونائب بمدة إلزامية (لا نيابة مفتوحة) ·
 * D3 needs_gm محسوب في الطلب قبل الإرسال · D4 تفويض لا يغطي النطاق ⇒ 403 ·
 * D5 الدورية: يومية إلزامية للاستعداد المفوتر + الشهرية بشرطها + الرجوع الآلي ·
 * D6 حدا حماية الصافي (ثلث + نصف) والتجاوز بقرارين · D7 الطارئة ⇒ A1 احتياطًا ·
 * D8 A1 بلا رصيد ⇒ معاملة «بلا أجر» · D9 المزامنة المتأخرة تُعلَّم لا تُرفض.
 * التشغيل: php tests/dec01_decisions_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'DEC01 test');
require_once dirname(__DIR__) . '/app/Services/Governance/UnitStateChangeService.php';
require_once dirname(__DIR__) . '/app/Services/Policy/PeriodicityService.php';
require_once dirname(__DIR__) . '/app/Services/Workforce/AttendanceService.php';

use App\Services\Governance\UnitStateChangeService as USC;
use App\Services\Policy\PeriodicityService as PS;
use App\Services\Workforce\AttendanceService as AS_;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO = 4;
$P1 = 999401; $P2 = 999402; $SITE_A = 999771; $SITE_B = 999772;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM signing_authorities WHERE doc_ref LIKE 'DEC1T%'");
    $conn->query("DELETE a FROM change_approvals a JOIN unit_state_changes c ON c.chg_id=a.chg_id WHERE c.reason LIKE '%DEC1T%'");
    $conn->query("DELETE FROM unit_state_changes WHERE reason LIKE '%DEC1T%'");
    $conn->query("DELETE FROM approval_signatures WHERE document_type='unit_state_change' AND company_id=4 AND person_id IN (999401,999402)");
    $conn->query("DELETE FROM chain_objections WHERE line_ref LIKE 'DEC1T%'");
    $conn->query("DELETE ac FROM approval_chains ac JOIN dept_policies p ON p.policy_id=ac.policy_id WHERE p.name_ar LIKE '%DEC1T%'");
    $conn->query("DELETE FROM dept_policies WHERE name_ar LIKE '%DEC1T%'");
    $conn->query("DELETE FROM attendance_days WHERE person_id IN (999401,999402)");
    $conn->query("DELETE FROM attendance_sweep_notices WHERE person_id IN (999401,999402)");
    $conn->query("DELETE FROM deduction_proposals WHERE person_id IN (999401,999402)");
    $conn->query("DELETE FROM shift_period_logs WHERE equipment_id = 999889");
    $conn->query("DELETE FROM fin_notifications WHERE title LIKE '%999401%' OR title LIKE '%999402%' OR title LIKE '%DEC1T%'");
};
register_shutdown_function($teardown);
$teardown();

head('D1 — حد الأثر الكبير: 5٪ أو 10,000$ أيهما أقل + الحقوق السبعة');
$r = USC::assessGmNeed(400, 10000, 400, '');
check(!$r['needs_gm'], '400 على مرجع 10,000 (4٪ < 5٪ ودون 10k$) — لا إدارة عامة');
$r = USC::assessGmNeed(600, 10000, 600, '');
check($r['needs_gm'], '600 على مرجع 10,000 (6٪ > 5٪) — تلزم الإدارة العامة');
$r = USC::assessGmNeed(12000, 400000, 12000, '');
check($r['needs_gm'], '12,000$ على مرجع 400,000 (3٪ فقط لكن > 10k$) — أيهما أقل يمسك');
$r = USC::assessGmNeed(50, 100000, 50, 'client_billing_attribution');
check($r['needs_gm'], 'حق جوهري (إسناد يمس فوترة العميل) بمبلغ 50 — مهما صغر المبلغ');
$r = USC::assessGmNeed(50, 100000, 50, 'employee_waiver_3days');
check($r['needs_gm'], 'إعفاء موظف فوق أجر ثلاثة أيام — سابع الحقوق الجوهرية');
check(count(USC::MATERIAL_RIGHTS) === 7, 'الحالات الجوهرية سبع بالضبط — قائمة محكومة لا مفتوحة');

head('D2 — تعيين مدير الحركة: سقف نطاقي · النائب بمدة إلزامية');
$r = USC::appointMovement($conn, $CO, array('person_id' => $P1, 'entity_id' => 1, 'site_id' => $SITE_A,
    'valid_from' => date('Y-m-d'), 'doc_ref' => 'DEC1T-APPOINT-1'));
check($r['ok'] && $r['auth_id'] > 0, 'الأصيل معيَّن على الموقع أ — سقفه نطاقي لا نقدي');
$principalAuth = $r['auth_id'];
$q = $conn->query("SELECT amount_cap, auth_type, scope_type FROM signing_authorities WHERE auth_id={$principalAuth}")->fetch_assoc();
check($q['amount_cap'] === null && $q['auth_type'] === 'operational' && $q['scope_type'] === 'site',
      'بلا سقف نقدي (يعتمد وقوع الواقعة لا قيمتها) — تشغيلي بنطاق موقع');
$r = USC::appointMovement($conn, $CO, array('person_id' => $P2, 'entity_id' => 1, 'site_id' => $SITE_A,
    'valid_from' => date('Y-m-d'), 'doc_ref' => 'DEC1T-DEP-OPEN', 'delegated_from_auth_id' => $principalAuth));
check(!$r['ok'] && $r['code'] === 422, 'نائب بلا مدة ⇒ 422 — لا تفويض شفوي ولا مفتوح المدة');
$r = USC::appointMovement($conn, $CO, array('person_id' => $P2, 'entity_id' => 1, 'site_id' => $SITE_A,
    'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+30 days')),
    'doc_ref' => 'DEC1T-DEP-1', 'delegated_from_auth_id' => $principalAuth));
check($r['ok'], 'وبمدة مكتوبة يُعيَّن — فلا تتوقف السلسلة بغياب الأصيل');

head('D3·D4 — الطلب يحسب الإدارة العامة قبل الإرسال · والنطاق يُفحص');
$mk = function ($site, $extra = array()) use ($conn, $CO) {
    return USC::request($conn, $CO, array_merge(array(
        'scope_type' => 'site', 'scope_id' => $site, 'date_from' => '2026-08-10', 'date_to' => '2026-08-12',
        'field_changed' => 'time_state', 'value_before' => 'breakdown', 'value_after' => 'standby',
        'reason' => 'DEC1T اختبار', 'doc_ref' => 'MSG-1', 'requested_by' => 77,
        'estimated_impact' => array('client' => '+18h'),
    ), $extra));
};
$r = $mk($SITE_A, array('impact_amount' => 600, 'monthly_ref_value' => 10000));
check($r['ok'] && $r['needs_gm'], 'D3: طلب بأثر 6٪ — needs_gm محسوب في الطلب قبل الإرسال لا بعده');
$chgGm = $r['chg_id'];
$r = $mk($SITE_A, array('impact_amount' => 100, 'monthly_ref_value' => 10000));
check($r['ok'] && !$r['needs_gm'], 'وطلب بأثر 1٪ — بلا إدارة عامة');
$chgA = $r['chg_id'];
$r = $mk($SITE_B, array('impact_amount' => 100, 'monthly_ref_value' => 10000));
$chgB = $r['chg_id'];

$r = USC::approveStep($conn, $CO, $chgB, 1, $P1);
check(!$r['ok'] && $r['code'] === 403, 'D4: تفويض الأصيل على الموقع أ لا يغطي طلب الموقع ب ⇒ 403 (السقف نطاقي)');
$r = USC::approveStep($conn, $CO, $chgA, 1, $P1);
check($r['ok'], 'وطلب الموقع أ يعتمده صاحب نطاقه');
$r = USC::approveStep($conn, $CO, $chgGm, 1, $P2);
check($r['ok'], 'والنائب المفوَّض بمدته يعتمد في نطاقه — السلسلة لا تتوقف بالغياب');

head('D5 — الدورية: يومية إلزامية للاستعداد المفوتر · الشهرية بشرطها · الرجوع الآلي');
$r = PS::resolve('hourly', 'monthly');
check($r['periodicity'] === 'daily' && $r['forced'], 'عقد ساعة طلب شهرية ⇒ **يومية إلزاميًّا** — إثبات الاستعداد يتعذر بعد أيام');
$r = PS::resolve('quantity', '');
check($r['periodicity'] === 'weekly', 'ولا نص ⇒ أسبوعية — الافتراض المعلن');
$conn->query("INSERT INTO dept_policies (company_id, domain, name_ar, scope_type, scope_id, valid_from) VALUES (4, 'suppliers', 'سياسة DEC1T', 'project', 999770, CURDATE())");
$POL = intval($conn->insert_id);
$conn->query("INSERT INTO approval_chains (policy_id, seq_no, approver_role, periodicity) VALUES ({$POL}, 1, 'site', 'weekly'), ({$POL}, 2, 'finance', 'weekly')");
$conn->query("INSERT INTO chain_objections (company_id, unit_id, line_ref, domain, reason_code, policy_id, person_id) VALUES (4, 1, 'DEC1T-L1', 'suppliers', 'out_of_container', {$POL}, 2)");
$r = PS::requestMonthly($conn, $CO, $POL, 1);
check(!$r['ok'] && $r['code'] === 422, 'الشهرية مرفوضة — الشرط ثلاثة أشهر بصفر اعتراض والمرصود 1');
$conn->query("INSERT INTO chain_objections (company_id, unit_id, line_ref, domain, reason_code, policy_id, person_id) VALUES (4, 2, 'DEC1T-L2', 'suppliers', 'charge_no_doc', {$POL}, 3)");
$n = PS::sweepAutoRevert($conn);
check($n >= 1, 'اعتراضان في شهر ⇒ رجوع آلي (' . $n . ' سياسة)');
$q = $conn->query("SELECT COUNT(*) c FROM approval_chains WHERE policy_id={$POL} AND periodicity='daily'")->fetch_assoc();
check((int) $q['c'] === 2, 'وحلقتا السياسة عادتا لليومية — وبإشعار لا بصمت');

head('D6 — حدا حماية الصافي: الثلث للاختياري والنصف للمجموع');
// جزاء (غير اختياري) 1200 على صافي 3000 — يُرحَّل كاملًا (عدم استحقاق لا سلفة)
AS_::proposeDeduction($gate, $CO, array('person_id' => $P1, 'period' => '2026-08', 'source' => 'penalty', 'source_ref' => 'PEN:DEC1T', 'proposed_amount' => 1200.00));
AS_::proposeDeduction($gate, $CO, array('person_id' => $P1, 'period' => '2026-08', 'source' => 'advance_installment', 'source_ref' => 'ADV:DEC1T', 'proposed_amount' => 400.00, 'is_voluntary' => 1));
$ids = array();
$q = $conn->query("SELECT ded_id FROM deduction_proposals WHERE person_id={$P1} ORDER BY ded_id");
while ($row = $q->fetch_assoc()) { $ids[] = (int) $row['ded_id']; }
$conn->query("UPDATE deduction_proposals SET state='Approved' WHERE person_id={$P1}");
$r = AS_::postDeduction($gate, $CO, $ids[0], 'GOV-DEC1T-1', 55, 3000);
check($r['ok'] && $r['posted_amount'] === 1200.0, 'الجزاء 1200 يُرحَّل كاملًا — خارج الحد الأول (عقوبة لا سلفة)');
$r = AS_::postDeduction($gate, $CO, $ids[1], 'GOV-DEC1T-2', 55, 3000);
check($r['ok'] && $r['posted_amount'] === 300.0,
      'والاختياري 400 يُقص إلى 300 — الحد الثاني: المجموع ≤ نصف الصافي (1500) والمرحَّل 1200');
$q = $conn->query("SELECT COUNT(*) c FROM fin_notifications WHERE title LIKE '%جدول%' AND title LIKE '%2026-08%'")->fetch_assoc();
check((int) $q['c'] >= 1, 'وإشعار العامل بجدوله الجديد — لا تخفيض بلا علمه');
// التجاوز: بقرار الإدارة العامة + طلب مكتوب من العامل — كلاهما
AS_::proposeDeduction($gate, $CO, array('person_id' => $P2, 'period' => '2026-08', 'source' => 'advance_installment', 'source_ref' => 'ADV:DEC1T2', 'proposed_amount' => 2000.00, 'is_voluntary' => 1));
$q = $conn->query("SELECT ded_id FROM deduction_proposals WHERE person_id={$P2}")->fetch_assoc();
$D2 = (int) $q['ded_id'];
$conn->query("UPDATE deduction_proposals SET state='Approved' WHERE ded_id={$D2}");
$r = AS_::postDeduction($gate, $CO, $D2, 'GOV-DEC1T-3', 55, 3000, array('gm_ref' => 'GM-DEC-9'));
check(!$r['ok'] && $r['code'] === 422, 'تجاوز بقرار الإدارة العامة وحده ⇒ 422 — طلب العامل المكتوب إلزامي ولا يُفرض عليه');
$r = AS_::postDeduction($gate, $CO, $D2, 'GOV-DEC1T-3', 55, 3000, array('gm_ref' => 'GM-DEC-9', 'worker_request_ref' => 'REQ-77'));
check($r['ok'] && $r['posted_amount'] === 2000.0, 'وبالقرارين معًا يُرحَّل فوق الحدين — موثَّقًا بمرجعيهما');

head('D7 — الطارئة EM: خمسة أيام عمل ثم A1 احتياطًا لا A2');
$emDate = date('Y-m-d', strtotime('-12 days'));
$conn->query("INSERT INTO attendance_days (company_id, person_id, att_date, status_code) VALUES (4, {$P1}, '{$emDate}', 'EM')");
$n = AS_::sweepEmergencyPending($conn, $gate, $CO);
check($n >= 1, 'طارئة عمرها 12 يومًا بلا قرار — كُنست (' . $n . ')');
$q = $conn->query("SELECT status_code, auto_reclassified FROM attendance_days WHERE person_id={$P1} AND att_date='{$emDate}'")->fetch_assoc();
check($q['status_code'] === 'A1' && (int) $q['auto_reclassified'] === 1, 'صارت **A1 احتياطًا لا A2** — الأصل براءة الذمة في الطارئ');
$emToday = date('Y-m-d', strtotime('-2 days'));
$conn->query("INSERT INTO attendance_days (company_id, person_id, att_date, status_code) VALUES (4, {$P2}, '{$emToday}', 'EM')");
AS_::sweepEmergencyPending($conn, $gate, $CO);
$q = $conn->query("SELECT status_code FROM attendance_days WHERE person_id={$P2} AND att_date='{$emToday}'")->fetch_assoc();
check($q['status_code'] === 'EM', 'وطارئة داخل المهلة معلَّقة كما هي — القرار للموارد البشرية');

head('D8 — A1 برصيد نافد يُعامل «بلا أجر» لا خصمًا');
$r = AS_::impactOf($gate, $CO, 'A1', array('leave_balance_days' => 3));
check($r['ok'] && $r['pay'] !== 'stops_accrual', 'A1 برصيد ⇒ من الرصيد لا من الراتب');
$r = AS_::impactOf($gate, $CO, 'A1', array('leave_balance_days' => 0));
check($r['ok'] && $r['pay'] === 'stops_accrual', 'A1 بلا رصيد ⇒ معاملة «بلا أجر» (عدم استحقاق لا عقوبة)');

head('D9 — المزامنة المتأخرة تُعلَّم لا تُرفض');
$r = AS_::logPeriod($gate, $CO, array('work_date' => date('Y-m-d', strtotime('-3 days')), 'equipment_id' => 999889,
    'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => $P1, 'qty' => 10, '_sync' => true), 1);
check($r['ok'] && $r['synced_late'] === 1, 'مزامنة بعد 3 أيام ⇒ مقبولة معلَّمة «مزامَن متأخر»');
$r = AS_::logPeriod($gate, $CO, array('work_date' => date('Y-m-d'), 'equipment_id' => 999889,
    'shift_no' => 1, 'period_no' => 2, 'operator_person_id' => $P1, 'qty' => 10, '_sync' => true), 1);
check($r['ok'] && $r['synced_late'] === 0, 'ومزامنة اليوم بلا وسم');
$r = AS_::logPeriod($gate, $CO, array('work_date' => date('Y-m-d', strtotime('-3 days')), 'equipment_id' => 999889,
    'shift_no' => 2, 'period_no' => 1, 'operator_person_id' => $P1, 'qty' => 10), 1);
check($r['ok'] && $r['synced_late'] === 0, 'والإدخال المكتبي المتأخر (غير المزامنة) بلا وسم — الوسم لقناة الجوال');

echo "\n═══ dec01_decisions_test: {$PASS} ✔ · {$FAIL} ✘ ═══\n";
exit($FAIL === 0 ? 0 : 1);
