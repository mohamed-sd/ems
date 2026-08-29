<?php
/**
 * 2028_01_02_rpr02_sod_test_registry_down.php — نقضُ موضعِ سجلِّ ربطِ الفواحص
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط `repair01_sod_test_registry` بقواعدِه الثلاث.
 * ⛔ وجداولُ `repair01_w*_sod` لا تُمَسّ — **فهي مصادرُ فصلِ الواجباتِ نفسِها**،
 *    سابقةٌ لهذه الهجرةِ ومولِّدةٌ لوثائقِ `W*_SOD.md`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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
$conn->query("DROP TABLE IF EXISTS `repair01_sod_test_registry`");
echo "  ✔ أُسقط `repair01_sod_test_registry`\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقض موضعُ السجلِّ — ومصادرُ فصلِ الواجباتِ سالمة\n";
