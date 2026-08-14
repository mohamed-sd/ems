<?php
/**
 * tools/fix_permgov_scope.php — اشتقاقُ نطاقِ عائلةِ الصلاحياتِ والحوكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * القاعدة: كلُّ ملاحظةٍ نوعُها `Permission Gap` أو `Governance Gap` وحالتُها
 * في `docs/fix_progress/INJ_findings_state.tsv` **ليست** «مُغلقٌ بشاهد».
 *
 * ◆ والمقامُ يُعلَن مع العدد — فرقمٌ بلا مقامِه لا يُقرَّر عليه.
 * ◆ و«مُغطًّى» **حالةٌ محسوبةٌ لا مخزَّنة**: تُشتقُّ هنا بالقاعدةِ (بندٌ له إصلاحٌ
 *   في الشجرةِ بلا شاهدٍ مُشغَّل) ولا تُقرأ من عمودِ حالة.
 *
 *   php tools/fix_permgov_scope.php [--csv=<مسار>] [--split]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$CSV = null; $SPLIT = in_array('--split', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--csv=') === 0) { $CSV = substr($a, 6); } }

/* ── ① الحالاتُ من ملفِّ الحالة ────────────────────────────────────────────── */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}

/* ── ② السجلُّ الجامع ─────────────────────────────────────────────────────── */
$FAMILY = array('Permission Gap', 'Governance Gap');
$rows = array(); $famTotal = 0; $regTotal = 0;
$byType = array(); $bySev = array(); $byState = array(); $byDept = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }                        /* ثلاثةُ أسطرِ ترويسة */
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    $regTotal++;
    $type = trim($c[9]);
    if (!in_array($type, $FAMILY, true)) { continue; }
    $famTotal++;
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    $byState[$st] = (isset($byState[$st]) ? $byState[$st] : 0) + 1;
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    $sev = trim($c[10]);
    $rows[$id] = array(
        'id' => $id, 'doc' => trim($c[1]), 'dept' => trim($c[3]), 'scr' => trim($c[4]),
        'url' => trim($c[5]), 'real' => trim($c[8]), 'type' => $type, 'sev' => $sev,
        'test' => trim($c[20]), 'state' => $st,
    );
    $byType[$type] = (isset($byType[$type]) ? $byType[$type] : 0) + 1;
    $bySev[$sev] = (isset($bySev[$sev]) ? $bySev[$sev] : 0) + 1;
    $d = trim($c[3]) !== '' ? trim($c[3]) : '—';
    $byDept[$d] = (isset($byDept[$d]) ? $byDept[$d] : 0) + 1;
}
fclose($fh);

echo "══════════════════════════════════════════════════════════════════\n";
echo " نطاقُ عائلةِ الصلاحياتِ والحوكمة — مشتقٌّ بالقاعدةِ لا منقولًا\n";
echo "══════════════════════════════════════════════════════════════════\n\n";
echo "  السجلُّ الجامع        : {$regTotal} ملاحظة\n";
echo "  منها العائلةُ كلُّها  : {$famTotal}  (Permission Gap + Governance Gap)\n";
ksort($byState);
foreach ($byState as $s => $k) {
    echo sprintf("     · %-18s %3d%s\n", $s, $k, $s === 'مُغلقٌ بشاهد' ? '   ⟵ مستبعَدةٌ من النطاق' : '');
}
echo "\n  ⇒ **النطاقُ: " . count($rows) . "**\n";
ksort($byType);
foreach ($byType as $t => $k) { echo sprintf("       %-20s %3d\n", $t, $k); }
echo "\n  الخطورة:\n";
ksort($bySev);
$p01 = 0; $p23 = 0;
foreach ($bySev as $s => $k) {
    echo sprintf("       %-6s %3d\n", $s, $k);
    if ($s === 'P0' || $s === 'P1') { $p01 += $k; } else { $p23 += $k; }
}
echo "\n     النصف ① (P0/P1): {$p01}\n";
echo "     النصف ② (P2/P3): {$p23}\n";

arsort($byDept);
echo "\n  الإداراتُ الأكثرُ حملًا:\n";
$i = 0;
foreach ($byDept as $d => $k) {
    echo sprintf("       %-34s %3d\n", mb_substr($d, 0, 32), $k);
    if (++$i >= 8) { break; }
}

if ($CSV !== null) {
    $out = "id\tdept\tscr\turl\ttype\tsev\tstate\ttest\n";
    foreach ($rows as $r) {
        $out .= $r['id'] . "\t" . $r['dept'] . "\t" . $r['scr'] . "\t" . $r['url'] . "\t"
              . $r['type'] . "\t" . $r['sev'] . "\t" . $r['state'] . "\t"
              . str_replace(array("\t", "\n"), ' ', $r['test']) . "\n";
    }
    $path = (strpos($CSV, ':') !== false) ? $CSV : ($ROOT . '/' . $CSV);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $out);
    echo "\n  · كُتب: {$CSV} (" . count($rows) . " صفًّا)\n";
}
exit(0);
