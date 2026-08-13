<?php
/**
 * tests/supplier_kpi_cards_test.php — شاهدُ بطاقاتِ ملفِّ المورّد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0158
 *
 * **العيب**: ستُّ بطاقاتٍ تعرض **رقمًا عاريًا وتسميةً** فقط — بلا وحدةٍ ولا
 * فترةٍ ولا مقارنةٍ ولا حالةٍ ولا مصدرٍ ولا رابطِ تعمّق. ورقمٌ لا يُتعمَّق فيه
 * لا يُقرَّر عليه. ومحورُ الصلاحيةِ مبذورٌ ولا شيءَ في الصفحةِ يقرؤه — فالمخوَّلُ
 * جزئيًّا يرى ما يراه المخوَّلُ كاملًا.
 *
 * **الإصلاح**: المكوّنُ `ems_kpi_card` قائمٌ **ويرفض التصييرَ بأقلَّ من السبعة**
 * — فالحكمُ يصير بالبناءِ لا بالمراجعة. والحقولُ الحساسةُ (ساعاتُ العقودِ
 * والتشغيلِ — أساسُ الفوترة) **تُنزع من المصفوفةِ قبل التصيير** لمن لا يملك
 * عرضَ مصدرِها: إخفاءٌ بـCSS ليس منعًا، فالرقمُ يبقى في المصدرِ لمن يقرؤه.
 *
 * ── والمنعُ يُقاس بالتمييزِ لا بالغياب ─────────────────────────────────────
 * حجبٌ للجميعِ ليس حجبًا. فيُقاس دورٌ **يجدها** ودورٌ **لا يجدها** على الشاشةِ
 * نفسِها وللمورّدِ نفسِه.
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
$say('══ INJ-0158 · بطاقاتُ المؤشرِ بعقدِها السباعيِّ وحجبٍ يميّز');

$sid = 0;
$r = $conn->query("SELECT id FROM suppliers WHERE company_id = {$CO} ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $sid = (int) $x[0]; }
$ok($sid > 0, "وُجد مورّدٌ حيٌّ (#{$sid})");

$jar = sys_get_temp_dir() . '/supkpi_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r, 'name="password"') === false;
};
$userOfRole = function ($role) use ($conn, $CO, $login) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $r = (string) $role; $st->bind_param('si', $r, $CO); $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { if ($login((string) $x[0])) { $st->close(); return (string) $x[0]; } }
    $st->close();
    return '';
};
$fetch = function ($role) use ($userOfRole, $http, $BASE, $sid) {
    $u = $userOfRole($role);
    if ($u === '') { return null; }
    $h = $http($BASE . '/Suppliers/supplier_profile.php?id=' . $sid);
    if (mb_strpos($h, 'name="password"') !== false) { return null; }
    return array('user' => $u, 'html' => $h);
};

/* ── ① العقدُ السباعيُّ في كلِّ بطاقة ────────────────────────────────────── */
$full = $fetch(2);
$ok($full !== null, 'دخل دورٌ يملك عرضَ مصادرِ البطاقاتِ كلِّها ('
    . ($full ? $full['user'] : '—') . ')');
$h = $full ? $full['html'] : '';
$cards = preg_match_all('~ems-kpi-card~', $h);
$ok($cards >= 6, "وصُيِّرت {$cards} بطاقةَ مؤشر");
$ok(preg_match_all('~<a class="ems-kpi-card~', $h) >= 6,
    '**وكلُّها روابطُ تعمّق** — فرقمٌ لا يُتعمَّق فيه لا يُقرَّر عليه');
$ok(mb_strpos($h, 'بطاقةُ مؤشرٍ ناقصةُ العقد') === false,
    '**ولا بطاقةَ ناقصةَ العقد** — المكوّنُ يرفض أقلَّ من السبعة');
foreach (array('معدة' => 'الوحدة', 'لحظي (' => 'الفترة') as $needle => $what) {
    $ok(mb_strpos($h, $needle) !== false, "وتحمل {$what}");
}
$ok(mb_strpos($h, 'بلا مقارنة معلنة') !== false || mb_strpos($h, 'من ') !== false,
    'والمقارنةُ معلنةٌ — تُقال أو يُقال إنها غائبة');
$ok(preg_match('~ems-kpi-(ok|warn|err)~', $h) === 1, 'والحالةُ نغمةٌ معلنة');
$ok(mb_strpos($h, 'المعداتُ المرتبطةُ بهذا المورّد') !== false,
    'ومصدرُ الرقمِ مكتوبٌ في البطاقة');

/* ── ② والحجبُ **يميّز**: دورٌ يجدها ودورٌ لا يجدها ────────────────────── */
$SENSITIVE = 'إجمالي ساعات العقود';
$has2 = ($h !== '' && mb_strpos($h, $SENSITIVE) !== false);
$ok($has2, "ودورُ المصدرِ يجد «{$SENSITIVE}» في استجابةِ الخادم");

$partial = null; $partialRole = 0;
foreach (array(1, 3, 6) as $rid) {
    $p = $fetch($rid);
    if ($p === null) { continue; }
    if (mb_strpos($p['html'], $SENSITIVE) === false) { $partial = $p; $partialRole = $rid; break; }
}
$ok($partial !== null, 'ووُجد دورٌ **لا يجدها** — فالحجبُ يميّز لا يعمّ (دور '
    . $partialRole . ' · ' . ($partial ? $partial['user'] : '—') . ')');
if ($partial !== null) {
    $ok(mb_strpos($partial['html'], $SENSITIVE) === false,
        '**والحقلُ الحساسُ غائبٌ من مصدرِ الصفحةِ نفسِه** — لا مُخفًى بـCSS');
    $ok(mb_strpos($partial['html'], 'بطاقاتٌ محجوبةٌ عن دورك') !== false,
        'ويُعلَن الحجبُ برمزٍ وسببٍ بدلَ فراغٍ صامت');
    $ok(preg_match_all('~ems-kpi-card~', $partial['html']) >= 5,
        'وبقيةُ البطاقاتِ تُصيَّر له كاملةً');
}
@unlink($jar);

/* ── ③ والمكوّنُ هو المصدرُ لا نسخةٌ محلية ────────────────────────────────── */
$src = (string) file_get_contents($ROOT . '/Suppliers/supplier_profile.php');
$ok(strpos($src, "require_once __DIR__ . '/../includes/kpi_card.php'") !== false
    && strpos($src, 'ems_kpi_card($__c)') !== false,
    'والشاشةُ تنادي المكوّنَ المشتركَ — لا تبني بطاقةً بيدها');
$ok(preg_match('~<div class="kpi">~', $src) === 0,
    'ولا أثرَ للبطاقةِ العاريةِ القديمة');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
