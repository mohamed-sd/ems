<?php
/**
 * 2028_03_30_govui_dep10_fields_down.php — DEP-10 · العكس
 * @migration-objects: columns for DEP-10
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

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g127'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g127` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g127 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g127`")) { echo "- tkt_reopen.g127\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g126'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g126` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g126 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g126`")) { echo "- tkt_reopen.g126\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g125'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g125` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g125 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g125`")) { echo "- tkt_reopen.g125\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g124'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g124` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g124 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g124`")) { echo "- tkt_reopen.g124\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g123'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g123` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g123 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g123`")) { echo "- tkt_reopen.g123\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g122'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g122` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g122 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g122`")) { echo "- tkt_reopen.g122\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g121'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g121` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g121 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g121`")) { echo "- tkt_reopen.g121\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g120'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g120` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g120 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g120`")) { echo "- tkt_reopen.g120\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g119'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g119` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g119 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g119`")) { echo "- tkt_reopen.g119\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g118'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g118` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g118 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g118`")) { echo "- tkt_reopen.g118\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g117'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g117` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g117 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g117`")) { echo "- tkt_reopen.g117\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g116'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_reopen` WHERE `g116` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_reopen.g116 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_reopen` DROP COLUMN `g116`")) { echo "- tkt_reopen.g116\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g64'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g64` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g64 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g64`")) { echo "- tkt_verification.g64\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g63'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g63` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g63 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g63`")) { echo "- tkt_verification.g63\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g62'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g62` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g62 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g62`")) { echo "- tkt_verification.g62\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g61'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g61` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g61 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g61`")) { echo "- tkt_verification.g61\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g60'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g60` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g60 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g60`")) { echo "- tkt_verification.g60\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g59'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g59` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g59 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g59`")) { echo "- tkt_verification.g59\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g58'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g58` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g58 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g58`")) { echo "- tkt_verification.g58\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g57'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g57` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g57 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g57`")) { echo "- tkt_verification.g57\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g56'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g56` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g56 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g56`")) { echo "- tkt_verification.g56\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g55'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g55` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g55 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g55`")) { echo "- tkt_verification.g55\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g54'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g54` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g54 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g54`")) { echo "- tkt_verification.g54\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g53'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g53` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g53 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g53`")) { echo "- tkt_verification.g53\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g52'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g52` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g52 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g52`")) { echo "- tkt_verification.g52\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g51'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g51` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g51 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g51`")) { echo "- tkt_verification.g51\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g50'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g50` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g50 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g50`")) { echo "- tkt_verification.g50\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g49'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tkt_verification` WHERE `g49` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي tkt_verification.g49 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `tkt_verification` DROP COLUMN `g49`")) { echo "- tkt_verification.g49\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `tkt_assignment`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_assignment لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_assignment`')) { echo '- جدول tkt_assignment
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_subject_types`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_subject_types لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_subject_types`')) { echo '- جدول tkt_subject_types
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_dashboard_kpi`')) { echo '- جدول tkt_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_communications`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_communications لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_communications`')) { echo '- جدول tkt_communications
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_routing`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_routing لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_routing`')) { echo '- جدول tkt_routing
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_ticket_sla_config`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_ticket_sla_config لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_ticket_sla_config`')) { echo '- جدول tkt_ticket_sla_config
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_resolution_actions`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_resolution_actions لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_resolution_actions`')) { echo '- جدول tkt_resolution_actions
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_tickets_list`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_tickets_list لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_tickets_list`')) { echo '- جدول tkt_tickets_list
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_escalation`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_escalation لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_escalation`')) { echo '- جدول tkt_escalation
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_ticket_form`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_ticket_form لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_ticket_form`')) { echo '- جدول tkt_ticket_form
'; }

$r = $conn->query('SELECT COUNT(*) FROM `tkt_ticket_contextual_open`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي tkt_ticket_contextual_open لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `tkt_ticket_contextual_open`')) { echo '- جدول tkt_ticket_contextual_open
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
