<?php
/**
 * 2028_05_17_perm01_function_screen_map_down.php — نزعُ خريطةِ الوظيفةِ والشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **والتراجعُ يُعيد البندَ ① إلى مقامِه المعطوب**: بلا الخريطةِ يعود المقياسُ
 *   إلى فرقِ مجموعتَي الأدوارِ الذي يسحب شاشاتٍ لا صلةَ لها بالتعارض.
 * ◆ ولا يمسُّ صفًّا في `sec_sod_pairs` ولا في القوالب — الخريطةُ سجلُّ اشتقاقٍ
 *   لا سياسة.
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$conn->query("DROP TABLE IF EXISTS `perm01_function_screen`");
$left = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                             WHERE TABLE_SCHEMA=DATABASE()
                               AND TABLE_NAME='perm01_function_screen'")->fetch_row()[0];
printf("★ الجدولُ أُسقط: %s\n", $left === 0 ? 'نعم' : '✘ ما يزال قائمًا');

foreach (array('2028_05_17_perm01_function_screen_map.php', basename(__FILE__)) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع.\n";
