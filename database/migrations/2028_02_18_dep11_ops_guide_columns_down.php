<?php
/**
 * 2028_02_18_dep11_ops_guide_columns_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-11
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
DROP TABLE IF EXISTS `ops_deviation_report`
SQL,
    <<<'SQL'
ALTER TABLE `ops_stop_register`
    DROP COLUMN `decision_no`,
    DROP COLUMN `event_date`,
    DROP COLUMN `stop_period_ref`,
    DROP COLUMN `stop_reason_ref`,
    DROP COLUMN `accumulated_stop_hours`,
    DROP COLUMN `mandatory_sla`,
    DROP COLUMN `sla_elapsed`,
    DROP COLUMN `decision_reason`,
    DROP COLUMN `readiness_effect`,
    DROP COLUMN `decision_effective_date`,
    DROP COLUMN `decision_state`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
ALTER TABLE `shift_period_logs`
    DROP COLUMN `daily_log_ref`,
    DROP COLUMN `time_state`,
    DROP COLUMN `from_time`,
    DROP COLUMN `to_time`,
    DROP COLUMN `duration_mins`,
    DROP COLUMN `stop_reason_l2`,
    DROP COLUMN `stop_reason_l3`,
    DROP COLUMN `stop_owner`,
    DROP COLUMN `client_obligation_ref`,
    DROP COLUMN `billing_effect`,
    DROP COLUMN `supplier_unit_effect`,
    DROP COLUMN `operator_wage_effect`,
    DROP COLUMN `stop_decision_required`,
    DROP COLUMN `stop_decision_no`,
    DROP COLUMN `field_note`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `ops_resource_move_order`
SQL,
    <<<'SQL'
ALTER TABLE `daily_plan_lines`
    DROP COLUMN `row_code`,
    DROP COLUMN `plan_day`,
    DROP COLUMN `project_ref`,
    DROP COLUMN `site_ref`,
    DROP COLUMN `work_zone`,
    DROP COLUMN `zone_supervisor`,
    DROP COLUMN `equipment_type`,
    DROP COLUMN `equipment_tech_state`,
    DROP COLUMN `critical_maint_block`,
    DROP COLUMN `operator_qualified`,
    DROP COLUMN `operator_license_valid`,
    DROP COLUMN `dispatch_conflict`,
    DROP COLUMN `zone_daily_target`,
    DROP COLUMN `row_state`,
    DROP COLUMN `reviewer`,
    DROP COLUMN `approved_by`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `ops_seasonal_factor`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `ops_room_kpi`
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
