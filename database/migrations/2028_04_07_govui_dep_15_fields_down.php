<?php
/**
 * 2028_04_07_govui_dep_15_fields_down.php — DEP-15 · العكس
 * @migration-objects: columns for DEP-15
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

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_orders_report`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_orders_report لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_orders_report`')) { echo '- جدول trp_transfer_orders_report
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_origin_handover`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_origin_handover لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_origin_handover`')) { echo '- جدول trp_transfer_origin_handover
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_permits`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_permits لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_permits`')) { echo '- جدول trp_transfer_permits
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_closure`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_closure لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_closure`')) { echo '- جدول trp_transfer_closure
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_damage_claims`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_damage_claims لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_damage_claims`')) { echo '- جدول trp_transfer_damage_claims
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_trip_legs`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_trip_legs لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_trip_legs`')) { echo '- جدول trp_transfer_trip_legs
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_requests`')) { echo '- جدول trp_transfer_requests
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_dashboard_kpi`')) { echo '- جدول trp_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_in_transit`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_in_transit لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_in_transit`')) { echo '- جدول trp_transfer_in_transit
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_fleet`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_fleet لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_fleet`')) { echo '- جدول trp_transfer_fleet
'; }

$r = $conn->query('SELECT COUNT(*) FROM `trp_transfer_order_form`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي trp_transfer_order_form لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `trp_transfer_order_form`')) { echo '- جدول trp_transfer_order_form
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
