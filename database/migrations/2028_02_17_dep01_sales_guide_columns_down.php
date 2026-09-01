<?php
/**
 * 2028_02_17_dep01_sales_guide_columns_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-01
 * مولَّدةٌ من `tools/gov_exec_dept_build.php --emit` على مواصفةِ الإدارة —
 * وأسماءُ الأعمدةِ تعليقُها اسمُ الحقلِ في ورقةِ الدليلِ حرفًا.
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

$SQL = array(
    <<<'SQL'
ALTER TABLE `contract_amendments`
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `doc_no`,
    DROP COLUMN `renewal_cycle`,
    DROP COLUMN `doc_text_adaptation`,
    DROP COLUMN `event_adaptation`,
    DROP COLUMN `signed_on`,
    DROP COLUMN `contractual_from`,
    DROP COLUMN `contractual_to`,
    DROP COLUMN `executive_from`,
    DROP COLUMN `executive_to`,
    DROP COLUMN `doc_target`,
    DROP COLUMN `uom_ref`,
    DROP COLUMN `cycles_effect`,
    DROP COLUMN `evidence_level`,
    DROP COLUMN `doc_state`,
    DROP COLUMN `amend_notes`
SQL,
    <<<'SQL'
ALTER TABLE `monthly_performance`
    DROP COLUMN `row_code`,
    DROP COLUMN `container_key`,
    DROP COLUMN `contract_ref`,
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `business_model`,
    DROP COLUMN `renewal_no`,
    DROP COLUMN `month_no`,
    DROP COLUMN `month_from`,
    DROP COLUMN `month_to`,
    DROP COLUMN `line_ref`,
    DROP COLUMN `machines_count`,
    DROP COLUMN `monthly_target`,
    DROP COLUMN `executed_qty`,
    DROP COLUMN `uom_ref`,
    DROP COLUMN `operating_hours`,
    DROP COLUMN `trips_count`,
    DROP COLUMN `deducted_standby`,
    DROP COLUMN `deducted_work`,
    DROP COLUMN `added_work`,
    DROP COLUMN `added_standby`,
    DROP COLUMN `measured_qty`,
    DROP COLUMN `executed_achievement`,
    DROP COLUMN `measured_achievement`,
    DROP COLUMN `computed_revenue_usd`,
    DROP COLUMN `computed_revenue_sdg`,
    DROP COLUMN `statement_source`,
    DROP COLUMN `perf_notes`,
    DROP COLUMN `billed_qty`,
    DROP COLUMN `billed_usd`,
    DROP COLUMN `unbilled_executed`,
    DROP COLUMN `unclaimed_revenue`,
    DROP COLUMN `invoice_ref`,
    DROP COLUMN `contract_currency`,
    DROP COLUMN `revenue_columns_state`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `sal_monthly_container`
SQL,
    <<<'SQL'
ALTER TABLE `contract_commitments`
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `business_model`,
    DROP COLUMN `contract_no`,
    DROP COLUMN `renewal_no`,
    DROP COLUMN `cycle_kind`,
    DROP COLUMN `contractual_from`,
    DROP COLUMN `contractual_to`,
    DROP COLUMN `executive_from`,
    DROP COLUMN `executive_to`,
    DROP COLUMN `cycle_months`,
    DROP COLUMN `cycle_capacity`,
    DROP COLUMN `uom_ref`,
    DROP COLUMN `elapsed_target`,
    DROP COLUMN `recorded_monthly_plan`,
    DROP COLUMN `executed_qty`,
    DROP COLUMN `measured_qty`,
    DROP COLUMN `achievement_pct`,
    DROP COLUMN `coverage_gap`,
    DROP COLUMN `cycle_state`,
    DROP COLUMN `evidence_level`,
    DROP COLUMN `cycle_notes`,
    DROP COLUMN `source_cycle_state`,
    DROP COLUMN `previous_version`,
    DROP COLUMN `version_kind`,
    DROP COLUMN `changed_vs_previous`,
    DROP COLUMN `change_events_count`,
    DROP COLUMN `trips_derived`,
    DROP COLUMN `operating_hours_derived`,
    DROP COLUMN `cycle_pattern`,
    DROP COLUMN `measure_unit`,
    DROP COLUMN `contracted_machines_count`,
    DROP COLUMN `min_guarantee`,
    DROP COLUMN `billing_threshold`,
    DROP COLUMN `new_container_reason`
SQL,
    <<<'SQL'
ALTER TABLE `contract_monthly_plan`
    DROP COLUMN `row_code`,
    DROP COLUMN `container_key`,
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `business_model`,
    DROP COLUMN `renewal_no`,
    DROP COLUMN `month_no`,
    DROP COLUMN `month_start`,
    DROP COLUMN `month_end`,
    DROP COLUMN `line_ref`,
    DROP COLUMN `machines_count`,
    DROP COLUMN `unit_basis`,
    DROP COLUMN `line_monthly_target`,
    DROP COLUMN `full_monthly_target`,
    DROP COLUMN `uom_ref`,
    DROP COLUMN `target_source`,
    DROP COLUMN `notes`
SQL,
    <<<'SQL'
ALTER TABLE `contracts`
    DROP COLUMN `contract_code`,
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `business_model`,
    DROP COLUMN `contract_evidence_level`,
    DROP COLUMN `obl_fuel`,
    DROP COLUMN `obl_oils`,
    DROP COLUMN `obl_maintenance`,
    DROP COLUMN `obl_spare_parts`,
    DROP COLUMN `obl_operators`,
    DROP COLUMN `obl_housing`,
    DROP COLUMN `obl_mobilization`,
    DROP COLUMN `obl_demobilization`,
    DROP COLUMN `obl_insurance`,
    DROP COLUMN `obl_damage`,
    DROP COLUMN `obl_waiting`,
    DROP COLUMN `obl_breakdown`,
    DROP COLUMN `obl_violations`,
    DROP COLUMN `obl_min_hours`,
    DROP COLUMN `obl_operating_guarantee`,
    DROP COLUMN `obl_site_schedule`,
    DROP COLUMN `obl_violation_deduction`,
    DROP COLUMN `obl_unpaid_stoppage`,
    DROP COLUMN `obl_termination`,
    DROP COLUMN `obl_renewal`,
    DROP COLUMN `obl_governing_law`,
    DROP COLUMN `obl_specific_bearing`,
    DROP COLUMN `obl_specific_terms`,
    DROP COLUMN `obl_silent_items`,
    DROP COLUMN `obl_fill_state`,
    DROP COLUMN `obl_evidence_level`,
    DROP COLUMN `obl_source_text`,
    DROP COLUMN `obl_derivation_basis`,
    DROP COLUMN `obl_notes`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
ALTER TABLE `contractequipments`
    DROP COLUMN `line_code`,
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `business_model`,
    DROP COLUMN `monthly_unit_basis`,
    DROP COLUMN `price_version_from`,
    DROP COLUMN `price_state`,
    DROP COLUMN `pricing_basis`,
    DROP COLUMN `price_source_text`,
    DROP COLUMN `shortfall_rule`,
    DROP COLUMN `mix_valid_from`,
    DROP COLUMN `container_key`,
    DROP COLUMN `target_source`,
    DROP COLUMN `notes`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
ALTER TABLE `fin_precontract_review`
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `project_no`,
    DROP COLUMN `final_offer_match`,
    DROP COLUMN `scope_review`,
    DROP COLUMN `prices_review`,
    DROP COLUMN `quantities_review`,
    DROP COLUMN `currency_ref`,
    DROP COLUMN `payment_terms`,
    DROP COLUMN `advance_terms`,
    DROP COLUMN `guarantee_terms`,
    DROP COLUMN `penalty_terms`,
    DROP COLUMN `client_obligations`,
    DROP COLUMN `commercial_risks`,
    DROP COLUMN `open_notes`,
    DROP COLUMN `sign_readiness`,
    DROP COLUMN `closed_date`,
    DROP COLUMN `contract_ref`,
    DROP COLUMN `notes`,
    DROP COLUMN `container_key`,
    DROP COLUMN `evidence_level`,
    DROP COLUMN `retro_value_basis`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
ALTER TABLE `activities`
    DROP COLUMN `client_no`,
    DROP COLUMN `client_name`,
    DROP COLUMN `project_no`,
    DROP COLUMN `opportunity_no`,
    DROP COLUMN `contract_ref`,
    DROP COLUMN `next_action`,
    DROP COLUMN `next_action_date`,
    DROP COLUMN `container_key`,
    DROP COLUMN `evidence_level`,
    DROP COLUMN `retro_value_basis`
SQL,
);
$n = 0;
foreach ($SQL as $s) {
    if (!$conn->query($s)) {
        $msg = $conn->error;
        if (stripos($msg, "check that column") !== false || stripos($msg, "doesn't exist") !== false) { continue; }
        exit("⛔ {$msg}\n  في: " . substr($s, 0, 120) . "\n");
    }
    $n++;
}
echo "✔ {$n} جملةً نُفِّذت\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
