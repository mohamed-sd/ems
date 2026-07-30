<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-15 — اختبار قبول: قواعدُ التحميل المسعَّرة وقواعدُ الجزاء
 *        (CON-03 §2-⑥ · §2-⑦ · §4 · §6 · ENT-02 §3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_rules_test.php
 *
 * ما يُثبته:
 *   ① التحميلاتُ الستُّ وطرائقُ التسعير الثلاث نصًّا — وما خرج عنها 422.
 *   ② **«قاعدةُ تحميلٍ بلا سعرٍ مكتوب → 422»** (§6-Validation) — خدمةً **وقيدًا
 *      بنيويًّا** معًا.
 *   ③ التسعيرُ الصحيح: تكلفةٌ كما هي · تكلفةٌ + نسبة · مبلغٌ ثابت · **والسقفُ يقصّ**.
 *   ④ **وبلا قاعدةٍ: التكلفةُ الخام موسومةً — لا نسبةٌ مخترَعة ولا حجب**.
 *   ⑤ الجزاءُ بقاعدته: عتبةٌ تُفعّله · معدلٌ يحسبه · **وسقفٌ يقصّه** (ENT-02 §3).
 *   ⑥ **«يشدّد لا يعكس» (§4)**: نقضُ الإسناد الموروث بلا سببٍ مكتوب → 422 ·
 *      و**CHECK يرفضه بنيويًّا** ولو التفّ أحدٌ على الخدمة.
 *   ⑦ الوصلُ الحي: تسعيرُ التحميلات في `SettlementService::collectLines`.
 *
 * البذرُ معزول: عقدُ موردٍ M15T وقواعدُه — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SupplierRuleService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Settlement\SupplierRuleService as SRS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999821;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM supplier_contracts WHERE notes LIKE 'M15T%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM supplier_charge_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_penalty_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contract_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contracts WHERE id = {$cid}");
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-15 — قواعدُ التحميل والجزاء ══\n");

head('البذر — عقدُ موردٍ نافذٌ للقواعد');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'start_date' => '2054-01-01', 'end_date' => '2054-12-31',
    'currency' => 'USD', 'notes' => 'M15T عقدُ اختبار القواعد'), $ACTOR);
check($r['ok'], 'عقدُ موردٍ أُنشئ');
$CID = intval($r['contract_id']);
$conn->query("UPDATE supplier_contracts SET state='نافذ' WHERE id={$CID}");

// ═══ ① القوائمُ المحكومة ═══
head('① القوائمُ المحكومة (§2-⑥ · §6)');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'catering', 'pricing' => 'cost', 'valid_from' => '2054-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '§2-⑥') !== false,
      'نوعُ تحميلٍ خارج الستة → 422 بنصّ المصدر');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'fuel', 'pricing' => 'markup', 'valid_from' => '2054-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وطريقةُ تسعيرٍ خارج الثلاث → 422');

// ═══ ② لا قاعدةَ بلا سعرٍ مكتوب ═══
head('② **«قاعدةُ تحميلٍ بلا سعرٍ مكتوب → 422»**');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'spares', 'pricing' => 'cost_plus', 'valid_from' => '2054-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'بكم') !== false,
      'تكلفةٌ مضافةٌ **بلا نسبة** → 422 («قاعدةٌ تُكتب ثم يُسأل عنها بكم؟»)');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'transport', 'pricing' => 'fixed', 'valid_from' => '2054-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'ومبلغٌ ثابتٌ **بلا مبلغ** → 422');

$conn->query("INSERT INTO supplier_charge_rules (company_id, contract_id, charge_type, pricing,
              rate, valid_from) VALUES ({$CO}, {$CID}, 'transport', 'fixed', NULL, '2054-01-01')");
$leak = intval($conn->query("SELECT COUNT(*) n FROM supplier_charge_rules
                              WHERE contract_id={$CID} AND charge_type='transport'")->fetch_assoc()['n']);
check($leak === 0, 'وكتابةٌ مباشرةٌ بلا معدل **يرفضها CHECK** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ③ التسعير ═══
head('③ التسعيرُ الصحيحُ بطرائقه الثلاث والسقفُ يقصّ');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'fuel', 'pricing' => 'cost', 'valid_from' => '2054-01-01'), $ACTOR);
check($r['ok'], 'قاعدةُ وقودٍ **بسعر التكلفة**');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'spares', 'pricing' => 'cost_plus', 'rate' => 15,
    'valid_from' => '2054-01-01'), $ACTOR);
check($r['ok'], 'وقطعُ غيارٍ **تكلفةٌ + 15٪**');
$r = SRS::saveChargeRule($conn, $gate, $CO, $CID, array(
    'charge_type' => 'maintenance', 'pricing' => 'cost_plus', 'rate' => 20,
    'cap' => 500, 'valid_from' => '2054-01-01'), $ACTOR);
check($r['ok'], 'وصيانةٌ **تكلفةٌ + 20٪ بسقف 500**');

$p = SRS::priceCharge($gate, $SUP, 'fuel', 1000, '2054-06-15');
check(abs($p['amount'] - 1000.0) < 0.005 && $p['basis'] === 'cost',
      'وقودٌ 1000 ⇒ **1000** كما هي (بسعر التكلفة)');
$p = SRS::priceCharge($gate, $SUP, 'parts', 1000, '2054-06-15');
check(abs($p['amount'] - 1150.0) < 0.005 && $p['basis'] === 'cost_plus',
      'وقطعُ غيارٍ 1000 ⇒ **1150** (تكلفةٌ + 15٪)');
$p = SRS::priceCharge($gate, $SUP, 'maintenance', 1000, '2054-06-15');
check(abs($p['amount'] - 500.0) < 0.005 && $p['capped'] === true
      && strpos($p['note'], 'مقصوصٌ بالسقف') !== false,
      'وصيانةٌ 1000 ⇒ 1200 **مقصوصةٌ إلى 500** — والقصُّ **معلَنٌ في نصّ السطر**');

// ═══ ④ بلا قاعدة ═══
head('④ **بلا قاعدةٍ: التكلفةُ الخام موسومةً** — لا اختراعَ ولا حجب');
$p = SRS::priceCharge($gate, $SUP, 'transport', 777, '2054-06-15');
check(abs($p['amount'] - 777.0) < 0.005 && $p['basis'] === 'no_rule'
      && $p['rule_id'] === null && strpos($p['note'], 'بلا قاعدةِ تسعيرٍ مكتوبة') !== false,
      'ترحيلٌ 777 بلا قاعدةٍ ⇒ **777 موسومةً** («يُرفض البند لا التسوية»)');

$p = SRS::priceCharge($gate, $SUP, 'fuel', 1000, '2055-06-15');
check($p['basis'] === 'no_rule', 'وخارجَ سريان القاعدة: تُعامَل معاملةَ بلا قاعدة');

// ═══ ⑤ الجزاء ═══
head('⑤ الجزاءُ بقاعدته وسقفه (§2-⑦ · ENT-02 §3)');
$r = SRS::savePenaltyRule($conn, $gate, $CO, $CID, array(
    'kind' => 'readiness', 'threshold' => 85, 'rate' => 100, 'rate_basis' => 'per_unit',
    'cap_percent' => 10, 'valid_from' => '2054-01-01',
    'formula_note' => 'عن كل نقطةِ نقصٍ تحت 85٪ ⇒ 100'), $ACTOR);
check($r['ok'], 'قاعدةُ جاهزيةٍ: حدٌّ 85٪ · 100 للنقطة · سقفٌ 10٪ من الأساس');

$a = SRS::assessPenalty($gate, $SUP, 'readiness', 90, 50000, '2054-06-15');
check($a['triggered'] === false && abs($a['amount']) < 0.005,
      'جاهزيةُ 90٪ فوق الحد ⇒ **لا جزاء**');

$a = SRS::assessPenalty($gate, $SUP, 'readiness', 81, 50000, '2054-06-15');
check($a['triggered'] === true && abs($a['amount'] - 400.0) < 0.005,
      'وجاهزيةُ 81٪ ⇒ عجزُ 4 نقاطٍ × 100 = **400**');

$a = SRS::assessPenalty($gate, $SUP, 'readiness', 20, 50000, '2054-06-15');
check($a['capped'] === true && abs($a['amount'] - 5000.0) < 0.005
      && strpos($a['note'], 'مقصوصٌ بسقف') !== false,
      'وجاهزيةُ 20٪ ⇒ 6500 **مقصوصةٌ بسقف 10٪ من 50000 = 5000** — «ولا تتجاوز سقفَها التعاقدي»');

$a = SRS::assessPenalty($gate, $SUP, 'delay', 10, 50000, '2054-06-15');
check($a['rule_id'] === null && abs($a['amount']) < 0.005
      && strpos($a['note'], 'لا يُجزى بلا قاعدة') !== false,
      'ونوعٌ بلا قاعدةٍ مكتوبة ⇒ **لا يُجزى** ويُعلَن');

// ═══ ⑥ يشدّد لا يعكس ═══
head('⑥ **«له أن يشدّد جزاءَه لا أن يعكس إسنادًا»** (§4)');
$r = SRS::savePenaltyRule($conn, $gate, $CO, $CID, array(
    'kind' => 'coverage', 'rate' => 50, 'inherits_attribution' => 0,
    'valid_from' => '2054-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'يشدّد') !== false,
      'نقضُ الإسناد **بلا سببٍ مكتوب** → 422 بنصّ §4');

$r = SRS::savePenaltyRule($conn, $gate, $CO, $CID, array(
    'kind' => 'coverage', 'rate' => 50, 'inherits_attribution' => 0,
    'override_reason' => 'اتفاقٌ تجاريٌّ يحمّله تغطيةَ يومٍ بعينه — محضر 2054/9',
    'valid_from' => '2054-01-01'), $ACTOR);
check($r['ok'], 'وبسببٍ مكتوب: تُقبل');
$a = SRS::assessPenalty($gate, $SUP, 'coverage', 2, 10000, '2054-06-15');
check($a['inherits_attribution'] === false && strpos($a['note'], 'ينقض الإسنادَ الموروث') !== false,
      'والاحتسابُ **يُعلن نقضَه للإسناد وسببَه** في نصّ السطر');

$conn->query("INSERT INTO supplier_penalty_rules (company_id, contract_id, kind, rate,
              inherits_attribution, override_reason, valid_from)
              VALUES ({$CO}, {$CID}, 'shortfall', 10, 0, NULL, '2054-01-01')");
$leak2 = intval($conn->query("SELECT COUNT(*) n FROM supplier_penalty_rules
                               WHERE contract_id={$CID} AND kind='shortfall'")->fetch_assoc()['n']);
check($leak2 === 0, 'وكتابةٌ مباشرةٌ تنقض الإسنادَ بلا سبب **يرفضها CHECK**');

// ═══ ⑦ الوصل ═══
head('⑦ الوصلُ الحي في تسويةِ المورد');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php');
check(substr_count($src, 'SupplierRuleService::priceCharge') >= 2,
      'تسعيرُ قطع الغيار **والصيانة** يمرّ بالقاعدة المكتوبة');
check(strpos($src, "\$priced['amount']") !== false,
      'والمبلغُ المحمَّل هو **المسعَّر** لا الخام');

// ═══ ⑧ القراءات ═══
head('⑧ القراءاتُ للشاشة');
check(count(SRS::chargeRulesOf($gate, $CID)) === 3, 'ثلاثُ قواعدِ تحميلٍ للعقد');
check(count(SRS::penaltyRulesOf($gate, $CID)) === 2, 'وقاعدتا جزاء');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
