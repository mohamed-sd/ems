<?php
/**
 * 2028_04_01_govui_dep16_fields_down.php — DEP-16 · العكس
 * @migration-objects: columns for DEP-16
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

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g191'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g191` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g191 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g191`")) { echo "- proc_order.g191\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g190'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g190` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g190 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g190`")) { echo "- proc_order.g190\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g189'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g189` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g189 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g189`")) { echo "- proc_order.g189\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g188'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g188` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g188 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g188`")) { echo "- proc_order.g188\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g187'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g187` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g187 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g187`")) { echo "- proc_order.g187\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g186'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g186` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g186 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g186`")) { echo "- proc_order.g186\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g185'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g185` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g185 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g185`")) { echo "- proc_order.g185\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g184'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g184` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g184 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g184`")) { echo "- proc_order.g184\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g183'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g183` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g183 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g183`")) { echo "- proc_order.g183\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g182'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g182` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g182 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g182`")) { echo "- proc_order.g182\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g181'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g181` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g181 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g181`")) { echo "- proc_order.g181\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g180'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g180` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g180 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g180`")) { echo "- proc_order.g180\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g179'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g179` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g179 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g179`")) { echo "- proc_order.g179\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g178'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g178` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g178 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g178`")) { echo "- proc_order.g178\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g177'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g177` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g177 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g177`")) { echo "- proc_order.g177\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g176'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g176` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g176 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g176`")) { echo "- proc_order.g176\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g175'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g175` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g175 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g175`")) { echo "- proc_order.g175\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g174'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g174` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g174 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g174`")) { echo "- proc_order.g174\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g173'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `proc_order` WHERE `g173` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي proc_order.g173 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `proc_order` DROP COLUMN `g173`")) { echo "- proc_order.g173\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `prc_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_dashboard_kpi`')) { echo '- جدول prc_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_package_lines`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_package_lines لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_package_lines`')) { echo '- جدول prc_package_lines
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_requests`')) { echo '- جدول prc_requests
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_offer_compare`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_offer_compare لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_offer_compare`')) { echo '- جدول prc_offer_compare
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_rfq`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_rfq لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_rfq`')) { echo '- جدول prc_proc_rfq
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_orders_proc`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_orders_proc لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_orders_proc`')) { echo '- جدول prc_orders_proc
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_delivery_track`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_delivery_track لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_delivery_track`')) { echo '- جدول prc_proc_delivery_track
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_packages`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_packages لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_packages`')) { echo '- جدول prc_proc_packages
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_supplier_eval`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_supplier_eval لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_supplier_eval`')) { echo '- جدول prc_proc_supplier_eval
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_award_minutes`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_award_minutes لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_award_minutes`')) { echo '- جدول prc_proc_award_minutes
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_offers`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_offers لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_offers`')) { echo '- جدول prc_proc_offers
'; }

$r = $conn->query('SELECT COUNT(*) FROM `prc_proc_po_amendments`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي prc_proc_po_amendments لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `prc_proc_po_amendments`')) { echo '- جدول prc_proc_po_amendments
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
