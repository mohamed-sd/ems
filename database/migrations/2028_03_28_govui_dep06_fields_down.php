<?php
/**
 * 2028_03_28_govui_dep06_fields_down.php — DEP-06 · العكس
 * @migration-objects: columns for DEP-06
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

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g185'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g185` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g185 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g185`")) { echo "- tre_cash_count.g185\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g184'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g184` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g184 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g184`")) { echo "- tre_cash_count.g184\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g183'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g183` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g183 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g183`")) { echo "- tre_cash_count.g183\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g182'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g182` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g182 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g182`")) { echo "- tre_cash_count.g182\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g181'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g181` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g181 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g181`")) { echo "- tre_cash_count.g181\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g180'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g180` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g180 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g180`")) { echo "- tre_cash_count.g180\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g179'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g179` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g179 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g179`")) { echo "- tre_cash_count.g179\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g178'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g178` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g178 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g178`")) { echo "- tre_cash_count.g178\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g177'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g177` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g177 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g177`")) { echo "- tre_cash_count.g177\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g176'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g176` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g176 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g176`")) { echo "- tre_cash_count.g176\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g175'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g175` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g175 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g175`")) { echo "- tre_cash_count.g175\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g174'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g174` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g174 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g174`")) { echo "- tre_cash_count.g174\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g173'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g173` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g173 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g173`")) { echo "- tre_cash_count.g173\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g172'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_cash_count` WHERE `g172` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tre_cash_count.g172 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_cash_count` DROP COLUMN `g172`")) { echo "- tre_cash_count.g172\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `tre_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_dashboard_kpi`')) { echo '- جدول tre_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_beneficiary`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_beneficiary لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_beneficiary`')) { echo '- جدول tre_beneficiary
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_bank_reconciliation_fin`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_bank_reconciliation_fin لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_bank_reconciliation_fin`')) { echo '- جدول tre_bank_reconciliation_fin
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_transfers`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_transfers لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_transfers`')) { echo '- جدول tre_transfers
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_allocations`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_allocations لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_allocations`')) { echo '- جدول tre_allocations
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_cash_moves`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_cash_moves لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_cash_moves`')) { echo '- جدول tre_cash_moves
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_petty_cash`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_petty_cash لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_petty_cash`')) { echo '- جدول tre_petty_cash
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_guarantees`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_guarantees لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_guarantees`')) { echo '- جدول tre_guarantees
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_instruments`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_instruments لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_instruments`')) { echo '- جدول tre_instruments
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_fx_deals`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_fx_deals لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_fx_deals`')) { echo '- جدول tre_fx_deals
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_vessels`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_vessels لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_vessels`')) { echo '- جدول tre_vessels
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_pay_batch`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_pay_batch لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_pay_batch`')) { echo '- جدول tre_pay_batch
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tre_payment_queue`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tre_payment_queue لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tre_payment_queue`')) { echo '- جدول tre_payment_queue
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
