<?php
/**
 * tools/fix_u7_list.php — سردُ الحقولِ الباقيةِ بلا عنوان، بسياقِها الحقيقيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * يطابق منطقَ البوابةِ حرفًا بحرف (وإلا اختلف العددُ فضاع الجهدُ على وهم)،
 * ويطبع لكلِّ حقلٍ ما يسبقه — فالعلاجُ يُبنى على ما هو موجودٌ لا على ما يُظنّ.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';

$limit = 24;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $limit = (int) substr($a, 8); } }

$shown = 0; $total = 0; $byShape = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (!preg_match_all('/<(input|select|textarea)\b((?:<\?(?:php|=)[\s\S]*?\?>|[^>])*)>/i', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $forIds = array();
    if (preg_match_all('/<label\b[^>]*\bfor=("|\')([^"\']+)\1/i', $src, $lm)) { $forIds = array_flip($lm[2]); }

    foreach ($m as $t) {
        $attrs = $t[2][0];
        if (preg_match('/\btype=("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { continue; }
        if (preg_match('/\baria-label(ledby)?=/i', $attrs)) { continue; }
        if (preg_match('/\bid=("|\')([^"\']+)\1/i', $attrs, $im) && isset($forIds[$im[2]])) { continue; }

        $at = $t[0][1];
        $before = substr($src, max(0, $at - 1400), min(1400, $at));

        // غلافٌ معروفٌ يحمل عنوانًا
        $wrapAt = false;
        foreach (array('class="field', "class='field", 'class="form-group', "class='form-group",
                       'class="ems-field', 'class="mb-3') as $needle) {
            $p = strrpos($before, $needle);
            if ($p !== false && ($wrapAt === false || $p > $wrapAt)) { $wrapAt = $p; }
        }
        if ($wrapAt !== false && stripos(substr($before, $wrapAt), '<label') !== false) { continue; }
        if (preg_match('/\b(placeholder|title)\s*=\s*("|\')\s*\S/i', $attrs)) { continue; }
        if (preg_match('/<t[dh]\b[^>]*>[^<]{0,200}$/i', $before) && stripos($src, '<thead') !== false) { continue; }
        if (preg_match('/>\s*([^<>]{2,60}?)\s*[:：]?\s*$/u', $before, $pm)
            && trim($pm[1]) !== '' && !preg_match('/^[\d\s.,%-]+$/u', $pm[1])) { continue; }

        $total++;

        // شكلُ السياق — لتصنيفِ العلاج
        $tail = trim(preg_replace('/\s+/u', ' ', mb_substr($before, -110)));
        $shape = 'غيرُ ذلك';
        if (preg_match('/<t[dh]\b[^>]*>\s*$/i', $before))          { $shape = 'خليةُ جدولٍ بلا ترويسة'; }
        elseif (preg_match('/<legend\b/i', $before))                { $shape = 'داخلَ fieldset'; }
        elseif (preg_match('/<(script|template)\b/i', $before))     { $shape = 'قالبُ جافاسكربت'; }
        elseif (preg_match('/<label\b[^>]*>\s*$/i', $before))       { $shape = 'داخلَ label بلا for'; }
        elseif (preg_match('/<div[^>]*class="[^"]*control/i', $before)) { $shape = 'غلافُ control بلا عنوان'; }
        $byShape[$shape] = ($byShape[$shape] ?? 0) + 1;

        if ($shown < $limit) {
            $shown++;
            printf("── %s\n   وسم : %s\n   سياق: …%s\n   شكل : %s\n",
                $rel, mb_substr(trim($t[0][0]), 0, 92), mb_substr($tail, -80), $shape);
        }
    }
}
arsort($byShape);
echo "\n" . str_repeat('═', 66) . "\n";
echo "الباقي بلا عنوان: {$total}\n";
foreach ($byShape as $k => $n) { printf("  %-34s %d\n", $k, $n); }
