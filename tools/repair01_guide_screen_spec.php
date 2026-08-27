<?php
/**
 * tools/repair01_guide_screen_spec.php — مواصفةُ الشاشةِ كاملةً من الدليلِ المعماريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك**: «إن وُجدت شاشةٌ غيرُ موجودةٍ فأنشئها **بنفسِ بياناتِ الجدولِ
 * الموجودةِ في الدليلِ المعماريّ**، واجعل لها فورم وجدولَ قاعدةِ بياناتٍ بنفسِ
 * بياناتِ جدولِ العرض».
 *
 * ◆ **والدليلُ يعطي كلَّ ما يلزم لذلك** في كتلةِ كلِّ شاشة:
 *   `الغرض` · `Grain` · `مصدر الحقيقة · المالك · النوع` · `عدد الحقول (مقيس)`
 *   ثمَّ **ثلاثةُ صفوفٍ متوازية**: أسماءُ الحقولِ · أنواعُها
 *   (`SYSTEM`/`REFERENCE`/`INPUT`/`DERIVED`) · وسلوكُها.
 *   ⇒ **فالاسمُ والنوعُ والسلوكُ ثلاثتُها منصوصة** — لا اجتهادَ في أيٍّ منها.
 *
 * ⛔ **ولا يُخترَع حقلٌ ولا يُحذَف**: عددُ الحقولِ المنصوصُ يُقابَل بالمستخرَجِ،
 *   **واختلافُهما يُعلَن** — فمواصفةٌ ناقصةٌ تُنتج جدولًا ناقصًا يبدو تامًّا.
 *
 * التشغيل: php tools/repair01_guide_screen_spec.php <رقمُ الورقة> [--json]
 *          مثال: php tools/repair01_guide_screen_spec.php 02 --json
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/vendor/autoload.php';
$JSON = in_array('--json', $argv, true);
$want = null;
foreach (array_slice($argv, 1) as $a) { if (preg_match('~^\d{2}$~', $a)) { $want = $a; } }
if ($want === null) { exit("⛔ مرّر رقمَ الورقة: 01..17\n"); }

$XLSX = $ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx';
$rd = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($XLSX);
$rd->setReadDataOnly(true);
$sp = $rd->load($XLSX);
$sheet = null;
foreach ($sp->getSheetNames() as $i => $nm) { if (strpos($nm, $want . '_') === 0) { $sheet = $i; break; } }
if ($sheet === null) { exit("⛔ لا ورقةَ بالرقم $want\n"); }
$rows = $sp->getSheet($sheet)->toArray(null, true, false, false);

$clean = function ($s) {
    $s = preg_replace('~\s*[—\-–]\s*بحسب انطباق.*$~u', '', (string) $s);
    $s = preg_replace('~\s*\([A-Za-z][^)]*\)~u', '', $s);
    $s = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}]~u', '', $s);
    return preg_replace('~\s+~u', ' ', trim($s));
};
$row = function ($r) { $o = array();
    foreach ($r as $v) { $o[] = trim((string) $v); } return $o; };

/* المفرداتُ الستُّ مقروءةٌ من الدليلِ — لا أربعٌ مفترَضة */
$TYPES = array('SYSTEM', 'REFERENCE', 'INPUT', 'DERIVED', 'AUDIT', 'CONTROLLED');
$out = array(); $cur = null; $group = '';
foreach ($rows as $i => $r) {
    $a = trim((string) (isset($r[0]) ? $r[0] : ''));
    if ($a === '') { continue; }
    if (mb_strpos($a, '⬛') === 0 && preg_match('~مجموعة\s*Sidebar\s*[—\-–]\s*(.+)$~u', $a, $m)) {
        $group = $clean($m[1]); continue;
    }
    if (mb_strpos($a, '■ الشاشة') === 0) {
        if ($cur) { $out[] = $cur; }
        preg_match('~الشاشة\s*(\d+)\s*من\s*(\d+)\s*·\s*\[([^\]]*)\]\s*·\s*(.+)$~u', $a, $m);
        $cur = array('no' => isset($m[1]) ? (int) $m[1] : 0, 'group' => isset($m[3]) ? $clean($m[3]) : $group,
                     'title' => isset($m[4]) ? $clean($m[4]) : '', 'purpose' => '', 'grain' => '',
                     'source' => '', 'declared' => 0, 'fields' => array());
        continue;
    }
    if ($cur === null) { continue; }
    $c3 = trim((string) (isset($r[2]) ? $r[2] : ''));
    if (mb_strpos($a, 'الغرض') === 0)           { $cur['purpose'] = $clean($c3); continue; }
    if (mb_strpos($a, 'Grain') === 0)           { $cur['grain']   = $clean($c3); continue; }
    if (mb_strpos($a, 'مصدر الحقيقة') === 0)    { $cur['source']  = $clean($c3); continue; }
    if (mb_strpos($a, 'عدد الحقول') === 0) {
        if (preg_match('~(\d+)~', $c3, $m)) { $cur['declared'] = (int) $m[1]; } continue;
    }
    /* ── صفُّ الأنواعِ هو المرساة: ما فوقَه أسماءٌ وما تحتَه سلوك ─────────────
       ◆ **والمرساةُ نوعٌ لا اسمٌ**: أسماءُ الحقولِ نصٌّ حرٌّ لا يُعرَف بنمط،
         **وصفُّ الأنواعِ مفرداتُه أربعٌ معروفة** — فيُعرَف يقينًا ويُقرأ جاراه. */
    /* ⚠ **افترضتُ أربعَ مفرداتٍ والحيُّ ستّ**: أسقطتُ `AUDIT` (1327 خليّة)
         و`CONTROLLED` (672) — **فنقصت مواصفةُ 28 شاشةٍ من 37 وبدت تامّة**.
         ⇒ **والمفرداتُ تُقرأ من الدليلِ لا تُفترَض** — وهذا سابعُ ظهورٍ لهذا
         النمطِ في الحملة. */
    if (preg_match('~^(SYSTEM|REFERENCE|INPUT|DERIVED|AUDIT|CONTROLLED)~', $a)) {
        $names = $row(isset($rows[$i - 1]) ? $rows[$i - 1] : array());
        $types = $row($r);
        $behav = $row(isset($rows[$i + 1]) ? $rows[$i + 1] : array());
        foreach ($types as $ci => $t) {
            $t = preg_replace('~[^A-Z_]~', '', $t);
            if (!in_array($t, $TYPES, true)) { continue; }
            $nm = isset($names[$ci]) ? $clean($names[$ci]) : '';
            if ($nm === '') { continue; }
            $cur['fields'][] = array('name' => $nm, 'type' => $t,
                                     'behaviour' => $clean(isset($behav[$ci]) ? $behav[$ci] : ''));
        }
        continue;
    }
}
if ($cur) { $out[] = $cur; }

$bad = 0;
foreach ($out as $x) { if ($x['declared'] > 0 && count($x['fields']) !== $x['declared']) { $bad++; } }
printf("\n═══ مواصفةُ الورقة %s ═══\n", $want);
printf("  شاشاتٌ: %d · **مواصفةٌ لا تطابق عددَها المنصوص: %d**\n\n", count($out), $bad);
foreach ($out as $x) {
    printf("  %2d %-38s [%s] حقولٌ %d/%d%s\n", $x['no'], mb_substr($x['title'], 0, 36),
        mb_substr($x['group'], 0, 12), count($x['fields']), $x['declared'],
        ($x['declared'] > 0 && count($x['fields']) !== $x['declared']) ? '  ⚠' : '');
}
if ($JSON) {
    $p = $ROOT . '/docs/REPAIR01_20260823/GUIDE_SPEC_' . $want . '.json';
    file_put_contents($p, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n✔ كُتب " . basename($p) . "\n";
}
