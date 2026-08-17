<?php
/**
 * tools/flat_gray_surfaces_audit.php — إحصاءُ «الطبقاتِ الرماديّةِ المسطّحة».
 * ═══════════════════════════════════════════════════════════════════════════
 * الظاهرةُ المقيسة: حاويةُ شاشةٍ (لافتةُ رأسٍ · قسمٌ · بطاقة …) خلفيتُها **رماديٌّ
 * مسطَّحٌ محايد** بينما نظائرُها في النظامِ تستعمل تدرّجًا دافئًا أو أبيضَ نقيًّا.
 *
 * البلاغُ الأصليّ: `Contracts/contracts_details.php` — «طبقةٌ من الرمادي لا أفهمها».
 * والقياسُ الحيُّ أظهر أن **الجداولَ نفسَها موحّدةٌ تمامًا** (ترويسة #6b6b6b ·
 * نصٌّ #161616)، وأن الرماديَّ هو `.contracts-details-page .page-hero` بـ#dddddd
 * على مساحةِ ١١٢١×٣٦٢ بكسل — لافتةُ الرأسِ لا الجدول.
 *
 * ولماذا هو خللٌ لا اختيار؟ لأن القاعدةَ نفسَها تحمل دائرتَين زخرفيّتَين
 * بشفافيّةِ `rgba(244,197,66,.14)` و`rgba(255,255,255,.04)` — وشفافيّةُ ٤٪ بيضاءُ
 * لا تُرى إلا فوقَ خلفيةٍ **داكنةٍ أو مشبعة**. فالزخرفةُ بقيت والخلفيّةُ سُطِّحت.
 *
 * ما يُعَدُّ رماديًّا مسطّحًا هنا:
 *   • لونٌ صلبٌ (لا تدرّج) قنواتُه متقاربةٌ (|R-G|,|G-B| ≤ 10)
 *   • وسطوعُه بين 176 و 240 — أغمقُ من «أبيضَ مائل» وأفتحُ من رمادٍ داكنٍ مقصود
 *   • على محدِّدٍ يمسُّ **حاويةَ شاشةٍ** لا مكوّنًا صغيرًا (زر · شارة · أيقونة)
 *
 * الاستعمال:  php tools/flat_gray_surfaces_audit.php [--screens]
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$showScreens = in_array('--screens', $argv, true);

/* محدِّداتُ الحاوياتِ — ما يشغل مساحةً واسعةً من الشاشة */
$CONTAINER_HINTS = array('hero', 'page-', 'section', 'card', 'panel', 'wrapper', 'wrap',
                         '-main', 'container', 'board', 'shell', 'block', 'tile', 'summary');
/* مكوّناتٌ صغيرةٌ تُستثنى ولو حملت تلميحَ حاوية */
$SMALL_PARTS = array('btn', 'button', 'badge', 'chip', 'icon', 'pill', 'dot', 'avatar',
                     'thumb', 'tag', 'label', 'input', 'select', 'checkbox', 'radio',
                     'scrollbar', '::-webkit', 'tooltip', 'caret', 'arrow', 'spinner');

/* ── ① خريطةُ الرموزِ اللونيّة ── */
$tokens = array();
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    if (strpos($cf, '.min.css') !== false) continue;
    $s = file_get_contents($cf);
    /* الفاصلُ `~` لا `#` — لأن النمطَ نفسَه يحوي `#` اللونِ فيُقرأ فاصلًا فينكسر. */
    if (preg_match_all('~(--[a-z0-9-]+)\s*:\s*(\#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))\s*;~i', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) if (!isset($tokens[strtolower($x[1])])) $tokens[strtolower($x[1])] = $x[2];
    }
}

function tc_resolve_color($v, $tokens, $depth = 0) {
    $v = trim($v);
    if ($depth > 5) return null;
    if (preg_match('#^var\(\s*(--[a-z0-9-]+)#i', $v, $m)) {
        $k = strtolower($m[1]);
        return isset($tokens[$k]) ? tc_resolve_color($tokens[$k], $tokens, $depth + 1) : null;
    }
    return $v;
}

function tc_to_rgb($c) {
    if ($c === null) return null;
    $c = trim($c);
    if (preg_match('#^\#([0-9a-f]{6})$#i', $c, $m)) {
        return array(hexdec(substr($m[1],0,2)), hexdec(substr($m[1],2,2)), hexdec(substr($m[1],4,2)), 1.0);
    }
    if (preg_match('#^\#([0-9a-f]{3})$#i', $c, $m)) {
        $h = $m[1];
        return array(hexdec($h[0].$h[0]), hexdec($h[1].$h[1]), hexdec($h[2].$h[2]), 1.0);
    }
    if (preg_match('#^rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)\s*(?:[,/]\s*([0-9.]+))?\s*\)$#i', $c, $m)) {
        return array((int)$m[1], (int)$m[2], (int)$m[3], isset($m[4]) ? (float)$m[4] : 1.0);
    }
    return null;
}

function tc_is_flat_gray($rgb) {
    if ($rgb === null) return false;
    list($r, $g, $b, $a) = $rgb;
    if ($a < 0.9) return false;                       // شبهُ شفافٍ ليس طبقةً
    if (abs($r - $g) > 10 || abs($g - $b) > 10) return false;
    return ($r >= 176 && $r <= 240);
}

/* ── الاختبارُ الذاتيّ ── */
$st = array(
    'gray+'  => tc_is_flat_gray(tc_to_rgb('#dddddd')) === true,
    'gray++' => tc_is_flat_gray(tc_to_rgb('rgb(221,221,221)')) === true,
    'white-' => tc_is_flat_gray(tc_to_rgb('#ffffff')) === false,
    'warm-'  => tc_is_flat_gray(tc_to_rgb('#fff7eb')) === false,
    'dark-'  => tc_is_flat_gray(tc_to_rgb('#333333')) === false,
    'alpha-' => tc_is_flat_gray(tc_to_rgb('rgba(221,221,221,.1)')) === false,
    'hex3+'  => tc_to_rgb('#ddd') === array(221, 221, 221, 1.0),
);
$bad = array();
foreach ($st as $k => $v) if (!$v) $bad[] = $k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: ' . implode(',', $bad) . PHP_EOL; exit(1); }
echo 'فحصُ الفاحص: ' . count($st) . ' حالةً — كلُّها سليمة.' . PHP_EOL . PHP_EOL;

/* ── ② المسحُ عن القواعد ── */
$hits = array();
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    $rel = 'assets/css/' . basename($cf);
    if (strpos($rel, '.min.css') !== false) continue;
    $css = preg_replace('#/\*.*?\*/#s', ' ', file_get_contents($cf));
    if (!preg_match_all('#([^{}]+)\{([^}]*)\}#s', $css, $mm, PREG_SET_ORDER)) continue;
    foreach ($mm as $r) {
        $sel = trim(preg_replace('#\s+#', ' ', $r[1]));
        if ($sel === '' || $sel[0] === '@') continue;
        $selL = strtolower($sel);

        $isContainer = false;
        foreach ($CONTAINER_HINTS as $h) if (strpos($selL, $h) !== false) { $isContainer = true; break; }
        if (!$isContainer) continue;
        foreach ($SMALL_PARTS as $p) if (strpos($selL, $p) !== false) { $isContainer = false; break; }
        if (!$isContainer) continue;

        if (!preg_match_all('#(?:^|;)\s*background(?:-color)?\s*:\s*([^;!]+)#i', $r[2], $bm)) continue;
        foreach ($bm[1] as $val) {
            $val = trim($val);
            if (stripos($val, 'gradient') !== false) continue;   // التدرّجُ ليس طبقةً مسطّحة
            if (stripos($val, 'url(') !== false) continue;
            $rgb = tc_to_rgb(tc_resolve_color($val, $tokens));
            if (!tc_is_flat_gray($rgb)) continue;
            $hits[] = array('file' => $rel, 'sel' => $sel, 'val' => $val,
                            'hex' => sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]));
        }
    }
}

/* ── ③ أيُّ شاشاتٍ تحمل هذه المحدِّدات؟ ── */
$phpFiles = array();
$SKIP = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database', 'tools', 'tests');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $phpFiles[$rel] = $p;
}

$byRule = array();
foreach ($hits as $h) {
    /* الصنفُ المميِّزُ: أعمقُ صنفٍ في المحدِّد */
    if (!preg_match_all('#\.([a-z0-9_-]+)#i', $h['sel'], $cm)) continue;
    $key = $h['file'] . '|' . $h['sel'];
    $classes = array_values(array_unique($cm[1]));
    $anchor = end($classes);
    $screens = array();
    foreach ($phpFiles as $rel => $abs) {
        $src = file_get_contents($abs);
        $ok = true;
        foreach ($classes as $c) { if (strpos($src, $c) === false) { $ok = false; break; } }
        if ($ok) $screens[] = $rel;
    }
    $byRule[$key] = array('h' => $h, 'classes' => $classes, 'anchor' => $anchor, 'screens' => $screens);
}

echo '══════════ الطبقاتُ الرماديّةُ المسطّحةُ على حاوياتِ الشاشات ══════════' . PHP_EOL;
if (!count($byRule)) { echo '  صفرُ حالة.' . PHP_EOL; exit(0); }

$i = 0; $totalScreens = array();
foreach ($byRule as $k => $v) {
    $i++;
    printf("%2d) %s   %s%s", $i, $v['h']['hex'], $v['h']['file'], PHP_EOL);
    echo '    المحدِّد: ' . mb_substr($v['h']['sel'], 0, 88) . PHP_EOL;
    echo '    القيمة : ' . $v['h']['val'] . PHP_EOL;
    echo '    شاشات  : ' . count($v['screens']);
    if ($showScreens && count($v['screens'])) {
        echo ' → ' . implode(', ', array_slice($v['screens'], 0, 6));
        if (count($v['screens']) > 6) echo ' …';
    }
    echo PHP_EOL . PHP_EOL;
    foreach ($v['screens'] as $s) $totalScreens[$s] = true;
}
echo '──────────────────────────────────────────────' . PHP_EOL;
echo 'قواعدُ رماديّةٌ مسطّحة ......... ' . count($byRule) . PHP_EOL;
echo 'شاشاتٌ متأثّرةٌ (فريدة) ........ ' . count($totalScreens) . PHP_EOL;

/* ═══════════════ ④ بصمةُ «التدرّجِ المُسطَّح» ═══════════════
   العلامةُ القاطعة: القاعدةُ الأمُّ خلفيتُها فاتحةٌ مسطّحة، وزخرفتُها في
   ::before/::after بيضاءُ بشفافيّةٍ ≤ ١٥٪ — وشفافيّةٌ كهذه لا تُرى إلا فوقَ
   خلفيةٍ داكنةٍ أو مشبعة. فوجودُها فوقَ رماديٍّ فاتحٍ يعني أن الخلفيةَ كانت
   داكنةً ثم سُطِّحت، وبقيت الزخرفةُ يتيمةً بلا أثر.
   (مقيسٌ حيًّا: `.contracts-details-page .page-hero` — دائرةٌ بيضاءُ ٣٠٠×٣٠٠
    بشفافيّةِ ٤٪ فوق #dddddd تُنتج #dfdfdf: فرقُ وحدتَين، أي لا شيء.)          */
echo PHP_EOL . '══════════ بصمةُ «التدرّجِ المُسطَّح» ══════════' . PHP_EOL;
$ghosts = array();
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    $rel = 'assets/css/' . basename($cf);
    if (strpos($rel, '.min.css') !== false) continue;
    $css = preg_replace('~/\*.*?\*/~s', ' ', file_get_contents($cf));
    if (!preg_match_all('~([^{}]+)\{([^}]*)\}~s', $css, $mm, PREG_SET_ORDER)) continue;

    $bases = array();                       /* محدِّدٌ أساسٌ → خلفيتُه المسطّحة */
    $decor = array();                       /* محدِّدٌ أساسٌ → شفافيّةُ زخرفتِه */
    foreach ($mm as $r) {
        $sel = trim(preg_replace('~\s+~', ' ', $r[1]));
        if ($sel === '' || $sel[0] === '@') continue;
        if (!preg_match_all('~(?:^|;)\s*background(?:-color)?\s*:\s*([^;!]+)~i', $r[2], $bm)) continue;
        $isPseudo = (bool) preg_match('~::(before|after)\s*$~i', $sel);
        $base = $isPseudo ? trim(preg_replace('~::(before|after)\s*$~i', '', $sel)) : $sel;
        foreach ($bm[1] as $val) {
            $val = trim($val);
            if (stripos($val, 'gradient') !== false || stripos($val, 'url(') !== false) continue;
            $rgb = tc_to_rgb(tc_resolve_color($val, $tokens));
            if ($rgb === null) continue;
            if ($isPseudo) {
                if ($rgb[3] <= 0.15 && $rgb[0] >= 200 && $rgb[1] >= 180) {
                    $decor[$base][] = sprintf('rgba(%d,%d,%d,%.2f)', $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
                }
            } else {
                if ($rgb[3] >= 0.9 && (($rgb[0] + $rgb[1] + $rgb[2]) / 3) >= 150) {
                    $bases[$base] = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                }
            }
        }
    }
    foreach ($decor as $base => $list) {
        if (!isset($bases[$base])) continue;
        $ghosts[] = array('file' => $rel, 'sel' => $base, 'bg' => $bases[$base], 'decor' => $list);
    }
}
if (!count($ghosts)) {
    echo '  صفرُ حالة.' . PHP_EOL;
} else {
    foreach ($ghosts as $k => $g) {
        printf("%2d) %s على %s%s", $k + 1, implode(' · ', $g['decor']), $g['bg'], PHP_EOL);
        echo '    ' . $g['file'] . '  ⟨' . mb_substr($g['sel'], 0, 70) . '⟩' . PHP_EOL;
    }
    echo PHP_EOL . '  المجموع: ' . count($ghosts) . ' قاعدةً زخرفتُها يتيمة.' . PHP_EOL;
}

if (!$showScreens) echo PHP_EOL . '(أضف --screens لأسماءِ الشاشات)' . PHP_EOL;
