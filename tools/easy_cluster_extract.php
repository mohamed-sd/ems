<?php
/**
 * tools/easy_cluster_extract.php — البنودُ «السهلة» بعناقيدِها القابلةِ للإغلاقِ دفعةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تقريرُ الحالةِ يصنّف **114 بندًا «سهلًا»**: موضعٌ واحدٌ وتغييرٌ ميكانيكيّ.
 *   وإغلاقُها بندًا بندًا بشاهدٍ لكلٍّ يستهلك جهدًا لا يقابله عائدٌ — بينما
 *   **العنقودُ** (بنودٌ عيبُها واحدٌ وشاهدُها واحد) يُغلق دفعةً بمِسبارٍ واحد.
 *
 * ◆ فتُجمَع بـ**نوعِ الفجوة** ثم بـ**الشاشةِ** ثم بـ**نمطِ العيب**، ويُخرَج لكلِّ
 *   عنقودٍ حجمُه وشاهدُه المرشَّح — ليُبدأ بأكبرِ عنقودٍ أوضحِ شاهد.
 *
 * ◆ ولا يُصنَّف بندٌ «سهلًا» لأن السجلَّ قال ذلك: يُطبَع نصُّ إصلاحِه ليُقرأ.
 *   (وقد سبق أن نقض القياسُ تصنيفَ «فجوةِ الدليل» في 58 بندًا.)
 *
 * التشغيل: php tools/easy_cluster_extract.php [--md=مسار] [--kind=النوع]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$mdOut = null; $onlyKind = null;
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); }
    if (strpos($a, '--kind=') === 0) { $onlyKind = substr($a, 7); }
}
$L = array();
function o($s = '') { global $L; $L[] = $s; echo $s . "\n"; }

/* قوائمُ التصنيفِ نفسُها المستعملةُ في تقريرِ الحالة — تُقرأ لا تُنسخ */
$rep = (string) file_get_contents($ROOT . '/tools/fix_status_report.php');
$CLOSED = array();
if (preg_match('/\$CLOSED\s*=\s*array\((.*?)\n\);/s', $rep, $m)) {
    if (preg_match_all("/'(INJ-\d+)'/", $m[1], $cm)) { $CLOSED = array_unique($cm[1]); }
}
$EASY_KINDS = array('Wrong Label', 'Broken Button', 'Wrong Sidebar Placement', 'Missing Evidence');
$COVERED_KINDS = array('Permission Gap', 'Governance Gap');

$rows = array();
foreach (file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES) as $i => $line) {
    if ($i < 2) { continue; }
    $r = explode("\t", $line);
    if (count($r) < 30 || strpos($r[0], 'INJ-') !== 0) { continue; }
    $id = trim($r[0]); $kind = trim($r[9]); $sev = trim($r[10]);
    if (in_array($id, $CLOSED, true)) { continue; }
    if (in_array($kind, $COVERED_KINDS, true) && in_array($sev, array('P0', 'P1'), true)) { continue; }
    if (!in_array($kind, $EASY_KINDS, true) || $sev === 'P0') { continue; }
    if ($onlyKind !== null && $kind !== $onlyKind) { continue; }
    $rows[] = array('id' => $id, 'kind' => $kind, 'sev' => $sev, 'dept' => trim($r[3]),
                    'screen' => trim($r[4]), 'route' => trim($r[5]),
                    'defect' => trim($r[8]), 'fix' => trim($r[16]), 'test' => trim($r[20]),
                    'src' => trim($r[27]));
}

o('══════════════════════════════════════════════════════════════════════');
o(' البنودُ «السهلة» بعناقيدها — ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');
o('');
o('المفتوحُ السهلُ: **' . count($rows) . '** بندًا');

/* ══ ① بنوعِ الفجوة ═══════════════════════════════════════════════════ */
$byKind = array();
foreach ($rows as $r) { $byKind[$r['kind']][] = $r; }
o('');
o('| نوعُ الفجوة | عدد | شاهدٌ مرشَّح |');
o('|---|---:|---|');
$WITNESS = array(
    'Wrong Sidebar Placement' => '`fix_nav_href_probe` + مِسبارُ موضعٍ جديدٌ يقابل الموضعَ المعتمَدَ بالحيّ',
    'Wrong Label'             => '`fix_nav_label_probe` (قائمٌ · يُبلّغ 27 انفصالًا)',
    'Broken Button'           => 'تصييرٌ حيٌّ + فحصُ وجودِ معالجِ الفعلِ في `actions`',
    'Missing Evidence'        => 'مِسبارٌ يقرأ اختبارَ القبولِ ويُثبته على النظامِ الحيّ',
);
arsort($byKind);
foreach ($byKind as $k => $v) {
    o('| ' . $k . ' | **' . count($v) . '** | ' . (isset($WITNESS[$k]) ? $WITNESS[$k] : '—') . ' |');
}

/* ══ ② أكبرُ العناقيدِ بالشاشةِ داخلَ كلِّ نوع ═══════════════════════════ */
o('');
o('╔══ ② العناقيدُ — بنودٌ تتشارك الشاشةَ أو الإدارةَ فتُغلق دفعةً');
foreach ($byKind as $k => $v) {
    $byScreen = array();
    foreach ($v as $r) { $byScreen[$r['screen'] !== '' ? $r['screen'] : '—'][] = $r['id']; }
    arsort($byScreen);
    $multi = array_filter($byScreen, static function ($x) { return count($x) > 1; });
    o('');
    o('### ' . $k . ' (' . count($v) . ')');
    if ($multi) {
        o('  عناقيدُ شاشةٍ واحدة:');
        $i = 0;
        foreach ($multi as $sc => $ids) {
            if (++$i > 6) { break; }
            o('    · **' . count($ids) . '** بندًا في «' . mb_substr((string) $sc, 0, 44) . '» — ' . implode('، ', $ids));
        }
    } else { o('  (لا عنقودَ شاشةٍ — كلُّ بندٍ شاشتُه)'); }
    $byDept = array();
    foreach ($v as $r) {
        $d = $r['dept'] !== '' ? $r['dept'] : '—';
        $byDept[$d] = isset($byDept[$d]) ? $byDept[$d] + 1 : 1;   // ++ على مفتاحٍ غيرِ موجودٍ ينبّه
    }
    arsort($byDept);
    $top = array(); $i = 0;
    foreach ($byDept as $d => $c) { if (++$i > 4) { break; } $top[] = $d . '=' . $c; }
    o('  بالإدارات: ' . implode(' · ', $top));
}

/* ══ ③ عيّنةٌ من نصوصِ الإصلاحِ — لتُقرأ لا تُفترَض ══════════════════════ */
o('');
o('╔══ ③ عيّنةُ نصوصِ الإصلاح (ثلاثةٌ من كلِّ نوع)');
foreach ($byKind as $k => $v) {
    o('');
    o('### ' . $k);
    $i = 0;
    foreach ($v as $r) {
        if (++$i > 3) { break; }
        o('- **' . $r['id'] . '** · ' . $r['sev'] . ' · ' . mb_substr($r['screen'], 0, 40));
        o('  - العيب: ' . mb_substr(preg_replace('/\s+/u', ' ', $r['defect']), 0, 150));
        o('  - الإصلاح: ' . mb_substr(preg_replace('/\s+/u', ' ', $r['fix']), 0, 170));
        o('  - القبول: ' . mb_substr(preg_replace('/\s+/u', ' ', $r['test']), 0, 170));
    }
}

o('');
o(str_repeat('═', 70));
o('◆ القرار: يُبدأ بأكبرِ عنقودٍ **أوضحِ شاهدًا** — لا بأكبرِ عددٍ مجرَّدًا.');
o(str_repeat('═', 70));

if ($mdOut) { file_put_contents($mdOut, "# البنودُ السهلةُ بعناقيدها\n\n" . implode("\n", $L) . "\n"); echo "\nكُتب: {$mdOut}\n"; }
exit(0);
