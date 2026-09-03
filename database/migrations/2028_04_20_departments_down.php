<?php
/**
 * 2028_04_20_departments_down.php — عكسُ سجلِّ الإدارات
 * ◆ يُسقط `departments` — الجدولُ أنشأته هذه الهجرةُ وحدَها، وقارئُه الوحيدُ
 *   `admin/departments.php` (‏شاشةُ لوحةِ الإدارةِ العليا) وهي تهبط إلى جدولٍ
 *   فارغٍ برسالةِ خطأٍ مسجَّلةٍ إن غاب.
 * ⚠ وبعد العكسِ يُنزَع سطرُ `departments` من `TenantRegistry` يدويًّا — السجلُّ
 *   كودٌ لا بيانات، والهجرةُ لا تحرّر شيفرةً.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$c = $conn->query('SELECT COUNT(*) c FROM `departments`');
$n = $c ? (int) $c->fetch_assoc()['c'] : 0;
$conn->query('DROP TABLE IF EXISTS `departments`');
echo "- أُسقط departments (كان فيه {$n} إدارةً)\n";

$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_04_20_departments.php'");
echo '- قيدُ الدفتر: ' . $conn->affected_rows . "\n";
