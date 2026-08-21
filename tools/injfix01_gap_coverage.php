<?php
/**
 * tools/injfix01_gap_coverage.php — مقياسُ إغلاقِ الفجواتِ الثلاثِ والثلاثين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي عولج هنا (GAP-56)**: كانت هذه الأداةُ تَعُدُّ الفجوةَ مغطّاةً
 *   إن **ذَكَرَ رمزَها** فاحصٌ أخضر. فأعلنت ٣٣ من ٣٣ **بينما فاحصُ إحداها ينفي
 *   الإغلاقَ بنصِّه**. ⇒ **الذِّكرُ ليس سدًّا، والخُضرةُ ليست إغلاقًا.**
 *
 * ◆ **والعقدُ البديل** — `tools/lib/gap_verdict.php`: الفاحصُ يُصرِّح بحكمِه
 *   بعدَ أن تجري قياساتُه بسطرٍ آليِّ القراءة `#GAPV <رمز> <PASS|OPEN> <معيار>`.
 *   وهذه الأداةُ **تقرأ التصريحَ ولا تستنتجه**.
 *
 * ◆ **وثلاثةُ أحكامٍ لا رابعَ لها**:
 *     ✔ CLOSED      — كلُّ تصريحٍ للفجوةِ `PASS` وفاحصُه أخضر
 *     ✘ OPEN        — تصريحٌ واحدٌ `OPEN` يغلب كلَّ `PASS` (فالإغلاقُ يلزمه
 *                      سدُّ كلِّ شقوقِه لا أحدِها) · أو فاحصُ التصريحِ أحمر
 *     ⛔ UNVERIFIED  — لا تصريحَ أصلًا · **والذِّكرُ وحدَه يقع هنا لا في الأول**
 *
 * ◆ **والحزامُ السلبيّ**: `--negative=GAP-nn` يقلب تصريحَ فجوةٍ واحدةٍ إلى
 *   `OPEN` قسرًا. فإن بقي العَدُّ كما هو فالمقياسُ **لا يستطيع الرسوبَ** وهو
 *   عطبٌ في المقياسِ نفسِه — ويخرج بغيرِ صفر.
 *
 * التشغيل:  php tools/injfix01_gap_coverage.php [--json=<ملف>] [--negative=GAP-nn]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$opt = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $opt[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$negative = isset($opt['negative']) ? strtoupper($opt['negative']) : '';

/* سجلُّ الفجواتِ الثلاثِ والثلاثين كما اعتُمد — المقامُ مُعلَنٌ ولا يُغيَّر ضمنًا */
$GAPS = array(
    'GAP-01' => array('P0', 'مفتاحُ السلالم'),
    'GAP-02' => array('P0', 'مساراتُ الاعتمادِ القديمة'),
    'GAP-07' => array('P0', 'إنذارُ توقفِ المستهلك'),
    'GAP-10' => array('P0', 'الحقولُ الحساسةُ — تسعُ قنوات'),
    'GAP-04' => array('P1', 'تصعيدُ الخطواتِ المتأخرة'),
    'GAP-05' => array('P1', 'حكمُ نوعِ الحدث'),
    'GAP-08' => array('P1', 'التسليماتُ اليتيمة'),
    'GAP-09' => array('P1', 'تلوثُ حقولِ المفاتيح'),
    'GAP-11' => array('P1', 'نقطةُ قرارِ الصلاحيةِ الواحدة'),
    'GAP-13' => array('P1', 'حجبُ الواجهةِ البرمجيةِ وعزلُها'),
    'GAP-16' => array('P1', 'ابتلاعُ فشلِ الذمة'),
    'GAP-17' => array('P1', 'بيئةُ المجدول'),
    'GAP-18' => array('P1', 'دفترُ الهجراتِ والبصمة'),
    'GAP-20' => array('P1', 'مِلكيةُ الأسطح'),
    'GAP-23' => array('P1', 'الشاشاتُ الذهبيةُ العشر'),
    'GAP-25' => array('P1', 'أسعارُ الصرف'),
    'GAP-27' => array('P1', 'كتّابُ دفترِ القيد'),
    'GAP-28' => array('P1', 'مساراتُ الشريطِ العلويّ'),
    'GAP-29' => array('P1', 'الاستعلامُ الخام'),
    'GAP-30' => array('P1', 'البحثُ العامُّ المفتوح'),
    'GAP-03' => array('P2', 'التفويضُ والتصعيد'),
    'GAP-06' => array('P2', 'القراءةُ من الجذرِ لا الإسقاط'),
    'GAP-12' => array('P2', 'تصنيفُ الحقولِ والحساسية'),
    'GAP-14' => array('P2', 'سجلُّ رفضٍ بفعلٍ فارغ'),
    'GAP-15' => array('P2', 'البوابةُ المالية'),
    'GAP-19' => array('P2', 'التنقلُ وجسرُ الدورة'),
    'GAP-22' => array('P2', 'الانفتاحُ الافتراضيّ'),
    'GAP-26' => array('P2', 'بصمةُ الشجرةِ والإصدار'),
    'GAP-31' => array('P2', 'صادرُ السعةِ والتصعيد'),
    'GAP-32' => array('P2', 'الرسائلُ الميتةُ والأفعال'),
    'GAP-33' => array('P2', 'ترويسةُ الأصلِ والقوادح'),
    'GAP-21' => array('P3', 'توثيقُ الأسماءِ التقنية'),
    'GAP-24' => array('P3', 'جدولا الإقفال'),
);

/* ══ ① تشغيلُ الشواهدِ وقراءةُ تصاريحِها ══════════════════════════════════ */
$tests = glob($ROOT . '/tests/injfix0*.php');
sort($tests);
$php = PHP_BINARY;
$verdicts = array();   /* GAP => array of ['state','criterion','test'] */
$mentions = array();   /* GAP => array of test (ذِكرٌ بلا تصريح) */
$suite    = array();

foreach ($tests as $t) {
    $b = basename($t, '.php');
    $out = array(); $code = 0;
    @exec('"' . $php . '" ' . escapeshellarg($t) . ' 2>&1', $out, $code);
    $suite[$b] = ($code === 0);
    $txt = implode("\n", $out);
    $declared = array();
    if (preg_match_all('/^#GAPV\s+(GAP-\d{2})\s+(PASS|OPEN)\s+(.*)$/mu', $txt, $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $g = $one[1];
            $state = $one[2];
            /* الحزامُ السلبيّ: قلبٌ قسريٌّ لإثباتِ قدرةِ المقياسِ على الرسوب */
            if ($negative !== '' && $g === $negative) { $state = 'OPEN'; }
            /* فاحصٌ أحمرُ لا يُصرِّح بإغلاق — ولو طبع PASS */
            if ($code !== 0) { $state = 'OPEN'; }
            $verdicts[$g][] = array('state' => $state, 'criterion' => trim($one[3]), 'test' => $b);
            $declared[$g] = true;
        }
    }
    /* الذِّكرُ بلا تصريح — يُسجَّل ولا يُحسب إغلاقًا */
    $src = (string) @file_get_contents($t);
    if (preg_match_all('/GAP-\d{2}/', $src, $mm)) {
        foreach (array_unique($mm[0]) as $g) {
            if (!isset($declared[$g]) && isset($GAPS[$g])) { $mentions[$g][] = $b; }
        }
    }
}

/* ══ ② الحكمُ لكلِّ فجوة ═══════════════════════════════════════════════════ */
$closed = array(); $open = array(); $unver = array();
ksort($GAPS);
echo "══ مقياسُ إغلاقِ فجواتِ INJ-FIX-01 — بالتصريحِ لا بالذِّكر ══\n";
if ($negative !== '') {
    echo "⚠ **حزامٌ سلبيٌّ نشط**: {$negative} مقلوبةٌ قسرًا إلى OPEN — هذه جولةُ إثباتِ رسوبٍ لا قياس.\n";
}
echo str_repeat('─', 100) . "\n";
foreach ($GAPS as $g => $meta) {
    list($sev, $title) = $meta;
    $vs = isset($verdicts[$g]) ? $verdicts[$g] : array();
    if (!$vs) {
        $mark = '⛔'; $verdict = 'UNVERIFIED'; $unver[] = $g . ' (' . $sev . ')';
        $why = isset($mentions[$g])
             ? 'مذكورٌ في ' . implode(' · ', array_unique($mentions[$g])) . ' **بلا تصريحِ حكم**'
             : '— لا شاهدَ يصرّح به';
    } else {
        $anyOpen = false; $crit = '';
        foreach ($vs as $v) { if ($v['state'] === 'OPEN') { $anyOpen = true; $crit = $v['criterion']; } }
        if ($anyOpen) {
            $mark = '✘'; $verdict = 'OPEN'; $open[] = $g . ' (' . $sev . ')'; $why = $crit;
        } else {
            $mark = '✔'; $verdict = 'CLOSED'; $closed[] = $g; $why = $vs[0]['criterion'];
        }
    }
    printf("  %-8s %-3s %s %-28s %s\n", $g, $sev, $mark, mb_substr($title, 0, 28), mb_substr($why, 0, 62));
}
echo str_repeat('─', 100) . "\n";
$den = count($GAPS);
printf("◆ **مُغلَقةٌ بتصريحٍ مقيس: %d من %d** · مفتوحةٌ مُعلَنةٌ: %d · بلا تصريح: %d\n",
       count($closed), $den, count($open), count($unver));
if ($open)  { echo "◆ مفتوحةٌ بحقّ : " . implode(' · ', $open) . "\n"; }
if ($unver) { echo "◆ بلا تصريحٍ  : " . implode(' · ', $unver) . "\n"; }
$greens = 0; foreach ($suite as $v) { if ($v) { $greens++; } }
printf("◆ الشواهد: %d من %d خضراء · تصاريحُ مقروءة: %d\n",
       $greens, count($suite), array_sum(array_map('count', $verdicts)));
echo "◆ **ولا يُكتب «مكتمل» على هذا المقام** ما دام فيه مفتوحٌ أو بلا تصريح.\n";

if (isset($opt['json'])) {
    $j = array('denominator' => $den, 'closed' => $closed, 'open' => $open,
               'unverified' => $unver, 'suite_green' => $greens, 'suite_total' => count($suite),
               'negative_belt' => $negative);
    file_put_contents($opt['json'], json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "◆ كُتب: {$opt['json']}\n";
}

/* ══ ③ الحزامُ السلبيّ — **بوابةٌ حكمُها يستحيل تحققُه خضراءُ أبدًا** ══════════
 * فلا يُصدَّق مرورُ مقياسٍ لم يُجرَّب معطوبًا. والجولةُ هنا تُثبت أن قلبَ تصريحٍ
 * واحدٍ **يُخرج فجوتَه من المُغلَق فعلًا** — وإلا فالعطبُ في المقياسِ نفسِه.
 */
if ($negative !== '') {
    echo str_repeat('─', 100) . "\n";
    if (!isset($GAPS[$negative])) {
        echo "✘ **الحزامُ السلبيُّ بلا هدف**: {$negative} ليست في المقام — الجولةُ لا تُثبت شيئًا\n";
        exit(1);
    }
    if (in_array($negative, $closed, true)) {
        echo "✘ **المقياسُ لا يستطيع الرسوب**: {$negative} قُلبت إلى OPEN قسرًا وما زالت تُحسب مُغلَقة.\n";
        echo "   ⇐ هذا عطبٌ في المقياسِ لا في النظام — ولا يُقرأ أيُّ رقمِ إغلاقٍ حتى يُصلَح.\n";
        exit(1);
    }
    echo "✔ **الحزامُ أثبت الرسوب**: {$negative} خرجت من المُغلَقِ حالَ قلبِ تصريحِها.\n";
    echo "   ⇐ فالمقياسُ يقيس التصريحَ المقيسَ لا ذِكرَ الرمز — وهذا شرطُ قراءةِ أيِّ رقمِ إغلاق.\n";
    exit(0);
}
exit(0);
