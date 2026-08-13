<?php
/**
 * tests/shell_axes_measured_test.php — شاهدُ محاورِ الغلافِ الحاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0547 · INJ-0572
 *
 * **العيب**: الشاشاتُ تبذر محاورَ الغلافِ باستدعاء `ems_shell_axes(null)` — أي
 * بمحاورَ فارغةٍ لا مشتقّةٍ من محرّكِ الصلاحيات. والقياسُ الحيُّ أعطى **١٩٠
 * شاشةً** على هذا النحو، لا اثنتين: فمحورُ الصلاحيةِ «غيرُ مقيس» في عامّةِ
 * النظامِ، ولا يرى المستخدمُ «قراءةٌ فقط» ولا «تحرير» في أيٍّ منها.
 *
 * **الإصلاح** في العُدَّةِ لا في ١٩٠ شاشة: `ems_shell_axes()` تسأل محرّكَ
 * الصلاحياتِ بنفسِها حين لا تُمرَّر إليها صلاحيات — مرةً واحدةً لكلِّ طلب.
 * وشاشةٌ لا تُحَلُّ إلى موديولٍ تبقى «غيرُ مقيس» عمدًا: المحرّكُ يردُّ منعًا
 * كاملًا لغيرِ المسجَّل، وعرضُ «بلا صلاحية» على صفحةٍ صُيِّرت **كذبٌ مطمئنّ**.
 *
 * ── والمحورُ لا يُقبل إلا إن **ميَّز** ───────────────────────────────────────
 * محورٌ يقول «تحرير» للجميع ليس قياسًا. فيُشترط هنا: الشاشةُ نفسُها تعطي
 * `full` لدورٍ و`partial` لآخر، **وعددُ نماذجِ الكتابةِ المُصيَّرةِ يتبع ذلك**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0547 · INJ-0572 · محورُ الصلاحيةِ مقيسٌ ومميِّزٌ في الغلافِ الحاكم');

$jar = sys_get_temp_dir() . '/shellax_' . getmypid() . '.txt';
$http = function ($url, $fields = null, $follow = true) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($fields !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return array('body' => $b, 'code' => $c, 'loc' => $loc);
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role; $st->bind_param('si', $r, $CO); $st->execute();
    $x = $st->get_result()->fetch_row(); $st->close();
    return $x ? (string) $x[0] : '';
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r['body'], 'name="password"') === false;
};
/* قياسُ صفحةٍ واحدةٍ: المحورُ · نماذجُ الكتابةِ · سطرُ السياق */
$measure = function ($rel) use ($http, $BASE) {
    $r = $http($BASE . '/' . $rel);
    $h = $r['body'];
    if (mb_strpos($h, 'name="password"') !== false) { return null; }   /* صفحةُ دخولٍ لا شاشة */
    preg_match('~data-ems-ax-permission="([a-z]+)"~', $h, $m);
    preg_match('~صلاحيتك: <b>([^<]*)</b>~u', $h, $c);
    return array(
        'ax'    => isset($m[1]) ? $m[1] : '—',
        'ctx'   => isset($c[1]) ? $c[1] : '',
        'forms' => preg_match_all('~<form[^>]*method=["\']post~i', $h),
    );
};

/* ── ① الشاشاتُ السبعُ المولَّدةُ + شاشةُ النقلِ الميدانية ─────────────────── */
$SCREENS = array(
    'Operations/stops_unattributed.php', 'Operations/shift_log.php', 'Operations/daily_plan.php',
    'Operations/site_gate_equip.php', 'Operations/site_gate_person.php',
    'Operations/site_shift_plan.php', 'Operations/site_work_calendar.php',
    'Transport/transfer_in_transit.php',
);
$u1 = $userOfRole(1);
$ok($u1 !== '' && $login($u1), "دخل حسابُ الدورِ الأول ({$u1})");
$unmeasured = array(); $seen = 0; $axes = array();
foreach ($SCREENS as $rel) {
    $m = $measure($rel);
    if ($m === null) { continue; }
    $seen++;
    $axes[$rel] = $m;
    if ($m['ax'] === 'unmeasured' || $m['ax'] === '—') { $unmeasured[] = $rel . ' (' . $m['ax'] . ')'; }
}
$ok($seen === count($SCREENS), "صُيِّرت الشاشاتُ الثمانِ المعنيّةُ ({$seen}/" . count($SCREENS) . ')');
$ok(empty($unmeasured), '**ومحورُ الصلاحيةِ مقيسٌ في كلٍّ منها** — لا «غيرُ مقيس»',
    implode(' · ', $unmeasured));
$withCtx = 0;
foreach ($axes as $m) { if ($m['ctx'] !== '') { $withCtx++; } }
$ok($withCtx === $seen, "ويظهر للمستخدمِ في سطرِ السياقِ («صلاحيتك: …») في {$withCtx}/{$seen}");

/* ── ② والمحورُ **يميّز**: دورٌ كاملٌ ودورُ قراءةٍ على الشاشةِ نفسِها ───────── */
$probe = 'Operations/stops_unattributed.php';
$full = isset($axes[$probe]) ? $axes[$probe] : null;
$ok($full !== null && $full['ax'] === 'full',
    "الدورُ الأولُ يرى `{$probe}` بصلاحيةِ **تحرير**",
    'المحورُ ' . ($full ? $full['ax'] : '—'));

$u3 = $userOfRole(3);
$ro = null;
if ($u3 !== '' && $login($u3)) { $ro = $measure($probe); }
$ok($ro !== null && $ro['ax'] === 'partial',
    "ودورُ الأسطولِ ({$u3}) يراها بصلاحيةِ **قراءةٍ فقط**",
    'المحورُ ' . ($ro ? $ro['ax'] : '—'));
$ok($full && $ro && $ro['forms'] < $full['forms'],
    '**وعددُ نماذجِ الكتابةِ يتبع المحور**: ' . ($ro ? $ro['forms'] : '?')
    . ' لقارئٍ مقابلَ ' . ($full ? $full['forms'] : '?') . ' لمحرِّر',
    'العددُ نفسُه للطرفين — فالمحورُ زخرفةٌ لا حكم');

/* ── ③ والحجبُ الكاملُ يُعلن برمزٍ صريحٍ لا بصفحةٍ فارغة ───────────────────── */
$denyRole = 0; $denyUser = '';
foreach (array(28, 29, 30, 24, 16) as $rid) {
    $st = $conn->prepare('SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? AND rp.can_view = 1');
    $st->bind_param('si', $probe, $rid); $st->execute();
    $c = (int) $st->get_result()->fetch_row()[0]; $st->close();
    $uu = $userOfRole($rid);
    if ($c === 0 && $uu !== '') { $denyRole = $rid; $denyUser = $uu; break; }
}
$ok($denyRole > 0, "وُجد دورٌ بلا صلاحيةِ عرضٍ على الشاشة (دور {$denyRole} · {$denyUser})");
$denied = false; $why = '';
if ($denyRole > 0 && $login($denyUser)) {
    $r = $http($BASE . '/' . $probe, null, false);          /* بلا اتّباعٍ — وإلا صار الحجبُ 200 */
    if ($r['code'] === 403) { $denied = true; }
    elseif ($r['code'] >= 300 && $r['code'] < 400) {
        $abs = (strpos($r['loc'], 'http') === 0) ? $r['loc']
             : ($BASE . '/' . ltrim(str_replace('../', '', (string) $r['loc']), '/'));
        $land = $http($abs);
        $denied = (bool) preg_match('~GOV-PERM-403~', $land['body']);
        $why = 'الوجهة ' . $abs;
    } else { $why = 'الرمزُ ' . $r['code']; }
}
$ok($denied, '**والحجبُ يُعلن برمزِ `GOV-PERM-403` صراحةً** لا بصفحةٍ صامتة', $why);

@unlink($jar);
$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
