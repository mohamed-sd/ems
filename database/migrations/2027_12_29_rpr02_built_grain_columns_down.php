<?php
/**
 * 2027_12_29_rpr02_built_grain_columns_down.php — نقضُ موضعِ الحبّةِ المقيسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ينقض `2027_12_29`: يُسقط القاعدةَ الصلبةَ ثمَّ الأعمدةَ الستّة.
 * ⛔ و`grain_ar` **لا يُمَسّ** — فهو ليس من إنشاءِ هذه الهجرة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_grain_witness'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    $conn->query("ALTER TABLE `repair01_screen_registry` DROP CONSTRAINT `chk_grain_witness`");
    echo "  ✔ أُسقطت `chk_grain_witness`\n";
}
foreach (array('grain_multi','grain_witness','grain_rule','grain_measured','grain_cardinality','grain_entity') as $col) {
    $x = $conn->query("SHOW COLUMNS FROM `repair01_screen_registry` LIKE '$col'");
    if ($x && $x->num_rows) {
        $conn->query("ALTER TABLE `repair01_screen_registry` DROP COLUMN `$col`");
        echo "  ✔ أُسقط `$col`\n";
    }
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقض موضعُ الحبّةِ المقيسة — و`grain_ar` المُعلَنُ سالم\n";
