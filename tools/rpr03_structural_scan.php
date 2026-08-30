<?php
/**
 * tools/rpr03_structural_scan.php — `RPR-03` §٩ · المسحُ البنيويُّ الآليّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٩: *«الاستجابةُ وإتاحةُ الوصول — **طبقتان
 *   لا واحدة**: مسحٌ آليٌّ بنيويٌّ **لكلِّ سطحٍ قابلٍ للعرض** (‏تجاوزُ الإطار ·
 *   منفذُ العرض · تسمياتٌ مفقودة · بنيةُ إتاحةٍ أساسيّة) · ومراجعةٌ يدويّةٌ
 *   عميقةٌ للشاشاتِ الذهبيّةِ العشر»* · و§١٠: `مسحٌ بنيويٌّ آليّ = منفَّذ`.
 *   ⛔ *«فالعشرُ عيّنةٌ عميقةٌ لا دليلٌ وحيدٌ على ستمئةِ شاشة»*.
 *
 * ◆ **والمسارُ يُقرأ من `route` لا من اسمِ الملفّ** — وهذا عطبٌ وقع فعلًا:
 *   **خمسةَ عشرَ اسمًا من ٦٢٣ له توأمٌ في مجلَّدَين** (`index.php` في سبعةِ
 *   مجلَّدات · `roles.php` في `Settings` و`includes` · `select_project.php` في
 *   `Equipments` و`Oprators` …). فمسحٌ يبحث بالاسمِ **يقرأ التوأمَ الخطأ**:
 *   قِيس `SCR-0402 role_board.php` «بلا قشرة» وهو `main/role_board.php` سليم،
 *   **والمقروءُ كان `includes/role_board.php` وهو جزءٌ مُضمَّنٌ لا شاشة**.
 *   ⇒ **المسارُ الكاملُ أوّلًا، والاسمُ آخرَ ملاذٍ ويُعلَن**.
 *
 * ◆ **وقشرةُ المستندِ تُتَّبع عبرَ الاشتمال**: `viewport` و`lang` و`dir` تصدر من
 *   `inheader.php` **ولا يمسُّها السطحُ مباشرةً** — و`u13_screen_kit.php` يشمله
 *   نيابةً عن أسطحِ المراجعةِ الداخليّة. ⛔ **فمسحٌ يقف عند قفزةٍ واحدةٍ يُبلِّغ
 *   عن تسعةٍ وسبعين سطحًا «بلا قشرة» وهي سليمة** — وقد وقع ذلك قبلَ التصحيح.
 *   ⇒ يُتتبَّع الاشتمالُ حتى **ثلاثِ قفزات**.
 *
 * ◆ **وسطحٌ يردُّ `JSON` ليس سطحًا مُصيَّرًا** — فيخرج من المقامِ بحكمِه لا سهوًا.
 *
 * التشغيل: php tools/rpr03_structural_scan.php [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

/** يبلغ السطحُ قشرةَ المستندِ عبرَ الاشتمالِ حتى ثلاثِ قفزات */
function ems_reaches_shell($file, $ROOT, $depth = 0, &$seen = array())
{
    if ($depth > 3) { return false; }
    $rp = realpath($file);
    if (!$rp || isset($seen[$rp])) { return false; }
    $seen[$rp] = 1;
    $src = (string) @file_get_contents($rp);
    if ($src === '') { return false; }
    if (preg_match('~inheader\.php|public_shell\.php~', $src)) { return true; }
    preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\'"]([^\'"]+\.php)[\'"]~', $src, $m);
    foreach ($m[1] as $inc) {
        $cand = array(dirname($rp) . '/' . $inc, $ROOT . '/' . ltrim($inc, './'),
                      $ROOT . '/includes/' . basename($inc));
        foreach ($cand as $cp) {
            if (is_file($cp) && ems_reaches_shell($cp, $ROOT, $depth + 1, $seen)) { return true; }
        }
    }
    return false;
}

$rows = array();
$r = $conn->query("SELECT screen_id, screen_file, route, ownership_verdict
                     FROM repair01_screen_registry
                    WHERE lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')
                      AND screen_file <> '' ORDER BY screen_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$scanned = 0; $json = 0; $unresolved = 0; $byName = 0;
$noShell = array(); $noAlt = array(); $noLabel = array();
$vendorReg = array(); $vendorRuled = array(); $apiCtl = array();
$tables = 0; $wrapHints = 0;

foreach ($rows as $s) {
    /* ⛔ **المسارُ الكاملُ أوّلًا** — والاسمُ آخرَ ملاذٍ ويُعدّ */
    $path = '';
    if (trim((string) $s['route']) !== '') {
        $p = $ROOT . '/' . ltrim(str_replace('\\', '/', $s['route']), '/');
        if (is_file($p)) { $path = $p; }
    }
    if ($path === '') {
        $h = glob($ROOT . '/*/' . basename($s['screen_file']));
        if (!$h) { $h = glob($ROOT . '/' . basename($s['screen_file'])); }
        if ($h) { $path = $h[0]; $byName++; }
    }
    if ($path === '') { $unresolved++; continue; }

    $src = (string) @file_get_contents($path);
    if ($src === '') { $unresolved++; continue; }
    /* سطحٌ يردُّ JSON ليس مُصيَّرًا — يخرج بحكمِه */
    if (preg_match('~header\(\s*[\'"]Content-Type:\s*application/json~i', $src)) { $json++; continue; }
    $scanned++;

    /* ⛔ **وغيرُ المُصيَّرِ يُصنَّف ولا يُعدُّ عيبًا** — ولا يُخفى أيضًا:
         · `vendor/` **ملفُّ مكتبةٍ مسجَّلٌ شاشةً** — عيبُ تسجيلٍ لا عيبُ إتاحة،
           وهو مقياسٌ مستقلٌّ في `RPR-02` §١٢ (`GAP-67`). ⛔ فلا يُبتلع هنا.
         · `api/` وحدةُ تحكُّمٍ تردُّ بيانًا لا صفحة. */
    $rel = ltrim(str_replace('\\', '/', (string) $s['route']), '/');
    if (strpos($rel, 'vendor/') === 0) {
        /* ⛔ **والحكمُ المسجَّلُ يُقرأ قبلَ أن يُعلَن عيب**: ملفُّ المكتبةِ الموسومُ
           `RETIRE` **حكمٌ صادرٌ في `RPR-02` §١١ (`GAP-67`) لا عطبٌ مفتوح**.
           ولو أُعلن هنا عيبًا لتناقض عدّادان على مفردةٍ واحدة — وذاك عطبُ
           قراءةٍ لا عطبُ نظام ([[counter-parity-two-readers]]). */
        if ((string) $s['ownership_verdict'] === 'RETIRE') { $vendorRuled[] = $s['screen_id'] . ' · ' . $rel; }
        else { $vendorReg[] = $s['screen_id'] . ' · ' . $rel; }
        $scanned--; continue;
    }
    if (strpos($rel, 'api/') === 0)    { $apiCtl[] = $s['screen_id'] . ' · ' . $rel; $scanned--; continue; }
    $seen = array();
    if (!ems_reaches_shell($path, $ROOT, 0, $seen)) { $noShell[] = $s['screen_id'] . ' · ' . $s['route']; }

    /* ⛔ **وسمٌ يحتضن كتلةَ PHP يُبتر عند `?>` فيضيع ما بعدها** — قِيس:
       `<img src="<?php echo … ?>" alt="…">` عُدَّ بلا `alt` كذبًا (سطحان
       زائفان). فتُحيَّد كتلُ PHP قبل فحوصِ البنيةِ — بطولٍ محفوظٍ كي لا
       تنزاح الأسطر. */
    $flat = preg_replace_callback('~<\?(?:php|=)?[\s\S]*?(?:\?>|$)~', function ($m0) {
        return str_repeat('x', strlen($m0[0]));
    }, $src);

    preg_match_all('~<img\b[^>]*>~i', $flat, $mg);
    foreach ($mg[0] as $tag) { if (!preg_match('~\balt=~i', $tag)) { $noAlt[] = $s['screen_id'] . ' · ' . $rel; break; } }

    preg_match_all('~<input\b[^>]*>~i', $flat, $mi);
    foreach ($mi[0] as $tag) {
        if (preg_match('~type=["\'](hidden|submit|button|reset)~i', $tag)) { continue; }
        if (!preg_match('~(aria-label|placeholder|\bid=)~i', $tag)) { $noLabel[] = $s['screen_id'] . ' · ' . $rel; break; }
    }

    preg_match_all('~<table\b~i', $src, $mt);
    $tables += count($mt[0]);
    preg_match_all('~overflow-x|table-responsive|ems-table-wrap~i', $src, $mw);
    $wrapHints += count($mw[0]);
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: يُقاس بالاسمِ لا بالمسار */
if ($SELF) {
    $seen = array();
    $p = $ROOT . '/includes/role_board.php';
    $wrongTwin = is_file($p) && !ems_reaches_shell($p, $ROOT, 0, $seen);
}

echo "\n═══ `RPR-03` §٩ — المسحُ البنيويُّ الآليّ ═══\n";
printf("  اللقطة: %s\n\n", $sid);
printf("  أسطحٌ مُصيَّرةٌ ممسوحة: **%d** · ردُّ `JSON` (‏خارجَ المقامِ بحكمِه): %d"
     . " · تعذّر حلُّ مسارِه: %d\n", $scanned, $json, $unresolved);
printf("  حُلَّت بالاسمِ لا بالمسار: %d ⛔ (‏١٥ اسمًا له توأمٌ في مجلَّدَين)\n\n", $byName);

printf("  ◆ خارجَ المقامِ بحكمِه: **ملفُّ مكتبةٍ مسجَّلٌ شاشةً %d** ⛔ (`RPR-02` §١٢ · `GAP-67`)"
     . " · وحدةُ تحكُّمِ `api` %d\n", count($vendorReg), count($apiCtl));
foreach ($vendorReg as $x) { echo "       ⛔ " . $x . "\n"; }
if ($vendorRuled) {
    printf("  ◆ **وملفُّ مكتبةٍ بحكمٍ مسجَّلٍ `RETIRE`: %d** — حكمٌ صادرٌ (`RPR-02` §١١ · `GAP-67`) لا عطبٌ مفتوح\n",
           count($vendorRuled));
    foreach ($vendorRuled as $x) { echo "       ✔ " . $x . "\n"; }
}
echo "\n";
printf("  ① منفذُ العرضِ واللغةُ والاتجاه — عبرَ قشرةِ المستند\n");
printf("     **بلا قشرة: %d** من %d\n", count($noShell), $scanned);
foreach (array_slice($noShell, 0, 8) as $x) { echo "        ⛔ " . $x . "\n"; }
printf("  ② تسمياتٌ مفقودةٌ في الحقولِ المرئيّة: **%d** سطحًا\n", count($noLabel));
foreach (array_slice($noLabel, 0, 8) as $x) { echo "        ⛔ " . $x . "\n"; }
printf("  ③ صورةٌ بلا `alt`: **%d** سطحًا\n", count($noAlt));
foreach (array_slice($noAlt, 0, 8) as $x) { echo "        ⛔ " . $x . "\n"; }
printf("  ④ تجاوزُ الإطار: جداولٌ %d · مؤشراتُ لفٍّ أفقيّ %d\n", $tables, $wrapHints);
echo "     ◆ **وهذا مؤشِّرٌ لا حكم**: اللفُّ قد يأتي من ورقةِ نمطٍ مشتركةٍ لا من السطح،\n";
echo "       ⛔ فلا يُرفع إلى عيبٍ بلا تصييرٍ حيّ — و`RPR-03` §٩ يجعل الطبقةَ الثانيةَ يدويّة.\n";

$defects = count($noShell) + count($noLabel) + count($noAlt);
echo "\n────────────────────────────────────────────────────────────\n";
printf("**المسحُ منفَّذٌ على %d سطحٍ · عيوبٌ بنيويّةٌ مرصودة: %d**\n", $scanned, $defects);
echo $defects === 0
    ? "🟢 **صفرُ عيبٍ بنيويٍّ في الطبقةِ الأولى**\n"
    : "◆ والعيوبُ مسمّاةٌ بأسطحِها — ⛔ ولا تُرفع نسبةٌ بإخراجِ سطحٍ من المقام\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $wrongTwin
        ? "🟢 **`includes/role_board.php` لا يبلغ القشرةَ — فلو قِيس بالاسمِ لظهر عيبًا وهو جزءٌ مُضمَّنٌ لا شاشة**\n"
        : "✘ **التوأمُ الخطأُ لا يُنتج فرقًا** — فالاختبارُ لا يُثبت شيئًا\n";
    exit($wrongTwin ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٩ — المسحُ البنيويُّ الآليّ\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "أسطحٌ مُصيَّرةٌ ممسوحة **" . $scanned . "** · ردُّ `JSON` خارجَ المقامِ بحكمِه **"
        . $json . "** · تعذّر حلُّ مسارِه **" . $unresolved . "**.\n\n";
    $o .= "## أربعةُ فحوصٍ بنصِّ §٩\n\n| الفحص | المرصود |\n|---|---|\n";
    $o .= "| ① منفذُ العرضِ واللغةُ والاتجاه (‏عبرَ القشرة) | **" . count($noShell) . "** بلا قشرة |\n";
    $o .= "| ② تسمياتٌ مفقودةٌ في الحقولِ المرئيّة | **" . count($noLabel) . "** |\n";
    $o .= "| ③ صورةٌ بلا `alt` | **" . count($noAlt) . "** |\n";
    $o .= "| ④ تجاوزُ الإطار | جداولٌ " . $tables . " · مؤشراتُ لفٍّ " . $wrapHints . " (‏مؤشِّرٌ لا حكم) |\n\n";
    if ($noShell) {
        $o .= "### بلا قشرة\n\n";
        foreach ($noShell as $x) { $o .= "- `" . $x . "`\n"; }
        $o .= "\n";
    }
    $o .= "## عطبان في المسحِ نفسِه أُصلحا قبلَ اعتمادِ رقمِه\n\n";
    $o .= "1. **قفزةٌ واحدةٌ لا تكفي**: `viewport`/`lang`/`dir` تصدر من `inheader.php`،\n";
    $o .= "   و`u13_screen_kit.php` يشمله نيابةً. فمسحٌ يقف عند قفزةٍ واحدةٍ أبلغ عن\n";
    $o .= "   **٧٩ سطحًا «بلا قشرة» وهي سليمة**. ⇒ يُتتبَّع الاشتمالُ حتى ثلاثِ قفزات.\n";
    $o .= "2. **الاسمُ يلتبس**: **١٥ اسمًا من ٦٢٣ له توأمٌ في مجلَّدَين**. فقِيس\n";
    $o .= "   `SCR-0402` «بلا قشرة» والمقروءُ `includes/role_board.php` — **جزءٌ مُضمَّنٌ\n";
    $o .= "   لا شاشة** — والشاشةُ `main/role_board.php` سليمة. ⇒ **المسارُ أوّلًا**.\n\n";
    $o .= "⛔ **والطبقةُ الثانيةُ يدويّةٌ ولا تُغني عنها هذه** — §٩: «فالعشرُ عيّنةٌ عميقةٌ\n";
    $o .= "لا دليلٌ وحيدٌ على ستمئةِ شاشة».\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_STRUCTURAL_SCAN.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_STRUCTURAL_SCAN.md\n";
}
