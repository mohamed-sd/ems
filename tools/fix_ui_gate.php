<?php
/**
 * tools/fix_ui_gate.php — بوابةُ FIX-02 (القشرةُ والنظامُ التصميميّ)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الجذريُّ في FIX-02 (FIXB-0001): «النظامُ التصميميُّ مبنيٌّ **ولا
 *   يُفرَض** — فالتصحيحُ في الإنفاذِ لا في التصميم». و«الفاحصُ الذي **يمنع**
 *   أهمُّ من الفاحصِ الذي **يعدّ** — فالعدُّ يوثّق والمنعُ يُصلح» (RSK-U4).
 *
 * ◆ ولذلك تُقاس هنا معاييرُ AC-U1..AC-U12 بأرقامٍ حيةٍ من الشجرةِ نفسِها،
 *   ويُعلَن لكلِّ معيارٍ ما يقيسه وما لا يقيسه. والأرقامُ الباقيةُ ديْنٌ معلَنٌ
 *   لا اكتمالٌ مُدَّعى.
 *
 * التشغيل: php tools/fix_ui_gate.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$R = array();
function u($code, $title, $measures, $notMeasures, $ok, $evidence)
{
    global $R;
    $R[] = compact('code', 'title', 'measures', 'notMeasures', 'ok', 'evidence');
    printf("%s %-7s %s\n        يقيس: %s\n        لا يقيس: %s\n        الشاهد: %s\n\n",
        $ok ? '✔' : '✘', $code, $title, $measures, $notMeasures, $evidence);
}

/** كلُّ ملفات الواجهةِ الحية (php/css/js) بلا نسخٍ ولا بائعين. */
function ui_files($ROOT, array $exts)
{
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        if (fix_is_skipped($rel)) { continue; }
        if (!in_array(strtolower($f->getExtension()), $exts, true)) { continue; }
        $out[] = $rel;
    }
    sort($out);
    return $out;
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " بوابةُ FIX-02 — القشرةُ والنظامُ التصميميُّ · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

/* ══ AC-U1 · ملفٌّ واحدٌ يُصدِر الترويسة ═══════════════════════════════════ */
$htmlEmitters = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (preg_match('/<html\b/i', $src)) { $htmlEmitters[] = $rel; }
}
u('AC-U1', 'ملفٌّ واحدٌ يُصدِر الترويسةَ لا سبعة',
    'يعدُّ ملفاتِ PHP التي تُصدِر وسمَ <html> نصًّا',
    'لا يقيس القوالبَ التي تُصدِرها بمتغيّرٍ مركَّب',
    count($htmlEmitters) <= 1,
    count($htmlEmitters) . ' ملفًّا يُصدِر <html>' . ($htmlEmitters ? ': ' . implode(' · ', array_slice($htmlEmitters, 0, 8)) : ''));

/* ══ AC-U2 · صفرُ لونٍ حرفيٍّ خارجَ ملفِّ الرموز ══════════════════════════ */
$tokenFile = 'assets/css/design-tokens.css';
$colorHits = 0; $colorFiles = array(); $distinct = array();
foreach (ui_files($ROOT, array('css')) as $rel) {
    if ($rel === $tokenFile) { continue; }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $src, $m)) {
        $colorHits += count($m[0]);
        $colorFiles[$rel] = count($m[0]);
        foreach ($m[0] as $c) { $distinct[strtolower($c)] = true; }
    }
}
arsort($colorFiles);
u('AC-U2', 'صفرُ قيمةٍ لونيةٍ حرفيةٍ خارجَ ملفِّ الرموز',
    'يعدُّ قيمَ الألوانِ الحرفيةَ في كلِّ ملفاتِ CSS الحيةِ عدا ملفِّ الرموز',
    'لا يقيس الألوانَ داخلَ سماتِ style في HTML/PHP — تُقاس في AC-U12',
    $colorHits === 0,
    "{$colorHits} قيمةً لونيةً حرفيةً · متمايزٌ منها " . count($distinct)
        . ' · أكثرُها: ' . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($colorFiles), 0, 3), array_slice($colorFiles, 0, 3))));

/* ══ AC-U3 · أربعةُ أصنافِ أزرار ══════════════════════════════════════════ */
$btnClasses = array(); $btnUses = 0;
foreach (ui_files($ROOT, array('php', 'css', 'js')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/\bbtn-[a-z0-9_-]+/i', $src, $m)) {
        foreach ($m[0] as $c) { $btnClasses[strtolower($c)] = ($btnClasses[strtolower($c)] ?? 0) + 1; $btnUses++; }
    }
}
u('AC-U3', 'أربعةُ أصنافِ أزرارٍ لا مئةٌ وأربعةٌ وثمانون',
    'يعدُّ الأصنافَ المتمايزةَ بنمطِ btn-* في كلِّ الشجرةِ الحية',
    'لا يقيس الأزرارَ المبنيةَ بأصنافٍ خارجَ هذا النمط (action-btn وأخواتها)',
    count($btnClasses) <= 4,
    count($btnClasses) . ' صنفًا عبرَ ' . $btnUses . ' استعمالًا');

/* ══ AC-U5/U6 · المناظرُ المحفوظةُ وحالاتُ الفراغ ═════════════════════════ */
$db = fix_db();
$viewsTable = (int) fix_one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_saved_views'");
$viewRows = $viewsTable ? (int) fix_one($db, "SELECT COUNT(*) FROM ems_saved_views") : 0;
u('AC-U5', 'كلُّ شاشةٍ فوقَ عشرينَ عمودًا لها منظرٌ افتراضيٌّ ومنتقٍ',
    'وجودُ جدولِ المناظرِ المحفوظةِ وعددُ صفوفِه المبذورة',
    'لا يقيس أن المنتقيَ مُصيَّرٌ في كلِّ شاشةٍ — ذاك يحتاج تصييرًا لكلِّ شاشةٍ طويلة',
    ($viewsTable > 0 && $viewRows > 0),
    'جدولُ المناظر: ' . ($viewsTable ? 'موجود' : 'غير موجود') . ' · صفوفٌ مبذورة: ' . $viewRows);

$emptyStateFiles = 0; $tableScreens = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (!preg_match('/<table\b/i', $src)) { continue; }
    $tableScreens++;
    if (preg_match('/ems-state-empty|ems-empty|لا بيانات|لا نتائج|EmsUI\.emptyState/u', $src)) { $emptyStateFiles++; }
}
u('AC-U6', 'كلُّ شاشةٍ بمجموعةٍ فارغةٍ تعرض سببًا وزرَّ إنشاء',
    'يعدُّ أسطحَ الجداولِ التي تحمل مكوّنَ حالةِ فراغٍ أو نصَّها',
    'لا يقيس تمييزَ «لا بيانات» عن «لا نتائج» ولا وجودَ زرِّ الإنشاء',
    ($tableScreens > 0 && $emptyStateFiles === $tableScreens),
    "{$emptyStateFiles}/{$tableScreens} سطحَ جدولٍ بحالةِ فراغ ("
        . ($tableScreens ? round($emptyStateFiles * 100 / $tableScreens) : 0) . '٪)');

/* ══ AC-U7 · ربطُ العناوينِ بالحقول ═══════════════════════════════════════ */
$inputs = 0; $labeled = 0; $aria = 0; $unlabeledFiles = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    $n = preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $src, $m, PREG_SET_ORDER);
    if (!$n) { continue; }
    $forIds = array();
    if (preg_match_all('/<label\b[^>]*\bfor=("|\')([^"\']+)\1/i', $src, $lm)) { $forIds = array_flip($lm[2]); }
    $fileUnlabeled = 0;
    foreach ($m as $tag) {
        $attrs = $tag[2];
        // الحقولُ المخفيةُ وأزرارُ الإرسالِ لا تحتاج عنوانًا
        if (preg_match('/\btype=("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { continue; }
        $inputs++;
        $hasAria = (bool) preg_match('/\baria-label(ledby)?=/i', $attrs);
        $id = null;
        if (preg_match('/\bid=("|\')([^"\']+)\1/i', $attrs, $im)) { $id = $im[2]; }
        $hasFor = ($id !== null && isset($forIds[$id]));
        if ($hasFor) { $labeled++; }
        elseif ($hasAria) { $aria++; }
        else { $fileUnlabeled++; }
    }
    if ($fileUnlabeled > 0) { $unlabeledFiles[$rel] = $fileUnlabeled; }
}
arsort($unlabeledFiles);
$covered = $labeled + $aria;
u('AC-U7', 'نسبةُ الحقولِ المرتبطةِ بعناوينها مئةٌ بالمئة',
    'يوسِّم كلَّ عنصرِ إدخالٍ في الشجرةِ الحيةِ ويطابقه بـ<label for> أو وسمٍ وصفيّ',
    'لا يقيس الحقولَ المولَّدةَ بجافاسكربت وقتَ التشغيل — تُقاس بالتصيير',
    ($inputs > 0 && $covered === $inputs),
    "{$covered}/{$inputs} مرتبطٌ (" . ($inputs ? round($covered * 100 / $inputs) : 0) . '٪) — منها '
        . "{$labeled} بعنوانٍ و{$aria} بوسمٍ وصفيّ · أكثرُ الملفاتِ نقصًا: "
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($unlabeledFiles), 0, 3), array_slice($unlabeledFiles, 0, 3))));

/* ══ AC-U9 · صفرُ اتجاهٍ من اليسارِ في الشريطِ العلوي ═════════════════════ */
$ltr = array();
foreach (array('includes/topbar.php', 'assets/css/ems-shell.css', 'assets/css/ems.main.all.style.css',
               'assets/css/ems-components.css', 'inheader.php') as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (preg_match_all('/direction\s*:\s*ltr|dir\s*=\s*("|\')ltr\1/i', $src, $m)) {
        $ltr[$rel] = count($m[0]);
    }
}
u('AC-U9', 'صفرُ إعلانِ اتجاهٍ من اليسارِ في الشريطِ العلوي',
    'يمسح ملفاتِ القشرةِ والشريطِ عن direction:ltr و dir="ltr"',
    'لا يقيس المقاطعَ اللاتينيةَ المستثناةَ عمدًا داخلَ عناصرَ بعينِها',
    empty($ltr),
    empty($ltr) ? 'صفر' : implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
        array_keys($ltr), $ltr)));

/* ══ AC-U11 · خمسُ نقاطِ كسر ══════════════════════════════════════════════ */
$bp = array();
foreach (ui_files($ROOT, array('css')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/@media[^{]*?\(\s*(?:min|max)-width\s*:\s*(\d+)\s*px/i', $src, $m)) {
        foreach ($m[1] as $px) { $bp[(int) $px] = ($bp[(int) $px] ?? 0) + 1; }
    }
}
ksort($bp);
u('AC-U11', 'صفرُ نقطةِ كسرٍ خارجَ الخمسِ المعلنة',
    'يعدُّ نقاطَ الكسرِ المتمايزةَ بالبكسل في كلِّ ملفاتِ CSS',
    'لا يقيس نقاطَ الكسرِ بوحداتٍ أخرى (em/rem) ولا استعلاماتِ الحاوية',
    count($bp) <= 5,
    count($bp) . ' نقطةً متمايزة: ' . implode('، ', array_slice(array_keys($bp), 0, 12))
        . (count($bp) > 12 ? ' …' : ''));

/* ══ AC-U12 · صفرُ نمطٍ موضعيٍّ في ملفِّ سطح ══════════════════════════════ */
$inline = array(); $inlineTotal = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    $n = preg_match_all('/\bstyle\s*=\s*("|\')[^"\']{3,}\1/i', $src);
    if ($n > 0) { $inline[$rel] = $n; $inlineTotal += $n; }
}
arsort($inline);
u('AC-U12', 'صفرُ ملفِّ سطحٍ يعرّف نمطًا موضعيًّا',
    'يعدُّ سماتِ style الموضعيةَ في كلِّ ملفِّ سطحٍ حيّ',
    'لا يقيس أنماطَ <style> المضمَّنةَ في رأسِ الشاشة',
    $inlineTotal === 0,
    "{$inlineTotal} نمطًا موضعيًّا في " . count($inline) . ' سطحًا · أكثرُها: '
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($inline), 0, 3), array_slice($inline, 0, 3))));

/* ── الحصيلة ───────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
foreach ($R as $r) { $r['ok'] ? $pass++ : $fail++; }
echo str_repeat('═', 70) . "\n";
printf("النتيجة: %d/%d — والباقي **ديْنٌ معلَنٌ بأرقامِه** لا اكتمالٌ مُدَّعى\n", $pass, $pass + $fail);
echo str_repeat('═', 70) . "\n";

if ($mdOut) {
    $md = "# بوابةُ FIX-02 — " . date('Y-m-d H:i') . "\n\n| المعيار | الحكم | الشاهد |\n|---|---|---|\n";
    foreach ($R as $r) { $md .= '| ' . $r['code'] . ' | ' . ($r['ok'] ? '✔' : '✘') . ' | ' . str_replace('|', '\\|', $r['evidence']) . " |\n"; }
    $md .= "\n**{$pass}/" . ($pass + $fail) . "**\n";
    @file_put_contents($mdOut, $md);
    echo "تقرير: {$mdOut}\n";
}
exit(0);
