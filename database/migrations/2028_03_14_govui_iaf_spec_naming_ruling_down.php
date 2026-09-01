<?php
/**
 * 2028_03_14_govui_iaf_spec_naming_ruling_down.php — العكس
 * @migration-objects: remove gov_migration_settlement row
 * ◆ يُحذف حكمُ الانطباقِ لهذا الملفِّ وحدَه — ولا يُمَسُّ حكمُ `_ledger.php`
 *   ولا أيُّ صفٍّ سواه.
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

$st = $conn->prepare("DELETE FROM gov_migration_settlement
                       WHERE filename = '_iaf_field_closure_spec.php' AND ruling = 'NOT_A_MIGRATION'");
if ($st) { $st->execute(); echo "محذوف: " . $st->affected_rows . "\n"; $st->close(); }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
