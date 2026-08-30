<?php
/**
 * 2028_01_23_ctl_evidence_closure_down.php — تراجعُ مسارِ الإغلاقِ بالدليل
 * ⛔ **يردُّ كلَّ متطلبٍ أُغلق إلى حالتِه السابقةِ من السجلِّ نفسِه** ثمَّ يُسقطه.
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
$back = 0;
$q = @$conn->query("SELECT requirement_id, before_state FROM repair01_evidence_closure");
while ($q && ($x = $q->fetch_assoc())) {
    if ($conn->query("UPDATE repair01_requirements SET amd01_state = '" . $conn->real_escape_string($x['before_state'])
                   . "' WHERE requirement_id = '" . $conn->real_escape_string($x['requirement_id'])
                   . "' AND amd01_state = 'EVIDENCE_CLOSED'")) { $back += $conn->affected_rows; }
}
echo "  ✔ رُدَّ $back متطلبًا إلى حالتِه السابقة\n";
$conn->query("DROP TABLE IF EXISTS `repair01_evidence_closure`");
echo "  ✔ أُسقط `repair01_evidence_closure`\n";
