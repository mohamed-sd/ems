<?php
/**
 * tools/repair01_edc_filter_adopt.php — تبنّي صندوقِ الفلترةِ المعياريِّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: مكوّنٌ واحدٌ يُبنى مرّةً · والقبولُ
 * `ELIGIBLE_LIST_SURFACES_WITHOUT_CANONICAL_FILTER = 0`.
 *
 * ◆ **والحقنُ سطرانِ لا إعادةُ كتابة**: `require_once` للمكوّن، ونداءٌ واحدٌ
 *   **قبلَ الجدولِ مباشرةً** يشير إليه بمُحدِّده. ⛔ **ولا يُمَسُّ منطقُ الشاشةِ
 *   ولا استعلامُها ولا صفوفُها.**
 *
 * ⛔ **وجدولٌ بلا مُعرِّفٍ يُعطى واحدًا مشتقًّا من اسمِ الملفِّ لا عشوائيًّا** —
 *   فالمُعرِّفُ العشوائيُّ يتغيّر كلَّ تشغيلٍ ويكسر أيَّ إشارةٍ إليه.
 *
 * ◆ **والتشغيلُ لا يُكرِّر**: يتخطّى ما تبنّى سلفًا. وهذا يجعل الأداةَ قابلةً
 *   للإعادةِ بلا ضرر.
 *
 * التشغيل:
 *   php tools/repair01_edc_filter_adopt.php --limit=3     ← تجريبٌ
 *   php tools/repair01_edc_filter_adopt.php --apply       ← الكلّ
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$LIMIT = 0;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $LIMIT = (int) substr($a, 8); $APPLY = true; } }

$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}

$done = 0; $skip = 0; $miss = 0; $n = 0;
$r = $conn->query("SELECT screen_file FROM repair01_screen_registry
                    WHERE origin REGEXP '^W[0-9]+$' AND on_disk = 1 ORDER BY screen_file");
while ($r && ($x = $r->fetch_row())) {
    $b = basename($x[0]);
    if (!isset($idx[$b])) { continue; }
    $f = $idx[$b];
    $c = (string) @file_get_contents($f);
    if (strpos($c, 'ems-filters') !== false || strpos($c, 'ems_filter_box') !== false) { $skip++; continue; }

    /* الجدولُ الأوّلُ الذي تُصيَّر صفوفُه بحلقة — هو القائمة */
    if (!preg_match('~<table\b[^>]*>~i', $c, $tm, PREG_OFFSET_CAPTURE)) { $miss++; continue; }
    $pos = $tm[0][1]; $tag = $tm[0][0];

    /* مُعرِّفٌ مشتقٌّ من اسمِ الملفِّ — ثابتٌ عبر التشغيلات */
    if (preg_match('~\bid\s*=\s*["\']([^"\']+)["\']~i', $tag, $im)) { $tid = $im[1]; $newTag = $tag; }
    else {
        $tid = 'emsList_' . preg_replace('~[^a-z0-9]+~i', '_', substr($b, 0, -4));
        $newTag = preg_replace('~^<table\b~i', '<table id="' . $tid . '"', $tag, 1);
    }

    /* موضعُ الحقن: أوّلُ سطرٍ يبدأ قبلَ الجدولِ — نحافظ على الإزاحة */
    $lineStart = strrpos(substr($c, 0, $pos), "\n");
    $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
    $indent = '';
    if (preg_match('~^[ \t]*~', substr($c, $lineStart, $pos - $lineStart), $sm)) { $indent = $sm[0]; }

    /* ⚠ **`$ROOT` بفواصلِ ويندوز و`$f` بفواصلِ يونكس** — فالاستبدالُ يخفق
         ويبقى المسارُ كاملًا، فيُعَدُّ خمسَ درجاتٍ بدل واحدة. **ونسختي الأولى
         حقنت `/../../../../../` في ثلاثِ شاشات**. ⇒ يُوحَّد الفاصلُ أوّلًا. */
    $rootU = strtr($ROOT, DIRECTORY_SEPARATOR, '/');
    $rel   = str_repeat('/..', substr_count(substr($f, strlen($rootU) + 1), '/'));
    $inc   = $rel . '/includes/ems_filter_box.php';
    /* ⛔ **و`php -l` فحصُ نحوٍ لا فحصُ مسار**: مرَّ على مسارٍ خاطئٍ يُفني وقتَ
         التشغيل. فيُتحقَّق من أنَّ المُضمَّنَ **موجودٌ فعلًا** قبل الكتابة. */
    if (!is_file(dirname($f) . $inc)) { echo "  ✘ $b — مسارُ المكوّنِ لا يحلُّ: $inc\n"; continue; }

    $inject = $indent . "<?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */\n"
            . $indent . "require_once __DIR__ . '" . $inc . "';\n"
            . $indent . "ems_filter_box(array('for' => '#" . $tid . "')); ?>\n";

    $out = substr($c, 0, $lineStart) . $inject . $indent
         . $newTag . substr($c, $pos + strlen($tag));
    $n++;
    if (!$APPLY) { continue; }
    if ($LIMIT && $done >= $LIMIT) { break; }
    file_put_contents($f, $out);
    /* ⛔ ولا يُترَك ملفٌّ مكسور: يُفحص فورًا ويُردُّ إن كسر */
    $chk = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($f) . ' 2>&1', $chk, $rc);
    if ($rc !== 0) { file_put_contents($f, $c); echo "  ✘ رُدَّ $b — " . implode(' ', $chk) . "\n"; continue; }
    $done++;
    printf("  ✔ %-34s #%s\n", $b, $tid);
}
echo "\n────────────────────────────────────────────────────────────\n";
printf("مؤهَّلٌ للحقن: %d · حُقن: %d · متبنٍّ سلفًا: %d · بلا جدولٍ مُصيَّر: %d\n", $n, $done, $skip, $miss);
if (!$APPLY) { echo "◆ عرضٌ فقط — أضِف `--apply` أو `--limit=N`\n"; }
