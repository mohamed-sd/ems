<?php
/**
 * tools/fix_literal_csrf_in_strings.php — رمزُ الحمايةِ نصًّا لا نداءً
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ عيبٌ انكشف أثناء حملةِ «دورةِ العملِ الخاطئة» (INJ-0026)
 *
 * حاقنُ CSRF السابقُ أدرج `<?php echo csrf_field(); ?>` **داخلَ سلاسلَ نصيةٍ
 * مزدوجةِ الاقتباس**:
 *
 *     $cell .= "<form method='post'>
 *         <?php echo csrf_field(); ?>" . "<input ...>";
 *
 * وPHP لا تُنفّذ وسمًا داخلَ سلسلة — فالنتيجةُ أمران معًا:
 *   ① **النموذجُ بلا رمزِ حماية** فيُردُّ ٤٠٣ تحت `CSRF_ENFORCE_PATHS`.
 *   ② و**النصُّ يُطبع حرفيًّا** فيقرأ المستخدمُ شفرةً في وجهِ الشاشة.
 *
 * والعلاجُ: تحويلُ الوسمِ إلى **وصلِ نداء** — `" . csrf_field() . "`.
 *
 * ◆ ولا يُكتب ملفٌّ لا يجتاز `php -l`: التعديلُ يُبنى ويُفحص في ملفٍّ مؤقّتٍ
 *   ثم يُكتب. وهذه القاعدةُ أنقذت شاشتين من التلفِ في حملةٍ سابقةٍ حين
 *   عولجت PHP نصًّا.
 *
 * التشغيل: php tools/fix_literal_csrf_in_strings.php [--run]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$RUN  = in_array('--run', $argv, true);

/* المسحُ يقصي نسخَ العملِ والنسخَ الاحتياطية */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    if ($p === strtr(__FILE__, '\\', '/')) { continue; }   /* الأداةُ لا تُصلح نفسَها */
    if (strpos($p, '/.claude/') !== false || strpos($p, '/storage/backups/') !== false
        || strpos($p, '/vendor/') !== false || strpos($p, '/node_modules/') !== false) { continue; }
    $files[] = $p;
}

echo "══ رمزُ الحمايةِ نصًّا لا نداءً ══\n\n";
$hit = 0; $fixed = 0; $skipped = array();

foreach ($files as $abs) {
    $src = (string) @file_get_contents($abs);
    if (strpos($src, 'csrf_field(); ?>"') === false) { continue; }
    $rel = ltrim(str_replace($ROOT, '', $abs), '/');
    $n = 0;
    /* الوسمُ داخلَ سلسلةٍ ⇒ وصلُ نداء. والفراغُ قبله يُبتلع فلا سطرَ فارغ. */
    $out = preg_replace('~\s*<\?php\s+echo\s+csrf_field\(\);\s*\?>"~', '" . csrf_field()', $src, -1, $n);
    if ($n === 0 || $out === null) { $skipped[] = $rel . ' (لم يُطابق)'; continue; }
    $hit += $n;

    $tmp = sys_get_temp_dir() . '/csrflit_' . getmypid() . '.php';
    file_put_contents($tmp, $out);
    $lint = array(); $rc = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lint, $rc);
    @unlink($tmp);
    if ($rc !== 0) {
        $skipped[] = $rel . ' (رسب الفحصُ النحويّ)';
        echo "  ✘ لم يُكتب — {$rel}\n";
        continue;
    }
    if ($RUN) { file_put_contents($abs, $out); }
    $fixed++;
    echo '  ' . ($RUN ? '✔' : '·') . " {$rel} — {$n} موضعًا\n";
}

echo "\n  ملفات: {$fixed} · مواضع: {$hit}"
   . (count($skipped) ? ' · تُخُطّي: ' . implode(' · ', $skipped) : '') . "\n";
if (!$RUN) { echo "  (جافٌّ — أعد التشغيلَ بـ`--run`)\n"; }
