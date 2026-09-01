<?php
/**
 * 2028_04_08_govui_ws_my_fields_down.php — WS-MY · العكس
 * @migration-objects: columns for WS-MY
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

$r = $conn->query('SELECT COUNT(*) FROM `my_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_requests`')) { echo '- جدول my_requests
'; }

$r = $conn->query('SELECT COUNT(*) FROM `my_user_capacities`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_user_capacities لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_user_capacities`')) { echo '- جدول my_user_capacities
'; }

$r = $conn->query('SELECT COUNT(*) FROM `my_tasks`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_tasks لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_tasks`')) { echo '- جدول my_tasks
'; }

$r = $conn->query('SELECT COUNT(*) FROM `my_portal`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_portal لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_portal`')) { echo '- جدول my_portal
'; }

$r = $conn->query('SELECT COUNT(*) FROM `my_achievement`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_achievement لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_achievement`')) { echo '- جدول my_achievement
'; }

$r = $conn->query('SELECT COUNT(*) FROM `my_reports`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي my_reports لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `my_reports`')) { echo '- جدول my_reports
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
