<?php
/**
 * JobScheduleService — جدولةُ المهامِّ الدوريةِ وإنذارُ توقفِ العامل
 * (ENG-01 JB-05/JB-06 · TSP-0262..0271 · F-15 · F-16 · CK-14 · CK-15)
 * ───────────────────────────────────────────────────────────────────────────
 * «◆ فأمرٌ يدويٌّ في رحلةِ متدرِّبٍ يعني نظامًا غيرَ مكتمل»
 * «◆ فتوقفُ العاملِ صامتًا أخطرُ من فشلِ مهمة — لأن كلَّ شيءٍ يبدو طبيعيًّا»
 *
 * الجدولةُ تقول ماذا يعمل ومتى، والطابورُ يقول ماذا يجري الآن. وبينهما:
 *   due()          → ما استحق ولم يُدرَج بعد (بلا تكرارٍ في النافذةِ نفسِها)
 *   materialize()  → يُدرج المستحقَّ في الطابورِ بمصدرٍ 'schedule'
 *   markSuccess()  → يُثبت آخرَ نجاح — وعليه يقوم إنذارُ التوقف
 *   stalled()      → ما تجاوز مهلةَ الإنذار (CK-15)
 *   alertStalled() → يرفع إنذارًا لمالكِ الجدولةِ بدورِه لا لمجهول
 *
 * والتعبيرُ الزمنيُّ cron_expr يُقرأ بخمسةِ حقولٍ قياسية (د س ي ش أ) بدعمِ
 * `*` و`*​/n` وقائمةِ الأرقام — لا مكتبةَ خارجيةَ ولا تفسيرَ فضفاض.
 */

namespace App\Services\Queue;

class JobScheduleService
{
    /** الأنواعُ الثمانيةُ المعلَنةُ في القائمةِ المغلقة (TSP-0248). */
    const TYPES = array(
        'fin_posting', 'capacity_rollup', 'depreciation_run', 'statement_build',
        'alert_dispatch', 'event_retry', 'settlement_recalc', 'pilot_monitor',
    );

    /**
     * تعريفُ جدولةٍ أو تحديثُها (job.schedule.define).
     * @return array{ok:bool, id?:int, reason:string}
     */
    public static function define(\mysqli $conn, array $in)
    {
        $type = (string) ($in['job_type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            return array('ok' => false, 'reason' => 'نوع المهمة خارج القائمة المغلقة: ' . $type);
        }
        $cron = trim((string) ($in['cron_expr'] ?? ''));
        if ($cron === '' || !self::validExpr($cron)) {
            return array('ok' => false, 'reason' => 'تعبير زمني غير صالح (خمسة حقول): ' . $cron);
        }
        $owner = (int) ($in['owner_role_id'] ?? 0);
        if ($owner <= 0) {
            return array('ok' => false, 'reason' => 'المسؤول عند التوقف إلزامي — ولا جدولة بلا مالك');
        }
        $maxRt  = max(30, (int) ($in['max_runtime_seconds'] ?? 600));
        $alert  = max(60, (int) ($in['alert_after_seconds'] ?? 3600));
        $active = !empty($in['is_active']) ? 1 : (isset($in['is_active']) ? 0 : 1);
        $co     = (int) ($in['company_id'] ?? 1);
        $repl   = isset($in['replaces_manual']) && $in['replaces_manual'] !== ''
            ? (string) $in['replaces_manual'] : null;
        $by     = (int) ($in['created_by'] ?? 0);

        $st = $conn->prepare(
            'INSERT INTO `ems_job_schedule`
                (`company_id`,`job_type`,`cron_expr`,`max_runtime_seconds`,`alert_after_seconds`,
                 `owner_role_id`,`is_active`,`replaces_manual`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                `cron_expr`=VALUES(`cron_expr`), `max_runtime_seconds`=VALUES(`max_runtime_seconds`),
                `alert_after_seconds`=VALUES(`alert_after_seconds`), `owner_role_id`=VALUES(`owner_role_id`),
                `is_active`=VALUES(`is_active`), `replaces_manual`=VALUES(`replaces_manual`)'
        );
        if (!$st) { return array('ok' => false, 'reason' => 'prepare: ' . $conn->error); }
        $st->bind_param('issiiiisi', $co, $type, $cron, $maxRt, $alert, $owner, $active, $repl, $by);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        if (!$ok) { return array('ok' => false, 'reason' => $err); }

        $id = (int) $conn->query(
            "SELECT `id` FROM `ems_job_schedule` WHERE `job_type`='" . $conn->real_escape_string($type) . "'"
        )->fetch_row()[0];
        return array('ok' => true, 'id' => $id, 'reason' => 'سجلت الجدولة');
    }

    /**
     * إدراجُ المستحقِّ في الطابور — مصدرُه 'schedule' لا 'manual'.
     * العطالةُ بالنافذة: لا يُدرَج نوعٌ له صفٌّ حيٌّ في الطابورِ بعد.
     *
     * @return array{enqueued:int, skipped:int, types:array}
     */
    public static function materialize(\mysqli $conn, \DateTimeInterface $now = null, $bootstrap = false)
    {
        $now = $now ?: new \DateTimeImmutable(self::dbNow($conn));
        $out = array('enqueued' => 0, 'skipped' => 0, 'types' => array());

        $rows = $conn->query(
            "SELECT `id`,`company_id`,`job_type`,`cron_expr`,`max_runtime_seconds`,`owner_role_id`
               FROM `ems_job_schedule` WHERE `is_active`=1 ORDER BY `id`"
        );
        if (!$rows) { return $out; }

        while ($s = $rows->fetch_assoc()) {
            // في التركيبِ لمرةٍ واحدة تُدرج كلُّ جدولةٍ نشطةٍ بلا انتظارِ دقيقتِها،
            // وفيما عداه لا يُدرَج إلا ما حانت دقيقتُه.
            if (!$bootstrap && !self::isDue((string) $s['cron_expr'], $now)) { continue; }

            // لا إدراجَ ثانٍ لنوعٍ ما يزال حيًّا في الطابور — «مهمةٌ واحدةٌ لكلِّ نافذة»
            $t = $conn->real_escape_string((string) $s['job_type']);
            $live = (int) $conn->query(
                "SELECT COUNT(*) FROM `ems_job_queue`
                  WHERE `job_type`='{$t}' AND `state` IN ('queued','claimed','processing','running')"
            )->fetch_row()[0];
            if ($live > 0) { $out['skipped']++; continue; }

            // company_id = 0 في الجدولةِ يعني «كلَّ كيانٍ نشط» — فتُفرَد مهمةً
            // لكلِّ كيانٍ بعمودِ عزلٍ حقيقيّ. وبلا هذا تعمل الجدولةُ على كيانٍ
            // واحدٍ وتتخطّى ما فيه البياناتُ فعلًا — ولا شيءَ يبدو معطَّلًا.
            $targets = ((int) $s['company_id'] === 0)
                ? self::activeCompanies($conn)
                : array((int) $s['company_id']);

            $jt  = (string) $s['job_type'];
            $ref = 'ems_job_schedule#' . (int) $s['id'];
            $ins = $conn->prepare(
                "INSERT INTO `ems_job_queue`
                    (`company_id`,`job_type`,`payload_json`,`state`,`source`,`source_ref`,
                     `max_attempts`,`next_attempt_at`,`created_at`)
                 VALUES (?,?,?, 'queued', 'schedule', ?, 3, NOW(3), NOW(3))"
            );
            foreach ($targets as $co) {
                $payload = json_encode(array(
                    'schedule_id' => (int) $s['id'],
                    'window'      => $now->format('Y-m-d H:i'),
                    'owner_role'  => (int) $s['owner_role_id'],
                    'company_id'  => $co,
                ), JSON_UNESCAPED_UNICODE);
                $ins->bind_param('isss', $co, $jt, $payload, $ref);
                if ($ins->execute()) { $out['enqueued']++; $out['types'][] = $jt . '@' . $co; }
            }
            $ins->close();
        }
        return $out;
    }

    /** الكياناتُ النشطة — مصدرُ الفَرْدِ حين تكون الجدولةُ لكلِّ كيان. */
    public static function activeCompanies(\mysqli $conn)
    {
        $out = array();
        $r = $conn->query("SELECT `id` FROM `admin_companies` WHERE COALESCE(`status`,'active') <> 'inactive' ORDER BY `id`");
        while ($r && ($x = $r->fetch_row())) { $out[] = (int) $x[0]; }
        return $out ?: array(1);
    }

    /** إثباتُ آخرِ نجاح — وعليه وحدَه يقوم إنذارُ التوقف. */
    public static function markSuccess(\mysqli $conn, $jobType)
    {
        $st = $conn->prepare("UPDATE `ems_job_schedule` SET `last_success_at`=NOW(3) WHERE `job_type`=?");
        $st->bind_param('s', $jobType);
        $st->execute();
        $n = $conn->affected_rows;
        $st->close();
        return $n;
    }

    /**
     * CK-15 — الجدولاتُ المتأخرةُ فوقَ مهلةِ الإنذار.
     * @return array<int, array{job_type:string, owner_role_id:int, late_seconds:?int}>
     */
    public static function stalled(\mysqli $conn)
    {
        $out = array();
        $r = $conn->query(
            "SELECT `job_type`, `owner_role_id`, `alert_after_seconds`, `last_success_at`,
                    TIMESTAMPDIFF(SECOND, `last_success_at`, NOW()) AS late
               FROM `ems_job_schedule`
              WHERE `is_active`=1
                AND (`last_success_at` IS NULL
                     OR `last_success_at` < NOW() - INTERVAL `alert_after_seconds` SECOND)
              ORDER BY `job_type`"
        );
        if (!$r) { return $out; }
        while ($x = $r->fetch_assoc()) {
            $out[] = array(
                'job_type'      => (string) $x['job_type'],
                'owner_role_id' => (int) $x['owner_role_id'],
                'late_seconds'  => $x['late'] !== null ? (int) $x['late'] : null,
                'never_ran'     => $x['last_success_at'] === null,
            );
        }
        return $out;
    }

    /**
     * «◆ وإن لم يلتقط عاملٌ خلالَ مهلةِ الإنذارِ يُنشر إنذارٌ» — لمالكِ الجدولةِ
     * بدورِه لا لمجهول. وإنذارٌ واحدٌ في الساعةِ لكلِّ نوعٍ — لا إغراق.
     *
     * @return int عددُ الإنذاراتِ المرفوعة
     */
    public static function alertStalled(\mysqli $conn)
    {
        $n = 0;
        foreach (self::stalled($conn) as $s) {
            $tag = '[JOB-STALL:' . $s['job_type'] . ']';
            $dupe = (int) $conn->query(
                "SELECT COUNT(*) FROM `fin_notifications`
                  WHERE `title` LIKE '" . $conn->real_escape_string($tag) . "%'
                    AND `created_at` > NOW() - INTERVAL 1 HOUR"
            )->fetch_row()[0];
            if ($dupe > 0) { continue; }

            $late = $s['never_ran']
                ? 'لم تعمل قط منذ تعريفها'
                : 'تأخرت ' . self::humanSeconds((int) $s['late_seconds']);
            $title = mb_substr(
                $tag . ' توقف العامل عن «' . $s['job_type'] . '» — ' . $late
                . '. وتوقف العامل صامتا أخطر من فشل مهمة.', 0, 195);

            $st = $conn->prepare(
                "INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                 VALUES (1, 'finance_manager', ?, 'Governance/job_schedule.php')"
            );
            $st->bind_param('s', $title);
            if ($st->execute()) { $n++; }
            $st->close();
        }
        return $n;
    }

    // ───────────────────────── تفسيرُ التعبيرِ الزمني ─────────────────────────

    /** خمسةُ حقول: دقيقة ساعة يوم شهر أسبوع — بدعمِ * و*​/n والقوائم. */
    public static function validExpr($expr)
    {
        $p = preg_split('/\s+/', trim((string) $expr));
        if (count($p) !== 5) { return false; }
        foreach ($p as $f) {
            if (!preg_match('#^(\*|\*/\d+|\d+(-\d+)?(,\d+(-\d+)?)*)$#', $f)) { return false; }
        }
        return true;
    }

    public static function isDue($expr, \DateTimeInterface $now)
    {
        if (!self::validExpr($expr)) { return false; }
        list($mi, $ho, $dm, $mo, $dw) = preg_split('/\s+/', trim((string) $expr));
        return self::fieldMatches($mi, (int) $now->format('i'))
            && self::fieldMatches($ho, (int) $now->format('G'))
            && self::fieldMatches($dm, (int) $now->format('j'))
            && self::fieldMatches($mo, (int) $now->format('n'))
            && self::fieldMatches($dw, (int) $now->format('w'));
    }

    private static function fieldMatches($field, $value)
    {
        if ($field === '*') { return true; }
        if (strpos($field, '*/') === 0) {
            $step = (int) substr($field, 2);
            return $step > 0 && ($value % $step) === 0;
        }
        foreach (explode(',', $field) as $part) {
            if (strpos($part, '-') !== false) {
                list($a, $b) = explode('-', $part, 2);
                if ($value >= (int) $a && $value <= (int) $b) { return true; }
            } elseif ((int) $part === $value) { return true; }
        }
        return false;
    }

    /** ساعةُ القاعدةِ لا ساعةُ PHP — «مهلُ الكنسِ بساعةِ القاعدة». */
    public static function dbNow(\mysqli $conn)
    {
        $r = $conn->query('SELECT NOW()');
        return $r ? (string) $r->fetch_row()[0] : date('Y-m-d H:i:s');
    }

    private static function humanSeconds($s)
    {
        if ($s < 3600) { return intdiv($s, 60) . ' دقيقة'; }
        if ($s < 86400) { return intdiv($s, 3600) . ' ساعة'; }
        return intdiv($s, 86400) . ' يوما';
    }
}
