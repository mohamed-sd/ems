<?php
/**
 * 2028_02_23_dep02_quota_table_misruling_fix_down.php — العكسُ المسوّى
 * @migration-objects: reverse — يعيد الحالَ إلى ما بعدَ 2028_02_16 (الحكمَ الخاطئ)
 * ⛔ **والعكسُ يعيد خطأً معروفًا** — يُسمّى صراحةً: أعمدةُ السطحِ 11 تعود إلى
 *   جدولِ السطحِ 12، والأعمدةُ الأصليةُ تُسقَط ثانيةً. لا يُشغَّل إلّا لاستعادةِ
 *   حالةٍ سابقةٍ بعينِها، والجدولُ يجب أن يكون فارغًا.
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$rows = (int) $conn->query("SELECT COUNT(*) FROM `sup_quota_supplier_unit`")->fetch_row()[0];
if ($rows > 0) { exit("⛔ الجدولُ ليس فارغًا — لا تراجعَ بلا حكمِ بيانات\n"); }
foreach (array('c2','c4','c12','c14','c19','month_21','c25','c26','c27','c28','equipment_30',
               'c32','c34','c35','supplier_36','c37','c38','c39','c40','c47','c48','c49','c50') as $c) {
    $conn->query("ALTER TABLE `sup_quota_supplier_unit` DROP COLUMN `{$c}`");
}
echo "reverted (الحكمُ الخاطئُ أُعيد عمدًا — انظر رأسَ الملف)\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
