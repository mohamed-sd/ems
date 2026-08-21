<?php
/**
 * tools/injfix02_capability_gate.php — INJ-FIX-02 · بوابةُ مصالحةِ القدرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الملحقُ ترك هذه الورقةَ فارغةً** («تُملأ من قائمةِ الـ١٨٤ اسمًا»)، ونصَّ
 *   على أن **مقامَ البناءِ لا يُعرف قبلَها**: «كلُّ اسمٍ يُكيَّف غيرَ
 *   `REQUIRED_AND_MISSING` يخرج من مقامِ البناء — فالرقمُ النهائيُّ أقلُّ من
 *   ١٨٤ يقينًا». فهذه الأداةُ تملؤها **بقياسٍ لا برأي**.
 *
 * ◆ **وهي قياسٌ لا بناء**: لا تُنشئ شاشةً ولا قدرةً، ولا تكتب في القاعدة. وهي
 *   بذلك **داخلَ تفويضِ INJ-FIX-01** وإن كان الملحقُ نفسُه مسودةً — لأن سؤالَ
 *   «ما الغائبُ حقًّا؟» شرطٌ لأيِّ قرارٍ للمالك، لا تنفيذٌ لقرارٍ لم يُتَّخذ.
 *
 * ◆ **والحكمُ الآليُّ لا يُغني عن البشر**: يُخرج لكلِّ اسمٍ **مرشَّحًا ودليلَه
 *   ودرجةَ ثقة**. وما لا مرشَّحَ له يُوسَم `REQUIRED_AND_MISSING (مبدئيّ)` —
 *   **مبدئيٌّ لأن غيابَ المرشَّحِ الآليِّ ليس إثباتَ غيابِ الوظيفة**.
 *
 * التشغيل: php tools/injfix02_capability_gate.php [--json] [--csv]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$EX   = $ROOT . '/docs/baseline_20260821/extract/';
$JSON = in_array('--json', $argv, true);
$CSV  = in_array('--csv', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ══ ① الأسطحُ الحيّةُ على القرص ══════════════════════════════════════════ */
/* ◆ **خريطتان لا واحدة** — وخلطُهما أفسد أولَ تشغيل:
 *   · `$anyPhp` تجيب «أهذا الملفُّ موجودٌ أصلًا؟» — وعليها يقوم مقامُ الـ١٨٤
 *     المُتحقَّقُ منه، فلا يتغيّر.
 *   · `$live` تجيب «أيصلح هذا مرشَّحًا بديلًا؟» — **وأسطحٌ فقط**. فأولُ تشغيلٍ
 *     رشَّح `database/` و`includes/` فقابل `risk_dept_iaf.php` بملفِّ **هجرة**
 *     و`my_workspace_v2.php` بملفِّ عون. **والقدرةُ لا تُنفَّذ في هجرة.** */
$anyPhp = array(); $live = array();
$SKIP_ALL  = array('vendor', 'node_modules', '.git', '.claude', 'logs', 'storage');
$SKIP_SURF = array('tools', 'tests', 'includes', 'app', 'vendor', 'database', 'docs', 'storage',
    'node_modules', 'assets', '.git', '.claude', 'logs', 'emsreports', 'install', 'examples', 'chats');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP_ALL, true)) { continue; }
    $anyPhp[mb_strtolower(basename($rel))] = $rel;
    if ($top !== '' && in_array($top, $SKIP_SURF, true)) { continue; }
    $live[mb_strtolower(basename($rel))] = $rel;
}

/* ══ ② التسمياتُ العربيةُ الحيّةُ من التنقّل — لكلِّ مسارٍ مصيَّر ═════════ */
$labelOf = array();              /* basename => [labels] */
$q = $conn->query("SELECT route, label_ar FROM `nav_items` WHERE active = 1 AND COALESCE(label_ar,'') <> ''");
while ($q && $x = $q->fetch_assoc()) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $x['route'])));
    if ($b === '') { continue; }
    $labelOf[$b][mb_strtolower(trim($x['label_ar']))] = 1;
}

/* ══ ③ رموزُ `?view=` المُعلَنة — أسطحٌ ابنةٌ لا ملفاتٌ مستقلة ═════════════ */
$views = array();                /* view-token => parent basename */
foreach ($live as $b => $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (strpos($src, "'view'") === false && strpos($src, '"view"') === false) { continue; }
    if (preg_match_all("/['\"]([a-z0-9_]{3,40})['\"]\s*=>/i", $src, $m)) {
        foreach ($m[1] as $tok) { $views[mb_strtolower($tok)][$b] = 1; }
    }
}

/* ══ ④ الغائبُ من دفترِ الدورة ═════════════════════════════════════════════ */
$miss = array();
$q = $conn->query("SELECT screen_file, screen_title, dept_name, stage_name FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_assoc()) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', trim((string) $x['screen_file']))));
    if ($b === '' || isset($anyPhp[$b])) { continue; }
    if (!isset($miss[$b])) {
        /* العنوانُ يحمل «(اسم_الملف.php)» لاحقةً — تُنزع فيبقى المعنى */
        $t = preg_replace('/\s*\([^)]*\.php\s*\)\s*$/u', '', (string) $x['screen_title']);
        $miss[$b] = array('title' => trim($t), 'depts' => array(), 'stages' => array(), 'n' => 0);
    }
    $miss[$b]['n']++;
    $miss[$b]['depts'][(string) $x['dept_name']] = 1;
    $miss[$b]['stages'][(string) $x['stage_name']] = 1;
}
ksort($miss);

/* ══ ⑤ التكييفُ — مرشَّحٌ ودليلٌ ودرجةُ ثقة ════════════════════════════════ */
function toks($s)
{
    $s = mb_strtolower(preg_replace('/\.php$/i', '', $s));
    $p = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
    /* بادئاتٌ لا تحمل معنًى وظيفيًّا — إسقاطُها يمنع تطابقًا زائفًا بالبادئةِ وحدَها */
    $stop = array('php', 'fin', 'acc', 'ar', 'tre', 'ctrl', 'gov', 'iaf', 'op', 'unit');
    return array_values(array_diff($p, $stop));
}
/**
 * رموزُ المعنى العربيِّ — تُنزع التشكيلُ وأدواتُ التعريفِ وحروفُ العطف، ويُوحَّد
 * رسمُ الألفِ والهاءِ والياء. فـ«بوابةُ المبيعات» و«بوابة المبيعات» كلمةٌ واحدة.
 */
function ar_toks($s)
{
    $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s);
    $s = strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'));
    $s = preg_replace('/[^\p{Arabic}\s]+/u', ' ', $s);
    $p = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY);
    $out = array();
    /* أدواتٌ ورabطٌ لا تحمل معنًى مميِّزًا — بقاؤها يُنتج تطابقًا زائفًا */
    $stop = array('في', 'من', 'الى', 'على', 'عن', 'و', 'او', 'ما', 'التي', 'الذي', 'هذا', 'هذه', 'كل');
    foreach ($p as $w) {
        $w = preg_replace('/^(وال|بال|فال|كال|لل|ال)/u', '', $w);   /* نزعُ التعريفِ ولواحقِه */
        if (mb_strlen($w) < 3 || in_array($w, $stop, true)) { continue; }
        $out[$w] = 1;
    }
    return array_keys($out);
}

$liveToks = array();
foreach ($live as $b => $rel) { $liveToks[$b] = toks($b); }

$out = array(); $tally = array();
foreach ($miss as $b => $m) {
    $kind = 'REQUIRED_AND_MISSING'; $cand = ''; $why = ''; $conf = '';

    /* ⓐ سطحٌ ابنٌ مُعلَنٌ بـ?view= */
    $stem = preg_replace('/\.php$/i', '', $b);
    if (isset($views[$stem])) {
        $kind = 'TAB_OR_CHILD_SURFACE'; $cand = implode(' · ', array_keys($views[$stem]));
        $why = "رمزُ `?view={$stem}` مُعلَنٌ في سطحٍ حيّ"; $conf = 'عالية';
    }

    /* ⓑ التسميةُ العربيةُ نفسُها حيّةٌ على مسارٍ آخر */
    if ($kind === 'REQUIRED_AND_MISSING' && $m['title'] !== '') {
        $t = mb_strtolower($m['title']);
        foreach ($labelOf as $lb => $labels) {
            if (isset($labels[$t])) {
                $kind = 'IMPLEMENTED_UNDER_OTHER_ROUTE'; $cand = $live[$lb] ?? $lb;
                $why = "التسميةُ «{$m['title']}» حيّةٌ في التنقّلِ على مسارٍ آخر"; $conf = 'عالية';
                break;
            }
        }
    }

    /* ⓑ-٢ تداخلُ **المعنى العربيّ** — والاسمُ التقنيُّ يخدع حيثُ لا يخدع المعنى.
     *   ◆ هذا هو ما أمسك فخَّ الملحقِ نفسِه: `unit_sales_gate.php` عنوانُه
     *     «بوابة مدير المبيعات»، وهو حيٌّ باسمِ `Approvals/hours_approval.php`
     *     «اعتمادُ ساعاتِ الوحدات (بوابةُ المبيعات)». والمطابقةُ بالاسمِ التقنيِّ
     *     تُسقطه، فيُبنى ثانيةً — **وهو عينُ الازدواجِ الذي حذّر منه الملحق**. */
    if ($kind === 'REQUIRED_AND_MISSING' && $m['title'] !== '') {
        $at = ar_toks($m['title']);
        if (count($at) >= 2) {
            $best = ''; $bestN = 0;
            foreach ($labelOf as $lb => $labels) {
                if (!isset($live[$lb])) { continue; }
                foreach (array_keys($labels) as $L) {
                    $n = count(array_intersect($at, ar_toks($L)));
                    if ($n > $bestN) { $bestN = $n; $best = $lb; }
                }
            }
            if ($bestN >= 2) {
                $kind = 'IMPLEMENTED_UNDER_OTHER_ROUTE'; $cand = $live[$best];
                $why = "تداخلُ {$bestN} كلمةً في **المعنى العربيّ** مع تسميةٍ حيّة";
                $conf = 'متوسطة — تحتاج حسمَ بشر';
            }
        }
    }

    /* ⓒ تداخلُ رموزِ الاسم مع سطحٍ حيّ — كلمتان فأكثرُ أو كلمةٌ نادرة */
    if ($kind === 'REQUIRED_AND_MISSING') {
        $mt = toks($b); $best = ''; $bestN = 0;
        if ($mt) {
            foreach ($liveToks as $lb => $lt) {
                $n = count(array_intersect($mt, $lt));
                if ($n > $bestN) { $bestN = $n; $best = $lb; }
            }
        }
        if ($bestN >= 2) {
            $kind = 'IMPLEMENTED_UNDER_OTHER_ROUTE'; $cand = $live[$best] ?? $best;
            $why = "تداخلُ {$bestN} رمزًا في الاسمِ مع سطحٍ حيّ"; $conf = 'متوسطة — تحتاج حسمَ بشر';
        } elseif ($bestN === 1 && $best !== '') {
            $cand = $live[$best] ?? $best;
            $why = "مرشَّحٌ ضعيفٌ برمزٍ واحد"; $conf = 'ضعيفة';
        }
    }
    if ($kind === 'REQUIRED_AND_MISSING' && $conf === '') { $conf = 'لا مرشَّحَ آليّ'; }

    $tally[$kind] = ($tally[$kind] ?? 0) + 1;
    $out[] = array('file' => $b, 'title' => $m['title'], 'appearances' => $m['n'],
        'depts' => implode(' · ', array_keys($m['depts'])),
        'kind' => $kind, 'candidate' => $cand, 'evidence' => $why, 'confidence' => $conf);
}

/* ══ ⑥ الإخراج ════════════════════════════════════════════════════════════ */
echo "══ INJ-FIX-02 · بوابةُ مصالحةِ القدرة — تكييفُ الـ" . count($out) . " اسمًا ══\n\n";
arsort($tally);
$reqMissing = $tally['REQUIRED_AND_MISSING'] ?? 0;
foreach ($tally as $k => $v) { printf("  %-34s %3d\n", $k, $v); }
echo "\n";
printf("◆ مقامُ البناءِ المبدئيُّ: **%d** من %d — والباقي (%d) له مرشَّحٌ قائمٌ يُحسم بشريًّا\n",
    $reqMissing, count($out), count($out) - $reqMissing);
echo "◆ ولا يُبنى اسمٌ حتى يُحسم تكييفُه — «بناءُ ما هو موجودٌ باسمٍ آخرَ أسوأُ من تركِه».\n";

echo "\n── أثقلُ ما لا مرشَّحَ له (بالظهورات) ──\n";
$rm = array_values(array_filter($out, function ($r) { return $r['kind'] === 'REQUIRED_AND_MISSING'; }));
usort($rm, function ($a, $b) { return $b['appearances'] - $a['appearances']; });
foreach (array_slice($rm, 0, 25) as $r) {
    printf("  %-30s %2d ظهورًا  %s\n", $r['file'], $r['appearances'], mb_substr($r['title'], 0, 44));
}

if ($CSV) {
    $p = $ROOT . '/docs/INJFIX01/evidence/NF-01_capability_gate.csv';
    $fh = fopen($p, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('اسم الملف', 'العنوان', 'ظهورات', 'الإدارات', 'التكييف', 'المرشَّح البديل', 'الدليل', 'الثقة'));
    foreach ($out as $r) { fputcsv($fh, array_values($r)); }
    fclose($fh);
    echo "\n↦ {$p}\n";
}
if ($JSON) {
    if (!is_dir($EX)) { mkdir($EX, 0777, true); }
    file_put_contents($EX . 'injfix02_capability_gate.json',
        json_encode(array('tally' => $tally, 'build_denominator_provisional' => $reqMissing,
            'rows' => $out), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "↦ {$EX}injfix02_capability_gate.json\n";
}
