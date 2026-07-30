<?php
/**
 * app/Services/Settlement/SupplierRuleService.php — قواعدُ التحميل والجزاء (M-15)
 * ═══════════════════════════════════════════════════════════════════════════
 * CON-03 §2-⑥: «ما يُحمَّل عليه من مصروفاتنا: وقودٌ · قطعُ غيارٍ · صيانةٌ ·
 * ترحيلٌ · رواتبُ مشغّليه · سلفٌ — **بأسعارٍ وقواعدَ مكتوبة**».
 * CON-03 §2-⑦: «جزاءاتُ العجز والجاهزية والتأخر … **سقوفُها**».
 * CON-03 §4: «وله أن **يشدّد جزاءَه لا أن يعكس إسنادًا**».
 * CON-03 §6-Validation: «**قاعدةُ تحميلٍ بلا سعرٍ مكتوب → 422**».
 * ENT-02 §3: «تُحتسب بقواعدها وتظهر بندًا باسمه — **ولا تتجاوز سقفَها التعاقدي**».
 *
 * ── ثلاثُ قواعدَ تحكم كلَّ رقمٍ هنا ─────────────────────────────────────────
 * ① **لا تسعيرَ بلا قاعدةٍ مكتوبة — ولا حجبَ بسببها**: تحميلٌ بلا قاعدةٍ يُمرَّر
 *    **بتكلفته الخام موسومًا `no_rule`** لا مضروبًا في نسبةٍ مخترَعة. (ENT-02
 *    §7: «تحميلٌ بلا مستند → **يُرفض البند لا التسوية**» — فالإعلانُ لا الحجب.)
 * ② **السقفُ يقصّ ويُعلن قصَّه** — «ولا تتجاوز سقفَها التعاقدي» حرفيًّا.
 * ③ **نقضُ الإسناد الموروث يلزمه سببٌ مكتوب** — «يشدّد لا يعكس» (§4)، والقيدُ
 *    البنيويُّ يحرسه فوق حارس الخدمة.
 */

namespace App\Services\Settlement;

class SupplierRuleService
{
    /** التحميلاتُ الستُّ في §2-⑥ نصًّا. */
    const CHARGE_TYPES = array('fuel', 'spares', 'maintenance', 'transport', 'operator_payroll', 'advance');
    const CHARGE_LABELS = array(
        'fuel' => 'وقود', 'spares' => 'قطع غيار', 'maintenance' => 'صيانة',
        'transport' => 'ترحيل', 'operator_payroll' => 'رواتب مشغّليه', 'advance' => 'سلف',
    );
    const PRICING = array('cost', 'cost_plus', 'fixed');
    const PRICING_LABELS = array('cost' => 'بسعر التكلفة', 'cost_plus' => 'تكلفةٌ مضافةٌ بنسبتها',
                                 'fixed' => 'مبلغٌ ثابت');

    /** جزاءاتُ §6 الأربعة نصًّا. */
    const PENALTY_KINDS = array('shortfall', 'readiness', 'coverage', 'delay');
    const PENALTY_LABELS = array('shortfall' => 'عجز', 'readiness' => 'جاهزية',
                                 'coverage' => 'تغطية', 'delay' => 'تأخر');

    /** خريطةُ نوع التحميل في التسوية ⇒ نوعِه في القاعدة. */
    const SETTLEMENT_TO_RULE = array(
        'fuel' => 'fuel', 'parts' => 'spares', 'maintenance' => 'maintenance',
        'transport' => 'transport', 'advance' => 'advance',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① كتابةُ القواعد
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,rule_id:?int} */
    public static function saveChargeRule($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rule_id' => null);
        $contractId = (int) $contractId;

        $head = self::contractOf($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }

        $type = isset($args['charge_type']) ? trim((string) $args['charge_type']) : '';
        if (!in_array($type, self::CHARGE_TYPES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'نوعُ تحميلٍ خارج الستة (§2-⑥): ' . implode(' · ', self::CHARGE_TYPES);
            return $out;
        }
        $pricing = isset($args['pricing']) ? trim((string) $args['pricing']) : 'cost';
        if (!in_array($pricing, self::PRICING, true)) {
            $out['code'] = 422; $out['reason'] = 'طريقةُ تسعيرٍ خارج الثلاث'; return $out;
        }
        $rate = (isset($args['rate']) && trim((string) $args['rate']) !== '')
                ? round((float) $args['rate'], 3) : null;

        // ── «قاعدةُ تحميلٍ بلا سعرٍ مكتوب → 422» (§6-Validation) ───────────
        if ($pricing !== 'cost' && ($rate === null || $rate <= 0)) {
            $out['code'] = 422;
            $out['reason'] = 'طريقةُ «' . self::PRICING_LABELS[$pricing]
                           . '» **بلا سعرٍ مكتوب** — قاعدةٌ تُكتب ثم يُسأل عنها «بكم؟» مرفوضة (§6)';
            return $out;
        }
        if ($pricing === 'cost') { $rate = null; }
        if ($pricing === 'cost_plus' && $rate !== null && $rate > 100) {
            $out['code'] = 422; $out['reason'] = 'نسبةُ الإضافة تتجاوز 100٪ — راجعها'; return $out;
        }

        $cap = (isset($args['cap']) && trim((string) $args['cap']) !== '')
               ? round((float) $args['cap'], 2) : null;
        if ($cap !== null && $cap <= 0) {
            $out['code'] = 422; $out['reason'] = 'السقفُ موجبٌ أو غيرُ مكتوب'; return $out;
        }
        $from = isset($args['valid_from']) ? trim((string) $args['valid_from']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $out['code'] = 422; $out['reason'] = 'سريانُ القاعدة إلزامي'; return $out;
        }

        try {
            $out['rule_id'] = (int) $gate->insert('supplier_charge_rules', array(
                'contract_id' => $contractId, 'charge_type' => $type, 'pricing' => $pricing,
                'rate' => $rate, 'cap' => $cap,
                'currency' => isset($args['currency']) && trim((string) $args['currency']) !== ''
                              ? strtoupper(trim((string) $args['currency'])) : $head['currency'],
                'valid_from' => $from,
                'valid_to' => isset($args['valid_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_to'])
                              ? (string) $args['valid_to'] : null,
                'state' => 'active',
                'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                          ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للعقد قاعدةٌ بهذا النوع والسريان (UQ)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_charge_rules', 'create',
            (int) $out['rule_id'], array(), array('type' => $type, 'pricing' => $pricing, 'rate' => $rate));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** @return array{ok:bool,code:int,reason:string,rule_id:?int} */
    public static function savePenaltyRule($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rule_id' => null);
        $contractId = (int) $contractId;

        $head = self::contractOf($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }

        $kind = isset($args['kind']) ? trim((string) $args['kind']) : '';
        if (!in_array($kind, self::PENALTY_KINDS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'نوعُ جزاءٍ خارج الأربعة (§6): ' . implode(' · ', self::PENALTY_KINDS);
            return $out;
        }
        $rate = isset($args['rate']) ? round((float) $args['rate'], 3) : 0.0;
        if ($rate <= 0) { $out['code'] = 422; $out['reason'] = 'معدلُ الجزاء موجب'; return $out; }

        $basis = isset($args['rate_basis']) ? trim((string) $args['rate_basis']) : 'per_unit';
        if (!in_array($basis, array('per_unit', 'percent_of_base'), true)) {
            $out['code'] = 422; $out['reason'] = 'أساسُ المعدل: per_unit أو percent_of_base'; return $out;
        }
        $cap = (isset($args['cap_percent']) && trim((string) $args['cap_percent']) !== '')
               ? round((float) $args['cap_percent'], 2) : null;
        if ($cap !== null && ($cap <= 0 || $cap > 100)) {
            $out['code'] = 422; $out['reason'] = 'سقفُ الجزاء نسبةٌ في (0، 100]'; return $out;
        }

        // ── «يشدّد لا يعكس» (§4): نقضُ الإسناد يلزمه سببٌ مكتوب ────────────
        $inherits = isset($args['inherits_attribution']) ? (int) $args['inherits_attribution'] : 1;
        $override = isset($args['override_reason']) ? trim((string) $args['override_reason']) : '';
        if ($inherits !== 1 && $override === '') {
            $out['code'] = 422;
            $out['reason'] = 'نقضُ إسناد عقد العميل **يلزمه سببٌ مكتوب** — «له أن يشدّد جزاءَه '
                           . 'لا أن يعكس إسنادًا» (CON-03 §4)';
            return $out;
        }
        $from = isset($args['valid_from']) ? trim((string) $args['valid_from']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $out['code'] = 422; $out['reason'] = 'سريانُ القاعدة إلزامي'; return $out;
        }

        try {
            $out['rule_id'] = (int) $gate->insert('supplier_penalty_rules', array(
                'contract_id' => $contractId, 'kind' => $kind,
                'threshold' => (isset($args['threshold']) && trim((string) $args['threshold']) !== '')
                               ? round((float) $args['threshold'], 3) : null,
                'rate' => $rate, 'rate_basis' => $basis, 'cap_percent' => $cap,
                'periodicity' => (isset($args['periodicity'])
                    && in_array($args['periodicity'], array('daily', 'monthly', 'contract'), true))
                    ? (string) $args['periodicity'] : 'monthly',
                'inherits_attribution' => $inherits === 1 ? 1 : 0,
                'override_reason' => $inherits === 1 ? null : mb_substr($override, 0, 255),
                'currency' => $head['currency'],
                'formula_note' => isset($args['formula_note']) && trim((string) $args['formula_note']) !== ''
                                  ? mb_substr(trim((string) $args['formula_note']), 0, 255) : null,
                'valid_from' => $from,
                'valid_to' => isset($args['valid_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_to'])
                              ? (string) $args['valid_to'] : null,
                'state' => 'active',
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للعقد قاعدةٌ بهذا النوع والسريان (UQ)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_penalty_rules', 'create',
            (int) $out['rule_id'], array(), array('kind' => $kind, 'rate' => $rate, 'cap' => $cap));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② التطبيق
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تسعيرُ تحميلٍ بقاعدته المكتوبة.
     *
     * **وبلا قاعدةٍ: التكلفةُ الخام موسومةً `no_rule`** — لا نسبةٌ مخترَعة ولا
     * حجبٌ للبند («يُرفض البند لا التسوية» — ENT-02 §7).
     *
     * @return array{amount:float,basis:string,rule_id:?int,capped:bool,note:string}
     */
    public static function priceCharge($gate, $supplierId, $chargeType, $baseCost, $date)
    {
        $baseCost = round((float) $baseCost, 2);
        $type = isset(self::SETTLEMENT_TO_RULE[(string) $chargeType])
                ? self::SETTLEMENT_TO_RULE[(string) $chargeType] : (string) $chargeType;

        $rule = self::activeChargeRule($gate, $supplierId, $type, $date);
        if ($rule === null) {
            return array('amount' => $baseCost, 'basis' => 'no_rule', 'rule_id' => null,
                         'capped' => false,
                         'note' => '⚠ بلا قاعدةِ تسعيرٍ مكتوبةٍ لـ«'
                                   . (self::CHARGE_LABELS[$type] ?? $type)
                                   . '» — حُمّل بتكلفته الخام ويُعلَن (§2-⑥ يوجب قاعدةً مكتوبة)');
        }

        $pricing = (string) $rule['pricing'];
        $rate = $rule['rate'] !== null ? (float) $rule['rate'] : null;
        if ($pricing === 'cost') {
            $amount = $baseCost;
            $note = 'بسعر التكلفة بقاعدة #' . (int) $rule['id'];
        } elseif ($pricing === 'cost_plus') {
            $amount = round($baseCost * (1 + ($rate / 100.0)), 2);
            $note = 'تكلفةٌ + ' . $rate . '٪ بقاعدة #' . (int) $rule['id'];
        } else {   // fixed
            $amount = round($rate, 2);
            $note = 'مبلغٌ ثابتٌ ' . $rate . ' بقاعدة #' . (int) $rule['id'];
        }

        $capped = false;
        if ($rule['cap'] !== null && (float) $rule['cap'] > 0 && $amount > (float) $rule['cap']) {
            $amount = round((float) $rule['cap'], 2);
            $capped = true;
            $note .= ' — **مقصوصٌ بالسقف** ' . $rule['cap'];
        }
        return array('amount' => $amount, 'basis' => $pricing, 'rule_id' => (int) $rule['id'],
                     'capped' => $capped, 'note' => $note);
    }

    /**
     * احتسابُ جزاءٍ بقاعدته وسقفه.
     *
     * @param float $measured القياسُ الفعلي (نسبةُ جاهزيةٍ · وحداتُ عجزٍ · ساعاتُ تأخر)
     * @param float $baseAmount الأساسُ الذي يُقاس عليه السقف (مستحقُّ الفترة عادةً)
     * @return array{amount:float,rule_id:?int,capped:bool,triggered:bool,
     *               inherits_attribution:bool,note:string}
     */
    public static function assessPenalty($gate, $supplierId, $kind, $measured, $baseAmount, $date)
    {
        $out = array('amount' => 0.0, 'rule_id' => null, 'capped' => false, 'triggered' => false,
                     'inherits_attribution' => true, 'note' => '');
        $rule = self::activePenaltyRule($gate, $supplierId, (string) $kind, $date);
        if ($rule === null) {
            $out['note'] = 'لا قاعدةَ جزاءٍ مكتوبةً لـ«'
                         . (self::PENALTY_LABELS[(string) $kind] ?? $kind)
                         . '» — **لا يُجزى بلا قاعدة**';
            return $out;
        }
        $out['rule_id'] = (int) $rule['id'];
        $out['inherits_attribution'] = ((int) $rule['inherits_attribution'] === 1);

        $measured = (float) $measured;
        $threshold = $rule['threshold'] !== null ? (float) $rule['threshold'] : null;

        // كمُّ العجز: للجاهزية = ما دون الحد · ولغيرها = القياسُ فوق العتبة
        if ((string) $kind === 'readiness') {
            $gap = ($threshold !== null && $measured < $threshold) ? ($threshold - $measured) : 0.0;
        } else {
            $gap = ($threshold !== null) ? max(0.0, $measured - $threshold) : $measured;
        }
        if ($gap <= 0) {
            $out['note'] = 'لا عجزَ يُجزى عليه (القياس ' . $measured
                         . ($threshold !== null ? (' · الحد ' . $threshold) : '') . ')';
            return $out;
        }
        $out['triggered'] = true;

        $rate = (float) $rule['rate'];
        $amount = ((string) $rule['rate_basis'] === 'percent_of_base')
                  ? round((float) $baseAmount * $gap * $rate / 100.0, 2)
                  : round($gap * $rate, 2);
        $note = 'عجزٌ ' . round($gap, 3) . ' × ' . $rate
              . ((string) $rule['rate_basis'] === 'percent_of_base' ? '٪ من الأساس' : ' للوحدة')
              . ' بقاعدة #' . (int) $rule['id'];

        // ── «ولا تتجاوز سقفَها التعاقدي» (ENT-02 §3) ──────────────────────
        if ($rule['cap_percent'] !== null && (float) $baseAmount > 0) {
            $ceiling = round((float) $baseAmount * ((float) $rule['cap_percent']) / 100.0, 2);
            if ($amount > $ceiling) {
                $amount = $ceiling; $out['capped'] = true;
                $note .= ' — **مقصوصٌ بسقف ' . $rule['cap_percent'] . '٪ من الأساس** (' . $ceiling . ')';
            }
        } elseif ($rule['cap_percent'] === null) {
            $note .= ' · ⚠ بلا سقفٍ مكتوب — يُعلَن';
        }

        if (!$out['inherits_attribution']) {
            $note .= ' · **ينقض الإسنادَ الموروث** بسبب: ' . (string) $rule['override_reason'];
        }
        $out['amount'] = $amount; $out['note'] = $note;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ قراءات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * قاعدةُ تحميلٍ ساريةٌ لمورد بتاريخ.
     *
     * **سريانٌ مزدوج**: سريانُ القاعدة **ومدةُ عقدها** معًا — فقاعدةٌ مفتوحةُ
     * النهاية **لا تعيش بعد انتهاء عقدها**: لا عقدَ ⇒ لا تحميلَ بقاعدته.
     */
    public static function activeChargeRule($gate, $supplierId, $chargeType, $date)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('r' => 'supplier_charge_rules'),
                      'enrich' => array('h' => 'supplier_contracts')),
                "SELECT r.* FROM supplier_charge_rules r
                   LEFT JOIN supplier_contracts h ON h.id = r.contract_id
                  WHERE {TENANT_SCOPE} AND h.supplier_id = ? AND COALESCE(h.is_deleted,0)=0
                    AND (h.start_date IS NULL OR h.start_date <= ?)
                    AND (h.end_date IS NULL OR h.end_date >= ?)
                    AND r.charge_type = ? AND r.state = 'active' AND COALESCE(r.is_deleted,0)=0
                    AND r.valid_from <= ? AND (r.valid_to IS NULL OR r.valid_to >= ?)
                  ORDER BY r.valid_from DESC, r.id DESC LIMIT 1",
                array((int) $supplierId, (string) $date, (string) $date,
                      (string) $chargeType, (string) $date, (string) $date));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** قاعدةُ جزاءٍ ساريةٌ — بالسريان المزدوج نفسِه (القاعدةُ وعقدُها). */
    public static function activePenaltyRule($gate, $supplierId, $kind, $date)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('r' => 'supplier_penalty_rules'),
                      'enrich' => array('h' => 'supplier_contracts')),
                "SELECT r.* FROM supplier_penalty_rules r
                   LEFT JOIN supplier_contracts h ON h.id = r.contract_id
                  WHERE {TENANT_SCOPE} AND h.supplier_id = ? AND COALESCE(h.is_deleted,0)=0
                    AND (h.start_date IS NULL OR h.start_date <= ?)
                    AND (h.end_date IS NULL OR h.end_date >= ?)
                    AND r.kind = ? AND r.state = 'active' AND COALESCE(r.is_deleted,0)=0
                    AND r.valid_from <= ? AND (r.valid_to IS NULL OR r.valid_to >= ?)
                  ORDER BY r.valid_from DESC, r.id DESC LIMIT 1",
                array((int) $supplierId, (string) $date, (string) $date,
                      (string) $kind, (string) $date, (string) $date));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function chargeRulesOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('r' => 'supplier_charge_rules')),
                "SELECT r.* FROM supplier_charge_rules r
                  WHERE {TENANT_SCOPE} AND r.contract_id = ? AND COALESCE(r.is_deleted,0)=0
                  ORDER BY r.charge_type, r.valid_from DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function penaltyRulesOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('r' => 'supplier_penalty_rules')),
                "SELECT r.* FROM supplier_penalty_rules r
                  WHERE {TENANT_SCOPE} AND r.contract_id = ? AND COALESCE(r.is_deleted,0)=0
                  ORDER BY r.kind, r.valid_from DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    private static function contractOf($gate, $contractId)
    {
        try { return $gate->selectOne('supplier_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
