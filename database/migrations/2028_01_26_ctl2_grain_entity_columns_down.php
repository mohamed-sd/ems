<?php
/**
 * 2028_01_26_ctl2_grain_entity_columns_down.php — التراجع: نزعُ عمودَي الكيان
 */
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
foreach (array('entity_witness', 'grain_entity') as $col) {
    $has = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE '$col'");
    if (!$has || $has->num_rows === 0) { echo "  ✔ `$col` غيرُ موجود\n"; continue; }
    if (!$conn->query("ALTER TABLE `repair01_requirements` DROP COLUMN `$col`")) { exit("✘ {$conn->error}\n"); }
    echo "  ✔ نُزع `$col`\n";
}
echo "✔ تراجُعُ قناةِ الكيانِ تامّ\n";
