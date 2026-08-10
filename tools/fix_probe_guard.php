<?php
/**
 * tools/fix_probe_guard.php — نداءٌ حيٌّ للحارسِ المركزي (شاهدُ AC-F1)
 * ═══════════════════════════════════════════════════════════════════════════
 * يُشغَّل في **عمليةٍ منفصلة**: يبني جلسةَ دورٍ حقيقيٍّ ثم يسأل الحارسَ عن شاشةٍ
 * غيرِ مسجَّلةٍ قطعًا، ويطبع ما أرجعه. ◆ هذا إثباتٌ بالنداءِ لا بمطابقةِ نصّ.
 *
 * الاستعمال: php tools/fix_probe_guard.php <مسارُ الشاشة> [رقمُ الدور]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

$screen = isset($argv[1]) ? $argv[1] : 'ZZ_UNREGISTERED/never_exists.php';
$role   = isset($argv[2]) ? (int) $argv[2] : 1;

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME']  = '/ems/' . ltrim($screen, '/');
$_SERVER['REQUEST_URI']  = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['user'] = array('id' => 0, 'role' => $role, 'company_id' => 1, 'name' => 'fix-probe');

$perms = get_current_page_permissions($conn, $_SERVER['SCRIPT_NAME']);
$byUrl = get_page_permissions($conn, $_SERVER['REQUEST_URI']);

$verdict = array(
    'screen'      => $screen,
    'role'        => $role,
    'id'          => $perms['id'],
    'can_view'    => !empty($perms['can_view']),
    'can_add'     => !empty($perms['can_add']),
    'can_edit'    => !empty($perms['can_edit']),
    'can_delete'  => !empty($perms['can_delete']),
    'can_export'  => !empty($perms['can_export']),
    'by_url_view' => !empty($byUrl['can_view']),
);
echo "GUARD|" . json_encode($verdict, JSON_UNESCAPED_UNICODE) . "\n";
