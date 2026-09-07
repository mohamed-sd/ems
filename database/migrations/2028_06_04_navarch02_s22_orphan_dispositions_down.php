<?php
/**
 * تراجعُ 2028_06_04 — يُزيل أحكامَ هذه الجولةِ وحدَها.
 * ◆ **بالإسنادِ لا بالتخمين**: الحذفُ بـ`decision_ref` الخاصِّ بالجولة، فلا يمسُّ
 *   صفًّا كتبته جولةٌ أخرى — و434 صفًّا سابقةً تبقى كما هي.
 * التشغيل: php database/migrations/2028_06_04_navarch02_s22_orphan_dispositions_down.php
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

$ref = 'NAV-ARCH-02 §22 · جولةُ أحكامِ اليتامى 2028-06-04';
$st = $conn->prepare("DELETE FROM `nav_legacy_disposition` WHERE decision_ref = ?");
$st->bind_param('s', $ref);
$st->execute();
echo "  ✔ حُذف {$st->affected_rows} صفًّا من أحكامِ هذه الجولة\n";
$st->close();
