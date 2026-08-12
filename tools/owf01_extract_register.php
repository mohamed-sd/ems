<?php
/**
 * tools/owf01_extract_register.php — استخراجُ سجلِّ OWF-01 من وثيقةِ المالك
 * ═══════════════════════════════════════════════════════════════════════════
 * وثيقةُ «قرارات المالك النافذة» (OWF-01) تحمل **272 متطلبًا ذريًّا** لكلٍّ
 * معرّفٌ ثابتٌ (`OWF-NNNN-أ/ب`) ونصٌّ واختبارُ قبولٍ، موزَّعةً على عشرةِ أبوابٍ.
 *
 * **ولا يُعمَل على نصٍّ سردي**: العملُ على وثيقةٍ بالقراءةِ يُنتج «شعرتُ أنّي
 * غطّيتُ البابَ» — والقياسُ يحتاج سجلًّا صفًّا صفًّا. فيُستخرَج هنا إلى TSV
 * بذاتِ شكلِ `master_register.tsv` ليُقاس بأدواتِ القياسِ القائمةِ نفسِها.
 *
 * ◆ والاستخراجُ **يُحصي ويُعلن الفارق**: إن خرج عددٌ غيرُ 272 فذلك عيبُ استخراجٍ
 *   لا نقصُ وثيقةٍ — يُعلَن ولا يُسكَت عنه، لأنَّ سجلًّا ناقصًا يُخفي متطلباتٍ
 *   ويُقرأ اكتمالًا.
 *
 * التشغيل: php tools/owf01_extract_register.php <ملف-النص> [--tsv=مسار]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$src = null; $out = dirname(__DIR__) . '/docs/owf01/OWF01_register.tsv';
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--tsv=') === 0) { $out = substr($a, 6); continue; }
    if (strpos($a, '--') !== 0) { $src = $a; }
}
if ($src === null || !is_file($src)) {
    exit("الاستخدام: php tools/owf01_extract_register.php <ملف-النص> [--tsv=مسار]\n");
}
$lines = file($src, FILE_IGNORE_NEW_LINES);
$n = count($lines);
fwrite(STDOUT, "── المصدرُ: {$src} · {$n} سطرًا\n");

/* ── ① الأبوابُ وعددُ متطلباتِ كلٍّ كما تُعلنه الوثيقةُ نفسُها ────────────────
     العنوانُ `▐ ٤-N ◆ اسمُ الباب` ويليه سطرٌ «M متطلبًا ذريًّا». فيُقرأ العددُ
     **المُعلَن** ليُقابَل بالمستخرَجِ — فاختلافُهما عيبُ استخراجٍ يُعلَن. */
$doors = array();          // door => array('title','declared','from','to')
$cur = null;
for ($i = 0; $i < $n; $i++) {
    $l = trim($lines[$i]);
    if (preg_match('~^▐\s*(٤-[٠-٩]+)\s*(?:◆\s*)?(.+)$~u', $l, $m)) {
        if ($cur !== null) { $doors[$cur]['to'] = $i - 1; }
        $cur = $m[1];
        $declared = 0;
        for ($j = $i + 1; $j < min($i + 4, $n); $j++) {
            if (preg_match('~^\s*([0-9]+)\s*متطلبًا~u', $lines[$j], $d)) { $declared = (int) $d[1]; break; }
        }
        $doors[$cur] = array('title' => trim($m[2]), 'declared' => $declared,
                             'from' => $i, 'to' => $n - 1);
    }
}
if ($cur !== null) { $doors[$cur]['to'] = $n - 1; }
$doors = array_filter($doors, function ($d) { return $d['declared'] > 0; });
fwrite(STDOUT, '── أبوابٌ بمتطلباتٍ مُعلَنة: ' . count($doors) . "\n");

/* ── ② المتطلباتُ: معرّفٌ ثم نصٌّ ثم اختبارُ قبولٍ (كلٌّ في سطرِه بعد « | ») ─── */
$rows = array();
$dup = array();
foreach ($doors as $code => $d) {
    for ($i = $d['from']; $i <= $d['to']; $i++) {
        $l = trim($lines[$i]);
        /* ◆ **المعرّفُ يبدأ بـ« | » هو أيضًا** — وأوّلُ تعبيرٍ لي اشترط بدايةَ
             السطرِ فخرج صفرُ متطلبٍ من 272 مُعلَنًا. والفارقُ هو الذي فضح العيبَ،
             ولو لم أُقابل المُعلَنَ بالمستخرَجِ لكتبتُ سجلًّا فارغًا وقرأتُه سجلًّا. */
        /* ◆ **وسبعةُ معرِّفاتٍ بلا لاحقةٍ** (`OWF-0010` …) وهي صفوفُ «حجّة» —
             فاشتراطُ اللاحقةِ أسقطها. والفارقُ 265/272 هو الذي دلَّ عليها. */
        if (!preg_match('~^(?:\|\s*)?(OWF-\d{4}(?:-\S)?)\s*$~u', $l, $m)) { continue; }
        $id = $m[1];
        /* النصُّ والاختبارُ في السطرين التاليين، كلٌّ يبدأ بـ« | » */
        $body = ''; $test = '';
        $got = 0;
        for ($j = $i + 1; $j <= min($i + 6, $d['to']); $j++) {
            $x = $lines[$j];
            if (!preg_match('~^\s*\|\s*(.+)$~u', $x, $c)) {
                if (trim($x) === '') { continue; }
                break;
            }
            $v = trim($c[1]);
            if ($v === '') { continue; }
            if ($got === 0) { $body = $v; $got = 1; continue; }
            if ($got === 1) { $test = $v; $got = 2; break; }
        }
        if (isset($dup[$id])) {
            fwrite(STDOUT, "   ⚠ معرّفٌ مكرَّر: {$id}\n");
            continue;
        }
        $dup[$id] = true;
        $rows[] = array('id' => $id, 'door' => $code, 'door_title' => $d['title'],
                        'req' => $body, 'test' => $test);
    }
}

/* ── ③ الحصيلةُ والفارقُ — يُعلَن ولا يُسكَت عنه ────────────────────────────── */
$declaredTotal = 0;
$byDoor = array();
foreach ($rows as $r) { $byDoor[$r['door']] = isset($byDoor[$r['door']]) ? $byDoor[$r['door']] + 1 : 1; }
fwrite(STDOUT, "\n── التوزّعُ (مُعلَنٌ / مستخرَج):\n");
$mismatch = array();
foreach ($doors as $code => $d) {
    $got = isset($byDoor[$code]) ? $byDoor[$code] : 0;
    $declaredTotal += $d['declared'];
    $flag = $got === $d['declared'] ? '✔' : '✘';
    if ($got !== $d['declared']) { $mismatch[] = $code . ' (' . $d['declared'] . '⇒' . $got . ')'; }
    printf("   %-8s %-46s %3d / %3d  %s\n", $code, mb_substr($d['title'], 0, 44),
           $d['declared'], $got, $flag);
}
$total = count($rows);
fwrite(STDOUT, "   ══ المجموعُ: مُعلَنٌ {$declaredTotal} · مستخرَجٌ {$total}\n");

/* ── ④ الكتابة ──────────────────────────────────────────────────────────── */
if (!is_dir(dirname($out))) { @mkdir(dirname($out), 0777, true); }
$fh = fopen($out, 'w');
fwrite($fh, "\xEF\xBB\xBF");   // BOM ليقرأه Excel عربيًّا
fwrite($fh, implode("\t", array('id', 'door', 'door_title', 'requirement', 'acceptance_test',
                                'state', 'evidence')) . "\n");
foreach ($rows as $r) {
    fwrite($fh, implode("\t", array(
        $r['id'], $r['door'], str_replace("\t", ' ', $r['door_title']),
        str_replace("\t", ' ', $r['req']), str_replace("\t", ' ', $r['test']),
        'غير مقيس', '',
    )) . "\n");
}
fclose($fh);
fwrite(STDOUT, "── كُتب: {$out}\n");

if ($mismatch) {
    fwrite(STDOUT, "\n⚠ فارقٌ بين المُعلَنِ والمستخرَجِ في: " . implode(' · ', $mismatch) . "\n");
    fwrite(STDOUT, "  هذا عيبُ استخراجٍ لا نقصُ وثيقةٍ — سجلٌّ ناقصٌ يُخفي متطلباتٍ ويُقرأ اكتمالًا.\n");
    exit(1);
}
fwrite(STDOUT, "\n✅ المستخرَجُ = المُعلَنُ في كلِّ باب — السجلُّ صورةٌ صادقةٌ للوثيقة.\n");
exit(0);
