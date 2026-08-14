<?php
/**
 * tools/fix_permgov_unmeasured.php — استخراجُ «غير مقيس» من تقريرِ الحملةِ السابقة
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ `docs/fix_2026-08/EVIDENCE_CAMPAIGN_COVERED_2026-08-13.md` ويصنّف كلَّ
 * بندٍ غيرِ مقيسٍ **بسببِه كما كتبه التقريرُ نفسُه** — لا بذاكرةٍ ولا بتقدير.
 *
 *   php tools/fix_permgov_unmeasured.php [--tsv=<مسار>] [--cause=<رمز>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$TSV = null; $ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--tsv=') === 0) { $TSV = substr($a, 6); }
    if (strpos($a, '--cause=') === 0) { $ONLY = substr($a, 8); }
}

/* رموزُ الأسبابِ — مشتقّةٌ من نصِّ التقرير، لا مخترعة */
$CAUSES = array(
    'NO_PARTIAL' => array('~لا دورَ بـ`can_view=1` و`can_edit=0`~u',
        'لا دورَ يعبر العرضَ ويُردُّ عند الكتابة'),
    'NO_CSRF'    => array('~الشوطُ الثالثُ لم يُثبِت~u',
        'طلبٌ بلا رمزِ جلسةٍ لم يُردَّ 403 — فلا يُميَّز حكمُ الصلاحيةِ من عطلِ الحماية'),
    'NO_TABLE'   => array('~اختبارُ القبولِ لا يُسمّي جدولًا قائمًا~u',
        'اختبارُ القبولِ لا يُسمّي جدولًا قائمًا'),
    'NO_FORM'    => array('~لا نموذجَ POST~u',
        'لا نموذجَ POST — الفعلُ قد يكون AJAX'),
    'GRANT_GAP'  => array('~فجوةُ صلاحياتٍ مستقلّة~u',
        'منحةٌ ناقصةٌ — بندٌ مستقلٌّ يحتاج قرارَ مالكِ نطاق'),
    'NO_FILE'    => array('~الرابطُ لا يشير إلى ملفٍّ حيّ~u',
        'الرابطُ لا يشير إلى ملفٍّ حيّ'),
    'NO_ACCOUNT' => array('~لا حسابَ لأحدِ الطرفين~u',
        'لا حسابَ لأحدِ الطرفين'),
);

$md = (string) @file_get_contents($ROOT . '/docs/fix_2026-08/EVIDENCE_CAMPAIGN_COVERED_2026-08-13.md');
if ($md === '') { exit("تعذّر قراءةُ تقريرِ الحملة\n"); }

$rows = array(); $byCause = array();
foreach (preg_split('~\r?\n~', $md) as $ln) {
    if (strpos($ln, '|') !== 0) { continue; }
    $c = array_map('trim', explode('|', trim($ln, '|')));
    if (count($c) < 4 || strpos($c[0], 'INJ-') !== 0) { continue; }
    $joined = implode(' ', $c);
    if (mb_strpos($joined, 'غيرُ مقيس') === false && mb_strpos($joined, 'غير مقيس') === false) { continue; }
    $why = trim(end($c));
    $code = 'OTHER';
    foreach ($CAUSES as $k => $def) { if (preg_match($def[0], $why)) { $code = $k; break; } }
    $rows[] = array('id' => $c[0], 'scr' => isset($c[1]) ? $c[1] : '', 'cause' => $code, 'why' => $why);
    $byCause[$code] = (isset($byCause[$code]) ? $byCause[$code] : 0) + 1;
}

arsort($byCause);
echo "══ أسبابُ «غير مقيس» — مشتقّةٌ من التقريرِ نفسِه ══\n\n";
$tot = 0;
foreach ($byCause as $k => $n) {
    $label = isset($CAUSES[$k]) ? $CAUSES[$k][1] : 'غيرُ مصنَّف';
    echo sprintf("  %-11s %3d   %s\n", $k, $n, $label);
    $tot += $n;
}
echo "\n  المجموع: {$tot}\n";

if ($ONLY !== null) {
    echo "\n── بنودُ {$ONLY}:\n";
    foreach ($rows as $r) {
        if ($r['cause'] !== $ONLY) { continue; }
        echo sprintf("  %-10s %-42s\n", $r['id'], mb_substr(str_replace('`', '', $r['scr']), 0, 40));
    }
}

if ($TSV !== null) {
    $out = "id\tscr\tcause\twhy\n";
    foreach ($rows as $r) {
        $out .= $r['id'] . "\t" . str_replace('`', '', $r['scr']) . "\t" . $r['cause'] . "\t"
              . str_replace(array("\t", "\n"), ' ', $r['why']) . "\n";
    }
    $path = (strpos($TSV, ':') !== false) ? $TSV : ($ROOT . '/' . $TSV);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $out);
    echo "\n  · كُتب: {$TSV}\n";
}
exit(0);
