<?php
/**
 * tests/contracts_tables_test.php — شاهدُ جداولِ وحدةِ العقود
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0146
 *
 * **العيب**: ثمانيةَ عشرَ جدولًا في وحدةِ العقودِ ضمن «الجداولِ الساكنةِ تمامًا»
 * — بلا بحثٍ ولا فرزٍ ولا ترقيم. والقبول: **كلُّ** جدولٍ في الوحدةِ فيه بحثٌ
 * وفرزٌ وترقيمٌ **وترويسةٌ ثابتةٌ عند التمرير**.
 *
 * ── ثلاثةٌ من العُدَّةِ وواحدٌ لم يكن له قاعدةٌ في المستودعِ كلِّه ──────────────
 * البحثُ والفرزُ والترقيمُ توفّرها التهيئةُ المركزيةُ (بعد أن صار الخروجُ
 * `no-datatable` استشاريًّا). أمَّا **الترويسةُ الثابتةُ** فلم تكن لها قاعدةٌ:
 * `position: sticky` الوحيدةُ في المستودعِ كانت على شريطٍ علويٍّ لا على ترويسةِ
 * جدول.
 *
 * ── وقاعدةٌ أُضيفت فلم تعمل — والسببُ ليس التخصيصَ بل قاعدةً تملك الخاصية ────
 * أوّلُ قاعدةٍ أُضيفت `position: sticky` فبقي المُقاسُ `relative`. والسببُ قاعدةٌ
 * في `ems.main.all.style.css` تضع `position: relative !important` على `th`
 * القابلِ للفرزِ (لأنَّ أيقونتَه مطلقةُ الموضع) — وهي أخصُّ وبـ`!important`.
 * فالإصلاحُ **في القاعدةِ المالكةِ** لا بطبقةِ تجاوزٍ فوقها: بُدِّلت إلى `sticky`
 * — وهي قيمةٌ موضَّعةٌ أيضًا فتبقى الأيقونةُ على حالها.
 *   مقيسٌ في متصفحٍ حقيقيّ: **٢٧ من ٢٧** ترويسةً `sticky` عند `top: 78px`
 *   (مُشتقًّا من ارتفاعِ الشريطِ الحقيقيِّ لا رقمًا مكتوبًا)، وأيقونةُ الفرزِ
 *   ما زالت تُصيَّر (`::after = "▼"`)، والبحثُ والترقيمُ والفرزُ حاضرةٌ كلُّها.
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
$say('══ INJ-0146 · جداولُ العقود: بحثٌ وفرزٌ وترقيمٌ وترويسةٌ ثابتة');

/* ── ① كلُّ جدولٍ في الوحدةِ مؤهَّلٌ للتهيئةِ المركزية ─────────────────────── */
$files = glob($ROOT . '/Contracts/*.php');
$withThead = 0; $hardOut = array(); $noHead = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    if (stripos($src, '<thead') === false) { $noHead++; continue; }
    $withThead++;
    if (strpos($src, 'data-no-dt="hard"') !== false || strpos($src, 'ems-no-enhance') !== false) {
        $hardOut[] = basename($f);
    }
}
$ok($withThead >= 18, "جداولُ الوحدةِ بترويسة: {$withThead} ملفًّا (السجلُّ ذكر ١٨)");
$ok(empty($hardOut), '**ولا خروجَ صلبًا يمنع التهيئةَ المركزية** (' . count($hardOut) . ')',
    implode(' · ', $hardOut));

$kit = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');
$ok(preg_match('~\|\|\s*!!table\.dataset\.noDt\)\s*\r?\n?\s*&&\s*pageHasManualDT\(\)\)\s*return;~', $kit) === 1,
    'والخروجُ الاستشاريُّ قائمٌ — فالجداولُ الساكنةُ تُهيَّأ');

/* ── ② الترويسةُ الثابتةُ: القاعدةُ موجودةٌ **ولا يهزمها `!important`** ──────── */
$tbl = (string) file_get_contents($ROOT . '/assets/css/ems-tables.css');
$ok(preg_match('~position:\s*sticky\s*!important~', $tbl) === 1,
    'وقاعدةُ الترويسةِ الثابتةِ مكتوبةٌ في قشرةِ الجداول');
$ok(strpos($tbl, 'var(--ems-sticky-top') !== false,
    'وإزاحتُها من رمزٍ لا من رقمٍ مكتوب');
$ok(strpos($kit, "setProperty('--ems-sticky-top'") !== false
    && strpos($kit, 'bootStickyOffset();') !== false,
    '**والعُدَّةُ تضبط الرمزَ من ارتفاعِ الشريطِ الحقيقيِّ** وتناديه في الإقلاع');

/* القاعدةُ المالكةُ لموضعِ `th` لم تبقَ `relative` — وإلا هزمت الثبات */
$main = (string) file_get_contents($ROOT . '/assets/css/ems.main.all.style.css');
$owner = '';
if (preg_match('~body\.ems-site \.main table\.dataTable thead > tr > th\.sorting_desc \{(.{0,400})~su', $main, $m)) {
    $owner = $m[1];
}
$ok($owner !== '', 'ووُجدت القاعدةُ المالكةُ لموضعِ ترويسةِ الفرز');
$ok($owner !== '' && preg_match('~position:\s*sticky\s*!important~', $owner) === 1,
    '**وهي `sticky` لا `relative`** — فالإصلاحُ في مالكِ الخاصيةِ لا بطبقةٍ فوقه',
    'ما زالت: ' . trim(mb_substr($owner, 0, 60)));
$ok($owner !== '' && preg_match('~position:\s*relative\s*!important~', $owner) === 0,
    'ولا أثرَ لـ`relative !important` فيها');
/* والطباعةُ تُستثنى: الثباتُ فيها يكرّر الترويسةَ في غيرِ موضعها */
$ok(preg_match('~@media print.{0,220}position:\s*static~su', $tbl) === 1,
    'والطباعةُ مُستثناةٌ من الثبات');

/* ── ③ ومُخرَجُ HTTP: الوحدةُ تُصيَّر بترويساتها وتُحمّل العُدَّة ─────────────── */
$jar = sys_get_temp_dir() . '/contbl_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $x ? $x[0] : '', 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
/* ◆ شاشاتُ **قائمةٍ** لا شاشاتُ تفصيل: `penalties.php` و`contract_lines.php`
     تحتاج `?contract=` فلا تُصيّر جدولًا بلا مُعامَل — وإعلانُ «بلا ترويسة»
     عنها قياسٌ لرابطٍ خاطئٍ لا حكمٌ على الشاشة. */
$sample = array('Contracts/contracts.php', 'Contracts/claims.php', 'Contracts/price_terms.php');
$seen = 0; $withKit = 0; $withThead2 = 0; $withCss = 0;
foreach ($sample as $rel) {
    $h = $http($BASE . '/' . $rel);
    if (mb_strpos($h, 'name="password"') !== false) { continue; }
    $seen++;
    if (strpos($h, 'ui-unification.js') !== false) { $withKit++; }
    if (preg_match('~<thead\b~i', $h)) { $withThead2++; }
    if (strpos($h, 'ems-tables.css') !== false) { $withCss++; }
}
@unlink($jar);
$ok($seen >= 2, "وصُيِّرت {$seen} شاشاتٍ من الوحدة");
$ok($withThead2 === $seen, 'وكلُّها تُصيّر ترويسةَ جدول');
$ok($withKit === $seen && $withCss === $seen,
    '**وكلُّها تُحمّل العُدَّةَ وقشرةَ الجداولِ** — فالبحثُ والفرزُ والثباتُ تصلها',
    "عُدَّة={$withKit} · قشرة={$withCss} من {$seen}");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
