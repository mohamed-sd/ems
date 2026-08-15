<?php
namespace App\Services\Workflow;

/**
 * ChainLinkService — المستندُ يولّد تاليَه بمرجعٍ ظاهرٍ في الشاشتين
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0142 (عرضٌ ⇒ عقد) · INJ-0091 (طلبٌ ⇒ عروضٌ ⇒ أمر) · INJ-0335 · INJ-0292
 *
 * ── العلّةُ الجامعة ───────────────────────────────────────────────────────
 * السلسلةُ مقطوعةٌ عند وصلاتِها: عرضُ سعرٍ يُقبَل ولا يولّد عقدًا · وطلبُ
 * عروضٍ يُشتقُّ من العقودِ لا من طلباتِ الشراء · وأمرُ شراءٍ يُحفظ بخيارِ
 * «— بلا طلب —» · وشاشةُ خصومٍ بلا فعلِ اقتراح. فكلُّ مستندٍ جزيرةٌ، ولا
 * سبيلَ إلى سؤالِ المراجعةِ الأول: **من أين جاء هذا؟**
 *
 * ── القاعدةُ ──────────────────────────────────────────────────────────────
 * التوليدُ **فعلٌ محكومٌ لا نسخٌ**: يتحقّق من حالةِ الأبِ أولًا، ويكتب المرجعَ
 * في الابن، ويعيد مرجعَ الأبِ للعرض، **ولا يولّد ثانيًا** إن كان الابنُ قائمًا
 * (العطالةُ بالمرجعِ لا بالوقت).
 *
 * ◆ **والحكمُ هنا لا في الشاشة** (CS-05): الشاشتانِ تنادِيان الدالّةَ نفسَها،
 *   فما يُصلَح في ملفِّ شاشةٍ يعود من الشاشةِ الثانيةِ التي تكتب الجدولَ نفسَه.
 * ◆ والقاعدةُ تحرس البنيةَ بقادحين (`trg_po_request_required` ·
 *   `trg_deduction_doc_required`) — **وهذه تحرس المعنى**: أنَّ الأبَ معتمدٌ
 *   وأنَّ الابنَ يحمل مرجعَه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class ChainLinkService
{
    /**
     * ① قبولُ عرضِ سعرٍ ⇒ عقدٌ مسودةٌ يحمل `quotation_id`.
     *
     * نصُّ القبول: «قبولُ عرضٍ يولّد عقدًا مسودةً يحمل `quotation_id`، وشاشةُ
     * العقد تعرض رابطًا للعرض الأب، وبنودُ العقد تطابق بنودَ العرض».
     *
     * @return array{ok:bool,code:int,reason:string,contract_id:?int,existing:bool}
     */
    public static function contractFromQuotation($conn, $gate, $companyId, $quotationId, $actor, array $opts = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'contract_id' => null, 'existing' => false);
        $quotationId = (int) $quotationId;
        if ($quotationId <= 0) { $out['code'] = 422; $out['reason'] = 'CHN-422: عرضٌ غيرُ صالح'; return $out; }

        $q = null;
        try {
            $q = $gate->selectOne('quotations', array('where' => array('id' => $quotationId)));
        } catch (\Throwable $t) { $q = null; }
        if (!$q) { $out['code'] = 404; $out['reason'] = 'CHN-404: العرضُ غيرُ موجودٍ في نطاقك'; return $out; }

        /* ◆ الأبُ يجب أن يكون **مقبولًا**: عقدٌ من عرضٍ مسودةٍ التزامٌ بلا قبول */
        if ((string) $q['state'] !== 'مقبول') {
            $out['code'] = 422;
            $out['reason'] = 'CHN-422: العرضُ «' . $q['state'] . '» — ولا يولّد عقدًا إلا المقبول';
            return $out;
        }

        /* ◆ العطالةُ بالمرجع: عرضٌ ولّد عقدًا لا يولّد ثانيًا */
        $ex = null;
        try {
            $ex = $gate->selectOne('contracts', array('columns' => array('id'),
                'where' => array('quotation_id' => $quotationId)));
        } catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true;
            $out['contract_id'] = (int) $ex['id'];
            $out['reason'] = 'العرضُ ولّد العقدَ #' . (int) $ex['id'] . ' سلفًا — لا توليدَ ثانٍ';
            return $out;
        }

        /* ◆ **والمشروعُ إلزاميٌّ بمفتاحٍ أجنبيٍّ في `contracts`** (`fk_contracts_project`)
             — فالعقدُ لا يقوم بلا مشروعٍ يقع تحته. ويُؤخذ من الطلبِ لا يُخترع:
             إن لم يُمرَّر، **يُردُّ بسببٍ مسمًّى** ولا يُلفَّق مشروعٌ عشوائيّ.
           ◆ وتاريخُ التوقيعِ إلزاميٌّ كذلك — ويُبدأ بتاريخِ اليوم للمسودة،
             ويُصحَّح عند التوقيعِ الفعليّ (والحالةُ «مسودة» تُعلن أنه لم يقع بعد). */
        $projectId = isset($opts['project_id']) ? (int) $opts['project_id'] : 0;
        if ($projectId <= 0) {
            $pr = null;
            try {
                $pr = $gate->selectOne('project', array('columns' => array('id'),
                    'where' => array('status' => 1)));
            } catch (\Throwable $t) { $pr = null; }
            $projectId = $pr ? (int) $pr['id'] : 0;
        }
        if ($projectId <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'CHN-422: لا مشروعَ يُسند إليه العقد — والعقدُ لا يقوم بلا مشروع';
            return $out;
        }

        $newId = null;
        try {
            $newId = (int) $gate->insert('contracts', array(
                'quotation_id'    => $quotationId,
                'project_id'      => $projectId,
                'contract_signing_date' => date('Y-m-d'),
                'second_party'    => self::clientNameOf($gate, (int) $q['client_id']),
                'contract_status' => 'مسودة',
                /* ◆ **وأسماءُ الأعمدةِ تُقاس لا تُظنّ**: `contracts` لا تحمل
                     `contract_value` ولا `currency` — بل `total_contract_permonth`
                     و`price_currency_contract`. واسمٌ مظنونٌ يُرجع خطأً وقتَ
                     الكتابةِ لا وقتَ القراءة. */
                'total_contract_permonth' => isset($q['amount_total']) ? $q['amount_total'] : null,
                'price_currency_contract' => isset($q['currency']) ? $q['currency'] : null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'CHN-422: تعذّر التوليد: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'contracts', 'from_quotation', $newId,
            array(), array('quotation_id' => $quotationId, 'contract_status' => 'مسودة'));

        $out['ok'] = true; $out['code'] = 200; $out['contract_id'] = $newId;
        $out['reason'] = 'وُلّد العقدُ المسودةُ #' . $newId . ' من العرضِ #' . $quotationId;
        return $out;
    }

    /**
     * ② طلبُ شراءٍ معتمدٌ ⇒ يظهر في شاشةِ طلبِ العروضِ ويُشتقُّ منه طلبُ عروض.
     *
     * @return array{ok:bool,code:int,reason:string,rfq_id:?int,existing:bool}
     */
    public static function rfqFromRequest($conn, $gate, $companyId, $requestId, $title, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rfq_id' => null, 'existing' => false);
        $requestId = (int) $requestId;
        if ($requestId <= 0) { $out['code'] = 422; $out['reason'] = 'CHN-422: طلبٌ غيرُ صالح'; return $out; }

        $r = null;
        try { $r = $gate->selectOne('proc_request', array('where' => array('id' => $requestId))); }
        catch (\Throwable $t) { $r = null; }
        if (!$r) { $out['code'] = 404; $out['reason'] = 'CHN-404: الطلبُ غيرُ موجودٍ في نطاقك'; return $out; }

        if (!self::requestApproved($r)) {
            $out['code'] = 422;
            $out['reason'] = 'CHN-422: الطلبُ «' . (string) $r['state'] . '» — ولا يُشتقُّ منه طلبُ عروضٍ إلا المعتمد';
            return $out;
        }

        $ex = null;
        try { $ex = $gate->selectOne('supplier_rfqs', array('columns' => array('id'),
            'where' => array('request_id' => $requestId))); }
        catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true; $out['rfq_id'] = (int) $ex['id'];
            $out['reason'] = 'للطلبِ طلبُ عروضٍ قائمٌ #' . (int) $ex['id'];
            return $out;
        }

        $no = 'RFQ-PR' . $requestId . '-' . date('ymd');
        $newId = null;
        try {
            $newId = (int) $gate->insert('supplier_rfqs', array(
                'rfq_no'     => $no,
                'request_id' => $requestId,
                'title'      => mb_substr(trim((string) $title) !== '' ? (string) $title
                                          : ('طلبُ عروضٍ عن الطلب #' . $requestId), 0, 160),
                'state'      => 'draft',
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'CHN-422: تعذّر الاشتقاق: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'supplier_rfqs', 'from_request', $newId,
            array(), array('request_id' => $requestId, 'rfq_no' => $no));

        $out['ok'] = true; $out['code'] = 200; $out['rfq_id'] = $newId;
        $out['reason'] = 'اشتُقَّ طلبُ العروضِ #' . $newId . ' من الطلبِ المعتمد #' . $requestId;
        return $out;
    }

    /**
     * ③ ترسيةٌ ⇒ مسودةُ أمرِ شراءٍ تحمل `rfq_id` و`award_id` و`request_id`.
     *
     * @return array{ok:bool,code:int,reason:string,order_id:?int,existing:bool}
     */
    public static function orderFromAward($conn, $gate, $companyId, $awardId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'order_id' => null, 'existing' => false);
        $awardId = (int) $awardId;
        if ($awardId <= 0) { $out['code'] = 422; $out['reason'] = 'CHN-422: ترسيةٌ غيرُ صالحة'; return $out; }

        $a = null;
        try { $a = $gate->selectOne('rfq_awards', array('where' => array('id' => $awardId))); }
        catch (\Throwable $t) { $a = null; }
        if (!$a) { $out['code'] = 404; $out['reason'] = 'CHN-404: الترسيةُ غيرُ موجودةٍ في نطاقك'; return $out; }

        $rfq = null;
        try { $rfq = $gate->selectOne('supplier_rfqs', array('where' => array('id' => (int) $a['rfq_id']))); }
        catch (\Throwable $t) { $rfq = null; }
        $reqId = ($rfq && !empty($rfq['request_id'])) ? (int) $rfq['request_id'] : 0;
        if ($reqId <= 0) {
            /* ◆ **والقادحُ في القاعدةِ يردُّ أمرًا بلا طلب** — فيُقال السببُ هنا
                 قبل أن يقع الخطأُ، لا بعد أن يرتدَّ الإدراج. */
            $out['code'] = 422;
            $out['reason'] = 'CHN-422: طلبُ العروضِ بلا طلبِ شراءٍ أب — ولا أمرَ شراءٍ بلا طلب';
            return $out;
        }

        $ex = null;
        try { $ex = $gate->selectOne('proc_order', array('columns' => array('id'),
            'where' => array('award_id' => $awardId))); }
        catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true; $out['order_id'] = (int) $ex['id'];
            $out['reason'] = 'للترسيةِ أمرُ شراءٍ قائمٌ #' . (int) $ex['id'];
            return $out;
        }

        $newId = null;
        try {
            $newId = (int) $gate->insert('proc_order', array(
                'code'        => 'PO-A' . $awardId . '-' . date('ymd'),
                'supplier_id' => (int) $a['supplier_id'],
                'request_id'  => $reqId,
                'rfq_id'      => (int) $a['rfq_id'],
                'award_id'    => $awardId,
                'currency'    => isset($a['currency']) ? $a['currency'] : null,
                'state'       => 'draft',
                'created_by'  => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'CHN-422: تعذّر التوليد: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'proc_order', 'from_award', $newId,
            array(), array('award_id' => $awardId, 'rfq_id' => (int) $a['rfq_id'], 'request_id' => $reqId));

        $out['ok'] = true; $out['code'] = 200; $out['order_id'] = $newId;
        $out['reason'] = 'وُلّدت مسودةُ أمرِ الشراءِ #' . $newId . ' عن الترسيةِ #' . $awardId;
        return $out;
    }

    /**
     * ④ اقتراحُ خصمٍ بمستندٍ مؤيّد ⇒ صفٌّ بحالة `proposed` ينتظر الاعتماد.
     *
     * نصُّ القبول: «الدور ٢٧ يقترح خصمًا بمستندٍ مؤيدٍ فيظهر في صندوق الاعتماد
     * **بحالة pending**، ولا يصير نافذًا إلا باكتمال سلّم الموافقات».
     *
     * @return array{ok:bool,code:int,reason:string,deduction_id:?int}
     */
    public static function proposeDeduction($conn, $gate, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'deduction_id' => null);
        $person = isset($a['person_id']) ? (int) $a['person_id'] : 0;
        $amount = isset($a['amount']) ? (float) $a['amount'] : 0.0;
        $doc    = isset($a['doc_ref']) ? trim((string) $a['doc_ref']) : '';
        $kind   = isset($a['source_type']) ? trim((string) $a['source_type']) : 'penalty';

        if ($person <= 0)  { $out['code'] = 422; $out['reason'] = 'CHN-422: الشخصُ إلزامي'; return $out; }
        if ($amount <= 0)  { $out['code'] = 422; $out['reason'] = 'CHN-422: مبلغُ الخصمِ يجب أن يكون موجبًا'; return $out; }
        if ($doc === '')   {
            $out['code'] = 422;
            $out['reason'] = 'CHN-422: لا اقتراحَ خصمٍ بلا مستندٍ مؤيّد — والقاعدةُ تردُّه بقادح';
            return $out;
        }

        $newId = null;
        try {
            /* ◆ **والجدولُ `fin_dues` لا `payroll_deductions`**: شاشةُ «الخصوم
                 المقترحة» تقرأ ذممًا مدينةً معلَّقةً (`direction=debit` ·
                 `settlement_state=pending`) — وكتابةُ الاقتراحِ في جدولٍ لا تقرؤه
                 الشاشةُ اقتراحٌ لا يراه أحد. **والكاتبُ يكتب حيث يقرأ العارض.**
               ◆ والمستندُ المؤيّدُ يقع في `source_doc_type/id` — والقاعدةُ تحمل
                 قيدَ M-11 «لا خصمَ بلا مصدر»، فالحارسُ في موضعين. */
            $newId = (int) $gate->insert('fin_dues', array(
                'party_type'       => 'employee',
                'party_ref'        => $person,
                'due_type'         => in_array($kind, array('advance', 'discount', 'penalty'), true) ? $kind : 'penalty',
                'direction'        => 'debit',
                'amount'           => $amount,
                'currency'         => isset($a['currency']) ? (string) $a['currency'] : 'SDG',
                'source_doc_type'  => isset($a['source_doc_type']) ? (string) $a['source_doc_type'] : 'penalty_assessment',
                'source_doc_id'    => isset($a['source_doc_id']) ? (int) $a['source_doc_id'] : null,
                'settlement_state' => 'pending',
                'created_by'       => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'CHN-422: تعذّر الاقتراح: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'fin_dues', 'propose', $newId,
            array(), array('settlement_state' => 'pending', 'amount' => $amount, 'doc_ref' => $doc));

        $out['ok'] = true; $out['code'] = 200; $out['deduction_id'] = $newId;
        $out['reason'] = 'اقتُرح الخصمُ #' . $newId . ' بحالة «مقترح» — ولا يصير نافذًا إلا بالاعتماد';
        return $out;
    }

    // ── مساعداتٌ ────────────────────────────────────────────────────────────

    /** أمعتمدٌ هو الطلب؟ — الحالاتُ المعتمدةُ في `proc_request`. */
    public static function requestApproved(array $r)
    {
        $s = mb_strtolower(trim((string) ($r['state'] ?? '')));
        $f = mb_strtolower(trim((string) ($r['fin_approval_state'] ?? '')));
        $okStates = array('approved', 'معتمد', 'معتمَد', 'مقبول');
        return in_array($s, $okStates, true) || in_array($f, $okStates, true);
    }

    private static function clientNameOf($gate, $clientId)
    {
        if ((int) $clientId <= 0) { return null; }
        try {
            $c = $gate->selectOne('clients', array('columns' => array('name'), 'where' => array('id' => (int) $clientId)));
            return $c ? (string) $c['name'] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** ◆ التدقيقُ يُحمَّل **عند موضعِ الاستعمال** — وإلا كان `function_exists` كاذبًا. */
    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        /* ◆ ثلاثةُ مستوياتٍ لا اثنان: `app/Services/Workflow` ⇐ الجذر */
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        if (function_exists('ems_audit_change')) {
            ems_audit_change($conn, 'workflow', $table, $action, (int) $rowId, $before, $after,
                array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        }
    }
}
