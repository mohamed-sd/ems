<?php
require_once __DIR__ . '/lib/xlsx_io.php';
$dir = __DIR__ . '/../docs/REPAIR01_20260823';
foreach (glob($dir . '/*.xlsx') as $f) {
    $base = basename($f);
    foreach (xlsx_read($f) as $sheet => $rows) {
        foreach ($rows as $ri => $r) {
            foreach ($r as $ci => $v) {
                if (preg_match('/(\d+)\s*(قرار|قرارًا|decision)[^\n]{0,24}(معلق|معلَّق|pending|ينتظر|مفتوح)/ui', $v, $mm)
                 || preg_match('/(معلق|معلَّق|pending|ينتظر)[^\n]{0,18}[:：]?\s*(\d+)/ui', $v, $mm)) {
                    printf("%-34s › %-26s ص%-4d : %s\n", mb_substr($base,0,34), mb_substr($sheet,0,26), $ri+1, mb_substr(trim($v),0,110));
                }
            }
        }
    }
}
