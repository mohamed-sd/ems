<?php
/**
 * trace_matrix — مصفوفة تتبع المتطلبات ↔ شواهدها (AC-E06-03).
 * ───────────────────────────────────────────────────────────────────────────
 * «صفرُ متطلبٍ بلا اختبار»: كل معرّفِ متطلبٍ في وثائق update0008
 * (AC-* · BR-* · WF-* · SCN-* · IAM-* · UXP-* · TS-* · DEC-*) يُبحث عن
 * شاهده في الشجرة (اختبارات · أحزمة · أدوات · شاشات · خدمات) — والمحصلة
 * CSV كامل + ملخص MD بالمغطى والعاري.
 *
 * التشغيل: php tools/trace_matrix.php --docs=<dir> [--md]
 *   الافتراض: docs/update0008_extracts إن وُجد وإلا يقرأ من --docs.
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);
$DOCS = '';
foreach ($argv as $a) { if (preg_match('/--docs=(.+)/u', $a, $m)) { $DOCS = $m[1]; } }
if ($DOCS === '') { $DOCS = $ROOT . '/docs/update0008_extracts'; }
$MD = in_array('--md', $argv, true);

if (!is_dir($DOCS)) { fwrite(STDERR, "لا مجلد مستخرجات: {$DOCS}\n"); exit(2); }

/* ① حصاد المعرفات من الوثائق */
$ids = array(); // id => [doc, ...]
foreach (glob($DOCS . '/*.txt') as $f) {
    $doc = basename($f, '.txt');
    $txt = (string) file_get_contents($f);
    if (preg_match_all('/\b(AC-[A-Z0-9]+-\d+|BR-[A-Z]+-\d+|WF-\d{2}|SCN-\d+|IAM-\d+|UXP-\d+|TS-\d{2}|DEC-\d{2}|SRC-\d{2}|WFM-\d{3})\b/u', $txt, $m)) {
        foreach ($m[1] as $id) { $ids[$id][$doc] = 1; }
    }
}
ksort($ids);
fwrite(STDOUT, 'معرفات المتطلبات المحصودة: ' . count($ids) . "\n");

/* ② فهرسة الشجرة مرة واحدة (الملفات النصية القابلة للشاهد) */
$corpus = array(); // rel => content
$scanDirs = array('tests', 'tools', 'app', 'includes', 'Portal', 'Operations', 'FinRequests',
    'Finance', 'Governance', 'Approvals', 'docs');
foreach ($scanDirs as $d) {
    $base = $ROOT . '/' . $d;
    if (!is_dir($base)) { continue; }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!$f->isFile()) { continue; }
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, array('php', 'md'), true)) { continue; }
        $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT))), '/');
        if (strpos($rel, '.claude/') === 0 || strpos($rel, 'docs/update0008') === 0) { continue; }
        $corpus[$rel] = (string) file_get_contents($f->getPathname());
    }
}
fwrite(STDOUT, 'ملفات الشواهد المفهرسة: ' . count($corpus) . "\n");

/* ③ المطابقة: أول شاهدٍ اختباري/أداتي يغلب، ثم أي شاهد كودي، ثم توثيقي */
$rows = array(); $covered = 0; $testCovered = 0; $bare = array();
foreach ($ids as $id => $docs) {
    $hitTest = ''; $hitCode = ''; $hitDoc = '';
    foreach ($corpus as $rel => $src) {
        if (strpos($src, $id) === false) { continue; }
        if ($hitTest === '' && (strpos($rel, 'tests/') === 0
            || (strpos($rel, 'tools/') === 0 && (strpos($rel, 'test') !== false || strpos($rel, 'checks') !== false || strpos($rel, 'verify') !== false || strpos($rel, 'guard') !== false)))) {
            $hitTest = $rel;
            break; // الاختباري كافٍ حاكمًا
        }
        if ($hitCode === '' && substr($rel, -4) === '.php') { $hitCode = $rel; }
        if ($hitDoc === '' && substr($rel, -3) === '.md') { $hitDoc = $rel; }
    }
    $evidence = $hitTest !== '' ? $hitTest : ($hitCode !== '' ? $hitCode : $hitDoc);
    $klass = $hitTest !== '' ? 'اختبار/حزام' : ($hitCode !== '' ? 'كود' : ($hitDoc !== '' ? 'توثيق' : 'عارٍ'));
    if ($evidence !== '') { $covered++; }
    if ($hitTest !== '') { $testCovered++; }
    if ($evidence === '') { $bare[] = $id; }
    $rows[] = array($id, implode('·', array_keys($docs)), $klass, $evidence);
}

/* ④ الإخراج */
$csv = fopen($ROOT . '/docs/TRACE_MATRIX_ar.csv', 'w');
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, array('المعرف', 'وثيقته', 'صنف الشاهد', 'الشاهد'));
foreach ($rows as $r) { fputcsv($csv, $r); }
fclose($csv);

$pct = count($ids) ? round($covered * 100 / count($ids), 1) : 0;
$pctT = count($ids) ? round($testCovered * 100 / count($ids), 1) : 0;
$sum = "# مصفوفة تتبع المتطلبات — AC-E06-03\n"
     . "**التاريخ:** " . date('Y-m-d H:i') . " · **المعرفات:** " . count($ids)
     . " · **مغطاة بشاهد:** {$covered} ({$pct}٪) · **بشاهد اختباري/حزامي:** {$testCovered} ({$pctT}٪)\n\n"
     . "المصفوفة الكاملة: `docs/TRACE_MATRIX_ar.csv`\n\n## العارية (" . count($bare) . ")\n";
foreach ($bare as $b) { $sum .= "- `{$b}`\n"; }
if (!$bare) { $sum .= "لا معرّفَ عاريًا — كلُّ متطلبٍ محصودٍ له شاهد.\n"; }
if ($MD) { file_put_contents($ROOT . '/docs/TRACE_MATRIX_ar.md', $sum); }
fwrite(STDOUT, $sum);
exit(count($bare) > 0 ? 1 : 0);
