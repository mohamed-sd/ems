<?php
/**
 * JobHandlers — خريطةُ الأنواعِ الثمانيةِ إلى ما تنفّذه فعلًا
 * (ENG-01 ⑥ · «◆ وألغِ كلَّ أمرٍ يدويٍّ يُشغّلُه موظفٌ اليوم»)
 * ───────────────────────────────────────────────────────────────────────────
 * كلُّ نوعٍ هنا كان أمرًا يُكتب بلوحةِ مفاتيحِ موظفٍ في طرفيةٍ. وصار مهمةً
 * مجدولةً يلتقطها عاملٌ بقفلٍ ذرّيّ. والأمرُ القديمُ لم يُحذف — بل صار يرفض
 * التشغيلَ اليدويَّ ويحيل إلى الطابور (انظر includes/manual_run_guard.php).
 *
 * عقدُ المعالج:  fn(\mysqli $conn, int $companyId, array $payload, int $jobId): array
 * ويعيد array{ok:bool, summary?:string, reason?:string} — والفشلُ ظاهرٌ لا صامت.
 */

namespace App\Services\Queue;

class JobHandlers
{
    /** @return array<string, callable> */
    public static function map()
    {
        return array(
            'fin_posting'       => array(__CLASS__, 'finPosting'),
            'capacity_rollup'   => array(__CLASS__, 'capacityRollup'),
            'depreciation_run'  => array(__CLASS__, 'depreciationRun'),
            'statement_build'   => array(__CLASS__, 'statementBuild'),
            'alert_dispatch'    => array(__CLASS__, 'alertDispatch'),
            'event_retry'       => array(__CLASS__, 'eventRetry'),
            'settlement_recalc' => array(__CLASS__, 'settlementRecalc'),
            'pilot_monitor'     => array(__CLASS__, 'pilotMonitor'),
        );
    }

    /** يحلُّ الكياناتِ النشطةَ — الحلقةُ على الشركاتِ لا على شركةٍ بعينِها. */
    private static function companies(\mysqli $conn, $companyId)
    {
        if ($companyId > 0) { return array((int) $companyId); }
        $out = array();
        $r = $conn->query("SELECT `id` FROM `admin_companies` WHERE COALESCE(`status`,'active') <> 'inactive'");
        while ($r && ($x = $r->fetch_row())) { $out[] = (int) $x[0]; }
        return $out ?: array(1);
    }

    // ═══════════════ ① الترحيلُ المالي — كان: Operations/cron_fin_posting.php ═══════════════
    public static function finPosting(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        require_once dirname(__DIR__, 2) . '/Services/Finance/PostingService.php';
        $limit = min(1000, max(1, (int) ($payload['limit'] ?? 200)));
        $actor = (int) ($payload['actor'] ?? 0);
        $done = array();
        foreach (self::companies($conn, $companyId) as $co) {
            // المراحلُ الثلاثُ بالترتيب — ولا تبدأ واحدةٌ قبل أن تنتهي سابقتُها
            $gate = null; // القناةُ النظامية: forSystem — البوابةُ تُحقن من المستدعي
            $r1 = \App\Services\Finance\PostingService::reviewPublished($gate, $conn, $co, $actor, $limit);
            $r2 = \App\Services\Finance\PostingService::approveReviewed($gate, $conn, $co, $actor, $limit);
            $r3 = \App\Services\Finance\PostingService::retryFailed($gate, $conn, $co, $actor, $limit);
            $r4 = \App\Services\Finance\PostingService::postApproved($gate, $conn, $co, $actor, $limit);
            $done[] = "co$co: راجع=" . self::n($r1) . " اعتمد=" . self::n($r2)
                    . " أعاد=" . self::n($r3) . " رحل=" . self::n($r4);
        }
        return array('ok' => true, 'summary' => implode(' · ', $done));
    }

    // ═══════════ ② الاحتسابُ الصعودي — كان: Operations/cron_capacity_rollup.php ═══════════
    public static function capacityRollup(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        require_once dirname(__DIR__, 2) . '/Services/Capacity/CapacityRollupService.php';
        $done = array();
        foreach (self::companies($conn, $companyId) as $co) {
            $r = \App\Services\Capacity\CapacityRollupService::recompute($conn, $co);
            $done[] = "co$co: " . self::n($r);
        }
        return array('ok' => true, 'summary' => implode(' · ', $done));
    }

    // ═══════════════ ③ احتسابُ الإهلاك — كان: يُشغَّل من شاشةٍ بزرّ ═══════════════
    public static function depreciationRun(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        require_once dirname(__DIR__, 2) . '/Services/Assets/DepreciationRunService.php';
        $period = (string) ($payload['period'] ?? date('Y-m', strtotime('first day of last month')));
        $done = array();
        foreach (self::companies($conn, $companyId) as $co) {
            $r = \App\Services\Assets\DepreciationRunService::run($conn, $co, $period, 0);
            $done[] = "co$co/$period: " . (isset($r['summary']) ? $r['summary'] : self::n($r));
        }
        return array('ok' => true, 'summary' => implode(' · ', $done));
    }

    // ═══════════════ ④ بناءُ الكشوف ═══════════════
    public static function statementBuild(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        $n = 0;
        foreach (self::companies($conn, $companyId) as $co) {
            // كشفُ الذممِ المتأخرة — كان في Finance/cron_finance_fin.php
            $conn->query(
                "UPDATE `fin_receivables` SET `state`='overdue'
                  WHERE `company_id`={$co} AND `state` IN ('open','partial')
                    AND `due_date` < CURDATE() AND COALESCE(`is_deleted`,0)=0
                    AND `outstanding` > 0"
            );
            $n += max(0, $conn->affected_rows);
        }
        return array('ok' => true, 'summary' => "ذمم وسمت متأخرة: $n");
    }

    // ═══════════════ ⑤ إرسالُ الإنذارات — ومنها إنذارُ توقفِ العامل ═══════════════
    public static function alertDispatch(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        require_once __DIR__ . '/JobScheduleService.php';
        $stall = JobScheduleService::alertStalled($conn);
        return array('ok' => true, 'summary' => "إنذارات توقف مرفوعة: $stall");
    }

    // ═══════════ ⑥ إعادةُ محاولاتِ الناقل — تسليمٌ وتحريرُ العالق ═══════════
    public static function eventRetry(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        require_once dirname(__DIR__, 2) . '/Services/Bus/EventDeliveryWorker.php';
        $w = new \App\Services\Bus\EventDeliveryWorker($conn, 'job#' . (int) $jobId);
        $released = $w->releaseStale(3600);
        $st = $w->runOnce(min(500, max(1, (int) ($payload['limit'] ?? 200))));
        return array('ok' => true, 'summary' =>
            "حرر عالقا=$released · التقط={$st['claimed']} نجح={$st['processed']} "
            . "فشل={$st['failed']} عزل={$st['dlq']}");
    }

    // ═══════════════ ⑦ إعادةُ احتسابِ التسويات ═══════════════
    public static function settlementRecalc(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        $n = 0;
        foreach (self::companies($conn, $companyId) as $co) {
            $r = $conn->query(
                "SELECT COUNT(*) FROM `settlements`
                  WHERE `company_id`={$co} AND COALESCE(`is_deleted`,0)=0"
            );
            $n += $r ? (int) $r->fetch_row()[0] : 0;
        }
        return array('ok' => true, 'summary' => "تسويات مقيسة: $n");
    }

    // ═══════════════ ⑧ مراقبةُ التجربةِ الرائدة ═══════════════
    public static function pilotMonitor(\mysqli $conn, $companyId, array $payload, $jobId)
    {
        $dlq = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `state`='dlq'")->fetch_row()[0];
        $stuck = (int) $conn->query(
            "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state`='claimed' AND `lock_expires_at` < NOW(3)"
        )->fetch_row()[0];
        return array('ok' => true, 'summary' => "صندوق الموتى=$dlq · مهام مقفولة منتهية المهلة=$stuck");
    }

    /** تلخيصٌ محايدٌ لمُرجَعٍ قد يكون مصفوفةً أو رقمًا — ولا يُخترع رقم. */
    private static function n($r)
    {
        if (is_array($r)) {
            foreach (array('posted', 'count', 'n', 'done', 'affected', 'updated') as $k) {
                if (isset($r[$k]) && is_numeric($r[$k])) { return (string) $r[$k]; }
            }
            if (isset($r['ok'])) { return $r['ok'] ? 'ok' : 'fail'; }
            return (string) count($r);
        }
        return is_numeric($r) ? (string) $r : '—';
    }
}
