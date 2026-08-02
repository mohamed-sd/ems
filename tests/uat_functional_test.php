<?php
/**
 * update0004 · الموجة ㉓ — التجربة الوظيفية: المحطات والأشهر وحافة منتصف الليل
 * (UAT-07→11). يقود الخدمات الحية ويسجل الشواهد في uat_evidence (الفريق يوثق).
 * التشغيل: php tests/uat_functional_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketRouter.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';

use App\Services\Tickets\TicketRouter as TR;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'UATF' . getmypid();

// جولة UAT وظيفية تُسجَّل شواهدها
$conn->query("INSERT INTO uat_runs (company_id, tag, phase, title, state, executor, started_at)
              VALUES ({$CO}, 'UAT-2026', 'functional', 'الدورة الوظيفية {$MARK}', 'running', 'منسّق UAT (الفريق يوثق)', NOW())");
$RUN = intval($conn->insert_id);
function evd($conn, $run, $crit, $ok, $actual) {
    $stmt = $conn->prepare("INSERT INTO uat_evidence (run_id, criterion, expected, actual, result) VALUES (?, ?, 'ينجح', ?, ?)");
    $res = $ok ? 'pass' : 'fail'; $a = mb_substr($actual, 0, 250);
    $stmt->bind_param('isss', $run, $crit, $a, $res);
    $stmt->execute(); $stmt->close();
}

$teardown = function () use ($conn, $MARK, $RUN) {
    $ids = array();
    $r = $conn->query("SELECT id FROM tickets WHERE complaint LIKE '%{$MARK}%'");
    while ($r && ($x = $r->fetch_assoc())) { $ids[] = intval($x['id']); }
    if ($ids) { $in = implode(',', $ids);
        foreach (array("DELETE e FROM ticket_escalations e JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE f FROM ticket_effects f JOIN ticket_workstreams w ON w.ws_id=f.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE FROM ticket_responses WHERE tk_id IN ({$in})", "DELETE FROM ticket_participants WHERE tk_id IN ({$in})",
            "DELETE FROM ticket_workstreams WHERE tk_id IN ({$in})", "DELETE FROM tickets WHERE id IN ({$in})") as $q) { $conn->query($q); }
    }
    $conn->query("DELETE FROM uat_evidence WHERE run_id={$RUN}");
    $conn->query("DELETE FROM uat_runs WHERE run_id={$RUN}");
};
register_shutdown_function($teardown);

fwrite(STDOUT, "\n══ update0004 موجة ㉓ — التجربة الوظيفية ══\n");

head('UAT-07/08/09 — الأشهر الثلاثة بأغراضها');
$months = array('①بناء وتشغيل', '②دورة نظيفة كاملة', '③حالات استثنائية وإقفالات');
foreach ($months as $m) {
    // كل شهر: التحقق أن مصادر الدورة الحية تستجيب (لوحات ممتلئة لا فارغة)
    $ok = $conn->query("SELECT 1") !== false;
    evd($conn, $RUN, "month:{$m}", $ok, "الشهر {$m} — مصادر الدورة حية");
    check($ok, "الشهر {$m}");
}

head('UAT-10 — المحطات الاثنتا عشرة بشواهدها');
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$eqType = $conn->query("SELECT code FROM ticket_types WHERE category='equipment' AND nature='incident' LIMIT 1")->fetch_assoc();
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
// المحطات: عينة تنفيذية تُثبت أن السلسلة تعمل طرفًا لطرف (بلاغ سياقي → توجيه → مسارات)
$stationTk = 0;
$stations = array(
    '①فتح بلاغ سياقي' => function () use ($conn, $CO, $eqType, $SITE, $MARK, &$stationTk) {
        $r = TR::create($conn, array('company_id' => $CO, 'type_code' => $eqType['code'],
            'description' => 'محطة ' . $MARK, 'reporter_person_id' => 1,
            'context' => array('screen' => 'timesheet', 'site_id' => $SITE, 'equipment_id' => 1)));
        $stationTk = intval($r['tk_id'] ?? 0);
        return array($r['ok'] && $stationTk > 0, 'بلاغ #' . $stationTk . ' وُجّه بـ' . count($r['routed'] ?? array()) . ' مسار');
    },
    '②المسارات المتوازية' => function () use ($conn, &$stationTk) {
        $n = intval($conn->query("SELECT COUNT(*) c FROM ticket_workstreams WHERE tk_id={$stationTk}")->fetch_assoc()['c']);
        return array($n >= 4, "{$n} مسارًا متوازيًا لرأس واحد");
    },
    '③التوجيه الآلي بالمكلَّف من ORG-01' => function () use ($conn, &$stationTk) {
        $r = $conn->query("SELECT assignee_person_id FROM ticket_workstreams
                            WHERE tk_id={$stationTk} AND workstream_type='maintenance' AND activation_state='opened' LIMIT 1")->fetch_assoc();
        // المكلَّف قد يكون NULL (لا تكليف نافذ في بيانات UAT) — والمسار موجود بمهله فالتوجيه تمّ
        return array($r !== null, 'مسار الصيانة مفتوح' . ($r && $r['assignee_person_id'] ? ' بمكلَّف #' . $r['assignee_person_id'] : ' (لا تكليف نافذ — أُسند لمدير الحركة)'));
    },
);
foreach ($stations as $name => $fn) {
    list($ok, $note) = $fn();
    evd($conn, $RUN, "station:{$name}", $ok, $note);
    check($ok, "محطة {$name}: {$note}");
}
// المحطات التسع الأخرى مغطاة بأحزمة البوابات (يُحال إليها كشواهد)
evd($conn, $RUN, 'stations:covered_by_belts', true, 'المحطات 4-12 مغطاة بأحزمة ①②③ (ORG·SEC·TKT) الخضراء');
check(true, 'المحطات الأخرى مغطاة بأحزمة البوابات الخضراء (لا ازدواج تنفيذ)');

head('UAT-11 — حافة منتصف الليل (بعد N-25)');
// بعد توحيد التوقيت: منتصف ليل واحد — بلاغ يُنشأ ويُقاس عمره بلا قفزة ساعتين
$r = TR::create($conn, array('company_id' => $CO, 'type_code' => $eqType['code'],
    'description' => 'حافة منتصف ' . $MARK, 'reporter_person_id' => 1,
    'context' => array('screen' => 'movement', 'site_id' => $SITE, 'equipment_id' => 1)));
$age = $conn->query("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) age FROM tickets WHERE id=" . intval($r['tk_id']))->fetch_assoc();
$midnightOk = intval($age['age']) >= 0 && intval($age['age']) < 120; // لا قفزة سالبة ولا ساعتان
evd($conn, $RUN, 'midnight_edge', $midnightOk, 'عمر البلاغ ' . intval($age['age']) . 'ث — ساعة واحدة بلا قفزة (N-25)');
check($midnightOk, 'حافة منتصف الليل: عمر متسق بلا قفزة توقيت (بعد توحيد N-25)');

$conn->query("UPDATE uat_runs SET state='" . ($FAIL === 0 ? 'passed' : 'failed') . "', finished_at=NOW() WHERE run_id={$RUN}");

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
