<?php
/**
 * tools/cmp03_apply.php — مطبّق CMP-03 الموجة ② (طبقة الحوكمة المشتركة)
 * ───────────────────────────────────────────────────────────────────────────
 * يحقن رؤوس <th data-gov="…"> الناقصة حرفيًّا في ترويسة الجدول الرئيس لكل شاشةٍ
 * مقارنة، بتسمية المستند الحرفية (فتتحول من «ناقص حوكمة» إلى «مطابق» في
 * scrdes_compare). خلايا الصفوف يحشوها ui-unification.js (padGovernanceCells).
 *
 * توصيتا المالك المعتمدتان هنا:
 *   ① فوق 22 عمودًا: الفائض يحمل class="none" فينهار لسطرٍ تابعٍ عبر
 *     DataTables Responsive — ويوثَّق في docs/CMP03_OVERFLOW_LOG_ar.md.
 *   ③ العمود بلا مصدرٍ صفّيٍّ بعدُ يعرض «—» — ومصدره مهمة لحاق تسجَّل في
 *     docs/CMP03_FOLLOWUP_SOURCES_ar.md.
 *
 * التشغيل: php tools/cmp03_apply.php [--apply] [--screen=file.php] [--limit=N]
 *          بلا --apply: عرض الفروق (diff) دون كتابة.
 *   --fn : الموجة ⑤ — حقن الأعمدة الوظيفية الناقصة (missFn) بدل الحوكمة،
 *          رؤوسًا <th data-fn="1"> بتسمية المستند الحرفية قبل كتلة الحوكمة،
 *          وخلاياها «—» من الحاشي المركزي حتى يُربط مصدرها الصفّي (توصية ③).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
require_once __DIR__ . '/../includes/gov_columns.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$APPLY = in_array('--apply', $argv, true);
$FN = in_array('--fn', $argv, true); // الموجة ⑤: الوظيفي بدل الحوكمة
$onlyScreen = null; $limit = 0;
$onlySet = null; // --screens=a.php,b.php — دفعة محددة
foreach ($argv as $a) {
    if (strpos($a, '--screen=') === 0)  { $onlyScreen = substr($a, 9); }
    if (strpos($a, '--screens=') === 0) { $onlySet = array_flip(array_filter(array_map('trim', explode(',', substr($a, 10))))); }
    if (strpos($a, '--limit=') === 0)   { $limit = (int) substr($a, 8); }
}

$MAX_VISIBLE = 22; // توصية المالك ①: حد الأعمدة الظاهرة

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$registry = ems_gov_registry();
$labelToKey = array(); $keyOrder = array_keys($registry);
foreach ($registry as $k => $def) { $labelToKey[cmp03_norm($def[0])] = $k; }

/* تجميع شاشات المستند على الملف الحقيقي الواحد (شاشتان قد تشيران لملفٍ واحد) */
$byFile = array();
foreach ($screens as $cf => $sc) {
    if ($onlyScreen !== null && $cf !== $onlyScreen) { continue; }
    if ($onlySet !== null && !isset($onlySet[$cf])) { continue; }
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $byFile[$map[$cf]['real_path']][$cf] = $sc;
}

$manual = array(); $planned = array(); $overflowLog = array(); $followups = array();
$nFiles = 0; $nCols = 0;

foreach ($byFile as $real => $docScreens) {
    if ($limit && $nFiles >= $limit) { break; }
    $path = $ROOT . '/' . $real;
    $src = @file_get_contents($path);
    if ($src === false) { $manual[] = "$real — لا يُقرأ"; continue; }

    $heads = cmp03_extract_heads($path);
    if (!$heads) { continue; } // لوحة بلا جدول — لا تُقارن

    if (preg_match('/serverSide\s*:\s*true/', $src)) {
        $manual[] = "$real — serverSide: أعمدة الجدول من JS، يُعالج يدويًّا";
        continue;
    }

    /* اتحاد الناقص (حوكمةً أو وظيفيًّا بحسب الوضع) لكل شاشات المستند المشيرة لهذا الملف */
    $missing = array(); // norm → original doc label — بترتيب ورود المستند
    $titles = array();
    foreach ($docScreens as $cf => $sc) {
        $titles[] = $sc['title'] . " ($cf)";
        $j = cmp03_judge($sc['cols'], cmp03_extract_heads($path));
        $pool = $FN ? $j['missFn'] : $j['missGov'];
        foreach ($sc['cols'] as $c) { // ترتيب المستند الحرفي
            $cn = cmp03_norm($c);
            if (isset($pool[$cn]) && !isset($missing[$cn])) { $missing[$cn] = $pool[$cn]; }
        }
    }
    if (!$missing) { continue; }

    /* الترويسة المستهدفة: أفضل <thead> تشابهًا بأعمدة المستند */
    if (!preg_match_all('/<thead\b[^>]*>(.*?)<\/thead>/su', $src, $mThead, PREG_OFFSET_CAPTURE)) {
        $manual[] = "$real — لا <thead>، يُعالج يدويًّا";
        continue;
    }
    $docAll = array();
    foreach ($docScreens as $sc) { foreach ($sc['cols'] as $c) { $docAll[cmp03_norm($c)] = 1; } }

    $best = null; // [score, trContentOffsetAbs, trContent, existingLeafCount, hasColspan, alreadyGov[]]
    foreach ($mThead[1] as $tb) {
        $theadContent = $tb[0]; $theadOff = $tb[1];
        if (!preg_match('/<tr\b[^>]*>(.*?)<\/tr>/su', $theadContent, $mTr, PREG_OFFSET_CAPTURE)) { continue; }
        $trContent = $mTr[1][0];
        $trAbs = $theadOff + $mTr[1][1]; // موضع بداية محتوى <tr> في الملف
        $score = 0; $count = 0; $colspan = false; $already = array();
        if (preg_match_all('/<th\b([^>]*)>(.*?)<\/th>/su', $trContent, $mTh)) {
            foreach ($mTh[1] as $i => $attrs) {
                $count++;
                if (preg_match('/colspan/i', $attrs)) { $colspan = true; }
                if (preg_match('/data-gov="([^"]+)"/', $attrs, $g)) { $already[$g[1]] = 1; }
                $txt = preg_replace('/<\?php.*?\?>/su', '', $mTh[2][$i]);
                $txt = cmp03_norm(trim(strip_tags($txt)));
                if ($txt === '') { continue; }
                if (isset($docAll[$txt])) { $score += 1; continue; }
                foreach ($docAll as $dn => $x) { if (cmp03_sim($dn, $txt) >= 0.6) { $score += 0.5; break; } }
            }
        }
        if ($count === 0) { continue; }
        if ($best === null || $score > $best[0]) {
            $best = array($score, $trAbs, $trContent, $count, $colspan, $already);
        }
    }
    if ($best === null) { $manual[] = "$real — لا صفَّ ترويسةٍ صالحًا"; continue; }
    if ($best[4]) { $manual[] = "$real — ترويسة مجمّعة (colspan)، تُعالج يدويًّا"; continue; }

    list($score, $trAbs, $trContent, $existing, , $already) = $best;

    /* المحاذاة: مسافة آخر <th> في الصف */
    $indent = '              ';
    if (preg_match_all('/\n([ \t]*)<th\b/', $trContent, $mInd) && $mInd[1]) {
        $indent = end($mInd[1]);
    }

    if ($FN) {
        /* الموجة ⑤: الوظيفي بترتيب المستند — قبل كتلة الحوكمة إن وُجدت */
        $inject = $missing;
        $block = "\n" . $indent . "<!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->";
        foreach ($inject as $dn => $lbl) {
            $block .= "\n" . $indent . '<th class="ems-fn-th" data-fn="1">' . $lbl . '</th>';
            $followups[] = array($real, implode(' · ', $titles), $lbl, 'fn');
            $nCols++;
        }
        $govAt = strpos($trContent, '<!-- CMP-03 ②③④');
        $insertRel = $govAt !== false ? $govAt : strlen($trContent);
        /* قصّ ذيل المسافات قبل نقطة الإدراج كي لا تتراكم */
        $head = rtrim(substr($trContent, 0, $insertRel), " \t\n");
        $tail = substr($trContent, $insertRel);
        $newTr = $head . $block . "\n" . $indent . ($govAt !== false ? $tail : '');
    } else {
        /* الموجة ②: الحوكمة بترتيب سجلها (تواتر ورقة 04) واستبعاد المحقون سلفًا */
        $inject = array();
        foreach ($keyOrder as $k) {
            $lbl = $registry[$k][0];
            $n = cmp03_norm($lbl);
            if (isset($missing[$n]) && !isset($already[$k])) { $inject[$k] = $missing[$n]; }
        }
        foreach ($missing as $dn => $orig) {
            if (!isset($labelToKey[$dn])) { $manual[] = "$real — تسمية حوكمة خارج السجل: $orig"; }
        }
        if (!$inject) { continue; }
        $block = "\n" . $indent . "<!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->";
        foreach ($inject as $k => $lbl) {
            $def = $registry[$k];
            $block .= "\n" . $indent . '<th class="ems-gov-th" data-gov="' . $k . '" data-slice="' . $def[1] . '" title="' . $def[2] . '">' . $lbl . '</th>';
            $followups[] = array($real, implode(' · ', $titles), $lbl, $k);
            $nCols++;
        }
        $newTr = rtrim($trContent, " \t\n") . $block . "\n" . $indent;
    }
    if (!$inject) { continue; }

    /* توصية المالك ①: إعادة وزن الظهور — عدّ الرؤوس تسلسليًّا، وما تجاوز 22 من
       رؤوسنا المحقونة (حوكمةً أو وظيفيًّا) يحمل none فينهار لسطرٍ تابع؛
       رؤوس الشاشة الأصلية لا تُمسّ أبدًا. */
    $seq = 0; $nOver = 0;
    $newTr = preg_replace_callback('/<th\b([^>]*)>(.*?)<\/th>/su', function ($mm) use (&$seq, $MAX_VISIBLE, &$overflowLog, &$nOver, $real) {
        $seq++;
        $attrs = $mm[1];
        if (!preg_match('/class="(ems-gov-th|ems-fn-th)[^"]*"/', $attrs, $mc)) { return $mm[0]; }
        $isOver = $seq > $MAX_VISIBLE;
        $cls = $mc[1] . ($isOver ? ' none' : '');
        $attrs = preg_replace('/class="(?:ems-gov-th|ems-fn-th)[^"]*"/', 'class="' . $cls . '"', $attrs);
        if ($isOver) { $overflowLog[] = array($real, trim(strip_tags($mm[2])), $seq); $nOver++; }
        return '<th' . $attrs . '>' . $mm[2] . '</th>';
    }, $newTr);

    $newSrc = substr($src, 0, $trAbs) . $newTr . substr($src, $trAbs + strlen($trContent));

    $planned[] = sprintf('%s ← %d عمودًا (%s)%s', $real, count($inject),
        implode('، ', array_values($inject)), $nOver > 0 ? " — $nOver فوق 22 تنهار لسطرٍ تابع" : '');
    $nFiles++;

    if ($APPLY) {
        file_put_contents($path, $newSrc);
        $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
        if (strpos((string) $lint, 'No syntax errors') === false) {
            echo "‼ خطأ صياغة بعد الحقن في $real — تراجُع\n$lint\n";
            file_put_contents($path, $src);
        }
    }
}

foreach ($planned as $p) { echo ($APPLY ? '✔ ' : '⏸ ') . $p . "\n"; }
if ($manual) {
    echo "\n── للمعالجة اليدوية (" . count($manual) . "):\n";
    foreach (array_unique($manual) as $m) { echo "   ⚠ $m\n"; }
}
echo "\n" . ($APPLY ? 'حُقن' : 'سيُحقن') . " $nCols عمودًا " . ($FN ? 'وظيفيًّا' : 'حاكمًا') . " في $nFiles ملفًّا.\n";

if ($APPLY) {
    /* توثيق توصية ① (الفائض) وتوصية ③ (مصادر اللحاق) */
    $ts = date('Y-m-d');
    if ($overflowLog) {
        $f = $ROOT . '/docs/CMP03_OVERFLOW_LOG_ar.md';
        $txt = is_file($f) ? file_get_contents($f)
            : "# CMP-03 — سجل فائض الأعمدة فوق 22 (توصية المالك ①)\n\nالفائض يحمل `class=\"none\"` فينهار لسطرٍ تابعٍ عبر DataTables Responsive — يظهر بزر التوسيع في أول الصف.\n\n| الملف | العمود | موضعه |\n|---|---|---|\n";
        foreach ($overflowLog as $o) { $txt .= "| {$o[0]} | {$o[1]} | {$o[2]} |\n"; }
        file_put_contents($f, $txt);
        echo "✎ وُثّق الفائض (" . count($overflowLog) . ") في docs/CMP03_OVERFLOW_LOG_ar.md\n";
    }
    if ($followups) {
        $f = $ROOT . '/docs/CMP03_FOLLOWUP_SOURCES_ar.md';
        $txt = is_file($f) ? file_get_contents($f)
            : "# CMP-03 — سجل مصادر اللحاق (توصية المالك ③)\n\nكل عمودٍ محقونٍ يعرض «—» حتى يُربط مصدره الصفّي (سلسلة الاعتماد · fin_event_links · publishFact · action_execution_log…). كل صفٍّ هنا مهمةُ ربطٍ تلحق.\n\n| الملف | الشاشة | العمود | المفتاح |\n|---|---|---|---|\n";
        foreach ($followups as $fu) { $txt .= "| {$fu[0]} | {$fu[1]} | {$fu[2]} | `{$fu[3]}` |\n"; }
        file_put_contents($f, $txt);
        echo "✎ سُجّلت مهام اللحاق (" . count($followups) . ") في docs/CMP03_FOLLOWUP_SOURCES_ar.md\n";
    }
}
