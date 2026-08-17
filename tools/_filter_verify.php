<?php
/**
 * tools/_filter_verify.php — تصييرُ صناديقِ الفلاترِ الأربعةِ والثلاثين معًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الغاية: إثباتُ أن **الشفرةَ المشحونةَ فعلًا** في كلِّ صفحةٍ تُنتج الصندوقَ
 *   الذهبيَّ — لا نموذجًا في مِجَسٍّ كتبتُه بيدي. فيُقتطع من كلِّ ملفٍّ كتلةُ
 *   `<div class="filter">…</div>` كما هي، وتُنزع منها كتلُ PHP (القيمُ تصير
 *   فارغةً والحلقةُ تُصيَّر دورةً واحدة) — والبنيةُ هي المقصودةُ بالقياس:
 *   الأغلفةُ والعناوينُ والضوابطُ والأزرارُ ومَن يملأ السطر.
 * ◆ الحدُّ: لا يقيس القيمَ ولا الخيارات — تلك شأنُ الخادم، وهذا فحصُ تخطيط.
 * ◆ تُحذف بعدَ إغلاقِ الجولة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
$ROOT = dirname(__DIR__);
$V = function ($f) use ($ROOT) {
    $p = $ROOT . '/assets/css/' . $f;
    return is_file($p) ? ('?v=' . filemtime($p)) : '';
};
$CSS = array(
    'all.min.css', 'local-fonts.css', 'design-tokens.css', 'uxui-tokens.css',
    'uxui-components.css', 'ems.main.all.style.css', 'ems-tables.css',
    'ems-forms.css', 'ems-buttons.css', 'ems-journey.css', 'ems-alerts.css',
    'ems-shell.css', 'ems-states.css', 'ems-screens.css', 'ems-ticket.css',
    'ems-profile.css', 'ems-filters.css',
);

/** يقتطع أولَ كتلةِ الصندوقِ كاملةً بموازنةِ الوسوم.
 *  ◆ الصندوقُ يقع بوسمَين في النظام: `<div class="filter">` (الغالب) و
 *    `<form … class="filter">` (خمسُ شاشاتٍ: `stock_proc` وأخواتُها). فلولا
 *    قبولُ الاثنين لسقطت الخمسُ من القياسِ وظُنَّت بلا صندوق. */
function grabFilterBox($src) {
    $best = null;
    foreach (array('div', 'form') as $tag) {
        if (!preg_match('~<' . $tag . '\b[^>]*\bclass\s*=\s*"[^"]*(?<![\w-])filter(?![\w-])[^"]*"[^>]*>~i', $src, $m, PREG_OFFSET_CAPTURE)) { continue; }
        $i = $m[0][1];
        if ($best !== null && $i > $best[0]) { continue; }
        $depth = 0; $j = $i; $n = strlen($src); $open = '<' . $tag; $close = '</' . $tag . '>';
        $ol = strlen($open); $cl = strlen($close);
        while ($j < $n) {
            if (substr($src, $j, 2) === '<?') { $k = strpos($src, '?>', $j); $j = ($k === false) ? $n : $k + 2; continue; }
            if (strcasecmp(substr($src, $j, $ol), $open) === 0) { $depth++; $j += $ol; continue; }
            if (strcasecmp(substr($src, $j, $cl), $close) === 0) {
                $depth--; $j += $cl;
                if ($depth === 0) { $best = array($i, substr($src, $i, $j - $i)); break; }
                continue;
            }
            $j++;
        }
    }
    return $best === null ? null : $best[1];
}

/** ينزع كتلَ PHP فتبقى البنيةُ الساكنةُ وحدَها */
function stripPhp($html) {
    $html = preg_replace('~<\?php.*?\?>~s', '', $html);
    $html = preg_replace('~<\?=.*?\?>~s', '', $html);
    $html = preg_replace('~<\?php.*$~s', '', $html);
    return $html;
}

$listFile = __DIR__ . '/_filter_pages.txt';
$pages = is_file($listFile)
    ? array_filter(array_map('trim', explode("\n", (string) file_get_contents($listFile))))
    : array();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تحقُّقُ صناديقِ الفلاتر</title>
<link rel="stylesheet" href="/ems/assets/css/bootstrap.min.css">
<?php foreach ($CSS as $f): ?>
<link rel="stylesheet" href="/ems/assets/css/<?php echo $f . $V($f); ?>">
<?php endforeach; ?>
<style>
  .vp-h { font: 700 13px/1.6 monospace; margin: 18px 0 4px; color: #444; }
  .vp-miss { color: #991B1B; font-weight: 700; }
</style>
</head>
<body class="ems-site">
<div class="main" style="padding:16px">
<?php
$i = 0;
foreach ($pages as $pg) {
    $abs = $ROOT . '/' . $pg;
    if (!is_file($abs)) { echo '<div class="vp-h vp-miss">✘ ' . htmlspecialchars($pg) . " — غيرُ موجود</div>\n"; continue; }
    $box = grabFilterBox((string) file_get_contents($abs));
    $i++;
    echo '<div class="vp-h" data-pg="' . htmlspecialchars($pg) . '">' . $i . ' · ' . htmlspecialchars($pg) . "</div>\n";
    if ($box === null) { echo '<div class="vp-miss">✘ لا كتلةَ <code>&lt;div class="filter"&gt;</code></div>' . "\n"; continue; }
    echo '<div data-vp="' . htmlspecialchars($pg) . '">' . stripPhp($box) . "</div>\n";
}
?>
</div>
<script src="/ems/assets/js/ems-filters.js<?php
    $j = $ROOT . '/assets/js/ems-filters.js';
    echo is_file($j) ? ('?v=' . filemtime($j)) : ''; ?>"></script>
</body>
</html>
