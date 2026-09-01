<?php
/**
 * 2028_02_12_dep04_fleet_guide_tables_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-04
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
DROP TABLE IF EXISTS `flt_financed_asset_recon`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_register_operation_recon`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_owner_reconciliation`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_numbering_bridge`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_code_reconciliation`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_use_right_range`
SQL,
    <<<'SQL'
ALTER TABLE `fleet_depreciation_profile`
    DROP COLUMN `rate_basis`,
    DROP COLUMN `standard_source`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_dashboard_kpi`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_management_decision`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_open_point`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_source_conflict`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_external_auditor_note`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_exception_register`
SQL,
    <<<'SQL'
ALTER TABLE `mnt_breakdown`
    DROP COLUMN `failure_start_date`,
    DROP COLUMN `failure_start_time`,
    DROP COLUMN `discovery_source`,
    DROP COLUMN `report_ref`,
    DROP COLUMN `operator_at_failure`,
    DROP COLUMN `site_location`,
    DROP COLUMN `unit_key`,
    DROP COLUMN `meter_reading`,
    DROP COLUMN `downtime_start`,
    DROP COLUMN `referred_to_maintenance`,
    DROP COLUMN `referral_date`,
    DROP COLUMN `diagnosis`,
    DROP COLUMN `root_cause`,
    DROP COLUMN `return_service_date`,
    DROP COLUMN `return_service_ref`,
    DROP COLUMN `downtime_end`,
    DROP COLUMN `downtime_hours`,
    DROP COLUMN `operator_liability`,
    DROP COLUMN `investigation_ref`,
    DROP COLUMN `bearer_party`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `review_date`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `record_basis`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `data_state`
SQL,
    <<<'SQL'
ALTER TABLE `fleet_equipment_history`
    DROP COLUMN `change_no`,
    DROP COLUMN `change_seq`,
    DROP COLUMN `change_time`,
    DROP COLUMN `change_reason`,
    DROP COLUMN `cause_event`,
    DROP COLUMN `prev_state_days`,
    DROP COLUMN `meter_reading`,
    DROP COLUMN `changed_by_name`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `record_basis`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `data_state`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_technical_state`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_daily_operating_log`
SQL,
    <<<'SQL'
ALTER TABLE `fleet_equipment_component`
    DROP COLUMN `component_code`,
    DROP COLUMN `description`,
    DROP COLUMN `manufacturer`,
    DROP COLUMN `meter_at_install`,
    DROP COLUMN `expected_life_hours`,
    DROP COLUMN `current_meter`,
    DROP COLUMN `consumption_pct`,
    DROP COLUMN `removal_reason`,
    DROP COLUMN `moved_to_asset`,
    DROP COLUMN `work_order_ref`,
    DROP COLUMN `is_capitalized`,
    DROP COLUMN `capitalization_ref`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `record_basis`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `data_state`,
    DROP COLUMN `notes`
SQL,
    <<<'SQL'
ALTER TABLE `equipment_documents`
    DROP COLUMN `is_mandatory`,
    DROP COLUMN `days_to_expiry`,
    DROP COLUMN `coverage_value`,
    DROP COLUMN `bearer_party`,
    DROP COLUMN `storage_place`,
    DROP COLUMN `renewal_owner`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `record_basis`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `data_state`
SQL,
    <<<'SQL'
ALTER TABLE `equipments`
    DROP COLUMN `class_code`,
    DROP COLUMN `old_code_main`,
    DROP COLUMN `old_code_alt`,
    DROP COLUMN `project_asset_code`,
    DROP COLUMN `power_source_code`,
    DROP COLUMN `purchase_state`,
    DROP COLUMN `supplier_code`,
    DROP COLUMN `supplier_contract_ref`,
    DROP COLUMN `finance_ref`,
    DROP COLUMN `ifrs_class`,
    DROP COLUMN `chassis_state`,
    DROP COLUMN `sticker_no`,
    DROP COLUMN `parent_asset_code`,
    DROP COLUMN `link_type`,
    DROP COLUMN `purchase_date`,
    DROP COLUMN `receipt_date`,
    DROP COLUMN `entry_basis`,
    DROP COLUMN `first_operation_site`,
    DROP COLUMN `technical_class`,
    DROP COLUMN `current_project`,
    DROP COLUMN `operating_party`,
    DROP COLUMN `current_unit_key`,
    DROP COLUMN `last_meter_reading`,
    DROP COLUMN `last_meter_date`,
    DROP COLUMN `capacity_ref`,
    DROP COLUMN `exit_date`,
    DROP COLUMN `exit_reason`,
    DROP COLUMN `exit_record_ref`,
    DROP COLUMN `evidence_level`,
    DROP COLUMN `file_completeness_pct`,
    DROP COLUMN `missing_links`,
    DROP COLUMN `verification_state`,
    DROP COLUMN `exception_flag`,
    DROP COLUMN `confidence_grade`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `record_basis`,
    DROP COLUMN `card_src_ref`,
    DROP COLUMN `card_data_state`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_inspection_card`
SQL,
    <<<'SQL'
ALTER TABLE `equipments_types`
    DROP COLUMN `class_code`,
    DROP COLUMN `type_en`,
    DROP COLUMN `main_category`,
    DROP COLUMN `sub_category`,
    DROP COLUMN `policy_note`,
    DROP COLUMN `ifrs_class`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `flt_power_source`
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
