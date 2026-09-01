<?php
/**
 * 2028_03_01_sup_contract_lines_guide_columns_down.php — العكسُ المسوّى
 * @migration-objects: drop SUP-12 columns from supplier_contract_lines
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
foreach (array('slot_code','slot_sequence','client_no','business_model','contract_no','renewal_no',
               'container_key','supplier_no','supplier_name','supplier_contract_code','line_type',
               'slot_type','continuity_class','slots_for_line','inferred_role','slot_monthly_basis',
               'supplier_months_in_cycle','elapsed_months','cycle_months_total','unit_months',
               'supplier_share','monthly_target','primary_units_required','primary_available',
               'standby_available','primary_gap','primary_active','equipment_deficit_flag',
               'equipment_coverage_pct','standby_reliance','executed_qty','achievement_pct',
               'share_valid_from','share_valid_to','supplier_unit_price','sale_unit_price',
               'unit_margin_val','negative_margin_flag','slot_state','evidence_level',
               'client_total_obligation','share_of_obligation_pct','deficit_surplus','notes',
               'contract_code_read','sale_currency_read','margin_currency_fit','idle_share_flag',
               'last_slot_activity') as $c) {
    $conn->query("ALTER TABLE `supplier_contract_lines` DROP COLUMN `{$c}`");
}
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
