<?php
/**
 * tests/nav_landing_anchor_test.php — شاهدُ مراسي وصولِ روابطِ القوائم
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0459
 *
 * **العيب**: روابطُ في السايدبار تحمل مِرساةً في آخرِها
 * (`Finance/approvals_inbox.php#n9g32i3`) تُميّز عنصرَ قائمةٍ عن آخرَ يقصد
 * الشاشةَ نفسَها من مرحلةٍ أخرى — و**لا وجودَ لتلك المِراسِ في وجهاتها**.
 * فالمتصفحُ يبتلع الجزءَ صامتًا: لا انتقالَ ولا تنبيه. والسجلُّ ذكر أربعةً في
 * دور ٣؛ والقياسُ الحيُّ أعطى ٧٨ صفًّا عبرَ الأدوار.
 *
 * **الإصلاح** في العُدَّةِ لا في الشاشات: `emsNavLandingAnchors()` في
 * `includes/unified_nav.php` تبثُّ المِرساةَ في كلِّ شاشةٍ يقصدها عنصرٌ يحملها.
 *
 * ── ولا يُقبل قياسٌ فارغ ────────────────────────────────────────────────────
 * فاحصٌ يمرُّ لأنَّ **لا رابطَ بمِرساةٍ** أصلًا يصادق على نفسِه. فيُشترط هنا
 * صراحةً أن يكون في القائمةِ رابطٌ بمِرساةٍ واحدٌ على الأقل، وإلا رسب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/unified_nav.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0459 · مِرساةُ الرابطِ موجودةٌ في وجهتِه');

$jar = sys_get_temp_dir() . '/navanchor_' . getmypid() . '.txt';
$http = function ($url) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : ''))));
    $b = (string) curl_exec($ch); curl_close($ch);
    return mb_strpos($b, 'name="password"') === false;
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users
                           WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role; $st->bind_param('si', $r, $CO); $st->execute();
    $x = $st->get_result()->fetch_row(); $st->close();
    return $x ? (string) $x[0] : '';
};

/* ── ① الأدوارُ التي في قوائمها روابطُ مِرساة ─────────────────────────────── */
$targets = array();          /* role => array(path => array(frag,...)) */
$totalAnchored = 0;
$rr = $conn->query('SELECT DISTINCT role_id FROM nav_items ORDER BY role_id');
$roles = array();
while ($rr && ($x = $rr->fetch_row())) { $roles[] = (int) $x[0]; }
foreach ($roles as $rid) {
    $items = getUnifiedNavItems($conn, $rid);
    foreach ($items as $it) {
        $route = (string) $it['route'];
        if (strpos($route, '#') === false) { continue; }
        $p = explode('#', $route, 2);
        $frag = trim($p[1]);
        $path = ltrim(preg_replace('~^(\.\./)+~', '', $p[0]), '/');
        if ($frag === '' || !is_file($ROOT . '/' . $path)) { continue; }
        $targets[$rid][$path][$frag] = true;
        $totalAnchored++;
    }
}
$ok($totalAnchored > 0, "في القوائمِ روابطُ مِرساةٍ يُقاس عليها ({$totalAnchored})",
    'صفرٌ — فالفاحصُ يمرُّ بلا مفحوص');
if ($totalAnchored === 0) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

/* ── ② القياسُ الحيُّ: أوّلُ ثلاثةِ أدوارٍ تحمل مراسيَ — بحسابِ الدورِ نفسِه ─── */
$checked = 0; $missing = array(); $rolesDone = 0;
foreach ($targets as $rid => $paths) {
    if ($rolesDone >= 3) { break; }
    $u = $userOfRole($rid);
    if ($u === '' || !$login($u)) { continue; }
    $rolesDone++;
    foreach ($paths as $path => $frags) {
        $res = $http($BASE . '/' . $path);
        if ($res['code'] !== 200) { continue; }
        foreach (array_keys($frags) as $frag) {
            $checked++;
            if (strpos($res['body'], 'id="' . $frag . '"') === false) {
                $missing[] = $path . '#' . $frag . ' (دور ' . $rid . ')';
            }
        }
    }
}
@unlink($jar);
$ok($rolesDone > 0, "دخلت حساباتُ {$rolesDone} أدوارٍ تحمل قوائمُها مراسيَ");
$ok($checked > 0, "قيست {$checked} مِرساةً على مُخرَجِ HTTP الحيِّ", 'لا شيءَ قيس');
$ok(empty($missing),
    '**كلُّ مِرساةٍ لها عنصرٌ بمعرِّفِها في وجهتِها** (' . ($checked - count($missing)) . '/' . $checked . ')',
    'غائبةٌ: ' . implode(' · ', array_slice($missing, 0, 4)));

/* ── ③ والمِرساةُ لا تُزيح المحتوى: الوعاءُ بلا ارتفاع ────────────────────── */
$navSrc = (string) file_get_contents($ROOT . '/includes/unified_nav.php');
$ok(strpos($navSrc, 'height:0;overflow:hidden') !== false,
    'ووعاءُ المراسي بلا ارتفاعٍ — فلا يُزيح تخطيطَ الشاشة');
$ok(strpos($navSrc, 'scroll-margin-top') !== false,
    'ولها هامشُ تمريرٍ — فالقفزُ لا يختفي تحت الشريطِ العلوي');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
