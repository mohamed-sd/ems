<?php
/**
 * tests/contract_resource_plan_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **175** — خطةُ موارد العقد (P-04 · PLAN-03 §2)
 * من الشاشة الحقيقية بمسؤول المبيعات (12):
 *   ① الشاشةُ تُفتح وتُعلن **قاعدةَ الفصل** نصًّا: لا سعرَ في خطة الموارد
 *   ② والحفظُ بحصصٍ **فوق المائة يُرفض من الخادم** بفائضه
 *   ③ وخطةٌ مكتملةٌ تُكتب من الشاشة ⇒ **الطاقةُ تظهر موزَّعةً** والفجوةُ صفر
 *   ④ و**قيمةُ العقد في شاشة البنود لم تتغيّر** بعد الخطة — البرهانُ الأكبر
 *   ⑤ والإنهاءُ من الشاشة يُبقي الصفَّ بسببه — **لا حذف**
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/contract_resource_plan_http_proof.php   (يتطلب Apache حيًّا)
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

function rp_req($url, $jar, $post = null) {
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
function rp_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = rp_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return rp_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function rp_msg($headers) {
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
$MARK = 'P04H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE p FROM contract_resource_plan p JOIN contracts c ON c.id = p.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE o FROM contract_operational_sites o JOIN contracts c ON c.id = o.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 175 · خطةُ موارد العقد ══\n");

head('البذر — عقدٌ وبندُ طنٍّ 12,000 × 5 = 60,000 USD');
$db->query("INSERT INTO project (company_id, name, client, location, total)
            VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($db->insert_id);
$db->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
            actual_start, actual_end, first_party, second_party, contract_status, project_id, created_at)
            VALUES ({$CO}, '2083-01-01', 365, '2083-01-01', '2083-12-31',
                    'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, NOW())");
$CID = intval($db->insert_id);
$db->query("INSERT INTO client_contract_lines
    (company_id, contract_id, line_no, pricing_model, description, qty_contracted,
     unit_price, currency, valid_from, valid_to, tax_status, state, created_at)
    VALUES ({$CO}, {$CID}, 1, 'ton', 'نقلُ خامٍ — بندُ {$MARK}', 12000,
            5.0000, 'USD', '2083-01-01', '2083-12-31', 'exempt', 'active', NOW())");
$LID = intval($db->insert_id);
$T = $db->query("SELECT id, type FROM equipments_types WHERE status='active' ORDER BY type LIMIT 2");
$tids = array(); $tnames = array();
while ($x = $T->fetch_assoc()) { $tids[] = intval($x['id']); $tnames[] = $x['type']; }
check($CID > 0 && $LID > 0 && count($tids) >= 2,
      "عقدٌ #{$CID} · بندٌ #{$LID} · نوعان: " . implode(' · ', $tnames));

$jar = $TMP . "/rp_{$MARK}.txt";
rp_login('مبيعات', $jar);
list($c, $h, $b) = rp_req($BASE . '/Contracts/contract_resource_plan.php?line=' . $LID, $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';

head('① الشاشةُ تُفتح و**قاعدةُ الفصل معلَنةٌ نصًّا**');
check($c === 200 && mb_strpos($b, 'خطة موارد العقد') !== false, 'الشاشةُ 175 تُفتح بالدور 12 (200)');
check(mb_strpos($b, 'تُغذّي الحاويات ولا تدخل القيمة') !== false,
      '★ والقاعدةُ مكتوبةٌ على الشاشة — لا في التوثيق وحدَه');
check(mb_strpos($b, 'بندُ ' . $MARK) !== false, 'والبندُ المبذورُ ظاهرٌ بخطته');
check(mb_strpos($b, 'name="share[') !== false && mb_strpos($b, 'name="price[') === false,
      '★★ وفي النموذج **خانةُ حصةٍ ولا خانةَ سعرٍ واحدة**');

head('② والحفظُ **فوق المائة يُرفض من الخادم**');
list($c, $h, $b2) = rp_req($BASE . '/Contracts/contract_resource_plan.php', $jar, array(
    'rp_action' => 'save', 'line_id' => $LID, 'valid_from' => '2083-01-01',
    'share' => array($tids[0] => '70', $tids[1] => '45'),
    'basic' => array($tids[0] => '3', $tids[1] => '2'),
    'csrf_token' => $TOK));
$msg = rp_msg($h);
check(mb_strpos($msg, '409') !== false && mb_strpos($msg, 'تتجاوز المائة') !== false, '★★ ' . $msg);
$n = intval($db->query("SELECT COUNT(*) c FROM contract_resource_plan WHERE line_id={$LID}")
                 ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ صفٍّ كُتب — **الفحصُ قبل الكتابة**');

head('③ وخطةٌ مكتملةٌ تُكتب من الشاشة');
list($c, $h, $b2) = rp_req($BASE . '/Contracts/contract_resource_plan.php', $jar, array(
    'rp_action' => 'save', 'line_id' => $LID, 'valid_from' => '2083-01-01',
    'share' => array($tids[0] => '60', $tids[1] => '40'),
    'basic' => array($tids[0] => '6', $tids[1] => '10'),
    'backup' => array($tids[0] => '2', $tids[1] => '3'),
    'shifts' => array($tids[0] => '2', $tids[1] => '2'),
    'hours' => array($tids[0] => '10', $tids[1] => '10'),
    'ops' => array($tids[0] => '12', $tids[1] => '20'),
    'csrf_token' => $TOK));
$msg = rp_msg($h);
check(mb_strpos($msg, 'مكتملة') !== false && mb_strpos($msg, '100') !== false, '★★ ' . $msg);

list($c, $h, $b) = rp_req($BASE . '/Contracts/contract_resource_plan.php?line=' . $LID, $jar);
check(mb_strpos($b, '7200') !== false && mb_strpos($b, '4800') !== false,
      '★★ والشاشةُ تُري الطاقةَ **7200 و4800** — لا نصفين متساويين');
check(mb_strpos($b, 'الفجوة 0%') !== false, 'والفجوةُ **صفرٌ بالمائة** ظاهرةً');
check(mb_strpos($b, '32 مشغّلًا') !== false, 'وطلبُ العمالة 32 مشغّلًا — **طلبٌ لا استحقاق**');

head('④ ★ **وقيمةُ العقد لم تتغيّر** — البرهانُ الأكبر');
list($c, $h, $bl) = rp_req($BASE . '/Contracts/contract_lines.php?contract=' . $CID, $jar);
check($c === 200 && (mb_strpos($bl, '60,000') !== false || mb_strpos($bl, '60000') !== false),
      '★★★ شاشةُ البنود ما زالت تقول **60,000 USD** بعد خطةِ موارد كاملة');
$q = $db->query("SELECT resource_share_total, qty_contracted, unit_price
                   FROM client_contract_lines WHERE id={$LID}")->fetch_assoc();
check(abs((float) $q['resource_share_total'] - 100.0) < 0.0005
      && abs((float) $q['qty_contracted'] * (float) $q['unit_price'] - 60000.0) < 0.005,
      'والصفُّ يحمل Σ الحصص 100% **والقيمةَ 60,000 كما هي**');

head('⑤ والإنهاءُ من الشاشة **يُبقي الصفَّ بسببه**');
$RID = intval($db->query("SELECT id FROM contract_resource_plan
                           WHERE line_id={$LID} AND state='active' ORDER BY id LIMIT 1")
                  ->fetch_assoc()['id']);
list($c, $h, $b2) = rp_req($BASE . '/Contracts/contract_resource_plan.php', $jar, array(
    'rp_action' => 'end_row', 'line_id' => $LID, 'row_id' => $RID,
    'end_reason' => 'خرج النوعُ من نطاق العمل — ' . $MARK, 'csrf_token' => $TOK));
check(mb_strpos(rp_msg($h), 'أُنهي الصفُّ') !== false, '★ ' . rp_msg($h));
$q = $db->query("SELECT state, end_reason FROM contract_resource_plan WHERE id={$RID}")->fetch_assoc();
check($q && $q['state'] === 'ended' && mb_strpos((string) $q['end_reason'], $MARK) !== false,
      '★★ والصفُّ **باقٍ** بحالِ `ended` وسببِه المكتوب — **لا حذف**');
list($c, $h, $b) = rp_req($BASE . '/Contracts/contract_resource_plan.php?line=' . $LID, $jar);
check(mb_strpos($b, 'المنتهيةُ تبقى للتاريخ') !== false && mb_strpos($b, 'خرج النوعُ من نطاق العمل') !== false,
      'وسجلُّ الخطة يعرضه بسببه — «المنتهيةُ تبقى للتاريخ»');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
