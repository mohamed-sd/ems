<?php
/**
 * app/Services/Contract/ContractStateMachine.php — آلةُ حالات العقد (H-02 · OPM-01 §3)
 * ═══════════════════════════════════════════════════════════════════════════
 * **الانتقالُ يُحرَس هنا لا في الشاشة.** الشاشةُ تعرض ما هو مشروعٌ وتطلبه؛ والحكمُ
 * بمشروعيته هنا وحدَه — فشاشةٌ جديدةٌ أو أمرُ صيانةٍ أو استيرادٌ لا يفتح بابًا
 * خلفيًّا. وكلُّ انتقالٍ **مرفوضٌ بنيويًّا** ما لم يُذكر في الخريطة صراحةً:
 * **قائمةُ سماحٍ لا قائمةُ منع** — فما نُسي مرفوضٌ لا ممرَّر.
 *
 * ── الخريطةُ منقولةٌ عن الوثيقة حرفًا بحرف ────────────────────────────────
 *   مسودة → تفاوض → معتمد → موقَّع → **نافذ** → قيد التنفيذ
 *     ↳ معلَّق: من أي مرحلةٍ حيّة، ويعود **إلى حيث كان** بالاستئناف
 *     ↳ معدَّل · مجدَّد: من «قيد التنفيذ» ويعودان إليه
 *     ↳ منتهٍ → مقفل → مصفّى — **نهائيةٌ بلا رجوع**
 *
 * ── «نافذ» — مدخلُ H-01 ──────────────────────────────────────────────────
 * «لا حاويةَ تُفتح إلا من عقدٍ نافذ»، و«لا حاويةَ نشطةٌ تحت عقدٍ غيرِ نافذ».
 * فتعريفُه **واحدٌ محسومٌ هنا** (`isEffective`) لا نصٌّ يُعاد كتابتُه في كل شاشة:
 * العقدُ نافذٌ إذا بلغ «نافذ» أو ما بعدها من الحالات العاملة (قيد التنفيذ ·
 * معدَّل · مجدَّد). و«معلَّق» **ليس نافذًا** — والتعليقُ يجمّد ما تحته.
 *
 * ── وراثةُ الحالة (نصٌّ ملزم) ─────────────────────────────────────────────
 * «حالةُ العقد تنزل آليًّا إلى كل حاويةٍ تحته: تعليقٌ يعلّق الكل، وانتهاءٌ يُقفل
 * الكل — **والعكسُ ممنوع: لا تُغيَّر حالةُ العقد من الحاوية**.» فالوراثةُ
 * **اشتقاقٌ للقراءة** (`inheritedState`) لا نسخٌ يُصان، فلا يفترق الأصلُ عن فرعه.
 * وحاوياتُ H-01 لم تُبنَ بعد، فالوراثةُ تُعرَّف هنا وتُستهلك يوم تُبنى — والدالةُ
 * تقول ذلك صراحةً بدل أن تُخترع لها جداولُ لا وجودَ لها.
 */

namespace App\Services\Contract;

class ContractStateMachine
{
    /** الحالاتُ الاثنتا عشرة — نصُّ ENUM حرفًا بحرف (خلافُه يبتلعه ENUM صامتًا). */
    const DRAFT       = 'مسودة';
    const NEGOTIATION = 'تفاوض';
    const APPROVED    = 'معتمد';
    const SIGNED      = 'موقَّع';
    const EFFECTIVE   = 'نافذ';
    const RUNNING     = 'قيد التنفيذ';
    const SUSPENDED   = 'معلَّق';
    const AMENDED     = 'معدَّل';
    const RENEWED     = 'مجدَّد';
    const ENDED       = 'منتهٍ';
    const CLOSED      = 'مقفل';
    const SETTLED     = 'مصفّى';

    const ALL = array(
        self::DRAFT, self::NEGOTIATION, self::APPROVED, self::SIGNED,
        self::EFFECTIVE, self::RUNNING, self::SUSPENDED, self::AMENDED,
        self::RENEWED, self::ENDED, self::CLOSED, self::SETTLED,
    );

    /**
     * الحالاتُ التي يُعدُّ فيها العقدُ **نافذًا** — شرطُ الحاويات والتشغيل.
     * «معلَّق» خارجَها عمدًا: التعليقُ يجمّد، فلا تُفتح تحته حاويةٌ ولا يُسجَّل يوم.
     */
    const EFFECTIVE_STATES = array(self::EFFECTIVE, self::RUNNING, self::AMENDED, self::RENEWED);

    /** الحالاتُ النهائيةُ بلا رجوع. */
    const TERMINAL_STATES = array(self::SETTLED);

    /**
     * **قائمةُ السماح** — وما ليس فيها مرفوضٌ بنيويًّا.
     * `معلَّق` مقصودٌ ألّا يكون له مصدرٌ ثابتٌ هنا: يُدخَل إليه من أي حالةٍ حيّةٍ
     * عبر `suspend()`، ويعود منه إلى **حيث كان** عبر `resume()` — فوجهتُه
     * ليست ثابتةً حتى تُكتب في خريطة.
     */
    const TRANSITIONS = array(
        self::DRAFT       => array(self::NEGOTIATION),
        self::NEGOTIATION => array(self::APPROVED, self::DRAFT),
        self::APPROVED    => array(self::SIGNED, self::NEGOTIATION),
        self::SIGNED      => array(self::EFFECTIVE),
        self::EFFECTIVE   => array(self::RUNNING, self::ENDED),
        self::RUNNING     => array(self::AMENDED, self::RENEWED, self::ENDED),
        self::AMENDED     => array(self::RUNNING, self::ENDED),
        self::RENEWED     => array(self::RUNNING, self::ENDED),
        self::ENDED       => array(self::CLOSED),
        self::CLOSED      => array(self::SETTLED),
        self::SETTLED     => array(),          // نهائيةٌ بلا رجوع
        self::SUSPENDED   => array(),          // الخروجُ منها بـresume() إلى حيث كان
    );

    /** الحالاتُ التي يجوز تعليقُها — الحيّةُ وحدَها (لا المنتهيةُ ولا المقفلة). */
    const SUSPENDABLE = array(self::NEGOTIATION, self::APPROVED, self::SIGNED,
                              self::EFFECTIVE, self::RUNNING, self::AMENDED, self::RENEWED);

    // ═══════════════════════════════════════════════════════════════════════
    // القراءة — بلا كتابةٍ ولا أثر
    // ═══════════════════════════════════════════════════════════════════════

    /** هل العقدُ **نافذ**؟ — التعريفُ الواحدُ الذي يقرؤه H-01 وغيرُه. */
    public static function isEffective($state)
    {
        return in_array((string) $state, self::EFFECTIVE_STATES, true);
    }

    /** هل الانتقالُ مشروع؟ قراءةٌ خالصةٌ تصلح للعرض كما تصلح للقرار. */
    public static function canTransition($from, $to)
    {
        $from = (string) $from; $to = (string) $to;
        if (!in_array($to, self::ALL, true)) { return false; }
        if (!isset(self::TRANSITIONS[$from])) { return false; }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /** الوجهاتُ المشروعةُ من حالةٍ — لبناء قائمةٍ لا تعرض ما يُرفض. */
    public static function allowedFrom($state)
    {
        $s = (string) $state;
        return isset(self::TRANSITIONS[$s]) ? self::TRANSITIONS[$s] : array();
    }

    /**
     * الحالةُ الموروثةُ لما تحت العقد (حاويةٌ · تخصيصٌ · أيُّ فرع).
     *
     * **اشتقاقٌ للقراءة لا نسخٌ يُصان**: لو خُزّنت في الفرع لافترقت عن أصلها يومَ
     * يتغير أحدهما. والاتجاهُ واحد: من العقد إلى فرعه — **ولا يُغيَّر العقدُ من فرعه**.
     *
     * @return array{active:bool,reason:string} هل الفرعُ نشطٌ ولماذا لا
     */
    public static function inheritedState($contractState)
    {
        $s = (string) $contractState;
        if ($s === self::SUSPENDED) {
            return array('active' => false, 'reason' => 'العقدُ معلَّق — وكلُّ ما تحته معلَّقٌ تبعًا له حتى الاستئناف');
        }
        if (in_array($s, array(self::ENDED, self::CLOSED, self::SETTLED), true)) {
            return array('active' => false, 'reason' => 'العقدُ ' . $s . ' — وما تحته مُقفلٌ للتسجيل ويبقى للقراءة');
        }
        if (!self::isEffective($s)) {
            return array('active' => false, 'reason' => 'العقدُ ليس نافذًا بعد (' . $s . ') — ولا فرعَ نشطٌ تحت عقدٍ غيرِ نافذ');
        }
        return array('active' => true, 'reason' => '');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الانتقال — الكتابةُ الوحيدةُ المسموحة على الحالة
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * تنفيذُ انتقالٍ مشروع.
     *
     * @param array $opts خياراتُ الانتقال: authority_ref (BR-CEO-01 عند التوقيع) ·
     *                    amount/currency (قيمةُ الالتزام لأثر ④-٣ إن عُرفت)
     * @return array{ok:bool,code:int,reason:string,from:?string,to:?string,changed:bool}
     */
    public static function transition($conn, $gate, $companyId, $contractId, $to, $note, $actor, array $opts = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'from' => null, 'to' => null, 'changed' => false);
        $contractId = (int) $contractId;
        $to = (string) $to;

        $c = self::contractOf($gate, $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $from = (string) $c['contract_status'];
        $out['from'] = $from; $out['to'] = $to;

        // العطالةُ قبل الحكم: الانتقالُ إلى الحالة نفسِها لا شيء
        if ($from === $to) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'العقدُ في هذه الحالة سلفًا';
            return $out;
        }
        if (!in_array($to, self::ALL, true)) {
            $out['code'] = 422; $out['reason'] = 'حالةٌ غيرُ معروفة: ' . $to; return $out;
        }
        if (in_array($from, self::TERMINAL_STATES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'العقدُ ' . $from . ' — حالةٌ نهائيةٌ بلا رجوع';
            return $out;
        }
        // التعليقُ والاستئنافُ لهما بابُهما (suspend/resume) — لا يمرّان من هنا
        if ($to === self::SUSPENDED) {
            $out['code'] = 422;
            $out['reason'] = 'التعليقُ يقع بـ«علِّق العقد» — فهو يلزمه سببٌ ومدةٌ ويحفظ ما قبله';
            return $out;
        }
        if ($from === self::SUSPENDED) {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ معلَّق — يُستأنَف أولًا فيعود إلى حيث كان، ثم يمضي';
            return $out;
        }
        if (!self::canTransition($from, $to)) {
            $out['code'] = 422;
            $allowed = self::allowedFrom($from);
            $out['reason'] = 'انتقالٌ غيرُ مشروع: ' . $from . ' ← ' . $to
                . ($allowed ? (' — والمشروعُ من هنا: ' . implode(' · ', $allowed))
                            : ' — ولا انتقالَ مشروعٌ من هذه الحالة');
            return $out;
        }

        // ═══ BR-CEO-01: التوقيعُ بالسلطة الأصلية — الحارسُ في نقطة الخنق ═══
        // بلوغُ «موقَّع» محصورٌ: الإدارةُ التنفيذية (الدور 9 أو السوبر) بسلطتها
        // الأصلية، وغيرُها بمرجع تفويضٍ موثَّقٍ إلزاميٍّ — ويُسجَّل المرجعُ مع
        // كل توقيعٍ في contracts.signing_authority_ref فيُجاب سؤالُ المراجعة
        // الأول: بأي صفةٍ وقّع؟ ولا توقيعَ بلا سند.
        $authorityRef = trim((string) ($opts['authority_ref'] ?? ''));
        if ($to === self::SIGNED) {
            $actorRole = '';
            if ((int) $actor > 0) {
                try {
                    $rs = mysqli_query($conn, 'SELECT role_id FROM users WHERE id = ' . (int) $actor);
                    $u = $rs ? mysqli_fetch_assoc($rs) : null;
                    $actorRole = $u ? strval($u['role_id']) : '';
                } catch (\Throwable $t) { $actorRole = ''; }
            }
            $isExec = ($actorRole === '9' || $actorRole === '-1');
            if ($isExec && $authorityRef === '') { $authorityRef = 'سلطة أصلية'; }
            if ($authorityRef === '') {
                $out['code'] = 403;
                $out['reason'] = 'BR-CEO-01: التوقيعُ محصورٌ بالسلطة الأصلية للمدير التنفيذي'
                    . ' أو تفويضٍ موثَّقٍ بمرجعه — مرِّر authority_ref أو وقِّع بحساب الإدارة التنفيذية';
                return $out;
            }
        }

        $upd = array('contract_status' => $to);
        if ($to === self::SIGNED && $authorityRef !== '') {
            $upd['signing_authority_ref'] = $authorityRef;
        }
        $gate->update('contracts', $upd, array('id' => $contractId));
        self::emit($conn, $gate, (int) $c['company_id'], $contractId, $from, $to, $note, $actor);

        // M-00 ④-٣: الأثرُ الرباعيُّ للتوقيع — نفاذٌ (بالآلة) · قيدٌ في السجل
        // الموحَّد (يقرأ contracts مباشرةً) · حاويةٌ تُولَّد · التزامٌ يدخل
        // الموازنةَ والتدفقَ بنمط المروحة (خريطة أثرٍ + صفُّ أثرٍ + وصلةُ عطالة)
        if ($to === self::SIGNED) {
            try {
                require_once __DIR__ . '/ContractSignedEffects.php';
                ContractSignedEffects::apply($conn, $gate, (int) $c['company_id'], $contractId, array(
                    'amount'   => $opts['amount'] ?? null,
                    'currency' => $opts['currency'] ?? null,
                    'actor'    => (int) $actor,
                ));
            } catch (\Throwable $t) {
                error_log('ContractSignedEffects #' . $contractId . ': ' . $t->getMessage());
            }
        }

        $out['ok'] = true; $out['code'] = 200; $out['changed'] = true;
        return $out;
    }

    /**
     * تعليقُ العقد — **بسببٍ إلزامي**، ويحفظ ما قبله ليعود إليه.
     * «كلُّ الحاويات التابعة تُعلَّق آليًّا» — والوراثةُ اشتقاقٌ فلا كتابةَ لها.
     */
    public static function suspend($conn, $gate, $companyId, $contractId, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'from' => null, 'changed' => false);
        $note = trim((string) $note);
        if ($note === '') {
            $out['code'] = 422;
            $out['reason'] = 'سببُ التعليق إلزامي — تعليقٌ يجمّد عقدًا وكلَّ ما تحته لا يكون بلا سببٍ مكتوب';
            return $out;
        }
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $from = (string) $c['contract_status'];
        $out['from'] = $from;
        if ($from === self::SUSPENDED) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'معلَّقٌ سلفًا'; return $out;
        }
        if (!in_array($from, self::SUSPENDABLE, true)) {
            $out['code'] = 422;
            $out['reason'] = 'لا يُعلَّق عقدٌ حالتُه «' . $from . '» — التعليقُ للعقود الحيّة';
            return $out;
        }

        // ما قبلَه يُحفظ في `pause_state_before` ليعود إليه بالاستئناف — ولا
        // يُخمَّن عند العودة («أعِده إلى نافذ» تخمينٌ يُسقط تقدّمَه).
        $gate->update('contracts', array(
            'contract_status'     => self::SUSPENDED,
            'pause_state_before'  => $from,
            'pause_date'          => date('Y-m-d'),
            'resume_date'         => null,
        ), array('id' => (int) $contractId));
        self::emit($conn, $gate, (int) $c['company_id'], (int) $contractId, $from, self::SUSPENDED, $note, $actor);

        $out['ok'] = true; $out['code'] = 200; $out['changed'] = true;
        return $out;
    }

    /** استئنافُ العقد — يعود **إلى حيث كان** لا إلى حالةٍ مفترَضة. */
    public static function resume($conn, $gate, $companyId, $contractId, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'to' => null, 'changed' => false);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }
        if ((string) $c['contract_status'] !== self::SUSPENDED) {
            $out['code'] = 422; $out['reason'] = 'العقدُ ليس معلَّقًا'; return $out;
        }
        $back = (string) $c['pause_state_before'];
        if ($back === '' || !in_array($back, self::ALL, true)) {
            // لا يُخترع مرجع: عقدٌ عُلّق قبل هذه الآلة لا يُعرف ما قبله
            $out['code'] = 422;
            $out['reason'] = 'لا حالةَ محفوظةً قبل التعليق — يُنقل يدويًّا إلى حالته الصحيحة بقرارٍ موثَّق';
            return $out;
        }
        $gate->update('contracts', array(
            'contract_status' => $back,
            'resume_date'     => date('Y-m-d'),
        ), array('id' => (int) $contractId));
        self::emit($conn, $gate, (int) $c['company_id'], (int) $contractId,
                   self::SUSPENDED, $back, $note, $actor);

        $out['ok'] = true; $out['code'] = 200; $out['to'] = $back; $out['changed'] = true;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════

    private static function contractOf($gate, $contractId)
    {
        try {
            return $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId)));
        } catch (\Throwable $t) {
            error_log('ContractStateMachine contractOf: ' . $t->getMessage());
            return null;
        }
    }

    /** حقيقةُ الانتقال في الجذر المحايد — إشهارٌ لا شرطُ صحة. */
    private static function emit($conn, $gate, $companyId, $contractId, $from, $to, $note, $actor)
    {
        // N-02: سجلُّ التدقيق بقيم قبل/بعد — نقطةُ الخنق الواحدة لكل انتقالات
        // العقد (transition/suspend/resume تمرّ كلُّها من هنا).
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contracts', 'state_transition', (int) $contractId,
            array('contract_status' => $from), array('contract_status' => $to),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor,
                  'contract_id' => (int) $contractId, 'note' => (string) $note));
        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'contract.state.changed',
                'category'        => 'operational',
                'source_module'   => 'sales',
                'company_id'      => (int) $companyId,
                'entity_type'     => 'contract',
                'entity_id'       => (int) $contractId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => (int) $actor ?: 1,
                // مفتاحٌ يميّز كلَّ انتقالٍ بنفسه: العودةُ إلى حالةٍ سابقةٍ حدثٌ آخر
                'idempotency_key' => 'contract_state:' . (int) $contractId . ':' . $from . '>' . $to
                                     . ':' . gmdate('YmdHis'),
                'contract_id'     => (int) $contractId,
                'notes'           => 'حالةُ العقد: ' . $from . ' ← ' . $to,
                'payload'         => array(
                    'contract_id' => (int) $contractId,
                    'from'        => $from,
                    'to'          => $to,
                    'note'        => trim((string) $note),
                    'effective'   => self::isEffective($to),
                ),
            ));

            // M-00 §11 (ContractSigned): بلوغُ «موقَّع» حقيقةٌ متخصصةٌ تُنشر باسمها
            // من نقطةِ الحدث نفسِها — يستهلكها السجلُّ الموحّد والمبيعاتُ والموردون
            // والتمويلُ والماليةُ والحوكمة. العطالة بالعقد ومصدرِ الانتقال: التوقيعُ
            // من المسارِ الواحد لا يتكرر، وإعادةُ توقيعٍ بعد مسارٍ آخر حقيقةٌ جديدة.
            if ($to === self::SIGNED) {
                $row = self::contractOf($gate, $contractId);
                \App\Core\EventPublisher::publishFact($conn, array(
                    'event_key'       => 'contract.signed',
                    'category'        => 'commercial',
                    'source_module'   => 'sales',
                    'company_id'      => (int) $companyId,
                    'entity_type'     => 'contract',
                    'entity_id'       => (int) $contractId,
                    'occurred_at'     => gmdate('Y-m-d H:i:s'),
                    'created_by'      => (int) $actor ?: 1,
                    'idempotency_key' => 'contract_signed:' . (int) $contractId . ':' . $from,
                    'contract_id'     => (int) $contractId,
                    'notes'           => 'توقيعُ العقد #' . (int) $contractId
                                         . (!empty($row['second_party']) ? ' — ' . $row['second_party'] : ''),
                    'payload'         => array(
                        'contract_id'  => (int) $contractId,
                        // هوية العقد الفعلية: طرفاه ومشروعه (لا عمودَ رقمٍ في contracts)
                        'first_party'  => isset($row['first_party']) ? (string) $row['first_party'] : '',
                        'second_party' => isset($row['second_party']) ? (string) $row['second_party'] : '',
                        'project_id'   => isset($row['project_id']) ? (int) $row['project_id'] : 0,
                        'signing_date' => isset($row['contract_signing_date']) ? (string) $row['contract_signing_date'] : '',
                        'from'         => $from,
                        'note'         => trim((string) $note),
                    ),
                ));
            }
        } catch (\Throwable $t) {
            error_log('ContractStateMachine emit #' . $contractId . ': ' . $t->getMessage());
        }
    }
}
