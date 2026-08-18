<?php
/**
 * tools/_uxw_mojibake_probe.php — رصدُ نصٍّ ظاهرٍ للمستخدمِ تالفِ الترميز
 * الشكل: إيموجي UTF-8 قُرئت بـcp1252 ثم أُعيدت كتابتُها UTF-8 فصارت «ðŸ‘️».
 * البادئةُ الدالّة: المحرف U+00F0 (ð) متبوعًا بـU+0178/U+009F… — أو «Ã»/«â€».
 * مسبارُ قراءةٍ فقط.
 */
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$hits = 0; $files = array();
$ALLOW = array();
if (is_file(__DIR__ . '/_uxw_mojibake_allow.txt')) {
    foreach (file(__DIR__ . '/_uxw_mojibake_allow.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $al) {
        if ($al[0] === '#') continue;
        $c = explode("\t", $al);
        if (count($c) === 2) $ALLOW[$c[0] . '|' . $c[1]] = true;
    }
}
$legacy = 0;

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
                if (isset($ALLOW[$rel . '|' . sha1(trim($line))])) { $legacy++; break; }
                echo $rel, ':', ($i + 1), "\n    ", trim(mb_substr($line, 0, 130)), "\n";
                $hits++; $files[$rel] = true;
                break;
            }
        }
    }
}
echo "\n═══ تالفٌ جديد: ", $hits, " · موروثٌ معلَنٌ بانتظارِ المالك: ", $legacy, " في ", count($files), " ملفًّا ═══\n";
/* --enforce: يخرج 1 عند أيِّ إصابةٍ (خطافُ التسليم) · --fix: إصلاحٌ حتميٌّ بعكسِ
   التحويلِ (UTF-8 المقروءُ cp1252 يُعاد بترميزِ المحارفِ إلى بايتاتِ cp1252 فتعود
   البايتاتُ الأصليةُ UTF-8) — سطرًا سطرًا وللأسطرِ المصابةِ وحدَها، وما تعذَّر
   عكسُه حتميًّا يُترك ويُعلَن. */
if (in_array('--enforce', $argv, true) && $hits > 0) { exit(1); }
if (in_array('--fix', $argv, true) && $hits > 0) {
    $fixedLines = 0; $skipped = 0;
    foreach (array_keys($files) as $rel) {
        $p = "$ROOT/$rel";
        $lines = file($p);
        $changed = false;
        foreach ($lines as $i => $line) {
            $hit = false;
            foreach ($SIGS as $sig) { if (mb_strpos($line, $sig) !== false) { $hit = true; break; } }
            if (!$hit) continue;
            $rev = @iconv('UTF-8', 'WINDOWS-1252//IGNORE', $line);
            if ($rev === false || $rev === '' || !mb_check_encoding($rev, 'UTF-8')) { $skipped++; continue; }
            $still = false;
            foreach ($SIGS as $sig) { if (mb_strpos($rev, $sig) !== false) { $still = true; break; } }
            if ($still) { $skipped++; continue; }
            $lines[$i] = $rev;
            $changed = true; $fixedLines++;
        }
        if ($changed) { file_put_contents($p, implode('', $lines)); }
    }
    echo "✔ أُصلح: $fixedLines سطرًا · تُرك معلَنًا (غيرُ حتميّ): $skipped\n";
}
