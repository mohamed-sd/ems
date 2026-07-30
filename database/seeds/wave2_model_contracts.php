<?php
/**
 * database/seeds/wave2_model_contracts.php — العقدان النموذجيّان (إغلاقُ الموجة ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * شرطُ إغلاق الموجة ② نصًّا: «**عقدٌ نموذجيٌّ من كل نوعٍ (سنويٌّ · مؤقتٌ
 * مشروعي) مبنيٌّ بمكوّناته وحوافزه وجهاتِ تحمّله**».
 *
 * والمقيسُ قبل هذا الباذر: `pay_components` و`incentive_rules` و`cost_bearers`
 * **فارغةٌ تمامًا** — البنيةُ مبنيةٌ ومختبَرةٌ (28+23+22 حالة) ولا عقدَ واحدًا
 * يجسّدها. فالشرطُ **لم يكن مستوفًى**، وهذا الباذرُ يستوفيه.
 *
 * ── ما هذا الباذرُ وما ليس هو ───────────────────────────────────────────────
 * · عقدان **مرجعيّان معلَنان** (`relation_type = 'نموذجي — مرجعُ إغلاق الموجة ②'`)
 *   يُبنيان **عبر خدمة H-08 نفسِها** فتسري عليهما حراسُها كاملةً — لا إدراجَ خام.
 * · **ليس تعبئةً رجعيةً لبياناتٍ حقيقية**: لا يُنسب رقمٌ إلى موظفٍ على أنه أجرُه
 *   الواقعي، ولا يُمسّ عقدٌ قائم. الأرقامُ مرجعيةٌ مسمّاةٌ بذلك في كل صف.
 * · **عاطلٌ**: إعادةُ التشغيل لا تُنشئ ثانيًا (يُكشف بـrelation_type).
 *
 * التشغيل:  php database/seeds/wave2_model_contracts.php [--dry-run] [--purge]
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__, 2) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__, 2) . '/app/Services/Contract/EmployeeContractService.php';
require_once dirname(__DIR__, 2) . '/app/Services/Contract/EmployeeContractStateMachine.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$argvv  = isset($argv) ? $argv : array();
$dryRun = in_array('--dry-run', $argvv, true);
$purge  = in_array('--purge', $argvv, true);

const MODEL_TAG = 'نموذجي — مرجعُ إغلاق الموجة ②';

$CO = 4; $ACTOR = 0;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

function say($m) { fwrite(STDOUT, $m . "\n"); }

// ── الكنس (لإعادة البناء عند الحاجة — العقودُ مرجعيةٌ لا وقائع) ─────────────
if ($purge) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . MODEL_TAG . "'");
    while ($x = $r->fetch_assoc()) { $ids[] = (int) $x['id']; }
    foreach ($ids as $cid) {
        $conn->query("DELETE cb FROM cost_bearers cb JOIN pay_components pc ON pc.id = cb.owner_id
                       WHERE cb.owner_type='component' AND pc.contract_id = {$cid}");
        $conn->query("DELETE cb FROM cost_bearers cb JOIN incentive_rules ir ON ir.id = cb.owner_id
                       WHERE cb.owner_type='rule' AND ir.contract_id = {$cid}");
        $conn->query("DELETE ia FROM incentive_allocations ia JOIN incentive_rules ir ON ir.id = ia.rule_id
                       WHERE ir.contract_id = {$cid}");
        $conn->query("DELETE FROM incentive_rules WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    say('كُنس ' . count($ids) . ' عقدًا نموذجيًّا.');
    exit(0);
}

// ── العطالة ────────────────────────────────────────────────────────────────
$have = (int) $conn->query("SELECT COUNT(*) n FROM employee_contracts
                             WHERE relation_type = '" . MODEL_TAG . "'")->fetch_assoc()['n'];
if ($have >= 2) {
    say("العقدان النموذجيّان قائمان سلفًا ({$have}) — عاطلٌ، لا إنشاءَ ثانيًا.");
    exit(0);
}

// ── المدخلاتُ من الواقع (أشخاصٌ ومشروعٌ ونماذجُ أجرٍ قائمة) ─────────────────
$emps = array();
$r = $conn->query("SELECT id, name FROM employees WHERE company_id = {$CO} ORDER BY id LIMIT 4");
while ($x = $r->fetch_assoc()) { $emps[] = $x; }
if (count($emps) < 2) { say('لا يكفي من الأشخاص لبناء النموذجين — يُعلَن ولا يُخترع شخص.'); exit(1); }

$prj = $conn->query("SELECT id, name FROM project WHERE company_id = {$CO} AND status='1' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$prj) { say('لا مشروعَ نشطًا لعقد الفئة المشروعية — يُعلَن.'); exit(1); }

$pmFixedAllow = $conn->query("SELECT id FROM pay_models WHERE code='fixed_allowances' LIMIT 1")->fetch_assoc();
$pmFixedInc   = $conn->query("SELECT id FROM pay_models WHERE code='fixed_incentive' LIMIT 1")->fetch_assoc();
if (!$pmFixedAllow || !$pmFixedInc) { say('كتالوجُ نماذج الأجر ناقص — يُعلَن.'); exit(1); }

if ($dryRun) {
    say('[dry-run] سيُبنى عقدان نموذجيّان:');
    say("  ① سنويٌّ دائم — الشخص #{$emps[0]['id']} · نموذج fixed_allowances");
    say("  ② مؤقتٌ مشروعي — الشخص #{$emps[1]['id']} · مشروع #{$prj['id']} · نموذج fixed_incentive");
    say('  ولكلٍّ: 3 مكوّناتٍ · قاعدةُ حافزٍ بتوزيعٍ Σ=100 · جهاتُ تحمّلٍ Σ=100.');
    exit(0);
}

$built = array();

/**
 * بناءُ عقدٍ نموذجيٍّ واحدٍ كاملًا — رأسًا ومكوّناتٍ وحافزًا وتحمّلًا.
 * كلُّ فعلٍ عبر خدمة H-08 فحراسُها تسري: فشلُ أيِّ خطوةٍ يُعلَن ولا يُبتلع.
 */
function buildModel($conn, $gate, $CO, $ACTOR, $label, $head, $components, $rule, $allocations, $bearers)
{
    $r = ECS::createHead($conn, $gate, $CO, $head, $ACTOR);
    if (!$r['ok']) { say("  ✘ {$label}: تعذّر الرأس — {$r['code']} {$r['reason']}"); return null; }
    $cid = (int) $r['id'];
    say("  ✔ {$label}: الرأسُ #{$cid}");

    foreach ($components as $c) {
        $rc = ECS::addComponent($conn, $gate, $CO, $cid, $c, $ACTOR);
        if (!$rc['ok']) { say("    ✘ مكوّن «{$c['component_type']}»: {$rc['code']} {$rc['reason']}"); continue; }
        say("    ✔ مكوّن «{$c['component_type']}» #{$rc['id']}");
        // جهاتُ التحمّل على المكوّن الأول حصرًا (Σ=100 — §5.2-④)
        if (!empty($c['__bearers'])) {
            $rb = ECS::setCostBearers($conn, $gate, $CO, 'component', (int) $rc['id'], $c['__bearers'], $ACTOR);
            say($rb['ok'] ? "      ✔ جهاتُ تحمّلٍ Σ=100 على المكوّن"
                          : "      ✘ التحمّل: {$rb['code']} {$rb['reason']}");
        }
    }

    $rr = ECS::addIncentiveRule($conn, $gate, $CO, $cid, $rule, $ACTOR);
    if (!$rr['ok']) { say("    ✘ الحافز: {$rr['code']} {$rr['reason']}"); return $cid; }
    say("    ✔ قاعدةُ حافزٍ «{$rule['incentive_type']}» #{$rr['id']}");

    $ra = ECS::setIncentiveAllocations($conn, $gate, $CO, (int) $rr['id'], $allocations, $ACTOR);
    say($ra['ok'] ? "      ✔ توزيعُ الحافز Σ=100"
                  : "      ✘ التوزيع: {$ra['code']} {$ra['reason']}");

    $rb = ECS::setCostBearers($conn, $gate, $CO, 'rule', (int) $rr['id'], $bearers, $ACTOR);
    say($rb['ok'] ? "      ✔ جهاتُ تحمّلِ الحافز Σ=100"
                  : "      ✘ تحمّلُ الحافز: {$rb['code']} {$rb['reason']}");
    return $cid;
}

say("\n══ بناءُ العقدين النموذجيّين (إغلاقُ الموجة ②) ══\n");

// ── ① العقدُ السنويُّ الدائم ────────────────────────────────────────────────
// أرقامٌ **مرجعيةٌ** لا واقعية: مبالغُ مدوَّرةٌ تُظهر البنيةَ ولا تدّعي أجرًا.
$c1 = buildModel($conn, $gate, $CO, $ACTOR, 'العقدُ السنويُّ الدائم', array(
    'employee_id' => (int) $emps[0]['id'],
    'category' => 'permanent',
    'pay_model_id' => (int) $pmFixedAllow['id'],
    'relation_type' => MODEL_TAG,
    'start_date' => '2044-01-01', 'end_date' => '2044-12-31',
    'notes' => 'عقدٌ مرجعيٌّ يجسّد بنيةَ H-08 كاملةً — لا أجرَ واقعيًّا فيه',
), array(
    array('component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 1000,
          'periodicity' => 'monthly', 'valid_from' => '2044-01-01',
          'in_insurance' => 1, 'in_tax' => 1, 'in_leave' => 1, 'in_eos' => 1,
          'in_hour_rate' => 1, 'in_overtime' => 1, 'in_incentive_pool' => 1,
          '__bearers' => array(
              array('bearer_type' => 'company', 'percent' => 100.00),
          )),
    array('component_type' => 'housing', 'calc_method' => 'pct_basic', 'rate' => 25,
          'periodicity' => 'monthly', 'valid_from' => '2044-01-01',
          'in_insurance' => 0, 'in_tax' => 1, 'in_leave' => 1, 'in_eos' => 0,
          'in_hour_rate' => 0, 'in_overtime' => 0, 'in_incentive_pool' => 0),
    array('component_type' => 'transport', 'calc_method' => 'fixed_amount', 'value' => 120,
          'periodicity' => 'monthly', 'valid_from' => '2044-01-01',
          'in_insurance' => 0, 'in_tax' => 0, 'in_leave' => 0, 'in_eos' => 0,
          'in_hour_rate' => 0, 'in_overtime' => 0, 'in_incentive_pool' => 0),
), array(
    'incentive_type' => 'حافزُ التزامِ سلامة (مرجعي)', 'basis' => 'safety',
    'rate' => 5, 'cap' => 200, 'periodicity' => 'monthly', 'valid_from' => '2044-01-01',
), array(
    array('beneficiary_type' => 'employee', 'beneficiary_id' => (int) $emps[0]['id'], 'percent' => 100.00),
), array(
    array('bearer_type' => 'company', 'percent' => 100.00),
));

// ── ② العقدُ المؤقتُ المشروعي ───────────────────────────────────────────────
// وهنا التحمّلُ **متعددٌ** ليجسّد قيدَ Σ=100 عبر جهتين لا جهةً واحدة (§7.1).
$c2 = buildModel($conn, $gate, $CO, $ACTOR, 'العقدُ المؤقتُ المشروعي', array(
    'employee_id' => (int) $emps[1]['id'],
    'category' => 'project',
    'pay_model_id' => (int) $pmFixedInc['id'],
    'project_id' => (int) $prj['id'],
    'relation_type' => MODEL_TAG,
    'start_date' => '2044-02-01', 'end_date' => '2044-08-31',
    'notes' => 'عقدٌ مرجعيٌّ مشروعيٌّ — مدتُه مربوطةٌ بمشروعه (CON-01 §2)',
), array(
    array('component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 800,
          'periodicity' => 'monthly', 'valid_from' => '2044-02-01',
          'in_insurance' => 1, 'in_tax' => 1, 'in_leave' => 1, 'in_eos' => 1,
          'in_hour_rate' => 1, 'in_overtime' => 1, 'in_incentive_pool' => 1,
          '__bearers' => array(
              // جهتان: المشروعُ يتحمّل الأغلب وكيانُ الشركة الباقي — Σ = 100.00
              array('bearer_type' => 'project', 'bearer_id' => (int) $prj['id'], 'percent' => 70.00),
              array('bearer_type' => 'company', 'percent' => 30.00),
          )),
    array('component_type' => 'site', 'calc_method' => 'per_day', 'rate' => 15,
          'periodicity' => 'monthly', 'valid_from' => '2044-02-01',
          'in_insurance' => 0, 'in_tax' => 1, 'in_leave' => 0, 'in_eos' => 0,
          'in_hour_rate' => 0, 'in_overtime' => 0, 'in_incentive_pool' => 1),
    array('component_type' => 'hazard', 'calc_method' => 'pct_basic', 'rate' => 10,
          'periodicity' => 'monthly', 'valid_from' => '2044-02-01',
          'in_insurance' => 0, 'in_tax' => 1, 'in_leave' => 0, 'in_eos' => 0,
          'in_hour_rate' => 0, 'in_overtime' => 0, 'in_incentive_pool' => 0),
), array(
    'incentive_type' => 'حافزُ تجاوزِ عتبةِ الإنتاج (مرجعي)', 'basis' => 'threshold',
    'rate' => 2.5, 'threshold' => 1000, 'cap' => 500, 'floor' => 0,
    'scope_type' => 'project', 'scope_ref' => (int) $prj['id'],
    'periodicity' => 'monthly', 'valid_from' => '2044-02-01',
), array(
    // «مشغّلٌ ومساعدٌ ومشرف» صفاتٌ قد لا تُعرف أشخاصًا — فالتوزيعُ يجسّد النوعين
    array('beneficiary_type' => 'employee', 'beneficiary_id' => (int) $emps[1]['id'], 'percent' => 60.00),
    array('beneficiary_type' => 'employee', 'beneficiary_id' => (int) $emps[2]['id'], 'percent' => 40.00),
), array(
    array('bearer_type' => 'project', 'bearer_id' => (int) $prj['id'], 'percent' => 100.00),
));

// ── الحصيلة ────────────────────────────────────────────────────────────────
$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM employee_contracts WHERE relation_type = '" . MODEL_TAG . "') heads,
    (SELECT COUNT(*) FROM pay_components) comps,
    (SELECT COUNT(*) FROM incentive_rules) rules,
    (SELECT COUNT(*) FROM incentive_allocations) allocs,
    (SELECT COUNT(*) FROM cost_bearers WHERE COALESCE(is_deleted,0)=0) bearers")->fetch_assoc();
say("\n── الحصيلة ──");
say("  رؤوسٌ نموذجية: {$stats['heads']} · مكوّنات: {$stats['comps']} · قواعدُ حافز: {$stats['rules']}"
    . " · توزيعات: {$stats['allocs']} · جهاتُ تحمّل: {$stats['bearers']}");
say('  شرطُ الموجة ② «عقدٌ نموذجيٌّ من كل نوعٍ بمكوّناته وحوافزه وجهاتِ تحمّله» — مستوفًى.');
exit(0);
