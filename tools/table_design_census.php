<?php
/**
 * tools/table_design_census.php — جردُ الجداولِ ووصولِها لملفِّ التصميم.
 * ═══════════════════════════════════════════════════════════════════════════
 * يجيب على السؤال: كم ملفًّا فيه جدول؟ وكم يصله التصميمُ وكم لا يصله؟
 *
 * ثلاثةُ دروسٍ مدفوعةُ الثمنِ مطبوعةٌ في هذا الفاحص:
 *   ① «لا يصل» وحدَها ليست حُكمًا. الجزءُ المُضمَّنُ (partial) لا يحمل ورقةً
 *      ولا يحتاج — يُصيَّر داخلَ شاشةٍ تحملها. فالتصنيفُ ثلاثيٌّ لا ثنائيّ.
 *   ② الوصولُ **متعدٍّ**: يُبنى رسمُ التضميناتِ ثم يُمشى عليه. وفحصُ الملفِّ
 *      وحدَه يُنتج عشراتِ الاتهاماتِ الباطلة.
 *   ③ التضمينُ في هذا المستودعِ بأربعِ صيغ، وإسقاطُ صيغةٍ واحدةٍ يقلبُ الرقم:
 *      إغفالُ `dirname(__DIR__)` وحدَه أظهر خمسَ شاشاتٍ يتيمةً وهي ليست كذلك،
 *      وإغفالُ `$var = '…'; include $var;` أظهر سادسة.
 *
 * الاستعمال:  php tools/table_design_census.php [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$SKIP = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database');
$TOOLDIR = array('tools', 'tests', 'scripts', 'install');
$showList = in_array('--list', $argv, true);

function tc_has_table_tag($s) {
    $off = 0;
    while (($i = stripos($s, '<table', $off)) !== false) {
        $c = isset($s[$i + 6]) ? $s[$i + 6] : ' ';
        if ($c === '>' || $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") return true;
        $off = $i + 6;
    }
    return false;
}

function tc_has_datatables($s) {
    foreach (array('.DataTable(', '.dataTable(', 'dataTables_wrapper',
                   'jquery.dataTables', 'datatable_server') as $n) {
        if (stripos($s, $n) !== false) return true;
    }
    return false;
}

function tc_links($s) {
    return stripos($s, 'ems-tables.css') !== false || stripos($s, 'table_design.php') !== false;
}

function tc_includes($s) {
    $out = array();
    $re = '#(?:include|require)(?:_once)?\s*\(?\s*'
        . '(?:__DIR__|\$[A-Za-z_][A-Za-z0-9_]*|dirname\s*\([^)]*\))?\s*\.?\s*'
        . '["\']([^"\']+\.php)["\']#i';
    if (preg_match_all($re, $s, $m)) foreach ($m[1] as $t) $out[] = $t;
    if (preg_match_all('#(?:include|require)(?:_once)?\s*\(?\s*(\$[A-Za-z_][A-Za-z0-9_]*)\s*\)?\s*;#i', $s, $mv)) {
        foreach (array_unique($mv[1]) as $var) {
            $ra = '#' . preg_quote($var, '#') . '\s*=\s*[^;]*?["\']([^"\']+\.php)["\']#';
            if (preg_match_all($ra, $s, $ma)) foreach ($ma[1] as $t) $out[] = $t;
        }
    }
    return $out;
}

/* الاختبارُ الذاتيُّ — إيجابيٌّ وسلبيٌّ لكلِّ كاشف */
$st = array(
    'tag+' => tc_has_table_tag('<table id="x">') === true,
    'tag-' => tc_has_table_tag('<tablet>') === false,
    'dt+'  => tc_has_datatables('.DataTable({})') === true,
    'dt-'  => tc_has_datatables('nothing') === false,
    'lnk+' => tc_links('table_design.php') === true,
    'lnk-' => tc_links('ems-forms.css') === false,
    'inc①' => tc_includes("include '../inheader.php';") === array('../inheader.php'),
    'inc②' => tc_includes('include $r . "/inheader.php";') === array('/inheader.php'),
    'inc③' => tc_includes('require_once dirname(__DIR__) . "/includes/x.php";') === array('/includes/x.php'),
    'inc④' => tc_includes('$i = __DIR__ . "/../inheader.php"; include $i;') === array('/../inheader.php'),
    'inc-' => tc_includes('$x = "no.php";') === array(),
);
$bad = array();
foreach ($st as $k => $v) if (!$v) $bad[] = $k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: ' . implode(',', $bad) . PHP_EOL; exit(1); }
echo 'فحصُ الفاحص: ' . count($st) . ' حالةً — كلُّها سليمة.' . PHP_EOL . PHP_EOL;

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $files[$rel] = $p;
}

$info = array();
foreach ($files as $rel => $abs) {
    $s = @file_get_contents($abs);
    if ($s === false) continue;
    $info[$rel] = array('table' => tc_has_table_tag($s), 'dt' => tc_has_datatables($s),
                        'link' => tc_links($s), 'incs' => tc_includes($s), 'res' => array());
}
function tc_resolve($from, $t, $files) {
    $t = preg_replace('#^C:/wamp64/www/ems/#i', '', str_replace(chr(92), '/', $t));
    $base = dirname($from); if ($base === '.') $base = '';
    $c = array(); if ($base !== '') $c[] = $base . '/' . $t; $c[] = ltrim($t, '/');
    foreach ($c as $cand) {
        $parts = array();
        foreach (explode('/', $cand) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        if (isset($files[implode('/', $parts)])) return implode('/', $parts);
    }
    $bn = basename($t); $hit = null; $n = 0;
    foreach ($files as $r => $x) if (basename($r) === $bn) { $hit = $r; $n++; }
    return ($n === 1) ? $hit : null;
}
foreach ($info as $rel => $d) {
    $r = array();
    foreach ($d['incs'] as $t) { $x = tc_resolve($rel, $t, $files); if ($x !== null) $r[$x] = true; }
    $info[$rel]['res'] = array_keys($r);
}
function tc_reaches($rel, &$info, &$memo, $depth = 0) {
    if (array_key_exists($rel, $memo)) return $memo[$rel];
    if ($depth > 15 || !isset($info[$rel])) return false;
    $memo[$rel] = false;
    if ($info[$rel]['link']) { $memo[$rel] = true; return true; }
    foreach ($info[$rel]['res'] as $c) if (tc_reaches($c, $info, $memo, $depth + 1)) { $memo[$rel] = true; return true; }
    return false;
}

$withTable = array(); $withDT = array(); $any = array();
foreach ($info as $rel => $d) {
    if ($d['table']) $withTable[] = $rel;
    if ($d['dt'])    $withDT[]    = $rel;
    if ($d['table'] || $d['dt']) $any[] = $rel;
}
$both = array_values(array_intersect($withTable, $withDT));

$reach = array(); $notReach = array();
foreach ($any as $rel) { $m = array(); if (tc_reaches($rel, $info, $m)) $reach[] = $rel; else $notReach[] = $rel; }

$catA = array(); $catB = array(); $catC = array();
foreach ($notReach as $o) {
    $top = explode('/', $o);
    if (in_array($top[0], $TOOLDIR, true)) { $catC[] = $o; continue; }
    $host = null;
    foreach ($info as $p => $pd) {
        if (!in_array($o, $pd['res'], true)) continue;
        $m = array();
        if (tc_reaches($p, $info, $m)) { $host = $p; break; }
    }
    if ($host !== null) $catB[$o] = $host; else $catA[] = $o;
}

$screens = array();
foreach ($any as $r) { $t = explode('/', $r); if (!in_array($t[0], $TOOLDIR, true)) $screens[] = $r; }
$covered = count($screens) - count($catA);
$pct = count($screens) ? round($covered * 100 / count($screens), 1) : 0;

echo '══════════ جردُ الجداول ══════════' . PHP_EOL;
echo 'ملفات PHP حيّة ................. ' . count($files) . PHP_EOL;
echo 'فيها وسمُ <table> ............... ' . count($withTable) . PHP_EOL;
echo 'فيها DataTables ................ ' . count($withDT) . PHP_EOL;
echo '  منها الاثنان معًا ............ ' . count($both) . PHP_EOL;
echo 'المجموعُ الفريد ................. ' . count($any) . PHP_EOL . PHP_EOL;
echo '══════════ الوصول ══════════' . PHP_EOL;
echo 'يصله التصميم ................... ' . count($reach) . PHP_EOL;
echo 'لا يصله ........................ ' . count($notReach) . PHP_EOL;
echo '   ├─ جزءٌ داخلَ شاشةٍ تصل ...... ' . count($catB) . '   (سليمٌ — يُصيَّر داخلَ مضيفِه)' . PHP_EOL;
echo '   ├─ عُدّةٌ/فحصٌ/تنصيب .......... ' . count($catC) . '   (خارجَ سطحِ المستخدم)' . PHP_EOL;
echo '   └─ شاشةٌ يتيمةٌ حقيقيّة ....... ' . count($catA) . (count($catA) ? '   ← تحتاج إصلاحًا' : '   ✔') . PHP_EOL . PHP_EOL;
echo '══════════ سطحُ المستخدم ══════════' . PHP_EOL;
echo 'شاشاتٌ فيها جدول ............... ' . count($screens) . PHP_EOL;
echo 'مغطّاةٌ بالتصميمِ الموحَّد ........ ' . $covered . '  (' . $pct . '٪)' . PHP_EOL;

if (count($catA)) { echo PHP_EOL . '── اليتامى ──' . PHP_EOL; foreach ($catA as $o) echo '   ✘ ' . $o . PHP_EOL; }
if ($showList) {
    echo PHP_EOL . '── أجزاءٌ مُضمَّنة ──' . PHP_EOL;
    foreach ($catB as $o => $h) echo '   • ' . $o . '  ⟵ ' . $h . PHP_EOL;
    echo PHP_EOL . '── خارجَ المقام ──' . PHP_EOL;
    foreach ($catC as $o) echo '   • ' . $o . PHP_EOL;
}
exit(count($catA) === 0 ? 0 : 1);
