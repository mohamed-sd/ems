<?php
/**
 * tools/cmp03_probe.php — مسح تمهيدي لموجة CMP-03 ②: أنماط جداول الشاشات المقارنة
 * يصنف كل شاشة مقارنة: عدد الجداول · thead · serverSide · columns: في JS · فئة datatable
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

require __DIR__ . '/cmp03_lib.php'; // مكتبة مشتركة: قراءة المستند وأحكام المقارنة

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);

$stats = array();
foreach ($screens as $cf => $sc) {
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $path = $ROOT . '/' . $map[$cf]['real_path'];
    $src = @file_get_contents($path);
    if ($src === false) { echo "!! لا يقرأ: $cf\n"; continue; }
    $tables = preg_match_all('/<table\b/i', $src);
    $theads = preg_match_all('/<thead\b/i', $src);
    $server = preg_match('/serverSide\s*:\s*true/', $src) ? 'serverSide' : '';
    $jscols = preg_match('/columns\s*:\s*\[/', $src) ? 'jsCols' : '';
    $nodt   = preg_match('/no-datatable|data-no-dt/', $src) ? 'noDT' : '';
    $ths    = preg_match_all('/<th\b/i', $src);
    $key = ($server ?: 'php') . '/' . ($jscols ?: 'domCols') . '/' . ($nodt ?: 'auto');
    $stats[$key][] = "$cf(t$tables,h$theads,th$ths)";
}
foreach ($stats as $k => $list) {
    echo "── $k: " . count($list) . "\n   " . implode(' ', array_slice($list, 0, 12)) . (count($list) > 12 ? ' …' : '') . "\n";
}
