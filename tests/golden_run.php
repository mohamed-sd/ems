<?php
/**
 * T1 — عدّة الجولة الذهبية المعمَّمة (K9 · خطة docs/K9_MIGRATION_PLAN_ar.md §2)
 * ───────────────────────────────────────────────────────────────────────────
 * توحيدٌ للعدّة المجرَّبة في المرحلة 0 وK2/K5: دخول فعلي بمستخدمٍ حقيقي عبر
 * تبديل hash مؤقت مضمون الاسترجاع (shutdown handler) ثم قياس صفحات.
 *
 * التشغيل:
 *   php tests/golden_run.php --user=13 --pages=Opportunities/opportunities.php[,...]
 * الخرج (سطر لكل صفحة — قابل للمقارنة بـ diff):
 *   <page>|HTTP=<code>|len=<bytes>|fatal=<Y/n>|kicked=<Y/n>
 * رمز الخروج: 0 = دخول ناجح وكل الصفحات بلا fatal · 1 = خلل عدّة/دخول.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';

$opts = getopt('', array('user:', 'pages:', 'base::'));
$UID = isset($opts['user']) ? intval($opts['user']) : 0;
$PAGES = isset($opts['pages']) ? array_filter(array_map('trim', explode(',', $opts['pages']))) : array();
$BASE = isset($opts['base']) ? rtrim($opts['base'], '/') . '/' : 'http://localhost/ems/';
if ($UID <= 0 || empty($PAGES)) { fwrite(STDERR, "usage: --user=<id> --pages=<p1,p2,...>\n"); exit(1); }
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) EMS-GoldenRun';

$db = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$u = $db->query("SELECT username, password FROM users WHERE id={$UID}")->fetch_assoc();
if (!$u || strlen($u['password']) < 50) { fwrite(STDERR, "FATAL: user {$UID} not found/bad hash\n"); exit(1); }
$USERNAME = $u['username'];
$origHash = $u['password'];

$restored = false;
$restore = function () use ($db, $origHash, $UID, &$restored) {
    if ($restored) return;
    $s = $db->prepare("UPDATE users SET password=? WHERE id={$UID}"); $s->bind_param('s', $origHash); $s->execute();
    $restored = ($db->query("SELECT password FROM users WHERE id={$UID}")->fetch_row()[0] === $origHash);
    echo "RESTORE hash(u{$UID}): " . ($restored ? "OK" : "!!FAILED!!") . "\n";
};
register_shutdown_function($restore);
$temp = bin2hex(random_bytes(12));
$h = password_hash($temp, PASSWORD_BCRYPT);
$s = $db->prepare("UPDATE users SET password=? WHERE id={$UID}"); $s->bind_param('s', $h); $s->execute();

$jar = tempnam(sys_get_temp_dir(), 'gold');
function greq($url, $post = null) {
    global $jar, $UA;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_USERAGENT=>$UA, CURLOPT_TIMEOUT=>40]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $i = curl_getinfo($ch); curl_close($ch);
    return [$i['http_code'], $b === false ? '' : $b, $i['url']];
}
list($c, $b) = greq($BASE . 'login.php');
if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $b, $m)) { fwrite(STDERR, "FATAL: no csrf\n"); exit(1); }
list($c, $b, $f) = greq($BASE . 'login.php', ['username'=>$USERNAME, 'password'=>$temp, 'csrf_token'=>$m[1]]);
$auth = (strpos($f, 'login.php') === false);
echo "LOGIN(u{$UID}): HTTP {$c} → " . ($auth ? "AUTH" : "NOT-AUTH") . "\n";
if (!$auth) { $restore(); exit(1); }

$bad = 0;
foreach ($PAGES as $p) {
    list($c, $b, $f) = greq($BASE . ltrim($p, '/'));
    $fatal = (stripos($b, 'Fatal error') !== false || stripos($b, 'Parse error') !== false);
    $kicked = (strpos($f, 'login.php') !== false);
    if ($fatal) $bad++;
    printf("%s|HTTP=%d|len=%d|fatal=%s|kicked=%s\n", $p, $c, strlen($b), $fatal?'Y':'n', $kicked?'Y':'n');
}
$restore();
exit($bad === 0 ? 0 : 1);
