<?php
/**
 * 2028_02_26_fleet_assignment_movement_columns_down.php — العكسُ المسوّى
 * @migration-objects: drop FLEET-12/13 columns
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
$P = array(
 'asset_assignment' => array('assign_code','unit_key','client_contract_ref','client_no','project_no',
   'business_model','machine_no_contract','project_asset_code','activation_ref','substitute_asset',
   'replace_sla_days','gap_days','executed_hours','reviewer','approved_by','approved_at',
   'record_basis','src_ref','data_state'),
 'fleet_equipment_history' => array('move_code','move_seq','from_site','from_project','from_unit',
   'to_site','to_project','to_unit','move_reason','meter_at_depart','meter_at_arrive',
   'transfer_request_ref','transfer_order_ref','pre_move_check_ref','post_move_check_ref',
   'transit_damage','out_of_service_days'),
);
foreach ($P as $t => $cs) { foreach ($cs as $c) { $conn->query("ALTER TABLE `{$t}` DROP COLUMN `{$c}`"); } }
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
