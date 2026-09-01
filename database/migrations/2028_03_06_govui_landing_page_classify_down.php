<?php
/**
 * 2028_03_06_govui_landing_page_classify_down.php — العكسُ من القيدِ نفسِه
 * @migration-objects: restore nav_placements.placement_type from govui_wiring_log
 * ◆ يردُّ صفوفَ `LANDING_PAGE` وحدَها إلى `old_type` المسجَّلِ لها — ولا يمسُّ
 *   أحكامَ الوصلِ الأخرى في الدفترِ نفسِه (تُميَّز بـ`new_type = 'LANDING_PAGE'`).
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
$q = $conn->query("SELECT id, target_id, old_type FROM govui_wiring_log
                    WHERE new_type = 'LANDING_PAGE' ORDER BY id DESC");
$n = 0; $ids = array();
while ($q && ($r = $q->fetch_assoc())) {
    $st = $conn->prepare("UPDATE nav_placements SET placement_type = ? WHERE target_id = ?");
    if (!$st) { continue; }
    $st->bind_param('ss', $r['old_type'], $r['target_id']);
    $st->execute(); $st->close();
    $ids[] = (int) $r['id']; $n++;
}
if ($ids) { $conn->query("DELETE FROM govui_wiring_log WHERE id IN (" . implode(',', $ids) . ")"); }
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
