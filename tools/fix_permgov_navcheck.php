<?php
/**
 * tools/fix_permgov_navcheck.php — أتظهر شاشةٌ في قائمةِ دورٍ بعينه؟
 * ═══════════════════════════════════════════════════════════════════════════
 * يُشغَّل **بعمليةٍ منفصلةٍ لكلِّ دور**. والسببُ مقيس: الجلسةُ تتلوّث بين الأدوارِ
 * داخلَ العمليةِ الواحدةِ فيرث الدورُ التالي قائمةَ سابقِه — فيبدو أنَّ الشاشةَ
 * تظهر للجميع. (النمطُ نفسُه في `tools/fix_nav_href_probe.php`.)
 *
 *   php tools/fix_permgov_navcheck.php <رقمُ الدور> <مسارُ الشاشة>
 *   ⇒ يطبع FOUND أو MISSING
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$role = isset($argv[1]) ? (int) $argv[1] : 0;
$rel  = isset($argv[2]) ? (string) $argv[2] : '';
if ($role <= 0 || $rel === '') { exit("MISSING (وسائطُ ناقصة)\n"); }

ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/unified_nav.php';
$conn = $GLOBALS['conn'];

$items = getUnifiedNavItems($conn, $role);
$base = basename($rel);
foreach ($items as $it) {
    $route = ltrim(preg_replace('~^(\.\./)+~', '', (string) $it['route']), '/');
    $route = explode('#', $route, 2)[0];
    if ($route === $rel || basename($route) === $base) {
        fwrite(STDOUT, "FOUND role={$role} route={$route}\n");
        exit(0);
    }
}
fwrite(STDOUT, "MISSING role={$role} items=" . count($items) . "\n");
exit(1);
