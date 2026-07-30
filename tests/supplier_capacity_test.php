<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-16 — اختبار قبول: الطاقةُ النظرية والجاهزيةُ ومهلةُ الإحلال
 *        (CON-03 §3 · §5 · §6 · §6.1-Q3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_capacity_test.php
 *
 * ما يُثبته:
 *   ① الحراسةُ عند الكتابة: طاقةٌ غيرُ موجبةٍ **422 وCHECK** · حدٌّ 120٪ 422 ·
 *      نموذجٌ خارج الأربعة 422 · معدةٌ خارج النطاق 422 · وتكرارُ (عقد × معدة ×
 *      سريان) **409**.
 *   ② القياسُ من **سجل الزمن الموحّد** بأرقامٍ مبذورة — والوقفةُ المخططةُ خارج
 *      المقام، و**غيرُ المسجَّل يُعلَن عددًا مستقلًّا** لا يُطرح صامتًا.
 *   ③ **مهلةُ الإحلال تنقل ولا تضيف**: نوبةُ 8 ساعاتٍ بمهلة 3 ⇒ **5 تُنقل إلى
 *      التغطية و3 تبقى في الجاهزية** — و`3 + 5 = 8` بالضبط: الساعةُ تُجزى مرة.
 *   ④ **الحدُّ يفعّل والقاعدةُ تحتسب**: بلا حدٍّ مكتوبٍ في بطاقة الطاقة **لا
 *      جزاءَ جاهزيةٍ ألبتة** ولو كانت قاعدةُ الجزاء قائمةً بعتبتها.
 *   ⑤ **بلا زمنٍ مخطط: لا قياسَ ولا جزاء** — ولا رقمَ من قسمةٍ على صفر.
 *   ⑥ الجزاءُ بقاعدته **وسقفُه يقصّ** · و**اختلافُ الحدّين يُعلَن نصًّا**.
 *   ⑦ **الوصلُ الحي**: التسويةُ المولَّدةُ تحمل الجزاءَ **خصمًا ظاهرًا** بسطره
 *      ومصدره (§6.1-Q3: «Ledger عند التسوية … خصمًا ظاهرًا»).
 *
 * البذرُ معزول: موردان وعقداهما وبطاقاتُهما وسجلُّ زمنٍ في 2093 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'M16 capacity test');

require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierCapacityService.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SupplierRuleService.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\SupplierCapacityService as SCAP;
use App\Services\Settlement\SupplierRuleService as SRS;
use App\Services\Settlement\SettlementService as SVC;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999831;
$MARK  = 'M16T' . getmypid();
$FROM  = '2093-04-01';
$TO    = '2093-04-30';
$PER   = '2093-04';

// ── الكنس: من الفرع إلى الجذر ─────────────────────────────────────────────
// `settlement_lines → settlements` قيدٌ يمنع حذفَ الرأس ما بقي سطر (گوتشا M-12)،
// و`supplier_capacity → supplier_contracts` كذلك.
$teardown = function () use ($conn, $MARK, $PER) {
    $conn->query("DELETE r FROM fin_requests r JOIN settlements s ON s.id = r.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE sl FROM settlement_lines sl JOIN settlements s ON s.id = sl.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM settlements WHERE party_name LIKE '%{$MARK}%'");
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE be.source_ref LIKE 'STL-%'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                   WHERE s.settlement_no = be.source_ref)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
    $conn->query("DELETE FROM fin_dues WHERE period_ref = '{$PER}'");
    $conn->query("DELETE FROM unit_time_log WHERE cause_note LIKE '{$MARK}%'");

    $ids = array();
    $r = $conn->query("SELECT id FROM supplier_contracts WHERE notes LIKE '{$MARK}%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM supplier_capacity WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_penalty_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_charge_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contract_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-16 — الطاقةُ والجاهزيةُ ومهلةُ الإحلال ══\n");

// ── البذر ─────────────────────────────────────────────────────────────────
head('البذر — موردان وعقداهما وثلاثُ معدات');

$eq = array();
$r = $conn->query("SELECT id FROM equipments WHERE company_id={$CO} ORDER BY id LIMIT 3");
while ($r && ($x = $r->fetch_assoc())) { $eq[] = intval($x['id']); }
check(count($eq) === 3, 'ثلاثُ معداتٍ في النطاق للقياس');
list($EQ1, $EQ2, $EQ3) = $eq;

$PRJ = 0;
$r = $conn->query("SELECT project_id FROM unit_time_log WHERE company_id={$CO} LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $PRJ = intval($x['project_id']); }
check($PRJ > 0, 'ومشروعٌ قائمٌ يُنسب إليه سجلُّ الزمن المبذور');

$mkSupplier = function ($suffix) use ($conn, $CO, $MARK) {
    // `suppliers.phone` NOT NULL بلا افتراض — يُملأ صراحةً فلا يعتمد البذرُ على
    // تساهل `sql_mode`؛ و**المُرجَعُ يُفحص ويُعلن** (config.php لا يرمي).
    $ok = $conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
                        VALUES ({$CO}, 'موردُ {$MARK}-{$suffix}', '0000000000', NOW())");
    if (!$ok) { fwrite(STDOUT, "  ! بذرُ المورد فشل: " . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$SUP1 = $mkSupplier('A');
$SUP2 = $mkSupplier('B');

$mkContract = function ($supplierId) use ($conn, $gate, $CO, $ACTOR, $MARK) {
    $r = SCS::createContract($conn, $gate, $CO, array(
        'supplier_id' => $supplierId, 'start_date' => '2093-01-01', 'end_date' => '2093-12-31',
        'currency' => 'USD', 'notes' => $MARK . ' عقدُ اختبار الطاقة'), $ACTOR);
    $cid = intval($r['contract_id']);
    if ($cid > 0) { $conn->query("UPDATE supplier_contracts SET state='نافذ' WHERE id={$cid}"); }
    return $cid;
};
$C1 = $mkContract($SUP1);
$C2 = $mkContract($SUP2);
check($C1 > 0 && $C2 > 0, 'وعقدا موردٍ نافذان');

// سجلُّ الزمن — الواقعةُ كما تقع: عملٌ · عطلٌ · وقفةٌ مخططةٌ · غيرُ مسجَّل
$mkLog = function ($eqId, $date, $state, $hours, $resp) use ($conn, $CO, $PRJ, $MARK) {
    $obl = ($state === 'actual_work') ? null : 'equipment_readiness';
    $st = $conn->prepare("INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
                          hours, ops_state, cause_note, resp_party, obligation_type, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $note = $MARK . '-بذر';
    $st->bind_param('isiidssss', $CO, $date, $PRJ, $eqId, $hours, $state, $note, $resp, $obl);
    $st->execute(); $st->close();
};

// المعدةُ ① — طاقةٌ بلا مهلةِ إحلال
$mkLog($EQ1, '2093-04-01', 'actual_work',    10.0, 'company');
$mkLog($EQ1, '2093-04-02', 'actual_work',     8.0, 'company');
$mkLog($EQ1, '2093-04-02', 'tech_breakdown',  2.0, 'supplier');
$mkLog($EQ1, '2093-04-03', 'planned_stop',    6.0, 'planned');    // خارج الخطة
$mkLog($EQ1, '2093-04-04', 'unlogged',        4.0, 'none');       // يُعلَن لا يُطرح صامتًا
$mkLog($EQ1, '2093-04-05', 'standby',         5.0, 'company');
// المعدةُ ② — نوبةُ عطلٍ متصلةٌ 8 ساعاتٍ ومهلةُ إحلالٍ 3
$mkLog($EQ2, '2093-04-10', 'tech_breakdown',  4.0, 'supplier');
$mkLog($EQ2, '2093-04-11', 'tech_breakdown',  4.0, 'supplier');
$mkLog($EQ2, '2093-04-12', 'actual_work',    12.0, 'company');
// المعدةُ ③ — للمورد الثاني: عطلٌ **بلا حدٍّ تعاقديٍّ مكتوب**
$mkLog($EQ3, '2093-04-01', 'actual_work',    10.0, 'company');
$mkLog($EQ3, '2093-04-02', 'tech_breakdown',  5.0, 'supplier');

// ═══ ① الحراسةُ عند الكتابة ═══
head('① الحراسةُ عند الكتابة (§3 · §6)');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ1, 'theoretical_daily' => 0, 'valid_from' => '2093-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'يُقاس أداءُ المورد') !== false,
      'طاقةٌ صفرٌ → 422 بنصّ «ومنها يُقاس أداءُ المورد»');

$conn->query("INSERT INTO supplier_capacity (company_id, contract_id, equipment_id, work_model,
              theoretical_daily, valid_from)
              VALUES ({$CO}, {$C1}, {$EQ1}, 'hour', 0, '2093-01-01')");
$leak = intval($conn->query("SELECT COUNT(*) n FROM supplier_capacity
                              WHERE contract_id={$C1}")->fetch_assoc()['n']);
check($leak === 0, 'وكتابةٌ مباشرةٌ بطاقةٍ صفرٍ **يرفضها CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ1, 'theoretical_daily' => 10, 'min_readiness_percent' => 120,
    'valid_from' => '2093-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وحدُّ جاهزيةٍ 120٪ → 422');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ1, 'work_model' => 'kilogram', 'theoretical_daily' => 10,
    'valid_from' => '2093-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '§2-②') !== false,
      'ونموذجٌ خارج الأربعة → 422 بنصّ المصدر');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => 0, 'theoretical_daily' => 10, 'valid_from' => '2093-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'ومعدةٌ غيرُ موجودةٍ في النطاق → 422');

// ── البطاقاتُ الصحيحة ──
$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ1, 'work_model' => 'hour', 'theoretical_daily' => 10,
    'min_readiness_percent' => 90, 'valid_from' => '2093-01-01'), $ACTOR);
check($r['ok'], 'بطاقةُ المعدة ① — 10/يوم · حدٌّ 90٪ · بلا مهلةِ إحلال');

$r2 = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ1, 'work_model' => 'hour', 'theoretical_daily' => 12,
    'valid_from' => '2093-01-01'), $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409, 'وتكرارُ (عقد × معدة × سريان) → **409** (UQ)');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ2, 'work_model' => 'hour', 'theoretical_daily' => 12,
    'min_readiness_percent' => 80, 'replace_hours' => 3, 'valid_from' => '2093-01-01'), $ACTOR);
check($r['ok'], 'وبطاقةُ المعدة ② — 12/يوم · حدٌّ 80٪ · **مهلةُ إحلالٍ 3 ساعات**');

$r = SCAP::saveCapacity($conn, $gate, $CO, $C2, array(
    'equipment_id' => $EQ3, 'work_model' => 'hour', 'theoretical_daily' => 10,
    'valid_from' => '2093-01-01'), $ACTOR);
check($r['ok'], 'وبطاقةُ المعدة ③ للمورد الثاني — **بلا حدٍّ مكتوب** (لم يُشترط)');

// ═══ ② القياس من سجل الزمن ═══
head('② القياسُ من سجل الزمن الموحّد (§3)');

$m = SCAP::measure($gate, $SUP1, $FROM, $TO);
$byEq = array();
foreach ($m['equipment'] as $x) { $byEq[$x['equipment_id']] = $x; }

$m1 = isset($byEq[$EQ1]) ? $byEq[$EQ1] : null;
check($m1 && abs($m1['planned_hours'] - 25.0) < 0.005,
      '★ الزمنُ المخطط 25 ساعةً — و**الوقفةُ المخططةُ 6 خارج المقام** (وُجد '
      . ($m1 ? $m1['planned_hours'] : '—') . ')');
check($m1 && abs($m1['unlogged_hours'] - 4.0) < 0.005,
      '★ و**4 ساعاتٍ غيرَ مسجَّلةٍ تُعلَن عددًا مستقلًّا** لا تُطرح صامتًا');
check($m1 && abs($m1['readiness'] - 92.0) < 0.005,
      '★ فالجاهزية (25 − 2) ÷ 25 = **92٪** (وُجد ' . ($m1 ? $m1['readiness'] : '—') . ')');

$found = false;
foreach ($m['notes'] as $n) { if (mb_strpos($n, 'غيرَ مسجَّلةٍ') !== false) { $found = true; } }
check($found, 'والإعلانُ مكتوبٌ في نصّ القياس لا في الرأس فقط');

// ═══ ③ مهلةُ الإحلال تنقل ولا تضيف ═══
head('③ **مهلةُ الإحلال تنقل ولا تضيف** — «يحوّل التوقفَ إلى عجزِ تغطية» (§3)');

$m2 = isset($byEq[$EQ2]) ? $byEq[$EQ2] : null;
check($m2 && count($m2['episodes']) === 1 && abs($m2['episodes'][0]['hours'] - 8.0) < 0.005,
      'نوبةٌ واحدةٌ متصلةٌ بيومين = 8 ساعات (وُجد '
      . ($m2 ? count($m2['episodes']) : '—') . ' نوبة)');
check($m2 && abs($m2['coverage_hours'] - 5.0) < 0.005,
      '★ الزائدُ على المهلة (8 − 3) = **5 ساعاتٍ نُقلت إلى التغطية**');
check($m2 && abs($m2['unfit_hours'] - 3.0) < 0.005,
      '★ والباقي في الجاهزية **3 ساعاتٍ فقط** — لا 8');
check($m2 && abs(($m2['unfit_hours'] + $m2['coverage_hours']) - $m2['unfit_raw']) < 0.005,
      '★★ و`3 + 5 = 8` بالضبط — **الساعةُ تُجزى مرةً واحدة**، نقلًا لا إضافة');
check($m2 && abs($m2['readiness'] - 85.0) < 0.005,
      'فجاهزيةُ المعدة ② (20 − 3) ÷ 20 = **85٪** — لا 60٪');

check(abs($m['readiness'] - 88.89) < 0.005,
      '★ وجاهزيةُ المورد مجموعًا (45 − 5) ÷ 45 = **88.89٪** (وُجد ' . $m['readiness'] . ')');
check(abs($m['contract_min'] - 85.56) < 0.005,
      'والحدُّ التعاقديُّ **مرجَّحًا بالزمن المخطط** (90×25 + 80×20) ÷ 45 = **85.56٪**');

// ═══ ④ الحدُّ يفعّل والقاعدةُ تحتسب ═══
head('④ **الحدُّ يفعّل والقاعدةُ تحتسب** (§3)');

$r = SRS::savePenaltyRule($conn, $gate, $CO, $C2, array(
    'kind' => 'readiness', 'threshold' => 90, 'rate' => 100, 'rate_basis' => 'per_unit',
    'cap_percent' => 10, 'valid_from' => '2093-01-01'), $ACTOR);
check($r['ok'], 'للمورد الثاني قاعدةُ جزاءِ جاهزيةٍ **قائمةٌ بعتبتها**');

$m2b = SCAP::measure($gate, $SUP2, $FROM, $TO);
check($m2b['readiness'] !== null && $m2b['contract_min'] === null,
      'وقياسُه موجودٌ (' . $m2b['readiness'] . '٪) **وحدُّه التعاقديُّ معدوم**');
$lines2 = SCAP::penaltyLines($gate, $SUP2, $FROM, $TO, 50000, 'USD');
check(count($lines2) === 0,
      '★ فبلا حدٍّ مكتوبٍ في بطاقة الطاقة: **صفرُ سطرِ جزاءِ جاهزية** — «نقصُها عن الحد التعاقدي يفعّل الجزاء»');
$declared = false;
foreach ($m2b['notes'] as $n) { if (mb_strpos($n, 'لا حدَّ جاهزيةٍ مكتوبًا') !== false) { $declared = true; } }
check($declared, 'والسببُ **معلَنٌ نصًّا** لا صامت');

// ═══ ⑤ بلا زمنٍ مخطط لا قياس ═══
head('⑤ **بلا زمنٍ مخطط: لا قياسَ ولا جزاء**');
$m0 = SCAP::measure($gate, $SUP2, '2093-09-01', '2093-09-30');
check($m0['readiness'] === null && abs($m0['planned_hours']) < 0.005,
      'شهرٌ بلا سجلِّ زمنٍ ⇒ **لا قياس** — ولا رقمَ من قسمةٍ على صفر');
$noMeasure = false;
foreach ($m0['notes'] as $n) { if (mb_strpos($n, 'لا قياس') !== false) { $noMeasure = true; } }
check($noMeasure, 'ويُعلَن «لا قياس» ولا يُفترض صفرٌ ولا مئة');
check(count(SCAP::penaltyLines($gate, $SUP2, '2093-09-01', '2093-09-30', 50000, 'USD')) === 0,
      'و**صفرُ جزاءٍ** على قياسٍ لا وجودَ له');

// ═══ ⑥ الجزاءُ بقاعدته وسقفه ═══
head('⑥ الجزاءُ بقاعدته **وسقفُه يقصّ** · واختلافُ الحدّين يُعلَن');

$r = SRS::savePenaltyRule($conn, $gate, $CO, $C1, array(
    'kind' => 'readiness', 'threshold' => 90, 'rate' => 100, 'rate_basis' => 'per_unit',
    'cap_percent' => 10, 'valid_from' => '2093-01-01',
    'formula_note' => 'عن كل نقطةِ نقصٍ تحت 90٪ ⇒ 100'), $ACTOR);
check($r['ok'], 'قاعدةُ جاهزيةٍ: حدٌّ 90٪ · 100 للنقطة · سقفٌ 10٪');
$r = SRS::savePenaltyRule($conn, $gate, $CO, $C1, array(
    'kind' => 'coverage', 'rate' => 50, 'rate_basis' => 'per_unit',
    'cap_percent' => 50, 'valid_from' => '2093-01-01'), $ACTOR);
check($r['ok'], 'وقاعدةُ تغطية: 50 لكل ساعةِ عجز');

$pl = SCAP::penaltyLines($gate, $SUP1, $FROM, $TO, 50000, 'USD');
$byKind = array();
foreach ($pl as $l) { $byKind[substr($l['source_ref'], 0, strpos($l['source_ref'], ':'))] = $l; }
check(count($pl) === 2, 'سطران: جاهزيةٌ وتغطية (وُجد ' . count($pl) . ')');
check(isset($byKind['readiness']) && abs($byKind['readiness']['amount'] - 111.0) < 0.005,
      '★ جزاءُ الجاهزية: عجزُ (90 − 88.89) = 1.11 نقطةٍ × 100 = **111** (وُجد '
      . (isset($byKind['readiness']) ? $byKind['readiness']['amount'] : '—') . ')');
check(isset($byKind['coverage']) && abs($byKind['coverage']['amount'] - 250.0) < 0.005,
      '★ وجزاءُ التغطية: **5 ساعاتٍ منقولةٍ** × 50 = **250** — لا 8 ساعات');
check(isset($byKind['readiness'])
      && mb_strpos($byKind['readiness']['description'], 'ويختلفان') !== false,
      '★ و**اختلافُ الحدّين معلَنٌ في نصّ السطر** (بطاقةٌ 85.56٪ · قاعدةٌ 90٪) — «الاسمُ في موضعين» يُقال لا يُخفى');
check(isset($byKind['readiness']) && (string) $byKind['readiness']['charge_type'] === 'penalty'
      && (string) $byKind['readiness']['source_kind'] === 'capacity',
      'وكلُّ سطرٍ **بنوعه ومصدره** — لا رقمٌ بلا أصل');

$plCap = SCAP::penaltyLines($gate, $SUP1, $FROM, $TO, 1000, 'USD');
$capped = null;
foreach ($plCap as $l) { if (strpos($l['source_ref'], 'readiness:') === 0) { $capped = $l; } }
check($capped !== null && abs($capped['amount'] - 100.0) < 0.005
      && mb_strpos($capped['description'], 'مقصوصٌ بسقف') !== false,
      '★ وبأساسٍ 1000: الجزاءُ 111 **مقصوصٌ إلى 100** (سقف 10٪) — والقصُّ معلَنٌ');

// ═══ ⑦ الوصلُ الحي ═══
head('⑦ الوصلُ الحي — «Ledger عند التسوية … خصمًا ظاهرًا» (§6.1-Q3)');

$st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                      amount, currency, period_ref, created_by, created_at)
                      VALUES (?, 'supplier', ?, 'hours', 'credit', 50000, 'USD', ?, 1, NOW())");
$ref = (string) $SUP1;
$st->bind_param('iss', $CO, $ref, $PER);
$st->execute(); $st->close();

$res = SVC::generate($gate, $conn, 'supplier', $SUP1, $FROM, $TO, 901);
check($res['ok'] === true, 'التسويةُ تولّدت (' . $res['reason'] . ')');
$SID = intval($res['settlement_id']);

$penLines = $gate->select('settlement_lines', array(
    'where' => array('settlement_id' => $SID, 'charge_type' => 'penalty')));
check(count($penLines) === 2,
      '★ وسطرا الجزاء **دخلا التسوية تلقائيًّا** من بطاقة الطاقة — لا بإدخالٍ يدوي (وُجد '
      . count($penLines) . ')');
$sumPen = 0.0; $withSrc = 0;
foreach ($penLines as $l) {
    $sumPen += (float) $l['amount'];
    if ((string) $l['source_kind'] === 'capacity' && (string) $l['source_ref'] !== '') { $withSrc++; }
}
check(abs($sumPen - 361.0) < 0.005, '★ ومجموعُهما 111 + 250 = **361** (وُجد ' . $sumPen . ')');
check($withSrc === 2, 'وكلاهما **برابط مصدره** — «كلُّ رقمٍ ينقر إلى مستنده»');

$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
check($s && abs((float) $s['charges_amount'] - 361.0) < 0.005,
      '★ والتحميلاتُ في رأس التسوية **361 خصمًا ظاهرًا** (وُجد ' . ($s ? $s['charges_amount'] : '—') . ')');
check($s && abs((float) $s['net_amount'] - 49639.0) < 0.005,
      'والصافي 50000 − 361 = **49639**');

$src = file_get_contents(dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php');
check(strpos($src, 'SupplierCapacityService::penaltyLines') !== false,
      'والوصلُ مكتوبٌ في `collectLines` — مسارٌ واحدٌ للتسوية كلِّها');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
