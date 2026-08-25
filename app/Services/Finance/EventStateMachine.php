<?php
/**
 * آلةُ حالات الحدث المالي — FES-01 §7.2/§7.3 (H-12 · 2026-07-30)
 * ───────────────────────────────────────────────────────────────────────────
 * أربعَ عشرةَ حالةً بقائمة سماحٍ لا منع (نمطُ ContractStateMachine): ما ليس في
 * القائمة مرفوضٌ باسمه. وكلُّ انتقالٍ:
 *   ① يفحص `event_version` ويرفعها — **قفلٌ تفاؤلي**: اعتمادان متزامنان،
 *     الأولُ يمضي والثاني يُرفض 409 برسالةٍ واضحة (FES §7.3).
 *   ② يختم فاعلَه: Approved → approved_by/at · Posted → posted_by/at.
 *   ③ يرآة الحالةَ القديمة `state` حيث لها مقابلٌ — فالشاشاتُ القائمة تقرأ
 *     `state` ولا تنكسر («الاسمُ في موضعين»)، وReversed ترآة `event_status`.
 *
 * التحديثُ عبر بوابة العزل: الأعمدةُ الستةُ ضمن `immutable_allow` في
 * TenantRegistry (قرارٌ موثَّقٌ هناك) — فالمضمونُ محصَّنٌ والدورةُ تدور.
 *
 * الفحصُ على المُرجَع لا الاستثناء حيث SQL مباشر (گوتشا mysqli الصامتة).
 */

namespace App\Services\Finance;

class EventStateMachine
{
    /** حالات FES الأربعَ عشرة (§4.1) */
    const STATES = array(
        'Draft', 'Published', 'ValidationFailed', 'UnderReview', 'ReturnedToSource',
        'Rejected', 'Approved', 'PostingFailed', 'RetryPending', 'Posted',
        'Reversed', 'Superseded', 'CancelledBeforePosting', 'Closed',
    );

    /**
     * قائمةُ السماح (§7.2 حرفيًّا) — من الحالة إلى ما يجوز.
     * Superseded تجوز من أي حالةٍ قبل Posted (سطرُ §7.2 الأخير قبل العكس).
     */
    const ALLOWED = array(
        'Draft'            => array('Published', 'ValidationFailed', 'Superseded'),
        'ValidationFailed' => array('Published', 'Superseded'),
        'Published'        => array('UnderReview', 'CancelledBeforePosting', 'Superseded'),
        'UnderReview'      => array('ReturnedToSource', 'Rejected', 'Approved',
                                    'CancelledBeforePosting', 'Superseded'),
        'ReturnedToSource' => array('Published', 'Superseded'),
        'Approved'         => array('Posted', 'PostingFailed', 'Superseded'),
        'PostingFailed'    => array('RetryPending', 'Superseded'),
        'RetryPending'     => array('Posted', 'PostingFailed', 'Superseded'),
        'Posted'           => array('Reversed', 'Closed'),
        // نهائية: لا انتقالَ منها إلا بمستندٍ/حدثٍ جديد (§7.2)
        'Rejected'         => array(),
        'Reversed'         => array(),
        'Superseded'       => array(),
        'CancelledBeforePosting' => array(),
        'Closed'           => array(),
    );

    /** مرآةُ FES → الحالة القديمة (حيث لها مقابلٌ يقرؤه القائم) */
    const LEGACY_MIRROR = array(
        'Draft'       => 'draft',
        'UnderReview' => 'fin_review',
        'Approved'    => 'approved',
        'Posted'      => 'posted',
        'Rejected'    => 'rejected',
        'Closed'      => 'closed',
    );

    /** مرآةُ الحالة القديمة → FES (لمزامنة مسارات الشاشات القائمة) */
    const FROM_LEGACY = array(
        'draft'         => 'Draft',
        'dept_review'   => 'UnderReview',
        'dept_approved' => 'UnderReview',
        'fin_review'    => 'UnderReview',
        'audited'       => 'UnderReview',
        'approved'      => 'Approved',
        'posted'        => 'Posted',
        'settled'       => 'Posted',
        'rejected'      => 'Rejected',
        'closed'        => 'Closed',
    );

    /** هل الانتقالُ مسموح؟ */
    public static function canTransition($from, $to)
    {
        return isset(self::ALLOWED[$from]) && in_array($to, self::ALLOWED[$from], true);
    }

    /**
     * انتقالٌ واحدٌ بقفلٍ تفاؤلي.
     *
     * @param  \App\Core\TenantDb $gate بوابةُ العزل (تحمل الشركة)
     * @param  \mysqli $conn للقراءة المسبقة
     * @param  int $eventId
     * @param  string $to الحالةُ الهدف (من STATES حصرًا)
     * @param  int $actor فاعلُ الانتقال
     * @param  int|null $expectedVersion النسخةُ المتوقعة — null = تُقرأ الآن
     * @return array{ok:bool, code:int, from:string, to:string, version:int, reasons:array}
     */
    public static function transition($gate, \mysqli $conn, $eventId, $to, $actor, $expectedVersion = null)
    {
        $eventId = intval($eventId);
        if (!in_array($to, self::STATES, true)) {
            return array('ok' => false, 'code' => 422, 'from' => '', 'to' => $to, 'version' => 0,
                'reasons' => array('حالة خارج قائمة FES الأربع عشرة: ' . $to));
        }
        $row = $gate->selectOne('fin_financial_events', array(
            'columns' => array('id', 'fes_status', 'event_version'),
            'where' => array('id' => $eventId)));
        if (!$row) {
            return array('ok' => false, 'code' => 404, 'from' => '', 'to' => $to, 'version' => 0,
                'reasons' => array('الحدث غير موجود'));
        }
        $from = (string) $row['fes_status'];
        $ver  = ($expectedVersion === null) ? intval($row['event_version']) : intval($expectedVersion);

        if (!self::canTransition($from, $to)) {
            return array('ok' => false, 'code' => 409, 'from' => $from, 'to' => $to, 'version' => $ver,
                'reasons' => array("الانتقال {$from} → {$to} خارج قائمة السماح (FES §7.2)"));
        }

        $fields = array(
            'fes_status'    => $to,
            'event_version' => $ver + 1,
        );
        if ($to === 'Approved') {
            $fields['approved_by'] = intval($actor);
            $fields['approved_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'Posted') {
            $fields['posted_by'] = intval($actor);
            $fields['posted_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'Reversed') {
            $fields['event_status'] = 'reversed'; // المرآةُ القديمة للعكس
        }
        if (isset(self::LEGACY_MIRROR[$to])) {
            $fields['state'] = self::LEGACY_MIRROR[$to]; // «الاسمُ في موضعين»
        }

        // القفلُ التفاؤلي: الشرطُ على النسخة والحالة معًا — الثاني المتزامن يجد
        // النسخةَ ارتفعت فيُرفض بـ409 (FES §7.3: الأولُ يمضي والثاني Conflict).
        $affected = $gate->update('fin_financial_events', $fields,
            array('id' => $eventId), 'event_version = ? AND fes_status = ?', array($ver, $from));

        if (intval($affected) < 1) {
            return array('ok' => false, 'code' => 409, 'from' => $from, 'to' => $to, 'version' => $ver,
                'reasons' => array('تعارض تزامن: نسخة الحدث تغيرت تحت يدك — أعد القراءة ثم أعد المحاولة'));
        }
        return array('ok' => true, 'code' => 200, 'from' => $from, 'to' => $to,
            'version' => $ver + 1, 'reasons' => array());
    }

    /**
     * مزامنةٌ من مسار الشاشات القائم: الحالةُ القديمة تقدّمت (advance/reject/post)
     * فتُساق fes_status إلى مقابلها **عبر وسائط السماح** (Draft→Published→UnderReview
     * دفعةً حين يعتمد المسارُ القديم مباشرة) — كلُّ خطوةٍ انتقالٌ مقفولٌ نسخةً.
     *
     * @return array آخرُ نتيجةِ انتقال (أو ok=true بلا خطوات إن كانت متزامنةً سلفًا)
     */
    public static function syncFromLegacy($gate, \mysqli $conn, $eventId, $legacyState, $actor)
    {
        if (!isset(self::FROM_LEGACY[$legacyState])) {
            return array('ok' => false, 'code' => 422, 'from' => '', 'to' => '', 'version' => 0,
                'reasons' => array('حالة قديمة لا مقابل لها: ' . $legacyState));
        }
        return self::syncTo($gate, $conn, $eventId, self::FROM_LEGACY[$legacyState], $actor);
    }

    /**
     * سَوقُ fes_status إلى هدفٍ عبر وسائط السماح — كلُّ خطوةٍ انتقالٌ مقفولٌ نسخةً.
     * (المسارُ القديم يقفز مراحلَ FES — كاعتمادِ مسودةٍ مباشرةً — فتُقطع الوسائطُ
     * خطوةً خطوةً بدل كسر قائمة السماح.)
     */
    public static function syncTo($gate, \mysqli $conn, $eventId, $target, $actor)
    {
        // طريقُ الوسائط من كل حالةٍ إلى أهدافِ المزامنة الشرعية
        $paths = array(
            'UnderReview' => array('Draft' => array('Published', 'UnderReview'),
                                   'ValidationFailed' => array('Published', 'UnderReview'),
                                   'ReturnedToSource' => array('Published', 'UnderReview'),
                                   'Published' => array('UnderReview')),
            'Approved'    => array('Draft' => array('Published', 'UnderReview', 'Approved'),
                                   'Published' => array('UnderReview', 'Approved'),
                                   'UnderReview' => array('Approved')),
            'Posted'      => array('Approved' => array('Posted'),
                                   'RetryPending' => array('Posted')),
            'Rejected'    => array('Draft' => array('Published', 'UnderReview', 'Rejected'),
                                   'Published' => array('UnderReview', 'Rejected'),
                                   'UnderReview' => array('Rejected')),
            // «يرجع بسببٍ إلزامي ويعود للدورة» — رفضُ الشاشة القائمة القابلُ
            // للإعادة هو ReturnedToSource دلاليًّا لا Rejected النهائية
            'ReturnedToSource' => array('Draft' => array('Published', 'UnderReview', 'ReturnedToSource'),
                                        'Published' => array('UnderReview', 'ReturnedToSource'),
                                        'UnderReview' => array('ReturnedToSource')),
            'Closed'      => array('Posted' => array('Closed')),
            'Published'   => array('Draft' => array('Published'),
                                   'ReturnedToSource' => array('Published'),
                                   'ValidationFailed' => array('Published')),
            'Draft'       => array(),
        );

        $row = $gate->selectOne('fin_financial_events', array(
            'columns' => array('fes_status'), 'where' => array('id' => intval($eventId))));
        if (!$row) {
            return array('ok' => false, 'code' => 404, 'from' => '', 'to' => $target, 'version' => 0,
                'reasons' => array('الحدث غير موجود'));
        }
        $cur = (string) $row['fes_status'];
        if ($cur === $target) {
            return array('ok' => true, 'code' => 200, 'from' => $cur, 'to' => $target, 'version' => 0, 'reasons' => array());
        }
        $steps = isset($paths[$target][$cur]) ? $paths[$target][$cur] : null;
        if ($steps === null) {
            return array('ok' => false, 'code' => 409, 'from' => $cur, 'to' => $target, 'version' => 0,
                'reasons' => array("لا طريق مزامنة من {$cur} إلى {$target}"));
        }
        $last = array('ok' => true, 'code' => 200, 'from' => $cur, 'to' => $target, 'version' => 0, 'reasons' => array());
        foreach ($steps as $step) {
            $last = self::transition($gate, $conn, $eventId, $step, $actor);
            if (!$last['ok']) { return $last; }
        }
        return $last;
    }
}
