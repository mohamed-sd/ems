<?php
require_once __DIR__ . '/lib/xlsx_io.php';
$wb = xlsx_read(__DIR__.'/../docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
$m = $wb['OWNER_DECISIONS_MASTER'];
$hdr = $m[3]; ksort($hdr); $H=array(); foreach($hdr as $i=>$v) $H[trim($v)]=$i;
foreach ($m as $ri=>$r) {
    if (!isset($r[0])) continue;
    $id = trim($r[0]);
    if (!in_array($id, array('DEC-OPEN-03','DEC-OPEN-18'))) continue;
    echo "════════════════════════════════════════\n$id  [" . trim($r[$H['Domain']]) . "]\n════════════════════════════════════════\n";
    foreach (array('Question','Current_State','Options','Recommended_Decision','Affected_Documents','Affected_Screens','Affected_Rules','Migration_Impact','Code_Impact') as $k) {
        if (!isset($H[$k])) continue;
        $v = trim(isset($r[$H[$k]]) ? $r[$H[$k]] : '');
        if ($v === '' || $v === '—') continue;
        printf("• %-22s %s\n", $k, wordwrap($v, 96, "\n" . str_repeat(' ', 25), false));
    }
    echo "\n";
}
