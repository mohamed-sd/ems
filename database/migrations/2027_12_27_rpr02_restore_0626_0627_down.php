<?php
/** 2027_12_27_..._down.php — نقضُ الإعادة · ⛔ ويُعيد تسعةَ مراجعَ يتيمةٍ إلى دفترِ الأسطح */
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
echo "⚠ النقضُ يُعيد تسعةَ مراجعَ يتيمةٍ في `repair01_surfaces` — وهو تراجعٌ عن تصحيح\n";
$conn->query("DELETE FROM repair01_screen_registry WHERE screen_id IN ('SCR-0626','SCR-0627')");
echo "✔ نُقضت الإعادة\n";
