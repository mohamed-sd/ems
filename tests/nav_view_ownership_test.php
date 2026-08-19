<?php
/**
 * tests/nav_view_ownership_test.php — لا قارئَ لـ`?view=` بلا ملكيةٍ مُعلَنة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطبُ الذي يحرسه — وقد **وقع فعلًا**:
 *   `includes/nav_view.php` مُخنِقٌ عامٌّ يُطبَّق على كلِّ شاشةٍ تُدرج
 *   `page_header.php`. قاعدتُه: رمزُ `?view=` غيرُ المُعلَنِ في السجلِّ المركزي
 *   **يُردُّ إلى الرابطِ العاري** (302). وبُنيت هذه القاعدةُ على مقدّمةٍ مكتوبةٍ
 *   في ترويسةِ `nav_views.php`: «صفرٌ من ١٥ ملفًّا يقرأ `$_GET['view']`».
 *
 *   والمقدّمةُ صادقةٌ على مقامِها (١٥ شاشةً ولّدها المولِّد) وكاذبةٌ على النظام:
 *   ثلاثةُ أصحابٍ يقرؤون المعاملَ بأنفسِهم. فكانت **تاباتُ «مهامي» العشرُ
 *   تُصيَّر وعدّاداتُها صحيحةٌ ولا تفتح واحدةٌ منها**.
 *
 * ◆ فهذا الفاحصُ يقيس المقدّمةَ بدل أن يأتمنَها: يمسح المستودعَ بالموسِّم
 *   (`token_get_all` — لا مطابقةَ نصٍّ تخلط التعليقَ بالشيفرة) ويرسب إن قرأ
 *   ملفٌّ `$_GET['view']` وهو **لا مُعلَنٌ في السجل ولا مُعلِنٌ ملكيتَه**.
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يفتح الشاشةَ فلا يشهد أن التابَ
 *   يعرض الصفَّ الصحيح؛ ذاك شأنُ `tests/my_tasks_views_test.php` الحيّ.
 *
 *   php tests/nav_view_ownership_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/nav_views.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; echo "  \xE2\x9C\x94 {$m}\n"; }
function bad($m) { global $FAIL; $FAIL++; echo "  \xE2\x9C\x98 {$m}\n"; }

/* المستثنياتُ من المسح: نسخٌ وأدواتٌ ليست أسطحًا حيّة */
$SKIP = array('/.git/', '/.claude/', '/vendor/', '/storage/', '/node_modules/', '/docs/', '/tests/');

/* ── من يقرأ المعاملَ فعلًا؟ ─────────────────────────────────────────────── */
function reads_get_view($src)
{
    $t = token_get_all($src);
    $n = count($t);
    for ($i = 0; $i < $n - 2; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_VARIABLE || $t[$i][1] !== '$_GET') { continue; }
        for ($j = $i + 1; $j < min($i + 4, $n); $j++) {
            if (is_array($t[$j]) && $t[$j][0] === T_CONSTANT_ENCAPSED_STRING
                && trim($t[$j][1], "'\"") === 'view') { return true; }
        }
    }
    return false;
}
/** أيُعلن الملفُّ ملكيتَه (أو تُعلَن نيابةً عنه في مُدرَجٍ يسبق الرأس)؟ */
function claims_ownership($src)
{
    return strpos($src, 'ems_nav_view_claim(') !== false;
}

echo "\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90 ملكيةُ \x60?view=\x60 \xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n";

$reg = ems_nav_views_registry();
$declared = array_map('strtolower', array_keys($reg));

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$readers = array(); $claimers = array(); $scanned = 0;
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { continue 2; } }
    $scanned++;
    $src = @file_get_contents($p);
    if ($src === false) { continue; }
    $rel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $p), '/');
    if (claims_ownership($src)) { $claimers[$rel] = true; }
    if (reads_get_view($src))   { $readers[$rel]  = true; }
}
echo "  ملفاتٌ مفحوصة: {$scanned} · قارئون: " . count($readers) . " · مُعلِنون: " . count($claimers) . "\n\n";

/* ① الآليةُ نفسُها قائمة — فلا يمرُّ الفاحصُ بصمتٍ لو حُذفت */
echo "\xE2\x94\x80\xE2\x94\x80 \xE2\x91\xA0 الآليةُ قائمة\n";
function_exists('ems_nav_view_claim') && function_exists('ems_nav_view_claimed')
    ? ok('ems_nav_view_claim / ems_nav_view_claimed مُعرَّفتان في nav_views.php')
    : bad('آليةُ الملكيةِ مفقودة — المُخنِقُ بلا مَخرجٍ لصاحبِ المعامل');

$nvSrc = (string) @file_get_contents($ROOT . '/includes/nav_view.php');
strpos($nvSrc, 'ems_nav_view_claimed()') !== false
    ? ok('nav_view.php يستشير الملكيةَ قبلَ أن يردَّ الزائر')
    : bad('nav_view.php لا يستشير الملكية — كلُّ صاحبِ معاملٍ يُداس ثانية');

/* ② كلُّ قارئٍ إمّا مُعلَنٌ في السجلِّ أو مُعلِنٌ ملكيتَه ────────────────────── */
echo "\n\xE2\x94\x80\xE2\x94\x80 \xE2\x91\xA1 كلُّ قارئٍ مالكٌ أو مُعلَن\n";
$orphans = array();
foreach (array_keys($readers) as $rel) {
    $lc = strtolower($rel);
    /* العُدّةُ المركزيةُ نفسُها (nav_view/page_header) هي المُخنِق لا سطحًا */
    if ($lc === 'includes/nav_view.php' || $lc === 'includes/page_header.php') { continue; }
    if (in_array($lc, $declared, true)) { continue; }          /* مُعلَنٌ في السجل */
    if (isset($claimers[$rel])) { continue; }                  /* أعلن ملكيتَه */
    $orphans[] = $rel;
}
if (!$orphans) {
    ok('صفرُ قارئٍ بلا ملكيةٍ ولا إعلان (' . count($readers) . ' قارئًا مفحوصًا)');
} else {
    bad(count($orphans) . ' قارئًا يُداس بالمُخنِق: ' . implode(' · ', $orphans));
}

/* ③ الأصحابُ الثلاثةُ المقيسون يُعلنون فعلًا — شاهدٌ باسمِه لا بالعدد ─────── */
echo "\n\xE2\x94\x80\xE2\x94\x80 \xE2\x91\xA2 الأصحابُ المقيسون يُعلنون\n";
foreach (array(
    'Portal/my_tasks.php'          => 'تاباتُ «مهامي» العشر',
    'Risk/_risk_views.php'         => 'مناظرُ شاشاتِ المخاطرِ الأربع',
    'includes/u13_screen_kit.php'  => 'أخواتُ الشاشةِ المضيفةِ في عُدّةِ U13',
) as $file => $why) {
    $src = (string) @file_get_contents($ROOT . '/' . $file);
    $src !== '' && claims_ownership($src)
        ? ok("{$file} — {$why}")
        : bad("{$file} لا يُعلن ملكيتَه — {$why} تُداس");
}

/* ④ القاعدةُ ① ما تزال نافذةً على من لا يملك — لا يُبطلها العلاج ─────────── */
echo "\n\xE2\x94\x80\xE2\x94\x80 \xE2\x91\xA3 الرمزُ الميتُ ما يزال يُردّ\n";
preg_match('~Location:~', $nvSrc) && strpos($nvSrc, "unset(\$q['view'])") !== false
    ? ok('ردُّ الرمزِ غيرِ المُعلَنِ إلى الرابطِ العاري باقٍ (القاعدة ① لم تُنقض)')
    : bad('ردُّ الرمزِ الميتِ اختفى — العلاجُ نقض القاعدةَ بدل أن يحدَّها');

echo "\n\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n";
echo "  اجتاز {$PASS} \xC2\xB7 أخفق {$FAIL}\n";
exit($FAIL === 0 ? 0 : 1);
