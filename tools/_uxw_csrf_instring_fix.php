<?php
/**
 * tools/_uxw_csrf_instring_fix.php — إخراجُ csrf_field() من جوفِ سلسلةِ PHP
 * ═══════════════════════════════════════════════════════════════════════════
 * الشكلُ المعطوب (أثرُ الحملةِ الآليةِ نفسِها التي أنتجت 701681a، لكنَّ الحقنَ
 * هنا وقع داخلَ **سلسلةِ PHP** لا داخلَ سمةِ وسم):
 *
 *     echo "<form method='post' class='x'>
 *         <?= csrf_field() ?><input type='hidden' …>";
 *
 * وسمُ `<?=` داخلَ سلسلةٍ لا يُنفَّذ — يخرج نصًّا حرفيًّا يبتلعه المتصفحُ
 * كتعليقٍ زائف. فالنموذجُ يُرسَل بلا رمزِ حمايةٍ ويُرَدُّ ٤٠٣ تحتَ الإنفاذ.
 *
 * ◆ **خطرٌ مقيسٌ تجنَّبه هذا الملف**: النداءُ نفسُه مكتوبٌ صحيحًا في **HTML عادي**
 *   في المِلفّاتِ نفسِها (خارجَ أيِّ سلسلة) — وهناك هو سليمٌ ولا يُمَسّ. فالكنسُ
 *   بمستوى الملفِّ يُفسِد الصحيحَ ليُصلح المعطوب. لذلك يُرقَّع **بإزاحةٍ بايتيةٍ
 *   داخلَ رمزِ السلسلةِ وحدَه**، وعلامةُ الاقتباسِ تُقرأ من المُعجِمِ لا تُخمَّن.
 *
 *   بلا وسائط : معاينة   ·   --apply : كتابة
 */
$ROOT  = str_replace(chr(92), '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
$TAG   = '/\R?[ \t]*<\?(?:=|php\s+echo)\s*csrf_field\(\)\s*;?\s*\?' . '>/';
$done = 0; $files = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$targets = array();
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/|tests/)#', $p)) continue;
    $targets[] = $p;
}
sort($targets);

foreach ($targets as $path) {
    $rel = str_replace($ROOT . '/', '', $path);
    $src = @file_get_contents($path);
    if ($src === false || strpos($src, 'csrf_field') === false) continue;
    $tokens = @token_get_all($src);
    if (!is_array($tokens)) continue;

    /* مرَّةٌ أولى: اجمعْ إزاحاتِ رموزِ السلاسلِ التي تحوي الوسمَ وعلامةَ اقتباسِها */
    $edits = array(); $off = 0; $inDouble = false;
    foreach ($tokens as $t) {
        $text = is_array($t) ? $t[1] : $t;
        $len  = strlen($text);
        if ($text === '"') { $inDouble = !$inDouble; $off += $len; continue; }
        if (is_array($t)) {
            $isConst = ($t[0] === T_CONSTANT_ENCAPSED_STRING && $text !== '' && ($text[0] === '"' || $text[0] === "'"));
            $isEncap = ($t[0] === T_ENCAPSED_AND_WHITESPACE && $inDouble);
            if (($isConst || $isEncap) && preg_match($TAG, $text)) {
                $edits[] = array($off, $len, $text, $isConst ? $text[0] : '"');
            }
        }
        $off += $len;
    }
    if (!$edits) continue;

    /* مرَّةٌ ثانيةٌ من الآخرِ للأول كي لا تنزاح الإزاحات */
    $out = $src; $n = 0;
    for ($i = count($edits) - 1; $i >= 0; $i--) {
        list($start, $len, $text, $q) = $edits[$i];
        $new = preg_replace($TAG, $q . ' . csrf_field() . ' . $q, $text, -1, $c);
        if ($new === null || $c === 0) continue;
        $out = substr($out, 0, $start) . $new . substr($out, $start + $len);
        $n += $c;
    }
    if ($n === 0) continue;

    echo ($APPLY ? '✔' : '·'), " $rel — $n موضعًا داخلَ سلسلة\n";
    if ($APPLY) { file_put_contents($path, $out); }
    $done += $n; $files++;
}
echo "\n", ($APPLY ? 'أُصلح' : 'قابلٌ للإصلاح'), ": $done موضعًا في $files ملفًّا\n";
