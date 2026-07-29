<?php
/**
 * tests/settlements_screen_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لدورة تسوية المورد (UX-05 §5.2 · UX-02 §15.3/§15.4).
 * من **الشاشتين الحقيقيتين** بدورَين مختلفين:
 *   ① إدارةُ الموردين (2) تفتح وتولّد — ولا ترى زرَّ الإجازة
 *   ② البنودُ تظهر بروابط أصولها وبصفرِ إدخالِ مبلغ
 *   ③ اعتراضٌ من الشاشة بسببه — والتسويةُ لا تتجمد
 *   ④ مديرُ الإدارة المالية (19) يرى الإجازة ولا يرى نموذجَ التوليد (فصلُ اليدين)
 *   ⑤ الإجازةُ تُمنع ما دام الاعتراضُ مفتوحًا، وتمضي بعد حسمه
 *   ⑥ الصافي السالب يفتح ذمّةً مدينةً على المورد
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/settlements_screen_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function sq_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function sq_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = sq_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return sq_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function sq_tok($body) {
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $body, $m);
    return isset($m[1]) ? $m[1] : '';
}
function sq_msg($headers) {
    if (preg_match('~Location:\s*(\S+)~i', $headers, $m)) {
        $q = parse_url(trim($m[1]), PHP_URL_QUERY);
        parse_str((string) $q, $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO   = 4;
$MARK = 'SQP' . getmypid();
$PER  = '2093-05';
$FROM = '2093-05-01';
$TO   = '2093-05-31';
$SCR  = $BASE . '/Suppliers/settlements.php';

$cleanup = function () use ($db, $MARK) {
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE be.source_ref LIKE 'STL-%'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                   WHERE s.settlement_no = be.source_ref)";
    // طلبُ الدفع قبل تسويته (`fk_req_settlement` بـRESTRICT)
    $db->query("DELETE r FROM fin_requests r JOIN settlements s ON s.id = r.settlement_id
                 WHERE s.party_name LIKE '%{$MARK}%'");
    $db->query("DELETE sl FROM settlement_lines sl JOIN settlements s ON s.id=sl.settlement_id
                 WHERE s.party_name LIKE '%{$MARK}%'");
    $db->query("DELETE FROM settlements WHERE party_name LIKE '%{$MARK}%'");
    $db->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $db->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
    $db->query("DELETE FROM fin_dues WHERE period_ref = '2093-05'");
    $db->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
$cleanup();

// مورّدٌ صافيه سالبٌ عمدًا: استحقاقٌ 400 وتحميلُ وقودٍ 1000 (بالدولار — له سعر)
$db->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'موردُ {$MARK}', NOW())");
$SUP = $db->insert_id;
// M-11: الخصمُ يلزمه مصدرٌ (قيدُ `ck_dues_debit_source` في القاعدة) — والوقودُ
// يُبذر بسند صرفٍ كما يقع في الواقع (قرارُ المالك: «لا جدولَ وقودٍ لكن نعم جدولُ صرف»).
$mk = function ($dir, $type, $amt) use ($db, $CO, $SUP, $PER) {
    $src = ($dir === 'debit') ? 'proc_issue' : null;
    $sid = ($dir === 'debit') ? 1 : null;
    $st = $db->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                        amount, currency, period_ref, source_doc_type, source_doc_id, created_by, created_at)
                        VALUES (?, 'supplier', ?, ?, ?, ?, 'USD', ?, ?, ?, 1, NOW())");
    $ref = (string) $SUP;
    $st->bind_param('isssdssi', $CO, $ref, $type, $dir, $amt, $PER, $src, $sid);
    $st->execute(); $st->close();
};
$mk('credit', 'hours',  400.00);
$mk('debit',  'fuel',  1000.00);

$jarSup = $TMP . "/sq_sup_{$MARK}.txt";
$jarFin = $TMP . "/sq_fin_{$MARK}.txt";

// ═══════════════════════════════════════════════════════════════════════════
head('① إدارةُ الموردين تُعدّ — ولا تُجيز');

sq_login('مصعب', $jarSup);                       // دور 2
list($c, $h, $b) = sq_req($SCR, $jarSup);
check($c === 200, "الشاشةُ ترد 200 (وُجد {$c})");
check(strpos($b, 'ولّد التسوية') !== false, 'ويرى نموذجَ التوليد');
$tok = sq_tok($b);
check($tok !== '', 'ورمزُ الحماية مضمَّن');

list($c, $h, $b) = sq_req($SCR, $jarSup, array(
    'generate' => '1', 'csrf_token' => $tok,
    'party_ref' => (string) $SUP, 'period_from' => $FROM, 'period_to' => $TO,
));
$msg = sq_msg($h);
check(strpos($msg, 'تولّدت التسوية') !== false, "التسويةُ تولّدت من الشاشة ({$msg})");
check(strpos($msg, '1 استحقاقًا') !== false && strpos($msg, '1 تحميلًا') !== false,
    '★ والرسالةُ تقول ماذا جُلب — استحقاقٌ وتحميل');

$r = $db->query("SELECT * FROM settlements WHERE party_name LIKE '%{$MARK}%' LIMIT 1");
$S = $r->fetch_assoc();
check($S !== null, 'والتسويةُ في القاعدة');
$SID = $S ? intval($S['id']) : 0;
check($S && abs((float) $S['net_amount'] + 600.00) < 0.005,
    '★ الصافي 400 − 1000 = −600 (وُجد ' . ($S ? $S['net_amount'] : '—') . ')');
check($S && (string) $S['net_direction'] === 'receivable', 'واتجاهُه «دَينٌ على المورد»');

// ═══════════════════════════════════════════════════════════════════════════
head('② البنودُ بروابط أصولها');

list($c, $h, $b) = sq_req($SCR . '?open=' . $SID, $jarSup);
check(strpos($b, 'بنودُ التسوية') !== false, 'صفحةُ البنود تُفتح');
check(strpos($b, 'due#') !== false, '★ وكلُّ بندٍ يعرض مصدرَه ومعرّفَه (due#…)');
check(strpos($b, 'استحقاق') !== false && strpos($b, 'تحميل') !== false,
    'ويميّز الاستحقاقَ من التحميل');

// ═══════════════════════════════════════════════════════════════════════════
head('③ الاعتراض من الشاشة — والتسويةُ لا تتجمد');

$r = $db->query("SELECT id FROM settlement_lines WHERE settlement_id={$SID} AND line_kind='charge' LIMIT 1");
$LINE = intval($r->fetch_assoc()['id']);

$tok = sq_tok($b);
list($c, $h, $b) = sq_req($SCR, $jarSup, array(
    'action' => 'object', 'csrf_token' => $tok, 'sid' => (string) $SID,
    'line_id' => (string) $LINE, 'note' => 'الوقودُ ليس لمعداتي',
));
check(strpos(sq_msg($h), '✅') !== false, 'الاعتراضُ سُجّل');
$r = $db->query("SELECT state, open_objections FROM settlements WHERE id={$SID}");
$row = $r->fetch_assoc();
check(intval($row['open_objections']) === 1, 'وعدّادُ الاعتراضات 1');
check((string) $row['state'] !== 'cancelled', '★ والتسويةُ لم تتجمد');

// ═══════════════════════════════════════════════════════════════════════════
head('④ فصلُ اليدين: الموردون لا يُجيزون · والمالية لا تُولّد');

// رفعٌ للمراجعة أولًا
list($c, $h, $b) = sq_req($SCR . '?open=' . $SID, $jarSup);
$tok = sq_tok($b);
sq_req($SCR, $jarSup, array('action' => 'submit', 'csrf_token' => $tok, 'sid' => (string) $SID));

list($c, $h, $b) = sq_req($SCR, $jarSup);
check(strpos($b, '>إجازة<') === false, '★ إدارةُ الموردين لا ترى زرَّ الإجازة');

sq_login('fin.deptmgr@equipation.sd', $jarFin);   // دور 19
list($c, $h, $b) = sq_req($SCR, $jarFin);
check($c === 200, 'ومديرُ المالية يفتح الشاشة');
check(strpos($b, 'ولّد التسوية') === false, '★ ولا يرى نموذجَ التوليد — لا يُعدّ ما يُجيز');
check(strpos($b, '>إجازة<') !== false, 'ويرى زرَّ الإجازة');

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ الإجازةُ تُمنع باعتراضٍ مفتوح وتمضي بعد حسمه');

$tok = sq_tok($b);
list($c, $h, $b2) = sq_req($SCR, $jarFin, array(
    'action' => 'approve', 'csrf_token' => $tok, 'sid' => (string) $SID,
));
$msg = sq_msg($h);
check(strpos($msg, 'معترَضًا') !== false, "★ الإجازةُ تُمنع بسببها المعلَن ({$msg})");

// حسمُ الاعتراض من إدارة الموردين
list($c, $h, $b) = sq_req($SCR . '?open=' . $SID, $jarSup);
$tok = sq_tok($b);
sq_req($SCR, $jarSup, array('action' => 'resolve', 'csrf_token' => $tok,
                            'sid' => (string) $SID, 'line_id' => (string) $LINE));

list($c, $h, $b) = sq_req($SCR, $jarFin);
$tok = sq_tok($b);
list($c, $h, $b2) = sq_req($SCR, $jarFin, array(
    'action' => 'approve', 'csrf_token' => $tok, 'sid' => (string) $SID,
));
$msg = sq_msg($h);
check(strpos($msg, '✅') !== false, "وبعد الحسم تمضي ({$msg})");

$r = $db->query("SELECT state, receivable_due_id FROM settlements WHERE id={$SID}");
$row = $r->fetch_assoc();
check((string) $row['state'] === 'approved', 'والحالةُ معتمدة');

// ═══════════════════════════════════════════════════════════════════════════
head('⑥ الصافي السالب فتح ذمّةً مدينةً على المورد');

check(!empty($row['receivable_due_id']), '★ وذمّةٌ مدينةٌ فُتحت — الدَّينُ لا يُنسى');
if (!empty($row['receivable_due_id'])) {
    $r = $db->query("SELECT direction, amount FROM fin_dues WHERE id=" . intval($row['receivable_due_id']));
    $d = $r->fetch_assoc();
    check($d && (string) $d['direction'] === 'debit' && abs((float) $d['amount'] - 600.00) < 0.005,
        'بقيمة 600 باتجاه مدين');
}
$r = $db->query("SELECT COUNT(*) c FROM ems_business_events WHERE idempotency_key='settlement:{$SID}'");
check(intval($r->fetch_assoc()['c']) === 1, 'وحدثُ FES منشورٌ مرةً واحدة');

$r = $db->query("SELECT payment_request_id FROM settlements WHERE id={$SID}");
check(empty($r->fetch_assoc()['payment_request_id']),
    '★ ولا طلبَ دفعٍ — لا يُصرف لمن يدين لك');
list($c, $h, $b) = sq_req($SCR, $jarFin);
check(strpos($b, 'لا دفعَ — دَينٌ عليه') !== false,
    'والشاشةُ تقول ذلك صراحةً في عمود طلب الدفع');

// ═══════════════════════════════════════════════════════════════════════════
head('⑥-ب الصافي الموجب يولّد طلبَ دفعٍ برابطه');

$db->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'دائنُ {$MARK}', NOW())");
$SUP2 = $db->insert_id;
$st = $db->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                    amount, currency, period_ref, created_by, created_at)
                    VALUES (?, 'supplier', ?, 'hours', 'credit', 900.00, 'USD', ?, 1, NOW())");
$ref2 = (string) $SUP2;
$st->bind_param('iss', $CO, $ref2, $PER); $st->execute(); $st->close();

list($c, $h, $b) = sq_req($SCR, $jarSup);
$tok = sq_tok($b);
sq_req($SCR, $jarSup, array('generate' => '1', 'csrf_token' => $tok,
    'party_ref' => (string) $SUP2, 'period_from' => $FROM, 'period_to' => $TO));
$r = $db->query("SELECT id FROM settlements WHERE party_name LIKE '%دائنُ {$MARK}%' LIMIT 1");
$SID2 = intval($r->fetch_assoc()['id']);
list($c, $h, $b) = sq_req($SCR . '?open=' . $SID2, $jarSup);
$tok = sq_tok($b);
sq_req($SCR, $jarSup, array('action' => 'submit', 'csrf_token' => $tok, 'sid' => (string) $SID2));

list($c, $h, $b) = sq_req($SCR, $jarFin);
$tok = sq_tok($b);
list($c, $h, $b2) = sq_req($SCR, $jarFin, array('action' => 'approve',
    'csrf_token' => $tok, 'sid' => (string) $SID2));
check(strpos(sq_msg($h), '✅') !== false, 'التسويةُ الموجبةُ أُجيزت');

$r = $db->query("SELECT s.payment_request_id, s.state, r.request_no, r.amount, r.state AS rstate,
                        r.beneficiary_ref, r.request_type
                   FROM settlements s LEFT JOIN fin_requests r ON r.id = s.payment_request_id
                  WHERE s.id = {$SID2}");
$row = $r->fetch_assoc();
check(!empty($row['payment_request_id']), '★ وتولّد طلبُ دفعٍ آليًّا');
check($row && (string) $row['state'] === 'payment_requested', 'وحالةُ التسوية «طُلب الدفع»');
check($row && abs((float) $row['amount'] - 900.00) < 0.005,
    '★ ومبلغُه 900 منسوخٌ من الصافي — لا يُكتب بيدٍ ثانية');
check($row && intval($row['beneficiary_ref']) === $SUP2, 'والمستفيدُ هو المورد');
check($row && (string) $row['rstate'] === 'approved',
    '★ ويدخل معتمَدًا جاهزًا للصرف — لا اعتمادَ مكرَّر');

list($c, $h, $b) = sq_req($SCR, $jarFin);
check(strpos($b, 'request_form.php?id=' . intval($row['payment_request_id'])) !== false,
    '★ والشاشةُ تعرض رابطَه — نقرةٌ واحدةٌ إلى رحلته');

// ═══════════════════════════════════════════════════════════════════════════
head('⑦ الكنس');

$cleanup();
@unlink($jarSup); @unlink($jarFin);
$r = $db->query("SELECT COUNT(*) c FROM settlements WHERE party_name LIKE '%{$MARK}%'");
check(intval($r->fetch_assoc()['c']) === 0, 'لا تسويةَ برهانٍ باقية');
// ⚠️ «يتيمٌ» = حدثٌ بلا تسويةٍ قائمةٍ تحمل رقمَه — بنفس تعريف الكنس أعلاه.
// (كان الشرطُ `source_ref LIKE 'STL-%'` وحده فيَعُدّ أحداثَ التسويات **الحقيقية**
//  الباقية بحقٍّ، فيحمرّ البرهانُ كذبًا أولَ ما تُعتمد تسويةٌ فعلية — وقع 2026-07-28.)
$r = $db->query("SELECT COUNT(*) c FROM ems_business_events be
                  WHERE be.source_ref LIKE 'STL-%'
                    AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                     WHERE s.settlement_no = be.source_ref)");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا حدثَ يتيمٌ في الدفتر');

fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
