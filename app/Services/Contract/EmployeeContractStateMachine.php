<?php
/**
 * app/Services/Contract/EmployeeContractStateMachine.php — آلةُ حالات عقد الموظف
 * (H-08-① · CON-01 §4) — نمطُ H-02 حرفًا: **الانتقالُ يُحرَس هنا لا في الشاشة**،
 * و**قائمةُ سماحٍ لا قائمةُ منع** — فما نُسي مرفوضٌ لا ممرَّر.
 *
 * ── الخريطةُ منقولةٌ عن جدول CON-01 §4 ──────────────────────────────────────
 *   draft → completed → validated → approved/rejected → accepted/declined
 *     → signed → active → confirmed · amended · expired · terminated
 *     ↳ suspended/seconded: بابان خاصان (سببٌ إلزامي · العودةُ إلى حيث كان)
 *     ↳ expired/terminated → settled → closed → archived (نهائيةٌ بلا رجوع)
 *   (العنوانُ يعدّ «سبعَ عشرةَ» والجدولُ يسمّي 18 حالةً متمايزة — أُخذ الجدول.)
 *
 * ── بوابةُ القراءة الواحدة ─────────────────────────────────────────────────
 * «لا يُقرأ في الاحتساب إلا عقدٌ Active/Confirmed/Amended (أو Seconded بنسبه)»
 * — تعريفُه هنا وحدَه (isReadable) يستهلكه ENT-01/H-09 يوم يُبنيان.
 *
 * ── المرحَّلُ قراءةً محصَّن ─────────────────────────────────────────────────
 * صفٌّ بsource_table مصدرُه القديمُ هو كاتبُه حتى إقفال الكتابة القديمة
 * بمطابقة فترةٍ (N-04) — فلا انتقالَ له من هنا (423 بذكر مصدره).
 */

namespace App\Services\Contract;

class EmployeeContractStateMachine
{
    const DRAFT      = 'draft';
    const COMPLETED  = 'completed';
    const VALIDATED  = 'validated';
    const APPROVED   = 'approved';
    const REJECTED   = 'rejected';
    const ACCEPTED   = 'accepted';
    const DECLINED   = 'declined';
    const SIGNED     = 'signed';
    const ACTIVE     = 'active';
    const CONFIRMED  = 'confirmed';
    const AMENDED    = 'amended';
    const SUSPENDED  = 'suspended';
    const SECONDED   = 'seconded';
    const EXPIRED    = 'expired';
    const TERMINATED = 'terminated';
    const SETTLED    = 'settled';
    const CLOSED     = 'closed';
    const ARCHIVED   = 'archived';

    const ALL = array(
        self::DRAFT, self::COMPLETED, self::VALIDATED, self::APPROVED, self::REJECTED,
        self::ACCEPTED, self::DECLINED, self::SIGNED, self::ACTIVE, self::CONFIRMED,
        self::AMENDED, self::SUSPENDED, self::SECONDED, self::EXPIRED, self::TERMINATED,
        self::SETTLED, self::CLOSED, self::ARCHIVED,
    );

    /** بوابةُ القراءة للاحتساب — التعريفُ الواحد (CON-01 §4 قيود الحالة). */
    const READING_STATES = array(self::ACTIVE, self::CONFIRMED, self::AMENDED, self::SECONDED);

    /** النهائيةُ بلا رجوع. */
    const TERMINAL_STATES = array(self::ARCHIVED);

    /**
     * **قائمةُ السماح** — جدولُ CON-01 §4 نصًّا.
     * rejected/declined → draft: إعادةُ صياغةٍ بعد سببِ الرفض الموثَّق (قياسًا
     * على رجوعات H-02 «تفاوض→مسودة» — الوثيقةُ توجب سببَ الرفض ولا تحرّم العودة).
     * completed → draft: رجوعُ تصحيحٍ قبل التحقق.
     * suspended/seconded بلا وجهةٍ هنا: الدخولُ بـhold() والخروجُ بـresume()
     * إلى **حيث كان** — فوجهتُهما ليست ثابتةً حتى تُكتب في خريطة.
     */
    const TRANSITIONS = array(
        self::DRAFT      => array(self::COMPLETED),
        self::COMPLETED  => array(self::VALIDATED, self::DRAFT),
        self::VALIDATED  => array(self::APPROVED, self::REJECTED),
        self::APPROVED   => array(self::ACCEPTED, self::DECLINED),
        self::REJECTED   => array(self::DRAFT),
        self::DECLINED   => array(self::DRAFT),
        self::ACCEPTED   => array(self::SIGNED),
        self::SIGNED     => array(self::ACTIVE),
        self::ACTIVE     => array(self::CONFIRMED, self::AMENDED, self::EXPIRED, self::TERMINATED),
        self::CONFIRMED  => array(self::AMENDED, self::EXPIRED, self::TERMINATED),
        self::AMENDED    => array(self::CONFIRMED, self::EXPIRED, self::TERMINATED),
        self::SUSPENDED  => array(),   // الخروجُ بـresume() إلى حيث كان
        self::SECONDED   => array(),   // الخروجُ بـresume() إلى حيث كان
        self::EXPIRED    => array(self::SETTLED),
        self::TERMINATED => array(self::SETTLED),
        self::SETTLED    => array(self::CLOSED),
        self::CLOSED     => array(self::ARCHIVED),
        self::ARCHIVED   => array(),   // نهائيةٌ بلا رجوع
    );

    /** ما يجوز تعليقُه/إعارتُه — النافذةُ العاملة (CON-01: Active → Suspended/Seconded). */
    const HOLDABLE = array(self::ACTIVE, self::CONFIRMED, self::AMENDED);

    /** التعريبُ للعرض — الENUM لاتينيٌّ عمدًا (گوتشا الترميز) والتعريبُ هنا. */
    const LABELS_AR = array(
        self::DRAFT => 'مسودة', self::COMPLETED => 'مكتمل', self::VALIDATED => 'محقَّق',
        self::APPROVED => 'معتمد', self::REJECTED => 'مرفوض', self::ACCEPTED => 'مقبول',
        self::DECLINED => 'معتذَر عنه', self::SIGNED => 'موقَّع', self::ACTIVE => 'نافذ',
        self::CONFIRMED => 'مثبَّت', self::AMENDED => 'معدَّل بملحق', self::SUSPENDED => 'معلَّق',
        self::SECONDED => 'معار', self::EXPIRED => 'منتهٍ', self::TERMINATED => 'منهًى',
        self::SETTLED => 'مصفًّى', self::CLOSED => 'مقفل', self::ARCHIVED => 'مؤرشف',
    );

    // ═══════════════════════════════════════════════════════════════════════
    // القراءة — بلا كتابةٍ ولا أثر
    // ═══════════════════════════════════════════════════════════════════════

    /** هل يُقرأ في الاحتساب؟ — بوابةُ H-09/ENT-01 الواحدة. */
    public static function isReadable($state)
    {
        return in_array((string) $state, self::READING_STATES, true);
    }

    public static function canTransition($from, $to)
    {
        $from = (string) $from; $to = (string) $to;
        if (!in_array($to, self::ALL, true)) { return false; }
        if (!isset(self::TRANSITIONS[$from])) { return false; }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /** الوجهاتُ المشروعة — لبناء أزرارٍ لا تعرض ما يُرفض. */
    public static function allowedFrom($state)
    {
        $s = (string) $state;
        return isset(self::TRANSITIONS[$s]) ? self::TRANSITIONS[$s] : array();
    }

    public static function labelAr($state)
    {
        $s = (string) $state;
        return isset(self::LABELS_AR[$s]) ? self::LABELS_AR[$s] : $s;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الانتقال — الكتابةُ الوحيدةُ المسموحة على الحالة
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @param int|null $expectedVersion قفلٌ تفاؤلي: نسخةٌ متغيرةٌ → 409 (CON-01 §7.2)
     * @return array{ok:bool,code:int,reason:string,from:?string,to:?string,changed:bool}
     */
    public static function transition($conn, $gate, $companyId, $contractId, $to, $note, $actor, $expectedVersion = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'from' => null, 'to' => null, 'changed' => false);
        $contractId = (int) $contractId;
        $to = (string) $to;

        $c = self::contractOf($gate, $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $guard = self::guardWritable($c);
        if ($guard !== null) { return array_merge($out, $guard); }

        $from = (string) $c['state'];
        $out['from'] = $from; $out['to'] = $to;

        // العطالةُ قبل الحكم
        if ($from === $to) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'العقدُ في هذه الحالة سلفًا';
            return $out;
        }
        if (!in_array($to, self::ALL, true)) {
            $out['code'] = 422; $out['reason'] = 'حالةٌ غيرُ معروفة: ' . $to; return $out;
        }
        if (in_array($from, self::TERMINAL_STATES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'العقدُ مؤرشف — حالةٌ نهائيةٌ بلا رجوع';
            return $out;
        }
        if ($to === self::SUSPENDED || $to === self::SECONDED) {
            $out['code'] = 422;
            $out['reason'] = 'التعليقُ والإعارةُ ببابيهما (hold) — يلزمهما سببٌ ويحفظان ما قبلهما';
            return $out;
        }
        if ($from === self::SUSPENDED || $from === self::SECONDED) {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ ' . self::labelAr($from) . ' — يُستأنف أولًا فيعود إلى حيث كان، ثم يمضي';
            return $out;
        }
        // «لا اعتمادَ لمن أنشأ» (CON-01 §4/§7.2) → 403
        if ($to === self::APPROVED && (int) $actor > 0
            && (int) $c['created_by'] === (int) $actor) {
            $out['code'] = 403;
            $out['reason'] = 'لا اعتمادَ لمن أنشأ — فصلُ الواجبات بنيويٌّ لا إخفاءُ زر';
            return $out;
        }
        // H-10: «Accepted → Signed · شرطُه رفعُ النسخة الموقَّعة (ثابتةٌ لا تُعدَّل)»
        if ($to === self::SIGNED && trim((string) ($c['signed_file_ref'] ?? '')) === '') {
            $out['code'] = 422;
            $out['reason'] = 'النسخةُ الموقَّعةُ تُرفع أولًا (attachSignedFile) — شرطُ التوقيع (CON-01 §4)';
            return $out;
        }
        if ($expectedVersion !== null && (int) $expectedVersion !== (int) $c['version']) {
            $out['code'] = 409;
            $out['reason'] = 'نسخةٌ متغيرة — أعِد التحميل (المسجَّلة ' . (int) $c['version'] . ')';
            return $out;
        }
        if (!self::canTransition($from, $to)) {
            $allowed = self::allowedFrom($from);
            $out['code'] = 422;
            $out['reason'] = 'انتقالٌ غيرُ مشروع: ' . self::labelAr($from) . ' ← ' . self::labelAr($to)
                . ($allowed ? (' — والمشروعُ من هنا: ' . implode(' · ', array_map(array(__CLASS__, 'labelAr'), $allowed)))
                            : ' — ولا انتقالَ مشروعٌ من هذه الحالة');
            return $out;
        }

        $upd = array('state' => $to, 'version' => (int) $c['version'] + 1);
        if ($to === self::APPROVED) {
            $upd['approved_by'] = (int) $actor ?: null;
            $upd['approved_at'] = gmdate('Y-m-d H:i:s');
        }
        $gate->update('employee_contracts', $upd, array('id' => $contractId));
        // H-11: الإنهاءُ والانتهاءُ يُبطلان اللقطاتِ من يومهما — «فيُعاد احتسابُ
        // ما بعده لا ما قبله» (ENT-01 §2؛ والملحقُ H-10 سيمرّ بسريانه).
        if ($to === self::TERMINATED || $to === self::EXPIRED) {
            self::invalidateSnapshots($conn, $gate, (int) $c['company_id'], $contractId,
                'انتقالُ الحالة: ' . self::labelAr($from) . ' ← ' . self::labelAr($to), $actor);
        }
        self::emit($conn, $gate, (int) $c['company_id'], $contractId, $from, $to, $note, $actor);

        $out['ok'] = true; $out['code'] = 200; $out['changed'] = true;
        return $out;
    }

    /**
     * التعليق/الإعارة — **بسببٍ إلزامي**، ويحفظ ما قبله ليعود إليه.
     * @param string $kind 'suspended'|'seconded'
     */
    public static function hold($conn, $gate, $companyId, $contractId, $kind, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'from' => null, 'changed' => false);
        $kind = (string) $kind;
        if ($kind !== self::SUSPENDED && $kind !== self::SECONDED) {
            $out['code'] = 422; $out['reason'] = 'بابُ الإيقاف: تعليقٌ أو إعارةٌ حصرًا'; return $out;
        }
        $note = trim((string) $note);
        if ($note === '') {
            $out['code'] = 422;
            $out['reason'] = ($kind === self::SUSPENDED
                ? 'سببُ التعليق إلزامي — «قرارٌ موثَّقٌ بسببٍ ومدة» (CON-01 §4)'
                : 'بيانُ الإعارة إلزامي — «كيانٌ مستفيدٌ آخرُ بمدةٍ ونسبِ تحمّل» (CON-01 §4)');
            return $out;
        }
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $guard = self::guardWritable($c);
        if ($guard !== null) { return array_merge($out, $guard); }

        $from = (string) $c['state'];
        $out['from'] = $from;
        if ($from === $kind) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = self::labelAr($kind) . ' سلفًا'; return $out;
        }
        if (!in_array($from, self::HOLDABLE, true)) {
            $out['code'] = 422;
            $out['reason'] = 'لا ' . ($kind === self::SUSPENDED ? 'يُعلَّق' : 'يُعار')
                . ' عقدٌ حالتُه «' . self::labelAr($from) . '» — البابُ للعقود النافذة العاملة';
            return $out;
        }

        $gate->update('employee_contracts', array(
            'state'             => $kind,
            'state_before_hold' => $from,
            'hold_reason'       => $note,
            'version'           => (int) $c['version'] + 1,
        ), array('id' => (int) $contractId));
        // H-11: التعليقُ/الإعارةُ من مصادر الإبطال المسمّاة (ENT-01 §2)
        self::invalidateSnapshots($conn, $gate, (int) $c['company_id'], (int) $contractId,
            ($kind === self::SUSPENDED ? 'تعليقُ العقد: ' : 'إعارةُ العقد: ') . $note, $actor);
        self::emit($conn, $gate, (int) $c['company_id'], (int) $contractId, $from, $kind, $note, $actor);

        $out['ok'] = true; $out['code'] = 200; $out['changed'] = true;
        return $out;
    }

    /** الاستئناف/عودةُ الإعارة — إلى **حيث كان** لا إلى حالةٍ مفترضة. */
    public static function resume($conn, $gate, $companyId, $contractId, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'to' => null, 'changed' => false);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $guard = self::guardWritable($c);
        if ($guard !== null) { return array_merge($out, $guard); }

        $from = (string) $c['state'];
        if ($from !== self::SUSPENDED && $from !== self::SECONDED) {
            $out['code'] = 422; $out['reason'] = 'العقدُ ليس معلَّقًا ولا معارًا'; return $out;
        }
        $back = (string) $c['state_before_hold'];
        if ($back === '' || !in_array($back, self::ALL, true)) {
            // لا يُخترع مرجع
            $out['code'] = 422;
            $out['reason'] = 'لا حالةَ محفوظةً قبل الإيقاف — يُنقل يدويًّا بقرارٍ موثَّق';
            return $out;
        }
        $gate->update('employee_contracts', array(
            'state'             => $back,
            'state_before_hold' => null,
            'hold_reason'       => null,
            'version'           => (int) $c['version'] + 1,
        ), array('id' => (int) $contractId));
        // H-11: الاستئنافُ يعيد سريانَ القواعد — لقطاتُ ما بعده تُبطل فتُعاد
        self::invalidateSnapshots($conn, $gate, (int) $c['company_id'], (int) $contractId,
            'استئنافُ العقد من ' . self::labelAr($from), $actor);
        self::emit($conn, $gate, (int) $c['company_id'], (int) $contractId,
                   $from, $back, $note, $actor);

        $out['ok'] = true; $out['code'] = 200; $out['to'] = $back; $out['changed'] = true;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════

    /** H-11: إبطالُ اللقطات من يوم الحدث — لا يرمي (الإبطالُ لا يعطّل الانتقال). */
    private static function invalidateSnapshots($conn, $gate, $companyId, $contractId, $reason, $actor)
    {
        try {
            require_once __DIR__ . '/ContractSnapshotService.php';
            ContractSnapshotService::invalidateFrom($conn, $gate, $companyId, $contractId,
                date('Y-m-d'), $reason, $actor);
        } catch (\Throwable $t) {
            error_log('EmployeeContractStateMachine invalidateSnapshots #' . $contractId . ': ' . $t->getMessage());
        }
    }

    /** المرحَّلُ قراءةً محصَّن: مصدرُه القديمُ كاتبُه حتى إقفال القديم (N-04). */
    private static function guardWritable($c)
    {
        $src = isset($c['source_table']) ? trim((string) $c['source_table']) : '';
        if ($src !== '') {
            return array('code' => 423,
                'reason' => 'صفٌّ مرحَّلٌ قراءةً — كاتبُه مصدرُه القديم (' . $src
                    . ') حتى إقفال الكتابة القديمة بمطابقة فترةٍ (N-04)');
        }
        return null;
    }

    private static function contractOf($gate, $contractId)
    {
        try {
            return $gate->selectOne('employee_contracts', array('where' => array('id' => (int) $contractId)));
        } catch (\Throwable $t) {
            error_log('EmployeeContractStateMachine contractOf: ' . $t->getMessage());
            return null;
        }
    }

    /** حقيقةُ الانتقال: تدقيقُ N-02 (قبل/بعد) + إشهارٌ في الجذر المحايد. */
    private static function emit($conn, $gate, $companyId, $contractId, $from, $to, $note, $actor)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contracts', 'state_transition', (int) $contractId,
            array('state' => $from), array('state' => $to),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor,
                  'note' => (string) $note));
        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                // عقدُ §9 يفرض `domain.entity.action` والنطاقُ الأولُ **بلا شرطة
                // سفلية** — و`employee_contract.…` كان يُرفض صامتًا فلا تصل الحقيقةُ
                // الجذرَ أبدًا (كُشف مع M-22 وأُصلح؛ صفرُ صفٍّ منشورٍ يفقده التغيير).
                'event_key'       => 'workforce.contract_state.changed',
                'category'        => 'operational',
                'source_module'   => 'workforce',
                'company_id'      => (int) $companyId,
                'entity_type'     => 'employee_contract',
                'entity_id'       => (int) $contractId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => (int) $actor ?: 1,
                'idempotency_key' => 'employee_contract_state:' . (int) $contractId . ':' . $from . '>' . $to
                                     . ':' . gmdate('YmdHis'),
                'notes'           => 'حالةُ عقد الموظف: ' . self::labelAr($from) . ' ← ' . self::labelAr($to),
                'payload'         => array(
                    'employee_contract_id' => (int) $contractId,
                    'from' => $from, 'to' => $to,
                    'note' => trim((string) $note),
                    'readable' => self::isReadable($to),
                ),
            ));
        } catch (\Throwable $t) {
            error_log('EmployeeContractStateMachine emit #' . $contractId . ': ' . $t->getMessage());
        }
    }
}
