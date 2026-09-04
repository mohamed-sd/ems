<?php
/**
 * 2028_05_10_perm01_screen_identity_down.php — نزعُ بذرِ إغلاقِ الوصول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُحذف ما وسمَته الهجرةُ وحدَه**: الشرطُ على `seeded_from` لا على
 *   `item_ref` — فقالبٌ كان يملك الكرتَ **قبلَ** الهجرةِ لا يُمَسّ. وحذفٌ
 *   بالمرجعِ وحدَه يسحب منحًا لم تُنشئها الهجرة.
 *
 * ⚠ **والتراجعُ يُعيد الشاشةَ إلى ما كانت**: أعِد حارسَ العرضِ في
 *   `Equipments/equipment_profile.php` مع هذا التراجعِ إن كان قد أُضيف —
 *   وإلا صارت الشاشةُ محروسةً بلا بذرٍ فتردُّ من كان يفتحها.
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$MARK = 'link_closure:equipments';
$conn->query("DELETE FROM gov_profile_items WHERE seeded_from = '{$MARK}'");
printf("بنودٌ نُزعت: %d\n", $conn->affected_rows);

$left = $conn->query("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from='{$MARK}'")->fetch_row()[0];
printf("★ بقي من بذرِ الهجرةِ: %d %s\n", (int) $left, ((int) $left === 0) ? '' : '✘');

foreach (array('2028_05_10_perm01_screen_identity.php', basename(__FILE__)) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع.\n";
