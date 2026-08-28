<?php
/**
 * 2027_12_24_rpr03_event_dead_letter_rulings_down.php — نقضُ أحكامِ ميّتِ الأحداث
 * ⛔ **والنقضُ يمحو أحكامًا بأسبابِها** — فتُقاس ويُردُّ النقضُ إن وُجدت.
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
$r = @$conn->query("SELECT COUNT(*) FROM rpr03_event_dead_letter_rulings
                     WHERE ruling <> 'NEEDS_ADJUDICATION'");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { exit("⛔ **$n رسالةً محكومٌ عليها بسببِها** — صرِّحْ بالمحو أوّلًا.\n"); }
$conn->query("DROP TABLE IF EXISTS `rpr03_event_dead_letter_rulings`");
echo "✔ نُقض سجلُّ أحكامِ ميّتِ الأحداث\n";
