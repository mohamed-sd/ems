<?php
/**
 * tests/nav_view_mechanism_test.php — المنظرُ المُعلَنُ وجهةٌ لا معاملٌ ميت
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0512 · INJ-0514
 *
 * ── لماذا هذا الفاحصُ موجودٌ أصلًا ────────────────────────────────────────
 * أوّلُ علاجٍ للبندين حوّل مِرساةً ميتةً (`#n9g10i1`) إلى `?view=` — وظنَّ أنَّ
 * المعاملَ وجهة. والقياسُ بعدَه قال: **صفرٌ من ١٥ ملفًّا يقرأ `$_GET['view']`**،
 * ونزعُ المِرساةِ جعل الصفَّينِ متطابقَينِ أمام حارسِ التكرارِ **فابتُلع ٢٥
 * رابطًا**: صفُّه حيٌّ في القاعدةِ ولا يراه المستخدمُ في القائمة.
 *
 * فهذا الفاحصُ يحرس الحدَّين معًا:
 *   ① **لا رمزَ في القائمةِ إلا مُعلَنًا** — وغيرُ المُعلَنِ يُردُّ إلى الرابطِ
 *      العاري بـ302، فلا صفحةَ بمعاملٍ ميتٍ تُوهم بمحتوًى لا يأتي.
 *   ② **والمُعلَنُ يُصيَّر ويظهر**: رابطاه في القائمةِ معًا، وعنوانُ الصفحةِ
 *      تسميةُ الرابطِ الذي ضُغط، وما أُعلن (قسمٌ أو عمود) **موجودٌ فعلًا في
 *      المُصيَّر** — فالمُطبِّقُ يجد ما يُطبّق عليه.
 *
 * ◆ والقياسُ **حيٌّ بـHTTP** لا بقراءةِ مصدرٍ: عيبُ المعاملِ الميتِ لا يظهر في
 *   المصدرِ إطلاقًا — يظهر حين تُفتح الصفحةُ وتجد المحتوى نفسَه.
 * ◆ و`CURLOPT_FOLLOWLOCATION` **مطفأٌ** عند قياسِ الردّ: متابعةُ التحويلِ
 *   تُحيل 302 إلى 200 فيبدو المعاملُ الميتُ مقبولًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/nav_views.php';

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ المنظرُ المُعلَنُ وجهةٌ لا معاملٌ ميت');

/* ── ① السجلُّ مبنيٌّ وكلُّ إعلانٍ يُسمّي شيئًا ────────────────────────────── */
$reg = ems_nav_views_registry();
$ok(count($reg) >= 3, 'سجلُّ المناظرِ مبنيٌّ (' . count($reg) . ' ملفًّا)');
$declared = 0; $unreal = array();
foreach ($reg as $file => $codes) {
    $src = (string) @file_get_contents($ROOT . '/' . $file);
    foreach ($codes as $code => $d) {
        $declared++;
        if ($src === '') { $unreal[] = $file . ' (غيرُ موجود)'; continue; }
        /* الإعلانُ يُسمّي قسمًا في الملفِّ أو عمودًا فيه — لا شيئًا متخيَّلًا */
        $needle = isset($d['section']) ? $d['section'] : (isset($d['col']) ? $d['col'] : '');
        if ($needle === '' || mb_strpos($src, $needle) === false) {
            $unreal[] = $file . '?' . $code . ' ⟵ «' . $needle . '»';
        }
    }
}
$ok(empty($unreal), "**وكلُّ إعلانٍ يُسمّي شيئًا موجودًا في ملفِّه** ({$declared} رمزًا)",
    implode(' · ', array_slice($unreal, 0, 4)));

/* ── ② لا رمزَ في القائمةِ غيرُ مُعلَن ───────────────────────────────────── */
$navCodes = 0; $undeclared = array();
$r = $conn->query("SELECT role_id, label_ar, route FROM nav_items WHERE route LIKE '%view=%'");
while ($r && ($x = $r->fetch_assoc())) {
    if (!preg_match('~[?&]view=([^&#]+)~', (string) $x['route'], $m)) { continue; }
    $navCodes++;
    $f = preg_replace('~[?#].*$~', '', (string) $x['route']);
    if (ems_nav_view_declared($f, urldecode($m[1])) === null) {
        $undeclared[] = 'دور ' . $x['role_id'] . ' ' . $x['route'];
    }
}
$ok($navCodes > 0, "وفي القائمةِ {$navCodes} رابطَ منظرٍ تُقاس");
$ok(empty($undeclared), '**ولا رمزَ في القائمةِ غيرُ مُعلَن** — فلا وعدَ بلا وفاء',
    implode(' · ', array_slice($undeclared, 0, 4)));

/* ── ③ الحارسُ يعدُّ المُعلَنَ مميِّزًا والمُدَّعى لا ─────────────────────────── */
require_once $ROOT . '/includes/unified_nav.php';
$one = null; $oneFile = null;
foreach ($reg as $f => $codes) { foreach ($codes as $c => $d) { $one = $c; $oneFile = $f; break 2; } }
$ok(ems_nav_route_has_view($oneFile . '?view=' . $one) === true,
    'ورمزٌ مُعلَنٌ يُعَدُّ مميِّزًا في الحارس');
$ok(ems_nav_route_has_view($oneFile . '?view=zzz_not_declared') === false,
    '**ورمزٌ مُدَّعًى لا يُميّز** — فلا يُهرَّب به صفٌّ مكرَّرٌ من الحارس');
$ok(ems_nav_norm_route($oneFile . '?view=' . $one, false) !== ems_nav_norm_route($oneFile, false),
    'وهويةُ الرابطِ تفرّق بين العاري والمنظرِ المُعلَن');
$ok(ems_nav_norm_route($oneFile . '?view=' . $one, true) === ems_nav_norm_route($oneFile, true),
    'وهويةُ **الملفِّ** تجمعهما — فالقياسُ يعرف أنهما شاشةٌ واحدة');

/* ── ④ القياسُ الحيُّ بـHTTP ──────────────────────────────────────────────── */
$jar = sys_get_temp_dir() . '/navview_' . getmypid() . '.txt';
$get = function ($url, $follow = true, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 60));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $b = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $code, 'raw' => $b);
};
/* الدخولُ بحسابٍ للدورِ الذي يملك المنظرَ المقيس */
$uName = '';
$st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = 4
                       AND username <> '' ORDER BY id LIMIT 1");
$roleForView = '17';                       /* الدورُ ١٧ يملك منظرَ الفاتورةِ الضريبية */
$st->bind_param('s', $roleForView);
$st->execute();
$x = $st->get_result()->fetch_row();
$st->close();
if ($x) { $uName = (string) $x[0]; }
$ok($uName !== '', "حسابُ الدورِ {$roleForView} موجودٌ للقياسِ الحيِّ ({$uName})");

$b = $get('http://localhost/ems/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['raw'], $t);
$get('http://localhost/ems/login.php', true, http_build_query(array(
    'username' => $uName, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));

$U = 'http://localhost/ems/Contracts/tax_invoices.php';
$bare = $get($U);
$live = (mb_strpos($bare['raw'], 'name="password"') === false);
$ok($live, 'والجلسةُ حيّةٌ — الشاشةُ تُفتح لا تُردُّ للدخول');

if ($live) {
    /* ⓐ رمزٌ **غيرُ مُعلَنٍ** ⇒ 302 إلى الرابطِ العاري — والمتابعةُ مطفأة */
    $bad = $get($U . '?view=zzz_not_declared', false);
    $loc = '';
    if (preg_match('~^Location:\s*(.+)$~mi', $bad['raw'], $lm)) { $loc = trim($lm[1]); }
    $ok($bad['code'] === 302, '**ورمزٌ غيرُ مُعلَنٍ يُردُّ بـ302** لا يُعرض (' . $bad['code'] . ')');
    $ok(strpos($loc, 'tax_invoices.php') !== false && strpos($loc, 'view=') === false,
        'وإلى الرابطِ العاري بلا معامل (' . ($loc !== '' ? $loc : '—') . ')');

    /* ⓑ رمزٌ **مُعلَنٌ** ⇒ يُعرض بشارتِه وعنوانِه */
    $good = $get($U . '?view=v72b04c', false);
    $ok($good['code'] === 200, 'ورمزٌ مُعلَنٌ يُعرض بـ200 (' . $good['code'] . ')');
    $ok(mb_strpos($good['raw'], 'ems-view-chip') !== false,
        '**وشارةُ المنظرِ ظاهرةٌ** — يعرف المستخدمُ أيَّ منظرٍ هو فيه');
    $ok(mb_strpos($good['raw'], 'عرض الكل') !== false,
        'وفيها «عرض الكل» — فالمنظرُ يُفكُّ بضغطةٍ لا يُحبَس فيه');
    $shown = '';
    if (preg_match('~<h1 class="head-title">(.*?)</h1>~su', $good['raw'], $hm)) {
        $shown = trim(preg_replace('~\s+~u', ' ', strip_tags($hm[1])));
    }
    $ok(mb_strpos($shown, 'الفاتورة الضريبية') !== false,
        '**وعنوانُ الصفحةِ تسميةُ الرابطِ المضغوط** — «' . ($shown !== '' ? $shown : '—') . '»');
    $ok(mb_strpos($good['raw'], 'فاتورة ضريبية') !== false,
        'والقسمُ المُعلَنُ **موجودٌ في المُصيَّرِ** — فللمُطبِّقِ ما يُطبّق عليه');

    /* ⓒ والرابطُ العاري يبقى بعنوانِه هو — فالوجهتان تختلفان في المرأى */
    $shownBare = '';
    if (preg_match('~<h1 class="head-title">(.*?)</h1>~su', $bare['raw'], $hb)) {
        $shownBare = trim(preg_replace('~\s+~u', ' ', strip_tags($hb[1])));
    }
    $ok($shownBare !== '' && $shownBare !== $shown,
        '**والوجهتان تختلفان فعلًا**: «' . $shownBare . '» ≠ «' . $shown . '»');
    $ok(mb_strpos($bare['raw'], 'ems-view-chip') === false,
        'ولا شارةَ على الرابطِ العاري — فالشارةُ للمنظرِ وحدَه');
}
@unlink($jar);

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
