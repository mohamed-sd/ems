<?php
/**
 * tools/uxui_golden_gates.php — بواباتُ الشاشاتِ الذهبيةِ (ف١٣-٣ · البنود 14..20)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ «بواباتُ المنعِ تُنشأ **قبلَ** ترحيلِ الشاشةِ الأولى لا بعدَه» (ف١٣-١ ②).
 *   وهذه تقرأ **النصَّ المُصيَّرَ** كما يصل المتصفحَ بجلبٍ حيٍّ بحسابِ اختبارِ
 *   كلِّ شاشةٍ من سجلِّ المالكِ `gov_golden_approvals` — لا قراءةَ مصدرٍ ولا
 *   استنتاجَ من جدول.
 *
 * البواباتُ السبع (والبند 13 «صفرُ فقد» في uxui_golden_inventory):
 *   G14  مسارُ ملفٍّ أو اسمُ جدولٍ أو معرِّفُ شاشةٍ في النصِّ الظاهر
 *   G15  قيمةُ حالةٍ داخليةٍ غيرُ مترجمة (draft · pending_approval …)
 *   G16  بياناتُ اختبارٍ أو وسمُ إصدارٍ داخليّ (UAT-2026 · (٣٫٠) · §١٣٫١)
 *   G17  أكثرُ من زرٍّ رئيسيٍّ واحدٍ في الشاشة
 *   G18  أكثرُ من إجراءَين ظاهرَين في خليةِ جدول
 *   G19  ترويسةٌ تتجاوز 96 بكسلًا (تُقاس بعناصرِها المعلنةِ لا بالتخمين)
 *   G20  شريطَا أدواتٍ في صفحةٍ واحدة
 *
 * ◆ المقياسُ يُعلن ما لا يقيس: G19 تُقاس بنيويًّا (عددُ صفوفِ الترويسةِ
 *   وعناصرِها) لا بتصييرٍ بصريٍّ في متصفح — فالارتفاعُ الحقيقيُّ يلزمه
 *   محرّكُ عرض، وهذا يُعلَن ولا يُدَّعى.
 *
 * التشغيل:
 *   php tools/uxui_golden_gates.php               تقريرُ الحالِ لكلِّ شاشة
 *   php tools/uxui_golden_gates.php --enforce     رمزُ خروجٍ 1 عند أيِّ مخالفة
 *   php tools/uxui_golden_gates.php --md=<path>   تقريرٌ Markdown
 *   php tools/uxui_golden_gates.php --screen=X    شاشةٌ واحدةٌ بتفصيلها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$COOKIES = sys_get_temp_dir() . '/uxui_gg_' . getmypid() . '.txt';
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$ENFORCE = isset($args['enforce']);

require_once $ROOT . '/includes/status_display.php';

function gg_http($url, $post = null) {
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
function gg_login($user) {
    global $BASE, $COOKIES;
    @unlink($COOKIES);
    list($login) = gg_http("$BASE/login.php");
    $csrf = preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m) ? $m[1] : '';
    gg_http("$BASE/login.php", array('username' => $user, 'password' => '12345678', 'csrf_token' => $csrf));
}

/** جسمُ الشاشةِ بعد عزلِ القشرةِ (السايدبار · التوبار · السكربت · النمط) */
function gg_body($html) {
    $h = preg_replace('~<script\b.*?</script>~su', '', $html);
    $h = preg_replace('~<style\b.*?</style>~su', '', $h);
    $h = preg_replace('~<nav\b.*?</nav>~su', '', $h);
    $h = preg_replace('~<aside\b.*?</aside>~su', '', $h);
    /* السايدبارُ الموحَّدُ محتوًى في ul.nav-group-items أو ul كامل — يُسقَط */
    $h = preg_replace('~<ul[^>]*class="[^"]*nav-group-items[^"]*".*?</ul>~su', '', $h);
    $h = preg_replace('~<li[^>]*class="[^"]*nav-group[^"]*".*?</li>~su', '', $h);
    $h = preg_replace('~<!--.*?-->~su', '', $h);
    return $h;
}
/** النصُّ الظاهرُ للمستخدمِ وحدَه (بلا وسومٍ ولا سماتٍ تقنية) */
function gg_visible_text($bodyHtml) {
    $t = preg_replace('~<[^>]+>~u', ' ', $bodyHtml);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    return preg_replace('/\s+/u', ' ', $t);
}

/* ── العشرُ من سجلِّ المالك ── */
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال\n"); }
$db->set_charset('utf8mb4');
$golden = array();
$r = $db->query("SELECT screen_file, title_ar, test_account, url_params, state FROM gov_golden_approvals ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) {
    if (!empty($args['screen']) && $x['screen_file'] !== $args['screen']) { continue; }
    $golden[] = $x;
}
if (!$golden) { exit("لا شاشاتٍ للقياس\n"); }

/* ── أنماطُ الكشف ── */
$INTERNAL_STATES = ems_status_internal_values();
$TEST_MARKS = array('UAT-2026', 'UAT2026', 'lorem', 'Lorem', 'test data', 'بيانات اختبار', 'dummy');
$VERSION_MARKS = '~\((?:\d+[٫.]\d+)\)|§\s*\d+[٫.]\d+|v\d+\.\d+\.\d+~u';
/* مسارُ ملفٍّ ظاهرٌ للمستخدم: Dir/file.php أو اسمُ جدولٍ بشرطةٍ سفليةٍ صريح */
$PATH_MARK = '~\b[A-Za-z][A-Za-z0-9_]*/[A-Za-z0-9_]+\.php\b~u';
$TABLE_MARK = '~\b(?:tbl_|gov_|fin_|proc_|mnt_|trs_|ems_)[a-z_]{3,}\b~u';

$rows = array(); $totalFail = 0;
foreach ($golden as $g) {
    gg_login($g['test_account']);
    $url = $BASE . '/' . $g['screen_file'] . ($g['url_params'] !== '' ? '?' . $g['url_params'] : '');
    list($html, $eff) = gg_http($url);
    $rec = array('screen' => $g['screen_file'], 'title' => $g['title_ar'], 'state' => $g['state']);
    if (strpos($eff, basename($g['screen_file'])) === false) {
        $rec['error'] = "حُوِّل عنها ({$eff}) بحساب «{$g['test_account']}»";
        $rows[] = $rec; $totalFail++;
        continue;
    }
    $body = gg_body($html);
    $text = gg_visible_text($body);

    /* G14 · مسارُ ملفٍّ أو اسمُ جدولٍ في النصِّ الظاهر */
    $hits14 = array();
    if (preg_match_all($PATH_MARK, $text, $m14)) { $hits14 = array_unique($m14[0]); }
    if (preg_match_all($TABLE_MARK, $text, $m14b)) { $hits14 = array_merge($hits14, array_unique($m14b[0])); }
    $rec['G14'] = array_values(array_unique($hits14));

    /* G15 · قيمةُ حالةٍ داخليةٍ غيرُ مترجمة */
    $hits15 = array();
    foreach ($INTERNAL_STATES as $st) {
        if (preg_match('~(?<![A-Za-z_])' . preg_quote($st, '~') . '(?![A-Za-z_])~u', $text)) { $hits15[] = $st; }
    }
    $rec['G15'] = array_values(array_unique($hits15));

    /* G16 · بياناتُ اختبارٍ أو وسمُ إصدار */
    $hits16 = array();
    foreach ($TEST_MARKS as $t) { if (mb_stripos($text, $t) !== false) { $hits16[] = $t; } }
    if (preg_match_all($VERSION_MARKS, $text, $m16)) { $hits16 = array_merge($hits16, array_unique($m16[0])); }
    $rec['G16'] = array_values(array_unique($hits16));

    /* G17 · أكثرُ من زرٍّ رئيسيٍّ واحد (الصنفُ المعياريُّ أو ما يعادله) */
    $primary = preg_match_all('~class="[^"]*(?:ux-btn--primary|btn-primary)[^"]*"~iu', $body);
    $rec['G17'] = $primary;

    /* G18 · أكثرُ من إجراءَين ظاهرَين في خليةِ جدول */
    $worstCell = 0; $worstSample = '';
    if (preg_match_all('~<td\b[^>]*>(.*?)</td>~su', $body, $cells)) {
        foreach ($cells[1] as $cell) {
            $acts = preg_match_all('~<(?:a|button)\b[^>]*(?:class="[^"]*(?:btn|action)[^"]*"|onclick=)~iu', $cell);
            if ($acts > $worstCell) { $worstCell = $acts; $worstSample = mb_substr(trim(gg_visible_text($cell)), 0, 40); }
        }
    }
    $rec['G18'] = array('max' => $worstCell, 'sample' => $worstSample);

    /* G19 · الترويسةُ — قياسٌ بنيويٌّ معلَنٌ (لا محرّكَ عرضٍ هنا) */
    $hdrEls = 0;
    if (preg_match('~<(?:header|div)[^>]*class="[^"]*(?:ux-page-header|page-header|ems-page-header)[^"]*".*?</(?:header|div)>~su', $body, $mh)) {
        $hdrEls = preg_match_all('~<(?:button|a|span|h1|h2|p)\b~iu', $mh[0]);
    }
    $rec['G19'] = $hdrEls;

    /* G20 · شريطَا أدواتٍ في صفحةٍ واحدة */
    $bars = preg_match_all('~class="[^"]*(?:ux-toolbar|dt-buttons|dataTables_filter|toolbar)[^"]*"~iu', $body);
    $rec['G20'] = $bars;

    $fails = 0;
    if ($rec['G14']) { $fails++; }
    if ($rec['G15']) { $fails++; }
    if ($rec['G16']) { $fails++; }
    if ($rec['G17'] > 1) { $fails++; }
    if ($rec['G18']['max'] > 2) { $fails++; }
    if ($rec['G20'] > 1) { $fails++; }
    $rec['fails'] = $fails;
    $totalFail += $fails;
    $rows[] = $rec;
}
@unlink($COOKIES);

/* ── التقرير ── */
echo "════ بواباتُ الشاشاتِ الذهبيةِ (14..20) — على النصِّ المُصيَّر ════\n";
foreach ($rows as $x) {
    if (isset($x['error'])) { echo "  ⚠ {$x['screen']}: {$x['error']}\n"; continue; }
    $mark = $x['fails'] === 0 ? '✔' : '✗';
    echo "  {$mark} {$x['screen']} ({$x['title']}) — مخالفات: {$x['fails']}\n";
    if ($x['G14']) { echo "      G14 مسارٌ/جدولٌ ظاهر: " . implode(' · ', array_slice($x['G14'], 0, 5)) . "\n"; }
    if ($x['G15']) { echo "      G15 حالةٌ داخلية: " . implode(' · ', array_slice($x['G15'], 0, 6)) . "\n"; }
    if ($x['G16']) { echo "      G16 بياناتُ اختبارٍ/إصدار: " . implode(' · ', array_slice($x['G16'], 0, 5)) . "\n"; }
    if ($x['G17'] > 1) { echo "      G17 أزرارٌ رئيسية: {$x['G17']} (الحدُّ 1)\n"; }
    if ($x['G18']['max'] > 2) { echo "      G18 إجراءاتٌ في خليةٍ واحدة: {$x['G18']['max']} — «{$x['G18']['sample']}»\n"; }
    if ($x['G20'] > 1) { echo "      G20 أشرطةُ أدوات: {$x['G20']} (الحدُّ 1)\n"; }
}
$clean = 0;
foreach ($rows as $x) { if (!isset($x['error']) && $x['fails'] === 0) { $clean++; } }
echo "\nالحصيلة: " . count($rows) . " شاشةً · نظيفةٌ تمامًا: {$clean} · إجماليُّ المخالفات: {$totalFail}\n";
echo "◆ لا يقيس: الارتفاعَ البصريَّ الحقيقيَّ للترويسة (G19) — يلزمه محرّكُ عرض؛\n";
echo "  والمقيسُ هنا عددُ عناصرِها البنيويةِ مؤشرًا، ويُعلَن ولا يُدَّعى.\n";

if (!empty($args['md'])) {
    $L = array('# بواباتُ الشاشاتِ الذهبيةِ — خطُّ الأساسِ قبلَ الترحيل', '',
        '· التاريخ: ' . date('Y-m-d H:i') . ' · المصدر: جلبٌ حيٌّ بحسابِ اختبارِ كلِّ شاشةٍ من `gov_golden_approvals`',
        '· أمرُ الإنتاج: `php tools/uxui_golden_gates.php --md=<هذا الملف>`', '',
        '| الشاشة | مخالفات | G14 مسار | G15 حالة | G16 اختبار | G17 رئيسي | G18 خلية | G20 أشرطة |',
        '|---|---|---|---|---|---|---|---|');
    foreach ($rows as $x) {
        if (isset($x['error'])) { $L[] = '| `' . $x['screen'] . '` | ⚠ | — | — | — | — | — | — |'; continue; }
        $L[] = '| `' . $x['screen'] . '` | ' . $x['fails'] . ' | ' . count($x['G14']) . ' | ' . count($x['G15'])
             . ' | ' . count($x['G16']) . ' | ' . $x['G17'] . ' | ' . $x['G18']['max'] . ' | ' . $x['G20'] . ' |';
    }
    $L[] = '';
    $L[] = '**الحصيلة:** ' . count($rows) . ' شاشةً · نظيفةٌ تمامًا: ' . $clean . ' · إجماليُّ المخالفات: ' . $totalFail;
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "MD ⇐ {$args['md']}\n";
}
exit(($ENFORCE && $totalFail > 0) ? 1 : 0);
