<?php
/**
 * 2026_09_01_govui_finish_fields_down.php — GOVUI-FINISH · العكس
 * @migration-objects: columns for GOVUI-FINISH
 * مولَّدةٌ من `tools/govui_field_close.php` على مواصفةِ الإدارة —
 * واسمُ العمودِ تعليقُه اسمُ الحقلِ في ورقةِ الدليل.
 * ⛔ ولا يُسقَط عمودٌ فيه بياناتٌ صامتًا — يُسمَّى ويُترَك.
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

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'trace_line_count'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `trace_line_count` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.trace_line_count لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `trace_line_count`")) { echo "- sup_dictionary_rule_derivation.trace_line_count\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'owner_rule'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `owner_rule` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.owner_rule لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `owner_rule`")) { echo "- sup_dictionary_rule_derivation.owner_rule\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'effective_authority_levels'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `effective_authority_levels` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.effective_authority_levels لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `effective_authority_levels`")) { echo "- sup_dictionary_rule_derivation.effective_authority_levels\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'inference_rule'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `inference_rule` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.inference_rule لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `inference_rule`")) { echo "- sup_dictionary_rule_derivation.inference_rule\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_key'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `source_key` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.source_key لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `source_key`")) { echo "- sup_dictionary_rule_derivation.source_key\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_sheet'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `source_sheet` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.source_sheet لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `source_sheet`")) { echo "- sup_dictionary_rule_derivation.source_sheet\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'source_file'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `source_file` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.source_file لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `source_file`")) { echo "- sup_dictionary_rule_derivation.source_file\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'field_or_record'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `field_or_record` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.field_or_record لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `field_or_record`")) { echo "- sup_dictionary_rule_derivation.field_or_record\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'sheet_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `sheet_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.sheet_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `sheet_name`")) { echo "- sup_dictionary_rule_derivation.sheet_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'rule_uid'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `rule_uid` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.rule_uid لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `rule_uid`")) { echo "- sup_dictionary_rule_derivation.rule_uid\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'approved_on'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `approved_on` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.approved_on لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `approved_on`")) { echo "- workforce_requirement.approved_on\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'reviewer_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `reviewer_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.reviewer_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `reviewer_name`")) { echo "- workforce_requirement.reviewer_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'need_source_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `need_source_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.need_source_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `need_source_ref`")) { echo "- workforce_requirement.need_source_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'need_from_date'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `need_from_date` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.need_from_date لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `need_from_date`")) { echo "- workforce_requirement.need_from_date\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'shift_pattern'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `shift_pattern` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.shift_pattern لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `shift_pattern`")) { echo "- workforce_requirement.shift_pattern\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'required_qualification_level'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `required_qualification_level` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.required_qualification_level لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `required_qualification_level`")) { echo "- workforce_requirement.required_qualification_level\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'linked_equipment_type'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `linked_equipment_type` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.linked_equipment_type لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `linked_equipment_type`")) { echo "- workforce_requirement.linked_equipment_type\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'site_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `site_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.site_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `site_ref`")) { echo "- workforce_requirement.site_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `workforce_requirement` LIKE 'client_contract_code'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `workforce_requirement` WHERE `client_contract_code` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي workforce_requirement.client_contract_code لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `workforce_requirement` DROP COLUMN `client_contract_code`")) { echo "- workforce_requirement.client_contract_code\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
