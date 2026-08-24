<?php
require_once __DIR__ . '/lib/xlsx_io.php';
$wb = xlsx_read(__DIR__ . '/../docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
$mst = $wb['OWNER_DECISIONS_MASTER'];
echo "═══ القرارات الثمانية عشر المنتظرة فعلًا ═══\n\n";
$n = 0;
foreach ($mst as $ri => $r) {
    if (!isset($r[0]) || !preg_match('/^DEC-/i', trim($r[0]))) continue;
    if (trim(isset($r[7]) ? $r[7] : '') !== 'NEEDS_OWNER_DECISION') continue;
    $n++;
    printf("%2d. %-16s [%s]\n", $n, trim($r[0]), mb_substr(trim(isset($r[1])?$r[1]:''), 0, 28));
    printf("    س: %s\n", mb_substr(trim(isset($r[2])?$r[2]:''), 0, 150));
    $aff = trim(isset($r[10]) ? $r[10] : '');
    if ($aff !== '') printf("    شاشات: %s\n", mb_substr($aff, 0, 110));
    $ci = trim(isset($r[13]) ? $r[13] : '');
    if ($ci !== '') printf("    أثر الكود: %s\n", mb_substr($ci, 0, 110));
    echo "\n";
}
