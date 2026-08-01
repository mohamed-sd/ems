<?php
/**
 * app/Services/Contract/ContractLifecycleService.php — اقتصادُ دورة الحياة (P-11)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §6 الجدولُ الحاكم · §6.1 القواعدُ الملزمةُ الخمس ·
 * §9-⑥: «إنهاءٌ بخطأ العميل: **المقدمُ رُد والمنفَّذُ فُوتر والتعويضُ احتُسب
 * بنصه**» · §9-⑦: «إلغاءٌ قبل البدء: **الحاوياتُ أُلغيت ولم تُقفل والمقدمُ رُد
 * كاملًا**».
 *
 * ── لماذا جدولٌ للأثر والحالةُ مسجَّلة ──────────────────────────────────────
 * `contracts` تحمل `termination_type` و`termination_reason` — **حالةَ العلاقة**
 * (`H-02`). و**لا موضعَ يحمل أثرَها المالي**: فإنهاءٌ «بخطأ العميل» وإنهاءٌ
 * «بخطئنا» يُسجَّلان **نصًّا واحدَ الشكل**، مع أن أثرَهما **متعاكس**:
 *   · بخطأ العميل: المقدمُ **يُرد كاملًا** · الضمانُ **يُرد** · المنفَّذُ
 *     **يُفوتر كاملًا** · **والشركةُ تُطالِب بتعويض**.
 *   · بخطئنا: المقدمُ **بعد خصم ما استُحق** · الضمانُ **قد يُصادر** ·
 *     المنفَّذُ **المقبولُ فقط** · **وغراماتُ الإخلال علينا**.
 * **والخلطُ بينهما يقلب اتجاهَ المال.**
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **الأثرُ محكومٌ بالحالة لا يُختار** — و`CHECK` بثمانية فروعٍ يحرسه؛ فمن أراد
 *    أثرًا مخالفًا **يلزمه تغييرُ الحالة**.
 * ② **والمنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء** (§6.1-③) — يُفوتر بمستخلصٍ
 *    ختاميٍّ **مهما كان سببُ الإنهاء**؛ **والعملُ المنفَّذ حقٌّ مكتسب**.
 * ③ **ولا خصمَ إلا بمادةٍ ومستند** (§6.1-④) — «وإلا فهي مطالبةٌ تفاوضيةٌ لا
 *    خصمٌ نظامي».
 * ④ **ولا أثرَ رجعي** (§6.1-⑤) — لكلِّ حالةٍ **تاريخُ أثر**، وما قبله بحكمه القديم.
 *
 * ⚠ **وهذه الخدمةُ تُقرّر ولا تُنفّذ**: تكتب الواقعةَ بأثرها وتُخرج **خطةَ
 *   عملٍ مسمّاة** (`plan()`)؛ أما الردُّ والفوترةُ وإقفالُ الحاويات فبيوتُها
 *   `M-01` و`ENT-03` و`H-01` — **ولا يُنفَّذ فعلٌ من هنا بابُه هناك**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/PlanActualLinkService.php';

class ContractLifecycleService
{
    const STATES = array('extension', 'renewal', 'suspension', 'natural_end',
                         'client_fault_end', 'our_fault_end', 'pre_start_cancel', 'dispute');

    const STATE_AR = array(
        'extension' => '① التمديد (ملحق)',
        'renewal' => '② التجديد (عقد جديد)',
        'suspension' => '③ التعليق',
        'natural_end' => '④ الإنهاء الطبيعي (انتهاء المدة)',
        'client_fault_end' => '⑤ الإنهاء بخطأ العميل',
        'our_fault_end' => '⑥ الإنهاء بخطئنا',
        'pre_start_cancel' => '⑦ الإلغاء قبل البدء',
        'dispute' => '⑧ النزاع أو التحكيم',
    );

    /** **جدولُ §6 حرفيًّا** — والأثرُ يُقرأ منه لا يُختار. */
    const MATRIX = array(
        'extension' => array(
            'advance' => 'continue', 'retention' => 'hold', 'unbilled' => 'bill_cycle',
            'penalty' => 'continue', 'container' => 'extend'),
        'renewal' => array(
            'advance' => 'settle_and_new', 'retention' => 'release_after_grace',
            'unbilled' => 'final_claim_old', 'penalty' => 'close_old_start_new',
            'container' => 'new_tree'),
        'suspension' => array(
            'advance' => 'pause_recovery', 'retention' => 'hold', 'unbilled' => 'bill_before_pause',
            'penalty' => 'pause_time_not_performance', 'container' => 'suspend'),
        'natural_end' => array(
            'advance' => 'consume_then_refund', 'retention' => 'release_after_grace',
            'unbilled' => 'final_claim', 'penalty' => 'accrue_to_effect_date',
            'container' => 'close_readonly'),
        'client_fault_end' => array(
            'advance' => 'refund_all_after_offset', 'retention' => 'release', 'unbilled' => 'bill_all',
            'penalty' => 'company_claims_compensation', 'container' => 'close_with_ref'),
        'our_fault_end' => array(
            'advance' => 'refund_after_dues', 'retention' => 'may_forfeit',
            'unbilled' => 'bill_accepted_only', 'penalty' => 'breach_penalties_capped',
            'container' => 'close'),
        'pre_start_cancel' => array(
            'advance' => 'refund_full', 'retention' => 'release', 'unbilled' => 'none',
            'penalty' => 'mobilization_cost_if_article', 'container' => 'cancel'),
        'dispute' => array(
            'advance' => 'freeze', 'retention' => 'hold', 'unbilled' => 'freeze_disputed_bill_rest',
            'penalty' => 'suspend_until_resolution', 'container' => 'suspend'),
    );

    const EFFECT_AR = array(
        'advance' => array(
            'continue' => 'يستمر بجدوله',
            'settle_and_new' => 'يُصفَّى مع القديم ويبدأ مقدمٌ جديدٌ ما لم يُنص على النقل',
            'pause_recovery' => 'يتوقف استهلاكُه بمدة التعليق',
            'consume_then_refund' => 'يُستهلك المتبقي من المستخلص الختامي وما فاض يُرد نقدًا',
            'refund_all_after_offset' => '**يُرد المتبقي كاملًا** بعد المقاصّة',
            'refund_after_dues' => 'يُرد المتبقي بعد خصم ما استُحق',
            'refund_full' => '**يُرد كاملًا** إن لم يبدأ التنفيذ',
            'freeze' => '**تُوقف المقاصّةُ ويُثبَّت الرصيد**'),
        'retention' => array(
            'hold' => 'يبقى محتجزًا',
            'release_after_grace' => 'يُرد بعد مهلته إن لم توجد مطالبات',
            'release' => '**يُرد**',
            'may_forfeit' => '**قد يُصادر كليًّا أو جزئيًّا** بنص العقد'),
        'unbilled' => array(
            'bill_cycle' => 'يُفوتر بدورته',
            'final_claim_old' => 'يُفوتر بمستخلصٍ ختاميٍّ للقديم',
            'bill_before_pause' => 'يُفوتر ما نُفّذ قبل التعليق',
            'final_claim' => 'يُفوتر بمستخلصٍ ختامي',
            'bill_all' => '**يُفوتر المنفَّذ كاملًا**',
            'bill_accepted_only' => 'يُفوتر **المنفَّذ المقبول فقط**',
            'none' => 'لا شيء',
            'freeze_disputed_bill_rest' => 'يُجمَّد المتنازَعُ عليه ويُفوتر الباقي'),
        'penalty' => array(
            'continue' => 'تستمر قواعدُها',
            'close_old_start_new' => 'تُقفل قواعدُ القديم وتبدأ الجديدة',
            'pause_time_not_performance' => 'تتوقف الغراماتُ الزمنيةُ لا الأدائية',
            'accrue_to_effect_date' => 'تُحتسب المستحقةُ حتى تاريخ الأثر',
            'company_claims_compensation' => '**تُطالَب الشركةُ بتعويض** بنود العقد (تعبئةٌ وإخلاءٌ وتوقف)',
            'breach_penalties_capped' => '**تُحتسب غراماتُ الإخلال بسقوفها**',
            'mobilization_cost_if_article' => 'تُحتسب كلفةُ التعبئة والإخلاء **إن نصّ العقدُ عليها**',
            'suspend_until_resolution' => 'تُعلَّق حتى الحسم'),
        'container' => array(
            'extend' => '**الحاويةُ نفسُها يمتد أجلُها ورصيدُها كما هو**',
            'new_tree' => '**حاويةٌ جديدةٌ ولا تُخلط الأرصدة**',
            'suspend' => 'تُعلَّق الحاويات',
            'close_readonly' => '**تُقفل للتسجيل وتبقى للقراءة**',
            'close_with_ref' => 'تُقفل بمرجع القرار',
            'close' => 'تُقفل الحاويات',
            'cancel' => '**تُلغى شجرةُ الحاويات ولا تُقفل** (لم تُستهلك)'),
    );

    const TERMINAL = array('natural_end', 'client_fault_end', 'our_fault_end', 'pre_start_cancel');

    /** الآثارُ الخمسةُ لحالةٍ — **قراءةٌ من الجدول لا اختيار**. */
    public static function effectsOf($state)
    {
        return isset(self::MATRIX[(string) $state]) ? self::MATRIX[(string) $state] : null;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ① التسجيل — والأثرُ يُشتقّ
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تسجيلُ واقعةِ دورة حياة — **والأثرُ يُكتب من الجدول لا من الطلب**.
     * @return array{ok:bool,code:int,reason:string,id:int,effects:array}
     */
    public static function record($conn, $gate, $companyId, $contractId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => 0, 'effects' => array());
        $state = (string) (isset($a['state']) ? $a['state'] : '');
        if (!in_array($state, self::STATES, true)) {
            $out['code'] = 422;
            $out['reason'] = '**حالةٌ خارج الثماني** — وجدولُ §6 ثمانيةٌ لا تاسعَ لها'; return $out;
        }
        $c = null;
        try { $c = $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غيرُ موجودٍ في نطاقك'; return $out; }

        // ④ **ولا أثرَ رجعي** — والتاريخُ إلزامي
        $day = trim((string) (isset($a['effect_date']) ? $a['effect_date'] : ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $out['code'] = 422;
            $out['reason'] = '**تاريخُ الأثر إلزامي** — «وما قبله بحكمه القديم وما بعده بالجديد» (§6.1-⑤)';
            return $out;
        }
        $ref = trim((string) (isset($a['decision_ref']) ? $a['decision_ref'] : ''));
        if (in_array($state, self::TERMINAL, true) && $ref === '') {
            $out['code'] = 422;
            $out['reason'] = '**مرجعُ القرار إلزاميٌّ للإنهاء والإلغاء** — ولا يخرج عقدٌ صامتًا';
            return $out;
        }

        // ⑦ «يُرد كاملًا **إن لم يبدأ التنفيذ**» — فالإلغاءُ قبل البدء **يُقاس**
        if ($state === 'pre_start_cancel') {
            $done = self::executedQty($gate, (int) $contractId);
            if ($done > 0.004) {
                $out['code'] = 409;
                $out['reason'] = '**التنفيذُ بدأ فعلًا** (' . $done . ' وحدةً مسجَّلة) — '
                    . 'ولا يُلغى «قبل البدء» ما بدأ؛ والحالُ المناسبُ إنهاءٌ لا إلغاء';
                return $out;
            }
        }

        // ③ **ولا خصمَ إلا بمادةٍ ومستند**
        $amt = (isset($a['claim_amount']) && trim((string) $a['claim_amount']) !== '')
               ? round((float) $a['claim_amount'], 2) : null;
        $article = trim((string) (isset($a['contract_article']) ? $a['contract_article'] : ''));
        $doc = trim((string) (isset($a['claim_doc_ref']) ? $a['claim_doc_ref'] : ''));
        $cur = trim((string) (isset($a['claim_currency']) ? $a['claim_currency'] : ''));
        if ($amt !== null) {
            if (abs($amt) < 0.005) { $amt = null; }
            elseif ($article === '' || $doc === '') {
                $out['code'] = 422;
                $out['reason'] = '**لا خصمَ ولا تعويضَ إلا بمادةٍ من العقد وحسابٍ موثَّقٍ بمستنداته** '
                    . '(§6.1-④) — «وإلا فهي **مطالبةٌ تفاوضيةٌ لا خصمٌ نظامي**»';
                return $out;
            } elseif ($cur === '') {
                $out['code'] = 422; $out['reason'] = '**عملةُ المطالبة إلزامية**'; return $out;
            }
        }

        $e = self::MATRIX[$state];
        $row = array(
            'contract_id' => (int) $contractId, 'state' => $state, 'effect_date' => $day,
            'decision_ref' => ($ref === '') ? null : mb_substr($ref, 0, 120),
            'advance_effect' => $e['advance'], 'retention_effect' => $e['retention'],
            'unbilled_effect' => $e['unbilled'], 'penalty_effect' => $e['penalty'],
            'container_effect' => $e['container'],
            'claim_amount' => $amt,
            'claim_currency' => ($amt === null) ? null : strtoupper(mb_substr($cur, 0, 8)),
            'contract_article' => ($amt === null) ? null : mb_substr($article, 0, 200),
            'claim_doc_ref' => ($amt === null) ? null : mb_substr($doc, 0, 120),
            'note' => isset($a['note']) ? mb_substr((string) $a['note'], 0, 255) : null,
            'created_by' => (int) $actor ?: null,
        );
        try { $id = (int) $gate->insert('contract_lifecycle_events', $row); }
        catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['ok'] = true; $out['code'] = 200; $out['effects'] = $e;
                $out['reason'] = 'الواقعةُ مسجَّلةٌ سلفًا بهذا التاريخ — **فعلٌ عاطل**'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التسجيل: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'lifecycle_event', $id, array(), $row);
        $out['ok'] = true; $out['code'] = 200; $out['id'] = $id; $out['effects'] = $e;
        $out['reason'] = 'سُجّلت «' . self::STATE_AR[$state] . '» بتاريخِ أثرٍ ' . $day
            . ' — **وأثرُها الخماسيُّ مكتوبٌ من جدول §6 لا من الطلب**';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② خطةُ العمل — تُقرّر ولا تُنفّذ
    // ═════════════════════════════════════════════════════════════════════

    /**
     * خطةُ عملٍ مسمّاةٌ لحالةٍ على عقدٍ بأرقامه الحيّة.
     * **تُقرّر ولا تُنفّذ**: الردُّ والفوترةُ وإقفالُ الحاويات بيوتُها الأخرى.
     *
     * @return array{ok:bool,code:int,state:string,actions:array,figures:array,note:string}
     */
    public static function plan($gate, $contractId, $state)
    {
        $o = array('ok' => false, 'code' => 0, 'state' => (string) $state,
                   'actions' => array(), 'figures' => array(), 'note' => '');
        if (!in_array((string) $state, self::STATES, true)) {
            $o['code'] = 422; $o['note'] = 'حالةٌ خارج الثماني'; return $o;
        }
        $e = self::MATRIX[(string) $state];

        // الأرقامُ الحيّةُ من بيوتها — **ولا رقمَ يُخترع هنا**
        require_once dirname(__DIR__, 3) . '/Contracts/advance_helpers.php';
        require_once __DIR__ . '/ContractGuaranteeService.php';
        $adv = advance_balance($gate, (int) $contractId);
        $ret = ContractGuaranteeService::retentionBalance($gate, (int) $contractId);
        $pv  = PlanActualLinkService::planVsActual($gate, (int) $contractId);
        $unbilled = round((float) $pv['totals']['actual'] - (float) $pv['totals']['billed'], 2);

        $o['figures'] = array(
            'advance_balance' => round((float) $adv['balance'], 2),
            'retention_balance' => round((float) $ret['balance'], 2),
            'executed' => (float) $pv['totals']['actual'],
            'billed' => (float) $pv['totals']['billed'],
            'unbilled' => $unbilled,
        );

        $o['actions'][] = array('area' => 'المقدَّم', 'rule' => self::EFFECT_AR['advance'][$e['advance']],
            'figure' => $o['figures']['advance_balance'], 'home' => 'M-01 · contract_advances');
        $o['actions'][] = array('area' => 'الضمان/المحتجَز', 'rule' => self::EFFECT_AR['retention'][$e['retention']],
            'figure' => $o['figures']['retention_balance'], 'home' => 'P-06 · claims/claim_lines');
        $o['actions'][] = array('area' => 'المنفَّذُ غيرُ المفوتر',
            'rule' => self::EFFECT_AR['unbilled'][$e['unbilled']],
            'figure' => $unbilled, 'home' => 'ENT-03 · المستخلص الختامي');
        $o['actions'][] = array('area' => 'الغرامات/التعويض', 'rule' => self::EFFECT_AR['penalty'][$e['penalty']],
            'figure' => null, 'home' => 'CON-02 §6 · بسقوفها');
        $o['actions'][] = array('area' => 'الحاويات', 'rule' => self::EFFECT_AR['container'][$e['container']],
            'figure' => null, 'home' => 'H-01 · شجرةُ الحاويات');

        // ② **والمنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء** — تحذيرٌ صريحٌ لكلِّ إنهاء
        if ($unbilled > 0.004 && in_array((string) $state, self::TERMINAL, true)
            && $e['unbilled'] !== 'none') {
            $o['actions'][] = array('area' => '⚠ حقٌّ مكتسب',
                'rule' => '**المنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء** — يُفوتر بمستخلصٍ ختاميٍّ '
                        . '**مهما كان سببُ الإنهاء** (§6.1-③)',
                'figure' => $unbilled, 'home' => 'ENT-03');
        }
        if ((string) $state === 'pre_start_cancel' && $unbilled > 0.004) {
            $o['actions'][] = array('area' => '⚠ تناقض',
                'rule' => '**«لا شيء» في المنفَّذ لا تستقيم مع تنفيذٍ مسجَّل** — والحالُ إنهاءٌ لا إلغاء',
                'figure' => $unbilled, 'home' => 'P-11');
        }

        $o['ok'] = true; $o['code'] = 200;
        $o['note'] = self::STATE_AR[(string) $state] . ' — مقدَّمٌ ' . $o['figures']['advance_balance']
            . ' · محتجَزٌ ' . $o['figures']['retention_balance']
            . ' · منفَّذٌ غيرُ مفوترٍ ' . $unbilled
            . ' · **والخدمةُ تُقرّر ولا تُنفّذ**: لكلِّ فعلٍ بيتُه';
        return $o;
    }

    /** الكميةُ المنفَّذةُ على العقد — «هل بدأ التنفيذ؟». */
    public static function executedQty($gate, $contractId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('u' => 'unit_entries')),
                "SELECT ROUND(COALESCE(SUM(u.qty),0),2) AS s FROM unit_entries u
                  WHERE {TENANT_SCOPE} AND u.contract_id = ?
                    AND u.state NOT IN ('draft','rejected','cancelled','reversed','superseded')",
                array((int) $contractId));
            return $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    public static function eventsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('e' => 'contract_lifecycle_events')),
                "SELECT e.* FROM contract_lifecycle_events e
                  WHERE {TENANT_SCOPE} AND e.contract_id = ? AND COALESCE(e.is_deleted,0)=0
                  ORDER BY e.effect_date DESC, e.id DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_lifecycle_events', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
