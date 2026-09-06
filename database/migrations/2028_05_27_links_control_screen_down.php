<?php
/**
 * 2028_05_27_links_control_screen_down.php — عكسُ تسجيلِ «تحكم الروابط»
 * ═══════════════════════════════════════════════════════════════════════════
 * يُزال ما وُلد في الجولةِ وحدَه:
 *   ① بنودُ القوالبِ بوسمِ الجولة (`seeded_from`) — **لا بمطابقةِ اسمِ الشاشة**،
 *      فبندٌ أضافه مديرُ الصلاحياتِ يدويًّا بعدَ الجولةِ يبقى بقرارِ صاحبِه.
 *   ② صفوفُ `role_permissions` على معرِّفِ الموديولِ — كلُّها وُلدت هنا لأنَّ
 *      الموديولَ نفسَه وُلد هنا.
 *   ③ صفُّ الموديول.
 * ⛔ ومنحُ `Settings/settings.php` لا يُمَسُّ في أيِّ خطوة.
 *
 * التشغيل: php database/migrations/2028_05_27_links_control_screen_down.php
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$SRC  = 'Settings/settings.php';
$CODE = 'Settings/links_control.php';
$MARK = 'links_control:2028_05_27';

$srcBefore = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                     JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                    WHERE i.item_kind = 'screen' AND i.item_ref = '{$SRC}' AND i.allow = 1");

$st = $conn->prepare("DELETE FROM gov_profile_items WHERE seeded_from = ?");
$st->bind_param('s', $MARK);
$st->execute();
printf("   ① بنودُ قوالبَ محذوفة: %d\n", $st->affected_rows);
$st->close();

$mid = $one("SELECT id FROM modules WHERE code = '{$CODE}' ORDER BY id LIMIT 1");
if ($mid > 0) {
    $conn->query("DELETE FROM role_permissions WHERE module_id = {$mid}");
    printf("   ② صفوفُ صلاحياتٍ محذوفة: %d\n", $conn->affected_rows);
    $conn->query("DELETE FROM modules WHERE id = {$mid}");
    printf("   ③ صفُّ الموديولِ محذوف: %d\n", $conn->affected_rows);
} else {
    echo "   ②③ لا صفَّ موديولٍ للشاشة — لا شيءَ يُحذف\n";
}

$left = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from = '{$MARK}'")
      + $one("SELECT COUNT(*) FROM modules WHERE code = '{$CODE}'");
printf("   المتبقّي من الجولة: %d (المستهدف صفر)\n", $left);

$srcAfter = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                    JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                   WHERE i.item_kind = 'screen' AND i.item_ref = '{$SRC}' AND i.allow = 1");
printf("   منحُ الإعدادات: %d (كان %d — لا يُمَسّ)\n", $srcAfter, $srcBefore);
if ($left > 0 || $srcAfter !== $srcBefore) { exit("⛔ العكسُ لم يكتمل.\n"); }

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_27_links_control_screen.php'");
echo "✔ عُكس.\n";
