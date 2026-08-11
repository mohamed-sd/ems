<?php
/**
 * tools/evidence_gap_extract.php — استخراجُ «فجوةِ الدليل» بمعاييرِ قبولِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ السجلُّ يصنّف 58 ملاحظةً «Implementation Closed · Evidence Open» — أي أن
 *   الشيفرةَ **قائمةٌ** والناقصَ **شاهدٌ يُشغَّل**. فهي أرخصُ إغلاقٍ صادقٍ في
 *   السجلِّ كلِّه: لا كودَ يُكتب بل قياسٌ يُبنى.
 *
 * ◆ ولا يُغلق بندٌ منها لأن السجلَّ يقول «التنفيذُ مُغلق» — بل **يُجَسُّ**: يُقرأ
 *   اختبارُ قبولِه ويُبنى له مِسبارٌ يعود صحيحًا أو خطأً على النظامِ الحيّ. فقولُ
 *   الوثيقةِ دعوى، والمِسبارُ حكم.
 *
 * ◆ ويُخرج الملفَّ مصنَّفًا بما يلزم كلَّ بندٍ من **نوعِ شاهد**، فيُجمع المتشابهُ
 *   في مِسبارٍ واحدٍ بدل 58 مِسبارًا.
 *
 * التشغيل: php tools/evidence_gap_extract.php [--md=مسار] [--tsv=مسار]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$mdOut = null; $tsvOut = null;
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); }
    if (strpos($a, '--tsv=') === 0) { $tsvOut = substr($a, 6); }
}
$L = array();
function o($s = '') { global $L; $L[] = $s; echo $s . "\n"; }

$rows = array();
foreach (file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES) as $i => $line) {
    if ($i < 2) { continue; }
    $r = explode("\t", $line);
    if (count($r) < 30 || strpos($r[0], 'INJ-') !== 0) { continue; }
    if (strpos(trim($r[28]), 'دليل') === false) { continue; }
    $rows[] = array(
        'id'     => trim($r[0]),
        'doc'    => trim($r[1]),
        'dept'   => trim($r[3]),
        'screen' => trim($r[4]),
        'route'  => trim($r[5]),
        'defect' => trim($r[8]),
        'kind'   => trim($r[9]),
        'sev'    => trim($r[10]),
        'evidence' => trim($r[13]),
        'fix'    => trim($r[16]),
        'test'   => trim($r[20]),
        'src'    => trim($r[27]),
        'close'  => trim($r[29]),
    );
}

/* ◆ تصنيفُ نوعِ الشاهدِ اللازم — من نصِّ اختبارِ القبولِ نفسِه لا من ظنّ */
function witness_kind($t, $fix, $defect)
{
    $s = $t . ' ' . $fix . ' ' . $defect;
    if (preg_match('/لقطة|screenshot|بصريًّا|بصري|يُرى|مرئي/u', $s)) { return 'بصريّ (لقطةٌ أو قياسٌ في المتصفح)'; }
    if (preg_match('/اختبار|test|حزمة|سكربت|يُشغَّل/u', $s)) { return 'اختبارٌ آليّ'; }
    if (preg_match('/سجل|log|أثر|تدقيق|audit/u', $s)) { return 'سجلُّ أثرٍ في القاعدة'; }
    if (preg_match('/استعلام|صفر|عدد|COUNT|جدول|عمود/u', $s)) { return 'استعلامٌ على القاعدة'; }
    if (preg_match('/وثيق|مستند|توثيق|README|docs/u', $s)) { return 'توثيقٌ مكتوب'; }
    return 'غيرُ مصنَّفٍ — يُقرأ يدويًّا';
}

o('══════════════════════════════════════════════════════════════════════');
o(' فجوةُ الدليل — ' . count($rows) . ' ملاحظةً · ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');

/* ① التوزيعُ بنوعِ الشاهد */
$byW = array(); $bySrc = array(); $bySev = array(); $byDept = array();
foreach ($rows as $k => $r) {
    $w = witness_kind($r['test'], $r['fix'], $r['defect']);
    $rows[$k]['witness'] = $w;
    $byW[$w] = isset($byW[$w]) ? $byW[$w] + 1 : 1;
    $bySrc[$r['src']] = isset($bySrc[$r['src']]) ? $bySrc[$r['src']] + 1 : 1;
    $bySev[$r['sev']] = isset($bySev[$r['sev']]) ? $bySev[$r['sev']] + 1 : 1;
    $byDept[$r['dept']] = isset($byDept[$r['dept']]) ? $byDept[$r['dept']] + 1 : 1;
}
o('');
o('╔══ ① نوعُ الشاهدِ اللازم — وبه تُجمَع في مسابرَ قليلةٍ لا 58');
o('');
o('| نوعُ الشاهد | عدد |');
o('|---|---:|');
arsort($byW);
foreach ($byW as $w => $n) { o('| ' . $w . ' | **' . $n . '** |'); }

o('');
o('╔══ ② بالخطورةِ والمصدرِ والإدارة');
o('');
ksort($bySev);
$line = array();
foreach ($bySev as $s => $n) { $line[] = $s . '=' . $n; }
o('  الخطورة: ' . implode(' · ', $line));
arsort($bySrc);
$line = array();
foreach ($bySrc as $s => $n) { $line[] = $s . '=' . $n; }
o('  المصدر:  ' . implode(' · ', $line));
arsort($byDept);
$line = array(); $i = 0;
foreach ($byDept as $s => $n) { if (++$i > 6) { break; } $line[] = ($s !== '' ? $s : '—') . '=' . $n; }
o('  الإدارات: ' . implode(' · ', $line));

/* ③ القائمةُ كاملةً */
o('');
o('╔══ ③ القائمةُ كاملةً — لكلٍّ اختبارُ قبولِه');
o('');
foreach ($rows as $r) {
    o('### ' . $r['id'] . ' · ' . $r['sev'] . ' · ' . ($r['dept'] !== '' ? $r['dept'] : '—'));
    o('- **الشاشة:** ' . ($r['screen'] !== '' ? $r['screen'] : '—') . ' (`' . $r['route'] . '`)');
    o('- **العيب:** ' . mb_substr($r['defect'], 0, 220));
    o('- **الإصلاحُ المطلوب:** ' . mb_substr($r['fix'], 0, 220));
    o('- **اختبارُ القبول:** ' . mb_substr($r['test'], 0, 260));
    o('- **نوعُ الشاهد:** ' . $r['witness'] . ' · **المصدر:** ' . $r['src']);
    o('');
}

o(str_repeat('═', 70));
o('المجموع: ' . count($rows) . ' ملاحظةَ «فجوةِ دليل» — تنفيذُها مُعلَنٌ تامًّا، والناقصُ شاهد.');
o('◆ ولا يُغلق بندٌ منها بقولِ السجل: يُبنى له مِسبارٌ يعود صحيحًا على النظامِ الحيّ.');
o(str_repeat('═', 70));

if ($tsvOut) {
    $out = array("id\tsev\tdept\tscreen\troute\twitness\tsrc\ttest");
    foreach ($rows as $r) {
        $out[] = implode("\t", array($r['id'], $r['sev'], $r['dept'], $r['screen'], $r['route'],
            $r['witness'], $r['src'], preg_replace('/\s+/u', ' ', $r['test'])));
    }
    file_put_contents($tsvOut, implode("\n", $out));
    echo "كُتب TSV: {$tsvOut}\n";
}
if ($mdOut) { file_put_contents($mdOut, "# فجوةُ الدليل — القائمةُ ومعاييرُ القبول\n\n" . implode("\n", $L) . "\n"); echo "كُتب: {$mdOut}\n"; }
exit(0);
