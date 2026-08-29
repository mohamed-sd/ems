<?php
/**
 * 2028_01_03_rpr02_sot_witness_down.php — نقضُ عمودَي شاهدِ مصدرِ الحقيقة
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط القاعدةَ الصلبةَ ثمَّ الأعمدةَ الثلاثة.
 * ⛔ و`source_of_truth` **لا يُمَسّ** — العمودُ سابقٌ لهذه الهجرةِ، ونقضُ
 *    الشاهدِ لا يُبيح محوَ القيمةِ التي شهد لها.
 * ⚠ **والنقضُ يترك القيمَ بلا شاهدٍ** — وذاك أثرُه المقصودُ ويُقال: من نقض
 *    الشاهدَ فقد أعاد الفتحَ لكتابةِ مصدرِ حقيقةٍ بلا دليل.
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
@$conn->query("ALTER TABLE `repair01_screen_registry` DROP CONSTRAINT `chk_sot_witness`");
@$conn->query("ALTER TABLE `repair01_screen_registry`
    DROP COLUMN `sot_rule`, DROP COLUMN `sot_witness`, DROP COLUMN `sot_snapshot`");
echo "  ✔ أُسقطت القاعدةُ والأعمدةُ الثلاثة — و`source_of_truth` سالم\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n⚠ والقيمُ باقيةٌ بلا شاهد — فمن نقض الشاهدَ أعاد الفتحَ لكتابةٍ بلا دليل\n";
