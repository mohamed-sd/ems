<?php
/**
 * tools/fix_probe_sidebar.php — نداءٌ حيٌّ لمُصيِّرِ القائمة (شاهدُ AC-M1)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يعدُّ صفوفَ ‎nav_items‎ — بل **يُصيِّر القائمةَ فعلًا** بالمُصيِّرِ الحيِّ
 *   ‎renderUnifiedNavigationV2‎ ويعدُّ عناصرَ ‎<a>‎ الناتجة. فالصفوفُ قد تكون
 *   موجودةً والقائمةُ خاويةً (وهي بالضبط حالةُ الأدوارِ 31/32/33: صفوفٌ بمجموعةٍ
 *   فارغةٍ ومسارٍ بادئتُه ‎../‎ فتسقط عند التجميع). عدُّ الصفوفِ يكذب.
 *
 * الاستعمال: php tools/fix_probe_sidebar.php <رقمُ الدور>
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

$role = isset($argv[1]) ? (int) $argv[1] : 0;
$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['user'] = array('id' => 0, 'role' => $role, 'company_id' => 1, 'name' => 'fix-probe');

$html = '';
$rendered = false;
ob_start();
try {
    if (function_exists('renderUnifiedNavigationV2')) {
        $rendered = (bool) renderUnifiedNavigationV2($conn, (string) $role, '../', array(), '');
    }
} catch (Throwable $e) {
    $html = 'EXC:' . $e->getMessage();
}
$html = ob_get_clean() . $html;

$links = preg_match_all('/<a\b[^>]*href=/i', $html);
$groups = preg_match_all('/class="[^"]*(nav-group|sidebar-group)[^"]*"/i', $html);

echo "SIDEBAR|" . json_encode(array(
    'role'     => $role,
    'unified'  => function_exists('unifiedNavEnabled') ? (bool) unifiedNavEnabled((string) $role) : null,
    'rendered' => $rendered,
    'items'    => $links,
    'groups'   => $groups,
    'bytes'    => strlen($html),
), JSON_UNESCAPED_UNICODE) . "\n";
