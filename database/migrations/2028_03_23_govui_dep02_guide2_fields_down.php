<?php
/**
 * 2028_03_23_govui_dep02_guide2_fields_down.php — DEP-02 · العكس
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

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'approved_date'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_account` WHERE `approved_date` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_account.approved_date لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_account` DROP COLUMN `approved_date`")) { echo "- sup_account.approved_date\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'approver_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_account` WHERE `approver_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_account.approver_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_account` DROP COLUMN `approver_name`")) { echo "- sup_account.approver_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'reviewer_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_account` WHERE `reviewer_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_account.reviewer_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_account` DROP COLUMN `reviewer_name`")) { echo "- sup_account.reviewer_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'creator_name'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_account` WHERE `creator_name` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_account.creator_name لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_account` DROP COLUMN `creator_name`")) { echo "- sup_account.creator_name\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'settle_no'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_account` WHERE `settle_no` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_account.settle_no لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_account` DROP COLUMN `settle_no`")) { echo "- sup_account.settle_no\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_need` LIKE 'obligation_gap'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_need` WHERE `obligation_gap` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_need.obligation_gap لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_need` DROP COLUMN `obligation_gap`")) { echo "- sup_need.obligation_gap\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_need` LIKE 'need_no'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_need` WHERE `need_no` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_need.need_no لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_need` DROP COLUMN `need_no`")) { echo "- sup_need.need_no\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
