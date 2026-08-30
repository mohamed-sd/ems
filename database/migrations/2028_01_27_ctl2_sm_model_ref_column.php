<?php
/**
 * 2028_01_27_ctl2_sm_model_ref_column.php — قناةُ آلةِ حالةِ المعاملة
 * ◆ شرطُ النوعِ الثاني للمعاملةِ (أمرُ الضبطِ ④): آلةُ حالةٍ مسمّاةٌ من
 *   الدليلِ قبل البناءِ — ⛔ ولا تُختلَق آلةٌ عند البناء. عمودُ مرجعِ
 *   النموذجِ وعمودُ شاهدِه، يملؤهما مسارُ ربطِ النماذجِ من عناوينِ الدليل.
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

foreach (array(
    'sm_model_ref' => "VARCHAR(200) NULL COMMENT 'مرجع آلة حالة المعاملة من الدليل المعماري'",
    'sm_witness'   => "VARCHAR(400) NULL COMMENT 'اقتباس موضع النموذج من الدليل بلقطته'",
) as $col => $def) {
    $has = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE '$col'");
    if ($has && $has->num_rows > 0) { echo "  ✔ `$col` موجودٌ سلفًا\n"; continue; }
    if (!$conn->query("ALTER TABLE `repair01_requirements` ADD COLUMN `$col` $def")) { exit("✘ {$conn->error}\n"); }
    echo "  ✔ أُضيف `$col`\n";
}
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ قناةُ آلةِ الحالةِ مفتوحة\n";
