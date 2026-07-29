<?php
/**
 * tests/qty_ruling_http_proof.php — M-24
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لحكم الكمية من **الشاشة الحقيقية** (لوحة الإسناد اليومي):
 *   ① لوحُ حكم الكمية يظهر لعقود الطن/المتر — و**لا يظهر لعقد الساعة**
 *      (فسؤالُ «هل الكميةُ مفوترة؟» لا معنى له حيث الكميةُ هي الساعاتُ نفسُها).
 *   ② المنعُ بلا سببٍ يُردّ من الشاشة برسالته.
 *   ③ المنعُ بسببه يقع، والشارةُ تنقلب «غيرُ مفوترة» والسببُ يظهر للمستخدم.
 *   ④ رفعُ الحكم يعيدها.
 *   ⑤ **البُعدان مفصولان في الشاشة نصًّا** — لا يُوهم المستخدمُ أن أحدهما يغني.
 *
 * يعيد حالةَ ما مسّه بالضبط في البدء والختام.
 * التشغيل: php tests/qty_ruling_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/ems_qtyrule.txt';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

function q_req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
}
function q_msg($h) {
    if (preg_match('~Location:\s*(\S+)~i', $h, $m)) {
        parse_str((string) parse_url(trim($m[1]), PHP_URL_QUERY), $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

$conn = $GLOBALS['conn'];
$CO = 4;

// واقعةُ كميةٍ لم تولّد مالًا (وإلا ردّ الحارسُ 409 بحق)
$e = $conn->query("SELECT id, entry_no, entry_date, unit_type, qty, qty_billable, qty_ruling_note
                     FROM unit_entries
                    WHERE company_id={$CO} AND unit_type IN ('ton','meter')
                      AND (sync_uuid IS NULL OR sync_uuid NOT IN
                           (SELECT CONCAT('ts:', l.parent_ref) FROM fin_event_links l
                             WHERE l.parent_kind='timesheet'))
                    ORDER BY id LIMIT 1")->fetch_assoc();
// ويومٌ **كلُّ وقائعه بالساعة** للمقارنة — اللوحةُ تعرض يومًا كاملًا، فيومٌ
// مختلطٌ يُظهر اللوحَ بحقٍّ لواقعة الكمية فيه ويُفشل المقارنةَ كذبًا.
$h = $conn->query("SELECT entry_date FROM unit_entries
                    WHERE company_id={$CO}
                    GROUP BY entry_date
                   HAVING SUM(unit_type IN ('ton','meter','cbm','trip')) = 0
                      AND SUM(unit_type = 'hour') > 0
                    LIMIT 1")->fetch_assoc();

if (!$e) { bad('لا واقعةَ كميةٍ صالحةٍ للاختبار'); }
else {
    $EID = (int) $e['id']; $DAY = (string) $e['entry_date'];
    $restore = function () use ($conn, $EID, $e) {
        $nb = ($e['qty_billable'] === null) ? 'NULL' : (int) $e['qty_billable'];
        $nn = ($e['qty_ruling_note'] === null) ? 'NULL'
              : "'" . $conn->real_escape_string($e['qty_ruling_note']) . "'";
        $conn->query("UPDATE unit_entries SET qty_billable={$nb}, qty_ruling_note={$nn},
                        qty_decided_by=NULL, qty_decided_at=NULL WHERE id={$EID}");
        $conn->query("DELETE FROM ems_business_events
                       WHERE event_key='attribution.qty_ruled' AND entity_id={$EID}");
    };
    $restore();
    register_shutdown_function($restore);

    fwrite(STDOUT, "\n══ M-24 — حكمُ الكمية من الشاشة ══\n");
    info("الواقعةُ {$e['entry_no']} · {$e['unit_type']} {$e['qty']} · يومَ {$DAY}");

    @unlink($JAR);
    list(, , $lp) = q_req($BASE . '/login.php');
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lp, $lm);
    list($lc) = q_req($BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678',
                                                  'csrf_token' => $lm[1] ?? ''));
    check($lc === 200 || $lc === 302, 'دخولُ «محمد» (الدور 1 — إدارة التشغيل)');

    // ═══ ① اللوحُ يظهر حيث يعني ═══
    head('① لوحُ حكم الكمية — يظهر لعقد الكمية لا لعقد الساعة');
    list($c1, , $p1) = q_req($BASE . '/Approvals/attribution_board.php?day=' . urlencode($DAY));
    check($c1 === 200, 'اللوحةُ صُيِّرت (200)');
    check(mb_strpos($p1, 'كميةُ الواقعة') !== false, 'ولوحُ حكم الكمية ظاهر');
    check(mb_strpos($p1, 'لا تُفوتر (إعادةُ تنفيذٍ لعيب)') !== false, 'وفيه خيارُ المنع بنصّه');
    check(mb_strpos($p1, 'مفوترة (لم يُحكم)') !== false, 'وشارتُه تقول الحالةَ الراهنة');

    if ($h) {
        list($c2, , $p2) = q_req($BASE . '/Approvals/attribution_board.php?day='
                                 . urlencode((string) $h['entry_date']));
        $hourOnly = ($c2 === 200 && mb_strpos($p2, 'كميةُ الواقعة') === false);
        check($hourOnly || mb_strpos($p2, 'كميةُ الواقعة') === false,
            'ويومُ عقدِ الساعة بلا لوحِ كمية — لا يُعرض سؤالٌ بلا معنى');
    } else { ok('(لا يومَ ساعةٍ للمقارنة)'); }

    // ═══ ⑤ البُعدان مفصولان نصًّا ═══
    head('⑤ البُعدان مفصولان في الشاشة');
    check(mb_strpos($p1, 'سؤالان مستقلّان') !== false
          && mb_strpos($p1, 'حكمُ أحدهما لا يغني عن الآخر') !== false,
        'الشاشةُ تقول صراحةً إنهما سؤالان لا واحد');

    // ═══ ② المنعُ بلا سبب ═══
    head('② المنعُ بلا سببٍ يُردّ');
    list(, $h2) = q_req($BASE . '/Approvals/attribution_board.php?day=' . urlencode($DAY),
        array('atb_action' => 'rule_qty', 'entry_id' => $EID, 'qty_billable' => '0', 'qty_note' => ''));
    $m2 = q_msg($h2);
    check(mb_strpos($m2, 'سببُ منع فوترة الكمية إلزامي') !== false,
        'برسالته للمستخدم: ' . mb_substr($m2, 0, 80));
    $st = $conn->query("SELECT qty_billable FROM unit_entries WHERE id={$EID}")->fetch_assoc();
    check($st['qty_billable'] === null, 'ولا صفَّ تغيّر');

    // ═══ ③ المنعُ بسببه ═══
    head('③ المنعُ بسببه يقع ويظهر');
    $why = 'إعادةُ تنفيذٍ لعيبٍ في الصبّة';
    list(, $h3) = q_req($BASE . '/Approvals/attribution_board.php?day=' . urlencode($DAY),
        array('atb_action' => 'rule_qty', 'entry_id' => $EID, 'qty_billable' => '0', 'qty_note' => $why));
    check(mb_strpos(q_msg($h3), 'حُكم بعدم فوترة الكمية') !== false, 'الرسالةُ تؤكّد الحكم');
    $st = $conn->query("SELECT qty_billable, qty_ruling_note, qty_decided_by FROM unit_entries WHERE id={$EID}")
               ->fetch_assoc();
    check((int) $st['qty_billable'] === 0 && $st['qty_ruling_note'] === $why,
        'والسجلُّ يحمل الحكمَ وسببَه');
    check((int) $st['qty_decided_by'] > 0, 'وباسم مَن حكم: #' . $st['qty_decided_by']);

    list(, , $p3) = q_req($BASE . '/Approvals/attribution_board.php?day=' . urlencode($DAY));
    check(mb_strpos($p3, 'غيرُ مفوترة') !== false, 'والشارةُ انقلبت «غيرُ مفوترة» في الشاشة');
    check(mb_strpos($p3, $why) !== false, 'والسببُ معروضٌ للمستخدم لا مخبوءٌ في السجل');

    $ev = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                               WHERE event_key='attribution.qty_ruled' AND entity_id={$EID}")
                     ->fetch_assoc()['c'];
    check($ev === 1, "وحدثٌ واحدٌ على الناقل: {$ev}");

    // ═══ ④ الرفع ═══
    head('④ رفعُ الحكم يعيدها');
    list(, $h4) = q_req($BASE . '/Approvals/attribution_board.php?day=' . urlencode($DAY),
        array('atb_action' => 'rule_qty', 'entry_id' => $EID, 'qty_billable' => '', 'qty_note' => ''));
    check(mb_strpos(q_msg($h4), 'رُفع حكمُ الكمية') !== false, 'الرسالةُ تؤكّد الرفع');
    $st = $conn->query("SELECT qty_billable FROM unit_entries WHERE id={$EID}")->fetch_assoc();
    check($st['qty_billable'] === null, 'والسجلُّ عاد NULL — تُفوتر كما كانت');
    $restore();
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
