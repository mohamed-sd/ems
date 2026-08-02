<?php
/**
 * JobQueueService — طابور المعالجة (update0004 · N-24 · NFR-05→09)
 * ───────────────────────────────────────────────────────────────────────────
 * • enqueue: يعيد فورًا «قيد المعالجة» — والصفحة لا تتجمد أبدًا (NFR-08).
 * • claim: قفل تنافسي (UPDATE مشروط) — عامل واحد يخطف المهمة.
 * • محاولات تصاعدية: 1د → 5د → 25د — وبعد الحد dead بسجل فشل ظاهر (NFR-05).
 * • runBatched: 20 دفعة × 100 — وفشل دفعة يسجَّل في batch_failures ولا
 *   يسقط الباقي (NFR-06).
 * • الاكتمال إشعار fin_notifications (NFR-08).
 * • حارس الخمس ثوان (NFR-09): ems_request_time_guard في config يرصد؛
 *   وmustQueue() يمنع بدء احتساب طويل داخل الطلب بنيويًّا.
 */

namespace App\Services\Queue;

class JobQueueService
{
    /** إدراج مهمة — يعيد فورًا. */
    public static function enqueue(\mysqli $conn, $companyId, $jobType, array $payload = array(), $createdBy = null, $maxAttempts = 3)
    {
        $stmt = $conn->prepare(
            "INSERT INTO ems_job_queue (company_id, job_type, payload_json, max_attempts, created_by, progress_total)
             VALUES (?, ?, ?, ?, ?, ?)");
        $co = intval($companyId);
        $pj = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ma = intval($maxAttempts);
        $cb = $createdBy !== null ? intval($createdBy) : null;
        $total = isset($payload['batches'], $payload['batch_size'])
            ? intval($payload['batches']) : 0;
        $stmt->bind_param('issiii', $co, $jobType, $pj, $ma, $cb, $total);
        $stmt->execute();
        $id = intval($conn->insert_id);
        $stmt->close();
        return array('ok' => $id > 0, 'job_id' => $id, 'state' => 'queued',
            'reason' => 'قيد المعالجة — ستصلك إشارة الاكتمال (الصفحة لا تنتظر)');
    }

    /** خطف مهمة مستحقة — قفل تنافسي بلا سباق. */
    public static function claim(\mysqli $conn)
    {
        $row = $conn->query(
            "SELECT job_id FROM ems_job_queue
              WHERE state IN ('queued','failed') AND next_attempt_at <= NOW()
              ORDER BY job_id LIMIT 1")->fetch_assoc();
        if (!$row) { return null; }
        $id = intval($row['job_id']);
        $conn->query("UPDATE ems_job_queue SET state = 'processing', started_at = NOW(), attempts = attempts + 1
                       WHERE job_id = {$id} AND state IN ('queued','failed')");
        if ($conn->affected_rows !== 1) { return null; } // خطفها عامل آخر
        return $conn->query("SELECT * FROM ems_job_queue WHERE job_id = {$id}")->fetch_assoc();
    }

    /** تنفيذ مهمة مخطوفة عبر معالجها — كل مهمة معاملة مستقلة الحدود. */
    public static function execute(\mysqli $conn, array $job, array $handlers)
    {
        $id = intval($job['job_id']);
        $type = (string) $job['job_type'];
        if (!isset($handlers[$type])) {
            self::fail($conn, $id, intval($job['attempts']), intval($job['max_attempts']),
                'لا معالج للنوع ' . $type . ' — يسجَّل صفًّا في worker لا كودًا هنا');
            return false;
        }
        try {
            $payload = json_decode((string) $job['payload_json'], true) ?: array();
            $result = call_user_func($handlers[$type], $conn, intval($job['company_id']), $payload, $id);
            if (is_array($result) && isset($result['ok']) && $result['ok'] === false) {
                self::fail($conn, $id, intval($job['attempts']), intval($job['max_attempts']),
                    isset($result['reason']) ? (string) $result['reason'] : 'فشل المعالج');
                return false;
            }
            $conn->query("UPDATE ems_job_queue SET state = 'done', finished_at = NOW() WHERE job_id = {$id}");
            self::notify($conn, intval($job['company_id']),
                'اكتملت المهمة #' . $id . ' (' . $type . ')'
                . (is_array($result) && isset($result['summary']) ? ' — ' . $result['summary'] : ''));
            return true;
        } catch (\Throwable $e) {
            self::fail($conn, $id, intval($job['attempts']), intval($job['max_attempts']),
                mb_substr($e->getMessage(), 0, 480));
            return false;
        }
    }

    /**
     * NFR-06 · المسيّر المقسَّط ونظائره: دفعات × حجم — فشل دفعة يسجَّل ولا
     * يسقط الباقي، والتقدم ظاهر أولًا بأول.
     * @param callable $batchFn fn($conn, $companyId, $batchNo, $offset, $limit): array{ok,reason?}
     */
    public static function runBatched(\mysqli $conn, $jobId, $companyId, $batches, $batchSize, $batchFn)
    {
        $jobId = intval($jobId);
        $batches = max(1, intval($batches));
        $batchSize = max(1, intval($batchSize));
        $failures = array();
        $doneCount = 0;
        $conn->query("UPDATE ems_job_queue SET progress_total = {$batches} WHERE job_id = {$jobId}");
        for ($b = 1; $b <= $batches; $b++) {
            $offset = ($b - 1) * $batchSize;
            try {
                $conn->begin_transaction(); // حدود معاملة لكل دفعة
                $r = call_user_func($batchFn, $conn, $companyId, $b, $offset, $batchSize);
                if (is_array($r) && isset($r['ok']) && $r['ok'] === false) {
                    $conn->rollback();
                    $failures[] = array('batch' => $b, 'error' => isset($r['reason']) ? $r['reason'] : 'فشل');
                } else {
                    $conn->commit();
                    $doneCount++;
                    if (is_array($r) && !empty($r['exhausted'])) {
                        // البيانات انتهت قبل الدفعات — إنجاز مبكر لا فشل
                        $conn->query("UPDATE ems_job_queue SET progress_done = {$b}, progress_total = {$b} WHERE job_id = {$jobId}");
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $conn->rollback();
                $failures[] = array('batch' => $b, 'error' => mb_substr($e->getMessage(), 0, 180));
            }
            $fj = $conn->real_escape_string(json_encode($failures, JSON_UNESCAPED_UNICODE));
            $conn->query("UPDATE ems_job_queue SET progress_done = {$b}, batch_failures = '{$fj}' WHERE job_id = {$jobId}");
        }
        return array('ok' => true, 'done' => $doneCount, 'failed_batches' => count($failures),
            'summary' => $doneCount . ' دفعة نجحت و' . count($failures) . ' فشلت (مسجلة ظاهرًا) — والباقي لم يسقط');
    }

    /** فشل بمحاولات تصاعدية: 1د → 5د → 25د — وبعد الحد dead ظاهرًا. */
    public static function fail(\mysqli $conn, $jobId, $attempts, $maxAttempts, $error)
    {
        $jobId = intval($jobId);
        $err = $conn->real_escape_string(mb_substr((string) $error, 0, 480));
        if ($attempts >= $maxAttempts) {
            $conn->query("UPDATE ems_job_queue SET state = 'dead', last_error = '{$err}', finished_at = NOW()
                           WHERE job_id = {$jobId}");
            $co = intval($conn->query("SELECT company_id FROM ems_job_queue WHERE job_id = {$jobId}")->fetch_assoc()['company_id']);
            self::notify($conn, $co, 'المهمة #' . $jobId . ' ماتت بعد ' . $attempts . ' محاولات — ' . mb_substr((string) $error, 0, 120));
            return;
        }
        $delayMin = pow(5, max(0, $attempts - 1)); // 1 · 5 · 25
        $conn->query("UPDATE ems_job_queue SET state = 'failed', last_error = '{$err}',
                      next_attempt_at = DATE_ADD(NOW(), INTERVAL {$delayMin} MINUTE)
                       WHERE job_id = {$jobId}");
    }

    /** NFR-09 · «لا احتساب يتجاوز خمس ثوان داخل الطلب» — يمنع بدءه بنيويًّا. */
    public static function mustQueue($estimatedSeconds, $context = '')
    {
        if (PHP_SAPI === 'cli') { return array('ok' => true, 'reason' => 'CLI — خارج الطلب'); }
        if (floatval($estimatedSeconds) > 5.0) {
            return array('ok' => false, 'code' => 409,
                'reason' => 'الاحتساب المقدر ' . $estimatedSeconds . ' ثانية يتجاوز حد الخمس داخل الطلب — يمر عبر الطابور: '
                    . $context . ' (NFR-09)');
        }
        return array('ok' => true, 'reason' => '');
    }

    public static function status(\mysqli $conn, $jobId)
    {
        $j = $conn->query("SELECT job_id, job_type, state, attempts, progress_done, progress_total,
                                  batch_failures, last_error, created_at, finished_at
                             FROM ems_job_queue WHERE job_id = " . intval($jobId))->fetch_assoc();
        return $j ?: null;
    }

    private static function notify(\mysqli $conn, $companyId, $title)
    {
        $stmt = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                                VALUES (?, 'all', ?, 'admin/bus_monitor.php')");
        $t = mb_substr($title, 0, 195);
        $stmt->bind_param('is', $companyId, $t);
        $stmt->execute();
        $stmt->close();
    }
}
