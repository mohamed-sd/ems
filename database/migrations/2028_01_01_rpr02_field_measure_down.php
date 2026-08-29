<?php
/**
 * 2028_01_01_rpr02_field_measure_down.php — نقضُ موضعِ دفترِ حقولِ المبنيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط `repair01_field_measure` بقواعدِه الثلاث.
 * ⛔ و`repair01_fields` و`gov_field_class` لا يُمَسّان — الأوّلُ مصدرٌ تصميميٌّ
 *    مستوعَبٌ والثاني مصنِّفُ حساسيّةٍ حوكميّ، **وكلاهما سابقٌ لهذه الهجرة**.
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
$conn->query("DROP TABLE IF EXISTS `repair01_field_measure`");
echo "  ✔ أُسقط `repair01_field_measure`\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقض موضعُ الدفتر — والمصدران التصميميُّ والحوكميُّ سالمان\n";
