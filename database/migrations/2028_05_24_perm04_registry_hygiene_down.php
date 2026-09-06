<?php
/**
 * 2028_05_24_perm04_registry_hygiene_down.php — عكسُ نظافةِ السجلّاتِ الحاكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُقلَب دورةُ الحياةِ إلى الوراء**: القوالبُ التي فُعِّلت يحملها
 *   موظّفون، وإسقاطُها يقطعهم عن النظامِ كلِّه. فالعكسُ ينزع ما يُنزَع بأمان،
 *   وما يخصُّ الإذنَ يُنزع من شاشةِ البناءِ بأثرٍ مسجَّل لا بهجرة.
 *
 * ◆ **والمسودّاتُ المتقاعدةُ تبقى**: إقعادُها مرَّ بالخدمةِ وترك سطرَه،
 *   وإحياؤها بهجرةٍ يكتب حالًا بلا سبب. تُنسَخ من الكونسولِ إن لزمت.
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

echo "══ عكسُ نظافةِ السجلّاتِ الحاكمة ══
";
$conn->query("UPDATE fin_approval_types SET active = 1 WHERE code NOT LIKE 'APR-%'");
printf("   أنواعُ اعتمادٍ أُعيدت: %d
", $conn->affected_rows);
$conn->query("UPDATE fin_approval_conflicts SET active = 1 WHERE apr_a NOT LIKE 'APR-%' OR apr_b NOT LIKE 'APR-%'");
printf("   تعارضاتٌ أُعيدت: %d
", $conn->affected_rows);
printf("
   ⚠ مسودّاتٌ متقاعدةٌ لم تُحيَ: %d — تُنسَخ من الكونسولِ إن لزمت
",
    $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='retired'"));

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "
✔ عُكس ما يُعكَس بأمان.
";
