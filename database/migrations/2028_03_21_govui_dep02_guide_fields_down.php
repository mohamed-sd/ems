<?php
/**
 * 2028_03_21_govui_dep02_guide_fields_down.php — DEP-02 · العكس
 * @migration-objects: columns for DEP-02
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

$q = $conn->query("SHOW COLUMNS FROM `sup_trace_migration` LIKE 'source_row_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_trace_migration` WHERE `source_row_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_trace_migration.source_row_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_trace_migration` DROP COLUMN `source_row_ref`")) { echo "- sup_trace_migration.source_row_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_migration` LIKE 'data_state'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_migration` WHERE `data_state` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_migration.data_state لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_migration` DROP COLUMN `data_state`")) { echo "- sup_dictionary_migration.data_state\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'authority_class'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `authority_class` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.authority_class لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `authority_class`")) { echo "- sup_dictionary_rule_derivation.authority_class\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_list_ref` LIKE 'authority_level'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_list_ref` WHERE `authority_level` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_list_ref.authority_level لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_list_ref` DROP COLUMN `authority_level`")) { echo "- sup_list_ref.authority_level\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'data_state'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_aging_obligation` WHERE `data_state` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_aging_obligation.data_state لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_aging_obligation` DROP COLUMN `data_state`")) { echo "- sup_aging_obligation.data_state\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'derivation_rule'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_aging_obligation` WHERE `derivation_rule` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_aging_obligation.derivation_rule لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_aging_obligation` DROP COLUMN `derivation_rule`")) { echo "- sup_aging_obligation.derivation_rule\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_aging_obligation` LIKE 'record_basis'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_aging_obligation` WHERE `record_basis` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_aging_obligation.record_basis لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_aging_obligation` DROP COLUMN `record_basis`")) { echo "- sup_aging_obligation.record_basis\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'approver_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_payment_disburse` WHERE `approver_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_payment_disburse.approver_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_payment_disburse` DROP COLUMN `approver_name`")) { echo "- sup_payment_disburse.approver_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'reviewer_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_payment_disburse` WHERE `reviewer_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_payment_disburse.reviewer_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_payment_disburse` DROP COLUMN `reviewer_name`")) { echo "- sup_payment_disburse.reviewer_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_payment_disburse` LIKE 'creator_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_payment_disburse` WHERE `creator_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_payment_disburse.creator_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_payment_disburse` DROP COLUMN `creator_name`")) { echo "- sup_payment_disburse.creator_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_qualification_legal_credit` LIKE 'data_state'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_qualification_legal_credit` WHERE `data_state` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_qualification_legal_credit.data_state لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_qualification_legal_credit` DROP COLUMN `data_state`")) { echo "- sup_qualification_legal_credit.data_state\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_qualification_legal_credit` LIKE 'authority_level'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_qualification_legal_credit` WHERE `authority_level` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_qualification_legal_credit.authority_level لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_qualification_legal_credit` DROP COLUMN `authority_level`")) { echo "- sup_qualification_legal_credit.authority_level\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
