<?php
/**
 * 2027_12_13_repair01_freeze_window_kind_down.php — نقضُ النافذتَين
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والقاعدةُ تُسقَط قبلَ عمودِها** — وإلّا رُدَّ الإسقاط.
 * ═══════════════════════════════════════════════════════════════════════════
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

$r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_frz_census'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    $conn->query("ALTER TABLE `repair01_freeze_snapshot` DROP CONSTRAINT `chk_frz_census`");
    echo "  ✔ أُسقطت القاعدة\n";
}
foreach (array('regression_census', 'window_kind') as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `repair01_freeze_snapshot` LIKE '" . $c . "'");
    if ($r && $r->num_rows) {
        $conn->query("ALTER TABLE `repair01_freeze_snapshot` DROP COLUMN `" . $c . "`");
        echo "  ✔ أُسقط `{$c}`\n";
    }
}
echo "✔ نُقضت النافذتان\n";
