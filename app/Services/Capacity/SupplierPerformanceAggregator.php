<?php
/**
 * app/Services/Capacity/SupplierPerformanceAggregator.php — تجميعُ الأداء (CAP-22/25)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §9/§9.1: «كلُّ ساعةٍ معتمدةٍ نفَّذتها معدةُ المورد — أساسيةً أو احتياطيةً
 * مفعَّلة — تُجمَّع في سجل أدائه … **والأساسيةُ والاحتياطيةُ المفعَّلةُ تُجمَّعان في
 * تنفيذ الحصة · والتغطيةُ المعطاةُ في بندٍ مستقل · والمستلَمةُ لا تُنسب إليه**».
 *
 * المؤشراتُ التسعة (§9.1) تُحسب من **الدفتر** لا من تجميعٍ تقريري — والتقريرُ
 * (نظيرُ supplier_period_performance في §16) **مشتقٌّ يُعاد بناؤه ولا يُحرَّر**
 * (CAP-25) · ⑦ لا تدخل ④ ولا ترفع ⑥ — «وإلا صار كلُّ موردٍ منفِّذًا مئةً بالمئة».
 *
 * C12: تعديلُ الحصة بعد إقفال الشهر → **423** — الانحرافُ التاريخيُّ محفوظ.
 * C19: إدراجُ ساعات التغطية في تنفيذ الحصة → **403**.
 */

namespace App\Services\Capacity;

class SupplierPerformanceAggregator
{
    /**
     * المؤشراتُ التسعة لبند حصةِ موردٍ في فترة — من الدفتر والإعكاسات حصرًا.
     * @return array{planned:float,executed_primary:float,executed_standby:float,
     *               executed_share_total:float,share_gap:float,share_execution_pct:float,
     *               exceptional_coverage_given:float,coverage_received:float,remaining_contract_qty:float,
     *               measure_code:?string}
     */
    public static function nineIndicators($gate, $supplierContractLineId, $period)
    {
        $lineId = (int) $supplierContractLineId;
        $lines = $gate->scopedQuery(array(
                'scope'  => array('l' => 'supplier_contract_lines'),
                'enrich' => array('cc' => 'contract_commitments')),
            "SELECT l.*, l.primary_units_committed * COALESCE(cc.qty_per_primary_unit_month, 0) AS planned_month,
                    cc.measure_code AS obl_measure
               FROM supplier_contract_lines l
               LEFT JOIN contract_commitments cc ON cc.id = l.contract_obligation_ref
              WHERE {TENANT_SCOPE} AND l.id = ?",
            array($lineId));
        if (!$lines) {
            throw new \RuntimeException('بندُ الحصة غيرُ موجودٍ في نطاقك');
        }
        $line = $lines[0];
        $planned = round((float) $line['planned_month'], 2); // ① مشتقةٌ لا تُدخل (§5-⑦)

        $sum = function ($cond, $params) use ($gate, $lineId, $period) {
            $rows = $gate->scopedQuery(array('scope' => array('d' => 'capacity_consumption_ledger')),
                "SELECT COALESCE(SUM(CASE WHEN d.effect_type = 'reversal' THEN -d.qty ELSE d.qty END), 0) q
                   FROM capacity_consumption_ledger d
                  WHERE {TENANT_SCOPE} AND d.period = ? {$cond}",
                array_merge(array((string) $period), $params));
            return $rows ? round((float) $rows[0]['q'], 2) : 0.0;
        };
        // ②/③ تنفيذُ الحصة: أسطرُ supplier_share على البند بدوريها — والعكسُ محسوم
        $execPrimary = $sum("AND d.supplier_contract_line_id = ? AND d.effect_type IN ('supplier_share','reversal')
                             AND d.role_snapshot = 'primary'", array($lineId));
        $execStandby = $sum("AND d.supplier_contract_line_id = ? AND d.effect_type IN ('supplier_share','reversal')
                             AND d.role_snapshot = 'standby'", array($lineId));
        // ⑦ التغطيةُ المعطاة — بندٌ مستقلٌّ لا يدخل ④ (exceptional_coverage باسم البند)
        $given = $sum("AND d.supplier_contract_line_id = ? AND d.effect_type = 'exceptional_coverage'", array($lineId));
        // ⑧ ما غطّاه الآخرون عنه — أسطرُ تغطيةٍ على تغطياتٍ متعطلُها هذا المورد
        $received = 0.0;
        $sup = $gate->scopedQuery(array('scope' => array('h' => 'supplier_contracts')),
            "SELECT h.supplier_id FROM supplier_contracts h WHERE {TENANT_SCOPE} AND h.id = ?",
            array((int) $line['contract_id']));
        if ($sup && $sup[0]['supplier_id'] !== null) {
            $rows = $gate->scopedQuery(array(
                    'scope' => array('d' => 'capacity_consumption_ledger', 'v' => 'substitute_coverages')),
                "SELECT COALESCE(SUM(CASE WHEN d.effect_type = 'reversal' THEN -d.qty ELSE d.qty END), 0) q
                   FROM capacity_consumption_ledger d
                   JOIN substitute_coverages v ON v.cov_id = d.coverage_id
                  WHERE {TENANT_SCOPE} AND d.period = ? AND d.effect_type = 'exceptional_coverage'
                    AND v.failed_supplier_id = ?",
                array((string) $period, (int) $sup[0]['supplier_id']));
            $received = $rows ? round((float) $rows[0]['q'], 2) : 0.0;
        }

        $total = round($execPrimary + $execStandby, 2);          // ④ = ② + ③ — و⑦ لا تدخل
        $gap = round(max(0, $planned - $total), 2);              // ⑤
        $pct = $planned > 0 ? round($total / $planned * 100, 1) : 0.0; // ⑥ — و⑦ لا ترفعها
        $totalContract = self::lifetimeConsumed($gate, $lineId);
        return array(
            'planned'                    => $planned,
            'executed_primary'           => $execPrimary,
            'executed_standby'           => $execStandby,
            'executed_share_total'       => $total,
            'share_gap'                  => $gap,
            'share_execution_pct'        => $pct,
            'exceptional_coverage_given' => $given,
            'coverage_received'          => $received,
            'remaining_contract_qty'     => $totalContract,
            'measure_code'               => $line['obl_measure'],
        );
    }

    /**
     * C19 (CAP-22): محاولةُ إدراج ساعات التغطية الاستثنائية في تنفيذ الحصة → 403.
     * الحارسُ الصريح لكل مسارٍ يجمّع — و«⑦ لا تدخل ④ ولا ترفع ⑥».
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function assertNotCountingCoverage($effectType, $intoShareExecution)
    {
        if ((string) $effectType === 'exceptional_coverage' && $intoShareExecution) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'ساعاتُ التغطية الاستثنائية بندٌ مستقلٌّ — لا تدخل تنفيذَ الحصة ولا ترفع نسبتَه (C19: وإلا صار كلُّ موردٍ منفِّذًا مئةً بالمئة)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => '');
    }

    /**
     * C12 (CAP-25): لا تُعدَّل حصةٌ بعد إقفال شهرٍ استُهلك فيه منها → 423.
     * الإقفالُ من n12 (monthly_performance.state=closed) — والانحرافُ التاريخيُّ
     * محفوظٌ: التعديلُ المشروعُ فترةٌ جديدةٌ بسريان.
     * @return array{ok:bool,code:int,reason:string,closed_periods:array}
     */
    public static function assertShareEditable($gate, $supplierContractLineId)
    {
        $rows = $gate->scopedQuery(array(
                'scope' => array('d' => 'capacity_consumption_ledger', 'mp' => 'monthly_performance')),
            "SELECT DISTINCT d.period FROM capacity_consumption_ledger d
               JOIN monthly_performance mp ON mp.period = d.period AND mp.state = 'closed'
              WHERE {TENANT_SCOPE} AND d.supplier_contract_line_id = ?",
            array((int) $supplierContractLineId));
        if ($rows) {
            $periods = array_map(function ($r) { return $r['period']; }, $rows);
            return array('ok' => false, 'code' => 423, 'closed_periods' => $periods,
                'reason' => 'الحصةُ استُهلك منها في شهرٍ مقفل (' . implode(' · ', $periods)
                          . ') — لا تُعدَّل لتطابق المنفَّذ؛ الانحرافُ التاريخيُّ محفوظٌ والتعديلُ فترةٌ جديدةٌ بسريان (C12)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => '', 'closed_periods' => array());
    }

    /** المستهلَكُ التراكمي للبند من الدفتر — للمتبقي التعاقدي ⑨. */
    private static function lifetimeConsumed($gate, $lineId)
    {
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'capacity_consumption_ledger')),
            "SELECT COALESCE(SUM(CASE WHEN d.effect_type = 'reversal' THEN -d.qty ELSE d.qty END), 0) q
               FROM capacity_consumption_ledger d
              WHERE {TENANT_SCOPE} AND d.supplier_contract_line_id = ?
                AND d.effect_type IN ('supplier_share','reversal')",
            array((int) $lineId));
        return $rows ? round((float) $rows[0]['q'], 2) : 0.0;
    }
}
