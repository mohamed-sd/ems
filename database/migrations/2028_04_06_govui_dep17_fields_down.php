<?php
/**
 * 2028_04_06_govui_dep17_fields_down.php — DEP-17 · العكس
 * @migration-objects: columns for DEP-17
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

$r = $conn->query('SELECT COUNT(*) FROM `wh_stock_proc`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_stock_proc لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_stock_proc`')) { echo '- جدول wh_stock_proc
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_transfer`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_transfer لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_transfer`')) { echo '- جدول wh_transfer
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_count`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_count لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_count`')) { echo '- جدول wh_count
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_issue_request_lines`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_issue_request_lines لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_issue_request_lines`')) { echo '- جدول wh_issue_request_lines
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_issue_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_issue_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_issue_requests`')) { echo '- جدول wh_issue_requests
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_warehouses`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_warehouses لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_warehouses`')) { echo '- جدول wh_warehouses
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_month_close`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_month_close لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_month_close`')) { echo '- جدول wh_month_close
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wh_hazmat`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wh_hazmat لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wh_hazmat`')) { echo '- جدول wh_hazmat
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
