<?php
/**
 * 2028_02_14_dep12_site_guide_tables_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-12
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
DROP TABLE IF EXISTS `site_reference_registry`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_closure_item`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_suspension`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_day_close_report`
SQL,
    <<<'SQL'
ALTER TABLE `tre_petty_expense`
    DROP COLUMN `expense_no`,
    DROP COLUMN `site_ref`,
    DROP COLUMN `expense_item`,
    DROP COLUMN `currency_ref`,
    DROP COLUMN `field_justification`,
    DROP COLUMN `local_supplier`,
    DROP COLUMN `charged_to`,
    DROP COLUMN `over_limit`,
    DROP COLUMN `override_approval_ref`,
    DROP COLUMN `treasury_settlement_ref`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_request_batch`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_supply_request`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_request_item`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_state_change_request`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_shift_handover`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_day_approval`
SQL,
    <<<'SQL'
ALTER TABLE `ops_stop_register`
    DROP COLUMN `stop_code`,
    DROP COLUMN `reason_tree_ref`,
    DROP COLUMN `billing_effect`,
    DROP COLUMN `contract_unit_effect`,
    DROP COLUMN `decision_doc`,
    DROP COLUMN `ops_endorsement`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `site_day_shift`
    DROP COLUMN `shift_code`,
    DROP COLUMN `span_text`,
    DROP COLUMN `permits_valid`,
    DROP COLUMN `unsafe_conditions`,
    DROP COLUMN `stop_work`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_day_unit`
SQL,
    <<<'SQL'
ALTER TABLE `site_day`
    DROP COLUMN `day_code`,
    DROP COLUMN `shift`,
    DROP COLUMN `supervisor_id`,
    DROP COLUMN `received_distribution`,
    DROP COLUMN `equipment_present`,
    DROP COLUMN `equipment_absent`,
    DROP COLUMN `operators_present`,
    DROP COLUMN `operators_absent`,
    DROP COLUMN `substitutes_activated`,
    DROP COLUMN `weather_state`,
    DROP COLUMN `opening_note`,
    DROP COLUMN `data_state`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_readiness_item`
SQL,
    <<<'SQL'
ALTER TABLE `sites`
    DROP COLUMN `site_code`,
    DROP COLUMN `project_name`,
    DROP COLUMN `client_contract_code`,
    DROP COLUMN `region`,
    DROP COLUMN `coordinates`,
    DROP COLUMN `work_zones`,
    DROP COLUMN `shifts_count`,
    DROP COLUMN `equipment_capacity`,
    DROP COLUMN `housing_facilities`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `site_dashboard_kpi`
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
