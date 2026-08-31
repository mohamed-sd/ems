<?php
/**
 * tools/rpr03_scheduler_entrypoints.php — `RPR-03` #١٩ · مداخلُ المهامِّ المجدولة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي كشفه القياسُ ولم يكن لأحدٍ أن يراه**: أربعةُ مستهلكين
 *   **مُفعَّلين** ومؤشِّراتُهم لم تتحرّك منذ **١٢ ← ١٦ أغسطس** — و`fx` خلفه
 *   **٢١٬٠٠٢** حدثًا. و#١٩ يقيس **دفترَ المهامِّ في القاعدة** (`ems_job_schedule`)
 *   ⇒ **فيقرأ نجاحًا لأنَّ الصفَّ يُحدَّث**، ⛔ **وهو أعمى عمّن يُشغِّله فعلًا**.
 *
 * ◆ **والسببُ مقيسٌ**: مهمّةُ الويندوز `EMS_cron_events` تنفّذ `cron_events.php`
 *   — **وهو مدخلٌ متقاعدٌ** يطبع «هذا الأمرُ أُلغي تشغيلُه يدويًّا — وصار
 *   مهمّةً مجدولة» ويخرج. **والعاملُ الحقيقيُّ `cron_jobs.php` لا تُشغِّله
 *   مهمّةٌ واحدة.** ⇒ **مجدولٌ يعمل كلَّ دقيقةٍ ولا يفعل شيئًا** — وسجلُّ
 *   المهمّةِ يقول `rc=3`.
 *
 * ◆ **ولا يُصلح هذا المقياسُ شيئًا** — يقيس ويُسمّي:
 *   **E1 · `RETIRED_ENTRYPOINT`** — المهمّةُ تنفّذ ملفًّا يحمل **وسمَ التقاعدِ
 *        في نصِّه** ⇒ تعمل ولا تفعل.
 *   **E2 · `MISSING_TARGET`** — الملفُّ المُشار إليه **لا وجودَ له**.
 *   **E3 · `RUNNER_UNSCHEDULED`** — عاملٌ **مُعلَنٌ نشطًا في دفترِ المهامّ**
 *        ولا تنفّذه مهمّةٌ واحدةٌ من مهامِّ النظام ⇒ **دفترٌ بلا يدٍ تُشغِّله**.
 *
 * ⛔ **ولا تُنشئ هذه الأداةُ مهمّةً ولا تُعدّلها ولا تُشغّل عاملًا**: تصريفُ
 *   واحدٍ وعشرين ألفَ حدثٍ **يُنشئ أثرًا ماليًّا حقيقيًّا**، وإنشاءُ مهمّةٍ في
 *   جدولِ الويندوز **تغييرُ بيئةٍ خارجَ المستودع**. ⇒ **كلاهما فعلُ تشغيلٍ
 *   بقرارِ مالكِه** — والمقياسُ يضعه أمامَه مسمًّى.
 *
 * التشغيل: php tools/rpr03_scheduler_entrypoints.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

/* ═══ ① القاعدةُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
/** وسمُ التقاعدِ **باستدعاءِ الحارسِ نفسِه** — `ems_manual_run_retired('job','file')`.
 *  ⛔ **ولا يُبحث عن نصِّ الشاشةِ**: النصُّ يتغيّر والاستدعاءُ عقد — **وهو يحمل
 *  اسمَ العاملِ أيضًا**، فيُعرف أيُّ مهمّةٍ فقدت يدَها لا أنَّ ملفًّا متقاعد. */
function se_retired_job($src)
{
    if (preg_match('~ems_manual_run_retired\s*\(\s*[\x27\x22]([a-z0-9_]+)[\x27\x22]~i', (string) $src, $m)) {
        return $m[1];
    }
    return '';
}
function se_is_retired($src) { return se_retired_job($src) !== ''; }

if ($SELF) {
    $fail = 0;
    if (se_retired_job("ems_manual_run_retired('event_retry', 'cron_events.php');") !== 'event_retry') {
        echo "  X وسمُ التقاعدِ لم يُرصَد باسمِ عاملِه\n"; $fail++;
    }
    /* ⛔ **ونصُّ الشاشةِ ليس وسمًا**: الرسالةُ تتغيّر والاستدعاءُ عقد */
    if (se_is_retired('… هذا الأمر ألغي تشغيله يدويا — وصار مهمة مجدولة …')) {
        echo "  X نصُّ شاشةٍ عُدَّ وسمًا\n"; $fail++;
    }
    /* **الكاسر**: مفردةٌ فريدةٌ لا ترد إلّا هنا */
    if (se_is_retired('zzq_unique_live_entrypoint_probe')) { echo "  X ملفٌّ حيٌّ عُدَّ متقاعدًا\n"; $fail++; }
    if (se_is_retired('')) { echo "  X الفراغُ عُدَّ متقاعدًا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والوسمُ يُرصَد ولا يُخمَّن بالاسم\n";
    exit($fail ? 1 : 0);
}

/* ═══ ② مهامُّ النظامِ — تُقرأ ولا تُمَسّ ═════════════════════════════════ */
$tasks = array();
$xml = array(); $rc = 0;
@exec('schtasks /query /fo LIST /v 2>&1', $xml, $rc);
$cur = array();
foreach ($xml as $ln) {
    $ln = trim($ln);
    if ($ln === '') {
        if (isset($cur['name']) && stripos($cur['name'], 'EMS') !== false) { $tasks[] = $cur; }
        $cur = array(); continue;
    }
    $p = strpos($ln, ':');
    if ($p === false) { continue; }
    $k = trim(substr($ln, 0, $p)); $v = trim(substr($ln, $p + 1));
    if ($k === 'TaskName' || $k === 'اسم المهمة') { $cur['name'] = $v; }
    if ($k === 'Task To Run' || $k === 'المهمة المطلوب تشغيلها') { $cur['cmd'] = $v; }
    if ($k === 'Last Result' || $k === 'آخر نتيجة') { $cur['rc'] = $v; }
    if ($k === 'Last Run Time' || $k === 'آخر وقت تشغيل') { $cur['last'] = $v; }
}
if (isset($cur['name']) && stripos($cur['name'], 'EMS') !== false) { $tasks[] = $cur; }

/* ═══ ③ الحكمُ لكلِّ مهمّة ════════════════════════════════════════════════ */
$E1 = array(); $E2 = array(); $ok = array(); $scriptsRun = array();
foreach ($tasks as $t) {
    $cmd = isset($t['cmd']) ? $t['cmd'] : '';
    if (!preg_match_all('~([A-Za-z]:\\\\[^\s"]+\.php|[A-Za-z]:/[^\s"]+\.php)~', $cmd, $m)) { continue; }
    $target = end($m[1]);
    $tp = str_replace(chr(92), '/', $target);
    $scriptsRun[strtolower(basename($tp))] = 1;
    if (!is_file($tp)) { $E2[] = array($t['name'], $tp); continue; }
    $src = (string) @file_get_contents($tp);
    $rj = se_retired_job($src);
    if ($rj !== '') { $E1[] = array($t['name'], basename($tp), isset($t['rc']) ? $t['rc'] : '—', $rj); }
    else { $ok[] = array($t['name'], basename($tp)); }
}

/* ═══ ④ عاملٌ مُعلَنٌ بلا يدٍ تُشغِّله ═══════════════════════════════════ */
$E3 = array();
$q = @$conn->query("SELECT job_type, is_active FROM ems_job_schedule WHERE is_active = 1");
$jobs = array();
while ($q && ($z = $q->fetch_assoc())) { $jobs[] = $z['job_type']; }
/* ◆ **والعاملُ العامُّ `cron_jobs.php` يُشغِّل دفترَ المهامِّ كلَّه** — فإن كان
   مجدولًا فلا عاملَ يتيمًا؛ وإلّا **فالدفترُ كلُّه بلا يد**. */
$runnerScheduled = isset($scriptsRun['cron_jobs.php']);
if (!$runnerScheduled) { foreach ($jobs as $j) { $E3[] = $j; } }

/* ═══ ⑤ المستهلكون — الأثرُ الذي يقيس صدقَ الجدولة ═════════════════════
   ⛔ **والرأسُ من ناقلِ الموزِّعِ نفسِه** (FINAL_CLOSE ⑨): المؤشِّراتُ معرِّفاتُ
   `fin_financial_events` — وقياسُها على `ems_business_events` مقارنةُ دفترَين. */
$maxEv = (int) @$conn->query("SELECT COALESCE(MAX(id),0) FROM fin_financial_events
                               WHERE event_key IS NOT NULL AND COALESCE(is_deleted,0)=0")->fetch_row()[0];
$cons = array();
$q = @$conn->query("SELECT consumer, enabled, cursor_event_id, updated_at FROM ems_event_consumers");
while ($q && ($z = $q->fetch_assoc())) {
    $z['behind'] = $maxEv - (int) $z['cursor_event_id'];
    $cons[] = $z;
}

/* ═══ ⑥ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-03` #١٩ — مداخلُ المهامِّ المجدولةِ مقيسةً من الأثر ═══\n";
printf("  مهامُّ `EMS_*` مقروءة: **%d** · وعاملُ الدفترِ `cron_jobs.php` مجدولٌ: **%s**\n\n",
       count($tasks), $runnerScheduled ? 'نعم' : '**لا**');
printf("  E1 `RETIRED_ENTRYPOINT`   %2d — تعمل وتنفّذ **مدخلًا متقاعدًا** ⇒ لا تفعل شيئًا\n", count($E1));
printf("  E2 `MISSING_TARGET`       %2d — الملفُّ المُشار إليه لا وجودَ له\n", count($E2));
printf("  E3 `RUNNER_UNSCHEDULED`   %2d — عاملٌ نشطٌ في الدفترِ ولا يدَ تُشغِّله\n", count($E3));
printf("  ✔ سليمة                   %2d\n", count($ok));

if ($E1) {
    echo "\n  ── مداخلُ متقاعدةٌ تُنفَّذ ──\n";
    foreach ($E1 as $x) { printf("   %-28s ⇐ %-26s (rc=%s)\n", $x[0], $x[1], $x[2]); }
}
if ($E2) {
    echo "\n  ── هدفٌ غيرُ موجود ──\n";
    foreach ($E2 as $x) { printf("   %-28s ⇐ %s\n", $x[0], $x[1]); }
}
if ($E3) {
    echo "\n  ── عاملٌ نشطٌ بلا يدٍ تُشغِّله ──\n";
    foreach (array_slice($E3, 0, 12) as $j) { printf("   · %s\n", $j); }
    if (count($E3) > 12) { printf("   … و%d غيرُه\n", count($E3) - 12); }
}
if ($cons) {
    echo "\n  ── الأثرُ: مؤشِّراتُ المستهلكين مقابلَ آخرِ حدث (" . $maxEv . ") ──\n";
    foreach ($cons as $c) {
        printf("   %-24s مُفعَّل=%s خلفَه **%6d** · آخرُ حركةٍ %s\n",
               $c['consumer'], $c['enabled'] ? 'نعم' : 'لا', $c['behind'], $c['updated_at']);
    }
}

$bad = count($E1) + count($E2) + count($E3);
echo "\n────────────────────────────────────────────────────────────\n";
printf("**إخفاقاتُ مدخلٍ مقيسة: %d**\n", $bad);
echo "⛔ **ولا تُنشئ هذه الأداةُ مهمّةً ولا تُشغّل عاملًا**: تصريفُ المتراكمِ\n";
echo "  **يُنشئ أثرًا ماليًّا حقيقيًّا**، وإنشاءُ مهمّةٍ **تغييرُ بيئةٍ خارجَ\n";
echo "  المستودع** ⇒ **كلاهما فعلُ تشغيلٍ بقرارِ مالكِه** — والمقياسُ يضعه\n";
echo "  أمامَه مسمًّى بدل أن يبقى سكونًا صامتًا يقرؤه الدفترُ نجاحًا.\n";

if ($MD) {
    $o  = "# `RPR-03` #١٩ — مداخلُ المهامِّ المجدولةِ مقيسةً من الأثر\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n\n";
    $o .= "## العطبُ الذي كان صامتًا\n\n";
    $o .= "#١٩ يقيس **دفترَ المهامِّ في القاعدة** فيقرأ نجاحًا لأنَّ الصفَّ يُحدَّث — ";
    $o .= "⛔ **وهو أعمى عمّن يُشغِّله فعلًا**. والأثرُ يشهد: مؤشِّراتُ المستهلكين لم تتحرّك.\n\n";
    $o .= "| الحكم | العدد |\n|---|---:|\n";
    $o .= "| `RETIRED_ENTRYPOINT` | **" . count($E1) . "** |\n";
    $o .= "| `MISSING_TARGET` | " . count($E2) . " |\n";
    $o .= "| `RUNNER_UNSCHEDULED` | " . count($E3) . " |\n";
    $o .= "| سليمة | " . count($ok) . " |\n\n";
    foreach ($E1 as $x) { $o .= "- ⛔ **`" . $x[0] . "`** ينفّذ `" . $x[1] . "` — **مدخلٌ متقاعد** (rc=" . $x[2] . ")\n"; }
    $o .= "\n## الأثر\n\n| المستهلك | مُفعَّل | خلفَه | آخرُ حركة |\n|---|---|---:|---|\n";
    foreach ($cons as $c) {
        $o .= '| `' . $c['consumer'] . '` | ' . ($c['enabled'] ? 'نعم' : 'لا') . ' | **'
            . $c['behind'] . '** | ' . $c['updated_at'] . " |\n";
    }
    $o .= "\n⛔ **ولا تُنشئ الأداةُ مهمّةً ولا تُشغّل عاملًا** — تصريفُ المتراكمِ يُنشئ أثرًا ";
    $o .= "ماليًّا حقيقيًّا، وإنشاءُ مهمّةٍ تغييرُ بيئةٍ خارجَ المستودع: **فعلُ تشغيلٍ بقرارِ مالكِه**.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_SCHEDULER_ENTRYPOINTS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_SCHEDULER_ENTRYPOINTS.md\n";
}
exit($bad ? 1 : 0);
