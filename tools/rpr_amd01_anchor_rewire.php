<?php
/**
 * tools/rpr_amd01_anchor_rewire.php — يستبدل ثوابتَ مرساةِ W00 بقراءةٍ منها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التحويلُ لا يُكتب بالمعنى بل بالمطابقةِ الحرفيّة**: كلُّ استبدالٍ هنا
 *   مقيَّدٌ بنصِّه كاملًا، **فإن لم يُطابَق حرفًا لم يقع** ويُعلَن. ⛔ ولا
 *   استبدالَ بتعبيرٍ فضفاضٍ يلتقط `str_repeat('─', 108)` أو `$e3 === 13`
 *   (‏عقودُ W03 الثلاثةَ عشرَ) — فكلاهما ليس مرساةً.
 * ◆ ويُدرَج `require_once` قارئِ المرساةِ بعد `set_charset` في كلِّ حاجب.
 *
 * التشغيل: php tools/rpr_amd01_anchor_rewire.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);

/* ملفٌّ ⇐ قائمةُ (نصٌّ قديمٌ حرفيّ ⇐ نصٌّ جديد) */
$OLD_DEC = '$W00[\'decisions\']';
$RULES = array(
 'repair01_w0_gate.php' => array(
   '($srcOk === 13 && $srcBad === 0 && $srcMissing === 0)'
     => '($srcOk === $W00[\'source_files\'] && $srcBad === 0 && $srcMissing === 0)',
   '($dTot === 108 && ($dApr + $dNeed) === 108 && !$regressed)'
     => '($dTot === $W00[\'decisions\'] && ($dApr + $dNeed) === $W00[\'decisions\'] && !$regressed)',
   "'108 · المجموع 108 · مرتدّ 0'"
     => '$W00[\'decisions\'] . \' · المجموع \' . $W00[\'decisions\'] . \' · مرتدّ 0\'',
   '($sN === 664), "أسطحُ الدراسة $sN", \'664\''
     => '($sN === $W00[\'surfaces\']), "أسطحُ الدراسة $sN", (string) $W00[\'surfaces\']',
 ),
 'repair01_w1_gate.php' => array(
   '$dec === 108 && $sf === 13 && $srAll === 664 && $fbAll === 265'
     => '$dec === $W00[\'decisions\'] && $sf === $W00[\'source_files\']'
      . ' && $srAll === $W00[\'surfaces\'] && $fbAll === $W00[\'ownership_forbidden\']',
 ),
 'repair01_w2_gate.php' => array(
   '$moved === $toMove && $noWave === 0 && $orig === 174'
     => '$moved === $toMove && $noWave === 0 && $orig === $W00[\'gaps_original\']',
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $f0 === 265'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $f0 === $W00[\'ownership_forbidden\']',
 ),
 'repair01_w3_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']',
   '$t0 === 174 && $e0 === 632'
     => '$t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
 'repair01_w4_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']',
   '$t0 === 174 && $e0 === 632'
     => '$t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
 'repair01_w5_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']',
   '$t0 === 174 && $e0 === 632'
     => '$t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
 'repair01_w6_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']',
   '$t0 === 174 && $e0 === 632'
     => '$t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
 'repair01_w7_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']',
   '$t0 === 174 && $e0 === 632'
     => '$t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
 'repair01_w8_gate.php' => array(
   '$dec === 108 && $srcF === 13 && $surf === 664 && $base === 651 && $unst === 0 && $gapsO === 174'
     => '$dec === $W00[\'decisions\'] && $srcF === $W00[\'source_files\']'
      . ' && $surf === $W00[\'surfaces\'] && $base === $W00[\'registry_base\']'
      . ' && $unst === 0 && $gapsO === $W00[\'gaps_original\']',
 ),
 'repair01_w9_gate.php' => array(
   '$decN === 108 && $srcN === 13 && $surfN === 664 && $baseN === 651'
     => '$decN === $W00[\'decisions\'] && $srcN === $W00[\'source_files\']'
      . ' && $surfN === $W00[\'surfaces\'] && $baseN === $W00[\'registry_base\']',
   '$gapOrig === 174 && $gapW02 === 160'
     => '$gapOrig === $W00[\'gaps_original\'] && $gapW02 === 160',
 ),
 'repair01_w10_gate.php' => array(
   '$decN === 108 && $srcN === 13 && $surfN === 664 && $baseN === 651'
     => '$decN === $W00[\'decisions\'] && $srcN === $W00[\'source_files\']'
      . ' && $surfN === $W00[\'surfaces\'] && $baseN === $W00[\'registry_base\']',
   '$gapOrig === 174 && $gapW02 === 160'
     => '$gapOrig === $W00[\'gaps_original\'] && $gapW02 === 160',
 ),
 'repair01_w11_gate.php' => array(
   '$d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651 && $gWild === 0 && $t0 === 174 && $e0 === 632'
     => '$d0 === $W00[\'decisions\'] && $s0 === $W00[\'source_files\']'
      . ' && $u0 === $W00[\'surfaces\'] && $g0 === $W00[\'registry_base\']'
      . ' && $gWild === 0 && $t0 === $W00[\'gaps_original\'] && $e0 === $W00[\'events_study\']',
 ),
);

$INJECT = "\n/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */\n"
        . "require_once __DIR__ . '/lib/repair01_w00_anchor.php';\n"
        . "\$W00 = w00_anchors(\$conn);\n";

$done = 0; $miss = 0; $files = 0;
foreach ($RULES as $file => $pairs) {
    $path = $ROOT . '/tools/' . $file;
    if (!is_file($path)) { echo "  ✘ لا ملفّ: $file\n"; $miss++; continue; }
    $txt = (string) file_get_contents($path);
    $orig = $txt;
    foreach ($pairs as $old => $new) {
        $n = substr_count($txt, $old);
        if ($n === 0) { printf("  ✘ %-26s لم يُطابَق: %s\n", $file, mb_substr($old, 0, 58)); $miss++; continue; }
        if ($n > 1)  { printf("  ⚠ %-26s تكرَّر %d مرّةً — يُستبدل كلُّه\n", $file, $n); }
        $txt = str_replace($old, $new, $txt);
        $done += $n;
    }
    if (strpos($txt, 'repair01_w00_anchor.php') === false) {
        $pos = strpos($txt, "\$conn->set_charset('utf8mb4');");
        if ($pos === false) { printf("  ✘ %-26s لا موضعَ للإدراج\n", $file); $miss++; }
        else {
            $end = $pos + strlen("\$conn->set_charset('utf8mb4');");
            $txt = substr($txt, 0, $end) . $INJECT . substr($txt, $end);
        }
    }
    if ($txt !== $orig) {
        $files++;
        if ($APPLY) { file_put_contents($path, $txt); }
    }
}
printf("\n%s — استبدالات %d · ملفّات %d · غيرُ مطابَقٍ %d\n",
    $APPLY ? '✔ طُبِّق' : '◆ تجربةٌ بلا كتابة (‏أضِفْ --apply)', $done, $files, $miss);
exit($miss ? 1 : 0);
