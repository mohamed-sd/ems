<?php
/**
 * tools/fix_u0_baseline.php — أهذا المحتوى الخارجُ موروثٌ أم مُحدَث؟
 * يقارن الحالَ الراهنَ بالمرجعِ (git ref) للسطحِ نفسِه.
 * التشغيل: php tools/fix_u0_baseline.php [ref]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$ref = $argv[1] ?? 'main';

/** يُرجع اسمَ أولِ عنصرِ محتوًى بعد إغلاقِ `.main`، أو '' إن لم يوجد. */
function u0_escaped($src)
{
    if (!preg_match('/<div\s[^>]*class\s*=\s*("|\')[^"\']*\bmain\b/i', $src, $om, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $at = $om[0][1]; $depth = 0; $closeAt = null; $len = strlen($src);
    for ($i = $at; $i < $len; $i++) {
        if ($src[$i] !== '<') { continue; }
        if (substr($src, $i, 4) === '<div') { $depth++; continue; }
        if (substr($src, $i, 6) === '</div>') { $depth--; if ($depth === 0) { $closeAt = $i + 6; break; } }
    }
    if ($closeAt === null) { return ''; }
    $tail = substr($src, $closeAt);
    $tail = preg_replace('#<script\b[\s\S]*?</script>#i', '', $tail);
    $tail = preg_replace('#<!--[\s\S]*?-->#', '', $tail);
    $tail = preg_replace('#<\?php[\s\S]*?\?>#', '', $tail);
    return preg_match('/<(div|form|table|section|main|article)\b/i', $tail, $tm) ? strtolower($tm[1]) : '';
}

$inherited = array(); $newOnes = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $now = u0_escaped((string) @file_get_contents($ROOT . '/' . $rel));
    if ($now === '') { continue; }
    $old = array();
    @exec('git show ' . escapeshellarg($ref . ':' . $rel) . ' 2>nul', $old, $rc);
    $wasSrc = ($rc === 0) ? implode("\n", $old) : '';
    $was = $wasSrc !== '' ? u0_escaped($wasSrc) : '(ملفٌّ جديد)';
    if ($was !== '') { $inherited[$rel] = $was; } else { $newOnes[$rel] = $now; }
}
echo 'موروثٌ (كان في ' . $ref . '): ' . count($inherited) . "\n";
foreach ($inherited as $f => $t) { echo "  · {$f} (<{$t}>)\n"; }
echo "\nمُحدَثٌ في هذه الحزمة: " . count($newOnes) . "\n";
foreach ($newOnes as $f => $t) { echo "  ✘ {$f} (<{$t}>)\n"; }
