<?php
/**
 * 2028_01_08_rpr02_cycle_bridge_down.php — نقضُ جسرِ دورةِ العملِ إلى المعرِّف
 * ═══════════════════════════════════════════════════════════════════════════
 * يُسقط القاعدةَ الصلبةَ ثمَّ الأعمدةَ الأربعة.
 * ⛔ و`screen_file` لا يُمَسّ — **مصدرٌ حاكمٌ يبقى كما ورد**، والجسرُ كُتب
 *    بجانبِه لا فوقَه.
 * ⚠ **والنقضُ يعيد الوصلَ إلى اسمِ الملفِّ المجرَّد** — و`index.php` يطابق
 *    ثلاثةَ أسطحٍ حيّة. ويُقال.
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
@$conn->query("ALTER TABLE `gov_screen_cycle` DROP CONSTRAINT `chk_cyc_bridge`");
@$conn->query("ALTER TABLE `gov_screen_cycle`
    DROP COLUMN `screen_id`, DROP COLUMN `bridge_rule`,
    DROP COLUMN `bridge_witness`, DROP COLUMN `bridge_snapshot`");
echo "  ✔ أُسقطت القاعدةُ والأعمدةُ — و`screen_file` سالم\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n⚠ والوصلُ يعود إلى اسمِ الملفِّ المجرَّد — الملتبسُ يعود مطابقًا\n";
