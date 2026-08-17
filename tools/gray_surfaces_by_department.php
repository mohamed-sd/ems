<?php
/**
 * tools/gray_surfaces_by_department.php
 * قائمةُ الشاشاتِ المرشَّحةِ لعلّةِ «الطبقةِ الرماديّة» — مع إدارةِ كلِّ شاشة.
 * ═══════════════════════════════════════════════════════════════════════════
 * العلّةُ الأمُّ (بلاغُ المالك على `Contracts/contracts_details.php`):
 *   سطحُ حاويةٍ خلفيتُه **رماديٌّ مسطَّحٌ محايد** بينما المكوّنُ نفسُه في شاشةٍ
 *   أخرى ملوَّنٌ أو متدرّج — فيظهر «طبقةً لا معنى لها».
 *
 * الإدارةُ تُقرأ من `nav_items` (الجدولُ الحيُّ) بمطابقةِ `route` بمسارِ الملف،
 * لا بمجلَّدِ الملفِّ — فالمجلَّدُ تنظيمٌ للشيفرةِ والإدارةُ تبعيّةُ عمل، وهما
 * يفترقان: `Clients/pricelists.php` مثلًا تحت مجلَّدِ العملاءِ وتبعيّتُها المبيعات.
 * وحين لا تُسجَّل الشاشةُ في القائمةِ يُذكر ذلك صراحةً «غيرُ مسجَّلةٍ في القائمة»
 * ولا يُخمَّن اسمُ إدارةٍ لها.
 *
 * الاستعمال:  php tools/gray_surfaces_by_department.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);

/* ── ① الاتصالُ بالقاعدةِ لقراءةِ التبعيّة ── */
function gs_env($root) {
    $env = array();
    $p = $root . '/.env';
    if (!is_file($p)) return $env;
    foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#') continue;
        $i = strpos($l, '=');
        if ($i === false) continue;
        $env[trim(substr($l, 0, $i))] = trim(substr($l, $i + 1));
    }
    return $env;
}

$env = gs_env($ROOT);
$deptOf = array();      // مسارُ الملفِّ → array(door, groups)
$navOk = false;
if (isset($env['DB_HOST'])) {
    $hp = explode(':', $env['DB_HOST']);
    $db = @new mysqli($hp[0], $env['DB_USER'], isset($env['DB_PASS']) ? $env['DB_PASS'] : '',
                      $env['DB_NAME'], isset($hp[1]) ? (int) $hp[1] : 3306);
    if (!$db->connect_errno) {
        $db->set_charset('utf8mb4');
        /* الإدارةُ الظاهرةُ للمستخدمِ هي **مجموعةُ القائمةِ الجانبية** (link_groups)
           لا رمزُ البابِ (DAILY/REC/GOV) — فالرمزُ تصنيفٌ داخليٌّ لا يعرفه المالك.
           ومعها اسمُ الوحدةِ من `modules` لأن الشاشةَ الواحدةَ قد تظهر في أكثرِ
           من مجموعةٍ بحسبِ الدور، والوحدةُ أثبتُ منها. */
        $q = $db->query(
            "SELECT n.route, n.label_ar, g.name AS grp, m.name AS mod_name   /* `mod` كلمةٌ محجوزةٌ في MariaDB (عاملُ الباقي) */
               FROM nav_items n
          LEFT JOIN link_groups g ON g.id = n.group_id
          LEFT JOIN modules     m ON m.id = n.module_id
              WHERE n.route <> ''"
        );
        if ($q) {
            $navOk = true;
            while ($row = $q->fetch_assoc()) {
                /* المسارُ في القائمةِ قد يكون نسبيًّا أو بمعاملات — يُجرَّد للاسم */
                $r = strtok($row['route'], '?');
                $r = ltrim(str_replace('\\', '/', $r), './');
                if ($r === '') continue;
                if (!isset($deptOf[$r])) {
                    $deptOf[$r] = array('doors' => array(), 'mods' => array(), 'label' => $row['label_ar']);
                }
                $g = trim((string) $row['grp']);
                if ($g !== '' && !in_array($g, $deptOf[$r]['doors'], true)) $deptOf[$r]['doors'][] = $g;
                $md = trim((string) $row['mod_name']);
                if ($md !== '' && !in_array($md, $deptOf[$r]['mods'], true)) $deptOf[$r]['mods'][] = $md;
            }
        }
    }
}

function gs_dept($rel, $deptOf, $navOk) {
    if (!$navOk) return 'تعذّرت قراءةُ القائمة';
    $cands = array($rel, basename($rel));
    foreach ($cands as $c) {
        if (isset($deptOf[$c]) && count($deptOf[$c]['doors'])) return implode(' · ', $deptOf[$c]['doors']);
    }
    /* مطابقةٌ بذيلِ المسار — القائمةُ قد تخزّن `../Clients/x.php` */
    foreach ($deptOf as $r => $v) {
        if (substr($r, -strlen($rel)) === $rel && count($v['doors'])) return implode(' · ', $v['doors']);
    }
    return 'غيرُ مسجَّلةٍ في القائمة';
}

function gs_label($rel, $deptOf) {
    foreach (array($rel, basename($rel)) as $c) if (isset($deptOf[$c])) return $deptOf[$c]['label'];
    foreach ($deptOf as $r => $v) if (substr($r, -strlen($rel)) === $rel) return $v['label'];
    return '';
}

/** اسمُ الوحدةِ (modules) — أثبتُ من المجموعةِ لأنه لا يتبدّل بالدور. */
function gs_mod($rel, $deptOf) {
    foreach (array($rel, basename($rel)) as $c)
        if (isset($deptOf[$c]) && count($deptOf[$c]['mods'])) return implode(' · ', $deptOf[$c]['mods']);
    foreach ($deptOf as $r => $v)
        if (substr($r, -strlen($rel)) === $rel && count($v['mods'])) return implode(' · ', $v['mods']);
    return '—';
}

/* ── ② الرموزُ اللونيّة ── */
$tokens = array();
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    if (strpos($cf, '.min.css') !== false) continue;
    $s = file_get_contents($cf);
    if (preg_match_all('~(--[a-z0-9-]+)\s*:\s*(\#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))\s*;~i', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) if (!isset($tokens[strtolower($x[1])])) $tokens[strtolower($x[1])] = $x[2];
    }
}
function gs_color($v, $tokens, $d = 0) {
    $v = trim($v);
    if ($d > 5) return null;
    if (preg_match('~^var\(\s*(--[a-z0-9-]+)~i', $v, $m)) {
        $k = strtolower($m[1]);
        return isset($tokens[$k]) ? gs_color($tokens[$k], $tokens, $d + 1) : null;
    }
    return $v;
}
function gs_rgb($c) {
    if ($c === null) return null;
    $c = trim($c);
    if (preg_match('~^\#([0-9a-f]{6})$~i', $c, $m))
        return array(hexdec(substr($m[1],0,2)), hexdec(substr($m[1],2,2)), hexdec(substr($m[1],4,2)), 1.0);
    if (preg_match('~^\#([0-9a-f]{3})$~i', $c, $m)) { $h=$m[1];
        return array(hexdec($h[0].$h[0]), hexdec($h[1].$h[1]), hexdec($h[2].$h[2]), 1.0); }
    if (preg_match('~^rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)\s*(?:[,/]\s*([0-9.]+))?\s*\)$~i', $c, $m))
        return array((int)$m[1], (int)$m[2], (int)$m[3], isset($m[4]) ? (float)$m[4] : 1.0);
    return null;
}
function gs_is_gray($rgb) {
    if ($rgb === null) return false;
    list($r,$g,$b,$a) = $rgb;
    if ($a < 0.9) return false;
    if (abs($r-$g) > 10 || abs($g-$b) > 10) return false;
    return ($r >= 176 && $r <= 240);
}

/* الاختبارُ الذاتيّ */
$st = array(
    'g+'  => gs_is_gray(gs_rgb('#dddddd')) === true,
    'g++' => gs_is_gray(gs_rgb('#ddd')) === true,
    'w-'  => gs_is_gray(gs_rgb('#ffffff')) === false,
    'warm-' => gs_is_gray(gs_rgb('#fff7eb')) === false,
    'a-'  => gs_is_gray(gs_rgb('rgba(221,221,221,.1)')) === false,
);
$bad = array(); foreach ($st as $k=>$v) if(!$v) $bad[]=$k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: '.implode(',',$bad).PHP_EOL; exit(1); }

/* ── ③ القواعدُ الرماديّةُ على الحاويات ── */
$HINTS = array('hero','page-','section','card','panel','wrapper','wrap','-main','container','board','shell','block','tile','summary');
$SMALL = array('btn','button','badge','chip','icon','pill','dot','avatar','thumb','tag','label',
               'input','select','checkbox','radio','scrollbar','::-webkit','tooltip','caret','arrow','spinner');
$rules = array();
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    $rel = 'assets/css/' . basename($cf);
    if (strpos($rel, '.min.css') !== false) continue;
    $css = preg_replace('~/\*.*?\*/~s', ' ', file_get_contents($cf));
    if (!preg_match_all('~([^{}]+)\{([^}]*)\}~s', $css, $mm, PREG_SET_ORDER)) continue;
    foreach ($mm as $r) {
        $sel = trim(preg_replace('~\s+~', ' ', $r[1]));
        if ($sel === '' || $sel[0] === '@') continue;
        $sl = strtolower($sel);
        $ok = false; foreach ($HINTS as $h) if (strpos($sl,$h)!==false) { $ok=true; break; }
        if ($ok) foreach ($SMALL as $p) if (strpos($sl,$p)!==false) { $ok=false; break; }
        if (!$ok) continue;
        if (!preg_match_all('~(?:^|;)\s*background(?:-color)?\s*:\s*([^;!]+)~i', $r[2], $bm)) continue;
        foreach ($bm[1] as $val) {
            $val = trim($val);
            if (stripos($val,'gradient')!==false || stripos($val,'url(')!==false) continue;
            $rgb = gs_rgb(gs_color($val, $tokens));
            if (!gs_is_gray($rgb)) continue;
            $rules[] = array('file'=>$rel, 'sel'=>$sel,
                             'hex'=>sprintf('#%02x%02x%02x', $rgb[0],$rgb[1],$rgb[2]),
                             'lum'=>$rgb[0]);
        }
    }
}

/* ── ④ أيُّ شاشاتٍ تحمل كلَّ أصنافِ المحدِّد؟ ── */
$SKIP = array('vendor','node_modules','.git','storage','.ssdiff','docs','database','tools','tests');
$php = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92),'/',$f->getPathname());
    $rel = substr($p, strlen($ROOT)+1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $php[$rel] = file_get_contents($p);
}

$screens = array();   // شاشة → قائمةُ الأسطحِ الرماديّة
foreach ($rules as $r) {
    if (!preg_match_all('~\.([a-z0-9_-]+)~i', $r['sel'], $cm)) continue;
    $classes = array_values(array_unique($cm[1]));
    foreach ($php as $rel => $src) {
        $all = true;
        foreach ($classes as $c) if (strpos($src, $c) === false) { $all = false; break; }
        if (!$all) continue;
        $screens[$rel][] = $r;
    }
}

/* ── ⑤ الترتيبُ بالخطورة: الأدكنُ أخطر ── */
$rows = array();
foreach ($screens as $rel => $list) {
    $dark = 240; $hexes = array();
    foreach ($list as $r) { if ($r['lum'] < $dark) $dark = $r['lum']; $hexes[$r['hex']] = true; }
    $rows[] = array('screen'=>$rel, 'dark'=>$dark, 'n'=>count($list),
                    'hexes'=>implode(' ', array_keys($hexes)),
                    'dept'=>gs_dept($rel, $deptOf, $navOk),
                    'mod' =>gs_mod($rel, $deptOf),
                    'label'=>gs_label($rel, $deptOf));
}
usort($rows, function($a,$b){ return $a['dark'] === $b['dark'] ? strcmp($a['screen'],$b['screen']) : $a['dark'] - $b['dark']; });

echo 'قراءةُ التبعيّةِ من nav_items: ' . ($navOk ? 'متاحة' : '✘ غيرُ متاحة') . PHP_EOL;
echo 'فحصُ الفاحص: ' . count($st) . ' حالةً — كلُّها سليمة.' . PHP_EOL;
echo 'الترتيبُ بالخطورة: الأدكنُ أوّلًا (الرماديُّ الغامقُ أظهرُ للعين).' . PHP_EOL . PHP_EOL;

/* عرضٌ رأسيٌّ — الجداولُ العربيةُ في الطرفيّةِ تتشوّه بـstr_pad لأن المحرفَ
   العربيَّ متعدّدُ البايتات فيُحسب طولُه خطأً. فالعرضُ سطرٌ لكلِّ حقل. */
$i = 0;
foreach ($rows as $r) {
    $i++;
    echo $i . ') ' . $r['screen'] . PHP_EOL;
    echo '     الوحدة   : ' . $r['mod'] . PHP_EOL;
    echo '     المجموعة : ' . $r['dept'] . PHP_EOL;
    echo '     الأسطح   : ' . $r['hexes'] . '   (' . $r['n'] . ' قاعدة)' . PHP_EOL;
    echo PHP_EOL;
}
echo str_repeat('─', 70) . PHP_EOL;
echo 'المجموع: ' . count($rows) . ' شاشةً · ' . count($rules) . ' قاعدةً رماديّة' . PHP_EOL;
