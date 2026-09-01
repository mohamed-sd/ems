<?php
/**
 * 2028_03_27_govui_dep07_fields_down.php — DEP-07 · العكس
 * @migration-objects: columns for DEP-07
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

$r = $conn->query('SELECT COUNT(*) FROM `hr_worker_leave_absence`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_worker_leave_absence لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_worker_leave_absence`')) { echo '- جدول hr_worker_leave_absence
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_employee_contracts`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_employee_contracts لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_employee_contracts`')) { echo '- جدول hr_employee_contracts
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_employee_documents`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_employee_documents لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_employee_documents`')) { echo '- جدول hr_employee_documents
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_rec_stages`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_rec_stages لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_rec_stages`')) { echo '- جدول hr_rec_stages
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_dashboard_kpi`')) { echo '- جدول hr_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_onboarding`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_onboarding لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_onboarding`')) { echo '- جدول hr_onboarding
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_rec_applications`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_rec_applications لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_rec_applications`')) { echo '- جدول hr_rec_applications
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_attendance`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_attendance لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_attendance`')) { echo '- جدول hr_attendance
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_employees`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_employees لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_employees`')) { echo '- جدول hr_employees
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_benefits`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_benefits لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_benefits`')) { echo '- جدول hr_benefits
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_training`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_training لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_training`')) { echo '- جدول hr_training
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_project_contracts`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_project_contracts لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_project_contracts`')) { echo '- جدول hr_project_contracts
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_performance`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_performance لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_performance`')) { echo '- جدول hr_performance
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_payroll_runs`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_payroll_runs لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_payroll_runs`')) { echo '- جدول hr_payroll_runs
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_payroll_lines`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_payroll_lines لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_payroll_lines`')) { echo '- جدول hr_payroll_lines
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_job_movements`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_job_movements لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_job_movements`')) { echo '- جدول hr_job_movements
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_workforce_report`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_workforce_report لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_workforce_report`')) { echo '- جدول hr_workforce_report
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_recruitment_pipeline`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_recruitment_pipeline لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_recruitment_pipeline`')) { echo '- جدول hr_recruitment_pipeline
'; }

$r = $conn->query('SELECT COUNT(*) FROM `hr_disciplinary`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي hr_disciplinary لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `hr_disciplinary`')) { echo '- جدول hr_disciplinary
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
