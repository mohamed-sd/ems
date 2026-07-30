<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-09-③ — اختبار قبول: المسارُ الإنتاجي (ENT-01 §3-② · §4 · PLAN-01 §6.1-③)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/payroll_production_path_test.php
 *
 * معيارُ القبول النصّي: «**مشغّلٌ بالطن ومشغّلٌ بالنقلة محسوبان بلا فرق**».
 *
 * الفترة: 2048-04-01 → 2048-04-30. والحساباتُ اليدويةُ في النصّ لا في الكود:
 *
 *  المشغّلُ (أ) — نموذجُ «بالطن» · معدلُ الوحدة 12 · حافزُ عتبةٍ (>200) بمعدل 5 وسقف 400
 *    وحداتٌ **محكومة**: 150 + 100 = 250 طنًّا · (و80 طنًّا لم تكتمل أحكامُها ⇒ تُستبعد)
 *    الإنتاج : 250 × 12                    = 3000.00
 *    الحافز  : (250 − 200) × 5 = 250 ≤ 400 =  250.00
 *    ─────────────────────────────────────────────────
 *    الإجمالي                              = 3250.00
 *
 *  المشغّلُ (ب) — نموذجُ «بالنقلة» · معدلُ الوحدة 45 · حافزُ وحدةٍ بمعدل 2 **وسقف 50**
 *    وحداتٌ محكومة: 40 نقلة
 *    الإنتاج : 40 × 45                     = 1800.00
 *    الحافز  : 40 × 2 = 80 > 50 ⇒ **مقصوص** =   50.00
 *    ─────────────────────────────────────────────────
 *    الإجمالي                              = 1850.00
 *
 *  المشغّلُ (ج) — نموذجُ «بالطن» **بلا معدلِ وحدةٍ في اللقطة** ⇒ 0.00 معلَنًا
 *  المشغّلُ (د) — نموذجُ «ثابت فقط» (**زمنيٌّ لا وحدةَ له**) في عقدٍ مشروعي ⇒ 0.00 معلَنًا
 *
 * ⓘ ولماذا ليس «بالساعة» مثالَ الزمنيّ؟ لأن `hourly` **وحدةُ إنتاجٍ مشروعة**:
 *   §3-② يعدّ «منفَّذٌ · استعدادٌ · **حضورٌ**» أساسًا للمسار الإنتاجي — فساعاتُ
 *   المشغّل تُقرأ من السجل القانوني كما تُقرأ أطنانُه. والزمنيُّ حقًّا ما لا
 *   وحدةَ قياسٍ له أصلًا (ثابتٌ مقطوع).
 *
 * البذرُ معزول: عقودٌ بوسم H093T ووقائعُ 2048 — تُكنس كاملةً.
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
require_once dirname(__DIR__) . '/app/Services/Payroll/ProductionPathService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\ProductionPathService as PPS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999963;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

const TAG3 = 'H093T المسارُ الإنتاجي';
const Q_FROM = '2048-04-01';
const Q_TO   = '2048-04-30';

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . TAG3 . "'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM payroll_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE ia FROM incentive_allocations ia JOIN incentive_rules ir ON ir.id = ia.rule_id
                       WHERE ir.contract_id = {$cid}");
        $conn->query("DELETE FROM incentive_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_snapshots WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE a FROM unit_party_awards a JOIN unit_entries e ON e.id = a.source_ref
                   WHERE a.source_kind='unit_record' AND e.entry_no LIKE 'H093T%'");
    $conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'H093T%'");
    $conn->query("DELETE l FROM payroll_lines l JOIN payroll_runs r ON r.id = l.run_id
                   WHERE r.period_from = '" . Q_FROM . "'");
    $conn->query("DELETE b FROM payroll_run_blocks b JOIN payroll_runs r ON r.id = b.run_id
                   WHERE r.period_from = '" . Q_FROM . "'");
    $conn->query("DELETE FROM payroll_runs WHERE period_from = '" . Q_FROM . "'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-09-③ — المسارُ الإنتاجي: بالطن وبالنقلة بلا فرق ══\n");

// ═══ البذر ═══
head('البذر — أربعةُ مشغّلين بنماذجَ مختلفة');
$emps = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id DESC LIMIT 4");
while ($x = $r->fetch_assoc()) { $emps[] = intval($x['id']); }
check(count($emps) === 4, 'أربعةُ أشخاصٍ من السجل الحي');
list($A, $B, $Cx, $D) = $emps;

$modelId = function ($code) use ($conn) {
    $r = $conn->query("SELECT id FROM pay_models WHERE code='" . $conn->real_escape_string($code) . "' LIMIT 1");
    $x = $r->fetch_assoc(); return intval($x['id']);
};
$mkContract = function ($empId, $modelCode) use ($conn, $gate, $CO, $ACTOR, $modelId) {
    $r = ECS::createHead($conn, $gate, $CO, array(
        'employee_id' => $empId, 'category' => 'operator', 'pay_model_id' => $modelId($modelCode),
        'relation_type' => TAG3, 'start_date' => '2048-01-01', 'end_date' => '2048-12-31'), $ACTOR);
    return !empty($r['ok']) ? intval($r['id']) : 0;
};
$addComp = function ($cid, $data) use ($conn, $gate, $CO, $ACTOR) {
    $r = ECS::addComponent($conn, $gate, $CO, $cid, $data, $ACTOR);
    return !empty($r['ok']) ? intval($r['id']) : 0;
};
$activate = function ($cid) use ($conn) {
    $conn->query("UPDATE employee_contracts SET state='active' WHERE id=" . intval($cid));
};

// (أ) بالطن · معدل 12 · حافزُ عتبة
$CA = $mkContract($A, 'per_ton');
$addComp($CA, array('component_type' => 'custom', 'calc_method' => 'per_unit', 'rate' => 12,
    'periodicity' => 'monthly', 'valid_from' => '2048-01-01'));
$ra = ECS::addIncentiveRule($conn, $gate, $CO, $CA, array(
    'incentive_type' => 'حافزُ تجاوزِ عتبةِ الأطنان', 'basis' => 'threshold',
    'rate' => 5, 'threshold' => 200, 'cap' => 400, 'periodicity' => 'monthly',
    'valid_from' => '2048-01-01'), $ACTOR);
ECS::setIncentiveAllocations($conn, $gate, $CO, intval($ra['id']), array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $A, 'percent' => 100.00)), $ACTOR);

// (ب) بالنقلة · معدل 45 · حافزُ وحدةٍ بسقف 50
$CB = $mkContract($B, 'per_trip');
$addComp($CB, array('component_type' => 'custom', 'calc_method' => 'per_unit', 'rate' => 45,
    'periodicity' => 'monthly', 'valid_from' => '2048-01-01'));
$rb = ECS::addIncentiveRule($conn, $gate, $CO, $CB, array(
    'incentive_type' => 'حافزُ النقلات', 'basis' => 'unit',
    'rate' => 2, 'cap' => 50, 'periodicity' => 'monthly', 'valid_from' => '2048-01-01'), $ACTOR);
ECS::setIncentiveAllocations($conn, $gate, $CO, intval($rb['id']), array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $B, 'percent' => 100.00)), $ACTOR);

// (ج) بالطن بلا معدلِ وحدة
$CC = $mkContract($Cx, 'per_ton');
$addComp($CC, array('component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 500,
    'periodicity' => 'monthly', 'valid_from' => '2048-01-01'));
// (د) «ثابت فقط» — نموذجٌ زمنيٌّ **لا وحدةَ قياسٍ له** في عقدٍ مشروعي
$CD = $mkContract($D, 'fixed_only');
$addComp($CD, array('component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 700,
    'periodicity' => 'monthly', 'valid_from' => '2048-01-01'));

foreach (array($CA, $CB, $CC, $CD) as $cid) { $activate($cid); }
check($CA && $CB && $CC && $CD, 'أربعةُ عقودٍ نافذةٍ (بالطن · بالنقلة · بالطن بلا معدل · ثابتٌ زمني)');

// ── وقائعُ السجل القانوني بأحكامها ──
$mkEntry = function ($no, $date, $empId, $unit, $qty, $state) use ($conn, $CO) {
    $conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, operator_employee_id,
                  unit_type, qty, state, record_basis) VALUES ({$CO}, '{$no}', '{$date}', {$empId},
                  '{$unit}', {$qty}, '{$state}', 'contract')");
    $eid = intval($conn->insert_id);
    // ⚠ گوتشا: `qty_due` عمودٌ **مولَّد** — كتابتُه ترفض الصفَّ كلَّه، و`config.php`
    //   لا يرمي فيمضي البذرُ صامتًا. (قاعدةُ المستودع: افحص المُرجَع لا الاستثناء.)
    $okAward = $conn->query("INSERT INTO unit_party_awards (company_id, source_kind, source_ref, party,
                  party_ref, award_unit_type, award_qty, entitlement_state, entitlement_pct)
                  VALUES ({$CO}, 'unit_record', {$eid}, 'operator', {$empId}, '{$unit}', {$qty},
                  'due', 100.00)");
    if (!$okAward) { bad('تعذّر بذرُ حكم الطرف: ' . $conn->error); }
    return $eid;
};
// (أ) 150 + 100 محكومة · 80 لم تكتمل أحكامُها
$mkEntry('H093T-A1', '2048-04-05', $A, 'ton', 150, 'parties_approved');
$mkEntry('H093T-A2', '2048-04-15', $A, 'ton', 100, 'sales_approved');
$mkEntry('H093T-A3', '2048-04-20', $A, 'ton', 80,  'submitted');
// (ب) 40 نقلة محكومة
$mkEntry('H093T-B1', '2048-04-10', $B, 'trip', 25, 'parties_approved');
$mkEntry('H093T-B2', '2048-04-22', $B, 'trip', 15, 'converted');
// (ج) 60 طنًّا محكومة — بلا معدلٍ في العقد
$mkEntry('H093T-C1', '2048-04-12', $Cx, 'ton', 60, 'parties_approved');
// خارجَ الفترة (لا تُحتسب)
$mkEntry('H093T-A4', '2048-05-03', $A, 'ton', 500, 'parties_approved');
ok('الوقائعُ مبذورةٌ بأحكامها (محكومةٌ · غيرُ مكتملة · وخارجَ الفترة)');

// ═══ الدورة ═══
head('الدورة — فتحٌ فربطٌ فمسارٌ إنتاجي');
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => Q_FROM, 'period_to' => Q_TO, 'category_filter' => 'operator'), $ACTOR);
check($r['ok'], 'الدورةُ فُتحت (operator · أبريل 2048)');
$RUN = intval($r['run_id']);
$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN, $ACTOR);
check($r['ok'], "ربطُ اللقطات: {$r['lines']} سطرًا · {$r['blocked']} مانعًا · {$r['excluded']} مستبعَدًا");
$r = PPS::compute($conn, $gate, $CO, $RUN, $ACTOR);
check($r['ok'], "المسارُ الإنتاجي: {$r['reason']}");

// ═══ المقارنةُ اليدوية ═══
head('**مشغّلٌ بالطن ومشغّلٌ بالنقلة محسوبان بلا فرق**');
$netOf = function ($personId) use ($conn, $RUN) {
    $row = $conn->query("SELECT ROUND(COALESCE(SUM(amount),0),2) s FROM payroll_lines
                          WHERE run_id={$RUN} AND person_id={$personId}
                            AND line_kind IN ('production','incentive')")->fetch_assoc();
    return round(floatval($row['s']), 2);
};
$netA = $netOf($A); $netB = $netOf($B); $netC = $netOf($Cx); $netD = $netOf($D);
check(abs($netA - 3250.00) < 0.005, "(أ) بالطن: 250×12 = 3000 + حافزُ (250−200)×5 = 250 ⇒ 3250 — آليًّا " . number_format($netA, 2));
check(abs($netB - 1850.00) < 0.005, "(ب) بالنقلة: 40×45 = 1800 + حافزٌ 40×2=80 مقصوصٌ بسقف 50 ⇒ 1850 — آليًّا " . number_format($netB, 2));
check(abs($netC - 0.00) < 0.005, "(ج) بلا معدلِ وحدة: **لا تسعيرَ ملفَّق** ⇒ 0 — آليًّا " . number_format($netC, 2));
check(abs($netD - 0.00) < 0.005, "(د) نموذجٌ زمنيٌّ بلا وحدةِ قياس: يُعلَن ⇒ 0 — آليًّا " . number_format($netD, 2));

// ═══ البراهينُ الجانبية ═══
head('البراهينُ الجانبية');
$prodA = $conn->query("SELECT qty, rate, amount, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$A} AND line_kind='production'")->fetch_assoc();
check($prodA && floatval($prodA['qty']) === 250.0 && floatval($prodA['rate']) === 12.0,
      '**الكميةُ المحكومةُ وحدَها**: 250 طنًّا (150+100) — و80 طنًّا لم تكتمل أحكامُها **لم تدخل**');
check(strpos($prodA['note'], 'لم تكتمل أحكامُ أطرافها') !== false,
      'والاستبعادُ **معلَنٌ في نصّ السطر** لا صامتًا');

$outOfPeriod = $conn->query("SELECT COUNT(*) n FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$A} AND note LIKE '%500%'")->fetch_assoc();
check(intval($outOfPeriod['n']) === 0, 'وواقعةُ مايو (500 طن) خارجَ الفترة — لم تدخل أبدًا');

$incB = $conn->query("SELECT amount, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$B} AND line_kind='incentive'")->fetch_assoc();
check($incB && floatval($incB['amount']) === 50.0 && strpos($incB['note'], 'مقصوصٌ بالسقف') !== false,
      '**السقفُ يقصّ ويُعلن قصَّه** في نصّ السطر (80 ⇒ 50)');

$noRate = $conn->query("SELECT amount, calc_state, qty, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$Cx} AND line_kind='production'")->fetch_assoc();
check($noRate && $noRate['amount'] === null && floatval($noRate['qty']) === 60.0
      && strpos($noRate['note'], 'لا معدلَ وحدةٍ') !== false,
      'وكميةٌ بلا معدل: **السطرُ يحمل كميتَه ويُعلن أنه بلا معدل** — لا صفرٌ ولا اختراع');

$timeModel = $conn->query("SELECT calc_state, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$D} AND line_kind='production'")->fetch_assoc();
check($timeModel && strpos($timeModel['note'], 'زمنيٌّ') !== false,
      'ونموذجٌ زمنيٌّ في عقدٍ مشروعي يُعلَن ولا يُحتسب بمسارٍ ليس مساره');

$inst = $conn->query("SELECT COUNT(*) n FROM payroll_lines
    WHERE run_id={$RUN} AND path='institutional' AND line_kind IN ('production','incentive')")->fetch_assoc();
check(intval($inst['n']) === 0, 'وصفرُ سطرٍ مؤسسيٍّ مسّه المسارُ الإنتاجي — كلٌّ بمساره');

// ═══ العطالة ═══
head('العطالة');
$before = $netOf($A);
PPS::compute($conn, $gate, $CO, $RUN, $ACTOR);
$after = $netOf($A);
check(abs($before - $after) < 0.005, "إعادةُ التشغيل: الصافي ثابتٌ ({$before} = {$after})");
$cnt = intval($conn->query("SELECT COUNT(*) n FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$A} AND line_kind='production'")->fetch_assoc()['n']);
check($cnt === 1, 'وسطرُ الإنتاج واحدٌ لا اثنان');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
