<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ② — حزام O1→O8 (ORG-01 §7.1) + الخدمات الأربع
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/org_authority_test.php
 *
 * O1  واحد لكل موقع → 409 ولا يُنشأ
 * O2  السقوط الآلي → الصلاحية تسقط لحظة الانتهاء واعتماد بعده 403 مسجَّلة
 * O3  النطاق → اعتماد خارج نطاق التكليف 403
 * O4  النيابة → النائب يعمل بسقفه ومدته، وبلا نائب تُرفع ولا تتوقف
 * O5  حد الاختصاص → (يُثبت بنيويًّا: لا صلاحية بلا capability تغطيه)
 * O6  الإلزاميات → بلا مدة/نطاق 422 · O6-ب خط واحد للموقعي 422
 * O6-و إنهاء بطلب المدير الفني → طلب يُسجَّل ولا يُنفَّذ
 * O7  السجل → إنهاء = سطر تدقيق قبل/بعد وسبب، ولا يُحذف
 * O8  التوقيع بالتكليف → approval_signatures.org_asg_id يحمل مرجع التكليف
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Org/OrgAuthorityResolver.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';
require_once dirname(__DIR__) . '/app/Services/Org/DeputyResolver.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentExpiryJob.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Services\Org\OrgAuthorityResolver as OAR;
use App\Services\Org\AssignmentService as ASV;
use App\Services\Org\DeputyResolver as DR;
use App\Services\Org\AssignmentExpiryJob as AEJ;
use App\Core\AuthorityGuard;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'ORG2T' . getmypid();

// مصدر قرار مخوَّل حقيقي (دور 1) — قياس لا افتراض
$decider = $conn->query("SELECT id FROM users WHERE role = '1' AND company_id = {$CO} LIMIT 1")->fetch_assoc();
$DECIDER = $decider ? intval($decider['id']) : 0;
$outsider = $conn->query("SELECT id FROM users WHERE role NOT IN ('1','-1') AND company_id = {$CO} LIMIT 1")->fetch_assoc();
$OUTSIDER = $outsider ? intval($outsider['id']) : 0;

$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);
$sites = $conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 3")->fetch_all(MYSQLI_ASSOC);
$SITE_A = intval($sites[0]['id']);
$SITE_B = intval($sites[1]['id']);

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id = l.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id = g.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM assignment_capabilities c JOIN org_assignments a ON a.asg_id = c.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM approval_signatures WHERE document_type = 'org2_test'");
    $conn->query("DELETE FROM guard_denials WHERE guard_code = 'org_authority' AND attempted_ref LIKE 'org2_test%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ② — O1→O8 ══\n");
check($DECIDER > 0, "مصدر قرار مخوَّل موجود (دور 1: user {$DECIDER})");

$today = OAR::dbToday($conn);
$plus1y = date('Y-m-d', strtotime($today . ' +364 days'));

head('O6 — الإلزاميات 422');
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9201, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE_A,
    'valid_from' => $today, 'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK));
check(!$r['ok'] && $r['code'] === 422, "بلا مدة → 422 ({$r['reason']})");
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9201, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'valid_from' => $today, 'valid_to' => $plus1y,
    'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK));
check(!$r['ok'] && $r['code'] === 422, 'بلا نطاق → 422');

head('403 — مصدر القرار غير مخوَّل');
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9201, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE_A,
    'valid_from' => $today, 'valid_to' => $plus1y,
    'decided_by_person_id' => $OUTSIDER, 'decision_ref' => $MARK));
check(!$r['ok'] && $r['code'] === 403, "مصدر قرار غير مخوَّل → 403");

head('O6-ب — الموقعي بخط واحد 422 وبخطين يُقبل');
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9201, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE_A,
    'valid_from' => $today, 'valid_to' => $plus1y, 'decided_by_person_id' => $DECIDER,
    'decision_ref' => $MARK,
    'reporting_lines' => array(array('line_type' => 'operational', 'reports_to_assignment_id' => 0))));
check(!$r['ok'] && $r['code'] === 422, 'خط تبعية واحد → 422');

/* ══ التكليفُ المركزيُّ **يُوجَد أو يُنشأ** — لا يُكرَّر ══════════════════════════
   الخدمةُ تحرس «**تكليفٌ واحدٌ في أي لحظة** للنوع والنطاق نفسيهما» (409). وفي
   الإنتاجِ تكليفٌ مركزيٌّ حقيقيٌّ قائمٌ لهذا النوعِ والنطاق: `#145` للشخص 58
   بمرجعِ قرارٍ `OPS-2026-100` نافذٌ إلى 2027-07-31. فمحاولةُ إنشاءِ ثانٍ **تُردُّ
   بحقٍّ** — وكان الفاحصُ يقرأ الردَّ عطبًا، فيُدين حارسًا يمنع ازدواجَ السلطة
   (وهو بالضبط ما يجب أن يمنعه).
   ⇒ يُبحَث عن المركزيِّ النافذِ أوّلًا ويُتَّخذ هدفًا لخطوطِ التبعية — فذاك
     الواقعُ الذي يُبرهن عليه؛ ولا يُنشأ إلا إن لم يكن (فيصحُّ على قاعدةٍ عذراءَ
     وعلى قاعدةٍ فيها إنتاجٌ معًا، ولا يزاحم بياناتٍ حقيقية).                    */
$exists = $conn->query("SELECT asg_id, person_id FROM org_assignments
                         WHERE company_id={$CO} AND assignment_type_code='maintenance_mgr'
                           AND scope_type='site_group' AND state='active'
                           AND valid_from <= '{$today}' AND valid_to >= '{$today}'
                         ORDER BY asg_id LIMIT 1")->fetch_assoc();
if ($exists) {
    $CENTRAL = (int) $exists['asg_id'];
    check($CENTRAL > 0, "تكليفٌ مركزيٌّ نافذٌ قائمٌ يُتَّخذ مرجعًا (#{$CENTRAL} للشخص {$exists['person_id']})"
        . ' — والحارسُ يمنع ثانيًا للنوعِ والنطاقِ نفسيهما');
} else {
    $rc = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9200, 'assignment_type_code' => 'maintenance_mgr',
        'org_unit_id' => $unitId, 'scope_type' => 'site_group', 'scope_id' => 0,
        'valid_from' => $today, 'valid_to' => $plus1y, 'decided_by_person_id' => $DECIDER,
        'decision_ref' => $MARK,
        'capabilities' => array(array('code' => 'maintenance.approve'))));
    check($rc['ok'], "تكليف مركزي مرجعي أُنشئ (#{$rc['asg_id']})"
        . ($rc['ok'] ? '' : ' — رمز ' . ($rc['code'] ?? '؟') . ': ' . mb_substr((string) ($rc['reason'] ?? ''), 0, 150)));
    $CENTRAL = $rc['asg_id'];
}

$r1 = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9201, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE_A,
    'valid_from' => $today, 'valid_to' => $plus1y, 'decided_by_person_id' => $DECIDER,
    'decision_ref' => $MARK, 'deputy_person_id' => 9250,
    'capabilities' => array(array('code' => 'unit.chain.approve.link1'), array('code' => 'daily_plan.submit')),
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => $CENTRAL),
        array('line_type' => 'functional', 'reports_to_assignment_id' => $CENTRAL))));
check($r1['ok'] && $r1['code'] === 201, "بخطين → 201 (#{$r1['asg_id']})");
$ASG1 = $r1['asg_id'];

head('O1 — واحد لكل موقع 409');
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9202, 'assignment_type_code' => 'site_manager',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE_A,
    'valid_from' => date('Y-m-d', strtotime($today . ' +30 days')), 'valid_to' => $plus1y,
    'decided_by_person_id' => $DECIDER, 'decision_ref' => $MARK,
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => $CENTRAL),
        array('line_type' => 'functional', 'reports_to_assignment_id' => $CENTRAL))));
check(!$r['ok'] && $r['code'] === 409, "مدير حركة ثانٍ للموقع نفسه → 409 ولا يُنشأ");

head('O3 — النطاق');
$c = OAR::can($conn, 9201, $CO, array('scope_type' => 'site', 'scope_id' => $SITE_A, 'capability' => 'daily_plan.submit'));
check($c['ok'] && $c['asg_id'] === $ASG1, 'داخل النطاق → سلطة بمرجع التكليف');
$c = OAR::can($conn, 9201, $CO, array('scope_type' => 'site', 'scope_id' => $SITE_B, 'attempted_ref' => 'org2_test:o3'));
check(!$c['ok'] && $c['code'] === 403, 'خارج النطاق → 403');
$d = $conn->query("SELECT COUNT(*) c FROM guard_denials WHERE guard_code='org_authority' AND attempted_ref='org2_test:o3'")->fetch_assoc();
check(intval($d['c']) === 1, 'والمنع مسجَّل في guard_denials');

head('O5 — حد الاختصاص بنيويًّا');
$c = OAR::can($conn, 9201, $CO, array('scope_type' => 'site', 'scope_id' => $SITE_A, 'capability' => 'contract.term.edit', 'attempted_ref' => 'org2_test:o5'));
check(!$c['ok'], 'صلاحية لا يحملها التكليف (تعديل بند عقد) → مرفوضة — الطلب مسار لا سلطة');

head('O4 — النيابة');
$a = DR::resolveActor($conn, $ASG1, false);
check($a['person_id'] === 9201 && !$a['is_deputy'], 'الحاضر: المكلَّف نفسه');
$a = DR::resolveActor($conn, $ASG1, true);
check($a['person_id'] === 9250 && $a['is_deputy'], 'الغائب: النائب يعمل بسقفه ومدته');
$aud = $conn->query("SELECT COUNT(*) c FROM assignment_audit WHERE asg_id={$ASG1} AND action='delegated'")->fetch_assoc();
check(intval($aud['c']) >= 1, 'التفعيل مسطَّر delegated في السجل');
$a = DR::resolveActor($conn, $CENTRAL, true);
check($a['person_id'] === null, 'بلا نائب معتمَد: تُرفع للأعلى ولا تتولى النيابة صامتة');

head('O8 — التوقيع بمرجع التكليف (monitor)');
$sig = AuthorityGuard::sign($conn, array('document_type' => 'org2_test', 'document_id' => 1,
    'person_id' => 9201, 'company_id' => $CO, 'scope_type' => 'site', 'scope_id' => $SITE_A));
check($sig['ok'], 'الاعتماد يمضي في monitor');
$row = $conn->query("SELECT org_asg_id FROM approval_signatures WHERE document_type='org2_test' AND document_id=1 LIMIT 1")->fetch_assoc();
check($row && intval($row['org_asg_id']) === $ASG1, "التوقيع يحمل مرجع التكليف #{$ASG1} لا الاسم فقط");

head('O6-و — إنهاء بطلب المدير الفني: طلب لا تنفيذ');
$r = ASV::end($conn, $ASG1, $OUTSIDER, 'أداء ضعيف');
check($r['ok'] && $r['requested'] === true && $r['code'] === 202, 'طلب يُسجَّل ولا يُنفَّذ');
$st = ASV::fetch($conn, $ASG1);
check($st['state'] === 'active', 'التكليف ما زال نافذًا — القرار لمن كلَّف');

head('O2 + O7 — السقوط الآلي والسجل');
// تكليف انقضت مدته أمس — يقنصه ExpiryJob
$conn->query("INSERT INTO org_assignments (company_id, person_id, assignment_type_code, org_unit_id,
    scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref, state)
    VALUES ({$CO}, 9203, 'site_warehouse_keeper', {$unitId}, 'site', {$SITE_B},
    DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), {$DECIDER}, '{$MARK}', 'active')");
$EXP = intval($conn->insert_id);
$res = AEJ::run($conn, $CO);
check($res['expired'] >= 1, "ExpiryJob أنهى المنقضي ({$res['expired']})");
$st = ASV::fetch($conn, $EXP);
check($st['state'] === 'ended', 'المنقضي صار ended');
$c = OAR::can($conn, 9203, $CO, array('scope_type' => 'site', 'scope_id' => $SITE_B, 'attempted_ref' => 'org2_test:o2'));
check(!$c['ok'] && $c['code'] === 403 && mb_strpos($c['reason'], 'سقطت آليًّا') !== false,
      'اعتماد بتكليف منتهٍ → 403 بسبب السقوط الآلي');
$aud = $conn->query("SELECT before_json, after_json, reason FROM assignment_audit WHERE asg_id={$EXP} AND action='ended' LIMIT 1")->fetch_assoc();
check($aud && $aud['before_json'] !== null && $aud['after_json'] !== null && $aud['reason'] !== null,
      'O7: سطر تدقيق بقيمة قبل وبعد وسبب');

head('الإنهاء بمصدر القرار — ويسقط فورًا');
$r = ASV::end($conn, $ASG1, $DECIDER, 'إنهاء اختباري');
check($r['ok'] && !$r['requested'], 'مصدر القرار يُنهي');
$c = OAR::can($conn, 9201, $CO, array('scope_type' => 'site', 'scope_id' => $SITE_A));
check(!$c['ok'], 'والصلاحية سقطت في اللحظة نفسها');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
