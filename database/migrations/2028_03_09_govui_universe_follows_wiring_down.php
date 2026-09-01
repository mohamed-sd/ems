<?php
/**
 * 2028_03_09_govui_universe_follows_wiring_down.php — العكسُ من القيدِ نفسِه
 * @migration-objects: restore repair01_target_universe from govui_universe_log
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
$q = $conn->query("SELECT target_uid, old_screen_id, old_verdict, old_witness FROM govui_universe_log ORDER BY id DESC");
$n = 0;
while ($q && ($r = $q->fetch_assoc())) {
    $st = $conn->prepare("UPDATE repair01_target_universe
                             SET screen_id = ?, verdict = ?, verdict_witness = ? WHERE target_uid = ?");
    if (!$st) { continue; }
    $st->bind_param('ssss', $r['old_screen_id'], $r['old_verdict'], $r['old_witness'], $r['target_uid']);
    $st->execute(); $st->close(); $n++;
}
$conn->query("DELETE FROM govui_universe_log");
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
