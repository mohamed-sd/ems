<?php
/**
 * tools/uxui_golden_inventory.php — جردُ الشاشاتِ العشرِ الذهبيةِ قبلَ الترحيل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ بوابة ١٣ (ف١٣-٣): «نقصُ زرٍّ أو عمودٍ أو مرشِّحٍ أو رابطٍ عن جردِ ما قبلَ
 *   الترحيلِ يُرسِّب البناء» — فالجردُ يُبنى **قبل** مسِّ أيِّ شاشةٍ ذهبية،
 *   ويُقارَن بعدَ ترحيلِها عددًا بعدد.
 * ◆ العشرُ من سجلِّ المالكِ الحيِّ gov_golden_approvals (هجرة 2027_06_23) —
 *   بحسابِ اختبارِ كلِّ شاشةٍ ومعاملِها كما سجَّلها المالك، وبالجلبِ الحيِّ
 *   عبرَ HTTP كما يصل المتصفحَ (لا قراءةَ مصدرٍ — فالوسومُ تُقاس كما تُرى).
 * ◆ ما يُجرَد من جسمِ الشاشةِ (بعد عزلِ السايدبارِ والقشرةِ العلوية):
 *   الأزرارُ (button + input[submit/button] + a.btn*) · أعمدةُ الجداول (th) ·
 *   عناصرُ الترشيحِ والإدخالِ الظاهرة (select + input غيرِ المخفيّ + textarea) ·
 *   الروابطُ (a[href]) · الجداولُ · النماذجُ (form).
 *
 * التشغيل:
 *   php tools/uxui_golden_inventory.php --save=<tsv>   الجردُ ويُكتب أساسًا
 *   php tools/uxui_golden_inventory.php --check=<tsv>  مقارنةٌ بالأساس — النقصُ يُرسِّب (رمز 1)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$COOKIES = sys_get_temp_dir() . '/uxui_golden_cookies_' . getmypid() . '.txt';
$args = array();
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; } }

function ugi_http($url, $post = null) {
    global $COOKIES;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $COOKIES, CURLOPT_COOKIEFILE => $COOKIES, CURLOPT_TIMEOUT => 60));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return array((string) $b, $eff);
}
function ugi_login($user) {
    global $BASE, $COOKIES;
    @unlink($COOKIES);
    list($login) = ugi_http("$BASE/login.php");
    $csrf = preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m) ? $m[1] : '';
    ugi_http("$BASE/login.php", array('username' => $user, 'password' => '12345678', 'csrf_token' => $csrf));
}

/** عزلُ جسمِ الشاشة: يسقط السايدبارُ والقشرةُ العلوية — فجردُ الشاشةِ لشاشتِها
 *  ◆ عيبُ قياسٍ وقع فعلًا وأُصلح (2026-08-18): كان يُسقط `ul.nav-group-items`
 *    ولا يُسقط غلافَها `li.nav-group` — ورأسُ كلِّ مجموعةٍ **زرٌّ** (`button
 *    .nav-group-head`). فلمّا غيّر مولّدُ السايدبارِ عددَ المجموعاتِ تغيّر عددُ
 *    «أزرارِ الشاشة» في شاشاتٍ لم تُمَسّ، فأعلن الجردُ فقدًا لا وجودَ له.
 *    القاعدة: ما لا يخصُّ الشاشةَ لا يدخل جردَها. */
function ugi_body($html) {
    $h = preg_replace('~<nav\b.*?</nav>~su', '', $html);
    $h = preg_replace('~<aside\b.*?</aside>~su', '', $h);
    $h = preg_replace('~<div[^>]*(?:id|class)="[^"]*(?:sidebar|insidebar|ems-topbar)[^"]*".*?</div>\s*(?=<)~su', '', $h);
    /* غلافُ المجموعةِ كاملًا — رأسُها زرٌّ وعناصرُها روابط */
    $h = preg_replace('~<li[^>]*class="[^"]*nav-group[^"]*".*?</li>~su', '', $h);
    $h = preg_replace('~<ul[^>]*class="[^"]*nav-group-items[^"]*".*?</ul>~su', '', $h);
    $h = preg_replace('~<button[^>]*class="[^"]*nav-group-head[^"]*".*?</button>~su', '', $h);
    $h = preg_replace('~<script\b.*?</script>~su', '', $h);
    $h = preg_replace('~<style\b.*?</style>~su', '', $h);
    return $h;
}
function ugi_counts($html) {
    $b = ugi_body($html);
    /* ◆ مفرداتُ المكتبةِ الجديدةِ تُعَدُّ ضوابطَ كما القديمة: `ux-chip` رقاقةُ
       منظرٍ **رابطٌ تفاعليٌّ** لا زخرفة. وبدونِها يقرأ الجردُ تغييرَ الشكلِ
       (وهو من المسموحاتِ الأربعة) فقدًا — والعنصرُ باقٍ يُثبته ثباتُ عدِّ
       الروابطِ قبلَ التغييرِ وبعدَه. */
    $buttons  = preg_match_all('~<button\b~iu', $b)
              + preg_match_all('~<input[^>]+type=["\'](?:submit|button)~iu', $b)
              + preg_match_all('~<a[^>]+class="[^"]*(?:btn|ux-chip)[^"]*"~iu', $b);
    $th       = preg_match_all('~<th\b~iu', $b);
    $selects  = preg_match_all('~<select\b~iu', $b);
    $inputs   = preg_match_all('~<input\b(?![^>]*type=["\'](?:hidden|submit|button))~iu', $b)
              + preg_match_all('~<textarea\b~iu', $b);
    $links    = preg_match_all('~<a\b[^>]*href=~iu', $b);
    $tables   = preg_match_all('~<table\b~iu', $b);
    $forms    = preg_match_all('~<form\b~iu', $b);
    return array('buttons' => $buttons, 'columns' => $th, 'filters' => $selects, 'inputs' => $inputs,
                 'links' => $links, 'tables' => $tables, 'forms' => $forms);
}

/* ── العشرُ من سجلِّ المالك ── */
require_once dirname(__DIR__) . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال\n"); }
$db->set_charset('utf8mb4');
$golden = array();
$r = $db->query("SELECT screen_file, title_ar, test_account, url_params, state FROM gov_golden_approvals ORDER BY id");
while ($x = $r->fetch_assoc()) { $golden[] = $x; }
if (empty($golden)) { exit("سجلُّ الذهبيةِ فارغ\n"); }

/* ── الجرد ── */
$rows = array();
foreach ($golden as $g) {
    ugi_login($g['test_account']);
    $url = $BASE . '/' . $g['screen_file'] . ($g['url_params'] !== '' ? '?' . $g['url_params'] : '');
    list($html, $eff) = ugi_http($url);
    if (strpos($eff, basename($g['screen_file'])) === false) {
        $rows[] = array('screen' => $g['screen_file'], 'error' => "حُوِّل عنها ({$eff}) بحساب «{$g['test_account']}»");
        continue;
    }
    $c = ugi_counts($html);
    $c['screen'] = $g['screen_file'];
    $c['title'] = $g['title_ar'];
    $c['state'] = $g['state'];
    $rows[] = $c;
}
@unlink($COOKIES);

/* ── الإخراج ── */
$hdrCols = array('screen', 'title', 'state', 'buttons', 'columns', 'filters', 'inputs', 'links', 'tables', 'forms');
if (!empty($args['save'])) {
    $f = fopen($args['save'], 'w');
    fwrite($f, implode("\t", $hdrCols) . "\n");
    foreach ($rows as $c) {
        if (isset($c['error'])) { fwrite($f, $c['screen'] . "\tERROR\t" . $c['error'] . "\n"); continue; }
        $line = array(); foreach ($hdrCols as $k) { $line[] = $c[$k]; }
        fwrite($f, implode("\t", $line) . "\n");
    }
    fclose($f);
    echo "جردُ ما قبلَ الترحيلِ كُتب ⇐ {$args['save']}\n";
    foreach ($rows as $c) {
        if (isset($c['error'])) { echo "  ⚠ {$c['screen']}: {$c['error']}\n"; continue; }
        echo "  ▪ {$c['screen']} ({$c['title']}): أزرار={$c['buttons']} · أعمدة={$c['columns']} · مرشِّحات={$c['filters']} · إدخال={$c['inputs']} · روابط={$c['links']} · جداول={$c['tables']} · نماذج={$c['forms']}\n";
    }
    exit(0);
}
if (!empty($args['check'])) {
    if (!is_file($args['check'])) { exit("لا أساسَ: {$args['check']}\n"); }
    $baseRows = array();
    foreach (array_slice(file($args['check']), 1) as $l) {
        $c = explode("\t", rtrim($l, "\n"));
        if (count($c) >= 10) { $baseRows[$c[0]] = array_combine($hdrCols, $c); }
    }
    $fails = 0;
    foreach ($rows as $c) {
        if (isset($c['error'])) { echo "  ⚠ {$c['screen']}: {$c['error']}\n"; $fails++; continue; }
        if (!isset($baseRows[$c['screen']])) { echo "  ⚠ {$c['screen']}: لا صفَّ له في الأساس\n"; continue; }
        $b = $baseRows[$c['screen']];
        $bad = array();
        foreach (array('buttons', 'columns', 'filters', 'inputs', 'links', 'tables', 'forms') as $k) {
            if ((int) $c[$k] < (int) $b[$k]) { $bad[] = "{$k}: {$b[$k]}⇐{$c[$k]}"; }
        }
        if ($bad) { $fails++; echo "  ✗ {$c['screen']}: نقصٌ — " . implode(' · ', $bad) . "\n"; }
        else { echo "  ✔ {$c['screen']}: لا نقص\n"; }
    }
    echo $fails === 0 ? "✔ صفرُ فقدٍ في العشرِ الذهبية\n" : "✗ {$fails} شاشة/شاشات فيها نقصٌ — الترحيلُ مرسَّب\n";
    exit($fails === 0 ? 0 : 1);
}
echo "حدِّد --save=<tsv> أو --check=<tsv>\n";
