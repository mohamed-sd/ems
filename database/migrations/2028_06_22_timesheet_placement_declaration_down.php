<?php
/**
 * تراجعُ 2028_06_22 — يُسقط إعلاناتِ هذه الجولةِ وحدَها.
 * ◆ **بالوسمِ لا بالتخمين**: تُحذَف الصفوفُ التي `source_ref` فيها وسمُ هذه
 *   الهجرةِ حصرًا — فلا يُمَسُّ إعلانٌ كتبته ورقةُ الدليلِ أو جولةٌ أخرى.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$mark = 'إتمامُ 2028_06_19 — الموضعُ كُتب في nav_workspace_placements ولم يُعلَن في ورقةِ الدليل (U3)';
$st = $conn->prepare('DELETE FROM nav_placements WHERE source_ref = ?');
$st->bind_param('s', $mark);
$st->execute();
echo "  ✔ إعلاناتٌ أُسقطت: " . $st->affected_rows . "\n";
