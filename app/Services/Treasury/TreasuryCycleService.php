<?php
/**
 * TreasuryCycleService — دورةُ الخزينة: قبضٌ ثمَّ تخصيص · وصرفٌ بعد اعتماد
 * ═══════════════════════════════════════════════════════════════════════════
 * **`Receipt ← Allocation` و`Bank Reconciliation ← Difference Lines`** (‏§٤-٦).
 *
 * ◆ **الخزينةُ تنفّذ ولا تعترف**: الطلبُ لا ينشأ هنا — ينشأ عند الإدارةِ مالكةِ
 *   الاستحقاقِ ويصل **إسقاطًا** (`TRS-08` نصًّا). و`executePayment` تردُّ
 *   `EXECUTE_WITHOUT_APPROVED_REQUEST` على أمرٍ بلا طلبٍ مكتملِ الاعتماد.
 *
 * ◆ **ولا دفعَ لمستفيدٍ غيرِ محقَّق** (`TRS-03`): `BENEFICIARY_NOT_VERIFIED`،
 *   وحسابُه البنكيُّ **يُقفل ضدَّ التعديل** بعد التحقّقِ فلا يُبدَّل صامتًا.
 *
 * ◆ **وفرقُ الصرفِ حركةٌ مستقلّةٌ لا تعديلٌ صامت** (`TRS-10`): كلُّ فرقٍ سطرُ
 *   حركةٍ بوسمِه، والرصيدُ الدفتريُّ **مشتقٌّ من الحركاتِ لا مكتوبٌ بيد**.
 *
 * ◆ **والجردُ بلجنةٍ لا بأمينٍ وحدَه** (`TRS-18`): `COUNT_WITHOUT_COMMITTEE`
 *   و`SAME_ACTOR_COUNT_AND_APPROVE` — والفرقُ يُعالَج بمساره لا يُدفَن.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w11_thresholds`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Treasury;

use App\Core\TenantDb;

class TreasuryCycleService
{
    const VESSEL_BANK = 'bank';
    const VESSEL_BOX  = 'cash_box';

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
                $r = @$c->query("SELECT threshold_key, value_num FROM repair01_w11_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return isset(self::$th[$key]) ? self::$th[$key] : null;
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
       ① دورةُ القبضِ — سندٌ ثمَّ تخصيصٌ على الفواتير
       ══════════════════════════════════════════════════════════════════════ */

    /** سندُ قبضٍ واحدٌ — والفاتورةُ والذمّةُ عند الماليّةِ تُحدَّثانِ بالتخصيص */
    public static function recordReceipt(TenantDb $gate, array $row, $actorId)
    {
        $amount = (float) (isset($row['amount']) ? $row['amount'] : 0);
        if ($amount <= 0) { return self::fail('RECEIPT_AMOUNT_INVALID', 'المبلغ غير موجب'); }
        $cur = trim((string) (isset($row['currency']) ? $row['currency'] : ''));
        if ($cur === '') { return self::fail('RECEIPT_WITHOUT_CURRENCY', 'السند بلا عملة'); }
        $rate = (float) (isset($row['fx_rate']) ? $row['fx_rate'] : 1);
        if ($rate <= 0) { $rate = 1; }
        /* ⛔ **سندُ قبضٍ بلا مرجعٍ بنكيٍّ لا يُقبَل** — والقاعدةُ في القاعدةِ
             نفسِها (`ck_collection_bank_ref`)، فتُقرأ هنا **قبل** الكتابةِ ليُردَّ
             برمزٍ مقروءٍ لا بانفجارِ بوّابةِ العزل: الحارسُ الذي يظهر خطؤه
             استثناءً لا رمزًا يُقرأ عطبٌ في الرسالةِ لا في القاعدة. */
        $bankRef = trim((string) (isset($row['bank_ref']) ? $row['bank_ref'] : ''));
        if ($bankRef === '') {
            return self::fail('RECEIPT_WITHOUT_BANK_REF', 'سند القبض بلا مرجع بنكي موثق');
        }
        $id = $gate->insert('fin_payments', array(
            'payment_no'         => (string) (isset($row['payment_no']) ? $row['payment_no']
                                              : ('RCV-' . substr(sha1(microtime(true) . 'r'), 0, 10))),
            'direction'          => 'collection',
            'party_type'         => (string) (isset($row['party_type']) ? $row['party_type'] : 'customer'),
            'party_ref'          => (int) (isset($row['party_ref']) ? $row['party_ref'] : 0),
            'method'             => (string) (isset($row['method']) ? $row['method'] : 'bank'),
            'bank_ref'           => (string) (isset($row['bank_ref']) ? $row['bank_ref'] : ''),
            'received_on'        => date('Y-m-d'),
            'amount'             => $amount,
            'allocated_amount'   => 0,
            'unallocated_amount' => $amount,
            'currency'           => $cur,
            'fx_rate'            => $rate,
            'base_amount'        => round($amount * $rate, 2),
            'state'              => 'approved',
            'created_by'         => (int) $actorId,
        ));
        return self::done(array('receipt_id' => (int) $id));
    }

    /**
     * تخصيصُ التحصيلِ على الفواتير. **الدفعةُ الواحدةُ قد تغطّي عدّةَ فواتير،
     * وكلُّ تخصيصٍ سطر** — ولا يتجاوز مجموعُ التخصيصِ مبلغَ السند.
     */
    public static function allocateReceipt(TenantDb $gate, $receiptId, array $allocations, $actorId)
    {
        $receiptId = (int) $receiptId;
        $p = $gate->selectOne('fin_payments', array('where' => array('id' => $receiptId)));
        if (!$p) { return self::fail('RECEIPT_NOT_FOUND', ''); }
        if ((string) $p['direction'] !== 'collection') {
            return self::fail('ALLOCATE_ON_DISBURSEMENT', 'التخصيص على سند قبض لا على امر دفع');
        }
        $sum = 0.0;
        foreach ($allocations as $a) { $sum += (float) (isset($a['amount']) ? $a['amount'] : 0); }
        if ($sum <= 0) { return self::fail('ALLOCATION_EMPTY', 'لا سطر تخصيص'); }
        $already = (float) $p['allocated_amount'];
        if ($sum + $already > (float) $p['amount'] + 0.005) {
            return self::fail('ALLOCATION_EXCEEDS_RECEIPT', 'مجموع التخصيص يتجاوز مبلغ السند');
        }

        /* ⛔ **هدفُ التخصيصِ يتَّسق مع نوعِه** — والقاعدةُ في القاعدةِ نفسِها
             (`ck_alloc_target`): تخصيصٌ على فاتورةٍ يوجب `receivable_id` مساويًا
             `target_ref`، وما ليس فاتورةً يوجب خلوَّه. فتُقرأ هنا قبل الكتابةِ
             ليُردَّ برمزٍ مقروءٍ لا بانفجارِ بوّابةِ العزل. */
        foreach ($allocations as $a) {
            $kind = (string) (isset($a['target_kind']) ? $a['target_kind'] : 'invoice');
            $rid  = (int) (isset($a['receivable_id']) ? $a['receivable_id'] : 0);
            $ref  = (int) (isset($a['target_ref']) ? $a['target_ref'] : 0);
            if ($kind === 'invoice' && $rid <= 0) {
                return self::fail('ALLOCATION_TARGET_MISMATCH', 'تخصيص على فاتورة بلا ذمة');
            }
            if ($kind !== 'invoice' && ($rid > 0 || $ref <= 0)) {
                return self::fail('ALLOCATION_TARGET_MISMATCH', 'هدف التخصيص لا يتسق مع نوعه');
            }
        }

        $n = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($allocations, $receiptId, $p, $actorId, &$n) {
            foreach ($allocations as $a) {
                $amt = (float) (isset($a['amount']) ? $a['amount'] : 0);
                if ($amt <= 0) { continue; }
                $kind = (string) (isset($a['target_kind']) ? $a['target_kind'] : 'invoice');
                $rid  = (int) (isset($a['receivable_id']) ? $a['receivable_id'] : 0);
                $g->insert('fin_collection_allocations', array(
                    'payment_id'     => $receiptId,
                    'receivable_id'  => $kind === 'invoice' ? $rid : null,
                    'target_kind'    => $kind,
                    'target_ref'     => $kind === 'invoice' ? $rid
                                        : (int) (isset($a['target_ref']) ? $a['target_ref'] : 0),
                    'amount'         => $amt,
                    'pay_currency'   => (string) $p['currency'],
                    'target_currency' => (string) $p['currency'],
                    'amount_target'  => $amt,
                    'fx_rate_pay'    => (float) $p['fx_rate'],
                    'fx_rate_target' => (float) $p['fx_rate'],
                    'base_amount'    => round($amt * (float) $p['fx_rate'], 2),
                    'basis'          => 'explicit',
                    'note'           => 'W11 تخصيص تحصيل',
                    'created_by'     => (int) $actorId,
                ));
                $n++;
            }
        }, 'W11 تخصيص تحصيل على فواتير');

        $gate->update('fin_payments', array(
            'allocated_amount'   => $already + $sum,
            'unallocated_amount' => (float) $p['amount'] - $already - $sum,
        ), array('id' => $receiptId));
        self::emit($gate, 'tre.receipt.allocated', array('receipt_id' => $receiptId, 'lines' => $n));
        return self::done(array('receipt_id' => $receiptId, 'lines' => $n));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② دورةُ الصرفِ — لا تنفيذَ إلّا لطلبٍ استوفى اعتمادَه
       ══════════════════════════════════════════════════════════════════════ */

    /** التحقّقُ من المستفيد — والحسابُ يُقفل ضدَّ التعديلِ بعد توثيقِه */
    public static function verifyBeneficiary(TenantDb $gate, $beneficiaryId, $docRef, $actorId)
    {
        $beneficiaryId = (int) $beneficiaryId;
        $docRef = trim((string) $docRef);
        if ($docRef === '') { return self::fail('VERIFY_WITHOUT_DOC', 'التحقق بلا مصدر توثيق'); }
        $b = $gate->selectOne('tre_beneficiaries', array('where' => array('id' => $beneficiaryId)));
        if (!$b) { return self::fail('BENEFICIARY_NOT_FOUND', ''); }
        $gate->update('tre_beneficiaries', array(
            'verified_by' => (int) $actorId, 'verified_at' => date('Y-m-d H:i:s'),
            'verify_doc_ref' => $docRef, 'locked_at' => date('Y-m-d H:i:s'),
        ), array('id' => $beneficiaryId));
        return self::done(array('beneficiary_id' => $beneficiaryId));
    }

    /** ⛔ **الحسابُ المقفلُ لا يُبدَّل صامتًا** — التغييرُ يوجب توثيقًا جديدًا */
    public static function changeBeneficiaryAccount(TenantDb $gate, $beneficiaryId, array $data, $docRef, $actorId)
    {
        $beneficiaryId = (int) $beneficiaryId;
        $b = $gate->selectOne('tre_beneficiaries', array('where' => array('id' => $beneficiaryId)));
        if (!$b) { return self::fail('BENEFICIARY_NOT_FOUND', ''); }
        if (trim((string) $b['locked_at']) !== '' && trim((string) $docRef) === '') {
            return self::fail('BENEFICIARY_ACCOUNT_LOCKED', 'الحساب المحقق لا يعدل بلا توثيق جديد');
        }
        $set = array();
        foreach (array('bank_name', 'iban', 'account_no', 'currency') as $f) {
            if (isset($data[$f])) { $set[$f] = (string) $data[$f]; }
        }
        if (!$set) { return self::fail('NOTHING_TO_CHANGE', ''); }
        $set['verified_by'] = 0; $set['verified_at'] = null; $set['locked_at'] = null;
        $set['verify_doc_ref'] = trim((string) $docRef);
        $gate->update('tre_beneficiaries', $set, array('id' => $beneficiaryId));
        return self::done(array('beneficiary_id' => $beneficiaryId, 'reverify_required' => 1));
    }

    /**
     * تنفيذُ أمرِ الدفع. **ومَن أعدَّ لا ينفّذ**، ولا تنفيذَ لمستفيدٍ غيرِ
     * محقَّق، ولا تنفيذَ لطلبٍ لم يستوفِ اعتمادَه في نطاقِه المالك.
     */
    public static function executePayment(TenantDb $gate, $paymentId, $beneficiaryId, $vesselId, $actorId)
    {
        $paymentId = (int) $paymentId; $actorId = (int) $actorId;
        $p = $gate->selectOne('fin_payments', array('where' => array('id' => $paymentId)));
        if (!$p) { return self::fail('PAYMENT_NOT_FOUND', ''); }
        if ((string) $p['direction'] !== 'disbursement') {
            return self::fail('EXECUTE_ON_COLLECTION', 'التنفيذ على امر دفع لا على سند قبض');
        }
        if ((string) $p['state'] === 'executed') {
            return self::done(array('payment_id' => $paymentId, 'idempotent' => 1));
        }
        if ((string) $p['state'] !== 'approved') {
            return self::fail('EXECUTE_WITHOUT_APPROVED_REQUEST', 'لا تنفيذ لطلب لم يعتمد');
        }
        if ((int) $p['created_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_EXECUTE', 'من اعد الدفعة لا ينفذها');
        }
        $b = $gate->selectOne('tre_beneficiaries', array('where' => array('id' => (int) $beneficiaryId)));
        if (!$b) { return self::fail('BENEFICIARY_NOT_FOUND', ''); }
        if ((int) $b['verified_by'] <= 0 || trim((string) $b['verified_at']) === '') {
            return self::fail('BENEFICIARY_NOT_VERIFIED', 'لا دفع لمستفيد غير محقق');
        }
        $gate->update('fin_payments', array(
            'state' => 'executed', 'executed_by' => $actorId, 'paid_at' => date('Y-m-d H:i:s'),
        ), array('id' => $paymentId));
        self::emit($gate, 'tre.payment.executed', array('payment_id' => $paymentId,
            'vessel_id' => (int) $vesselId, 'beneficiary_id' => (int) $beneficiaryId));
        return self::done(array('payment_id' => $paymentId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ حركةُ الأوعيةِ والتحويلُ بينها
       ══════════════════════════════════════════════════════════════════════ */

    public static function recordMove(TenantDb $gate, array $row, $actorId)
    {
        $kind = (string) (isset($row['vessel_kind']) ? $row['vessel_kind'] : '');
        if (!in_array($kind, array(self::VESSEL_BANK, self::VESSEL_BOX), true)) {
            return self::fail('MOVE_VESSEL_INVALID', $kind);
        }
        $dir = (string) (isset($row['direction']) ? $row['direction'] : '');
        if (!in_array($dir, array('in', 'out'), true)) { return self::fail('MOVE_DIRECTION_INVALID', $dir); }
        $ref = trim((string) (isset($row['ref_kind']) ? $row['ref_kind'] : ''));
        if ($ref === '') { return self::fail('MOVE_WITHOUT_REFERENCE', 'حركة نقد بلا مرجع موثق'); }
        $amount = (float) (isset($row['amount']) ? $row['amount'] : 0);
        if ($amount <= 0) { return self::fail('MOVE_AMOUNT_INVALID', 'المبلغ غير موجب'); }
        $rate = (float) (isset($row['fx_rate']) ? $row['fx_rate'] : 1);
        if ($rate <= 0) { $rate = 1; }
        $id = $gate->insert('tre_cash_move', array(
            'move_no'     => (string) (isset($row['move_no']) ? $row['move_no']
                                        : ('MV-' . substr(sha1(microtime(true) . $ref . $amount), 0, 12))),
            'vessel_kind' => $kind,
            'vessel_id'   => (int) (isset($row['vessel_id']) ? $row['vessel_id'] : 0),
            'direction'   => $dir,
            'amount'      => $amount,
            'currency'    => (string) (isset($row['currency']) ? $row['currency'] : ''),
            'fx_rate'     => $rate,
            'base_amount' => round($amount * $rate, 2),
            'ref_kind'    => $ref,
            'ref_id'      => (int) (isset($row['ref_id']) ? $row['ref_id'] : 0),
            'is_fx_diff'  => (int) (isset($row['is_fx_diff']) ? $row['is_fx_diff'] : 0),
            'moved_by'    => (int) $actorId,
            'note'        => (string) (isset($row['note']) ? $row['note'] : ''),
        ));
        return self::done(array('move_id' => (int) $id));
    }

    /** الرصيدُ الدفتريُّ مشتقٌّ من الحركاتِ — لا يُكتب بيد */
    public static function vesselBalance(TenantDb $gate, $kind, $vesselId)
    {
        $rows = $gate->select('tre_cash_move', array(
            'where' => array('vessel_kind' => (string) $kind, 'vessel_id' => (int) $vesselId), 'limit' => 5000));
        $bal = 0.0;
        foreach ($rows as $r) {
            $bal += ((string) $r['direction'] === 'in') ? (float) $r['amount'] : -1 * (float) $r['amount'];
        }
        if ((string) $kind === self::VESSEL_BOX) {
            $box = $gate->selectOne('tre_cash_box', array('where' => array('id' => (int) $vesselId)));
            if ($box) { $bal += (float) $box['opening_balance']; }
        }
        return round($bal, 2);
    }

    /** التحويلُ بين أوعيةِ الشركةِ — مسارٌ أخفُّ بقاعدتِه وبتوقيعِ مفوَّض */
    public static function executeTransfer(TenantDb $gate, $transferId, $actorId)
    {
        $transferId = (int) $transferId; $actorId = (int) $actorId;
        $t = $gate->selectOne('tre_transfer', array('where' => array('id' => $transferId)));
        if (!$t) { return self::fail('TRANSFER_NOT_FOUND', ''); }
        if ((string) $t['state'] === 'executed') {
            return self::done(array('transfer_id' => $transferId, 'idempotent' => 1));
        }
        if ((string) $t['state'] !== 'draft') { return self::fail('TRANSFER_NOT_DRAFT', (string) $t['state']); }
        if (trim((string) $t['authority_rule_id']) === '' || (int) $t['signed_by'] <= 0) {
            return self::fail('TRANSFER_WITHOUT_AUTHORITY', 'التحويل بلا قاعدة صلاحية او بلا توقيع مفوض');
        }
        if ((string) $t['from_kind'] === (string) $t['to_kind'] && (int) $t['from_id'] === (int) $t['to_id']) {
            return self::fail('TRANSFER_TO_SELF', 'الوعاء لا يحول الى نفسه');
        }
        $bal = self::vesselBalance($gate, (string) $t['from_kind'], (int) $t['from_id']);
        if ($bal + 0.005 < (float) $t['amount']) {
            return self::fail('TRANSFER_EXCEEDS_BALANCE', 'الرصيد ' . $bal . ' والمطلوب ' . (float) $t['amount']);
        }

        $outId = 0; $inId = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($t, $transferId, $actorId, &$outId, &$inId) {
            $outId = (int) $g->insert('tre_cash_move', array(
                'move_no' => 'TRF-O-' . $transferId, 'vessel_kind' => (string) $t['from_kind'],
                'vessel_id' => (int) $t['from_id'], 'direction' => 'out',
                'amount' => (float) $t['amount'], 'currency' => (string) $t['currency'],
                'fx_rate' => 1, 'base_amount' => (float) $t['amount'],
                'ref_kind' => 'transfer', 'ref_id' => $transferId, 'moved_by' => $actorId,
                'note' => 'W11 تحويل بين اوعية الشركة',
            ));
            $inId = (int) $g->insert('tre_cash_move', array(
                'move_no' => 'TRF-I-' . $transferId, 'vessel_kind' => (string) $t['to_kind'],
                'vessel_id' => (int) $t['to_id'], 'direction' => 'in',
                'amount' => (float) $t['amount'], 'currency' => (string) $t['currency'],
                'fx_rate' => 1, 'base_amount' => (float) $t['amount'],
                'ref_kind' => 'transfer', 'ref_id' => $transferId, 'moved_by' => $actorId,
                'note' => 'W11 تحويل بين اوعية الشركة',
            ));
        }, 'W11 تنفيذ تحويل بين اوعية');

        $gate->update('tre_transfer', array(
            'state' => 'executed', 'executed_by' => $actorId, 'executed_at' => date('Y-m-d H:i:s'),
            'out_move_id' => $outId, 'in_move_id' => $inId,
        ), array('id' => $transferId));
        return self::done(array('transfer_id' => $transferId, 'out_move_id' => $outId, 'in_move_id' => $inId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ الصرفُ الأجنبيُّ — سعرُ الصفقةِ الموثَّقُ لا سعرُ الجدول
       ══════════════════════════════════════════════════════════════════════ */

    public static function recordFxDeal(TenantDb $gate, array $row, $actorId)
    {
        $sc = trim((string) (isset($row['sell_currency']) ? $row['sell_currency'] : ''));
        $bc = trim((string) (isset($row['buy_currency']) ? $row['buy_currency'] : ''));
        if ($sc === '' || $bc === '') { return self::fail('FX_CURRENCY_MISSING', ''); }
        if ($sc === $bc) { return self::fail('FX_SAME_CURRENCY', 'الصفقة بين عملتين مختلفتين'); }
        $doc = trim((string) (isset($row['doc_ref']) ? $row['doc_ref'] : ''));
        if ($doc === '') { return self::fail('FX_WITHOUT_DOC', 'صفقة صرف بلا مستند'); }
        $rate = (float) (isset($row['deal_rate']) ? $row['deal_rate'] : 0);
        if ($rate <= 0) { return self::fail('FX_RATE_INVALID', ''); }
        $table = (float) (isset($row['table_rate']) ? $row['table_rate'] : 0);
        $id = $gate->insert('tre_fx_deal', array(
            'deal_no'       => (string) (isset($row['deal_no']) ? $row['deal_no']
                                          : ('FX-' . substr(sha1(microtime(true) . $doc), 0, 10))),
            'deal_kind'     => (string) (isset($row['deal_kind']) ? $row['deal_kind'] : 'buy'),
            'sell_currency' => $sc, 'buy_currency' => $bc,
            'sell_amount'   => (float) (isset($row['sell_amount']) ? $row['sell_amount'] : 0),
            'buy_amount'    => (float) (isset($row['buy_amount']) ? $row['buy_amount'] : 0),
            'deal_rate'     => $rate, 'table_rate' => $table,
            'rate_gap'      => $table > 0 ? round($rate - $table, 8) : 0,
            'counterparty'  => (string) (isset($row['counterparty']) ? $row['counterparty'] : ''),
            'doc_ref'       => $doc, 'dealt_by' => (int) $actorId,
        ));
        return self::done(array('deal_id' => (int) $id, 'rate_gap' => $table > 0 ? round($rate - $table, 8) : 0));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ المطابقةُ البنكيّةُ وبنودُ فروقِها
       ══════════════════════════════════════════════════════════════════════ */

    public static function openBankDifference(TenantDb $gate, $statementId, array $line, $actorId)
    {
        $statementId = (int) $statementId;
        $st = $gate->selectOne('bank_statements', array('where' => array('id' => $statementId)));
        if (!$st) { return self::fail('STATEMENT_NOT_FOUND', ''); }
        foreach (array('diff_kind', 'cause', 'responsible_role', 'action_taken') as $f) {
            if (trim((string) (isset($line[$f]) ? $line[$f] : '')) === '') {
                return self::fail('DIFFERENCE_WITHOUT_OWNER', 'الفرق بلا نوع او سبب او مسؤول او اجراء');
            }
        }
        $id = $gate->insert('tre_recon_difference', array(
            'statement_id'     => $statementId,
            'match_id'         => (int) (isset($line['match_id']) ? $line['match_id'] : 0),
            'diff_kind'        => (string) $line['diff_kind'],
            'cause'            => (string) $line['cause'],
            'amount'           => (float) (isset($line['amount']) ? $line['amount'] : 0),
            'responsible_role' => (string) $line['responsible_role'],
            'action_taken'     => (string) $line['action_taken'],
            'state'            => 'open',
            'opened_by'        => (int) $actorId,
        ));
        self::recomputeStatementDiffs($gate, $statementId);
        return self::done(array('difference_id' => (int) $id));
    }

    public static function resolveBankDifference(TenantDb $gate, $diffId, $actorId)
    {
        $diffId = (int) $diffId;
        $d = $gate->selectOne('tre_recon_difference', array('where' => array('id' => $diffId)));
        if (!$d) { return self::fail('DIFFERENCE_NOT_FOUND', ''); }
        $gate->update('tre_recon_difference', array(
            'state' => 'resolved', 'resolved_by' => (int) $actorId, 'resolved_at' => date('Y-m-d H:i:s'),
        ), array('id' => $diffId));
        self::recomputeStatementDiffs($gate, (int) $d['statement_id']);
        return self::done(array('difference_id' => $diffId));
    }

    /** عدّادُ الفروقِ المفتوحةِ مشتقٌّ — لا يُكتب بيد */
    public static function recomputeStatementDiffs(TenantDb $gate, $statementId)
    {
        $statementId = (int) $statementId;
        $n = $gate->count('tre_recon_difference',
            array('where' => array('statement_id' => $statementId, 'state' => 'open')));
        $gate->update('bank_statements', array('diff_count' => (int) $n), array('id' => $statementId));
        return (int) $n;
    }

    /**
     * إقفالُ جلسةِ المطابقة. **الخزينةُ تُعِدُّ والماليّةُ تراجع** (`TRS-13`
     * نصًّا) — فمن أعدَّ الجلسةَ لا يراجعها، ولا إقفالَ وفيها فرقٌ مفتوح.
     */
    public static function closeBankRecon(TenantDb $gate, $statementId, $actorId)
    {
        $statementId = (int) $statementId; $actorId = (int) $actorId;
        $st = $gate->selectOne('bank_statements', array('where' => array('id' => $statementId)));
        if (!$st) { return self::fail('STATEMENT_NOT_FOUND', ''); }
        if ((string) $st['state'] === 'closed') {
            return self::done(array('statement_id' => $statementId, 'idempotent' => 1));
        }
        if ((int) $st['created_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_REVIEW_BANK', 'من اعد المطابقة لا يراجعها');
        }
        $open = self::recomputeStatementDiffs($gate, $statementId);
        if ($open > 0) {
            return self::fail('BANK_CLOSE_WITH_OPEN_DIFF', 'فروق مفتوحة ' . $open);
        }
        $gate->update('bank_statements', array(
            'state' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'closed_by' => $actorId,
        ), array('id' => $statementId));
        self::emit($gate, 'tre.bank.reconciled', array('statement_id' => $statementId,
            'period_id' => (int) (isset($st['period_id']) ? $st['period_id'] : 0)));
        return self::done(array('statement_id' => $statementId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ العهدةُ النثريّةُ — لا تجديدَ قبل تسويةِ السابقةِ بمستنداتها
       ══════════════════════════════════════════════════════════════════════ */

    public static function openPettyCustody(TenantDb $gate, array $row, $actorId)
    {
        $holder = (int) (isset($row['holder_id']) ? $row['holder_id'] : 0);
        if ($holder <= 0) { return self::fail('CUSTODY_WITHOUT_HOLDER', ''); }
        $open = $gate->count('tre_petty_custody',
            array('where' => array('holder_id' => $holder, 'state' => 'open')));
        if ((int) $open > 0) {
            return self::fail('CUSTODY_RENEW_BEFORE_SETTLE', 'لا تجديد قبل تسوية العهدة السابقة');
        }
        $cap = (float) (isset($row['ceiling_amount']) ? $row['ceiling_amount'] : 0);
        if ($cap <= 0) { return self::fail('CUSTODY_WITHOUT_CEILING', 'العهدة بلا حد'); }
        $id = $gate->insert('tre_petty_custody', array(
            'custody_no'     => (string) (isset($row['custody_no']) ? $row['custody_no']
                                           : ('PC-' . substr(sha1(microtime(true) . $holder), 0, 10))),
            'holder_id'      => $holder,
            'ceiling_amount' => $cap,
            'currency'       => (string) (isset($row['currency']) ? $row['currency'] : ''),
            'opened_at'      => date('Y-m-d'),
            'due_date'       => isset($row['due_date']) ? (string) $row['due_date'] : null,
            'state'          => 'open',
            'note'           => (string) (isset($row['note']) ? $row['note'] : ''),
        ));
        return self::done(array('custody_id' => (int) $id));
    }

    /** بندُ مصروفٍ بلا مستندٍ لا يُقبَل — وأمينُ العهدةِ لا يقبل بنودَه */
    public static function acceptPettyExpense(TenantDb $gate, $expenseId, $actorId)
    {
        $expenseId = (int) $expenseId; $actorId = (int) $actorId;
        $e = $gate->selectOne('tre_petty_expense', array('where' => array('id' => $expenseId)));
        if (!$e) { return self::fail('EXPENSE_NOT_FOUND', ''); }
        if (trim((string) $e['doc_ref']) === '') {
            return self::fail('EXPENSE_WITHOUT_DOC', 'بند بلا مستند');
        }
        $c = $gate->selectOne('tre_petty_custody', array('where' => array('id' => (int) $e['custody_id'])));
        if (!$c) { return self::fail('CUSTODY_NOT_FOUND', ''); }
        if ((int) $c['holder_id'] === $actorId) {
            return self::fail('SAME_ACTOR_HOLD_AND_ACCEPT', 'امين العهدة لا يقبل بنود عهدته');
        }
        $spent = (float) $c['spent_amount'] + (float) $e['amount'];
        if ($spent > (float) $c['ceiling_amount'] + 0.005) {
            return self::fail('CUSTODY_CEILING_EXCEEDED', 'المصروف يتجاوز حد العهدة');
        }
        $gate->update('tre_petty_expense', array('state' => 'accepted'), array('id' => $expenseId));
        $gate->update('tre_petty_custody', array('spent_amount' => round($spent, 2)),
            array('id' => (int) $c['id']));
        return self::done(array('expense_id' => $expenseId, 'spent' => round($spent, 2)));
    }

    public static function settlePettyCustody(TenantDb $gate, $custodyId, $actorId)
    {
        $custodyId = (int) $custodyId;
        $c = $gate->selectOne('tre_petty_custody', array('where' => array('id' => $custodyId)));
        if (!$c) { return self::fail('CUSTODY_NOT_FOUND', ''); }
        $claimed = $gate->count('tre_petty_expense',
            array('where' => array('custody_id' => $custodyId, 'state' => 'claimed')));
        if ((int) $claimed > 0) {
            return self::fail('SETTLE_WITH_UNREVIEWED_LINES', 'بنود لم تراجع ' . (int) $claimed);
        }
        $gate->update('tre_petty_custody', array(
            'state' => 'settled', 'settled_at' => date('Y-m-d H:i:s'),
            'settled_amount' => (float) $c['spent_amount'],
        ), array('id' => $custodyId));
        return self::done(array('custody_id' => $custodyId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑦ الجردُ النقديُّ — بلجنةٍ لا بأمينٍ وحدَه
       ══════════════════════════════════════════════════════════════════════ */

    public static function openCashCount(TenantDb $gate, array $row, $actorId)
    {
        $box = (int) (isset($row['box_id']) ? $row['box_id'] : 0);
        if ($box <= 0) { return self::fail('COUNT_WITHOUT_BOX', ''); }
        $committee = (int) (isset($row['committee_size']) ? $row['committee_size'] : 0);
        if ($committee < 2) {
            return self::fail('COUNT_WITHOUT_COMMITTEE', 'الجرد بلجنة لا بامين الصندوق وحده');
        }
        $book = self::vesselBalance($gate, self::VESSEL_BOX, $box);
        $counted = (float) (isset($row['counted_balance']) ? $row['counted_balance'] : 0);
        $id = $gate->insert('tre_cash_count', array(
            'count_no'        => (string) (isset($row['count_no']) ? $row['count_no']
                                            : ('CC-' . substr(sha1(microtime(true) . $box), 0, 10))),
            'box_id'          => $box,
            'count_kind'      => (string) (isset($row['count_kind']) ? $row['count_kind'] : 'periodic'),
            'counted_at'      => date('Y-m-d H:i:s'),
            'book_balance'    => $book,
            'counted_balance' => $counted,
            'difference'      => round($counted - $book, 2),
            'committee_size'  => $committee,
            'counted_by'      => (int) $actorId,
            'state'           => 'draft',
        ));
        return self::done(array('count_id' => (int) $id, 'difference' => round($counted - $book, 2)));
    }

    /** ⛔ **مَن عدَّ لا يعتمد** — والفرقُ يُعالَج بمسارِه لا يُدفَن */
    public static function approveCashCount(TenantDb $gate, $countId, $actionRef, $actorId)
    {
        $countId = (int) $countId; $actorId = (int) $actorId;
        $c = $gate->selectOne('tre_cash_count', array('where' => array('id' => $countId)));
        if (!$c) { return self::fail('COUNT_NOT_FOUND', ''); }
        if ((string) $c['state'] === 'approved') {
            return self::done(array('count_id' => $countId, 'idempotent' => 1));
        }
        if ((int) $c['counted_by'] === $actorId) {
            return self::fail('SAME_ACTOR_COUNT_AND_APPROVE', 'من عد لا يعتمد جرده');
        }
        $actionRef = trim((string) $actionRef);
        if (abs((float) $c['difference']) > 0.005 && $actionRef === '') {
            return self::fail('COUNT_DIFF_WITHOUT_ACTION', 'الفرق يعالج فورا بمساره');
        }
        $gate->update('tre_cash_count', array(
            'state' => 'approved', 'approved_by' => $actorId, 'approved_at' => date('Y-m-d H:i:s'),
            'action_ref' => $actionRef,
        ), array('id' => $countId));
        self::emit($gate, 'tre.count.approved', array('count_id' => $countId, 'box_id' => (int) $c['box_id']));
        return self::done(array('count_id' => $countId, 'difference' => (float) $c['difference']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑧ الأدواتُ الماليّةُ — بدورتِها لا بحقلِ حالةٍ صامت
       ══════════════════════════════════════════════════════════════════════ */

    const INSTRUMENT_FLOW = array(
        'received'  => array('deposited', 'returned'),
        'deposited' => array('collected', 'bounced'),
        'bounced'   => array('returned', 'deposited'),
        'collected' => array(),
        'returned'  => array(),
        'handed'    => array('collected'),
    );

    public static function moveInstrument(TenantDb $gate, $instrumentId, $toState, $reason, $actorId)
    {
        $instrumentId = (int) $instrumentId;
        $i = $gate->selectOne('tre_instrument', array('where' => array('id' => $instrumentId)));
        if (!$i) { return self::fail('INSTRUMENT_NOT_FOUND', ''); }
        $from = (string) $i['state']; $toState = (string) $toState;
        if (!isset(self::INSTRUMENT_FLOW[$from])
            || !in_array($toState, self::INSTRUMENT_FLOW[$from], true)) {
            return self::fail('INSTRUMENT_TRANSITION_FORBIDDEN', $from . ' الى ' . $toState);
        }
        if ($toState === 'bounced' && trim((string) $reason) === '') {
            return self::fail('BOUNCE_WITHOUT_REASON', 'الارتجاع بلا سبب مكتوب');
        }
        $gate->update('tre_instrument', array(
            'state' => $toState,
            'bounce_reason' => $toState === 'bounced' ? trim((string) $reason) : (string) $i['bounce_reason'],
        ), array('id' => $instrumentId));
        return self::done(array('instrument_id' => $instrumentId, 'state' => $toState));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑨ خطاباتُ الضمانِ — الإصدارُ على تسهيلِه وبقاعدتِه
       ══════════════════════════════════════════════════════════════════════ */

    public static function issueGuarantee(TenantDb $gate, $guaranteeId, $actorId)
    {
        $guaranteeId = (int) $guaranteeId;
        $g = $gate->selectOne('tre_guarantee', array('where' => array('id' => $guaranteeId)));
        if (!$g) { return self::fail('GUARANTEE_NOT_FOUND', ''); }
        if ((int) $g['facility_id'] <= 0) {
            return self::fail('GUARANTEE_WITHOUT_FACILITY', 'الاصدار على تسهيله لا بلا تسهيل');
        }
        $f = $gate->selectOne('fin_funding_facilities', array('where' => array('id' => (int) $g['facility_id'])));
        if (!$f) { return self::fail('FACILITY_NOT_FOUND', ''); }
        if (trim((string) $g['authority_rule_id']) === '') {
            return self::fail('GUARANTEE_WITHOUT_AUTHORITY', 'الاصدار بلا قاعدة صلاحية');
        }
        $gate->update('tre_guarantee', array(
            'state' => 'issued', 'issued_at' => date('Y-m-d'),
        ), array('id' => $guaranteeId));
        return self::done(array('guarantee_id' => $guaranteeId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑩ المستهلكون — كلٌّ يترك أثرًا تجاريًّا مقيسًا (§46)
       ══════════════════════════════════════════════════════════════════════
       ⛔ **ولا استعلامَ خامٍّ على جدولِ مستأجِرٍ هنا** (`GAP-29` · `FR-SEC-006`):
         عقدُ المستهلكِ يمرّر `mysqli` لا `TenantDb`، فيُبنى الجسرُ من **كيانِ
         الحدثِ نفسِه** — والكيانُ يُقرأ من الحمولةِ لا يُخمَّن. ومستهلكٌ بلا
         كيانٍ يقف مُعلِنًا سببَه ولا يقرأ صفًّا واحدًا.
       ══════════════════════════════════════════════════════════════════════ */

    /** بوّابةُ المستهلكِ — من كيانِ الحدثِ لا من سياقِ جلسة */
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

    /** التخصيصُ يحرّك الذمّةَ عند الماليّة — والفاتورةُ تُقفل بتغطيتِها */
    public function onReceiptAllocated(array $event, \mysqli $conn)
    {
        $rid = (int) self::payloadOf($event, 'receipt_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($rid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $targets = array();
        foreach ($g->select('fin_collection_allocations',
                 array('where' => array('payment_id' => $rid), 'limit' => 2000)) as $a) {
            $rv = (int) $a['receivable_id'];
            if ($rv > 0) { $targets[$rv] = true; }
        }
        $n = 0;
        foreach (array_keys($targets) as $recv) {
            $col = 0.0;
            foreach ($g->select('fin_collection_allocations',
                     array('where' => array('receivable_id' => $recv), 'limit' => 2000)) as $x) {
                $col += (float) $x['amount'];
            }
            $rv = $g->selectOne('fin_receivables', array('where' => array('id' => $recv)));
            if (!$rv) { continue; }
            $out = round((float) $rv['amount'] - $col, 2);
            $state = ($out <= 0.005) ? 'collected' : (($col > 0) ? 'partial' : 'open');
            $g->update('fin_receivables',
                array('collected' => round($col, 2), 'outstanding' => $out, 'state' => $state),
                array('id' => $recv));
            $n++;
        }
        return 'W11:RECEIVABLES_UPDATED:' . $n;
    }

    /** التنفيذُ يخرج نقدًا من وعائِه — والحركةُ أثرُه لا صفُّ الحدث */
    public function onPaymentExecuted(array $event, \mysqli $conn)
    {
        $pid = (int) self::payloadOf($event, 'payment_id');
        $vessel = (int) self::payloadOf($event, 'vessel_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($pid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $ex = (int) $g->count('tre_cash_move',
            array('where' => array('ref_kind' => 'payment', 'ref_id' => $pid)));
        if ($ex > 0) { return 'W11:IDEMPOTENT:' . $ex; }
        $p = $g->selectOne('fin_payments', array('where' => array('id' => $pid)));
        if (!$p) { return 'W11:SKIP:NOT_FOUND'; }
        $rate = (float) $p['fx_rate'];
        if ($rate <= 0) { $rate = 1; }
        $mid = (int) $g->insert('tre_cash_move', array(
            'move_no'     => 'PAY-' . $pid,
            'vessel_kind' => self::VESSEL_BANK,
            'vessel_id'   => $vessel,
            'direction'   => 'out',
            'amount'      => (float) $p['amount'],
            'currency'    => (string) $p['currency'],
            'fx_rate'     => $rate,
            'base_amount' => round((float) $p['amount'] * $rate, 2),
            'ref_kind'    => 'payment',
            'ref_id'      => $pid,
            'note'        => 'W11 خروج نقد بتنفيذ امر دفع',
        ));
        return $mid > 0 ? ('W11:CASH_OUT:' . $mid) : 'W11:MOVE_FAILED';
    }

    /** المطابقةُ المقفلةُ تُغلق بندَ مطابقةِ البنكِ في قائمةِ الإقفال */
    public function onBankReconciled(array $event, \mysqli $conn)
    {
        $sid = (int) self::payloadOf($event, 'statement_id');
        $co  = (int) self::payloadOf($event, 'company_id');
        $g = self::gateOf($conn, $co);
        if ($sid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $st = $g->selectOne('bank_statements', array('where' => array('id' => $sid)));
        if (!$st) { return 'W11:SKIP:NOT_FOUND'; }
        $d = substr((string) $st['period_to'], 0, 10);
        $pid = 0;
        foreach ($g->select('fin_financial_periods',
                 array('where' => array('period_type' => 'month'), 'limit' => 500)) as $p) {
            if ((string) $p['start_date'] <= $d && (string) $p['end_date'] >= $d) {
                $pid = (int) $p['id']; break;
            }
        }
        if ($pid <= 0) { return 'W11:NO_PERIOD'; }
        require_once \dirname(\dirname(__DIR__)) . '/Services/Finance/AccountingCycleService.php';
        return \App\Services\Finance\AccountingCycleService::markChecklist(
            $conn, $co, $pid, 'reconcile_bank');
    }

    /** فرقُ الجردِ يصير حركةَ تسويةٍ مسمّاةً — لا رقمًا يُدفَن في حقل */
    public function onCashCountApproved(array $event, \mysqli $conn)
    {
        $cid = (int) self::payloadOf($event, 'count_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($cid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $c = $g->selectOne('tre_cash_count', array('where' => array('id' => $cid)));
        if (!$c) { return 'W11:SKIP:NOT_FOUND'; }
        $diff = (float) $c['difference'];
        if (abs($diff) <= 0.005) { return 'W11:NO_DIFFERENCE'; }
        $ex = (int) $g->count('tre_cash_move',
            array('where' => array('ref_kind' => 'cash_count', 'ref_id' => $cid)));
        if ($ex > 0) { return 'W11:IDEMPOTENT:' . $ex; }
        $box = $g->selectOne('tre_cash_box', array('where' => array('id' => (int) $c['box_id'])));
        $mid = (int) $g->insert('tre_cash_move', array(
            'move_no'     => 'CNT-' . $cid,
            'vessel_kind' => self::VESSEL_BOX,
            'vessel_id'   => (int) $c['box_id'],
            'direction'   => $diff > 0 ? 'in' : 'out',
            'amount'      => abs($diff),
            'currency'    => $box ? (string) $box['currency'] : '',
            'fx_rate'     => 1,
            'base_amount' => abs($diff),
            'ref_kind'    => 'cash_count',
            'ref_id'      => $cid,
            'note'        => 'W11 تسوية فرق جرد نقدي',
        ));
        return $mid > 0 ? ('W11:COUNT_ADJUSTED:' . $mid) : 'W11:MOVE_FAILED';
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
        'tre.receipt.allocated' => array('fin_payments',   'receipt_id'),
        'tre.payment.executed'  => array('fin_payments',   'payment_id'),
        'tre.bank.reconciled'   => array('bank_statements', 'statement_id'),
        'tre.count.approved'    => array('tre_cash_count', 'count_id'),
    );

    private static function emit(TenantDb $gate, $eventKey, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!isset(self::EVENT_ENTITY[$eventKey])) { return null; }
        list($table, $idKey) = self::EVENT_ENTITY[$eventKey];
        $entityId = isset($payload[$idKey]) ? (int) $payload[$idKey] : 0;

        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;

        $company = 0;
        try {
            $row = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => $entityId)));
            if ($row) { $company = (int) $row['company_id']; }
        } catch (\Throwable $t) { $company = 0; }

        /* ⚠ **الكيانُ يُحمَل في الحمولةِ لا يُستنتَج عند المستهلك**: عقدُ المستهلكِ
             يمرّر `mysqli` بلا سياقِ مستأجِر، فبلا كيانٍ في الحمولةِ يضطرُّ إلى
             استعلامٍ خامٍّ ليعرف كيانَ الصفّ — وهو ما يمنعه `GAP-29`. */
        $payload['company_id'] = $company;
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'treasury',
                'source_module'   => 'treasury',
                'entity_type'     => $table,
                'entity_id'       => $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w11:' . $eventKey . ':' . $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'TreasuryCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
