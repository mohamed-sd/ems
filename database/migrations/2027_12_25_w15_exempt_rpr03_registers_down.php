<?php
/**
 * 2027_12_25_w15_exempt_rpr03_registers_down.php — سحبُ إعلانِ سجلَّي RPR-03
 * ⛔ **والسحبُ يُرجع `W15-05` أحمرَ** ما دام الجدولان قائمَين — فلا يُشغَّل
 *   إلّا مع نقضِ الجدولَين أنفسِهما.
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
foreach (array('rpr03_event_classification', 'rpr03_event_dead_letter_rulings') as $t) {
    $ex = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $t . "'")->fetch_row()[0];
    if ($ex) { echo "⚠ `$t` ما يزال قائمًا — وسحبُ إعلانِه يُرجع `W15-05` أحمرَ\n"; }
    $conn->query("DELETE FROM repair01_w15_table_exempt WHERE table_name='" . $t . "'");
    echo "  ✔ سُحب إعلانُ `$t`\n";
}
echo "✔ سُحب الإعلان\n";
