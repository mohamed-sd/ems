<?php
/**
 * tests/journey_bar_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشريط الرحلة على شاشة الطلب الحيّة (الدستور §5 · UX-02 §13-①).
 * يفتح كلَّ طلبٍ في القاعدة بحساب صاحبه ويثبت:
 *   ① الشريط مطبوعٌ في الصفحة بمراحله السبع
 *   ② المرحلةُ المضاءة تطابق حالةَ الطلب في القاعدة (لا نصًّا مثبَّتًا)
 *   ③ سطرُ «الخطوة التالية» موجودٌ ويحمل اسمَ صاحبها
 *   ④ الشريط يسبق بطاقةَ الملخص — «أعلى شاشة كل معاملة» نصًّا
 * يقرأ ولا يكتب صفًّا واحدًا.
 *
 * التشغيل: php tests/journey_bar_http_proof.php   (يتطلب Apache حيًّا)
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

function jp_req($url, $jar, $post = null) {
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

function jp_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = jp_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return jp_req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

function jp_text($s) {
    return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8')));
}

/** الحالة → اسمُ المرحلة المضاءة (خريطة finreq_journey نفسُها — نظيرٌ مستقل) */
function jp_expected_stage($state, $hasEvent, $isCollection) {
    switch ($state) {
        case 'draft': case 'returned':  return 'الإنشاء والإرسال';
        case 'under_review':            return 'اعتماد الإدارة';
        case 'pending_approval':        return $hasEvent ? 'الاعتماد المالي' : 'مكتب المحاسب';
        case 'approved':                return 'القيد';
        case 'posted':                  return $isCollection ? 'التحصيل' : 'الصرف';
        case 'paid': case 'collected':  return 'الإغلاق';
    }
    return null;   // ختاميةٌ أو متوقفة — لا مرحلةَ مضاءة
}

// المتوقَّع يُقرأ من القاعدة لا يُثبَّت نصًّا — فلا يكذب البرهانُ إن تغيّرت البيانات
$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$cases = array();
$res = $db->query(
    "SELECT r.id, r.request_no, r.state, r.event_id, r.request_type, u.username
       FROM fin_requests r JOIN users u ON u.id = r.created_by
      ORDER BY r.id");
while ($res && ($row = $res->fetch_assoc())) { $cases[] = $row; }
if (!$cases) { fwrite(STDOUT, "  (لا طلباتٍ في القاعدة — لا شيء يُبرهن)\n"); exit(0); }

foreach ($cases as $c) {
    $jar = $TMP . '/jp_' . md5($c['username']) . '.txt';
    jp_login($c['username'], $jar);
    list($code, $h, $b) = jp_req($BASE . '/FinRequests/request_form.php?id=' . intval($c['id']), $jar);
    $tag = "{$c['request_no']} ({$c['state']})";

    if ($code !== 200) { bad("{$tag}: HTTP {$code} — الصفحة لم تُفتح"); continue; }

    $posBar  = strpos($b, 'class="ems-journey"');
    $posCard = strpos($b, 'class="card" style="margin-bottom:14px;"');
    check($posBar !== false, "{$tag}: الشريط مطبوعٌ في الشاشة");
    if ($posBar === false) { continue; }
    check($posCard !== false && $posBar < $posCard,
        "{$tag}: الشريط أعلى بطاقة الملخص (الدستور §5)");

    preg_match_all('~<div class="(ems-jstep[^"]*)"[^>]*>.*?<div class="ems-jlabel">([^<]*)</div>~s',
        $b, $st, PREG_SET_ORDER);
    check(count($st) === 7, "{$tag}: سبعُ مراحلَ مطبوعة (وُجد " . count($st) . ")");

    $currentLabel = null;
    foreach ($st as $s) {
        if (strpos($s[1], 'is-current') !== false) { $currentLabel = trim($s[2]); break; }
    }
    $want = jp_expected_stage($c['state'], !empty($c['event_id']), $c['request_type'] === 'collection');

    if ($want === null) {
        check($currentLabel === null, "{$tag}: حالةٌ ختاميةٌ/متوقفة — لا مرحلةَ مضاءة");
        continue;
    }

    check($currentLabel === $want,
        "{$tag}: المضاءة «" . ($currentLabel !== null ? $currentLabel : '—') . "» = المتوقع «{$want}»");

    if (preg_match('~<div class="ems-journey-next">(.*?)</div>~s', $b, $n)) {
        $t = jp_text($n[1]);
        check(strpos($t, $want) !== false, "{$tag}: سطرُ الخطوة التالية يذكر المرحلة");
        check(mb_strlen($t) > mb_strlen('الخطوة التالية: ' . $want) + 3,
            "{$tag}: وسطرُ الخطوة التالية يحمل صاحبَها");
    } else {
        bad("{$tag}: سطرُ الخطوة التالية غائب");
    }
}

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
