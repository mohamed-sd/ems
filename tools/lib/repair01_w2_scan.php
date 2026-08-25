<?php
/**
 * tools/lib/repair01_w2_scan.php
 *   ماسحُ W02 — السجلُّ المعياريُّ والسايدبار  ·  **مصدرٌ واحدٌ لمستهلكَين**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا مكتبةٌ لا نسخةٌ في كلِّ أداة**: `repair01_w2_apply.php` يكتب،
 *   و`repair01_w2_gate.php` يتحقق، و`repair01_w2_negative.php` يكسر ويرجع.
 *   ثلاثةُ مستهلكين لمنطقِ قياسٍ واحد — ولو نسخ كلٌّ منهم منطقَه لتفرَّقت
 *   الأرقامُ بصمتٍ عند أوّلِ تعديل (وهو ما وقع قبلًا في عدّادٍ وعارضٍ في
 *   ملفَّين). فالماسحُ واحدٌ ومستهلكوه ثلاثة.
 *
 * ◆ **والبوّابةُ مع ذلك تُعيد القياسَ ولا تقرأ ما خزَّنه المُطبِّق**: المكتبةُ
 *   تقيس **من القرصِ والحيِّ** لا من `repair01_screen_registry` — فالبوّابةُ
 *   تقارن قياسَها الحيَّ بما في السجلّ، لا السجلَّ بنفسِه.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (‏_CONTEXT §قواعد القياس ٣): الحارسُ
 *   يُرصد باسمِ دالّتِه، والبندُ اليدويُّ بوسمِ `<li><a href=` بعدَ **نزعِ
 *   التعليقات**، والكتابةُ بجملةِ SQL بعدَ نزعِ التعليقاتِ أيضًا.
 *   ⇐ فتعليقٌ فيه `INSERT INTO` لا يُحسب كتابةً (وقع فعلًا في
 *     `Procurement/rfq_compare_award.php`: تعليقُ الإصلاحِ نفسُه يذكر
 *     `INSERT INTO rfq_awards` قبلَ الحارسِ بسطرَين — **الكاشفُ يرصد
 *     مفرداتِه هو** لو قِيس الخامّ).
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** ملفّاتُ القشرةِ التي يُقاس فيها البندُ اليدويّ — والقائمةُ مُعلَنةٌ لا مُخفاة. */
function repair01_w2_shell_files()
{
    return array('insidebar.php', 'includes/unified_nav.php', 'includes/dynamic_nav.php');
}

/**
 * القشرةُ الجذرُ — الملفُّ الذي ينفِّذ حارسَ العرضِ المركزيَّ لكلِّ من يُضمِّنه.
 * ولا يُكتب هنا اسمُ غلافٍ ثانٍ: **الأغلفةُ تُكتشف بالإغلاقِ العوديِّ** لا بقائمةٍ
 * يدوية — لأنّ القائمةَ اليدويّةَ عميت عن `Risk/dept_risk_space.php`
 * (١٤ شاشةَ مخاطرَ تُصيَّر به) وعن `includes/fin_analysis_shell.php`، فحُسبت
 * أربعَ عشرةَ شاشةً محروسةً «ليست شاشةً».
 */
function repair01_w2_shell_root() { return 'insidebar.php'; }

/** ما لا يُقاس شاشةً بحكمِ موضعِه — مُعلَنٌ لا مُخفى. */
function repair01_w2_skip_dirs()
{
    return array('storage/', 'vendor/', '.git/', 'docs/', 'node_modules/', 'examples/',
                 'tests/', 'tools/', 'database/', 'logs/', 'install/', 'scripts/',
                 'user_guide/', 'assets/', 'api/', 'app/', 'cron/', 'excel/');
}

/** الحارسُ المركزيُّ الذي يُنفِّذه `insidebar.php` لكلِّ شاشةٍ تُضمِّنه. */
function repair01_w2_shell_guard_fn() { return 'enforce_current_page_view_permission'; }

/**
 * نزعُ التعليقاتِ والنصوصِ الحرفيةِ الطويلةِ بمُحلِّلِ PHP نفسِه.
 * ◆ **لماذا `token_get_all` لا `preg_replace`**: التعبيرُ النمطيُّ لا يفرِّق
 *   `//` داخلَ سلسلةِ مسارٍ عن تعليق، ولا يعرف `#` من لونٍ في CSS.
 *   والمُحلِّلُ يعرف — فالرسوُّ على البنيةِ لا على العبارة.
 * @return string الشيفرةُ بلا تعليقاتٍ (الأطوالُ محفوظةٌ بالمسافاتِ فتبقى
 *                المواضعُ قابلةً للمقارنة).
 */
function repair01_w2_strip_comments($src)
{
    $out = '';
    $toks = @token_get_all($src);
    if (!is_array($toks)) { return $src; }
    foreach ($toks as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                /* يُستبدل بمسافاتٍ بطولِه — فلا تنزاح المواضعُ بعده */
                $out .= str_repeat(' ', strlen($t[1]) - substr_count($t[1], "\n")) . str_repeat("\n", substr_count($t[1], "\n"));
                continue;
            }
            $out .= $t[1];
        } else {
            $out .= $t;
        }
    }
    return $out;
}

/**
 * كلُّ ملفّاتِ PHP الإنتاجيّةِ مع شيفرتِها بلا تعليقات — مقامُ المسحِ الخام.
 * @return array<string,string> المسارُ النسبيُّ ⇐ الشيفرةُ بلا تعليقات
 */
function repair01_w2_php_files($ROOT)
{
    static $cache = array();
    $ROOT = rtrim(str_replace('\\', '/', $ROOT), '/');
    if (isset($cache[$ROOT])) { return $cache[$ROOT]; }
    $SKIP = repair01_w2_skip_dirs();
    $out = array();
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
        $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($ROOT)), '/');
        $skip = false;
        foreach ($SKIP as $s) { if (strpos($rel, $s) === 0 || strpos($rel, '/' . $s) !== false) { $skip = true; break; } }
        if ($skip) { continue; }
        $out[$rel] = repair01_w2_strip_comments((string) file_get_contents($f->getPathname()));
    }
    ksort($out);
    $cache[$ROOT] = $out;
    return $out;
}

/** مسارُ تضمينٍ نسبيٌّ مُحَلٌّ إلى مسارِ المستودع — أو '' إن تعذّر. */
function repair01_w2_resolve_include($fromRel, $target)
{
    $target = str_replace('\\', '/', trim($target));
    $target = preg_replace('~^\.?/~', '', $target);
    $base = dirname($fromRel);
    if ($base === '.') { $base = ''; }
    $p = ($base === '' ? '' : $base . '/') . $target;
    $parts = array();
    foreach (explode('/', $p) as $seg) {
        if ($seg === '' || $seg === '.') { continue; }
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    return implode('/', $parts);
}

/**
 * خريطةُ التضمين: لكلِّ ملفٍّ **مواضعُ** ما يُضمِّنه من ملفّاتِ المستودع.
 *
 * ◆ يُقرأ من الشيفرةِ بلا تعليقات، ويُحَلُّ بمرشَّحَين لا بواحد:
 *   **نسبيًّا لملفِّ المُضمِّن** (`'../insidebar.php'`) و**نسبيًّا لجذرِ المستودع**
 *   (`$u13Root . '/insidebar.php'` — والجذرُ متغيّرٌ لا يُقرأ نصًّا).
 *   ⇐ والحلُّ بمرشَّحٍ واحدٍ (النسبيِّ وحدَه) يعمى عن `includes/u13_screen_kit.php`
 *     فتخرج **إحدى وأربعون شاشةً مولَّدةً** من مقامِ الشاشاتِ وتُحسب «ليست شاشة».
 * ◆ ولا يُقبل مرشَّحٌ لا وجودَ له في مقامِ المسح — فالمِرساةُ ملفٌّ قائمٌ لا سلسلةُ نصّ.
 *
 * @return array<string,array<string,int>> ملفٌّ ⇐ (ملفٌّ مُضمَّنٌ ⇐ أوّلُ موضع)
 */
function repair01_w2_include_map($files)
{
    $known = $files;
    $known[repair01_w2_shell_root()] = true;   /* القشرةُ الجذرُ قد تكون خارجَ المسحِ الضيّق */
    $map = array();
    foreach ($files as $rel => $clean) {
        $map[$rel] = array();
        if (!preg_match_all('~\b(?:include|require)(?:_once)?\b\s*\(?\s*([^;]{0,200}?)\s*\)?\s*;~i',
                            $clean, $mm, PREG_OFFSET_CAPTURE)) { continue; }
        foreach ($mm[1] as $i => $arg) {
            if (!preg_match_all('~[\'"]([^\'"]*?\.php)[\'"]~', $arg[0], $lit)) { continue; }
            foreach ($lit[1] as $t) {
                foreach (array(repair01_w2_resolve_include($rel, $t),
                               repair01_w2_resolve_include('', $t)) as $r) {
                    if ($r === '' || !isset($known[$r]) || isset($map[$rel][$r])) { continue; }
                    $map[$rel][$r] = $mm[0][$i][1];
                    break;
                }
            }
        }
    }
    return $map;
}

/**
 * الملفّاتُ **الحاملةُ للقشرة** — إغلاقٌ عوديٌّ من `insidebar.php` صعودًا.
 *
 * ◆ **لماذا الإغلاقُ لا قائمةٌ يدويّة**: أربعةَ عشرَ سطحَ مخاطرَ تُصيَّر عبر
 *   `Risk/dept_risk_space.php`، وخمسُ شاشاتٍ ماليةٍ عبر
 *   `includes/fin_analysis_shell.php` — ولا واحدةٌ منها تذكر `insidebar`
 *   في سطرِها. فقائمةُ الأغلفةِ اليدويّةُ تعمى عنها وتحسبها «ليست شاشة»،
 *   والإغلاقُ يجدها لأنّه يتبع الاستدعاءَ لا الاسم.
 *
 * @return array<string,int> ملفٌّ حاملٌ للقشرةِ ⇐ عمقُه من الجذر (0 = القشرةُ نفسُها)
 */
function repair01_w2_shell_bearers($map)
{
    $root = repair01_w2_shell_root();
    $bearers = array($root => 0);
    for ($pass = 0; $pass < 8; $pass++) {
        $grew = false;
        foreach ($map as $rel => $incs) {
            if (isset($bearers[$rel])) { continue; }
            foreach ($incs as $t => $pos) {
                if (isset($bearers[$t])) { $bearers[$rel] = $bearers[$t] + 1; $grew = true; break; }
            }
        }
        if (!$grew) { break; }
    }
    return $bearers;
}

/**
 * الشاشاتُ الحيّةُ المقيسةُ في W02.
 *
 * ◆ الشاشةُ: ملفُّ PHP إنتاجيٌّ **يحمل القشرةَ** (مباشرةً أو عبر غلافٍ)،
 *   وليس هو غلافًا ولا عُدّةً تحت `includes/`.
 * ◆ والمُحوِّلُ ليس شاشةً: ملفٌّ لا يحمل القشرةَ وكلُّ عملِه `header('Location:`
 *   أو `ems_route_redirect(` — لا يكشف بيانًا فلا يُقاس عليه حارسُ عرض.
 *
 * @return array<string,string> المسارُ النسبيُّ ⇐ الشيفرةُ بلا تعليقات
 */
function repair01_w2_live_screens($ROOT)
{
    $files = repair01_w2_php_files($ROOT);
    $map   = repair01_w2_include_map($files);
    $bear  = repair01_w2_shell_bearers($map);
    $out = array();
    foreach ($files as $rel => $clean) {
        if (!isset($bear[$rel]) || $bear[$rel] === 0) { continue; }   /* لا قشرةَ، أو هو القشرة */
        if (strpos($rel, 'includes/') === 0) { continue; }            /* عُدّةٌ لا شاشة */
        /* غلافٌ مشترَكٌ: يحمل القشرةَ ويُضمَّن من شاشاتٍ أخرى — لا يُقاس شاشةً */
        $isShell = false;
        foreach ($map as $other => $incs) {
            if ($other === $rel) { continue; }
            if (isset($incs[$rel])) { $isShell = true; break; }
        }
        if ($isShell) { continue; }
        $out[$rel] = $clean;
    }
    return $out;
}

/**
 * حارسُ العرضِ على الخادمِ لشاشةٍ واحدة — **صنفُه ودليلُه البنيويّ**.
 *
 * خمسةُ أصناف:
 *   `SELF_EARLY`   الشاشةُ تنادي الحارسَ بنفسِها قبلَ أيِّ كتابة.
 *   `SHARED_SHELL` تُصيَّر بغلافٍ يحمل القشرةَ (والكتابةُ بعده).
 *   `SHELL`        تُضمِّن `insidebar.php` — ومنفِّذُ الحارسِ فيه — ولا كتابةَ قبله.
 *   `REDIRECT`     مُحوِّلٌ لا يكشف بيانًا — الحارسُ في الوجهةِ لا هنا.
 *   `NONE`         **الدَّين**: كتابةٌ تسبق الحارسَ، أو لا حارسَ أصلًا.
 *                  «إخفاءُ الرابطِ لا يُغلق المسارَ المباشر» — والكتابةُ قبلَ
 *                  الحارسِ تعني أنّ الأثرَ يُرحَّل ثمّ يُقال «لا صلاحية».
 *
 * @param string $clean الشيفرةُ **بعد** `repair01_w2_strip_comments`
 * @param array  $incs  ما يُضمِّنه هذا الملفُّ (مُخرَجُ `repair01_w2_include_map`)
 * @param array  $bear  الملفّاتُ الحاملةُ للقشرة
 * @return array{kind:string, evidence:string}
 */
function repair01_w2_guard($rel, $clean, $incs = array(), $bear = array())
{
    $GUARD_FN = repair01_w2_shell_guard_fn();
    $selfPos = PHP_INT_MAX; $selfEv = '';
    $selfRx = array(
        $GUARD_FN                        => '~\b' . $GUARD_FN . '\s*\(~',
        'ems_require_governance_screen'  => '~\bems_require_governance_screen\s*\(~',
        'check_page_permissions'         => '~\bcheck_page_permissions\s*\(~',
    );
    foreach ($selfRx as $name => $rx) {
        if (preg_match($rx, $clean, $m, PREG_OFFSET_CAPTURE) && $m[0][1] < $selfPos) {
            $selfPos = $m[0][1]; $selfEv = $name . '()';
        }
    }

    /* موضعُ أوّلِ تضمينٍ لملفٍّ حاملٍ للقشرة — هو موضعُ الحارسِ الموروث */
    $shellPos = PHP_INT_MAX; $shellEv = ''; $shellDepth = 0;
    foreach ($incs as $t => $pos) {
        if (!isset($bear[$t])) { continue; }
        if ($pos < $shellPos) { $shellPos = $pos; $shellEv = $t; $shellDepth = $bear[$t]; }
    }

    $guardPos = min($selfPos, $shellPos);
    if ($guardPos === PHP_INT_MAX) {
        if (preg_match('~ems_route_redirect\s*\(|header\s*\(\s*[\'"]\s*Location\s*:~i', $clean)) {
            return array('kind' => 'REDIRECT', 'evidence' => 'مُحوِّلٌ — الحارسُ في الوجهة');
        }
        return array('kind' => 'NONE', 'evidence' => 'لا حارسَ ولا قشرةَ تحمله');
    }

    /* كتابةٌ تسبق الحارسَ — بعدَ نزعِ التعليقاتِ حصرًا */
    if (preg_match_all('~\b(?:INSERT\s+(?:IGNORE\s+)?INTO|UPDATE\s+`?[A-Za-z_]|DELETE\s+FROM)\b~i',
                       $clean, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[0] as $hit) {
            if ($hit[1] < $guardPos) {
                return array('kind' => 'NONE',
                             'evidence' => 'كتابةٌ عند ' . $hit[1] . ' تسبق الحارسَ عند ' . $guardPos);
            }
        }
    }

    if ($selfPos === $guardPos)  { return array('kind' => 'SELF_EARLY',   'evidence' => $selfEv); }
    if ($shellDepth > 1)         { return array('kind' => 'SHARED_SHELL', 'evidence' => $shellEv . ' ⇐ ' . repair01_w2_shell_root()); }
    return array('kind' => 'SHELL', 'evidence' => $shellEv . ' ⇐ ' . $GUARD_FN . '()');
}

/**
 * البندُ اليدويُّ في القشرة — **وسمُ رابطٍ مكتوبٌ بيدٍ داخلَ الشيفرة**.
 *
 * ◆ **المِرساةُ مسارٌ حرفيٌّ داخلَ `href` لا وسمُ `<li>`**: البندُ يُكتب في
 *   هذا المستودعِ بصيغتَين — وسمًا متّصلًا (`'<li><a href="../x.php">'`)
 *   وسلسلةً موصولةً (`'<li>' . '<a href="../x.php"'`). والرسوُّ على `<li>`
 *   يرى الأولى ويعمى عن الثانية. **والمسارُ الحرفيُّ هو الدَّينُ نفسُه**: هو
 *   ما يُحرَّر يدويًّا حين يتغيَّر البند.
 * ◆ **والمُصيِّرُ المُعمَّمُ ليس بندًا**: `href="' . $basePrefix . $code . '"`
 *   لا مسارَ حرفيًّا فيه — يقرأ من السجلِّ ويطبع. فهو الحلُّ لا العطب.
 * ◆ التعليقاتُ **تُنزَع أوّلًا** — تعليقاتُ PHP بالمُحلِّلِ وتعليقاتُ HTML بعده:
 *   في `insidebar.php` ملكيّةٌ موروثةٌ ميتةٌ داخلَ `<!-- … -->` يطبعها PHP ولا
 *   يعرضها المتصفح، فعدٌّ على الخامِّ يرى ما لا يراه المستخدم.
 *
 * @return array<string,array<int,string>> الملفُّ ⇐ المساراتُ الحرفيّةُ فيه
 */
function repair01_w2_manual_nav_items($ROOT)
{
    $ROOT = rtrim(str_replace('\\', '/', $ROOT), '/');
    $out = array();
    foreach (repair01_w2_shell_files() as $rel) {
        $p = $ROOT . '/' . $rel;
        if (!is_file($p)) { continue; }
        $clean = repair01_w2_strip_comments((string) file_get_contents($p));
        /* تعليقاتُ HTML لا يعرفها مُحلِّلُ PHP — تُنزع بعدَه */
        $clean = preg_replace('~<!--.*?-->~s', '', $clean);
        if (preg_match_all('~href\s*=\s*["\'][^>]{0,200}?([A-Za-z0-9_]+\.php)~i', $clean, $m)) {
            $out[$rel] = $m[1];
        }
    }
    return $out;
}

/**
 * موجةُ الرمزِ المعياريّ — **القاعدةُ نفسُها التي أسندت المتطلَّباتِ**
 * (`tools/repair01_stage_assign.php`) مترجَمةً إلى الرموزِ المعيارية.
 * ولا موجةَ تُخترع هنا: الوحدةُ ⇐ المرحلةُ كما في W00، والرمزُ جسرُها.
 * @return string 'W03'..'W14' أو '' لما لا قاعدةَ له
 */
function repair01_w2_wave_for_code($code)
{
    static $map = array(
        'DEP-11' => 'W04', 'DEP-12' => 'W04',
        'DEP-04' => 'W05', 'DEP-13' => 'W05',
        'DEP-14' => 'W06', 'DEP-15' => 'W06',
        'DEP-01' => 'W07', 'DEP-02' => 'W07',
        'DEP-16' => 'W08', 'DEP-17' => 'W08',
        'DEP-05' => 'W10', 'DEP-06' => 'W10',
        'DEP-03' => 'W11',
        'DEP-07' => 'W12', 'DEP-10' => 'W12',
        'DEP-08' => 'W13', 'DEP-09' => 'W13', 'IAF' => 'W13',
        'EX-CEO' => 'W14', 'EX-DVP' => 'W14', 'WS-MY' => 'W14',
    );
    $code = trim((string) $code);
    return isset($map[$code]) ? $map[$code] : '';
}

/** المسارُ المعياريُّ: شرطاتٌ أماميةٌ · بلا استعلامٍ ولا مِرساة. */
function repair01_w2_norm_route($p)
{
    $p = str_replace('\\', '/', trim((string) $p));
    $p = preg_replace('~[?#].*$~', '', $p);
    return ltrim($p, '/');
}
