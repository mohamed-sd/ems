<?php
/**
 * 2028_02_24_dep02_quota_correct_table.php — DEP-02 · إدارة الموردين — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-02
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
    ADD COLUMN `dist_row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف سطر التوزيع',
    ADD COLUMN `obligation_cycle_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام السنوية ◄',
    ADD COLUMN `supplier_no_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم المورد ◄',
    ADD COLUMN `supplier_name_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم المورد ◄',
    ADD COLUMN `line_type_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع الآلية/البند ◄',
    ADD COLUMN `monthly_unit_base` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'أساس الوحدة التعاقدية الشهري ◄',
    ADD COLUMN `avg_monthly_executed` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'متوسط المنفَّذ الشهري ◄',
    ADD COLUMN `equivalent_units` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الوحدات التعاقدية المكافئة (الاستحقاق) ◄',
    ADD COLUMN `granted_units` INT NULL DEFAULT NULL COMMENT 'الوحدات التعاقدية الممنوحة (أعدادًا صحيحة)',
    ADD COLUMN `largest_remainder_gap` DECIMAL(18,4) NULL DEFAULT NULL COMMENT 'فارق أكبر البواقي ◄',
    ADD COLUMN `excess_machines` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'آليات زائدة عن الوحدات التعاقدية ◄',
    ADD COLUMN `supplier_class` VARCHAR(80) NULL DEFAULT NULL COMMENT 'تصنيف المورد ▼',
    ADD COLUMN `shared_slot_member` VARCHAR(80) NULL DEFAULT NULL COMMENT 'عضو خانة مشتركة؟ ▼',
    ADD COLUMN `shared_contribution_pct` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نسبة المساهمة في الوحدة التعاقدية المشتركة',
    ADD COLUMN `contractual_machines` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'آلات تعاقدية = المستهدف ÷ أساس الوحدة التعاقدية ◄',
    ADD COLUMN `actual_machines` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'آلات واقعية = المستهدف ÷ إنتاجية آلة المورد ◄',
    ADD COLUMN `contractual_vs_actual_gap` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الفرق بين التعاقدي والواقعي ◄',
    ADD COLUMN `valid_from` DATE NULL DEFAULT NULL COMMENT 'ساري من تاريخ',
    ADD COLUMN `dist_decision_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع قرار التوزيع',
    ADD COLUMN `dist_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التوزيع ▼',
    DROP COLUMN `c10`,
    DROP COLUMN `unit_11`,
    DROP COLUMN `c13`,
    DROP COLUMN `c14`,
    DROP COLUMN `c17`,
    DROP COLUMN `month_7`
SQL,
);
$n = 0;
foreach ($SQL as $s) {
    if (!$conn->query($s)) {
        $msg = $conn->error;
        if (stripos($msg, 'Duplicate column') !== false) { continue; }
        exit("⛔ {$msg}\n  في: " . substr($s, 0, 120) . "\n");
    }
    $n++;
}
echo "✔ {$n} جملةً نُفِّذت\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
