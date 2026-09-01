<?php
/**
 * 2028_03_17_govui_dep08_field_sources_down.php — العكس
 * @migration-objects: drop table gov_dashboard_kpi · drop col gov_policy.review_periodicity
 * ⛔ ولا يُسقَط ما فيه بياناتٌ صامتًا — يُسمَّى ويُترَك.
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

$q = $conn->query("SHOW TABLES LIKE 'gov_dashboard_kpi'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_dashboard_kpi`");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي gov_dashboard_kpi لبياناتِه ({$n} صفًّا)\n"; }
    elseif ($conn->query("DROP TABLE `gov_dashboard_kpi`")) { echo "أُسقط gov_dashboard_kpi\n"; }
} else { echo "= gov_dashboard_kpi غيرُ موجود\n"; }

$q = $conn->query("SHOW COLUMNS FROM `gov_policy` LIKE 'review_periodicity'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `gov_policy` WHERE `review_periodicity` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي العمودُ لبياناتِه ({$n} صفًّا فيه قيمة)\n"; }
    elseif ($conn->query("ALTER TABLE `gov_policy` DROP COLUMN `review_periodicity`")) { echo "أُسقط العمود\n"; }
} else { echo "= العمودُ غيرُ موجود\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
