<?php
/**
 * tools/fix_data_mismatch_scope.php — نطاقُ عائلةِ «تعارضِ البيانات» مشتقًّا بالقاعدة
 * ═══════════════════════════════════════════════════════════════════════════
 * القاعدة: كلُّ ملاحظةٍ نوعُها `Data Mismatch` في السجلِّ الجامعِ وحالتُها في
 * `docs/fix_progress/INJ_findings_state.tsv` **ليست** «مُغلقٌ بشاهد».
 *
 * ◆ ولا يُنقل رقمٌ من نصِّ التكليف: العددُ يُحسب هنا كلَّ مرة. ورقمٌ منقولٌ
 *   يتقادم بصمتٍ بينما الحالةُ تتغيّر — وقد وقع هذا فعلًا في حملةٍ سابقة.
 * ◆ ونوعُ الملاحظةِ العمودُ العاشر (فهرس ٩) · ونصُّ اختبارِ القبول العمودُ
 *   الحادي والعشرون (فهرس ٢٠) · والترويسةُ ثلاثةُ أسطر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));

/* ── الحالةُ ────────────────────────────────────────────────────────────── */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}

/* ── السجلُّ الجامع ─────────────────────────────────────────────────────── */
$all = array(); $fam = array(); $open = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($line = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($line, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    $id = trim($c[0]);
    $all[$id] = true;
    if (trim($c[9]) !== 'Data Mismatch') { continue; }
    $row = array('id' => $id, 'doc' => trim($c[1]), 'dept' => trim($c[3]),
                 'screen' => trim($c[4]), 'sev' => trim($c[10]), 'test' => trim($c[20]),
                 'state' => isset($state[$id]) ? $state[$id] : 'غيرُ مقيس');
    $fam[$id] = $row;
    if ($row['state'] !== 'مُغلقٌ بشاهد') { $open[$id] = $row; }
}
fclose($fh);

echo "══════════════════════════════════════════════════════════════════\n";
echo " نطاقُ «تعارضِ البيانات» — مشتقٌّ بالقاعدةِ لا منقولًا\n";
echo "══════════════════════════════════════════════════════════════════\n\n";
echo '  السجلُّ الجامع        : ' . count($all) . " ملاحظة\n";
echo '  منها العائلةُ كلُّها  : ' . count($fam) . "  (Data Mismatch)\n";
$closed = count($fam) - count($open);
echo "     · مُغلقٌ بشاهد {$closed}   ⟵ مستبعَدةٌ من النطاق\n";
echo '     · غيرُ مقيس   ' . count($open) . "\n\n";
echo '  ⇒ **النطاقُ: ' . count($open) . "**\n\n";

$bySev = array(); $byDept = array();
foreach ($open as $r) {
    $bySev[$r['sev']] = (isset($bySev[$r['sev']]) ? $bySev[$r['sev']] : 0) + 1;
    $byDept[$r['dept']] = (isset($byDept[$r['dept']]) ? $byDept[$r['dept']] : 0) + 1;
}
ksort($bySev);
echo "  الخطورة:\n";
foreach ($bySev as $k => $v) { echo '       ' . str_pad($k, 8) . $v . "\n"; }
echo "\n  الإداراتُ (" . count($byDept) . "):\n";
arsort($byDept);
foreach (array_slice($byDept, 0, 14, true) as $k => $v) {
    echo '       ' . str_pad(mb_substr($k, 0, 30), 32) . $v . "\n";
}

if (in_array('--list', $argv, true)) {
    echo "\n  البنود:\n";
    foreach ($open as $r) {
        echo '  · ' . $r['id'] . ' [' . $r['sev'] . '] ' . mb_substr($r['dept'], 0, 22)
           . ' · ' . mb_substr($r['screen'], 0, 46) . "\n";
        if (in_array('--tests', $argv, true)) { echo '      ' . $r['test'] . "\n"; }
    }
}
if (in_array('--json', $argv, true)) {
    file_put_contents($ROOT . '/docs/fix_progress/data_mismatch_scope.json',
        json_encode(array_values($open), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  · كُتب: docs/fix_progress/data_mismatch_scope.json\n";
}
