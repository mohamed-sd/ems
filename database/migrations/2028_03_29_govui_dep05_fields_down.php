<?php
/**
 * 2028_03_29_govui_dep05_fields_down.php — DEP-05 · العكس
 * @migration-objects: columns for DEP-05
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

$r = $conn->query('SELECT COUNT(*) FROM `fina_financial_statements_fin`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_financial_statements_fin لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_financial_statements_fin`')) { echo '- جدول fina_financial_statements_fin
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_currencies`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_currencies لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_currencies`')) { echo '- جدول fina_currencies
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_journal_form_fin`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_journal_form_fin لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_journal_form_fin`')) { echo '- جدول fina_journal_form_fin
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_dues`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_dues لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_dues`')) { echo '- جدول fina_dues
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_dashboard_kpi`')) { echo '- جدول fina_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_collections`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_collections لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_collections`')) { echo '- جدول fina_collections
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_reconciliations`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_reconciliations لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_reconciliations`')) { echo '- جدول fina_reconciliations
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_acc_cost_centers`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_acc_cost_centers لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_acc_cost_centers`')) { echo '- جدول fina_acc_cost_centers
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_closing_checklist`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_closing_checklist لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_closing_checklist`')) { echo '- جدول fina_closing_checklist
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_tax_fin`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_tax_fin لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_tax_fin`')) { echo '- جدول fina_tax_fin
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_acc_credit_control`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_acc_credit_control لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_acc_credit_control`')) { echo '- جدول fina_acc_credit_control
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_effect_map`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_effect_map لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_effect_map`')) { echo '- جدول fina_effect_map
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_acc_trial_balance`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_acc_trial_balance لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_acc_trial_balance`')) { echo '- جدول fina_acc_trial_balance
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_periods_fin`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_periods_fin لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_periods_fin`')) { echo '- جدول fina_periods_fin
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_acc_reopen_governance`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_acc_reopen_governance لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_acc_reopen_governance`')) { echo '- جدول fina_acc_reopen_governance
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fina_acc_adjustments`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fina_acc_adjustments لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fina_acc_adjustments`')) { echo '- جدول fina_acc_adjustments
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
