<?php
/**
 * 2028_02_08_users_role_id_retire_down.php — العكس
 * @migration-objects: alter:users(role_id comment revert)
 * الردمُ إثراءُ بياناتٍ لا يُعكس بمحوٍ (م111: الفراغُ «غيرُ مقيس») — العكسُ
 * يقتصر على إزالةِ وسمِ التقاعدِ من المخطَّط.
 * التشغيل: php database/migrations/2028_02_08_users_role_id_retire_down.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
$conn->query("ALTER TABLE users MODIFY role_id INT NULL COMMENT ''");
echo "reverted comment\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
