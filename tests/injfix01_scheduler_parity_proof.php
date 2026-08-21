<?php
/**
 * tests/injfix01_scheduler_parity_proof.php
 *   توحيدُ بيئةِ المجدول — INJ-FIX-01 · الموجة ب · الحاجز ③ · GAP-17
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول**: «إصدارُ لغةٍ واحدٌ لكلِّ المهامِّ المجدولة · وصفرُ مهمةٍ ناقصة».
 *
 * ◆ **والجردُ من مجدولِ النظامِ لا من جدولِ القاعدة**: `ems_job_schedule` يصف
 *   **ما ينبغي أن يعمل**، ومجدولُ ويندوز يصف **ما يعمل فعلًا**. والفرقُ بينهما
 *   هو بالضبط ما تخفيه بطاقةُ الفجوة: مهمةٌ في القاعدةِ بلا مهمةٍ في النظامِ
 *   لا تعمل ولو بدا جدولُها سليمًا.
 *
 * ◆ **وثلاثةُ أسئلةٍ تُقاس**:
 *   ① أكلُّ المهامِّ على إصدارٍ واحد؟ (وإلا اختلف تحويلُ الأنواعِ والتحذيرات
 *      بين مهمّتَين تكتبان في الجدولِ نفسِه — فيُقرأ الرقمُ صحيحًا وهو خطأ)
 *   ② أكلُّ عاملٍ على القرصِ مجدولٌ فعلًا؟
 *   ③ أثمَّ عاملٌ متعثِّرٌ فوقَ مهلتِه؟ — وخبرٌ لا حكم.
 *
 * التشغيل: php tests/injfix01_scheduler_parity_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';

$BASELINE_PHP = '8.2.30';   // إصدارُ الحزمةِ المُعلَنُ في DO_NOT_DELETE_8.2.30.txt
$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ توحيدُ بيئةِ المجدول — GAP-17 ════\n";

/* ── جردُ مجدولِ النظام ─────────────────────────────────────────────────── */
/* ◆ **يُكتب السكربتُ ملفًّا ثم يُشغَّل** — لا يُمرَّر بـ`-Command` نصًّا:
     اقتباسُ PowerShell داخلَ اقتباسِ الصدفةِ داخلَ `escapeshellarg` على ويندوز
     يبتلع المحدِّداتِ صامتًا، فيعود المُخرَجُ فارغًا **ويُقرأ الفراغُ
     «صفرَ مهامٍّ» — وهو أسوأُ من خطأ**: مقامٌ صفرٌ يُنتج بوابةً لا ترسب. */
$psFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'injfix01_sched_' . getmypid() . '.ps1';
file_put_contents($psFile,
    "Get-ScheduledTask | Where-Object { \$_.TaskName -match '^EMS' } | ForEach-Object {\n"
  . "  \$a = \$_.Actions[0]\n"
  . "  Write-Output (\$_.TaskName + '::' + \$a.Execute + ' ' + \$a.Arguments)\n"
  . "}\n");
$out = array();
exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($psFile) . ' 2>&1', $out);
@unlink($psFile);

$tasks = array();
foreach ($out as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '::') === false) { continue; }
    list($name, $cmd) = explode('::', $line, 2);
    $ver = preg_match('/php([0-9]+\.[0-9]+\.[0-9]+)/', $cmd, $m) ? $m[1] : '—';
    $script = preg_match('~([A-Za-z0-9_\\\\/]*\\\\)?(cron_[A-Za-z0-9_]+\.php)~', $cmd, $m2) ? $m2[2] : '—';
    $tasks[trim($name)] = array('ver' => $ver, 'script' => $script, 'cmd' => $cmd);
}
ok(count($tasks) > 0, 'قُرئ مجدولُ النظام', $pass, $fail, count($tasks) . ' مهمةً بادئتُها EMS');
if (!$tasks) { echo "  ◆ تعذّرت القراءةُ — الفاحصُ يلزمه ويندوز وPowerShell.\n"; exit(1); }

/* ── ① إصدارٌ واحدٌ لكلِّ المهام ────────────────────────────────────────── */
echo "\n── ① إصدارُ اللغة ──\n";
$vers = array();
foreach ($tasks as $n => $t) {
    printf("  %-26s %-8s %s\n", $n, $t['ver'], $t['script']);
    if ($t['ver'] !== '—') { $vers[$t['ver']][] = $n; }
}
ksort($vers);
ok(count($vers) === 1, 'إصدارٌ واحدٌ لكلِّ المهامِّ المجدولة', $pass, $fail,
   count($vers) . ' إصدارًا: ' . implode(' · ', array_keys($vers)));
ok(isset($vers[$BASELINE_PHP]) && count($vers) === 1,
   "والإصدارُ هو أساسُ الحزمة {$BASELINE_PHP}", $pass, $fail,
   isset($vers[$BASELINE_PHP]) ? count($vers[$BASELINE_PHP]) . ' مهمةً عليه' : 'لا مهمةَ عليه');
foreach ($vers as $v => $names) {
    if ($v !== $BASELINE_PHP) { echo "        ↳ خارجَ الأساس ({$v}): " . implode(' · ', $names) . "\n"; }
}

/* ── ② صفرُ عاملٍ على القرصِ بلا جدولة ─────────────────────────────────── */
echo "\n── ② العاملون على القرصِ مقابلَ المجدولين ──\n";
$onDisk = array_map('basename', glob($ROOT . '/cron_*.php'));
sort($onDisk);
$scheduled = array();
foreach ($tasks as $t) { if ($t['script'] !== '—') { $scheduled[$t['script']] = true; } }
$unscheduled = array_values(array_diff($onDisk, array_keys($scheduled)));
echo "  على القرص: " . implode(' · ', $onDisk) . "\n";
ok(count($unscheduled) === 0, 'صفرُ عاملٍ على القرصِ بلا مهمةٍ مجدولة', $pass, $fail,
   count($unscheduled) ? 'غيرُ مجدول: ' . implode(' · ', $unscheduled) : '—');

/* ── ③ خبرٌ خارجَ الحكم: العاملون المتعثِّرون ─────────────────────────────── */
echo "\n── ③ خبرٌ خارجَ الحكم — تعثُّرُ الجدولات ──\n";
$q = $conn->query("SELECT job_type, cron_expr, alert_after_seconds, last_success_at,
                          TIMESTAMPDIFF(SECOND, last_success_at, NOW()) late
                     FROM ems_job_schedule WHERE is_active = 1 ORDER BY job_type");
$late = 0;
while ($q && $x = $q->fetch_assoc()) {
    $isLate = $x['late'] !== null && (int) $x['late'] > (int) $x['alert_after_seconds'];
    if ($isLate) {
        $late++;
        printf("  ◆ %-22s %-14s متأخرةٌ %s ساعة (المهلة %s ث)\n",
               $x['job_type'], $x['cron_expr'], round($x['late'] / 3600, 1), $x['alert_after_seconds']);
    }
}
echo "  المتعثِّرون: {$late}\n";
if ($late > 0) {
    echo "    ⇐ **ولا يُقرأ كلُّ تأخُّرٍ عطبًا**: `depreciation_run` شهريةٌ (`0 2 1 * *`)\n";
    echo "      ومهلةُ إنذارِها 65535 ثانيةً (~18 ساعة) — فهي **تُنذر أبدًا** بين\n";
    echo "      شهرٍ وشهر. تلك **مهلةٌ مضبوطةٌ خطأً** لا مهمةٌ متوقفة.\n";
    echo "      وأمّا اليوميّتان فتأخُّرُهما حقيقيّ: لم تعملا في موعدِهما.\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
if ($fail > 0) {
    echo "◆ العلاج: powershell -ExecutionPolicy Bypass -File tools\\injfix01_scheduler_unify.ps1\n";
    echo "  (وبـ-WhatIf للمعاينة · وبـ-Revert للرجوع من التعريفاتِ المصدَّرة)\n";
}
exit($fail === 0 ? 0 : 1);
