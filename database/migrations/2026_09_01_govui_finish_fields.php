<?php
/**
 * 2026_09_01_govui_finish_fields.php — GOVUI-FINISH · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for GOVUI-FINISH
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

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'client_contract_code'");
if ($q && $q->num_rows) { echo "= workforce_requirement.client_contract_code قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `client_contract_code` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود عقد العميل'")) {
    echo "+ workforce_requirement.client_contract_code\n";
} else { echo "x workforce_requirement.client_contract_code: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'site_ref'");
if ($q && $q->num_rows) { echo "= workforce_requirement.site_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `site_ref` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الموقع'")) {
    echo "+ workforce_requirement.site_ref\n";
} else { echo "x workforce_requirement.site_ref: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'linked_equipment_type'");
if ($q && $q->num_rows) { echo "= workforce_requirement.linked_equipment_type قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `linked_equipment_type` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع المعدة المرتبط'")) {
    echo "+ workforce_requirement.linked_equipment_type\n";
} else { echo "x workforce_requirement.linked_equipment_type: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'required_qualification_level'");
if ($q && $q->num_rows) { echo "= workforce_requirement.required_qualification_level قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `required_qualification_level` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى التأهيل المطلوب'")) {
    echo "+ workforce_requirement.required_qualification_level\n";
} else { echo "x workforce_requirement.required_qualification_level: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'shift_pattern'");
if ($q && $q->num_rows) { echo "= workforce_requirement.shift_pattern قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `shift_pattern` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نمط الوردية'")) {
    echo "+ workforce_requirement.shift_pattern\n";
} else { echo "x workforce_requirement.shift_pattern: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'need_from_date'");
if ($q && $q->num_rows) { echo "= workforce_requirement.need_from_date قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `need_from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ'")) {
    echo "+ workforce_requirement.need_from_date\n";
} else { echo "x workforce_requirement.need_from_date: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'need_source_ref'");
if ($q && $q->num_rows) { echo "= workforce_requirement.need_source_ref قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `need_source_ref` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر الاحتياج'")) {
    echo "+ workforce_requirement.need_source_ref\n";
} else { echo "x workforce_requirement.need_source_ref: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'reviewer_name'");
if ($q && $q->num_rows) { echo "= workforce_requirement.reviewer_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `reviewer_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ workforce_requirement.reviewer_name\n";
} else { echo "x workforce_requirement.reviewer_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'approved_on'");
if ($q && $q->num_rows) { echo "= workforce_requirement.approved_on قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `workforce_requirement` ADD COLUMN `approved_on` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ workforce_requirement.approved_on\n";
} else { echo "x workforce_requirement.approved_on: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'rule_uid'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.rule_uid قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `rule_uid` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معرف القاعدة'")) {
    echo "+ sup_dictionary_rule_derivation.rule_uid\n";
} else { echo "x sup_dictionary_rule_derivation.rule_uid: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'sheet_name'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.sheet_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `sheet_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الشيت'")) {
    echo "+ sup_dictionary_rule_derivation.sheet_name\n";
} else { echo "x sup_dictionary_rule_derivation.sheet_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'field_or_record'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.field_or_record قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `field_or_record` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحقل/السجل'")) {
    echo "+ sup_dictionary_rule_derivation.field_or_record\n";
} else { echo "x sup_dictionary_rule_derivation.field_or_record: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_file'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.source_file قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `source_file` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملف المصدر'")) {
    echo "+ sup_dictionary_rule_derivation.source_file\n";
} else { echo "x sup_dictionary_rule_derivation.source_file: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_sheet'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.source_sheet قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `source_sheet` VARCHAR(190) NULL DEFAULT NULL COMMENT 'شيت المصدر'")) {
    echo "+ sup_dictionary_rule_derivation.source_sheet\n";
} else { echo "x sup_dictionary_rule_derivation.source_sheet: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_key'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.source_key قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `source_key` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مفتاح المصدر'")) {
    echo "+ sup_dictionary_rule_derivation.source_key\n";
} else { echo "x sup_dictionary_rule_derivation.source_key: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'inference_rule'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.inference_rule قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `inference_rule` TEXT NULL DEFAULT NULL COMMENT 'قاعدة الاستنتاج'")) {
    echo "+ sup_dictionary_rule_derivation.inference_rule\n";
} else { echo "x sup_dictionary_rule_derivation.inference_rule: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'effective_authority_levels'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.effective_authority_levels قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `effective_authority_levels` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستويات الحجية الفعلية (محسوبة من سجل التتبع 24)'")) {
    echo "+ sup_dictionary_rule_derivation.effective_authority_levels\n";
} else { echo "x sup_dictionary_rule_derivation.effective_authority_levels: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'owner_rule'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.owner_rule قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `owner_rule` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قاعدة المالك'")) {
    echo "+ sup_dictionary_rule_derivation.owner_rule\n";
} else { echo "x sup_dictionary_rule_derivation.owner_rule: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'trace_line_count'");
if ($q && $q->num_rows) { echo "= sup_dictionary_rule_derivation.trace_line_count قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` ADD COLUMN `trace_line_count` INT NULL DEFAULT NULL COMMENT 'عدد أسطر التتبع'")) {
    echo "+ sup_dictionary_rule_derivation.trace_line_count\n";
} else { echo "x sup_dictionary_rule_derivation.trace_line_count: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
