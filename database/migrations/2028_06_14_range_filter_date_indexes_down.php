<?php
/**
 * تراجعُ 2028_06_14 — يُسقط فهارسَ المدى وحدَها.
 * ◆ **بالوسمِ لا بالتخمين**: `ix_rng_*` وسمُ هذه الجولةِ حصرًا، و`ix_hot_*`
 *   وسمُ الجولةِ السابقة — فتراجعُ إحداهما لا يمسّ الأخرى.
 * التشغيل: php database/migrations/2028_06_14_range_filter_date_indexes_down.php
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

$drop = array();
$r = $conn->query("SELECT DISTINCT TABLE_NAME t, INDEX_NAME i FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME LIKE 'ix\_rng\_%'");
while ($r && ($x = $r->fetch_assoc())) { $drop[] = $x; }

$n = 0; $err = 0;
foreach ($drop as $d) {
    if ($conn->query('ALTER TABLE `' . $d['t'] . '` DROP INDEX `' . $d['i'] . '`')) { $n++; }
    else { $err++; }
}
echo "  ✔ فهارسُ مدًى أُسقطت: {$n}" . ($err ? " · تعذّر {$err}" : '') . "\n";
