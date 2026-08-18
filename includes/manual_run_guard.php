<?php
/**
 * manual_run_guard.php — إلغاءُ الأوامرِ اليدويةِ التي يُشغّلُها موظفٌ اليوم
 * (ENG-01 ⑥ · JB-04 — «◆ فأمرٌ يدويٌّ في رحلةِ متدرِّبٍ يعني نظامًا غيرَ مكتمل»)
 * ───────────────────────────────────────────────────────────────────────────
 * الأمرُ القديمُ لا يُحذف — «ولا تحذفْ صفًّا أبدًا» ولا ملفًّا يعتمد عليه أحد.
 * بل يُلغى تشغيلُه اليدويُّ ويُحال إلى الطابور: من نادى الملفَّ بيدِه يقرأ
 * أين صارت المهمةُ ومَن يشغّلها ومتى، ويخرج برمزِ 3 (مُحال لا فشل).
 *
 * ويبقى بابٌ واحدٌ مشروعٌ: النداءُ من العاملِ الخلفيِّ نفسِه، ويُعرَف بثابتٍ
 * يضبطه العاملُ قبلَ التضمين — لا بوسيطٍ يكتبه المستخدم. فلا يُتجاوز الحارسُ
 * بكتابةِ علمٍ في سطرِ الأوامر.
 *
 * الاستعمال — أولَ سطرٍ تنفيذيٍّ في الأمرِ القديم:
 *   require_once __DIR__ . '/../includes/manual_run_guard.php';
 *   ems_manual_run_retired('fin_posting', 'Operations/cron_fin_posting.php');
 */

if (!defined('EMS_JOB_WORKER')) { define('EMS_JOB_WORKER', false); }

if (!function_exists('ems_manual_run_retired')) {
    /**
     * @param string $jobType نوعُ المهمةِ المجدولةِ التي حلّت محلَّ هذا الأمر
     * @param string $oldPath مسارُ الأمرِ القديمِ كما يُكتب في الطرفية
     */
    function ems_manual_run_retired($jobType, $oldPath)
    {
        // البابُ المشروعُ الوحيد: العاملُ الخلفيُّ يضبط الثابتَ قبلَ التضمين
        if (defined('EMS_JOB_WORKER') && EMS_JOB_WORKER === true) { return; }

        $isCli = (PHP_SAPI === 'cli');
        $nl = $isCli ? "\n" : "<br>\n";
        if (!$isCli) { http_response_code(409); header('Content-Type: text/plain; charset=UTF-8'); }

        $sched = '—'; $owner = '—'; $last = 'لم تعمل بعد';
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $st = $GLOBALS['conn']->prepare(
                'SELECT `cron_expr`, `owner_role_id`, `last_success_at`
                   FROM `ems_job_schedule` WHERE `job_type`=? LIMIT 1');
            if ($st) {
                $st->bind_param('s', $jobType);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) {
                    $sched = (string) $row['cron_expr'];
                    $owner = (string) $row['owner_role_id'];
                    if ($row['last_success_at'] !== null) { $last = (string) $row['last_success_at']; }
                }
            }
        }

        echo $nl;
        echo "═══════════════════════════════════════════════════════════════" . $nl;
        echo " هذا الأمرُ أُلغي تشغيلُه يدويًّا — وصار مهمةً مجدولة" . $nl;
        echo "═══════════════════════════════════════════════════════════════" . $nl;
        echo " الأمرُ القديم : {$oldPath}" . $nl;
        echo " نوعُ المهمة   : {$jobType}" . $nl;
        echo " جدولتُها      : {$sched}" . $nl;
        echo " مالكُها (دور) : {$owner}" . $nl;
        echo " آخرُ نجاح     : {$last}" . $nl;
        echo $nl;
        echo " والتشغيلُ الآن بالعاملِ الخلفيِّ وحدَه:" . $nl;
        echo "   php cron_jobs.php" . $nl;
        echo $nl;
        echo " ولمتابعتِها من الشاشة: Governance/job_queue.php · Governance/job_schedule.php" . $nl;
        echo "═══════════════════════════════════════════════════════════════" . $nl;

        exit(3); // 3 = مُحال إلى الطابور — ليس فشلًا وليس نجاحًا
    }
}
