<?php
/**
 * tools/cmp03_extras_apply.php — مطبّق قرارات المالك في الزائد (CMP-03 الموجة ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ docs/CMP03_EXTRAS_DECISION_ar.csv بعد ملء المالك عمودَ القرار الأخير —
 * «المقرَّر يُنفَّذ وغير المقرَّر لا يُلمس» (عرف دورة CMP-01):
 *   «يُلغى» → رأس العمود يُقاعَد: class="ems-extra-retired none" data-fn="1"
 *     فينطوي عن العرض بآلية الطي المركزية ويبقى في المصدر موثقًا — والنزع
 *     الفيزيائي (th+خلايا الحلقة) مهمةُ شاشةٍ تلحق بورقة العمل هذه.
 *   «يدخل المستند» → لا مساس بالنظام؛ يُجمع في ملحق docs/CMP03_DOC_ADDENDUM_ar.csv
 *     ليُدمج في SCR-DES (ملف المالك) فيصير العمود موثقًا لا زائدًا.
 *
 * التشغيل: php tools/cmp03_extras_apply.php [--apply]   (الافتراض معاينة)
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);

$f = $ROOT . '/docs/CMP03_EXTRAS_DECISION_ar.csv';
if (!is_file($f)) { echo "لا ورقة قرار — ولّدها أولًا: php tools/cmp03_extras_sheet.php\n"; exit(1); }
$fh = fopen($f, 'r');
$head = fgetcsv($fh); // يتخطى BOM في أول خلية ضمنيًّا عبر التطبيع أدناه
$retire = array(); $addendum = array(); $undecided = 0;
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < 8) { continue; }
    $decision = trim((string) $r[7]);
    if ($decision === '') { $undecided++; continue; }
    if (mb_strpos($decision, 'يلغ') !== false || mb_strpos($decision, 'يُلغ') !== false) {
        $retire[$r[0]][] = $r[4];
    } else {
        $addendum[] = $r;
    }
}
fclose($fh);

$nRet = 0;
foreach ($retire as $real => $labels) {
    $path = $ROOT . '/' . $real;
    $src = @file_get_contents($path);
    if ($src === false) { echo "⚠ $real لا يُقرأ\n"; continue; }
    $orig = $src;
    foreach ($labels as $lbl) {
        $ln = cmp03_norm($lbl);
        $done = false;
        $src = preg_replace_callback('/<th\b([^>]*)>((?:\s*<[a-z]+\b[^>]*>\s*<\/[a-z]+>)*\s*)([^<]*?)(\s*)<\/th>/su',
            function ($mm) use (&$done, $ln) {
                if ($done || cmp03_norm($mm[3]) !== $ln || trim($mm[3]) === '') { return $mm[0]; }
                if (strpos($mm[1], 'ems-extra-retired') !== false) { $done = true; return $mm[0]; }
                $done = true;
                $attrs = $mm[1];
                if (preg_match('/class="([^"]*)"/', $attrs, $mc)) {
                    $attrs = str_replace($mc[0], 'class="' . $mc[1] . ' ems-extra-retired none" data-fn="1"', $attrs);
                } else {
                    $attrs .= ' class="ems-extra-retired none" data-fn="1"';
                }
                return '<th' . $attrs . '>' . $mm[2] . $mm[3] . $mm[4] . '</th>';
            }, $src);
        if ($done) { $nRet++; echo ($APPLY ? '✔' : '⏸') . " قعّد: $real ← «$lbl»\n"; }
        else { echo "⚠ $real: لم يوجد رأس «$lbl» نصًّا صرفًا — يدويًّا\n"; }
    }
    if ($APPLY && $src !== $orig) {
        file_put_contents($path, $src);
        $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
        if (strpos((string) $lint, 'No syntax errors') === false) { file_put_contents($path, $orig); echo "‼ تراجُع $real\n"; }
    }
}

if ($addendum && $APPLY) {
    $af = $ROOT . '/docs/CMP03_DOC_ADDENDUM_ar.csv';
    $fh = fopen($af, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('الشاشة القانونية','اسم الشاشة','الورقة المالكة','العمود يُضاف للمستند'));
    foreach ($addendum as $r) { fputcsv($fh, array($r[1], $r[2], $r[3], $r[4])); }
    fclose($fh);
    echo "✎ ملحق المستند: docs/CMP03_DOC_ADDENDUM_ar.csv (" . count($addendum) . ")\n";
}
echo "\nقرارات إلغاء: $nRet · دخول للمستند: " . count($addendum) . " · بلا قرار (لا تُلمس): $undecided\n";
