<?php
/** tools/fix_cs12_list_open.php — سردُ ما بقي من تجاهلٍ بلا سببٍ معلَن، بسياقه. */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
require_once $ROOT . '/tools/fix_checks.php';

$scope = array('app/Services/', 'app/Core/', 'includes/');
$n = 0;
foreach (fix_php_files($ROOT) as $rel) {
    $in = false;
    foreach ($scope as $d) { if (strpos($rel, $d) === 0) { $in = true; break; } }
    if (!$in) { continue; }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (strpos($src, 'ems_catch_ignored') === false) { continue; }
    $L = explode("\n", $src);
    foreach ($L as $i => $line) {
        if (!preg_match('/ems_catch_ignored\s*\(\s*\$\w+\s*,[^,]*,\s*([\'"])\s*\1\s*\)/', $line)) { continue; }
        $n++;
        echo '── ' . $rel . ':' . ($i + 1) . "\n";
        for ($k = max(0, $i - 4); $k <= $i; $k++) {
            echo '    ' . mb_substr(trim($L[$k]), 0, 104) . "\n";
        }
        echo "\n";
    }
}
echo "المجموع: {$n}\n";
