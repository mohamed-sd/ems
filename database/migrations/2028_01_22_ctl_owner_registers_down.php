<?php
/** تراجعُ سجلَّي المالك — ⛔ ويُرفض متى حمل السجلُّ قرارًا صادرًا (`DECIDED`). */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$r = @$conn->query("SELECT COUNT(*) FROM repair01_owner_actions WHERE status = 'DECIDED'");
if ($r && ($x = $r->fetch_row()) && (int) $x[0] > 0) {
    exit("⛔ السجلُّ يحمل {$x[0]} قرارًا صادرًا — لا يُسقط سجلٌّ فيه أحكامُ مالك\n");
}
$conn->query("DROP TABLE IF EXISTS `repair01_owner_actions`");
$conn->query("DROP TABLE IF EXISTS `repair01_platform_ownership`");
echo "  ✔ أُسقط السجلّان — ولم يكن فيهما قرارٌ صادر\n";
