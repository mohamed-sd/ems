<?php
/**
 * tools/actions_col_rollback.php — التراجعُ عن جولةِ «الإجراءاتُ أوّلًا».
 *
 * لماذا نسخٌ ملفّيٌّ لا `git checkout`؟
 *   لأن الشجرةَ كانت تحوي **٢٢ ملفًّا معدَّلًا غيرَ ملتزَمٍ** قبل الجولة (موجةُ
 *   الأنماطِ السطرية)، وفيها خمسةٌ من ملفاتِ هذه الجولةِ نفسِها. فاستعادةٌ من
 *   الالتزامِ تمحو عملَ غيري معها. واللقطةُ تعيد ما لمستُه أنا وحدَه.
 *
 *   php tools/actions_col_rollback.php --check   ← ماذا سيُستعاد
 *   php tools/actions_col_rollback.php --apply   ← استعادةُ الحالِ قبل الجولة
 *   php tools/actions_col_rollback.php --redo    ← إعادةُ الجولةِ بعد التراجع
 */

$ROOT  = dirname(__DIR__);
$STAMP = 'actions_col_20260817';
$BK    = $ROOT . '/storage/backups/' . $STAMP;          // الحالُ قبلَ الجولة
$AF    = $ROOT . '/storage/backups/' . $STAMP . '_after'; // الحالُ بعدَها

$check = in_array('--check', $argv, true);
$apply = in_array('--apply', $argv, true);
$redo  = in_array('--redo',  $argv, true);
if (!$check && !$apply && !$redo) { fwrite(STDERR, "الاستعمال: --check | --apply | --redo\n"); exit(2); }
if (!is_dir($BK)) { fwrite(STDERR, "لا لقطةَ في $BK\n"); exit(2); }

/** يعدّ ملفاتِ لقطةٍ ما ويُرجع مساراتِها النسبية. */
function acr_list($dir) {
    $out = [];
    if (!is_dir($dir)) return $out;
    $base = strtr($dir, DIRECTORY_SEPARATOR, '/');
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!$f->isFile()) continue;
        $p = strtr($f->getPathname(), DIRECTORY_SEPARATOR, '/');
        $out[] = substr($p, strlen($base) + 1);
    }
    sort($out);
    return $out;
}

$files = acr_list($BK);
echo 'ملفاتُ الجولة: ' . count($files) . "\n";

if ($check) {
    $diff = 0;
    foreach ($files as $rel) {
        $now = @file_get_contents($ROOT . '/' . $rel);
        $was = file_get_contents($BK . '/' . $rel);
        if ($now !== $was) { $diff++; echo "  ≠ $rel\n"; }
    }
    echo "\nمختلفٌ عن اللقطة: $diff\n";
    echo is_dir($AF) ? "لقطةُ «بعد» موجودة — `--redo` متاح\n" : "لا لقطةَ «بعد» — نفِّذ `--apply` أولًا فتُحفظ\n";
    exit(0);
}

if ($apply) {
    /* احفظِ الحالَ الراهنَ أولًا فيبقى الرجوعُ ذا مصراعين */
    if (!is_dir($AF)) {
        foreach ($files as $rel) {
            $p = $AF . '/' . $rel;
            @mkdir(dirname($p), 0777, true);
            @copy($ROOT . '/' . $rel, $p);
        }
        echo "حُفظت لقطةُ «بعد» في storage/backups/{$STAMP}_after\n";
    }
    $n = 0;
    foreach ($files as $rel) {
        if (@copy($BK . '/' . $rel, $ROOT . '/' . $rel)) $n++;
        else echo "  ✘ تعذّرت استعادةُ $rel\n";
    }
    echo "استُعيد $n ملفًّا إلى ما قبلَ الجولة\n";
    exit(0);
}

if ($redo) {
    if (!is_dir($AF)) { fwrite(STDERR, "لا لقطةَ «بعد» — لا شيءَ يُعاد\n"); exit(2); }
    $n = 0;
    foreach (acr_list($AF) as $rel) {
        if (@copy($AF . '/' . $rel, $ROOT . '/' . $rel)) $n++;
    }
    echo "أُعيد $n ملفًّا إلى حالِ ما بعدَ الجولة\n";
    exit(0);
}
