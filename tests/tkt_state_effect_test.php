<?php
/**
 * update0004 · الموجة ⑬ — حزام الحالات والأثر والسرية (TKT-11→14)
 * T2·T4·T6·T7·T10·T13·T14·T15·T17·T18 + خرط الموروث
 * التشغيل: php tests/tkt_state_effect_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketRouter.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketStateService.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketEffectService.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/ConfidentialityGuard.php';

use App\Services\Tickets\TicketRouter as TR;
use App\Services\Tickets\TicketStateService as TS;
use App\Services\Tickets\TicketEffectService as TE;
use App\Services\Tickets\ConfidentialityGuard as CG;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'TS13T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $ids = array();
    $r = $conn->query("SELECT id FROM tickets WHERE complaint LIKE '%{$MARK}%' OR private_details LIKE '%{$MARK}%' OR operational_summary LIKE '%{$MARK}%' OR reporting_person LIKE '%{$MARK}%'");
    while ($r && ($x = $r->fetch_assoc())) { $ids[] = intval($x['id']); }
    if ($ids) {
        $in = implode(',', $ids);
        foreach (array(
            "DELETE e FROM ticket_escalations e JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE h FROM ticket_holds h JOIN ticket_workstreams w ON w.ws_id=h.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE f FROM ticket_effects f JOIN ticket_workstreams w ON w.ws_id=f.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE FROM ticket_responses WHERE tk_id IN ({$in})",
            "DELETE FROM ticket_participants WHERE tk_id IN ({$in})",
            "DELETE FROM ticket_workstreams WHERE tk_id IN ({$in})",
            "DELETE FROM sensitive_read_log WHERE subject_type='ticket' AND subject_id IN ({$in})",
            "DELETE FROM tickets WHERE id IN ({$in})",
        ) as $q) { $conn->query($q); }
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑬ — الحالات والأثر والسرية ══\n");

$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$eqType = $conn->query("SELECT id, code FROM ticket_types WHERE category='equipment' AND nature='incident' LIMIT 1")->fetch_assoc();

$r = TR::create($conn, array('company_id' => $CO, 'type_code' => $eqType['code'],
    'description' => 'عطل ' . $MARK, 'reporter_person_id' => 9901,
    'context' => array('screen' => 'timesheet', 'site_id' => $SITE, 'equipment_id' => 1)));
$TK = $r['tk_id'];
$ws = array();
$res = $conn->query("SELECT ws_id, workstream_type, assignee_person_id, mandatory FROM ticket_workstreams WHERE tk_id={$TK}");
while ($res && ($x = $res->fetch_assoc())) { $ws[$x['workstream_type']] = $x; }
$MWS = intval($ws['maintenance']['ws_id']);
$VWS = intval($ws['movement']['ws_id']);
$ASSIGNEE = intval($ws['maintenance']['assignee_person_id']);

head('T2/T14 — الخطوات الأربع: فحص لا إصلاح وإسناد مبدئي');
$e = TE::onFaultReported($conn, $TK, $MWS, $VWS);
check($e['ok'] && $e['effects'][0]['type'] === 'inspection_request', 'طلب فحص لا أمر إصلاح (①)');
$prov = $conn->query("SELECT is_provisional FROM ticket_effects e JOIN ticket_workstreams w ON w.ws_id=e.ws_id
                      WHERE w.tk_id={$TK} AND e.effect_type='stoppage_attribution'")->fetch_assoc();
check(intval($prov['is_provisional']) === 1, 'الإسناد مبدئي — صفر أثر على الفوترة قبل الاعتمادين');
$f = TE::approveOperationalImpact($conn, $TK, $VWS, $decider);
check(!$f['ok'] && $f['code'] === 409, 'لا نهائية قبل اعتماد السبب الفني (الترتيب محروس)');
TE::approveTechnicalCause($conn, $TK, $MWS, $decider, 'تسريب خرطوم');
$f = TE::approveOperationalImpact($conn, $TK, $VWS, $decider);
check($f['ok'] && $f['finalized'] >= 1, 'بعد الاعتمادين (③④): الإسناد نهائي ويؤثر في الفوترة');

head('T4 — التعليق المحكوم');
$h = TS::hold($conn, $MWS, 'سبب حر', date('Y-m-d H:i:s', strtotime('+1 day')));
check(!$h['ok'] && $h['code'] === 422, 'سبب حر → 422');
$h = TS::hold($conn, $MWS, 'awaiting_part', null);
check(!$h['ok'] && $h['code'] === 422, 'بلا مدة متوقعة → 422');
$h = TS::hold($conn, $MWS, 'awaiting_part', date('Y-m-d H:i:s', strtotime('+1 day')));
check($h['ok'], 'وبسبب محكوم ومدة → يُعلَّق');
$conn->query("UPDATE ticket_holds SET ended_at = NOW() WHERE ws_id = {$MWS} AND ended_at IS NULL");
$conn->query("UPDATE ticket_workstreams SET state='in_progress' WHERE ws_id={$MWS}");

head('T6/T7 — لا إغلاق ذاتي ولا إغلاق بلا أثر');
$d = TS::markDone($conn, $MWS, $ASSIGNEE);
check($d['ok'], 'الإنجاز بأثر مسجل → done_pending (لا يغلق المكلف بنفسه)');
$c = TS::closeWorkstream($conn, $MWS, $ASSIGNEE, 'reporter_confirm');
check(!$c['ok'] && $c['code'] === 403, 'المكلف يحاول الإغلاق → 403');
$emptyWs = intval($ws['operators']['ws_id']);
$conn->query("UPDATE ticket_workstreams SET assignee_person_id=9905, state='in_progress' WHERE ws_id={$emptyWs}");
$d2 = TS::markDone($conn, $emptyWs, 9905);
check(!$d2['ok'] && $d2['code'] === 422, 'إنجاز بلا أثر مسجل → 422');

head('T13 — لا يُغلق الرأس ومسار إلزامي مفتوح');
$c = TS::closeHead($conn, $TK);
check(!$c['ok'] && $c['code'] === 423, "رأس بمسارات إلزامية مفتوحة → 423 ({$c['reason']})");

head('T17 — سياسة الإغلاق: لا آلي لما سياسته تمنعه');
$c = TS::closeWorkstream($conn, $MWS, 0, 'auto');
check(!$c['ok'] && $c['code'] === 403, 'إغلاق آلي لنوع سياسته owner_approve → 403');
$c = TS::closeWorkstream($conn, $MWS, 9901, 'owner_approve');
check($c['ok'], 'وباعتماد صاحب الاختصاص (غير المكلف) يُغلق');

head('T10 — حد المركز: الإداري للمكرر والملغى فقط');
$c = TS::closeWorkstream($conn, $VWS, 9905, 'admin');
check(!$c['ok'] && $c['code'] === 403, 'إداري من غير المركز → 403');
$tktMgr = intval($conn->query("SELECT id FROM users WHERE role='24' LIMIT 1")->fetch_assoc()['id'] ?? 0);
check($tktMgr > 0, "مستخدم مركز البلاغات موجود ({$tktMgr})");

head('T8 — إعادة الفتح الثالثة ترفع للمركز');
$rw = intval($ws['warehouse']['ws_id']);
$conn->query("UPDATE ticket_workstreams SET assignee_person_id=9906, state='in_progress' WHERE ws_id={$rw}");
TE::recordLightEffect($conn, $rw, 'issue_request', 9906, 'صرف قطعة');
TS::markDone($conn, $rw, 9906);
for ($i = 1; $i <= 3; $i++) {
    TS::closeWorkstream($conn, $rw, 9901, 'reporter_confirm');
    $ro = TS::reopen($conn, $rw, 9901);
}
check($ro['ok'] && $ro['reopen_count'] === 3 && $ro['raised_to_center'] === true,
    'الإعادة الثالثة → رفع آلي للمركز وعداد ظاهر (' . $ro['reopen_count'] . ')');

head('T15 — السرية: الفصل البنيوي والاطلاع المسجل');
$hrType = $conn->query("SELECT code FROM ticket_types WHERE code='complaint_hr'")->fetch_assoc();
$rc = TR::create($conn, array('company_id' => $CO, 'type_code' => 'complaint_hr',
    'description' => 'ظرف شخصي حساس ' . $MARK, 'reporter_person_id' => 9907,
    'context' => array('screen' => 'portal')));
check($rc['ok'], "شكوى موظف أُنشئت (#{$rc['tk_id']})");
$TK2 = $rc['tk_id'];
$opView = CG::fetchTicket($conn, $TK2, 9910, '13'); // دور الصيانة — غير مخول
check($opView !== null && !isset($opView['private_details']) && !isset($opView['complaint']),
    'التشغيل: التفاصيل لا تُجلب أصلًا — الملخص وحده');
check(mb_strpos((string) $opView['operational_summary'], 'غير متاح') !== false,
    'الملخص التشغيلي: «غير متاح — يحتاج معالجة» بلا سبب ولا اسم');
$hrView = CG::fetchTicket($conn, $TK2, 9911, '4'); // الموارد البشرية
check(isset($hrView['private_details']) && mb_strpos($hrView['private_details'], $MARK) !== false,
    'الموارد البشرية ترى التفاصيل');
$lg = $conn->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE subject_type='ticket' AND subject_id={$TK2}")->fetch_assoc();
check(intval($lg['c']) >= 1, 'وكل اطلاع مسجل (' . $lg['c'] . ')');

head('T18 — الأثر المناسب: معلومة تُغلق بإقرار لا أمر عمل');
$ri = TR::create($conn, array('company_id' => $CO, 'type_code' => 'information_note',
    'description' => 'إخطار ' . $MARK, 'reporter_person_id' => 9907,
    'context' => array('screen' => 'portal')));
$TK3 = $ri['tk_id'];
$w3 = $conn->query("SELECT ws_id FROM ticket_workstreams WHERE tk_id={$TK3} LIMIT 1")->fetch_assoc();
$conn->query("UPDATE ticket_workstreams SET assignee_person_id=9912, state='in_progress' WHERE ws_id=" . intval($w3['ws_id']));
TE::recordLightEffect($conn, intval($w3['ws_id']), 'acknowledge', 9912, 'استُلم وأُحيط علمًا');
$d3 = TS::markDone($conn, intval($w3['ws_id']), 9912);
$c3 = TS::closeWorkstream($conn, intval($w3['ws_id']), 9907, 'auto'); // سياسة المعلومة auto
check($d3['ok'] && $c3['ok'], 'المعلومة أُغلقت بإقرار استلام — ولا أمر عمل');
$hs = $conn->query("SELECT head_state FROM tickets WHERE id={$TK3}")->fetch_assoc();
check($hs['head_state'] === 'closed', 'والرأس المشتق أُغلق بإغلاق مساره الوحيد');

head('TKT-14 — خرط الموروث');
/* ══ **البلاغُ الملغى لا عملَ له يُوجَّه.** الشرطُ صحيحٌ في جوهرِه (لا بلاغَ بلا
     مسارٍ ولا مالك) لكنه كان يعدُّ **كلَّ** مرحلةٍ ومنها `cancelled` — وطلبُ
     مسارٍ لبلاغٍ ملغًى يعني تلفيقَ مكلَّفٍ ومهلةٍ لعملٍ لن يُنفَّذ.
     وقد كشف هذا الشرطُ خمسةَ عشرَ بلاغًا بلا مسار، والقياسُ أثبت أنها **آثارُ
     تحقُّقٍ يدويٍّ** من 2026-08-08 («تحقق 1/2/3» · «تحقق CSRF» · «انحدار الترقيم»
     عشرةٌ · «اختبار مدير البلاغات») لا بلاغاتِ عمل — فأُلغيت بسببٍ مكتوبٍ في
     نصِّها (هجرة 2027_03_06) ولم تُحذف.
   ⇒ فيُقاس ما يهمُّ: **صفرُ بلاغٍ غيرِ ملغًى بلا مسار** · ويُعلَن عددُ الملغاةِ
     بلا مسارٍ إعلامًا لا حكمًا. */
$r = $conn->query("SELECT COUNT(*) c FROM tickets t
                    WHERE t.stage <> 'cancelled'
                      AND NOT EXISTS (SELECT 1 FROM ticket_workstreams w WHERE w.tk_id = t.id)")->fetch_assoc();
$noPathLive = intval($r['c']);
$r2 = $conn->query("SELECT COUNT(*) c FROM tickets t
                     WHERE t.stage = 'cancelled'
                       AND NOT EXISTS (SELECT 1 FROM ticket_workstreams w WHERE w.tk_id = t.id)")->fetch_assoc();
check($noPathLive === 0,
    'صفر بلاغ غيرِ ملغًى بلا مسار — الخرط شامل (وملغاةٌ بلا مسار: '
    . intval($r2['c']) . ' — والملغى لا عملَ له يُوجَّه)');
/* ◆ الجذرُ نفسُه كما في `tkt_structure_test`: `done` = منجَزٌ بانتظارِ التأكيدِ
     ورأسُه مفتوحٌ بقرارٍ لاحقٍ نقض خرطَ 2026_08_02. */
$r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE stage IN ('closed','cancelled') AND head_state='open'")->fetch_assoc();
check(intval($r['c']) === 0, 'ولا مغلقٌ أو ملغًى قديمٌ برأسٍ مفتوح');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
