<?php
/**
 * 2028_05_21_perm01_restore_removed_screens_down.php — نزعُ البنودِ المُكمَّلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **والتراجعُ يُعيد القفلَ على 30 دورًا**: نزعُ هذه البنودِ يُرجع الفجوةَ إلى
 *   1,147 زوجًا، ويعود مشرفُ المبيعاتِ إلى 16 سطحًا من 46. فلا يُشغَّل إلّا
 *   بقرارٍ يعرف ما يفعل.
 *
 * ◆ **والنزعُ بالبذرِ المسمّى وحدَه**: لا يُحذف إلّا ما حمل وسمَ
 *   `perm01_restore_20260905:` — فبنودُ القالبِ الأصليّةُ (`TGT-*`) لا تُمَسّ،
 *   ولا بندٌ أضافه غيرُ هذه الهجرة.
 * ⛔ ولا يمسُّ منحةً ولا حالةَ قالبٍ ولا `role_permissions`.
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

$n = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from LIKE 'perm01_restore_20260905:%'");
printf("بنودٌ موسومةٌ بالبذرِ ستُنزَع: %d\n", $n);

if (!$conn->query("DELETE FROM gov_profile_items WHERE seeded_from LIKE 'perm01_restore_20260905:%'")) {
    exit("✘ فشل النزع: " . $conn->error . "\n");
}
printf("نُزع: %d\n", $conn->affected_rows);

$left = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from LIKE 'perm01_restore_20260905:%'");
printf("★ ضابطٌ سالب — بقيّةٌ بعدَ النزع: %d %s\n", $left, $left === 0 ? '' : '✘');

foreach (array(basename(__FILE__), str_replace('_down.php', '.php', basename(__FILE__))) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع — والفجوةُ عادت، فاقرأْ التحذيرَ أعلاه.\n";
