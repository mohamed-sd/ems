<?php
/**
 * tests/claims_actions_col_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP: عمودُ الإجراءاتِ في جدولِ المستخلصات — مُسمًّى وأوّلَ عمود.
 * يقيسُ **المُصيَّرَ كما يصل المتصفحَ** لا نصَّ المصدر:
 *   ① الرأسُ الأوّلُ نصُّه «الإجراءات» — لا رأسَ عاريًا في الجدول
 *   ② الخليةُ الأولى في كلِّ صفٍّ هي التي فيها أيقونةُ العرض (fa-eye)
 *   ③ عددُ الرؤوسِ = عددُ خلايا الصفِّ (لا انزياح)
 *
 * التشغيل: php tests/claims_actions_col_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function ac_req($url, $jar, $post = null) {
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
function ac_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list(, , $b) = ac_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return ac_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

$jar = sys_get_temp_dir() . '/ems_ac_' . getmypid() . '.txt';
ac_login('مبيعات', $jar);

fwrite(STDOUT, "══════════════════════════════════════════════\n");
fwrite(STDOUT, "برهانُ عمودِ الإجراءاتِ — Contracts/claims.php\n");
fwrite(STDOUT, "══════════════════════════════════════════════\n");

head('① الشاشةُ تُفتح والجدولُ يُصيَّر');
list($code, , $html) = ac_req($BASE . '/Contracts/claims.php', $jar);
check($code === 200, "الشاشةُ رُدَّت 200 (فعليًّا {$code})");

/* عزلُ **جدولِ المستخلصاتِ** وحدَه: هو الجدولُ الذي فيه رأسُ «رقم البند».
   المرساةُ رأسٌ لا يتكرر — لا فهرسٌ يزحزحه ظهورُ جدولٍ آخرَ في الصفحة. */
$tables = array();
if (preg_match_all('~<table\b[^>]*>.*?</table>~su', $html, $m)) { $tables = $m[0]; }
$tbl = '';
foreach ($tables as $t) {
    if (strpos($t, '<th>رقم البند</th>') !== false) { $tbl = $t; break; }
}
check($tbl !== '', 'جدولُ المستخلصاتِ وُجد في المُصيَّر');
if ($tbl === '') { goto done; }

head('② الرأسُ الأوّلُ مُسمًّى «الإجراءات»');
preg_match('~<thead\b[^>]*>.*?</thead>~su', $tbl, $th);
$thead = isset($th[0]) ? $th[0] : '';
preg_match_all('~<th\b[^>]*>(.*?)</th>~su', $thead, $hm);
$heads = array_map(function ($x) { return trim(strip_tags($x)); }, $hm[1]);
check(isset($heads[0]) && $heads[0] === 'الإجراءات',
      'الرأسُ رقم 0 = «الإجراءات» (فعليًّا: «' . (isset($heads[0]) ? $heads[0] : '—') . '»)');
$bare = 0;
foreach ($heads as $h) { if ($h === '') { $bare++; } }
check($bare === 0, "لا رأسَ عاريًا في الجدول (عُدَّ {$bare})");

head('③ الخليةُ الأولى في كلِّ صفٍّ فيها أيقونةُ العرض');
preg_match('~<tbody\b[^>]*>.*?</tbody>~su', $tbl, $tb);
$tbody = isset($tb[0]) ? $tb[0] : '';
preg_match_all('~<tr\b[^>]*>(.*?)</tr>~su', $tbody, $rm);
$rows = $rm[1];
check(count($rows) > 0, 'الجدولُ فيه صفوفٌ للقياس (' . count($rows) . ' صفًّا)');
$bad_first = 0; $mismatch = 0;
foreach ($rows as $r) {
    preg_match_all('~<td\b[^>]*>(.*?)</td>~su', $r, $cm);
    $cells = $cm[1];
    if (count($cells) !== count($heads)) { $mismatch++; }
    if (!isset($cells[0]) || strpos($cells[0], 'fa-eye') === false) { $bad_first++; }
}
check($bad_first === 0, "أيقونةُ العرضِ في الخليةِ الأولى في كلِّ صفٍّ (شاذٌّ {$bad_first})");

head('④ لا انزياحَ: عددُ الرؤوسِ = عددُ الخلايا');
check($mismatch === 0,
      'كلُّ صفٍّ ' . count($heads) . ' خليةً بعددِ الرؤوسِ (منزاحٌ ' . $mismatch . ')');

done:
@unlink($jar);
fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
