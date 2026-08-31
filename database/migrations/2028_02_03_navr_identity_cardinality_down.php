<?php
/**
 * 2028_02_03_navr_identity_cardinality_down.php — العكس
 * @migration-objects: drop nav_placements.target_id · nav_targets · uq_one_primary · restore uq_role_binding
 * التشغيل: php database/migrations/2028_02_03_navr_identity_cardinality_down.php
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$conn->query("ALTER TABLE `nav_placements` DROP FOREIGN KEY `fk_np_target`");
$conn->query("ALTER TABLE `nav_placements` DROP COLUMN `target_id`");
$conn->query("DROP TABLE IF EXISTS `nav_targets`");
$conn->query("ALTER TABLE `nav_ws_roles` DROP INDEX `uq_one_primary`, DROP COLUMN `primary_role`");
$conn->query("ALTER TABLE `nav_ws_roles` ADD UNIQUE KEY `uq_role_binding` (`role_id`, `binding`)");
echo "✔ عُكست الهويّةُ والكاردينالية\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
