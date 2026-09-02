<?php
if (php_sapi_name() !== 'cli') { exit("CLI\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
require_once $ROOT . '/tools/lib/xlsx_io.php';
$f = $argv[1];
$wb = xlsx_read($f);
foreach ($wb as $sheet => $rows) {
    echo "=== SHEET: $sheet · rows=" . count($rows) . " ===\n";
    $lim = isset($argv[2]) ? (int) $argv[2] : 6;
    foreach ($rows as $i => $r) {
        if ($i >= $lim) break;
        echo "  [$i] " . implode(' | ', array_map(function($x){ return mb_substr((string)$x,0,34); }, $r)) . "\n";
    }
}
