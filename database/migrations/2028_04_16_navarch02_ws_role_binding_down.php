<?php
/**
 * 2028_04_16_navarch02_ws_role_binding_down.php — عكسُ ربطِ الأدوارِ الفرعيّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يحذف صفوفَ `SECONDARY` وحدَها ثمَّ العمودَين — و**صفوفُ `PRIMARY` لا
 *   تُمَسّ**، غيرَ أنَّ `ruling` يعود فارغًا معها بحذفِ العمود.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$conn->query("DELETE FROM nav_ws_roles WHERE binding = 'SECONDARY'");
echo "- صفوفُ SECONDARY: " . $conn->affected_rows . "\n";
foreach (array('parent_role_id', 'ruling') as $col) {
    $q = $conn->query("SHOW COLUMNS FROM `nav_ws_roles` LIKE '{$col}'");
    if ($q && $q->num_rows) {
        if ($conn->query("ALTER TABLE `nav_ws_roles` DROP COLUMN `{$col}`")) { echo "- nav_ws_roles.{$col}\n"; }
        else { echo "x {$col}: " . $conn->error . "\n"; }
    }
}
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_04_16_navarch02_ws_role_binding.php'");
echo "- قيدُ الدفتر: " . $conn->affected_rows . "\n";
