<?php
/**
 * 2028_05_22_perm03_cap_ruling_and_cleanup_down.php — عكسُ حكمِ بندِ السقفِ وكنسِ المسبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُقلَب دورةُ الحياةِ إلى الوراء**: القوالبُ التي فُعِّلت يحملها
 *   موظّفون، وإسقاطُها يقطعهم عن النظامِ كلِّه. فالعكسُ ينزع ما يُنزَع بأمان،
 *   وما يخصُّ الإذنَ يُنزع من شاشةِ البناءِ بأثرٍ مسجَّل لا بهجرة.
 *
 * ◆ **والمنزوعُ لا يُعاد**: قالبُ المسبارِ نُزع وسطورُ أثرِه باقيةٌ تاريخًا —
 *   وإعادةُ صدفةِ مسبارٍ إلى السجلِّ عبثٌ لا تراجع.
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

echo "══ عكسُ حكمِ بندِ السقفِ وكنسِ المسبار ══
";
$conn->query("DELETE FROM perm01_layer_ruling WHERE doc_ref = 'PERM-03-REVERSE-AUDIT'");
printf("   أحكامٌ منزوعة: %d
", $conn->affected_rows);
printf("   أحكامٌ باقية: %d
", $one("SELECT COUNT(*) FROM perm01_layer_ruling"));

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "
✔ عُكس ما يُعكَس بأمان.
";
