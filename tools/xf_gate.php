<?php
/**
 * tools/xf_gate.php — بوابةُ «البياناتِ الإضافية» (XF-01) · تُقاس حيّةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تقيس النظامَ الحيَّ لا نيّةَ الخطة**: كلُّ شاشةٍ مسجَّلةٍ تُفحص من الملفِّ
 *   ومن القاعدةِ معًا، وترجع 1 عند أيِّ إخفاق — فتصلح خطّافًا قبلَ الالتزام.
 *
 * ◆ **البنودُ السبعةُ ومقامُ كلِّ بند** (فلا رقمَ بلا مصدر):
 *   ① كلُّ عمودٍ `own` له عمودٌ في جدولِه         · المقام: أعمدةُ `own` المسجَّلة
 *   ② وكلُّها NULLable — الاختياريُّ ليس إلزاميًّا   · المقام: نفسُه
 *   ③ كلُّ عمودٍ مسجَّلٍ له رأسٌ موصولٌ في الملفّ   · المقام: أعمدةُ السجلِّ كلُّها
 *   ④ عددُ الرؤوسِ الموصولةِ = عددُ أعمدةِ السجل  · المقام: الشاشاتُ المسجَّلة
 *   ⑤ الشاشةُ تطبع خلايا الصفّ (`ems_xf_tds`)     · المقام: نفسُه
 *   ⑥ الشاشةُ تجمع POST (`ems_xf_collect`)        · المقام: شاشاتٌ فيها `own`
 *   ⑦ وتدمجها في الكتابة (`array_merge` أو ما يعادله) · المقام: نفسُه
 *
 * ◆ **وحارسُ المقامِ الصفريّ**: بندٌ بمقامٍ صفرٍ لا يُعلَن ناجحًا بل `NOT_MEASURED`
 *   — فصفرُ مخالفةٍ على صفرِ صفوفٍ نجاحٌ كاذبٌ يُسكِت البوابةَ عن عطبٍ حقيقيّ.
 *
 * التشغيل: php tools/xf_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/extra_fields.php';
require_once __DIR__ . '/xf_lib.php';

$conn = xf_db($ROOT);
if (!$conn) { exit("⛔ تعذّر الاتصالُ بالقاعدة — لا تُقاس البوابةُ بلا جداولَ حيّة\n"); }

$reg = ems_xf_registry();
if (!$reg) { exit("⚠ لا شاشةَ مسجَّلةً — لا شيءَ يُقاس (NOT_MEASURED)\n"); exit(1); }

$R = array();
function item(&$R, $no, $title, $num, $den, $sample = array()) {
    $verdict = ($den <= 0) ? 'NOT_MEASURED' : (($num === $den) ? 'PASS' : 'FAIL');
    $R[$no] = array('t' => $title, 'v' => $verdict, 'n' => $num, 'd' => $den, 's' => array_slice($sample, 0, 5));
}

$ownOk = 0; $ownDen = 0; $nullOk = 0; $badCol = array(); $badNull = array();
$headOk = 0; $headDen = 0; $badHead = array();
$screenHead = 0; $screenTds = 0; $screenDen = 0; $badTds = array();
$collectOk = 0; $mergeOk = 0; $collectDen = 0; $badCollect = array(); $badMerge = array();

foreach ($reg as $screen => $def) {
    $path = $ROOT . '/' . $screen;
    $src  = is_file($path) ? file_get_contents($path) : '';
    $cols = xf_table_columns($conn, $def['table']);
    $screenDen++;

    $own = 0; $bound = 0; $total = 0;
    foreach ($def['columns'] as $c) {
        $total++;
        $headDen++;
        /* ③ رأسٌ موصولٌ في الملفِّ لهذا المفتاح */
        $needle = "ems_xf_th_attrs(\$XF_SCREEN, '" . $c['key'] . "')";
        if ($src !== '' && strpos($src, $needle) !== false) { $headOk++; $bound++; }
        else { $badHead[] = $screen . ' :: ' . $c['key']; }

        if (!isset($c['kind']) || $c['kind'] !== 'own') { continue; }
        $own++; $ownDen++;
        /* ① العمودُ موجودٌ · ② وNULLable */
        if (!isset($cols[$c['key']])) { $badCol[] = $screen . ' :: ' . $def['table'] . '.' . $c['key']; continue; }
        $ownOk++;
        if ($cols[$c['key']]['Null'] === 'YES') { $nullOk++; }
        else { $badNull[] = $screen . ' :: ' . $def['table'] . '.' . $c['key']; }
    }

    /* ④ كلُّ أعمدةِ الشاشةِ موصولةُ الرؤوس */
    if ($total > 0 && $bound === $total) { $screenHead++; }

    /* ⑤ خلايا الصفّ */
    if ($src !== '' && strpos($src, 'ems_xf_tds(') !== false) { $screenTds++; }
    else { $badTds[] = $screen; }

    /* ⑥⑦ الجمعُ والدمج — لشاشاتِ `own` وحدَها (بلا `own` لا كتابةَ أصلًا) */
    if ($own > 0) {
        $collectDen++;
        if ($src !== '' && strpos($src, 'ems_xf_collect(') !== false) { $collectOk++; }
        else { $badCollect[] = $screen; }
        if ($src !== '' && preg_match('/array_merge\s*\(.*\$xf_values/s', $src)) { $mergeOk++; }
        else { $badMerge[] = $screen; }
    }
}

item($R, 1, 'كلُّ عمودٍ `own` له عمودٌ في جدولِه',              $ownOk,     $ownDen,     $badCol);
item($R, 2, 'وكلُّها NULLable — الاختياريُّ ليس إلزاميًّا',       $nullOk,    $ownDen,     $badNull);
item($R, 3, 'كلُّ عمودٍ مسجَّلٍ له رأسٌ موصولٌ في الملفّ',        $headOk,    $headDen,    $badHead);
item($R, 4, 'شاشاتٌ وُصلت رؤوسُها كاملةً',                       $screenHead,$screenDen,  array());
item($R, 5, 'الشاشةُ تطبع خلايا الصفِّ (`ems_xf_tds`)',          $screenTds, $screenDen,  $badTds);
item($R, 6, 'الشاشةُ تجمع POST (`ems_xf_collect`)',              $collectOk, $collectDen, $badCollect);
item($R, 7, 'وتدمجها في الكتابة (`array_merge … $xf_values`)',   $mergeOk,   $collectDen, $badMerge);

echo "════ بوابةُ «البياناتِ الإضافية» (XF-01) ════\n";
echo "  شاشاتٌ مسجَّلة: " . count($reg) . "\n";
$pass = 0; $fail = 0; $nm = 0;
foreach ($R as $no => $x) {
    $mark = $x['v'] === 'PASS' ? '✔' : ($x['v'] === 'FAIL' ? '✗' : '◌');
    printf("  %s %d) %-48s %d/%d  %s\n", $mark, $no, $x['t'], $x['n'], $x['d'], $x['v']);
    foreach ($x['s'] as $s) { echo "        · {$s}\n"; }
    if ($x['v'] === 'PASS') { $pass++; } elseif ($x['v'] === 'FAIL') { $fail++; } else { $nm++; }
}
echo "──────────────────────────────────────────────────────────\n";
echo "  مرَّ: {$pass}/7 · أخفق: {$fail} · غيرُ مقيس: {$nm}\n";
if ($nm) { echo "  ◌ «غيرُ مقيس» **لا يُحسب مارًّا** — بندٌ بمقامٍ صفرٍ لا يُعلَن ناجحًا\n"; }
if ($fail || $nm) { exit(1); }
echo "✔ البوابةُ خضراءُ — كلُّ عمودٍ مسجَّلٍ اختياريٌّ وموصولٌ ومكتوب\n";
