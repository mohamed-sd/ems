<?php
/**
 * 2028_04_13_govui_dep_01_fields_down.php — DEP-01 · العكس
 * @migration-objects: columns for DEP-01
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

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g140'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g140` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g140 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g140`")) { echo "- sal_quotation_lines.g140\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g139'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g139` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g139 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g139`")) { echo "- sal_quotation_lines.g139\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g138'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g138` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g138 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g138`")) { echo "- sal_quotation_lines.g138\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g137'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g137` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g137 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g137`")) { echo "- sal_quotation_lines.g137\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g136'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g136` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g136 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g136`")) { echo "- sal_quotation_lines.g136\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g135'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g135` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g135 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g135`")) { echo "- sal_quotation_lines.g135\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g134'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g134` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g134 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g134`")) { echo "- sal_quotation_lines.g134\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g133'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g133` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g133 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g133`")) { echo "- sal_quotation_lines.g133\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g132'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g132` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g132 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g132`")) { echo "- sal_quotation_lines.g132\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g131'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g131` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g131 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g131`")) { echo "- sal_quotation_lines.g131\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g130'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g130` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g130 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g130`")) { echo "- sal_quotation_lines.g130\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g129'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g129` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g129 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g129`")) { echo "- sal_quotation_lines.g129\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g128'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g128` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g128 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g128`")) { echo "- sal_quotation_lines.g128\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g127'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g127` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g127 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g127`")) { echo "- sal_quotation_lines.g127\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g126'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g126` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g126 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g126`")) { echo "- sal_quotation_lines.g126\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g125'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g125` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g125 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g125`")) { echo "- sal_quotation_lines.g125\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g124'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g124` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g124 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g124`")) { echo "- sal_quotation_lines.g124\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g123'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g123` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g123 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g123`")) { echo "- sal_quotation_lines.g123\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g122'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g122` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g122 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g122`")) { echo "- sal_quotation_lines.g122\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g121'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g121` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g121 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g121`")) { echo "- sal_quotation_lines.g121\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g120'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g120` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g120 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g120`")) { echo "- sal_quotation_lines.g120\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g119'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g119` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g119 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g119`")) { echo "- sal_quotation_lines.g119\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g118'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g118` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g118 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g118`")) { echo "- sal_quotation_lines.g118\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g117'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sal_quotation_lines` WHERE `g117` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sal_quotation_lines.g117 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sal_quotation_lines` DROP COLUMN `g117`")) { echo "- sal_quotation_lines.g117\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `sal_commercial_board`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_commercial_board لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_commercial_board`')) { echo '- جدول sal_commercial_board
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_client_contacts`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_client_contacts لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_client_contacts`')) { echo '- جدول sal_client_contacts
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_projects`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_projects لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_projects`')) { echo '- جدول sal_projects
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_quotations`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_quotations لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_quotations`')) { echo '- جدول sal_quotations
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_claims`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_claims لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_claims`')) { echo '- جدول sal_claims
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_quotation_negotiation`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_quotation_negotiation لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_quotation_negotiation`')) { echo '- جدول sal_quotation_negotiation
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_clients`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_clients لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_clients`')) { echo '- جدول sal_clients
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_client_need_rfq`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_client_need_rfq لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_client_need_rfq`')) { echo '- جدول sal_client_need_rfq
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sal_contracts`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sal_contracts لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sal_contracts`')) { echo '- جدول sal_contracts
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
