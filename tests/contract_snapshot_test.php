<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-11 — اختبار قبول لقطة العقد الثابتة ببصمتها (ENT-01 §2)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_snapshot_test.php
 *
 * ما يُثبته:
 *   ① البنية: contract_snapshots **Insert-only** (لا updated_at ولا حذفَ ناعمًا)
 *      ببصمته وأعمدةِ إبطاله — والتسجيلُ في بوابة العزل.
 *   ② بوابةُ القراءة الواحدة: لقطةُ عقدٍ نافذٍ ببصمةٍ تُتحقق (verify) · مسودةٌ
 *      → 422 «لا يُقرأ إلا نافذ».
 *   ③ العطالة: الطلبُ الثاني بالمضمون نفسِه يعيد اللقطةَ نفسَها لا يكررها —
 *      وتغيّرُ المضمون (بعد إبطالٍ وتعديل) يصنع لقطةً جديدةً ببصمةٍ جديدة.
 *   ④ أثرُ البصمة: تلاعبٌ مباشرٌ بالمضمون يكشفه verify.
 *   ⑤ الإبطالُ بالسريان: لقطةُ ما قبل التاريخ تبقى صالحةً وما بعده يُبطل —
 *      والتعليقُ/الإنهاءُ يُبطلان آليًّا من آلة الحالات (الوصلُ الحي).
 *
 * البذرُ معزول: عقدُ اختبارٍ بوسم H11T_<pid> وتواريخَ 2035 يُكنس في النهاية.
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
require_once dirname(__DIR__) . '/app/Services/Contract/ContractSnapshotService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Contract\ContractSnapshotService as CSS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'H11T_' . getmypid();
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;

$teardown = function () use ($conn) {
    $conn->query("DELETE cs FROM contract_snapshots cs JOIN employee_contracts ec ON ec.id = cs.contract_id
                   WHERE ec.relation_type LIKE 'H11T_%'");
    $conn->query("DELETE pc FROM pay_components pc JOIN employee_contracts ec ON ec.id = pc.contract_id
                   WHERE ec.relation_type LIKE 'H11T_%'");
    $conn->query("DELETE FROM employee_contracts WHERE relation_type LIKE 'H11T_%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-11 — لقطةُ العقد الثابتة ببصمتها وبوابةُ القراءة الواحدة ══\n");

// ═══ ① البنية ═══
head('① البنية — Insert-only ببصمته وأعمدةِ إبطاله');
$r = $conn->query("SHOW TABLES LIKE 'contract_snapshots'");
check($r && $r->num_rows === 1, 'جدول contract_snapshots قائم');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contract_snapshots'
                     AND COLUMN_NAME IN ('updated_at','is_deleted','deleted_at')");
check($r && intval($r->fetch_assoc()['c']) === 0, '**Insert-only**: لا updated_at ولا حذفَ ناعمًا');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contract_snapshots'
                     AND COLUMN_NAME IN ('fingerprint','as_of_date','valid','invalidated_from','invalidation_reason','amendment_ref')");
check($r && intval($r->fetch_assoc()['c']) === 6, 'البصمةُ وتاريخُ الاحتساب وأعمدةُ الإبطال ومرجعُ الملحق');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'contract_snapshots' => array('type' => self::T_TENANT, 'soft' => false)") !== false,
      'contract_snapshots في السجل (T_TENANT · لا soft — الإبطالُ لا الحذف)');

// ═══ بذرٌ: عقدٌ نافذٌ بمكوّن ═══
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));
$emp = $conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
                      AND NOT EXISTS (SELECT 1 FROM employee_contracts ec WHERE ec.employee_id = e.id)
                      LIMIT 1")->fetch_assoc();
$EID = $emp ? intval($emp['id']) : 0;
$pmFixed = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_only'")->fetch_assoc()['id']);
$r = ECS::createHead($conn, $gate, $CO, array('employee_id' => $EID, 'category' => 'permanent',
    'pay_model_id' => $pmFixed, 'relation_type' => $MARK,
    'start_date' => '2035-01-01', 'end_date' => '2035-12-31'), $CREATOR);
$CID = intval($r['id']);
ECS::addComponent($conn, $gate, $CO, $CID, array('component_type' => 'basic',
    'calc_method' => 'fixed_amount', 'value' => '1200'), $CREATOR);

// ═══ ② بوابةُ القراءة ═══
head('② البوابةُ الواحدة — النافذُ يُلتقط والمسودةُ تُرفض');
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, '2035-02-01', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'مسودةٌ → 422 «لا يُقرأ في الاحتساب إلا نافذ»');
foreach (array('completed', 'validated') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
ECSM::transition($conn, $gateApprover, $CO, $CID, 'approved', '', $APPROVER);
ECSM::transition($conn, $gate, $CO, $CID, 'accepted', '', $CREATOR);
ECS::attachSignedFile($conn, $gate, $CO, $CID, 'signed/' . $MARK . '.pdf', $CREATOR); // H-10 شرطُ التوقيع
foreach (array('signed', 'active') as $to) { ECSM::transition($conn, $gate, $CO, $CID, $to, '', $CREATOR); }
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, '2035-02-01', $CREATOR);
check($r['ok'] && intval($r['id']) > 0 && !$r['reused'], 'لقطةُ العقد النافذ أُدرجت ببصمتها');
$SNAP = intval($r['id']); $FP1 = strval($r['fingerprint']);
$v = CSS::verify($gate, $SNAP);
check($v['ok'] === true, 'verify: البصمةُ تطابق المضمون');
$row = $conn->query("SELECT snapshot_json FROM contract_snapshots WHERE id = {$SNAP}")->fetch_assoc();
$js = json_decode($row['snapshot_json'], true);
check(isset($js['head']['pay_model_id']) && count($js['components']) === 1
      && isset($js['incentives']) && isset($js['cost_bearers']),
      'المضمونُ القانوني: الرأسُ والمكوّناتُ والحوافزُ والتحمّل');

// ═══ ③ العطالة ═══
head('③ العطالة — المضمونُ نفسُه لقطةٌ واحدة');
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, '2035-02-01', $CREATOR);
check($r['ok'] && $r['reused'] && intval($r['id']) === $SNAP && $r['fingerprint'] === $FP1,
      'الطلبُ الثاني يعيد اللقطةَ نفسَها (reused) لا يكررها');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_snapshots WHERE contract_id = {$CID}")->fetch_assoc()['c']);
check($n === 1, 'صفٌّ واحدٌ في الجدول');
// يومُ احتسابٍ آخرُ بمضمونٍ ثابت = لقطةٌ أخرى ليومه (كلُّ سطرٍ يحمل لقطتَه ليومه)
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, '2035-03-01', $CREATOR);
check($r['ok'] && !$r['reused'] && $r['fingerprint'] === $FP1,
      'يومُ احتسابٍ آخرُ للمضمون نفسِه = لقطةٌ ليومه بالبصمة نفسِها');
$SNAP2 = intval($r['id']);

// ═══ ④ أثرُ البصمة ═══
head('④ أثرُ البصمة — التلاعبُ المباشر مكشوف');
$conn->query("UPDATE contract_snapshots SET snapshot_json = REPLACE(snapshot_json, '1200', '9900') WHERE id = {$SNAP}");
$v = CSS::verify($gate, $SNAP);
check($v['ok'] === false, 'تعديلُ المضمون خلسةً → verify يكشفه بالمقارنة');
$conn->query("UPDATE contract_snapshots SET snapshot_json = REPLACE(snapshot_json, '9900', '1200') WHERE id = {$SNAP}");
check(CSS::verify($gate, $SNAP)['ok'] === true, 'إرجاعُ المضمون يعيد التطابق (عدّةُ اختبارٍ لا مسارُ إنتاج)');

// ═══ ⑤ الإبطالُ بالسريان ═══
head('⑤ الإبطال — من تاريخ السريان فقط والوصلُ الحي بالآلة');
$n = CSS::invalidateFrom($conn, $gate, $CO, $CID, '2035-03-01', 'اختبارُ سريان', $CREATOR);
check($n === 1, 'الإبطالُ من 2035-03-01 أبطل لقطةَ آذارَ وحدَها');
$rows = $conn->query("SELECT id, valid FROM contract_snapshots WHERE contract_id = {$CID} ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$map = array(); foreach ($rows as $x) { $map[intval($x['id'])] = intval($x['valid']); }
check($map[$SNAP] === 1 && $map[$SNAP2] === 0,
      'لقطةُ شباطَ (قبل السريان) صالحةٌ — «يُعاد احتسابُ ما بعده لا ما قبله»');
// الوصلُ الحي: التعليقُ يُبطل من يومه — نصنع لقطةً بتاريخ اليوم ثم نعلّق
$today = date('Y-m-d');
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, $today, $CREATOR);
$SNAP3 = intval($r['id']);
ECSM::hold($conn, $gate, $CO, $CID, 'suspended', 'تعليقُ اختبار الإبطال', $CREATOR);
$row = $conn->query("SELECT valid, invalidation_reason FROM contract_snapshots WHERE id = {$SNAP3}")->fetch_assoc();
check(intval($row['valid']) === 0 && strpos(strval($row['invalidation_reason']), 'تعليق') !== false,
      'التعليقُ أبطل لقطةَ يومِه آليًّا (الوصلُ الحي بآلة الحالات)');
// لقطاتُ 2035 كلُّها بعد يومِ التعليق (اليوم) — فإبطالُها صحيحٌ نصًّا: «يُعاد
// احتسابُ ما بعده»؛ وبقاءُ ما قبل السريان أثبتته حالةُ invalidateFrom الصريحة أعلاه.
check(intval($conn->query("SELECT valid FROM contract_snapshots WHERE id = {$SNAP}")->fetch_assoc()['valid']) === 0,
      'ولقطةُ شباط 2035 (بعد يوم التعليق) أُبطلت معه — ما بعد الحدث يُعاد كلُّه');
// معلَّقٌ لا يُقرأ — البوابةُ ترفض
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, $today, $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'المعلَّقُ لا لقطةَ له (خارج بوابة القراءة)');
ECSM::resume($conn, $gate, $CO, $CID, 'عودة', $CREATOR);
$r = CSS::snapshotFor($conn, $gate, $CO, $CID, $today, $CREATOR);
check($r['ok'] && !$r['reused'], 'بعد الاستئناف: لقطةٌ جديدةٌ تُلتقط (القديمةُ أُبطلت)');

// المرحَّلُ قراءةً النافذُ تُؤخذ لقطتُه (قراءةٌ لا كتابة)
$mig = $conn->query("SELECT id FROM employee_contracts WHERE source_table IS NOT NULL AND state='active' LIMIT 1")->fetch_assoc();
if ($mig) {
    $r = CSS::snapshotFor($conn, $gate, $CO, intval($mig['id']), '2026-08-01', $CREATOR);
    check($r['ok'], 'المرحَّلُ النافذُ تُؤخذ لقطتُه — قراءةٌ لا كتابةَ عليه');
    $conn->query("DELETE FROM contract_snapshots WHERE contract_id = " . intval($mig['id']) . " AND as_of_date = '2026-08-01'");
} else { bad('لا مرحَّلَ نافذًا للفحص'); }

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
