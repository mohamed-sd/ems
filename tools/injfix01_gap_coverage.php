<?php
/**
 * tools/injfix01_gap_coverage.php — تغطيةُ الفجواتِ الثلاثِ والثلاثين بالشواهد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يجيب سؤالًا واحدًا**: أيُّ فجوةٍ من سجلِّ INJ-FIX-01 لها **شاهدٌ يسمّيها**
 *   ويمرُّ اليوم؟ فالفجوةُ المغلقةُ بلا شاهدٍ تُعاد فتحًا في أولِ مراجعة.
 *
 * ◆ **ولا يُعَدُّ ذكرُ الرمزِ في هجرةٍ تغطيةً**: الهجرةُ تُنفَّذ مرةً، والشاهدُ
 *   يُعاد تشغيلُه. فالمقياسُ **ملفُّ فحصٍ يسمّي الرمزَ وخروجُه صفر**.
 *
 * التشغيل: php tools/injfix01_gap_coverage.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* سجلُّ الفجواتِ الثلاثِ والثلاثين كما اعتُمد */
$GAPS = array(
    'GAP-01' => 'P0', 'GAP-02' => 'P0', 'GAP-07' => 'P0', 'GAP-10' => 'P0',
    'GAP-04' => 'P1', 'GAP-05' => 'P1', 'GAP-08' => 'P1', 'GAP-09' => 'P1',
    'GAP-11' => 'P1', 'GAP-13' => 'P1', 'GAP-16' => 'P1', 'GAP-17' => 'P1',
    'GAP-18' => 'P1', 'GAP-20' => 'P1', 'GAP-23' => 'P1', 'GAP-25' => 'P1',
    'GAP-27' => 'P1', 'GAP-28' => 'P1', 'GAP-29' => 'P1', 'GAP-30' => 'P1',
    'GAP-03' => 'P2', 'GAP-06' => 'P2', 'GAP-12' => 'P2', 'GAP-14' => 'P2',
    'GAP-15' => 'P2', 'GAP-19' => 'P2', 'GAP-22' => 'P2', 'GAP-26' => 'P2',
    'GAP-31' => 'P2', 'GAP-32' => 'P2', 'GAP-33' => 'P2',
    'GAP-21' => 'P3', 'GAP-24' => 'P3',
);

/* الشواهدُ ومَن تسمّيه */
$tests = glob($ROOT . '/tests/injfix0*.php');
$named = array();
foreach ($tests as $t) {
    $src = (string) @file_get_contents($t);
    if (preg_match_all('/GAP-\d{2}/', $src, $m)) {
        foreach (array_unique($m[0]) as $g) { $named[$g][] = basename($t); }
    }
}

/* أيُّ الشواهدِ يمرُّ الآن؟ — يُشغَّل كلٌّ مرةً واحدة */
$php = PHP_BINARY;
$pass = array();
foreach ($tests as $t) {
    $out = array(); $code = 0;
    @exec('"' . $php . '" ' . escapeshellarg($t) . ' 2>&1', $out, $code);
    $pass[basename($t)] = ($code === 0);
}

echo "══ تغطيةُ فجواتِ INJ-FIX-01 بالشواهد ══\n";
echo str_repeat('─', 92) . "\n";
$cov = 0; $uncov = array();
ksort($GAPS);
foreach ($GAPS as $g => $sev) {
    $ts = isset($named[$g]) ? array_unique($named[$g]) : array();
    $green = array();
    foreach ($ts as $one) { if (!empty($pass[$one])) { $green[] = $one; } }
    $mark = count($green) ? '✔' : (count($ts) ? '◐' : '·');
    if (count($green)) { $cov++; } else { $uncov[] = $g . ' (' . $sev . ')'; }
    printf("  %-8s %-4s %s  %s\n", $g, $sev, $mark,
        count($green) ? implode(' · ', array_map(function ($x) {
            return str_replace(array('injfix01_', 'injfix02_', '.php'), '', $x);
        }, array_slice($green, 0, 3))) : (count($ts) ? 'شاهدٌ يسمّيه لكنه أحمر' : '— بلا شاهدٍ يسمّيه'));
}
echo str_repeat('─', 92) . "\n";
printf("◆ **مغطّاةٌ بشاهدٍ أخضرَ يسمّيها: %d من %d**\n", $cov, count($GAPS));
if ($uncov) {
    echo "◆ بلا شاهدٍ يسمّيها: " . implode(' · ', $uncov) . "\n";
}
$g = 0; foreach ($pass as $v) { if ($v) { $g++; } }
printf("◆ الشواهد: %d من %d خضراء\n", $g, count($pass));
