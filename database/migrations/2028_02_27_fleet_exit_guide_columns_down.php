<?php
/**
 * 2028_02_27_fleet_exit_guide_columns_down.php — العكسُ المسوّى
 * @migration-objects: drop FLEET-21/22 columns from asset_exit
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
foreach (array('exit_code','meter_reading','withdrawing_party','justification',
               'decision_notice_ref','expected_days','return_service_ref','actual_out_days',
               'deviation_days','contract_unit_effect','client_notified','substitute_asset',
               'disposal_code','disposal_decision_date','actual_exit_date','disposal_reason',
               'technical_state_ref','buyer_receiver','buyer_relation','final_meter',
               'net_proceeds','currency_ref','cost_ref','accum_depr_ref','book_value_ref',
               'gain_loss','sale_minutes_ref','journal_ref','title_transfer_ref',
               'owners_approval','unit_vacated','reviewer','approved_by','approved_at',
               'record_basis','src_ref','data_state') as $c) {
    $conn->query("ALTER TABLE `asset_exit` DROP COLUMN `{$c}`");
}
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
