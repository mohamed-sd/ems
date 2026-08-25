<?php
namespace App\Services\Contract;

require_once __DIR__ . '/ContractStateMachine.php';

/**
 * ContractLifecycleActions — سجلُّ أفعالِ دورةِ حياةِ العقدِ تصريحيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0026 (عقودُ العملاء · ٨ أفعال) · INJ-0152 (عقودُ الموردين · ٧ أفعال)
 *
 * ── العلّةُ المقيسة ────────────────────────────────────────────────────────
 * `ContractStateMachine` مبنيةٌ كاملةً (١٢ حالةً · جدولُ انتقالاتٍ · تدقيقٌ ·
 * نشرُ حدث) — **وشاشتاها تتجاوزانها**: شاشةُ العقودِ تعرض فعلين من ثمانية،
 * وشاشةُ عقودِ الموردين **لا تنادي الانتقالَ ولا مرة** رغم أنَّ خدمتَها
 * (`SupplierContractService::transition`) قائمةٌ ومحروسة. فالدورةُ موجودةٌ في
 * الشفرةِ ومفقودةٌ في العمل — وهو عيبُ MD-05 نفسُه: **آلةٌ تُبنى ولا تُتبنّى**.
 *
 * ── ولماذا سجلٌّ تصريحيٌّ لا ستُّ نسخٍ من كتلةِ فعل ───────────────────────
 * الفعلُ الواحدُ في الشاشةِ ٢٥ سطرًا (عقدُ إرسالٍ · تحقّقٌ · نداءٌ · رسالة).
 * وستةُ أفعالٍ × شاشتين = ٣٠٠ سطرٍ متطابقةٍ إلا في حرفين — أي **ثلاثون فرصةً
 * للنسيان**. فالأفعالُ تُعلَن هنا مرةً واحدةً، والشاشةُ تُصيّرها وتُرسلها،
 * والحكمُ يبقى في الخدمةِ حيث هو (CS-05).
 *
 * ── وفعلُ العكسِ شرطٌ لا زينة (CS-08) ─────────────────────────────────────
 * لكلِّ فعلٍ ذي أثرٍ **إمّا فعلُ عكسٍ** يُعيد الحالةَ السابقة، **أو حكمٌ صريحٌ
 * أنه لا عكسَ له بطبيعته** — ولا يُترك السؤالُ بلا جواب. وثلاثةٌ هنا لها عكسٌ
 * حقيقيٌّ (إعادةٌ لمسودة · إعادةٌ لتفاوض · استئنافٌ بعد تعليق)، وخمسةٌ حُكم
 * عليها بالامتناع **بسببٍ مكتوب**: التوقيعُ واقعةٌ قانونيةٌ تُنقض بفسخٍ لا
 * برجوع، والسريانُ والتنفيذُ والإنهاءُ والإقفالُ وقائعُ زمنيةٌ يُصحَّح أثرُها
 * بحركةٍ جديدةٍ بمرجعها لا بمحوِ ما وقع.
 *
 * ◆ **والانتقالُ المشروعُ ليس رأيًا**: كلُّ فعلٍ هنا مأخوذٌ من `TRANSITIONS`
 *   في الآلةِ نفسِها — فإن تغيّر الجدولُ تغيّرت الأفعالُ معه ولا تتفرّقان.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class ContractLifecycleActions
{
    const KIND_CUSTOMER = 'customer';
    const KIND_SUPPLIER = 'supplier';

    /** لا عكسَ بطبيعته — والسببُ مكتوبٌ لا مسكوتٌ عنه. */
    const NO_REVERSE = '__none__';

    /**
     * سجلُّ الأفعال. لكلِّ فعل:
     *   label   · from[] · to · reverse (رمزُ فعلٍ عكسيّ أو NO_REVERSE)
     *   why     · سببُ امتناعِ العكسِ حين يمتنع
     *   needs   · حقلٌ إضافيٌّ إلزاميّ (مثل سببِ التعليق)
     */
    public static function registry($kind = self::KIND_CUSTOMER)
    {
        $S = 'App\\Services\\Contract\\ContractStateMachine';
        $DRAFT = $S::DRAFT; $NEG = $S::NEGOTIATION; $APP = $S::APPROVED;
        $SIG = $S::SIGNED; $EFF = $S::EFFECTIVE; $RUN = $S::RUNNING;
        $SUS = $S::SUSPENDED; $AMD = $S::AMENDED; $REN = $S::RENEWED;
        $END = $S::ENDED; $CLO = $S::CLOSED; $SET = $S::SETTLED;

        $common = array(
            /* ① */ 'submit_negotiation' => array(
                'label' => 'رفع للتفاوض', 'from' => array($DRAFT), 'to' => $NEG,
                'reverse' => 'return_draft'),
            /* ② */ 'approve' => array(
                'label' => 'اعتماد', 'from' => array($NEG), 'to' => $APP,
                'reverse' => 'return_negotiation'),
            /* ③ */ 'sign' => array(
                'label' => 'توقيع', 'from' => array($APP), 'to' => $SIG,
                'reverse' => self::NO_REVERSE,
                'why' => 'التوقيع واقعة قانونية نافذة — تنقض بفسخ موثق لا برجوع صامت'),
            /* ④ */ 'activate' => array(
                'label' => 'إنفاذ', 'from' => array($SIG), 'to' => $EFF,
                'reverse' => self::NO_REVERSE,
                'why' => 'السريان واقعة زمنية — ما وقع تحته من التزام لا يمحى بإرجاع الحالة'),
            /* ⑤ */ 'start_running' => array(
                'label' => 'بدء التنفيذ', 'from' => array($EFF), 'to' => $RUN,
                'reverse' => self::NO_REVERSE,
                'why' => 'التنفيذ بدأ فعلا — وإيقافه تعليق بسبب لا إنكار لبدئه'),
            /* ⑥ */ 'end' => array(
                'label' => 'إنهاء', 'from' => array($EFF, $RUN, $AMD, $REN), 'to' => $END,
                'reverse' => self::NO_REVERSE,
                'why' => 'الإنهاء يعالج بتجديد أو بعقد جديد — لا بإحياء عقد انتهى'),
            /* ⑦ */ 'close' => array(
                'label' => 'إقفال', 'from' => array($END), 'to' => $CLO,
                'reverse' => self::NO_REVERSE,
                'why' => 'الإقفال يلي تصفية الالتزامات — ونقضه يعيد فتح ما سوي'),
            /* ⑧ */ 'settle' => array(
                'label' => 'تصفية', 'from' => array($CLO), 'to' => $SET,
                'reverse' => self::NO_REVERSE,
                'why' => 'التصفية حالة نهائية بنص الآلة — ولا انتقال منها البتة'),

            /* أفعالُ العكسِ — مشروعةٌ في جدولِ الانتقالاتِ نفسِه */
            'return_draft' => array(
                'label' => 'إعادة لمسودة', 'from' => array($NEG), 'to' => $DRAFT,
                'reverse' => 'submit_negotiation', 'is_reverse' => true),
            'return_negotiation' => array(
                'label' => 'إعادة للتفاوض', 'from' => array($APP), 'to' => $NEG,
                'reverse' => 'approve', 'is_reverse' => true),
        );

        if ($kind === self::KIND_CUSTOMER) {
            /* التعليقُ والاستئنافُ بابانِ خاصّان في الآلة (يحفظان ما قبلَهما) */
            $common['suspend'] = array(
                'label' => 'تعليق', 'from' => $S::SUSPENDABLE, 'to' => $SUS,
                'reverse' => 'resume', 'door' => 'suspend', 'needs' => 'note');
            $common['resume'] = array(
                'label' => 'استئناف', 'from' => array($SUS), 'to' => null,
                'reverse' => 'suspend', 'door' => 'resume', 'is_reverse' => true);
        }
        return $common;
    }

    /** الأفعالُ الأصليةُ (بلا أفعالِ العكس) — وهي ما يُعَدُّ في نصِّ البند. */
    public static function primary($kind = self::KIND_CUSTOMER)
    {
        $out = array();
        foreach (self::registry($kind) as $code => $a) {
            if (empty($a['is_reverse'])) { $out[$code] = $a; }
        }
        return $out;
    }

    /** ما يجوز فعلُه من هذه الحالة — تُصيّرها الشاشةُ أزرارًا. */
    public static function availableFor($kind, $state)
    {
        $out = array();
        foreach (self::registry($kind) as $code => $a) {
            if (in_array((string) $state, $a['from'], true)) { $out[$code] = $a; }
        }
        return $out;
    }

    /**
     * تنفيذُ فعلٍ واحدٍ — الحكمُ يبقى في الآلةِ/الخدمةِ لا هنا.
     *
     * @return array{ok:bool,code:int,reason:string,action:string}
     */
    public static function run($conn, $gate, $companyId, $kind, $contractId, $code, $note, $actor, $role = 0, $version = 0)
    {
        $out = array('ok' => false, 'code' => 422, 'reason' => '', 'action' => (string) $code);
        $reg = self::registry($kind);
        if (!isset($reg[$code])) {
            $out['reason'] = 'CLA-422: فعل غير معلن في سجل دورة الحياة — لا يخمن';
            return $out;
        }
        $a = $reg[$code];
        $contractId = (int) $contractId;
        if ($contractId <= 0) { $out['reason'] = 'CLA-422: عقد غير صالح'; return $out; }

        /* حقلٌ إلزاميٌّ مُعلَنٌ للفعل (سببُ التعليقِ مثلًا) */
        if (!empty($a['needs']) && $a['needs'] === 'note' && trim((string) $note) === '') {
            $out['reason'] = 'CLA-422: «' . $a['label'] . '» يلزمه سبب مكتوب';
            return $out;
        }

        if ($kind === self::KIND_SUPPLIER) {
            require_once __DIR__ . '/SupplierContractService.php';
            $r = SupplierContractService::transition($conn, $gate, (int) $companyId,
                $contractId, (string) $a['to'], (int) $version, (int) $actor);
            $out['ok'] = !empty($r['ok']); $out['code'] = (int) $r['code'];
            $out['reason'] = $r['ok'] ? ('تم «' . $a['label'] . '» — الحالة الآن ' . $r['state']) : $r['reason'];
            return $out;
        }

        /* ══ INJ-0143 · ملاحظةٌ حرجةٌ مفتوحةٌ تحجب التوقيع ═══════════════════════
             نصُّ القبول: «ملاحظةٌ حرجةٌ **تحجب التوقيعَ** حتى تُغلق بمستندٍ
             ومعتمِد؛ **ولا يعتمد الحجبُ على أيِّ مطابقةٍ نصية**».
           ◆ **والحجبُ بالعمودِ لا بالكلمة**: `severity='critical'` و
             `note_state='open'` — ومطابقةُ النصِّ تُخدع بحرفٍ، وملاحظةٌ تقول
             «غير حرج» تُقرأ حرجةً بالبحثِ عن كلمة.
           ◆ وموضعُه **التوقيعُ وحدَه**: المراجعةُ تسبق التوقيعَ لا الإنهاءَ ولا
             الإقفال — فلا يُجمَّد العقدُ في كلِّ خطوة. */
        if ($code === 'sign' && $kind === self::KIND_CUSTOMER && $conn instanceof \mysqli) {
            $blk = 0; $first = '';
            $bq = $conn->prepare("SELECT COUNT(*), COALESCE(MIN(LEFT(note, 60)), '')
                                    FROM contract_notes
                                   WHERE contract_id = ? AND severity = 'critical' AND note_state = 'open'");
            if ($bq) {
                $bq->bind_param('i', $contractId);
                if ($bq->execute()) {
                    $br = $bq->get_result()->fetch_row();
                    if ($br) { $blk = (int) $br[0]; $first = (string) $br[1]; }
                }
                $bq->close();
            }
            if ($blk > 0) {
                $out['code'] = 423;
                $out['reason'] = 'CNOTE-423: ' . $blk . ' ملاحظة حرجة مفتوحة تحجب التوقيع — '
                               . 'تغلق بمستند ومعتمد أولا («' . mb_substr($first, 0, 40) . '»)';
                return $out;
            }
        }

        /* ◆ **والفعلانِ الأوّلانِ يمرّانِ بخدمةِ الاعتمادِ لا بالآلةِ مباشرةً**:
             فيها سقفُ التفويضِ والتصعيدُ عند تجاوزه (INJ-0001). وتجاوزُها هنا
             يُسقط الحارسَ الذي بُني — وهو الخطأُ نفسُه الذي نُصلحه. */
        if ($code === 'submit_negotiation' || $code === 'approve') {
            require_once __DIR__ . '/ContractApprovalService.php';
            $svc = new ContractApprovalService($conn);
            $r = ($code === 'approve')
                ? $svc->approve($gate, (int) $companyId, $contractId, (string) $note, (int) $actor, (int) $role)
                : $svc->submitForNegotiation($gate, (int) $companyId, $contractId, (string) $note, (int) $actor);
            $out['ok'] = !empty($r['ok']); $out['code'] = (int) $r['code'];
            $out['reason'] = $r['reason'];
            return $out;
        }

        /* بابا التعليقِ والاستئناف — لهما مسلكُهما في الآلة */
        if (!empty($a['door']) && $a['door'] === 'suspend') {
            $r = ContractStateMachine::suspend($conn, $gate, (int) $companyId, $contractId, (string) $note, (int) $actor);
        } elseif (!empty($a['door']) && $a['door'] === 'resume') {
            $r = ContractStateMachine::resume($conn, $gate, (int) $companyId, $contractId, (string) $note, (int) $actor);
        } else {
            $r = ContractStateMachine::transition($conn, $gate, (int) $companyId, $contractId,
                (string) $a['to'], (string) $note, (int) $actor, array());
        }
        $out['ok'] = !empty($r['ok']); $out['code'] = (int) $r['code'];
        $out['reason'] = $r['ok']
            ? ('تم «' . $a['label'] . '» — الحالة الآن ' . (isset($r['to']) ? $r['to'] : ''))
            : $r['reason'];
        return $out;
    }

    /**
     * وصفُ العكسِ لفعلٍ — للعرضِ في الشاشةِ وللشاهد.
     * @return array{has:bool,code:?string,label:?string,why:?string}
     */
    public static function reverseOf($kind, $code)
    {
        $reg = self::registry($kind);
        if (!isset($reg[$code])) { return array('has' => false, 'code' => null, 'label' => null, 'why' => null); }
        $rv = $reg[$code]['reverse'];
        if ($rv === self::NO_REVERSE) {
            return array('has' => false, 'code' => null, 'label' => null,
                         'why' => isset($reg[$code]['why']) ? $reg[$code]['why'] : '');
        }
        return array('has' => true, 'code' => $rv,
                     'label' => isset($reg[$rv]) ? $reg[$rv]['label'] : $rv, 'why' => null);
    }
}
