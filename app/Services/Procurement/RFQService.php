<?php
/**
 * app/Services/Procurement/RFQService.php — دورةُ عروض الموردين (H-21)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-05 §2.1: «مساحةُ عملٍ جديدة: **بنودُ الاحتياج من التزامات عقد العميل**».
 * §8.2: «**بنودُ RFQ من الالتزامات اشتقاقًا لا إدخالًا**» · «عقدٌ بلا التزاماتٍ
 * → **422** · عرضٌ بعد الإقفال → **423** · موردٌ يقرأ عرضَ غيره → **403
 * مسجَّلة** · تخصيصٌ يجاوز الالتزام → **409 بقيمة المتاح**» · «Awarded جزئيًّا
 * (12k+8k) وΣ=20k — ومحاولةُ 21k → **409**» · «Awarded → Contracted →
 * ContainersAllocated» · «**ولا حدثَ ماليًّا قبل التنفيذ الفعلي**».
 *
 * ── خمسُ قواعد ──────────────────────────────────────────────────────────────
 * ① **اشتقاقًا لا إدخالًا**: البنودُ تُولَّد من `contract_commitments`، ولا
 *    مَدخلَ لكميةٍ بيد. وعقدٌ بلا التزاماتٍ ⇒ **422** لا طلبٌ فارغ.
 * ② **والإقفالُ يحكم**: عرضٌ بعد موعد الإقفال أو بعد `closed` ⇒ **423**.
 * ③ **والعزلُ متبادل**: `quotesForSupplier` تُرجع عرضَ صاحبها وحدَه، وقراءةُ
 *    غيره ⇒ **403 تُسجَّل في سجل الأمن** — لا تُرجَع فارغةً صامتة.
 * ④ **والترسيةُ لا تجاوز الالتزام**: البندُ يحمل `qty_awarded` بـ`CHECK`،
 *    والترسيةُ **معاملةٌ واحدة** — فـ409 **بقيمة المتاح** لا برفضٍ أعمى.
 * ⑤ **ولا حدثَ ماليًّا هنا**: الأحداثُ **حقائقُ محايدة** (`publishFact`) —
 *    والمالُ يبدأ من الوحدات المنفَّذة (FES) لا من ترسية.
 */

namespace App\Services\Procurement;

require_once __DIR__ . '/../../../includes/catch_log.php';

class RFQService
{
    /** الحالاتُ التي تُقبل فيها العروض. */
    const OPEN_STATES = array('sent');

    /** التزاماتُ العميل التي **نحن** ملتزمون بها ⇒ نطلب لها موردًا. */
    const COMMITMENT_SCOPE = 'client';
    const COMMITMENT_OBLIGOR = 'company';

    // ═════════════════════════════════════════════════════════════════════
    // ① الفتحُ اشتقاقًا
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,rfq_id:?int,lines:int}
     */
    public static function openFromContract($conn, $gate, $companyId, $contractId, $dueDate, $actor, $title = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rfq_id' => null, 'lines' => 0);
        $contractId = (int) $contractId;
        if ($contractId <= 0) { $out['code'] = 422; $out['reason'] = 'العقدُ إلزامي'; return $out; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dueDate)) {
            $out['code'] = 422;
            $out['reason'] = '**موعدُ الإقفال إلزامي** — وبلا موعدٍ لا معنى لـ«عرضٌ بعد الإقفال»';
            return $out;
        }

        // ① اشتقاقًا لا إدخالًا — والالتزاماتُ هي المصدر
        $commits = self::commitmentsOf($gate, $contractId);
        if (!$commits) {
            $out['code'] = 422;
            $out['reason'] = '**عقدٌ بلا التزاماتٍ** يلتزم بها طرفُنا (§8.2) — '
                           . 'فلا بنودَ تُشتق، ولا يُفتح طلبٌ فارغ';
            return $out;
        }

        $rfqId = null; $n = 0;
        $no = 'RFQ-' . date('Y') . '-' . strtoupper(substr(sha1($contractId . microtime(true)), 0, 6));
        try {
            $gate->runInTransaction(function ($g) use (&$rfqId, &$n, $contractId, $dueDate,
                                                       $commits, $actor, $no, $title) {
                $rfqId = (int) $g->insert('supplier_rfqs', array(
                    'rfq_no' => $no, 'client_contract_id' => $contractId,
                    'title' => $title !== '' ? mb_substr((string) $title, 0, 160) : null,
                    'due_date' => (string) $dueDate, 'state' => 'draft',
                    'created_by' => (int) $actor ?: null,
                ));
                if ($rfqId <= 0) { throw new \RuntimeException('تعذّر إنشاءُ الطلب'); }
                $i = 0;
                foreach ($commits as $c) {
                    $i++;
                    $g->insert('rfq_lines', array(
                        'rfq_id' => $rfqId, 'commitment_id' => (int) $c['id'], 'line_no' => $i,
                        'description' => mb_substr(self::describe($c), 0, 255),
                        'unit_type' => ($c['unit_type'] !== null && (string) $c['unit_type'] !== '')
                                       ? (string) $c['unit_type'] : null,
                        'qty_required' => round((float) $c['qty'], 2),
                        'qty_awarded' => 0,
                    ));
                    $n++;
                }
            }, 'فتح RFQ للعقد ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }

        self::fact($conn, $companyId, 'supplier.rfq.opened', (int) $rfqId,
            'rfq_open:' . (int) $rfqId, array('contract_id' => $contractId, 'lines' => $n), $actor);
        self::audit($conn, $companyId, $actor, 'open', (int) $rfqId, array(),
            array('contract_id' => $contractId, 'lines' => $n));

        $out['ok'] = true; $out['code'] = 200; $out['rfq_id'] = (int) $rfqId; $out['lines'] = $n;
        $out['reason'] = 'فُتح ' . $no . ' بـ' . $n . ' بندًا **مشتقًّا من الالتزامات**';
        return $out;
    }

    /** الإرسالُ للمؤهلين — وبه تُفتح نافذةُ العروض. */
    public static function send($conn, $gate, $companyId, $rfqId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $r = self::rfqOf($gate, (int) $rfqId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'الطلبُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $r['state'] !== 'draft') {
            $out['code'] = 409; $out['reason'] = 'الإرسالُ للمسودة (حالُه: ' . $r['state'] . ')'; return $out;
        }
        try {
            $gate->update('supplier_rfqs',
                array('state' => 'sent', 'sent_at' => date('Y-m-d H:i:s')), array('id' => (int) $rfqId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإرسال: ' . $t->getMessage(); return $out;
        }
        self::fact($conn, $companyId, 'supplier.rfq.sent', (int) $rfqId,
            'rfq_sent:' . (int) $rfqId, array('due_date' => (string) $r['due_date']), $actor);
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** الإقفالُ — وبعده لا عرضَ يُقبل. */
    public static function close($conn, $gate, $companyId, $rfqId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $r = self::rfqOf($gate, (int) $rfqId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'الطلبُ غيرُ موجود'; return $out; }
        if ((string) $r['state'] !== 'sent') {
            $out['code'] = 409; $out['reason'] = 'الإقفالُ للمرسَل (حالُه: ' . $r['state'] . ')'; return $out;
        }
        try {
            $gate->update('supplier_rfqs',
                array('state' => 'closed', 'closed_at' => date('Y-m-d H:i:s')), array('id' => (int) $rfqId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإقفال'; return $out;
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② العروض
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تقديمُ عرضٍ — «عرضٌ بعد الإقفال → 423».
     * @return array{ok:bool,code:int,reason:string,quote_id:?int}
     */
    public static function submitQuote($conn, $gate, $companyId, $lineId, $supplierId, array $q, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'quote_id' => null);
        $l = self::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'البندُ غيرُ موجودٍ في نطاقك'; return $out; }
        $r = self::rfqOf($gate, (int) $l['rfq_id']);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'الطلبُ غيرُ موجود'; return $out; }

        // ② الإقفالُ يحكم — حالةً وتاريخًا معًا
        if (!in_array((string) $r['state'], self::OPEN_STATES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'الطلبُ «' . $r['state'] . '» — **لا عرضَ إلا على مرسَلٍ مفتوح** (§8.2)';
            return $out;
        }
        if (date('Y-m-d') > (string) $r['due_date']) {
            $out['code'] = 423;
            $out['reason'] = '**عرضٌ بعد الإقفال** — انقضى موعدُ ' . (string) $r['due_date'];
            return $out;
        }

        $supplierId = (int) $supplierId;
        if ($supplierId <= 0) { $out['code'] = 422; $out['reason'] = 'المورد إلزامي'; return $out; }
        $price = round((float) (isset($q['unit_price']) ? $q['unit_price'] : 0), 4);
        $qty   = round((float) (isset($q['qty_offered']) ? $q['qty_offered'] : 0), 2);
        if ($price <= 0 || $qty <= 0) {
            $out['code'] = 422; $out['reason'] = 'السعرُ والكميةُ موجبان'; return $out;
        }
        if ($qty > round((float) $l['qty_required'], 2)) {
            $out['code'] = 422;
            $out['reason'] = 'الكميةُ المعروضةُ تجاوز المطلوبَ ' . $l['qty_required'];
            return $out;
        }

        // «السجل» من مصدره (M-17) لا من رأي — وغيابُه NULL معلَنٌ لا صفرٌ مخترَع
        $rating = self::supplierRating($gate, $supplierId);

        $data = array(
            'rfq_id' => (int) $l['rfq_id'], 'line_id' => (int) $lineId, 'supplier_id' => $supplierId,
            'unit_price' => $price, 'qty_offered' => $qty,
            'currency' => isset($q['currency']) && $q['currency'] !== '' ? (string) $q['currency'] : 'SDG',
            'readiness_days' => (isset($q['readiness_days']) && $q['readiness_days'] !== '')
                                ? (int) $q['readiness_days'] : null,
            'record_rating' => $rating,
            'note' => isset($q['note']) ? mb_substr((string) $q['note'], 0, 255) : null,
            'submitted_by' => (int) $actor ?: null,
        );
        $ex = null;
        try {
            $ex = $gate->selectOne('rfq_quotes', array(
                'whereRaw' => 'line_id = ? AND supplier_id = ?', 'params' => array((int) $lineId, $supplierId)));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $ex'); $ex = null; }

        try {
            if ($ex) {
                // «التعديلُ استبدالٌ لا تكديس» — عرضٌ واحدٌ لكل (بند × مورد)
                unset($data['rfq_id'], $data['line_id'], $data['supplier_id']);
                $data['submitted_at'] = date('Y-m-d H:i:s');
                $gate->update('rfq_quotes', $data, array('id' => (int) $ex['id']));
                $out['quote_id'] = (int) $ex['id'];
            } else {
                $out['quote_id'] = (int) $gate->insert('rfq_quotes', $data);
            }
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::fact($conn, $companyId, 'supplier.rfq_quote.submitted', (int) $out['quote_id'],
            'rfq_quote:' . (int) $lineId . ':' . $supplierId,
            array('line_id' => (int) $lineId, 'supplier_id' => $supplierId,
                  'unit_price' => $price, 'qty' => $qty), $actor);

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * ③ **العزلُ متبادل**: موردٌ يقرأ عرضَ غيره ⇒ **403 تُسجَّل**.
     * @return array{ok:bool,code:int,reason:string,rows:array}
     */
    public static function quotesForSupplier($gate, $rfqId, $askingSupplierId, $ofSupplierId = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rows' => array());
        $asking = (int) $askingSupplierId;
        $of = (int) $ofSupplierId > 0 ? (int) $ofSupplierId : $asking;
        if ($asking <= 0) { $out['code'] = 422; $out['reason'] = 'هويةُ المورد إلزامية'; return $out; }
        if ($of !== $asking) {
            if (function_exists('log_security_event')) {
                log_security_event('rfq_cross_supplier_read',
                    'REFUSED | asking=' . $asking . ' of=' . $of . ' rfq=' . (int) $rfqId);
            }
            $out['code'] = 403;
            $out['reason'] = '**لا يقرأ موردٌ عرضَ غيره** (§8.2) — والمحاولةُ مسجَّلة';
            return $out;
        }
        try {
            $out['rows'] = $gate->scopedQuery(array('scope' => array('q' => 'rfq_quotes')),
                "SELECT q.* FROM rfq_quotes q
                  WHERE {TENANT_SCOPE} AND q.rfq_id = ? AND q.supplier_id = ?
                  ORDER BY q.line_id", array((int) $rfqId, $asking));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'فشلٌ يُعامَل بقيمةٍ افتراضية — $out[\'rows\'] = array()'); $out['rows'] = array(); }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** جدولُ المقارنة — **لمدير الموردين** لا للمورد: العروضُ بالمعايير الثلاثة. */
    public static function comparison($gate, $lineId)
    {
        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('q' => 'rfq_quotes'), 'enrich' => array('s' => 'suppliers')),
                "SELECT q.*, s.name AS supplier_name FROM rfq_quotes q
                   LEFT JOIN suppliers s ON s.id = q.supplier_id
                  WHERE {TENANT_SCOPE} AND q.line_id = ?
                  ORDER BY q.unit_price ASC, q.readiness_days ASC, q.record_rating DESC",
                array((int) $lineId));
        } catch (\Throwable $t) { return array(); }
        // الوسمُ يُعلَن ولا يقرّر: الأرخصُ والأسرعُ والأعلى سجلًّا قد لا يكونون واحدًا
        $best = array('price' => null, 'ready' => null, 'record' => null);
        foreach ($rows as $r) {
            if ($best['price'] === null || (float) $r['unit_price'] < (float) $best['price']) { $best['price'] = $r['unit_price']; }
            if ($r['readiness_days'] !== null && ($best['ready'] === null || (int) $r['readiness_days'] < (int) $best['ready'])) { $best['ready'] = $r['readiness_days']; }
            if ($r['record_rating'] !== null && ($best['record'] === null || (float) $r['record_rating'] > (float) $best['record'])) { $best['record'] = $r['record_rating']; }
        }
        foreach ($rows as $i => $r) {
            $rows[$i]['is_cheapest'] = ($best['price'] !== null && abs((float) $r['unit_price'] - (float) $best['price']) < 0.00005);
            $rows[$i]['is_fastest']  = ($r['readiness_days'] !== null && $best['ready'] !== null && (int) $r['readiness_days'] === (int) $best['ready']);
            $rows[$i]['is_best_record'] = ($r['record_rating'] !== null && $best['record'] !== null && abs((float) $r['record_rating'] - (float) $best['record']) < 0.005);
        }
        return $rows;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الترسية — جزئيةٌ ولا تجاوز الالتزام
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @param array $awards كلٌّ: {line_id, supplier_id, qty, reason?}
     * @return array{ok:bool,code:int,reason:string,awarded:int,total:float}
     */
    public static function award($conn, $gate, $companyId, $rfqId, array $awards, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'awarded' => 0, 'total' => 0.0);
        $r = self::rfqOf($gate, (int) $rfqId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'الطلبُ غيرُ موجود'; return $out; }
        if (!in_array((string) $r['state'], array('closed', 'awarded'), true)) {
            $out['code'] = 423;
            $out['reason'] = '**الترسيةُ بعد الإقفال** — الطلبُ «' . $r['state'] . '»';
            return $out;
        }
        if (!$awards) { $out['code'] = 422; $out['reason'] = 'لا ترسيةَ فارغة'; return $out; }

        // ④ فحصُ الإتاحة **قبل** أي كتابة — و409 **بقيمة المتاح**
        $need = array();
        foreach ($awards as $a) {
            $lid = (int) (isset($a['line_id']) ? $a['line_id'] : 0);
            $qty = round((float) (isset($a['qty']) ? $a['qty'] : 0), 2);
            if ($lid <= 0 || $qty <= 0) { $out['code'] = 422; $out['reason'] = 'ترسيةٌ ناقصةُ البند أو الكمية'; return $out; }
            if (!isset($need[$lid])) { $need[$lid] = 0.0; }
            $need[$lid] = round($need[$lid] + $qty, 2);
        }
        foreach ($need as $lid => $q) {
            $l = self::lineOf($gate, $lid);
            if (!$l || (int) $l['rfq_id'] !== (int) $rfqId) {
                $out['code'] = 422; $out['reason'] = 'بندٌ خارج هذا الطلب: ' . $lid; return $out;
            }
            $avail = round((float) $l['qty_required'] - (float) $l['qty_awarded'], 2);
            if ($q > $avail + 0.0001) {
                $out['code'] = 409;
                $out['reason'] = '**تخصيصٌ يجاوز الالتزام** في البند ' . $lid
                               . ' — المطلوبُ ' . $q . ' **والمتاحُ ' . $avail . '**';
                return $out;
            }
        }

        $n = 0; $total = 0.0;
        try {
            $gate->runInTransaction(function ($g) use (&$n, &$total, $awards, $rfqId, $actor) {
                foreach ($awards as $a) {
                    $lid = (int) $a['line_id'];
                    $sup = (int) (isset($a['supplier_id']) ? $a['supplier_id'] : 0);
                    $qty = round((float) $a['qty'], 2);
                    $quote = null;
                    try {
                        $quote = $g->selectOne('rfq_quotes', array(
                            'whereRaw' => 'line_id = ? AND supplier_id = ?',
                            'params' => array($lid, $sup)));
                    } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $quote'); $quote = null; }
                    if (!$quote) {
                        throw new \RuntimeException('لا عرضَ لهذا المورد في البند ' . $lid
                            . ' — **ولا تُرسى كميةٌ بلا عرضٍ مقدَّم**');
                    }
                    $g->insert('rfq_awards', array(
                        'rfq_id' => (int) $rfqId, 'line_id' => $lid, 'supplier_id' => $sup,
                        'quote_id' => (int) $quote['id'],
                        'qty_awarded' => $qty,
                        'unit_price' => round((float) $quote['unit_price'], 4),
                        'currency' => (string) $quote['currency'],
                        'reason' => isset($a['reason']) ? mb_substr((string) $a['reason'], 0, 255) : null,
                        'awarded_by' => (int) $actor ?: null,
                    ));
                    // عدّادُ البند — و`CHECK` يحرس السقفَ ذرّيًّا
                    $line = $g->selectOne('rfq_lines', array('where' => array('id' => $lid)));
                    $g->update('rfq_lines',
                        array('qty_awarded' => round((float) $line['qty_awarded'] + $qty, 2)),
                        array('id' => $lid));
                    $n++;
                    $total = round($total + ($qty * (float) $quote['unit_price']), 2);
                }
                $g->update('supplier_rfqs', array(
                    'state' => 'awarded', 'awarded_at' => date('Y-m-d H:i:s'),
                    'awarded_by' => (int) $actor ?: null), array('id' => (int) $rfqId));
            }, 'ترسية RFQ ' . $rfqId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت الترسية: ' . $t->getMessage(); return $out;
        }

        // ⑤ **حقيقةٌ محايدة لا حدثٌ مالي** — «FES يبدأ من الوحدات»
        self::fact($conn, $companyId, 'supplier.rfq.awarded', (int) $rfqId,
            'rfq_awarded:' . (int) $rfqId . ':' . $n,
            array('awards' => $n, 'value' => $total), $actor);
        self::audit($conn, $companyId, $actor, 'award', (int) $rfqId, array(),
            array('awards' => $n, 'value' => $total));

        $out['ok'] = true; $out['code'] = 200; $out['awarded'] = $n; $out['total'] = $total;
        $out['reason'] = 'رُسي ' . $n . ' بندًا-موردًا بقيمةٍ تقديريةٍ ' . $total;
        return $out;
    }

    /** الانتقالُ إلى التعاقد — «Awarded → Contracted». */
    public static function markContracted($conn, $gate, $companyId, $rfqId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $r = self::rfqOf($gate, (int) $rfqId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'الطلبُ غيرُ موجود'; return $out; }
        if ((string) $r['state'] !== 'awarded') {
            $out['code'] = 409; $out['reason'] = 'التعاقدُ بعد الترسية (حالُه: ' . $r['state'] . ')'; return $out;
        }
        try {
            $gate->update('supplier_rfqs', array('state' => 'contracted'), array('id' => (int) $rfqId));
        } catch (\Throwable $t) { $out['code'] = 422; $out['reason'] = 'تعذّر'; return $out; }
        self::fact($conn, $companyId, 'supplier.rfq.contracted', (int) $rfqId,
            'rfq_contracted:' . (int) $rfqId, array(), $actor);
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function rfqOf($gate, $id)
    {
        try { return $gate->selectOne('supplier_rfqs', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    public static function lineOf($gate, $id)
    {
        try { return $gate->selectOne('rfq_lines', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    public static function linesOf($gate, $rfqId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'rfq_lines')),
                "SELECT l.* FROM rfq_lines l WHERE {TENANT_SCOPE} AND l.rfq_id = ?
                  ORDER BY l.line_no", array((int) $rfqId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function awardsOf($gate, $rfqId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('a' => 'rfq_awards'), 'enrich' => array('s' => 'suppliers')),
                "SELECT a.*, s.name AS supplier_name FROM rfq_awards a
                   LEFT JOIN suppliers s ON s.id = a.supplier_id
                  WHERE {TENANT_SCOPE} AND a.rfq_id = ? ORDER BY a.line_id, a.id",
                array((int) $rfqId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function listAll($gate, $limit = 100)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('r' => 'supplier_rfqs')),
                "SELECT r.* FROM supplier_rfqs r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
                  ORDER BY r.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    /** التزاماتُ العقد التي نلتزم بها — مصدرُ البنود. */
    public static function commitmentsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
                "SELECT c.* FROM contract_commitments c
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                    AND c.party_scope = ? AND c.obliged_party = ? AND c.contract_ref = ?
                    AND c.qty > 0
                  ORDER BY c.id",
                array(self::COMMITMENT_SCOPE, self::COMMITMENT_OBLIGOR, (int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function describe(array $c)
    {
        $t = (string) $c['commitment_type'];
        $u = ($c['unit_type'] !== null && (string) $c['unit_type'] !== '') ? (string) $c['unit_type'] : 'وحدة';
        return 'التزام ' . (string) $c['commitment_code'] . ' — ' . $t . ': '
             . round((float) $c['qty'], 2) . ' ' . $u
             . (($c['note'] !== null && (string) $c['note'] !== '') ? (' · ' . (string) $c['note']) : '');
    }

    /** «السجل» من التقييم الدوري (M-17) — وغيابُه NULL معلَنٌ لا صفرٌ مخترَع. */
    private static function supplierRating($gate, $supplierId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('e' => 'supplier_evaluations')),
                "SELECT e.score FROM supplier_evaluations e
                  WHERE {TENANT_SCOPE} AND e.supplier_id = ? AND e.state = 'approved'
                  ORDER BY e.id DESC LIMIT 1", array((int) $supplierId));
            if ($r && $r[0]['score'] !== null) { return round((float) $r[0]['score'] / 20.0, 2); }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا تقييمَ مقروء'); /* لا تقييمَ مقروء */ }
        return null;
    }

    /** «ولا حدثَ ماليًّا قبل التنفيذ الفعلي» — حقيقةٌ محايدةٌ في الجذر فقط. */
    private static function fact($conn, $companyId, $key, $entityId, $idem, array $payload, $actor)
    {
        try {
            require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key' => $key, 'category' => 'commercial', 'source_module' => 'suppliers',
                'company_id' => (int) $companyId, 'entity_type' => 'supplier_rfq',
                'entity_id' => (int) $entityId, 'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => (int) $actor ?: 1, 'idempotency_key' => (string) $idem,
                'payload' => $payload,
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-21 fact'); error_log('H-21 fact ' . $key . ': ' . $t->getMessage()); }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', 'supplier_rfqs', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
