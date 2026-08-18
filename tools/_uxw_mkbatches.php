<?php
/* tools/_uxw_mkbatches.php — تقسيمُ الشاشاتِ غيرِ المرحَّلةِ دفعاتٍ بالمجلدِ ثم الشدّة */
$csv = $argv[1]; $outDir = $argv[2]; $max = (int)($argv[3] ?: 10);
$rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
array_shift($rows);
$byDir = array();
foreach ($rows as $r) { $byDir[$r[7]][] = $r; }
/* ترتيبُ المجلداتِ بمجموعِ الشدّة تنازليًّا */
uasort($byDir, function ($a, $b) {
    return array_sum(array_column($b, 6)) <=> array_sum(array_column($a, 6));
});
$n = 0; $manifest = array();
foreach ($byDir as $dir => $items) {
    usort($items, function ($a, $b) { return $b[6] <=> $a[6]; });
    /* الشاشاتُ الثقيلةُ (شدّة ≥60) دفعاتٌ أصغر */
    $chunks = array(); $cur = array(); $curSev = 0;
    foreach ($items as $it) {
        $cur[] = $it; $curSev += (int)$it[6];
        if (count($cur) >= $max || $curSev >= 260) { $chunks[] = $cur; $cur = array(); $curSev = 0; }
    }
    if ($cur) $chunks[] = $cur;
    foreach ($chunks as $c) {
        $n++;
        $tag = sprintf('B%02d_%s', $n, preg_replace('/[^A-Za-z0-9]/', '', $dir));
        $path = $outDir . '/_scope_' . $tag . '.txt';
        file_put_contents($path, implode("\n", array_column($c, 0)) . "\n");
        $manifest[] = array('tag' => $tag, 'scope' => 'tools/_scope_' . $tag . '.txt', 'dir' => $dir,
            'files' => array_column($c, 0), 'sev' => array_sum(array_column($c, 6)));
    }
}
file_put_contents($outDir . '/_batches.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($manifest as $m) printf("%-26s %-14s %2d ملف  شدّة %5d\n", $m['tag'], $m['dir'], count($m['files']), $m['sev']);
printf("\nالدفعات: %d · الشاشات: %d\n", count($manifest), array_sum(array_map(function($m){return count($m['files']);}, $manifest)));
