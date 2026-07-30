<?php
/**
 * app/Services/Revenue/CollectionService.php — إحكامُ التحصيل (M-05)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-03 §4: «التحصيل · قبضٌ **بمرجعٍ بنكيٍّ أو سند** · Collected جزئيًّا أو
 * كليًّا — **والرصيدُ وعمرُه يتحدثان فورًا**» · «**التحصيلُ الجزئي** — يُطبَّق
 * على **أقدم فاتورةٍ أولًا** ما لم يحدد العميلُ مرجعًا صريحًا — **والتخصيصُ
 * ظاهرٌ في الكشف لا صامتًا**» · «Invoiced → **PartiallyCollected** → Collected».
 * §7: «**bank_ref (إلزامي)** — UQ (bank_ref, amount, received_at)».
 *
 * ── أربعُ قواعدَ تحكم كلَّ قبضٍ هنا ─────────────────────────────────────────
 * ① **لا قبضَ بلا مرجع**: 422 خدمةً و`CHECK` بنيويًّا — و`legacy_no_ref` وسمُ
 *    الموروث **يُعلَن ولا يُقبل لجديد** (نمطُ M-11).
 * ② **ولا يُقبض مرتين**: (مرجع × مبلغ × يوم) فريدٌ — **409 بمرجع القائم**.
 * ③ **أقدمُ فاتورةٍ أولًا ما لم يُحدَّد مرجعٌ صريح** — و**كلُّ تخصيصٍ سطرٌ
 *    مستقلٌّ بأساسه**، فتوزيعُ دفعةٍ على فاتورتين يُرى ولا يُخمَّن.
 * ④ **والفائضُ يُعلَن ولا يُبتلع**: قبضٌ يفوق ذممَ العميل يُقيَّد بما خُصّص
 *    ويُعلن الباقيَ — «رقمٌ يختفي أسوأُ من رقمٍ معلَن».
 */

namespace App\Services\Revenue;

class CollectionService
{
    /** وسمُ الموروث — يُعلَن ولا يُقبل لقبضٍ جديد. */
    const LEGACY_REF = 'legacy_no_ref';

    /**
     * تسجيلُ قبضٍ وتخصيصُه.
     *
     * @return array{ok:bool,code:int,reason:string,payment_id:?int,allocated:float,
     *               unallocated:float,allocations:array,claims_touched:array}
     */
    public static function record($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'payment_id' => null,
                     'allocated' => 0.0, 'unallocated' => 0.0,
                     'allocations' => array(), 'claims_touched' => array());

        $clientId = isset($args['client_id']) ? (int) $args['client_id'] : 0;
        if ($clientId <= 0) { $out['code'] = 422; $out['reason'] = 'العميلُ إلزامي'; return $out; }

        $amount = isset($args['amount']) ? round((float) $args['amount'], 2) : 0.0;
        if ($amount <= 0) { $out['code'] = 422; $out['reason'] = 'المبلغُ موجبٌ إلزامًا'; return $out; }

        // ── ① لا قبضَ بلا مرجع ─────────────────────────────────────────────
        $ref = isset($args['bank_ref']) ? trim((string) $args['bank_ref']) : '';
        if ($ref === '') {
            $out['code'] = 422;
            $out['reason'] = '**المرجعُ البنكيُّ (أو السند) إلزامي** — «قبضٌ بمرجعٍ بنكيٍّ أو سند» (ENT-03 §4)';
            return $out;
        }
        if ($ref === self::LEGACY_REF) {
            $out['code'] = 422;
            $out['reason'] = '«' . self::LEGACY_REF . '» **وسمُ الموروث** يُعلَن ولا يُكتب لقبضٍ جديد';
            return $out;
        }
        $on = isset($args['received_on']) ? trim((string) $args['received_on']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ القبض إلزاميٌّ بصيغة Y-m-d'; return $out;
        }

        // ── ② ولا يُقبض مرتين ──────────────────────────────────────────────
        $dup = null;
        try {
            $dup = $gate->selectOne('fin_payments', array(
                'columns'  => array('id', 'payment_no'),
                'whereRaw' => "direction = 'collection' AND bank_ref = ? AND amount = ? AND received_on = ?",
                'params'   => array($ref, $amount, $on)));
        } catch (\Throwable $t) { $dup = null; }
        if ($dup) {
            $out['code'] = 409; $out['payment_id'] = (int) $dup['id'];
            $out['reason'] = 'قبضٌ بالمرجع نفسِه والمبلغ نفسِه في اليوم نفسِه مسجَّلٌ سلفًا: '
                           . $dup['payment_no'] . ' — **ولا يُقبض مرتين**';
            return $out;
        }

        // ── ③ التخصيص: مرجعٌ صريحٌ أو أقدمُ فاتورةٍ أولًا ───────────────────
        $explicit = isset($args['receivable_id']) ? (int) $args['receivable_id'] : 0;
        $targets = self::openReceivables($gate, $clientId, $explicit);
        if (!$targets) {
            $out['code'] = 422;
            $out['reason'] = $explicit > 0
                ? 'الذمّةُ المحددةُ غيرُ مفتوحةٍ لهذا العميل'
                : 'لا ذمّةَ مفتوحةً لهذا العميل — **ولا يُخصَّص قبضٌ لعدم**';
            return $out;
        }

        $paymentId = null; $allocations = array(); $left = $amount;
        try {
            $gate->runInTransaction(function ($g) use (
                &$paymentId, &$allocations, &$left, $clientId, $amount, $ref, $on,
                $targets, $explicit, $actor, $args
            ) {
                $paymentId = (int) $g->insert('fin_payments', array(
                    'payment_no'  => self::nextNo($g),
                    'direction'   => 'collection',
                    'party_type'  => 'client',
                    'party_ref'   => $clientId,
                    'amount'      => $amount,
                    'currency'    => isset($args['currency']) && trim((string) $args['currency']) !== ''
                                     ? strtoupper(trim((string) $args['currency'])) : 'USD',
                    'method'      => isset($args['method']) && trim((string) $args['method']) !== ''
                                     ? (string) $args['method'] : 'bank',
                    'bank_ref'    => $ref,
                    'received_on' => $on,
                    'state'       => 'executed',
                    'paid_at'     => $on . ' 00:00:00',
                    'memo'        => isset($args['memo']) ? mb_substr(trim((string) $args['memo']), 0, 200) : null,
                    'created_by'  => (int) $actor ?: null,
                ));

                foreach ($targets as $recv) {
                    if ($left <= 0.004) { break; }
                    $outstanding = round((float) $recv['outstanding'], 2);
                    if ($outstanding <= 0) { continue; }
                    $take = min($left, $outstanding);
                    $take = round($take, 2);

                    $g->insert('fin_collection_allocations', array(
                        'payment_id'    => $paymentId,
                        'receivable_id' => (int) $recv['id'],
                        'amount'        => $take,
                        'basis'         => $explicit > 0 ? 'explicit' : 'oldest_first',
                        'created_by'    => (int) $actor ?: null,
                    ));
                    $newCollected = round((float) $recv['collected'] + $take, 2);
                    $g->update('fin_receivables', array(
                        'collected' => $newCollected,
                        'state'     => ($newCollected >= round((float) $recv['amount'], 2) - 0.004)
                                       ? 'collected' : 'partial',
                    ), array('id' => (int) $recv['id']));

                    $allocations[] = array('receivable_id' => (int) $recv['id'],
                        'doc_ref' => (string) $recv['doc_ref'], 'amount' => $take,
                        'basis' => $explicit > 0 ? 'explicit' : 'oldest_first');
                    $left = round($left - $take, 2);
                }
                // أولُ تخصيصٍ يُكتب في العمود القديم أيضًا — مرآةٌ للقائم لا مصدر
                if ($allocations) {
                    $g->update('fin_payments', array('receivable_id' => $allocations[0]['receivable_id']),
                        array('id' => $paymentId));
                }
            }, 'تسجيل تحصيل ' . $ref);
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'قبضٌ مكرر (المرجع × المبلغ × اليوم)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التسجيل: ' . $t->getMessage(); return $out;
        }

        // ── «Invoiced → PartiallyCollected → Collected» (§4) ───────────────
        $touched = array();
        foreach ($allocations as $a) {
            $c = self::reflectClaimState($conn, $gate, $companyId, (int) $a['receivable_id'], $actor);
            if ($c !== null) { $touched[] = $c; }
        }

        $out['ok'] = true; $out['code'] = 200; $out['payment_id'] = (int) $paymentId;
        $out['allocated'] = round($amount - $left, 2);
        $out['unallocated'] = round($left, 2);
        $out['allocations'] = $allocations;
        $out['claims_touched'] = $touched;
        if ($out['unallocated'] > 0.004) {
            // ④ الفائضُ يُعلَن ولا يُبتلع
            $out['reason'] = '⚠ خُصّص ' . $out['allocated'] . ' و**بقي ' . $out['unallocated']
                           . ' بلا ذمّةٍ يُخصَّص لها** — يُعلَن ويُسوّى بقرار';
        }
        return $out;
    }

    /**
     * ارتدادُ حالة المستخلص من ذمّته — «والرصيدُ وعمرُه يتحدثان فورًا».
     * @return ?array{claim_id:int,state:string}
     */
    public static function reflectClaimState($conn, $gate, $companyId, $receivableId, $actor)
    {
        $recv = null;
        try { $recv = $gate->selectOne('fin_receivables', array('where' => array('id' => (int) $receivableId))); }
        catch (\Throwable $t) { return null; }
        if (!$recv) { return null; }

        // الذمّةُ ترتبط بالمستخلص بمرجعها (رقمُ الفاتورة) أو بعموده الصريح
        $claim = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('c' => 'claims')),
                "SELECT c.id, c.state FROM claims c
                  WHERE {TENANT_SCOPE} AND (c.receivable_id = ? OR c.invoice_no = ?)
                    AND COALESCE(c.is_deleted,0)=0
                  ORDER BY c.id DESC LIMIT 1",
                array((int) $receivableId, (string) $recv['doc_ref']));
            $claim = $rows ? $rows[0] : null;
        } catch (\Throwable $t) { $claim = null; }
        if (!$claim) { return null; }

        $collected = round((float) $recv['collected'], 2);
        $amount = round((float) $recv['amount'], 2);
        if ($collected <= 0.004) { return null; }
        $newState = ($collected >= $amount - 0.004) ? 'collected' : 'partially_collected';
        if ((string) $claim['state'] === $newState) { return null; }

        try {
            $gate->update('claims', array('state' => $newState), array('id' => (int) $claim['id']));
        } catch (\Throwable $t) { return null; }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'claims', 'collect_state', (int) $claim['id'],
            array('state' => (string) $claim['state']), array('state' => $newState),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        return array('claim_id' => (int) $claim['id'], 'state' => $newState);
    }

    /** الذممُ المفتوحةُ **بأقدمها أولًا** — أو الذمّةُ المحددةُ وحدَها. */
    public static function openReceivables($gate, $clientId, $explicit = 0)
    {
        try {
            if ((int) $explicit > 0) {
                $rows = $gate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                    "SELECT r.* FROM fin_receivables r
                      WHERE {TENANT_SCOPE} AND r.id = ? AND r.customer_entity_id = ?
                        AND COALESCE(r.is_deleted,0)=0 AND r.outstanding > 0",
                    array((int) $explicit, (int) $clientId));
                return $rows ? $rows : array();
            }
            return $gate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                "SELECT r.* FROM fin_receivables r
                  WHERE {TENANT_SCOPE} AND r.customer_entity_id = ?
                    AND COALESCE(r.is_deleted,0)=0 AND r.outstanding > 0
                  ORDER BY COALESCE(r.due_date, DATE(r.created_at)), r.id",
                array((int) $clientId));
        } catch (\Throwable $t) { return array(); }
    }

    /** تخصيصاتُ قبضٍ — «والتخصيصُ ظاهرٌ في الكشف لا صامتًا». */
    public static function allocationsOf($gate, $paymentId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('a' => 'fin_collection_allocations'),
                      'enrich' => array('r' => 'fin_receivables')),
                "SELECT a.*, r.doc_ref, r.amount AS recv_amount, r.collected, r.outstanding, r.state AS recv_state
                   FROM fin_collection_allocations a
                   LEFT JOIN fin_receivables r ON r.id = a.receivable_id
                  WHERE {TENANT_SCOPE} AND a.payment_id = ?
                  ORDER BY a.id", array((int) $paymentId));
        } catch (\Throwable $t) { return array(); }
    }

    /** ذممُ العميل بأعمارها — «كلُّ فاتورةٍ ذمةٌ بعمرها» (§4). */
    public static function ageing($gate, $clientId = 0)
    {
        try {
            $params = array();
            $where = '';
            if ((int) $clientId > 0) { $where = ' AND r.customer_entity_id = ?'; $params[] = (int) $clientId; }
            return $gate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                "SELECT r.id, r.doc_ref, r.customer_entity_id, r.amount, r.collected, r.outstanding,
                        r.state, r.due_date, DATE(r.created_at) AS opened_on,
                        DATEDIFF(CURDATE(), COALESCE(r.due_date, DATE(r.created_at))) AS age_days
                   FROM fin_receivables r
                  WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0" . $where . "
                  ORDER BY age_days DESC, r.id", $params);
        } catch (\Throwable $t) { return array(); }
    }

    private static function nextNo($gate)
    {
        $year = date('Y');
        try {
            $rows = $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
                "SELECT COUNT(*) AS n FROM fin_payments p
                  WHERE {TENANT_SCOPE} AND p.payment_no LIKE ?", array('RCT-' . $year . '-%'));
            $n = $rows ? ((int) $rows[0]['n'] + 1) : 1;
        } catch (\Throwable $t) { $n = 1; }
        return 'RCT-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
