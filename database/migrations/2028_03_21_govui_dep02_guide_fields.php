<?php
/**
 * 2028_03_21_govui_dep02_guide_fields.php — DEP-02 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
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

$q = $conn->query("SHOW COLUMNS FROM `sup_qualification_legal_credit` LIKE 'authority_level'");
if ($q && $q->num_rows) { echo "= sup_qualification_legal_credit.authority_level قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_qualification_legal_credit` ADD COLUMN `authority_level` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى الحجية'")) {
    echo "+ sup_qualification_legal_credit.authority_level\n";
} else { echo "x sup_qualification_legal_credit.authority_level: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_qualification_legal_credit` LIKE 'data_state'");
if ($q && $q->num_rows) { echo "= sup_qualification_legal_credit.data_state قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_qualification_legal_credit` ADD COLUMN `data_state` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ sup_qualification_legal_credit.data_state\n";
} else { echo "x sup_qualification_legal_credit.data_state: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'creator_name'");
if ($q && $q->num_rows) { echo "= sup_payment_disburse.creator_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_payment_disburse` ADD COLUMN `creator_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ sup_payment_disburse.creator_name\n";
} else { echo "x sup_payment_disburse.creator_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'reviewer_name'");
if ($q && $q->num_rows) { echo "= sup_payment_disburse.reviewer_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_payment_disburse` ADD COLUMN `reviewer_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ sup_payment_disburse.reviewer_name\n";
} else { echo "x sup_payment_disburse.reviewer_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'approver_name'");
if ($q && $q->num_rows) { echo "= sup_payment_disburse.approver_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_payment_disburse` ADD COLUMN `approver_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ sup_payment_disburse.approver_name\n";
} else { echo "x sup_payment_disburse.approver_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'record_basis'");
if ($q && $q->num_rows) { echo "= sup_aging_obligation.record_basis قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_aging_obligation` ADD COLUMN `record_basis` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Record_Basis'")) {
    echo "+ sup_aging_obligation.record_basis\n";
} else { echo "x sup_aging_obligation.record_basis: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'derivation_rule'");
if ($q && $q->num_rows) { echo "= sup_aging_obligation.derivation_rule قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_aging_obligation` ADD COLUMN `derivation_rule` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Derivation_Rule'")) {
    echo "+ sup_aging_obligation.derivation_rule\n";
} else { echo "x sup_aging_obligation.derivation_rule: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'data_state'");
if ($q && $q->num_rows) { echo "= sup_aging_obligation.data_state قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_aging_obligation` ADD COLUMN `data_state` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ sup_aging_obligation.data_state\n";
} else { echo "x sup_aging_obligation.data_state: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_list_ref` LIKE 'authority_level'");
if ($q && $q->num_rows) { echo "= sup_list_ref.authority_level قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_list_ref` ADD COLUMN `authority_level` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى الحجية'")) {
    echo "+ sup_list_ref.authority_level\n";
} else { echo "x sup_list_ref.authority_level: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'authority_class'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.authority_class قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `authority_class` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف الحجية'")) {
    echo "+ sup_dictionary_rule_derivation.authority_class\n";
} else { echo "x sup_dictionary_rule_derivation.authority_class: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_migration` LIKE 'data_state'");
if ($q && $q->num_rows) { echo "= sup_dictionary_migration.data_state قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_migration` ADD COLUMN `data_state` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ sup_dictionary_migration.data_state\n";
} else { echo "x sup_dictionary_migration.data_state: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_trace_migration` LIKE 'source_row_ref'");
if ($q && $q->num_rows) { echo "= sup_trace_migration.source_row_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_trace_migration` ADD COLUMN `source_row_ref` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ sup_trace_migration.source_row_ref\n";
} else { echo "x sup_trace_migration.source_row_ref: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
