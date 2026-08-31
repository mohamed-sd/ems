<?php
/**
 * 2028_02_02_navr_placement_model_down.php — عكسُ نموذجِ الملاحةِ الجديد
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: drop nav_placements · nav_lifecycle_groups · nav_ws_roles ·
 *   nav_workspaces · gov_nav_findings (بترتيبِ الأبناءِ قبل الآباء — FK)
 * التشغيل: php database/migrations/2028_02_02_navr_placement_model_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

foreach (array('nav_placements', 'nav_lifecycle_groups', 'nav_ws_roles', 'nav_workspaces', 'gov_nav_findings') as $t) {
    $conn->query("DROP TABLE IF EXISTS `{$t}`");
    echo "  ✔ أُسقط {$t}\n";
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "✔ العكسُ اكتمل وقُيّد\n";
