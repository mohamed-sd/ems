<?php
/**
 * 2028_03_26_govui_exceo_fields_down.php — EX-CEO · العكس
 * @migration-objects: columns for EX-CEO
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

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g316'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g316` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g316 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g316`")) { echo "- exec_project_charters.g316\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g315'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g315` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g315 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g315`")) { echo "- exec_project_charters.g315\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g314'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g314` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g314 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g314`")) { echo "- exec_project_charters.g314\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g313'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g313` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g313 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g313`")) { echo "- exec_project_charters.g313\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g312'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g312` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g312 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g312`")) { echo "- exec_project_charters.g312\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g311'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g311` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g311 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g311`")) { echo "- exec_project_charters.g311\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g310'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g310` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g310 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g310`")) { echo "- exec_project_charters.g310\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g309'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g309` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g309 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g309`")) { echo "- exec_project_charters.g309\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g308'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g308` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g308 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g308`")) { echo "- exec_project_charters.g308\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g307'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g307` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g307 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g307`")) { echo "- exec_project_charters.g307\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g306'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g306` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g306 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g306`")) { echo "- exec_project_charters.g306\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g305'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g305` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g305 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g305`")) { echo "- exec_project_charters.g305\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g304'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g304` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g304 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g304`")) { echo "- exec_project_charters.g304\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g303'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g303` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g303 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g303`")) { echo "- exec_project_charters.g303\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g302'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g302` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g302 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g302`")) { echo "- exec_project_charters.g302\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g301'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `exec_project_charters` WHERE `g301` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي exec_project_charters.g301 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `exec_project_charters` DROP COLUMN `g301`")) { echo "- exec_project_charters.g301\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `exec_action_followup`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_action_followup لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_action_followup`')) { echo '- جدول exec_action_followup
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_meeting_decision`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_meeting_decision لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_meeting_decision`')) { echo '- جدول exec_meeting_decision
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_leadership_appointment`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_leadership_appointment لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_leadership_appointment`')) { echo '- جدول exec_leadership_appointment
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_strategic_decision`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_strategic_decision لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_strategic_decision`')) { echo '- جدول exec_strategic_decision
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_crisis_case`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_crisis_case لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_crisis_case`')) { echo '- جدول exec_crisis_case
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_escalation`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_escalation لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_escalation`')) { echo '- جدول exec_escalation
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_assurance_report`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_assurance_report لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_assurance_report`')) { echo '- جدول exec_assurance_report
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_critical_exception`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_critical_exception لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_critical_exception`')) { echo '- جدول exec_critical_exception
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_reserved_matter`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_reserved_matter لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_reserved_matter`')) { echo '- جدول exec_reserved_matter
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_redline_breach`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_redline_breach لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_redline_breach`')) { echo '- جدول exec_redline_breach
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_contract_registry`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_contract_registry لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_contract_registry`')) { echo '- جدول exec_contract_registry
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_request_queue`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_request_queue لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_request_queue`')) { echo '- جدول exec_request_queue
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_monthly_pack`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_monthly_pack لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_monthly_pack`')) { echo '- جدول exec_monthly_pack
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_weekly_report`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_weekly_report لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_weekly_report`')) { echo '- جدول exec_weekly_report
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_daily_deviation`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_daily_deviation لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_daily_deviation`')) { echo '- جدول exec_daily_deviation
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_daily_stop`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_daily_stop لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_daily_stop`')) { echo '- جدول exec_daily_stop
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_daily_report`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_daily_report لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_daily_report`')) { echo '- جدول exec_daily_report
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_org_project`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_org_project لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_org_project`')) { echo '- جدول exec_org_project
'; }

$r = $conn->query('SELECT COUNT(*) FROM `exec_board_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي exec_board_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `exec_board_kpi`')) { echo '- جدول exec_board_kpi
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
