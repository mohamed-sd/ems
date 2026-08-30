<?php
/**
 * tools/ctl_render_probe.php — مجسُّ تصييرٍ فعليٍّ لشاشةٍ واحدةٍ في عمليةٍ معزولة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا عمليةٌ لكلِّ شاشة**: حرّاسُ الشاشاتِ يستدعون `exit()` عند المنعِ
 *   أو التحويل — وعمليةٌ واحدةٌ تجمع شاشاتٍ يقتلها أوّلُ حارس.
 * ◆ ⛔ **ولا يُستبَقُ `config.php`** — قِيس فسقط: الشاشاتُ تُدخله بـ`include`
 *   لا `include_once` (‏هي نقاطُ دخولٍ في الويب فلا يتكرّر)، واستباقُه في
 *   المجسِّ يكرّر تصريحَ دوالِّه **ففاتلٌ صامتٌ برمزِ ٢٥٥ وصفرِ مخرَج**
 *   (`display_errors=0` في config). فهويّةُ المستخدمِ تُقرأ باتصالٍ مستقلٍّ
 *   من `.env`، والجلسةُ عبرَ `session_bootstrap` وحدَه (‏`require_once`
 *   يدمجه مع نداءِ الشاشةِ نفسِها)، والشاشةُ تُجري تمهيدَها كاملًا بنفسِها.
 * ◆ **الجلسةُ جلسةُ مستخدمٍ حقيقيٍّ في co4** — فتسري بواباتُ المنحِ fail-closed.
 *
 * الخرجُ سطرٌ واحد:
 *   `STATUS:OK len=<n> sha=<12>` · `STATUS:EMPTY len=<n>` · `STATUS:ERR <سبب>`
 * التشغيل: php tools/ctl_render_probe.php <route> <role_id>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$route = isset($argv[1]) ? trim($argv[1], '/') : '';
$role  = isset($argv[2]) ? (string) (int) $argv[2] : '0';
if ($route === '' || $role === '0') { echo "STATUS:ERR usage\n"; exit(2); }

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$path = $ROOT . '/' . $route;
if (!is_file($path)) { echo "STATUS:ERR file_absent\n"; exit(2); }

/* بيئةُ الطلبِ كما يراها الخادم */
$_SERVER['SCRIPT_NAME'] = '/ems/' . $route;
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET = array(); $_POST = array();

/* هويّةُ المستخدمِ باتصالٍ مستقلٍّ — ⛔ لا عبر config */
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $port = 3306;
if (strpos($h, ':') !== false) { list($h, $port) = explode(':', $h); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$c0 = @new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if (!$c0 || $c0->connect_errno) { echo "STATUS:ERR db\n"; exit(2); }
$c0->set_charset('utf8mb4');
/* ◆ **القياسُ بالمستخدمِ لا بالدور** ([[permission-dual-path-shadow]]): طبقةُ
   القوالبِ (GOV-AUTH-01) قد تغطّي مستخدمًا بقالبٍ يستثني الشاشةَ فيُمنع وإن
   مُنح دورُه — فيُختار مستخدمٌ **غيرُ مغطًّى بقالبٍ نافذٍ** أو **مغطًّى وقالبُه
   يسمّي الشاشةَ بسماح**؛ فإن لم يوجد فأوّلُ مستخدمٍ (والمنعُ حينها حكمُ
   النظامِ الحقيقيُّ يُبلَّغ بسببِه لا يُلتفُّ عليه). */
$r0 = (int) $role;
$st = $c0->prepare(
    "SELECT u.id FROM users u
      WHERE u.company_id = 4 AND CAST(u.role AS UNSIGNED) = ?
        AND ( NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                           JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                          WHERE g.user_id = u.id AND g.revoked_at IS NULL
                            AND (g.valid_to IS NULL OR g.valid_to > NOW()))
           OR EXISTS(SELECT 1 FROM gov_authority_grants g2
                       JOIN gov_role_profiles p2 ON p2.profile_id = g2.profile_id AND p2.state = 'active'
                       JOIN gov_profile_items i2 ON i2.profile_id = p2.profile_id
                            AND i2.item_kind = 'screen' AND i2.item_ref = ? AND i2.allow = 1
                      WHERE g2.user_id = u.id AND g2.revoked_at IS NULL
                        AND (g2.valid_to IS NULL OR g2.valid_to > NOW())) )
      ORDER BY u.id LIMIT 1");
$uid = 0;
if ($st) {
    $st->bind_param('is', $r0, $route);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $uid = $row ? (int) $row[0] : 0;
    $st->close();
}
if ($uid === 0) {
    $st = $c0->prepare("SELECT MIN(id) FROM users WHERE company_id = 4 AND CAST(role AS UNSIGNED) = ?");
    $st->bind_param('i', $r0);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $uid = $row ? (int) $row[0] : 0;
    $st->close();
}
$c0->close();
if ($uid === 0) { echo "STATUS:ERR no_user_for_role\n"; exit(2); }

/* ◆ **التحويلُ الصامتُ يصير تشخيصًا ناطقًا**: دوالُّ التحويلِ الحوكميِّ محاطةٌ
   بـ`function_exists` في المُساعِدِ — فتعريفُها هنا أوّلًا يجعل كلَّ منعٍ يقول
   وجهتَه ورمزَه بدل `EMPTY` أعمى. ⛔ **ولا يغيّر حكمًا**: المنعُ يبقى منعًا. */
if (!function_exists('ems_gov_flash_redirect')) {
    function ems_gov_flash_redirect($to, $message, $code = 'GOV-403', $hint = '')
    {
        $GLOBALS['__ctl_done'] = true;
        while (ob_get_level()) { ob_end_clean(); }
        echo 'STATUS:REDIRECT code=' . $code . ' to=' . $to . ' msg=' . mb_substr((string) $message, 0, 60) . "\n";
        exit(3);
    }
}
if (!function_exists('ems_gov_redirect')) {
    function ems_gov_redirect($location)
    {
        $GLOBALS['__ctl_done'] = true;
        while (ob_get_level()) { ob_end_clean(); }
        echo 'STATUS:REDIRECT to=' . mb_substr((string) $location, 0, 80) . "\n";
        exit(3);
    }
}

/* الجلسةُ عبرَ الرافدِ المشترك — `require_once` يدمجه مع نداءِ الشاشةِ نفسِها */
require_once $ROOT . '/includes/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['user'] = array('id' => $uid, 'role' => $role, 'company_id' => 4, 'name' => 'ctl-render-probe');

register_shutdown_function(function () {
    if (!empty($GLOBALS['__ctl_done'])) { return; }
    $html = '';
    while (ob_get_level()) { $html = ob_get_clean() . $html; }
    $len = strlen($html);
    if (getenv('CTL_PROBE_DEBUG')) {
        $le = error_get_last();
        fwrite(STDERR, 'DBG last_error=' . ($le ? $le['message'] . ' @' . $le['file'] . ':' . $le['line'] : '—') . "\n");
        if ($len > 0) { fwrite(STDERR, substr($html, 0, 300) . "\n"); }
    }
    /* صفحةٌ حقيقيّةٌ = جسمٌ لا سطرُ رسالة — والعتبةُ ٢٠٤٨: أصغرُ قشرةٍ تفوقها */
    if ($len >= 2048) { echo 'STATUS:OK len=' . $len . ' sha=' . substr(sha1($html), 0, 12) . "\n"; }
    else { echo "STATUS:EMPTY len=$len\n"; }
});
ob_start();
chdir(dirname($path));
include $path;
