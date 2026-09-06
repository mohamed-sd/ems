<?php
/**
 * 2028_05_20_perm03_bind_cap_scope_field_down.php — عكسُ ربطِ السقوفِ والمجالاتِ والحقول
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُقلَب دورةُ الحياةِ إلى الوراء**: القوالبُ التي فُعِّلت يحملها
 *   موظّفون، وإسقاطُها يقطعهم عن النظامِ كلِّه. فالعكسُ ينزع ما يُنزَع بأمان،
 *   وما يخصُّ الإذنَ يُنزع من شاشةِ البناءِ بأثرٍ مسجَّل لا بهجرة.
 *
 * ◆ **والبنودُ لا تُنزَع هنا**: قوالبُها صارت متقاعدةً وخلفتها إصداراتٌ لاحقة،
 *   ونزعُ بندٍ من قالبٍ نافذٍ فعلُ سياسةٍ يمرُّ بالكونسول. فهذا العكسُ **يُبلِّغ
 *   ولا يكتب** — ويُسمّي ما يلزم فعلُه إن أُريد التراجعُ حقًّا.
 *
 * @no-rollback: دورةُ الحياةِ لا تُقلَب: القوالبُ المُفعَّلةُ يحملها موظّفون وإسقاطُها يقطعهم عن النظام — والنزعُ يمرُّ بشاشةِ البناءِ بأثرٍ مسجَّل لا بهجرة
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

echo "══ عكسُ ربطِ السقوفِ والمجالاتِ والحقول ══
";
$kinds = array('cap', 'scope', 'field');
foreach ($kinds as $k) {
    $n = $one("SELECT COUNT(*) FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
               WHERE i.item_kind = '" . $conn->real_escape_string($k) . "' AND i.allow = 1");
    printf("   بنودُ %-6s نافذةٌ الآن: %d
", $k, $n);
}
echo "
   ⚠ التراجعُ يكون بنزعِ البنودِ من شاشةِ البناءِ لكلِّ قالبٍ نافذ،
";
echo "     فيبقى لكلِّ نزعٍ سببُه وفاعلُه. ولا تُمَسُّ صفوفٌ من هنا.
";

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "
✔ عُكس ما يُعكَس بأمان.
";
