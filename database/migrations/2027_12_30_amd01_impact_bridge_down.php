<?php
/**
 * 2027_12_30_amd01_impact_bridge_down.php — نقضُ موضعِ جسرِ الأثر
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط `repair01_decision_screen_bridge` بقيدَيه.
 * ⛔ و`repair01_decisions.affected_screens` لا يُمَسّ — فهو مصدرٌ حاكمٌ لا
 *    من إنشاءِ هذه الهجرة.
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
$conn->query("DROP TABLE IF EXISTS `repair01_decision_screen_bridge`");
echo "  ✔ أُسقط `repair01_decision_screen_bridge`\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقض موضعُ الجسر — والمصدرُ الحاكمُ سالم\n";
