<?php
/**
 * app/Services/Settlement/SupplierAdvanceService.php — سلفياتُ الموردين (M-12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-02 §3:
 *  · «**بوابةٌ واحدةٌ** لكل ما يُصرف للمورد **خارج التسوية** (نقدًا · نيابةً ·
 *    **عهدةً**) **بمستندٍ وجدولِ استرداد** — ورصيدُها **ظاهرٌ في بطاقته دائمًا**».
 *  · «**لا إدخالَ حرًّا** — كلُّ تحميلٍ سطرٌ برابط مستنده؛ **وما لا مستندَ له لا
 *    يُحمَّل** — والمبلغُ يُقرأ من مصدره لا يُكتب».
 *  · «**المقاصّة الظاهرة** — المستحقُّ يُحتسب كاملًا والتحميلاتُ تُعرض كاملةً ثم
 *    تُجرى المقاصّة — فلا يُبخَس المصروفُ ولا يُخفى المدين».
 *
 * ── القاعدةُ الحاكمة في توقيت الاسترداد ────────────────────────────────────
 * القسطُ يظهر **بندًا في التسوية عند توليدها**، ولا يُنقص الرصيدَ إلا **عند
 * اعتمادها**: التسويةُ المسودةُ **نيّةٌ** والمعتمَدةُ **واقعة** — ولا يُنقص رصيدٌ
 * بنيّة. ومفتاحُ `UQ(سلفة × تسوية)` يمنع الاستردادَ مرتين بنيويًّا.
 *
 * (البنيةُ توأمُ `employee_advances` في H-09-④ عمدًا — نمطٌ واحدٌ يُثبت نفسَه.)
 */

namespace App\Services\Settlement;

require_once __DIR__ . '/../../../includes/catch_log.php';

class SupplierAdvanceService
{
    const TYPES = array('cash', 'on_behalf', 'custody');
    const TYPE_LABELS = array('cash' => 'نقدًا', 'on_behalf' => 'دفعٌ نيابةً عنه', 'custody' => 'عهدة');
    const DEDUCTIBLE_STATES = array('active', 'approved');

    // ═════════════════════════════════════════════════════════════════════
    // ① البوابة
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,advance_id:?int} */
    public static function open($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'advance_id' => null);

        $sup = isset($args['supplier_id']) ? (int) $args['supplier_id'] : 0;
        if ($sup <= 0) { $out['code'] = 422; $out['reason'] = 'الموردُ إلزامي'; return $out; }

        $type = isset($args['advance_type']) ? trim((string) $args['advance_type']) : 'cash';
        if (!in_array($type, self::TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'نوعُ صرفٍ خارج الثلاثة (نقدًا · نيابةً · عهدةً)'; return $out;
        }
        $amount = isset($args['amount']) ? round((float) $args['amount'], 2) : 0.0;
        if ($amount <= 0) { $out['code'] = 422; $out['reason'] = 'مبلغُ السلفة موجب'; return $out; }

        $doc = isset($args['doc_ref']) ? trim((string) $args['doc_ref']) : '';
        if ($doc === '') {
            $out['code'] = 422;
            $out['reason'] = 'سندُ الصرف إلزامي — «**وما لا مستندَ له لا يُحمَّل**» (ENT-02 §3)';
            return $out;
        }
        $issued = isset($args['issued_date']) ? trim((string) $args['issued_date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issued)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الصرف إلزامي'; return $out;
        }
        $count = isset($args['installments_count']) ? (int) $args['installments_count'] : 1;
        if ($count < 1) { $out['code'] = 422; $out['reason'] = 'عددُ الأقساط واحدٌ فأكثر'; return $out; }
        $inst = (isset($args['installment_amount']) && trim((string) $args['installment_amount']) !== '')
                ? round((float) $args['installment_amount'], 2)
                : round($amount / $count, 2);
        if ($inst <= 0 || $inst > $amount) {
            $out['code'] = 422; $out['reason'] = 'قسطُ الاسترداد موجبٌ ولا يتجاوز الأصل'; return $out;
        }

        $s = null;
        try { $s = $gate->selectOne('suppliers', array('columns' => array('id'), 'where' => array('id' => $sup))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $s'); $s = null; }
        if (!$s) { $out['code'] = 422; $out['reason'] = 'الموردُ غيرُ موجودٍ في نطاقك'; return $out; }

        try {
            // ⚠ `balance` مولَّد — لا يُكتب (كتابتُه ترفض الصفَّ كلَّه)
            $out['advance_id'] = (int) $gate->insert('supplier_advance_requests', array(
                'supplier_id' => $sup,
                'supplier_contract_id' => isset($args['supplier_contract_id'])
                    && (int) $args['supplier_contract_id'] > 0 ? (int) $args['supplier_contract_id'] : null,
                'advance_type' => $type, 'amount' => $amount,
                'currency' => isset($args['currency']) && trim((string) $args['currency']) !== ''
                              ? strtoupper(trim((string) $args['currency'])) : null,
                'doc_ref' => mb_substr($doc, 0, 120), 'issued_date' => $issued,
                'installments_count' => $count, 'installment_amount' => $inst,
                'first_recovery_period' => isset($args['first_recovery_period'])
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['first_recovery_period'])
                    ? (string) $args['first_recovery_period'] : $issued,
                'recovered' => 0.00, 'state' => 'draft',
                'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                          ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'open', (int) $out['advance_id'],
            array(), array('supplier' => $sup, 'amount' => $amount, 'doc' => $doc));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** اعتمادُ سلفةٍ — **«فصلُ اليدين»** كتسويةِ ENT-02 §15.4 نفسِها (403). */
    public static function approve($conn, $gate, $companyId, $advanceId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $a = self::advanceOf($gate, $advanceId);
        if (!$a) { $out['code'] = 404; $out['reason'] = 'السلفةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $a['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'السلفةُ «' . $a['state'] . '» — الاعتمادُ للمسودة'; return $out;
        }
        if ((int) $a['created_by'] > 0 && (int) $a['created_by'] === (int) $actor) {
            $out['code'] = 403; $out['reason'] = 'لا يعتمد المرءُ ما أعدّ (فصلُ اليدين)'; return $out;
        }
        try {
            $gate->update('supplier_advance_requests', array(
                'state' => 'active', 'approved_by' => (int) $actor ?: null,
                'approved_at' => date('Y-m-d H:i:s')), array('id' => (int) $advanceId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الاعتماد: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'approve', (int) $advanceId,
            array('state' => 'draft'), array('state' => 'active'));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الوصلُ بالتسوية
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أقساطُ السلف المستحقةُ لموردٍ في فترة — **بندًا في التسوية بمستنده**.
     * تُستدعى من `SettlementService::collectLines` فرعِ المورد.
     *
     * @return array أسطرُ تحميلٍ بشكل `collectLines`
     */
    public static function chargeLines($gate, $supplierId, $from, $to)
    {
        $lines = array();
        foreach (self::dueAdvances($gate, $supplierId, $to) as $a) {
            $balance = round((float) $a['amount'] - (float) $a['recovered'], 2);
            if ($balance <= 0) { continue; }
            $take = min(round((float) $a['installment_amount'], 2), $balance);
            if ($take <= 0) { continue; }
            $lines[] = array(
                'line_kind'   => 'charge',
                'charge_type' => 'advance',
                'source_kind' => 'supplier_advance',
                'source_ref'  => (string) $a['id'],
                'description' => 'قسطُ سلفةٍ (' . (isset(self::TYPE_LABELS[$a['advance_type']])
                                  ? self::TYPE_LABELS[$a['advance_type']] : $a['advance_type'])
                                 . ') — سند ' . $a['doc_ref'] . ' · الرصيدُ قبلَه ' . $balance,
                'work_date'   => (string) $to,
                'amount'      => $take,
                'currency'    => ($a['currency'] !== null && $a['currency'] !== '')
                                 ? (string) $a['currency'] : 'SDG',
            );
        }
        return $lines;
    }

    /**
     * تطبيقُ الاسترداد **عند اعتماد التسوية** — لا عند توليدها.
     * المسودةُ نيّةٌ والمعتمَدةُ واقعة، ولا يُنقص رصيدٌ بنيّة.
     * والعطالةُ بمفتاح `UQ(سلفة × تسوية)`.
     *
     * @return array{applied:int,total:float}
     */
    public static function applyRecoveries($conn, $gate, $companyId, $settlementId, $actor)
    {
        $out = array('applied' => 0, 'total' => 0.0);
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'settlement_lines')),
                "SELECT l.source_ref, l.amount FROM settlement_lines l
                  WHERE {TENANT_SCOPE} AND l.settlement_id = ? AND l.source_kind = 'supplier_advance'",
                array((int) $settlementId));
        } catch (\Throwable $t) {
            /* ◆ FR-FIN-002/003 — **الفشلُ ليس قائمةً فارغة**: كان فشلُ قراءةِ
             *   سطورِ السلفةِ يُعامَل «لا سلفةَ تُسترد»، فتُعتمد التسويةُ ورصيدُ
             *   المورّدِ لا ينقص — **مالٌ لا يُسترَدُّ بصمتِ عطب**. ⇒ يُرفَع. */
            throw new \RuntimeException('SETTLEMENT_COMPONENT_FAILED:advance recovery read: '
                . $t->getMessage(), 0, $t);
        }

        foreach ($rows as $r) {
            $advId = (int) $r['source_ref'];
            $amount = round((float) $r['amount'], 2);
            if ($advId <= 0 || $amount <= 0) { continue; }
            $a = self::advanceOf($gate, $advId);
            if (!$a) { continue; }
            $balance = round((float) $a['amount'] - (float) $a['recovered'], 2);
            $take = min($amount, $balance);
            if ($take <= 0) { continue; }

            try {
                $gate->insert('supplier_advance_recoveries', array(
                    'advance_id' => $advId, 'settlement_id' => (int) $settlementId,
                    'amount' => $take, 'doc_ref' => mb_substr((string) $a['doc_ref'], 0, 120),
                    'note' => 'استردادٌ باعتماد التسوية #' . (int) $settlementId,
                    'created_by' => (int) $actor ?: null,
                ));
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'UQ: استُرد بهذه التسوية سلفًا — لا ضجيج');
                continue;   // UQ: استُرد بهذه التسوية سلفًا — لا ضجيج
            }

            $rec = round((float) $a['recovered'] + $take, 2);
            $data = array('recovered' => $rec);
            if ($rec >= (float) $a['amount']) { $data['state'] = 'settled'; }
            try { $gate->update('supplier_advance_requests', $data, array('id' => $advId)); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'M-12 recovery #'); error_log('M-12 recovery #' . $advId . ': ' . $t->getMessage()); }

            $out['applied']++; $out['total'] = round($out['total'] + $take, 2);
        }
        if ($out['applied'] > 0) {
            self::audit($conn, $companyId, $actor, 'recover', (int) $settlementId,
                array(), array('applied' => $out['applied'], 'total' => $out['total']));
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function dueAdvances($gate, $supplierId, $periodTo)
    {
        $states = "'" . implode("','", self::DEDUCTIBLE_STATES) . "'";
        try {
            return $gate->scopedQuery(array('scope' => array('a' => 'supplier_advance_requests')),
                "SELECT a.* FROM supplier_advance_requests a
                  WHERE {TENANT_SCOPE} AND a.supplier_id = ? AND COALESCE(a.is_deleted,0)=0
                    AND a.state IN ({$states})
                    AND (a.first_recovery_period IS NULL OR a.first_recovery_period <= ?)
                    AND a.recovered < a.amount
                  ORDER BY a.issued_date, a.id", array((int) $supplierId, (string) $periodTo));
        } catch (\Throwable $t) { return array(); }
    }

    public static function advanceOf($gate, $advanceId)
    {
        try { return $gate->selectOne('supplier_advance_requests', array('where' => array('id' => (int) $advanceId))); }
        catch (\Throwable $t) { return null; }
    }

    /** «رصيدُها ظاهرٌ في بطاقته دائمًا» — كلُّ سلف موردٍ بأرصدتها. */
    public static function advancesOf($gate, $supplierId = 0)
    {
        try {
            $where = (int) $supplierId > 0 ? ' AND a.supplier_id = ?' : '';
            $params = (int) $supplierId > 0 ? array((int) $supplierId) : array();
            return $gate->scopedQuery(array('scope' => array('a' => 'supplier_advance_requests')),
                "SELECT a.* FROM supplier_advance_requests a
                  WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0" . $where . "
                  ORDER BY a.id DESC", $params);
        } catch (\Throwable $t) { return array(); }
    }

    /** إجماليُّ رصيدِ موردٍ — للبطاقة. */
    public static function openBalance($gate, $supplierId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('a' => 'supplier_advance_requests')),
                "SELECT ROUND(SUM(a.amount - a.recovered),2) b FROM supplier_advance_requests a
                  WHERE {TENANT_SCOPE} AND a.supplier_id = ? AND COALESCE(a.is_deleted,0)=0
                    AND a.state IN ('active','approved')", array((int) $supplierId));
            return $r && $r[0]['b'] !== null ? round((float) $r[0]['b'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', 'supplier_advance_requests', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
