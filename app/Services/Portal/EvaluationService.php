<?php
/**
 * app/Services/Portal/EvaluationService.php — التقييمُ الثنائي وشهادتُه (H-18)
 * ═══════════════════════════════════════════════════════════════════════════
 * USR-01 §7: ذاتيٌّ ← مديرٌ (**لا يُفتح قبل إقفال الذاتي — منعًا للتأثير**) ←
 * مناقشةٌ ← اعتماد · «فارقٌ ≥ درجتين يوجب تعليقًا (422 بدونه)» · والشهادةُ
 * «تُولَّد من الأرقام المقاسة؛ ولا تُصدَر لفترةٍ لم تُقفل أو لتقييمٍ لم
 * يُعتمد — ولا تُصدَر مرتين».
 *
 * الانتقالاتُ **بفحص النسخة** (version): نسخةٌ متقادمةٌ ⇒ 409 — لا كتابةَ
 * فوق كتابةٍ لم تُقرأ.
 */

namespace App\Services\Portal;

require_once __DIR__ . '/../../../includes/catch_log.php';

class EvaluationService
{
    // ═════════════════════════════════════════════════════════════════════
    // ① الذاتي
    // ═════════════════════════════════════════════════════════════════════

    /** فتحُ/حفظُ التقييم الذاتي لفترة — ينشئ السطرَ إن لم يوجد. */
    public static function selfSave($conn, $gate, $companyId, $capacityId, $from, $to, array $scores, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'eval_id' => null);
        $ev = self::find($gate, $capacityId, $from, $to);
        if ($ev && !in_array((string) $ev['state'], array('SelfDraft'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'الذاتي أقفل (' . $ev['state'] . ') — لا تعديل بعد الإقفال';
            return $out;
        }
        try {
            if ($ev) {
                $gate->update('evaluations', array(
                    'self_scores_json' => json_encode($scores, JSON_UNESCAPED_UNICODE),
                    'version' => (int) $ev['version'] + 1,
                ), array('id' => (int) $ev['id']));
                $out['eval_id'] = (int) $ev['id'];
            } else {
                $out['eval_id'] = (int) $gate->insert('evaluations', array(
                    'company_id' => (int) $companyId, 'capacity_id' => (int) $capacityId,
                    'period_from' => (string) $from, 'period_to' => (string) $to,
                    'self_scores_json' => json_encode($scores, JSON_UNESCAPED_UNICODE),
                    'state' => 'SelfDraft',
                ));
            }
        } catch (\Throwable $t) { $out['code'] = 422; $out['reason'] = $t->getMessage(); return $out; }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** إقفالُ الذاتي — SelfDraft ⇒ SelfClosed بفحص النسخة. */
    public static function selfClose($conn, $gate, $companyId, $evalId, $expectedVersion, $actor)
    {
        return self::transition($gate, $evalId, $expectedVersion, 'SelfDraft', array(
            'state' => 'SelfClosed', 'self_closed_at' => date('Y-m-d H:i:s')));
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② المدير — لا يُفتح قبل إقفال الذاتي (U6)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تقييمُ المدير — «يرى تقييمَ الموظف **بعد** إغلاقه لا قبله» ·
     * «فارقٌ ≥ درجتين يوجب تعليقًا».
     */
    public static function mgrSubmit($conn, $gate, $companyId, $evalId, $expectedVersion,
                                     array $scores, $comment, $mgrAccount)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $ev = self::head($gate, $evalId);
        if (!$ev) { $out['code'] = 404; $out['reason'] = 'التقييم غير موجود'; return $out; }

        // U6: المديرُ يفتح قبل إقفال الموظف ⇒ 423
        if (!in_array((string) $ev['state'], array('SelfClosed', 'MgrDraft'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'تقييم المدير **لا يفتح قبل إقفال الذاتي** (حاله: '
                           . $ev['state'] . ') — منعا للتأثير (U6)';
            return $out;
        }
        if ((int) $ev['version'] !== (int) $expectedVersion) {
            $out['code'] = 409; $out['reason'] = 'نسخة متقادمة — أعد القراءة'; return $out;
        }

        // «الفارقُ الكبيرُ يوجب تعليقًا» — يُقاس على المحاور المشتركة
        $self = json_decode((string) $ev['self_scores_json'], true) ?: array();
        $maxGap = 0.0;
        foreach ($scores as $axis => $score) {
            if (isset($self[$axis])) {
                $gap = abs((float) $score - (float) $self[$axis]);
                if ($gap > $maxGap) { $maxGap = $gap; }
            }
        }
        if ($maxGap >= 2 && trim((string) $comment) === '') {
            $out['code'] = 422;
            $out['reason'] = 'فارق ' . $maxGap . ' ≥ درجتين **يوجب تعليقا** (422 بدونه) — USR-01 §7-②';
            return $out;
        }

        try {
            $gate->update('evaluations', array(
                'mgr_scores_json' => json_encode($scores, JSON_UNESCAPED_UNICODE),
                'mgr_by' => (int) $mgrAccount,
                'mgr_comment' => trim((string) $comment) !== '' ? mb_substr(trim((string) $comment), 0, 500) : null,
                'state' => 'MgrDraft',
                'version' => (int) $ev['version'] + 1,
            ), array('id' => (int) $evalId));
        } catch (\Throwable $t) { $out['code'] = 422; $out['reason'] = $t->getMessage(); return $out; }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ المناقشة والاعتماد
    // ═════════════════════════════════════════════════════════════════════

    public static function discuss($conn, $gate, $companyId, $evalId, $expectedVersion, $notes, $actor)
    {
        if (trim((string) $notes) === '') {
            return array('ok' => false, 'code' => 422,
                'reason' => 'المناقشة جلسة تسجل — نقاط الاتفاق والاختلاف إلزامية');
        }
        return self::transition($gate, $evalId, $expectedVersion, 'MgrDraft', array(
            'state' => 'Discussed', 'discussion_notes' => (string) $notes));
    }

    /** الاعتمادُ — بدرجةٍ نهائيةٍ ومعتمِدٍ **غيرِ صاحب الصفة** (لا اعتمادَ للذات). */
    public static function approve($conn, $gate, $companyId, $evalId, $expectedVersion, $finalScore, $approver)
    {
        $ev = self::head($gate, $evalId);
        if (!$ev) { return array('ok' => false, 'code' => 404, 'reason' => 'غير موجود'); }
        // لا اعتمادَ للذات — صاحبُ الصفة لا يعتمد تقييمَه
        $cap = null;
        try { $cap = $gate->selectOne('user_capacities', array('where' => array('id' => (int) $ev['capacity_id']))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $cap'); $cap = null; }
        if ($cap && (int) $cap['account_id'] === (int) $approver) {
            return array('ok' => false, 'code' => 403,
                'reason' => '**لا اعتماد للذات** — صاحب الصفة لا يعتمد تقييمه (USR-01 §8-④)');
        }
        $score = round((float) $finalScore, 2);
        if ($score <= 0) {
            return array('ok' => false, 'code' => 422, 'reason' => 'الدرجة النهائية بأوزان المحاور — لا صفرا');
        }
        return self::transition($gate, $evalId, $expectedVersion, 'Discussed', array(
            'state' => 'Approved', 'final_score' => $score,
            'approved_by' => (int) $approver, 'approved_at' => date('Y-m-d H:i:s')));
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ الشهادة — من الأرقام المقاسة ولا تُصدَر مرتين (U7)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إصدارُ شهادةِ إنجاز: **لقطةٌ محسوبةٌ** لفترةٍ + **تقييمٌ معتمدٌ** لها —
     * وإلا 422. والفريدُ (اللقطة) يمنع الإصدارَ مرتين.
     */
    public static function issueCertificate($conn, $gate, $companyId, $snapId, $evalId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'serial_no' => null, 'verify_code' => null);
        $snap = null;
        try { $snap = $gate->selectOne('achievement_snapshots', array('where' => array('id' => (int) $snapId))); }
        catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $snap'); $snap = null; }
        if (!$snap) {
            $out['code'] = 422;
            $out['reason'] = '**لا شهادة لفترة لم تقفل بلقطة** — الشهادة من الأرقام المقاسة لا من كتابة حرة (U7)';
            return $out;
        }
        $ev = self::head($gate, (int) $evalId);
        if (!$ev || (string) $ev['state'] !== 'Approved') {
            $out['code'] = 422;
            $out['reason'] = '**لا شهادة لتقييم لم يعتمد** (حاله: ' . ($ev ? $ev['state'] : 'غير موجود') . ') — U7';
            return $out;
        }
        if ((int) $ev['capacity_id'] !== (int) $snap['capacity_id']
            || (string) $ev['period_from'] !== (string) $snap['period_from']
            || (string) $ev['period_to'] !== (string) $snap['period_to']) {
            $out['code'] = 422;
            $out['reason'] = 'اللقطة والتقييم لفترتين أو صفتين مختلفتين — لا تلفيق شهادة من مصدرين';
            return $out;
        }

        $year = (int) date('Y');
        $serial = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('c' => 'achievement_certificates')),
                "SELECT COUNT(*) n FROM achievement_certificates c WHERE {TENANT_SCOPE}");
            $seq = $rows ? ((int) $rows[0]['n'] + 1) : 1;
            $serial = 'CERT-' . $year . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $verify = strtoupper(substr(sha1($serial . '|' . $snap['source_fingerprint'] . '|' . microtime(true)), 0, 10));
            $certId = (int) $gate->insert('achievement_certificates', array(
                'company_id' => (int) $companyId, 'eval_id' => (int) $evalId,
                'snap_id' => (int) $snapId, 'serial_no' => $serial,
                'verify_code' => $verify, 'issued_by' => (int) $actor,
            ));
            $out['ok'] = true; $out['code'] = 200;
            $out['serial_no'] = $serial; $out['verify_code'] = $verify;
            $out['cert_id'] = $certId;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'إصدار شهادة تقييم فشل — التقييم محفوظ والشهادة تعاد بطلب جديد');
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409;
                $out['reason'] = '**لا تصدر الشهادة مرتين بالمفتاح نفسه** — للقطة شهادتها الصادرة (U7)';
            } else { $out['code'] = 422; $out['reason'] = $t->getMessage(); }
        }
        return $out;
    }

    /** التحققُ من شهادةٍ برمزها — «يمكن التحقق منه لاحقًا». */
    public static function verify($gate, $verifyCode)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('c' => 'achievement_certificates'),
                      'enrich' => array('s' => 'achievement_snapshots')),
                "SELECT c.*, s.period_from, s.period_to, s.metrics_json
                   FROM achievement_certificates c
                   LEFT JOIN achievement_snapshots s ON s.id = c.snap_id
                  WHERE {TENANT_SCOPE} AND c.verify_code = ? LIMIT 1",
                array((string) $verifyCode));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════
    // مرافق
    // ═════════════════════════════════════════════════════════════════════

    public static function find($gate, $capacityId, $from, $to)
    {
        try {
            return $gate->selectOne('evaluations', array('where' => array(
                'capacity_id' => (int) $capacityId,
                'period_from' => (string) $from, 'period_to' => (string) $to)));
        } catch (\Throwable $t) { return null; }
    }

    public static function head($gate, $evalId)
    {
        try { return $gate->selectOne('evaluations', array('where' => array('id' => (int) $evalId))); }
        catch (\Throwable $t) { return null; }
    }

    /** انتقالٌ بفحص الحالة **والنسخة** — نسخةٌ متقادمة 409 وحالٌ غيرُ متوقعٍ 423. */
    private static function transition($gate, $evalId, $expectedVersion, $fromState, array $set)
    {
        $ev = self::head($gate, (int) $evalId);
        if (!$ev) { return array('ok' => false, 'code' => 404, 'reason' => 'غير موجود'); }
        if ((string) $ev['state'] !== $fromState) {
            return array('ok' => false, 'code' => 423,
                'reason' => 'الانتقال من «' . $fromState . '» والحال «' . $ev['state'] . '»');
        }
        if ((int) $ev['version'] !== (int) $expectedVersion) {
            return array('ok' => false, 'code' => 409, 'reason' => 'نسخة متقادمة — أعد القراءة ثم أعد الفعل');
        }
        $set['version'] = (int) $ev['version'] + 1;
        try { $gate->update('evaluations', $set, array('id' => (int) $evalId)); }
        catch (\Throwable $t) { return array('ok' => false, 'code' => 422, 'reason' => $t->getMessage()); }
        return array('ok' => true, 'code' => 200, 'reason' => '', 'version' => $set['version']);
    }
}
