<?php
/**
 * 2028_05_15_perm01_retire_parallel_layer_down.php — نزعُ أحكامِ ق-٦
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **والتراجعُ يُعيد الطبقةَ إلى «مبنيّةٌ بلا حكم»** — ولا يُعيد إليها نفاذًا،
 *   لأنَّ الوسمَ لم يسلبها شيئًا: هي غيرُ مقروءةٍ في قرارِ الشاشةِ أصلًا.
 * ◆ ولا يمسُّ صفًّا من `effective_permissions` ولا من سجلِّ التدقيق.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط
"); }
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
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "
"); }
$conn->set_charset('utf8mb4');

$conn->query("DELETE FROM perm01_layer_ruling WHERE doc_ref LIKE '%ق-٦%'");
printf("أحكامٌ نُزعت: %d
", $conn->affected_rows);
foreach (array('2028_05_15_perm01_retire_parallel_layer.php', basename(__FILE__)) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع.
";
