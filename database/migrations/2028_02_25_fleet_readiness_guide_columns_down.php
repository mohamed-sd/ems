<?php
/**
 * 2028_02_25_fleet_readiness_guide_columns_down.php — العكسُ المسوّى
 * @migration-objects: drop the FLEET-19/20 columns from asset_readiness
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
foreach (array('record_code','unit_key','project_ref','business_model','meter_start','meter_end',
               'meter_hours','jackhammer_hours','extra_hours','maint_down_hours','reliab_down_hours',
               'oper_down_hours','total_hours','accumulated_hours','tons_moved','meters_done',
               'fuel_consumed','fuel_rate_hour','meter_vs_timesheet','statement_source',
               'confidence_grade','reviewer','approved_at','available_hours','operating_hours',
               'unplanned_down','performance_pct','oee_pct','down_days','readiness_state') as $c) {
    $conn->query("ALTER TABLE `asset_readiness` DROP COLUMN `{$c}`");
}
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
