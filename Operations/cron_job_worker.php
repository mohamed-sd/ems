<?php
/**
 * Operations/cron_job_worker.php — عامل الطابور (update0004 · N-24 · NFR-06/07)
 * ───────────────────────────────────────────────────────────────────────────
 * يخطف المهام المستحقة وينفذها بمعالجاتها — كل دقيقة (أو أكثر) مجدولًا.
 * المعالجات المسجلة:
 *   payroll_bind    المسيّر عبر الطابور لا داخل الطلب (فتح/ربط دورة) — NFR-08
 *   periodic_cron   الدوريات المالية عبر الطابور — NFR-07
 *   bank_recon_scan مسح المطابقة البنكية دفعات 20×100 — NFR-07 + NFR-06
 *   debt_catchup    استدراك الدين (تقرير جرد الفجوات — N-22 خارج النطاق فيقاس ولا يقيد)
 *   batch_loop      مثبت التقسيط العام 20×100 (للأحزمة والقوالب)
 * التشغيل: php Operations/cron_job_worker.php [max_jobs]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_job_worker.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once dirname(__DIR__) . '/app/Services/Queue/JobQueueService.php';

use App\Services\Queue\JobQueueService as JQ;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$handlers = array(
    // NFR-08: المسيّر لا يجري داخل الطلب — الشاشة تدرج المهمة وتُشعَر بالاكتمال
    'payroll_bind' => function (\mysqli $conn, $co, $payload, $jobId) {
        require_once dirname(__DIR__) . '/app/Services/Payroll/PayrollRunService.php';
        require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
        require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
        require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
        require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
        $gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($co, 0, '', true));
        $r = \App\Services\Payroll\PayrollRunService::bindSnapshots(
            $conn, $gate, $co, intval($payload['run_id'] ?? 0), intval($payload['actor'] ?? 0));
        return array('ok' => !empty($r['ok']) || intval($r['code']) === 200,
            'reason' => isset($r['reason']) ? $r['reason'] : '',
            'summary' => 'ربط المسيّر: أشخاص=' . intval($r['persons'] ?? 0) . ' أسطر=' . intval($r['lines'] ?? 0));
    },
    // NFR-07: الدوريات المالية عبر الطابور
    'periodic_cron' => function (\mysqli $conn, $co, $payload, $jobId) {
        $out = shell_exec(escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__) . '/Finance/cron_periodic_fin.php') . ' 2>&1');
        return array('ok' => true, 'summary' => 'الدوريات: ' . mb_substr(trim((string) $out), -120));
    },
    // NFR-07+06: مسح المطابقة البنكية دفعات — فشل دفعة لا يسقط الباقي
    'bank_recon_scan' => function (\mysqli $conn, $co, $payload, $jobId) {
        $batches = intval($payload['batches'] ?? 20);
        $size = intval($payload['batch_size'] ?? 100);
        return JQ::runBatched($conn, $jobId, $co, $batches, $size,
            function (\mysqli $c, $co2, $b, $offset, $limit) {
                $r = $c->query("SELECT COUNT(*) n FROM (SELECT id FROM fin_bank_statement_lines
                                 WHERE company_id = {$co2} AND matched_at IS NULL
                                 ORDER BY id LIMIT {$limit} OFFSET {$offset}) x");
                if ($r === false) {
                    // جدول الكشف باسم آخر أو غائب — الدفعة تفشل ظاهرًا ولا تسقط الباقي
                    return array('ok' => false, 'reason' => 'كشف البنك: ' . $c->error);
                }
                $n = intval($r->fetch_assoc()['n']);
                return array('ok' => true, 'exhausted' => $n < $limit);
            });
    },
    // N-22 خارج النطاق: يقاس تقريرًا لا قيدًا (قرار النطاق المعلن)
    'debt_catchup' => function (\mysqli $conn, $co, $payload, $jobId) {
        $r = $conn->query("SELECT COUNT(*) c FROM fin_dues WHERE company_id = {$co} AND state = 'open'");
        $open = $r ? intval($r->fetch_assoc()['c']) : -1;
        return array('ok' => true, 'summary' => 'جرد استدراك الدين (تقرير لا قيد — N-22 خارج النطاق): ذمم مفتوحة=' . $open);
    },
    // مثبت التقسيط العام — 20×100
    'batch_loop' => function (\mysqli $conn, $co, $payload, $jobId) {
        $batches = intval($payload['batches'] ?? 20);
        $size = intval($payload['batch_size'] ?? 100);
        $failAt = isset($payload['fail_batches']) && is_array($payload['fail_batches']) ? $payload['fail_batches'] : array();
        return JQ::runBatched($conn, $jobId, $co, $batches, $size,
            function (\mysqli $c, $co2, $b, $offset, $limit) use ($failAt) {
                if (in_array($b, $failAt, true)) { return array('ok' => false, 'reason' => 'فشل مقصود للدفعة ' . $b); }
                return array('ok' => true);
            });
    },
);

$max = isset($argv[1]) ? max(1, intval($argv[1])) : 10;
$ran = 0; $okN = 0;
while ($ran < $max) {
    $job = JQ::claim($conn);
    if ($job === null) { break; }
    $ran++;
    if (JQ::execute($conn, $job, $handlers)) { $okN++; }
}
fwrite(STDOUT, "العامل: نفّذ {$ran} مهمة ({$okN} نجحت) — والفاشل بمحاولات تصاعدية وسجل ظاهر.\n");
exit(0);
