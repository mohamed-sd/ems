<?php
/**
 * 2028_04_09_govui_iaf_fields_down.php — IAF · العكس
 * @migration-objects: columns for IAF
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

$r = $conn->query('SELECT COUNT(*) FROM `iaf_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي iaf_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `iaf_dashboard_kpi`')) { echo '- جدول iaf_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `iaf_function_risks`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي iaf_function_risks لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `iaf_function_risks`')) { echo '- جدول iaf_function_risks
'; }

$r = $conn->query('SELECT COUNT(*) FROM `iaf_test_samples`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي iaf_test_samples لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `iaf_test_samples`')) { echo '- جدول iaf_test_samples
'; }

$r = $conn->query('SELECT COUNT(*) FROM `iaf_audit_programs`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي iaf_audit_programs لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `iaf_audit_programs`')) { echo '- جدول iaf_audit_programs
'; }

$r = $conn->query('SELECT COUNT(*) FROM `iaf_evidence_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي iaf_evidence_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `iaf_evidence_requests`')) { echo '- جدول iaf_evidence_requests
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
