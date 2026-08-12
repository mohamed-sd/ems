<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-09-④ — اختبار قبول: المقاصّةُ والسلف (ENT-01 §4 · §8-E4 · PLAN-01 §6.1-④)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/payroll_offset_test.php
 *
 * معيارُ القبول النصّي: «**لا خصمَ بلا مستندٍ يُفتح، وتعارضُ حدِّ الحماية يعيد
 * الجدولة**».
 *
 * الفترة: 2049-05-01 → 2049-05-31. الحساباتُ اليدويةُ في النصّ لا في الكود:
 *
 *  (أ) إجماليٌّ 1000 · سلفةٌ 900 بقسط 700 · **حدُّ الحماية 50٪**
 *      المسموحُ خصمُه = 1000 × (1 − 50٪)      =  500.00
 *      المخصومُ فعلًا  = min(700، 500)         =  500.00  ← **قُصّ**
 *      الباقي 200 **يُرحَّل** والرصيدُ = 900 − 500 = 400.00
 *      الصافي = 1000 − 500                     =  500.00
 *
 *  (ب) إجماليٌّ 1000 · سلفةٌ 300 بقسط 300 · الحدُّ نفسُه
 *      المسموح 500 ≥ 300 ⇒ يُخصم كاملًا       =  300.00
 *      الصافي = 1000 − 300                     =  700.00 · والسلفةُ **settled**
 *
 * البذرُ معزول: عقودٌ بوسم H094T وسلفٌ بمستندات H094T — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
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

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\TimePathService as TPS;
use App\Services\Payroll\OffsetService as OFS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $AUTHOR = 999974; $APPROVER = 999975;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $AUTHOR, '', true));

const TAG4 = 'H094T المقاصّةُ والسلف';
const R_FROM = '2049-05-01';
const R_TO   = '2049-05-31';

$prevSetting = null;
$r = $conn->query("SELECT id, protection_percent FROM payroll_settings WHERE company_id={$CO} LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $prevSetting = $x; }

$teardown = function () use ($conn, $CO, $prevSetting) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . TAG4 . "'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM payroll_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_snapshots WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE d FROM payroll_deductions d JOIN payroll_runs r ON r.id = d.run_id
                   WHERE r.period_from = '" . R_FROM . "'");
    $conn->query("DELETE l FROM payroll_lines l JOIN payroll_runs r ON r.id = l.run_id
                   WHERE r.period_from = '" . R_FROM . "'");
    $conn->query("DELETE b FROM payroll_run_blocks b JOIN payroll_runs r ON r.id = b.run_id
                   WHERE r.period_from = '" . R_FROM . "'");
    $conn->query("DELETE FROM payroll_runs WHERE period_from = '" . R_FROM . "'");
    $conn->query("DELETE FROM employee_advances WHERE doc_ref LIKE 'H094T%'");
    // إعادةُ إعداد الحماية إلى ما كان — الاختبارُ لا يترك أثرًا على الإعداد
    if ($prevSetting === null) {
        $conn->query("DELETE FROM payroll_settings WHERE company_id={$CO}");
    } else {
        $p = $prevSetting['protection_percent'] === null ? 'NULL' : floatval($prevSetting['protection_percent']);
        $conn->query("UPDATE payroll_settings SET protection_percent={$p} WHERE company_id={$CO}");
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-09-④ — المقاصّةُ والسلف ══\n");

// ═══ البذر ═══
head('البذر — موظفان بإجماليٍّ 1000 لكلٍّ');
$emps = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 2");
while ($x = $r->fetch_assoc()) { $emps[] = intval($x['id']); }
list($A, $B) = $emps;
$pm = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_only' LIMIT 1")->fetch_assoc()['id']);

$mk = function ($empId) use ($conn, $gate, $CO, $AUTHOR, $pm) {
    $r = ECS::createHead($conn, $gate, $CO, array(
        'employee_id' => $empId, 'category' => 'permanent', 'pay_model_id' => $pm,
        'relation_type' => TAG4, 'start_date' => '2049-01-01', 'end_date' => '2049-12-31'), $AUTHOR);
    if (empty($r['ok'])) { return 0; }
    $cid = intval($r['id']);
    ECS::addComponent($conn, $gate, $CO, $cid, array(
        'component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 1000,
        'periodicity' => 'monthly', 'valid_from' => '2049-01-01'), $AUTHOR);
    $conn->query("UPDATE employee_contracts SET state='active' WHERE id={$cid}");
    return $cid;
};
$CA = $mk($A); $CB = $mk($B);
check($CA && $CB, 'عقدان نافذان بأساسيٍّ ثابتٍ 1000');

// ═══ ① بوابةُ السلفيات — «لا خصمَ بلا مستند» يبدأ من السلفة ═══
head('① بوابةُ السلفيات — «كلٌّ بمستنده وجدولِ استرداده»');
$r = OFS::openAdvance($conn, $gate, $CO, array(
    'person_id' => $A, 'amount' => 900, 'doc_ref' => '', 'issued_date' => '2049-04-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'مستندُ الصرف') !== false,
      '**سلفةٌ بلا مستندٍ → 422**: مالٌ يخرج بلا سندٍ لا يُخصم لاحقًا');

$r = OFS::openAdvance($conn, $gate, $CO, array(
    'person_id' => $A, 'amount' => 0, 'doc_ref' => 'H094T/1', 'issued_date' => '2049-04-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'ومبلغٌ صفريٌّ → 422');

/* ══ الحكم: «الرصيدُ لا ينحرف عن حركته» — ويُقاس **بالضمانةِ لا بأثرِها الجانبي**.
     كان الفحصُ يشترط أن **يُرفض الصفُّ كلُّه** عند كتابةِ العمودِ المولَّد. والمقيسُ
     أن مارياDB **لا ترفض** بل **تتجاهل القيمةَ المُمرَّرةَ وتخزّن المحسوبة**
     (`balance` = `amount` − `recovered`): أُدرج رصيدٌ كاذبٌ 9999 على 500−100
     فخُزِّن **400**. والرفضُ يقع في الوضعِ الصارمِ وحدَه، و`sql_mode` **فارغٌ**
     في هذا الخادم.
     ⇒ فالحكمُ **محفوظٌ فعلًا** والفاحصُ كان يقيس آليةً لا ضمانة. وقياسُ الضمانةِ
       أقوى: يُمرَّر كذبٌ ويُثبَت أن المخزَّنَ هو المحسوبُ لا الكذب — وهذا يبقى
       صادقًا في الوضعين الصارمِ والمتساهلِ معًا.
     (و`sql_mode` الفارغُ ديْنٌ يُعلَن: تشديدُه قرارُ بيئةٍ أثرُه واسع.) */
$conn->query("INSERT INTO employee_advances (company_id, person_id, advance_type, amount, doc_ref,
              issued_date, installments_count, installment_amount, recovered, state, balance)
              VALUES ({$CO}, {$A}, 'cash', 500, 'H094T/RAW', '2049-04-01', 1, 500, 100, 'active', 9999)");
$raw = $conn->query("SELECT amount, recovered, balance FROM employee_advances
                      WHERE doc_ref='H094T/RAW'")->fetch_assoc();
$rawOk = $raw
      && abs((float) $raw['balance'] - ((float) $raw['amount'] - (float) $raw['recovered'])) < 0.005
      && abs((float) $raw['balance'] - 9999.0) > 0.005;
check($rawOk, 'الرصيدُ لا ينحرف عن حركته: مُرِّر 9999 فخُزِّن '
      . ($raw ? number_format((float) $raw['balance'], 2) : '—') . ' = المبلغُ − المستردّ (عمودٌ مولَّد)');
$conn->query("DELETE FROM employee_advances WHERE doc_ref='H094T/RAW'");

// السلفتان السليمتان
$r = OFS::openAdvance($conn, $gate, $CO, array(
    'person_id' => $A, 'amount' => 900, 'doc_ref' => 'H094T/A-900', 'issued_date' => '2049-04-01',
    'installments_count' => 2, 'installment_amount' => 700), $AUTHOR);
check($r['ok'], '(أ) سلفةُ 900 بقسط 700 بمستندها');
$ADV_A = intval($r['advance_id']);

$r = OFS::openAdvance($conn, $gate, $CO, array(
    'person_id' => $B, 'amount' => 300, 'doc_ref' => 'H094T/B-300', 'issued_date' => '2049-04-01',
    'installments_count' => 1, 'installment_amount' => 300), $AUTHOR);
$ADV_B = intval($r['advance_id']);
check($r['ok'], '(ب) سلفةُ 300 بقسط 300 بمستندها');

// ═══ ② الاعتماد — «من أنشأ لا يعتمد» ═══
head('② «من أنشأ لا يعتمد»');
$r = OFS::approveAdvance($conn, $gate, $CO, $ADV_A, $AUTHOR);
check(!$r['ok'] && $r['code'] === 403, 'منشئُ السلفة يعتمدها → **403**');
$r = OFS::approveAdvance($conn, $gate, $CO, $ADV_A, $APPROVER);
check($r['ok'], 'ومعتمِدٌ آخر: تمرّ');
OFS::approveAdvance($conn, $gate, $CO, $ADV_B, $APPROVER);

// ═══ ③ الدورة والمقاصّة ═══
head('③ الدورةُ والمقاصّة بحدِّ حمايةٍ **مقرَّرٍ** 50٪');
$conn->query("INSERT INTO payroll_settings (company_id, protection_percent, note)
              VALUES ({$CO}, 50.00, 'H094T') ON DUPLICATE KEY UPDATE protection_percent=50.00");
check(abs(OFS::protectionPercent($gate) - 50.0) < 0.001, 'الحدُّ المقرَّر يُقرأ 50٪');

$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => R_FROM, 'period_to' => R_TO, 'category_filter' => 'permanent'), $AUTHOR);
$RUN = intval($r['run_id']);
check($r['ok'], 'الدورةُ فُتحت');
PRS::bindSnapshots($conn, $gate, $CO, $RUN, $AUTHOR);
TPS::compute($conn, $gate, $CO, $RUN, $AUTHOR);
$r = OFS::computeOffsets($conn, $gate, $CO, $RUN, $AUTHOR);
check($r['ok'], "المقاصّة: {$r['reason']}");

// ═══ ④ المقارنةُ اليدوية ═══
head('④ **تعارضُ حدِّ الحماية يعيد الجدولة**');
$dedA = $conn->query("SELECT amount, requested_amount, rescheduled, doc_ref, note FROM payroll_deductions
    WHERE run_id={$RUN} AND person_id={$A}")->fetch_assoc();
check($dedA && abs(floatval($dedA['amount']) - 500.0) < 0.005
      && abs(floatval($dedA['requested_amount']) - 700.0) < 0.005,
      '(أ) المستحقُّ 700 والمخصومُ **500** (= 1000 × (1−50٪)) — القصُّ بالحدّ');
check($dedA && intval($dedA['rescheduled']) === 1 && strpos($dedA['note'], 'يُرحَّل') !== false,
      'والسطرُ موسومٌ `rescheduled` **بسببه المكتوب** — الباقي 200 يُرحَّل لا يُلغى');
check($dedA && strpos($dedA['doc_ref'], 'H094T/A-900') !== false,
      'وسطرُ الخصم **يرث مستندَ سلفته** — لا خصمَ يتيم');

$advA = $conn->query("SELECT recovered, balance, state FROM employee_advances WHERE id={$ADV_A}")->fetch_assoc();
check(abs(floatval($advA['recovered']) - 500.0) < 0.005 && abs(floatval($advA['balance']) - 400.0) < 0.005
      && $advA['state'] === 'active',
      'ورصيدُ السلفة 900 − 500 = **400** (مولَّدًا) وحالتُها ما زالت نشطة');

$netA = OFS::netOf($gate, $RUN, $A);
check(abs($netA - 500.0) < 0.005, '(أ) الصافي = 1000 − 500 = 500.00 — آليًّا ' . number_format($netA, 2));

$dedB = $conn->query("SELECT amount, rescheduled FROM payroll_deductions
    WHERE run_id={$RUN} AND person_id={$B}")->fetch_assoc();
check($dedB && abs(floatval($dedB['amount']) - 300.0) < 0.005 && intval($dedB['rescheduled']) === 0,
      '(ب) المسموح 500 ≥ 300 ⇒ **يُخصم كاملًا** بلا ترحيل');
$netB = OFS::netOf($gate, $RUN, $B);
check(abs($netB - 700.0) < 0.005, '(ب) الصافي = 1000 − 300 = 700.00 — آليًّا ' . number_format($netB, 2));
$advB = $conn->query("SELECT balance, state FROM employee_advances WHERE id={$ADV_B}")->fetch_assoc();
check(abs(floatval($advB['balance']) - 0.0) < 0.005 && $advB['state'] === 'settled',
      'وسلفةُ (ب) صارت **settled** برصيدٍ صفر');

// ═══ ⑤ العطالة — «فلا يُخصم بندٌ مرتين» ═══
head('⑤ «فلا يُخصم بندٌ مرتين» (UQ) — والعطالةُ لا تضاعف استردادًا');
$r2 = OFS::computeOffsets($conn, $gate, $CO, $RUN, $AUTHOR);
$advA2 = $conn->query("SELECT recovered, balance FROM employee_advances WHERE id={$ADV_A}")->fetch_assoc();
check(abs(floatval($advA2['recovered']) - 500.0) < 0.005 && abs(floatval($advA2['balance']) - 400.0) < 0.005,
      'إعادةُ المقاصّة: المستردُّ ما زال 500 والرصيدُ 400 — **لا تضاعف**');
$cntA = intval($conn->query("SELECT COUNT(*) n FROM payroll_deductions
    WHERE run_id={$RUN} AND person_id={$A}")->fetch_assoc()['n']);
check($cntA === 1, 'وسطرُ الخصم واحدٌ لا اثنان');
$conn->query("INSERT INTO payroll_deductions (company_id, run_id, person_id, source_type, source_id,
              amount, doc_ref) VALUES ({$CO}, {$RUN}, {$A}, 'advance', {$ADV_A}, 100, 'H094T/DUP')");
$cntDup = intval($conn->query("SELECT COUNT(*) n FROM payroll_deductions
    WHERE run_id={$RUN} AND person_id={$A}")->fetch_assoc()['n']);
check($cntDup === 1, 'وخصمٌ ثانٍ للمصدر نفسِه **ترفضه القاعدة** (UQ) — لا بفحصٍ يُنسى');

// ═══ ⑥ الفترةُ التالية تلتقط المرحَّل ═══
head('⑥ المرحَّلُ يُخصم في فترته');
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2049-06-01', 'period_to' => '2049-06-30', 'category_filter' => 'permanent'), $AUTHOR);
$RUN2 = intval($r['run_id']);
PRS::bindSnapshots($conn, $gate, $CO, $RUN2, $AUTHOR);
TPS::compute($conn, $gate, $CO, $RUN2, $AUTHOR);
OFS::computeOffsets($conn, $gate, $CO, $RUN2, $AUTHOR);
$dedA2 = $conn->query("SELECT amount FROM payroll_deductions
    WHERE run_id={$RUN2} AND person_id={$A}")->fetch_assoc();
check($dedA2 && abs(floatval($dedA2['amount']) - 400.0) < 0.005,
      'الفترةُ التالية تخصم الرصيدَ الباقي 400 (< القسط 700 و< المسموح 500)');
$advA3 = $conn->query("SELECT balance, state FROM employee_advances WHERE id={$ADV_A}")->fetch_assoc();
check(abs(floatval($advA3['balance']) - 0.0) < 0.005 && $advA3['state'] === 'settled',
      'وبها تُقفل السلفةُ **settled** — «الرصيدُ ينقص» حتى يصفر');
$conn->query("DELETE FROM payroll_runs WHERE id={$RUN2}");

// ═══ ⑦ حدٌّ لم يُقرَّر ═══
head('⑦ حدُّ حمايةٍ **لم يُقرَّر** — يُعلَن ولا يُفترض');
$conn->query("UPDATE payroll_settings SET protection_percent=NULL WHERE company_id={$CO}");
check(OFS::protectionPercent($gate) === null, 'الحدُّ غيرُ المقرَّر يُقرأ `null` لا صفرًا ولا رقمًا مفترضًا');
$r = OFS::computeOffsets($conn, $gate, $CO, $RUN, $AUTHOR);
check($r['protection'] === null && strpos($r['reason'], 'لا حدَّ حمايةٍ مقرَّرًا') !== false,
      'والمقاصّةُ **تُعلن** أن لا حدَّ مقرَّرًا في نتيجتها');

// ═══ ⑧ الوصلُ الثاني — تسويةُ العامل (M-21) ═══
head('⑧ الوصلُ الثاني: تحميلاتُ العامل في تسويته (فجوةُ M-21 المقيسة)');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php');
$posEmp = strpos($src, "\$partyType === 'employee'");
$posGuard = strpos($src, "if (\$partyType !== 'supplier') { return \$lines; }");
check($posEmp !== false && $posGuard !== false && $posEmp < $posGuard,
      'فرعُ العامل **قبل** حارس «للمورد وحده» — فتسويتُه تعرض ما عليه لا استحقاقَه فقط');
check(strpos($src, "'source_kind' => 'payroll_deduction'") !== false,
      'والتحميلُ يحمل مصدرَه `payroll_deduction` بمرجع سنده');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
