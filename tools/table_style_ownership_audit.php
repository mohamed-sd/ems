<?php
/**
 * tools/table_style_ownership_audit.php — مَن يملكُ تصميمَ الجداولِ فعلًا؟
 * ═══════════════════════════════════════════════════════════════════════════
 * السؤال: هل كلُّ جداولِ النظامِ تحت التصميمِ الموحَّد، أم بقيت جداولُ تُصمَّم
 * من ملفاتٍ أخرى أو من كتلِ <style> داخلَ الشاشات؟
 *
 * والجوابُ يحتاج تمييزَ ثلاثةِ أشياءَ يخلطُها العدُّ الساذج:
 *
 *   ① **الهويّةُ** (لون · خلفية · لونُ حدٍّ · وزنُ خطٍّ · حجمُه) — هذه يجب أن
 *      تُقرأ من لوحةِ مفاتيحِ ems-tables.css. كتابتُها صريحةً خروجٌ عن التوحيد.
 *   ② **الهندسةُ** (حشوة · عرض · محاذاة · تمرير) — شاشيّةٌ بطبعِها: جدولُ
 *      الفحوصِ اليوميّةِ أضيقُ من جدولِ العقود، وتوحيدُها قسرًا إفقارٌ لا توحيد.
 *   ③ **قاعدةٌ تقرأ من المفاتيح** — وإن كانت في ملفٍّ آخرَ فهي **ملتزمة**:
 *      المكانُ الواحدُ هو مكانُ **القيمة** لا مكانُ القاعدة.
 *
 * فالمقياسُ هنا: كم قاعدةً تمسُّ **هويّةَ** جدولٍ **وتكتبُ قيمتَها صريحةً**
 * خارجَ ems-tables.css — ومَن يفوزُ منها فعلًا.
 *
 * الاستعمال:  php tools/table_style_ownership_audit.php [--rules]
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$showRules = in_array('--rules', $argv, true);
$SKIP = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database', 'tools', 'tests');

/** خصائصُ الهويّةِ البصريّة — ما يجب أن يأتيَ من اللوحة. */
$IDENTITY = array('color', 'background', 'background-color', 'background-image',
                  'border', 'border-color', 'border-top', 'border-bottom',
                  'border-top-color', 'border-bottom-color', 'border-left-color',
                  'border-right-color', 'font-weight', 'font-size', 'border-radius');

/** محدِّداتٌ تحوي كلمةَ جدولٍ لكنها ليست خليّةً ولا صفًّا ولا الجدولَ نفسَه. */
$NOT_A_TABLE = array(
    '.card', '.box', '.panel', '.table-container', '.table-responsive',
    '.table-section', '.table-wrap', '.table-responsive-wrapper',
    'badge', 'chip', 'btn', 'button', 'icon', 'pill',
    '.dt-button', '.dataTables_filter', '.dataTables_length', '.dataTables_info',
    '.dataTables_paginate', '.dataTables_wrapper .', '.ems-xscroll',
    '.action-btn', '.delete-btn', '.edit-btn', '.eye-btn', '.mnt-seg-btn',
    '.portable', '.notable', '.tablet', '.contracts-table-filter-wrap',
    '.contracts-group-toolbar', '.ems-view-picker', '.ems-colvis', '.ems-more',
);

function ts_strip_comments($css) { return preg_replace('~/\*.*?\*/~s', ' ', $css); }

/** هل المحدِّدُ يمسُّ الجدولَ نفسَه أو صفًّا أو خليّة؟ */
function ts_targets_table($sel, $NOT_A_TABLE) {
    $s = strtolower($sel);
    foreach ($NOT_A_TABLE as $n) if (strpos($s, strtolower($n)) !== false) return false;
    /* `~` مهروبٌ داخلَ الفئةِ لأنه فاصلُ النمطِ نفسِه — والاختبارُ الذاتيُّ أدناه
       هو ما كشف هذا؛ بلا فحصٍ ذاتيٍّ كانت الدالّةُ سترجع false صامتةً فيُعلَن
       «صفرُ مخالفة» كذبًا. */
    return (bool) preg_match('~(^|[\s>+\~,(\.\#])(table|thead|tbody|tfoot|tr|th|td|datatable|alltable|alltables|modern-table)([\s>+\~,)\.\:\[]|$)~i', $sel);
}

/** هل الإعلانُ يقرأ من لوحةِ المفاتيح؟ */
function ts_reads_token($val) { return (bool) preg_match('~var\(\s*--table-~i', $val); }

/** هل يكتبُ قيمةً صريحةً (لا يقرأ من اللوحة)؟ */
function ts_writes_raw($val) {
    $v = preg_replace('~var\(\s*--table-[a-z0-9-]+[^)]*\)~i', 'TOKEN', $val);
    if (preg_match('~\#[0-9a-f]{3,8}\b~i', $v)) return true;
    if (preg_match('~\b(rgb|rgba|hsl|hsla)\s*\(~i', $v)) return true;
    if (preg_match('~\bvar\(\s*--(?!table-)~i', $v)) return true;
    if (preg_match('~\b(red|blue|green|black|white|gray|grey|orange|yellow|navy|transparent|inherit|currentColor)\b~i', $v)) return true;
    if (preg_match('~^\s*\d~', $v)) return true;      /* أوزانٌ وأحجامٌ وسُمكُ حدود */
    if (preg_match('~\b(bold|bolder|normal|lighter)\b~i', $v)) return true;
    return false;
}

/* ── الاختبارُ الذاتيّ ── */
$st = array(
    'tgt+'  => ts_targets_table('body.ems-site table tbody td', $NOT_A_TABLE) === true,
    'tgt++' => ts_targets_table('.modern-table thead th', $NOT_A_TABLE) === true,
    'tgt-'  => ts_targets_table('.table-container', $NOT_A_TABLE) === false,
    'tgt--' => ts_targets_table('.dt-button:hover', $NOT_A_TABLE) === false,
    'tgt---'=> ts_targets_table('.sidebar .nav-link', $NOT_A_TABLE) === false,
    'tok+'  => ts_reads_token('var(--table-text, #161616)') === true,
    'tok-'  => ts_reads_token('var(--c-161616)') === false,
    'raw+'  => ts_writes_raw('#161616') === true,
    'raw++' => ts_writes_raw('var(--c-eeeeee)') === true,
    'raw+++'=> ts_writes_raw('700') === true,
    'raw-'  => ts_writes_raw('var(--table-text, #161616)') === false,
);
$bad = array(); foreach ($st as $k => $v) if (!$v) $bad[] = $k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: ' . implode(',', $bad) . PHP_EOL; exit(1); }
echo 'فحصُ الفاحص: ' . count($st) . ' حالةً — كلُّها سليمة.' . PHP_EOL . PHP_EOL;

/* ── المسح ── */
$sources = array();     // ملف → array(identityRaw, identityToken, geometry)
$rawList = array();

function ts_scan($label, $css, &$sources, &$rawList, $IDENTITY, $NOT_A_TABLE, $isSource) {
    $css = ts_strip_comments($css);
    if (!preg_match_all('~([^{}]+)\{([^}]*)\}~s', $css, $mm, PREG_SET_ORDER)) return;
    foreach ($mm as $r) {
        $sel = trim(preg_replace('~\s+~', ' ', $r[1]));
        if ($sel === '' || $sel[0] === '@') continue;
        if (!ts_targets_table($sel, $NOT_A_TABLE)) continue;
        foreach (explode(';', $r[2]) as $decl) {
            if (strpos($decl, ':') === false) continue;
            list($p, $v) = explode(':', $decl, 2);
            $p = strtolower(trim($p));
            $v = trim($v);
            if ($p === '' || $v === '') continue;
            if (!isset($sources[$label])) $sources[$label] = array('raw' => 0, 'token' => 0, 'geom' => 0, 'wins' => 0);
            if (!in_array($p, $IDENTITY, true)) { $sources[$label]['geom']++; continue; }
            if (ts_reads_token($v))            { $sources[$label]['token']++; continue; }
            if (ts_writes_raw($v)) {
                $sources[$label]['raw']++;
                /* ── أيستطيعُ هذا الإعلانُ الفوزَ على ems-tables.css؟ ──────────
                   قواعدُ المصدرِ تحمل `!important`، فلا يغلبها إعلانٌ عاديّ مهما
                   تأخّر أو دقّ محدِّدُه. ولا يغلبها إلا:
                     • إعلانٌ `!important` بمحدِّدٍ أخصَّ أو متأخّرٍ عنها، أو
                     • `!important` مع مُعرِّفٍ (#id) يرفع الأسبقيّة.
                   فما دونهما **بقيّةٌ ميّتة**: موجودةٌ في الملفِّ ولا أثرَ لها
                   على ما يراه المستخدم. والتمييزُ لازمٌ وإلا أُعلن رقمٌ مفزعٌ
                   لا يقابله عطلٌ واحدٌ على الشاشة. */
                /* ⚠ تصحيحٌ بعدَ مراجعة: `#id` **وحدَه لا يكفي**. في تتالي CSS
                   يهزم `!important` كلَّ إعلانٍ عاديٍّ مهما بلغت خصوصيّتُه —
                   والمُعرِّفُ يرجّح بين إعلانَين متساويَين في الأهميّة لا أكثر.
                   وقواعدُ ems-tables.css على الهويّةِ كلُّها `!important`، فما
                   ليس `!important` خارجَها **لا يفوز**. وحسبانُ `#id` مُنازِعًا
                   ضخّم العددَ من ٥ إلى ٨ ثلاثةً منها لا أثرَ لها. */
                $imp   = stripos($v, '!important') !== false;
                $hasId = strpos($sel, '#') !== false;
                if ($imp) {
                    $sources[$label]['wins']++;
                    if (!$isSource) $rawList[] = array($label, substr($sel, 0, 52),
                        $p . ': ' . substr($v, 0, 30) . '  [!]' . ($hasId ? '[#]' : ''));
                }
            } else { $sources[$label]['geom']++; }
        }
    }
}

foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    $b = basename($cf);
    if (strpos($b, '.min.css') !== false) continue;
    ts_scan('assets/css/' . $b, file_get_contents($cf), $sources, $rawList, $IDENTITY, $NOT_A_TABLE, strpos($b, 'ems-tables') !== false);
}

$inlineScreens = array(); $inlineAttr = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $src = file_get_contents($p);
    if (preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $src, $sm)) {
        $before = isset($sources['<style> ' . $rel]) ? $sources['<style> ' . $rel]['raw'] : 0;
        foreach ($sm[1] as $blk) ts_scan('<style> ' . $rel, $blk, $sources, $rawList, $IDENTITY, $NOT_A_TABLE, false);
        $after = isset($sources['<style> ' . $rel]) ? $sources['<style> ' . $rel]['raw'] : 0;
        if ($after > $before) $inlineScreens[$rel] = $after - $before;
    }
    if (preg_match_all('~<t[dhr]\b[^>]*style\s*=\s*"([^"]*)"~i', $src, $am)) {
        foreach ($am[1] as $stv) {
            foreach (explode(';', $stv) as $d) {
                if (strpos($d, ':') === false) continue;
                list($pp, $vv) = explode(':', $d, 2);
                if (in_array(strtolower(trim($pp)), $IDENTITY, true)) {
                    $inlineAttr[$rel] = (isset($inlineAttr[$rel]) ? $inlineAttr[$rel] : 0) + 1;
                }
            }
        }
    }
}

/* ── العرض ── */
uasort($sources, function ($a, $b) { return $b['raw'] - $a['raw']; });

echo '══════════ مَن يُصمِّمُ الجداولَ — بحسبِ المصدر ══════════' . PHP_EOL;
echo '  (هويّةٌ صريحة = خروجٌ عن التوحيد · هويّةٌ من اللوحة = التزام · هندسة = شأنٌ شاشيّ)' . PHP_EOL . PHP_EOL;
printf("  %-40s %7s %7s %7s %7s%s", 'المصدر', 'صريحة', 'تفوز', 'اللوحة', 'هندسة', PHP_EOL);
echo '  ' . str_repeat('─', 70) . PHP_EOL;
$tr = 0; $tt = 0; $tg = 0; $tw = 0;
foreach ($sources as $k => $v) {
    if ($v['raw'] + $v['token'] === 0 && $v['geom'] < 3) continue;
    printf("  %-40s %7d %7d %7d %7d%s", (mb_strlen($k) > 40 ? '…' . mb_substr($k, -39) : $k), $v['raw'], $v['wins'], $v['token'], $v['geom'], PHP_EOL);
    $tr += $v['raw']; $tt += $v['token']; $tg += $v['geom']; $tw += isset($v['wins'])?$v['wins']:0;
}
echo '  ' . str_repeat('─', 70) . PHP_EOL;
printf("  %-40s %7d %7d %7d %7d%s", 'المجموع', $tr, $tw, $tt, $tg, PHP_EOL);

$srcRaw  = isset($sources['assets/css/ems-tables.css']) ? $sources['assets/css/ems-tables.css']['raw'] : 0;
$srcWins = isset($sources['assets/css/ems-tables.css']) ? $sources['assets/css/ems-tables.css']['wins'] : 0;
$outsideRaw = $tr - $srcRaw;
$outsideWins = $tw - $srcWins;

echo PHP_EOL . '══════════ الحصيلة ══════════' . PHP_EOL;
echo 'إعلاناتُ هويّةٍ صريحةٍ **خارجَ** ems-tables.css ...... ' . $outsideRaw . PHP_EOL;
echo '   منها ما **يستطيع الفوزَ** فعلًا (!important أو #id) ... ' . $outsideWins
   . ($outsideWins === 0 ? '   ← الباقي بقيّةٌ ميّتةٌ لا أثرَ لها' : '   ← تحتاج معالجة') . PHP_EOL;
echo 'إعلاناتُ هويّةٍ تقرأ من اللوحة ..................... ' . $tt . PHP_EOL;
echo 'شاشاتٌ فيها <style> يكتب هويّةَ جدولٍ صريحةً ........ ' . count($inlineScreens) . PHP_EOL;
echo 'شاشاتٌ فيها style="" على خليّةٍ يكتب هويّة ......... ' . count($inlineAttr)
   . ' (' . array_sum($inlineAttr) . ' خليّة)' . PHP_EOL;

if (count($inlineScreens)) {
    echo PHP_EOL . '── شاشاتٌ بكتلِ <style> تُلوّنُ جداولَها ──' . PHP_EOL;
    arsort($inlineScreens);
    foreach ($inlineScreens as $s => $n) echo '   • ' . $s . '   (' . $n . ' إعلانًا)' . PHP_EOL;
}
if (count($inlineAttr)) {
    echo PHP_EOL . '── شاشاتٌ بخلايا style="" ──' . PHP_EOL;
    arsort($inlineAttr);
    foreach ($inlineAttr as $s => $n) echo '   • ' . $s . '   (' . $n . ' خليّة)' . PHP_EOL;
}
if ($showRules && count($rawList)) {
    echo PHP_EOL . '── تفصيلُ الإعلاناتِ الصريحةِ خارجَ المصدر (أول 60) ──' . PHP_EOL;
    foreach (array_slice($rawList, 0, 60) as $x) {
        echo '   ✘ ' . str_pad($x[0], 34) . ' ⟨' . $x[1] . '⟩  ' . $x[2] . PHP_EOL;
    }
    if (count($rawList) > 60) echo '   … و' . (count($rawList) - 60) . ' غيرُها' . PHP_EOL;
}
if (!$showRules) echo PHP_EOL . '(أضف --rules للتفصيل)' . PHP_EOL;
