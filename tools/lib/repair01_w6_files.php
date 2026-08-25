<?php
/**
 * tools/lib/repair01_w6_files.php
 *   ملفُّ الشاشةِ مصدرَ نصٍّ — REPAIR01 · W06 · الجولةُ الثانيةُ مصحَّحةُ النطاق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هذا الملفُّ أصلًا**: جولةُ W06 الأولى (‏2026-08-25) خرجت خضراءَ
 *   ١٨/١٨ وهي تفحص **سبعةَ جداولَ فقط**. و٨٧٢ ملفَّ شاشةٍ خارجَ مقامِها فيها
 *   **٤٧٧٩٤ علامةَ تشكيلٍ في نصٍّ غيرِ تعليقيّ**. و«لا اكتمالَ ببوّابةٍ تفحص
 *   ما اخترتَ فحصَه» (‏_CONTEXT §قواعد القياس ٤).
 *
 * ◆ **والفصلُ بالرمزنةِ لا بالتخمين** (‏W06 §٤-٥): `token_get_all` تعزل
 *   `T_COMMENT` و`T_DOC_COMMENT` — فعربيّةُ التوثيقِ المشكولةُ (‏١٠٠٠١٠
 *   علامة) **ليست واجهةً ولا تُحاسَب**. ويبقى `T_INLINE_HTML` والسلاسلُ
 *   النصّيةُ في المقام.
 *
 * ◆ **والتعليقُ تعليقٌ بأيِّ لغةٍ كُتب**: `T_INLINE_HTML` يحمل داخلَه
 *   `<script>` و`<style>` وفيهما تعليقاتُ جافاسكربت وCSS وتعليقُ HTML نفسُه.
 *   واحتسابُها «نصَّ واجهة» بينما تُعزَل نظيرتُها في PHP **مقامٌ مزدوجُ
 *   المعيار** — فتُعزَل بالمعيارِ نفسِه ويُعلَن عددُها.
 *
 * ◆ ⛔ **وأخطرُ ما في هذه الجولة: نصٌّ يُقارَن لا يُعرَض.**
 *   `if ($state === 'مشتقّة')` و`case 'مخصّص'` و`$map['مشغّل']` و
 *   `WHERE status = 'منتهٍ'` — **قيمُ بياناتٍ في ثوبِ نصّ** (‏البند ١٩).
 *   ونزعُ تشكيلِها في الشيفرةِ وحدَها **يفكُّ المقارنةَ بصمتٍ**: الصفُّ في
 *   القاعدةِ مشكولٌ والمقارنُ في الشيفرةِ منزوعٌ فلا يلتقيان — وهو عطبٌ لا
 *   يظهر في شاشةٍ ولا في `php -l`، بل في حكمٍ يصمت. وهو نفسُه عطبُ «عدّادٍ
 *   وعارضٍ في ملفَّين يتفرّقان» الذي قِيس في هذا المستودعِ قبلًا.
 *   **فالمعجمُ المقترنُ يُجمَع أوّلًا من الشجرةِ كلِّها، ثمَّ يُعذَر كلُّ نصٍّ
 *   يساويه حرفًا — ولا يُمَسّ طرفٌ دون طرف.** ويُعلَن بعددِه لا يُدَّعى صفرًا.
 *
 * ◆ **والرمزنةُ تُعيد بناءَ الملفِّ حرفًا**: مجموعُ نصوصِ الرموزِ يساوي الملفَّ
 *   الأصليَّ بايتًا بايتًا، فالتحريرُ يقع على **مدًى محسوبٍ** لا على بحثٍ
 *   واستبدالٍ في الخام. و`php -l` يشهد بعد كلِّ كتابة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once dirname(dirname(__DIR__)) . '/app/Services/Ui/UiPurity.php';

use App\Services\Ui\UiPurity;

/**
 * ① نطاقُ الملفّات — **مُعلَنٌ لا مضمَر**، ومطابقٌ لمقامِ `repair01_plan_gen`.
 * @return array{roots:string, skip:array<string>, ext:string, why:string}
 */
function repair01_w6_file_scope()
{
    return array(
        'ext'  => '.php',
        /* المستبعَدُ مُعلَنٌ وله عذر: أدواتُ القياسِ ووثائقُ الحملةِ ليست
           واجهةَ أعمال (‏_CONTEXT)، والهجراتُ بياناتٌ لا شاشات، والمكتباتُ
           الخارجيّةُ ليست ملكَ هذا المستودع. */
        'skip' => array(
            'vendor'      => 'مكتبةٌ خارجيّةٌ لا يملكها المستودع',
            'node_modules'=> 'مكتبةٌ خارجيّةٌ لا يملكها المستودع',
            '.git'        => 'بياناتُ المستودعِ لا شيفرتُه',
            'tools'       => 'أدواتُ القياسِ ليست واجهةَ أعمال',
            'tests'       => 'حزامُ الفحصِ ليس واجهةَ أعمال',
            'database'    => 'الهجراتُ بياناتٌ ومخطَّطٌ لا شاشات',
            'docs'        => 'وثائقُ الحملةِ ليست واجهةَ أعمال',
            'storage'     => 'مخرَجاتٌ لا شيفرة',
            '.ssdiff'     => 'حزامُ لقطاتٍ لا شيفرة',
            'backups'     => 'نسخٌ لا شيفرة',
        ),
        'why' => 'المقامُ نفسُه الذي يقيسه repair01_plan_gen — فلا مقامانِ لرقمٍ واحد',
    );
}

/** ملفّاتُ النطاقِ بمسارِها النسبيّ. */
function repair01_w6_files($ROOT)
{
    $sc = repair01_w6_file_scope();
    $ROOT = str_replace(chr(92), '/', rtrim($ROOT, '/' . chr(92)));
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p   = str_replace(chr(92), '/', $f->getPathname());
        if (substr($p, -strlen($sc['ext'])) !== $sc['ext']) { continue; }
        $rel = ltrim(substr($p, strlen($ROOT)), '/');
        $skip = false;
        foreach (array_keys($sc['skip']) as $s) {
            if (strpos($rel, $s . '/') === 0) { $skip = true; break; }
        }
        if (!$skip) { $out[$rel] = $p; }
    }
    ksort($out);
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② تقطيعُ الملفِّ إلى مدياتٍ مصنَّفة
   الأصنافُ ثلاثةٌ لا أكثر — وكلٌّ منها له حكمٌ واحدٌ لا اجتهاد:
     `UI`      نصٌّ يولّده النظامُ ويراه المستخدم ⇒ **يُنقّى**
     `COUPLED` نصٌّ يُقارَن أو يُستعلَم به ⇒ **يُعذَر ويُعلَن بعددِه**
     `COMMENT` تعليقُ شيفرةٍ بأيِّ لغة ⇒ **خارجَ المقامِ أصلًا**
   ═══════════════════════════════════════════════════════════════════════════ */

const REPAIR01_W6_UI      = 'UI';
const REPAIR01_W6_COUPLED = 'COUPLED';
const REPAIR01_W6_COMMENT = 'COMMENT';

/** التشكيلُ والتطويل — النطاقُ نفسُه الذي يقيسه `UiPurity`. */
function repair01_w6_diac_re()
{
    return UiPurity::RE_DIACRITIC;
}

/**
 * أهو نصٌّ عربيٌّ أصلًا؟ سلسلةٌ بلا حرفٍ عربيٍّ استعلامٌ أو مفتاحٌ أو مسار —
 * ولا تُعَدُّ نصَّ واجهةٍ ولا يُبحَث فيها عن تشكيل.
 */
function repair01_w6_has_arabic($s)
{
    return preg_match('/[\x{0620}-\x{064A}]/u', (string) $s) === 1;
}

/**
 * تقطيعُ نصِّ `T_INLINE_HTML` — الوسمُ يحمل ثلاثَ لغاتٍ لا لغةً واحدة.
 *
 * ◆ ⛔ **والحالةُ تُحمَل بين الرموزِ لا تُستأنَف مع كلِّ رمز**: `<script>`
 *   يُفتَح في رمزِ HTML ثمَّ يقطعه `<?php … ?>` ثمَّ يُغلَق في رمزٍ لاحق —
 *   فمعالجةُ كلِّ رمزٍ وحدَه تفقد أنّها **داخلَ جافاسكربت**، فتُعَدُّ تعليقاتُه
 *   `// …` نصَّ واجهةٍ وتُنقَّى (‏قِيس حيًّا في `insidebar.php`: ستُّ مدياتِ
 *   تعليقٍ جافاسكربت نُقّيت وهي ليست واجهةً). و`$mode` يعبر بالحالةِ من رمزٍ
 *   إلى ما يليه.
 *
 * @param string $html
 * @param int    $base  إزاحةُ بدايةِ النصِّ في الملفّ
 * @param string $mode  الحالةُ الموروثةُ: HTML · SCRIPT · STYLE (‏تُحدَّث بالمرجع)
 * @return array<array{0:int,1:int,2:string,3:string}> [إزاحة, طول, صنف, سبب]
 */
function repair01_w6_html_spans($html, $base, &$mode = "HTML", &$jsq = "")
{
    $len = strlen($html);
    $init = ($mode === 'SCRIPT') ? 'S' : (($mode === 'STYLE') ? 'Y' : 'U');
    $cls = str_repeat($init, $len);
    $mark = function ($from, $to, $ch) use (&$cls, $len) {
        if ($from < 0) { $from = 0; }
        if ($to > $len) { $to = $len; }
        for ($i = $from; $i < $to; $i++) { $cls[$i] = $ch; }
    };

    /* ② -أ تعليقُ HTML — تعليقٌ بأيِّ لغةٍ كُتب */
    $off = 0;
    while ($off < $len && ($a = strpos($html, '<!--', $off)) !== false) {
        $b = strpos($html, '-->', $a);
        $b = ($b === false) ? $len : $b + 3;
        $mark($a, $b, 'C');
        $off = $b;
    }

    /* ② -ب مديات `<script>` و`<style>` — والحالةُ الموروثةُ تُغلَق أوّلًا */
    $low = strtolower($html);
    if ($mode !== 'HTML') {
        $tag = ($mode === 'SCRIPT') ? 'script' : 'style';
        $end = strpos($low, '</' . $tag);
        if ($end === false) {
            /* الرمزُ كلُّه داخلَ الوسمِ — والحالةُ تبقى كما هي */
            $end = $len;
        } else {
            for ($i = $end; $i < $len; $i++) { $cls[$i] = 'U'; }
            $mode = 'HTML';
        }
    }
    foreach (array('script' => 'S', 'style' => 'Y') as $tag => $ch) {
        $off = 0;
        while ($off < $len && ($a = strpos($low, '<' . $tag, $off)) !== false) {
            if ($cls[$a] !== 'U') { $off = $a + 1; continue; }
            $open = strpos($html, '>', $a);
            if ($open === false) { break; }
            $b = strpos($low, '</' . $tag, $open);
            if ($b === false) {
                /* لم يُغلَق في هذا الرمز — الحالةُ تعبر إلى ما يليه */
                $b = $len;
                $mode = ($ch === 'S') ? 'SCRIPT' : 'STYLE';
            }
            /* داخلُ الوسمِ وحدَه — والوسمانِ نفسُهما نصٌّ لا يحمل عربيّة */
            for ($i = $open + 1; $i < $b; $i++) { if ($cls[$i] === 'U') { $cls[$i] = $ch; } }
            $off = $b + 1;
        }
    }

    /* ② -ج مسحٌ واحدٌ من اليسارِ إلى اليمينِ على مدياتِ جافاسكربت
       ═══════════════════════════════════════════════════════════════════════
       ⛔ **ولماذا مسحٌ واحدٌ لا مسحان**: مسحُ التعليقاتِ ثمَّ مسحُ السلاسلِ
         يُخطئ في الاتّجاهَين — `'https://…'` فيها `//` فتُقرَأ تعليقًا يبتلع
         بقيّةَ السطر، و`// don't` فيها `'` فتُقرَأ فاتحةَ سلسلةٍ تبتلع صفحةً.
         فالحكمُ يقع مرّةً واحدةً بترتيبِ ورودِ المحارف.
       ⛔ **والسلسلةُ تعبر الرموزَ كالوسم**: `'…css<?php echo … ?>'` سلسلةٌ
         واحدةٌ يقطعها PHP، فيبدأ الرمزُ التالي **داخلَها** ويكون أوّلُ محرفٍ
         فيه هو **خاتمتَها** لا فاتحةَ سلسلةٍ جديدة. وبلا حملِ `$jsq` ينقلب
         الحكمُ فيصير كلُّ تعليقٍ نصًّا وكلُّ نصٍّ تعليقًا (‏قِيس حيًّا في
         `insidebar.php`: سبعُ مدياتِ تعليقٍ نُقّيت وهي ليست واجهة). */
    $i = 0;
    if ($jsq !== '' && $len > 0 && $cls[0] === 'S') {
        /* استئنافُ سلسلةٍ فُتحت في رمزٍ سابق: تُقرأ حتى خاتمتِها */
        $j = 0;
        while ($j < $len) {
            if ($html[$j] === chr(92)) { $j += 2; continue; }
            if ($html[$j] === $jsq) { break; }
            $j++;
        }
        if ($j >= $len) { $i = $len; }
        else { $i = $j + 1; $jsq = ''; }
        $mark(0, $i, 'S');
    }
    while ($i < $len) {
        if ($cls[$i] !== 'S') { $i++; continue; }
        $c = $html[$i];
        if ($c === '/' && $i + 1 < $len && $html[$i + 1] === '*') {
            $b = strpos($html, '*/', $i + 2);
            $b = ($b === false) ? $len : $b + 2;
            $mark($i, $b, 'C');
            $i = $b;
            continue;
        }
        if ($c === '/' && $i + 1 < $len && $html[$i + 1] === '/') {
            $b = strpos($html, "\n", $i + 2);
            $b = ($b === false) ? $len : $b;
            $mark($i, $b, 'C');
            $i = $b;
            continue;
        }
        if ($c !== "'" && $c !== '"' && $c !== '`') { $cls[$i] = 'X'; $i++; continue; }
        $j = $i + 1;
        while ($j < $len) {
            if ($html[$j] === chr(92)) { $j += 2; continue; }
            if ($html[$j] === $c) { break; }
            $j++;
        }
        if ($j >= $len) { $jsq = $c; $j = $len; } else { $j++; }

        $before = substr($html, max(0, $i - 12), min(12, $i));
        $after  = ($j < $len) ? substr($html, $j, 12) : '';
        $why = repair01_w6_js_coupled($before, $after);
        $ch = ($why === '') ? 'S' : (($why === 'JS_KEY') ? 'k' : 'K');
        $mark($i, $j, $ch);
        $i = $j;
    }

    /* ② -د تعليقُ CSS داخلَ `<style>` — ولا سلاسلَ يُعتدُّ بها فيه */
    for ($i = 0; $i < $len - 1; $i++) {
        if ($cls[$i] !== 'Y') { continue; }
        if ($html[$i] === '/' && $html[$i + 1] === '*') {
            $b = strpos($html, '*/', $i + 2);
            $b = ($b === false) ? $len : $b + 2;
            $mark($i, $b, 'C');
            $i = $b - 1;
        }
    }

    /* ② -هـ الترجمةُ إلى مديات */
    $spans = array();
    $map = array('U' => REPAIR01_W6_UI, 'S' => REPAIR01_W6_UI, 'Y' => REPAIR01_W6_UI,
                 'C' => REPAIR01_W6_COMMENT, 'K' => REPAIR01_W6_COUPLED,
                 'k' => REPAIR01_W6_COUPLED, 'X' => REPAIR01_W6_COUPLED);
    $why = array('K' => 'JS_COMPARE', 'k' => 'JS_KEY', 'X' => 'CODE');
    $start = 0;
    for ($i = 1; $i <= $len; $i++) {
        if ($i === $len || $cls[$i] !== $cls[$start]) {
            $c = $cls[$start];
            $spans[] = array($base + $start, $i - $start, $map[$c],
                             isset($why[$c]) ? $why[$c] : '');
            $start = $i;
        }
    }
    return $spans;
}

/**
 * سلسلةُ جافاسكربت في موضعِ مقارنةٍ أو مفتاحٍ ⇒ قيمةُ بياناتٍ لا نصُّ واجهة.
 * @return string سببُ الاقتران أو '' فهي نصُّ واجهة
 */
function repair01_w6_js_coupled($before, $after)
{
    $b = rtrim($before);
    $a = ltrim($after);
    if (preg_match('/(===|!==|==|!=)\s*$/', $b)) { return 'JS_COMPARE'; }
    if (preg_match('/^\s*(===|!==|==|!=)/', $a)) { return 'JS_COMPARE'; }
    if (preg_match('/\bcase\s*$/', $b)) { return 'JS_COMPARE'; }
    if (substr($b, -1) === '[' && substr($a, 0, 1) === ']') { return 'JS_INDEX'; }
    if (preg_match('/\.(indexOf|includes|startsWith|endsWith|split|replace|match)\s*\(\s*$/', $b)) { return 'JS_COMPARE'; }
    if (substr($a, 0, 1) === ':') { return 'JS_KEY'; }      /* مفتاحُ كائن */
    return '';
}

/**
 * تقطيعُ ملفِّ PHP كاملًا إلى مدياتٍ مصنَّفة.
 * @return array{spans:array, ok:bool}
 */
function repair01_w6_file_spans($src)
{
    $toks = @token_get_all($src);
    if (!is_array($toks)) { return array('spans' => array(), 'ok' => false); }
    $spans = array();
    $off = 0;
    $n = count($toks);
    $mode = 'HTML'; $jsq = '';
    for ($i = 0; $i < $n; $i++) {
        $t = $toks[$i];
        $txt = is_array($t) ? $t[1] : $t;
        $len = strlen($txt);
        $id  = is_array($t) ? $t[0] : 0;

        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            $spans[] = array($off, $len, REPAIR01_W6_COMMENT, '');
        } elseif ($id === T_INLINE_HTML) {
            foreach (repair01_w6_html_spans($txt, $off, $mode, $jsq) as $s) { $spans[] = $s; }
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
            $why = repair01_w6_php_coupled($toks, $i, $txt);
            $spans[] = ($why === '')
                ? array($off, $len, REPAIR01_W6_UI, '')
                : array($off, $len, REPAIR01_W6_COUPLED, $why);
        } else {
            /* شيفرةٌ خالصة: معرّفاتٌ وكلماتٌ محجوزةٌ — لا نصَّ واجهةٍ فيها */
            $spans[] = array($off, $len, REPAIR01_W6_COUPLED, 'CODE');
        }
        $off += $len;
    }
    return array('spans' => $spans, 'ok' => true);
}

/**
 * سلسلةُ PHP في موضعِ مقارنةٍ أو مفتاحٍ أو استعلام ⇒ **قيمةُ بياناتٍ تُعذَر**.
 * ◆ والاستعلامُ يُعرَف بكلماتِه لا بنيّةِ كاتبِه: نصٌّ فيه `WHERE` أو `VALUES`
 *   يُنفَّذ في القاعدةِ ولو بدا جملةً — ونزعُ تشكيلِ قيمةٍ فيه يفكُّ الشرط.
 * @return string سببُ الاقتران: SQL · COMPARE · CASE · KEY · INDEX — أو '' فهو نصُّ واجهة
 */
function repair01_w6_php_coupled($toks, $i, $txt)
{
    if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|WHERE|VALUES|SET|LIKE|IN)\s/i', $txt)
        && preg_match('/\b(FROM|WHERE|VALUES|SET|JOIN)\b/i', $txt)) { return 'SQL'; }

    $skip = array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT);
    $j = $i - 1;
    while ($j >= 0 && is_array($toks[$j]) && in_array($toks[$j][0], $skip, true)) { $j--; }
    $k = $i + 1;
    $n = count($toks);
    while ($k < $n && is_array($toks[$k]) && in_array($toks[$k][0], $skip, true)) { $k++; }

    $prevId = ($j >= 0 && is_array($toks[$j])) ? $toks[$j][0] : 0;
    $nextId = ($k < $n && is_array($toks[$k])) ? $toks[$k][0] : 0;
    $prevCh = ($j >= 0 && !is_array($toks[$j])) ? $toks[$j] : '';
    $nextCh = ($k < $n && !is_array($toks[$k])) ? $toks[$k] : '';

    $cmp = array(T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL);
    if (in_array($prevId, $cmp, true) || in_array($nextId, $cmp, true)) { return 'COMPARE'; }
    if ($prevId === T_CASE) { return 'CASE'; }

    /* ⛔ **قيمةُ ثابتٍ مُعلَنٍ مفردةُ معجمٍ لا لافتةُ عرض** (‏قِيس حيًّا:
       `const SIGNED = 'موقَّع';` في `ContractStateMachine` — **قيمةٌ تُكتَب
       في القاعدةِ وتُقارَن بها في اثنَي عشرَ موضعًا**). ونزعُ تشكيلِها في
       الشيفرةِ يجعل الثابتَ لا يطابق صفًّا واحدًا من صفوفِ القاعدةِ القائمة،
       **بلا خطأٍ يظهر**. فالثابتُ يُعذَر ومفردتُه تدخل المعجمَ فتُعذَر معها
       كلُّ لافتةٍ تساويها — ولا يُمَسّ طرفٌ دون طرف. */
    $k2 = $j;
    while ($k2 >= 0 && is_array($toks[$k2]) && in_array($toks[$k2][0], $skip, true)) { $k2--; }
    if ($prevCh === '=' || $prevId === T_DOUBLE_ARROW) {
        $m = $j - 1;
        $depth = 0;
        while ($m >= 0 && $m > $j - 40) {
            $tk = $toks[$m];
            $tid = is_array($tk) ? $tk[0] : 0;
            $tch = is_array($tk) ? '' : $tk;
            if ($tch === ';' || $tch === '{' || $tch === '}') { break; }
            if ($tid === T_CONST) { return 'CONST'; }
            $m--;
        }
        unset($depth);
    }
    if ($prevCh === '(' || $prevCh === ',') {
        /* `define('X', 'قيمة')` — الطرفانِ مفردتا معجمٍ لا نصُّ واجهة.
           و**دوالُّ البحثِ والمطابقة** (`in_array` · `str_contains` · …)
           وسيطُها قيمةٌ تُقارَن لا لافتةٌ تُعرَض — ونزعُ تشكيلِها يُسكِت
           الشرطَ كما يُسكته نزعُه من `===`. */
        $fn = array('define' => 'CONST', 'in_array' => 'COMPARE', 'array_search' => 'COMPARE',
                    'array_key_exists' => 'COMPARE', 'strpos' => 'COMPARE', 'stripos' => 'COMPARE',
                    'mb_strpos' => 'COMPARE', 'str_contains' => 'COMPARE',
                    'str_starts_with' => 'COMPARE', 'str_ends_with' => 'COMPARE',
                    'strcmp' => 'COMPARE', 'strcasecmp' => 'COMPARE');
        $m = $j;
        while ($m >= 0 && is_array($toks[$m]) && in_array($toks[$m][0], $skip, true)) { $m--; }
        for ($z = $m; $z >= 0 && $z > $m - 12; $z--) {
            if (is_array($toks[$z]) && $toks[$z][0] === T_STRING) {
                $nm = strtolower($toks[$z][1]);
                if (isset($fn[$nm])) { return $fn[$nm]; }
            }
        }
    }

    if ($nextId === T_DOUBLE_ARROW) { return 'KEY'; }        /* مفتاحُ مصفوفة */
    if ($prevCh === '[' && $nextCh === ']') { return 'INDEX'; } /* دليلُ مصفوفة */
    return '';
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ المعجمُ المقترن — يُجمَع من الشجرةِ كلِّها **قبل** أيِّ كتابة
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * كلُّ نصٍّ عربيٍّ مشكولٍ وقع في موضعِ مقارنةٍ أو مفتاحٍ أو استعلامٍ في أيِّ
 * ملفٍّ من ملفّاتِ النطاق — **ومعه قيمُ `ENUM` و`SET` المشكولةُ في المخطَّط**.
 * وأيُّ نصِّ واجهةٍ يساوي أحدَها حرفًا **يُعذَر ولا يُمَسّ**: طرفٌ يُنقّى
 * وطرفٌ لا يُنقّى حكمٌ يصمت.
 * @return array<string,string> النصُّ ⇐ مصدرُ اقترانِه
 */
function repair01_w6_coupled_vocab($ROOT, $conn = null)
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = array();
    $diac = repair01_w6_diac_re();

    foreach (repair01_w6_files($ROOT) as $rel => $path) {
        $src = (string) @file_get_contents($path);
        $r = repair01_w6_file_spans($src);
        if (!$r['ok']) { continue; }
        foreach ($r['spans'] as $s) {
            if ($s[2] !== REPAIR01_W6_COUPLED) { continue; }
            $why = isset($s[3]) ? $s[3] : '';
            /* ⛔ **ومفتاحُ المصفوفةِ لا يورّث اقترانَه**: مسحُ الحيِّ يجد أنَّ
               الغالبَ الأعمَّ من مفاتيحِ المصفوفاتِ العربيّةِ في هذا المستودعِ
               **لافتاتُ عرضٍ** (`'مخاطر مقيَّمة' => 12` بطاقةُ إحصاء) لا
               مفاتيحَ بحثٍ تأتي من القاعدة. فيُعذَر المفتاحُ **في موضعِه**
               (‏لئلّا ينكسر بحثٌ بمفتاحٍ حرفيّ) **ولا تدخل مفرداتُه المعجمَ**،
               وإلّا أعفت كلمةٌ في لافتةٍ (`يومًا`) مئةً واثنتَينِ وستّينَ علامةً
               في نصوصٍ لا علاقةَ لها بها. والمُورِّثُ **ما يجب أن يتطابق**:
               مقارنةٌ · `case` · دليلٌ حرفيّ · قيمةُ شرطٍ في استعلام · `ENUM`. */
            if (!in_array($why, array('COMPARE', 'CASE', 'CONST', 'INDEX', 'SQL',
                                      'JS_COMPARE', 'JS_INDEX'), true)) {
                continue;
            }
            $txt = substr($src, $s[0], $s[1]);
            if (!preg_match($diac, $txt) || !repair01_w6_has_arabic($txt)) { continue; }
            /* ⛔ **والاستعلامُ لا يُسلَّم معجمُه كلُّه**: نصُّ SQL يحمل تعليقَه
               (`-- فالسؤالُ …`) وسطورَ عرضٍ يبنيها `CONCAT` — وأخذُ كلِّ مقطعٍ
               عربيٍّ فيه معجمًا يُعفي مئاتِ نصوصِ الواجهةِ بلا اقترانٍ حقيقيّ
               (‏قِيس: «يومًا» من تعليقِ استعلامٍ أعفت ١٦٢ علامة). فالمأخوذُ من
               الاستعلامِ **قيمةُ المقارنةِ وحدَها**: ما يلي `=` أو `LIKE` أو
               `IN (` — وهو الذي يفكُّه نزعُ التشكيل. */
            $terms = ($why === 'SQL')
                ? repair01_w6_sql_compare_values($txt)
                : repair01_w6_vocab_terms($txt);
            foreach ($terms as $term) {
                if (!isset($cache[$term])) { $cache[$term] = $why . ' · ' . $rel; }
            }
        }
    }

    foreach (repair01_w6_db_value_vocab($conn) as $term => $where) {
        if (!isset($cache[$term])) { $cache[$term] = $where; }
    }
    return $cache;
}

/**
 * ⛔ **الحزامُ الأمتن: القيمةُ القائمةُ في القاعدةِ نفسِها.**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الرمزنةُ تعرف مواضعَ المقارنةِ في **الشيفرة**، ولا تعرف أنَّ
 *   `'موقَّع'` **مكتوبٌ في ألفِ صفٍّ من `contracts.status`**. ونزعُ تشكيلِه في
 *   الشيفرةِ يجعلها لا تطابق صفًّا واحدًا — بلا خطأٍ يظهر في شاشةٍ ولا في
 *   محلِّل. **فالمخزنُ حكمٌ** (‏_CONTEXT: «المخزنُ في القاعدة هو الحقيقة»):
 *   كلُّ قيمةٍ مشكولةٍ قائمةٍ اليومَ في عمودِ حالةٍ أو نوعٍ أو تصنيفٍ **مفردةُ
 *   اقترانٍ** لا تُمَسّ في الشيفرة.
 * ◆ **والمقامُ مُعلَنٌ لا مضمَر**: `ENUM` و`SET` كلُّها، وأعمدةُ النصِّ القصيرِ
 *   التي يسمّيها اسمُها حالةً أو نوعًا أو تصنيفًا. وما عداها **قيمُ بياناتٍ
 *   حرّةٌ لا تُقارَن** (‏أسماءٌ ووصفٌ وملاحظات) فلا تدخل المعجمَ وإلّا ابتلع
 *   كلَّ نصِّ الواجهة.
 * @return array<string,string> القيمةُ ⇐ موضعُها
 */
function repair01_w6_db_value_vocab($conn)
{
    if (!($conn instanceof mysqli)) { return array(); }
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = array();
    $diac = repair01_w6_diac_re();

    /* ① كلُّ قيمِ `ENUM` و`SET` — تعريفُها في المخطَّطِ يكفي دليلًا */
    $q = @$conn->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
                          FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND DATA_TYPE IN ('enum','set')");
    while ($q && $x = $q->fetch_assoc()) {
        if (!preg_match($diac, (string) $x['COLUMN_TYPE'])) { continue; }
        if (preg_match_all("/'((?:[^']|'')*)'/", (string) $x['COLUMN_TYPE'], $mm)) {
            foreach ($mm[1] as $v) {
                $v = str_replace("''", "'", $v);
                if (preg_match($diac, $v) && !isset($cache[$v])) {
                    $cache[$v] = 'ENUM · ' . $x['TABLE_NAME'] . '.' . $x['COLUMN_NAME'];
                }
            }
        }
    }

    /* ② القيمُ القائمةُ في أعمدةِ الحالةِ والنوعِ والتصنيف */
    $q = @$conn->query("SELECT TABLE_NAME, COLUMN_NAME
                          FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND DATA_TYPE IN ('varchar','char')
                           AND CHARACTER_MAXIMUM_LENGTH <= 80
                           AND COLUMN_NAME REGEXP
                               '(^|_)(status|state|kind|type|category|class|stage|result|decision|mode|level|phase|verdict|outcome)(_|$)'");
    $cols = array();
    while ($q && $x = $q->fetch_row()) { $cols[] = array($x[0], $x[1]); }
    foreach ($cols as $c) {
        /* نمطُ التشكيلِ بحروفِه — `REGEXP` في ماريادبي لا تعرف `\u{…}` */
        $r = @$conn->query("SELECT DISTINCT `" . $c[1] . "` v FROM `" . $c[0] . "`
                             WHERE `" . $c[1] . "` REGEXP '[ً-ْٰ]' LIMIT 200");
        while ($r && $x = $r->fetch_row()) {
            $v = trim((string) $x[0]);
            if ($v !== '' && preg_match($diac, $v) && !isset($cache[$v])) {
                $cache[$v] = 'DB_VALUE · ' . $c[0] . '.' . $c[1];
            }
        }
    }
    return $cache;
}

/**
 * قيمُ المقارنةِ داخلَ استعلام — **وحدَها** تدخل معجمَ الاقتران.
 * `WHERE state = 'مُعتمد'` · `AND kind IN ('مشغّل','سائق')` · `LIKE 'مُعلَّق%'`
 * @return array<string>
 */
function repair01_w6_sql_compare_values($sql)
{
    $diac = repair01_w6_diac_re();
    $out = array();
    $re = '/(?:=|<>|!=|\bLIKE\b|\bIN\b\s*\()\s*[\'"]?\s*([\x{0600}-\x{06FF}\s%]{2,60}?)\s*[\'"%,\)]/iu';
    if (preg_match_all($re, $sql, $mm)) {
        foreach ($mm[1] as $v) {
            $v = trim($v, " \t%");
            if ($v !== '' && mb_strlen($v, 'UTF-8') >= 3 && preg_match($diac, $v)) { $out[] = $v; }
        }
    }
    return array_unique($out);
}

/**
 * مفرداتُ نصٍّ مقترن: النصُّ كاملًا بلا أقواسِه، وكلُّ مقطعٍ عربيٍّ متّصلٍ فيه.
 * ◆ **ولماذا المقاطعُ لا النصُّ وحدَه**: الاستعلامُ يحمل قيمتَه داخلَ جملةٍ
 *   (`WHERE state = 'مُعتمد'`) — فمساواةُ النصِّ كاملًا لا تلتقط القيمة.
 *
 * ◆ ⛔ **وشرطانِ يمنعان المعجمَ من ابتلاعِ الواجهة**: المفردةُ **تحمل تشكيلًا**
 *   و**تبلغ ثلاثةَ أحرف**. وبغيرِهما تدخل `لا` و`من` و`ثم` معجمَ الاقتران —
 *   وهي حروفُ ربطٍ في كلِّ جملةٍ عربيّة، فيُعذَر بها **كلُّ** نصِّ واجهةٍ
 *   ويصير الإعفاءُ سترًا للدَّينِ لا صيانةً للمقارنة (‏قِيس: ٢٨٢٦ علامةً
 *   أُعفيت بلا سببٍ قبل الشرطَين).
 * ◆ **والنصُّ كاملًا لا يُؤخَذ إلّا إن كان قيمةً خالصة**: نصٌّ فيه حرفٌ
 *   لاتينيٌّ استعلامٌ أو رسالةُ سجلٍّ لا قيمةَ حالةٍ تُقارَن.
 */
function repair01_w6_vocab_terms($txt)
{
    $diac = repair01_w6_diac_re();
    $keep = function ($s) use ($diac) {
        $s = trim($s);
        return $s !== '' && mb_strlen($s, 'UTF-8') >= 3
            && repair01_w6_has_arabic($s) && preg_match($diac, $s) === 1;
    };
    $out = array();
    $t = trim(trim($txt), "'\"");
    if ($keep($t) && !preg_match('/[A-Za-z]/', $t)) { $out[] = trim($t); }
    if (preg_match_all('/[\x{0600}-\x{06FF}\s]{3,}/u', $txt, $mm)) {
        foreach ($mm[0] as $p) {
            if ($keep($p)) { $out[] = trim($p); }
        }
    }
    return array_unique($out);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ القياسُ والتنقية
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * قياسُ ملفٍّ واحد.
 * @return array{ui:int, coupled:int, comment:int, excused:int, ok:bool}
 *   `ui` علاماتٌ في نصِّ واجهةٍ **تجب تنقيتُها** · `excused` منها ما أُعفي
 *   لاقترانِه بمعجمٍ يُقارَن · `coupled` علاماتٌ في مواضعِ المقارنةِ نفسِها ·
 *   `comment` علاماتٌ في تعليقٍ (‏خارجَ المقامِ أصلًا).
 */
function repair01_w6_file_measure($src, array $vocab)
{
    $r = repair01_w6_file_spans($src);
    $o = array('ui' => 0, 'coupled' => 0, 'comment' => 0, 'excused' => 0, 'ok' => $r['ok'],
               'samples' => array(), 'terms' => array());
    if (!$r['ok']) { return $o; }
    $diac = repair01_w6_diac_re();
    foreach ($r['spans'] as $s) {
        $txt = substr($src, $s[0], $s[1]);
        $n = preg_match_all($diac, $txt);
        if (!$n) { continue; }
        if ($s[2] === REPAIR01_W6_COMMENT)  { $o['comment'] += $n; continue; }
        if ($s[2] === REPAIR01_W6_COUPLED)  { $o['coupled'] += $n; continue; }
        $term = repair01_w6_span_excused($txt, $vocab);
        if ($term !== '') {
            $o['excused'] += $n;
            $o['terms'][$term] = isset($o['terms'][$term]) ? $o['terms'][$term] + $n : $n;
            continue;
        }
        $o['ui'] += $n;
        if (count($o['samples']) < 3) {
            $o['samples'][] = trim(preg_replace('/\s+/u', ' ', mb_substr($txt, 0, 60, 'UTF-8')));
        }
    }
    return $o;
}

/**
 * أيُعذَر هذا المدى لاقترانِه؟ — **المساواةُ حرفًا لا الاحتواء**، وبالشرطَين
 * نفسِهما اللذَين بُني بهما المعجم (‏تشكيلٌ · ثلاثةُ أحرف). ولو قِيس بالاحتواء
 * لأعفت مفردةٌ واحدةٌ كلَّ جملةٍ تذكرها — وهو إعفاءٌ يستر الدَّينَ لا يصون
 * المقارنة.
 * @return string '' إن لم يُعذَر، وإلّا المفردةُ التي أوجبت الإعفاء
 */
function repair01_w6_span_excused($txt, array $vocab)
{
    if (!$vocab) { return ''; }
    $t = trim(trim($txt), "'\"");
    if ($t !== '' && isset($vocab[$t])) { return $t; }
    /* مقطعٌ عربيٌّ متّصلٌ داخلَ المدى يساوي مفردةً مقترنة ⇒ يُعذَر المدى كلُّه:
       مسُّ جزءٍ منه يفكُّ المقارنةَ نفسَها. */
    if (preg_match_all('/[\x{0600}-\x{06FF}\s]{3,}/u', $txt, $mm)) {
        foreach ($mm[0] as $p) {
            $p = trim($p);
            if ($p !== '' && isset($vocab[$p])) { return $p; }
        }
    }
    return '';
}

/**
 * تنقيةُ ملفٍّ — التحريرُ على مدًى محسوبٍ ومن الآخِرِ إلى الأوّلِ حتى لا
 * تُزحزِحَ كتابةٌ إزاحةَ ما بعدَها.
 * @return array{src:string, changed:int, ok:bool}
 */
function repair01_w6_file_purify($src, array $vocab)
{
    $r = repair01_w6_file_spans($src);
    if (!$r['ok']) { return array('src' => $src, 'changed' => 0, 'ok' => false); }
    $diac = repair01_w6_diac_re();
    $edits = array();
    foreach ($r['spans'] as $s) {
        if ($s[2] !== REPAIR01_W6_UI) { continue; }
        $txt = substr($src, $s[0], $s[1]);
        if (!preg_match($diac, $txt)) { continue; }
        if (repair01_w6_span_excused($txt, $vocab)) { continue; }
        $new = preg_replace($diac, '', $txt);
        if ($new !== $txt) { $edits[] = array($s[0], $s[1], $new); }
    }
    if (!$edits) { return array('src' => $src, 'changed' => 0, 'ok' => true); }
    usort($edits, function ($a, $b) { return $b[0] - $a[0]; });
    $out = $src;
    foreach ($edits as $e) { $out = substr_replace($out, $e[2], $e[0], $e[1]); }
    return array('src' => $out, 'changed' => count($edits), 'ok' => true);
}

/**
 * مسحُ الشجرةِ كلِّها — الرقمُ الذي تقرؤه البوّابةُ والمنقّي والفاحص.
 * @return array{files:int, dirty:int, ui:int, coupled:int, comment:int, excused:int, top:array}
 */
function repair01_w6_files_scan($ROOT, $conn = null)
{
    $vocab = repair01_w6_coupled_vocab($ROOT, $conn);
    $o = array('files' => 0, 'dirty' => 0, 'ui' => 0, 'coupled' => 0, 'comment' => 0,
               'excused' => 0, 'unparsed' => 0, 'top' => array(), 'terms' => array(),
               'vocab' => count($vocab));
    foreach (repair01_w6_files($ROOT) as $rel => $path) {
        $m = repair01_w6_file_measure((string) @file_get_contents($path), $vocab);
        $o['files']++;
        if (!$m['ok']) { $o['unparsed']++; continue; }
        $o['ui'] += $m['ui']; $o['coupled'] += $m['coupled'];
        $o['comment'] += $m['comment']; $o['excused'] += $m['excused'];
        foreach ($m['terms'] as $t => $c) {
            $o['terms'][$t] = isset($o['terms'][$t]) ? $o['terms'][$t] + $c : $c;
        }
        if ($m['ui'] > 0) {
            $o['dirty']++;
            $o['top'][$rel] = $m['ui'] . ($m['samples'] ? ' ⇐ ' . $m['samples'][0] : '');
        }
    }
    return $o;
}
