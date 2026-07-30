<?php
/**
 * app/Services/Contract/ClientAmendmentEffects.php — آثارُ ملحق عقد العميل
 * (M-10 · CON-02 §6) — «كلُّ تغييرٍ في الكمية أو المصفوفة ملحقٌ بسريان،
 * والحاوياتُ تُعاد موازنتُها عند تغيّر الكمية».
 *
 * ثلاثُ وظائف تسدّ الفجواتِ المقيسة:
 *  · changeObligation — ملحقُ «تغيير التزامات» يولّد صفَّ المصفوفة مسودةً
 *    بسريانه (والإجازةُ تبقى بيد المالية — ق-18: الملحقُ يولّد والماليةُ تُجيز).
 *  · closePredecessors — إجازةُ الصف الجديد تقفل سلفَه على (سريانه − 1) —
 *    «ما قبله بحكمه القديم وما بعده بالجديد» (A6) بنيويًّا لا التباسًا.
 *  · rebalanceOps — ملحقُ النطاق يوازن الجذرَ الساعيَّ الواحد، والتخفيضُ دون
 *    المخصَّص للموردين يُرفض بمرجعه (Σ ≤ الملتزم — UX-05 §8.2 + CHECK حزامًا).
 */

namespace App\Services\Contract;

class ClientAmendmentEffects
{
    /** قوائمُ CON-02 §8 المحكومة — مرايا ENUMات contract_obligations. */
    const OBLIGATION_TYPES = array('fuel', 'access_road', 'loading_equipment', 'equipment_readiness',
                                   'operators', 'permits_safety', 'utilities', 'catering_camp', 'force_majeure');
    const OBLIGORS = array('client', 'company', 'supplier', 'operator', 'none');
    const EFFECTS = array('billable_standby', 'non_billable', 'per_clause');

    /**
     * ملحقُ «تغيير التزامات» — يولّد صفَّ الالتزام مسودةً بسريانه ذريًّا.
     * @return array{ok:bool,code:int,reason:string,amendment_id:?int,obligation_id:?int}
     */
    public static function changeObligation($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'amendment_id' => null, 'obligation_id' => null);
        $contractId = (int) $contractId;

        $otype   = isset($args['obligation_type']) ? (string) $args['obligation_type'] : '';
        $obligor = isset($args['obligor']) ? (string) $args['obligor'] : '';
        $effect  = isset($args['effect_on_billing']) ? (string) $args['effect_on_billing'] : '';
        $eff     = isset($args['effective_from']) ? trim((string) $args['effective_from']) : '';
        $reason  = isset($args['reason']) ? trim((string) $args['reason']) : '';

        if (!in_array($otype, self::OBLIGATION_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'بندُ التزامٍ من خارج قائمة §4'; return $out;
        }
        if (!in_array($obligor, self::OBLIGORS, true)) {
            $out['code'] = 422; $out['reason'] = 'طرفٌ ملتزمٌ من خارج القائمة المحكومة'; return $out;
        }
        if (!in_array($effect, self::EFFECTS, true)) {
            $out['code'] = 422; $out['reason'] = 'أثرُ إخلالٍ من خارج الثلاثة'; return $out;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eff)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ السريان إلزامي — «ملحقٌ بسريان» (§6)'; return $out;
        }

        $contract = null;
        try { $contract = $gate->selectOne('contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { $contract = null; }
        if (!$contract) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجودٍ في نطاقك'; return $out; }

        // الملتزمُ النافذُ الحالي للبند — «قبل» الصادق في الملحق
        $current = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
                "SELECT o.obligor FROM contract_obligations o
                 WHERE {TENANT_SCOPE} AND o.client_contract_id = ? AND o.obligation_type = ?
                   AND o.approval_state = 'approved' AND COALESCE(o.is_deleted,0)=0
                 ORDER BY o.valid_from DESC LIMIT 1", array($contractId, $otype));
            $current = $rows ? (string) $rows[0]['obligor'] : null;
        } catch (\Throwable $t) { $current = null; }
        if ($current !== null && $current === $obligor) {
            $out['code'] = 422; $out['reason'] = 'الملتزمُ الجديد هو النافذُ نفسُه — لا ملحقَ بلا تغيير'; return $out;
        }

        $newAmd = 0; $newObl = 0;
        try {
            $gate->runInTransaction(function ($g) use ($conn, $contract, $contractId, $otype, $obligor,
                                                       $effect, $eff, $reason, $current, $actor,
                                                       &$newAmd, &$newObl) {
                $newAmd = (int) $g->insert('contract_amendments', array(
                    'amendment_code' => self::nextAmendmentCode($conn, (int) $contract['company_id']),
                    'contract_id'    => $contractId,
                    'amend_type'     => 'تغيير التزامات',
                    'amend_date'     => date('Y-m-d'),
                    'effective_from' => $eff,
                    'requested_by'   => (int) $actor ?: null,
                    'reason'         => $reason !== '' ? $reason : null,
                    'old_value'      => $current !== null ? $current : 'غير معبأ',
                    'new_value'      => $obligor,
                    'effect_summary' => 'تغييرُ ملتزم «' . $otype . '» إلى «' . $obligor . '» بسريان ' . $eff,
                    'created_by'     => (int) $actor ?: null,
                ));
                // صفُّ الالتزام مسودةً — الإجازةُ بيد المالية في شاشتها (ق-18)
                $newObl = (int) $g->insert('contract_obligations', array(
                    'client_contract_id' => $contractId,
                    'obligation_type'    => $otype,
                    'obligor'            => $obligor,
                    'effect_on_billing'  => $effect,
                    'valid_from'         => $eff,
                    'approval_state'     => 'draft',
                    'created_by'         => (int) $actor ?: null,
                ));
                return true;
            }, 'M-10 change obligation via amendment');
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'uq_obligation_contract_type_from') !== false
                || strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409;
                $out['reason'] = 'للبند صفٌّ بتاريخ السريان نفسِه (UQ) — غيّر التاريخ';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التوليد الذري: ' . $t->getMessage(); return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_obligations', 'amendment_generate', $newObl,
            array('obligor' => $current), array('obligor' => $obligor, 'valid_from' => $eff, 'amendment_id' => $newAmd),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor, 'contract_id' => $contractId));

        $out['ok'] = true; $out['code'] = 200;
        $out['amendment_id'] = $newAmd; $out['obligation_id'] = $newObl;
        return $out;
    }

    /**
     * إقفالُ الأسلاف عند إجازة صفٍّ — على (سريان الجديد − 1 يوم).
     * يُستدعى داخل مسار الإجازة (بعد approve) — والأسلافُ الأقدمُ سريانًا حصرًا.
     * @return int عددُ ما أُقفل
     */
    public static function closePredecessors($gate, $obligationRow)
    {
        $cid   = (int) $obligationRow['client_contract_id'];
        $otype = (string) $obligationRow['obligation_type'];
        $from  = (string) $obligationRow['valid_from'];
        $oid   = (int) $obligationRow['id'];
        if ($from === '') { return 0; }
        $cut = date('Y-m-d', strtotime($from . ' -1 day'));
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
                "SELECT o.id FROM contract_obligations o
                 WHERE {TENANT_SCOPE} AND o.client_contract_id = ? AND o.obligation_type = ?
                   AND o.id <> ? AND o.approval_state = 'approved' AND COALESCE(o.is_deleted,0)=0
                   AND o.valid_from < ?
                   AND (o.valid_to IS NULL OR o.valid_to >= ?)",
                array($cid, $otype, $oid, $from, $from));
        } catch (\Throwable $t) { $rows = array(); }
        $n = 0;
        foreach ($rows as $r) {
            try {
                $gate->update('contract_obligations', array('valid_to' => $cut), array('id' => (int) $r['id']));
                $n++;
            } catch (\Throwable $t) {
                error_log('ClientAmendmentEffects closePredecessors #' . $r['id'] . ': ' . $t->getMessage());
            }
        }
        return $n;
    }

    /**
     * عملياتُ موازنة الحاويات لملحق النطاق (الكميةُ بإشارتها — ساعات).
     * جذرٌ ساعيٌّ واحدٌ نشط → عمليةُ تحديثٍ تنضم لمعاملة الملحق؛
     * تعددُ الجذور → موازنةٌ يدويةٌ معلَنة (لا اجتهادَ في توزيع الكمية)؛
     * تخفيضٌ دون المخصَّص → رفضٌ بمرجع الحاوية (Σ ≤ الملتزم).
     * @return array{ok:bool,reason:string,ops:array,note:string}
     */
    public static function rebalanceOps($gate, $contract, $signedQty)
    {
        $out = array('ok' => true, 'reason' => '', 'ops' => array(), 'note' => '');
        $signedQty = (float) $signedQty;
        if ($signedQty == 0.0) { return $out; }
        $roots = array();
        try {
            $roots = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.id, c.cap_qty, c.allocated_qty, c.consumed_qty FROM op_containers c
                 WHERE {TENANT_SCOPE} AND c.contract_id = ? AND c.level = 'رئيسية'
                   AND c.unit_type = 'hour' AND c.origin = 'عقد'
                   AND c.state <> 'مقفلة' AND COALESCE(c.is_deleted,0)=0
                 ORDER BY c.id", array((int) $contract['id']));
        } catch (\Throwable $t) { $roots = array(); }

        if (!$roots) {
            $out['note'] = 'لا جذرَ حاويةٍ ساعيًّا لهذا العقد — لا موازنةَ (مُعلَن)';
            return $out;
        }
        if (count($roots) > 1) {
            // لا اجتهادَ في توزيع الكمية على جذورٍ متعددة — تلفيقُ تخصيص
            $out['note'] = 'للعقد ' . count($roots) . ' جذورٍ ساعية — الموازنةُ يدويةٌ على شاشة الحاويات (CON-02 §6 + عقيدة ⑦)';
            return $out;
        }
        $root = $roots[0];
        $newCap = round((float) $root['cap_qty'] + $signedQty, 2);
        $floor = max((float) $root['allocated_qty'], (float) $root['consumed_qty']);
        if ($newCap < $floor) {
            $out['ok'] = false;
            $out['reason'] = 'التخفيضُ يهبط بسعة الحاوية #' . (int) $root['id'] . ' إلى ' . $newCap
                . ' دون المخصَّص/المستهلَك (' . $floor . ') — Σ حصصُ الموردين ≤ الملتزم (UX-05 §8.2)';
            return $out;
        }
        $out['ops'][] = array(
            'db_action' => 'update',
            'table' => 'op_containers',
            'where' => array('id' => (int) $root['id']),
            'data' => array('cap_qty' => $newCap),
        );
        $out['note'] = 'موازنةُ الجذر #' . (int) $root['id'] . ': ' . $root['cap_qty'] . ' ← ' . $newCap;
        return $out;
    }

    /** الكودُ التالي AMD-NNNN بشركة العقد — مرآةُ مولّد الشاشة. */
    private static function nextAmendmentCode($conn, $companyId)
    {
        $next = 1;
        if ($stmt = $conn->prepare("SELECT amendment_code FROM contract_amendments
                WHERE company_id = ? AND amendment_code REGEXP '^AMD-[0-9]+$' AND is_deleted = 0
                ORDER BY CAST(SUBSTRING(amendment_code, 5) AS UNSIGNED) DESC LIMIT 1")) {
            $stmt->bind_param('i', $companyId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $next = intval(substr($row['amendment_code'], 4)) + 1;
                }
            }
            $stmt->close();
        }
        return 'AMD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
