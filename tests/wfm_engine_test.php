<?php
/**
 * حزام محرّك WFM — الحالات الأربع لكل فعلٍ حاكم (E-06 · TS-01)
 * ───────────────────────────────────────────────────────────────────────────
 * «سماحٌ ومنعٌ وتكرارٌ وعكس» على: إنشاء العنصر (WF-02/08) · الانتقالات
 * والإغلاق من غير المنفِّذ (WF-04) · اشتقاق الإنجاز وموانعه وعكسه (WF-03) ·
 * دورة الطلب والرد التسعة (WF-05) · حل المدير والمتحقق · كنس المهل.
 * البيانات موسومة TST-WFM وتُكنس في الخاتمة.
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
/* ثلاثة فاعلين حقيقيين من co4: مكلِّف (له مدير) · منفِّذ · متحقِّق */
$actors = array();
$r = mysqli_query($conn, "SELECT id, parent_id FROM users WHERE company_id = {$CO} AND COALESCE(status,'active')='active' ORDER BY id LIMIT 6");
while ($x = mysqli_fetch_assoc($r)) { $actors[] = $x; }
if (count($actors) < 3) { fwrite(STDOUT, "لا فاعلين كفاية في co4\n"); exit(1); }
$OWNER = intval($actors[0]['id']);
$EXEC  = intval($actors[1]['id']);
$VERIF = intval($actors[2]['id']);
$due = date('Y-m-d H:i:s', time() + 86400);

fwrite(STDOUT, "── ① الإنشاء (WF-02): الحارس السباعي\n");
$r1 = WI::create($conn, array('company_id' => $CO, 'source_type' => 'SRC-01', 'source_ref' => 'TST-WFM-1',
    'source_screen' => 'tests', 'owner_user_id' => $OWNER, 'assigned_user_id' => $EXEC, 'verifier_user_id' => $VERIF,
    'org_unit_id' => 1, 'title' => 'TST-WFM مهمة اختبار', 'deliverable' => 'مخرج اختباري',
    // INJ-0486: الدليلُ المطلوبُ صار شرطًا في الحارس — والفِخاخُ الموجبةُ توفيه
    // كي تُقاس الأبوابُ التاليةُ لا يُحجب المسارُ عندها (والرفضُ مقيسٌ في r2/r3)
    'evidence_required' => 'أثر التنفيذ في سجل التدقيق',
    'due_at' => $due, 'created_by' => $OWNER));
ok($r1['ok'] && $r1['status'] === 'assigned', 'سماح: السبعة كاملة ⇒ أُنشئت مسنَدة #' . ($r1['id'] ?? '؟'));
$ITEM = intval($r1['id'] ?? 0);

$r2 = WI::create($conn, array('company_id' => $CO, 'source_type' => 'SRC-01', 'source_ref' => 'TST-WFM-2',
    'owner_user_id' => $OWNER, 'title' => 'ناقصة', 'created_by' => $OWNER));
ok(!$r2['ok'] && mb_strpos($r2['reason'], 'الناقص') !== false, 'منع: الناقص يُسمّى — ' . mb_substr($r2['reason'], 0, 60));

$r3 = WI::create($conn, array('company_id' => $CO, 'source_type' => 'SRC-99', 'source_ref' => 'TST-WFM-3',
    'owner_user_id' => $OWNER, 'assigned_user_id' => $EXEC, 'org_unit_id' => 1,
    'title' => 'مصدر دخيل', 'deliverable' => 'x', 'due_at' => $due, 'created_by' => $OWNER));
ok(!$r3['ok'], 'منع: مصدرٌ خارج الأربعة عشر يُرفض');

/* WF-08: تفويض منتهٍ يوقف التوليد */
mysqli_query($conn, "INSERT INTO work_delegations (company_id, kind, from_user_id, to_user_id, scope_ref, starts_at, ends_at, status, created_by)
                     VALUES ({$CO}, 'deputize', {$OWNER}, {$EXEC}, 'TST-WFM-DELEG', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'active', {$OWNER})");
$r4 = WI::create($conn, array('company_id' => $CO, 'source_type' => 'SRC-02', 'source_ref' => 'TST-WFM-4',
    'owner_user_id' => $OWNER, 'assigned_user_id' => $EXEC, 'org_unit_id' => 1, 'delegation_ref' => 'TST-WFM-DELEG',
    // السبعةُ كاملةٌ عمدًا — وإلّا رُدَّ لنقصِها فقِيس رفضٌ غيرُ الذي يقصده الفخ
    'evidence_required' => 'x', 'verifier_user_id' => $VERIF,
    'title' => 'بتفويض ميت', 'deliverable' => 'x', 'due_at' => $due, 'created_by' => $OWNER));
ok(!$r4['ok'] && mb_strpos($r4['reason'], 'WF-08') !== false, 'منع (WF-08): تفويضٌ منتهٍ لا يولّد');

fwrite(STDOUT, "── ② الدورة والإغلاق من غير المنفِّذ (WF-04)\n");
ok(WI::transition($conn, $ITEM, 'accepted', $EXEC)['ok'], 'سماح: المنفِّذ يستلم');
ok(WI::transition($conn, $ITEM, 'in_progress', $EXEC)['ok'], 'سماح: بدء التنفيذ');
$rb = WI::transition($conn, $ITEM, 'blocked', $EXEC, '');
ok(!$rb['ok'] && $rb['code'] === 422, 'منع: التوقف بلا سببٍ يُرفض');
ok(WI::transition($conn, $ITEM, 'blocked', $EXEC, 'awaiting_part')['ok'], 'سماح: توقف بسببٍ من القائمة (يوقف العد)');
ok(WI::transition($conn, $ITEM, 'in_progress', $EXEC)['ok'], 'سماح: استئناف');
ok(WI::transition($conn, $ITEM, 'done_pending_verify', $EXEC)['ok'], 'سماح: الإكمال وتقديم الدليل');
$rc = WI::transition($conn, $ITEM, 'closed_accepted', $EXEC);
ok(!$rc['ok'] && $rc['code'] === 403, 'منع (WF-04): المنفِّذ لا يغلق مهمته — 403');
ok(WI::transition($conn, $ITEM, 'closed_accepted', $VERIF)['ok'], 'سماح: المتحقِّق يقبل ويغلق');
$rr = WI::transition($conn, $ITEM, 'closed_accepted', $VERIF);
ok(!$rr['ok'] && $rr['code'] === 409, 'تكرار: إغلاق المغلق يُرفض 409');

fwrite(STDOUT, "── ③ الإنجاز (WF-03): اشتقاقٌ وموانعُ وعكس\n");
$c1 = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) FROM achievement_records WHERE source_kind='task' AND source_ref='" . $ITEM . "' AND reversed_at IS NULL");
if ($r) { $c1 = intval(mysqli_fetch_row($r)[0]); }
ok($c1 >= 2, "اشتُق بالإغلاق: تنفيذيٌّ للمنفِّذ وإشرافيٌّ للمتحقق ({$c1} صفًّا)");
$d1 = ACH::derive($conn, array('company_id' => $CO, 'source_kind' => 'task', 'source_ref' => (string) $ITEM,
    'person_user_id' => $EXEC, 'attribution' => 'executive', 'title' => 'تكرار', 'evidence_ref' => 'x', 'created_by' => $VERIF));
$r = mysqli_query($conn, "SELECT COUNT(*) FROM achievement_records WHERE source_kind='task' AND source_ref='" . $ITEM . "'");
$c2 = intval(mysqli_fetch_row($r)[0]);
ok($d1['ok'] && $c2 === $c1, 'تكرار: الاشتقاق المكرر يعود بالمرجع ولا يضاعف (منع التضاعف بنيوي)');
$d2 = ACH::derive($conn, array('company_id' => $CO, 'source_kind' => 'self_claim', 'source_ref' => 'x',
    'person_user_id' => $EXEC, 'title' => 'ادعاء', 'evidence_ref' => 'x', 'created_by' => $EXEC));
ok(!$d2['ok'], 'منع: مصدرٌ خارج الثمانية يُرفض (لا تسجيلَ ذاتيًّا)');
$d3 = ACH::derive($conn, array('company_id' => $CO, 'source_kind' => 'task', 'source_ref' => 'TST-WFM-NOEV',
    'person_user_id' => $EXEC, 'title' => 'بلا دليل', 'evidence_ref' => '', 'created_by' => $VERIF));
ok(!$d3['ok'], 'منع: صفرُ إنجازٍ بلا دليل');
$ro = WI::transition($conn, $ITEM, 'reopened', $OWNER, 'خللٌ في الدليل', array('governance_ok' => true));
$r = mysqli_query($conn, "SELECT COUNT(*) FROM achievement_records WHERE source_kind='task' AND source_ref='" . $ITEM . "' AND reversed_at IS NULL");
$c3 = intval(mysqli_fetch_row($r)[0]);
ok($ro['ok'] && $c3 === 0, 'عكس: إعادةُ الفتح عكست الإنجازَ آليًّا (الحي=0)');

fwrite(STDOUT, "── ④ الطلبات (WF-05/07): القاموس والتوجيه والرد التسعة\n");
$q1 = RQ::submit($conn, array('company_id' => $CO, 'request_type_code' => 'RQ-XX-99',
    'requester_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'TST-WFM دخيل'));
ok(!$q1['ok'], 'منع: نوعٌ خارج القاموس يُرفض');
$q2 = RQ::submit($conn, array('company_id' => $CO, 'request_type_code' => 'RQ-HR-04',
    'requester_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'TST-WFM شهادة خبرة', 'created_by' => $EXEC));
ok($q2['ok'] && !empty($q2['holder']), 'سماح: قُدّم وفُتحت خطوة السلسلة — الحامل u' . ($q2['holder'] ?? '؟'));
$REQ = intval($q2['id'] ?? 0);
$HOLDER = intval($q2['holder']);
$r = mysqli_query($conn, "SELECT COUNT(*) FROM approval_links WHERE source_kind='request' AND source_ref='" . ($q2['request_no'] ?? '') . "' AND status='pending'");
ok(intval(mysqli_fetch_row($r)[0]) === 1, 'الورقة 09 تشغيلًا: خطوةُ approval_links مفتوحةٌ للحامل');
$self = RQ::decide($conn, $REQ, 'approve', $EXEC);
ok(!$self['ok'] && $self['code'] === 403, 'منع: لا اعتمادَ للذات (من قدَّم لا يعتمد)');
$stranger2 = null;
foreach ($actors as $ac) { $aid = intval($ac['id']); if ($aid !== $EXEC && $aid !== $HOLDER) { $stranger2 = $aid; break; } }
$ns = RQ::decide($conn, $REQ, 'approve', $stranger2);
ok(!$ns['ok'] && $ns['code'] === 403, 'منع: القرارُ لحامل الخطوة وحدَه — الغريب 403');
ok(RQ::decide($conn, $REQ, 'approve', $HOLDER, 'مستوفٍ')['ok'], 'سماح: اعتماد حامل الخطوة');
$r = mysqli_query($conn, "SELECT current_holder_user_id FROM requests WHERE id = {$REQ}");
$EXEC_HOLDER = intval(mysqli_fetch_row($r)[0]);
ok($EXEC_HOLDER > 0, 'بعد السلسلة: الحاملُ منفِّذُ الإدارة (u' . $EXEC_HOLDER . ')');
$e0 = RQ::executeAndClose($conn, $REQ, $stranger2, array('decision' => 'approved', 'result_doc_ref' => 'x', 'executed_summary' => 'x'));
ok(!$e0['ok'] && $e0['code'] === 403, 'منع: التنفيذُ لحامله وحدَه');
$e1 = RQ::executeAndClose($conn, $REQ, $EXEC_HOLDER, array('decision' => 'approved'));
ok(!$e1['ok'] && mb_strpos($e1['reason'], 'WF-05') !== false, 'منع (WF-05): إغلاقٌ بلا الرد التسعة يُرفض');
$e2 = RQ::executeAndClose($conn, $REQ, $EXEC_HOLDER, array(
    'decision' => 'approved', 'decided_capacity' => 'الموارد', 'notes' => 'استُوفي',
    'action_required' => 'استلام المستند', 'result_doc_ref' => 'DOC-TST-1',
    'executed_summary' => 'أُصدرت الشهادة ووُقّعت', 'next_step' => 'لا شيء'));
ok($e2['ok'], 'سماح: التنفيذ والإغلاق بالرد التسعة');
$r = mysqli_query($conn, "SELECT COUNT(*) FROM request_responses WHERE request_id = {$REQ}");
ok(intval(mysqli_fetch_row($r)[0]) === 1, 'الردُّ صفٌّ محفوظٌ بعناصره');
$r = mysqli_query($conn, "SELECT status, current_holder_user_id FROM requests WHERE id = {$REQ}");
$x = mysqli_fetch_assoc($r);
ok($x['status'] === 'closed' && $x['current_holder_user_id'] === null, 'الحالة closed ولا حامل (يُعرف أين توقف دائمًا)');
$q3 = RQ::submit($conn, array('company_id' => $CO, 'request_type_code' => 'RQ-HR-07',
    'requester_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'TST-WFM تدريب', 'created_by' => $EXEC));
ok($q3['ok'], 'سماح: النوع المقترح صار نافذًا بقرار الشركة (RQ-HR-07)');
$REQ2 = intval($q3['id'] ?? 0);
$cs = RQ::cancel($conn, $REQ2, $stranger2, 'دخيل');
ok(!$cs['ok'] && $cs['code'] === 403, 'منع: الإلغاءُ لمقدِّمه أو حامله وحدهما — الغريب 403');
$cx = RQ::cancel($conn, $REQ2, $EXEC, 'اختبار الإلغاء');
ok($cx['ok'], 'عكس: إلغاءُ المقدِّم بسببٍ يمضي ويعكس أثره');
$c2 = RQ::cancel($conn, $REQ2, $EXEC, 'ثانية');
ok(!$c2['ok'] && $c2['code'] === 409, 'تكرار: إلغاءُ الملغى 409');

fwrite(STDOUT, "── ⑤ الهرم والمهل\n");
$mgrKnown = null;
$r = mysqli_query($conn, "SELECT id FROM users WHERE company_id = {$CO} AND parent_id IS NOT NULL AND parent_id > 0 LIMIT 1");
if ($r && ($x = mysqli_fetch_row($r))) { $mgrKnown = ems_resolve_manager($conn, intval($x[0])); }
ok($mgrKnown !== null, 'resolveManager: مرؤوسٌ حقيقيٌّ يجد مديره (u' . $mgrKnown . ')');
ok(ems_resolve_verifier($conn, 881, $CO) !== null, 'resolveVerifier: قمة الهرم ترتد للحوكمة');
$r5 = WI::create($conn, array('company_id' => $CO, 'source_type' => 'SRC-11', 'source_ref' => 'TST-WFM-SLA',
    'owner_user_id' => $OWNER, 'assigned_user_id' => $EXEC, 'org_unit_id' => 1,
    'evidence_required' => 'x', 'verifier_user_id' => $VERIF,
    'title' => 'TST-WFM متأخرة', 'deliverable' => 'x', 'due_at' => date('Y-m-d H:i:s', time() - 3600), 'created_by' => $OWNER));
$sla = WI::sweepSla($conn);
$r = mysqli_query($conn, "SELECT status, escalation_level FROM work_items WHERE id = " . intval($r5['id']));
$x = mysqli_fetch_assoc($r);
ok($x['status'] === 'overdue' && intval($x['escalation_level']) === 1, 'كنس المهل: متأخرةٌ وُسمت وصُعّدت درجة (AC-WFM-09)');
$r = mysqli_query($conn, "SELECT COUNT(*) FROM work_escalations WHERE item_kind='work_item' AND item_ref=" . intval($r5['id']));
ok(intval(mysqli_fetch_row($r)[0]) === 1, 'صفُّ تصعيدٍ واحدٌ مسجَّل');

fwrite(STDOUT, "── ⑥ تفسير الظهور (AC-WFM-13)\n");
$ex = WI::explainAppearance($conn, $ITEM, $EXEC);
ok($ex['complete'] && count($ex['steps']) === 5, 'السلسلة الخماسية كاملة للمنفِّذ');
$stranger = intval($actors[count($actors) - 1]['id']);
$ex2 = WI::explainAppearance($conn, $ITEM, $stranger);
ok(!$ex2['complete'] || $stranger === $EXEC || $stranger === $OWNER || $stranger === $VERIF,
   'الغريبُ خارج النطاق ⇒ سلسلةٌ ناقصة (يُحجب)');

fwrite(STDOUT, "── ⑦ سلسلة متعددة الخطوات (المدير ← الموارد)\n");
$q4 = RQ::submit($conn, array('company_id' => $CO, 'request_type_code' => 'RQ-HR-01',
    'requester_user_id' => $EXEC, 'org_unit_id' => 1, 'title' => 'TST-WFM إجازة', 'created_by' => $EXEC));
$H1 = intval($q4['holder'] ?? 0);
ok($q4['ok'] && intval($q4['steps']) === 2 && $H1 > 0, 'خطوتان — الأولى للمدير (u' . $H1 . ')');
$REQ4 = intval($q4['id'] ?? 0);
$d1 = RQ::decide($conn, $REQ4, 'approve', $H1, 'موافق');
ok($d1['ok'] && ($d1['status'] ?? '') === 'in_approval', 'اعتمادُ الوسطى يفتح التالية ولا يعتمد الطلب');
$r = mysqli_query($conn, "SELECT current_holder_user_id, current_step FROM requests WHERE id = {$REQ4}");
$x = mysqli_fetch_assoc($r);
ok(intval($x['current_step']) === 2 && intval($x['current_holder_user_id']) !== $H1, 'الحاملُ انتقل للخطوة 2 (u' . $x['current_holder_user_id'] . ')');
$r = mysqli_query($conn, "SELECT COUNT(*) FROM approval_links WHERE source_kind='request' AND source_ref='" . ($q4['request_no'] ?? '') . "'");
ok(intval(mysqli_fetch_row($r)[0]) === 2, 'صفّا خطوتين في approval_links (الأولى مختومة)');
RQ::cancel($conn, $REQ4, $EXEC, 'اختبار');

/* الخاتمة: كنس بيانات الاختبار */
mysqli_query($conn, "DELETE FROM achievement_records WHERE source_ref IN ('" . $ITEM . "','" . ($q2['request_no'] ?? 'x') . "') OR title LIKE 'TST-WFM%' OR title LIKE '%TST-WFM%'");
mysqli_query($conn, "DELETE FROM work_escalations WHERE note LIKE 'TST-WFM%'");
mysqli_query($conn, "DELETE FROM task_assignments WHERE item_id IN (SELECT id FROM work_items WHERE source_ref LIKE 'TST-WFM%' OR title LIKE '%TST-WFM%')");
mysqli_query($conn, "DELETE FROM personal_notifications WHERE title LIKE '%TST-WFM%' OR body LIKE '%TST-WFM%'");
mysqli_query($conn, "DELETE FROM request_responses WHERE request_id IN (SELECT id FROM requests WHERE title LIKE '%TST-WFM%')");
mysqli_query($conn, "DELETE FROM approval_links WHERE source_kind='request' AND source_ref IN (SELECT request_no FROM requests WHERE title LIKE '%TST-WFM%')");
mysqli_query($conn, "DELETE FROM work_items WHERE source_ref LIKE 'TST-WFM%' OR title LIKE '%TST-WFM%' OR parent_ref IN (SELECT request_no FROM requests WHERE title LIKE '%TST-WFM%')");
mysqli_query($conn, "DELETE FROM requests WHERE title LIKE '%TST-WFM%'");
mysqli_query($conn, "DELETE FROM work_delegations WHERE scope_ref = 'TST-WFM-DELEG'");

fwrite(STDOUT, "══ النتيجة: {$pass} ناجحة · {$failN} فاشلة ══\n");
exit($failN === 0 ? 0 : 1);
