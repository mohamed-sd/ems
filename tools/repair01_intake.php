<?php
/** tools/repair01_intake.php — جردُ الاستيعاب: ملفٌّ/ورقةٌ/صفوف/أعمدة/رأس */
require_once __DIR__ . '/lib/xlsx_io.php';
$dir = __DIR__ . '/../docs/REPAIR01_20260823';
$files = glob($dir . '/*.xlsx');
sort($files);
$grand = 0; $sheets = 0;
foreach ($files as $f) {
    $base = basename($f);
    $wb = xlsx_read($f);
    echo "\n╔══ $base  (" . count($wb) . " ورقة)\n";
    foreach ($wb as $name => $rows) {
        $sheets++;
        $maxRow = $rows ? max(array_keys($rows)) + 1 : 0;
        $n = count($rows);
        $maxCol = 0;
        foreach ($rows as $r) { if ($r) { $c = max(array_keys($r)) + 1; if ($c > $maxCol) $maxCol = $c; } }
        // رأسُ الجدول = أوّلُ صفٍّ فيه خليّتانِ فأكثر غيرُ فارغتين
        $hdr = array(); $hdrRow = -1;
        foreach ($rows as $ri => $r) {
            $vals = array_filter(array_map('trim', $r), function ($v) { return $v !== ''; });
            if (count($vals) >= 2) { $hdr = $r; $hdrRow = $ri; break; }
        }
        $data = $hdrRow >= 0 ? max(0, $n - ($hdrRow + 1)) : 0;
        $grand += $data;
        ksort($hdr);
        $hs = implode(' | ', array_map(function ($v) { return mb_substr(trim($v), 0, 22); }, array_slice($hdr, 0, 9)));
        printf("  ├ %-38s صفوف:%-5d بيانات:%-5d أعمدة:%-3d\n", mb_substr($name, 0, 38), $n, $data, $maxCol);
        printf("  │   رأس(ص%d): %s%s\n", $hdrRow + 1, $hs, count($hdr) > 9 ? ' …' : '');
    }
}
echo "\n══════════════════════════════════════════\n";
echo "ملفّات: " . count($files) . "  ·  أوراق: $sheets  ·  صفوفُ بياناتٍ إجمالًا: $grand\n";
