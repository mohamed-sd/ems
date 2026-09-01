<?php
/**
 * 2028_03_19_govui_dep08_cmp03_fields_down.php — DEP-08 · العكس
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

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'denial_log_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_perm_explain` WHERE `denial_log_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_perm_explain.denial_log_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_perm_explain` DROP COLUMN `denial_log_ref`")) { echo "- scr_perm_explain.denial_log_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'policy_version'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_perm_explain` WHERE `policy_version` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_perm_explain.policy_version لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_perm_explain` DROP COLUMN `policy_version`")) { echo "- scr_perm_explain.policy_version\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'delegation_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_perm_explain` WHERE `delegation_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_perm_explain.delegation_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_perm_explain` DROP COLUMN `delegation_ref`")) { echo "- scr_perm_explain.delegation_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_perm_explain` LIKE 'role_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_perm_explain` WHERE `role_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_perm_explain.role_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_perm_explain` DROP COLUMN `role_ref`")) { echo "- scr_perm_explain.role_ref\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_doc_types` LIKE 'last_number'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_doc_types` WHERE `last_number` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_doc_types.last_number لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_doc_types` DROP COLUMN `last_number`")) { echo "- scr_doc_types.last_number\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'masked_roles'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `masked_roles` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_sensitive_fields.masked_roles لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` DROP COLUMN `masked_roles`")) { echo "- scr_sensitive_fields.masked_roles\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'owner_screen'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `owner_screen` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_sensitive_fields.owner_screen لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` DROP COLUMN `owner_screen`")) { echo "- scr_sensitive_fields.owner_screen\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_sensitive_fields` LIKE 'field_key'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `field_key` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_sensitive_fields.field_key لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_sensitive_fields` DROP COLUMN `field_key`")) { echo "- scr_sensitive_fields.field_key\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `scr_guards` LIKE 'exception_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `scr_guards` WHERE `exception_ref` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي scr_guards.exception_ref لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `scr_guards` DROP COLUMN `exception_ref`")) { echo "- scr_guards.exception_ref\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
