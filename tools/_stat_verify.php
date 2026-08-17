<?php
/**
 * tools/_stat_verify.php — تصييرُ بطاقاتِ الإحصاءِ من كلِّ صفحةٍ معًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُقتطع من كلِّ ملفٍّ **ما هو مشحونٌ فعلًا** من كتلِ البطاقات، وتُنزع كتلُ
 *   PHP، وتُصيَّر كلُّها في صفحةٍ واحدةٍ تحمل سلسلةَ الأنماطِ نفسَها — فيُقاس
 *   أن التصميمَ الموحَّدَ يبلغها جميعًا مهما اختلفت أسماءُ أصنافِها.
 * ◆ الحدُّ: بنيةٌ وتخطيطٌ فقط — لا قيمَ ولا بيانات.
 * ◆ تُحذف بعدَ إغلاقِ الجولة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
$ROOT = dirname(__DIR__);
$V = function ($f) use ($ROOT) { $p = $ROOT . '/assets/css/' . $f; return is_file($p) ? ('?v=' . filemtime($p)) : ''; };
$CSS = array('all.min.css','local-fonts.css','design-tokens.css','uxui-tokens.css','uxui-components.css',
             'ems.main.all.style.css','ems-tables.css','ems-forms.css','ems-buttons.css','ems-journey.css',
             'ems-alerts.css','ems-shell.css','ems-states.css','ems-screens.css','ems-ticket.css',
             'ems-profile.css','ems-filters.css','ems-statcards.css');

$FAM  = '~(^|-)(stat|stats|kpi|metric|metrics|tile)(-|$)|(^|-)summary-(card|item|box)(-|$)~i';
$SKIP = '~^(counter-field|counter-input-group|counter-separator|counter-seg|required-indicator|rtl-number)$~i';
$PART = '~-(icon|ico|img|title|value|val|label|lbl|num|number|text|desc|caption|cap|foot|head|header|meta|badge|body|sub|note|link|arrow|trend|delta|unit|row|col|wrap|inner|content|line|main|info|left|right|top|bottom|bar|dot|chip|pill|tag|hint|grid|section|container|list)$~i';
$VAL  = '~class\s*=\s*"[^"]*(?:^|[\s-])(value|val|num|number|count|figure|amount)(?![a-z])~i';
$LBL  = '~class\s*=\s*"[^"]*(?:^|[\s-])(title|label|lbl|cap|caption|name|text|desc)(?![a-z])~i';

function htmlOf($src) {
    $out = ''; $toks = @token_get_all($src); if (!$toks) { return $src; }
    $in = false;
    foreach ($toks as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { $out .= ' '; continue; }
            if ($t[0] === T_OPEN_TAG || $t[0] === T_OPEN_TAG_WITH_ECHO) { $in = true; $out .= '◆'; continue; }
            if ($t[0] === T_CLOSE_TAG) { $in = false; $out .= ' '; continue; }
            if ($t[0] === T_INLINE_HTML) { $out .= $t[1]; continue; }
            $out .= $in ? '' : $t[1];
        } else { $out .= $in ? '' : $t; }
    }
    return preg_replace('~<!--.*?-->~s', ' ', $out);
}
function blockAt($src, $i, $tag) {
    $open = '<' . $tag; $close = '</' . $tag . '>';
    $ol = strlen($open); $cl = strlen($close);
    $depth = 0; $j = $i; $n = strlen($src);
    while ($j < $n) {
        if (strcasecmp(substr($src, $j, $ol), $open) === 0) { $depth++; $j += $ol; continue; }
        if (strcasecmp(substr($src, $j, $cl), $close) === 0) {
            $depth--; $j += $cl;
            if ($depth === 0) { return substr($src, $i, $j - $i); }
            continue;
        }
        $j++;
    }
    return null;
}
function cardsOf($html, $FAM, $SKIP, $PART, $VAL, $LBL) {
    $res = array();
    if (!preg_match_all('~<(div|a|li|section|article|span)\b[^>]*\bclass\s*=\s*"([^"]*)"[^>]*>~i', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) { return $res; }
    $taken = array();
    foreach ($m as $hit) {
        $off = $hit[0][1]; $tag = strtolower($hit[1][0]);
        $fam = null;
        foreach (preg_split('~\s+~', trim($hit[2][0])) as $tk) {
            if ($tk === '' || preg_match($SKIP, $tk) || preg_match($PART, $tk)) { continue; }
            if (preg_match($FAM, $tk)) { $fam = $tk; break; }
        }
        if ($fam === null) { continue; }
        $skip = false;
        foreach ($taken as $t) { if ($off > $t[0] && $off < $t[1]) { $skip = true; break; } }
        if ($skip) { continue; }
        $blk = blockAt($html, $off, $tag);
        if ($blk === null) { continue; }
        $inner = 0;
        if (preg_match_all('~<(?:div|a|li|section|article|span)\b[^>]*\bclass\s*=\s*"([^"]*)"[^>]*>~i', substr($blk, strlen($hit[0][0])), $mi)) {
            foreach ($mi[1] as $ic) {
                foreach (preg_split('~\s+~', trim($ic)) as $itk) {
                    if ($itk === '' || preg_match($SKIP, $itk) || preg_match($PART, $itk)) { continue; }
                    if (preg_match($FAM, $itk)) { $inner++; break; }
                }
            }
        }
        if ($inner >= 2) { continue; }
        $hasVal = preg_match($VAL, $blk) || strpos($blk, '◆') !== false || preg_match('~>\s*[\d٠-٩][\d٠-٩,.]*\s*<~u', $blk);
        $hasLbl = preg_match($LBL, $blk) || preg_match('~[\x{0600}-\x{06FF}]~u', strip_tags($blk))
               || substr_count($blk, '◆') >= 2 || preg_match_all('~<(div|span|p|h[1-6]|small|strong|b)\b~i', $blk) >= 2;
        if (!$hasVal || !$hasLbl) { continue; }
        $taken[] = array($off, $off + strlen($blk));
        $res[] = $blk;
    }
    return $res;
}
/** ◆ داخلَ `class="…"` يصير مخرَجُ PHP **فراغًا** لا رقمًا: المصدرُ يكتب
 *  `class="stat-value<?php echo $x; ?>"` فوضعُ رقمٍ مكانَه يلحم الرمزَ
 *  (`stat-value٠`) فيتغيّر معناه ويُقرأ بطاقةً وهو قيمة. وخارجَها يصير رقمًا
 *  ليظهر للعينِ نصٌّ معقول. */
function stripPhp($h) {
    $h = preg_replace_callback('~class\s*=\s*"[^"]*"~i', function ($m) {
        return str_replace('◆', ' ', $m[0]);
    }, $h);
    return str_replace('◆', '٠', $h);
}

$listFile = __DIR__ . '/_stat_pages.txt';
$pages = is_file($listFile) ? array_filter(array_map('trim', explode("\n", (string) file_get_contents($listFile)))) : array();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تحقُّقُ بطاقاتِ الإحصاء</title>
<link rel="stylesheet" href="/ems/assets/css/bootstrap.min.css">
<?php foreach ($CSS as $f): ?>
<link rel="stylesheet" href="/ems/assets/css/<?php echo $f . $V($f); ?>">
<?php endforeach; ?>
<style>.vp-h{font:700 12px/1.6 monospace;margin:16px 0 4px;color:#444}.vp-miss{color:#991B1B;font-weight:700}</style>
</head>
<body class="ems-site">
<div class="main" style="padding:16px">
<?php
$i = 0;
foreach ($pages as $pg) {
    $abs = $ROOT . '/' . $pg;
    if (!is_file($abs)) { continue; }
    $cards = cardsOf(htmlOf((string) file_get_contents($abs)), $FAM, $SKIP, $PART, $VAL, $LBL);
    $i++;
    echo '<div class="vp-h" data-pg="' . htmlspecialchars($pg) . '">' . $i . ' · ' . htmlspecialchars($pg)
       . ' — ' . count($cards) . " بطاقة</div>\n";
    if (!$cards) { echo '<div class="vp-miss" data-empty="' . htmlspecialchars($pg) . '">✘ لا كتلةَ بطاقةٍ حرفية (عونٌ مركزيٌّ أو حلقةُ PHP)</div>' . "\n"; continue; }
    echo '<div data-vp="' . htmlspecialchars($pg) . '"><div>';
    foreach ($cards as $c) { echo stripPhp($c); }
    echo "</div></div>\n";
}
?>
</div>
<script src="/ems/assets/js/ems-statcards.js<?php
    $j = $ROOT . '/assets/js/ems-statcards.js'; echo is_file($j) ? ('?v=' . filemtime($j)) : ''; ?>"></script>
</body>
</html>
