<?php
/**
 * 2028_03_15_drill_migration_set_hash_down.php — العكس
 * @migration-objects: drop col dr_drills.migration_set_hash
 * ◆ العمودُ وصفيٌّ بحتٌ لا يعتمد عليه صفٌّ قائم — فإسقاطُه يردُّ الحاجبَ إلى
 *   قاعدةِ الختمِ وحدَها (وهي ما يجري على المحاضرِ التي بلا بصمة).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
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

$q = $conn->query("SHOW COLUMNS FROM dr_drills LIKE 'migration_set_hash'");
if ($q && $q->num_rows) {
    if ($conn->query("ALTER TABLE dr_drills DROP COLUMN migration_set_hash")) { echo "أُسقط العمود\n"; }
    else { echo "⛔ " . $conn->error . "\n"; }
} else {
    echo "= العمودُ غيرُ موجودٍ أصلًا\n";
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
