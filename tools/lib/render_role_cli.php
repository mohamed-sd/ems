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

/* العنوانُ الفرعيُّ (nav-subhead) الأقربُ فوق كلِّ رابطٍ — فمجموعاتُ الملفِّ
   التصميميِّ عناوينُ فرعيّةٌ داخل رؤوسِ الطيِّ لا الرؤوسُ نفسُها. يُمسح
   HTML تسلسليًّا: رأسُ طيٍّ يصفّر الفرعيَّ، وفرعيٌّ يعلَّم، ورابطٌ يُنسب. */
$sub = array(); $curSub = '';
if (preg_match_all('~<li class="nav-subhead"[^>]*><span>(.*?)</span>|<span class="nav-group-name">(.*?)</span>|<a[^>]+href="([^"]+)"~su', $html, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        if (isset($m[3]) && $m[3] !== '') {
            $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim(html_entity_decode($m[3])))));
            if ($b !== '' && !isset($sub[$b])) { $sub[$b] = $curSub; }
        } elseif (isset($m[2]) && $m[2] !== '') {
            $curSub = '';                                    /* رأسُ طيٍّ جديدٌ يصفّر الفرعيّ */
        } elseif (isset($m[1]) && $m[1] !== '') {
            $curSub = trim(html_entity_decode(strip_tags($m[1])));
        }
    }
}
foreach ($out['positions'] as $i => $p) {
    $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $p['h']))));
    $out['positions'][$i]['s'] = isset($sub[$b]) ? $sub[$b] : '';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
