<?php
/**
 * tools/_uxw_phptag_in_string_probe.php — وسمُ PHP مكتوبٌ داخلَ سلسلةِ PHP
 * ═══════════════════════════════════════════════════════════════════════════
 * الشكلُ المعطوب:
 *
 *     echo "<form …>" . "<?= csrf_field() ?>" . "…";
 *
 * وسمُ `<?php` أو `<?=` **داخلَ سلسلةٍ** لا يُنفَّذ — يخرج نصًّا حرفيًّا يبتلعه
 * المتصفحُ كتعليقٍ زائف. فنداءُ `csrf_field()` لا يقع أصلًا: النموذجُ بلا رمزِ
 * حمايةٍ ويُرَدُّ ٤٠٣ تحتَ الإنفاذ. (رصدَته دفعةُ المشتريات في ثلاثةِ نماذجِ قرار.)
 *
 * وهو شكلٌ **غيرُ** الذي يرصده `_uxw_csrf_probe.php` — ذاك سمةٌ لم تُغلَق، وهذا
 * سلسلةٌ تحوي وسمًا. ولذلك يلزم مسباران.
 *
 * الكشفُ بمُعجِمِ PHP: كلُّ سلسلةٍ (ثابتةٍ أو مُقحَمة) يُفحَص نصُّها الخام.
 * مسبارُ قراءةٍ فقط.
 */
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$hits = 0; $files = array(); $csrfHits = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/|tests/)#', $p)) continue;
    $src = @file_get_contents($f->getPathname());
    if ($src === false) continue;
    $rel = str_replace($ROOT . '/', '', $p);

    $tokens = @token_get_all($src);
    if (!is_array($tokens)) continue;
    foreach ($tokens as $t) {
        if (!is_array($t)) continue;
        if ($t[0] !== T_CONSTANT_ENCAPSED_STRING && $t[0] !== T_ENCAPSED_AND_WHITESPACE) continue;
        /* وسمُ فتحٍ داخلَ نصِّ السلسلة — والهروبُ `\<` لا يغيّر شيئًا فالمقصودُ النصُّ الخام */
        if (!preg_match('/<\?(php\b|=)/', $t[1])) continue;
        $isCsrf = (strpos($t[1], 'csrf_field') !== false);
        echo $rel, ':', $t[2], ($isCsrf ? '   ◀ csrf_field' : ''), "\n    ",
             trim(preg_replace('/\s+/', ' ', mb_substr($t[1], 0, 150))), "\n";
        $hits++; $files[$rel] = true;
        if ($isCsrf) { $csrfHits++; }
    }
}
echo "\n═══ وسمُ PHP داخلَ سلسلة: ", $hits, " في ", count($files), " ملفًّا · منها نداءُ csrf: ", $csrfHits, " ═══\n";
