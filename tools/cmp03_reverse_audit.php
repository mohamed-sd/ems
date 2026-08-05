<?php
/**
 * tools/cmp03_reverse_audit.php — المراجعة العكسية لـCMP-03 (المستند ← الشاشات)
 * ───────────────────────────────────────────────────────────────────────────
 * لا تثق بمقياس scrdes_compare: تنطلق من المستند عمودًا عمودًا وتحاكم الجدولَ
 * الرئيس وحده بصرامةٍ أشد من المقارن الذي يجمع <th> من الملف كله:
 *   ✔ في الجدول الرئيس (البنية الصحيحة)
 *   ≈ مرادف بنيوي مجمد بقرار (J-03/J-06 — قائمة التجميد نفسها)
 *   ⚠ خارج الجدول الرئيس — رأسه في جدولٍ آخر بالملف (مودال/تفاصيل): نجاحٌ
 *     واهنٌ كان المقياس يحسبه مطابقًا — يُكشف هنا
 *   ✘ غائب من الملف كله
 * ويقيس أمانةَ الترتيب: أطول تتابعٍ من أعمدة المستند محفوظ الترتيب في الجدول
 * الرئيس (LIS) نسبةً إلى المطابق.
 *
 * التشغيل: php tools/cmp03_reverse_audit.php [--full]  (--full يفصّل كل شاشة)
 * المخرج: docs/CMP03_REVERSE_AUDIT_ar.md
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$FULL = in_array('--full', $argv, true);

/* المرادفات البنيوية المجمدة بقرار (J-03/J-06): تسمية المستند المحرومة ← الرأس
   المثبَّت الذي يقوم مقامها (شاشتا مستندٍ مدمجتان بملفٍ واحد) */
$FROZEN = array(
    'Contracts/tax_invoices.php'        => array('الفترة' => 'فترة الإقرار'),
    'Suppliers/supplier_capacity.php'   => array('نسبة الجاهزية' => 'نسبة الجاهزية الدنيا'),
    'Operations/distribution_space.php' => array('من تاريخ' => 'التاريخ', 'إلى تاريخ' => 'تاريخ السريان', 'الموقع' => 'من الموقع'),
    'Suppliers/shares_coverage.php'     => array('العقد العميل' => 'العقد'),
    'Finance/entitlement_gate.php'      => array('رقم الحدث' => 'رقم الحدث المولَّد'),
);

/** كل جداول الملف: [ ['ths'=>[نصوص الصف الأول], 'marks'=>[محقوننا؟], 'all'=>[نصوص كل صفوف الترويسة]] ] */
function ra_tables($src) {
    $out = array();
    if (!preg_match_all('/<thead\b[^>]*>(.*?)<\/thead>/su', $src, $mt)) { return $out; }
    foreach ($mt[1] as $theadContent) {
        $entry = array('ths' => array(), 'marks' => array(), 'all' => array());
        if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/su', $theadContent, $mtrAll)) {
            foreach ($mtrAll[1] as $ti => $trContent) {
                if (preg_match_all('/<th\b([^>]*)>(.*?)<\/th>/su', $trContent, $mth)) {
                    foreach ($mth[2] as $i => $h) {
                        $h = preg_replace('/<\?php.*?\?>/su', '', $h);
                        $h = trim(strip_tags($h));
                        if ($h === '') { continue; }
                        if ($ti === 0) {
                            $entry['ths'][] = $h;
                            $entry['marks'][] = (bool) preg_match('/data-(gov|fn)=/', $mth[1][$i]);
                        }
                        $entry['all'][] = $h;
                    }
                }
            }
        }
        if ($entry['all']) { $out[] = $entry; }
    }
    return $out;
}

/** أطول تتابع صاعد (LIS) — أمانة الترتيب */
function ra_lis($seq) {
    $tails = array();
    foreach ($seq as $v) {
        $lo = 0; $hi = count($tails);
        while ($lo < $hi) { $mid = intdiv($lo + $hi, 2); if ($tails[$mid] < $v) { $lo = $mid + 1; } else { $hi = $mid; } }
        $tails[$lo] = $v;
    }
    return count($tails);
}

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$uiNorm = array();
foreach (cmp03_ui_cols() as $u) { $uiNorm[cmp03_norm($u)] = 1; }

$T = array('screens' => 0, 'cols' => 0, 'main' => 0, 'frozen' => 0, 'weak' => 0, 'ours' => 0,
           'missing' => 0, 'orderOk' => 0, 'orderAll' => 0, 'noTable' => 0);
$findWeak = array(); $findOurs = array(); $findMissing = array(); $findOrder = array(); $lines = array();

foreach ($screens as $cf => $sc) {
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $real = $map[$cf]['real_path'];
    $src = @file_get_contents($ROOT . '/' . $real);
    if ($src === false) { $lines[] = "‼ $cf → $real لا يُقرأ"; continue; }
    $tables = ra_tables($src);
    /* أعمدة المستند بلا أعمدة الواجهة */
    $docCols = array();
    foreach ($sc['cols'] as $c) { if (!isset($uiNorm[cmp03_norm($c)])) { $docCols[] = $c; } }
    if (!$docCols) { continue; }
    if (!$tables) { $T['noTable']++; continue; } // لوحة

    /* الجدول الرئيس: الأكثر تقاطعًا حرفيًّا مع المستند (الصف الأول) */
    $best = null; $bestHit = -1; $bestIdx = -1;
    foreach ($tables as $ti => $tb) {
        $set = array();
        foreach ($tb['ths'] as $h) { $set[cmp03_norm($h)] = 1; }
        $hit = 0;
        foreach ($docCols as $c) { if (isset($set[cmp03_norm($c)])) { $hit++; } }
        if ($hit > $bestHit) { $bestHit = $hit; $best = $tb; $bestIdx = $ti; }
    }
    $mainPos = array(); // norm → position
    foreach ($best['ths'] as $i => $h) { $n = cmp03_norm($h); if (!isset($mainPos[$n])) { $mainPos[$n] = $i; } }
    /* أين يقع كل رأسٍ في الملف — وهل هو من حقننا (data-gov/data-fn)؟ */
    $allNorm = array(); $oursOutside = array();
    foreach ($tables as $ti => $tb) {
        foreach ($tb['all'] as $h) { $allNorm[cmp03_norm($h)] = 1; }
        if ($ti === $bestIdx) { continue; }
        foreach ($tb['ths'] as $i => $h) {
            if (!empty($tb['marks'][$i])) { $oursOutside[cmp03_norm($h)] = 1; }
        }
    }
    /* المجمدات بالاتجاهين: عمود المستند المجمد يقبل رأسه المثبَّت بديلًا */
    $frozenHere = array();
    if (isset($FROZEN[$real])) {
        foreach ($FROZEN[$real] as $docSide => $fixedSys) {
            $frozenHere[cmp03_norm($docSide)] = $fixedSys;
        }
    }

    $T['screens']++;
    $sMain = 0; $sFrozen = 0; $sWeak = 0; $sOurs = 0; $sMiss = 0; $posSeq = array();
    foreach ($docCols as $c) {
        $n = cmp03_norm($c);
        $T['cols']++;
        if (isset($mainPos[$n])) { $sMain++; $T['main']++; $posSeq[] = $mainPos[$n]; continue; }
        if (isset($frozenHere[$n]) && isset($allNorm[cmp03_norm($frozenHere[$n])])) {
            $sFrozen++; $T['frozen']++; continue;
        }
        if (isset($oursOutside[$n])) {
            $sOurs++; $T['ours']++;
            $findOurs[] = "$real — «{$c}» حقنّاه في جدولٍ غير الرئيس — يُنقل ({$sc['title']})";
            continue;
        }
        if (isset($allNorm[$n])) {
            $sWeak++; $T['weak']++;
            $findWeak[] = "$real — «{$c}» في جدولٍ آخر بالشاشة — سيد/تفصيل بتصميمها ({$sc['title']})";
            continue;
        }
        $sMiss++; $T['missing']++;
        $findMissing[] = "$real — «{$c}» غائبٌ من الملف كله ({$sc['title']})";
    }
    /* أمانة الترتيب على المطابق في الجدول الرئيس */
    $lis = $posSeq ? ra_lis($posSeq) : 0;
    $T['orderOk'] += $lis; $T['orderAll'] += count($posSeq);
    $ordPct = $posSeq ? round($lis * 100 / count($posSeq)) : 100;
    if ($ordPct < 70 && count($posSeq) >= 5) {
        $findOrder[] = "$real — أمانة الترتيب {$ordPct}٪ ({$lis}/" . count($posSeq) . ") ({$sc['title']})";
    }
    if ($FULL || $sWeak || $sMiss || $sOurs) {
        $lines[] = sprintf('%s (%s): رئيس %d · مجمد %d · ✎محقوننا الضال %d · ⚠موزع %d · ✘غائب %d · ترتيب %d٪',
            $sc['title'], $cf, $sMain, $sFrozen, $sOurs, $sWeak, $sMiss, $ordPct);
    }
}

$md = "# CMP-03 — المراجعة العكسية (المستند ← أعمدة جداول الشاشات)\n\n";
$md .= "**التاريخ:** " . date('Y-m-d H:i') . " · **الأداة:** tools/cmp03_reverse_audit.php — محاكمة الجدول الرئيس وحده (أصرم من المقياس)\n\n";
$md .= "| المقام | العدد |\n|---|---|\n";
$md .= "| شاشات حوكمت | {$T['screens']} (+{$T['noTable']} لوحات بلا جدول) |\n";
$md .= "| أعمدة المستند المحاكمة | {$T['cols']} |\n";
$md .= "| ✔ في الجدول الرئيس | {$T['main']} |\n";
$md .= "| ≈ مرادف بنيوي مجمد بقرار | {$T['frozen']} |\n";
$md .= "| ✎ محقوننا في غير الرئيس (يُنقل) | {$T['ours']} |\n";
$md .= "| ⚠ موزع على جداول الشاشة (سيد/تفصيل — يوثق) | {$T['weak']} |\n";
$md .= "| ✘ غائب من الملف | {$T['missing']} |\n";
$md .= "| أمانة الترتيب (LIS على المطابق) | " . ($T['orderAll'] ? round($T['orderOk'] * 100 / $T['orderAll']) : 100) . "٪ ({$T['orderOk']}/{$T['orderAll']}) |\n\n";
foreach (array('✎ محقوننا في غير الرئيس' => $findOurs, '⚠ موزع على جداول الشاشة' => $findWeak, '✘ غائب' => $findMissing, '↕ ترتيب دون 70٪' => $findOrder) as $h => $list) {
    if (!$list) { continue; }
    $md .= "## $h (" . count($list) . ")\n";
    foreach ($list as $f) { $md .= "- $f\n"; }
    $md .= "\n";
}
file_put_contents($ROOT . '/docs/CMP03_REVERSE_AUDIT_ar.md', $md);

foreach ($lines as $l) { echo $l, "\n"; }
echo str_repeat('═', 60), "\n";
echo "شاشات: {$T['screens']} · أعمدة: {$T['cols']} · رئيس: {$T['main']} · مجمد: {$T['frozen']} · ✎ضال: {$T['ours']} · ⚠موزع: {$T['weak']} · ✘غائب: {$T['missing']}\n";
echo "أمانة الترتيب: " . ($T['orderAll'] ? round($T['orderOk'] * 100 / $T['orderAll']) : 100) . "٪\n";
echo "✎ docs/CMP03_REVERSE_AUDIT_ar.md\n";
exit(($T['ours'] + $T['missing']) === 0 ? 0 : 1);
