<?php
/**
 * 2028_05_19_perm03_layers_and_recorder_down.php — عكسُ تسجيلِ شاشةِ تسجيلِ قرارِ الاعتماد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُقلَب دورةُ الحياةِ إلى الوراء**: القوالبُ التي فُعِّلت يحملها
 *   موظّفون، وإسقاطُها يقطعهم عن النظامِ كلِّه (مغلقٌ افتراضيًّا). فالعكسُ
 *   **ينزع ما أضافته الهجرةُ من هويّةٍ وموضعٍ وحكمٍ**، ويترك الإذنَ لمسارِه:
 *   يُنزع من شاشةِ البناءِ بالاستبعادِ بأثرٍ مسجَّل، لا بهجرة.
 *
 * ◆ **وبنودُ الأفعالِ تبقى**: نزعُها يُبطل حارسَ الفعلِ في بوّابةِ الاعتماد،
 *   وذاك قرارُ سياسةٍ يُتّخذ من الكونسول لا أثرٌ جانبيٌّ لعكسِ تسجيل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط
"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "
"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };
$e = function ($s) use ($conn) { return $conn->real_escape_string($s); };

echo "══ عكسُ تسجيلِ شاشةِ تسجيلِ قرارِ الاعتماد ══
";

$SCREEN = 'Finance/acc_approval_record.php';
$c = $e($SCREEN);
$lower = strtolower(str_replace('.php', '', $SCREEN));
$n = array();
/* ◆ **ولا يُنزع ما لم تُضِفْه الهجرة**: هذه سجّلت الهويّةَ وتصريحَ الظهورِ
     وبندَ الملاحةِ فقط — ولا مواضعَ دليلٍ ولا مساحةٍ لها. فلا تُلمَس. */
$conn->query("DELETE FROM nav_items WHERE route = '{$c}'");
$n['nav_items'] = $conn->affected_rows;
$mid = $one("SELECT id FROM modules WHERE code = '{$c}' LIMIT 1");
if ($mid > 0) {
    $conn->query("DELETE FROM role_permissions WHERE module_id = {$mid}");
    $n['role_permissions'] = $conn->affected_rows;
    $conn->query("DELETE FROM modules WHERE id = {$mid}");
    $n['modules'] = $conn->affected_rows;
}
foreach ($n as $t => $k) { printf("   %-28s منزوع: %d
", $t, $k); }
$left = $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_ref = '{$c}' AND allow = 1");
printf("
   ⚠ بنودُ قوالبٍ باقيةٌ على الشاشة: %d — تُنزع من شاشةِ البناءِ لا من هنا
", $left);

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "
✔ عُكس ما يُعكَس — والإذنُ يبقى لمسارِه المحروس.
";
