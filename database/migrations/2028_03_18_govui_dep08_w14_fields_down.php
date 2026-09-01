<?php
/**
 * 2028_03_18_govui_dep08_w14_fields_down.php — DEP-08 · العكس
 * @migration-objects: columns for DEP-08
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

$q = $conn->query("SHOW COLUMNS FROM `gov_audit_followup` LIKE 'finding_class'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_audit_followup` WHERE `finding_class` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_audit_followup.finding_class لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_audit_followup` DROP COLUMN `finding_class`")) { echo "- gov_audit_followup.finding_class\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_audit_followup` LIKE 'finding_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_audit_followup` WHERE `finding_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_audit_followup.finding_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_audit_followup` DROP COLUMN `finding_ar`")) { echo "- gov_audit_followup.finding_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_breach` LIKE 'impact_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_breach` WHERE `impact_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_breach.impact_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_breach` DROP COLUMN `impact_ar`")) { echo "- gov_breach.impact_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'recommendations_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_investigation` WHERE `recommendations_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_investigation.recommendations_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_investigation` DROP COLUMN `recommendations_ar`")) { echo "- gov_investigation.recommendations_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'due_period'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_investigation` WHERE `due_period` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_investigation.due_period لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_investigation` DROP COLUMN `due_period`")) { echo "- gov_investigation.due_period\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_investigation` LIKE 'confidentiality'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_investigation` WHERE `confidentiality` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_investigation.confidentiality لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_investigation` DROP COLUMN `confidentiality`")) { echo "- gov_investigation.confidentiality\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_integrity_report` LIKE 'description_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_integrity_report` WHERE `description_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_integrity_report.description_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_integrity_report` DROP COLUMN `description_ar`")) { echo "- gov_integrity_report.description_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_sod_conflict` LIKE 'treatment_decision'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_sod_conflict` WHERE `treatment_decision` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_sod_conflict.treatment_decision لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_sod_conflict` DROP COLUMN `treatment_decision`")) { echo "- gov_sod_conflict.treatment_decision\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_sod_conflict` LIKE 'severity'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_sod_conflict` WHERE `severity` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_sod_conflict.severity لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_sod_conflict` DROP COLUMN `severity`")) { echo "- gov_sod_conflict.severity\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_conduct_ack` LIKE 'ack_channel'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_conduct_ack` WHERE `ack_channel` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_conduct_ack.ack_channel لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_conduct_ack` DROP COLUMN `ack_channel`")) { echo "- gov_conduct_ack.ack_channel\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_conduct_ack` LIKE 'ack_no'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_conduct_ack` WHERE `ack_no` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_conduct_ack.ack_no لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_conduct_ack` DROP COLUMN `ack_no`")) { echo "- gov_conduct_ack.ack_no\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'context_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_gift_disclosure` WHERE `context_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_gift_disclosure.context_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` DROP COLUMN `context_ar`")) { echo "- gov_gift_disclosure.context_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'description_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_gift_disclosure` WHERE `description_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_gift_disclosure.description_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` DROP COLUMN `description_ar`")) { echo "- gov_gift_disclosure.description_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_gift_disclosure` LIKE 'direction'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_gift_disclosure` WHERE `direction` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_gift_disclosure.direction لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_gift_disclosure` DROP COLUMN `direction`")) { echo "- gov_gift_disclosure.direction\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'approved_at'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_conflict_disclosure` WHERE `approved_at` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_conflict_disclosure.approved_at لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` DROP COLUMN `approved_at`")) { echo "- gov_conflict_disclosure.approved_at\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'controls_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_conflict_disclosure` WHERE `controls_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_conflict_disclosure.controls_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` DROP COLUMN `controls_ar`")) { echo "- gov_conflict_disclosure.controls_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_conflict_disclosure` LIKE 'relation_ar'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_conflict_disclosure` WHERE `relation_ar` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_conflict_disclosure.relation_ar لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_conflict_disclosure` DROP COLUMN `relation_ar`")) { echo "- gov_conflict_disclosure.relation_ar\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_filing` LIKE 'filing_kind'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_filing` WHERE `filing_kind` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_filing.filing_kind لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_filing` DROP COLUMN `filing_kind`")) { echo "- gov_filing.filing_kind\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `gov_compliance_due` LIKE 'due_no'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_compliance_due` WHERE `due_no` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_compliance_due.due_no لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_compliance_due` DROP COLUMN `due_no`")) { echo "- gov_compliance_due.due_no\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
