<?php
/**
 * REV-08 · البند 3 — برهان الصمّام Fail-Closed للشاشات غير المسجَّلة (CLI فقط)
 * ───────────────────────────────────────────────────────────────────────────
 * يُثبت — عبر خادم الويب الحيّ ومُنفِّذ عرض الصفحة الحقيقي، لا محاكاة — ثلاثة أمور:
 *   (أ) مراقبة: شاشةٌ غير مسجَّلةٍ تحت بادئةٍ مُهاجَرة مراقَبة  → 200 + سطر
 *       UNREGISTERED_SCREEN_WOULD_DENY (شفافةٌ لكن مرصودة — النواة القديمة سليمة).
 *   (ب) إنفاذ: شاشةٌ غير مسجَّلةٍ تحت بادئةٍ في قائمة الإنفاذ → 403 + سطر
 *       UNREGISTERED_SCREEN_DENY (الصمّام يحجب فعلًا).
 *   (ج) الافتراض الآمن: القائمة نفسها فارغةً (افتراض الإنتاج) → 200 (صفر تغيير سلوك).
 *
 * لا يُنفَّذ على أيّ بادئةٍ لها شاشةٌ حقيقية: الإنفاذ يُجرَّب على بادئةٍ رميّة /zzfc/
 * وحدها. ذاتيُّ التنظيف (shutdown): يُعيد .env كما كان بالضبط، ويحذف الشاشتين
 * الرميّتين. يُرفَق مخرَجُه في ردّ REV-08.
 *
 * التشغيل:  php tests/proof_failclosed.php --user=7
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';

$opts = getopt('', array('user:', 'base::'));
$UID  = isset($opts['user']) ? intval($opts['user']) : 7;
$BASE = isset($opts['base']) ? rtrim($opts['base'], '/') . '/' : 'http://localhost/ems/';
$ROOT = dirname(__DIR__);
$ENV  = $ROOT . '/.env';
$LOG  = $ROOT . '/logs/security.log';
$UA   = 'Mozilla/5.0 EMS-FailClosedProof';

$db = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

// ── ملفات رميّة + لقطة .env للاسترجاع المضمون ──
$MON_PROBE = $ROOT . '/Finance/zz_fc_monitor_probe.php';   // تحت بادئةٍ مراقَبة
$ENF_DIR   = $ROOT . '/zzfc';
$ENF_PROBE = $ENF_DIR . '/zz_fc_enforce_probe.php';         // تحت بادئةٍ رميّة تُنفَّذ
$PROBE_SRC = "<?php require_once dirname(__DIR__).'/insidebar.php'; echo 'PROBE-REACHED-BODY';\n";
$envOrig   = file_get_contents($ENV);

$cleaned = false;
$cleanup = function () use (&$cleaned, $ENV, $envOrig, $MON_PROBE, $ENF_PROBE, $ENF_DIR) {
    if ($cleaned) return; $cleaned = true;
    file_put_contents($ENV, $envOrig);                     // استرجاع .env حرفيًّا
    @unlink($MON_PROBE); @unlink($ENF_PROBE); @rmdir($ENF_DIR);
    $envOk = (file_get_contents($ENV) === $envOrig);
    $filesOk = !file_exists($MON_PROBE) && !file_exists($ENF_PROBE) && !is_dir($ENF_DIR);
    echo "\nتنظيف: .env مُستعاد " . ($envOk ? "✓" : "✗ !!") . " · الملفات الرميّة محذوفة " . ($filesOk ? "✓" : "✗ !!") . "\n";
};
register_shutdown_function($cleanup);

// كتابة الشاشتين الرميّتين
file_put_contents($MON_PROBE, $PROBE_SRC);
if (!is_dir($ENF_DIR)) { mkdir($ENF_DIR); }
file_put_contents($ENF_PROBE, $PROBE_SRC);

// أداة تعديل بادئة الإنفاذ في .env (الخادم يقرأ .env طازجًا كلَّ طلب)
$setEnforce = function ($val) use ($ENV) {
    $t = file_get_contents($ENV);
    $t = preg_replace('/^EMS_FAILCLOSED_ENFORCE_PREFIXES=.*$/m', 'EMS_FAILCLOSED_ENFORCE_PREFIXES=' . $val, $t);
    file_put_contents($ENV, $t);
};

// ── دخولٌ فعليّ بمستخدمٍ حقيقي (نمط golden_run: hash مؤقت مضمون الاسترجاع) ──
$u = $db->query("SELECT username, password FROM users WHERE id={$UID}")->fetch_assoc();
if (!$u || strlen($u['password']) < 50) { fwrite(STDERR, "FATAL: user {$UID} bad\n"); exit(1); }
$USERNAME = $u['username']; $origHash = $u['password'];
$hashRestored = false;
$restoreHash = function () use ($db, $origHash, $UID, &$hashRestored) {
    if ($hashRestored) return;
    $s = $db->prepare("UPDATE users SET password=? WHERE id={$UID}"); $s->bind_param('s', $origHash); $s->execute();
    $hashRestored = ($db->query("SELECT password FROM users WHERE id={$UID}")->fetch_row()[0] === $origHash);
    echo "استرجاع hash(u{$UID}): " . ($hashRestored ? "✓" : "✗ !!") . "\n";
};
register_shutdown_function($restoreHash);
$temp = bin2hex(random_bytes(12));
$h = password_hash($temp, PASSWORD_BCRYPT);
$s = $db->prepare("UPDATE users SET password=? WHERE id={$UID}"); $s->bind_param('s', $h); $s->execute();

$jar = tempnam(sys_get_temp_dir(), 'fcp');
// FOLLOWLOCATION=true ضروريّ لتثبيت جلسةٍ متماسكة عبر جرّة الكوكيز (session-cookie
// round-trip)؛ الـ403 لا يُتبَع (بلا Location) فيُلتقَط كما هو — والـ3xx وحده يُتبَع.
$req = function ($path, $post = null) use ($jar, $UA, $BASE) {
    $ch = curl_init($BASE . ltrim($path, '/'));
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_USERAGENT=>$UA, CURLOPT_TIMEOUT=>40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $i = curl_getinfo($ch); curl_close($ch);
    return array($i['http_code'], $b === false ? '' : $b, $i['url']);
};

list($c, $b) = $req('login.php');
if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $b, $m)) { fwrite(STDERR, "FATAL: no csrf\n"); exit(1); }
list($c, $b, $f) = $req('login.php', array('username'=>$USERNAME, 'password'=>$temp, 'csrf_token'=>$m[1]));
$auth = (strpos($f, 'login.php') === false);
// تأكيد تماسك الجلسة على صفحةٍ عادية قبل قياس المسابر (وإلا فحصُ المسبار بلا معنى)
list($cd, $bd) = $req('main/dashboard.php');
$carries = ($cd == 200 && stripos($bd, 'logout') !== false);
echo "دخول (u{$UID} «{$USERNAME}»): " . ($auth ? "AUTH ✓" : "NOT-AUTH ✗")
   . " · الجلسة متماسكة: " . ($carries ? "✓" : "✗") . "\n\n";
if (!$auth || !$carries) { exit(1); }

$logSize0 = file_exists($LOG) ? filesize($LOG) : 0;

// ── (أ) مراقبة: بادئةٌ مُهاجَرة مراقَبة، إنفاذٌ فارغ → 200 شفّاف + WOULD_DENY ──
$setEnforce('');
list($cm) = $req('Finance/zz_fc_monitor_probe.php');
echo "(أ) مراقبة  Finance/zz_fc_monitor_probe.php → HTTP {$cm}  (المتوقع 200، شفّافٌ مرصود)\n";

// ── (ب) إنفاذ: بادئةٌ رميّة /zzfc/ في قائمة الإنفاذ → 403 + DENY ──
$setEnforce('/zzfc/');
list($ce, $be) = $req('zzfc/zz_fc_enforce_probe.php');
$isJson = (strpos($be, 'unregistered_screen') !== false);
echo "(ب) إنفاذ   zzfc/zz_fc_enforce_probe.php  → HTTP {$ce}  (المتوقع 403)"
   . ($isJson ? " · جسمٌ JSON unregistered_screen ✓" : "") . "\n";

// ── (ج) الافتراض الآمن: القائمة فارغة → 200 (صفر تغيير سلوك) ──
$setEnforce('');
list($cs) = $req('zzfc/zz_fc_enforce_probe.php');
echo "(ج) فارغ    zzfc/zz_fc_enforce_probe.php  → HTTP {$cs}  (المتوقع 200، الافتراض الإنتاجيّ آمن)\n";

// ── سطور السجل الجديدة ──
clearstatcache();
$newLog = '';
if (file_exists($LOG) && filesize($LOG) > $logSize0) {
    $fh = fopen($LOG, 'rb'); fseek($fh, $logSize0); $newLog = stream_get_contents($fh); fclose($fh);
}
$wouldDeny = (strpos($newLog, 'UNREGISTERED_SCREEN_WOULD_DENY') !== false);
$deny      = (strpos($newLog, 'UNREGISTERED_SCREEN_DENY') !== false);
echo "\n── سطور logs/security.log الجديدة ──\n";
foreach (array_filter(explode("\n", $newLog)) as $ln) {
    if (strpos($ln, 'UNREGISTERED_SCREEN') !== false) echo "  " . trim($ln) . "\n";
}

echo "\n";
echo "  (أ) مراقبة 200 + WOULD_DENY مسجَّل   " . (($cm == 200 && $wouldDeny) ? "✓" : "✗") . "\n";
echo "  (ب) إنفاذ 403 + DENY مسجَّل + JSON    " . (($ce == 403 && $deny && $isJson) ? "✓" : "✗") . "\n";
echo "  (ج) فارغ 200 (صفر تغيير سلوك)         " . ($cs == 200 ? "✓" : "✗") . "\n";

$pass = ($cm == 200 && $wouldDeny) && ($ce == 403 && $deny && $isJson) && ($cs == 200);
echo "\n" . ($pass
    ? "PASS: الصمّام يحجب غير المسجَّل تحت الإنفاذ (403)، يرصد تحت المراقبة (شفّاف)، وآمنٌ فارغًا (200)\n"
    : "FAIL: monitor={$cm}/{$wouldDeny} enforce={$ce}/{$deny}/{$isJson} empty={$cs}\n");

exit($pass ? 0 : 1);
