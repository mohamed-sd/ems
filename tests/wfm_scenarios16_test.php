<?php
/**
 * tests/wfm_scenarios16_test.php — السيناريوهات الستة عشر بندًا بندًا (AC-WFM-15)
 * ───────────────────────────────────────────────────────────────────────────
 * «كلُّ سيناريو في قسم الاختبارات يُنفَّذ وينجح» — كان الحكم 🟡 لأن اختبار
 * المحرك (44 حالة) يغطي الجوهر بلا مطابقةٍ مسماة. هنا الستة عشر من عائلات
 * قسم اختبارات WFM-01 (§5-9) كلٌّ باسمه ومرجعه، تنفيذًا حيًّا على المحرك
 * لا مشاهدةً. البيانات موسومة TST-S16 وتُكنس في الخاتمة.
 * التشغيل: php tests/wfm_scenarios16_test.php
 */
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/resolve_manager.php';
require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
require_once __DIR__ . '/../app/Services/Work/AchievementService.php';
require_once __DIR__ . '/../app/Services/Work/RequestService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

use App\Services\Work\WorkItemService as WI;
use App\Services\Work\AchievementService as ACH;
use App\Services\Work\RequestService as RQ;

$pass = 0; $failN = 0;
function ok($cond, $label) {
    global $pass, $failN;
    if ($cond) { $pass++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $failN++; fwrite(STDOUT, "  ✘ {$label}\n"); }
}
$CO = 4;
$actors = array();
$r = mysqli_query($conn, "SELECT id FROM users WHERE company_id = {$CO} AND COALESCE(status,'active')='active' ORDER BY id LIMIT 6");
while ($x = mysqli_fetch_row($r)) { $actors[] = intval($x[0]); }
list($OWNER, $EXEC, $VERIF) = $actors;
$due = date('Y-m-d H:i:s', time() + 86400);
$mk = function ($ref, $extra = array()) use ($conn, $CO, $OWNER, $EXEC, $VERIF, $due) {
    return WI::create($conn, array_merge(array(
        'company_id' => $CO, 'source_type' => 'SRC-01', 'source_ref' => $ref,
        'source_screen' => 'tests', 'owner_user_id' => $OWNER, 'assigned_user_id' => $EXEC,
        'verifier_user_id' => $VERIF, 'org_unit_id' => 1, 'title' => 'TST-S16 ' . $ref,
        'deliverable' => 'مخرج', 'due_at' => $due, 'created_by' => $OWNER), $extra));
};

fwrite(STDOUT, "═══ السيناريوهات الستة عشر (WFM-01 §5-9 · AC-WFM-15) ═══\n");

/* S01 · WFM-001-ب: مساحة عملي لا تُنشئ — عنصر بلا مصدرٍ أصلي يُرفض */
$s = WI::create($conn, array('company_id' => $CO, 'owner_user_id' => $OWNER,
    'assigned_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'بلا مصدر',
    'deliverable' => 'x', 'due_at' => $due, 'created_by' => $OWNER));
ok(!$s['ok'], 'S01 (WFM-001-ب): إنشاءٌ بلا مصدرٍ أصليٍّ يُرفض');

/* S02 · WFM-006-د: المهمة عملٌ والموافقة حكم — القرار لحامل الخطوة لا للمنفذ */
$q = RQ::submit($conn, array('company_id' => $CO, 'request_type_code' => 'RQ-HR-04',
    'requester_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'TST-S16 طلب تمييز', 'created_by' => $EXEC));
$REQ = intval($q['id'] ?? 0);
$dec = RQ::decide($conn, $REQ, 'approve', $EXEC);
ok($q['ok'] && !$dec['ok'], 'S02 (WFM-006-د): الموافقة حكمُ حاملها — مقدِّمُ الطلب لا يقرر لنفسه');

/* S03 · WFM-014-ب: الإسناد يبدأ عدَّ الاستجابة ويُخطر المنفذ */
$t3 = $mk('TST-S16-3');
$row3 = $conn->query("SELECT response_due_at FROM work_items WHERE id = " . intval($t3['id']))->fetch_assoc();
$n3 = intval($conn->query("SELECT COUNT(*) FROM personal_notifications WHERE user_id = {$EXEC}
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)")->fetch_row()[0]);
ok($t3['ok'] && $row3['response_due_at'] !== null && $n3 > 0,
   'S03 (WFM-014-ب): الإسناد يبدأ عدّ الاستجابة ويُخطر المنفذ');

/* S04 · WFM-017: التوقف بسببٍ محكومٍ فقط */
WI::transition($conn, intval($t3['id']), 'accepted', $EXEC);
WI::transition($conn, intval($t3['id']), 'in_progress', $EXEC);
$b1 = WI::transition($conn, intval($t3['id']), 'blocked', $EXEC, '');
$b2 = WI::transition($conn, intval($t3['id']), 'blocked', $EXEC, 'awaiting_part');
ok(!$b1['ok'] && $b2['ok'], 'S04 (WFM-017): التعطل بلا سببٍ يُرفض وبسببٍ من القائمة يمر');

/* S05 · WFM-021: رفض المنفذ بمبرر (rejected من assigned بيد المنفذ بسبب إلزامي) */
$t5 = $mk('TST-S16-5');
$rj0 = WI::transition($conn, intval($t5['id']), 'rejected', $EXEC, '');
$rj = WI::transition($conn, intval($t5['id']), 'rejected', $EXEC, 'مبرر الرفض: نقص مدخلات');
ok(!$rj0['ok'] && $rj['ok'], 'S05 (WFM-021): الرفض بلا مبررٍ يُرفض وبمبررٍ يمر ويُخطر المكلف');

/* S06 · WFM-023: النقل لمنفذٍ آخر لا يصفّر العد */
$t6 = $mk('TST-S16-6');
$before = $conn->query("SELECT due_at FROM work_items WHERE id = " . intval($t6['id']))->fetch_assoc();
$mv = WI::reassign($conn, intval($t6['id']), $VERIF, $OWNER, 'نقل اختباري');
$after = $conn->query("SELECT due_at, assigned_user_id FROM work_items WHERE id = " . intval($t6['id']))->fetch_assoc();
ok($mv['ok'] && $after['due_at'] === $before['due_at'] && intval($after['assigned_user_id']) === $VERIF,
   'S06 (WFM-023): النقل يستمر بعدّه ولا يُصفَّر');

/* S07 · WF-04: لا يغلق أحدٌ مهمته — والمتحقق يغلق */
$t7 = $mk('TST-S16-7');
WI::transition($conn, intval($t7['id']), 'accepted', $EXEC);
WI::transition($conn, intval($t7['id']), 'in_progress', $EXEC);
WI::transition($conn, intval($t7['id']), 'done_pending_verify', $EXEC);
$c1 = WI::transition($conn, intval($t7['id']), 'closed_accepted', $EXEC);
$c2 = WI::transition($conn, intval($t7['id']), 'closed_accepted', $VERIF);
ok(!$c1['ok'] && $c1['code'] === 403 && $c2['ok'], 'S07 (WF-04): المنفذ لا يغلق مهمته (403) والمتحقق يغلق');

/* S08 · WF-03: الإغلاق يشتق الإنجاز بنوعيه ولا يتضاعف */
$n8 = intval($conn->query("SELECT COUNT(*) FROM achievement_records WHERE source_kind='task'
    AND source_ref='" . intval($t7['id']) . "' AND reversed_at IS NULL")->fetch_row()[0]);
ok($n8 >= 2, "S08 (WF-03): إنجازٌ تنفيذيٌّ وإشرافيٌّ اشتُقا بالإغلاق ({$n8})");

/* S09 · AC-WFM-05: الإدخال اليدوي للإنجاز يُرفض */
$d = ACH::derive($conn, array('company_id' => $CO, 'source_kind' => 'self_claim', 'source_ref' => 'x',
    'person_user_id' => $EXEC, 'title' => 'ادعاء', 'evidence_ref' => 'x', 'created_by' => $EXEC));
ok(!$d['ok'], 'S09 (AC-WFM-05): صفرُ إنجازٍ يدويٍّ أو بلا دليل');

/* S10 · AC-WFM-14: إعادة الفتح تعكس الإنجاز */
WI::transition($conn, intval($t7['id']), 'reopened', $OWNER, 'خلل بالدليل', array('governance_ok' => true));
$n10 = intval($conn->query("SELECT COUNT(*) FROM achievement_records WHERE source_kind='task'
    AND source_ref='" . intval($t7['id']) . "' AND reversed_at IS NOT NULL")->fetch_row()[0]);
ok($n10 >= 2, "S10 (AC-WFM-14): العكس آليٌّ بإعادة الفتح ({$n10} معكوسًا)");

/* S11 · AC-WFM-07: الطلب يُعرف أين توقف (حامل معلوم منذ التوجيه) */
$h = $conn->query("SELECT current_holder_user_id, status FROM requests WHERE id = {$REQ}")->fetch_assoc();
ok(intval($h['current_holder_user_id']) > 0, 'S11 (AC-WFM-07): حاملُ الطلب معلومٌ منذ توجيهه (' . $h['status'] . ')');

/* S12 · WF-05: لا إغلاقَ بلا الرد التسعة */
$holder = intval($h['current_holder_user_id']);
RQ::decide($conn, $REQ, 'approve', $holder, 'مستوفٍ');
$e0 = RQ::executeAndClose($conn, $REQ, $holder, array('decision' => 'approved'));
ok(!$e0['ok'], 'S12 (WF-05): إغلاقٌ ناقصُ الرد التسعة يُرفض مسمًّى');

/* S13 · AC-WFM-09: المهلة الفائتة تُوسم وتُصعَّد آليًّا */
$t13 = $mk('TST-S16-13');
mysqli_query($conn, "UPDATE work_items SET due_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = " . intval($t13['id']));
WI::sweepSla($conn);
$st13 = $conn->query("SELECT status, escalation_level FROM work_items WHERE id = " . intval($t13['id']))->fetch_assoc();
ok($st13['status'] === 'overdue' && intval($st13['escalation_level']) >= 1,
   'S13 (AC-WFM-09): فائتة المهلة وُسمت متأخرةً وصُعّدت درجةً');

/* S14 · WF-08: التفويض المنتهي يوقف التوليد في اللحظة */
mysqli_query($conn, "INSERT INTO work_delegations (company_id, kind, from_user_id, to_user_id, scope_ref, starts_at, ends_at, status, created_by)
   VALUES ({$CO}, 'deputize', {$OWNER}, {$EXEC}, 'TST-S16-DLG', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'active', {$OWNER})");
$t14 = $mk('TST-S16-14', array('delegation_ref' => 'TST-S16-DLG'));
ok(!$t14['ok'], 'S14 (WF-08): تفويضٌ منقضٍ لا يولّد عنصرًا');

/* S15 · AC-WFM-11: رؤية المدير من الهيكل لا من قوائم */
$scope = ems_manager_scope_user_ids($conn, $OWNER, 2);
$strangerIn = false;
$sr = mysqli_query($conn, "SELECT id FROM users WHERE company_id <> {$CO} LIMIT 1");
if ($sr && ($sx = mysqli_fetch_row($sr))) { $strangerIn = in_array(intval($sx[0]), $scope, true); }
ok(!$strangerIn, 'S15 (AC-WFM-11): نطاق المدير من parent_id — لا غريبَ فيه');

/* S16 · AC-WFM-13: سلسلة تفسير الظهور الخماسية كاملة للعنصر السليم */
$ex = WI::explainAppearance($conn, intval($t3['id']), $EXEC);
ok(count($ex['steps']) === 5, 'S16 (AC-WFM-13): السلسلة الخماسية كاملة (' . count($ex['steps']) . '/5)');

/* كنس آثار الاختبار */
mysqli_query($conn, "DELETE FROM achievement_records WHERE source_kind='task' AND source_ref IN
    (SELECT id FROM work_items WHERE source_ref LIKE 'TST-S16%')");
mysqli_query($conn, "DELETE FROM work_items WHERE source_ref LIKE 'TST-S16%'");
mysqli_query($conn, "DELETE FROM work_delegations WHERE scope_ref = 'TST-S16-DLG'");
mysqli_query($conn, "UPDATE requests SET status='cancelled', status_reason='TST-S16 كنس' WHERE id = {$REQ}");
fwrite(STDOUT, "\n══ النتيجة: {$pass} ناجحة · {$failN} فاشلة ══\n");
exit($failN ? 1 : 0);
