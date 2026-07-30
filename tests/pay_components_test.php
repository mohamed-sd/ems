<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-08-② — اختبار قبول مكوّنات الأجر (CON-01 §3.2/§7.1 · PLAN-01 §5.2-②)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/pay_components_test.php
 *
 * ما يُثبته:
 *   ① البنية: pay_components بنوعَيه المحكومَين (ENUM 20 نوعًا · 10 طرق) وأعلامِ
 *      الدخول السبعة وFK الرأسِ ومركزِ التكلفة — والتسجيلُ في بوابة العزل.
 *   ② القاعدة: «عددُ المكوّنات غيرُ محدود» (3+ على عقدٍ واحد) و«لا نسبَ
 *      مثبَّتةً في الكود» (لا literal نسبةٍ في الخدمة).
 *   ③ التحقق: نوعٌ/طريقةٌ أجنبيان 422 · المبلغُ للثابت والمعدلُ للنسب 422 ·
 *      سالبٌ 422 · سريانٌ منعكس 422.
 *   ④ الحراس: عقدٌ نافذٌ 423 «بملحق» · مرحَّلٌ قراءةً 423 بمصدره ·
 *      الإنهاءُ يؤرّخ valid_to على المحرَّر حصرًا.
 *
 * البذرُ معزول: عقدُ اختبارٍ بوسم H082_<pid> وتواريخَ 2032 يُكنس في النهاية.
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
$MARK = 'H082_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn) {
    $conn->query("DELETE pc FROM pay_components pc JOIN employee_contracts ec ON ec.id = pc.contract_id
                   WHERE ec.relation_type LIKE 'H082_%'");
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H082_%'");
};
register_shutdown_function($teardown);
$teardown(); // بقايا جولاتٍ سابقة

fwrite(STDOUT, "\n══ H-08-② — مكوّناتُ الأجر بخصائصها وطريقةِ حسابها وسريانها ══\n");

// ═══ ① البنية ═══
head('① البنية — القائمتان المحكومتان والأعلامُ السبعة');
$r = $conn->query("SHOW TABLES LIKE 'pay_components'");
check($r && $r->num_rows === 1, 'جدول pay_components قائم');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pay_components' AND COLUMN_NAME='component_type'");
check($r && substr_count(strval($r->fetch_assoc()['COLUMN_TYPE']), "','") === 19, 'النوعُ ENUM بقائمة §3.2 العشرين');
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pay_components' AND COLUMN_NAME='calc_method'");
check($r && substr_count(strval($r->fetch_assoc()['COLUMN_TYPE']), "','") === 9, 'طريقةُ الاحتساب ENUM بالعشر');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pay_components'
                     AND COLUMN_NAME IN ('in_insurance','in_tax','in_leave_pay','in_eos','in_hour_base','in_overtime','in_incentive_base')");
check($r && intval($r->fetch_assoc()['c']) === 7, 'أعلامُ الدخول السبعة («خصائصُها السبع» PLAN-01)');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pay_components'
                     AND REFERENCED_TABLE_NAME IN ('employee_contracts','fin_cost_centers')");
check($r && intval($r->fetch_assoc()['c']) === 2, 'FK → employee_contracts + fin_cost_centers');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'pay_components' => array('type' => self::T_TENANT, 'soft' => true)") !== false,
      'pay_components في السجل: T_TENANT بحذفٍ ناعم');
check(count(ECS::COMPONENT_TYPES) === 20 && count(ECS::CALC_METHODS) === 10 && count(ECS::COMPONENT_FLAGS) === 7,
      'قوائمُ الخدمة: 20 نوعًا · 10 طرق · 7 أعلام');

// «لا نسبَ مثبَّتةً في الكود» — لا literal نسبةٍ عشريةٍ في دوال المكوّنات
$svc = file_get_contents(dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php');
check(!preg_match('/(rate|value|pct)[^;\n]*=\s*[0-9]+\.[0-9]/', $svc),
      'لا نسبةَ ولا قيمةَ افتراضيةً مثبَّتةً في كود الخدمة (قاعدة §3.2)');

// ═══ بذرُ عقدِ اختبار ═══
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
$pmFixed = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_allowances'")->fetch_assoc()['id']);
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'permanent',
    'pay_model_id' => $pmFixed, 'relation_type' => $MARK,
    'start_date' => '2032-01-01', 'end_date' => '2032-12-31'), $CREATOR);
$CID = intval($r['id']);
check($CID > 0, "عقدُ بذرٍ مسودةً (#{$CID} · شخص #{$EID})");

// ═══ ② + ③ الإضافةُ والتحقق ═══
head('② عددٌ غيرُ محدودٍ — و③ قواعدُ التحقق');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'basic',
    'calc_method' => 'fixed_amount', 'value' => '850', 'in_insurance' => 1, 'in_tax' => 1,
    'in_leave_pay' => 1, 'in_eos' => 1, 'in_hour_base' => 1, 'in_incentive_base' => 1,
    'valid_from' => '2032-01-01'), $CREATOR);
check($r['ok'] && intval($r['id']) > 0, 'الأساسيُّ مبلغًا ثابتًا بأعلامه');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'housing',
    'calc_method' => 'pct_basic', 'rate' => '25', 'in_eos' => 1), $CREATOR);
check($r['ok'], 'السكنُ نسبةً من الأساسي (المعدلُ من الاتفاق لا من الكود)');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'hazard',
    'calc_method' => 'per_shift', 'rate' => '15', 'is_variable' => 1, 'periodicity' => 'periodic'), $CREATOR);
check($r['ok'], 'المخاطرُ عن ورديةٍ متغيّرًا — الثالثُ يمضي (عددٌ غيرُ محدود)');
$compId = intval($r['id']);
$n = intval($conn->query("SELECT COUNT(*) c FROM pay_components WHERE contract_id = {$CID}")->fetch_assoc()['c']);
check($n === 3, "ثلاثةُ مكوّناتٍ على العقد ({$n})");

$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'overtime_pay',
    'calc_method' => 'fixed_amount', 'value' => '1'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'نوعٌ من خارج العشرين → 422');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'basic',
    'calc_method' => 'percentage', 'rate' => '10'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'طريقةٌ من خارج العشر → 422');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'site',
    'calc_method' => 'fixed_amount'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مبلغٌ ثابتٌ بلا value → 422');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'site',
    'calc_method' => 'pct_gross'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'نسبةٌ بلا rate → 422');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'site',
    'calc_method' => 'fixed_amount', 'value' => '-50'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مبلغٌ سالبٌ → 422 (الخصومُ بيتُها M-11)');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'site',
    'calc_method' => 'fixed_amount', 'value' => '100',
    'valid_from' => '2032-06-01', 'valid_to' => '2032-01-01'), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'سريانٌ منعكس → 422');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'custom',
    'calc_method' => 'tiers'), $CREATOR);
check($r['ok'], 'الشرائحُ تُقبل بلا قيمةٍ (بيانُها لاحق) — والعطبُ ليس صمتًا بل تصريحًا');
$tiersId = intval($r['id']);

// التعديلُ والإنهاء على المحرَّر
$r = ECS::updateComponent($conn, $gate, $CO, $compId, array('rate' => '18'), $CREATOR);
check($r['ok'], 'تعديلُ المعدل على المسودة يمضي');
$r = ECS::endComponent($conn, $gate, $CO, $tiersId, '2032-06-30', $CREATOR);
$row = $conn->query("SELECT state, valid_to FROM pay_components WHERE id = {$tiersId}")->fetch_assoc();
check($r['ok'] && $row['state'] === 'ended' && $row['valid_to'] === '2032-06-30',
      'الإنهاءُ يؤرّخ valid_to ويقلب الحالة ended');

// ═══ ④ الحراس ═══
head('④ الحراس — النافذُ بملحقٍ والمرحَّلُ في مصدره');
// دورةُ العقد إلى النفاذ
foreach (array('completed', 'validated') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER);
foreach (array('accepted', 'signed', 'active') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
$st = $conn->query("SELECT state FROM employee_contracts WHERE id = {$CID}")->fetch_assoc()['state'];
check($st === 'active', 'عقدُ البذر بلغ النفاذ');
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'night',
    'calc_method' => 'per_shift', 'rate' => '5'), $CREATOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'ملحق') !== false,
      'إضافةٌ على نافذ → 423 «بملحقٍ بسريان (H-10)»');
$r = ECS::updateComponent($conn, $gate, $CO, $compId, array('rate' => '99'), $CREATOR);
check(!$r['ok'] && $r['code'] === 423, 'تعديلٌ على نافذ → 423');
$r = ECS::endComponent($conn, $gate, $CO, $compId, '2032-07-01', $CREATOR);
check(!$r['ok'] && $r['code'] === 423, 'إنهاءٌ على نافذ → 423');

$mig = $conn->query("SELECT id FROM employee_contracts WHERE source_table IS NOT NULL LIMIT 1")->fetch_assoc();
if ($mig) {
    $r = ECS::addComponent($conn, $gate, $CO, intval($mig['id']), array('component_type' => 'basic',
        'calc_method' => 'fixed_amount', 'value' => '1'), $CREATOR);
    check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'مرحَّل') !== false,
          'مكوّنٌ على مرحَّلٍ قراءةً → 423 بمصدره');
} else { bad('لا صفَّ مرحَّلًا للفحص'); }

// الشاشةُ موصولة
$scr = file_get_contents(dirname(__DIR__) . '/Workforce/contract_registry.php');
check(strpos($scr, 'comp_add') !== false && strpos($scr, 'addComponent') !== false
      && strpos($scr, 'COMPONENT_TYPES') !== false,
      'الشاشةُ موصولةٌ بالخدمة (إضافةٌ/تعديلٌ/إنهاءٌ عبرها حصرًا)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
