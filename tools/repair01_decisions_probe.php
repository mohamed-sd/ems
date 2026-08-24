<?php
/** tools/repair01_decisions_probe.php — قياسُ سجلِّ القرارات وانحرافِ التوابع */
require_once __DIR__ . '/lib/xlsx_io.php';
$dir = __DIR__ . '/../docs/REPAIR01_20260823';

/* ① المصدرُ الحاكم */
$wb = xlsx_read($dir . '/09 · السجلات المؤسسية والقرارات.xlsx');
$m = $wb['OWNER_DECISIONS_MASTER'];
ksort($m);
$hdrRow = 3; $hdr = $m[$hdrRow]; ksort($hdr);
$H = array(); foreach ($hdr as $i => $v) { $H[trim($v)] = $i; }
echo "أعمدةُ MASTER: " . implode(' · ', array_keys($H)) . "\n\n";

$master = array(); $byStatus = array(); $byDomain = array();
foreach ($m as $ri => $r) {
    if ($ri <= $hdrRow) continue;
    $id = isset($r[$H['Decision_ID']]) ? trim($r[$H['Decision_ID']]) : '';
    if ($id === '' || !preg_match('/^DEC/i', $id)) continue;
    $st = isset($r[$H['Decision_Status']]) ? trim($r[$H['Decision_Status']]) : '';
    $dm = isset($r[$H['Domain']]) ? trim($r[$H['Domain']]) : '';
    $master[$id] = $st;
    $byStatus[$st] = (isset($byStatus[$st]) ? $byStatus[$st] : 0) + 1;
    $byDomain[$dm] = (isset($byDomain[$dm]) ? $byDomain[$dm] : 0) + 1;
}
echo "═══ MASTER: " . count($master) . " قرارًا ═══\n";
arsort($byStatus);
foreach ($byStatus as $s => $n) printf("  %-46s %d\n", ($s === '' ? '(فارغ)' : $s), $n);
echo "\nنطاقات: " . count($byDomain) . "\n";

/* ② الانحراف: أيُّ ملفٍّ تابعٍ يحمل Decision_ID بحالةٍ مغايرة */
echo "\n═══ فحصُ الانحراف عبر الملفّات التابعة ═══\n";
$files = glob($dir . '/*.xlsx');
sort($files);
$drift = array(); $seenIn = array();
foreach ($files as $f) {
    $base = basename($f);
    if (strpos($base, '09 · ') === 0) continue;
    $w = xlsx_read($f);
    foreach ($w as $sheet => $rows) {
        foreach ($rows as $ri => $r) {
            $txt = implode(' ¦ ', $r);
            if (!preg_match_all('/\bDEC-[A-Z0-9\-]+/u', $txt, $mm)) continue;
            foreach (array_unique($mm[0]) as $id) {
                $seenIn[$id][] = $base;
                if (!isset($master[$id])) { $drift['يتيم'][] = "$id ← $base › $sheet ص" . ($ri + 1); continue; }
                $mst = $master[$id];
                $isApproved = (stripos($mst, 'APPROVED') !== false);
                $saysPending = preg_match('/معلق|معلَّق|PENDING|مفتوح|ينتظر/ui', $txt);
                if ($isApproved && $saysPending) {
                    $drift['معلَّقٌ وهو معتمد'][] = "$id ← $base › $sheet ص" . ($ri + 1);
                }
            }
        }
    }
}
foreach ($drift as $k => $v) {
    echo "\n▸ $k : " . count($v) . "\n";
    foreach (array_slice(array_unique($v), 0, 14) as $line) echo "   · $line\n";
    if (count(array_unique($v)) > 14) echo "   … و" . (count(array_unique($v)) - 14) . " غيرها\n";
}
if (!$drift) echo "لا انحراف مقيس.\n";
