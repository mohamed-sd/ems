<?php
/**
 * tests/ticket_journey_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشريط رحلة البلاغ على الشاشة الحيّة (الدستور §5 · UX-07 §2).
 * يفتح كلَّ بلاغٍ في القاعدة بحساب مدير البلاغات ويثبت لكلٍّ:
 *   ① الشريط مطبوعٌ بمراحله الخمس
 *   ② المرحلةُ المضاءة تطابق مرحلةَ البلاغ في القاعدة (لا نصًّا مثبَّتًا)
 *   ③ الوقفتان (waiting · follow_up) تبقيان على ③ بلافتةِ سببٍ لا كمرحلتين
 *   ④ الملغى بلا خطوةٍ تالية · والمغلق بلافتة اكتمال
 * يقرأ ولا يكتب صفًّا واحدًا.
 *
 * التشغيل: php tests/ticket_journey_http_proof.php   (يتطلب Apache حيًّا)
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

function tj_req($url, $jar, $post = null) {
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

/** المرحلة → اسمُ الشريحة المضاءة (نظيرٌ مستقلٌّ لخريطة tkt_journey) */
function tj_expected($stage) {
    switch ($stage) {
        case 'new': case 'classified':                    return 'سُجّل';
        case 'routed':                                    return 'وُجّه';
        case 'in_progress': case 'waiting': case 'follow_up': return 'قيد التنفيذ';
        case 'done':                                      return 'أُنجز';
    }
    return null;   // closed (اكتمال) · cancelled (توقف) — لا مرحلةَ مضاءة
}

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

// مدير البلاغات (دور 24) يرى كلَّ البلاغات — برجُ المراقبة
$jar = $TMP . '/tj_tickets_mgr.txt';
@unlink($jar);
list($c, $h, $b) = tj_req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
tj_req($BASE . '/login.php', $jar, array('username' => 'بلاغات', 'password' => '12345678',
    'csrf_token' => isset($m[1]) ? $m[1] : ''));

$rows = array();
$res = $db->query("SELECT id, ticket_no, stage FROM tickets ORDER BY id");
while ($res && ($r = $res->fetch_assoc())) { $rows[] = $r; }
if (!$rows) { fwrite(STDOUT, "  (لا بلاغاتٍ في القاعدة)\n"); exit(0); }

$seen = array();
foreach ($rows as $t) {
    list($code, $h, $b) = tj_req($BASE . '/Tickets/ticket_form.php?id=' . intval($t['id']), $jar);
    $tag = "{$t['ticket_no']} ({$t['stage']})";
    if ($code !== 200) { bad("{$tag}: HTTP {$code}"); continue; }
    if (strpos($b, 'class="ems-journey"') === false) { bad("{$tag}: لا شريطَ في الشاشة"); continue; }

    preg_match_all('~<div class="(ems-jstep[^"]*)"[^>]*>.*?<div class="ems-jlabel">([^<]*)</div>~s',
        $b, $st, PREG_SET_ORDER);
    check(count($st) === 5, "{$tag}: خمسُ مراحلَ مطبوعة (وُجد " . count($st) . ")");

    $currentLabel = null;
    foreach ($st as $s) {
        if (strpos($s[1], 'is-current') !== false) { $currentLabel = trim($s[2]); break; }
    }
    $want = tj_expected($t['stage']);
    check($currentLabel === $want,
        "{$tag}: المضاءة «" . ($currentLabel !== null ? $currentLabel : '—') . "» = المتوقع «"
        . ($want !== null ? $want : '—') . "»");

    $hasNext   = (strpos($b, 'ems-journey-next') !== false);
    $hasStop   = (strpos($b, 'ems-journey-banner is-stop') !== false);
    $hasDone   = (strpos($b, 'ems-journey-banner is-done') !== false);
    $hasPause  = (strpos($b, 'ems-journey-banner is-return') !== false);

    if ($t['stage'] === 'cancelled') {
        check(!$hasNext && $hasStop, "{$tag}: الملغى بلا خطوةٍ تاليةٍ وبلافتة توقف");
    } elseif ($t['stage'] === 'closed') {
        check(!$hasNext && $hasDone, "{$tag}: المغلق بلا خطوةٍ تاليةٍ وبلافتة اكتمال");
    } elseif ($t['stage'] === 'waiting' || $t['stage'] === 'follow_up') {
        check($hasNext && $hasPause, "{$tag}: الوقفةُ تبقى على ③ بلافتةِ سببها وخطوةٍ تالية");
    } else {
        check($hasNext, "{$tag}: سطرُ الخطوة التالية موجود");
    }
    $seen[$t['stage']] = true;
}

fwrite(STDOUT, "\n  المراحلُ المغطّاة فعليًّا: " . implode(' · ', array_keys($seen)) . "\n");
check(count($seen) >= 6, 'التغطيةُ شملت ست مراحلَ مختلفةٍ فأكثر من بيانات حيّة');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
