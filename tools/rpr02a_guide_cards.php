<?php
/**
 * tools/rpr02a_guide_cards.php — استخراجُ بطاقاتِ الشاشةِ من الدليلِ المعماريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المرساةُ عنوانُ البطاقة: `■ الشاشة N من M · [مجموعة] · الاسم` — وهي
 *   المفردةُ الوحيدةُ التي لا تتكرّر ولا تُخمَّن.
 * ◆ ولكلِّ بطاقةٍ يُقرأ صفُّ «مصدر الحقيقة · المالك · النوع» فيُشتقُّ منه
 *   **نوعُ الشاشة** و**الإدارةُ المالكة** — والنوعُ هو ما يفرز سطحَ التوثيق
 *   (`Documentation Artifact`) عن سطحٍ يُبنى.
 * ◆ ⛔ **ولا يُقاس النوعُ بالعبارةِ في أيِّ خليّةٍ**: يُقرأ من مفردتِه
 *   `نوع الشاشة:` وحدَها — فوجودُ النصِّ في خليّةٍ أخرى أخضرُ كاذب.
 *
 * التشغيل: php tools/rpr02a_guide_cards.php <guide.xlsx> [--json=path]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_io.php';

$src = $argv[1] ?? ($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx');
$jsonOut = '';
foreach ($argv as $a) { if (strpos($a, '--json=') === 0) { $jsonOut = substr($a, 7); } }
if (!is_file($src)) { exit("لا ملفّ: $src\n"); }

$WB = xlsx_read($src);
$cards = array();
foreach ($WB as $sheet => $rows) {
    if (!preg_match('/^(\d{2}|WS|AS)_/u', $sheet)) { continue; }   /* أوراقُ الإداراتِ وحدَها */
    if (preg_match('/^(98|99|00)_/u', $sheet)) { continue; }        /* الفهرسُ والمصفوفةُ والمراجعة */
    ksort($rows);
    $cur = null;
    foreach ($rows as $ri => $r) {
        ksort($r);
        $c0 = isset($r[0]) ? trim((string) $r[0]) : '';
        $c2 = isset($r[2]) ? trim((string) $r[2]) : '';
        if (preg_match('/^■\s*الشاشة\s+(\d+)\s+من\s+(\d+)\s*·\s*\[(.*?)\]\s*·\s*(.+)$/u', $c0, $m)) {
            if ($cur) { $cards[] = $cur; }
            $cur = array(
                'sheet' => $sheet, 'row' => $ri + 1,
                'idx' => (int) $m[1], 'total' => (int) $m[2],
                'group' => trim($m[3]), 'name' => trim($m[4]),
                'type' => '', 'owner' => '', 'grain' => '',
            );
            continue;
        }
        if (!$cur) { continue; }
        if ($c0 === 'Grain — السطر الواحد' && $cur['grain'] === '') { $cur['grain'] = $c2; }
        if (strpos($c0, 'مصدر الحقيقة') === 0 && strpos($c0, 'المالك') !== false) {
            if (preg_match('/نوع الشاشة\s*:\s*(.+?)\s*$/u', $c2, $mm)) { $cur['type'] = trim($mm[1]); }
            if (preg_match('/الإدارة المالكة\s*:\s*(.+?)\s*·/u', $c2, $mm)) { $cur['owner'] = trim($mm[1]); }
        }
    }
    if ($cur) { $cards[] = $cur; $cur = null; }
}

/* ═══ التقرير ═══ */
$ts = date('Y-m-d H:i:s');
echo "# RPR-02-A · بطاقاتُ الشاشةِ في الدليلِ المعماريّ\n";
echo "> `php tools/rpr02a_guide_cards.php " . basename($src) . "`\n> مولَّدٌ حيًّا: $ts\n\n";
echo "إجماليُّ البطاقات: " . count($cards) . "\n\n";

$bySheet = array();
foreach ($cards as $c) { $bySheet[$c['sheet']][] = $c; }
echo "## بالورقة — والمعلَنُ «من M» شاهدٌ على الاكتمال\n";
$sumDecl = 0;
foreach ($bySheet as $sh => $cs) {
    $decl = $cs[0]['total'];
    $sumDecl += $decl;
    printf("  %-42s مقيس=%-4d معلَن=%-4d %s\n", $sh, count($cs), $decl, count($cs) === $decl ? '✔' : '⛔ فارق');
}
echo "  مجموعُ المعلَن: $sumDecl · مجموعُ المقيس: " . count($cards) . "\n\n";

echo "## بنوعِ الشاشة\n";
$byType = array();
foreach ($cards as $c) { $t = $c['type'] !== '' ? $c['type'] : '(بلا نوعٍ مقروء)'; $byType[$t] = ($byType[$t] ?? 0) + 1; }
arsort($byType);
foreach ($byType as $t => $n) { printf("  %-6d %s\n", $n, $t); }

echo "\n## أسطحُ التوثيق — `Documentation Artifact`\n";
$doc = array_values(array_filter($cards, fn($c) => stripos($c['type'], 'Documentation Artifact') !== false));
echo "  العدد: " . count($doc) . "\n";
$docBySheet = array();
foreach ($doc as $c) { $docBySheet[$c['sheet']][] = $c; }
foreach ($docBySheet as $sh => $cs) {
    printf("  %-42s %d\n", $sh, count($cs));
    foreach ($cs as $c) { echo "      · " . $c['name'] . "\n"; }
}

if ($jsonOut !== '') { file_put_contents($jsonOut, json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo "\nJSON ⇒ $jsonOut\n"; }
