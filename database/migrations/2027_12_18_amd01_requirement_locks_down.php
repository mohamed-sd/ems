<?php
/** 2027_12_18_amd01_requirement_locks_down.php — فكُّ قفلَي الدفتر · والمحتوى باقٍ */
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
foreach (array('chk_req_state_evidence', 'chk_req_state_snapshot') as $c) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$c'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("ALTER TABLE `repair01_requirements` DROP CONSTRAINT `$c`");
        echo "  ✔ رُفعت `$c`\n";
    }
}
echo "✔ فُكَّ القفلان — والمحتوى باقٍ\n";
