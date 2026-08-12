<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-09-⑤ — اختبار قبول: المسيّرُ والكشف (ENT-01 §5 · §6 · §7 · PLAN-01 §6.1-⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/payroll_run_cycle_test.php
 *
 * معيارُ القبول النصّي: «فترةٌ كاملةٌ بلا تدخلٍ يدويٍّ و**صفرُ صفٍّ أحمرَ معتمد**».
 * و«الأحمرُ» معرَّفٌ في §7: «**صفٌّ بلا لقطةٍ صالحةٍ يظهر أحمرَ ولا يُعتمد**».
 *
 * ما يُثبته:
 *   ① قائمةُ السماح: كلُّ انتقالٍ خارجَ جدول §5 → 422 بالمسموح.
 *   ② **صفرُ صفٍّ أحمرَ معتمد** — ثلاثةُ ألوانٍ حمراءَ ترفض الاعتماد كلٌّ باسمه:
 *      سطرٌ لم يكتمل احتسابُه · مانعٌ مفتوح · لقطةٌ لا تطابق بصمتَها.
 *   ③ «لا اعتمادَ للذات» → 403 · و«الصرفُ بمرجعه» → 422 بلا مرجع.
 *   ④ **الدورةُ الكاملةُ بلا تدخلٍ يدوي**: فتحٌ ← لقطاتٌ ← مسارٌ زمني ← مقاصّةٌ
 *      ← مراجعةٌ ← اعتمادٌ ← صرفٌ ← إقفال.
 *   ⑤ **الأثرُ الواحد**: حدثٌ لكل (شخص × فترة) — وإعادةُ الاعتماد لا تكرر.
 *   ⑥ الكشفُ الفردي **بطبقاته** وكلُّ رقمٍ بمصدره · وسجلُّ المراجعة بمجاميعه.
 *   ⑦ المقفلةُ نهائيةٌ — «التصحيحُ بعدها بحدثٍ عاكسٍ لا بتعديل».
 *
 * البذرُ معزول: عقودٌ بوسم H095T وفترةُ 2050-07 — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: FIXA-0008-ب · FIXA-0008-ج
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/PayrollRunService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/TimePathService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/OffsetService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/PayrollStateMachine.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\TimePathService as TPS;
use App\Services\Payroll\OffsetService as OFS;
use App\Services\Payroll\PayrollStateMachine as PSM;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $AUTHOR = 999986; $REVIEWER = 999987; $APPROVER = 999988;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $AUTHOR, '', true));

const TAG5 = 'H095T المسيّرُ والكشف';
const S_FROM = '2050-07-01';
const S_TO   = '2050-07-31';

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . TAG5 . "'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM payroll_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_snapshots WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE d FROM payroll_deductions d JOIN payroll_runs r ON r.id = d.run_id
                   WHERE r.period_from = '" . S_FROM . "'");
    $conn->query("DELETE l FROM payroll_lines l JOIN payroll_runs r ON r.id = l.run_id
                   WHERE r.period_from = '" . S_FROM . "'");
    $conn->query("DELETE b FROM payroll_run_blocks b JOIN payroll_runs r ON r.id = b.run_id
                   WHERE r.period_from = '" . S_FROM . "'");
    $conn->query("DELETE FROM payroll_runs WHERE period_from = '" . S_FROM . "'");
    $conn->query("DELETE FROM employee_advances WHERE doc_ref LIKE 'H095T%'");
    $conn->query("DELETE FROM ems_business_events WHERE idempotency_key LIKE 'payroll_settlement:%:2050-07'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-09-⑤ — المسيّرُ والكشف ══\n");

// ═══ البذر ═══
head('البذر — ثلاثةُ موظفين دائمين');
$emps = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 3");
while ($x = $r->fetch_assoc()) { $emps[] = intval($x['id']); }
$pm = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_only' LIMIT 1")->fetch_assoc()['id']);
$mk = function ($empId, $basic) use ($conn, $gate, $CO, $AUTHOR, $pm) {
    $r = ECS::createHead($conn, $gate, $CO, array(
        'employee_id' => $empId, 'category' => 'permanent', 'pay_model_id' => $pm,
        'relation_type' => TAG5, 'start_date' => '2050-01-01', 'end_date' => '2050-12-31'), $AUTHOR);
    if (empty($r['ok'])) { return 0; }
    $cid = intval($r['id']);
    ECS::addComponent($conn, $gate, $CO, $cid, array(
        'component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => $basic,
        'periodicity' => 'monthly', 'valid_from' => '2050-01-01'), $AUTHOR);
    $conn->query("UPDATE employee_contracts SET state='active' WHERE id={$cid}");
    return $cid;
};
$C1 = $mk($emps[0], 2000); $C2 = $mk($emps[1], 3000); $C3 = $mk($emps[2], 1500);
check($C1 && $C2 && $C3, 'ثلاثةُ عقودٍ نافذةٍ (2000 · 3000 · 1500)');

// سلفةٌ للأول تُختبر في المقاصّة
$ra = OFS::openAdvance($conn, $gate, $CO, array(
    'person_id' => $emps[0], 'amount' => 400, 'doc_ref' => 'H095T/ADV', 'issued_date' => '2050-06-01',
    'installments_count' => 1, 'installment_amount' => 400), $AUTHOR);
OFS::approveAdvance($conn, $gate, $CO, intval($ra['advance_id']), $APPROVER);
ok('وسلفةُ 400 للأول معتمَدة');

// ═══ ④ الدورةُ الكاملة بلا تدخلٍ يدوي ═══
head('④ **الدورةُ الكاملةُ بلا تدخلٍ يدوي**');
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => S_FROM, 'period_to' => S_TO, 'category_filter' => 'permanent'), $AUTHOR);
check($r['ok'], 'الدورةُ فُتحت (Open)');
$RUN = intval($r['run_id']);

$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN, $AUTHOR);
check($r['ok'] && $r['state'] === 'Calculated', "ربطُ اللقطات ⇒ {$r['state']} ({$r['lines']} سطرًا)");
TPS::compute($conn, $gate, $CO, $RUN, $AUTHOR);
$ro = OFS::computeOffsets($conn, $gate, $CO, $RUN, $AUTHOR);
check($ro['ok'], "المقاصّة: {$ro['reason']}");

// ═══ ① قائمةُ السماح ═══
head('① قائمةُ السماح — جدولُ §5 حرفيًّا');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::PAID, $APPROVER, array('payment_ref' => 'X'));
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'المسموح') !== false,
      'قفزٌ من «محتسَبة» إلى «مدفوعة» → 422 **بالمسموح مسمًّى**');
check(PSM::canTransition(PSM::CLOSED, PSM::PAID) === false, 'والمقفلةُ لا تعود — نهائيةٌ بلا رجوع');

// ═══ ② صفرُ صفٍّ أحمرَ معتمد ═══
head('② **صفرُ صفٍّ أحمرَ معتمد** — ثلاثةُ ألوانٍ ترفض الاعتماد');

// (أ) سطرٌ لم يكتمل احتسابُه
$conn->query("UPDATE payroll_lines SET calc_state='pending_slice'
               WHERE run_id={$RUN} ORDER BY id LIMIT 1");
$red = PSM::redRows($gate, $RUN);
check(!$red['ok'] && $red['pending'] === 1, '(أ) سطرٌ `pending_slice` ⇒ أحمرُ «لم يكتمل»');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'صفرُ صفٍّ أحمرَ معتمد') !== false,
      'والمراجعةُ **مرفوضةٌ بنصّ المعيار**');
$conn->query("UPDATE payroll_lines SET calc_state='computed' WHERE run_id={$RUN}");

// (ب) مانعٌ مفتوح
$conn->query("INSERT INTO payroll_run_blocks (company_id, run_id, contract_id, person_id, kind,
              block_code, block_http, reason) VALUES ({$CO}, {$RUN}, {$C1}, {$emps[0]}, 'blocked',
              'test_block', 422, 'مانعٌ اصطناعيٌّ للاختبار')");
$red = PSM::redRows($gate, $RUN);
check(!$red['ok'] && $red['blocked'] === 1, '(ب) مانعٌ مفتوحٌ ⇒ أحمرُ «لا احتسابَ ناقصٌ صامت»');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
check(!$r['ok'] && $r['code'] === 422, 'والمراجعةُ مرفوضة');
$conn->query("DELETE FROM payroll_run_blocks WHERE run_id={$RUN} AND block_code='test_block'");

// (ج) لقطةٌ لا تطابق بصمتَها
$snapId = intval($conn->query("SELECT snapshot_id FROM payroll_lines WHERE run_id={$RUN} LIMIT 1")->fetch_assoc()['snapshot_id']);
$conn->query("UPDATE contract_snapshots SET snapshot_json=CONCAT(snapshot_json,' ') WHERE id={$snapId}");
$red = PSM::redRows($gate, $RUN);
check(!$red['ok'] && $red['tampered'] >= 1, '(ج) لقطةٌ لا تطابق بصمتَها ⇒ أحمرُ **تلاعبٍ مكشوف**');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
check(!$r['ok'] && $r['code'] === 422, 'والمراجعةُ مرفوضة');
$conn->query("UPDATE contract_snapshots SET snapshot_json=TRIM(TRAILING ' ' FROM snapshot_json) WHERE id={$snapId}");
$red = PSM::redRows($gate, $RUN);
check($red['ok'], 'وبعد إصلاح الثلاثة: **صفرُ أحمر**');

// ═══ ③ الحراس الشخصية ═══
head('③ «لا اعتمادَ للذات» و«الصرفُ بمرجعه»');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
check($r['ok'] && $r['state'] === 'Review', 'الانتقالُ إلى «مراجعة» مرّ');

$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::APPROVED, $AUTHOR);
check(!$r['ok'] && $r['code'] === 403, 'ومنشئُ الدورة يعتمدها → **403**');

$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::APPROVED, $APPROVER);
check($r['ok'] && $r['state'] === 'Approved', 'ومعتمِدٌ آخر: تمرّ ⇒ «معتمَدة»');

$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::PAID, $APPROVER, array('payment_ref' => ''));
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'مرجعُ الصرف') !== false,
      'وصرفٌ **بلا مرجع** → 422');

// ═══ ⑤ الأثرُ الواحد ═══
head('⑤ **الأثرُ الواحد**: حدثٌ لكل (شخص × فترة)');
$evt = intval($conn->query("SELECT COUNT(*) n FROM ems_business_events
    WHERE idempotency_key LIKE 'payroll_settlement:%:2050-07'")->fetch_assoc()['n']);
check($evt === 3, "ثلاثةُ أحداثٍ لثلاثة أشخاص ({$evt})");
// إعادةُ الاعتماد: رجوعٌ للمراجعة ثم اعتمادٌ ثانٍ
PSM::transition($conn, $gate, $CO, $RUN, PSM::CALCULATED, $REVIEWER);
PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
PSM::transition($conn, $gate, $CO, $RUN, PSM::APPROVED, $APPROVER);
$evt2 = intval($conn->query("SELECT COUNT(*) n FROM ems_business_events
    WHERE idempotency_key LIKE 'payroll_settlement:%:2050-07'")->fetch_assoc()['n']);
check($evt2 === 3, "وإعادةُ الاعتماد **لا تكرر** ({$evt2}) — مفتاحُ (شخص × فترة)");

// ═══ ⑥ الكشفُ والسجل ═══
head('⑥ الكشفُ الفردي بطبقاته وسجلُّ المراجعة');
$slip = PSM::payslip($gate, $RUN, $emps[0]);
check(isset($slip['layers']['الأجرُ والبدلات']) && abs($slip['gross'] - 2000.0) < 0.005,
      'كشفُ الأول: طبقةُ «الأجرُ والبدلات» بإجمالي 2000');
check(isset($slip['layers']['الخصوماتُ بمراجعها']) && abs($slip['deductions'] - 400.0) < 0.005,
      'وطبقةُ «الخصوماتُ بمراجعها» = 400 (السلفة)');
check(abs($slip['net'] - 1600.0) < 0.005, 'والصافي = 2000 − 400 = 1600.00');
check(count($slip['snapshot_ids']) === 1,
      'وكلُّ سطرٍ يشير إلى لقطته — «تبويبُ اللقطة يعرض القيمَ التي احتُسب بها»');

$reg = PSM::register($gate, $RUN);
check(count($reg) === 3, 'سجلُّ المراجعة: صفٌّ لكل شخص (3)');
$sumNet = 0.0; $reds = 0;
foreach ($reg as $rr) { $sumNet += (float) $rr['net']; $reds += (int) $rr['red_rows']; }
check(abs($sumNet - 6100.0) < 0.005, 'ومجموعُ الصافي = (2000−400) + 3000 + 1500 = 6100.00');
check($reds === 0, 'وصفرُ صفٍّ أحمرَ في السجل — «ولا يُعتمد» تحقّق فعلًا');

// ═══ ⑦ الصرفُ والإقفال ═══
head('⑦ الصرفُ فالإقفال — والمقفلةُ نهائية');
$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::PAID, $APPROVER, array('payment_ref' => 'PAY-H095T-1'));
check($r['ok'] && $r['state'] === 'Paid', 'الصرفُ بمرجعه ⇒ «مدفوعة»');
$note = $conn->query("SELECT note FROM payroll_runs WHERE id={$RUN}")->fetch_assoc();
check(strpos($note['note'], 'PAY-H095T-1') !== false, 'ومرجعُ الصرف مسجَّلٌ على الدورة');

$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::CLOSED, $APPROVER);
check($r['ok'] && $r['state'] === 'Closed', 'والإقفال ⇒ «مقفلة»');

$r = PSM::transition($conn, $gate, $CO, $RUN, PSM::REVIEW, $REVIEWER);
check(!$r['ok'] && $r['code'] === 422,
      'والمقفلةُ **لا تُفتح** — «التصحيحُ بعدها بحدثٍ عاكسٍ لا بتعديل» (§5)');

$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN, $AUTHOR);
check(!$r['ok'] && $r['code'] === 423, 'وإعادةُ الربط على مقفلةٍ → 423 — لا يُمسّ محسوبٌ أُقفل');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
