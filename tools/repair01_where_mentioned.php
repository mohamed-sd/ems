<?php
require_once __DIR__ . '/lib/xlsx_io.php';
$D = __DIR__ . '/../docs/REPAIR01_20260823/';
$targets = array('DEC-OPEN-03','DEC-OPEN-18');
foreach (glob($D.'*.xlsx') as $f) {
    $base = basename($f);
    foreach (xlsx_read($f) as $sheet => $rows) {
        foreach ($rows as $ri => $r) {
            $txt = implode(' ¦ ', $r);
            foreach ($targets as $t) {
                if (strpos($txt, $t) === false) continue;
                printf("\n▸ %s\n  %s › %s › صف %d\n", $t, mb_substr($base,0,44), mb_substr($sheet,0,34), $ri+1);
                $shown = 0;
                foreach ($r as $ci => $v) {
                    $v = trim($v); if ($v==='') continue;
                    if ($shown++ >= 7) { echo "     …\n"; break; }
                    printf("     [%s] %s\n", xlsx_col_letter($ci), mb_substr($v, 0, 120));
                }
            }
        }
    }
}
