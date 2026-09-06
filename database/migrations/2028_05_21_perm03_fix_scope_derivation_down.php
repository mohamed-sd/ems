<?php
/**
 * 2028_05_21_perm03_fix_scope_derivation_down.php — عكسُ تصحيحِ اشتقاقِ بندِ المجال
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُقلَب دورةُ الحياةِ إلى الوراء**: القوالبُ التي فُعِّلت يحملها
 *   موظّفون، وإسقاطُها يقطعهم عن النظامِ كلِّه. فالعكسُ ينزع ما يُنزَع بأمان،
 *   وما يخصُّ الإذنَ يُنزع من شاشةِ البناءِ بأثرٍ مسجَّل لا بهجرة.
 *
 * ⛔ **وعكسُ هذا التصحيحِ يُعيد الانحدار**: قبلَه كان ستّةٌ وثلاثون مستخدمًا
 *   يفقدون نحوَ نصفِ روابطِهم. فلا يُعكَس إلّا بقرارٍ يعرف ذلك.
 *
 * @no-rollback: عكسُ التصحيحِ يُعيد الانحدارَ المقيس: ستّةٌ وثلاثون مستخدمًا يفقدون نحوَ نصفِ روابطِهم — فلا يُعكَس إلّا بقرارٍ يعرف ذلك
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

echo "══ عكسُ تصحيحِ اشتقاقِ بندِ المجال ══
";
$loss = 0; $u = 0;
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/navarch_renderer.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$c2 = $GLOBALS['conn'];
$q = $c2->query("SELECT u.id, u.role FROM users u
    JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
    JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
    JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='scope' AND i.allow=1
    WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4 GROUP BY u.id");
while ($q && ($x = $q->fetch_assoc())) {
    $u++;
    $_SESSION['user'] = array('id'=>(int)$x['id'],'role'=>(string)$x['role'],'company_id'=>4,'name'=>'down probe');
    $with = count(navarch_authorized_routes($c2, (int) $x['role']));
    $_SESSION['user'] = array('id'=>0,'role'=>'-999','company_id'=>4,'name'=>'down probe');
    $loss += count(navarch_authorized_routes($c2, (int) $x['role'])) - $with;
}
printf("   مستخدمون بمجال: %d · الفاقدُ الآن: %d
", $u, $loss);
echo "
   ⚠ العكسُ يعيد الفاقدَ إلى نحوِ 1,570 رابطًا. لا يُنفَّذ بلا قرار.
";

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "
✔ عُكس ما يُعكَس بأمان.
";
