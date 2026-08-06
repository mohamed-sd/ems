<?php
/**
 * tools/trace_bare_context.php — سياق المعرفات العارية (شاهدها توثيقي فقط)
 * المستخرجات تضع المعرف سطرًا وحده ونصه في الأسطر التالية — يُجمعان معًا
 * ويُصنَّف النمط (إتاحة شاشة · ظهور · سلوك · مبدأ UX · …) → docs/TRACE_BARE_ar.csv
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);
$rows = array_map('str_getcsv', file($ROOT . '/docs/TRACE_MATRIX_ar.csv'));
array_shift($rows);
$bare = array();
foreach ($rows as $r) { if (isset($r[2]) && $r[2] === 'توثيق') { $bare[$r[0]] = $r[1]; } }

$docsTxt = array();
foreach (glob($ROOT . '/docs/update0008_extracts/*.txt') as $f) {
    $docsTxt[basename($f, '.txt')] = explode("\n", (string) file_get_contents($f));
}

/** نص المعرف: أول موضع يبدأ سطره بالمعرف — يُلحق به ما يليه من أسطر « | » */
function bare_ctx(array $lines, $id) {
    $n = count($lines);
    $best = '';
    for ($i = 0; $i < $n; $i++) {
        $ln = trim($lines[$i]);
        if ($ln !== $id && strpos($ln, $id . '-') !== 0 && strpos($ln, $id . ' ') !== 0) { continue; }
        $buf = array();
        for ($j = $i + 1; $j < min($i + 5, $n); $j++) {
            $t = trim($lines[$j]);
            if ($t === '' || preg_match('/^(SCN|UXP|IAM|WFM|TS|WF|AC|BR|DEC|SRC)-?\d/u', $t)) { break; }
            $buf[] = trim($t, " |\t");
        }
        $txt = implode(' § ', array_filter($buf));
        if (mb_strlen($txt) > mb_strlen($best)) { $best = $txt; }
        if (mb_strlen($best) > 80) { break; } // نص وافٍ وُجد
    }
    return mb_substr(preg_replace('/\s+/u', ' ', $best), 0, 500);
}

$out = fopen($ROOT . '/docs/TRACE_BARE_ar.csv', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array('المعرف', 'وثيقته', 'النمط', 'نصه'));
$patterns = array();
foreach ($bare as $id => $docList) {
    $ctx = '';
    foreach (explode('·', $docList) as $doc) {
        foreach ($docsTxt as $name => $lines) {
            if ($doc !== $name && mb_strpos($name, $doc) === false) { continue; }
            $c = bare_ctx($lines, $id);
            if (mb_strlen($c) > mb_strlen($ctx)) { $ctx = $c; }
        }
    }
    $pat = 'أخرى';
    if (mb_strpos($ctx, 'يجب أن تُتاح شاشة') !== false || mb_strpos($ctx, 'يجب أن تُتاح شاشةُ') !== false) { $pat = 'إتاحة شاشة'; }
    elseif (mb_strpos($ctx, 'اختبارُ ظهور') !== false) { $pat = 'ظهور'; }
    elseif (mb_strpos($ctx, 'يجب أن') !== false) { $pat = 'إلزام'; }
    $patterns[$pat] = ($patterns[$pat] ?? 0) + 1;
    fputcsv($out, array($id, $docList, $pat, $ctx));
}
fclose($out);
fwrite(STDOUT, count($bare) . " معرفًا — الأنماط:\n");
foreach ($patterns as $p => $n) { fwrite(STDOUT, "  {$p}: {$n}\n"); }
