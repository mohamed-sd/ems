<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-10 — اختبار قبول ملاحق عقد الموظف والنسخة الموقَّعة المقفلة (CON-01 §4/§5/§7.2)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/employee_contract_amendment_test.php
 *
 * ما يُثبته:
 *   ① البنية: employee_contract_amendments (UQ عقد×سريان×نوع · FK RESTRICT ·
 *      أنواعُ §4) والتسجيلُ في بوابة العزل — والقائمُ contract_amendments لم يُمسّ.
 *   ② النسخةُ الموقَّعة: التوقيعُ بلا ملفٍّ 422 · الرفعُ في accepted حصرًا ·
 *      الثانيةُ 423 «ثابتةٌ لا تُستبدل».
 *   ③ الإنشاء: المسودةُ تُعدَّل مباشرةً فلا ملحقَ لها 422 · سريانٌ قبل البدء 422
 *      (§7.2 نصًّا) · نسخةٌ متغيرة 409 · مكرر (UQ) 409 · «قبل» من الواقع الحي.
 *   ④ الاعتماد: المنشئُ 403 · يطبّق ذريًّا (الحقلُ تغيّر · العقدُ amended ·
 *      version++) · الواقعُ المتغيِّرُ تحته 409 · **اللقطاتُ أُبطلت من السريان**.
 *   ⑤ الرفضُ بسببٍ إلزامي · المرحَّلُ قراءةً 423.
 *
 * البذرُ معزول: عقدُ اختبارٍ بوسم H10T_<pid> وتواريخَ 2036 يُكنس في النهاية.
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
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractAmendmentService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractSnapshotService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Contract\EmployeeContractAmendmentService as ECAS;
use App\Services\Contract\ContractSnapshotService as CSS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'H10T_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn) {
    $conn->query("DELETE a FROM employee_contract_amendments a JOIN employee_contracts ec ON ec.id = a.contract_id
                   WHERE ec.relation_type LIKE 'H10T_%'");
    $conn->query("DELETE cs FROM contract_snapshots cs JOIN employee_contracts ec ON ec.id = cs.contract_id
                   WHERE ec.relation_type LIKE 'H10T_%'");
    $conn->query("DELETE pc FROM pay_components pc JOIN employee_contracts ec ON ec.id = pc.contract_id
                   WHERE ec.relation_type LIKE 'H10T_%'");
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H10T_%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-10 — ملاحقُ عقد الموظف والنسخةُ الموقَّعة المقفلة ══\n");

// ═══ ① البنية ═══
head('① البنية — UQ الثلاثي وRESTRICT والقائمُ لم يُمسّ');
$r = $conn->query("SHOW TABLES LIKE 'employee_contract_amendments'");
check($r && $r->num_rows === 1, 'جدول employee_contract_amendments قائم');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_contract_amendments'
                     AND INDEX_NAME='uq_eca_contract_eff_type'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UQ (عقد × سريان × نوع) — §7.1 نصًّا');
$r = $conn->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_eca_contract'");
$row = $r ? $r->fetch_assoc() : null;
check($row && $row['DELETE_RULE'] === 'RESTRICT', 'FK RESTRICT — الملحقُ يمنع كنسَ أصله');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contract_amendments'
                     AND REFERENCED_TABLE_NAME='employee_contracts'");
check($r && intval($r->fetch_assoc()['c']) === 0, 'contract_amendments القائمُ (عقودُ العملاء) لم يُمسّ');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'employee_contract_amendments' => array('type' => self::T_TENANT, 'soft' => true)") !== false,
      'التسجيلُ في بوابة العزل');

// ═══ بذرٌ حتى accepted ═══
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
$pmFixed = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_only'")->fetch_assoc()['id']);
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'permanent',
    'pay_model_id' => $pmFixed, 'relation_type' => $MARK,
    'start_date' => '2036-01-01', 'end_date' => '2036-12-31'), $CREATOR);
$CID = intval($r['id']);
$r = ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'housing',
    'calc_method' => 'fixed_amount', 'value' => '150'), $CREATOR);
$COMP = intval($r['id']);
check($CID > 0 && $COMP > 0, "بذرٌ: عقد #{$CID} بمكوّن سكن #{$COMP} (مثال §7.2)");

// ملحقٌ على مسودة → 422 (تُعدَّل مباشرة)
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2036-03-01'), array(array('field' => 'head:end_date', 'after' => '2037-06-30')), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'ملحقٌ على مسودة → 422 (المسودةُ تُعدَّل مباشرةً)');

// ═══ ② النسخةُ الموقَّعة ═══
head('② النسخةُ الموقَّعة — شرطُ التوقيع وثباتُها');
foreach (array('completed', 'validated') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER);
$r = ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/x.pdf', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'الرفعُ قبل قبول الموظف → 422 (بابُه accepted)');
ECSM::transition($conn, $gate, $CO, $CID, 'accepted', '', $CREATOR);
$r = ECSM::transition($conn, $gate, $CO, $CID, 'signed', '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'التوقيعُ بلا نسخةٍ موقَّعة → 422 (شرطُ §4)');
$r = ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/' . $MARK . '.pdf', $CREATOR);
check($r['ok'], 'الرفعُ في accepted يمضي');
$r = ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/other.pdf', $CREATOR);
check(!$r['ok'] && $r['code'] === 423, 'الثانيةُ → 423 «ثابتةٌ لا تُستبدل — التصحيحُ ملحقٌ يوضّح»');
foreach (array('signed', 'active') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
$st = $conn->query("SELECT state, version FROM employee_contracts WHERE id = {$CID}")->fetch_assoc();
check($st['state'] === 'active', 'العقدُ نافذٌ بنسخته الموقَّعة');

// ═══ ③ الإنشاء ═══
head('③ إنشاءُ الملحق — السريانُ والنسخةُ و«قبل» الصادق');
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2035-12-01'), array(array('field' => 'component:' . $COMP . ':value', 'after' => '200')), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'سريانٌ قبل بدء العقد → 422 (§7.2 نصًّا)');
$ver = intval($st['version']);
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2036-03-01', 'expected_version' => $ver + 9),
    array(array('field' => 'component:' . $COMP . ':value', 'after' => '200')), $CREATOR);
check(!$r['ok'] && $r['code'] === 409, 'نسخةٌ متغيرة → 409 (§7.2)');
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2036-03-01', 'expected_version' => $ver),
    array(array('field' => 'component:' . $COMP . ':value', 'after' => '200')), $CREATOR);
check($r['ok'] && intval($r['id']) > 0, 'ملحقُ رفع السكن 150→200 بسريان أول آذار (مثال §7.2 حرفيًّا)');
$AMD = intval($r['id']);
check(strval($r['changes'][0]['before']) === '150.00', '«قبل» التُقط من الواقع الحي (150.00) لا من المرسل');
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2036-03-01'), array(array('field' => 'head:currency', 'after' => 'USD')), $CREATOR);
check(!$r['ok'] && $r['code'] === 409, 'مكرر (عقد×سريان×نوع) → 409 (UQ §7.1)');
$r = ECAS::createAmendment($conn, $gate, $CO, $CID, array('amend_type' => 'other',
    'effective_from' => '2036-04-01'), array(array('field' => 'component:99999:value', 'after' => '1')), $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مكوّنٌ ليس لهذا العقد → 422');

// لقطاتٌ قبل الاعتماد: شباط (قبل السريان) وآذار (بعده)
$s1 = CSS::snapshotFor($conn, $gate, $CO, $CID, '2036-02-01', $CREATOR);
$s2 = CSS::snapshotFor($conn, $gate, $CO, $CID, '2036-03-15', $CREATOR);
check($s1['ok'] && $s2['ok'], 'لقطتان: شباط (قبل السريان) وآذار (بعده)');

// ═══ ④ الاعتماد ═══
head('④ الاعتمادُ — فصلُ الواجبات والتطبيقُ الذري وإبطالُ اللقطات من السريان');
$r = ECAS::approveAmendment($conn, $gate, $CO, $AMD, $CREATOR);
check(!$r['ok'] && $r['code'] === 403, 'اعتمادُ المنشئ → 403');
$r = ECAS::approveAmendment($conn, $gateApprover, $CO, $AMD, $APPROVER);
check($r['ok'] && $r['snapshot_invalidated_from'] === '2036-03-01',
      'الاعتمادُ مضى وأعلن snapshot_invalidated_from=2036-03-01 (§7.2)');
$pc = $conn->query("SELECT value FROM pay_components WHERE id = {$COMP}")->fetch_assoc();
check(strval($pc['value']) === '200.00', 'التطبيقُ الذري: السكنُ صار 200.00');
$c = $conn->query("SELECT state, version FROM employee_contracts WHERE id = {$CID}")->fetch_assoc();
check($c['state'] === 'amended' && intval($c['version']) === $ver + 1, 'العقدُ amended وversion++ — والأصلُ لم «يُعدَّل» مباشرة');
$v1 = $conn->query("SELECT valid FROM contract_snapshots WHERE id = " . intval($s1['id']))->fetch_assoc();
$v2 = $conn->query("SELECT valid FROM contract_snapshots WHERE id = " . intval($s2['id']))->fetch_assoc();
check(intval($v1['valid']) === 1 && intval($v2['valid']) === 0,
      'لقطةُ شباطَ صالحةٌ ولقطةُ آذارَ أُبطلت — «ما قبل السريان بالقديم وما بعده بالجديد» (N3)');
// الواقعُ تغيّر تحت ملحقٍ ثانٍ
$r = ECAS::createAmendment($conn, $gateApprover, $CO, $CID, array('amend_type' => 'pay_change',
    'effective_from' => '2036-05-01'), array(array('field' => 'component:' . $COMP . ':value', 'after' => '250')), $APPROVER);
$AMD2 = intval($r['id']);
$conn->query("UPDATE pay_components SET value = 175.00 WHERE id = {$COMP}"); // تغييرٌ خلسةً تحت الملحق
$r = ECAS::approveAmendment($conn, $gate, $CO, $AMD2, $CREATOR);
check(!$r['ok'] && $r['code'] === 409, 'الواقعُ تغيّر تحت الملحق → 409 (لا تطبيقَ فوق واقعٍ مغاير)');
$conn->query("UPDATE pay_components SET value = 200.00 WHERE id = {$COMP}"); // إرجاعُ عدّة الاختبار

// ═══ ⑤ الرفضُ والمرحَّل ═══
head('⑤ الرفضُ بسببٍ والمرحَّلُ محصَّن');
$r = ECAS::rejectAmendment($conn, $gate, $CO, $AMD2, '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'رفضٌ بلا سبب → 422');
$r = ECAS::rejectAmendment($conn, $gate, $CO, $AMD2, 'قيمةٌ تحتاج إعادةَ تفاوض', $CREATOR);
check($r['ok'], 'الرفضُ بسببٍ يمضي');
$mig = $conn->query("SELECT id FROM employee_contracts WHERE source_table IS NOT NULL AND state='active' LIMIT 1")->fetch_assoc();
if ($mig) {
    $r = ECAS::createAmendment($conn, $gate, $CO, intval($mig['id']), array('amend_type' => 'other',
        'effective_from' => '2036-06-01'), array(array('field' => 'head:currency', 'after' => 'USD')), $CREATOR);
    check(!$r['ok'] && $r['code'] === 423, 'ملحقٌ على مرحَّلٍ قراءةً → 423 بمصدره');
} else { bad('لا مرحَّلَ نافذًا للفحص'); }
$scr = file_get_contents(dirname(__DIR__) . '/Workforce/contract_registry.php');
check(strpos($scr, 'amd_add') !== false && strpos($scr, 'approveAmendment') !== false
      && strpos($scr, 'sign_attach') !== false,
      'الشاشةُ موصولةٌ (الملاحقُ والنسخةُ الموقَّعة عبر الخدمة حصرًا)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
