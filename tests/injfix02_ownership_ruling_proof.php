<?php
/**
 * tests/injfix02_ownership_ruling_proof.php
 *   INJ-FIX-01 · GAP-20 موسَّعةً بـ INJ-FIX-02 · NF-14 — جولةُ حسمِ المِلكية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «رفعُ كلِّ ترجيحٍ مؤقتٍ إلى حكمٍ صريحٍ أو مِلكيةٍ مشتركةٍ معلَنة».
 *
 * ◆ **ولا يقيس هذا الفاحصُ نسبةً وحدَها** — بل ثلاثةَ أشياء:
 *   ① أن كلَّ حكمٍ مكتوبٍ **له شاهدٌ** لا رأي.
 *   ② أن **سطحًا واحدًا لا يحمل حكمَين** — فحكمان متناقضان أسوأُ من لا حكم.
 *   ③ **سقّاطة**: ما بقي بلا حسمٍ لا يزداد.
 *
 * ◆ **والنسبةُ تُعلَن بمقامِها ولا تُجمَع بغيرِها** — «لا نسبةَ نظام».
 *
 * التشغيل: php tests/injfix02_ownership_ruling_proof.php [--retighten]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$EX   = $ROOT . '/docs/baseline_20260821/extract/';
$BASE = $ROOT . '/docs/INJFIX01/evidence/NF-14_ownership_baseline.json';
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① كلُّ حكمٍ له شاهد ═══════════════════════════════════════════════════ */
echo "══ ① لكلِّ حكمٍ شاهدٌ مكتوب ══\n";
$noWitness = array();
$q = $conn->query("SELECT `route`,`ruling`,`witness`,`reason` FROM `gov_ownership_rulings`");
$total = 0; $byRuling = array();
while ($q && $x = $q->fetch_assoc()) {
    $total++;
    $byRuling[$x['ruling']] = ($byRuling[$x['ruling']] ?? 0) + 1;
    if (trim((string) $x['witness']) === '' || trim((string) $x['reason']) === '') {
        $noWitness[] = $x['route'];
    }
}
chk(count($noWitness) === 0, "صفرُ حكمٍ بلا شاهدٍ أو سبب — من {$total} حكمًا"
    . (count($noWitness) ? ' — ' . implode(' · ', array_slice($noWitness, 0, 5)) : ''));
arsort($byRuling);
foreach ($byRuling as $k => $v) { printf("     · %-20s %d\n", $k, $v); }

/* ══ ② سطحٌ واحدٌ حكمٌ واحد ════════════════════════════════════════════════ */
echo "\n══ ② لا سطحَ بحكمَين ══\n";
$dup = array();
$q = $conn->query("SELECT LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) b,
                          COUNT(DISTINCT `owner_after`) n, COUNT(*) c
                     FROM `gov_ownership_rulings`
                    GROUP BY b HAVING n > 1");
while ($q && $x = $q->fetch_assoc()) { $dup[] = "{$x['b']} ({$x['n']} مالكًا في {$x['c']} صفًّا)"; }
chk(count($dup) === 0, 'صفرُ سطحٍ بمالكَين مختلفَين — ' . count($dup)
    . (count($dup) ? ' — ' . implode(' · ', array_slice($dup, 0, 5)) : ''));

/* ══ ③ المقياسُ على السجلِّ الموحَّد ═══════════════════════════════════════ */
echo "\n══ ③ أساسُ المِلكيةِ في السجلِّ الموحَّد ══\n";
if (!is_file($EX . 'screen_registry.json')) {
    chk(false, 'سجلُّ الشاشاتِ غيرُ موجود — يُشغَّل tools/baseline_reconcile.php');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1);
}
$reg = json_decode((string) file_get_contents($EX . 'screen_registry.json'), true);
$b = array();
foreach ($reg as $r) { $k = $r['owner_basis'] ?? '?'; $b[$k] = ($b[$k] ?? 0) + 1; }
$den   = count($reg);
$final = ($b['RULING'] ?? 0) + ($b['CONSENSUS'] ?? 0);
$open  = ($b['MAJORITY'] ?? 0) + ($b['NONE'] ?? 0);
foreach (array('RULING', 'CONSENSUS', 'MAJORITY', 'NONE') as $k) {
    printf("     %-12s %d\n", $k, $b[$k] ?? 0);
}
printf("  ◆ **مِلكيةٌ نهائيةٌ: %d من %d = %.1f%%** (مقامُ السجلِّ الموحَّدِ وحدَه — ولا يُجمع بغيرِه)\n",
    $final, $den, $den ? 100 * $final / $den : 0);

/* ══ ④ سقّاطةُ ما بقي ═════════════════════════════════════════════════════ */
echo "\n══ ④ السقّاطة — ما بقي بلا حسمٍ لا يزداد ══\n";
if (in_array('--retighten', $argv, true) || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array(
        'gap' => 'GAP-20 · NF-14', 'denominator' => $den,
        'open' => $open, 'majority' => $b['MAJORITY'] ?? 0, 'none' => $b['NONE'] ?? 0,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى {$open}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blOpen = (int) ($bl['open'] ?? 0);
chk($open <= $blOpen, "بلا حسمٍ: {$open} ≤ {$blOpen}");
if ($open < $blOpen) {
    chk(false, "◆ انخفض إلى {$open} من {$blOpen} — **تُشدُّ السقّاطة**: "
             . 'php tests/injfix02_ownership_ruling_proof.php --retighten');
}
echo "  ◆ والباقي **مُعلَنٌ لا مُدَّعًى**: لا ظهورَ له في أيِّ مساحةٍ ولا والدَ مسجَّل،\n";
echo "     فلا يُحسم بترجيحٍ — «الترجيحُ عدٌّ لا حجة».\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-20', true, 'لكلِّ حكمِ ملكيةٍ شاهدٌ مكتوب ولا سطحَ بحكمَين — والباقي مُعلَنٌ لا مُدَّعًى', $bad);

exit($bad === 0 ? 0 : 1);
