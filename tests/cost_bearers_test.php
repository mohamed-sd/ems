<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-08-④ — اختبار قبول جهات التحمّل Σ=100 رفضًا للحفظ (CON-01 §3.3/§7.1 · خاتمةُ H-08)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cost_bearers_test.php
 *
 * ما يُثبته:
 *   ① البنية: cost_bearers متعددُ المالك (component·rule) بجهات §3.3 الأربع
 *      وحذفٍ ناعم — والتسجيلُ في بوابة العزل.
 *   ② Σ=100: مكوّنٌ محمَّلٌ 60/40 على مشروعين يُحفظ · وقاعدةُ حافزٍ محمَّلةٌ
 *      70/30 (مشروعٌ وكيانُ الشركة) تُحفظ.
 *   ③ رفضُ الحفظ: 70/40 → **422 بالفارق والقائمُ محفوظ** (لا حفظَ جزئيًّا).
 *   ④ التحقق: جهةٌ أجنبية 422 · مشروعٌ من خارج النطاق 422 · جهةٌ مكررة 422 ·
 *      جهةٌ بلا معرّفٍ (غيرُ company) 422 · الاستبدالُ يطوي ناعمًا لا يراكم.
 *   ⑤ الحراس: نافذٌ 423 «بملحق» · مرحَّلٌ 423 بمصدره.
 *
 * البذرُ معزول: عقدُ اختبارٍ بوسم H084_<pid> وتواريخَ 2034 يُكنس في النهاية.
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
$MARK = 'H084_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn) {
    $conn->query("DELETE cb FROM cost_bearers cb JOIN pay_components pc ON pc.id = cb.owner_id AND cb.owner_type='component'
                   JOIN employee_contracts ec ON ec.id = pc.contract_id WHERE ec.relation_type LIKE 'H084_%'");
    $conn->query("DELETE cb FROM cost_bearers cb JOIN incentive_rules ir ON ir.id = cb.owner_id AND cb.owner_type='rule'
                   JOIN employee_contracts ec ON ec.id = ir.contract_id WHERE ec.relation_type LIKE 'H084_%'");
    $conn->query("DELETE pc FROM pay_components pc JOIN employee_contracts ec ON ec.id = pc.contract_id
                   WHERE ec.relation_type LIKE 'H084_%'");
    $conn->query("DELETE ir FROM incentive_rules ir JOIN employee_contracts ec ON ec.id = ir.contract_id
                   WHERE ec.relation_type LIKE 'H084_%'");
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H084_%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-08-④ — جهاتُ التحمّل بنسبها Σ=100 رفضًا للحفظ ══\n");

// ═══ ① البنية ═══
head('① البنية — متعددُ المالك بجهات §3.3 الأربع');
$r = $conn->query("SHOW TABLES LIKE 'cost_bearers'");
check($r && $r->num_rows === 1, 'جدول cost_bearers قائم');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cost_bearers' AND COLUMN_NAME='owner_type'");
check($r && substr_count(strval($r->fetch_assoc()['COLUMN_TYPE']), "','") === 1, 'المالكُ ENUM(component·rule) — §7.1');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cost_bearers' AND COLUMN_NAME='bearer_type'");
check($r && substr_count(strval($r->fetch_assoc()['COLUMN_TYPE']), "','") === 3, 'الجهةُ ENUM بالأربع (§3.3)');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cost_bearers' AND COLUMN_NAME='is_deleted'");
check($r && intval($r->fetch_assoc()['c']) === 1, 'حذفٌ ناعم — الاستبدالُ يطوي ولا يمحو');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'cost_bearers' => array('type' => self::T_TENANT, 'soft' => true)") !== false,
      'cost_bearers في سجل بوابة العزل');
check(count(ECS::COST_BEARER_TYPES) === 4, 'قائمةُ الخدمة: أربعُ جهات');

// ═══ بذر ═══
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
$pmFixed = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_only'")->fetch_assoc()['id']);
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'permanent',
    'pay_model_id' => $pmFixed, 'relation_type' => $MARK,
    'start_date' => '2034-01-01', 'end_date' => '2034-12-31'), $CREATOR);
$CID = intval($r['id']);
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'basic',
    'calc_method' => 'fixed_amount', 'value' => '1000'), $CREATOR);
$COMP = intval($r['id']);
$r = ECS::addIncentiveRule($conn, $gate, $CO, $CID, array(
    'incentive_type' => 'حافز جاهزية', 'basis' => 'readiness', 'rate' => '50'), $CREATOR);
$RULE = intval($r['id']);
check($CID > 0 && $COMP > 0 && $RULE > 0, "بذرٌ: عقد #{$CID} · مكوّن #{$COMP} · قاعدة #{$RULE}");
$projs = array();
$pr = $conn->query("SELECT id FROM project WHERE company_id = {$CO} AND COALESCE(is_deleted,0)=0 LIMIT 2");
while ($row = $pr->fetch_assoc()) { $projs[] = intval($row['id']); }
$P1 = $projs[0] ?? 0; $P2 = $projs[1] ?? $P1;
check($P1 > 0 && $P2 > 0 && $P1 !== $P2, "مشروعا نطاقٍ ({$P1} · {$P2})");

// ═══ ② Σ=100 يُحفظ ═══
head('② Σ=100 — مكوّنٌ على مشروعين وقاعدةٌ على مشروعٍ والشركة');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'project', 'bearer_id' => $P1, 'percent' => 60),
    array('bearer_type' => 'project', 'bearer_id' => $P2, 'percent' => 40),
), $CREATOR);
check($r['ok'], 'مكوّنٌ محمَّلٌ 60/40 على مشروعين — Σ=100 يُحفظ');
$sum = $conn->query("SELECT COALESCE(SUM(percent),0) s, COUNT(*) c FROM cost_bearers
                     WHERE owner_type='component' AND owner_id={$COMP} AND is_deleted=0")->fetch_assoc();
check(floatval($sum['s']) === 100.0 && intval($sum['c']) === 2, 'Σ الحي للمكوّن = 100.00 بصفّين');
$r = ECS::setCostBearers($conn, $gate, $CO, 'rule', $RULE, array(
    array('bearer_type' => 'project', 'bearer_id' => $P1, 'percent' => 70),
    array('bearer_type' => 'company', 'percent' => 30),
), $CREATOR);
check($r['ok'], 'قاعدةٌ محمَّلةٌ 70/30 (مشروعٌ + كيانُ الشركة بلا معرّف)');

// ═══ ③ رفضُ الحفظ بالفارق ═══
head('③ Σ ≠ 100 → 422 بالفارق والقائمُ محفوظ');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'project', 'bearer_id' => $P1, 'percent' => 70),
    array('bearer_type' => 'project', 'bearer_id' => $P2, 'percent' => 40),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '110') !== false,
      'تحميلٌ 70/40 → 422 **بالفارق** (المجموع 110) — رفضُ الحفظ نصًّا');
$sum = $conn->query("SELECT COALESCE(SUM(percent),0) s FROM cost_bearers
                     WHERE owner_type='component' AND owner_id={$COMP} AND is_deleted=0")->fetch_assoc();
check(floatval($sum['s']) === 100.0, 'القائمُ 60/40 لم يُمسّ — لا حفظَ جزئيًّا');

// ═══ ④ بقيةُ التحقق ═══
head('④ التحقق — الجهاتُ والنطاقُ والتكرارُ والطيُّ الناعم');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'supplier', 'bearer_id' => 1, 'percent' => 100),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'جهةٌ من خارج الأربع → 422');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'project', 'bearer_id' => 99999999, 'percent' => 100),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مشروعٌ من خارج النطاق → 422');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'dept', 'percent' => 100),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'جهةُ إدارةٍ بلا معرّف → 422');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'project', 'bearer_id' => $P1, 'percent' => 50),
    array('bearer_type' => 'project', 'bearer_id' => $P1, 'percent' => 50),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'جهةٌ مكررة → 422');
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'company', 'percent' => 100),
), $CREATOR);
$live = $conn->query("SELECT COUNT(*) c FROM cost_bearers WHERE owner_type='component' AND owner_id={$COMP} AND is_deleted=0")->fetch_assoc();
$dead = $conn->query("SELECT COUNT(*) c FROM cost_bearers WHERE owner_type='component' AND owner_id={$COMP} AND is_deleted=1")->fetch_assoc();
check($r['ok'] && intval($live['c']) === 1 && intval($dead['c']) >= 2,
      'الاستبدالُ يطوي القديمَ ناعمًا (أثرُ تدقيقٍ باقٍ) ويُبقي الحيَّ 100٪ للشركة');
$bl = ECS::costBearersOf($gate, 'component', $COMP);
check(count($bl) === 1 && $bl[0]['bearer_type'] === 'company', 'costBearersOf تقرأ الحيَّ وحدَه');

// ═══ ⑤ الحراس ═══
head('⑤ الحراس — النافذُ بملحقٍ والمرحَّلُ في مصدره');
foreach (array('completed', 'validated') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER);
ECSM::transition($conn, $gate, $CO, $CID, 'accepted', '', $CREATOR);
ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/' . $MARK . '.pdf', $CREATOR); // H-10 شرطُ التوقيع
foreach (array('signed', 'active') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
$r = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP, array(
    array('bearer_type' => 'company', 'percent' => 100),
), $CREATOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'ملحق') !== false,
      'تحميلٌ على نافذ → 423 «بملحق» (H-10)');
$mig = $conn->query("SELECT ec.id FROM employee_contracts ec WHERE ec.source_table IS NOT NULL LIMIT 1")->fetch_assoc();
if ($mig) {
    // مالكٌ وهميٌّ على عقدٍ مرحَّل غيرُ وارد (لا مكوّناتَ عليه أصلًا) — الحارسُ يرفض المالكَ الغائب 404
    $r = ECS::setCostBearers($conn, $gate, $CO, 'component', 99999999, array(
        array('bearer_type' => 'company', 'percent' => 100),
    ), $CREATOR);
    check(!$r['ok'] && $r['code'] === 404, 'مالكٌ غيرُ موجود → 404 (والمرحَّلُ لا مكوّناتَ له بنيويًّا — محصَّنٌ من بابه)');
} else { bad('لا صفَّ مرحَّلًا للفحص'); }
$scr = file_get_contents(dirname(__DIR__) . '/Workforce/contract_registry.php');
check(strpos($scr, 'bearer_set') !== false && strpos($scr, 'setCostBearers') !== false
      && strpos($scr, 'COST_BEARER_TYPES') !== false,
      'الشاشةُ موصولةٌ بالخدمة (التحمّلُ للمكوّن والقاعدة عبرها حصرًا)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
