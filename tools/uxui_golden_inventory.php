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
/**
 * ◆ عيبُ قياسٍ ثانٍ رُصد وأُصلح (2026-08-20): التجريدُ كان **بالتعبيرِ النمطيّ**،
 *   و`<div class="sidebar">` **يحوي أقسامًا متداخلة** — والنمطُ غيرُ الجَشِع
 *   `.*?</div>` يقف عند أولِ `</div>` فيترك بقيةَ السايدبار. ولمّا كانت بنودُه
 *   **خارجَ `li.nav-group`** (روابطُ مستوًى أعلى) نجت من الأنماطِ الثلاثةِ بعدَه.
 *   فبقيَ في «جسمِ الشاشة» **٢٧ بندَ سايدبارٍ و٦ روابطِ قشرةٍ علوية**، ولمّا
 *   أزالت موجةُ عزلِ الإداراتِ ١٧٦ بندًا قرأها الجردُ **نقصَ محتوًى** في ثمانِ
 *   شاشاتٍ لم يُمَسّ محتواها. أي أن بوابةَ صفرِ الفقدِ صارت تُرسِّب ما أذنت به
 *   الوثيقةُ نفسُها — وهو إخفاقٌ كاذبٌ لا يقلُّ ضررًا عن نجاحٍ كاذب.
 * ◆ والعلاجُ بنيويٌّ لا نمطيّ: **الشجرةُ تُقرأ شجرةً**. `DOMDocument` يحذف
 *   العقدةَ بكاملِ نسلِها مهما تداخلت، فلا يبقى منها شيء. والتعبيرُ النمطيُّ
 *   يبقى احتياطًا **مُعلَنًا** إن تعذَّر التحليلُ — ولا يُسكَت عن تعذُّره.
 */
function ugi_body($html) {
    if ($html === '' || strpos($html, '<') === false) { return $html; }
    $prevErr = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok  = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErr);
    if (!$ok) {
        fwrite(STDERR, "  ⚠ تعذَّر تحليلُ الشجرة — سقط التجريدُ إلى النمطِ الاحتياطيّ\n");
        return ugi_body_regex($html);
    }
    $xp = new DOMXPath($doc);
    /* ما لا يخصُّ الشاشةَ لا يدخل جردَها — بعقدتِه وكلِّ نسلِها */
    $kill = $xp->query(
          '//script | //style | //nav | //aside | //footer'
        . ' | //*[@role="navigation"]'
        . ' | //*[@id="sidebar" or @id="sidebarOverlay" or @id="sidebarMobileHead"]'
        . ' | //*[contains(concat(" ", normalize-space(@class), " "), " sidebar ")]'
        . ' | //*[contains(@class, "sidebar-")]'
        . ' | //*[contains(@class, "insidebar")]'
        . ' | //*[contains(@class, "ems-topbar")]'
        . ' | //*[contains(@class, "nav-group")]'
    );
    /* تُجمع أولًا: حذفُ عقدةٍ من قائمةٍ حيةٍ يُزحزح ما بعدَها */
    $doomed = array();
    foreach ($kill as $node) { $doomed[] = $node; }
    foreach ($doomed as $node) { if ($node->parentNode) { $node->parentNode->removeChild($node); } }

    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body === null) { return $doc->saveHTML(); }
    $out = '';
    foreach ($body->childNodes as $child) { $out .= $doc->saveHTML($child); }
    return $out;
}
/** الاحتياطُ النمطيُّ — يُستعمل عندَ تعذُّرِ التحليلِ وحدَه، ويُعلَن حين يُستعمل */
function ugi_body_regex($html) {
    $h = preg_replace('~<nav\b.*?</nav>~su', '', $html);
    $h = preg_replace('~<aside\b.*?</aside>~su', '', $h);
    $h = preg_replace('~<div[^>]*(?:id|class)="[^"]*(?:sidebar|insidebar|ems-topbar)[^"]*".*?</div>\s*(?=<)~su', '', $h);
    $h = preg_replace('~<li[^>]*class="[^"]*nav-group[^"]*".*?</li>~su', '', $h);
    $h = preg_replace('~<ul[^>]*class="[^"]*nav-group-items[^"]*".*?</ul>~su', '', $h);
    $h = preg_replace('~<button[^>]*class="[^"]*nav-group-head[^"]*".*?</button>~su', '', $h);
    $h = preg_replace('~<script\b.*?</script>~su', '', $h);
    $h = preg_replace('~<style\b.*?</style>~su', '', $h);
    return $h;
}
/**
 * عدُّ القدرةِ لا عدُّ الصفوف — والفرقُ جوهريّ.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ عيبُ قياسٍ ثالثٌ رُصد وأُصلح (2026-08-18): كانت الأداةُ تعدُّ كلَّ زرٍّ ورابطٍ
 *   وحقلٍ في الصفحةِ **بما فيها ما يتضاعف بعددِ صفوفِ البيانات**. فشاشةُ
 *   «مهامي» أعلنت فقدًا (نماذج 22⇐0) بينما ملفُّها لم يُلمَس: عشرون مهمةً
 *   انقلبت `overdue` بمرورِ مهلتِها أثناءَ الجلسة، فخرجت من منظرِ «اليوم».
 *   فالعددُ نزل بحركةِ بياناتٍ زمنيةٍ لا بفقدِ قدرة.
 * ◆ و«صفرُ فقد» معناه: **لا قدرةَ تُنزَع** — لا «لا صفَّ ينقص». فالقياسُ صار
 *   على محورَين:
 *   ① القدرةُ البنيوية: ما هو خارجَ `tbody` (ترويسةٌ وشريطٌ ومرشِّحاتٌ وأعمدةٌ
 *      ونماذجُ الصفحة) — لا يتحرك بالبيانات، وهو ما ترسِّب عليه البوابة.
 *   ② قالبُ الصف: عناصرُ **صفٍّ واحدٍ** (الأولِ) لا مجموعُ الصفوف — فيُكشف
 *      نقصُ إجراءٍ من الصفِّ ولو تغيّر عددُ الصفوف.
 *   وعددُ الصفوفِ يُسجَّل خبرًا لا حكمًا (data_rows) فيُفسِّر ولا يُرسِّب.
 */
function ugi_counts($html) {
    $b = ugi_body($html);

    /* قالبُ الصفِّ: أولُ صفٍّ في أولِ tbody */
    $rowTpl = '';
    $dataRows = 0;
    if (preg_match_all('~<tbody\b[^>]*>(.*?)</tbody>~su', $b, $tb)) {
        foreach ($tb[1] as $body) {
            $dataRows += preg_match_all('~<tr\b~iu', $body);
            if ($rowTpl === '' && preg_match('~<tr\b[^>]*>(.*?)</tr>~su', $body, $m1)) { $rowTpl = $m1[1]; }
        }
    }
    /* البنيةُ = الصفحةُ بلا محتوى tbody */
    $struct = preg_replace('~<tbody\b[^>]*>.*?</tbody>~su', '<tbody></tbody>', $b);

    $countIn = function ($s) {
        return array(
            'buttons' => preg_match_all('~<button\b~iu', $s)
                       + preg_match_all('~<input[^>]+type=["\'](?:submit|button)~iu', $s)
                       + preg_match_all('~<a[^>]+class="[^"]*(?:btn|ux-chip)[^"]*"~iu', $s),
            'inputs'  => preg_match_all('~<input\b(?![^>]*type=["\'](?:hidden|submit|button))~iu', $s)
                       + preg_match_all('~<textarea\b~iu', $s),
            'links'   => preg_match_all('~<a\b[^>]*href=~iu', $s),
            'forms'   => preg_match_all('~<form\b~iu', $s),
        );
    };
    $S = $countIn($struct);
    $R = $countIn($rowTpl);

    return array(
        /* ① القدرةُ البنيوية — عليها ترسِّب البوابة */
        'buttons' => $S['buttons'], 'inputs' => $S['inputs'], 'links' => $S['links'], 'forms' => $S['forms'],
        'columns' => preg_match_all('~<th\b~iu', $struct),
        'filters' => preg_match_all('~<select\b~iu', $struct),
        'tables'  => preg_match_all('~<table\b~iu', $struct),
        /* ② قالبُ الصفِّ — نقصُ إجراءٍ في الصفِّ يُرسِّب أيضًا */
        'row_buttons' => $R['buttons'], 'row_links' => $R['links'], 'row_inputs' => $R['inputs'],
        /* خبرٌ يفسّر ولا يحكم */
        'data_rows' => $dataRows,
    );
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
$hdrCols = array('screen', 'title', 'state', 'buttons', 'columns', 'filters', 'inputs', 'links', 'tables', 'forms',
                 'row_buttons', 'row_links', 'row_inputs', 'data_rows');
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
        echo "  ▪ {$c['screen']} ({$c['title']}): أزرار={$c['buttons']} · أعمدة={$c['columns']} · مرشِّحات={$c['filters']} · إدخال={$c['inputs']} · روابط={$c['links']} · جداول={$c['tables']} · نماذج={$c['forms']} · قالبُ الصفِّ(أزرار/روابط)={$c['row_buttons']}/{$c['row_links']} · صفوفُ بيانات={$c['data_rows']}\n";
    }
    exit(0);
}
if (!empty($args['check'])) {
    if (!is_file($args['check'])) { exit("لا أساسَ: {$args['check']}\n"); }
    $baseRows = array();
    foreach (array_slice(file($args['check']), 1) as $l) {
        $c = explode("\t", rtrim($l, "\n"));
        if (count($c) === count($hdrCols)) { $baseRows[$c[0]] = array_combine($hdrCols, $c); }
    }
    $fails = 0;
    foreach ($rows as $c) {
        if (isset($c['error'])) { echo "  ⚠ {$c['screen']}: {$c['error']}\n"; $fails++; continue; }
        if (!isset($baseRows[$c['screen']])) { echo "  ⚠ {$c['screen']}: لا صفَّ له في الأساس\n"; continue; }
        $b = $baseRows[$c['screen']];
        $bad = array(); $delta = array();
        /* data_rows خبرٌ يفسّر ولا يحكم — والحكمُ على القدرةِ وقالبِ الصفّ */
        foreach (array('buttons', 'columns', 'filters', 'inputs', 'links', 'tables', 'forms',
                      'row_buttons', 'row_links', 'row_inputs') as $k) {
            $o = (int) $b[$k]; $w = (int) $c[$k];
            if ($w !== $o) { $delta[] = "{$k}: {$o}⇒{$w}" . ($w < $o ? ' ▼' : ' ▲'); }
            if ($w < $o) { $bad[] = "{$k}: {$b[$k]}⇐{$c[$k]}"; }
        }
        if ($bad) { $fails++; echo "  ✗ {$c['screen']}: نقصٌ — " . implode(' · ', $bad) . "\n"; }
        else { echo "  ✔ {$c['screen']}: لا نقص\n"; }
        /* ◆ الفرقُ كلُّه — صعودًا ونزولًا. فمن رأى النزولَ وحدَه لم يعرف سببَه */
        if (!empty($args['diff']) && $delta) { echo "      ◆ " . implode(' · ', $delta) . "\n"; }
    }
    echo $fails === 0 ? "✔ صفرُ فقدٍ في العشرِ الذهبية\n" : "✗ {$fails} شاشة/شاشات فيها نقصٌ — الترحيلُ مرسَّب\n";
    exit($fails === 0 ? 0 : 1);
}
echo "حدِّد --save=<tsv> أو --check=<tsv>\n";
