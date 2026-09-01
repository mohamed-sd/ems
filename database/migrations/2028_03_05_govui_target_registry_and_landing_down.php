<?php
/**
 * 2028_03_05_govui_target_registry_and_landing_down.php
 * @migration-objects: drop:govui_target_registry + revert nav_placements enum
 * ◆ الردُّ يشترط ألّا يبقى صفٌّ `LANDING_PAGE` — فحذفُ مفردةٍ من `ENUM` وفيها
 *   صفوفٌ يفرّغها صامتًا. فإن وُجدت رُدَّت إلى `MENU_ITEM` أوّلًا **بإعلان**.
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
$r = $conn->query("SELECT COUNT(*) FROM nav_placements WHERE placement_type = 'LANDING_PAGE'");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) {
    echo "  ⟲ {$n} صفَّ LANDING_PAGE يُردُّ إلى MENU_ITEM قبلَ تضييقِ المفردات\n";
    $conn->query("UPDATE nav_placements SET placement_type = 'MENU_ITEM' WHERE placement_type = 'LANDING_PAGE'");
}
$conn->query("ALTER TABLE `nav_placements`
  MODIFY `placement_type` enum('MENU_ITEM','TAB_CHILD','DIRECT_ONLY','PROJECTION','UTILITY','NOT_BUILT') NOT NULL");
$conn->query("DROP TABLE IF EXISTS `govui_target_registry`");
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
