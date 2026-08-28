<?php
/** 2027_12_19_amd01_decision_impact_down.php — نقضُ سجلِّ الأثر · ⛔ ويُقاس المُسقَطُ أوّلًا */
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
$r = @$conn->query("SELECT COUNT(*) FROM repair01_decision_impact WHERE status='PROJECTED'");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { exit("⛔ **$n أثرًا مُسقَطًا بمصدرِه** — صرِّحْ بالمحو أوّلًا.\n"); }
$conn->query("DROP TABLE IF EXISTS `repair01_decision_impact`");
echo "✔ نُقض سجلُّ الأثر\n";
