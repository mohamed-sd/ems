<?php
/**
 * tools/repair01_edc_filter_eligibility.php — من يستحقُّ صندوقَ فلترةٍ أصلًا؟
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: يُبنى `Canonical Shared Filter Component` **مرّةً
 * واحدة**، والقبولُ `ELIGIBLE_LIST_SURFACES_WITHOUT_CANONICAL_FILTER = 0`.
 *
 * ◆ **وكلمةُ `ELIGIBLE` في نصِّ الحكمِ ليست حشوًا**: **سطحٌ بلا قائمةٍ لا يحتاج
 *   فلترةً** — واستمارةُ إدخالٍ أو لوحةُ مؤشّراتٍ أو صفحةُ تفصيلٍ لا يُفلتَر فيها
 *   شيء. **ومقامٌ يضمُّ غيرَ المؤهَّلِ يجعل الهدفَ `= 0` مستحيلًا** فيُترَك مفتوحًا
 *   إلى الأبد، أو يُغلَق بحقنِ مكوّنٍ في صفحاتٍ لا تعرضه.
 *
 * ⛔ **والأهليّةُ تُقاس من الكودِ لا من الاسم**: يعرض جدولًا أو `DataTable`
 *   ويقرأ صفوفًا متعدّدة. **واسمٌ فيه «سجل» أو «تقرير» دعوى لا دليل.**
 *
 * التشغيل: php tools/repair01_edc_filter_eligibility.php [--md]
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
$MD = in_array('--md', $argv, true);

$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}

$E = array(); $N = array(); $has = 0;
$r = $conn->query("SELECT screen_file, route, canonical_label_ar, owner_code
                     FROM repair01_screen_registry
                    WHERE origin REGEXP '^W[0-9]+$' AND on_disk = 1 ORDER BY screen_file");
while ($r && ($x = $r->fetch_assoc())) {
    $b = basename($x['screen_file']);
    if (!isset($idx[$b])) { continue; }
    $c = (string) @file_get_contents($idx[$b]);
    /* الفلترُ المعياريُّ: مكوّنٌ مستدعًى **أو** ترميزٌ بالبنيةِ الموثَّقة */
    if (strpos($c, 'ems_filter_box') !== false || strpos($c, 'ems-filters') !== false) { $has++; continue; }

    /* ═══ الإشارةُ الصادقة: **حلقةٌ داخلَ الجدول** ══════════════════════════
       ⚠ **وطرفانِ فاسدانِ سبقاها**: اشتراطُ استعلامٍ خامٍّ استبعد ٨٣ سطحًا
         تقرأ بخدمة · ثمَّ `<table>` أو `foreach` **في أيِّ موضعٍ** شمل ١٠٧
         كلَّها — و`foreach` يُصيِّر خياراتِ قائمةٍ منسدلةٍ أيضًا، و`<table>`
         قد يأتي من قالبٍ مشترَك.
       ⇒ **فالقائمةُ أن تُصيَّر الصفوفُ داخلَ الجدولِ نفسِه** — وهذا يُقاس
         بموضعِ الحلقةِ من `<table … </table>` لا بوجودِ كليهما. */
    $tbl = false;
    if (preg_match_all('~<table\b.*?</table>~si', $c, $tm)) {
        foreach ($tm[0] as $blk) {
            if (preg_match('~(while\s*\(|foreach\s*\(|<\?(php|=)\s*foreach)~i', $blk)) { $tbl = true; break; }
        }
    }
    /* أو جدولٌ يُبنى في جافاسكربت من مصدرٍ متعدِّد (`DataTable` بـ`data`/`ajax`) */
    if (!$tbl && preg_match('~DataTable\s*\(\s*\{[^}]*(data|ajax)\s*:~si', $c)) { $tbl = true; }
    $loop = $tbl;
    /* ⚠ **نسختي الأولى استبعدت ٨٣ سطحًا بوسمِ «صفٌّ واحد»** — وفيها `committees`
         و`breaches` وهي قوائمُ بلا شكّ. **والسببُ أنَّ الملفَّ لا يحوي `SELECT`
         أصلًا فهو يقرأ بخدمة**، فصار غيابُ الاستعلامِ الخامِّ دليلَ صفٍّ واحد.
         ⇒ **والوسمُ كان خاطئًا والمنطقُ خاطئًا معًا.** فحلقةُ التصييرِ وحدَها
         تُثبت التعدُّد، **والاستبعادُ لا يقع إلّا بدليلٍ موجَبٍ على الوحدانيّة**:
         استعلامٌ حاضرٌ كلُّه مقيَّدٌ بـ`LIMIT 1`. */
    $sel  = preg_match_all('~\bSELECT\b~i', $c);
    $lim1 = preg_match_all('~LIMIT\s+1\b~i', $c);
    $one  = ($sel > 0 && $lim1 >= $sel);
    $why  = array();
    if (!$tbl)  { $why[] = 'لا جدول'; }
    if (!$loop) { $why[] = 'لا حلقة تصيير'; }
    if ($one)   { $why[] = 'كل استعلاماته LIMIT 1'; }
    $many = !$one;

    $row = array($b, $x['route'], $x['owner_code'], $x['canonical_label_ar']);
    if ($tbl && $loop && $many) { $E[] = $row; }
    else { $row[] = implode(' · ', $why); $N[] = $row; }
}

echo "\n═══ أهليّةُ صندوقِ الفلترة — من الكودِ لا من الاسم ═══\n";
printf("  يستعمل `ems-filters` سلفًا: %d\n", $has);
printf("  **مؤهَّلٌ بلا صندوق: %d**\n", count($E));
printf("  غيرُ مؤهَّلٍ (‏لا يحتاج): %d\n\n", count($N));

$byOwner = array();
foreach ($E as $x) { $byOwner[$x[2]] = (isset($byOwner[$x[2]]) ? $byOwner[$x[2]] : 0) + 1; }
arsort($byOwner);
echo "  المؤهَّلُ موزَّعًا على الإدارات:\n";
foreach (array_slice($byOwner, 0, 8, true) as $o => $n) { printf("     %-9s %d\n", $o, $n); }

echo "\n  عيّنةٌ من غيرِ المؤهَّلِ — بسببِ استبعادِه:\n";
foreach (array_slice($N, 0, 6) as $x) { printf("     %-30s %s\n", $x[0], $x[4]); }

if ($MD) {
    $o  = "# أهليّةُ صندوقِ الفلترةِ — البندُ ⑦\n\n";
    $o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_edc_filter_eligibility.php --md`\n";
    $o .= "> **حكمُ المالك**: مكوّنٌ معياريٌّ واحدٌ يُبنى مرّةً · والقبولُ\n";
    $o .= "> `ELIGIBLE_LIST_SURFACES_WITHOUT_CANONICAL_FILTER = 0`.\n";
    $o .= "> **و«المؤهَّل» يُقاس من الكود**: جدولٌ + حلقةُ تصييرٍ + استعلامُ مجموعة.\n\n";
    $o .= sprintf("| الحال | العدد |\n|---|---:|\n| يستعمل `ems-filters` سلفًا | %d |\n", $has);
    $o .= sprintf("| **مؤهَّلٌ بلا صندوق** | **%d** |\n| غيرُ مؤهَّلٍ — لا يحتاج | %d |\n\n", count($E), count($N));
    $o .= "## المؤهَّلُ بلا صندوق\n\n| الشاشة | الإدارة | الاسم |\n|---|---|---|\n";
    foreach ($E as $x) { $o .= sprintf("| `%s` | %s | %s |\n", $x[0], $x[2], $x[3]); }
    $o .= "\n## غيرُ المؤهَّلِ — ولكلٍّ سببُ استبعادِه\n\n| الشاشة | السبب |\n|---|---|\n";
    foreach ($N as $x) { $o .= sprintf("| `%s` | %s |\n", $x[0], $x[4]); }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/EDC_FILTER_ELIGIBILITY.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/EDC_FILTER_ELIGIBILITY.md\n";
}
