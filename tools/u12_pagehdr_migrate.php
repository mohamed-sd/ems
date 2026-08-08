<?php
/**
 * tools/u12_pagehdr_migrate.php — AS-04/AS-05: رأسُ الصفحةِ الموحَّدُ لكلِّ شاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UXR-01 · AS-04 «سطرُ الأرقامِ السياقيةِ في الرأس» وAS-05 «شريطُ
 * الأفعالِ الموحَّد» — وكلاهما يسكن includes/page_header.php. تسعٌ وخمسون شاشةً
 * حيةً ما زالت تكتب رأسَها بيدها بثلاثةِ أعرافٍ قديمة، فلا شريطَ أفعالٍ موحَّدًا
 * ولا سطرَ سياقٍ ولا زرَّ بلاغٍ.
 *
 * الأعرافُ الثلاثةُ وما يصير إليه كلٌّ منها:
 *   (أ) <div class="ems-topbar"><h4><i class="أيقونة"></i> عنوان</h4>لواحق</div>
 *       ⇐ العنوانُ والأيقونةُ يذهبان للرأسِ الموحَّد، واللواحقُ تُلتقط بمخزنِ
 *         خرجٍ وتُمرَّر كعنصرِ فعلٍ خامٍّ فلا يضيع منها حرف.
 *   (ب) <h1 class="page-title|title-content|hero-title|head-title">عنوان</h1>
 *       ⇐ العنوانُ يذهب للرأسِ الموحَّد، والوعاءُ المحيطُ يبقى كما هو.
 *   (ج) بلا عنوانٍ ظاهرٍ ⇐ الرأسُ يُحقن بعنوانٍ من سجلِّ الشاشاتِ الحي.
 *
 * العنوانُ قد يحمل «<?= تعبير ?>» فيُحوَّل إلى وصلِ نصوصٍ في PHP — لا يُطرح.
 *
 * الحارساتُ: نسخةٌ احتياطية · فحصُ صياغةٍ وتراجعٌ عند الفساد · ولا حذفَ لسطر.
 *
 * التشغيل: php tools/u12_pagehdr_migrate.php [--dry] [--shape=a|b|c] [--file=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$shapeOnly = null; $only = null;
foreach ($argv as $a) {
    if (strpos($a, '--shape=') === 0) { $shapeOnly = strtolower(substr($a, 8)); }
    if (strpos($a, '--file=') === 0)  { $only = substr($a, 7); }
}
$BACKUP = $ROOT . '/storage/backups/u12_pagehdr_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

/* ── عناوينُ الشاشاتِ من السجلِّ الحي (للشكلِ ج) ─────────────────────────── */
$titles = array();
$dbFile = $ROOT . '/config.php';
if (is_file($dbFile)) {
    $env = $ROOT . '/.env';
    $cfg = array('host' => 'localhost:3307', 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
    if (is_file($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            if (strpos($ln, '=') === false || $ln[0] === '#') { continue; }
            list($k, $v) = explode('=', $ln, 2);
            $k = trim($k); $v = trim($v);
            if ($k === 'DB_HOST') { $cfg['host'] = $v; }
            if ($k === 'DB_USER') { $cfg['user'] = $v; }
            if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
            if ($k === 'DB_NAME') { $cfg['db']   = $v; }
        }
    }
    $hp = explode(':', $cfg['host']);
    $mysqli = @new mysqli($hp[0], $cfg['user'], $cfg['pass'], $cfg['db'], isset($hp[1]) ? (int) $hp[1] : 3306);
    if ($mysqli && !$mysqli->connect_errno) {
        $mysqli->set_charset('utf8mb4');
        if ($r = @$mysqli->query("SELECT code, name FROM modules WHERE code IS NOT NULL AND code <> ''")) {
            while ($row = $r->fetch_assoc()) {
                $titles[str_replace('\\', '/', trim($row['code'], '/'))] = trim((string) $row['name']);
            }
        }
    }
}

/** «نصٌّ فيه <?= تعبير ?>» ⇐ تعبيرُ PHP يُنتج النصَّ نفسَه */
function ph_expr($html)
{
    $out = array(); $pos = 0; $n = strlen($html);
    while ($pos < $n) {
        $o = strpos($html, '<?', $pos);
        if ($o === false) { $out[] = ph_lit(substr($html, $pos)); break; }
        if ($o > $pos) { $out[] = ph_lit(substr($html, $pos, $o - $pos)); }
        $c = strpos($html, '?>', $o);
        if ($c === false) { $out[] = ph_lit(substr($html, $pos)); break; }
        $code = substr($html, $o, $c - $o + 2);
        $inner = preg_replace('~^<\?(?:php\s+echo|=)\s*~', '', substr($code, 0, -2));
        $inner = rtrim(trim($inner), ';');
        if ($inner === '' || $inner === substr($code, 0, -2)) { $out[] = ph_lit($code); }
        else { $out[] = '(' . $inner . ')'; }
        $pos = $c + 2;
    }
    $out = array_values(array_filter($out, function ($s) { return $s !== "''"; }));
    if (!$out) { return "''"; }
    return implode(' . ', $out);
}
function ph_lit($s)
{
    $s = preg_replace('~\s+~u', ' ', $s);
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $s) . "'";
}

/**
 * يُرجع داخلَ أولِ `<div class="X">` مطابقًا بعدَّادِ عمقٍ — لا بأولِ `</div>`.
 * التعبيرُ غيرُ الجَشِعِ يقف عند إغلاقِ أولِ وعاءٍ داخليٍّ فيبتر العنوانَ، وهذا
 * سببُ رأسٍ بلا عنوان. فالمطابقةُ هنا بالعمقِ لا بالجوار.
 */
function ph_inner_div($block, $class)
{
    if (!preg_match('~<div[^>]*class="' . preg_quote($class, '~') . '"[^>]*>~i', $block, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $open = $m[0][1];
    $bodyStart = $open + strlen($m[0][0]);
    $end = ph_match_div($block, $open);
    if ($end === false) { return null; }
    return array(substr($block, $bodyStart, $end - 6 - $bodyStart), $open, $end);
}

/** يعثر على `</div>` المُطابِق لفتحةِ div عند $start */
function ph_match_div($src, $start)
{
    $depth = 0; $i = $start;
    $n = strlen($src);
    while ($i < $n) {
        $o = stripos($src, '<div', $i);
        $c = stripos($src, '</div>', $i);
        if ($c === false) { return false; }
        if ($o !== false && $o < $c) { $depth++; $i = $o + 4; continue; }
        $depth--;
        if ($depth === 0) { return $c + 6; }
        $i = $c + 6;
    }
    return false;
}

$php = PHP_BINARY;
$stat = array('a' => 0, 'b' => 0, 'c' => 0, 'skip' => 0, 'revert' => 0, 'locked' => 0);
$notes = array();

$files = array();
if ($only !== null) { $files[] = $ROOT . '/' . ltrim($only, '/'); }
else { foreach ($dirs as $d) { foreach (glob($ROOT . '/' . $d . '/*.php') as $f) { $files[] = $f; } } }

foreach ($files as $f) {
    $src = (string) @file_get_contents($f);
    if ($src === '') { continue; }
    $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    if (strpos($src, 'insidebar') === false) { continue; }
    if (strpos($src, 'page_header.php') !== false) { continue; }

    $orig = $src;
    $depth = substr_count($rel, '/');
    $inc = "include " . ($depth === 1 ? "__DIR__ . '/../includes/page_header.php'" : "__DIR__ . '/includes/page_header.php'") . ";";
    $shape = null;

    /* ── (أ) عرفُ ems-topbar ─────────────────────────────────────────────── */
    $ot = strpos($src, '<div class="ems-topbar">');
    if ($ot !== false) {
        $end = ph_match_div($src, $ot);
        if ($end !== false) {
            $block = substr($src, $ot, $end - $ot);
            if (preg_match('~<h4[^>]*>(.*?)</h4>~su', $block, $mh)) {
                $inner = $mh[1];
                $icon = 'fas fa-circle';
                if (preg_match('~<i class="([^"]+)"[^>]*>\s*</i>~su', $inner, $mi)) {
                    $icon = $mi[1];
                    $inner = str_replace($mi[0], '', $inner);
                }
                $titleExpr = ph_expr(trim($inner));
                /* اللواحقُ: ما بعدَ </h4> حتى نهايةِ الوعاء */
                $afterH4 = substr($block, strpos($block, $mh[0]) + strlen($mh[0]));
                $afterH4 = preg_replace('~</div>\s*$~', '', $afterH4);
                $extras = trim($afterH4);

                $repl = "<?php\n"
                    . "/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —\n"
                    . "   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */\n"
                    . "\$header_icon = " . ph_lit($icon) . ";\n"
                    . "\$header_title_html = htmlspecialchars(" . $titleExpr . ", ENT_QUOTES, 'UTF-8');\n";
                if ($extras !== '') {
                    $repl .= "ob_start(); ?>" . $extras . "<?php\n"
                        . "\$header_actions = array(array('raw' => trim((string) ob_get_clean())));\n";
                } else {
                    $repl .= "\$header_actions = array();\n";
                }
                $repl .= "\$header_back = false;\n" . $inc . "\n?>";

                $src = substr($src, 0, $ot) . $repl . substr($src, $end);
                $shape = 'a';
            }
        }
    }

    /* ── (ب١) عرفُ الرأسِ اليدويِّ المُحاكي: .header ⊃ .actions/.title/.back ─
       هذه شاشاتٌ بنت بيدها ما يفعله الرأسُ الموحَّد. فالوعاءُ كلُّه يُستبدل —
       لا يُحشى العنوانُ داخلَه — وإلا صار رأسٌ داخلَ رأس. أفعالُه وزرُّ عودتِه
       يُنقلان بمخزنِ خرجٍ فلا يضيع منها زرّ. */
    if ($shape === null && preg_match('~<div[^>]*class="header"[^>]*>~i', $src, $mw, PREG_OFFSET_CAPTURE)) {
        $wStart = $mw[0][1];
        $wEnd = ph_match_div($src, $wStart);
        if ($wEnd !== false) {
            $wBlock = substr($src, $wStart, $wEnd - $wStart);
            $mt = ph_inner_div($wBlock, 'title');
            if ($mt !== null) {
                $inner = $mt[0];
                $icon = 'fas fa-circle';
                if (preg_match('~<i class="([^"]+)"[^>]*>\s*</i>~su', $inner, $mi)) {
                    $icon = $mi[1];
                    $inner = str_replace($mi[0], '', $inner);
                }
                $titleExpr = ph_expr(trim(strip_tags_keep_php($inner)));
                $ma = ph_inner_div($wBlock, 'actions');
                $actHtml = $ma !== null ? trim($ma[0]) : '';
                $mb = ph_inner_div($wBlock, 'back');
                $backHtml = $mb !== null ? trim($mb[0]) : '';

                $repl = "<?php\n"
                    . "/* AS-04/AS-05 (UXR-01): الرأسُ الموحَّدُ بدلَ الرأسِ اليدويِّ المُحاكي —\n"
                    . "   مصدرٌ واحدٌ للبنيةِ، والأفعالُ وزرُّ العودةِ منقولانِ كما هما. */\n"
                    . "\$header_icon = " . ph_lit($icon) . ";\n"
                    . "\$header_title_html = htmlspecialchars(" . $titleExpr . ", ENT_QUOTES, 'UTF-8');\n";
                if ($actHtml !== '') {
                    $repl .= "ob_start(); ?>" . $actHtml . "<?php\n"
                        . "\$header_actions = array(array('raw' => trim((string) ob_get_clean())));\n";
                } else { $repl .= "\$header_actions = array();\n"; }
                if ($backHtml !== '') {
                    $repl .= "ob_start(); ?>" . $backHtml . "<?php\n"
                        . "\$header_back = array('raw' => trim((string) ob_get_clean()));\n";
                } else { $repl .= "\$header_back = false;\n"; }
                $repl .= $inc . "\n?>";

                $src = substr($src, 0, $wStart) . $repl . substr($src, $wEnd);
                $shape = 'b';
            }
        }
    }

    /* ── (ب٢) عرفُ h1 المفرد ─────────────────────────────────────────────── */
    if ($shape === null
        && preg_match('~<h1[^>]*class="(?:page-title|title-content|hero-title|head-title)[^"]*"[^>]*>(.*?)</h1>~su', $src, $m1)) {
        $inner = $m1[1];
        $icon = 'fas fa-circle';
        if (preg_match('~<i class="([^"]+)"[^>]*>\s*</i>~su', $inner, $mi)) {
            $icon = $mi[1];
            $inner = str_replace($mi[0], '', $inner);
        }
        $titleExpr = ph_expr(trim(strip_tags_keep_php($inner)));
        $repl = "<?php\n"
            . "/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ العنوانِ اليدويّ. */\n"
            . "\$header_icon = " . ph_lit($icon) . ";\n"
            . "\$header_title_html = htmlspecialchars(" . $titleExpr . ", ENT_QUOTES, 'UTF-8');\n"
            . "\$header_actions = array();\n\$header_back = false;\n" . $inc . "\n?>";
        $src = str_replace($m1[0], $repl, $src);
        $shape = 'b';
    }

    /* ── (ج) بلا عنوانٍ ظاهر ─────────────────────────────────────────────── */
    if ($shape === null) {
        $key = $rel;
        $title = isset($titles[$key]) ? $titles[$key] : '';
        if ($title === '') {
            $title = ucwords(str_replace(array('_', '-'), ' ', basename($rel, '.php')));
        }
        /* موضعُ الحقن: بعد أولِ `<div class="main"…>` — وإلا بعد تضمينِ السايدبار */
        $pos = null; $tail = 0;
        if (preg_match('~<div[^>]*class="[^"]*\bmain\b[^"]*"[^>]*>~i', $src, $mm, PREG_OFFSET_CAPTURE)) {
            $pos = $mm[0][1] + strlen($mm[0][0]);
        } elseif (preg_match('~(include|require)(_once)?[^;]*insidebar\.php[^;]*;~i', $src, $mm, PREG_OFFSET_CAPTURE)) {
            $pos = $mm[0][1] + strlen($mm[0][0]);
        }
        if ($pos === null) { $stat['skip']++; $notes[] = $rel . ' — بلا موضعِ حقنٍ آمن'; continue; }
        $repl = "\n<?php\n"
            . "/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */\n"
            . "\$header_icon = 'fas fa-window-maximize';\n"
            . "\$header_title_html = htmlspecialchars(" . ph_lit($title) . ", ENT_QUOTES, 'UTF-8');\n"
            . "\$header_actions = array();\n\$header_back = false;\n" . $inc . "\n?>\n";
        $src = substr($src, 0, $pos) . $repl . substr($src, $pos);
        $shape = 'c';
    }

    if ($shapeOnly !== null && $shape !== $shapeOnly) { continue; }
    if ($dry) { $stat[$shape]++; continue; }
    if (!is_writable($f)) { $stat['locked']++; $notes[] = $rel . ' — مقفلٌ خارجيًّا'; continue; }

    @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
    if (@file_put_contents($f, $src) === false) { $stat['locked']++; $notes[] = $rel . ' — تعذّرت الكتابة'; continue; }
    $lint = array(); $rc = 0;
    exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
    if ($rc !== 0) {
        file_put_contents($f, $orig);
        $stat['revert']++;
        $notes[] = $rel . ' — تراجعٌ: ' . trim(implode(' ', $lint));
        continue;
    }
    $stat[$shape]++;
}

/** يزيل الوسومَ ويُبقي شيفرةَ PHP القصيرة */
function strip_tags_keep_php($s)
{
    $s = preg_replace('~<(?!\?)[^>]*>~su', '', $s);
    return $s;
}

echo 'AS-04/AS-05 — رأسُ الصفحةِ الموحَّد' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 56), "\n";
echo "الشكلُ (أ) ems-topbar: {$stat['a']}\n";
echo "الشكلُ (ب) h1 معنون: {$stat['b']}\n";
echo "الشكلُ (ج) بلا رأس: {$stat['c']}\n";
echo "تُخطّي: {$stat['skip']}  ·  تراجعٌ: {$stat['revert']}  ·  مقفلٌ: {$stat['locked']}\n";
if ($notes) {
    echo "\nالتفصيل:\n";
    foreach (array_slice($notes, 0, 25) as $s) { echo '  · ' . $s . "\n"; }
}
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
