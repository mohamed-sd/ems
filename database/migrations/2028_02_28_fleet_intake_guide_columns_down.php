<?php
/**
 * 2028_02_28_fleet_intake_guide_columns_down.php — العكسُ المسوّى
 * @migration-objects: drop FLEET-03/04/05/11 columns
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
 'asset_intake' => array('request_date','requester_name','need_source','client_contract_ref',
   'project_no','power_source_code','requested_class','requested_spec','requested_count',
   'need_date','operational_justification','impact_if_unmet','resulting_asset_code',
   'activation_code','activation_kind','inspection_ref','work_order_ref','activation_date',
   'activation_meter','activation_site','activation_project','state_before','state_after',
   'readiness_evidence','down_days_before','reviewer','approved_at','record_basis','data_state'),
 'asset_source_check' => array('check_code','power_source_code','supplying_party','party_nature',
   'ownership_proven','ownership_proof_ref','docs_required','docs_received','docs_missing',
   'docs_complete_pct','chassis_matches','chassis_duplicate','reservations','exception_ref',
   'reviewer','approved_by','approved_at','record_basis','src_ref','data_state'),
 'asset_inspection_order' => array('inspection_type','issue_reason','cause_event_ref','issuer_name',
   'issue_date','executor_party','assigned_inspector','target_site','project_ref','priority',
   'inspection_scope','card_no','actual_exec_date','delay_days','reviewer','review_date',
   'approved_by','approved_at','record_basis','src_ref','data_state'),
);
foreach ($P as $t => $cs) { foreach ($cs as $c) { $conn->query("ALTER TABLE `{$t}` DROP COLUMN `{$c}`"); } }
echo "reverted\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
