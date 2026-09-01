<?php
/**
 * 2028_02_15_dep13_workforce_guide_tables_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-13
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
DROP TABLE IF EXISTS `wf_field_incident`
SQL,
    <<<'SQL'
ALTER TABLE `worker_settlement`
    DROP COLUMN `settlement_no`,
    DROP COLUMN `allocation_ref`,
    DROP COLUMN `person_ref`,
    DROP COLUMN `end_reason`,
    DROP COLUMN `housing_cleared`,
    DROP COLUMN `custody_returned`,
    DROP COLUMN `custody_pending`,
    DROP COLUMN `due_basis`,
    DROP COLUMN `paying_party`,
    DROP COLUMN `hr_clearance_ref`,
    DROP COLUMN `settlement_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `worker_evaluation`
    DROP COLUMN `row_code`,
    DROP COLUMN `period_ref`,
    DROP COLUMN `person_ref`,
    DROP COLUMN `category_ref`,
    DROP COLUMN `approved_hours`,
    DROP COLUMN `approved_units`,
    DROP COLUMN `field_days`,
    DROP COLUMN `shift_compliance`,
    DROP COLUMN `period_incidents`,
    DROP COLUMN `performance_index`
SQL,
    <<<'SQL'
ALTER TABLE `operator_rotations`
    DROP COLUMN `cycle_code`,
    DROP COLUMN `person_ref`,
    DROP COLUMN `rotation_pattern`,
    DROP COLUMN `cycle_start_date`,
    DROP COLUMN `leave_start`,
    DROP COLUMN `leave_end`,
    DROP COLUMN `leave_type`,
    DROP COLUMN `assigned_backup`,
    DROP COLUMN `backup_qual_check`,
    DROP COLUMN `swap_state`,
    DROP COLUMN `coverage_effect`,
    DROP COLUMN `cycle_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `worker_movement`
    DROP COLUMN `row_code`,
    DROP COLUMN `row_date`,
    DROP COLUMN `person_ref`,
    DROP COLUMN `row_kind`,
    DROP COLUMN `presence_state`,
    DROP COLUMN `span_from`,
    DROP COLUMN `span_to`,
    DROP COLUMN `transfer_order_ref`,
    DROP COLUMN `site_presence`,
    DROP COLUMN `row_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_equipment_shift_assignment`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_project_allocation`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_qualification_matrix`
SQL,
    <<<'SQL'
ALTER TABLE `worker_qualification`
    DROP COLUMN `qual_code`,
    DROP COLUMN `certificate_no`,
    DROP COLUMN `practical_test_result`,
    DROP COLUMN `qual_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `equipment_operators`
    DROP COLUMN `person_code`,
    DROP COLUMN `person_name`,
    DROP COLUMN `wf_category`,
    DROP COLUMN `affiliation`,
    DROP COLUMN `distribution_track`,
    DROP COLUMN `qualified_equipment_types`,
    DROP COLUMN `current_site`,
    DROP COLUMN `current_allocation`,
    DROP COLUMN `rotation_pattern`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_category`
SQL,
    <<<'SQL'
ALTER TABLE `worker_contract`
    DROP COLUMN `row_code`,
    DROP COLUMN `person_ref`,
    DROP COLUMN `project_contract_ref`,
    DROP COLUMN `project_ref`,
    DROP COLUMN `valid_from`,
    DROP COLUMN `valid_to`,
    DROP COLUMN `end_trigger`,
    DROP COLUMN `contract_state`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_nomination`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `wf_dashboard_kpi`
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
