<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ⑨ — حزام القوالب والتغيير والمراجعة: S19·S24·S25 (SEC-23→26)
 * التشغيل: php tests/sec_templates_workflow_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Security/PermissionTemplateService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionChangeWorkflow.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionReviewService.php';

use App\Services\Security\PermissionTemplateService as PTS;
use App\Services\Security\PermissionChangeWorkflow as PCW;
use App\Services\Security\PermissionReviewService as PRS;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'TW9T' . getmypid();
/* **وعائلةُ الوسمِ لا وسمُ الشوطِ.** الكنسُ بـ`%{$MARK}%` أعمى عمّا تركه شوطٌ
   سابقٌ أخفق كنسُه، والقياسُ وجد **53 دورةَ مراجعةٍ** متراكمةً (32 موقَّعةً و21
   مُصعَّدة). وتراكمُها ليس زينةً: فتحُ دورةٍ جديدةٍ يُردُّ فيعود `cycle_id = 0`
   — وهو ما رأته `sec_full_belt` (سقطت ستةُ فحوصٍ فيها بينما يمرُّ الفاحصُ
   وحدَه). فالكنسُ بالعائلةِ `TW9T` يجعل الشوطَ يشفي ما أفسده سابقُه. */
$FAMILY = 'TW9T';

$teardown = function () use ($conn, $MARK, $FAMILY) {
    if ($FAMILY === '') { return; }   // وسمٌ فارغٌ ⇒ لا كنسَ (صونًا للبيانات)
    foreach (array(
        "DELETE l FROM permission_review_lines l JOIN permission_review_cycles c ON c.cycle_id=l.cycle_id WHERE c.period LIKE '%{$FAMILY}%'",
        "DELETE FROM permission_review_cycles WHERE period LIKE '%{$FAMILY}%'",
        "DELETE s FROM permission_approval_steps s JOIN permission_change_requests r ON r.req_id=s.req_id WHERE r.reason LIKE '%{$FAMILY}%'",
        "DELETE FROM permission_change_requests WHERE reason LIKE '%{$FAMILY}%'",
        "DELETE FROM permission_audit_events WHERE reason LIKE '%{$FAMILY}%' OR request_ref LIKE '%{$FAMILY}%'",
        "DELETE tp FROM template_permissions tp JOIN permission_template_versions v ON v.ver_id=tp.template_version_id WHERE v.change_reason LIKE '%{$FAMILY}%'",
        "DELETE FROM permission_template_versions WHERE change_reason LIKE '%{$FAMILY}%'",
        "UPDATE founding_mode SET enabled=0, ends_at=NULL, started_at=NULL WHERE mode='permission_test'",
    ) as $q) { $conn->query($q); }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑨ — القوالب والتغيير والمراجعة ══\n");

head('S24 — إصدارات القالب: الأثر قبل النشر ولا تعديل رجعيًّا');
$r = PTS::createVersion($conn, 'family', 'fam_warehouse', array(
    array('dimension' => 'visibility', 'permission_code' => 'wh.stock.view'),
    array('dimension' => 'action', 'permission_code' => 'wh.issue.create'),
), 'v1 ' . $MARK);
check($r['ok'], "إصدار مسودة (#{$r['ver_id']})");
$V1 = $r['ver_id'];
$p = PTS::publish($conn, $V1, 'APR-' . $MARK);
check(!$p['ok'] && $p['code'] === 409, 'لا نشر قبل الاختبار في وضع اختبار الصلاحيات');
$t = PTS::markTested($conn, $V1, $MARK);
check(!$t['ok'], 'ولا وسم «مختبَر» ووضع الاختبار مطفأ');
// فعّل وضع اختبار الصلاحيات (بنهاية إلزامية)
$conn->query("UPDATE founding_mode SET enabled=1, started_at=NOW(), ends_at=DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE mode='permission_test'");
$t = PTS::markTested($conn, $V1, $MARK);
check($t['ok'], 'الوضع مفعَّل → وُسم مختبَرًا');
$p = PTS::publish($conn, $V1, 'APR-' . $MARK);
check(!$p['ok'] && mb_strpos($p['reason'], 'أثر') !== false, 'ولا نشر بلا حساب الأثر');
$imp = PTS::impactPreview($conn, $V1);
check($imp['ok'] && isset($imp['impact']['affected_users']), 'الأثر: كم مستخدمًا وأي صلاحية (' . count($imp['impact']['added']) . ' مضافة)');
$p = PTS::publish($conn, $V1, 'APR-' . $MARK);
check($p['ok'], 'نُشر بعد الاختبار والأثر');
$a = PTS::amendVersion($conn, $V1, array(array('permission_code' => 'x')));
check(!$a['ok'] && $a['code'] === 423, 'تعديل المنشور → 423 — لا أثر رجعي');
// إصدار ثان يجُبّ الأول
$r2 = PTS::createVersion($conn, 'family', 'fam_warehouse', array(
    array('dimension' => 'visibility', 'permission_code' => 'wh.stock.view'),
), 'v2 ' . $MARK);
PTS::markTested($conn, $r2['ver_id'], $MARK);
PTS::impactPreview($conn, $r2['ver_id']);
$imp2 = PTS::impactPreview($conn, $r2['ver_id']);
check(in_array('wh.issue.create|grant', $imp2['impact']['removed'], true), 'أثر v2 يظهر المسحوب من v1');
PTS::publish($conn, $r2['ver_id'], 'APR2-' . $MARK);
$st = $conn->query("SELECT state, superseded_by FROM permission_template_versions WHERE ver_id={$V1}")->fetch_assoc();
check($st['state'] === 'superseded' && intval($st['superseded_by']) === intval($r2['ver_id']), 'السابق superseded بمرجع خلفه');

head('S19 — المصفوفة الرباعية لا تُختصر');
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$hrU = intval($conn->query("SELECT id FROM users WHERE role='4' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$finU = intval($conn->query("SELECT id FROM users WHERE role='17' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$othU = intval($conn->query("SELECT id FROM users WHERE role='12' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$r = PCW::open($conn, array('company_id' => $CO, 'person_id' => 9601, 'change_kind' => 'dept_mgr_or_high',
    'reason' => 'ترفيع مدير إدارة ' . $MARK, 'created_by' => $othU,
    'to_json' => array('target' => 'dept_mgr')));
check($r['ok'], "طلب ترفيع عالٍ فُتح (#{$r['req_id']})");
$REQ = $r['req_id'];
$steps = intval($conn->query("SELECT COUNT(*) c FROM permission_approval_steps WHERE req_id={$REQ}")->fetch_assoc()['c']);
check($steps === 4, "أربع خطوات لا تُختصر (وُجد {$steps})");
PCW::decide($conn, $REQ, $hrU);      // hr
PCW::decide($conn, $REQ, $decider);  // functional_owner
$d3 = PCW::decide($conn, $REQ, $finU); // finance
check($d3['ok'] && $d3['state'] === 'pending' && intval($d3['remaining']) === 1, 'بثلاث موافقات ما زال معلقًا (بقي 1)');
$ap = PCW::apply($conn, $REQ, $decider);
check(!$ap['ok'] && $ap['code'] === 409, 'ولا يُطبَّق بثلاث — يُرفض حتى تكتمل الأربع');
$sup = intval($conn->query("SELECT id FROM users WHERE role='-1' LIMIT 1")->fetch_assoc()['id'] ?? 0);
if ($sup === 0) { $sup = intval($conn->query("SELECT id FROM users WHERE role='19' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']); }
$d4 = PCW::decide($conn, $REQ, $sup); // executive
check($d4['ok'] && $d4['state'] === 'approved', 'الرابعة تكمل — approved');
$ap = PCW::apply($conn, $REQ, $decider);
check($ap['ok'], 'ثم يُطبَّق ويعاد بناء المشتق');

head('حارس الذات في الدورة');
$r = PCW::open($conn, array('company_id' => $CO, 'person_id' => 9602, 'change_kind' => 'within_role',
    'reason' => 'ذاتي ' . $MARK, 'created_by' => $othU));
$d = PCW::decide($conn, $r['req_id'], $othU);
check(!$d['ok'] && $d['code'] === 403, 'منشئ الطلب لا يعتمده — 403');

head('S25 — المراجعة النصف سنوية والتصعيد');
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='maintenance'")->fetch_assoc()['unit_id']);
$c = PRS::openCycle($conn, $CO, $unitId, 'H2-' . $MARK, $decider, 14);
check($c['ok'], "دورة فُتحت (#{$c['cycle_id']}) ببنودها ({$c['lines']})");
$CY = $c['cycle_id'];
// أدرج بندًا يدويًّا إن كانت الوحدة بلا مراكز نشطة
if (intval($c['lines']) === 0) {
    $conn->query("INSERT INTO permission_review_lines (cycle_id, person_id, permission_code, scope_rule) VALUES ({$CY}, 9603, 'mnt.order.view', 'site:1')");
}
$s = PRS::sign($conn, $CY, $decider);
check(!$s['ok'] && $s['code'] === 409, 'لا توقيع وفيها بند بلا قرار — بندًا بندًا');
$lines = $conn->query("SELECT line_id FROM permission_review_lines WHERE cycle_id={$CY}")->fetch_all(MYSQLI_ASSOC);
foreach ($lines as $i => $l) {
    PRS::decideLine($conn, $l['line_id'], $i === 0 ? 'revoke' : 'confirm', $i === 0 ? 'لا حاجة' : null);
}
$dl = PRS::decideLine($conn, $lines[0]['line_id'], 'confirm');
check(!$dl['ok'], 'البند المحسوم لا يُعاد — Insert-only على القرار');
$s = PRS::sign($conn, $CY, 999999);
check(!$s['ok'] && $s['code'] === 403, 'التوقيع لمدير الدورة وحده');
$s = PRS::sign($conn, $CY, $decider);
check($s['ok'], 'وُقِّعت بعد حسم البنود');
// دورة متأخرة → تصعيد
$c2 = PRS::openCycle($conn, $CO, intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='warehouse'")->fetch_assoc()['unit_id']), 'H2b-' . $MARK, $decider, 14);
$conn->query("UPDATE permission_review_cycles SET due_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE cycle_id = " . intval($c2['cycle_id']));
$n = PRS::escalateOverdue($conn, $CO);
check($n >= 1, "المتأخرة صُعِّدت للإدارة العامة ({$n})");
$st = $conn->query("SELECT state FROM permission_review_cycles WHERE cycle_id=" . intval($c2['cycle_id']))->fetch_assoc();
check($st['state'] === 'escalated', 'الحالة: escalated');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
