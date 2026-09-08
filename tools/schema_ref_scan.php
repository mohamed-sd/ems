<?php
/**
 * tools/schema_ref_scan.php — جردُ مراجعِ (جدول.عمود) في شيفرةِ المنتجِ مقابلَ المخطَّطِ الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤال**: أيُّ استعلامٍ حيٍّ يسأل عن عمودٍ أو جدولٍ لا وجودَ له؟
 * ◆ **الطريقة**: token_get_all (فلا يُقرأ تعليقٌ كشيفرة — درسُ «الماسحُ يقرأ
 *   التعليق») ⇒ سلاسلُ SQL ⇒ تُجرَّد النصوصُ المقتبسةُ داخلَها (فمفتاحُ حدثٍ
 *   مثل 'project.chartered' ليس عمودًا) ⇒ خريطةُ (لقب⇒جدول) من FROM/JOIN في
 *   السلسلةِ نفسِها ⇒ مراجعُ لقب.عمود ⇒ مصادمةٌ بـinformation_schema.
 *   ولقبٌ لا يُحَلُّ لجدولٍ حقيقيٍّ يُهمَل (لا يُتَّهَم) — فالمقياسُ لا يختلق.
 * ◆ **④ حارسُ مفردة**: صفرُ ملفٍّ أو قلّةُ مراجعَ محلولةٍ = رسوبُ أداةٍ لا نظافة.
 * ◆ **الضابطُ السالب** (--selftest): عيّنةٌ مصنوعةٌ **داخلَ الأداةِ** تمرُّ
 *   بخطِّ الأنابيبِ نفسِه ويجب أن تُرى — فلا يعتمد الضابطُ على بقاءِ عطبٍ
 *   حقيقيٍّ في الشجرة. (وقد وقع مقيسًا: حرفُ 0x08 تسلَّل إلى نمطٍ فأخرس
 *   فرعَ الجداولِ الوهميّةِ كلَّه بصمت — فالضابطُ يفحص الفرعَين كليهما.)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصالٌ فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$DB = ems_env('DB_NAME');

/* ── المخطَّطُ الحيُّ: الجداولُ وأعمدتُها ─────────────────────────────────── */
$tables = array(); $cols = array();
$r = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . $conn->real_escape_string($DB) . "'");
while ($x = $r->fetch_row()) { $t = strtolower($x[0]); $tables[$t] = 1; $cols[$t][strtolower($x[1])] = 1; }
if (count($tables) < 100) { fwrite(STDERR, "④ المخطَّطُ المقروءُ صغيرٌ بشكلٍ مريب (" . count($tables) . ")\n"); exit(1); }

/* ── المفردات ─────────────────────────────────────────────────────────────── */
$EXT = array_flip(array('php','js','css','html','htm','png','jpg','jpeg','svg','json','txt','md','xml','ini','log','lock','zip','xlsx','csv','ico','woff','woff2','ttf','map','sql','gif','pdf'));
$STOPTBL = array_flip(array('dual','information_schema','mysql','performance_schema','current_timestamp'));
$RESERVED = array_flip(array('where','on','set','left','right','inner','outer','cross','join','group','order','limit','having','union','select','as','using','values','when','then','and','or','not','in','is','like','between','case','end','ignore','force','use','index','key','natural','if','exists','duplicate','for','update','into','from','straight_join','partition','with','distinct','v'));

/* ── استخراجُ سلاسلِ SQL من التوكنات (تعليقٌ لا يُقرأ) ───────────────────── */
function srs_blobs($src) {
    $toks = @token_get_all($src);
    $blobs = array(); $cur = ''; $curLine = 0;
    $flush = function () use (&$cur, &$curLine, &$blobs) {
        if ($cur !== '' && preg_match('~\b(from|join|update|into)\b~i', $cur)) { $blobs[] = array($curLine, $cur); }
        $cur = ''; $curLine = 0;
    };
    foreach ($toks as $t) {
        if (is_array($t)) {
            $id = $t[0]; $txt = $t[1]; $ln = $t[2];
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                if ($curLine === 0) { $curLine = $ln; }
                $cur .= substr($txt, 1, -1); continue;
            }
            if ($id === T_ENCAPSED_AND_WHITESPACE) { if ($curLine === 0) { $curLine = $ln; } $cur .= $txt; continue; }
            if ($id === T_VARIABLE || $id === T_LNUMBER || $id === T_DNUMBER || $id === T_STRING || $id === T_OBJECT_OPERATOR || $id === T_DOUBLE_ARROW) { if ($curLine === 0) { $curLine = $ln; } $cur .= ' V '; continue; }
            if ($id === T_WHITESPACE) { $cur .= ' '; continue; }
            if ($id === T_START_HEREDOC || $id === T_END_HEREDOC || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) { continue; }
            $flush(); continue;
        }
        if ($t === '"' || $t === '`' || $t === '.') { continue; }
        if ($t === '(' || $t === ')' || $t === '[' || $t === ']' || $t === '{' || $t === '}') { $cur .= ' '; continue; }
        $flush();
    }
    $flush();
    return $blobs;
}

/**
 * فحصُ كتلةِ SQL واحدة — خطُّ الأنابيبِ نفسُه للشجرةِ ولعيّنةِ الضابطِ السالب.
 */
function srs_scan_blob($sqlRaw, $rel, $line, $tables, $cols, $RESERVED, $STOPTBL, $EXT, &$phantomCols, &$phantomTbls, &$nRefs, &$nResolved) {
    /* النصُّ المقتبسُ داخلَ SQL قيمةٌ لا مُعرِّف — يُجرَّد قبلَ كلِّ مطابقة */
    $sql = preg_replace("~'[^']*'~s", " ' ' ", $sqlRaw);
    $map = array();
    if (preg_match_all('~\b(from|join|update|into)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?(?:\s+(?:as\s+)?`?([a-zA-Z_][a-zA-Z0-9_]*)`?)?~i', $sql, $m, PREG_SET_ORDER)) {
        $isSelectish = (bool) preg_match('~\bselect\b~i', $sql);
        foreach ($m as $g) {
            $kw = strtolower($g[1]);
            $tb = strtolower($g[2]);
            if (isset($RESERVED[$tb]) || isset($STOPTBL[$tb])) { continue; }
            $map[$tb] = $tb;
            if (!isset($tables[$tb]) && $isSelectish && ($kw === 'from' || $kw === 'join')) { $phantomTbls[$tb][] = $rel . ':' . $line; }
            if (isset($g[3]) && $g[3] !== '') {
                $al = strtolower($g[3]);
                if (!isset($RESERVED[$al])) { $map[$al] = $tb; }
            }
        }
    }
    if (preg_match_all('~\b([a-zA-Z_][a-zA-Z0-9_]*)\.`?([a-zA-Z_][a-zA-Z0-9_]*)`?~', $sql, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $g) {
            $al = strtolower($g[1]); $co = strtolower($g[2]);
            if (isset($EXT[$co])) { continue; }   /* مسارُ ملفٍّ لا مرجعُ عمود */
            $nRefs++;
            $tb = isset($map[$al]) ? $map[$al] : (isset($tables[$al]) ? $al : null);
            if ($tb === null || !isset($tables[$tb])) { continue; }   /* لقبٌ لا يُحلّ — لا يُتَّهم */
            $nResolved++;
            if (!isset($cols[$tb][$co])) { $phantomCols[$tb . '.' . $co][] = $rel . ':' . $line; }
        }
    }
}

/* ── الضابطُ السالب: عيّنةٌ مصنوعةٌ تمرُّ بالخطِّ نفسِه ──────────────────── */
if (in_array('--selftest', $argv, true)) {
    $fix = '<?php $q = "SELECT u.srs_no_such_col, ' . "'a.b'" . ' k FROM users u JOIN srs_no_such_table z ON z.id = u.id";';
    $pc = array(); $pt = array(); $r1 = 0; $r2 = 0;
    foreach (srs_blobs($fix) as $b) {
        srs_scan_blob($b[1], 'fixture', $b[0], $tables, $cols, $RESERVED, $STOPTBL, $EXT, $pc, $pt, $r1, $r2);
    }
    $okC = isset($pc['users.srs_no_such_col']);
    $okT = isset($pt['srs_no_such_table']);
    $okQ = !isset($pc['a.b']);   /* النصُّ المقتبسُ لا يُتَّهم */
    if (!$okC || !$okT || !$okQ) {
        fwrite(STDERR, "✘ الضابطُ السالب: عمود=" . var_export($okC, true) . " جدول=" . var_export($okT, true) . " تجريدُ الاقتباس=" . var_export($okQ, true) . " — الفاحصُ أعمى\n");
        exit(1);
    }
    echo "✔ الضابطُ السالب: فرعا العمودِ والجدولِ يريان العيّنةَ المصنوعة — والاقتباسُ لا يُتَّهم\n";
}

/* ── ملفّاتُ المنتج ───────────────────────────────────────────────────────── */
$EXCL = array('tools','tests','database','docs','scripts','logs','vendor','node_modules','.git','.ssdiff','uploads','assets','css','js','images','fonts','backups','storage','examples');
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    function ($f) use ($EXCL, $ROOT) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        $top = explode('/', $rel);
        $top = $top[0];
        if ($f->isDir()) { return !in_array($top, $EXCL, true); }
        return substr($rel, -4) === '.php';
    }));
foreach ($it as $f) { $files[] = str_replace('\\', '/', $f->getPathname()); }
sort($files);
if (count($files) < 50) { fwrite(STDERR, "④ صفرُ/قِلّةُ ملفّاتٍ ممسوحة — عطبُ أداة\n"); exit(1); }

$phantomCols = array(); $phantomTbls = array();
$nFiles = 0; $nBlobs = 0; $nRefs = 0; $nResolved = 0;
foreach ($files as $path) {
    $src = @file_get_contents($path);
    if ($src === false) { continue; }
    $nFiles++;
    $rel = substr($path, strlen($ROOT) + 1);
    foreach (srs_blobs($src) as $b) {
        $nBlobs++;
        srs_scan_blob($b[1], $rel, $b[0], $tables, $cols, $RESERVED, $STOPTBL, $EXT, $phantomCols, $phantomTbls, $nRefs, $nResolved);
    }
}
if ($nResolved < 100) { fwrite(STDERR, "④ مراجعُ محلولةٌ قليلةٌ بشكلٍ مريب ({$nResolved}) — عطبُ أداة\n"); exit(1); }

/* ── التقرير ──────────────────────────────────────────────────────────────── */
ksort($phantomCols); ksort($phantomTbls);
echo "══ جردُ مراجعِ المخطَّط — " . $DB . " ══\n";
echo "  ملفّات=" . $nFiles . " · سلاسلُ SQL=" . $nBlobs . " · مراجعُ=" . $nRefs . " · محلولة=" . $nResolved . "\n";
echo "── أعمدةٌ وهميّة (" . count($phantomCols) . " زوجًا):\n";
foreach ($phantomCols as $k => $sites) {
    $u = array_values(array_unique($sites));
    echo sprintf("  ✘ %-46s ×%d  %s%s\n", $k, count($sites), implode(' · ', array_slice($u, 0, 3)), count($u) > 3 ? ' …' : '');
}
echo "── جداولٌ وهميّةٌ مُستعلَمة (" . count($phantomTbls) . "):\n";
foreach ($phantomTbls as $k => $sites) {
    $u = array_values(array_unique($sites));
    echo sprintf("  ✘ %-46s ×%d  %s%s\n", $k, count($sites), implode(' · ', array_slice($u, 0, 3)), count($u) > 3 ? ' …' : '');
}
exit(count($phantomCols) + count($phantomTbls) > 0 ? 2 : 0);
