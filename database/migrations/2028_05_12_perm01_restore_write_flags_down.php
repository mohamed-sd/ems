<?php
/**
 * 2028_05_12_perm01_restore_write_flags_down.php — إعادةُ القوالبِ إلى العرضِ وحدَه
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **والتراجعُ يُعيد العطبَ**: إطفاءُ أعلامِ الكتابةِ يُرجِع الخمسةَ والسبعين
 *   إلى «القراءةِ فقط». لا تتراجع إلّا لعزلِ عطبٍ، ثمَّ أعِد التشغيلَ فورًا.
 * ◆ **والإطفاءُ شاملٌ عمدًا**: الحالُ قبلَ الهجرةِ كان **صفرَ علمِ كتابةٍ**
 *   مقيسًا في 2,831 بندًا — فالإرجاعُ إلى الصفرِ إرجاعٌ أمينٌ لا تقريب.
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

$conn->query("UPDATE gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                 SET i.can_add = 0, i.can_edit = 0, i.can_delete = 0
               WHERE i.item_kind = 'screen'");
printf("بنودٌ أُطفئت أعلامُها: %d\n", $conn->affected_rows);

$left = (int) $conn->query("SELECT COUNT(*) FROM gov_profile_items i
                              JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                             WHERE i.item_kind='screen' AND (i.can_add|i.can_edit|i.can_delete)=1")->fetch_row()[0];
printf("★ بقي بأعلامِ كتابة: %d %s\n", $left, $left === 0 ? '' : '✘');

foreach (array('2028_05_12_perm01_restore_write_flags.php', basename(__FILE__)) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع — والنظام عاد الى القراءة فقط.\n";
