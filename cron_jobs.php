<?php
/**
 * cron_jobs.php — العاملُ الخلفيُّ الواحد (ENG-01 ⑥ · JB-01..JB-08)
 * ═══════════════════════════════════════════════════════════════════════════
 * التشغيل:  php cron_jobs.php [--worker=W1] [--cycles=1] [--lock=600]
 *           أو GET ?key=<JOBS_CRON_KEY من .env>
 *
 * دورةٌ واحدةٌ تفعل خمسةً بالترتيب:
 *   ① تُحرّر ما انقضت مهلةُ قفلِه   (F-16 · CK-14)
 *   ② تُجسّد المستحقَّ من الجدولةِ في الطابور (مصدرُه schedule لا manual)
 *   ③ تلتقط بقفلٍ ذرّيٍّ وتنفّذ      (F-15 — ROW_COUNT()=1 يعني الالتقاط)
 *   ④ تُثبت آخرَ نجاحٍ على الجدولة   (وعليه يقوم إنذارُ التوقف)
 *   ⑤ ترفع إنذارَ توقفِ العامل       (CK-15 — والصمتُ أخطرُ من الفشل)
 *
 * ◆ تشغيلُ عدةِ عمّالٍ معًا آمنٌ بنيويًّا: القفلُ في WHERE لا في ترتيبِ النداء.
 * ═══════════════════════════════════════════════════════════════════════════
 */
$IS_CLI = (PHP_SAPI === 'cli');
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Services/Queue/JobQueueService.php';
require_once __DIR__ . '/app/Services/Queue/JobScheduleService.php';
require_once __DIR__ . '/app/Services/Queue/JobHandlers.php';

use App\Services\Queue\JobQueueService as JQ;
use App\Services\Queue\JobScheduleService as JS;
use App\Services\Queue\JobHandlers as JH;

// fail-closed: مفتاحٌ غير مضبوطٍ في .env = لا مسارَ ويب إطلاقًا (CLI لا يتأثر)
if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('JOBS_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}
while (ob_get_level() > 0) { ob_end_clean(); }

$args = array();
if ($IS_CLI) {
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
    }
}
$workerId = (string) ($args['worker'] ?? ('w' . getmypid() . '-' . substr(bin2hex(random_bytes(3)), 0, 6)));
$cycles   = max(1, (int) ($args['cycles'] ?? 1));
$lockSecs = max(30, (int) ($args['lock'] ?? 600));
$maxJobs  = max(1, (int) ($args['max'] ?? 20));

$handlers = JH::map();
$totals = array('released' => 0, 'enqueued' => 0, 'claimed' => 0, 'done' => 0, 'failed' => 0, 'alerts' => 0);

echo "══ العامل الخلفي: {$workerId} · دورات={$cycles} · مهلة القفل={$lockSecs}ث ══\n";

for ($c = 1; $c <= $cycles; $c++) {
    // ① تحريرُ الأقفالِ المنقضية (F-16)
    $rel = JQ::releaseExpiredLocks($conn);
    $totals['released'] += $rel;
    if ($rel > 0) { echo "  ① حرر {$rel} قفلا منقضيا\n"; }

    // ② تجسيدُ المستحقِّ من الجدولة
    //   --bootstrap: خطوةُ تركيبٍ لمرةٍ واحدة — تُدرج تشغيلةً أولى لكلِّ جدولةٍ
    //   نشطةٍ بلا انتظارِ دقيقتِها، فالجدولةُ التي لم تعمل قطُّ تُقرأ «متوقفة»
    //   في CK-15 وهي لم تُركَّب بعد. وليست أمرًا يدويًّا دوريًّا: تُشغَّل مرةً
    //   عند التركيب، وبعدَها تحكمها دقائقُها وحدَها.
    if ($c === 1 && isset($args['bootstrap'])) {
        $mat = JS::materialize($conn, null, true);
        echo "  ② [تركيب] أدرج {$mat['enqueued']}: " . implode(', ', $mat['types']) . "\n";
    } else {
        $mat = JS::materialize($conn);
        if ($mat['enqueued'] > 0) { echo "  ② أدرج {$mat['enqueued']}: " . implode(', ', $mat['types']) . "\n"; }
    }
    $totals['enqueued'] += $mat['enqueued'];

    // ③ الالتقاطُ الذرّيُّ والتنفيذ
    $ran = 0;
    while ($ran < $maxJobs) {
        $job = JQ::claimAtomic($conn, $workerId, $lockSecs);
        if (!$job) { break; }
        $ran++;
        $totals['claimed']++;
        $jobId = (int) $job['job_id'];
        $type  = (string) $job['job_type'];

        // claimed → processing (chk_lock مستوفًى ما دامت 'claimed')
        $conn->query("UPDATE `ems_job_queue` SET `state`='processing' WHERE `job_id`={$jobId}");

        if (!isset($handlers[$type])) {
            JQ::fail($conn, $jobId, (int) $job['attempts'], (int) $job['max_attempts'],
                'لا معالج للنوع ' . $type);
            $totals['failed']++;
            echo "  ✗ #{$jobId} {$type} — لا معالج\n";
            continue;
        }

        try {
            $payload = json_decode((string) $job['payload_json'], true) ?: array();
            $r = call_user_func($handlers[$type], $conn, (int) $job['company_id'], $payload, $jobId);
            if (is_array($r) && isset($r['ok']) && $r['ok'] === false) {
                JQ::fail($conn, $jobId, (int) $job['attempts'], (int) $job['max_attempts'],
                    (string) ($r['reason'] ?? 'فشل المعالج'));
                $totals['failed']++;
                echo "  ✗ #{$jobId} {$type} — " . ($r['reason'] ?? 'فشل') . "\n";
                continue;
            }
            $conn->query(
                "UPDATE `ems_job_queue`
                    SET `state`='done', `finished_at`=NOW(), `worker_id`=NULL, `lock_expires_at`=NULL
                  WHERE `job_id`={$jobId}"
            );
            // ④ آخرُ نجاحٍ على الجدولة — وعليه وحدَه يقوم إنذارُ التوقف
            JS::markSuccess($conn, $type);
            $totals['done']++;
            echo "  ✔ #{$jobId} {$type} — " . (is_array($r) && isset($r['summary']) ? $r['summary'] : 'تمّ') . "\n";
        } catch (\Throwable $e) {
            JQ::fail($conn, $jobId, (int) $job['attempts'], (int) $job['max_attempts'],
                mb_substr($e->getMessage(), 0, 480));
            $totals['failed']++;
            echo "  ✗ #{$jobId} {$type} — " . mb_substr($e->getMessage(), 0, 120) . "\n";
        }
    }
    if ($ran === 0) { echo "  ③ لا مهمة مستحقة في هذه الدورة\n"; }
}

// ⑤ إنذارُ توقفِ العامل — «فتوقفُ العاملِ صامتًا أخطرُ من فشلِ مهمة»
$totals['alerts'] = JS::alertStalled($conn);
if ($totals['alerts'] > 0) { echo "  ⑤ رفع {$totals['alerts']} إنذار توقف\n"; }

echo "══ الحصيلة: حرر={$totals['released']} أدرج={$totals['enqueued']} "
   . "التقط={$totals['claimed']} نجح={$totals['done']} فشل={$totals['failed']} "
   . "إنذارات={$totals['alerts']} ══\n";

exit($totals['failed'] > 0 ? 1 : 0);
