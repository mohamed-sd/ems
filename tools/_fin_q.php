<?php
if (php_sapi_name() !== 'cli') { exit("CLI\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$c = $GLOBALS['conn']; $c->set_charset('utf8mb4');
$r = $c->query(getenv('Q'));
if (!$r) { echo "ERR: " . $c->error . "\n"; exit(1); }
if ($r === true) { echo "OK\n"; exit(0); }
while ($z = $r->fetch_row()) { echo implode(" | ", array_map(function($x){return $x===null?'NULL':$x;}, $z)) . "\n"; }
