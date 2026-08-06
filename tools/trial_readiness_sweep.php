<?php
/**
 * trial_readiness_sweep — مسح جاهزية تجربة اليوم (ق-15).
 * ───────────────────────────────────────────────────────────────────────────
 * لكل حساب دورٍ حيٍّ في شركة الاختبار: دخولٌ فعلي ثم فتح لوحته وأول شاشاتِ
 * قائمته، ورصدُ: فشل الدخول · 500 · بصمات Fatal/Warning في الجسم · حلقات
 * إعادة التوجيه · الصفحة البيضاء. الناتج جدولُ جاهزيةٍ وقائمةُ كسورٍ للإصلاح.
 *
 * التشغيل: php tools/trial_readiness_sweep.php [--per-role=3] [--md]
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/trial_' . getmypid() . '.jar';
$PER_ROLE = 3;
foreach ($argv as $a) { if (preg_match('/--per-role=(\d+)/', $a, $m)) { $PER_ROLE = (int) $m[1]; } }
$MD = in_array('--md', $argv, true);

function tr_req($url, $post = null, $follow = true) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => !$follow, CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_TIMEOUT => 25,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $eurl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return array('code' => $code, 'body' => $body, 'url' => $eurl);
}
function tr_csrf($html) {
    return preg_match('/name="csrf_token"\s+value="([^"]+)"/u', $html, $m) ? $m[1] : '';
}
/** تشخيص جسم صفحة: '' سليم وإلا وصف العطب */
function tr_diagnose($r, $expectLogin = false) {
    $b = $r['body'];
    if ($r['code'] >= 500) { return 'HTTP ' . $r['code']; }
    if (trim($b) === '') { return 'صفحة بيضاء'; }
    if (stripos($b, 'Fatal error') !== false) { return 'Fatal PHP'; }
    if (stripos($b, 'Parse error') !== false) { return 'Parse PHP'; }
    if (stripos($b, 'Uncaught') !== false) { return 'استثناء غير ملتقط'; }
    if (!$expectLogin && mb_strpos($r['url'], 'login.php') !== false) { return 'ارتداد للدخول (جلسة/صلاحية)'; }
    return '';
}

/* الحسابات: أول N لكل دور حي في co4 — كلمة السر الموحدة للتجربة */
$accounts = array();
$q = $conn->query("SELECT id, username, role FROM users
                    WHERE company_id = 4 AND COALESCE(status,'active') = 'active'
                      AND role NOT IN ('-1')
                    ORDER BY CAST(role AS UNSIGNED), id");
$perRole = array();
while ($w = $q->fetch_assoc()) {
    $r = (string) $w['role'];
    $perRole[$r] = ($perRole[$r] ?? 0) + 1;
    if ($perRole[$r] <= $PER_ROLE) { $accounts[] = $w; }
}

fwrite(STDOUT, "═══ مسح جاهزية التجربة — " . count($accounts) . " حسابًا عبر " . count($perRole) . " دورًا ═══\n");
$rows = array(); $broken = array();

foreach ($accounts as $acc) {
    @unlink($JAR);
    $lg = tr_req($BASE . '/login.php');
    $in = tr_req($BASE . '/login.php', array(
        'username' => $acc['username'], 'password' => '12345678', 'csrf_token' => tr_csrf($lg['body'])));
    $dash = tr_req($BASE . '/dashboard.php');
    $loginOk = mb_strpos($dash['url'], 'login.php') === false;
    if (!$loginOk) {
        $rows[] = array($acc['role'], $acc['username'], '✘ الدخول نفسه', 'فشل الدخول (كلمة سر/حالة)');
        $broken[] = array('u' . $acc['id'] . ' ' . $acc['username'] . ' (دور ' . $acc['role'] . ')', 'login', 'فشل الدخول');
        continue;
    }
    $diag = tr_diagnose($dash);
    if ($diag !== '') {
        $rows[] = array($acc['role'], $acc['username'], 'dashboard', $diag);
        $broken[] = array('u' . $acc['id'] . ' ' . $acc['username'], 'dashboard.php', $diag);
    }

    // أول شاشات قائمته الفعلية (روابط الدور النشطة بترتيب الظهور)
    $nav = array();
    $rn = $conn->query("SELECT route FROM nav_items WHERE role_id = " . intval($acc['role']) . "
                         AND active = 1 AND route IS NOT NULL AND route <> ''
                         ORDER BY sort_order, id LIMIT 4");
    while ($x = $rn->fetch_assoc()) { $nav[] = $x['route']; }
    $okCount = 0; $navFail = '';
    foreach ($nav as $route) {
        $pg = tr_req($BASE . '/' . ltrim($route, '/'));
        $d = tr_diagnose($pg);
        if ($d === '') { $okCount++; }
        elseif ($navFail === '') { $navFail = $route . ' — ' . $d; $broken[] = array('u' . $acc['id'] . ' ' . $acc['username'] . ' (دور ' . $acc['role'] . ')', $route, $d); }
    }
    $rows[] = array($acc['role'], $acc['username'],
        $okCount . '/' . count($nav) . ' شاشات', $navFail !== '' ? $navFail : '✔');
}
@unlink($JAR);

/* ── التقرير ─────────────────────────────────────────────────────────────── */
$out = "# مسح جاهزية التجربة — " . date('Y-m-d H:i') . "\n\n";
$out .= "| الدور | الحساب | لوحته وقائمته | الحكم |\n|---|---|---|---|\n";
foreach ($rows as $r) {
    $out .= '| ' . implode(' | ', array_map(function ($c) { return str_replace('|', '·', (string) $c); }, $r)) . " |\n";
}
$out .= "\n## الكسور (" . count($broken) . ")\n";
if (!$broken) { $out .= "لا كسورَ — كل الحسابات تدخل وشاشاتها الأولى تُصيَّر سليمة.\n"; }
foreach ($broken as $b) { $out .= '- **' . $b[0] . '** على `' . $b[1] . '`: ' . $b[2] . "\n"; }

if ($MD) { file_put_contents(dirname(__DIR__) . '/docs/TRIAL_READINESS_ar.md', $out); fwrite(STDOUT, "كُتب: docs/TRIAL_READINESS_ar.md\n"); }
fwrite(STDOUT, $out);
fwrite(STDOUT, "═══ الكسور: " . count($broken) . " ═══\n");
exit(count($broken) > 0 ? 1 : 0);
