<?php
/**
 * tools/cmp03_synonyms.php — مطبّق CMP-03 الموجة ① (توحيد المرادفات)
 * ───────────────────────────────────────────────────────────────────────────
 * «العمود موجود في النظام باسمٍ آخر — والتوحيد تسميةٌ لا بناء» (ورقة 03).
 * لكل زوجِ مرادفةٍ يحكم به المقارنُ (تقاطع جذوع ≥60٪ بلا متعدد-لواحد) يعيد
 * تسميةَ رأس النظام إلى تسمية المستند الحرفية — **الاسم الفائز اسم المستند
 * دائمًا** (توصية المالك ② المعتمدة). التسمية داخل صف ترويسة الجدول الرئيس
 * وحده؛ ورأسٌ يحوي وسومًا أو PHP داخله يُترك للمعالجة اليدوية.
 *
 * التشغيل: php tools/cmp03_synonyms.php [--apply] [--screen=file.php]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$APPLY = in_array('--apply', $argv, true);
$onlyScreen = null;
foreach ($argv as $a) { if (strpos($a, '--screen=') === 0) { $onlyScreen = substr($a, 9); } }

/* استثناءات محسومة (سجل الأحكام J-03/J-04 في docs/CMP03_EXECUTION_LOG_ar.md):
   شاشتا مستندٍ مدمجتان بملفٍ واحدٍ تطلبان اسمين لعمودٍ واحد — تسميةُ المالك
   القانوني تثبت ولا يتأرجح الرأس، والزوج الباقي مرادفٌ بنيوي بقرار. المفتاح:
   real_path => [norm(الرأس الثابت) => 1] */
$SKIP = array(
    'Contracts/tax_invoices.php'        => array(cmp03_norm('فترة الإقرار') => 1, cmp03_norm('الفترة') => 1),
    'Suppliers/supplier_capacity.php'   => array(cmp03_norm('نسبة الجاهزية الدنيا') => 1, cmp03_norm('نسبة الجاهزية') => 1),
    'ActivityLogs/activity_logs.php'    => array(cmp03_norm('القيمة') => 1),
    'Operations/distribution_space.php' => array(cmp03_norm('التاريخ') => 1, cmp03_norm('من تاريخ') => 1,
                                                 cmp03_norm('تاريخ السريان') => 1, cmp03_norm('إلى تاريخ') => 1,
                                                 cmp03_norm('من الموقع') => 1, cmp03_norm('الموقع') => 1),
    'Suppliers/shares_coverage.php'     => array(cmp03_norm('العقد') => 1, cmp03_norm('العقد العميل') => 1),
    'Finance/entitlement_gate.php'      => array(cmp03_norm('رقم الحدث المولَّد') => 1, cmp03_norm('رقم الحدث') => 1),
);

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);

$byFile = array();
foreach ($screens as $cf => $sc) {
    if ($onlyScreen !== null && $cf !== $onlyScreen) { continue; }
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $byFile[$map[$cf]['real_path']][$cf] = $sc;
}

$manual = array(); $nRen = 0; $nFiles = 0;

foreach ($byFile as $real => $docScreens) {
    $path = $ROOT . '/' . $real;
    $src = @file_get_contents($path);
    if ($src === false) { continue; }
    $orig = $src;
    $fileRen = array();

    foreach ($docScreens as $cf => $sc) {
        $heads = array();
        /* الرؤوس من المصدر الجاري (قد تغيّره تسمياتٌ سابقة في الملف نفسه) */
        if (preg_match_all('/<th\b[^>]*>(.*?)<\/th>/su', $src, $m)) {
            foreach ($m[1] as $h) {
                $h2 = preg_replace('/<\?php.*?\?>/su', '', $h);
                $h2 = trim(strip_tags($h2));
                if ($h2 !== '' && mb_strlen($h2) < 60 && !preg_match('/^[#\d\W]+$/u', $h2)) {
                    $heads[cmp03_norm($h2)] = $h2;
                }
            }
        }
        if (!$heads) { continue; }
        $j = cmp03_judge($sc['cols'], $heads);
        if (!$j['syn']) { continue; }
        $docN = array(); foreach ($sc['cols'] as $c) { $docN[cmp03_norm($c)] = $c; }

        foreach ($j['syn'] as $dn => $hn) {
            if (isset($SKIP[$real][$hn]) || isset($SKIP[$real][$dn])) { continue; } // محسوم بقرار
            $docLabel = $docN[$dn];
            $sysLabel = null;
            /* أعد استخراج النص الأصلي لرأس النظام */
            foreach ($heads as $nk => $origTxt) { if ($nk === $hn) { $sysLabel = $origTxt; break; } }
            if ($sysLabel === null) { continue; }
            /* استبدال أول <th> نصُّه الظاهر يطابق رأسَ النظام — نصٌّ صرفٌ أو
               مسبوقٌ بوسومٍ مكتملةٍ فارغةِ النص (أيقونات <i>…</i> ونحوها) */
            $done = false;
            $src = preg_replace_callback(
                '/(<th\b[^>]*>)((?:\s*<[a-z]+\b[^>]*>\s*<\/[a-z]+>)*\s*)([^<]*?)(\s*)(<\/th>)/su',
                function ($mm) use (&$done, $hn, $docLabel) {
                    if ($done) { return $mm[0]; }
                    if (cmp03_norm($mm[3]) !== $hn || trim($mm[3]) === '') { return $mm[0]; }
                    $done = true;
                    return $mm[1] . $mm[2] . $docLabel . $mm[4] . $mm[5];
                }, $src);
            if ($done) {
                $fileRen[] = "$sysLabel ← $docLabel";
                $nRen++;
            } else {
                $manual[] = "$real — «{$sysLabel}» رأسٌ بوسومٍ داخلية، يُوحَّد يدويًّا إلى «{$docLabel}»";
            }
        }
    }

    if ($fileRen) {
        $nFiles++;
        echo ($APPLY ? '✔ ' : '⏸ ') . "$real: " . implode(' · ', $fileRen) . "\n";
        if ($APPLY && $src !== $orig) {
            file_put_contents($path, $src);
            $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
            if (strpos((string) $lint, 'No syntax errors') === false) {
                echo "‼ خطأ صياغة بعد التوحيد في $real — تراجُع\n";
                file_put_contents($path, $orig);
            }
        }
    }
}

if ($manual) {
    echo "\n── للمعالجة اليدوية (" . count($manual) . "):\n";
    foreach (array_unique($manual) as $m2) { echo "   ⚠ $m2\n"; }
}
echo "\n" . ($APPLY ? 'وُحّدت' : 'ستُوحَّد') . " $nRen تسميةً في $nFiles ملفًّا.\n";
