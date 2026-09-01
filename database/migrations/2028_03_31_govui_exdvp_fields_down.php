<?php
/**
 * 2028_03_31_govui_exdvp_fields_down.php — EX-DVP · العكس
 * @migration-objects: columns for EX-DVP
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

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g20'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_monthly_pack` WHERE `g20` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_monthly_pack.g20 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_monthly_pack` DROP COLUMN `g20`")) { echo "- exec_monthly_pack.g20\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g19'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_monthly_pack` WHERE `g19` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_monthly_pack.g19 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_monthly_pack` DROP COLUMN `g19`")) { echo "- exec_monthly_pack.g19\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g18'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_monthly_pack` WHERE `g18` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_monthly_pack.g18 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_monthly_pack` DROP COLUMN `g18`")) { echo "- exec_monthly_pack.g18\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g17'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_monthly_pack` WHERE `g17` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_monthly_pack.g17 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_monthly_pack` DROP COLUMN `g17`")) { echo "- exec_monthly_pack.g17\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_weekly_report` LIKE 'g16'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_weekly_report` WHERE `g16` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_weekly_report.g16 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_weekly_report` DROP COLUMN `g16`")) { echo "- exec_weekly_report.g16\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_weekly_report` LIKE 'g15'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_weekly_report` WHERE `g15` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_weekly_report.g15 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_weekly_report` DROP COLUMN `g15`")) { echo "- exec_weekly_report.g15\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g14'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g14` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g14 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g14`")) { echo "- exec_org_project.g14\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g13'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g13` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g13 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g13`")) { echo "- exec_org_project.g13\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g12'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g12` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g12 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g12`")) { echo "- exec_org_project.g12\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g11'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g11` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g11 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g11`")) { echo "- exec_org_project.g11\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g10'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g10` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g10 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g10`")) { echo "- exec_org_project.g10\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g9'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g9` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g9 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g9`")) { echo "- exec_org_project.g9\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g8'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_org_project` WHERE `g8` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_org_project.g8 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_org_project` DROP COLUMN `g8`")) { echo "- exec_org_project.g8\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_action_followup` LIKE 'g7'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_action_followup` WHERE `g7` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_action_followup.g7 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_action_followup` DROP COLUMN `g7`")) { echo "- exec_action_followup.g7\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g6'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_request_queue` WHERE `g6` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_request_queue.g6 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_request_queue` DROP COLUMN `g6`")) { echo "- exec_request_queue.g6\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g5'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_request_queue` WHERE `g5` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_request_queue.g5 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_request_queue` DROP COLUMN `g5`")) { echo "- exec_request_queue.g5\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g4'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_request_queue` WHERE `g4` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_request_queue.g4 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_request_queue` DROP COLUMN `g4`")) { echo "- exec_request_queue.g4\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g3'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_request_queue` WHERE `g3` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_request_queue.g3 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_request_queue` DROP COLUMN `g3`")) { echo "- exec_request_queue.g3\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_daily_report` LIKE 'g2'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_daily_report` WHERE `g2` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_daily_report.g2 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_daily_report` DROP COLUMN `g2`")) { echo "- exec_daily_report.g2\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_daily_report` LIKE 'g1'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_daily_report` WHERE `g1` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_daily_report.g1 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_daily_report` DROP COLUMN `g1`")) { echo "- exec_daily_report.g1\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `dvp_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي dvp_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `dvp_dashboard_kpi`')) { echo '- جدول dvp_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `dvp_vp_pending_actions`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي dvp_vp_pending_actions لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `dvp_vp_pending_actions`')) { echo '- جدول dvp_vp_pending_actions
'; }

$r = $conn->query('SELECT COUNT(*) FROM `dvp_delegations`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي dvp_delegations لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `dvp_delegations`')) { echo '- جدول dvp_delegations
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
