<?php
/* قارئُ docx كامل: الفقراتُ **وخلايا الجداول**.
   ⚠ عطبٌ وقع فعلًا: `<w:t[^>]*>` يلتقط `<w:tcPr>` و`<w:tbl>` و`<w:tr>` —
     فالفاصلُ يجب أن يكون **مسافةً أو إغلاقًا فوريًّا**: `<w:t(?:\s[^>]*)?>` */
mb_internal_encoding('UTF-8');
$z = new ZipArchive();
if ($z->open($argv[1]) !== true) { exit("FAIL open\n"); }
$xml = $z->getFromName('word/document.xml');
$z->close();

function cellText($frag) {
    preg_match_all('~<w:t(?:\s[^>]*)?>(.*?)</w:t>~su', $frag, $m);
    $s = html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    return preg_replace('~\s+~u', ' ', trim($s));
}

/* نمشي على أبناءِ الجسدِ بالترتيب: w:p و w:tbl */
preg_match('~<w:body[^>]*>(.*)</w:body>~su', $xml, $bm);
$body = $bm[1] ?? $xml;

$out = [];
$off = 0;
$len = strlen($body);
while ($off < $len) {
    $pP = strpos($body, '<w:p ', $off);  $pP2 = strpos($body, '<w:p>', $off);
    if ($pP === false || ($pP2 !== false && $pP2 < $pP)) { $pP = $pP2; }
    $pT = strpos($body, '<w:tbl>', $off); $pT2 = strpos($body, '<w:tbl ', $off);
    if ($pT === false || ($pT2 !== false && $pT2 < $pT)) { $pT = $pT2; }
    if ($pP === false && $pT === false) { break; }

    if ($pT !== false && ($pP === false || $pT < $pP)) {
        $end = strpos($body, '</w:tbl>', $pT);
        $tbl = substr($body, $pT, $end - $pT + 8);
        $out[] = '┌─── جدول ───';
        preg_match_all('~<w:tr[\s>].*?</w:tr>~su', $tbl, $rows);
        foreach ($rows[0] as $tr) {
            preg_match_all('~<w:tc[\s>].*?</w:tc>~su', $tr, $tcs);
            $cells = [];
            foreach ($tcs[0] as $tc) { $cells[] = cellText($tc); }
            if (implode('', $cells) !== '') { $out[] = '│ ' . implode('  ¦  ', $cells); }
        }
        $out[] = '└────────────';
        $off = $end + 8;
    } else {
        $end = strpos($body, '</w:p>', $pP);
        if ($end === false) { break; }
        $p = substr($body, $pP, $end - $pP + 6);
        $s = cellText($p);
        $style = '';
        if (preg_match('~<w:pStyle w:val="([^"]*)"~u', $p, $m)) { $style = $m[1]; }
        if ($s !== '') {
            $out[] = ($style && stripos($style, 'Head') !== false) ? "\n══ [$style] $s" : $s;
        }
        $off = $end + 6;
    }
}
echo implode("\n", $out), "\n";
