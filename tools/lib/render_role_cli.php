<?php
/**
 * tools/lib/render_role_cli.php — تصييرُ سايدبارِ دورٍ في عمليّةٍ نقيّةٍ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_RENDER_FIX §٣: «لا تبنِ مُصيِّرًا جديدًا — استعمل
 *   `uxp_render_role_html`» — وهذا **غلافُ عمليّةٍ** لا مُصيِّرٌ: كلُّ نداءٍ
 *   عمليّةُ PHP جديدةٌ فلا يعبر مخبأٌ ساكنٌ من دورٍ لآخرَ ولا من قبلِ
 *   تغييرٍ تجريبيٍّ لِبعدِه (فخُّ [[ctl2-round-five-contracts]] المقيس).
 * ◆ المخرَج JSON: {role, uid, positions:[{g,l,h}…], shells:[{name,links}…]}
 *   — المواضعُ بالترتيبِ الظاهرِ حرفًا.
 *
 * التشغيل: php tools/lib/render_role_cli.php <role_id> [uid]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$role = isset($argv[1]) ? (int) $argv[1] : 0;
$uid  = isset($argv[2]) ? (int) $argv[2] : null;
if ($role <= 0) { fwrite(STDERR, "دور مطلوب\n"); exit(2); }

$html = uxp_render_role_html($conn, $role, $uid);
$pos = uxp_parse_nav_html($html);
$shells = uxp_nav_group_shells($html);
$out = array('role' => $role, 'uid' => $uid, 'positions' => array(), 'shells' => array());
foreach ($pos as $p) {
    $out['positions'][] = array('g' => (string) $p['group'], 'l' => (string) $p['label'],
                                'h' => (string) $p['href']);
}
foreach ($shells as $s) { $out['shells'][] = $s; }
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
