<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-17 — اختبار قبول: التقييمُ الدوريُّ للمورد
 *        (CON-03 §4-التقييم · §5 · UX-05 §5.1-⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_evaluation_test.php
 *
 * ما يُثبته:
 *   ① **لا نتيجةَ بلا وزنٍ مكتوب**: Σ الأوزان ≠ 100 ⇒ **422 بالمجموع مسمًّى** ·
 *      ومؤشرٌ خارج الخمسة 422 · ووزنٌ خارج (0،100] 422.
 *   ② **المؤشرُ بلا مصدرٍ يُعلَن ولا يُقدَّر**: «الحوادثُ» بلا مقياسٍ مكتوب
 *      ⇒ `measurable=0` بسببه، و**تغطيةُ الوزن تنقص وتُعلَن** ولا تُطبَّع صامتةً.
 *   ③ الحسابُ الصحيحُ بأرقامٍ مبذورة — والنتيجةُ تتغير **بإعادة التوليد** حين
 *      يُكتب المقياس (المسودةُ تُعاد · والمعتمَدُ **423 لا يُعاد**).
 *   ④ **تقييمٌ أكثرُ من نصف وزنه بلا مصدرٍ لا يُعتمد** (422).
 *   ⑤ **منعُ التجديد يلزمه سببٌ مكتوب** — خدمةً (422) و**`CHECK`** بنيويًّا.
 *   ⑥ **الوصلُ الحي**: «ونتيجتُه شرطٌ في التجديد» — الانتقالُ إلى «مجدَّد»
 *      **423 بلا تقييم** · **423 بغير المؤهَّل بسببه** · ويقع بعد تقييمٍ مؤهِّل.
 *
 * البذرُ معزول: موردان وعقدٌ ومعدةٌ وسجلُّ زمنٍ وبلاغٌ في 2094 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'M17 evaluation test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierCapacityService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierEvaluationService.php';

use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\SupplierCapacityService as SCAP;
use App\Services\Contract\SupplierEvaluationService as SES;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999841;
$MARK  = 'M17T' . getmypid();

// ── الكنس ─────────────────────────────────────────────────────────────────
// الأوزانُ **مشتركةٌ للشركة** لا للمورد — فتُحفظ حالتُها الأصليةُ وتُعاد،
// وإلا كسر الاختبارُ إعدادًا حيًّا (وهو ما يمنعه §3 حرفيًّا).
$savedWeights = array();
$r = $conn->query("SELECT indicator, weight, scale_max, note FROM supplier_evaluation_weights
                    WHERE company_id={$CO}");
while ($r && ($x = $r->fetch_assoc())) { $savedWeights[] = $x; }

$teardown = function () use ($conn, $MARK, $CO, $savedWeights) {
    $conn->query("DELETE l FROM supplier_evaluation_lines l
                    JOIN supplier_evaluations e ON e.id = l.evaluation_id
                    JOIN suppliers s ON s.id = e.supplier_id
                   WHERE s.name LIKE '%{$MARK}%'");
    $conn->query("DELETE e FROM supplier_evaluations e JOIN suppliers s ON s.id = e.supplier_id
                   WHERE s.name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM tickets WHERE ticket_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM unit_time_log WHERE cause_note LIKE '{$MARK}%'");
    $ids = array();
    $r = $conn->query("SELECT id FROM supplier_contracts WHERE notes LIKE '{$MARK}%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM supplier_capacity WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contract_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
    // إعادةُ الأوزان إلى ما كانت عليه بالضبط
    $conn->query("DELETE FROM supplier_evaluation_weights WHERE company_id={$CO}");
    foreach ($savedWeights as $w) {
        $st = $conn->prepare("INSERT INTO supplier_evaluation_weights
                              (company_id, indicator, weight, scale_max, note)
                              VALUES (?, ?, ?, ?, ?)");
        $st->bind_param('isdds', $CO, $w['indicator'], $w['weight'], $w['scale_max'], $w['note']);
        $st->execute(); $st->close();
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-17 — التقييمُ الدوريُّ للمورد ══\n");

// ── البذر ─────────────────────────────────────────────────────────────────
head('البذر — موردان وعقدٌ ومعدةٌ وسجلُّ زمنٍ وبلاغُ سلامة');

$EQ = 0;
$r = $conn->query("SELECT id FROM equipments WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $EQ = intval($x['id']); }
$PRJ = 0;
$r = $conn->query("SELECT project_id FROM unit_time_log WHERE company_id={$CO} LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $PRJ = intval($x['project_id']); }
check($EQ > 0 && $PRJ > 0, 'معدةٌ ومشروعٌ في النطاق');

$mkSupplier = function ($suffix) use ($conn, $CO, $MARK) {
    $ok = $conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
                        VALUES ({$CO}, 'موردُ {$MARK}-{$suffix}', '0000000000', NOW())");
    if (!$ok) { fwrite(STDOUT, '  ! بذرُ المورد فشل: ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$SUP1 = $mkSupplier('A');
$SUP2 = $mkSupplier('B');   // بلا بطاقاتِ طاقةٍ — «بلا مصدرٍ يُعلَن»
check($SUP1 > 0 && $SUP2 > 0, 'وموردان مبذوران');

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP1, 'start_date' => '2094-01-01', 'end_date' => '2094-12-31',
    'currency' => 'USD', 'notes' => $MARK . ' عقدُ اختبار التقييم'), $ACTOR);
$C1 = intval($r['contract_id']);
check($C1 > 0, 'وعقدُ موردٍ أُنشئ');
// «قيد التنفيذ» هي الحالةُ التي يُتاح منها التجديدُ في قائمة السماح
$conn->query("UPDATE supplier_contracts SET state='قيد التنفيذ' WHERE id={$C1}");

$r = SCAP::saveCapacity($conn, $gate, $CO, $C1, array(
    'equipment_id' => $EQ, 'work_model' => 'hour', 'theoretical_daily' => 10,
    'min_readiness_percent' => 90, 'replace_hours' => 3, 'valid_from' => '2094-01-01'), $ACTOR);
check($r['ok'], 'وبطاقةُ طاقةٍ للمعدة (مصدرُ الجاهزية والتغطية)');

$mkLog = function ($date, $state, $hours, $resp) use ($conn, $CO, $PRJ, $EQ, $MARK) {
    $obl = ($state === 'actual_work') ? null : 'equipment_readiness';
    $st = $conn->prepare("INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
                          hours, ops_state, cause_note, resp_party, obligation_type, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $note = $MARK . '-بذر';
    $st->bind_param('isiidssss', $CO, $date, $PRJ, $EQ, $hours, $state, $note, $resp, $obl);
    $st->execute(); $st->close();
};
// فترةُ أيار: 25 ساعةً مخططةً · عطلُ 2 · توقفُ مشغّلٍ 5
$mkLog('2094-05-01', 'actual_work',    10.0, 'company');
$mkLog('2094-05-02', 'actual_work',     8.0, 'company');
$mkLog('2094-05-02', 'tech_breakdown',  2.0, 'supplier');
$mkLog('2094-05-03', 'operator_stop',   5.0, 'operator');
// فترةُ حزيران: عملٌ خالصٌ — تقييمٌ تامّ
$mkLog('2094-06-01', 'actual_work',    10.0, 'company');

$TYP = 0;
$r = $conn->query("SELECT id FROM ticket_types WHERE code='safety_incident' LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $TYP = intval($x['id']); }
check($TYP > 0, 'ونوعُ بلاغِ السلامة قائمٌ في القاموس (مصدرُ «الحوادث»)');
$st = $conn->prepare("INSERT INTO tickets (company_id, ticket_no, ticket_type_id, ticket_nature,
                      equipment_id, project_id, call_date, stage, created_by, created_at)
                      VALUES (?, ?, ?, 'incident', ?, ?, '2094-05-04', 'new', 1, NOW())");
$tno = $MARK . '-INC1';
$st->bind_param('isiii', $CO, $tno, $TYP, $EQ, $PRJ);
$okT = $st->execute(); $st->close();
check($okT, 'وبلاغُ سلامةٍ واحدٌ على المعدة في أيار');

// ═══ ⓿ بلا تقييمٍ لا تجديد ═══
head('⓿ **بلا تقييمٍ معتمَدٍ لا تجديد** — قبل أي تقييم');
$r = SCS::transition($conn, $gate, $CO, $C1, \App\Services\Contract\ContractStateMachine::RENEWED, 0, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'لا تقييمَ دوريًّا معتمَدًا') !== false,
      '★ التجديدُ **423** ونصُّه يقول ما يُفعل: «قيّمه بفترةٍ ثم جدّد» — لا رفضٌ أعمى');

// ═══ ① لا نتيجةَ بلا وزنٍ مكتوب ═══
head('① **لا نتيجةَ بلا وزنٍ مكتوب** (§4)');

$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-05-01', '2094-05-31', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'انطباعٌ برقم') !== false,
      'بلا أوزانٍ مكتوبةٍ ⇒ **422** — «النتيجةُ بلا وزنٍ انطباعٌ برقم»');

$r = SES::saveWeight($conn, $gate, $CO, array('indicator' => 'punctuality', 'weight' => 10), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], '§4-التقييم') !== false,
      'ومؤشرٌ خارج الخمسة → 422 بنصّ المصدر');
$r = SES::saveWeight($conn, $gate, $CO, array('indicator' => 'readiness', 'weight' => 120), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'ووزنٌ 120٪ → 422');

$W = array('readiness' => 30, 'coverage' => 20, 'attributed_stops' => 20,
           'operator_quality' => 20, 'incidents' => 10);
foreach ($W as $ind => $w) {
    SES::saveWeight($conn, $gate, $CO, array('indicator' => $ind, 'weight' => $w), $ACTOR);
}
check(abs(SES::weightsSum($gate) - 100.0) < 0.005, 'والأوزانُ الخمسةُ مكتوبةٌ وΣ = 100');

SES::saveWeight($conn, $gate, $CO, array('indicator' => 'incidents', 'weight' => 15), $ACTOR);
$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-05-01', '2094-05-31', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], '105') !== false,
      '★ وΣ = 105 ⇒ **422 بالمجموع مسمًّى** — لا رفضٌ أعمى');
SES::saveWeight($conn, $gate, $CO, array('indicator' => 'incidents', 'weight' => 10), $ACTOR);

// ═══ ② المؤشرُ بلا مصدرٍ يُعلَن ولا يُقدَّر ═══
head('② **بلا مقياسٍ مكتوب لا يُقاس** — والتغطيةُ تُعلَن');

$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-05-01', '2094-05-31', $ACTOR);
check($r['ok'], 'وُلّد تقييمُ أيار (' . $r['reason'] . ')');
$EV1 = intval($r['evaluation_id']);
check(abs($r['coverage'] - 90.0) < 0.005,
      '★ تغطيةُ الوزن **90٪** — و«الحوادثُ» بوزنها 10 **خارج القياس** (بلا مقياسٍ مكتوب)');

$lines = SES::linesOf($gate, $EV1);
$byInd = array();
foreach ($lines as $l) { $byInd[(string) $l['indicator']] = $l; }
check(isset($byInd['incidents']) && intval($byInd['incidents']['measurable']) === 0
      && mb_strpos((string) $byInd['incidents']['source_note'], 'بلا مقياسٍ مكتوب') !== false,
      '★ وسطرُ «الحوادث» موجودٌ **موسومًا بلا مصدرٍ وبسببه نصًّا** — لا يُحذف ولا يُقدَّر');
check(abs((float) $r['score'] - 91.11) < 0.02,
      '★ والنتيجةُ 82 ÷ 90 × 100 = **91.11** — والتطبيعُ **معلَنٌ بتغطيته** لا مخفيّ (وُجد '
      . $r['score'] . ')');

// ═══ ③ الحساب وإعادةُ التوليد ═══
head('③ الحسابُ من السجلات — وإعادةُ التوليد للمسودة');

check(isset($byInd['readiness']) && abs((float) $byInd['readiness']['measured_value'] - 92.0) < 0.005
      && abs((float) $byInd['readiness']['earned'] - 27.6) < 0.005,
      '★ الجاهزية 92٪ ⇒ مكتسَبٌ 30 × 0.92 = **27.6** (من M-16 لا من تقدير)');
check(isset($byInd['attributed_stops'])
      && abs((float) $byInd['attributed_stops']['measured_value'] - 2.0) < 0.005
      && abs((float) $byInd['attributed_stops']['earned'] - 18.4) < 0.005,
      '★ وتوقفاتٌ مسندةٌ إليه ساعتان من 25 ⇒ **18.4** (`resp_party=supplier`)');
check(isset($byInd['operator_quality'])
      && abs((float) $byInd['operator_quality']['measured_value'] - 5.0) < 0.005
      && abs((float) $byInd['operator_quality']['earned'] - 16.0) < 0.005,
      '★ وجودةُ المشغّلين: 5 ساعاتِ توقفٍ من 25 ⇒ **16** (`ops_state=operator_stop`)');
check(isset($byInd['coverage']) && abs((float) $byInd['coverage']['earned'] - 20.0) < 0.005,
      'والتغطيةُ تامّةٌ (صفرُ ساعةٍ تجاوزت مهلةَ الإحلال) ⇒ **20**');

SES::saveWeight($conn, $gate, $CO, array('indicator' => 'incidents', 'weight' => 10,
    'scale_max' => 5, 'note' => 'خمسةُ بلاغاتٍ في الفترة ⇒ صفر'), $ACTOR);
$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-05-01', '2094-05-31', $ACTOR);
check($r['ok'] && intval($r['evaluation_id']) === $EV1,
      'وإعادةُ توليدِ المسودة **تُصلح السجلَّ نفسَه** لا تُنشئ ثانيًا');
check(abs($r['coverage'] - 100.0) < 0.005 && abs((float) $r['score'] - 90.0) < 0.005,
      '★ وبالمقياس المكتوب: التغطيةُ **100٪** والنتيجة **90.00** (بلاغٌ واحدٌ من 5 ⇒ 8 من 10)');

$lines = SES::linesOf($gate, $EV1);
check(count($lines) === 5, 'وخمسةُ أسطرٍ لا أكثر — الكنسُ قبل الكتابة (لا ازدواج)');

// ═══ ④ التغطيةُ الدنيا ═══
head('④ **تقييمٌ أكثرُ من نصف وزنه بلا مصدرٍ لا يُعتمد**');

$r = SES::generate($conn, $gate, $CO, $SUP2, '2094-05-01', '2094-05-31', $ACTOR);
check($r['ok'] && abs($r['coverage']) < 0.005 && $r['score'] === null,
      'موردٌ بلا بطاقةِ طاقةٍ: تقييمٌ **بتغطيةِ صفرٍ ونتيجةٍ معدومة** — يُعلَن ولا يُختلق');
$r2 = SES::decide($conn, $gate, $CO, intval($r['evaluation_id']), 'eligible', '', $ACTOR);
check(!$r2['ok'] && $r2['code'] === 422 && mb_strpos($r2['reason'], 'نصف وزنه') !== false,
      '★ واعتمادُه **422** — «تقييمٌ أكثرُ من نصف وزنه بلا مصدرٍ لا يُعتمد»');

// ═══ ⑤ منعُ التجديد يلزمه سبب ═══
head('⑤ **منعُ التجديد يلزمه سببٌ مكتوب** — خدمةً وقيدًا');

$r = SES::decide($conn, $gate, $CO, $EV1, 'not_eligible', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'لا يكون صامتًا') !== false,
      'منعٌ بلا سببٍ → **422**');
$r = SES::decide($conn, $gate, $CO, $EV1, 'not_eligible',
    'تكرارُ العطل وتجاوزُ مهلة الإحلال مرتين — محضر 2094/6', $ACTOR);
check($r['ok'], 'وبسببٍ مكتوب: يُعتمد');

$conn->query("UPDATE supplier_evaluations SET decision_note = NULL WHERE id = {$EV1}");
$still = $conn->query("SELECT decision_note FROM supplier_evaluations WHERE id={$EV1}")->fetch_assoc();
check($still['decision_note'] !== null,
      '★ ومحوُ السبب مباشرةً **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-05-01', '2094-05-31', $ACTOR);
check(!$r['ok'] && $r['code'] === 423, 'وإعادةُ توليدِ **المعتمَد 423** — التصحيحُ بفترةٍ تالية');
$r = SES::decide($conn, $gate, $CO, $EV1, 'eligible', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 409, 'وقرارٌ ثانٍ على معتمَدٍ **409**');

// ═══ ⑥ الوصلُ الحي: شرطُ التجديد ═══
head('⑥ **الوصلُ الحي** — «ونتيجتُه شرطٌ في التجديد» (§4)');

$r = SCS::transition($conn, $gate, $CO, $C1, \App\Services\Contract\ContractStateMachine::RENEWED, 0, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'غيرُ مؤهَّلٍ للتجديد') !== false,
      '★ التجديدُ **423** — والسببُ يسمّي الفترةَ والنتيجةَ ونصَّ القرار');

$r = SES::generate($conn, $gate, $CO, $SUP1, '2094-06-01', '2094-06-30', $ACTOR);
check($r['ok'] && abs((float) $r['score'] - 100.0) < 0.005,
      'وتقييمُ حزيران **100.00** (عملٌ خالصٌ بلا عطلٍ ولا توقفٍ ولا بلاغ)');
$r2 = SES::decide($conn, $gate, $CO, intval($r['evaluation_id']), 'eligible',
    'تحسّنٌ تامٌّ في حزيران', $ACTOR);
check($r2['ok'], 'واعتُمد **مؤهَّلًا للتجديد**');

$g = SES::renewalGate($gate, $SUP1);
check($g['ok'] && abs((float) $g['score'] - 100.0) < 0.005,
      'وبوابةُ التجديد تقرأ **آخرَ معتمَدٍ بفترته** لا أولَه');

$r = SCS::transition($conn, $gate, $CO, $C1, \App\Services\Contract\ContractStateMachine::RENEWED, 0, $ACTOR);
check($r['ok'] && (string) $r['state'] === 'مجدَّد',
      '★★ والعقدُ **تجدّد** بعد تقييمٍ مؤهِّل — «ونتيجتُه شرطٌ في التجديد» حيّةً لا نصًّا');

$hd = SCS::head($gate, $C1);
check($hd && (string) $hd['state'] === 'مجدَّد', 'والحالةُ محفوظةٌ في الرأس');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
