<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-08-③ — اختبار قبول قواعد الحوافز وتوزيعها 100٪ (CON-01 §3.3/§7.1-7.3 · تدمج M-23)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/incentive_rules_test.php
 *
 * ما يُثبته:
 *   ① البنية: incentive_rules (أسسُ §3.3 السبعة ENUM · سقفٌ وحدٌّ أدنى ونطاقٌ
 *      وشرطُ استحقاق) + incentive_allocations (CASCADE · UQ مستفيدٍ لكل قاعدة)
 *      والتسجيلُ في بوابة العزل.
 *   ② N1 (§7.3): قاعدةُ طنٍّ بحافزٍ موزَّعٍ 80/20 ← تُحفظ وΣ=100.
 *   ③ N2 (§7.3): توزيعٌ 80/30 ← **422 بالفارق ولا يُحفظ** — والقائمُ لا يُمسّ
 *      (الذرّيةُ عبر replaceChildren).
 *   ④ التحقق: أساسٌ أجنبي 422 · سقفٌ دون الأدنى 422 · نسبةٌ خارج (0،100] 422 ·
 *      مستفيدٌ مكرر 422 · إعادةُ التوزيع تستبدل لا تُراكم · الغائبُ = 100٪ لصاحبه.
 *   ⑤ الحراس: نافذٌ 423 «بملحق» · مرحَّلٌ قراءةً 423 بمصدره · الإنهاءُ يؤرّخ.
 *
 * البذرُ معزول: عقدُ اختبارٍ بوسم H083_<pid> وتواريخَ 2033 يُكنس في النهاية.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'H083_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn) {
    // CASCADE يكنس التوزيعات مع قواعدها
    $conn->query("DELETE ir FROM incentive_rules ir JOIN employee_contracts ec ON ec.id = ir.contract_id
                   WHERE ec.relation_type LIKE 'H083_%'");
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H083_%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-08-③ — قواعدُ الحوافز وتوزيعُها بمجموع 100٪ ══\n");

// ═══ ① البنية ═══
head('① البنية — الأسسُ السبعة والتوزيعُ ابنُ قاعدته');
$r = $conn->query("SHOW TABLES LIKE 'incentive_rules'");
check($r && $r->num_rows === 1, 'جدول incentive_rules قائم');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='incentive_rules' AND COLUMN_NAME='basis'");
check($r && substr_count(strval($r->fetch_assoc()['COLUMN_TYPE']), "','") === 6, 'الأساسُ ENUM بأسس §3.3 السبعة');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='incentive_rules'
                     AND COLUMN_NAME IN ('cap','floor','threshold','condition_text','scope_type','periodicity')");
check($r && intval($r->fetch_assoc()['c']) === 6, 'السقفُ والأدنى والعتبةُ والشرطُ والنطاقُ والدورية — §3.3 كاملةً');
$r = $conn->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_ia_rule'");
$row = $r ? $r->fetch_assoc() : null;
check($row && $row['DELETE_RULE'] === 'CASCADE', 'التوزيعُ ابنُ قاعدته (CASCADE)');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='incentive_allocations' AND INDEX_NAME='uq_ia_beneficiary'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UQ المستفيد داخل القاعدة');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'incentive_rules' => array('type' => self::T_TENANT, 'soft' => true)") !== false
      && strpos($src, "'incentive_allocations' => array('type' => self::T_TENANT, 'soft' => false)") !== false,
      'الجدولان في سجل بوابة العزل');
check(count(ECS::INCENTIVE_BASES) === 7, 'قائمةُ الخدمة: سبعةُ أسس');

// ═══ بذرُ عقد ═══
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
$emp2 = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO} AND e.id <> {$EID} LIMIT 1")->fetch_assoc();
$EID2 = $emp2 ? intval($emp2['id']) : $EID;
$pmTon = intval($conn->query("SELECT id FROM pay_models WHERE code='per_ton'")->fetch_assoc()['id']);
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'operator',
    'pay_model_id' => $pmTon, 'relation_type' => $MARK,
    'start_date' => '2033-01-01', 'end_date' => '2033-12-31'), $CREATOR);
$CID = intval($r['id']);
check($CID > 0, "عقدُ بذرٍ تشغيليٌّ بالطن (#{$CID})");

// ═══ ② N1 — القاعدةُ والتوزيعُ 80/20 ═══
head('② N1 (§7.3) — حافزُ طنٍّ بسقفٍ وتوزيعٍ 80/20');
$r = ECS::addIncentiveRule($conn, $gate, $CO, $CID, array(
    'incentive_type' => 'حافز إنتاج الطن', 'basis' => 'threshold',
    'rate' => '0.35', 'threshold' => '4000', 'cap' => '900', 'floor' => '0',
    'condition_text' => 'فوق 4,000 طن شهريًّا'), $CREATOR);
check($r['ok'] && intval($r['id']) > 0, 'القاعدةُ بأساسها ومعدلها وعتبتها وسقفها (مثال الوثيقة §6 حرفيًّا)');
$RID = intval($r['id']);
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID, 'percent' => 80),
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID2, 'percent' => 20),
), $CREATOR);
check($r['ok'], 'التوزيعُ 80/20 يُحفظ (Σ=100)');
$sum = $conn->query("SELECT COALESCE(SUM(percent),0) s, COUNT(*) c FROM incentive_allocations WHERE rule_id = {$RID}")->fetch_assoc();
check(floatval($sum['s']) === 100.0 && intval($sum['c']) === 2, 'Σ في القاعدة = 100.00 بصفّين');

// ═══ ③ N2 — 80/30 يُرفض ولا يُمسّ القائم ═══
head('③ N2 (§7.3) — Σ ≠ 100 → 422 بالفارق والقائمُ محفوظ');
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID, 'percent' => 80),
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID2, 'percent' => 30),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '110') !== false,
      'توزيعٌ 80/30 → 422 **بالفارق** (المجموع 110)');
$sum = $conn->query("SELECT COALESCE(SUM(percent),0) s FROM incentive_allocations WHERE rule_id = {$RID}")->fetch_assoc();
check(floatval($sum['s']) === 100.0, 'القائمُ 80/20 لم يُمسّ (الذرّية — لا توزيعَ نصفيًّا أبدًا)');

// ═══ ④ بقيةُ التحقق ═══
head('④ التحقق — الأساسُ والحدودُ والتكرارُ والاستبدال');
$r = ECS::addIncentiveRule($conn, $gate, $CO, $CID, array(
    'incentive_type' => 'حافز غامض', 'basis' => 'attendance'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'أساسٌ من خارج السبعة → 422');
$r = ECS::addIncentiveRule($conn, $gate, $CO, $CID, array(
    'incentive_type' => 'مقلوب', 'basis' => 'quality', 'cap' => '100', 'floor' => '200'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'سقفٌ دون الحد الأدنى → 422');
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID, 'percent' => 0),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'نسبةٌ صفر → 422');
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID, 'percent' => 50),
    array('beneficiary_type' => 'employee', 'beneficiary_id' => $EID, 'percent' => 50),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مستفيدٌ مكرر → 422');
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(
    array('beneficiary_type' => 'job_title', 'beneficiary_id' => 1, 'percent' => 100),
), $CREATOR);
$n = intval($conn->query("SELECT COUNT(*) c FROM incentive_allocations WHERE rule_id = {$RID}")->fetch_assoc()['c']);
check($r['ok'] && $n === 1, 'إعادةُ التوزيع تستبدل لا تُراكم (صفٌّ واحدٌ 100٪ لمسمًّى)');
$r = ECS::setIncentiveAllocations($conn, $gate, $CO, $RID, array(), $CREATOR);
$n = intval($conn->query("SELECT COUNT(*) c FROM incentive_allocations WHERE rule_id = {$RID}")->fetch_assoc()['c']);
check($r['ok'] && $n === 0, 'التوزيعُ الغائب مقبول — 100٪ لصاحب العقد ضمنًا (§3.3 «قد يُوزَّع»)');

// ═══ ⑤ الحراس ═══
head('⑤ الحراس — النافذُ بملحقٍ والمرحَّلُ في مصدره والإنهاء');
$r = ECS::endIncentiveRule($conn, $gate, $CO, $RID, '2033-06-30', $CREATOR);
$row = $conn->query("SELECT state, valid_to FROM incentive_rules WHERE id = {$RID}")->fetch_assoc();
check($r['ok'] && $row['state'] === 'ended' && $row['valid_to'] === '2033-06-30', 'الإنهاءُ يؤرّخ valid_to');
foreach (array('completed', 'validated') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER);
foreach (array('accepted', 'signed', 'active') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
$r = ECS::addIncentiveRule($conn, $gate, $CO, $CID, array(
    'incentive_type' => 'متأخر', 'basis' => 'safety'), $CREATOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'ملحق') !== false,
      'قاعدةٌ على نافذ → 423 «بملحق» (لا رجعية — §3.3)');
$mig = $conn->query("SELECT id FROM employee_contracts WHERE source_table IS NOT NULL LIMIT 1")->fetch_assoc();
if ($mig) {
    $r = ECS::addIncentiveRule($conn, $gate, $CO, intval($mig['id']), array(
        'incentive_type' => 'دخيل', 'basis' => 'unit'), $CREATOR);
    check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'مرحَّل') !== false,
          'قاعدةٌ على مرحَّلٍ قراءةً → 423 بمصدره');
} else { bad('لا صفَّ مرحَّلًا للفحص'); }
$scr = file_get_contents(dirname(__DIR__) . '/Workforce/contract_registry.php');
check(strpos($scr, 'inc_add') !== false && strpos($scr, 'setIncentiveAllocations') !== false
      && strpos($scr, 'INCENTIVE_BASES') !== false,
      'الشاشةُ موصولةٌ بالخدمة (القاعدةُ والتوزيعُ عبرها حصرًا)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
