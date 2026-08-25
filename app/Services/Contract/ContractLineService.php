<?php
/**
 * app/Services/Contract/ContractLineService.php — بنودُ بيع عقد العميل (P-02)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §2 (عبر الملحق §3-`P-02`): «**بنودُ المبيعات** بنموذجها وسعرها
 * وسريانها وحالتها الضريبية — **وفصلُها عن خطة الموارد**».
 * §4 القبول: «**عقدُ طنٍّ بخطة معدات: قيمةُ العقد لم تتضاعف** — وهذا هو
 * برهانُ `P-02`».
 *
 * ── القاعدةُ التي تُبنى عليها كلُّ الدالة ────────────────────────────────────
 * `contract_commitments` يخلط محورين في ENUM واحد: **كمياتٍ تُفوتَر** و**طاقةً
 * لا تُفوتَر**. والخلطُ لم يضرّ حتى الآن **لأن أحدًا لم يحسب القيمة قط**؛
 * فالخطرُ يتحقق **لحظةَ بنائها**. ولذلك:
 *
 *   **هذا الجدولُ وحدَه يحمل المال.** والطاقةُ (عددُ المعدات · ساعاتُ الإتاحة ·
 *   دعمُ الطاقة) **لا تدخل القيمة أبدًا** — بيتُها خطةُ الموارد (`P-04`).
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **الطاقةُ لا تصير بندَ بيع** ⇒ **422 باسم النوع**.
 * ② **ولا سعرَ بلا مرجعٍ ضريبيّ** — الخاضعُ يلزمه رمزٌ مسجَّل (و`CHECK` حزامٌ).
 * ③ **والسريانُ لا يتداخل** ⇒ **409**؛ والتغييرُ **نسخةٌ تُخلِف** لا تعديلٌ رجعي.
 * ④ **وقيمةُ العقد تعريفٌ واحد** — بكل عملةٍ على حدة، **وتُعلن ما استبعدته**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

class ContractLineService
{
    /**
     * التزاماتُ **الطاقة** — لا تحمل مالًا ولا تصير بندَ بيع.
     * (مقيسٌ من `contract_commitments.commitment_type`.)
     */
    const CAPACITY_TYPES = array('equipment_count', 'daily_availability_hours', 'capacity_support');

    /** التزاماتُ **الكمية** — هي وحدَها التي تصلح مصدرًا لبند بيع. */
    const BILLABLE_TYPES = array('total_qty', 'period_qty', 'min_guaranteed', 'period_hours');

    const CAPACITY_LABEL_AR = array(
        'equipment_count' => 'عددُ المعدات',
        'daily_availability_hours' => 'ساعاتُ الإتاحة اليومية',
        'capacity_support' => 'دعمُ الطاقة',
    );

    const MODELS = array('hour', 'ton', 'trip', 'meter', 'cbm', 'day', 'shift', 'lump_sum', 'standby');

    const TAX_STATUSES = array('taxable', 'exempt', 'zero_rated', 'reverse_charge');

    // ═════════════════════════════════════════════════════════════════════
    // ① الإضافة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,line_id:?int}
     */
    public static function add($conn, $gate, $companyId, $contractId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => null);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقد غير موجود في نطاقك'; return $out; }

        // ① الطاقةُ لا تصير بندَ بيع — **الحارسُ الأول قبل أي شيء**
        $srcId = (isset($a['source_commitment_id']) && (int) $a['source_commitment_id'] > 0)
                 ? (int) $a['source_commitment_id'] : null;
        if ($srcId !== null) {
            $cm = null;
            try { $cm = $gate->selectOne('contract_commitments', array('where' => array('id' => $srcId))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $cm'); $cm = null; }
            if (!$cm) { $out['code'] = 422; $out['reason'] = 'الالتزام غير موجود في نطاقك'; return $out; }
            if ((int) $cm['contract_ref'] !== (int) $contractId) {
                $out['code'] = 422; $out['reason'] = 'الالتزام يخص عقدا آخر'; return $out;
            }
            $type = (string) $cm['commitment_type'];
            if (in_array($type, self::CAPACITY_TYPES, true)) {
                $out['code'] = 422;
                $out['reason'] = '**التزام طاقة لا يصير بند بيع**: «'
                    . (isset(self::CAPACITY_LABEL_AR[$type]) ? self::CAPACITY_LABEL_AR[$type] : $type)
                    . '» — بيتُه **خطةُ الموارد** (P-04) و**لا يدخل القيمة**. '
                    . 'وخلطهما **يضاعف الإيراد** في عقد واحد';
                return $out;
            }
            if (!in_array($type, self::BILLABLE_TYPES, true)) {
                $out['code'] = 422;
                $out['reason'] = 'نوع التزام لا يعرف له حكم في القيمة: ' . $type
                               . ' — **يعلن ولا يفترض**';
                return $out;
            }
        }

        $model = (string) (isset($a['pricing_model']) ? $a['pricing_model'] : '');
        if (!in_array($model, self::MODELS, true)) {
            $out['code'] = 422; $out['reason'] = 'نموذج تسعير غير معروف: ' . $model; return $out;
        }
        $qty = round((float) (isset($a['qty_contracted']) ? $a['qty_contracted'] : 0), 2);
        if ($model === 'lump_sum' && $qty <= 0) { $qty = 1.0; }
        $price = round((float) (isset($a['unit_price']) ? $a['unit_price'] : 0), 4);
        if ($qty <= 0 || $price <= 0) {
            $out['code'] = 422; $out['reason'] = 'الكمية والسعر موجبان'; return $out;
        }
        $from = self::dateOrNull(isset($a['valid_from']) ? $a['valid_from'] : null);
        if ($from === null) {
            $out['code'] = 422;
            $out['reason'] = '**تاريخ السريان إلزامي** — وبلا سريان لا يعرف أي فترة يحكمها البند';
            return $out;
        }
        $to = self::dateOrNull(isset($a['valid_to']) ? $a['valid_to'] : null);
        if ($to !== null && $to < $from) {
            $out['code'] = 422; $out['reason'] = 'نهاية السريان قبل بدايته'; return $out;
        }

        // ② ولا سعرَ بلا مرجعٍ ضريبيّ
        $tax = (string) (isset($a['tax_status']) ? $a['tax_status'] : 'taxable');
        if (!in_array($tax, self::TAX_STATUSES, true)) {
            $out['code'] = 422; $out['reason'] = 'حالة ضريبية غير معروفة: ' . $tax; return $out;
        }
        $taxCode = (isset($a['tax_code_id']) && (int) $a['tax_code_id'] > 0) ? (int) $a['tax_code_id'] : null;
        if ($tax === 'taxable') {
            if ($taxCode === null) {
                $out['code'] = 422;
                $out['reason'] = '**بند خاضع بلا رمز ضريبي** — «الضريبة سطر بمرجعها» (M-03 §5)';
                return $out;
            }
            $tc = null;
            try { $tc = $gate->selectOne('fin_tax_codes', array('where' => array('id' => $taxCode))); }
            catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $tc'); $tc = null; }
            if (!$tc) {
                $out['code'] = 422;
                $out['reason'] = 'الرمز الضريبي غير مسجل في نطاقك — ولا يخترع';
                return $out;
            }
        } else {
            $taxCode = null; // غيرُ الخاضع لا يحمل رمزًا — فلا يُدَّعى
        }

        // ③ والسريانُ لا يتداخل — بالمفتاح نفسِه (نموذجٌ + وصفٌ + عملة)
        $clash = self::overlapping($gate, (int) $contractId, $model, $from, $to, $srcId, 0);
        if ($clash !== null) {
            $out['code'] = 409; $out['line_id'] = (int) $clash['id'];
            $out['reason'] = 'بند قائم #' . (int) $clash['id'] . ' يتداخل سريانه ('
                . (string) $clash['valid_from'] . ' → ' . (string) ($clash['valid_to'] ?? '…')
                . ') — **والتغيير نسخة تخلف لا تداخل**';
            return $out;
        }

        $state = (string) (isset($a['state']) ? $a['state'] : 'active');
        if (!in_array($state, array('draft', 'active'), true)) { $state = 'active'; }

        $lineNo = self::nextLineNo($gate, (int) $contractId);
        $lid = null;
        try {
            $lid = (int) $gate->insert('client_contract_lines', array(
                'contract_id' => (int) $contractId, 'line_no' => $lineNo,
                'pricing_model' => $model,
                'description' => mb_substr(trim((string) (isset($a['description']) ? $a['description'] : '')) !== ''
                                 ? (string) $a['description'] : ('بند ' . $model), 0, 255),
                'qty_contracted' => $qty, 'unit_price' => $price,
                'currency' => (isset($a['currency']) && $a['currency'] !== '')
                              ? mb_substr((string) $a['currency'], 0, 8) : 'SDG',
                'valid_from' => $from, 'valid_to' => $to,
                'tax_status' => $tax, 'tax_code_id' => $taxCode,
                'source_commitment_id' => $srcId,
                // ⚠ الگوتشا وقعت هنا فعلًا: كان التعبيرُ يفحص القيمةَ الافتراضية
                // ثم **يقرأ `$a['state']` غيرَ الموجود** فيمرّ `''` — و**ENUM
                // يبتلعه صامتًا** (`sql_mode` فارغ)، فيُكتب بندٌ بحالةٍ فارغةٍ
                // **لا تراه قيمةُ العقد**. فالافتراضُ يُحسب **مرةً واحدةً** ثم يُفحص.
                'state' => $state,
                'note' => isset($a['note']) ? mb_substr((string) $a['note'], 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذرت الإضافة: ' . $t->getMessage(); return $out;
        }
        if ($lid <= 0) { $out['code'] = 422; $out['reason'] = 'تعذر الإدراج — افحص القيود'; return $out; }

        self::audit($conn, $companyId, $actor, 'add_line', $lid, array(),
            array('contract_id' => (int) $contractId, 'model' => $model, 'qty' => $qty, 'price' => $price));
        $out['ok'] = true; $out['code'] = 200; $out['line_id'] = $lid;
        return $out;
    }

    /**
     * تغييرُ السعر **بنسخةٍ تُخلِف** — «ملحقٌ يغيّر السعرَ في منتصف المدة ⇒
     * نسختان **والمقارنةُ التاريخية لم تتغير**» (§4).
     * @return array{ok:bool,code:int,reason:string,new_line_id:?int}
     */
    public static function reprice($conn, $gate, $companyId, $lineId, $newPrice, $effectiveFrom, $actor, $note = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'new_line_id' => null);
        $l = self::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'البند غير موجود في نطاقك'; return $out; }
        if ((string) $l['state'] !== 'active') {
            $out['code'] = 409; $out['reason'] = 'إعادة التسعير للنافذ (حاله: ' . $l['state'] . ')'; return $out;
        }
        $from = self::dateOrNull($effectiveFrom);
        if ($from === null) { $out['code'] = 422; $out['reason'] = 'تاريخ سريان السعر الجديد إلزامي'; return $out; }
        if ($from <= (string) $l['valid_from']) {
            $out['code'] = 422;
            $out['reason'] = 'سريان النسخة الجديدة **بعد** سريان القائمة ('
                           . (string) $l['valid_from'] . ') — **ولا تعديل رجعيا**';
            return $out;
        }
        $price = round((float) $newPrice, 4);
        if ($price <= 0) { $out['code'] = 422; $out['reason'] = 'السعر موجب'; return $out; }

        $cut = date('Y-m-d', strtotime($from . ' -1 day'));
        $newId = null;
        try {
            $gate->runInTransaction(function ($g) use (&$newId, $l, $lineId, $price, $from, $cut, $actor, $note) {
                // القديمةُ تُغلق عند **سريان الجديدة − يوم** — فأثرُ ما قبله بحكمها
                $g->update('client_contract_lines',
                    array('valid_to' => $cut, 'state' => 'superseded'), array('id' => (int) $lineId));
                $newId = (int) $g->insert('client_contract_lines', array(
                    'contract_id' => (int) $l['contract_id'],
                    'line_no' => self::nextLineNoRaw($g, (int) $l['contract_id']),
                    'pricing_model' => (string) $l['pricing_model'],
                    'description' => (string) $l['description'],
                    'qty_contracted' => (float) $l['qty_contracted'],
                    'unit_price' => $price, 'currency' => (string) $l['currency'],
                    'valid_from' => $from, 'valid_to' => $l['valid_to'],
                    'tax_status' => (string) $l['tax_status'],
                    'tax_code_id' => $l['tax_code_id'] !== null ? (int) $l['tax_code_id'] : null,
                    'source_commitment_id' => $l['source_commitment_id'] !== null
                                              ? (int) $l['source_commitment_id'] : null,
                    'supersedes_line_id' => (int) $lineId,
                    'state' => 'active',
                    'note' => mb_substr(trim((string) $note) !== '' ? (string) $note
                              : ('إعادة تسعير من ' . $l['unit_price'] . ' إلى ' . $price), 0, 255),
                    'created_by' => (int) $actor ?: null,
                ));
                if ($newId <= 0) { throw new \RuntimeException('تعذر إنشاء النسخة'); }
            }, 'إعادة تسعير بند ' . $lineId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذرت إعادة التسعير: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'reprice', (int) $lineId,
            array('unit_price' => (float) $l['unit_price']),
            array('unit_price' => $price, 'new_line_id' => $newId, 'from' => $from));
        $out['ok'] = true; $out['code'] = 200; $out['new_line_id'] = $newId;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قيمةُ العقد — **التعريفُ الواحد**
    // ═════════════════════════════════════════════════════════════════════

    /**
     * «قيمةُ العقد» — ولا تُحسب في موضعٍ آخر أبدًا.
     *
     * @param string|null $onDate بتاريخٍ بعينه (NULL = كلُّ البنود النافذة أيًّا كان تاريخُها)
     * @return array{ok:bool,by_currency:array,lines:array,excluded:array,note:string}
     */
    public static function contractValue($gate, $contractId, $onDate = null)
    {
        $out = array('ok' => true, 'by_currency' => array(), 'lines' => array(),
                     'excluded' => array(), 'note' => '');
        $day = ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $onDate))
               ? (string) $onDate : null;

        // **الحالةُ تختلف بحسب السؤال** — والفرقُ جوهريٌّ لا تفصيل:
        //  · بلا تاريخ = «قيمةُ العقد **اليوم**» ⇒ النافذُ وحدَه.
        //  · بتاريخ    = «قيمتُه **يومئذٍ**» ⇒ النافذُ **والمستبدَلُ داخل نافذته**،
        //    لأن «المقارنةَ التاريخية لم تتغير» (§4): البندُ الذي حكم آذارَ هو
        //    الذي يجب أن يُقرأ لآذار ولو أُخلف في تموز. وإسقاطُ المستبدَل يمحو
        //    التاريخَ — وهو عينُ ما مُنع في E-24 لسياسة الأجر.
        $rows = array();
        try {
            if ($day !== null) {
                $sql = "SELECT l.* FROM client_contract_lines l
                         WHERE {TENANT_SCOPE} AND l.contract_id = ? AND COALESCE(l.is_deleted,0)=0
                           AND l.state IN ('active','superseded','ended')
                           AND l.valid_from <= ? AND (l.valid_to IS NULL OR l.valid_to >= ?)
                         ORDER BY l.line_no";
                $p = array((int) $contractId, $day, $day);
            } else {
                $sql = "SELECT l.* FROM client_contract_lines l
                         WHERE {TENANT_SCOPE} AND l.contract_id = ? AND COALESCE(l.is_deleted,0)=0
                           AND l.state = 'active'
                         ORDER BY l.line_no";
                $p = array((int) $contractId);
            }
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')), $sql, $p);
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $rows'); $rows = array(); }

        foreach ($rows as $r) {
            $cur = (string) $r['currency'];
            $amt = round((float) $r['qty_contracted'] * (float) $r['unit_price'], 2);
            if (!isset($out['by_currency'][$cur])) { $out['by_currency'][$cur] = 0.0; }
            $out['by_currency'][$cur] = round($out['by_currency'][$cur] + $amt, 2);
            $out['lines'][] = array(
                'line_id' => (int) $r['id'], 'line_no' => (int) $r['line_no'],
                'model' => (string) $r['pricing_model'], 'qty' => (float) $r['qty_contracted'],
                'price' => (float) $r['unit_price'], 'amount' => $amt, 'currency' => $cur,
                'tax_status' => (string) $r['tax_status'],
            );
        }

        // **وتُعلن ما استبعدته**: التزاماتُ الطاقة لا تدخل القيمة — ولا تُخفى
        $caps = "'" . implode("','", self::CAPACITY_TYPES) . "'";
        try {
            $ex = $gate->scopedQuery(array('scope' => array('m' => 'contract_commitments')),
                "SELECT m.id, m.commitment_code, m.commitment_type, m.qty, m.unit_type
                   FROM contract_commitments m
                  WHERE {TENANT_SCOPE} AND COALESCE(m.is_deleted,0)=0
                    AND m.contract_ref = ? AND m.commitment_type IN ({$caps})
                  ORDER BY m.id", array((int) $contractId));
            foreach ($ex as $e) {
                $t = (string) $e['commitment_type'];
                $out['excluded'][] = array(
                    'commitment_id' => (int) $e['id'], 'code' => (string) $e['commitment_code'],
                    'type' => $t,
                    'label' => isset(self::CAPACITY_LABEL_AR[$t]) ? self::CAPACITY_LABEL_AR[$t] : $t,
                    'qty' => (float) $e['qty'],
                    'why' => '**طاقة لا تفوتر** — بيتها خطة الموارد (P-04) ولا تدخل القيمة',
                );
            }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'الاستبعاد يعلن حين يقرأ'); /* الاستبعادُ يُعلَن حين يُقرأ */ }

        $parts = array();
        foreach ($out['by_currency'] as $cur => $v) { $parts[] = $v . ' ' . $cur; }
        $out['note'] = ($parts ? implode(' · ', $parts) : 'صفر')
            . ' من ' . count($out['lines']) . ' بند بيع'
            . (count($out['excluded']) > 0
               ? (' · **واستبعد ' . count($out['excluded']) . ' التزام طاقة معلنا**') : '')
            . (count($out['by_currency']) > 1 ? ' · **تعدد عملات: لا يجمع في رقم**' : '');
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function linesOf($gate, $contractId, $includeHistory = true)
    {
        try {
            $w = $includeHistory ? '' : " AND l.state = 'active'";
            return $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
                "SELECT l.* FROM client_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_id = ? AND COALESCE(l.is_deleted,0)=0" . $w . "
                  ORDER BY l.line_no", array((int) $contractId));
        } catch (\Throwable $t) { error_log('P-02 linesOf: ' . $t->getMessage()); return array(); }
    }

    /** التزاماتُ العقد مفروزةً: ما يصلح بندَ بيعٍ وما لا يصلح — **بسببه**. */
    public static function commitmentsSplit($gate, $contractId)
    {
        $out = array('billable' => array(), 'capacity' => array(), 'unknown' => array());
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('m' => 'contract_commitments')),
                "SELECT m.* FROM contract_commitments m
                  WHERE {TENANT_SCOPE} AND COALESCE(m.is_deleted,0)=0 AND m.contract_ref = ?
                  ORDER BY m.id", array((int) $contractId));
        } catch (\Throwable $t) { return $out; }
        foreach ($rows as $r) {
            $t = (string) $r['commitment_type'];
            if (in_array($t, self::CAPACITY_TYPES, true)) { $out['capacity'][] = $r; }
            elseif (in_array($t, self::BILLABLE_TYPES, true)) { $out['billable'][] = $r; }
            else { $out['unknown'][] = $r; }
        }
        return $out;
    }

    public static function lineOf($gate, $id)
    {
        try { return $gate->selectOne('client_contract_lines', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    public static function taxCodes($gate)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('t' => 'fin_tax_codes')),
                "SELECT t.id, t.code, t.name, t.rate FROM fin_tax_codes t
                  WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0)=0 AND t.active = 1
                  ORDER BY t.code");
        } catch (\Throwable $t) { return array(); }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function overlapping($gate, $contractId, $model, $from, $to, $srcId, $exceptId)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
                "SELECT l.id, l.valid_from, l.valid_to FROM client_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_id = ? AND COALESCE(l.is_deleted,0)=0
                    AND l.state IN ('draft','active') AND l.id <> ?
                    AND l.pricing_model = ?
                    AND l.source_commitment_id <=> ?
                    AND (l.valid_to IS NULL OR l.valid_to >= ?)
                  ORDER BY l.valid_from",
                array((int) $contractId, (int) $exceptId, (string) $model, $srcId, (string) $from));
            foreach ($rows as $r) {
                if ($to !== null && (string) $r['valid_from'] > $to) { continue; }
                return $r;
            }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'P-02 overlapping'); error_log('P-02 overlapping: ' . $t->getMessage()); }
        return null;
    }

    private static function nextLineNo($gate, $contractId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
                "SELECT COALESCE(MAX(l.line_no),0) AS m FROM client_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_id = ?", array((int) $contractId));
            return ($r ? (int) $r[0]['m'] : 0) + 1;
        } catch (\Throwable $t) { return 1; }
    }

    private static function nextLineNoRaw($g, $contractId)
    {
        try {
            $r = $g->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
                "SELECT COALESCE(MAX(l.line_no),0) AS m FROM client_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_id = ?", array((int) $contractId));
            return ($r ? (int) $r[0]['m'] : 0) + 1;
        } catch (\Throwable $t) { return 1; }
    }

    private static function contractOf($gate, $contractId)
    {
        try { return $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'client_contract_lines', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
