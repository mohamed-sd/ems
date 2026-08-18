<?php
/**
 * tools/uxw_a11y_contrast.php — فحصُ الوصولِ الرقميِّ ① : تضادُّ الألوان
 * ───────────────────────────────────────────────────────────────────────────
 * UXW-01 §9-3: «4.5:1 للنصِّ العاديِّ و3:1 للكبيرِ وعناصرِ الواجهة —
 * ولا تُعلَن مطابقةٌ قبلَ القياس». يقيس أزواجَ (خلفية/نص) المعلنةَ في كتلِ
 * مكوّناتِ النظامِ التصميميِّ نفسِها — حيث يجتمع background وcolor في
 * القاعدةِ الواحدةِ بقيمِ رموزٍ قابلةٍ للحلّ — ويحلُّ الرموزَ من ملفِها.
 * الأزواجُ الراسبةُ تُعلَن بأرقامِها ولا تُصلَح صامتةً (قرارُ لوحةٍ بعينِ المالك).
 *   php tools/uxw_a11y_contrast.php          # النصيُّ
 *   php tools/uxw_a11y_contrast.php --csv    # storage/reports/uxw01_contrast.csv
 */
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* ── حلُّ الرموز من ملفَّيها ────────────────────────────────────────────── */
$VARS = array();
foreach (array('assets/css/design-tokens.css', 'assets/css/ems-shell.css') as $f) {
    $css = @file_get_contents("$ROOT/$f");
    if ($css === false) continue;
    if (preg_match_all('/(--[A-Za-z0-9-]+)\s*:\s*([^;]+);/', $css, $m, PREG_SET_ORDER)) {
        foreach ($m as $d) { if (!isset($VARS[$d[1]])) { $VARS[$d[1]] = trim($d[2]); } }
    }
}
function resolve_color(string $v, array $VARS, int $depth = 0): ?array {
    $v = trim($v);
    if ($depth > 6) return null;
    if (preg_match('/^var\(\s*(--[A-Za-z0-9-]+)\s*(?:,\s*([^)]+))?\)$/', $v, $m)) {
        if (isset($VARS[$m[1]])) return resolve_color($VARS[$m[1]], $VARS, $depth + 1);
        return isset($m[2]) ? resolve_color($m[2], $VARS, $depth + 1) : null;
    }
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $v, $m)) {
        $h = $m[1];
        return array(hexdec($h[0].$h[0]), hexdec($h[1].$h[1]), hexdec($h[2].$h[2]));
    }
    if (preg_match('/^#([0-9a-fA-F]{6})/', $v, $m)) {
        return array(hexdec(substr($m[1],0,2)), hexdec(substr($m[1],2,2)), hexdec(substr($m[1],4,2)));
    }
    if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $v, $m)) {
        return array((int)$m[1], (int)$m[2], (int)$m[3]); // ألفا تُقاس على الأسوأ: اللونُ الصريح
    }
    static $named = array('white' => array(255,255,255), 'black' => array(0,0,0));
    return $named[strtolower($v)] ?? null;
}
function luminance(array $rgb): float {
    $c = array();
    foreach ($rgb as $ch) {
        $s = $ch / 255.0;
        $c[] = $s <= 0.03928 ? $s / 12.92 : pow(($s + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio(array $a, array $b): float {
    $l1 = luminance($a); $l2 = luminance($b);
    if ($l1 < $l2) { $t = $l1; $l1 = $l2; $l2 = $t; }
    return ($l1 + 0.05) / ($l2 + 0.05);
}

/* ── الأزواجُ المعلنةُ في كتلِ مكوّناتِ النظام ─────────────────────────── */
$FILES = array('assets/css/ems-states.css', 'assets/css/ems-buttons.css',
               'assets/css/ems-shell.css', 'assets/css/ems-alerts.css',
               'assets/css/ems-tables.css', 'assets/css/ems-forms.css');
$pairs = array(); $unresolved = 0;
foreach ($FILES as $f) {
    $css = @file_get_contents("$ROOT/$f");
    if ($css === false) continue;
    if (!preg_match_all('/([^{}]+)\{([^{}]+)\}/', $css, $blocks, PREG_SET_ORDER)) continue;
    foreach ($blocks as $b) {
        $sel = trim(preg_replace('/\s+/', ' ', $b[1]));
        $body = $b[2];
        $bg = preg_match('/(?:^|;)\s*background(?:-color)?\s*:\s*([^;]+)/', $body, $bm) ? trim($bm[1]) : null;
        $fg = preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/', $body, $fm) ? trim($fm[1]) : null;
        if ($bg === null || $fg === null) continue;
        if (stripos($bg, 'gradient') !== false || $bg === 'transparent' || $bg === 'none') continue;
        $bgc = resolve_color($bg, $VARS);
        $fgc = resolve_color($fg, $VARS);
        if ($bgc === null || $fgc === null) { $unresolved++; continue; }
        $pairs[] = array('file' => basename($f), 'sel' => mb_substr($sel, 0, 70),
                         'bg' => $bg, 'fg' => $fg, 'ratio' => round(ratio($bgc, $fgc), 2));
    }
}

/* ── الحكم: 4.5 نصًّا عاديًّا — و3.0 للكبيرِ وعناصرِ الواجهة (caption/badges تُحكم 4.5 تشددًا) ── */
$pass = 0; $fails = array(); $exempt = 0;
foreach ($pairs as $i => $p) {
    /* WCAG 1.4.3: «النصُّ في مكوّنٍ معطَّلٍ لا شرطَ تضادٍّ عليه» — يُعلن استثناءً لا يُخفى */
    if (preg_match('/is-disabled|:disabled|\[disabled\]/', $p['sel'])) {
        $pairs[$i]['exempt'] = true; $exempt++; continue;
    }
    if ($p['ratio'] >= 4.5) { $pass++; }
    else { $fails[] = $p; }
}
usort($fails, function ($a, $b) { return $a['ratio'] <=> $b['ratio']; });

echo "════ فحصُ التضادِّ — أزواجُ المكوّناتِ المعلنة ════\n";
$judged = count($pairs) - $exempt;
printf("أزواجٌ مقيسة: %d · مجتازة ≥4.5: %d (%.1f٪) · راسبة: %d · معطَّلةٌ مستثناةٌ بنصِّ WCAG 1.4.3: %d · متعذرُ الحلِّ (تدرجات/شفافيات): %d\n",
    $judged, $pass, $judged ? $pass * 100.0 / $judged : 0, count($fails), $exempt, $unresolved);
foreach ($fails as $p) {
    printf("  ✗ %.2f:1  %s — %s  (bg %s / fg %s)\n", $p['ratio'], $p['file'], $p['sel'], $p['bg'], $p['fg']);
}
if (in_array('--csv', $argv, true)) {
    $out = array("الملف\tالمحدد\tالخلفية\tالنص\tالنسبة\tالحكم");
    foreach ($pairs as $p) {
        $verdict = !empty($p['exempt']) ? 'مستثنى' : ($p['ratio'] >= 4.5 ? 'مجتاز' : 'راسب');
        $out[] = "{$p['file']}\t{$p['sel']}\t{$p['bg']}\t{$p['fg']}\t{$p['ratio']}\t{$verdict}";
    }
    file_put_contents("$ROOT/storage/reports/uxw01_contrast.csv", "\xEF\xBB\xBF" . implode("\n", $out));
    echo "⇒ storage/reports/uxw01_contrast.csv\n";
}
exit(count($fails) > 0 ? 1 : 0);
