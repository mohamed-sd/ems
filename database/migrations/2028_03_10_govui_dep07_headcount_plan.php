<?php
/**
 * 2028_03_10_govui_dep07_headcount_plan.php — DEP-07 · إدارة الموارد البشرية — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-07
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
CREATE TABLE IF NOT EXISTS `hr_headcount_plan` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `plan_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    `plan_year` SMALLINT NULL DEFAULT NULL COMMENT 'السنة ◄',
    `org_unit_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الوحدة التنظيمية ◄',
    `category_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الفئة ▼',
    `approved_headcount` INT NULL DEFAULT NULL COMMENT 'العدد المعتمد',
    `actual_headcount` INT NULL DEFAULT NULL COMMENT 'الفعلي ◄',
    `headcount_gap` INT NULL DEFAULT NULL COMMENT 'الفجوة ◄',
    `open_vacancies` INT NULL DEFAULT NULL COMMENT 'شواغر مفتوحة ◄',
    `off_plan_approved` INT NULL DEFAULT NULL COMMENT 'خارج الخطة بموافقة ◄',
    `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة السطر ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_06ac83b6_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-07 — خطة القوى العاملة · الحبة: سطرُ خطةٍ واحدٌ: وحدةٌ تنظيميّةٌ × فئةٌ × سنة'
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
