<?php
/**
 * tests/contract_guarantees_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **177** — ضمانات العقد (P-06 · PLAN-03 §3.1 · §9-⑯)
 * من الشاشة الحقيقية بمسؤول المبيعات (12):
 *   ① الشاشةُ تُفتح و**قاعدةُ الفصل معلَنةٌ نصًّا** والرقمان في **عمودين**
 *   ② وخطابُ ضمانٍ يُسجَّل من الشاشة ⇒ **يظهر خارجَ الميزانية لا أصلًا**
 *   ③ ومحتجزٌ **بلا تاريخِ ردٍّ يرفضه الخادم**
 *   ④ والإفراجُ من الشاشة **يُبقي الصفَّ بسببه**
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/contract_guarantees_http_proof.php   (يتطلب Apache حيًّا)
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

function cg_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
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
function cg_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = cg_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return cg_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function cg_msg($headers) {
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
$MARK = 'P06H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE g FROM contract_guarantees g JOIN contracts c ON c.id = g.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 177 · ضمانات العقد ══\n");

head('البذر — عقدٌ بلا ضماناتٍ مسجَّلة');
$db->query("INSERT INTO project (company_id, name, client, location, total)
            VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($db->insert_id);
$db->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
            actual_start, actual_end, first_party, second_party, contract_status, project_id,
            price_currency_contract, retention_pct, created_at)
            VALUES ({$CO}, '2087-01-01', 365, '2087-01-01', '2087-12-31',
                    'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 0, NOW())");
$CID = intval($db->insert_id);
check($CID > 0, "عقدٌ #{$CID}");

$jar = $TMP . "/cg_{$MARK}.txt";
cg_login('مبيعات', $jar);
list($c, $h, $b) = cg_req($BASE . '/Contracts/contract_guarantees.php?contract=' . $CID, $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';

head('① الشاشةُ تُفتح و**الرقمان في عمودين لا في مجموع**');
check($c === 200 && mb_strpos($b, 'ضمانات العقد') !== false, 'الشاشةُ 177 تُفتح بالدور 12 (200)');
check(mb_strpos($b, 'لا يُخصم من مستخلصٍ ولا يظهر أصلًا') !== false,
      '★ والقاعدةُ مكتوبةٌ على الشاشة — لا في التوثيق وحدَه');
check(mb_strpos($b, 'رقمان لا يُجمعان') !== false,
      '★★ و«**رقمان لا يُجمعان**» ظاهرةً بين العمودين');

head('③ ومحتجزٌ **بلا تاريخِ ردٍّ يرفضه الخادم**');
list($c, $h, $b2) = cg_req($BASE . '/Contracts/contract_guarantees.php', $jar, array(
    'g_action' => 'add', 'contract_id' => $CID, 'kind' => 'cash_retention',
    'amount' => '5000', 'currency' => 'USD', 'csrf_token' => $TOK));
$msg = cg_msg($h);
check(mb_strpos($msg, '422') !== false && mb_strpos($msg, 'أصلٌ بلا موعدِ عودةٍ') !== false, '★★ ' . $msg);

head('② وخطابُ ضمانٍ يُسجَّل ⇒ **خارجَ الميزانية لا أصلًا**');
list($c, $h, $b2) = cg_req($BASE . '/Contracts/contract_guarantees.php', $jar, array(
    'g_action' => 'add', 'contract_id' => $CID, 'kind' => 'bank_guarantee',
    'amount' => '80000', 'currency' => 'USD', 'issuer' => 'بنكُ ' . $MARK,
    'instrument_ref' => 'LG-' . $MARK, 'expiry_date' => '2088-06-30',
    'csrf_token' => $TOK));
$msg = cg_msg($h);
check(mb_strpos($msg, 'لا يُخصم من مستخلص') !== false, '★★ ' . $msg);
$q = $db->query("SELECT nature, deductible_from_claim FROM contract_guarantees
                  WHERE contract_id={$CID} AND kind='bank_guarantee'")->fetch_assoc();
check($q && $q['nature'] === 'off_balance' && (int) $q['deductible_from_claim'] === 0,
      '★★★ والصفُّ مخزَّنٌ **خارجَ الميزانية وغيرَ قابلٍ للخصم** — والطبيعةُ لم تُطلَب أصلًا');

list($c, $h, $b) = cg_req($BASE . '/Contracts/contract_guarantees.php?contract=' . $CID, $jar);
check(mb_strpos($b, '80000') !== false, 'والشاشةُ تُري 80,000');
check(preg_match('~أصلٌ لدى العميل — <strong>محتجزٌ نقديٌّ فعليّ</strong></div>\s*<div[^>]*>0~u', $b) === 1
      || mb_strpos($b, '>0 USD') !== false,
      '★★★ و**عمودُ الأصل صفرٌ** رغم الثمانين ألفًا — «ولا يظهر أصلًا»');

head('④ والإفراجُ من الشاشة **يُبقي الصفَّ بسببه**');
$GID = intval($db->query("SELECT id FROM contract_guarantees
                           WHERE contract_id={$CID} AND kind='bank_guarantee'")->fetch_assoc()['id']);
list($c, $h, $b2) = cg_req($BASE . '/Contracts/contract_guarantees.php', $jar, array(
    'g_action' => 'state', 'contract_id' => $CID, 'gid' => $GID, 'state' => 'released',
    'state_reason' => 'أُعيد الخطابُ للبنك — ' . $MARK, 'state_at' => '2088-06-30',
    'csrf_token' => $TOK));
check(mb_strpos(cg_msg($h), 'مُفرَجٌ عنه') !== false, '★ ' . cg_msg($h));
$q = $db->query("SELECT state, state_reason FROM contract_guarantees WHERE id={$GID}")->fetch_assoc();
check($q['state'] === 'released' && mb_strpos((string) $q['state_reason'], $MARK) !== false,
      '★★ والصفُّ **باقٍ** بحالِه وسببِه — **لا حذف**');
list($c, $h, $b) = cg_req($BASE . '/Contracts/contract_guarantees.php?contract=' . $CID, $jar);
check(mb_strpos($b, 'أُعيد الخطابُ للبنك') !== false, 'والشاشةُ تعرض السببَ تحت حاله');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
