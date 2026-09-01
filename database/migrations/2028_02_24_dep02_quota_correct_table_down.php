<?php
/**
 * 2028_02_24_dep02_quota_correct_table_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for DEP-02
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
ALTER TABLE `sup_slot_allocation_quota`
    DROP COLUMN `dist_row_code`,
    DROP COLUMN `obligation_cycle_key`,
    DROP COLUMN `supplier_no_ref`,
    DROP COLUMN `supplier_name_ref`,
    DROP COLUMN `line_type_ref`,
    DROP COLUMN `monthly_unit_base`,
    DROP COLUMN `avg_monthly_executed`,
    DROP COLUMN `equivalent_units`,
    DROP COLUMN `granted_units`,
    DROP COLUMN `largest_remainder_gap`,
    DROP COLUMN `excess_machines`,
    DROP COLUMN `supplier_class`,
    DROP COLUMN `shared_slot_member`,
    DROP COLUMN `shared_contribution_pct`,
    DROP COLUMN `contractual_machines`,
    DROP COLUMN `actual_machines`,
    DROP COLUMN `contractual_vs_actual_gap`,
    DROP COLUMN `valid_from`,
    DROP COLUMN `dist_decision_ref`,
    DROP COLUMN `dist_state`,
    ADD COLUMN `c10` varchar(160) NOT NULL DEFAULT '\'\'' COMMENT 'فارق أكبر البواقي ◄',
    ADD COLUMN `unit_11` varchar(160) NOT NULL DEFAULT '\'\'' COMMENT 'آليات زائدة عن الوحدات التعاقدية ◄',
    ADD COLUMN `c13` varchar(160) NOT NULL DEFAULT '\'\'' COMMENT 'عضو خانة مشتركة؟ ▼',
    ADD COLUMN `c14` decimal(9,4) NULL DEFAULT 'NULL' COMMENT 'نسبة المساهمة في الوحدة التعاقدية المشتركة',
    ADD COLUMN `c17` varchar(160) NOT NULL DEFAULT '\'\'' COMMENT 'الفرق بين التعاقدي والواقعي ◄',
    ADD COLUMN `month_7` varchar(160) NOT NULL DEFAULT '\'\'' COMMENT 'متوسط المنفذ الشهري ◄'
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
