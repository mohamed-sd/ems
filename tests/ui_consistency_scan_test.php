<?php
/**
 * tests/ui_consistency_scan_test.php — شاهدانِ يُقاسان بالمسحِ لا بالتصيير
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0593 · INJ-0498
 *
 * ── INJ-0593 · رؤوسٌ لاتينيةٌ بلا مقابلٍ عربيٍّ في المشتريات ────────────────
 * «Min» و«Max» في كتالوجِ الأصناف، و«Min»/«Max»/«ROP» في حدودِ إعادةِ الطلب —
 * بلا مقابلٍ عربيٍّ ولا تلميح. والقبولُ: مسحُ رؤوسِ الجداولِ في `Procurement/`
 * يعيد **صفرَ رأسٍ لاتينيٍّ غيرِ مصحوبٍ بعربيّ**.
 *
 * ── INJ-0498 · نسختا بوتستراب متعارضتان ────────────────────────────────────
 * القشرةُ الرئيسةُ تحمّل `bootstrap.min.css` وصفحاتُ التقاريرِ `bootstrap.rtl.min.css`
 * — نسختانِ بمحاذاتين. والقبولُ: إحدى القائمتين **فارغة**.
 * والاتجاهُ المختارُ هو موافقةُ القشرةِ (٣٤٦ شاشة) لا العكس: أقلُّ تغييرٍ وأقلُّ
 * مجالِ انفجار.
 *
 * ── والمسحُ يستثني ما ليس حيًّا ──────────────────────────────────────────────
 * `storage/backups/` و`.claude/worktrees/` و`vendor/` ليست شفرةً تُصيَّر — وعدُّها
 * يجعل الفاحصَ يرسب أبدًا على نُسَخٍ ميتة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* ◆ و`tests/` تُستثنى أيضًا: **هذا الفاحصُ نفسُه** يذكر اسمَي الملفَّين نصًّا،
     فأوّلُ جولةٍ رسبت عليه هو — فاحصٌ يرسب على ذكرِ نفسِه لا على الواقع. */
$dead = '~[\\\\/](storage[\\\\/]backups|\.claude[\\\\/]worktrees|vendor|node_modules|tests|tools|docs)[\\\\/]~';
$live = function ($p) use ($dead) { return !preg_match($dead, $p); };

/* ══ INJ-0593 ══════════════════════════════════════════════════════════════ */
$say('══ INJ-0593 · صفرُ رأسٍ لاتينيٍّ بلا مقابلٍ عربيٍّ في المشتريات');
$files = glob($ROOT . '/Procurement/*.php');
$ok(count($files) > 0, 'مجلدُ المشترياتِ فيه ' . count($files) . ' ملفًّا يُمسح');
$scanned = 0; $heads = 0; $latin = array();
foreach ($files as $f) {
    if (!$live($f)) { continue; }
    $src = (string) file_get_contents($f);
    if (!preg_match_all('~<th\b([^>]*)>(.*?)</th>~su', $src, $m, PREG_SET_ORDER)) { continue; }
    $scanned++;
    foreach ($m as $x) {
        $attrs = (string) $x[1];
        $txt = trim(strip_tags((string) $x[2]));
        /* رأسٌ **مولَّدٌ** لا نصٌّ ثابت: `<?= …`  أو تركيبُ سلسلةٍ `' . $lbl . '`.
           فمحتواه يُعرف عند التنفيذِ لا في المصدر، ولا يصحُّ الحكمُ على حرفِه. */
        if ($txt === '' || strpos($txt, '<?') !== false
            || strpos($txt, '$') !== false || strpos($txt, '<%') !== false) { continue; }
        $heads++;
        $hasArabic = (bool) preg_match('~[\x{0600}-\x{06FF}]~u', $txt);
        $hasLatin  = (bool) preg_match('~[A-Za-z]{2,}~', $txt);
        if ($hasLatin && !$hasArabic) {
            /* تلميحٌ عربيٌّ في `title` يكفي — فالمقابلُ حاضرٌ للقارئ */
            if (preg_match('~title="[^"]*[\x{0600}-\x{06FF}][^"]*"~u', $attrs)) { continue; }
            $latin[] = basename($f) . ': «' . $txt . '»';
        }
    }
}
$ok($scanned > 0 && $heads > 20, "مُسحت {$heads} ترويسةً في {$scanned} ملفًّا",
    'قليلٌ جدًّا — فالمسحُ لا يقيس شيئًا');
$ok(empty($latin), '**صفرُ رأسٍ لاتينيٍّ بلا عربيّ** (' . count($latin) . ')',
    implode(' · ', array_slice($latin, 0, 6)));

/* ══ INJ-0498 ══════════════════════════════════════════════════════════════ */
$say('');
$say('══ INJ-0498 · نسخةُ بوتستراب واحدةٌ في الشفرةِ الحية');
$rtl = array(); $ltr = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = $p->getPathname();
    if (substr($path, -4) !== '.php' || !$live($path)) { continue; }
    $src = (string) @file_get_contents($path);
    /* الأداةُ التي تسرد أسماءَ الأصولِ ليست شاشةً تُحمّلها */
    if (strpos($src, 'bootstrap.rtl.min.css') !== false && strpos($path, 'tools' . DIRECTORY_SEPARATOR) === false) {
        $rtl[] = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path);
    }
    if (strpos($src, 'bootstrap.min.css') !== false && strpos($path, 'tools' . DIRECTORY_SEPARATOR) === false) {
        $ltr[] = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path);
    }
}
$ok(count($ltr) > 0 || count($rtl) > 0, 'الشفرةُ الحيةُ تحمّل بوتستراب في '
    . (count($ltr) + count($rtl)) . ' ملفًّا');
$ok(empty($rtl) || empty($ltr), '**إحدى النسختين غيرُ مستعملةٍ في الشفرةِ الحية** (RTL='
    . count($rtl) . ' · LTR=' . count($ltr) . ')',
    'RTL: ' . implode(' · ', array_slice($rtl, 0, 4)) . '  ‖  LTR: ' . implode(' · ', array_slice($ltr, 0, 4)));
$ok(!empty($ltr), 'والقشرةُ الرئيسةُ تحمّل نسختَها فعلًا — فالتوحيدُ ليس حذفًا');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
