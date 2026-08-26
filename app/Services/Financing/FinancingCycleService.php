<?php
/**
 * FinancingCycleService — دورةُ التمويل: تعاقدٌ ثمَّ أقساطٌ ثمَّ إقفالاتٌ ثلاثة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الإقفالاتُ الثلاثةُ كياناتٌ متمايزةٌ لا حالاتٌ لكيانٍ واحد** (§22):
 *   `closeContractPeriod` تكتب في `fin_contract_close` **وحدَه** بفترتِه
 *   التعاقديّةِ · `closeMonth` في `fin_monthly_close` **وحدَه** بشهرٍ تقويميّ ·
 *   `closeFinal` في `fin_final_close` **وحدَه** مرّةً لعمليةٍ لا غير.
 *   ولا دالّةَ واحدةً تكتب في اثنَين، ولا صفَّ يُعاد استعمالُه لمعنًى آخر —
 *   والربطُ بينها في `fin_close_link` **بأزواجٍ مسموحةٍ ثلاثةٍ لا رابع**.
 *
 * ◆ **ونموذجُ أمرِ الدفعِ المستقبليُّ لا يرث محدوديّةَ التاريخيّ** (§22):
 *   `requestPaymentOrder` تفرض طالبًا ومبلغًا وعملةً وتاريخَ طلبٍ — وهي أربعةٌ
 *   لا تستطيع الصفوفُ المجمَّعةُ التاريخيّةُ توفيرَها. والتاريخيُّ يدخل من
 *   بابِه وحدَه `ingestLegacyAggregate` إلى جدولِه، **وحجّيّتُه ومرجعُ صفِّه
 *   شرطُ قبول**. و`allocatePayment` **من أمرٍ فقط** — فالمجمَّعُ لا يُخصَّص.
 *   ⛔ **ولا يُخفَّض عمودٌ في النموذجِ ليقبلَ ما لا يملكه التاريخيّ.**
 *
 * ◆ **والأثرُ الماليُّ يُقرأ من الأحداثِ ولا يُكتب قيدًا** (§48): التنفيذُ يصدر
 *   **طلبَ اعترافٍ** عبر `AccountingCycleService::requestRecognition` والماليّةُ
 *   تقرّر وتثبّت. ⛔ **ولا سطرَ في `fin_journal_*` من هذا الملفّ.**
 *
 * ◆ **وفصلُ الواجباتِ يُنفَّذ برمزِ ردٍّ يُقرأ لا بانفجارِ بوّابة**: من رفع
 *   الحاجةَ لا يعتمدها · من أعدَّ العقدَ لا يوقّعه · من طلب الأمرَ لا يعتمده ·
 *   من أعدَّ الإقفالَ لا يعتمده · من رفع الانحرافَ لا يحسمه.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w12_thresholds`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Financing;

require_once __DIR__ . '/FinancingService.php';

use App\Core\TenantDb;

class FinancingCycleService
{
    const KIND_CONTRACTUAL = 'CONTRACTUAL';
    const KIND_MONTHLY     = 'MONTHLY';
    const KIND_FINAL       = 'FINAL';

    const LAYER_FUTURE = 'FUTURE';
    const LAYER_LEGACY = 'LEGACY';

    private static $eventConn = null;
    private static $thConn = null;
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /** العتبةُ من السجلِّ — ولا رقمَ مكتوبٌ في هذا الملفّ */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            $c = self::$thConn;
            if ($c instanceof \mysqli) {
                $r = @$c->query("SELECT threshold_key, value_num FROM repair01_w12_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return isset(self::$th[$key]) ? self::$th[$key] : null;
    }

    /**
     * ⛔ **بابُ المجالِ المقيَّدِ واحدٌ لا اثنان**: `financing_operations` و
     *   `financing_installments` و`financing_deviations` مقيَّدةٌ في سجلِّ
     *   البوّابة (`T_RESTRICTED` · N-15) — تُقرأ وتُكتب عبر `FinancingService`
     *   وحدَه. وهذه الخدمةُ **تفوّض إليه ولا تفتح بابًا ثانيًا** ولا تحمل
     *   استعلامًا خامًّا (‏`FR-SEC-006` · `GAP-29`) — والنمطُ سابقةُ W11 في
     *   `GAP-27`: تُنقَل الكتابةُ إلى كاتبِها المعتمَدِ لا يُضاف استثناء.
     */
    private static function restrictedConn()
    {
        return (self::$eventConn instanceof \mysqli) ? self::$eventConn : null;
    }

    private static function fail($code, $detail = '')
    {
        return array('ok' => false, 'code' => $code, 'detail' => $detail);
    }
    private static function done(array $data = array())
    {
        return array_merge(array('ok' => true, 'code' => 'OK'), $data);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ① التأسيسُ والدورةُ — الحاجةُ ثمَّ العرضُ ثمَّ المراجعة
       ══════════════════════════════════════════════════════════════════════ */

    public static function raiseNeed(TenantDb $gate, array $row, $actorId)
    {
        $amount = (float) (isset($row['amount_needed']) ? $row['amount_needed'] : 0);
        if ($amount <= 0) { return self::fail('NEED_AMOUNT_INVALID', 'المبلغ غير موجب'); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('NEED_WITHOUT_CURRENCY', 'الحاجة بلا عملة'); }
        $why = trim((string) (isset($row['justification']) ? $row['justification'] : ''));
        if ($why === '') { return self::fail('NEED_WITHOUT_JUSTIFICATION', 'الحاجة بلا مبرر مكتوب'); }
        $id = $gate->insert('fin_funding_need', array(
            'need_code'      => (string) (isset($row['need_code']) ? $row['need_code']
                                          : ('FNEED-' . substr(sha1(microtime(true) . 'n'), 0, 10))),
            'title'          => (string) (isset($row['title']) ? $row['title'] : ''),
            'requester_dept' => (string) (isset($row['requester_dept']) ? $row['requester_dept'] : ''),
            'purpose'        => (string) (isset($row['purpose']) ? $row['purpose'] : 'general'),
            'amount_needed'  => $amount,
            'currency'       => $cur,
            'needed_by'      => isset($row['needed_by']) ? $row['needed_by'] : null,
            'justification'  => $why,
            'state'          => 'submitted',
            'raised_by'      => (int) $actorId,
        ));
        return self::done(array('need_id' => (int) $id));
    }

    /** ⛔ **من رفع الحاجةَ لا يعتمدها** */
    public static function approveNeed(TenantDb $gate, $needId, $actorId)
    {
        $n = $gate->selectOne('fin_funding_need', array('where' => array('id' => (int) $needId)));
        if (!$n) { return self::fail('NEED_NOT_FOUND', ''); }
        if ((string) $n['state'] !== 'submitted') { return self::fail('NEED_NOT_SUBMITTED', ''); }
        if ((int) $n['raised_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_RAISE_AND_APPROVE_NEED', 'من رفع الحاجة لا يعتمدها');
        }
        $gate->update('fin_funding_need',
            array('state' => 'approved', 'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s')),
            array('id' => (int) $needId));
        return self::done(array('need_id' => (int) $needId));
    }

    /** العرضُ نسخةٌ لا دهس — والتفاوضُ يُنشئ إصدارًا يحمل مرجعَ سابقِه */
    public static function receiveOffer(TenantDb $gate, array $row, $actorId)
    {
        $principal = (float) (isset($row['principal']) ? $row['principal'] : 0);
        if ($principal <= 0) { return self::fail('OFFER_AMOUNT_INVALID', ''); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('OFFER_WITHOUT_CURRENCY', ''); }
        $code = (string) (isset($row['offer_code']) ? $row['offer_code']
                          : ('FOFR-' . substr(sha1(microtime(true) . 'o'), 0, 10)));
        $ver = (int) (isset($row['version_no']) ? $row['version_no'] : 1);
        if ($ver < 1) { $ver = 1; }
        $id = $gate->insert('fin_funding_offer', array(
            'offer_code'    => $code,
            'need_id'       => (int) (isset($row['need_id']) ? $row['need_id'] : 0),
            'entity_id'     => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'version_no'    => $ver,
            'model_code'    => (string) (isset($row['model_code']) ? $row['model_code'] : ''),
            'principal'     => $principal,
            'currency'      => $cur,
            'profit_rate'   => (float) (isset($row['profit_rate']) ? $row['profit_rate'] : 0),
            'tenor_months'  => (int) (isset($row['tenor_months']) ? $row['tenor_months'] : 0),
            'grace_months'  => (int) (isset($row['grace_months']) ? $row['grace_months'] : 0),
            'fees_total'    => (float) (isset($row['fees_total']) ? $row['fees_total'] : 0),
            'collateral'    => (string) (isset($row['collateral']) ? $row['collateral'] : ''),
            'offer_doc_ref' => (string) (isset($row['offer_doc_ref']) ? $row['offer_doc_ref'] : ''),
            'received_on'   => isset($row['received_on']) ? $row['received_on'] : date('Y-m-d'),
            'valid_until'   => isset($row['valid_until']) ? $row['valid_until'] : null,
            'state'         => 'received',
            'created_by'    => (int) $actorId,
        ));
        if ($ver > 1 && !empty($row['supersedes_id'])) {
            $gate->update('fin_funding_offer', array('superseded_by' => (int) $id, 'state' => 'negotiating'),
                          array('id' => (int) $row['supersedes_id']));
        }
        return self::done(array('offer_id' => (int) $id));
    }

    /** المراجعةُ قبل التعاقدِ — رأيُ كلِّ جهةٍ بمسؤولِه، والحجبُ بسببٍ مكتوب */
    public static function decidePrecontract(TenantDb $gate, $reviewId, $verdict, $reason, $actorId)
    {
        $r = $gate->selectOne('fin_precontract_review', array('where' => array('id' => (int) $reviewId)));
        if (!$r) { return self::fail('REVIEW_NOT_FOUND', ''); }
        $verdict = (string) $verdict;
        if (!in_array($verdict, array('cleared', 'blocked'), true)) {
            return self::fail('REVIEW_VERDICT_INVALID', '');
        }
        if ($verdict === 'blocked' && trim((string) $reason) === '') {
            return self::fail('REVIEW_BLOCK_WITHOUT_REASON', 'الحجب بلا سبب مكتوب');
        }
        if ($verdict === 'cleared' && ((int) $r['legal_by'] === 0 || (int) $r['finance_by'] === 0)) {
            return self::fail('REVIEW_CLEARED_WITHOUT_OPINIONS', 'لا اجازة قبل راي القانوني والمالية');
        }
        $gate->update('fin_precontract_review', array(
            'verdict' => $verdict, 'blocking_reason' => (string) $reason,
            'decided_by' => (int) $actorId, 'decided_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $reviewId));
        return self::done(array('review_id' => (int) $reviewId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② التعاقدُ — العقدُ يُوقَّع بمن ليس مُعِدَّه
       ══════════════════════════════════════════════════════════════════════ */

    public static function draftContract(TenantDb $gate, array $row, $actorId)
    {
        $principal = (float) (isset($row['principal']) ? $row['principal'] : 0);
        if ($principal <= 0) { return self::fail('CONTRACT_AMOUNT_INVALID', ''); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('CONTRACT_WITHOUT_CURRENCY', ''); }
        $id = $gate->insert('fin_finance_contract', array(
            'contract_code' => (string) (isset($row['contract_code']) ? $row['contract_code']
                                         : ('FCTR-' . substr(sha1(microtime(true) . 'c'), 0, 10))),
            'entity_id'     => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'offer_id'      => (int) (isset($row['offer_id']) ? $row['offer_id'] : 0),
            'review_id'     => (int) (isset($row['review_id']) ? $row['review_id'] : 0),
            'op_id'         => (int) (isset($row['op_id']) ? $row['op_id'] : 0),
            'model_code'    => (string) (isset($row['model_code']) ? $row['model_code'] : ''),
            'principal'     => $principal,
            'currency'      => $cur,
            'start_on'      => isset($row['start_on']) ? $row['start_on'] : null,
            'end_on'        => isset($row['end_on']) ? $row['end_on'] : null,
            'periods_total' => (int) (isset($row['periods_total']) ? $row['periods_total'] : 0),
            'state'         => 'draft',
            'prepared_by'   => (int) $actorId,
        ));
        return self::done(array('contract_id' => (int) $id));
    }

    /** ⛔ **من أعدَّ العقدَ لا يوقّعه** — ولا توقيعَ بلا مراجعةٍ مجازةٍ ومستند */
    public static function signContract(TenantDb $gate, $contractId, $docRef, $actorId)
    {
        $c = $gate->selectOne('fin_finance_contract', array('where' => array('id' => (int) $contractId)));
        if (!$c) { return self::fail('CONTRACT_NOT_FOUND', ''); }
        if ((string) $c['state'] !== 'draft') { return self::fail('CONTRACT_NOT_DRAFT', ''); }
        if (trim((string) $docRef) === '') {
            return self::fail('SIGN_WITHOUT_DOCUMENT', 'العقد بلا مستند موقع');
        }
        if ((int) $c['prepared_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_SIGN', 'من اعد العقد لا يوقعه');
        }
        $rv = (int) $c['review_id'] > 0
            ? $gate->selectOne('fin_precontract_review', array('where' => array('id' => (int) $c['review_id'])))
            : null;
        if (!$rv || (string) $rv['verdict'] !== 'cleared') {
            return self::fail('SIGN_WITHOUT_CLEARED_REVIEW', 'لا توقيع قبل اجازة مراجعة ما قبل التعاقد');
        }
        $gate->update('fin_finance_contract', array(
            'state' => 'signed', 'signed_by' => (int) $actorId, 'signed_on' => date('Y-m-d'),
            'contract_doc_ref' => (string) $docRef,
        ), array('id' => (int) $contractId));
        self::emit($gate, 'fin.contract.signed', array('contract_id' => (int) $contractId));
        return self::done(array('contract_id' => (int) $contractId));
    }

    /**
     * التنازلُ عن التزامٍ تعاقديّ. ⛔ **ولا تنازلَ بلا قاعدةِ صلاحيةٍ ومستندٍ
     * من الممول**، و⛔ **من رصد الإخلالَ لا يتنازل عنه** — فالإعفاءُ سلطةٌ
     * بسقفِها لا إجراءٌ داخليٌّ صامت. ولا يقع على التزامٍ لم يُرصَد إخلالُه:
     * إعفاءٌ مسبقٌ بلا واقعةٍ يفرّغ المصفوفةَ من معناها.
     */
    public static function waiveCovenant(TenantDb $gate, $covenantId, $waiverRef, $actorId, $breachBy = 0)
    {
        $c = $gate->selectOne('fin_contract_covenant', array('where' => array('id' => (int) $covenantId)));
        if (!$c) { return self::fail('COVENANT_NOT_FOUND', ''); }
        if (trim((string) $waiverRef) === '') {
            return self::fail('WAIVE_WITHOUT_AUTHORITY', 'لا تنازل بلا مستند من الممول وقاعدة صلاحية');
        }
        if ((int) $breachBy > 0 && (int) $breachBy === (int) $actorId) {
            return self::fail('WAIVE_WITHOUT_AUTHORITY', 'من رصد الاخلال لا يتنازل عنه');
        }
        if ((string) $c['state'] !== 'breached') {
            return self::fail('WAIVE_WITHOUT_BREACH', 'لا تنازل عن التزام لم يرصد اخلاله');
        }
        $gate->update('fin_contract_covenant',
            array('state' => 'waived', 'waiver_ref' => (string) $waiverRef),
            array('id' => (int) $covenantId));
        return self::done(array('covenant_id' => (int) $covenantId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ **الإقفالُ التعاقديّ** — كيانُه وحدَه
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * إقفالٌ تعاقديٌّ واحد: `ممول × عملية × فترة تعاقديّة`.
     * ⛔ **ولا يُكتب هنا صفٌّ شهريٌّ ولا نهائيٌّ** — ولكلٍّ دالّتُه وجدولُه.
     */
    public static function prepareContractClose(TenantDb $gate, array $row, $actorId)
    {
        $periodNo = (int) (isset($row['contract_period_no']) ? $row['contract_period_no'] : 0);
        if ($periodNo <= 0) {
            return self::fail('CLOSE_WITHOUT_CONTRACT_PERIOD',
                              'الاقفال التعاقدي بلا رقم فترته التعاقدية يصير شهرا مقنعا');
        }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('CLOSE_WITHOUT_CURRENCY', ''); }
        $opId = (int) (isset($row['op_id']) ? $row['op_id'] : 0);
        if ($opId <= 0) { return self::fail('CLOSE_WITHOUT_OPERATION', ''); }

        /* **الافتتاحيُّ يساوي ختاميَّ السابقةِ أو لا يُقبَل** — والترحيلُ يُقاس */
        $prev = $gate->selectOne('fin_contract_close', array(
            'where' => array('op_id' => $opId, 'contract_period_no' => $periodNo - 1)));
        $openP = (float) (isset($row['open_principal']) ? $row['open_principal'] : 0);
        $openF = (float) (isset($row['open_profit']) ? $row['open_profit'] : 0);
        $roll = 1;
        if ($prev) {
            if (abs($openP - (float) $prev['close_principal']) > 0.005
                || abs($openF - (float) $prev['close_profit']) > 0.005) {
                return self::fail('ROLLFORWARD_BROKEN',
                                  'الرصيد الافتتاحي لا يساوي ختامي الفترة التعاقدية السابقة');
            }
        }
        $dueP = (float) (isset($row['due_principal']) ? $row['due_principal'] : 0);
        $dueF = (float) (isset($row['due_profit']) ? $row['due_profit'] : 0);
        $fees = (float) (isset($row['due_fees']) ? $row['due_fees'] : 0);
        $adj  = (float) (isset($row['approved_adjust']) ? $row['approved_adjust'] : 0);
        $paid = (float) (isset($row['allocated_paid']) ? $row['allocated_paid'] : 0);
        /* الختاميُّ **مشتقٌّ لا مكتوبٌ بيد** — ورقمانِ من مصدرَين يتفرّقان */
        $closeP = round($openP + $dueP + $adj - $paid, 2);
        $closeF = round($openF + $dueF, 2);

        $id = $gate->insert('fin_contract_close', array(
            'close_kind'         => self::KIND_CONTRACTUAL,
            'close_code'         => (string) (isset($row['close_code']) ? $row['close_code']
                                              : ('FCON-' . substr(sha1(microtime(true) . 'k'), 0, 10))),
            'op_id'              => $opId,
            'entity_id'          => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'contract_id'        => (int) (isset($row['contract_id']) ? $row['contract_id'] : 0),
            'contract_period_no' => $periodNo,
            'period_start'       => (string) $row['period_start'],
            'period_end'         => (string) $row['period_end'],
            'currency'           => $cur,
            'open_principal'     => $openP,
            'open_profit'        => $openF,
            'due_principal'      => $dueP,
            'due_profit'         => $dueF,
            'due_fees'           => $fees,
            'approved_adjust'    => $adj,
            'allocated_paid'     => $paid,
            'close_principal'    => $closeP,
            'close_profit'       => $closeF,
            'arrears_amount'     => (float) (isset($row['arrears_amount']) ? $row['arrears_amount'] : 0),
            'arrears_days'       => (int) (isset($row['arrears_days']) ? $row['arrears_days'] : 0),
            'next_due_on'        => isset($row['next_due_on']) ? $row['next_due_on'] : null,
            'rollforward_ok'     => $roll,
            'statement_ref'      => (string) (isset($row['statement_ref']) ? $row['statement_ref'] : ''),
            'state'              => 'prepared',
            'prepared_by'        => (int) $actorId,
            'note'               => (string) (isset($row['note']) ? $row['note'] : ''),
        ));
        return self::done(array('close_id' => (int) $id, 'close_kind' => self::KIND_CONTRACTUAL));
    }

    /** ⛔ **من أعدَّ الإقفالَ التعاقديَّ لا يعتمده** */
    public static function approveContractClose(TenantDb $gate, $closeId, $actorId)
    {
        $c = $gate->selectOne('fin_contract_close', array('where' => array('id' => (int) $closeId)));
        if (!$c) { return self::fail('CLOSE_NOT_FOUND', ''); }
        if ((string) $c['state'] === 'approved') { return self::done(array('idempotent' => 1)); }
        if ((int) $c['prepared_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE', 'من اعد الاقفال لا يعتمده');
        }
        if ((int) $c['rollforward_ok'] !== 1) {
            return self::fail('ROLLFORWARD_BROKEN', 'لا اعتماد لاقفال لم يثبت ترحيل رصيده');
        }
        $gate->update('fin_contract_close', array(
            'state' => 'approved', 'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $closeId));
        self::emit($gate, 'fin.contract.closed', array('close_id' => (int) $closeId));
        return self::done(array('close_id' => (int) $closeId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ **الإقفالُ الشهريّ** — كيانُه وحدَه بشهرٍ تقويميّ
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * إقفالٌ شهريٌّ واحد: `ممول × عملية × شهر تقويميّ × عملة`.
     * ⛔ **والشهرُ تقويميٌّ لا فترةٌ تعاقديّة** — فوعاءُ الشهرِ لا يخدم معنى
     *   التعاقديِّ أيضًا، والقاعدةُ تردُّ ما يخالف بـ`chk_fmc_month`.
     */
    public static function prepareMonthlyClose(TenantDb $gate, array $row, $actorId)
    {
        $month = trim((string) (isset($row['accounting_month']) ? $row['accounting_month'] : ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return self::fail('MONTH_NOT_CALENDAR', 'الشهر المحاسبي بصيغة السنة والشهر وحدها');
        }
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('CLOSE_WITHOUT_CURRENCY', ''); }
        $opId = (int) (isset($row['op_id']) ? $row['op_id'] : 0);
        if ($opId <= 0) { return self::fail('CLOSE_WITHOUT_OPERATION', ''); }

        $prevMonth = date('Y-m', strtotime($start . ' -1 month'));
        $prev = $gate->selectOne('fin_monthly_close', array(
            'where' => array('op_id' => $opId, 'accounting_month' => $prevMonth, 'currency' => $cur)));
        $open = (float) (isset($row['open_balance']) ? $row['open_balance'] : 0);
        if ($prev && abs($open - (float) $prev['close_balance']) > 0.005) {
            return self::fail('ROLLFORWARD_BROKEN', 'رصيد اول الشهر لا يساوي رصيد اخر الشهر السابق');
        }
        $due  = (float) (isset($row['due_in_month']) ? $row['due_in_month'] : 0);
        $paid = (float) (isset($row['paid_in_month']) ? $row['paid_in_month'] : 0);
        $alloc = (float) (isset($row['allocated_in_month']) ? $row['allocated_in_month'] : 0);
        $close = round($open + $due - $alloc, 2);

        $id = $gate->insert('fin_monthly_close', array(
            'close_kind'       => self::KIND_MONTHLY,
            'close_code'       => (string) (isset($row['close_code']) ? $row['close_code']
                                            : ('FMC-' . substr(sha1(microtime(true) . 'm'), 0, 10))),
            'op_id'            => $opId,
            'entity_id'        => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'accounting_month' => $month,
            'month_start'      => $start,
            'month_end'        => $end,
            'currency'         => $cur,
            'contract_closes_n' => 0,
            'open_balance'     => $open,
            'due_in_month'     => $due,
            'paid_in_month'    => $paid,
            'allocated_in_month' => $alloc,
            'unallocated_in_month' => round($paid - $alloc, 2),
            'arrears_in_month' => (float) (isset($row['arrears_in_month']) ? $row['arrears_in_month'] : 0),
            'close_balance'    => $close,
            'rollforward_ok'   => 1,
            'state'            => 'prepared',
            'prepared_by'      => (int) $actorId,
            'note'             => (string) (isset($row['note']) ? $row['note'] : ''),
        ));
        return self::done(array('close_id' => (int) $id, 'close_kind' => self::KIND_MONTHLY));
    }

    /**
     * ضمُّ إقفالٍ تعاقديٍّ إلى إقفالِ شهرِه — **ربطٌ لا دمج**.
     * ⛔ والأبُ لا يكون من صنفِ ابنِه، والقاعدةُ تردُّ الرابطَ الذاتيَّ.
     */
    public static function linkContractCloseToMonth(TenantDb $gate, $monthlyId, $contractCloseId, $why)
    {
        $m = $gate->selectOne('fin_monthly_close', array('where' => array('id' => (int) $monthlyId)));
        $k = $gate->selectOne('fin_contract_close', array('where' => array('id' => (int) $contractCloseId)));
        if (!$m || !$k) { return self::fail('CLOSE_NOT_FOUND', ''); }
        if ((int) $m['op_id'] !== (int) $k['op_id']) {
            return self::fail('LINK_ACROSS_OPERATIONS', 'الشهر يضم اقفالات عمليته لا عملية اخرى');
        }
        if ((string) $m['state'] === 'approved') {
            return self::fail('LINK_AFTER_APPROVAL', 'لا يضم الى شهر معتمد');
        }
        $exists = $gate->selectOne('fin_close_link', array('where' => array(
            'parent_kind' => self::KIND_MONTHLY, 'parent_id' => (int) $monthlyId,
            'child_kind' => self::KIND_CONTRACTUAL, 'child_id' => (int) $contractCloseId)));
        if (!$exists) {
            $gate->insert('fin_close_link', array(
                'parent_kind' => self::KIND_MONTHLY, 'parent_id' => (int) $monthlyId,
                'child_kind'  => self::KIND_CONTRACTUAL, 'child_id' => (int) $contractCloseId,
                'link_rule'   => 'W12_MONTH_AGGREGATES_CONTRACT',
                'why'         => (string) ($why !== '' ? $why : 'الشهر يضم اقفالات فتراته التعاقدية ولا يحل محلها'),
            ));
        }
        $n = (int) $gate->count('fin_close_link', array('where' => array(
            'parent_kind' => self::KIND_MONTHLY, 'parent_id' => (int) $monthlyId,
            'child_kind' => self::KIND_CONTRACTUAL)));
        $gate->update('fin_monthly_close', array('contract_closes_n' => $n), array('id' => (int) $monthlyId));
        return self::done(array('links' => $n));
    }

    /** ⛔ **من أعدَّ الشهريَّ لا يعتمده** — ولا اعتمادَ بلا تعاقديٍّ مربوط */
    public static function approveMonthlyClose(TenantDb $gate, $closeId, $actorId)
    {
        $m = $gate->selectOne('fin_monthly_close', array('where' => array('id' => (int) $closeId)));
        if (!$m) { return self::fail('CLOSE_NOT_FOUND', ''); }
        if ((string) $m['state'] === 'approved') { return self::done(array('idempotent' => 1)); }
        if ((int) $m['prepared_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY', 'من اعد الاقفال الشهري لا يعتمده');
        }
        $n = (int) $gate->count('fin_close_link', array('where' => array(
            'parent_kind' => self::KIND_MONTHLY, 'parent_id' => (int) $closeId,
            'child_kind' => self::KIND_CONTRACTUAL)));
        if ($n === 0) {
            return self::fail('MONTHLY_WITHOUT_CONTRACT_CLOSE',
                              'الشهري يضم اقفالات تعاقدية ولا ينشئ معناها وحده');
        }
        $gate->update('fin_monthly_close', array(
            'state' => 'approved', 'contract_closes_n' => $n,
            'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $closeId));
        self::emit($gate, 'fin.monthly.closed', array('close_id' => (int) $closeId));
        return self::done(array('close_id' => (int) $closeId, 'links' => $n));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ **الإقفالُ النهائيّ** — كيانُه وحدَه مرّةً لعمليةٍ لا غير
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ⚠ **الكيانُ يُمرَّر صراحةً هنا** — لا يُستنتَج: موقفُ العمليةِ يُقاس من
     *   المجالِ المقيَّدِ (`financing_installments` · `financing_deviations`)،
     *   وبابُه `FinancingService` بمستدعٍ مخوَّلٍ يمرّر `mysqli` و`company_id`.
     *   وبوّابةُ المستأجرِ **لا تخدم هذا المجال** (‏`T_RESTRICTED` · N-15).
     */
    public static function requestFinalClose(TenantDb $gate, $companyId, array $row, $actorId)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) { return self::fail('CLOSE_WITHOUT_ENTITY', 'لا اقفال بلا كيان قانوني'); }
        $opId = (int) (isset($row['op_id']) ? $row['op_id'] : 0);
        if ($opId <= 0) { return self::fail('CLOSE_WITHOUT_OPERATION', ''); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('CLOSE_WITHOUT_CURRENCY', ''); }
        $dup = $gate->selectOne('fin_final_close', array('where' => array('op_id' => $opId)));
        if ($dup) {
            return self::fail('FINAL_CLOSE_ALREADY_EXISTS', 'الاقفال النهائي مرة واحدة لعملية واحدة');
        }
        $rc = self::restrictedConn();
        if (!$rc) { return self::fail('RESTRICTED_DOMAIN_UNAVAILABLE', 'لا اتصال بالمجال المقيد'); }
        /* **موقفُ العمليةِ يُقاس لا يُدَّعى**: استحقاقاتٌ مفتوحةٌ وانحرافاتٌ حاجبة */
        $openDues = FinancingService::openDuesCount($rc, $companyId, $opId);
        $openDev  = FinancingService::openBlockingDeviations($rc, $companyId, (string) $opId);
        $last     = FinancingService::lastPeriodicClose($rc, $companyId, $opId);

        $id = $gate->insert('fin_final_close', array(
            'close_kind'  => self::KIND_FINAL,
            'close_code'  => (string) (isset($row['close_code']) ? $row['close_code']
                                       : ('FFIN-' . substr(sha1(microtime(true) . 'f'), 0, 10))),
            'op_id'       => $opId,
            'entity_id'   => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'currency'    => $cur,
            'requested_on' => date('Y-m-d'),
            'last_periodic_close_id' => $last ? (int) $last['id'] : 0,
            'last_payment_ref' => (string) (isset($row['last_payment_ref']) ? $row['last_payment_ref'] : ''),
            'residual_principal' => $last ? (float) $last['close_principal'] : 0,
            'residual_profit'    => $last ? (float) $last['close_profit'] : 0,
            'open_dues_n' => $openDues,
            'open_deviations_n' => $openDev,
            'ownership_transferred' => (int) (isset($row['ownership_transferred']) ? $row['ownership_transferred'] : 0),
            'ownership_doc_ref' => (string) (isset($row['ownership_doc_ref']) ? $row['ownership_doc_ref'] : ''),
            'clearance_doc_ref' => (string) (isset($row['clearance_doc_ref']) ? $row['clearance_doc_ref'] : ''),
            'early_settlement_ref' => (string) (isset($row['early_settlement_ref']) ? $row['early_settlement_ref'] : ''),
            'state'       => 'requested',
            'prepared_by' => (int) $actorId,
        ));
        if ($last) {
            $gate->insert('fin_close_link', array(
                'parent_kind' => self::KIND_FINAL, 'parent_id' => (int) $id,
                'child_kind'  => self::KIND_CONTRACTUAL, 'child_id' => (int) $last['id'],
                'link_rule'   => 'W12_FINAL_READS_LAST_PERIODIC',
                'why'         => 'النهائي يقرا اخر اقفال دوري ولا يحل محله',
            ));
        }
        return self::done(array('close_id' => (int) $id, 'close_kind' => self::KIND_FINAL,
                                'open_dues' => $openDues, 'open_deviations' => $openDev));
    }

    /**
     * ⛔ **لا إقفالَ نهائيَّ باستحقاقٍ مفتوحٍ ولا بانحرافٍ حاجبٍ ولا بلا إخلاءِ طرف**
     * ⛔ **ومن أعدَّه لا يعتمده.**
     */
    public static function approveFinalClose(TenantDb $gate, $closeId, $actorId)
    {
        $f = $gate->selectOne('fin_final_close', array('where' => array('id' => (int) $closeId)));
        if (!$f) { return self::fail('CLOSE_NOT_FOUND', ''); }
        if ((string) $f['state'] === 'approved') { return self::done(array('idempotent' => 1)); }
        if ((int) $f['prepared_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_FINAL', 'من طلب الاقفال النهائي لا يعتمده');
        }
        if ((int) $f['open_dues_n'] > 0) {
            return self::fail('FINAL_CLOSE_WITH_OPEN_DUES', 'استحقاقات مفتوحة تمنع الاقفال النهائي');
        }
        if ((int) $f['open_deviations_n'] > 0) {
            return self::fail('FINAL_CLOSE_WITH_OPEN_DEVIATION', 'انحراف حاجب مفتوح يمنع الاقفال النهائي');
        }
        if (trim((string) $f['clearance_doc_ref']) === '') {
            return self::fail('FINAL_CLOSE_WITHOUT_CLEARANCE', 'لا اقفال نهائي بلا اخلاء طرف او شهادة');
        }
        if ((int) $f['last_periodic_close_id'] === 0) {
            return self::fail('FINAL_CLOSE_WITHOUT_PERIODIC', 'النهائي يقرا اخر اقفال دوري ولا يخترعه');
        }
        $gate->update('fin_final_close', array(
            'state' => 'approved', 'closed_on' => date('Y-m-d'),
            'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $closeId));
        self::emit($gate, 'fin.final.closed', array('close_id' => (int) $closeId));
        return self::done(array('close_id' => (int) $closeId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ **نموذجُ أمرِ الدفعِ المستقبليّ** — طبقةُ `FUTURE` وحدَها
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * أمرُ دفعٍ مستقبليّ. **حقولُ نموذجِه مطلوبةٌ بحقِّها لا بما يستطيعه
     * التاريخيّ**: طالبٌ · تاريخُ طلبٍ · مبلغٌ · عملة — أربعةٌ لا يملكها الصفُّ
     * المجمَّعُ التاريخيّ، **ولا تُخفَّض من أجلِه**.
     */
    public static function requestPaymentOrder(TenantDb $gate, array $row, $actorId)
    {
        $amount = (float) (isset($row['requested_amount']) ? $row['requested_amount'] : 0);
        if ($amount <= 0) { return self::fail('ORDER_AMOUNT_INVALID', ''); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('ORDER_WITHOUT_CURRENCY', ''); }
        if ((int) $actorId <= 0) { return self::fail('ORDER_WITHOUT_REQUESTER', 'امر الدفع بلا طالب'); }
        $opId = (int) (isset($row['op_id']) ? $row['op_id'] : 0);
        if ($opId <= 0) { return self::fail('ORDER_WITHOUT_OPERATION', ''); }
        /* ⛔ **ولا يُقبَل صفٌّ تاريخيٌّ من هذا الباب** — والقاعدةُ تردُّه أيضًا */
        $layer = strtoupper(trim((string) (isset($row['source_kind']) ? $row['source_kind'] : self::LAYER_FUTURE)));
        if ($layer !== self::LAYER_FUTURE) {
            return self::fail('LEGACY_AGGREGATE_AS_ORDER',
                              'الطبقة التاريخية المجمعة تدخل من بابها لا من نموذج امر الدفع');
        }
        $id = $gate->insert('fin_payment_order', array(
            'source_kind'      => self::LAYER_FUTURE,
            'order_code'       => (string) (isset($row['order_code']) ? $row['order_code']
                                            : ('FPAYO-' . substr(sha1(microtime(true) . 'p'), 0, 10))),
            'op_id'            => $opId,
            'entity_id'        => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'requested_at'     => date('Y-m-d H:i:s'),
            'requested_by'     => (int) $actorId,
            'requested_amount' => $amount,
            'currency'         => $cur,
            'state'            => 'requested',
        ));
        return self::done(array('order_id' => (int) $id));
    }

    /** ⛔ **من طلب أمرَ الدفعِ لا يعتمده** */
    public static function approvePaymentOrder(TenantDb $gate, $orderId, $approvedAmount, $actorId)
    {
        $o = $gate->selectOne('fin_payment_order', array('where' => array('id' => (int) $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }
        if ((string) $o['state'] !== 'requested') { return self::fail('ORDER_NOT_REQUESTED', ''); }
        if ((int) $o['requested_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_REQUEST_AND_APPROVE_ORDER', 'من طلب امر الدفع لا يعتمده');
        }
        $amt = (float) $approvedAmount;
        if ($amt <= 0) { return self::fail('ORDER_APPROVED_AMOUNT_INVALID', ''); }
        if ($amt > (float) $o['requested_amount'] + 0.005) {
            return self::fail('ORDER_APPROVED_ABOVE_REQUEST', 'المعتمد لا يتجاوز المطلوب');
        }
        $gate->update('fin_payment_order', array(
            'state' => 'approved', 'approved_amount' => $amt,
            'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $orderId));
        self::emit($gate, 'fin.order.approved', array('order_id' => (int) $orderId));
        return self::done(array('order_id' => (int) $orderId));
    }

    /**
     * تنفيذُ أمرِ الدفع. **ويصدر عنه طلبُ اعترافٍ إلى الماليّةِ** (§48) —
     * ⛔ ولا يُكتب قيدٌ هنا: الماليّةُ تقرّر وتثبّت.
     */
    public static function executePaymentOrder(TenantDb $gate, $orderId, array $exec, $actorId)
    {
        $o = $gate->selectOne('fin_payment_order', array('where' => array('id' => (int) $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }
        if ((string) $o['state'] !== 'approved') {
            return self::fail('EXECUTE_WITHOUT_APPROVED_ORDER', 'لا تنفيذ لامر غير معتمد');
        }
        $bankRef = trim((string) (isset($exec['bank_ref']) ? $exec['bank_ref'] : ''));
        if ($bankRef === '') { return self::fail('EXECUTE_WITHOUT_BANK_REF', 'التنفيذ بلا مرجع بنكي'); }
        $method = trim((string) (isset($exec['method']) ? $exec['method'] : ''));
        if ($method === '') { return self::fail('EXECUTE_WITHOUT_METHOD', 'التنفيذ بلا طريقة سداد'); }
        $amt = (float) (isset($exec['executed_amount']) ? $exec['executed_amount'] : $o['approved_amount']);
        if ($amt <= 0) { return self::fail('EXECUTE_AMOUNT_INVALID', ''); }

        /* **بابُ الاعترافِ الواحد** — النطاقُ يطلب والماليّةُ تقرّر (W11) */
        $reqId = 0;
        $svc = \dirname(\dirname(__DIR__)) . '/Services/Finance/AccountingCycleService.php';
        if (\is_file($svc)) {
            require_once $svc;
            $rr = \App\Services\Finance\AccountingCycleService::requestRecognition($gate, array(
                'source_module' => 'financing',
                'source_screen' => 'Financing/fin_payment_orders.php',
                'source_ref'    => 'FPAYO:' . (string) $o['order_code'],
                'source_doc_id' => (int) $orderId,
                'event_type'    => 'payable',
                'amount'        => $amt,
                'currency'      => (string) $o['currency'],
            ), $actorId);
            if (!empty($rr['ok'])) { $reqId = (int) $rr['request_id']; }
        }
        if ($reqId <= 0) {
            return self::fail('EXECUTE_WITHOUT_RECOGNITION_REQUEST',
                              'التنفيذ يصدر طلب اعتراف الى المالية ولا يكتب قيدا');
        }
        $gate->update('fin_payment_order', array(
            'state' => 'executed', 'executed_on' => date('Y-m-d'), 'executed_amount' => $amt,
            'method' => $method, 'bank_ref' => $bankRef,
            'treasury_ref' => (string) (isset($exec['treasury_ref']) ? $exec['treasury_ref'] : ''),
            'recognition_request_id' => $reqId,
        ), array('id' => (int) $orderId));
        self::emit($gate, 'fin.order.executed', array('order_id' => (int) $orderId,
                                                      'recognition_request_id' => $reqId));
        return self::done(array('order_id' => (int) $orderId, 'recognition_request_id' => $reqId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑦ **الطبقةُ التاريخيّةُ المجمَّعة** — بابُها وحدَه وحجّيّتُها شرط
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * إدخالُ صفٍّ تاريخيٍّ مجمَّع. **لا يدخل نموذجَ أمرِ الدفعِ أبدًا**، ولا
     * يُخصَّص على قسطٍ، و**حجّيّتُه ومرجعُ صفِّه الأصليُّ شرطُ قبولٍ** — فرقمٌ
     * تاريخيٌّ بلا سندٍ يُقرأ حقيقةً وهو دعوى.
     */
    public static function ingestLegacyAggregate(TenantDb $gate, array $row, $actorId)
    {
        $grade = trim((string) (isset($row['evidence_grade']) ? $row['evidence_grade'] : ''));
        if ($grade === '') { return self::fail('LEGACY_WITHOUT_EVIDENCE_GRADE', 'الصف التاريخي بلا حجية'); }
        $srcRef = trim((string) (isset($row['source_row_ref']) ? $row['source_row_ref'] : ''));
        if ($srcRef === '') { return self::fail('LEGACY_WITHOUT_SOURCE_REF', 'الصف التاريخي بلا مرجع صفه'); }
        $opId = (int) (isset($row['op_id']) ? $row['op_id'] : 0);
        if ($opId <= 0) { return self::fail('LEGACY_WITHOUT_OPERATION', ''); }
        $id = $gate->insert('fin_legacy_payment_aggregate', array(
            'layer'          => self::LAYER_LEGACY,
            'op_id'          => $opId,
            'entity_id'      => (int) (isset($row['entity_id']) ? $row['entity_id'] : 0),
            'period_label'   => (string) (isset($row['period_label']) ? $row['period_label'] : ''),
            'paid_aggregate' => (float) (isset($row['paid_aggregate']) ? $row['paid_aggregate'] : 0),
            'ledger_rows'    => (int) (isset($row['ledger_rows']) ? $row['ledger_rows'] : 0),
            'currency'       => (string) (isset($row['currency']) ? $row['currency'] : ''),
            'evidence_grade' => $grade,
            'source_row_ref' => $srcRef,
            'data_state'     => 'legacy',
            'allocatable'    => 0,
            'note'           => (string) (isset($row['note']) ? $row['note'] : ''),
        ));
        return self::done(array('legacy_id' => (int) $id, 'layer' => self::LAYER_LEGACY));
    }

    /**
     * تخصيصُ سدادٍ على قسط. ⛔ **من أمرِ دفعٍ منفَّذٍ وحدَه** — والصفُّ
     * المجمَّعُ التاريخيُّ لا يُخصَّص ولو حمل مبلغًا.
     */
    public static function allocatePayment(TenantDb $gate, $orderId, array $lines, $actorId)
    {
        $o = $gate->selectOne('fin_payment_order', array('where' => array('id' => (int) $orderId)));
        if (!$o) {
            return self::fail('LEGACY_AGGREGATE_AS_ORDER', 'التخصيص من امر دفع لا من صف مجمع');
        }
        if ((string) $o['source_kind'] !== self::LAYER_FUTURE) {
            return self::fail('LEGACY_AGGREGATE_AS_ORDER', 'الطبقة التاريخية لا تخصص كامر');
        }
        if ((string) $o['state'] !== 'executed') {
            return self::fail('ALLOCATE_BEFORE_EXECUTION', 'لا تخصيص قبل التنفيذ');
        }
        $sum = 0.0;
        foreach ($lines as $l) { $sum += (float) (isset($l['amount']) ? $l['amount'] : 0); }
        if ($sum <= 0) { return self::fail('ALLOCATION_EMPTY', 'لا سطر تخصيص'); }
        if ($sum > (float) $o['executed_amount'] + 0.005) {
            return self::fail('ALLOCATION_ABOVE_EXECUTED', 'مجموع التخصيص يتجاوز المنفذ');
        }
        $n = 0;
        foreach ($lines as $l) {
            $amt = (float) (isset($l['amount']) ? $l['amount'] : 0);
            if ($amt <= 0) { continue; }
            $gate->insert('fin_payment_allocation', array(
                'order_id'       => (int) $orderId,
                'installment_id' => (int) (isset($l['installment_id']) ? $l['installment_id'] : 0),
                'close_kind'     => (string) (isset($l['close_kind']) ? $l['close_kind'] : self::KIND_CONTRACTUAL),
                'close_id'       => (int) (isset($l['close_id']) ? $l['close_id'] : 0),
                'amount'         => $amt,
                'part_kind'      => (string) (isset($l['part_kind']) ? $l['part_kind'] : 'principal'),
                'allocated_by'   => (int) $actorId,
                'note'           => (string) (isset($l['note']) ? $l['note'] : ''),
            ));
            $n++;
        }
        $gate->update('fin_payment_order', array('match_state' => 'matched'), array('id' => (int) $orderId));
        self::emit($gate, 'fin.payment.allocated', array('order_id' => (int) $orderId, 'lines' => $n));
        return self::done(array('order_id' => (int) $orderId, 'lines' => $n));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑧ الحوكمةُ — الانحرافُ يُرفَع ويُحسَم بيدَين
       ══════════════════════════════════════════════════════════════════════ */

    /** ⚠ الانحرافُ في المجالِ المقيَّد — الكيانُ يُمرَّر ولا يُستنتَج */
    public static function raiseDeviation($companyId, array $row, $actorId)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) { return self::fail('DEVIATION_WITHOUT_ENTITY', 'لا انحراف بلا كيان قانوني'); }
        $ref = trim((string) (isset($row['subject_ref']) ? $row['subject_ref'] : ''));
        if ($ref === '') { return self::fail('DEVIATION_WITHOUT_SUBJECT', ''); }
        $rc = self::restrictedConn();
        if (!$rc) { return self::fail('RESTRICTED_DOMAIN_UNAVAILABLE', 'لا اتصال بالمجال المقيد'); }
        /* ⛔ **الرافعُ لا يُكتب في حقلِ الحاسم**: `closed_by` عمودُ من حسم،
             وملؤه عند الرفعِ يجعل الرافعَ حاسمًا في القراءةِ ويفكُّ فصلَ
             الواجباتِ بلا خطأٍ يظهر — فيبقى صفرًا حتّى يقع الحسمُ فعلًا. */
        $id = FinancingService::raiseDeviation($rc, $companyId, $row);
        if ($id <= 0) { return self::fail('DEVIATION_NOT_WRITTEN', ''); }
        self::emitRaw($companyId, 'fin.deviation.raised',
            array('deviation_id' => (int) $id, 'raised_by' => (int) $actorId));
        return self::done(array('deviation_id' => (int) $id));
    }

    public static function resolveDeviation($companyId, $devId, $decision, $docRef, $actorId, $raisedBy = 0)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) { return self::fail('DEVIATION_WITHOUT_ENTITY', ''); }
        if (trim((string) $decision) === '') {
            return self::fail('DEVIATION_RESOLVED_WITHOUT_DECISION', 'الحسم بلا قرار مكتوب');
        }
        if ((int) $raisedBy > 0 && (int) $raisedBy === (int) $actorId) {
            return self::fail('SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION', 'من رفع الانحراف لا يحسمه');
        }
        $rc = self::restrictedConn();
        if (!$rc) { return self::fail('RESTRICTED_DOMAIN_UNAVAILABLE', 'لا اتصال بالمجال المقيد'); }
        $r = FinancingService::closeDeviation($rc, $companyId, (int) $devId,
                                              (string) $decision, (string) $docRef, (int) $actorId);
        if (empty($r['ok'])) {
            return self::fail('DEVIATION_NOT_RESOLVED', (string) (isset($r['reason']) ? $r['reason'] : ''));
        }
        return self::done(array('deviation_id' => (int) $devId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑨ المستهلكون — كلٌّ يترك أثرًا تجاريًّا مقيسًا (§46)
       ══════════════════════════════════════════════════════════════════════
       ⛔ **ولا استعلامَ خامٍّ على جدولِ مستأجِرٍ هنا** (`GAP-29`): عقدُ المستهلكِ
         يمرّر `mysqli` لا `TenantDb`، فتُبنى البوّابةُ من **كيانِ الحدثِ نفسِه**
         — والكيانُ يُقرأ من الحمولةِ لا يُخمَّن (‏درسُ W11).
       ══════════════════════════════════════════════════════════════════════ */

    private static function gateOf(\mysqli $conn, $companyId)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) { return null; }
        $core = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/';
        require_once $core . 'TenantGateException.php';
        require_once $core . 'TenantRegistry.php';
        require_once $core . 'TenantContext.php';
        require_once $core . 'TenantDb.php';
        try {
            return new \App\Core\TenantDb($conn,
                \App\Core\TenantContext::forSystem($companyId, 0, '', true));
        } catch (\Throwable $t) { return null; }
    }

    /** توقيعُ العقدِ يربط العمليةَ بعقدِها فتقرأ العمليةُ سندَها */
    public function onContractSigned(array $event, \mysqli $conn)
    {
        $cid = (int) self::payloadOf($event, 'contract_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($cid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $c = $g->selectOne('fin_finance_contract', array('where' => array('id' => $cid)));
        if (!$c || (int) $c['op_id'] <= 0) { return 'W12:NO_OPERATION'; }
        FinancingService::linkOperationContract($conn, $co, (int) $c['op_id'], $cid);
        return 'W12:OPERATION_LINKED:' . (int) $c['op_id'];
    }

    /** توليدُ الجدولِ يجعل لكلِّ قسطٍ فترتَه التعاقديّة */
    public function onScheduleGenerated(array $event, \mysqli $conn)
    {
        $opId = (int) self::payloadOf($event, 'op_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($opId <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $n = 0;
        foreach ($g->select('fin_contract_close', array('where' => array('op_id' => $opId),
                 'limit' => 500)) as $k) {
            $n += FinancingService::sealInstallmentsForPeriod($conn, $co, $opId, (int) $k['id'],
                                                              (string) $k['period_start'],
                                                              (string) $k['period_end']);
        }
        return 'W12:INSTALLMENTS_MAPPED:' . $n;
    }

    /** الاعتمادُ يفتح بابَ التنفيذِ ولا ينفّذ — والأثرُ حالةُ الأمر */
    public function onOrderApproved(array $event, \mysqli $conn)
    {
        $oid = (int) self::payloadOf($event, 'order_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($oid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $o = $g->selectOne('fin_payment_order', array('where' => array('id' => $oid)));
        if (!$o) { return 'W12:ORDER_MISSING'; }
        $g->update('fin_payment_order', array('match_state' => 'unmatched'), array('id' => $oid));
        return 'W12:ORDER_READY_TO_EXECUTE:' . (string) $o['approved_amount'];
    }

    /** التنفيذُ يرفع المدفوعَ على العملية — والرصيدُ مشتقٌّ من الحركة */
    public function onOrderExecuted(array $event, \mysqli $conn)
    {
        $oid = (int) self::payloadOf($event, 'order_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($oid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $o = $g->selectOne('fin_payment_order', array('where' => array('id' => $oid)));
        if (!$o) { return 'W12:ORDER_MISSING'; }
        $out = FinancingService::applyExecutedPayment($conn, $co, (int) $o['op_id'],
                                                      (float) $o['executed_amount']);
        if ($out === null) { return 'W12:OPERATION_MISSING'; }
        return 'W12:OUTSTANDING_UPDATED:' . $out;
    }

    /** التخصيصُ يحرّك المخصَّصَ على القسطِ وحالتَه */
    public function onPaymentAllocated(array $event, \mysqli $conn)
    {
        $oid = (int) self::payloadOf($event, 'order_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($oid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $targets = array();
        foreach ($g->select('fin_payment_allocation',
                 array('where' => array('order_id' => $oid), 'limit' => 2000)) as $a) {
            $iid = (int) $a['installment_id'];
            if ($iid > 0) { $targets[$iid] = true; }
        }
        $n = 0;
        foreach (array_keys($targets) as $iid) {
            /* **المخصَّصُ مشتقٌّ من مجموعِ سطورِه لا من سطرٍ واحد** — والقسطُ
               يُقفَل بتغطيتِه. والاشتقافُ عند بابِ المجالِ المقيَّد. */
            if (FinancingService::recomputeInstallmentAllocation($conn, $co, $iid) !== null) { $n++; }
        }
        return 'W12:INSTALLMENTS_ALLOCATED:' . $n;
    }

    /** الإقفالُ التعاقديُّ يُثبّت رصيدَ الفترةِ فيصير افتتاحيَّ التالية */
    public function onContractClosed(array $event, \mysqli $conn)
    {
        $cid = (int) self::payloadOf($event, 'close_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($cid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $k = $g->selectOne('fin_contract_close', array('where' => array('id' => $cid)));
        if (!$k) { return 'W12:CLOSE_MISSING'; }
        $n = FinancingService::sealInstallmentsForPeriod($conn, $co, (int) $k['op_id'], $cid,
                                                         (string) $k['period_start'],
                                                         (string) $k['period_end']);
        return 'W12:PERIOD_INSTALLMENTS_SEALED:' . $n;
    }

    /** الشهريُّ يُثبّت عددَ التعاقديّاتِ التي ضمَّها — ولا يخترع معناها */
    public function onMonthlyClosed(array $event, \mysqli $conn)
    {
        $cid = (int) self::payloadOf($event, 'close_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($cid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $n = (int) $g->count('fin_close_link', array('where' => array(
            'parent_kind' => self::KIND_MONTHLY, 'parent_id' => $cid, 'child_kind' => self::KIND_CONTRACTUAL)));
        $g->update('fin_monthly_close', array('contract_closes_n' => $n), array('id' => $cid));
        return 'W12:MONTH_AGGREGATED:' . $n;
    }

    /** النهائيُّ يُقفل العمليةَ ويربطها بإقفالِها — والعمليةُ تقرأ ختامَها */
    public function onFinalClosed(array $event, \mysqli $conn)
    {
        $cid = (int) self::payloadOf($event, 'close_id');
        $co = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($cid <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $f = $g->selectOne('fin_final_close', array('where' => array('id' => $cid)));
        if (!$f) { return 'W12:CLOSE_MISSING'; }
        FinancingService::closeOperationByFinal($conn, $co, (int) $f['op_id'], $cid);
        return 'W12:OPERATION_CLOSED:' . (int) $f['op_id'];
    }

    /** الانحرافُ المرفوعُ يحجب الإقفالَ النهائيَّ ما دام مفتوحًا */
    public function onDeviationRaised(array $event, \mysqli $conn)
    {
        $did = (int) self::payloadOf($event, 'deviation_id');
        $co = (int) self::payloadOf($event, 'company_id');
        if ($did <= 0) { return 'W12:SKIP:NO_ID'; }
        if ($co <= 0) { return 'W12:NO_ENTITY'; }
        $n = FinancingService::openBlockingDeviations($conn, $co);
        return 'W12:BLOCKING_DEVIATIONS:' . $n;
    }

    /** انتقالُ الملكيّةِ يُوسَم في الإقفالِ النهائيِّ بمستندِه */
    public function onOwnershipTransferred(array $event, \mysqli $conn)
    {
        $opId = (int) self::payloadOf($event, 'op_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($opId <= 0) { return 'W12:SKIP:NO_ID'; }
        if (!$g) { return 'W12:NO_ENTITY'; }
        $f = $g->selectOne('fin_final_close', array('where' => array('op_id' => $opId)));
        if (!$f) { return 'W12:NO_FINAL_CLOSE'; }
        $doc = (string) self::payloadOf($event, 'ownership_doc_ref');
        if ($doc === '') { return 'W12:OWNERSHIP_WITHOUT_DOC'; }
        $g->update('fin_final_close',
            array('ownership_transferred' => 1, 'ownership_doc_ref' => $doc),
            array('id' => (int) $f['id']));
        return 'W12:OWNERSHIP_SEALED:' . (int) $f['id'];
    }

    /* ══════════════════════════════════════════════════════════════════════
       أدواتٌ داخليّة
       ══════════════════════════════════════════════════════════════════════ */

    private static function payloadOf(array $event, $key)
    {
        if (isset($event['payload'])) {
            $p = $event['payload'];
            if (is_string($p)) { $p = json_decode($p, true); }
            if (is_array($p) && isset($p[$key])) { return $p[$key]; }
        }
        return isset($event[$key]) ? $event[$key] : 0;
    }

    const EVENT_ENTITY = array(
        'fin.contract.signed'      => array('fin_finance_contract', 'contract_id'),
        'fin.schedule.generated'   => array('financing_operations', 'op_id'),
        'fin.order.approved'       => array('fin_payment_order', 'order_id'),
        'fin.order.executed'       => array('fin_payment_order', 'order_id'),
        'fin.payment.allocated'    => array('fin_payment_order', 'order_id'),
        'fin.contract.closed'      => array('fin_contract_close', 'close_id'),
        'fin.monthly.closed'       => array('fin_monthly_close', 'close_id'),
        'fin.final.closed'         => array('fin_final_close', 'close_id'),
        'fin.deviation.raised'     => array('financing_deviations', 'deviation_id'),
        'fin.ownership.transferred' => array('financing_operations', 'op_id'),
    );

    private static function emit(TenantDb $gate, $eventKey, array $payload)
    {
        if (!isset(self::EVENT_ENTITY[$eventKey])) { return null; }
        list($table, $idKey) = self::EVENT_ENTITY[$eventKey];
        $entityId = isset($payload[$idKey]) ? (int) $payload[$idKey] : 0;
        /* ⚠ **الكيانُ يُقرأ من صفِّه عبر البوّابةِ ثمَّ يُحمَل في الحمولة** —
             ولا يُستنتَج عند المستهلك (‏`GAP-29` · درسُ W11). */
        $company = 0;
        try {
            $row = $gate->selectOne($table, array('columns' => array('company_id'),
                                                  'where' => array('id' => $entityId)));
            if ($row) { $company = (int) $row['company_id']; }
        } catch (\Throwable $t) { $company = 0; }
        return self::emitRaw($company, $eventKey, $payload);
    }

    /**
     * نشرٌ بكيانٍ مُمرَّرٍ صراحةً — لواقعةٍ في **المجالِ المقيَّد** الذي لا
     * تخدمه بوّابةُ المستأجر، فيُعلَن كيانُها ولا يُقرأ بجسرٍ لا يملكه.
     */
    private static function emitRaw($companyId, $eventKey, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!isset(self::EVENT_ENTITY[$eventKey])) { return null; }
        list($table, $idKey) = self::EVENT_ENTITY[$eventKey];
        $entityId = isset($payload[$idKey]) ? (int) $payload[$idKey] : 0;

        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;

        $company = (int) $companyId;
        $payload['company_id'] = $company;
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'financing',
                'source_module'   => 'financing',
                'entity_type'     => $table,
                'entity_id'       => $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w12:' . $eventKey . ':' . $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'FinancingCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
