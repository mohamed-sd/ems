<?php
/**
 * tests/table_design_unification_test.php
 * حارسُ توحيدِ تصميمِ الجداول.                                (توحيد 2026-08-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكمُ الذي يحرسه:
 *   «تغييرُ قيمةٍ واحدةٍ في لوحةِ مفاتيحِ ems-tables.css يُغيّرُ تصميمَ الجداولِ
 *    في كلِّ شاشةٍ فيها وسمُ <table> أو DataTable — بلا استثناء.»
 *
 * وهذا الحكمُ ينكسرُ بأربعِ طرقٍ، فلكلٍّ بوابة:
 *   G1  شاشةٌ فيها جدولٌ لا يصلها ملفُّ التصميم          → لا شيءَ يحكمها
 *   G2  ملفٌّ آخرُ يكتب لونَ جدولٍ صريحًا بـ!important/#id → يغلبُ المصدرَ الواحد
 *   G3  ورقةٌ تُحمَّل بعدَ ems-tables.css وتمسُّ جدولًا      → الترتيبُ يهزم الحكم
 *   G4  مفتاحٌ ناقصٌ أو موضعٌ يقرأ من غيرِ المفاتيح        → المِقبضُ لا يصلُ للقاعدة
 *
 * ⚠ كلُّ فاحصٍ هنا مُجرَّبٌ إيجابيًّا **وسلبيًّا** في selftest() قبلَ تصديقِ
 *   نتيجته. بوابةٌ يستحيلُ إفشالُها خضراءُ أبدًا ولا تحرسُ شيئًا.
 *
 * الاستعمال:  php tests/table_design_unification_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$SKIP = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database', 'tools', 'tests', 'scripts', 'install');

/* المفاتيحُ الواجبُ وجودُها في لوحةِ ems-tables.css */
$REQUIRED_TOKENS = array(
    '--table-text', '--table-surface', '--table-row-odd-bg', '--table-row-even-bg',
    '--table-row-hover-bg', '--table-header-text', '--table-header-bg',
    '--table-header-scroll-bg', '--table-border', '--table-border-soft', '--table-radius',
);

/**
 * قائمةُ الإعفاءِ المُعلَنة — محدِّداتٌ تحوي كلمةَ «table» لكنها ليست تصميمَ
 * خليةِ جدول. كلُّ سطرٍ هنا قرارٌ مكتوبٌ لا صمتٌ، وإضافةُ سطرٍ إليه تظهر في
 * الفرقِ فتُراجَع.
 */
$ALLOW_SUBSTR = array(
    '.card', '.box', '.panel',              // حاويات صُودفت بـ.table-container
    '.table-container', '.table-responsive', '.table-section', '.table-wrap',
    '.mnt-seg-btn',                         // زرٌّ داخلَ جدول، لا خلية
    'badge', 'chip', '.price-chip',         // شارات
    '.dt-button', '.dataTables_filter', '.dataTables_length',
    '.dataTables_paginate', '.dataTables_info', '.dataTables_wrapper .',
    '.action-btn', '.delete-btn', '.edit-btn', '.eye-btn',
    '.portable', '.notable', '.tablet',
    '.contracts-table-filter-wrap', '.contracts-group-toolbar',  // شريطُ أدواتٍ فوقَ الجدول لا خليةٌ فيه
);

/* ─────────────────────── كاشفاتٌ بلا شرطاتٍ حرفيّة ─────────────────────── */

function td_has_table_tag($s) {
    $off = 0;
    while (($i = stripos($s, '<table', $off)) !== false) {
        $c = isset($s[$i + 6]) ? $s[$i + 6] : ' ';
        if ($c === '>' || $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") return true;
        $off = $i + 6;
    }
    return false;
}

function td_has_datatables($s) {
    foreach (array('.DataTable(', '.dataTable(', 'dataTables_wrapper',
                   'jquery.dataTables', 'datatable_server') as $n) {
        if (stripos($s, $n) !== false) return true;
    }
    return false;
}

/**
 * مساراتُ التضمين.
 * تشمل ثلاثَ صيغٍ حيّةٍ في هذا المستودع:
 *   ① `include '../inheader.php'`                     — حرفيّةٌ مباشرة
 *   ② `include $u13Root . '/inheader.php'`             — بادئةٌ متغيّرة
 *   ③ `$__inc = __DIR__ . '/../inheader.php'; include $__inc;`  — بالإسنادِ ثم التضمين
 * والثالثةُ لازمةٌ: `includes/eng01_screen_view.php` يستعملها، وبدونها يُحسَب
 * يتيمًا وهو ليس كذلك — وبوابةٌ تتَّهم بريئًا تُفقِدُ الثقةَ كما تُفقِدُها بوابةٌ
 * تُبرِّئ مذنبًا. والحلُّ **إسنادٌ مقيَّد**: لا تُقبل إلا القيمُ المُسنَدةُ إلى
 * متغيّرٍ يُضمَّن فعلًا في الملفِّ نفسِه، فلا يتحوّل الفاحصُ إلى تقريبٍ متساهل.
 */
function td_includes($s) {
    $out = array();
    $re = '#(?:include|require)(?:_once)?\s*\(?\s*'
        . '(?:__DIR__|\$[A-Za-z_][A-Za-z0-9_]*|dirname\s*\([^)]*\))?\s*\.?\s*'
        . '["\']([^"\']+\.php)["\']#i';
    if (preg_match_all($re, $s, $m)) foreach ($m[1] as $t) $out[] = $t;

    /* ③ المتغيّراتُ المُضمَّنةُ بلا نصٍّ في جملةِ التضمين */
    if (preg_match_all('#(?:include|require)(?:_once)?\s*\(?\s*(\$[A-Za-z_][A-Za-z0-9_]*)\s*\)?\s*;#i', $s, $mv)) {
        foreach (array_unique($mv[1]) as $var) {
            $reAssign = '#' . preg_quote($var, '#') . '\s*=\s*[^;]*?["\']([^"\']+\.php)["\']#';
            if (preg_match_all($reAssign, $s, $ma)) foreach ($ma[1] as $t) $out[] = $t;
        }
    }
    return $out;
}

/** نزعُ تعليقاتِ CSS — وإلا ابتلع التعليقُ ما قبلَ `{` فصار جزءًا من المحدِّد. */
function td_strip_css_comments($css) {
    return preg_replace('#/\*.*?\*/#s', ' ', $css);
}

/** هل القيمةُ لونٌ صريحٌ مكتوبٌ باليد (لا قراءةٌ من مفتاح)؟ */
function td_is_raw_color($decl) {
    /* كلُّ var(--table-…) قراءةٌ سليمة — حتى لو كان لها احتياطٌ صريح. */
    $stripped = preg_replace('#var\(\s*--table-[a-z0-9-]+[^)]*\)#i', 'TOKEN', $decl);
    if (preg_match('#\#[0-9a-f]{3,8}\b#i', $stripped)) return true;
    if (preg_match('#\b(rgb|rgba|hsl|hsla)\s*\(#i', $stripped)) return true;
    if (preg_match('#\bvar\(\s*--(?!table-)#i', $stripped)) return true;   // مفتاحٌ من غيرِ اللوحة
    if (preg_match('#:\s*(red|blue|green|black|white|gray|grey|orange|yellow|navy|silver)\b#i', $stripped)) return true;
    return false;
}

/* ─────────────────────────── اختبارُ الفاحصِ نفسِه ────────────────────────── */

function td_selftest() {
    $bad = array();
    $cases = array(
        'tag+'   => td_has_table_tag('<table class="x">') === true,
        'tag++'  => td_has_table_tag('<table>') === true,
        'tag-'   => td_has_table_tag('<tablet>') === false,
        'tag--'  => td_has_table_tag('portable device') === false,
        'dt+'    => td_has_datatables('$("#t").DataTable({})') === true,
        'dt-'    => td_has_datatables('nothing here') === false,
        'inc+'   => td_includes("include '../inheader.php';") === array('../inheader.php'),
        'inc++'  => td_includes('include $u13Root . "/inheader.php";') === array('/inheader.php'),
        'inc+++' => td_includes('require_once dirname(__DIR__,2) . "/includes/x.php";') === array('/includes/x.php'),
        'inc④'   => td_includes('$__i = __DIR__ . "/../inheader.php"; include $__i;') === array('/../inheader.php'),
        'inc-'   => td_includes('$x = "not_an_include.php";') === array(),
        'cmt+'   => trim(td_strip_css_comments('/* table */ .a { color:red }')) === '.a { color:red }',
        'cmt-'   => td_strip_css_comments('.a { color:red }') === '.a { color:red }',
        'raw+'   => td_is_raw_color('color: #161616') === true,
        'raw++'  => td_is_raw_color('background: rgba(1,2,3,.5)') === true,
        'raw+++' => td_is_raw_color('color: var(--c-161616)') === true,
        'raw-'   => td_is_raw_color('color: var(--table-text, #161616)') === false,
        'raw--'  => td_is_raw_color('padding: 9px 12px') === false,
    );
    foreach ($cases as $k => $v) if (!$v) $bad[] = $k;
    return $bad;
}

$bad = td_selftest();
echo '── فحصُ الفاحصِ (' . count(td_selftest()) . ' فشلًا من ' . (15 + 3) . ' حالة) ──' . PHP_EOL;
if (count($bad)) {
    echo 'الفاحصُ غيرُ موثوق — الحالاتُ الفاشلة: ' . implode(', ', $bad) . PHP_EOL;
    exit(1);
}
echo '  كلُّ حالاتِ الاختبارِ الذاتيِّ سليمة.' . PHP_EOL . PHP_EOL;

/* ───────────────────────────── المسحُ ───────────────────────────── */

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ext = strtolower($f->getExtension());
    if ($ext !== 'php' && $ext !== 'css') continue;
    $p   = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $files[$rel] = $p;
}

$info = array();
foreach ($files as $rel => $abs) {
    if (substr($rel, -4) !== '.php') continue;
    $s = file_get_contents($abs);
    $info[$rel] = array(
        'table' => td_has_table_tag($s),
        'dt'    => td_has_datatables($s),
        'link'  => (stripos($s, 'ems-tables.css') !== false
                 || stripos($s, 'table_design.php') !== false),
        'incs'  => td_includes($s),
        'res'   => array(),
    );
}
function td_resolve($fromRel, $target, $files) {
    $target = preg_replace('#^C:/wamp64/www/ems/#i', '', str_replace(chr(92), '/', $target));
    $base   = dirname($fromRel); if ($base === '.') $base = '';
    $cands  = array();
    if ($base !== '') $cands[] = $base . '/' . $target;
    $cands[] = ltrim($target, '/');
    foreach ($cands as $c) {
        $parts = array();
        foreach (explode('/', $c) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        if (isset($files[implode('/', $parts)])) return implode('/', $parts);
    }
    $bn = basename($target); $hit = null; $n = 0;
    foreach ($files as $r => $x) if (basename($r) === $bn) { $hit = $r; $n++; }
    return ($n === 1) ? $hit : null;
}
foreach ($info as $rel => $d) {
    $r = array();
    foreach ($d['incs'] as $t) { $x = td_resolve($rel, $t, $files); if ($x !== null) $r[$x] = true; }
    $info[$rel]['res'] = array_keys($r);
}
function td_reaches($rel, &$info, &$memo, $depth = 0) {
    if (array_key_exists($rel, $memo)) return $memo[$rel];
    if ($depth > 15 || !isset($info[$rel])) return false;
    $memo[$rel] = false;
    if ($info[$rel]['link']) { $memo[$rel] = true; return true; }
    foreach ($info[$rel]['res'] as $c) {
        if (td_reaches($c, $info, $memo, $depth + 1)) { $memo[$rel] = true; return true; }
    }
    return false;
}

$fail = 0; $lines = array();
function gate($ok, $title, $detail = '') {
    global $fail, $lines;
    if (!$ok) $fail++;
    $lines[] = '  ' . ($ok ? '✔' : '✘') . '  ' . $title . ($detail !== '' ? ('  — ' . $detail) : '');
    return $ok;
}

/* ── G1: كلُّ شاشةٍ فيها جدولٌ تصلها الورقة ── */
$orph = array();
foreach ($info as $rel => $d) {
    if (!$d['table'] && !$d['dt']) continue;
    $memo = array();
    if (td_reaches($rel, $info, $memo)) continue;
    /* جزءٌ مُضمَّنٌ داخلَ شاشةٍ تصل ليس يتيمًا */
    $hosted = false;
    foreach ($info as $p => $pd) {
        if (!in_array($rel, $pd['res'], true)) continue;
        $m2 = array();
        if (td_reaches($p, $info, $m2)) { $hosted = true; break; }
    }
    if (!$hosted) $orph[] = $rel;
}
gate(count($orph) === 0, 'G1 كلُّ شاشةٍ فيها جدولٌ يصلها ملفُّ التصميم',
     count($orph) ? (count($orph) . ' يتيمًا: ' . implode(', ', array_slice($orph, 0, 6))) : 'صفرُ يتيم');

/* ── G2: صفرُ لونٍ صريحٍ في قاعدةِ جدولٍ خارجَ ems-tables.css ── */
$RE_TRULE = '#([^{}]*\b(?:table|thead|tbody|tfoot|td|th|dataTable)\b[^{}]*)\{([^}]*)\}#is';
$RE_CDECL = '#(?:^|;)\s*(?:color|background|background-color|border|border-color|border-top|border-bottom)\s*:[^;]*#i';
$raws = array();
foreach ($files as $rel => $abs) {
    if (strpos($rel, 'ems-tables.css') !== false) continue;
    if (strpos($rel, '.min.css') !== false || strpos($rel, 'assets/vendor/') !== false) continue;
    $s = file_get_contents($abs);
    $blocks = array();
    if (substr($rel, -4) === '.css') $blocks[] = $s;
    else if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $s, $mm)) $blocks = $mm[1];
    foreach ($blocks as $css) {
        $css = td_strip_css_comments($css);   // وإلا صار التعليقُ جزءًا من المحدِّد
        if (!preg_match_all($RE_TRULE, $css, $rr, PREG_SET_ORDER)) continue;
        foreach ($rr as $r) {
            $sel = trim(preg_replace('#\s+#', ' ', $r[1]));
            $strong = (stripos($r[2], '!important') !== false) || (strpos($sel, '#') !== false);
            if (!$strong) continue;                       // الضعيفةُ يهزمها ems-tables تلقائيًّا
            $skip = false;
            foreach ($ALLOW_SUBSTR as $a) if (stripos($sel, $a) !== false) { $skip = true; break; }
            if ($skip) continue;
            if (!preg_match_all($RE_CDECL, $r[2], $dd)) continue;
            foreach ($dd[0] as $decl) {
                if (td_is_raw_color($decl)) {
                    $raws[] = $rel . '  ⟨' . substr($sel, 0, 46) . '⟩  ' . trim(str_replace(array("\n", ';'), ' ', $decl));
                }
            }
        }
    }
}
gate(count($raws) === 0, 'G2 صفرُ لونٍ صريحٍ قويٍّ في قاعدةِ جدولٍ خارجَ المصدر',
     count($raws) ? (count($raws) . ' موضعًا') : 'صفرُ موضع');

/* ── G3: ems-tables.css آخرُ ورقةٍ في كلِّ قشرة ── */
$shellFail = array();
foreach (array('inheader.php', 'insidebar.php', 'admin/includes/layout_head.php') as $shell) {
    $p = $ROOT . '/' . $shell;
    if (!is_file($p)) { $shellFail[] = $shell . ' (مفقود)'; continue; }
    $s = file_get_contents($p);
    $posT = max(strripos($s, 'ems-tables.css'), strripos($s, 'table_design.php'));
    if ($posT === false || $posT < 0) { $shellFail[] = $shell . ' (لا يحمل الورقة)'; continue; }
    if (preg_match_all('#[\'"]/ems/assets/css/([a-z0-9_.-]+\.css)#i', $s, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[0] as $k => $hit) {
            $name = $mm[1][$k][0];
            if (strpos($name, 'ems-tables') !== false) continue;
            if (strpos($name, 'mobile') !== false) continue;      // محبوسةٌ في @media ولا تمسُّ جدولًا
            if ($hit[1] > $posT) { $shellFail[] = $shell . ' → ' . $name . ' بعدَها'; }
        }
    }
}
gate(count($shellFail) === 0, 'G3 ورقةُ الجداولِ آخرُ ورقةٍ في كلِّ قشرة',
     count($shellFail) ? implode(' · ', array_slice($shellFail, 0, 4)) : 'ثلاثُ قشراتٍ سليمة');

/* ── G4: لوحةُ المفاتيحِ كاملةٌ وفريدة ── */
$tblCss = file_get_contents($ROOT . '/assets/css/ems-tables.css');
$missing = array(); $dup = array();
foreach ($REQUIRED_TOKENS as $t) {
    $n = preg_match_all('#^\s*' . preg_quote($t, '#') . '\s*:#mi', $tblCss);
    if ($n === 0) $missing[] = $t;
    if ($n > 1)  $dup[] = $t . '×' . $n;
}
gate(count($missing) === 0 && count($dup) === 0,
     'G4 لوحةُ المفاتيحِ كاملةٌ ولا مفتاحَ معرَّفٌ مرّتين',
     count($missing) ? ('ناقص: ' . implode(',', $missing)) : (count($dup) ? ('مكرَّر: ' . implode(',', $dup)) : count($REQUIRED_TOKENS) . ' مفتاحًا'));

/* ── G5: صفرُ قاعدةٍ جوهريّةٍ **داخلَ** ems-tables.css تكتبُ لونًا صريحًا ──
   الحكمُ السابقُ كان «وُجدت قاعدةٌ واحدةٌ موصولة» — وهو حكمٌ لا يُكسَر: فصلُ
   قاعدةٍ يترك أخواتِها موصولةً فتبقى البوابةُ خضراء. وقد أثبتَ الحزامُ السلبيُّ
   ذلك حرفيًّا. فقُلب الحكمُ إلى نفيٍ شامل: **لا قاعدةَ جوهريّةً مفصولة**. */
$coreRaw = array();
$tblNoCmt = td_strip_css_comments($tblCss);
/* تُستثنى كتلُ :root — فهي موضعُ تعريفِ القيمِ لا موضعُ استعمالِها */
$tblBody = preg_replace('#:root\s*\{[^}]*\}#is', '', $tblNoCmt);
if (preg_match_all($RE_TRULE, $tblBody, $rr5, PREG_SET_ORDER)) {
    foreach ($rr5 as $r) {
        $sel = trim(preg_replace('#\s+#', ' ', $r[1]));
        /* الجوهريُّ: قاعدةٌ تمسُّ خليةً أو ترويسةً أو صفًّا — لا زرًّا ولا شارة */
        if (!preg_match('#\b(thead|tbody|tfoot)\b#i', $sel) && !preg_match('#\btable\b#i', $sel)) continue;
        $skip = false;
        foreach ($ALLOW_SUBSTR as $a) if (stripos($sel, $a) !== false) { $skip = true; break; }
        if ($skip) continue;
        if (!preg_match_all($RE_CDECL, $r[2], $dd5)) continue;
        foreach ($dd5[0] as $decl) {
            if (td_is_raw_color($decl)) {
                $coreRaw[] = '⟨' . substr($sel, 0, 52) . '⟩  ' . trim(str_replace(array("\n", ';'), ' ', $decl));
            }
        }
    }
}
gate(count($coreRaw) === 0, 'G5 صفرُ قاعدةٍ جوهريّةٍ في المصدرِ تكتبُ لونًا صريحًا',
     count($coreRaw) ? (count($coreRaw) . ' قاعدةً مفصولة') : 'كلُّها تقرأ من اللوحة');

/* ── G6: الطبقةُ الكونيّةُ حاضرة (تغطّي ما لا يحمل ems-site) ── */
gate(strpos($tblCss, 'UNIVERSAL TABLE BASE') !== false
     && preg_match('#:where\(table[^)]*\)[^{]*\{#i', $tblCss) === 1
     || strpos($tblCss, 'UNIVERSAL TABLE BASE') !== false && substr_count($tblCss, ':where(table') >= 5,
     'G6 الطبقةُ الكونيّةُ حاضرةٌ (لا تشترط body.ems-site)',
     substr_count($tblCss, ':where(table') . ' قاعدةً غيرَ مشروطة');

/* ── G7: كلُّ غلافِ تمريرٍ يستضيف جدولًا يُصفِّر إزاحةَ الترويسةِ اللاصقة ──
   العلّةُ المقيسة (بلاغُ المالك · Contracts/contracts_details.php):
     `th` بـ`position:sticky; top: var(--ems-sticky-top)` والمتغيّرُ يضبطه
     ui-unification.js بارتفاعِ الشريطِ العلويِّ (56px). لكنّ مرجعَ اللصوقِ هو
     **أقربُ حاويةِ تمريرٍ** لا نافذةُ العرض — فإن كان الجدولُ داخلَ غلافٍ ذي
     `overflow:auto` قِيست الـ56 من أعلى الغلاف، فانفصلت خلايا الترويسةِ عن
     صفِّها **٤٣ بكسلًا** (قياسٌ حيّ): يبقى الصفُّ شريطًا داكنًا فارغًا وتطفو
     خلاياه فوقَ أوّلِ صفِّ بيانات.

   وui-unification.js نفسُه يُعلن أغلفةً يمتنع عن لفِّها بـ`.ems-xscroll`
   (`ensureHorizontalScroll`) — وتلك بالضبط هي التي تفقد التصفير. فالبوابةُ
   تربط الملفَّين: **كلُّ صنفٍ في قائمةِ امتناعِ الـJS يجب أن يظهر في قاعدةِ
   التصفيرِ في ems-tables.css.** وقد كان `.ems-xscroll` وحدَه مُصفَّرًا. */
$uiJs = @file_get_contents($ROOT . '/assets/js/ui-unification.js');
$skipClasses = array();
if ($uiJs !== false && preg_match('#closest\s*\(\s*[\'"]([^\'"]*table-responsive-wrapper[^\'"]*)[\'"]#i', $uiJs, $sm)) {
    foreach (explode(',', $sm[1]) as $c) {
        $c = trim($c);
        if ($c !== '') $skipClasses[] = ltrim($c, '.');
    }
}
$zeroBlock = '';
if (preg_match('#((?:[^{}]*--ems-sticky-top\s*:\s*0)[^}]*\})#s', $tblCss)) {
    /* كتلةُ التصفير: المحدِّداتُ التي تسبق `--ems-sticky-top: 0` */
    $parts = explode('--ems-sticky-top', $tblCss);
    foreach ($parts as $k => $seg) {
        if ($k === 0) continue;
        if (!preg_match('#^\s*:\s*0#', $seg)) continue;
        $before = $parts[$k - 1];
        $zeroBlock .= substr($before, max(0, strlen($before) - 500));
    }
}
$notZeroed = array();
foreach ($skipClasses as $c) if (strpos($zeroBlock, $c) === false) $notZeroed[] = $c;
gate(count($skipClasses) > 0 && count($notZeroed) === 0,
     'G7 كلُّ غلافِ تمريرٍ يستضيف جدولًا يُصفِّر إزاحةَ الترويسة',
     count($skipClasses) === 0 ? 'تعذّرت قراءةُ قائمةِ الامتناعِ من ui-unification.js'
        : (count($notZeroed) ? ('غيرُ مُصفَّر: ' . implode(', ', $notZeroed))
                             : (count($skipClasses) . ' غلافًا مُصفَّرًا')));

/* ───────────────────────────── الحصيلة ───────────────────────────── */
echo '══════════ بواباتُ توحيدِ تصميمِ الجداول ══════════' . PHP_EOL;
foreach ($lines as $l) echo $l . PHP_EOL;
echo PHP_EOL;
if (count($orph)) {
    echo '── تفصيلُ G1 (اليتامى) ──' . PHP_EOL;
    foreach ($orph as $o) echo '   ✘ ' . $o . PHP_EOL;
    echo PHP_EOL;
}
if (count($coreRaw)) {
    echo '── تفصيلُ G5 (قواعدُ المصدرِ المفصولة) ──' . PHP_EOL;
    foreach (array_slice($coreRaw, 0, 30) as $c) echo '   ✘ ' . $c . PHP_EOL;
    if (count($coreRaw) > 30) echo '   … و' . (count($coreRaw) - 30) . ' غيرُها' . PHP_EOL;
    echo PHP_EOL;
}
if (count($raws)) {
    echo '── تفصيلُ G2 (ألوانٌ صريحة) ──' . PHP_EOL;
    foreach (array_slice($raws, 0, 30) as $r) echo '   ✘ ' . $r . PHP_EOL;
    if (count($raws) > 30) echo '   … و' . (count($raws) - 30) . ' غيرُها' . PHP_EOL;
    echo PHP_EOL;
}
echo ($fail === 0 ? 'النتيجة: سبع بوابات من سبع — التوحيدُ قائم.'
                  : 'النتيجة: ' . (7 - $fail) . ' من 7 — ' . $fail . ' بوابةً مكسورة.') . PHP_EOL;
exit($fail === 0 ? 0 : 1);
