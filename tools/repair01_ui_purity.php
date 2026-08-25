<?php
/**
 * tools/repair01_ui_purity.php
 *   الفواحصُ الأربعةُ لنقاءِ لغةِ الواجهة — REPAIR01 · W06 §٤-٨
 * ═══════════════════════════════════════════════════════════════════════════
 * ① **تشكيلٌ عربيّ** — النتيجةُ المطلوبةُ صفر.
 * ② **مصطلحٌ تقنيٌّ ظاهر** — `PK` · `FK` · `Grain` · `Derived` ·
 *    `Source of Truth` · `Migration` · `SQL` · `API` · `Rule ID` · واسمُ جدولٍ
 *    أو خدمةٍ أو ملفٍّ أو رمزٍ داخليٍّ أو لفظٍ لاتينيٍّ حرٍّ في نصٍّ عربيّ.
 * ③ **معادلةٌ ظاهرة** — `=` · `×` · `Σ` · `SUM` · مرجعُ حقل.
 * ④ **طولٌ زائد** لاسمِ زرٍّ أو حقلٍ أو تبويبٍ أو بندِ قائمة —
 *    **والحدُّ من `repair01_w6_thresholds` لا من الشيفرة** (§٥).
 *
 * ◆ **والمقامُ محوران لا واحد**: صفوفُ المصادرِ المُصيَّرةِ **والنصُّ المُصيَّرُ
 *   نفسُه**. فصفٌّ نقيٌّ في جدولٍ لا يعني نصًّا نقيًّا على الشاشة (المُصيِّرُ
 *   يضمُّ ويلفّ ويزيد)، ونصٌّ نقيٌّ لا يعني مصدرًا نقيًّا (‏قد لا يُصيَّر اليوم
 *   ويُصيَّر غدًا).
 *
 * التشغيل: php tools/repair01_ui_purity.php [--samples]
 * الخروج : 0 نقيٌّ · 1 فيه عيب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/lib/repair01_w6_scan.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$SAMPLES = in_array('--samples', $argv, true);

echo "══ الفواحصُ الأربعةُ لنقاءِ لغةِ الواجهة — REPAIR01 · W06 ══\n\n";

/* ── محورُ ①: صفوفُ المصادرِ المُصيَّرة ────────────────────────────────── */
$src = repair01_w6_scan_sources($conn);
$sDia = 0; $sTech = 0; $sEq = 0; $sDecor = 0; $sRows = 0;
echo "① المصادرُ المُصيَّرة\n";
printf("  %-34s %7s %7s %7s %7s %7s\n", 'المصدر', 'صفوف', 'تشكيل', 'تقني', 'معادلة', 'زخرفة');
foreach ($src as $k => $m) {
    $sRows += $m['rows']; $sDia += $m['dia']; $sTech += $m['tech']; $sEq += $m['eq']; $sDecor += $m['decor'];
    printf("  %-34s %7d %7d %7d %7d %7d\n", $k, $m['rows'], $m['dia'], $m['tech'], $m['eq'], $m['decor']);
    if ($SAMPLES) { foreach ($m['samples'] as $s) { echo "      · $s\n"; } }
}
printf("  %-34s %7d %7d %7d %7d %7d\n", 'المجموع', $sRows, $sDia, $sTech, $sEq, $sDecor);

/* ── محورُ ②: النصُّ المُصيَّرُ نفسُه ─────────────────────────────────────── */
$ren = repair01_w6_scan_rendered($ROOT, $conn);
$cyc = repair01_w6_scan_cycle($conn);
echo "\n② النصُّ المُصيَّر\n";
printf("  سايدبار: %d نصًّا متمايزًا · تشكيل %d · تقني %d · معادلة %d · زخرفة %d\n",
    $ren['n'], count($ren['dia']), count($ren['tech']), count($ren['eq']), count($ren['decor']));
printf("  سطرُ الدورة: %d شاشةً تُصيِّره · تشكيل %d · تقني %d · معادلة %d\n",
    $cyc['screens'], count($cyc['dia']), count($cyc['tech']), count($cyc['eq']));
if ($SAMPLES) {
    foreach (array_slice(array_merge($ren['dia'], $ren['tech'], $ren['eq']), 0, 8) as $s) { echo "      · $s\n"; }
    foreach (array_slice(array_merge($cyc['dia'], $cyc['tech'], $cyc['eq']), 0, 8) as $s) { echo "      · $s\n"; }
}

/* ── ④ الطولُ الزائد ───────────────────────────────────────────────────── */
$len = repair01_w6_scan_length($ROOT, $conn);
echo "\n④ الطولُ الزائد\n";
if (!$len['limits']) {
    echo "  ⚠ لا عتبةَ مسجَّلةٌ في repair01_w6_thresholds — لم يُقَس (ولا يُعَدُّ صفرًا)\n";
} else {
    printf("  الحدود: %s\n", implode(' · ', array_map(
        function ($k, $v) { return $k . '=' . $v; }, array_keys($len['limits']), $len['limits'])));
    printf("  مفحوصٌ %d · زائدٌ %d\n", $len['checked'], count($len['over']));
    foreach (array_slice($len['over'], 0, $SAMPLES ? 20 : 5) as $s) { echo "      · $s\n"; }
}

/* ── حَوكمةُ السجلّ: اسمٌ خارجَه · متقاعدٌ حيٌّ · رمزٌ خامٌّ · تسريبُ تقنيّ ── */
$unreg = repair01_w6_unregistered($ROOT, $conn);
$dep   = repair01_w6_deprecated_live($conn);
$raw   = repair01_w6_raw_codes($ROOT, $conn);
$leak  = repair01_w6_dev_only_leak($ROOT, $conn);
echo "\n⑤ حَوكمةُ السجلّ\n";
printf("  اسمٌ مُصيَّرٌ خارجَ السجلّ: %d من %d\n", count($unreg['missing']), $unreg['rendered']);
foreach (array_slice($unreg['missing'], 0, $SAMPLES ? 20 : 5) as $s) { echo "      · $s\n"; }
printf("  مسمًّى متقاعدٌ حيّ: %d (‏المتقاعدُ المسجَّل %d)\n", count($dep['alive']), $dep['checked']);
foreach (array_slice($dep['alive'], 0, $SAMPLES ? 20 : 5) as $s) { echo "      · $s\n"; }
printf("  رمزٌ داخليٌّ خامٌّ في نصٍّ مُصيَّر: %d (‏القاموس %d رمزًا)\n", count($raw['raw']), $raw['dict']);
foreach (array_slice($raw['raw'], 0, $SAMPLES ? 20 : 5) as $s) { echo "      · $s\n"; }
printf("  مسمًّى تقنيٌّ (DEVELOPER_ONLY) مُصيَّرٌ للمستخدم: %d من %d\n", count($leak['leaked']), $leak['dev']);
foreach (array_slice($leak['leaked'], 0, 5) as $s) { echo "      · $s\n"; }

/* ◆ **الطولُ دَينٌ مُعلَنٌ لا خرقٌ دستوريّ** (‏§٦): البوّابةُ تشترط صفرًا في
     خمسةٍ — تشكيلٌ · مصطلحٌ تقنيٌّ · معادلةٌ · اسمٌ خارجَ السجلِّ · متقاعدٌ حيّ —
     ولا تشترطه في الطول. فالطولُ يُعلَن بعددِه ويُقارَن بسقفِه المسجَّلِ في
     `W6-D-08`، ويسقط إن نما. وعدُّه هنا خرقًا يجعل الأداةَ تخالف بوّابتَها. */
$bad = $sDia + $sTech + $sEq
     + count($ren['dia']) + count($ren['tech']) + count($ren['eq'])
     + count($cyc['dia']) + count($cyc['tech']) + count($cyc['eq'])
     + count($unreg['missing']) + count($dep['alive'])
     + count($raw['raw']) + count($leak['leaked']);

echo "\n" . str_repeat('─', 78) . "\n";
printf("تشكيلٌ ظاهر %d · مصطلحٌ تقنيٌّ ظاهر %d · معادلةٌ ظاهرة %d · طولٌ زائد %d · اسمٌ خارجَ السجلّ %d · مسمًّى متقاعدٌ حيٌّ %d\n",
    $sDia + count($ren['dia']) + count($cyc['dia']),
    $sTech + count($ren['tech']) + count($cyc['tech']),
    $sEq + count($ren['eq']) + count($cyc['eq']),
    count($len['over']), count($unreg['missing']), count($dep['alive']));
echo 'الحكم: ' . ($bad === 0 ? "نقيّ ✔\n" : "فيه عيبٌ ✘ (المجموع $bad)\n");
exit($bad === 0 ? 0 : 1);
