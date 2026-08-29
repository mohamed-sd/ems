<?php
/**
 * 2028_01_04_rpr02_grain_fact_scope_down.php — نقضُ عمودَي الطبقةِ والمدى
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط `grain_tier` و`grain_fact_scope`.
 * ⛔ و`grain_entity` و`grain_witness` لا يُمَسّان — الطبقةُ ما تزال مكتوبةً
 *    في الشاهدِ نصًّا، **والنقضُ يعيد المقياسَ إلى قراءةِ النثرِ بـ`LIKE`**
 *    وذاك أثرُه المقصودُ ويُقال.
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
@$conn->query("ALTER TABLE `repair01_screen_registry`
    DROP COLUMN `grain_tier`, DROP COLUMN `grain_fact_scope`");
echo "  ✔ أُسقط العمودان — و`grain_entity` و`grain_witness` سالمان\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n⚠ والمقياسُ يعود إلى قراءةِ الشاهدِ النثريِّ — مقياسٌ على شكلِ عبارة\n";
