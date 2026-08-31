<?php
/**
 * 2028_02_09_universe_wave_anchor_down.php — العكس
 * @migration-objects: alter:repair01_target_universe(match_method -WAVE_ANCHOR)
 * التشغيل: php database/migrations/2028_02_09_universe_wave_anchor_down.php
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
$conn->query("UPDATE repair01_target_universe SET match_method='EXACT_UNIT' WHERE match_method='WAVE_ANCHOR'");
$conn->query("ALTER TABLE repair01_target_universe
    MODIFY match_method ENUM('EXACT_UNIT','EXACT_ANY','COMPOUND_SPLIT','CONTAINMENT_CANDIDATE','NONE') NOT NULL DEFAULT 'NONE'");
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
