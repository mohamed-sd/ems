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

require_once __DIR__ . '/../../../includes/catch_log.php';

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
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $dup'); $dup = null; }
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
                        // P-07: الفاتورةُ **واحدٌ من خمسةِ أهداف** لا الهدفَ الوحيد
                        'target_kind'   => 'invoice',
                        'target_ref'    => (int) $recv['id'],
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
                // و**العدّادُ يُرفع في المعاملة نفسِها** (P-07): `CHECK` يحرس
                // «Σ ≤ السند»، و`unallocated_amount` مولَّدٌ فيظهر الباقي رصيدًا.
                $g->update('fin_payments', array(
                    'allocated_amount' => round($amount - $left, 2),
                ) + ($allocations ? array('receivable_id' => $allocations[0]['receivable_id']) : array()),
                    array('id' => $paymentId));
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
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $claim'); $claim = null; }
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

    // ═════════════════════════════════════════════════════════════════════
    // P-07 — أهدافُ التخصيص الخمسة (الملحق §3-`P-07`)
    // «توسعةٌ لا هدم»: `record()` تبقى كما هي وهذه تضيف الأهدافَ الأربعةَ
    // الأخرى فوقها — والقيدُ الحاكمُ **Σ ≤ السند** محمولٌ على السند.
    // ═════════════════════════════════════════════════════════════════════

    const TARGET_KINDS = array('advance', 'invoice', 'milestone', 'retention', 'final');

    const TARGET_AR = array(
        'advance' => 'مقدَّم', 'invoice' => 'فاتورة', 'milestone' => 'معلَم',
        'retention' => 'محتجَز', 'final' => 'ختامية',
    );

    /** نوعُ سطرِ خطة الدفع (P-05) المقابلُ لكل هدفٍ غيرِ الفاتورة. */
    const SCHEDULE_KIND_OF = array(
        'advance' => 'advance', 'milestone' => 'milestone',
        'retention' => 'retention_release', 'final' => 'final',
    );

    /**
     * تخصيصُ سندٍ قائمٍ على **أهدافٍ متعددة** — «سندٌ واحدٌ على مقدمٍ وفاتورتين».
     *
     * @param array $targets كلٌّ: {target_kind, target_ref, amount, note?}
     * @return array{ok:bool,code:int,reason:string,allocated:float,unallocated:float,rows:array}
     */
    public static function allocate($conn, $gate, $companyId, $paymentId, array $targets, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'allocated' => 0.0,
                     'unallocated' => 0.0, 'rows' => array());
        $p = null;
        try { $p = $gate->selectOne('fin_payments', array('where' => array('id' => (int) $paymentId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $p'); $p = null; }
        if (!$p) { $out['code'] = 404; $out['reason'] = 'السندُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $p['direction'] !== 'collection') {
            $out['code'] = 422;
            $out['reason'] = '**التخصيصُ للقبض لا للصرف** — والسندُ ' . $p['direction']; return $out;
        }
        if (!$targets) { $out['code'] = 422; $out['reason'] = 'لا أهدافَ في الطلب'; return $out; }

        $amount = round((float) $p['amount'], 2);
        $already = round((float) $p['allocated_amount'], 2);
        $free = round($amount - $already, 2);

        // ── التحقُّقُ **كلُّه قبل أيِّ كتابة** ────────────────────────────────
        $clean = array(); $sum = 0.0;
        foreach ($targets as $t) {
            $kind = (string) (isset($t['target_kind']) ? $t['target_kind'] : '');
            if (!in_array($kind, self::TARGET_KINDS, true)) {
                $out['code'] = 422; $out['reason'] = 'هدفٌ غيرُ معروف: ' . $kind; return $out;
            }
            $ref = (int) (isset($t['target_ref']) ? $t['target_ref'] : 0);
            if ($ref <= 0) {
                $out['code'] = 422;
                $out['reason'] = '**مرجعُ الهدف إلزامي** — ولا يُخصَّص قبضٌ لمجهول'; return $out;
            }
            $amt = round((float) (isset($t['amount']) ? $t['amount'] : 0), 2);
            if ($amt <= 0) {
                $out['code'] = 422; $out['reason'] = 'مبلغُ التخصيص موجبٌ إلزامًا'; return $out;
            }
            $key = $kind . ':' . $ref;
            if (isset($clean[$key])) {
                $out['code'] = 409;
                $out['reason'] = 'الهدفُ «' . self::TARGET_AR[$kind] . ' #' . $ref
                               . '» مكرَّرٌ في الطلب — **ولا سطران لهدفٍ واحدٍ من سندٍ واحد**';
                return $out;
            }
            // الهدفُ **موجودٌ ومفتوح** — لا يُخصَّص قبضٌ لعدم
            // (P-08: والمقارنةُ **بالمعادل** حين تختلف العملتان)
            $chk = self::checkTarget($gate, $kind, $ref, $amt,
                (string) $p['currency'], (string) ($p['received_on'] ?: date('Y-m-d')));
            if (!$chk['ok']) { $out['code'] = $chk['code']; $out['reason'] = $chk['reason']; return $out; }
            $clean[$key] = array('kind' => $kind, 'ref' => $ref, 'amount' => $amt,
                                 'recv' => $chk['recv'], 'fx' => $chk['fx'],
                                 'note' => isset($t['note']) ? mb_substr((string) $t['note'], 0, 255) : null);
            $sum = round($sum + $amt, 2);
        }

        // **Σ لا يتجاوز السند أبدًا** — والرسالةُ تسمّي الفائض
        if ($sum > $free + 0.0001) {
            $out['code'] = 409;
            $out['reason'] = '**Σ التخصيصات ' . $sum . ' تتجاوز المتاحَ من السند ' . $free
                . '** (المبلغُ ' . $amount . ' · المخصَّصُ سلفًا ' . $already . ') — '
                . 'والفائضُ ' . round($sum - $free, 2) . ' **يُعلَن ولا يُبتلع**';
            $out['allocated'] = $already; $out['unallocated'] = $free;
            return $out;
        }

        $rows = array(); $fxRows = array();
        $payCur = (string) $p['currency'];
        $payOn = (string) ($p['received_on'] ?: date('Y-m-d'));
        try {
            $gate->runInTransaction(function ($g) use (
                $clean, $paymentId, $already, $sum, $actor, &$rows, &$fxRows, $payCur, $payOn
            ) {
                foreach ($clean as $c) {
                    $fx = $c['fx'];
                    // P-08: **الفرقُ المحقَّق** = قيمةُ النقد بالوظيفية − قيمةُ
                    // الذمّة الملغاة بسعرها **المجمَّد يومَ الاعتراف**.
                    $diff = 0.0;
                    $rateRec = null;
                    if ($c['kind'] === 'invoice' && $c['recv'] !== null
                        && $c['recv']['fx_rate_recognized'] !== null) {
                        $rateRec = (float) $c['recv']['fx_rate_recognized'];
                        $diff = \App\Services\Finance\FxSettlementService::realizedDiff(
                            $fx['amount_target'], $fx['base'], $rateRec);
                    }
                    $allocId = (int) $g->insert('fin_collection_allocations', array(
                        'payment_id'     => (int) $paymentId,
                        'receivable_id'  => ($c['kind'] === 'invoice') ? (int) $c['ref'] : null,
                        'target_kind'    => $c['kind'],
                        'target_ref'     => (int) $c['ref'],
                        'amount'         => $c['amount'],
                        'pay_currency'   => $payCur,
                        'target_currency' => (string) $fx['target_currency'],
                        'amount_target'  => $fx['amount_target'],
                        'fx_rate_pay'    => $fx['rate_pay'],
                        'fx_rate_target' => $fx['rate_target'],
                        'base_amount'    => $fx['base'],
                        'fx_diff_base'   => $diff,
                        'basis'          => 'explicit',
                        'note'           => $c['note'],
                        'created_by'     => (int) $actor ?: null,
                    ));
                    // الفاتورةُ تُطفأ **بالمعادل** لا بالمبلغ الخام (§9-⑨)
                    if ($c['kind'] === 'invoice' && $c['recv'] !== null) {
                        $newCollected = round((float) $c['recv']['collected'] + $fx['amount_target'], 2);
                        $g->update('fin_receivables', array(
                            'collected' => $newCollected,
                            'state' => ($newCollected >= round((float) $c['recv']['amount'], 2) - 0.004)
                                       ? 'collected' : 'partial',
                        ), array('id' => (int) $c['ref']));
                    }
                    // وسطرُ خطة الدفع (P-05) يُحدَّث في مستلمِه — **لا رقمَ ثانٍ**
                    if ($c['kind'] !== 'invoice' && $c['recv'] !== null) {
                        $rec = round((float) $c['recv']['received_amount'] + $fx['amount_target'], 2);
                        $g->update('contract_payment_schedule', array(
                            'received_amount' => $rec,
                            'state' => ($rec >= round((float) $c['recv']['amount_expected'], 2) - 0.004)
                                       ? 'completed' : 'partial',
                        ), array('id' => (int) $c['ref']));
                    }
                    $rows[] = array('kind' => $c['kind'], 'ref' => (int) $c['ref'],
                                    'amount' => $c['amount'], 'amount_target' => $fx['amount_target'],
                                    'target_currency' => (string) $fx['target_currency'],
                                    'fx_diff' => $diff);
                    if (abs($diff) >= 0.005) {
                        $fxRows[] = array('alloc_id' => $allocId, 'diff' => $diff,
                                          'from' => (string) $fx['target_currency'],
                                          'rate_from' => $rateRec, 'rate_to' => $fx['rate_pay']);
                    }
                }
                $g->update('fin_payments', array('allocated_amount' => round($already + $sum, 2)),
                    array('id' => (int) $paymentId));
            }, 'تخصيص سند ' . $paymentId);
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409;
                $out['reason'] = '**الهدفُ مخصَّصٌ سلفًا من هذا السند** — والتعديلُ بسطرٍ جديدٍ لهدفٍ آخر';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التخصيص: ' . $t->getMessage(); return $out;
        }

        // ارتدادُ حالة المستخلص لكل فاتورةٍ مسّها التخصيص
        foreach ($rows as $r) {
            if ($r['kind'] === 'invoice') {
                self::reflectClaimState($conn, $gate, $companyId, (int) $r['ref'], $actor);
            }
        }
        // P-08: **وفرقُ الصرف بسطره** في العملة الوظيفية — بابٌ غيرُ باب الذمّة
        foreach ($fxRows as $f) {
            \App\Services\Finance\FxSettlementService::recordDiff($conn, $gate, $companyId, array(
                'kind' => 'realized', 'source_kind' => 'allocation',
                'source_ref' => (int) $f['alloc_id'], 'party_ref' => (int) $p['party_ref'],
                'from_currency' => $f['from'], 'amount' => $f['diff'],
                'rate_from' => $f['rate_from'], 'rate_to' => $f['rate_to'],
                'occurred_on' => $payOn,
                'note' => 'فرقُ صرفٍ محقَّقٌ عند تخصيص السند ' . (int) $paymentId,
            ), $actor);
        }
        self::audit($conn, $companyId, $actor, 'allocate_targets', (int) $paymentId,
            array('allocated' => $already), array('allocated' => round($already + $sum, 2)));

        $out['ok'] = true; $out['code'] = 200; $out['rows'] = $rows;
        $out['allocated'] = round($already + $sum, 2);
        $out['unallocated'] = round($amount - $already - $sum, 2);
        $fxTotal = 0.0; $crossed = 0;
        foreach ($rows as $r) {
            $fxTotal = round($fxTotal + (float) $r['fx_diff'], 2);
            if ((string) $r['target_currency'] !== $payCur) { $crossed++; }
        }
        $out['fx_diff'] = $fxTotal;
        $out['reason'] = 'خُصّص ' . $sum . ' ' . $payCur . ' على ' . count($rows) . ' هدفًا · '
            . 'المخصَّصُ ' . $out['allocated'] . ' من ' . $amount
            . ($crossed > 0 ? (' · **' . $crossed . ' هدفًا بعملةٍ أخرى أُطفئ بالمعادل**') : '')
            . (abs($fxTotal) >= 0.005
               ? (' · **وفرقُ صرفٍ ' . $fxTotal . ' بسطره في العملة الوظيفية**') : '')
            . ($out['unallocated'] > 0.004
               ? (' · **وبقي ' . $out['unallocated'] . ' رصيدًا غيرَ مخصَّصٍ ظاهرًا**')
               : ' · **Σ التخصيصات = السند**');
        return $out;
    }

    /**
     * الهدفُ موجودٌ ومفتوحٌ ويتسع للمبلغ — **قبل أيِّ كتابة**.
     * و**P-08**: حين تختلف عملةُ السداد عن عملة الهدف تُقارَن **بالمعادل**،
     * و`fx` تحمل ما يلزم لكتابة السطر وحسابِ الفرق المحقَّق.
     */
    private static function checkTarget($gate, $kind, $ref, $amount, $payCur = '', $payDate = null)
    {
        $o = array('ok' => false, 'code' => 422, 'reason' => '', 'recv' => null, 'fx' => null);
        if ($kind === 'invoice') {
            $r = null;
            try { $r = $gate->selectOne('fin_receivables', array('where' => array('id' => (int) $ref))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $r'); $r = null; }
            if (!$r) { $o['code'] = 404; $o['reason'] = 'الذمّةُ #' . $ref . ' غيرُ موجودة'; return $o; }
            $outst = round((float) $r['outstanding'], 2);
            if ($outst <= 0.004) {
                $o['code'] = 409;
                $o['reason'] = 'الذمّةُ #' . $ref . ' **مسدَّدةٌ كاملًا** — ولا يُخصَّص قبضٌ لمسدَّد';
                return $o;
            }
            $fx = self::fxOf($amount, $payCur, (string) $r['currency'], $payDate);
            if (!$fx['ok']) { $o['code'] = 422; $o['reason'] = $fx['reason']; return $o; }
            if ($fx['amount_target'] > $outst + 0.0001) {
                $o['code'] = 409;
                $o['reason'] = 'المبلغُ ' . $amount . ' ' . $payCur
                             . ($fx['crossed'] ? (' (= ' . $fx['amount_target'] . ' ' . $r['currency'] . ')') : '')
                             . ' **يتجاوز متبقّي الذمّة ' . $outst . ' ' . $r['currency'] . '** — '
                             . 'والزيادةُ **رصيدٌ دائنٌ للعميل لا إيراد**';
                return $o;
            }
            $o['ok'] = true; $o['code'] = 200; $o['recv'] = $r; $o['fx'] = $fx; return $o;
        }
        // الأهدافُ الأربعةُ الأخرى **أسطرُ خطةِ الدفع** (P-05) — لا جدولَ ثالث
        $r = null;
        try { $r = $gate->selectOne('contract_payment_schedule', array('where' => array('id' => (int) $ref))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $r'); $r = null; }
        if (!$r) { $o['code'] = 404; $o['reason'] = 'سطرُ خطة الدفع #' . $ref . ' غيرُ موجود'; return $o; }
        if ($r['effective_to'] !== null) {
            $o['code'] = 423;
            $o['reason'] = 'السطرُ #' . $ref . ' **من نسخةٍ مختومة** — والتخصيصُ على النافذة'; return $o;
        }
        $want = self::SCHEDULE_KIND_OF[$kind];
        if ((string) $r['payment_kind'] !== $want) {
            $o['code'] = 422;
            $o['reason'] = '**الهدفُ «' . self::TARGET_AR[$kind] . '» لا يطابق نوعَ السطر «'
                . $r['payment_kind'] . '»** — ولا يُخصَّص مقدمٌ على معلَمٍ ولا العكس';
            return $o;
        }
        $left = round((float) $r['amount_expected'] - (float) $r['received_amount'], 2);
        if ($left <= 0.004) {
            $o['code'] = 409;
            $o['reason'] = 'السطرُ #' . $ref . ' **مستلَمٌ كاملًا** — ولا يُخصَّص قبضٌ لمكتمل'; return $o;
        }
        $fx = self::fxOf($amount, $payCur, (string) $r['currency'], $payDate);
        if (!$fx['ok']) { $o['code'] = 422; $o['reason'] = $fx['reason']; return $o; }
        if ($fx['amount_target'] > $left + 0.0001) {
            $o['code'] = 409;
            $o['reason'] = 'المبلغُ ' . $amount . ' ' . $payCur
                         . ($fx['crossed'] ? (' (= ' . $fx['amount_target'] . ' ' . $r['currency'] . ')') : '')
                         . ' **يتجاوز متبقّي السطر ' . $left . ' ' . $r['currency'] . '** — '
                         . 'والزيادةُ **رصيدٌ دائنٌ للعميل لا إيراد**';
            return $o;
        }
        $o['ok'] = true; $o['code'] = 200; $o['recv'] = $r; $o['fx'] = $fx; return $o;
    }

    /**
     * P-08 — **الذمةُ تُطفأ بالمعادل**: تحويلُ مبلغ السداد إلى عملة الهدف،
     * وقيمتُه بالعملة الوظيفية. و**النقصُ بعد التحويل رصيدٌ غيرُ مسددٍ يبقى في
     * الذمّة** — لا فرقُ صرفٍ يُقفل به الباب (§9-⑨).
     */
    private static function fxOf($amount, $payCur, $targetCur, $payDate)
    {
        require_once dirname(__DIR__) . '/Finance/FxSettlementService.php';
        $payCur = trim((string) $payCur);
        $targetCur = trim((string) $targetCur);
        $o = array('ok' => true, 'reason' => '', 'crossed' => false,
                   'pay_currency' => $payCur, 'target_currency' => $targetCur,
                   'amount_target' => round((float) $amount, 2),
                   'rate_pay' => null, 'rate_target' => null,
                   'base' => round((float) $amount, 2));
        if ($targetCur === '') {
            // ذمّةٌ بلا عملةٍ — وهو ما كانت عليه البنيةُ قبل P-08
            $o['ok'] = false;
            $o['reason'] = '**الهدفُ بلا عملةٍ مسجَّلة** — ولا يُخصَّص قبضٌ لمبلغٍ لا يُعرف بأيِّ عملةٍ هو';
            return $o;
        }
        $r = \App\Services\Finance\FxSettlementService::convert($amount, $payCur, $targetCur, $payDate);
        if (!$r['ok']) { $o['ok'] = false; $o['reason'] = $r['reason']; return $o; }
        $o['crossed'] = ($payCur !== $targetCur);
        $o['amount_target'] = $r['amount'];
        $o['rate_pay'] = $r['rate_from'];
        $o['rate_target'] = $r['rate_to'];
        $o['base'] = $r['base'];
        return $o;
    }

    /** **الرصيدُ غيرُ المخصَّص ظاهرًا** — لا رقمٌ في رسالةٍ تختفي. */
    public static function unallocatedOf($gate, $paymentId)
    {
        try {
            $p = $gate->selectOne('fin_payments', array('where' => array('id' => (int) $paymentId)));
            if (!$p) { return null; }
            return array(
                'amount' => round((float) $p['amount'], 2),
                'allocated' => round((float) $p['allocated_amount'], 2),
                'unallocated' => round((float) $p['unallocated_amount'], 2),
                'currency' => (string) $p['currency'],
            );
        } catch (\Throwable $t) { return null; }
    }

    /** السنداتُ التي فيها رصيدٌ غيرُ مخصَّص — «الفجوةُ تُرى». */
    public static function unallocatedPayments($gate)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
                "SELECT p.id, p.payment_no, p.party_ref, p.amount, p.allocated_amount,
                        p.unallocated_amount, p.currency, p.received_on, p.bank_ref
                   FROM fin_payments p
                  WHERE {TENANT_SCOPE} AND p.direction = 'collection'
                    AND COALESCE(p.is_deleted,0) = 0 AND p.unallocated_amount > 0.004
                  ORDER BY p.received_on DESC, p.id DESC LIMIT 200");
        } catch (\Throwable $t) { return array(); }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'finance', 'fin_collection_allocations', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
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
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'تعذّر عدُّ الإيصالاتِ فيبدأ الترقيمُ من ١ — والتفرُّدُ محروسٌ بقيدِ القاعدةِ لا بالعدّ'); $n = 1; }
        return 'RCT-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
