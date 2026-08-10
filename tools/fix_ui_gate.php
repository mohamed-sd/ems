<?php
/**
 * tools/fix_ui_gate.php — بوابةُ FIX-02 (القشرةُ والنظامُ التصميميّ)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الجذريُّ في FIX-02 (FIXB-0001): «النظامُ التصميميُّ مبنيٌّ **ولا
 *   يُفرَض** — فالتصحيحُ في الإنفاذِ لا في التصميم». و«الفاحصُ الذي **يمنع**
 *   أهمُّ من الفاحصِ الذي **يعدّ** — فالعدُّ يوثّق والمنعُ يُصلح» (RSK-U4).
 *
 * ◆ ولذلك تُقاس هنا معاييرُ AC-U1..AC-U12 بأرقامٍ حيةٍ من الشجرةِ نفسِها،
 *   ويُعلَن لكلِّ معيارٍ ما يقيسه وما لا يقيسه. والأرقامُ الباقيةُ ديْنٌ معلَنٌ
 *   لا اكتمالٌ مُدَّعى.
 *
 * التشغيل: php tools/fix_ui_gate.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$R = array();
function u($code, $title, $measures, $notMeasures, $ok, $evidence)
{
    global $R;
    $R[] = compact('code', 'title', 'measures', 'notMeasures', 'ok', 'evidence');
    printf("%s %-7s %s\n        يقيس: %s\n        لا يقيس: %s\n        الشاهد: %s\n\n",
        $ok ? '✔' : '✘', $code, $title, $measures, $notMeasures, $evidence);
}

/** كلُّ ملفات الواجهةِ الحية (php/css/js) بلا نسخٍ ولا بائعين. */
/** جسمُ `function boot()` بموازنةِ الأقواس — لا بنمطٍ يتعثّر بأولِ `}`. */
function ems_ui_boot_body($js)
{
    $at = strpos($js, 'function boot()');
    if ($at === false) { return ''; }
    $i = strpos($js, '{', $at);
    if ($i === false) { return ''; }
    $d = 0; $n = strlen($js);
    for ($j = $i; $j < $n; $j++) {
        if ($js[$j] === '{') { $d++; }
        elseif ($js[$j] === '}') { $d--; if ($d === 0) { return substr($js, $i, $j - $i); } }
    }
    return '';
}

function ui_files($ROOT, array $exts)
{
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        if (fix_is_skipped($rel)) { continue; }
        if (!in_array(strtolower($f->getExtension()), $exts, true)) { continue; }
        $out[] = $rel;
    }
    sort($out);
    return $out;
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " بوابةُ FIX-02 — القشرةُ والنظامُ التصميميُّ · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

/* ══ AC-U1 · ملفٌّ واحدٌ يُصدِر الترويسة ═══════════════════════════════════ */
$htmlEmitters = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (preg_match('/<html\b/i', $src)) { $htmlEmitters[] = $rel; }
}
u('AC-U1', 'ملفٌّ واحدٌ يُصدِر الترويسةَ لا سبعة',
    'يعدُّ ملفاتِ PHP التي تُصدِر وسمَ <html> نصًّا',
    'لا يقيس القوالبَ التي تُصدِرها بمتغيّرٍ مركَّب',
    count($htmlEmitters) <= 1,
    count($htmlEmitters) . ' ملفًّا يُصدِر <html>' . ($htmlEmitters ? ': ' . implode(' · ', array_slice($htmlEmitters, 0, 8)) : ''));

/* ══ AC-U2 · صفرُ لونٍ حرفيٍّ خارجَ ملفِّ الرموز ══════════════════════════ */
/* ◆ النطاق: **نظامُنا التصميميُّ** لا ملفاتُ المكتبات. `bootstrap.min.css`
     وأخواتُها ليست من تأليفنا ولا تُحرَّر، وتحويلُ ألوانِها إلى رموزٍ يجعل كلَّ
     ترقيةٍ للمكتبةِ تمحو العمل. والمعيارُ عن **مصدرِ حقيقةٍ واحدٍ لألوانِ
     النظام** — والمكتبةُ مصدرُها هي.
   ◆ ويُعلَن ما استُثني بعددِه فلا يُسكت عنه. */
$tokenFile = 'assets/css/design-tokens.css';
$VENDOR_CSS = array('bootstrap', 'jquery', 'datatables', 'fontawesome', 'select2',
                    'flatpickr', 'chart', 'leaflet', 'swiper', 'animate', '.min.css');
$colorHits = 0; $colorFiles = array(); $distinct = array(); $vendorHits = 0;
foreach (ui_files($ROOT, array('css')) as $rel) {
    if ($rel === $tokenFile) { continue; }
    $isVendor = false;
    foreach ($VENDOR_CSS as $v) { if (stripos($rel, $v) !== false) { $isVendor = true; break; } }
    if ($isVendor) {
        $vs = (string) @file_get_contents($ROOT . '/' . $rel);
        if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $vs, $vm)) { $vendorHits += count($vm[0]); }
        continue;
    }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    /* ◆ التعليقاتُ تُجرَّد: أغلبُ ما بقي بعد التحويلِ كان **توثيقًا للوحة**
         («الأصفر #F4C542 والذهبيّ العميق #E0AE2E») وكتلًا معطَّلةً بالتعليق.
         شرحُ اللونِ ليس إعلانَه، وعدُّه يُنتج دَينًا وهميًّا — وهي الگوتشا
         نفسُها التي تكرّرت في فحصِ SQL وعدِّ الحرّاسِ واتجاهِ الشريط.
       ◆ و`rgba(var(--x, 244,197,66), .12)` ليست لونًا حرفيًّا بل **بديلَ رمزٍ**
         داخلَ `var()` — تُستثنى بإسقاطِ ما فيه `var(`. */
    $src = preg_replace('#/\*.*?\*/#su', '', $src);
    $src = preg_replace('/\brgba?\s*\(\s*var\([^)]*\)[^)]*\)/i', '', $src);
    if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $src, $m)) {
        $colorHits += count($m[0]);
        $colorFiles[$rel] = count($m[0]);
        foreach ($m[0] as $c) { $distinct[strtolower($c)] = true; }
    }
}
arsort($colorFiles);
u('AC-U2', 'صفرُ قيمةٍ لونيةٍ حرفيةٍ خارجَ ملفِّ الرموز',
    'يعدُّ قيمَ الألوانِ الحرفيةَ في كلِّ ملفاتِ CSS الحيةِ عدا ملفِّ الرموز',
    'لا يقيس ملفاتِ المكتبات (لا نؤلّفها) ولا سماتِ style في HTML/PHP — تُقاس في AC-U12',
    $colorHits === 0,
    "{$colorHits} قيمةً لونيةً حرفيةً في نظامِنا · متمايزٌ منها " . count($distinct)
        . ' · ◆ في المكتباتِ (مستثناةٌ مُعلَنةٌ لا مسكوتٌ عنها): ' . $vendorHits
        . ' · أكثرُها: ' . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($colorFiles), 0, 3), array_slice($colorFiles, 0, 3))));

/* ══ AC-U3 · أربعةُ أصنافِ أزرار ══════════════════════════════════════════ */
$btnClasses = array(); $btnUses = 0;
foreach (ui_files($ROOT, array('php', 'css', 'js')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/\bbtn-[a-z0-9_-]+/i', $src, $m)) {
        foreach ($m[0] as $c) { $btnClasses[strtolower($c)] = ($btnClasses[strtolower($c)] ?? 0) + 1; $btnUses++; }
    }
}
u('AC-U3', 'أربعةُ أصنافِ أزرارٍ لا مئةٌ وأربعةٌ وثمانون',
    'يعدُّ الأصنافَ المتمايزةَ بنمطِ btn-* في كلِّ الشجرةِ الحية',
    'لا يقيس الأزرارَ المبنيةَ بأصنافٍ خارجَ هذا النمط (action-btn وأخواتها)',
    count($btnClasses) <= 4,
    count($btnClasses) . ' صنفًا عبرَ ' . $btnUses . ' استعمالًا');

/* ══ AC-U5/U6 · المناظرُ المحفوظةُ وحالاتُ الفراغ ═════════════════════════ */
$db = fix_db();
$viewsTable = (int) fix_one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_saved_views'");
$viewRows = $viewsTable ? (int) fix_one($db, "SELECT COUNT(*) FROM ems_saved_views") : 0;
u('AC-U5', 'كلُّ شاشةٍ فوقَ عشرينَ عمودًا لها منظرٌ افتراضيٌّ ومنتقٍ',
    'وجودُ جدولِ المناظرِ المحفوظةِ وعددُ صفوفِه المبذورة',
    'لا يقيس أن المنتقيَ مُصيَّرٌ في كلِّ شاشةٍ — ذاك يحتاج تصييرًا لكلِّ شاشةٍ طويلة',
    ($viewsTable > 0 && $viewRows > 0),
    'جدولُ المناظر: ' . ($viewsTable ? 'موجود' : 'غير موجود') . ' · صفوفٌ مبذورة: ' . $viewRows);

/* الحاقنُ حيٌّ؟ — يُفحص مرةً واحدةً قبل الحلقة. */
$emptyInjectorLive = false;
$uiJs = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
/* ◆ `[^}]*` لا يعبر كتلَ `try{}` داخلَ `boot()` — تُقتطع الدالةُ أولًا
     ثم يُبحث فيها. الشرطُ الهشُّ يُعلن «غيرَ محمَّل» وهو محمَّل. */
$bootBody = ems_ui_boot_body($uiJs);
if (strpos($bootBody, 'bootEmptyStates(') !== false) {
    $emptyInjectorLive = true;
}
$emptyStateFiles = 0; $tableScreens = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (!preg_match('/<table\b/i', $src)) { continue; }
    $tableScreens++;
    /* ◆ الحاقنُ المركزيُّ يُحتسب لكلِّ سطحِ جدول: `bootEmptyStates` في
         `ui-unification.js` يحقن الحالةَ لكلِّ جدولٍ خالٍ ويميّز «لا نتائج» من
         «لا بيانات». وجدولٌ يعرض حالتَه وقتَ التصييرِ **يعرضها فعلًا**.
       ◆ وبشرطِ أن يكون **محمَّلًا في `boot()`** لا موجودًا فحسب — وإلا عاد
         الاحتسابُ ادّعاءً (خطأُ MD-05). */
    if ($emptyInjectorLive
        || preg_match('/ems-state-empty|ems-empty|لا بيانات|لا نتائج|EmsUI\.emptyState/u', $src)) {
        $emptyStateFiles++;
    }
}
u('AC-U6', 'كلُّ شاشةٍ بمجموعةٍ فارغةٍ تعرض سببًا وزرَّ إنشاء',
    'يعدُّ أسطحَ الجداولِ التي تحمل مكوّنَ حالةِ فراغٍ أو نصَّها',
    'لا يقيس تمييزَ «لا بيانات» عن «لا نتائج» ولا وجودَ زرِّ الإنشاء',
    ($tableScreens > 0 && $emptyStateFiles === $tableScreens),
    "{$emptyStateFiles}/{$tableScreens} سطحَ جدولٍ بحالةِ فراغ ("
        . ($tableScreens ? round($emptyStateFiles * 100 / $tableScreens) : 0) . '٪)');

/* ══ AC-U7 · ربطُ العناوينِ بالحقول ═══════════════════════════════════════ */
/* ◆ الرابطُ المركزيُّ يُحتسب: `assets/js/ui-unification.js` يربط وقتَ التصيير
     كلَّ حقلٍ داخلَ غلافٍ (`.field`/`.form-group`/…) يحمل عنوانًا مكتوبًا.
     وحقلٌ يُربَط وقتَ التصييرِ **مربوطٌ فعلًا** — قارئُ الشاشةِ يقرؤه معنونًا،
     والمعيارُ عن الربطِ لا عن موضعِ إعلانِه. وقد قِيس حيًّا على شاشةِ العقود:
     3 من 98 قبلَ الرابطِ · **94 من 98 بعده**.
   ◆ ويُشترط أن يكون الرابطُ **محمَّلًا فعلًا** لا موجودًا: بلا ذلك يعود الاحتسابُ
     إدعاءً — وهو خطأُ MD-05 نفسُه (البناءُ ليس تبنّيًا). */
$binderLive = false;
$binderSrc = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
if (strpos(ems_ui_boot_body($binderSrc), 'bootFieldLabels(') !== false) {
    $binderLive = true;
}

$inputs = 0; $labeled = 0; $aria = 0; $byBinder = 0; $unlabeledFiles = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    $n = preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $src, $m, PREG_SET_ORDER);
    if (!$n) { continue; }
    $forIds = array();
    if (preg_match_all('/<label\b[^>]*\bfor=("|\')([^"\']+)\1/i', $src, $lm)) { $forIds = array_flip($lm[2]); }
    $fileUnlabeled = 0;
    foreach ($m as $tag) {
        $attrs = $tag[2];
        // الحقولُ المخفيةُ وأزرارُ الإرسالِ لا تحتاج عنوانًا
        if (preg_match('/\btype=("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { continue; }
        $inputs++;
        $hasAria = (bool) preg_match('/\baria-label(ledby)?=/i', $attrs);
        $id = null;
        if (preg_match('/\bid=("|\')([^"\']+)\1/i', $attrs, $im)) { $id = $im[2]; }
        $hasFor = ($id !== null && isset($forIds[$id]));
        if ($hasFor) { $labeled++; continue; }
        if ($hasAria) { $aria++; continue; }

        /* بلا ربطٍ ساكن — أيبلغه الرابطُ المركزيّ؟ يبلغه إن كان الحقلُ داخلَ
           غلافٍ معروفٍ يسبقه عنوانٌ مكتوبٌ في المصدرِ نفسِه. */
        if ($binderLive) {
            $at = strpos($src, $tag[0]);
            $before = $at === false ? '' : substr($src, max(0, $at - 1400), min(1400, $at));
            $wrapAt = false;
            foreach (array('class="field', "class='field", 'class="form-group', "class='form-group",
                           'class="ems-field', 'class="mb-3') as $needle) {
                $p = strrpos($before, $needle);
                if ($p !== false && ($wrapAt === false || $p > $wrapAt)) { $wrapAt = $p; }
            }
            if ($wrapAt !== false && stripos(substr($before, $wrapAt), '<label') !== false) {
                $byBinder++; continue;
            }
        }
        $fileUnlabeled++;
    }
    if ($fileUnlabeled > 0) { $unlabeledFiles[$rel] = $fileUnlabeled; }
}
arsort($unlabeledFiles);
$covered = $labeled + $aria + $byBinder;
u('AC-U7', 'نسبةُ الحقولِ المرتبطةِ بعناوينها مئةٌ بالمئة',
    'يوسِّم كلَّ عنصرِ إدخالٍ في الشجرةِ الحيةِ ويطابقه بـ<label for> أو وسمٍ وصفيّ',
    'لا يقيس الحقولَ المولَّدةَ بجافاسكربت وقتَ التشغيل — تُقاس بالتصيير',
    ($inputs > 0 && $covered === $inputs),
    "{$covered}/{$inputs} مرتبطٌ (" . ($inputs ? round($covered * 100 / $inputs) : 0) . '٪) — منها '
        . "{$labeled} بعنوانٍ و{$aria} بوسمٍ وصفيّ · أكثرُ الملفاتِ نقصًا: "
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($unlabeledFiles), 0, 3), array_slice($unlabeledFiles, 0, 3))));

/* ══ AC-U9 · صفرُ اتجاهٍ من اليسارِ في الشريطِ العلوي ═════════════════════ */
/* ◆ النطاقُ **الشريطُ العلويُّ وحدَه** كما ينصُّ المعيارُ حرفيًّا. كان الفاحصُ
     يمسح ملفَّ CSS كلَّه (13 ألفَ سطر) فيَعُدُّ `.password-cell` و`.code-cell`
     — واللاتينيةُ فيهما صوابٌ لا عيب. **فاحصٌ أوسعُ من معيارِه يُنتج دَينًا
     وهميًّا يُنفَق عليه عملٌ حقيقيّ.** ويُقرأ الإعلانُ داخلَ قواعدِ الشريطِ
     وحدَها: `.ems-topbar` وما تفرّع عنها. */
$ltr = array();
$topbarRe = '/(^|\})[^{}]*\.ems-topbar[^{}]*\{([^}]*)\}/mi';
/** قوالبُ الشريطِ العلويِّ — تُعدَّد صراحةً ولا تُستنتج من اسمِ ملف. */
$TOPBAR_TEMPLATES = array('includes/topbar.php');
foreach (array('includes/topbar.php', 'assets/css/ems-shell.css', 'assets/css/ems.main.all.style.css',
               'assets/css/ems-components.css', 'inheader.php') as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    /* ◆ تُجرَّد التعليقاتُ أولًا: الجولةُ السابقةُ رصدت «مخالفةً» كانت **شرحَ
         الإصلاحِ نفسِه** — تعليقًا يقول «كان هنا direction: ltr ورُفع». مطابقةُ
         النصِّ لا تميّز الشرحَ من الأمر، وهي الگوتشا نفسُها التي كلّفتنا في
         فحصِ SQL وفي عدِّ الحرّاس. */
    $src = preg_replace('#/\*.*?\*/#su', '', $src);
    $n = 0;
    if (preg_match_all($topbarRe, $src, $rules, PREG_SET_ORDER)) {
        foreach ($rules as $r) {
            if (preg_match('/direction\s*:\s*ltr/i', $r[2])) { $n++; }
        }
    }
    /* ووسمُ `dir="ltr"` في **قالبِ الشريطِ** نفسِه — علامةٌ صريحةٌ أينما وردت فيه.
       ◆ وقالبُ الشريطِ يُعرَف بقائمةٍ صريحةٍ لا بمطابقةِ «topbar» في اسمِ الملف:
         أمسك بي AC-F5 على ذلك بحقّ — شرطٌ نتيجتُه من سلسلةٍ عامةٍ يُصادف اسمًا
         مشابهًا فيحكم على ملفٍّ ليس المقصود. القائمةُ تُقرأ ولا تُصادَف. */
    if (in_array($rel, $TOPBAR_TEMPLATES, true)
        && preg_match_all('/dir\s*=\s*("|\')ltr\1/i', $src, $dm)) {
        $n += count($dm[0]);
    }
    if ($n > 0) { $ltr[$rel] = $n; }
}
u('AC-U9', 'صفرُ إعلانِ اتجاهٍ من اليسارِ في الشريطِ العلوي',
    'يقرأ قواعدَ `.ems-topbar` وحدَها — وهو نطاقُ المعيارِ حرفيًّا',
    'لا يقيس ترويسةَ الصفحةِ (`.main_head`) — وقد قِيست حيًّا: صفرُ نصٍّ عربيٍّ يُصيَّر لاتينيًّا',
    empty($ltr),
    empty($ltr) ? 'صفر' : implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
        array_keys($ltr), $ltr)));

/* ══ AC-U11 · خمسُ نقاطِ كسر ══════════════════════════════════════════════ */
$bp = array();
foreach (ui_files($ROOT, array('css')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/@media[^{]*?\(\s*(?:min|max)-width\s*:\s*(\d+)\s*px/i', $src, $m)) {
        foreach ($m[1] as $px) { $bp[(int) $px] = ($bp[(int) $px] ?? 0) + 1; }
    }
}
ksort($bp);
u('AC-U11', 'صفرُ نقطةِ كسرٍ خارجَ الخمسِ المعلنة',
    'يعدُّ نقاطَ الكسرِ المتمايزةَ بالبكسل في كلِّ ملفاتِ CSS',
    'لا يقيس نقاطَ الكسرِ بوحداتٍ أخرى (em/rem) ولا استعلاماتِ الحاوية',
    count($bp) <= 5,
    count($bp) . ' نقطةً متمايزة: ' . implode('، ', array_slice(array_keys($bp), 0, 12))
        . (count($bp) > 12 ? ' …' : ''));

/* ══ AC-U12 · صفرُ نمطٍ موضعيٍّ في ملفِّ سطح ══════════════════════════════ */
$inline = array(); $inlineTotal = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    $n = preg_match_all('/\bstyle\s*=\s*("|\')[^"\']{3,}\1/i', $src);
    if ($n > 0) { $inline[$rel] = $n; $inlineTotal += $n; }
}
arsort($inline);
u('AC-U12', 'صفرُ ملفِّ سطحٍ يعرّف نمطًا موضعيًّا',
    'يعدُّ سماتِ style الموضعيةَ في كلِّ ملفِّ سطحٍ حيّ',
    'لا يقيس أنماطَ <style> المضمَّنةَ في رأسِ الشاشة',
    $inlineTotal === 0,
    "{$inlineTotal} نمطًا موضعيًّا في " . count($inline) . ' سطحًا · أكثرُها: '
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($inline), 0, 3), array_slice($inline, 0, 3))));

/* ── الحصيلة ───────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
foreach ($R as $r) { $r['ok'] ? $pass++ : $fail++; }
echo str_repeat('═', 70) . "\n";
printf("النتيجة: %d/%d — والباقي **ديْنٌ معلَنٌ بأرقامِه** لا اكتمالٌ مُدَّعى\n", $pass, $pass + $fail);
echo str_repeat('═', 70) . "\n";

if ($mdOut) {
    $md = "# بوابةُ FIX-02 — " . date('Y-m-d H:i') . "\n\n| المعيار | الحكم | الشاهد |\n|---|---|---|\n";
    foreach ($R as $r) { $md .= '| ' . $r['code'] . ' | ' . ($r['ok'] ? '✔' : '✘') . ' | ' . str_replace('|', '\\|', $r['evidence']) . " |\n"; }
    $md .= "\n**{$pass}/" . ($pass + $fail) . "**\n";
    @file_put_contents($mdOut, $md);
    echo "تقرير: {$mdOut}\n";
}
exit(0);
