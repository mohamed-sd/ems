<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ③ — اختبار قبول: حارس الأذونات (ORG-11→ORG-14)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/permit_gate_test.php
 *
 * ما يُثبته (ORG-01 §5 · §7-⑤ · O6-د · O6-هـ):
 *   ① الطلب pending وتسلسل الموافقات محروس: خطوة قبل سابقتها → 409.
 *   ② موافق بلا تفويض ساري لدوره → 403.
 *   ③ اكتمال الإلزاميات → approved وvalid_until بساعة القاعدة.
 *   ④ البوابة: بلا إذن → 403 (enforce) · إذن منتهٍ → 423 · الساري يُستهلك used.
 *   ⑤ monitor يسجّل في guard_denials ويمضي.
 *   ⑥ ORG-14: الإذن المعلَّق بندٌ في الصندوق الجامع بدوره الحالي.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/_guard_env.php';
ems_test_env_override(array('EMS_PERMIT_GATE' => 'enforce', 'EMS_PERMIT_GATE_SITES' => ''));
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Org/PermitGate.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';
require_once dirname(__DIR__) . '/app/Services/Finance/ApprovalsInboxService.php';

use App\Services\Org\PermitGate as PG;
use App\Services\Org\AssignmentService as ASV;
use App\Services\Finance\ApprovalsInboxService as AIS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'PGT' . getmypid();

$decider = $conn->query("SELECT id FROM users WHERE role = '1' AND company_id = {$CO} LIMIT 1")->fetch_assoc();
$DECIDER = intval($decider['id']);
$fleetU = $conn->query("SELECT id FROM users WHERE role = '3' AND company_id = {$CO} LIMIT 1")->fetch_assoc();
$FLEET = $fleetU ? intval($fleetU['id']) : 0;
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);
$mntUnit = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='maintenance'")->fetch_assoc()['unit_id']);
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE h FROM permit_status_history h JOIN permit_requests r ON r.req_id=h.req_id WHERE r.subject_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE a FROM permit_approval_actions a JOIN permit_requests r ON r.req_id=a.req_id WHERE r.subject_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM permit_requests WHERE subject_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id=l.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id=g.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM assignment_capabilities c JOIN org_assignments a ON a.asg_id=c.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM guard_denials WHERE guard_code='permit_gate' AND attempted_ref LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ③ — حارس الأذونات ══\n");
check($FLEET > 0, "مستخدم أسطول موجود (دور 3: {$FLEET})");

// بذر السلطة: مدير حركة وصيانة موقعيان بتكليف نافذ
$today = $conn->query('SELECT CURDATE() d')->fetch_assoc()['d'];
$to = date('Y-m-d', strtotime($today . ' +180 days'));
$mk = function ($person, $type, $unit) use ($conn, $CO, $SITE, $today, $to, $DECIDER, $MARK) {
    $r = ASV::create($conn, array('company_id' => $CO, 'person_id' => $person,
        'assignment_type_code' => $type, 'org_unit_id' => $unit,
        'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today, 'valid_to' => $to,
        'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK,
        'reporting_lines' => array(
            array('line_type' => 'operational', 'reports_to_assignment_id' => 0),
            array('line_type' => 'functional', 'reports_to_assignment_id' => 0))));
    return $r;
};
/* ══ خطا التبعية يحتاجان مرجعًا — ويُعاد استعمالُ القائمِ لا يُنشأ ثانٍ.
     **العيبُ المقيس**: الخدمةُ تحرس «تكليفٌ واحدٌ في أيِّ لحظةٍ للنوعِ والنطاقِ
     نفسيهما» (409)، وفي القاعدةِ تكليفٌ مركزيٌّ قائمٌ لـ`maintenance_mgr` على
     `site_group 0` (#145) **من بياناتِ النظامِ لا من بقايا فاحص**. فكانت
     البذرةُ تُنشئ ثانيًا فتُردّ، فيعود `asg_id = 0`، فتفشل خطوطُ التبعيةِ
     كلُّها ويتساقط عشرةُ فحوصٍ — **والحارسُ يعمل بحقٍّ والبذرةُ هي المخطئة**.
   ⇒ يُبحَث عن القائمِ أولًا، ولا يُنشأ إلا إن لم يكن. */
/* ◆ **والمفتاحُ اسمُه `asg_id` لا `id`** — مقيسٌ من `SHOW COLUMNS`. (وكان أولُ
     بحثٍ لي بـ`id` فعاد فارغًا فقُرئ «لا تكليفَ مركزيًّا» وهو قائم.) */
$findCen = function () use ($conn, $CO) {
    $q = $conn->query("SELECT asg_id FROM org_assignments
                        WHERE company_id = {$CO} AND assignment_type_code = 'maintenance_mgr'
                          AND scope_type = 'site_group' AND scope_id = 0
                          AND state IN ('active','suspended')
                        ORDER BY asg_id LIMIT 1");
    $x = $q ? $q->fetch_row() : null;
    return $x ? (int) $x[0] : 0;
};
/* ◆ **كنسُ بقايا الجولاتِ الميتةِ بالأشخاصِ المحجوزين لا بالوسمِ وحدَه.**
     الوسمُ `PGT<pid>` لكلِّ عمليةٍ على حدة، فجولةٌ تنفجر تترك تكليفَها بوسمِ
     pid آخرَ فلا يمسّه كنسُ الجولةِ التالية — وحارسُ «تكليفٌ واحدٌ في أيِّ
     لحظةٍ للنوعِ والنطاق» يرفض حينها **بحقّ** فيتساقط عشرةُ فحوص. وقع فعلًا:
     التكليفُ #353 (شخص 9301 · وسم PGT3360) عطّل الفاحصَ حتى مُحي.
     والأشخاصُ 9300-9303 محجوزون لهذا الفاحصِ وحدَه، فكنسُهم آمنٌ ويجعله
     **يشفي نفسَه**. */
/* ◆ **وأربعةُ مفاتيحَ تشير إلى `org_assignments` كلُّها بـ`asg_id`** — مقيسةٌ
     من `information_schema`: `assignment_audit` · `assignment_capabilities` ·
     و`assignment_reporting_lines` بعمودَين (`asg_id` **و**`reports_to_assignment_id`).
     فحذفُ الأبِ قبلَ أبنائه يُردُّ بمفتاحٍ أجنبيّ، والحذفُ بعمودٍ اسمُه
     `assignment_id` يرمي «Unknown column». فالكنسُ **بالترتيبِ وبالأسماءِ
     المقيسة**: الأبناءُ أولًا (وبالعمودَين معًا) ثم الأب. */
$__probeAsg = "SELECT asg_id FROM (SELECT asg_id FROM org_assignments
                 WHERE person_id BETWEEN 9300 AND 9303) x";
$conn->query("DELETE FROM assignment_reporting_lines
               WHERE asg_id IN ({$__probeAsg}) OR reports_to_assignment_id IN ({$__probeAsg})");
$conn->query("DELETE FROM assignment_capabilities WHERE asg_id IN ({$__probeAsg})");
$conn->query("DELETE FROM assignment_audit WHERE asg_id IN ({$__probeAsg})");
$conn->query('DELETE FROM org_assignments WHERE person_id BETWEEN 9300 AND 9303');
$CEN = $findCen();
if ($CEN <= 0) {
    $rc = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9300,
        'assignment_type_code' => 'maintenance_mgr', 'org_unit_id' => $mntUnit,
        'scope_type' => 'site_group', 'scope_id' => 0, 'valid_from' => $today, 'valid_to' => $to,
        'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK));
    $CEN = isset($rc['asg_id']) ? (int) $rc['asg_id'] : 0;
    if ($CEN <= 0) { $CEN = $findCen(); }
}
$fix = function ($r) use ($conn, $CEN) {
    return $r;
};
$rMov = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9301,
    'assignment_type_code' => 'site_movement_mgr', 'org_unit_id' => $unitId,
    'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today, 'valid_to' => $to,
    'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK,
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => $CEN),
        array('line_type' => 'functional', 'reports_to_assignment_id' => $CEN))));
check($rMov['ok'], 'بُذر مدير حركة الموقع (9301) — '
    . (empty($rMov['ok']) ? ($rMov['code'] . ': ' . $rMov['reason'] . ' · CEN=' . $CEN
        . ' · unit=' . $unitId . ' · site=' . $SITE) : 'ok'));
$rMnt = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9302,
    'assignment_type_code' => 'site_maintenance_officer', 'org_unit_id' => $mntUnit,
    'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today, 'valid_to' => $to,
    'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK,
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => $rMov['asg_id']),
        array('line_type' => 'functional', 'reports_to_assignment_id' => $CEN))));
check($rMnt['ok'], 'بُذر مسؤول صيانة الموقع (9302)');

head('① الطلب والتسلسل');
$rq = PG::request($conn, array('company_id' => $CO, 'permit_type_code' => 'equipment_site_entry',
    'subject_ref' => 'EQ:' . $MARK, 'site_id' => $SITE, 'requested_by' => 9301));
check($rq['ok'] && $rq['code'] === 201, "طلب إذن دخول معدة (#{$rq['req_id']})");
$REQ = $rq['req_id'];
$steps = PG::steps($conn, $REQ);
check(count($steps) === 3, 'ثلاث خطوات (الحركة ثم الأسطول ثم الصيانة — §5-①)');

// خطوة قبل سابقتها: الصيانة (seq 3) قبل الحركة
$r = PG::approve($conn, $REQ, 9302, 'approve', null, $steps[2]['rq_id']);
check(!$r['ok'] && $r['code'] === 409, "خطوة قبل سابقتها → 409 ({$r['reason']})");

head('② موافق بلا تفويض');
$r = PG::approve($conn, $REQ, 9999, 'approve');
check(!$r['ok'] && $r['code'] === 403, 'شخص بلا تكليف يوافق عن الحركة → 403');

head('③ اكتمال التسلسل → approved');
$r = PG::approve($conn, $REQ, 9301, 'approve');
check($r['ok'] && $r['state'] === 'pending', 'الحركة وافقت (1/3) — '
    . (empty($r['ok']) ? ($r['code'] . ': ' . $r['reason']) : ('state=' . $r['state'])));
$r = PG::approve($conn, $REQ, $FLEET, 'approve');
check($r['ok'] && $r['state'] === 'pending', 'الأسطول وافق (2/3) — دور موازٍ بusers.role');
$r = PG::approve($conn, $REQ, 9302, 'approve');
check($r['ok'] && $r['state'] === 'approved', 'الصيانة وافقت (3/3) → approved');
$p = PG::fetch($conn, $REQ);
check($p['valid_until'] !== null, 'valid_until حُسب من validity_hours بساعة القاعدة');
$h = $conn->query("SELECT COUNT(*) c FROM permit_status_history WHERE req_id={$REQ} AND to_state='approved'")->fetch_assoc();
check(intval($h['c']) === 1, 'PermitApproved سطر في التاريخ');

head('④ البوابة enforce');
$g = PG::check($conn, $CO, 'equipment_site_entry', 'EQ:NOPERM' . $MARK, $SITE, 9301);
check(!$g['ok'] && $g['code'] === 403, 'بلا إذن → 403 والفعل محجوب');
$g = PG::check($conn, $CO, 'equipment_site_entry', 'EQ:' . $MARK, $SITE, 9301);
check($g['ok'] && $g['req_id'] === $REQ, 'الإذن الساري يمرّر الفعل');
$p = PG::fetch($conn, $REQ);
check($p['state'] === 'used', 'ويُستهلك used (PermitUsed)');
$g = PG::check($conn, $CO, 'equipment_site_entry', 'EQ:' . $MARK, $SITE, 9301);
check(!$g['ok'] && $g['code'] === 403, 'المستهلَك لا يُستعمل ثانية');

// إذن منتهي المدة → 423
$rq2 = PG::request($conn, array('company_id' => $CO, 'permit_type_code' => 'equipment_service_exit',
    'subject_ref' => 'EQ2:' . $MARK, 'site_id' => $SITE, 'requested_by' => 9301));
$REQ2 = $rq2['req_id'];
PG::approve($conn, $REQ2, 9301, 'approve');
$r = PG::approve($conn, $REQ2, 9302, 'approve');
check($r['state'] === 'approved', 'إذن الإخراج اكتمل (الحركة + الصيانة)');
$conn->query("UPDATE permit_requests SET valid_until = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE req_id = {$REQ2}");
$g = PG::check($conn, $CO, 'equipment_service_exit', 'EQ2:' . $MARK, $SITE, 9301);
check(!$g['ok'] && $g['code'] === 423, 'إذن منتهي المدة يُستعمل → 423 ويُطلب تجديده');
$p = PG::fetch($conn, $REQ2);
check($p['state'] === 'expired', 'وسُجّل PermitExpired');

head('⑤ monitor يسجّل ويمضي — بمسبار فرعي (ems_env تُخبَّأ في static داخل العملية)');
ems_test_env_override(array('EMS_PERMIT_GATE' => 'monitor', 'EMS_PERMIT_GATE_SITES' => ''));
$before = intval($conn->query("SELECT COUNT(*) c FROM guard_denials WHERE guard_code='permit_gate'")->fetch_assoc()['c']);
$probe = escapeshellarg(__DIR__ . '/_permit_monitor_probe.php');
$outJson = shell_exec(escapeshellarg(PHP_BINARY) . " {$probe} {$CO} operator_site_entry " . escapeshellarg('EMP:' . $MARK) . " {$SITE} 9301");
$g = json_decode((string) $outJson, true);
check(is_array($g) && $g['ok'] === true && $g['mode'] === 'monitor', 'monitor: الفعل يمضي');
$after = intval($conn->query("SELECT COUNT(*) c FROM guard_denials WHERE guard_code='permit_gate'")->fetch_assoc()['c']);
check($after === $before + 1, 'والمخالفة مسجَّلة في guard_denials');
ems_test_env_override(array('EMS_PERMIT_GATE' => 'enforce'));

head('⑥ ORG-14 — الصندوق الجامع');
$rq3 = PG::request($conn, array('company_id' => $CO, 'permit_type_code' => 'worker_final_exit',
    'subject_ref' => 'FS:' . $MARK, 'site_id' => $SITE, 'requested_by' => 9301));
$inbox = AIS::inbox($conn, $CO);
$permitBox = null;
foreach ($inbox['boxes'] as $b) { if ($b['key'] === 'permits') { $permitBox = $b; } }
check($permitBox !== null, 'صندوق «أذونات المواقع» قائم في الصندوق الجامع');
$found = false;
foreach ($permitBox['rows'] as $row) {
    if (mb_strpos($row['label'], 'FS:' . $MARK) !== false) {
        $found = true;
        check(mb_strpos($row['label'], 'movement') !== false, 'البند يعرض الدور الحالي (الحركة أولًا)');
    }
}
check($found, 'الإذن المعلَّق بند واحد في الصندوق');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
