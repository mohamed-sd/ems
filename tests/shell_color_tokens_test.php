<?php
/**
 * tests/shell_color_tokens_test.php — شاهدُ ألوانِ القشرةِ الحاكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0496
 *
 * **العيب**: القشرةُ نفسُها تخالف نظامَ الرموز — ألوانٌ مكتوبةٌ صلبًا في
 * `insidebar.php` و`includes/topbar.php`، فتغييرُ رمزٍ دلاليٍّ واحدٍ لا يغيّر
 * مظهرَ القشرة. والقبول: **صفرُ لونٍ صلبٍ** في الملفاتِ الثلاثة.
 *
 * **الإصلاح**: كلُّ لونٍ صار رمزًا. وما لم يكن له رمزٌ (لونُ العنصرِ النشطِ في
 * السايدبار) **أُنشئ له رمزٌ بقيمتِه نفسِها** — فلا تغييرَ بصريَّ ألبتّة،
 * والقيمةُ صارت في مكانٍ واحد.
 *
 * ── ورمزٌ بلا تعريفٍ أسوأُ من لونٍ صلب ────────────────────────────────────
 * `var(--x)` غيرُ معرَّفةٍ تُسقط الخاصيةَ كلَّها فيرث العنصرُ لونًا آخر. فيُقاس
 * هنا أنَّ **كلَّ رمزٍ تناديه القشرةُ معرَّفٌ فعلًا** — لا أنَّ الحروفَ اختفت.
 *
 * ◆ ولا يُقاس بـ`getComputedStyle` في متصفحٍ آليّ: جُرِّب فأعاد لقطةً جامدةً —
 *   حتى `el.style.setProperty('color', …, 'important')` لم يغيّر المُقاس. فما
 *   لا يُقاس بأداةٍ يُقاس بأخرى، ولا يُدَّعى بلا قياس.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0496 · القشرةُ تلوّن برموزٍ لا بحروفٍ صلبة');

$SHELL = array('assets/css/ems-shell.css', 'insidebar.php', 'includes/topbar.php');
$LITERAL = '~#[0-9a-fA-F]{3,8}\b|\brgba?\([0-9 ,.%/]+\)|\bhsla?\([0-9 ,.%/]+\)~';

/* ── ① صفرُ لونٍ صلبٍ في الثلاثة ───────────────────────────────────────────── */
$hits = array(); $tokenRefs = array(); $totalRefs = 0;
foreach ($SHELL as $rel) {
    $src = (string) file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all($LITERAL, $src, $m)) {
        foreach ($m[0] as $g) { $hits[] = $rel . ': ' . $g; }
    }
    if (preg_match_all('~var\(\s*(--[A-Za-z0-9_-]+)~', $src, $v)) {
        foreach ($v[1] as $t) { $tokenRefs[$t] = true; $totalRefs++; }
    }
}
$ok(empty($hits), '**صفرُ لونٍ صلبٍ** في القشرةِ الحاكمةِ الثلاثية (' . count($hits) . ')',
    implode(' · ', array_slice($hits, 0, 6)));
$ok($totalRefs > 50, "وتنادي {$totalRefs} إشارةَ رمزٍ (" . count($tokenRefs) . ' رمزًا متمايزًا)',
    'قليلٌ جدًّا — فالخلوُّ من الحروفِ قد يكون خلوًّا من التلوينِ أصلًا');

/* ── ② وكلُّ رمزٍ تناديه القشرةُ **معرَّفٌ** ───────────────────────────────── */
$defined = array();
foreach (array('assets/css/design-tokens.css', 'assets/css/ems-shell.css',
               'assets/css/ems.main.all.style.css') as $rel) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { continue; }
    $src = (string) file_get_contents($p);
    if (preg_match_all('~(^|[;{\s])(--[A-Za-z0-9_-]+)\s*:~m', $src, $d)) {
        foreach ($d[2] as $t) { $defined[$t] = true; }
    }
}
$missing = array();
foreach (array_keys($tokenRefs) as $t) { if (!isset($defined[$t])) { $missing[] = $t; } }
$ok(empty($missing), '**وكلُّ رمزٍ تناديه معرَّفٌ فعلًا** — فلا خاصيةَ تسقط صامتةً',
    'بلا تعريف: ' . implode(' · ', array_slice($missing, 0, 8)));

/* ── ③ ورمزُ العنصرِ النشطِ يحمل القيمةَ التي كانت مكتوبةً صلبًا ────────────── */
$tok = (string) file_get_contents($ROOT . '/assets/css/design-tokens.css');
$ok(preg_match('~--c-nav-active:\s*#E0AE2E~i', $tok) === 1,
    'ورمزُ `--c-nav-active` بقيمةِ اللونِ السابقةِ نفسِها — صفرُ تغييرٍ بصريّ');

/* ── ④ والإشارةُ تصل إلى المتصفحِ فعلًا (مُخرَجُ HTTP لا المصدر) ─────────────── */
$jar = sys_get_temp_dir() . '/shellcol_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$user = $x ? (string) $x[0] : '';
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$page = $http($BASE . '/main/role_board.php');
@unlink($jar);
$ok(mb_strpos($page, 'name="password"') === false && strlen($page) > 20000,
    'وصُيِّرت شاشةٌ كاملةٌ بالقشرةِ (' . strlen($page) . ' بايت)');
$ok(strpos($page, 'var(--c-nav-active)') !== false,
    '**والإشارةُ إلى الرمزِ تصل إلى المتصفحِ** في CSS القشرة');
/* الوعاءُ الذي يحمل قواعدَ السايدبار الموضعيةَ خلا من الحروفِ اللونية */
$inline = '';
if (preg_match_all('~<style[^>]*>(.*?)</style>~su', $page, $ms)) {
    foreach ($ms[1] as $blk) {
        if (strpos($blk, '.ems-site .sidebar ul li.active') !== false) { $inline .= $blk; }
    }
}
$ok($inline !== '', 'ووُجدت قواعدُ العنصرِ النشطِ في المُخرَج');
$ok($inline !== '' && preg_match($LITERAL, $inline) === 0,
    '**وصفرُ حرفٍ لونيٍّ داخلَها** — التلوينُ كلُّه بالرموز',
    'بقي: ' . (preg_match($LITERAL, $inline, $g) ? $g[0] : ''));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
