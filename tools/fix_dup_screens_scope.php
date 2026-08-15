<?php
/**
 * tools/fix_dup_screens_scope.php — نطاقُ «الشاشاتِ المكرَّرة» وفرزُه ثلاثًا
 * ═══════════════════════════════════════════════════════════════════════════
 * القاعدة: نوعُ الثغرةِ `Duplicate Screen` وحالتُها في
 * `docs/fix_progress/INJ_findings_state.tsv` **ليست** «مُغلقٌ بشاهد».
 *
 * ── والفرزُ ثلاثٌ لأنَّ العلاجَ يختلف جذريًّا ────────────────────────────────
 *   ① **تكرارُ تنقّل**: رابطان لنفسِ الملفِّ في سايدبارِ دورٍ واحد.
 *      ميكانيكيٌّ بالكامل — يُنفَّذ كلُّه.
 *   ② **مسارانِ كاتبان**: ملفّان يكتبان في الجدولِ نفسِه.
 *      **لا يُحسم**: أيُّهما يبقى قرارُ مالكِ نطاقٍ — يُجهَّز ويُرفع.
 *   ③ **نسختان لشاشةٍ واحدة**: واحدةٌ تُحوَّل إلى الأخرى.
 *      يُنفَّذ حيث الوجهةُ لا لبسَ فيها.
 *
 * ◆ والفرزُ يُشتقُّ من **نصِّ الملاحظةِ واختبارِ قبولِها** لا من ظنّي.
 *   php tools/fix_dup_screens_scope.php [--tsv=<مسار>] [--class=①]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$TSV = null; $ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--tsv=') === 0) { $TSV = substr($a, 6); }
    if (strpos($a, '--class=') === 0) { $ONLY = substr($a, 8); }
}

$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}

/* ── قواعدُ الفرز — الأخصُّ قبلَ الأعمّ ─────────────────────────────────────── */
$CLASSIFY = function ($real, $test, $scr) {
    $t = $real . ' ' . $test . ' ' . $scr;
    /* ② مسارانِ كاتبان: يُذكر جدولٌ يُكتب فيه من مسارين */
    if (preg_match('~مساران?\s+(يكتبان|كاتبان)|محرّكان|تكتب(ان)?\s+في\s+(الجدول|جدول)'
        . '|مسارُ كتابةٍ واحدٌ لا مساران|كاتبانِ لجدول~u', $t)) { return '②'; }
    /* ① تكرارُ تنقّل: رابطٌ يتكرر في القائمة */
    if (preg_match('~مرتين في (القائمة|سايدبار)|رابطان|صفّان? في `?nav_items|يتكرر في القائمة'
        . '|في سايدبار|القائمةِ الجانبية|مكرَّرٌ في قائمة~u', $t)) { return '①'; }
    /* ③ نسختان لشاشةٍ واحدة */
    if (preg_match('~نسخت(ان|ين)|شاشتان? (متطابقت|لنفس)|تُحوَّل إلى|إعادة توجيه|redirect'
        . '|الشاشةُ القديمة|legacy~u', $t)) { return '③'; }
    return '؟';
};

$rows = array(); $bySev = array(); $byClass = array(); $famTotal = 0;
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++; if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (trim($c[9]) !== 'Duplicate Screen') { continue; }
    $famTotal++;
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    $cls = $CLASSIFY(trim($c[8]), trim($c[20]), trim($c[4]));
    if ($ONLY !== null && $cls !== $ONLY) { continue; }
    $rows[$id] = array('id' => $id, 'dept' => trim($c[3]), 'scr' => trim($c[4]), 'url' => trim($c[5]),
        'sev' => trim($c[10]), 'real' => trim($c[8]), 'test' => trim($c[20]), 'cls' => $cls);
    $bySev[trim($c[10])] = (isset($bySev[trim($c[10])]) ? $bySev[trim($c[10])] : 0) + 1;
    $byClass[$cls] = (isset($byClass[$cls]) ? $byClass[$cls] : 0) + 1;
}
fclose($fh);

echo "══════════════════════════════════════════════════════════════════\n";
echo " الشاشاتُ المكرَّرة — النطاقُ مشتقًّا بالقاعدة\n";
echo "══════════════════════════════════════════════════════════════════\n\n";
echo "  العائلةُ كلُّها (Duplicate Screen): {$famTotal}\n";
echo "  منها «مُغلقٌ بشاهد»: " . ($famTotal - count($rows)) . "\n";
echo "  ⇒ **النطاق: " . count($rows) . "**\n\n";
ksort($bySev);
echo '  الخطورة: ' . implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v; },
    array_keys($bySev), array_values($bySev))) . "\n\n";

$LABEL = array('①' => 'تكرارُ تنقّل — ميكانيكيٌّ يُنفَّذ كلُّه',
               '②' => 'مسارانِ كاتبان — يُرفع للمالكِ ولا يُحسم',
               '③' => 'نسختان لشاشةٍ — تحويلٌ حيث لا لبس',
               '؟' => 'غيرُ مصنَّفٍ — يُفرَز يدويًّا');
echo "── الفرز\n";
ksort($byClass);
foreach ($byClass as $k => $v) {
    printf("  %s  %2d   %s\n", $k, $v, isset($LABEL[$k]) ? $LABEL[$k] : '');
}
echo "\n── البنود\n";
foreach ($rows as $r) {
    printf("  %s %-10s %-4s %-22s %s\n", $r['cls'], $r['id'], $r['sev'],
        mb_substr($r['dept'], 0, 20), mb_substr($r['scr'], 0, 34));
}

if ($TSV !== null) {
    $out = "id\tcls\tsev\tdept\tscr\turl\ttest\n";
    foreach ($rows as $r) {
        $out .= implode("\t", array($r['id'], $r['cls'], $r['sev'], $r['dept'], $r['scr'],
            $r['url'], str_replace(array("\t", "\n"), ' ', $r['test']))) . "\n";
    }
    $path = (strpos($TSV, ':') !== false) ? $TSV : ($ROOT . '/' . $TSV);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $out);
    echo "\n  · كُتب: {$TSV}\n";
}
exit(0);
