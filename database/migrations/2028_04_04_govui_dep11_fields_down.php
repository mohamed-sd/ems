<?php
/**
 * 2028_04_04_govui_dep11_fields_down.php — DEP-11 · العكس
 * @migration-objects: columns for DEP-11
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

$r = $conn->query('SELECT COUNT(*) FROM `ops_worker_worklog`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي ops_worker_worklog لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `ops_worker_worklog`')) { echo '- جدول ops_worker_worklog
'; }

$r = $conn->query('SELECT COUNT(*) FROM `ops_monthly_close`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي ops_monthly_close لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `ops_monthly_close`')) { echo '- جدول ops_monthly_close
'; }

$r = $conn->query('SELECT COUNT(*) FROM `ops_hours_approval`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي ops_hours_approval لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `ops_hours_approval`')) { echo '- جدول ops_hours_approval
'; }

$r = $conn->query('SELECT COUNT(*) FROM `ops_monthly_plan`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي ops_monthly_plan لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `ops_monthly_plan`')) { echo '- جدول ops_monthly_plan
'; }

$r = $conn->query('SELECT COUNT(*) FROM `ops_timesheet`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي ops_timesheet لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `ops_timesheet`')) { echo '- جدول ops_timesheet
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
