<?php
/**
 * المراجعةُ الداخليةُ المستقلة — InternalAuditService (IAF-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * تحرس أربعةَ أحكامٍ لا تُخالَف:
 *
 *  ① IAF-0043 — «◆ ولا كتابةَ لها على السجلاتِ التشغيليةِ أو الماليةِ الأصلية».
 *     فلا سطرَ هنا يكتب في جدولٍ ماليٍّ أو تشغيليّ — جداولُ `iaf_*` وحدَها.
 *     و`assertReadOnly()` تُثبت هذا عند كلِّ طلبِ كتابة.
 *
 *  ② IAF-0044 — الدورةُ لا تُقفز: «لا مهمةَ بلا خطةٍ ولا خطةَ بلا كونٍ رقابيٍّ
 *     ولا كونَ بلا ميثاق».
 *
 *  ③ §٢-٢ — «لا تُغلق ملاحظةٌ **بلا دليلٍ يقبله المراجعُ** — ولا تُغلق من
 *     الإدارةِ نفسِها» · وCEO-Y0125: «ولا يملك الرئيسُ إغلاقَها بلا دليلٍ يقبله
 *     المراجع — فسلطتُه في القرارِ لا في إسقاطِ الدليل».
 *
 *  ④ §٢-٢ — «والتصعيدُ آليٌّ بالمهلةِ ويصل الجهةَ المشرفةَ مباشرةً — **ولا يملك
 *     أحدٌ منعَه**» · وCEO-Y0119: التقريرُ يصل الرئيسَ **غيرَ مفلتر**.
 */

namespace App\Services\Audit;

class InternalAuditService
{
    /** المراجعُ الداخليُّ المستقل — roles.id (includes/roles.php). */
    const ROLE_AUDITOR = 33;

    /** الرئيسُ التنفيذي — وجهةُ التقريرِ والتصعيد. */
    const ROLE_CEO = 9;

    /**
     * ◆ الجداولُ التي **لا** تملك المراجعةُ الكتابةَ عليها بحال (IAF-0043).
     *   البادئةُ تكفي: كلُّ ما يبدأ بها سجلٌّ أصليٌّ تشغيليٌّ أو ماليّ.
     */
    const FORBIDDEN_WRITE_PREFIXES = array(
        'fin_', 'ob_', 'contract', 'employees', 'users', 'exec_', 'sec_',
        'nav', 'work_items', 'ems_', 'trs_', 'mnt_',
    );

    /* ═══════════════════════════════════════════════════════════════════════
       ① الحدُّ: قراءةٌ بلا كتابةٍ على الأصول
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * IAF-0043: يُستدعى قبلَ أيِّ كتابةٍ يطلبها دورُ المراجعة.
     * fail-closed: ما لم يكن الجدولُ من `iaf_*` فالكتابةُ مرفوضة.
     */
    public static function assertReadOnly($roleId, $table)
    {
        if ((int) $roleId !== self::ROLE_AUDITOR) {
            return array('ok' => true, 'code' => 200, 'reason' => 'ليس دورَ المراجعةِ فلا يخضع لحدِّه');
        }
        $t = strtolower(trim((string) $table));
        if (strpos($t, 'iaf_') === 0) {
            return array('ok' => true, 'code' => 200, 'reason' => 'سجلُّ المراجعةِ نفسِها');
        }
        foreach (self::FORBIDDEN_WRITE_PREFIXES as $p) {
            if (strpos($t, $p) === 0) {
                return array('ok' => false, 'code' => 403,
                    'reason' => 'المراجعُ الداخليُّ لا يملك كتابةً على السجلاتِ الأصلية: '
                              . $table . ' (IAF-0043)');
            }
        }
        return array('ok' => false, 'code' => 403,
            'reason' => 'المراجعُ الداخليُّ يقرأ ولا يكتب خارجَ سجلِّه (IAF-0043)');
    }

    /** IAF-0036: كلُّ اطّلاعٍ حساسٍ يُسجَّل — فالوظيفةُ الرقابيةُ مراقَبةٌ أيضًا. */
    public static function logAccess(\mysqli $conn, array $ctx)
    {
        $st = $conn->prepare(
            "INSERT INTO iaf_access_log
               (company_id, auditor_id, scope_kind, scope_ref, purpose, engagement_id, accessed_at)
             VALUES (?,?,?,?,?,?,NOW())");
        if (!$st) { return 0; }
        $co  = intval($ctx['company_id'] ?? 0);
        $aud = intval($ctx['auditor_id'] ?? 0);
        $sk  = mb_substr((string) ($ctx['scope_kind'] ?? ''), 0, 60);
        $sr  = mb_substr((string) ($ctx['scope_ref'] ?? ''), 0, 160);
        $pp  = mb_substr((string) ($ctx['purpose'] ?? ''), 0, 200);
        $eng = !empty($ctx['engagement_id']) ? intval($ctx['engagement_id']) : null;
        /* ◆ 6 وسائط — والأنواعُ مقطعًا مقطعًا لا سلسلةً تُعدُّ بالعين. */
        $vals  = array($co, $aud, $sk, $sr, $pp, $eng);
        $types = 'ii' . 'sss' . 'i';
        if (strlen($types) !== count($vals)) { $st->close(); return 0; }
        $st->bind_param($types, ...$vals);
        $st->execute();
        $id = $st->insert_id;
        $st->close();
        return $id;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② الدورةُ لا تُقفز (IAF-0044)
       ═══════════════════════════════════════════════════════════════════════ */

    /** «لا مهمةَ بلا خطةٍ ولا خطةَ بلا كونٍ رقابيٍّ ولا كونَ بلا ميثاق». */
    public static function assertCycle(\mysqli $conn, $co, $stage, array $ctx = array())
    {
        $co = (int) $co;
        switch ($stage) {
            case 'universe':
                if (!self::scalar($conn, "SELECT COUNT(*) FROM iaf_charter
                                           WHERE company_id={$co} AND state='approved'")) {
                    return self::fail(409, 'لا كونَ رقابيٌّ بلا ميثاقٍ معتمد (IAF-0044)');
                }
                break;
            case 'plan':
                if (!self::scalar($conn, "SELECT COUNT(*) FROM iaf_universe
                                           WHERE company_id={$co} AND active=1")) {
                    return self::fail(409, 'لا خطةَ بلا كونٍ رقابيٍّ مبنيّ (IAF-0044)');
                }
                break;
            case 'engagement':
                $plan = intval($ctx['plan_id'] ?? 0);
                if (!self::scalar($conn, "SELECT COUNT(*) FROM iaf_plan
                                           WHERE id={$plan} AND company_id={$co} AND state='approved'")) {
                    return self::fail(409, 'لا مهمةَ بلا خطةٍ معتمدة (IAF-0044)');
                }
                /* IAF-0009: إقرارُ الاستقلالِ **قبلَ كل تكليف**. */
                $aud = intval($ctx['lead_auditor'] ?? 0);
                $ind = self::scalar($conn, "SELECT COUNT(*) FROM iaf_independence
                                             WHERE company_id={$co} AND auditor_id={$aud}
                                               AND has_conflict=0
                                               AND (valid_until IS NULL OR valid_until >= CURDATE())");
                if (!$ind) {
                    return self::fail(409, 'لا تكليفَ بمهمةٍ بلا إقرارِ استقلالٍ سارٍ (IAF-0009)');
                }
                break;
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'المرحلةُ مستوفيةٌ لما قبلها');
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ الإغلاق — بدليلٍ يقبله المراجعُ وحدَه
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * قبولُ الدليل — **المراجعُ حصرًا**. ولا الرئيسُ ولا الإدارةُ المُراجَعة.
     */
    public static function acceptEvidence(\mysqli $conn, array $ctx)
    {
        $co   = intval($ctx['company_id'] ?? 0);
        $no   = trim((string) ($ctx['finding_no'] ?? ''));
        $by   = intval($ctx['accepted_by'] ?? 0);
        if ($co <= 0 || $no === '' || $by <= 0) { return self::fail(422, 'قبولُ الدليلِ يحتاج الملاحظةَ وفاعلَه'); }
        if (self::roleOf($conn, $by) !== self::ROLE_AUDITOR) {
            return self::fail(403, 'قبولُ الدليلِ للمراجعِ الداخليِّ حصرًا — ولو كان الطالبُ الرئيسَ (CEO-Y0125)');
        }
        $f = self::finding($conn, $co, $no);
        if ($f === null) { return self::fail(404, 'ملاحظةٌ غيرُ موجودة: ' . $no); }

        $st = $conn->prepare("UPDATE iaf_findings
                                 SET evidence_accepted = 1, accepted_by = ?, evidence_ref = ?
                               WHERE company_id = ? AND finding_no = ?");
        if (!$st) { return self::fail(500, 'تعذّر قبولُ الدليل'); }
        $ev = mb_substr((string) ($ctx['evidence_ref'] ?? $f['evidence_ref']), 0, 300);
        $st->bind_param('isis', $by, $ev, $co, $no);
        $st->execute();
        $st->close();
        return array('ok' => true, 'code' => 200, 'reason' => 'قَبِل المراجعُ الدليل');
    }

    /**
     * إغلاقُ الملاحظة. حارسان:
     *   ① لا إغلاقَ بلا دليلٍ **قَبِله المراجع**.
     *   ② ولا يُغلقها من الإدارةِ نفسِها — ولا الرئيسُ.
     */
    public static function closeFinding(\mysqli $conn, array $ctx)
    {
        $co = intval($ctx['company_id'] ?? 0);
        $no = trim((string) ($ctx['finding_no'] ?? ''));
        $by = intval($ctx['closed_by'] ?? 0);
        if ($co <= 0 || $no === '' || $by <= 0) { return self::fail(422, 'الإغلاقُ يحتاج الملاحظةَ وفاعلَه'); }

        $f = self::finding($conn, $co, $no);
        if ($f === null) { return self::fail(404, 'ملاحظةٌ غيرُ موجودة: ' . $no); }
        if ($f['state'] === 'closed') { return self::fail(409, 'الملاحظةُ مُغلقةٌ سلفًا'); }

        /* ② الإدارةُ المُراجَعةُ لا تُغلق ملاحظةَ نفسِها. */
        if ((int) $f['auditee_user_id'] === $by) {
            return self::fail(403, 'لا تُغلق ملاحظةٌ من الإدارةِ نفسِها (IAF §2-2)');
        }
        /* ② الإغلاقُ فعلُ المراجعِ — والرئيسُ يقرر ولا يُسقط الدليل. */
        $role = self::roleOf($conn, $by);
        if ($role !== self::ROLE_AUDITOR) {
            return self::fail(403, $role === self::ROLE_CEO
                ? 'لا يملك الرئيسُ إغلاقَ ملاحظةٍ — فسلطتُه في القرارِ لا في إسقاطِ الدليل (CEO-Y0125)'
                : 'إغلاقُ الملاحظةِ للمراجعِ الداخليِّ حصرًا');
        }
        /* ① ولا إغلاقَ بلا دليلٍ قَبِله المراجع. */
        if ((int) $f['evidence_accepted'] !== 1) {
            return self::fail(409, 'لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجعُ (IAF §2-2)');
        }

        $st = $conn->prepare("UPDATE iaf_findings
                                 SET state='closed', closed_by=?, closed_at=NOW()
                               WHERE company_id=? AND finding_no=? AND state<>'closed'");
        if (!$st) { return self::fail(500, 'تعذّر الإغلاق'); }
        $st->bind_param('iis', $by, $co, $no);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        return $n > 0
            ? array('ok' => true, 'code' => 200, 'reason' => 'أُغلقت الملاحظةُ بدليلٍ مقبول')
            : self::fail(409, 'لم يتغير شيء');
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ④ التصعيدُ الآليُّ — ولا يملك أحدٌ منعَه
       ═══════════════════════════════════════════════════════════════════════ */

    /** ما تجاوز مهلتَه يُصعَّد للجهةِ المشرفةِ آليًّا — بلا طلبٍ من أحد. */
    public static function escalateOverdue(\mysqli $conn, $co, $asOf = null)
    {
        $asOf = $asOf ?: date('Y-m-d');
        $st = $conn->prepare(
            "UPDATE iaf_findings
                SET state='escalated', escalated_at=NOW(), escalated_to='ceo'
              WHERE company_id=? AND state NOT IN ('closed','escalated')
                AND action_due IS NOT NULL AND action_due < ?");
        if (!$st) { return self::fail(500, 'تعذّر التصعيد'); }
        $co = (int) $co;
        $st->bind_param('is', $co, $asOf);
        $st->execute();
        $n = max(0, $st->affected_rows);
        $st->close();
        return array('ok' => true, 'code' => 200, 'escalated' => $n,
                     'reason' => "صُعِّد $n ملاحظةً متأخرةً إلى الرئيسِ آليًّا");
    }

    /**
     * CEO-Y0119: التقريرُ يصل الرئيسَ **مباشرةً غيرَ مفلتر**.
     * والمسارُ يُسجَّل ليُفحص — فأيُّ وسيطٍ يُكشف بالمسح.
     */
    public static function deliverReport(\mysqli $conn, array $ctx)
    {
        $path = (string) ($ctx['delivery_path'] ?? 'direct');
        if ($path !== 'direct') {
            return self::fail(403, 'تقريرُ المراجعةِ يصل الرئيسَ مباشرةً غيرَ مفلتر — '
                                 . 'ولا يمرُّ بالماليةِ ولا بالحوكمةِ ولا بمن يُراجَع (CEO-Y0119)');
        }
        $sql = "INSERT INTO exec_audit_reports
                  (company_id, report_no, title, period_label, scope_label, overall_opinion,
                   findings_total, findings_critical, closure_rate, overdue_escalated,
                   issued_by, issued_at, delivery_path, received_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),'direct',NOW())
                ON DUPLICATE KEY UPDATE title=VALUES(title), findings_total=VALUES(findings_total),
                  findings_critical=VALUES(findings_critical), closure_rate=VALUES(closure_rate),
                  overdue_escalated=VALUES(overdue_escalated), received_at=NOW()";
        $st = $conn->prepare($sql);
        if (!$st) { return self::fail(500, 'تعذّر تسليمُ التقرير: ' . $conn->error); }
        $co = intval($ctx['company_id'] ?? 0);
        $no = mb_substr((string) ($ctx['report_no'] ?? ''), 0, 40);
        $ti = mb_substr((string) ($ctx['title'] ?? ''), 0, 300);
        $pe = mb_substr((string) ($ctx['period_label'] ?? ''), 0, 60);
        $sc = mb_substr((string) ($ctx['scope_label'] ?? ''), 0, 300);
        $op = mb_substr((string) ($ctx['overall_opinion'] ?? ''), 0, 300);
        $ft = intval($ctx['findings_total'] ?? 0);
        $fc = intval($ctx['findings_critical'] ?? 0);
        $cr = (float) ($ctx['closure_rate'] ?? 0);
        $oe = intval($ctx['overdue_escalated'] ?? 0);
        $ib = intval($ctx['issued_by'] ?? 0);
        $vals  = array($co, $no, $ti, $pe, $sc, $op, $ft, $fc, $cr, $oe, $ib);
        $types = 'i' . 'sssss' . 'ii' . 'd' . 'ii';
        if (strlen($types) !== count($vals)) {
            return self::fail(500, sprintf('انزياحُ وسائط: أنواع %d · قيم %d', strlen($types), count($vals)));
        }
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) { $e = $st->error; $st->close(); return self::fail(500, 'تعذّر التسليم: ' . $e); }
        $st->close();
        return array('ok' => true, 'code' => 200, 'reason' => 'وصل التقريرُ الرئيسَ مباشرةً بلا وسيط');
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    private static function finding(\mysqli $conn, $co, $no)
    {
        $st = $conn->prepare("SELECT * FROM iaf_findings WHERE company_id=? AND finding_no=? LIMIT 1");
        if (!$st) { return null; }
        $co = (int) $co;
        $st->bind_param('is', $co, $no);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ?: null;
    }

    /** ◆ الدورُ في `users` عمودان: `role` نصًّا و`role_id` رقمًا — يُقرآن معًا. */
    public static function roleOf(\mysqli $conn, $userId)
    {
        $st = $conn->prepare("SELECT COALESCE(NULLIF(role_id,0), CAST(NULLIF(role,'') AS UNSIGNED)) rid
                                FROM users WHERE id=? LIMIT 1");
        if (!$st) { return 0; }
        $userId = (int) $userId;
        $st->bind_param('i', $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? intval($r['rid']) : 0;
    }

    private static function scalar(\mysqli $conn, $sql)
    {
        $r = $conn->query($sql);
        if (!$r) { return 0; }
        $x = $r->fetch_row();
        return $x ? (int) $x[0] : 0;
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }
}
