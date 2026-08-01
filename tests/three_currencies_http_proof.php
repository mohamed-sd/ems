<?php
/**
 * tests/three_currencies_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **166** — الفروقُ الأربعة (P-08 · §9-⑨ · §9-⑫)
 * من الشاشة الحقيقية بالمدير المالي (17):
 *   ① الشاشةُ تُفتح و**الفروقُ الأربعةُ في أربعة أبوابٍ لا مجموعٍ واحد**
 *   ② والتخصيصُ بعملةٍ أخرى من الشاشة ⇒ **الذمّةُ تُطفأ بالمعادل**
 *   ③ و**المتبقي رصيدٌ غيرُ مسددٍ ظاهرٌ** لا فرقُ صرف
 *   ④ و**فرقُ الصرف بسطره** في سجل الفروق على الشاشة نفسِها
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/three_currencies_http_proof.php   (يتطلب Apache حيًّا)
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

function fx_req($url, $jar, $post = null) {
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
function fx_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = fx_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return fx_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function fx_msg($headers) {
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
$MARK = 'P08H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE d FROM fin_fx_differences d
                 JOIN fin_collection_allocations a ON a.id = d.source_ref
                 JOIN fin_payments p ON p.id = a.payment_id
                WHERE p.bank_ref LIKE '%{$MARK}%'");
    $db->query("DELETE a FROM fin_collection_allocations a JOIN fin_payments p ON p.id = a.payment_id
                 WHERE p.bank_ref LIKE '%{$MARK}%'");
    $db->query("DELETE FROM fin_payments WHERE bank_ref LIKE '%{$MARK}%'");
    $db->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 166 · الفروقُ الأربعة ══\n");

head('البذر — ذمّةٌ بالجنيه بسعرِ اعترافٍ أعلى · وسندٌ بالدولار');
$RATE = 0.000185;
$OLD  = $RATE * 1.20;
$db->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
            amount, currency, fx_rate_recognized, base_amount, collected, due_date, state, created_at)
            VALUES ({$CO}, 1, 'invoice', 'INV-H-{$MARK}', 8000000, 'SDG', {$OLD},
                    ROUND(8000000*{$OLD},2), 0, '2090-03-31', 'open', NOW())");
$R = intval($db->insert_id);
$db->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
            method, bank_ref, received_on, amount, currency, state, created_at)
            VALUES ({$CO}, 'RCTH-{$MARK}', 'collection', 'customer', 1,
                    'bank', 'BNKH-{$MARK}', '2090-02-01', 1000, 'USD', 'executed', NOW())");
$P = intval($db->insert_id);
check($R > 0 && $P > 0, "ذمّةٌ #{$R} بـ8,000,000 SDG · وسندٌ #{$P} بـ1,000 USD");

$jar = $TMP . "/fx_{$MARK}.txt";
fx_login('مديرمالي', $jar);
list($c, $h, $b) = fx_req($BASE . '/Contracts/collections.php?payment_id=' . $P, $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';

head('① الشاشةُ تُفتح و**الفروقُ الأربعةُ في أربعة أبواب**');
check($c === 200 && mb_strpos($b, 'الذمم والتحصيل') !== false, 'الشاشةُ 166 تُفتح بالدور 17 (200)');
check(mb_strpos($b, 'لا تُخلط ولا تُجمع في رقم') !== false,
      '★★ و«**لا تُخلط ولا تُجمع في رقم**» معلَنةً على الشاشة');
foreach (array('رصيدٌ غيرُ مسدد', 'محقَّق', 'غيرُ محقَّق', 'زيادةُ سداد') as $lbl) {
    check(mb_strpos($b, $lbl) !== false, 'وبابُ «' . $lbl . '» ظاهر');
}
check(mb_strpos($b, 'رصيدٌ دائنٌ للعميل لا إيراد') !== false,
      '★ و«زيادةُ السداد **رصيدٌ دائنٌ للعميل لا إيراد**» مكتوبةً تحت بابها');
check(mb_strpos($b, 'تقديرٌ لا يُقفل ذمّةً ولا يمسّ رصيدًا') !== false,
      '★ و«غيرُ المحقَّق **تقديرٌ لا يُقفل ذمّةً**» مكتوبةً تحت بابه');

head('② والتخصيصُ **بعملةٍ أخرى** من الشاشة');
list($c, $h, $b2) = fx_req($BASE . '/Contracts/collections.php', $jar, array(
    'col_action' => 'allocate', 'payment_id' => $P,
    't_kind' => array(0 => 'invoice'), 't_ref' => array(0 => $R),
    't_amount' => array(0 => '1000'), 't_note' => array(0 => 'قبضٌ بالدولار — ' . $MARK),
    'csrf_token' => $TOK));
$msg = fx_msg($h);
check(mb_strpos($msg, 'أُطفئ بالمعادل') !== false, '★★ ' . mb_substr($msg, 0, 150));

$a = $db->query("SELECT pay_currency, target_currency, amount, amount_target, fx_diff_base
                  FROM fin_collection_allocations WHERE payment_id={$P}")->fetch_assoc();
$equiv = round(1000 / $RATE, 2);
check($a && $a['pay_currency'] === 'USD' && $a['target_currency'] === 'SDG'
      && abs((float) $a['amount_target'] - $equiv) < 1.0,
      '★★★ والسطرُ: 1,000 USD ⇒ **' . $a['amount_target'] . ' SDG معادلًا**');

head('③ و**المتبقي رصيدٌ غيرُ مسددٍ** لا فرقُ صرف');
$q = $db->query("SELECT collected, outstanding, state FROM fin_receivables WHERE id={$R}")->fetch_assoc();
check(abs((float) $q['collected'] - (float) $a['amount_target']) < 0.01 && $q['state'] === 'partial',
      '★★★ والذمّةُ **أُطفئت بالمعادل وبقيت جزئية** — والمتبقي ' . $q['outstanding'] . ' SDG');

head('④ و**فرقُ الصرف بسطره** على الشاشة نفسِها');
$d = $db->query("SELECT d.kind, d.amount, d.functional_currency FROM fin_fx_differences d
                  JOIN fin_collection_allocations a ON a.id = d.source_ref
                 WHERE a.payment_id={$P}")->fetch_assoc();
check($d && $d['kind'] === 'realized' && (float) $d['amount'] < 0,
      '★★ سطرُ **فرقٍ محقَّقٍ** بـ' . ($d ? $d['amount'] : '—') . ' ' . ($d ? $d['functional_currency'] : ''));
list($c, $h, $b) = fx_req($BASE . '/Contracts/collections.php', $jar);
check(mb_strpos($b, 'محقَّق') !== false && mb_strpos($b, 'سعرُ الاعتراف') !== false,
      '★★ وسجلُّ الفروق يعرضه **بسعرَي اعترافه وسدادِه** — لا مبتلعًا في مبلغ');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
