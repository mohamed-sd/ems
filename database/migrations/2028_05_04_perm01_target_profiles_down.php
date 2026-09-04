<?php
/**
 * 2028_05_04_perm01_target_profiles_down.php — عكسُ سجلِّ الأهداف
 * ◆ **العكسُ نظيفٌ بلا علامةِ ماء**: الهجرةُ لم تكتب في جدولٍ قائمٍ حرفًا —
 *   أنشأت جدولَين جديدَين وحدَهما. فإسقاطُهما يُعيد القاعدةَ إلى ما كانت،
 *   ولا يُمَسُّ قالبٌ ولا منحةٌ ولا صلاحيّةُ أحد.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ.
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
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

foreach (array('perm01_target_item', 'perm01_target_profile') as $t) {
    $conn->query("DROP TABLE IF EXISTS `{$t}`");
    echo "- جدولٌ أُسقط: {$t}\n";
}
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_04_perm01_target_profiles.php',
                                    '2028_05_04_perm01_target_profiles_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\nعُكست.\n";
