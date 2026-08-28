<?php
/** 2027_12_28_..._down.php — نزعُ وسمِ RETIRE · ⛔ ويُرجع w135/G2 أحمرَ */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
echo "⚠ نزعُ الوسمِ يُرجع `w135/G2` أحمرَ — وهو تراجعٌ عن حكمٍ مسجَّل\n";
$conn->query("UPDATE repair01_screen_registry SET ownership_verdict = ''
               WHERE ghost_verdict = 'VENDOR_NOT_A_SCREEN'");
echo "✔ نُزع الوسم\n";
