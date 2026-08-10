<?php
/**
 * tools/fix_u7_dump.php — تفريغُ الحقولِ الباقيةِ بلا عنوان (AC-U7)
 * ═══════════════════════════════════════════════════════════════════════════
 * يطابق منطقَ البوابةِ حرفًا بحرف — وإلا اختلف العددُ فضاع الجهدُ على وهم.
 * القاموسُ يُقرأ من ملفِّ الجافاسكربت نفسِه: مصدرٌ واحدٌ للحقيقة لا نسخةٌ تتعفّن.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';

/** قاموسُ العناوينِ اليدويّ، مقروءًا من assets/js/ui-unification.js */
$DICT = array();
$uj = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
if (preg_match('/EMS_FIELD_LABELS\s*=\s*\{([\s\S]*?)\n\s*\};/u', $uj, $dm)) {
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*:\s*\'/u', $dm[1], $km)) {
        $DICT = array_flip($km[1]);
    }
}

$TAG_RE = '/<(input|select|textarea)\b((?:<\?(?:php|=)[\s\S]*?\?>|[^>])*)>/i';
$rows = array();
$byFile = array();

foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (!preg_match_all($TAG_RE, $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) { continue; }

    $forIds = array();
    if (preg_match_all('/<label\b(?:<\?(?:php|=)[\s\S]*?\?>|[^>])*?\bfor=("|\')([^"\']+)\1/i', $src, $lm)) {
        $forIds = array_flip($lm[2]);
    }
    foreach ($m as $t) {
        $attrs = $t[2][0];
        if (preg_match('/\btype=("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { continue; }
        if (preg_match('/\baria-label(ledby)?=/i', $attrs)) { continue; }
        if (preg_match('/\bid=("|\')([^"\']+)\1/i', $attrs, $im) && isset($forIds[$im[2]])) { continue; }

        $at  = $t[0][1];
        $pre = substr($src, 0, $at);
        $lo  = strripos($pre, '<label');
        $lc  = strripos($pre, '</label>');
        if ($lo !== false && ($lc === false || $lc < $lo)) { continue; }   // label محيط

        $before = substr($src, max(0, $at - 1400), min(1400, $at));
        $wrapAt = false;
        foreach (array('class="field', "class='field", 'class="form-group', "class='form-group",
                       'class="ems-field', 'class="mb-3') as $needle) {
            $p = strrpos($before, $needle);
            if ($p !== false && ($wrapAt === false || $p > $wrapAt)) { $wrapAt = $p; }
        }
        if ($wrapAt !== false && stripos(substr($before, $wrapAt), '<label') !== false) { continue; }
        if (preg_match('/\b(placeholder|title)\s*=\s*("|\')\s*\S/i', $attrs)) { continue; }
        if (preg_match('/<t[dh]\b[^>]*>[^<]{0,200}$/i', $before) && stripos($src, '<thead') !== false) { continue; }

        $near = trim(preg_replace('/\s+/u', ' ', strip_tags(substr($before, -220))));
        $near = preg_replace('/[:：*]\s*$/u', '', $near);
        if ($near !== '' && mb_strlen($near) >= 2 && !preg_match('/^[\d\s.,%\-\/]+$/u', $near)) { continue; }

        preg_match('/\bname\s*=\s*("|\')([^"\']+)\1/i', $attrs, $nm);
        $kk = isset($nm[2]) ? preg_replace('/\[.*$/', '', $nm[2]) : '';
        if ($kk !== '' && isset($DICT[$kk])) { continue; }

        preg_match('/\bid\s*=\s*("|\')([^"\']+)\1/i', $attrs, $idm);
        preg_match('/\btype\s*=\s*("|\')([^"\']+)\1/i', $attrs, $tym);

        $line = substr_count(substr($src, 0, $at), "\n") + 1;
        $rows[] = array($rel, $line, strtolower($t[1][0]), $tym[2] ?? '', $nm[2] ?? '', $idm[2] ?? '',
                        trim(preg_replace('/\s+/u', ' ', mb_substr($before, -90))), trim($t[0][0]));
        $byFile[$rel] = ($byFile[$rel] ?? 0) + 1;
    }
}
arsort($byFile);
echo 'القاموس: ' . count($DICT) . " مدخلة\n";
echo 'الباقي بلا عنوان: ' . count($rows) . ' في ' . count($byFile) . " ملفًّا\n\n";
foreach ($byFile as $f => $n) { printf("  %-48s %d\n", $f, $n); }
echo "\n" . str_repeat('─', 74) . "\n";
foreach ($rows as $r) {
    printf("%s:%d | %s%s | name=%s | id=%s\n    سياق: …%s\n    وسم : %s\n\n",
        $r[0], $r[1], $r[2], $r[3] !== '' ? ':' . $r[3] : '',
        $r[4] !== '' ? $r[4] : '—', $r[5] !== '' ? $r[5] : '—',
        mb_substr($r[6], -78), mb_substr($r[7], 0, 150));
}
