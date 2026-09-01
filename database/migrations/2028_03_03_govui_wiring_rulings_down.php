<?php
/**
 * 2028_03_03_govui_wiring_rulings_down.php — العكسُ من القيدِ لا من قائمةٍ ثانية
 * @migration-objects: restore nav_placements from govui_wiring_log
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
$q = $conn->query("SELECT target_id, old_route, old_screen_id, old_type FROM govui_wiring_log ORDER BY id DESC");
$n = 0;
while ($q && ($r = $q->fetch_assoc())) {
    $st = $conn->prepare("UPDATE nav_placements SET route = ?, screen_id = ?, placement_type = ? WHERE target_id = ?");
    if (!$st) { continue; }
    $st->bind_param('ssss', $r['old_route'], $r['old_screen_id'], $r['old_type'], $r['target_id']);
    $st->execute(); $st->close(); $n++;
}
$conn->query("DELETE FROM govui_wiring_log");
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
