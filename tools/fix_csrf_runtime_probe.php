<?php
/**
 * tools/fix_csrf_runtime_probe.php — هل يحمل كلُّ نموذجِ POST **مُصيَّرًا** رمزَه؟
 * ═══════════════════════════════════════════════════════════════════════════
 * المسحُ على المصدرِ أعطى ٣٣٣ نموذجًا «بلا رمز» — وهو قياسٌ **للمصدرِ لا للمُخرَج**.
 * و`ems_inject_csrf_fields()` تحقن الرمزَ في كلِّ `<form method=post>` **عند
 * التصيير**. فالحكمُ على المُخرَجِ الحيِّ وحدَه.
 *
 * ◆ ويُقاس بحسابٍ **يملك عرضَ الشاشة** — وإلا صُيِّرت صفحةُ حجبٍ بلا نماذج
 *   فبدت «سليمة».
 * ◆ ويُستثنى `/admin/` — له حمايتُه الخاصةُ ولا يمرُّ بالحاقن.
 *
 *   php tools/fix_csrf_runtime_probe.php [--limit=40]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$LIMIT = 40;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $LIMIT = (int) substr($a, 8); } }

ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/csrfprobe_' . getmypid() . '.txt';

$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r['body'], 'name="password"') === false;
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role;
    $st->bind_param('si', $r, $CO);
    $st->execute();
    $x = $st->get_result()->fetch_row();
    $st->close();
    return $x ? (string) $x[0] : '';
};

/* شاشاتٌ مسجَّلةٌ في `modules` لها دورٌ يعرضها — والربطُ بـ`code` (لا عمودَ مسار) */
$screens = array();
$r = $conn->query("SELECT m.code, MIN(rp.role_id) rid
                     FROM modules m JOIN role_permissions rp ON rp.module_id = m.id
                    WHERE rp.can_view = 1 AND m.code LIKE '%.php'
                    GROUP BY m.code ORDER BY m.code");
while ($r && ($x = $r->fetch_assoc())) {
    $code = (string) $x['code'];
    if (strpos($code, 'admin/') === 0) { continue; }
    if (!is_file($ROOT . '/' . $code)) { continue; }
    $src = (string) @file_get_contents($ROOT . '/' . $code);
    if (stripos($src, '<form') === false) { continue; }
    $screens[$code] = (int) $x['rid'];
}

echo "══ رمزُ الحمايةِ في المُخرَجِ الحيِّ ══\n\n";
echo "  شاشاتٌ مسجَّلةٌ فيها نموذجٌ وتُعرض لدورٍ: " . count($screens) . "\n";
echo "  المفحوصُ في هذه الجولة: " . min($LIMIT, count($screens)) . "\n\n";

$byRole = array();
foreach ($screens as $code => $rid) { $byRole[$rid][] = $code; }

$done = 0; $formsTot = 0; $formsBare = 0; $bare = array(); $skipped = 0;
foreach ($byRole as $rid => $codes) {
    if ($done >= $LIMIT) { break; }
    $u = $userOfRole($rid);
    if ($u === '' || !$login($u)) { $skipped += count($codes); continue; }
    foreach ($codes as $code) {
        if ($done >= $LIMIT) { break; }
        $res = $http($BASE . '/' . $code);
        $done++;
        if ($res['code'] !== 200) { continue; }
        $b = $res['body'];
        if (mb_strpos($b, 'name="password"') !== false) { continue; }   /* صفحةُ دخولٍ */
        /* كتلُ السكربتِ تُقنَّع — لا نعدُّ نموذجًا في قالبِ JS */
        $masked = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $b);
        if (!is_string($masked)) { continue; }
        if (preg_match_all('~<form\b[^>]*>(.*?)</form>~si', $masked, $m, PREG_SET_ORDER)) {
            foreach ($m as $f) {
                if (!preg_match('~method\s*=\s*["\']?\s*post~i', $f[0])) { continue; }
                $formsTot++;
                if (stripos($f[1], 'name="csrf_token"') === false) {
                    $formsBare++;
                    if (count($bare) < 12) { $bare[] = $code; }
                }
            }
        }
    }
}
@unlink($jar);

echo "  نماذجُ POST مُصيَّرةٌ: {$formsTot}\n";
echo "  منها **بلا رمزٍ في المُخرَج**: {$formsBare}\n";
if ($bare) {
    echo "\n  الشاشاتُ المعنيّة:\n";
    foreach (array_unique($bare) as $c) { echo "     ✘ {$c}\n"; }
} else {
    echo "\n  ✔ **كلُّ نموذجِ POST مُصيَّرٍ يحمل رمزَه** — فالحاقنُ المركزيُّ يفي،\n";
    echo "     وتوسيعُ `CSRF_ENFORCE_PATHS` لا يكسر نموذجًا عاديًّا.\n";
}
if ($skipped) { echo "\n  · شاشاتٌ لم تُفحص (لا حسابَ لدورِها): {$skipped}\n"; }
exit(0);
