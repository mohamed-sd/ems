<?php
/**
 * tools/_uxw_csrf_fix.php — إخراجُ csrf_field() من جوفِ سمةِ وسمِ النموذج
 * ═══════════════════════════════════════════════════════════════════════════
 * الشكلُ المعطوبُ الموحَّد (رصده tools/_uxw_csrf_probe.php):
 *
 *     <form ... class="allforms<?php echo $x ? ' allforms-visible' : ''; ?>
 *         <?= csrf_field() ?>">
 *
 * السطرُ الأولُ يترك سمةَ `class` مفتوحةً، فيبتلعها السطرُ الثاني: مخرَجُ
 * `csrf_field()` — وهو وسمُ input مخفيّ — يصير نصًّا داخلَ قيمةِ السمة. فالنموذجُ
 * يُرسَل بلا رمزِ حماية (٤٠٣ تحتَ الإنفاذ)، وقيمةُ الصنفِ تتلوّث فيسقط النموذجُ
 * على `.allforms { display:none }` فلا يظهر أصلًا.
 *
 * الإصلاحُ نقلُ `">` إلى نهايةِ السطرِ الأولِ وتركُ النداءِ حقلًا حقيقيًّا.
 * يُمسَح الشكلُ آليًّا (لا أرقامَ سطورٍ مثبَّتةٍ تنزاح)، ولا يُعدَّل سطرٌ لا
 * يطابق حرفًا. والملفاتُ التي تعملُ عليها دفعةٌ جاريةٌ تُستثنى بـ--skip=.
 *
 *   بلا وسائط : معاينة   ·   --apply : كتابة   ·   --skip=a.php,b.php : استثناء
 */
$ROOT  = str_replace(chr(92), '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
$SKIP  = array();
foreach ($argv as $a) {
    if (strpos($a, '--skip=') === 0) {
        foreach (explode(',', substr($a, 7)) as $s) { $s = trim($s); if ($s !== '') $SKIP[$s] = true; }
    }
}

$done = 0; $skipped = 0; $held = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$targets = array();
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/)#', $p)) continue;
    $targets[] = $p;
}
sort($targets);

foreach ($targets as $path) {
    $rel = str_replace($ROOT . '/', '', $path);
    $src = @file_get_contents($path);
    if ($src === false || strpos($src, 'csrf_field') === false) continue;
    if (isset($SKIP[$rel])) { $held++; echo "⊘ $rel — مُستثنًى (دفعةٌ جارية)\n"; continue; }

    $lines = file($path);
    $changed = 0;
    for ($i = 1, $n = count($lines); $i < $n; $i++) {
        $csrfLine = rtrim($lines[$i], "\r\n");
        /* سطرٌ لا يحمل إلا نداءَ csrf ثم إغلاقَ سمةٍ ووسمٍ: ...?>"> */
        if (!preg_match('/^(\s*)(<\?=?\s*(?:php\s+echo\s+)?csrf_field\(\)\s*;?\s*\?' . '>)"' . '>\s*$/', $csrfLine, $m)) continue;
        $openLine = rtrim($lines[$i - 1], "\r\n");
        /* والسطرُ السابقُ يترك اقتباسًا مفتوحًا — وإلا فالشكلُ غيرُ الذي نعرف */
        if (substr_count($openLine, '"') % 2 !== 1) {
            echo "✗ $rel:", ($i + 1), " — السمةُ ليست مفتوحةً كما يُتوقَّع، تُرك\n"; $skipped++; continue;
        }
        $lines[$i - 1] = $openLine . "\">\n";   // أغلِق السمةَ والوسمَ حيث يجب
        $lines[$i]     = $m[1] . $m[2] . "\n";  // واترك النداءَ حقلًا حقيقيًّا
        echo ($APPLY ? '✔' : '·'), " $rel:", ($i + 1), "\n";
        $changed++; $done++;
    }
    if ($changed && $APPLY) { file_put_contents($path, implode('', $lines)); }
}
echo "\n", ($APPLY ? 'أُصلح' : 'قابلٌ للإصلاح'), ": $done · غيرُ مطابق: $skipped · مُستثنًى: $held\n";
