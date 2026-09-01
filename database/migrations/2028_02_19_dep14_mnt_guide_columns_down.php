<?php
/**
 * 2028_02_19_dep14_mnt_guide_columns_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-14
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
ALTER TABLE `mnt_return_cert`
    DROP COLUMN `cert_validity`,
    DROP COLUMN `data_state`
SQL,
    <<<'SQL'
ALTER TABLE `mnt_order_labor`
    DROP COLUMN `row_code`,
    DROP COLUMN `labor_date`,
    DROP COLUMN `tech_note`,
    DROP COLUMN `row_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
ALTER TABLE `mnt_order`
    DROP COLUMN `open_date`,
    DROP COLUMN `diagnosis_ref`,
    DROP COLUMN `equipment_type_ref`,
    DROP COLUMN `site_ref`,
    DROP COLUMN `tree_node_ref`,
    DROP COLUMN `order_labor_summary`,
    DROP COLUMN `meter_at_open`,
    DROP COLUMN `planned_time`,
    DROP COLUMN `target_finish_date`,
    DROP COLUMN `accumulated_downtime_hours`,
    DROP COLUMN `estimated_parts_cost`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `mnt_diagnosis_request`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `mnt_downtime_segment`
SQL,
    <<<'SQL'
ALTER TABLE `mnt_inspection`
    DROP COLUMN `row_code`,
    DROP COLUMN `fleet_order_ref`,
    DROP COLUMN `returned_card_ref`,
    DROP COLUMN `row_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `failure_codes`
    DROP COLUMN `node_level`,
    DROP COLUMN `parent_node`,
    DROP COLUMN `node_desc`,
    DROP COLUMN `billing_effect`,
    DROP COLUMN `supplier_unit_effect`,
    DROP COLUMN `operator_wage_effect`,
    DROP COLUMN `stops_readiness`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
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
