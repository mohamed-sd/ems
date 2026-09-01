<?php
/**
 * 2028_04_02_govui_dep14_fields_down.php — DEP-14 · العكس
 * @migration-objects: columns for DEP-14
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

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g128'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g128` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g128 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g128`")) { echo "- mnt_daily_care.g128\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g127'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g127` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g127 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g127`")) { echo "- mnt_daily_care.g127\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g126'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g126` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g126 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g126`")) { echo "- mnt_daily_care.g126\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g125'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g125` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g125 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g125`")) { echo "- mnt_daily_care.g125\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g124'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g124` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g124 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g124`")) { echo "- mnt_daily_care.g124\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g123'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g123` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g123 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g123`")) { echo "- mnt_daily_care.g123\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g122'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g122` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g122 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g122`")) { echo "- mnt_daily_care.g122\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g121'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g121` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g121 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g121`")) { echo "- mnt_daily_care.g121\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g120'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g120` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g120 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g120`")) { echo "- mnt_daily_care.g120\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g119'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g119` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g119 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g119`")) { echo "- mnt_daily_care.g119\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g118'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g118` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g118 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g118`")) { echo "- mnt_daily_care.g118\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g117'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `mnt_daily_care` WHERE `g117` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي mnt_daily_care.g117 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `mnt_daily_care` DROP COLUMN `g117`")) { echo "- mnt_daily_care.g117\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `mnt_external_repairs`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_external_repairs لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_external_repairs`')) { echo '- جدول mnt_external_repairs
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_part_requests`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_part_requests لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_part_requests`')) { echo '- جدول mnt_part_requests
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_kpis`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_kpis لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_kpis`')) { echo '- جدول mnt_kpis
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_repeat_repairs`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_repeat_repairs لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_repeat_repairs`')) { echo '- جدول mnt_repeat_repairs
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_dashboard_kpi`')) { echo '- جدول mnt_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_preventive_plans`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_preventive_plans لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_preventive_plans`')) { echo '- جدول mnt_preventive_plans
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_work_orders`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_work_orders لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_work_orders`')) { echo '- جدول mnt_work_orders
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_workshop`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_workshop لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_workshop`')) { echo '- جدول mnt_workshop
'; }

$r = $conn->query('SELECT COUNT(*) FROM `mnt_breakdown_intake`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي mnt_breakdown_intake لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `mnt_breakdown_intake`')) { echo '- جدول mnt_breakdown_intake
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
