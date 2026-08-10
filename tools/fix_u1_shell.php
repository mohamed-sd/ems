<?php
/**
 * tools/fix_u1_shell.php — توحيدُ قشرةِ الصفحة (AC-U1 · SH-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ستَّ عشرةَ شاشةً حيةً تبني رأسَها بنفسها: `<!DOCTYPE>` و`<head>` وقائمةَ
 *   أنماطٍ خاصةٍ بها، ثم تُضمِّن `insidebar` وحدَه. فهي **قشرةٌ سادسةَ عشرةَ**
 *   لا شاشةٌ على القشرة.
 *
 * ◆ وكلفةُ ذلك ليست جماليةً: كلُّ تحسينٍ في القشرةِ الموحَّدةِ لا يصلها. حين
 *   أُضيف كاسرُ الذاكرةِ لملفِّ الرموزِ في `inheader` بقيت هذه الستَّ عشرةَ
 *   تخدم لوحةً قديمة. وكلُّ أداةِ قياسٍ تحتاج حالةً خاصةً لكلٍّ منها — وقد
 *   كلّفني ذلك أربعَ جولاتٍ من الإيجابيّاتِ الكاذبةِ في فحصِ العناوين.
 *
 * ◆ **ولا يُنزع نمطٌ تنفرد به شاشة**: الروابطُ التي لا يحمّلها `inheader`
 *   تُنقل إلى ما بعده فتبقى وتفوز بترتيبها. والنزعُ الأعمى يُسقط تنسيقًا
 *   لا يعوّضه أحد.
 *
 * ◆ ويُحفظ `$page_title` من الوسمِ `<title>` قبل الاستبدال — فالعنوانُ محتوًى
 *   لا زخرفة.
 *
 * التشغيل: php tools/fix_u1_shell.php [--apply] [--only=path]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$only  = null;
foreach ($argv as $a) { if (strpos($a, '--only=') === 0) { $only = substr($a, 7); } }
require_once $ROOT . '/tools/fix_lib.php';

/** الأنماطُ التي يحمّلها `inheader` سلفًا — لا تُكرَّر. */
$INHEADER_CSS = array(
    'all.min.css', 'bootstrap.min.css', 'bootstrap.rtl.min.css', 'local-fonts.css',
    'design-tokens.css', 'ems.main.all.style.css', 'ems-tables.css', 'ems-forms.css',
    'ems-buttons.css', 'ems-nav-groups.css', 'ems-alerts.css', 'ems-shell.css',
    'ems-journey.css', 'ems-excel.css', 'jquery.dataTables.min.css', 'buttons.dataTables.min.css',
);

$done = 0; $skipped = array();

foreach (fix_surface_files($ROOT) as $rel) {
    if ($only !== null && $rel !== $only) { continue; }
    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '' || !preg_match('/<html\b/i', $src)) { continue; }

    /* ── حدودُ القشرةِ المحلية: من `<!DOCTYPE` إلى نهايةِ وسمِ `<body …>` ── */
    $dtAt = stripos($src, '<!DOCTYPE');
    if ($dtAt === false) { $skipped[$rel] = 'لا وسمَ DOCTYPE'; continue; }
    if (!preg_match('/<body\b[^>]*>/i', $src, $bm, PREG_OFFSET_CAPTURE, $dtAt)) {
        $skipped[$rel] = 'لا وسمَ body'; continue;
    }
    $bodyEnd = $bm[0][1] + strlen($bm[0][0]);
    $shell   = substr($src, $dtAt, $bodyEnd - $dtAt);

    // العنوان
    $title = '';
    if (preg_match('#<title>\s*(.*?)\s*</title>#us', $shell, $tm)) { $title = trim($tm[1]); }
    if ($title === '' || strpos($title, '<?') !== false) {
        $skipped[$rel] = 'عنوانٌ ديناميٌّ أو غائب'; continue;
    }

    // صنفُ الجسم — إن كان غيرَ المعتاد يُترك للمراجعة
    $bodyClass = '';
    if (preg_match('/class\s*=\s*("|\')([^"\']*)\1/i', $bm[0][0], $cm)) { $bodyClass = trim($cm[2]); }
    if ($bodyClass !== '' && $bodyClass !== 'ems-site') {
        $skipped[$rel] = 'صنفُ جسمٍ خاصٌّ: ' . $bodyClass; continue;
    }

    /* ── أنماطٌ تنفرد بها الشاشةُ — تُنقل ولا تُنزع ─────────────────────── */
    $extra = array();
    if (preg_match_all('/<link\b[^>]*rel\s*=\s*("|\')stylesheet\1[^>]*>/i', $shell, $lm)) {
        foreach ($lm[0] as $tag) {
            if (!preg_match('/href\s*=\s*("|\')([^"\']+)\1/i', $tag, $hm)) { continue; }
            $base = basename(strtok($hm[2], '?#'));
            if (in_array($base, $INHEADER_CSS, true)) { continue; }
            $extra[$base] = $tag;
        }
    }
    // كتلُ <style> داخلَ الرأسِ المحليّ
    $inlineStyles = '';
    if (preg_match_all('#<style\b[^>]*>.*?</style>#is', $shell, $sm)) {
        $inlineStyles = implode("\n", $sm[0]);
    }

    /* ── البناء ────────────────────────────────────────────────────────── */
    $q = str_replace("'", "\\'", $title);
    $rep  = "<?php\n";
    $rep .= "/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>\n";
    $rep .= "   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل\n";
    $rep .= "   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).\n";
    $rep .= "   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */\n";
    $rep .= "\$page_title = '{$q}';\n";
    $rep .= "include __DIR__ . '/../inheader.php';\n";
    $rep .= "?>\n";
    if ($extra) {
        $rep .= "<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->\n"
              . implode("\n", $extra) . "\n";
    }
    if ($inlineStyles !== '') { $rep .= $inlineStyles . "\n"; }

    $new = substr($src, 0, $dtAt) . $rep . substr($src, $bodyEnd);

    // فحصُ التركيبِ قبل الكتابة
    try { token_get_all($new, TOKEN_PARSE); }
    catch (\ParseError $e) { $skipped[$rel] = 'يكسر التركيب: ' . $e->getMessage(); continue; }

    if ($apply) {
        $bdir = $ROOT . '/storage/backups/u1_' . gmdate('Ymd') . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($abs, $bdir . '/' . basename($rel));
        file_put_contents($abs, $new);
    }
    $done++;
    printf("  %s %-52s عنوان «%s» · أنماطٌ منقولة: %d\n",
        $apply ? '✔' : '·', $rel, mb_substr($title, 0, 26), count($extra));
}

echo "\n" . ($apply ? 'حُوِّل' : 'سيُحوَّل') . ": {$done} ملفًّا · متروكٌ للمراجعة: " . count($skipped) . "\n";
foreach ($skipped as $f => $why) { echo "  ⚠ {$f} — {$why}\n"; }
