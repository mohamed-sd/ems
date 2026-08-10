<?php
/**
 * tools/fix_u12_scan.php — مسحُ الأنماطِ الموضعيةِ قبل أيِّ تحويل (AC-U12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يعدِّل شيئًا. يقيس **شكلَ** الدَّين: كم إعلانًا متمايزًا، وما نسبةُ ما
 *   تغطيه القمة، وكم منها على عناصرَ تتنازعها قواعدُ أخرى.
 * ◆ والخطرُ المعروفُ في هذا التحويل: النمطُ الموضعيُّ يتفوّق على **كلِّ** قاعدة،
 *   ونقلُه إلى صنفٍ يُسقط أخصّيتَه — فقد تظهر قاعدةٌ كانت مكبوتة. ولذلك
 *   يُقاس قبل أن يُنقَل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';

/** يطبّع إعلانَ النمطِ: يرتّب الخصائصَ ويوحّد المسافات. */
function u12_norm($v)
{
    $out = array();
    foreach (explode(';', (string) $v) as $d) {
        $d = trim($d);
        if ($d === '') { continue; }
        $p = strpos($d, ':');
        if ($p === false) { continue; }
        $k = strtolower(trim(substr($d, 0, $p)));
        $val = trim(preg_replace('/\s+/', ' ', substr($d, $p + 1)));
        if ($k === '' || $val === '') { continue; }
        $out[$k] = $val;
    }
    ksort($out);
    $s = '';
    foreach ($out as $k => $v2) { $s .= $k . ':' . $v2 . ';'; }
    return $s;
}

$sets = array();
$byFile = array();
$dynamic = 0;
$total = 0;

foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (!preg_match_all('/\bstyle\s*=\s*("|\')(.*?)\1/is', $src, $m, PREG_SET_ORDER)) { continue; }
    foreach ($m as $hit) {
        $raw = $hit[2];
        $total++;
        // نمطٌ فيه كتلةُ PHP قيمتُه تُحسب وقتَ التشغيل — لا يُحوَّل إلى صنفٍ ثابت
        if (strpos($raw, '<?') !== false) { $dynamic++; continue; }
        $n = u12_norm($raw);
        if ($n === '') { continue; }
        $sets[$n] = ($sets[$n] ?? 0) + 1;
        $byFile[$rel] = ($byFile[$rel] ?? 0) + 1;
    }
}
arsort($sets);
arsort($byFile);

$fixed = array_sum($sets);
echo "إجمالي سماتِ style: {$total}\n";
echo "منها ديناميٌّ (قيمتُه من PHP — لا يُحوَّل): {$dynamic}\n";
echo "ثابتٌ قابلٌ للتحويل: {$fixed} · متمايزٌ: " . count($sets) . "\n\n";

$cum = 0; $i = 0;
foreach ($sets as $k => $n) {
    $cum += $n; $i++;
    if (in_array($i, array(10, 25, 50, 100, 200, 400), true)) {
        printf("  أعلى %-4d إعلانًا تغطي %d%%\n", $i, round($cum * 100 / max(1, $fixed)));
    }
}
echo "\n=== أكثرُ الإعلاناتِ تكرارًا ===\n";
$i = 0;
foreach ($sets as $k => $n) {
    if ($i++ >= 12) { break; }
    printf("  %-52s ×%d\n", mb_substr($k, 0, 50), $n);
}
echo "\n=== أثقلُ الملفات ===\n";
$i = 0;
foreach ($byFile as $f => $n) { if ($i++ >= 6) { break; } printf("  %-48s %d\n", $f, $n); }
