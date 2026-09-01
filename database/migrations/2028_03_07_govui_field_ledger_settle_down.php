<?php
/**
 * 2028_03_07_govui_field_ledger_settle_down.php — ردُّ دفترِ الحقولِ من نسخةِ رجوعِه
 * @migration-objects: restore repair01_fields from govui_fields_pre_settle
 * ◆ ⛔ **ولا يردُّ أعمدةَ التصميمِ في `repair01_requirements`**: لم تُحذف صفوفٌ
 *   ولم تُمَسّ أعمدةُ حكمٍ، وردُّ نصِّ التصميمِ إلى الحزمةِ القديمةِ يعني
 *   **إرجاعَ المخزنِ إلى ملفٍّ مُنحّى** — وهو ما يمنعه §2. فالردُّ للحقولِ وحدَها،
 *   وهو المقامُ الذي تُقاس عليه النسبة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
$r = $conn->query("SHOW TABLES LIKE 'govui_fields_pre_settle'");
if (!$r || !$r->num_rows) { exit("⛔ لا نسخةَ رجوعٍ — لا يُردّ بالتخمين\n"); }
$conn->query("DELETE FROM repair01_fields");
$conn->query("INSERT INTO repair01_fields SELECT * FROM govui_fields_pre_settle");
$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_fields")->fetch_row()[0];
echo "reverted {$n} حقلًا\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
