<?php
/**
 * 2028_03_18_govui_dep08_w14_fields.php — DEP-08 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
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

$q = $conn->query("SHOW COLUMNS FROM `gov_compliance_due` LIKE 'due_no'");
if ($q && $q->num_rows) { echo "= gov_compliance_due.due_no قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_compliance_due` ADD COLUMN `due_no` VARCHAR(40) NULL DEFAULT NULL COMMENT 'معرف الاستحقاق'")) {
    echo "+ gov_compliance_due.due_no\n";
} else { echo "x gov_compliance_due.due_no: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_filing` LIKE 'filing_kind'");
if ($q && $q->num_rows) { echo "= gov_filing.filing_kind قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_filing` ADD COLUMN `filing_kind` VARCHAR(40) NULL DEFAULT NULL COMMENT 'نوع التقديم'")) {
    echo "+ gov_filing.filing_kind\n";
} else { echo "x gov_filing.filing_kind: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'relation_ar'");
if ($q && $q->num_rows) { echo "= gov_conflict_disclosure.relation_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` ADD COLUMN `relation_ar` VARCHAR(200) NULL DEFAULT NULL COMMENT 'العلاقة'")) {
    echo "+ gov_conflict_disclosure.relation_ar\n";
} else { echo "x gov_conflict_disclosure.relation_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'controls_ar'");
if ($q && $q->num_rows) { echo "= gov_conflict_disclosure.controls_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` ADD COLUMN `controls_ar` VARCHAR(400) NULL DEFAULT NULL COMMENT 'الضوابط المفروضة'")) {
    echo "+ gov_conflict_disclosure.controls_ar\n";
} else { echo "x gov_conflict_disclosure.controls_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'approved_at'");
if ($q && $q->num_rows) { echo "= gov_conflict_disclosure.approved_at قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ gov_conflict_disclosure.approved_at\n";
} else { echo "x gov_conflict_disclosure.approved_at: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'direction'");
if ($q && $q->num_rows) { echo "= gov_gift_disclosure.direction قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` ADD COLUMN `direction` VARCHAR(24) NULL DEFAULT NULL COMMENT 'الاتجاه'")) {
    echo "+ gov_gift_disclosure.direction\n";
} else { echo "x gov_gift_disclosure.direction: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'description_ar'");
if ($q && $q->num_rows) { echo "= gov_gift_disclosure.description_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` ADD COLUMN `description_ar` VARCHAR(400) NULL DEFAULT NULL COMMENT 'الوصف'")) {
    echo "+ gov_gift_disclosure.description_ar\n";
} else { echo "x gov_gift_disclosure.description_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'context_ar'");
if ($q && $q->num_rows) { echo "= gov_gift_disclosure.context_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` ADD COLUMN `context_ar` VARCHAR(400) NULL DEFAULT NULL COMMENT 'السياق'")) {
    echo "+ gov_gift_disclosure.context_ar\n";
} else { echo "x gov_gift_disclosure.context_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_conduct_ack` LIKE 'ack_no'");
if ($q && $q->num_rows) { echo "= gov_conduct_ack.ack_no قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_conduct_ack` ADD COLUMN `ack_no` VARCHAR(40) NULL DEFAULT NULL COMMENT 'معرف الإقرار'")) {
    echo "+ gov_conduct_ack.ack_no\n";
} else { echo "x gov_conduct_ack.ack_no: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_conduct_ack` LIKE 'ack_channel'");
if ($q && $q->num_rows) { echo "= gov_conduct_ack.ack_channel قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_conduct_ack` ADD COLUMN `ack_channel` VARCHAR(24) NULL DEFAULT NULL COMMENT 'قناة الإقرار'")) {
    echo "+ gov_conduct_ack.ack_channel\n";
} else { echo "x gov_conduct_ack.ack_channel: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_sod_conflict` LIKE 'severity'");
if ($q && $q->num_rows) { echo "= gov_sod_conflict.severity قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_sod_conflict` ADD COLUMN `severity` VARCHAR(24) NULL DEFAULT NULL COMMENT 'درجة الخطورة'")) {
    echo "+ gov_sod_conflict.severity\n";
} else { echo "x gov_sod_conflict.severity: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_sod_conflict` LIKE 'treatment_decision'");
if ($q && $q->num_rows) { echo "= gov_sod_conflict.treatment_decision قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_sod_conflict` ADD COLUMN `treatment_decision` VARCHAR(24) NULL DEFAULT NULL COMMENT 'قرار المعالجة'")) {
    echo "+ gov_sod_conflict.treatment_decision\n";
} else { echo "x gov_sod_conflict.treatment_decision: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_integrity_report` LIKE 'description_ar'");
if ($q && $q->num_rows) { echo "= gov_integrity_report.description_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_integrity_report` ADD COLUMN `description_ar` VARCHAR(600) NULL DEFAULT NULL COMMENT 'الوصف المقيد'")) {
    echo "+ gov_integrity_report.description_ar\n";
} else { echo "x gov_integrity_report.description_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'confidentiality'");
if ($q && $q->num_rows) { echo "= gov_investigation.confidentiality قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_investigation` ADD COLUMN `confidentiality` VARCHAR(24) NULL DEFAULT NULL COMMENT 'مستوى السرية'")) {
    echo "+ gov_investigation.confidentiality\n";
} else { echo "x gov_investigation.confidentiality: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'due_period'");
if ($q && $q->num_rows) { echo "= gov_investigation.due_period قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_investigation` ADD COLUMN `due_period` VARCHAR(40) NULL DEFAULT NULL COMMENT 'المدة المقررة'")) {
    echo "+ gov_investigation.due_period\n";
} else { echo "x gov_investigation.due_period: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'recommendations_ar'");
if ($q && $q->num_rows) { echo "= gov_investigation.recommendations_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_investigation` ADD COLUMN `recommendations_ar` VARCHAR(600) NULL DEFAULT NULL COMMENT 'التوصيات'")) {
    echo "+ gov_investigation.recommendations_ar\n";
} else { echo "x gov_investigation.recommendations_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_breach` LIKE 'impact_ar'");
if ($q && $q->num_rows) { echo "= gov_breach.impact_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_breach` ADD COLUMN `impact_ar` VARCHAR(400) NULL DEFAULT NULL COMMENT 'الأثر المقدر'")) {
    echo "+ gov_breach.impact_ar\n";
} else { echo "x gov_breach.impact_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_audit_followup` LIKE 'finding_ar'");
if ($q && $q->num_rows) { echo "= gov_audit_followup.finding_ar قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_audit_followup` ADD COLUMN `finding_ar` VARCHAR(600) NULL DEFAULT NULL COMMENT 'النتيجة'")) {
    echo "+ gov_audit_followup.finding_ar\n";
} else { echo "x gov_audit_followup.finding_ar: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_audit_followup` LIKE 'finding_class'");
if ($q && $q->num_rows) { echo "= gov_audit_followup.finding_class قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_audit_followup` ADD COLUMN `finding_class` VARCHAR(40) NULL DEFAULT NULL COMMENT 'التصنيف'")) {
    echo "+ gov_audit_followup.finding_class\n";
} else { echo "x gov_audit_followup.finding_class: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
