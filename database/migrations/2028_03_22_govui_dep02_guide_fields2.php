<?php
/**
 * 2028_03_22_govui_dep02_guide_fields2.php — DEP-02 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-02
 * مولَّدةٌ من `tools/govui_field_close.php` على مواصفةِ الإدارة —
 * واسمُ العمودِ تعليقُه اسمُ الحقلِ في ورقةِ الدليل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$q = $conn->query("SHOW COLUMNS FROM `sup_list_ref` LIKE 'business_model'");
if ($q && $q->num_rows) { echo "= sup_list_ref.business_model قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_list_ref` ADD COLUMN `business_model` VARCHAR(160) NULL DEFAULT NULL COMMENT 'نموذج العمل'")) {
    echo "+ sup_list_ref.business_model\n";
} else { echo "x sup_list_ref.business_model: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'code_rule'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.code_rule قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `code_rule` VARCHAR(60) NULL DEFAULT NULL COMMENT 'كود القاعدة'")) {
    echo "+ sup_dictionary_rule_derivation.code_rule\n";
} else { echo "x sup_dictionary_rule_derivation.code_rule: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_migration` LIKE 'name_technical'");
if ($q && $q->num_rows) { echo "= sup_dictionary_migration.name_technical قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_migration` ADD COLUMN `name_technical` VARCHAR(160) NULL DEFAULT NULL COMMENT 'الاسم التقني'")) {
    echo "+ sup_dictionary_migration.name_technical\n";
} else { echo "x sup_dictionary_migration.name_technical: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_report_accept` LIKE 'section'");
if ($q && $q->num_rows) { echo "= sup_report_accept.section قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_report_accept` ADD COLUMN `section` VARCHAR(160) NULL DEFAULT NULL COMMENT 'القسم'")) {
    echo "+ sup_report_accept.section\n";
} else { echo "x sup_report_accept.section: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
