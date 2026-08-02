<?php
/**
 * update0004 · الموجة ⑫ — حزام التوجيه: T1·T3·T5·T12·T16 + الشرطي (TKT-07→10)
 * التشغيل: php tests/tkt_routing_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketRouter.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/WorkstreamActivator.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/DuplicateDetector.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/SlaMonitor.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';

use App\Services\Tickets\TicketRouter as TR;
use App\Services\Tickets\WorkstreamActivator as WA;
use App\Services\Tickets\DuplicateDetector as DD;
use App\Services\Tickets\SlaMonitor as SM;
use App\Services\Org\AssignmentService as ASV;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'TR12T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $ids = array();
    $r = $conn->query("SELECT id FROM tickets WHERE complaint LIKE '%{$MARK}%' OR operational_summary LIKE '%{$MARK}%'");
    while ($r && ($x = $r->fetch_assoc())) { $ids[] = intval($x['id']); }
    if ($ids) {
        $in = implode(',', $ids);
        $conn->query("DELETE e FROM ticket_escalations e JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id IN ({$in})");
        $conn->query("DELETE h FROM ticket_holds h JOIN ticket_workstreams w ON w.ws_id=h.ws_id WHERE w.tk_id IN ({$in})");
        $conn->query("DELETE f FROM ticket_effects f JOIN ticket_workstreams w ON w.ws_id=f.ws_id WHERE w.tk_id IN ({$in})");
        $conn->query("DELETE FROM ticket_responses WHERE tk_id IN ({$in})");
        $conn->query("DELETE FROM ticket_participants WHERE tk_id IN ({$in})");
        $conn->query("DELETE FROM ticket_workstreams WHERE tk_id IN ({$in})");
        $conn->query("DELETE FROM tickets WHERE id IN ({$in})");
    }
    $conn->query("DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id=l.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id=g.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM assignment_capabilities c JOIN org_assignments a ON a.asg_id=c.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑫ — التوجيه ══\n");

// بذر سلطة: مسؤول صيانة موقعي (سيُحل مكلفًا من ORG لا من جدول النوع)
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$mntUnit = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='maintenance'")->fetch_assoc()['unit_id']);
$movUnit = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);
$cen = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9801, 'assignment_type_code' => 'maintenance_mgr',
    'org_unit_id' => $mntUnit, 'scope_type' => 'site_group', 'scope_id' => 0,
    'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+90 days')),
    'decided_by_person_id' => $decider, 'decision_ref' => $MARK));
$mnt = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9802, 'assignment_type_code' => 'site_maintenance_officer',
    'org_unit_id' => $mntUnit, 'scope_type' => 'site', 'scope_id' => $SITE,
    'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+90 days')),
    'decided_by_person_id' => $decider, 'decision_ref' => $MARK,
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => $cen['asg_id']),
        array('line_type' => 'functional', 'reports_to_assignment_id' => $cen['asg_id']))));
check($mnt['ok'], 'بُذر مسؤول صيانة الموقع (9802)');

$eqType = $conn->query("SELECT id, code FROM ticket_types WHERE category='equipment' AND nature='incident' LIMIT 1")->fetch_assoc();
$EQ = intval($conn->query("SELECT id FROM equipments LIMIT 1")->fetch_assoc()['id'] ?? 0);

head('T1 — الفتح السياقي والتوجيه خلال ثانية');
$r = TR::create($conn, array('company_id' => $CO, 'type_code' => $eqType['code'],
    'description' => 'تسريب هيدروليك ' . $MARK, 'reporter_person_id' => $decider,
    'context' => array('screen' => 'timesheet', 'site_id' => $SITE, 'equipment_id' => $EQ, 'shift_no' => 1, 'period_no' => 2)));
check($r['ok'] && $r['tk_id'] > 0, "أُنشئ ووُجّه (#{$r['tk_id']})");
$TK = $r['tk_id'];
$t = $conn->query("SELECT source_screen, site_id, equipment_id, shift_no FROM tickets WHERE id={$TK}")->fetch_assoc();
check($t['source_screen'] === 'timesheet' && intval($t['site_id']) === $SITE && intval($t['shift_no']) === 1,
    'السياق محمول كاملًا — ولم يُدخل المستخدم موقعًا ولا معدة');
$r2 = TR::create($conn, array('company_id' => $CO, 'type_code' => $eqType['code'],
    'description' => 'بلا سياق ' . $MARK, 'reporter_person_id' => $decider,
    'context' => array('screen' => 'timesheet')));
check(!$r2['ok'] && $r2['code'] === 422, 'سياق ناقص من شاشة تشغيلية → 422');

head('T12 — المسارات المتوازية والشرطي pending');
$ws = $conn->query("SELECT workstream_type, activation_state, mandatory, assignee_person_id FROM ticket_workstreams WHERE tk_id={$TK} ORDER BY ws_id")->fetch_all(MYSQLI_ASSOC);
check(count($ws) === 5, 'خمسة مسارات فُتحت دفعة واحدة (وُجد ' . count($ws) . ')');
$byType = array();
foreach ($ws as $w) { $byType[$w['workstream_type']] = $w; }
check($byType['procurement']['activation_state'] === 'pending', 'المشتريات شرطي pending — لا يُفتح بالإنشاء');
check($byType['maintenance']['activation_state'] === 'opened' && intval($byType['maintenance']['assignee_person_id']) === 9802,
    'الصيانة فورية ومكلفها حُلّ من تكليف ORG-01 (9802) لا من جدول النوع');
check(intval($byType['movement']['mandatory']) === 1 && intval($byType['operators']['mandatory']) === 0,
    'الإلزامي والاختياري كما بُذر');

head('التفعيل الشرطي — StockUnavailable');
$a = WA::onEvent($conn, $TK, 'StockUnavailable', 9802);
check($a['ok'] && $a['activated'] === 1, 'حدث النفاد فتح مسار المشتريات');
$st = $conn->query("SELECT activation_state, state FROM ticket_workstreams WHERE tk_id={$TK} AND workstream_type='procurement'")->fetch_assoc();
check($st['activation_state'] === 'opened' && $st['state'] === 'new', 'وصار opened بمهله');

head('T3 — التصعيد الآلي بلا طلب');
$conn->query("UPDATE ticket_workstreams SET response_due_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE)
              WHERE tk_id={$TK} AND workstream_type='maintenance'");
$m = SM::run($conn, $CO);
check($m['response_breach'] >= 1, "تجاوز الاستجابة رُصد ({$m['response_breach']})");
$esc = $conn->query("SELECT e.level, e.triggered_by FROM ticket_escalations e
                      JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id={$TK}")->fetch_all(MYSQLI_ASSOC);
check(count($esc) >= 1 && $esc[0]['triggered_by'] === 'sla_breach', 'Escalated سطر Insert-only');
$own = $conn->query("SELECT assignee_person_id FROM ticket_workstreams WHERE tk_id={$TK} AND workstream_type='maintenance'")->fetch_assoc();
check(intval($own['assignee_person_id']) === 9802, 'والمالك الأصلي يبقى مسؤولًا — التصعيد إضافة رقيب');
$m2 = SM::run($conn, $CO);
check($m2['response_breach'] === 0, 'الدورة الثانية لا تكرر التصعيد نفسه (عطالة)');

head('T5 — التعليق المتجاوز يُصعَّد هو نفسه');
$wsM = intval($conn->query("SELECT ws_id FROM ticket_workstreams WHERE tk_id={$TK} AND workstream_type='warehouse'")->fetch_assoc()['ws_id']);
$conn->query("INSERT INTO ticket_holds (ws_id, reason_code, expected_until, started_at)
              VALUES ({$wsM}, 'awaiting_part', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR))");
$HOLD = intval($conn->insert_id);
$conn->query("UPDATE ticket_workstreams SET state='on_hold' WHERE ws_id={$wsM}");
$m = SM::run($conn, $CO);
check($m['hold_overdue'] >= 1, 'التعليق المتجاوز مهلته صُعِّد نفسه');
$conn->query("UPDATE ticket_workstreams SET response_due_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE) WHERE ws_id={$wsM}");
$res = SM::resumeFromHold($conn, $HOLD, 9802);
check($res['ok'], 'رفع التعليق يعيد الساعة بإزاحة مدته');
$due = $conn->query("SELECT response_due_at > DATE_ADD(NOW(), INTERVAL 4 HOUR) ok FROM ticket_workstreams WHERE ws_id={$wsM}")->fetch_assoc();
check(intval($due['ok']) === 1, 'المهلة أُزيحت بزمن التعليق (~5 ساعات)');

head('T16 — التكرار: يُعرض الأصل ويُضاف متابع لا بلاغ ثانٍ');
$found = DD::findOpen($conn, $CO, intval($eqType['id']), $SITE, $EQ);
check(count($found) >= 1 && intval($found[0]['id']) === $TK, 'المفتوح بنفس (النوع×المعدة) يُعرض قبل الحفظ');
$before = intval($conn->query("SELECT COUNT(*) c FROM tickets WHERE company_id={$CO}")->fetch_assoc()['c']);
$lnk = DD::linkDuplicate($conn, $TK, 9803);
$after = intval($conn->query("SELECT COUNT(*) c FROM tickets WHERE company_id={$CO}")->fetch_assoc()['c']);
check($lnk['ok'] && $after === $before, '«أتابعه»: متابع أُضيف ولا بلاغ ثانٍ');
$p = $conn->query("SELECT COUNT(*) c FROM ticket_participants WHERE tk_id={$TK} AND person_id=9803 AND role='duplicate_reporter'")->fetch_assoc();
check(intval($p['c']) === 1, 'ومبلغ المكرر لم يُفقد أنه أبلغ');
DD::linkDuplicate($conn, $TK, 9804);
$lnk3 = DD::linkDuplicate($conn, $TK, 9805);
check($lnk3['promoted'] === true, 'الثالث خلال شهر → تُرفع «مشكلة» (RecurrenceThresholdReached)');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
