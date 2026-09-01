<?php
/**
 * 2028_03_22_govui_dep02_guide_fields2_down.php — DEP-02 · العكس
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

$q = $conn->query("SHOW COLUMNS FROM `sup_report_accept` LIKE 'section'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_report_accept` WHERE `section` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_report_accept.section لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_report_accept` DROP COLUMN `section`")) { echo "- sup_report_accept.section\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_migration` LIKE 'name_technical'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_migration` WHERE `name_technical` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_migration.name_technical لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_migration` DROP COLUMN `name_technical`")) { echo "- sup_dictionary_migration.name_technical\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_dictionary_rule_derivation` LIKE 'code_rule'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_dictionary_rule_derivation` WHERE `code_rule` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_dictionary_rule_derivation.code_rule لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_dictionary_rule_derivation` DROP COLUMN `code_rule`")) { echo "- sup_dictionary_rule_derivation.code_rule\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_list_ref` LIKE 'business_model'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_list_ref` WHERE `business_model` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_list_ref.business_model لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_list_ref` DROP COLUMN `business_model`")) { echo "- sup_list_ref.business_model\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
