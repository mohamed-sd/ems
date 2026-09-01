<?php
/**
 * 2028_03_19_govui_dep08_cmp03_fields.php — DEP-08 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-08
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

$q = $conn->query("SHOW COLUMNS FROM `scr_guards` LIKE 'exception_ref'");
if ($q && $q->num_rows) { echo "= scr_guards.exception_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_guards` ADD COLUMN `exception_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرجع الاستثناء المسموح'")) {
    echo "+ scr_guards.exception_ref\n";
} else { echo "x scr_guards.exception_ref: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'field_key'");
if ($q && $q->num_rows) { echo "= scr_sensitive_fields.field_key قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` ADD COLUMN `field_key` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الاسم التقني للحقل'")) {
    echo "+ scr_sensitive_fields.field_key\n";
} else { echo "x scr_sensitive_fields.field_key: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'owner_screen'");
if ($q && $q->num_rows) { echo "= scr_sensitive_fields.owner_screen قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` ADD COLUMN `owner_screen` VARCHAR(190) NULL DEFAULT NULL COMMENT 'السطح المالك'")) {
    echo "+ scr_sensitive_fields.owner_screen\n";
} else { echo "x scr_sensitive_fields.owner_screen: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'masked_roles'");
if ($q && $q->num_rows) { echo "= scr_sensitive_fields.masked_roles قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` ADD COLUMN `masked_roles` VARCHAR(300) NULL DEFAULT NULL COMMENT 'الأدوار التي تراه مقنعا'")) {
    echo "+ scr_sensitive_fields.masked_roles\n";
} else { echo "x scr_sensitive_fields.masked_roles: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_doc_types` LIKE 'last_number'");
if ($q && $q->num_rows) { echo "= scr_doc_types.last_number قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_doc_types` ADD COLUMN `last_number` VARCHAR(60) NULL DEFAULT NULL COMMENT 'آخر رقم مولد'")) {
    echo "+ scr_doc_types.last_number\n";
} else { echo "x scr_doc_types.last_number: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'role_ref'");
if ($q && $q->num_rows) { echo "= scr_perm_explain.role_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_perm_explain` ADD COLUMN `role_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الدور'")) {
    echo "+ scr_perm_explain.role_ref\n";
} else { echo "x scr_perm_explain.role_ref: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'delegation_ref'");
if ($q && $q->num_rows) { echo "= scr_perm_explain.delegation_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_perm_explain` ADD COLUMN `delegation_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'التفويض الساري'")) {
    echo "+ scr_perm_explain.delegation_ref\n";
} else { echo "x scr_perm_explain.delegation_ref: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'policy_version'");
if ($q && $q->num_rows) { echo "= scr_perm_explain.policy_version قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_perm_explain` ADD COLUMN `policy_version` VARCHAR(60) NULL DEFAULT NULL COMMENT 'إصدار السياسة وقت القرار'")) {
    echo "+ scr_perm_explain.policy_version\n";
} else { echo "x scr_perm_explain.policy_version: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'denial_log_ref'");
if ($q && $q->num_rows) { echo "= scr_perm_explain.denial_log_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `scr_perm_explain` ADD COLUMN `denial_log_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرجع سجل الرفض'")) {
    echo "+ scr_perm_explain.denial_log_ref\n";
} else { echo "x scr_perm_explain.denial_log_ref: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
