<?php
/**
 * tools/_uxw_mojibake_probe.php — رصدُ نصٍّ ظاهرٍ للمستخدمِ تالفِ الترميز
 * الشكل: إيموجي UTF-8 قُرئت بـcp1252 ثم أُعيدت كتابتُها UTF-8 فصارت «ðŸ‘️».
 * البادئةُ الدالّة: المحرف U+00F0 (ð) متبوعًا بـU+0178/U+009F… — أو «Ã»/«â€».
 * مسبارُ قراءةٍ فقط.
 */
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$hits = 0; $files = array();
$SIGS = array("\u{00F0}\u{009F}", "\u{00F0}\u{0178}", "\u{00E2}\u{0080}\u{0099}", "\u{00C3}\u{00A9}", "\u{00E2}\u{0098}");
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.(php|css|js)$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/)#', $p)) continue;
    $s = @file_get_contents($f->getPathname());
    if ($s === false) continue;
    $rel = str_replace($ROOT . '/', '', $p);
    foreach (file($f->getPathname()) as $i => $line) {
        foreach ($SIGS as $sig) {
            if (mb_strpos($line, $sig) !== false) {
                echo $rel, ':', ($i + 1), "\n    ", trim(mb_substr($line, 0, 130)), "\n";
                $hits++; $files[$rel] = true;
                break;
            }
        }
    }
}
echo "\n═══ أسطرٌ فيها نصٌّ تالفُ الترميزِ ظاهرٌ للمستخدم: ", $hits, " في ", count($files), " ملفًّا ═══\n";
