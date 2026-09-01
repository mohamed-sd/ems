<?php
/**
 * 2028_02_21_field_measure_key_per_target_down.php — العكسُ المسوّى
 * @migration-objects: reverse uq_screen_req ⇒ uq_screen
 * ⛔ والعكسُ **قد يفشل بحقٍّ** إن كان الدفترُ يحمل سطحًا بهدفَين — وذلك بعينُه
 *   ما يمنعه المفتاحُ القديم. فالتراجعُ يتطلّب كنسَ الدفترِ أوّلًا، وهو مُعلَنٌ هنا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$conn->query("ALTER TABLE `repair01_field_measure` DROP INDEX `uq_screen_req`");
if (!$conn->query("ALTER TABLE `repair01_field_measure` ADD UNIQUE KEY `uq_screen` (`screen_id`)")) {
    echo "⚠ تعذّر ردُّ المفتاحِ القديم: {$conn->error}\n";
    echo "   السببُ المتوقَّع: الدفترُ يحمل سطحًا بهدفَين — اكنسْه ثمَّ أعِد التراجع.\n";
}
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
