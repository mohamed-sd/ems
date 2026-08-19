<?php
/**
 * tests/centrality_live_test.php — اختبارُ المركزيةِ الحيّ (تاسعًا-② بقرارِ المالك)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «غيّرْ خاصيةً في مكوّنٍ مركزيٍّ وقِسِ الأثرَ في كلِّ نظيرٍ ثم استعِدْ».
 *
 * ◆ **ولماذا هذا الاختبارُ موجودٌ أصلًا**: في هذه الجولةِ نُقلت قاعدةٌ واحدةٌ
 *   (`.main{max-width:600px}`) من شاشةِ تغييرِ كلمةِ المرورِ إلى الطبقةِ المركزية،
 *   **فانكمشَ النظامُ كلُّه** من 1837px إلى 600px. ولم يكشفها فحصُ البناءِ ولا
 *   عددُ العقدِ ولا الأساسُ البصريّ — لأن البنيةَ لم تتغيّر. كشفها **قياسُ عرضٍ
 *   في متصفحٍ حيّ**. فالمركزيةُ **خاصيةٌ تُقاس لا تُفترض**.
 *
 * ◆ ويقيسُ اتجاهَين — والعطبُ في أيِّهما عطب:
 *   ① **الصاعد (فورك)**: تغييرٌ في المكوّنِ المركزيِّ يجب أن يصلَ **كلَّ** نظير.
 *      فما لا يصلُه نظيرٌ فذلك النظيرُ متشعّبٌ والمركزيةُ فيه دعوى.
 *   ② **الهابط (تسرّب)**: تغييرٌ في طبقةٍ **مقيَّدةٍ بشاشة** يجب ألّا يطبِّقَ على
 *      نظيرٍ واحد. وهذا بعينُه ما فشل في عطبِ الـ600px.
 *
 * ◆ **والقياسُ كما يصلُ المتصفحَ لا كما يُقرأ في المصدر**: تُجلبُ الصفحةُ عبر HTTP،
 *   وتُستخرَجُ وسومُ `<link>` من الجسمِ المُصيَّر، وتُجلبُ أوراقُ الأنماطِ بعناوينها
 *   الفعليةِ (ببصمةِ الإصدار) — لأن وسمًا غيرَ مُغلَقٍ ابتلعَ ما بعدَه صامتًا مرةً
 *   من قبل، فقراءةُ المصدرِ كانت تكذب.
 *
 * ◆ **الأمان**: كلُّ ملفٍّ يُمَسُّ تُؤخذ بصمتُه sha256 قبلًا، والاستعادةُ مسجَّلةٌ
 *   في `register_shutdown_function` فتقعُ حتى على خطأٍ قاتلٍ أو مقاطعة. ويرفض
 *   الاختبارُ العملَ إن كان أحدُ الملفَّين مُعدَّلًا في الشجرةِ — كي لا يُخلَط
 *   عملُك بأثرِه.
 *
 * التشغيل: php tests/centrality_live_test.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/centrality_' . getmypid();
$PEER_ROLE = 6;   /* دورُ إدارةِ الموقع — نفسُ هويةِ الدخولِ أدناه، فلا يُقاس نظيرٌ لا يفتحُه */

/* ══ المكوّنُ المركزيُّ المقيسُ ونظيرُه المقيَّد ══════════════════════════════ */
$CENTRAL = $ROOT . '/assets/css/uxui-tokens.css';   /* الطبقةُ المركزيةُ الجذرية */
$SCOPED  = $ROOT . '/assets/css/ems-screens.css';   /* طبقةٌ مقيَّدةٌ بأصنافِ شاشات */

$SENTINEL_UP   = '--ems-centrality-probe-up';
$SENTINEL_DOWN = '--ems-centrality-probe-down';
$MARK_UP       = 'ctrup' . dechex(crc32(__FILE__ . 'up'));
$MARK_DOWN     = 'ctrdn' . dechex(crc32(__FILE__ . 'dn'));
$SCOPE_CLASS   = 'scr-settings-change-password';
/* ◆ **بصمةُ ضبطٍ لا تُكتب في أيِّ ملف**: إن وُجدت في نظيرٍ فالكاشفُ يكذب
 *   (يُرجع «وجَدتُ» بلا شرط) — وحينَها الـ100٪ أعلاه بلا معنى. فالاختبارُ
 *   يُثبتُ أولًا أنه **قادرٌ على أن يقولَ لا**. */
$MARK_CTRL     = 'ctrctl' . dechex(crc32(__FILE__ . 'ctl'));

/* ══ ٠ حارسُ الشجرةِ النظيفة ══════════════════════════════════════════════ */
$dirty = array();
foreach (array($CENTRAL, $SCOPED) as $f) {
    $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    $out = array(); $rc = 0;
    exec('git -C ' . escapeshellarg($ROOT) . ' status --porcelain -- ' . escapeshellarg($rel) . ' 2>&1', $out, $rc);
    if ($rc === 0 && !empty(array_filter($out))) { $dirty[] = $rel; }
}
if ($dirty) {
    echo "✘ الشجرةُ غيرُ نظيفةٍ في: " . implode(' · ', $dirty) . "\n";
    echo "  والاختبارُ يكتب في هذَين الملفَّين ثم يستعيد — فلا يُشغَّل فوقَ عملٍ غيرِ ملتزَم.\n";
    exit(2);
}

/* ══ ٠-ب البصماتُ والاستعادةُ المضمونة ══════════════════════════════════ */
$ORIG = array();
foreach (array($CENTRAL, $SCOPED) as $f) {
    if (!is_file($f)) { echo "✘ مفقود: $f\n"; exit(2); }
    $ORIG[$f] = array('body' => file_get_contents($f), 'sha' => hash_file('sha256', $f));
}
/**
 * كتابةٌ مُعاوِدةٌ — أباتشي يُبقي ورقةَ الأنماطِ مفتوحةً وهو يخدمُها، فترتدُّ
 * الكتابةُ بـ«Permission denied» على وندوز. وقد **ارتدَّت الاستعادةُ فعلًا** في
 * أولِ تشغيلٍ فبقيت البصمتانِ في الشجرة — فالمعاودةُ شرطُ أمانٍ لا تحسين.
 */
function ctr_write(string $f, string $body, int $tries = 40): bool {
    for ($i = 0; $i < $tries; $i++) {
        if (@file_put_contents($f, $body) !== false) { clearstatcache(true, $f); return true; }
        usleep(150000);
    }
    return false;
}

$GLOBALS['__CTR_ORIG'] = $ORIG;
register_shutdown_function(function () {
    foreach ($GLOBALS['__CTR_ORIG'] as $f => $o) {
        if (hash_file('sha256', $f) !== $o['sha']) { ctr_write($f, $o['body']); }
    }
});

/* ══ أدواتُ الشبكة ══════════════════════════════════════════════════════ */
function http(string $url, string $jar, ?array $post = null): array {
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
    $resp = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array('code' => $code, 'head' => substr($resp, 0, $hlen), 'body' => substr($resp, $hlen));
}

/** يستخرجُ وسومَ link الخاصةَ بالأنماطِ من الجسمِ **المُصيَّر** لا من المصدر. */
function css_links(string $html): array {
    $out = array();
    if (preg_match_all('/<link\b[^>]*>/i', $html, $m)) {
        foreach ($m[0] as $tag) {
            if (stripos($tag, 'stylesheet') === false) { continue; }
            if (preg_match('/href\s*=\s*"([^"]+)"/i', $tag, $h)) { $out[] = $h[1]; continue; }
            if (preg_match("/href\\s*=\\s*'([^']+)'/i", $tag, $h)) { $out[] = $h[1]; }
        }
    }
    return $out;
}

function abs_url(string $href, string $base): string {
    if (preg_match('#^https?://#i', $href)) { return $href; }
    if ($href !== '' && $href[0] === '/') { return 'http://localhost' . $href; }
    return rtrim($base, '/') . '/' . ltrim($href, '/');
}

/* ══ ١ الدخولُ بهويةٍ حقيقية ═══════════════════════════════════════════ */
@unlink($JAR);
$g = http($BASE . '/login.php', $JAR);
if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) {
    echo "✘ تعذّر رمزُ الدخول\n"; exit(2);
}
$lg = http($BASE . '/login.php', $JAR, array(
    'csrf_token' => $m[1], 'username' => 'موقع', 'password' => '12345678',
));
if (!in_array($lg['code'], array(301, 302, 303), true) || stripos($lg['head'], 'login.php') !== false) {
    echo "✘ فشلَ الدخولُ بالحسابِ المقيس (code={$lg['code']})\n"; exit(2);
}

/* ══ ٢ النظراءُ = مساراتُ سايدبارِ الدورِ **كما تُصيَّر** — من المصدرِ القانونيِّ
 *    نفسِه (`uxp_render_role`) لا من كشطِ صفحةِ الهبوط. وكشطُ الهبوطِ أعطى
 *    نظيرَين اثنَين لأن `dashboard.php` **موجِّهٌ لا صفحة** — ومقامٌ كاذبٌ
 *    يُنتج حكمًا كاذبًا، فلا يُقبَل مصدرًا. ══════════════════════════════════ */
$peers = array();
$navOut = array(); $navRc = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tests/_centrality_routes.php')
     . ' ' . (int) $PEER_ROLE . ' 2>&1', $navOut, $navRc);
foreach ($navOut as $line) {
    $line = trim($line);
    if ($line === '' || substr($line, -4) !== '.php') { continue; }
    $peers['http://localhost/ems/' . ltrim($line, '/')] = true;
}
$peers = array_keys($peers);
sort($peers);
$peersAll = count($peers);
$PEER_CAP = 40;                      /* مقامٌ مُعلَنٌ لا مُخفى */
$peers    = array_slice($peers, 0, $PEER_CAP);

echo "════ اختبارُ المركزيةِ الحيّ ════\n";
echo "  الهوية: دورُ إدارةِ الموقع · النظراءُ المتاحون من سايدبارِه المُصيَّر: {$peersAll}\n";
echo "  المقامُ المقيسُ (سقفٌ مُعلَن): " . count($peers) . "\n";
if (count($peers) < 5) { echo "✘ مقامٌ أصغرُ من أن يُحكَم به — NOT_MEASURED\n"; exit(2); }

/* ══ ٣ حقنُ البصمتَين ══════════════════════════════════════════════════ */
$okUp = ctr_write($CENTRAL, $ORIG[$CENTRAL]['body']
    . "\n/* centrality probe */\n:root{ {$SENTINEL_UP}: {$MARK_UP}; }\n");
$okDn = ctr_write($SCOPED, $ORIG[$SCOPED]['body']
    . "\n/* centrality probe */\n.{$SCOPE_CLASS}{ {$SENTINEL_DOWN}: {$MARK_DOWN}; }\n");
if (!$okUp || !$okDn) { echo "✘ تعذّر حقنُ البصمة (الملفُّ مقفولٌ من الخادم) — NOT_MEASURED
"; exit(2); }
clearstatcache();

/* ══ ٤ القياسُ على كلِّ نظير ═════════════════════════════════════════════ */
$cssCache = array();
function css_body(string $url, string $jar, array &$cache): string {
    if (!isset($cache[$url])) {
        $r = http($url, $jar);
        $cache[$url] = ($r['code'] === 200) ? $r['body'] : '';
    }
    return $cache[$url];
}

$reachedUp = 0; $leakedDown = 0; $unreachable = 0; $ctrlHits = 0;
$missUp = array(); $leakList = array();
foreach ($peers as $u) {
    $p = http($u, $JAR);
    if ($p['code'] !== 200) { $unreachable++; continue; }
    $chain = css_links($p['body']);
    if (!$chain) { $unreachable++; continue; }
    $up = false; $dn = false; $ctrl = false;
    foreach ($chain as $href) {
        $b = css_body(abs_url($href, $BASE), $JAR, $cssCache);
        if ($b === '') { continue; }
        if (strpos($b, $MARK_UP) !== false)   { $up = true; }
        if (strpos($b, $MARK_DOWN) !== false) { $dn = true; }
        if (strpos($b, $MARK_CTRL) !== false) { $ctrl = true; }
    }
    $short = str_replace($BASE . '/', '', $u);
    if ($up) { $reachedUp++; } else { $missUp[] = $short; }
    if ($ctrl) { $ctrlHits++; }
    /* الهابطُ تسرُّبٌ فقط إذا **طبَّق**: وصلَ الورقةَ **و**حملَ الجسمُ صنفَ النطاق */
    if ($dn && strpos($p['body'], $SCOPE_CLASS) !== false && stripos($u, 'change_password') === false) {
        $leakedDown++; $leakList[] = $short;
    }
}

/* ══ ٥ الاستعادةُ والتحقّقُ منها ══════════════════════════════════════════ */
foreach ($ORIG as $f => $o) { ctr_write($f, $o['body']); }
clearstatcache();
$restored = true;
foreach ($ORIG as $f => $o) { if (hash_file('sha256', $f) !== $o['sha']) { $restored = false; } }
@unlink($JAR);

/* ══ ٦ الحكمُ الثلاثيّ ═══════════════════════════════════════════════════ */
$den = count($peers) - $unreachable;
echo "\n▐ ① الصاعد — تغييرُ المكوّنِ المركزيِّ يصلُ كلَّ نظير\n";
if ($den <= 0) {
    echo "  ⊘ NOT_MEASURED (مقامٌ صفريّ)\n";
} else {
    printf("  وصلَ %d من %d (%.1f٪)%s\n", $reachedUp, $den, $reachedUp * 100 / $den,
        $missUp ? " · لم يصلْ: " . implode(' · ', array_slice($missUp, 0, 6)) : '');
}
echo "▐ ② الهابط — تغييرُ الطبقةِ المقيَّدةِ لا يطبِّقُ على نظير\n";
printf("  طبَّقَ على %d نظيرًا%s\n", $leakedDown,
    $leakList ? ": " . implode(' · ', array_slice($leakList, 0, 6)) : '');
echo "▐ ③ الضبط — بصمةٌ لم تُكتب قطُّ يجب ألّا تُوجَد
";
printf("  وُجدت في %d نظيرًا%s
", $ctrlHits, $ctrlHits ? " ← الكاشفُ يكذب، فالـ① بلا معنى" : "");
echo "▐ ④ الاستعادة\n";
echo "  " . ($restored
    ? "✔ الملفّانِ عادا ببصمتِهما الأصلية (sha256 مطابق)"
    : "✘ الاستعادةُ لم تكتمل — أوقفْ وافحصْ") . "\n";
if ($unreachable) {
    echo "  ◆ استُثني {$unreachable} نظيرًا (غيرُ 200 أو بلا سلسلةِ أنماط) — مطروحٌ من المقامِ لا محسوبٌ نجاحًا\n";
}

$pass    = ($den > 0) && ($reachedUp === $den) && ($leakedDown === 0) && ($ctrlHits === 0) && $restored;
$verdict = ($den <= 0) ? 'NOT_MEASURED' : ($pass ? 'PASS' : 'FAIL');
echo "\n" . ($pass ? '✔' : '✘') . " الحكم: {$verdict}\n";
exit($pass ? 0 : 1);
