<?php
require_once __DIR__ . '/lib/xlsx_io.php';
$wb = xlsx_read(__DIR__ . '/../docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
$mst = $wb['OWNER_DECISIONS_MASTER']; $reg = $wb['05_سجل_القرارات'];

/* رأسُ 05 */
$h = $reg[3]; ksort($h);
echo "رأسُ 05_سجل_القرارات: " . implode(' | ', array_map('trim', $h)) . "\n\n";

$A = array(); foreach ($mst as $ri => $r) { if (isset($r[0]) && preg_match('/^DEC-/i', trim($r[0]))) $A[trim($r[0])] = isset($r[7]) ? trim($r[7]) : ''; }
$B = array(); foreach ($reg as $ri => $r) { if (isset($r[0]) && preg_match('/^DEC-/i', trim($r[0]))) $B[trim($r[0])] = isset($r[7]) ? trim($r[7]) : ''; }

echo "MASTER " . count($A) . "  ·  05 " . count($B) . "  ·  مشترك " . count(array_intersect_key($A,$B)) . "  ·  في MASTER فقط " . count(array_diff_key($A,$B)) . "\n\n";

$pairs = array();
foreach (array_intersect_key($A, $B) as $id => $sa) {
    $sb = $B[$id];
    $pairs[$sa . ' ⇄ ' . $sb][] = $id;
}
echo "═══ توزيعُ الأزواج (MASTER ⇄ 05) ═══\n";
uasort($pairs, function($x,$y){ return count($y)-count($x); });
foreach ($pairs as $k => $v) printf("  %-58s %3d   مثال: %s\n", $k, count($v), $v[0]);

echo "\n═══ الـ14 في MASTER وحدَه ═══\n";
foreach (array_diff_key($A,$B) as $id => $s) printf("  %-18s %s\n", $id, $s);
