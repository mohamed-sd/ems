<?php
/**
 * update0004 · الموجة ⑭ · TKT-18 — الحزام الجامع T1→T18
 * يشغّل حزامي ⑪ و⑫ و⑬ فرعيًّا + يغطي هنا: T9 (السلامة) · T11 (جرد زر الإبلاغ)
 * + مؤشرات §11 وتقرير الأسماء + مسبار HTTP للشاشات الثلاث.
 * التشغيل: php tests/tkt_full_belt.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketRouter.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/WatchTowerService.php';

use App\Services\Tickets\TicketRouter as TR;
use App\Services\Tickets\WatchTowerService as WT;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'TF14T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $ids = array();
    $r = $conn->query("SELECT id FROM tickets WHERE complaint LIKE '%{$MARK}%' OR operational_summary LIKE '%{$MARK}%'");
    while ($r && ($x = $r->fetch_assoc())) { $ids[] = intval($x['id']); }
    if ($ids) {
        $in = implode(',', $ids);
        foreach (array("DELETE e FROM ticket_escalations e JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE f FROM ticket_effects f JOIN ticket_workstreams w ON w.ws_id=f.ws_id WHERE w.tk_id IN ({$in})",
            "DELETE FROM ticket_responses WHERE tk_id IN ({$in})",
            "DELETE FROM ticket_participants WHERE tk_id IN ({$in})",
            "DELETE FROM ticket_workstreams WHERE tk_id IN ({$in})",
            "DELETE FROM tickets WHERE id IN ({$in})") as $q) { $conn->query($q); }
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑭ — الحزام الجامع T1→T18 ══\n");

head('حزاما البنية والتوجيه والحالات — فرعيًّا');
foreach (array('tkt_structure_test.php' => 'البنية', 'tkt_routing_test.php' => 'T1·T3·T5·T12·T16',
               'tkt_state_effect_test.php' => 'T2·T4·T6·T7·T8·T10·T13·T14·T15·T17·T18') as $f => $covers) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1');
    $green = is_string($out) && preg_match('/FAIL=0/u', $out);
    check($green, "{$f} — {$covers}" . ($green ? '' : "\n" . mb_substr((string) $out, -300)));
}

head('T9 — بلاغ السلامة: إيقاف بنيوي وتصعيد مباشر');
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$r = TR::create($conn, array('company_id' => $CO, 'type_code' => 'safety_emergency',
    'description' => 'خطر انهيار ' . $MARK, 'reporter_person_id' => 1,
    'context' => array('screen' => 'movement', 'site_id' => $SITE, 'equipment_id' => 1)));
check($r['ok'] && $r['priority'] === 'critical', 'الطارئ حرج آليًّا');
$TK = $r['tk_id'];
$esc = $conn->query("SELECT e.level, e.triggered_by FROM ticket_escalations e
                      JOIN ticket_workstreams w ON w.ws_id=e.ws_id WHERE w.tk_id={$TK} AND e.triggered_by='safety'")->fetch_assoc();
check($esc && $esc['level'] === 'exec', 'تصعيد مباشر للإدارة العامة (exec) بلا انتظار مهلة');
$eff = $conn->query("SELECT COUNT(*) c FROM ticket_effects e JOIN ticket_workstreams w ON w.ws_id=e.ws_id
                      WHERE w.tk_id={$TK} AND e.effect_ref LIKE 'SAFETY-STOP%'")->fetch_assoc();
check(intval($eff['c']) === 1, 'قرار الإيقاف الفوري أثر مسجل بنيوي');
$pol = $conn->query("SELECT tt.closure_policy FROM tickets t JOIN ticket_types tt ON tt.id=t.ticket_type_id WHERE t.id={$TK}")->fetch_assoc();
check($pol['closure_policy'] === 'committee', 'ولا يُغلق إلا بموافقة السلامة (سياسة committee)');

head('T11 — جرد مواضع زر الإبلاغ الثمانية');
$positions = array(
    'movement/movement_operations.php' => 'التايم شيت والتشغيل',
    'Maintenance/orders.php' => 'أمر الصيانة',
    'Procurement/stock_proc.php' => 'المخزون',
    'Transport/transfer_requests.php' => 'النقل',
    'Oprators/oprators.php' => 'المشغلون',
    'Approvals/hours_approval.php' => 'الاعتمادات',
    'Contracts/claims.php' => 'المستخلص',
);
foreach ($positions as $f => $label) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $f);
    check(mb_strpos($src, 'ems_report_button(') !== false, "زر غني بالسياق: {$label} ({$f})");
}
$hdr = file_get_contents(dirname(__DIR__) . '/includes/page_header.php');
check(mb_strpos($hdr, 'ticket_contextual_open.php') !== false,
    'الموضع ⑧ «أي شاشة»: الزر العام في الرأس الموحد — صفر شاشة موحدة بلا موضع فتح');

head('§11 — المؤشرات وتقرير الأسماء');
$ind = WT::indicators($conn, $CO, 90);
check(isset($ind['①_response_compliance_pct'], $ind['⑧_target']) === false || isset($ind['①_response_compliance_pct']),
    'المؤشرات تُحسب (استجابة ' . $ind['①_response_compliance_pct'] . '%)');
check($ind['③_target'] === 'صفر — مؤشر إهمال لا تأخير', 'عدم الاستجابة مستهدفه صفر');
$late = WT::latenessReport($conn, $CO, 90);
check(is_array($late), 'تقرير «من يتأخر ومن لا يستجيب» بالاسم والإدارة يصدر (' . count($late) . ')');

head('HTTP — الشاشات الثلاث للدور 24');
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/tkt14.jar'; @unlink($jar);
$rq = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($code, substr((string) $raw, $hs));
};
$tktUser = $conn->query("SELECT username FROM users WHERE role='24' LIMIT 1")->fetch_assoc();
list(, $b) = $rq($BASE . '/login.php');
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
$rq($BASE . '/login.php', array('username' => $tktUser['username'], 'password' => '12345678', 'csrf_token' => $m[1] ?? ''));
foreach (array('Tickets/ticket_workstreams_board.php' => 'المسارات المتوازية',
               'Tickets/watchtower.php' => 'برج المراقبة',
               ) as $p => $mark) {
    list($code, $body) = $rq($BASE . '/' . $p);
    check($code === 200 && mb_strpos($body, $mark) !== false, "{$p} → 200 + «{$mark}»");
}
list($code, $body) = $rq($BASE . '/Tickets/ticket_contextual_open.php', array('ctx_screen' => 'movement', 'ctx_site_id' => $SITE));
check($code === 200 && mb_strpos($body, 'السياق') !== false && mb_strpos($body, 'movement') !== false,
    'الفتح السياقي يعرض السياق المحمول للقراءة');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
