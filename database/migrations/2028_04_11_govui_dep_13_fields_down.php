<?php
/**
 * 2028_04_11_govui_dep_13_fields_down.php — DEP-13 · العكس
 * @migration-objects: columns for DEP-13
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

$r = $conn->query('SELECT COUNT(*) FROM `wf_housing_units`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wf_housing_units لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wf_housing_units`')) { echo '- جدول wf_housing_units
'; }

$r = $conn->query('SELECT COUNT(*) FROM `wf_coverage_lines`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي wf_coverage_lines لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `wf_coverage_lines`')) { echo '- جدول wf_coverage_lines
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
