<?php
/** tools/rpr02a_q.php — منفِّذُ استعلامٍ للقراءةِ في جولةِ RPR-02-A (أداةُ قياسٍ لا تكتب) */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$sql = $argv[1] ?? '';
if ($sql === '-') { $sql = stream_get_contents(STDIN); }
$res = $conn->query($sql);
if ($res === false) { fwrite(STDERR, "SQL ERROR: {$conn->error}\n"); exit(1); }
if ($res === true) { echo "OK affected=" . $conn->affected_rows . "\n"; exit(0); }
$first = true;
while ($r = $res->fetch_assoc()) {
    if ($first) { echo implode("\t", array_keys($r)) . "\n"; $first = false; }
    echo implode("\t", array_map(fn($v) => $v === null ? 'NULL' : str_replace(["\n","\t"], [' ',' '], (string)$v), $r)) . "\n";
}
if ($first) { echo "(0 rows)\n"; }
